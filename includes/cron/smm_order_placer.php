<?php
// --- CRON JOB: SMM ORDER PLACER (SECURE & DEBUG MODE) ---

// 1. Session Start (Admin Check ke liye)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK (End Level)
// Allow only if running from CLI (Server) OR Logged in Admin
$is_cli = (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR']));
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1 && isset($_SESSION['ghost_access']) && $_SESSION['ghost_access'] === true);

if (!$is_cli && !$is_admin) {
    // Agar hacker access karega to 403 milega
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: You are not authorized to run this cron manually.");
}

// 3. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_error.log');

// 4. Time & Memory
set_time_limit(120); 
ini_set('memory_limit', '256M');

// 5. Paths
$base_path = dirname(dirname(__DIR__));
$log_file = $base_path . '/assets/logs/smm_order_placer.log';

function write_log($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        echo $msg . "<br>";
    }
}

write_log("--- CRON STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';
    require_once 'smm_api.class.php';
    require_once 'wallet.class.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 6. Active Provider
    $stmt_provider = $db->prepare("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
    $stmt_provider->execute();
    $provider = $stmt_provider->fetch();

    if (!$provider) {
        write_log("No active SMM provider found. Stopping.");
        exit;
    }

    // 7. Orders Processing
    $stmt_orders = $db->prepare("
        SELECT o.*, s.service_id as provider_service_id 
        FROM smm_orders o
        JOIN smm_services s ON o.service_id = s.id
        WHERE o.status = 'pending'
        LIMIT 15
    ");
    $stmt_orders->execute();
    $pending_orders = $stmt_orders->fetchAll();

    if (empty($pending_orders)) {
        write_log("No pending orders found.");
    } else {
        write_log("Found " . count($pending_orders) . " orders.");
        
        $api = new SmmApi($provider['api_url'], $provider['api_key']);

        foreach ($pending_orders as $order) {
            $order_id = $order['id'];
            $provider_service_id = $order['provider_service_id'];
            $link = $order['link'];
            $quantity = $order['quantity'];
            $comments = $order['comments'] ?? '';

            write_log("Processing Order #$order_id");

            $result = $api->placeOrder($provider_service_id, $link, $quantity, null, $comments);

            if (isset($result['success']) && $result['success']) {
                $provider_order_id = $result['provider_order_id'];
                $db->prepare("UPDATE smm_orders SET status = 'in_progress', provider_order_id = ? WHERE id = ?")
                   ->execute([$provider_order_id, $order_id]);
                write_log("SUCCESS: Order #$order_id placed. ID: $provider_order_id");
            } else {
                $error_msg = $result['error'] ?? 'Unknown API Error';
                write_log("FAILED: Order #$order_id - $error_msg");

                // Refund
                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE smm_orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
                    $wallet = new Wallet($db);
                    $wallet->addCredit($order['user_id'], $order['charge'], 'refund', $order_id, "Order Failed: $error_msg");
                    $db->commit();
                    write_log("REFUNDED: Order #$order_id");
                } catch (Exception $e) {
                    $db->rollBack();
                    write_log("REFUND ERROR: " . $e->getMessage());
                }
            }
        }
    }

} catch (Exception $e) {
    write_log("CRITICAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

write_log("--- CRON FINISHED ---");
?>