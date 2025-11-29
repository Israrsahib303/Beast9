<?php
// --- CRON JOB: EXPIRE SUBSCRIPTIONS (SECURE & DEBUG MODE) ---

// 1. Session Start (Admin Check ke liye)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK (End Level)
// Allow only if running from CLI (Server) OR Logged in Admin (Ghost Mode)
$is_cli = (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR']));
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1 && isset($_SESSION['ghost_access']) && $_SESSION['ghost_access'] === true);

if (!$is_cli && !$is_admin) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: You are not authorized to run this cron manually.");
}

// 3. Error Reporting (Debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_error.log');

// 4. Time & Memory Limits
set_time_limit(120); 
ini_set('memory_limit', '256M');

// 5. Absolute Paths & Logging
$base_path = dirname(dirname(__DIR__)); // Root folder
$log_file = $base_path . '/assets/logs/subscriptions.log';

function writeLog($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        echo $msg . "<br>";
    }
}

writeLog("--- SUBSCRIPTION EXPIRY CHECK STARTED ---");

try {
    // Config Check
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    // Directory Change (Important for includes)
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 6. Expire Logic
    // Note: Hum 'active' aur 'completed' dono check kar rahe hain taake koi miss na ho
    // Sirf wahi orders jinka 'end_at' time guzar chuka hai
    $stmt = $db->prepare("
        UPDATE orders 
        SET status = 'expired'
        WHERE status IN ('active', 'completed') 
        AND end_at IS NOT NULL 
        AND end_at < NOW()
    ");
    
    $stmt->execute();
    $expired_count = $stmt->rowCount();

    if ($expired_count > 0) {
        writeLog("SUCCESS: Marked $expired_count subscriptions as 'expired'.");
    } else {
        writeLog("No expired subscriptions found.");
    }

} catch (Exception $e) {
    writeLog("CRITICAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

writeLog("--- FINISHED ---");
?>