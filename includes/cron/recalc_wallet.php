<?php
// --- CRON JOB: RECALCULATE WALLET BALANCES (SECURE & DEBUG MODE) ---
// Yeh script 'wallet_ledger' table ka sum kar ke users ka balance fix karti hai

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

// 3. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_error.log');

// 4. Time & Memory Limits
set_time_limit(300); // 5 Minutes max (Heavy calculation)
ini_set('memory_limit', '512M');

// 5. Absolute Paths & Logging
$base_path = dirname(dirname(__DIR__)); // Root folder
$log_file = $base_path . '/assets/logs/wallet_recalc.log';

function writeLog($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        echo $msg . "<br>";
    }
}

writeLog("--- WALLET RECALCULATION STARTED ---");

try {
    // Config Check
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    // Directory Change
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 6. Recalculation Logic
    $db->beginTransaction();

    // Get all users (Admin included, kyunki unka bhi balance ho sakta hai)
    $stmt_users = $db->query("SELECT id, email, balance FROM users");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $fix_count = 0;
    $total_users = count($users);
    
    writeLog("Checking $total_users users...");

    foreach ($users as $user) {
        $user_id = $user['id'];
        $current_balance = (float)$user['balance'];

        // Ledger ka Sum nikalein
        $stmt_ledger = $db->prepare("
            SELECT 
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) 
            as real_balance
            FROM wallet_ledger 
            WHERE user_id = ?
        ");
        $stmt_ledger->execute([$user_id]);
        $real_balance = (float)$stmt_ledger->fetchColumn();

        // Agar farq hai (Mismatch > 0.01)
        if (abs($current_balance - $real_balance) > 0.01) {
            
            // Balance Fix Karein
            $update = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update->execute([$real_balance, $user_id]);
            
            writeLog("FIXED: User ID $user_id ({$user['email']}). Old: $current_balance, New: $real_balance");
            $fix_count++;
        }
    }

    $db->commit();
    
    if ($fix_count > 0) {
        writeLog("SUCCESS: Fixed balances for $fix_count users.");
    } else {
        writeLog("All wallet balances are correct.");
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    writeLog("CRITICAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

writeLog("--- FINISHED ---");
?>