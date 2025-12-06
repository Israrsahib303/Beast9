<?php
// --- SMART ADAPTIVE CURRENCY SYNC (MANUAL RESPECT MODE) ---
// 1. Ye script Google ka rate check karti hai.
// 2. Agar aapne Admin Panel se rate manually change kiya hai, to ye usay detect kar legi.
// 3. Ye overwrite nahi karegi, balkay aapke naye rate ko "New Standard" maan legi.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check (CLI or Admin Only)
$is_cli = (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR']));
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);
if (!$is_cli && !$is_admin) { die("Access Denied"); }

// Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Paths
$base_path = dirname(dirname(__DIR__));
$log_file = $base_path . '/assets/logs/currency_sync.log';
$state_file = $base_path . '/assets/logs/currency_gap_state.json'; // Gap yaad rakhne ke liye file

// Ensure log directory exists
if (!is_dir(dirname($log_file))) {
    @mkdir(dirname($log_file), 0755, true);
}

function write_log($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    @file_put_contents($log_file, $entry, FILE_APPEND);
    if (php_sapi_name() !== 'cli') echo $msg . "<br>";
}

write_log("--- ADAPTIVE SYNC STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    chdir($base_path . '/includes');
    require_once 'config.php';
    require_once 'db.php';

    if (!$db) throw new Exception("Database connection failed.");

    // 1. Live Market Rate Fetch (Google/API)
    $api_url = "https://api.exchangerate-api.com/v4/latest/USD";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("API Error: " . curl_error($ch));
    }
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['rates']['PKR'])) {
        $live_market_rate = (float)$data['rates']['PKR'];
        
        // 2. Current Database Rate Fetch Karein
        $stmt_old = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency_conversion_rate'");
        $stmt_old->execute();
        $current_db_rate = (float)$stmt_old->fetchColumn();

        // 3. Last Remembered Gap Load Karein
        $stored_gap = 5.0; // Default startup gap (Agar file na ho)
        if (file_exists($state_file)) {
            $state = json_decode(file_get_contents($state_file), true);
            if (isset($state['gap'])) {
                $stored_gap = (float)$state['gap'];
            }
        }

        // 4. Logic: Kya User ne hath se rate change kiya hai?
        // Expected Rate wo hai jo script calculate kar rahi thi
        $expected_rate = $live_market_rate + $stored_gap;
        
        // Agar DB Rate aur Expected Rate mein 1 PKR se zyada farq hai
        // Iska matlab aapne manually change kiya hai.
        $diff = abs($current_db_rate - $expected_rate);

        if ($diff > 1.0) {
            
            // New Gap Calculate Karein (User Setting - Market Rate)
            $new_gap = $current_db_rate - $live_market_rate;
            
            // Save New Gap for future
            @file_put_contents($state_file, json_encode(['gap' => $new_gap]));
            
            write_log("✋ Manual Change Detected!");
            write_log("User Set Rate: $current_db_rate | Live Market: $live_market_rate");
            write_log("System learned new Gap: $new_gap (Will follow this from now)");
            
            if (!$is_cli) echo "<div style='color:green; border:1px solid green; padding:10px;'><h3>✅ Manual Rate Accepted!</h3>System has adjusted to your new rate ($current_db_rate). <br>It will not overwrite it.</div>";

        } else {
            
            // 5. Normal Update (User ne kuch nahi chera, Market Update karo)
            // Use existing gap to update rate
            $final_rate = $live_market_rate + $stored_gap;
            $final_rate = round($final_rate, 2);

            // Sirf tab update karein agar value change hui ho
            if ((string)$final_rate !== (string)$current_db_rate) {
                $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_conversion_rate'");
                if ($stmt->execute([$final_rate])) {
                    write_log("✅ Auto Update: Market moved to $live_market_rate. New Rate: $final_rate");
                    if (!$is_cli) echo "<h3>✅ Rate Auto-Synced: 1 USD = $final_rate PKR</h3>";
                }
            } else {
                write_log("ℹ️ Stable: Market $live_market_rate | Rate $final_rate (No Change Needed)");
                if (!$is_cli) echo "<h3>ℹ️ Rate is Stable ($final_rate)</h3>";
            }
        }

    } else {
        throw new Exception("Failed to get rate from API.");
    }

} catch (Exception $e) {
    write_log("❌ ERROR: " . $e->getMessage());
    if (!$is_cli) echo "Error: " . $e->getMessage();
}

write_log("--- SYNC FINISHED ---");
?>