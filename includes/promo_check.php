<?php
// includes/promo_check.php - Validates Code & Calculates % Bonus

// 1. Error Reporting OFF (Taake HTML errors JSON ko kharab na karein)
error_reporting(0);
ini_set('display_errors', 0);

// 2. Header Set
header('Content-Type: application/json');

// 3. Output Buffering (Pichla kachra saaf karne ke liye)
ob_start();

try {
    // 4. Include Files (Safe Path)
    if (!file_exists(__DIR__ . '/helpers.php')) {
        throw new Exception("System Error: Helper file missing.");
    }
    require_once __DIR__ . '/helpers.php'; 
    
    // Buffer Clean (Ab tak jo bhi output hua use mita do)
    ob_clean(); 

    // 5. Auth Check
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        echo json_encode(['valid' => false, 'error' => 'Please login first']);
        exit;
    }

    // 6. Input Handling
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $amount = floatval($_POST['amount'] ?? 0);

    if (empty($code)) {
        echo json_encode(['valid' => false, 'error' => 'Enter promo code']);
        exit;
    }
    if ($amount <= 0) {
        echo json_encode(['valid' => false, 'error' => 'Enter valid amount']);
        exit;
    }

    // 7. DB Check
    if (!isset($db)) {
        throw new Exception("Database not connected.");
    }

    $stmt = $db->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    // 8. Validations
    if (!$promo) {
        echo json_encode(['valid' => false, 'error' => 'Invalid Promo Code']);
        exit;
    }

    if ($promo['max_uses'] > 0 && $promo['current_uses'] >= $promo['max_uses']) {
        echo json_encode(['valid' => false, 'error' => 'Usage Limit Reached']);
        exit;
    }

    if ($amount < $promo['min_deposit']) {
        // Helper function check
        $min_text = function_exists('formatCurrency') ? formatCurrency($promo['min_deposit']) : $promo['min_deposit'];
        echo json_encode(['valid' => false, 'error' => "Min deposit $min_text required"]);
        exit;
    }

    // 9. Calculation
    $bonus_amount = ($amount * $promo['deposit_bonus']) / 100;
    $total_get = $amount + $bonus_amount;

    // 10. Final Response
    echo json_encode([
        'valid' => true,
        'bonus_amount' => number_format($bonus_amount, 2),
        'total_amount' => number_format($total_get, 2),
        'percent' => $promo['deposit_bonus']
    ]);

} catch (Exception $e) {
    // Agar koi bhi error aaye to use JSON mein convert karo
    ob_clean();
    echo json_encode(['valid' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

exit;
?>