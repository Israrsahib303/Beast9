<?php
// --- CRON JOB: SMM STATUS SYNC (SECURE & DEBUG MODE) ---

// 1. Session Start (Admin Check ke liye)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK (End Level)
$is_cli = (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR']));
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1 && isset($_SESSION['ghost_access']) && $_SESSION['ghost_access'] === true);

if (!$is_cli && !$is_admin) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: You are not authorized to run this cron manually.");
}

// 3. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_error.log');

// 4. Time & Memory
set_time_limit(180); // 3 Minutes max
ini_set('memory_limit', '256M');

// 5. Absolute Paths
$base_path = dirname(dirname(__DIR__));
$log_file = $base_path . '/assets/logs/smm_status_sync.log';

function writeLog($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        echo $msg . "<br>";
    }
}

writeLog("--- STATUS SYNC STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    // Directory Change
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';
    require_once 'helpers.php';
    require_once 'smm_api.class.php';
    require_once 'wallet.class.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 1. Provider Check
    $stmt_provider = $db->prepare("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
    $stmt_provider->execute();
    $provider = $stmt_provider->fetch();

    if (!$provider) {
        writeLog("No active SMM provider found. Stopping.");
        exit;
    }
    
    $api = new SmmApi($provider['api_url'], $provider['api_key']);
    $wallet = new Wallet($db);

    // 2. Fetch Orders (In Progress)
    $stmt_orders = $db->prepare("
        SELECT id, user_id, provider_order_id, quantity, charge 
        FROM smm_orders 
        WHERE status = 'in_progress'
        LIMIT 50
    ");
    $stmt_orders->execute();
    $in_progress_orders = $stmt_orders->fetchAll();

    if (empty($in_progress_orders)) {
        writeLog("No in_progress orders found.");
    } else {
        writeLog(count($in_progress_orders) . " orders found. Checking status...");
        
        foreach ($in_progress_orders as $order) {
            $order_id = $order['id'];
            $provider_order_id = $order['provider_order_id'];
            
            $result = $api->getOrderStatus($provider_order_id);

            if ($result['success']) {
                $status_data = $result['status_data'];
                $api_status = strtolower($status_data['status'] ?? '');
                
                $start_count = (int)($status_data['start_count'] ?? 0);
                $remains = (int)($status_data['remains'] ?? 0);

                // Default Update Query
                $sql = "UPDATE smm_orders SET start_count = ?, remains = ? WHERE id = ?";
                $params = [$start_count, $remains, $order_id];

                // --- STATUS LOGIC ---
                if ($api_status == 'completed') {
                    $sql = "UPDATE smm_orders SET status = 'completed', start_count = ?, remains = ? WHERE id = ?";
                    writeLog("SUCCESS: Order #$order_id COMPLETED.");
                
                } elseif ($api_status == 'partial' || $api_status == 'cancelled' || $api_status == 'canceled') {
                    
                    $final_status = ($api_status == 'partial') ? 'partial' : 'cancelled';
                    
                    $sql = "UPDATE smm_orders SET status = ?, start_count = ?, remains = ? WHERE id = ?";
                    $params = [$final_status, $start_count, $remains, $order_id];
                    
                    // Auto Refund Logic
                    if ($remains > 0) {
                        $charge_per_item = (float)$order['charge'] / (float)$order['quantity'];
                        $refund_amount = $charge_per_item * $remains;

                        $wallet->addCredit($order['user_id'], $refund_amount, 'refund', $order_id, "Auto Refund: $final_status (Rem: $remains)");
                        
                        writeLog("REFUND: Order #$order_id ($final_status). Refunded " . formatCurrency($refund_amount));
                    } else {
                         writeLog("STATUS UPDATE: Order #$order_id ($final_status). No refund (0 remains).");
                    }
                    
                } elseif (in_array($api_status, ['processing', 'in progress', 'pending', 'waiting'])) {
                    writeLog("UPDATE: Order #$order_id is still $api_status.");
                } else {
                    writeLog("INFO: Order #$order_id status: $api_status. No action.");
                }
                
                // Execute Update
                $db->prepare($sql)->execute($params);

            } else {
                $error_msg = $result['error'] ?? 'Unknown Error';
                writeLog("FAILED: Order #$order_id check failed. $error_msg");
            }
        }
    }

} catch (Exception $e) {
    writeLog("CRITICAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

writeLog("--- STATUS SYNC FINISHED ---");
?>