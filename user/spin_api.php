<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/wallet.class.php';

header('Content-Type: application/json');
requireLogin();

$user_id = $_SESSION['user_id'];
$wallet = new Wallet($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid Request']);
    exit;
}

try {
    // --- 1. SERVER-SIDE TIME CHECK (ANTI-CHEAT) ---
    $stmt = $db->prepare("SELECT last_spin_time FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $last_spin = $user['last_spin_time'] ? strtotime($user['last_spin_time']) : 0;
    $current_time = time();
    $cooldown_config = $GLOBALS['settings']['daily_spin_cooldown_hours'] ?? 24;
    $cooldown_seconds = $cooldown_config * 60 * 60;

    // Agar time poora nahi hua
    if (($current_time - $last_spin) < $cooldown_seconds) {
        echo json_encode(['success' => false, 'error' => "Cooldown active! Please wait."]);
        exit;
    }

    // --- 2. FETCH PRIZES FROM ADMIN SETTINGS ---
    $prizes = $db->query("SELECT * FROM wheel_prizes WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($prizes)) {
        throw new Exception("No prizes configured in admin panel.");
    }

    // --- 3. WEIGHTED RANDOM LOGIC ---
    $total_prob = 0;
    foreach ($prizes as $p) {
        $total_prob += (int)$p['probability'];
    }

    $rand = mt_rand(1, $total_prob);
    $current_prob = 0;
    $won_prize = null;
    $won_index = 0;

    foreach ($prizes as $index => $prize) {
        $current_prob += (int)$prize['probability'];
        if ($rand <= $current_prob) {
            $won_prize = $prize;
            $won_index = $index; // JS needs this index to stop wheel
            break;
        }
    }

    if (!$won_prize) {
        // Fallback (Rare case)
        $won_prize = $prizes[0];
        $won_index = 0;
    }

    // --- 4. PROCESS REWARD (Database Transaction) ---
    $db->beginTransaction();

    $amount_won = (float)$won_prize['amount'];
    $is_win = $amount_won > 0;

    // Sirf tab wallet mein add karo agar amount > 0 ho
    if ($is_win) {
        $wallet->addCredit($user_id, $amount_won, 'spin_win', $won_prize['id'], "Won " . $won_prize['label']);
    }

    // Update Last Spin Time
    $db->prepare("UPDATE users SET last_spin_time = NOW() WHERE id = ?")->execute([$user_id]);

    // Log the Spin (Even if lost, log it)
    $stmt_log = $db->prepare("INSERT INTO wheel_spins_log (user_id, prize_id, amount_won, spin_time) VALUES (?, ?, ?, NOW())");
    $stmt_log->execute([$user_id, $won_prize['id'], $amount_won]);

    $db->commit();

    // --- 5. SEND RESPONSE ---
    echo json_encode([
        'success' => true,
        'prize_index' => $won_index,
        'amount' => $amount_won,
        'label' => $won_prize['label'],
        'message' => $is_win ? "You won " . formatCurrency($amount_won) : "Better luck next time! (" . $won_prize['label'] . ")"
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>