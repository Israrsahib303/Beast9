<?php
// --- CRON JOB: CURRENCY RATE SYNC (SECURE & DEBUG MODE) ---

// 1. Session Start (Admin Check ke liye)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK (End Level)
// Allow only if running from CLI (Server) OR Logged in Admin
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
set_time_limit(120); 
ini_set('memory_limit', '256M');

// 5. Paths
$base_path = dirname(dirname(__DIR__));
$log_dir = $base_path . '/assets/logs';
$log_file = $log_dir . '/currency_sync.log';

// --- FOLDER CHECK & CREATE ---
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function write_log($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    // Force write
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        // Fallback for debugging if permission issue
        echo "LOG ERROR: Could not write to $log_file. Msg: $msg <br>";
    }
}

write_log("--- CURRENCY SYNC STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    // Directory change
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 6. Fetch Live Rate (USD to PKR)
    $api_url = "https://api.exchangerate-api.com/v4/latest/USD";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['rates']['PKR'])) {
        $live_rate = (float)$data['rates']['PKR'];
        
        // --- SAFETY MARGIN (+3 PKR) ---
        $margin = 3.00; 
        $safe_rate = $live_rate + $margin; 

        // Check Old Rate
        $stmt_old = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency_conversion_rate'");
        $stmt_old->execute();
        $old_rate = $stmt_old->fetchColumn();

        // 7. Database Update
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_conversion_rate'");
        
        if ($stmt->execute([$safe_rate])) {
            write_log("SUCCESS: Updated USD Rate. Old: $old_rate | Live: $live_rate | New (Safe): $safe_rate");
            if (!$is_cli) echo "✅ Updated: 1 USD = $safe_rate PKR";
        } else {
            throw new Exception("Database update failed.");
        }
    } else {
        throw new Exception("Failed to fetch PKR rate from API response.");
    }

} catch (Exception $e) {
    write_log("CRITICAL ERROR: " . $e->getMessage());
    if (!$is_cli) echo "Error: " . $e->getMessage();
}

write_log("--- CURRENCY SYNC FINISHED ---");
?>