<?php
// Yeh file AJAX request handle karti hai
include '_auth_check.php'; // Admin login check karega
require_once __DIR__ . '/../includes/smm_api.class.php';
require_once __DIR__ . '/../includes/wallet.class.php';

header('Content-Type: application/json');

$order_id = (int)($_GET['id'] ?? 0);
if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID.']);
    exit;
}

try {
    // 1. Order aur Provider ki details fetch karein
    $stmt_order = $db->prepare("
        SELECT o.*, s.service_id as provider_service_id, p.api_url, p.api_key
        FROM smm_orders o
        JOIN smm_services s ON o.service_id = s.id
        JOIN smm_providers p ON s.provider_id = p.id
        WHERE o.id = ?
    ");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        throw new Exception('Order or provider details not found.');
    }
    
    if (empty($order['provider_order_id'])) {
         throw new Exception('This order has not been sent to the provider yet.');
    }

    // 2. API class ko call karein
    $api = new SmmApi($order['api_url'], $order['api_key']);
    $result = $api->getOrderStatus($order['provider_order_id']);

    if (!$result['success']) {
        throw new Exception('API Error: ' . ($result['error'] ?? 'Unknown error'));
    }

    // 3. Wahi logic jo cron job mein hai (smm_status_sync.php se)
    $status_data = $result['status_data'];
    $new_status = strtolower($status_data['status']);
    $start_count = (int)($status_data['start_count'] ?? 0);
    $remains = (int)($status_data['remains'] ?? 0);
    $final_message = "Status: $new_status | Start: $start_count | Remains: $remains";
    
    // Database ko update karein
    $stmt_update = $db->prepare("UPDATE smm_orders SET status = ?, start_count = ?, remains = ? WHERE id = ?");
    $stmt_update->execute([$new_status, $start_count, $remains, $order_id]);
    
    // Agar partial ya cancel hua hai toh refund karein
    $wallet = new Wallet($db);
    
    if ($new_status == 'partial') {
        $charge = (float)$order['charge'];
        $quantity = (float)($order['quantity'] ?? 0);
        $refund_amount = 0;
        if ($quantity > 0 && $remains > 0) {
            $per_item_cost = $charge / $quantity;
            $refund_amount = $per_item_cost * $remains;
        }

        $db->beginTransaction();
        if ($refund_amount > 0) {
            $wallet->addCredit($order['user_id'], $refund_amount, 'admin_adjust', $order_id, "SMM Order Partial Refund #" . $order['provider_order_id']);
        }
        $db->commit();
        $final_message .= " | Refunded: " . formatCurrency($refund_amount);
        
    } elseif ($new_status == 'canceled' || $new_status == 'refunded') {
        $charge = (float)$order['charge'];
        $db->beginTransaction();
        $wallet->addCredit($order['user_id'], $charge, 'admin_adjust', $order_id, "SMM Order Cancelled Refund #" . $order['provider_order_id']);
        $db->commit();
        $final_message .= " | Refunded: " . formatCurrency($charge);
    }
    
    echo json_encode(['success' => true, 'message' => 'Live status fetched successfully! ' . $final_message]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;