<?php
// --- SMART CURRENCY SYNC (EXACT MATCH LOGIC) ---
// Yeh script Provider ka chupa hua USD rate dhoond kar system update karega.

// ==========================================================
// 👇 SIRF YEH 2 CHEEZEIN EDIT KAREIN
// ==========================================================

// 1. Provider ki koi bhi ek Service ID likhein (Jo Active ho)
$reference_service_id = 13893; // Example: Provider ki kisi service ki ID

// 2. Us Service ka Provider ki Website par kya Rate hai (PKR mein)?
// Example: Agar upar wali ID ka rate provider ke panel par 100 Rs hai, to yahan 100 likhein.
$reference_pkr_price = 100.00; 

// ==========================================================
// 🚀 ISKE NEECHAY KUCH CHANGE NA KAREIN
// ==========================================================

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

function write_log($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    @file_put_contents($log_file, $entry, FILE_APPEND);
    echo $msg . "<br>";
}

write_log("--- SMART SYNC STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    chdir($base_path . '/includes');
    require_once 'config.php';
    require_once 'db.php';
    require_once 'smm_api.class.php';

    // 1. Active Provider dhoondein
    $stmt = $db->query("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provider) {
        throw new Exception("No active provider found.");
    }

    // 2. API se Services fetch karein
    $api = new SmmApi($provider['api_url'], $provider['api_key']);
    $response = $api->getServices();

    if (!$response['success']) {
        throw new Exception("API Error: " . ($response['error'] ?? 'Unknown'));
    }

    // 3. Reference Service dhoondein
    $found_service = null;
    foreach ($response['services'] as $s) {
        if ($s['service'] == $reference_service_id) {
            $found_service = $s;
            break;
        }
    }

    if (!$found_service) {
        throw new Exception("Reference Service ID ($reference_service_id) not found in Provider API response.");
    }

    // 4. Exact Rate Calculate Karein
    // Formula: Target Price (PKR) / API Price (USD) = Perfect Exchange Rate
    
    $api_rate_usd = (float)$found_service['rate']; // Provider ka rate (USD mein)
    
    if ($api_rate_usd <= 0) {
        throw new Exception("API Rate is 0 or invalid.");
    }

    $perfect_rate = $reference_pkr_price / $api_rate_usd;
    $perfect_rate = round($perfect_rate, 2); // Round off (e.g. 290.02)

    // Log Details
    write_log("Reference Service ID: $reference_service_id");
    write_log("Provider API Price: $$api_rate_usd");
    write_log("Target PKR Price: Rs $reference_pkr_price");
    write_log("---------------------------------");
    write_log("CALCULATED RATE: 1 USD = $perfect_rate PKR");

    // 5. Settings Update Karein
    $update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'currency_conversion_rate'");
    if ($update->execute([$perfect_rate])) {
        write_log("✅ SUCCESS! System Rate updated to $perfect_rate.");
        if (!$is_cli) echo "<h3>✅ Rate Fixed: 1 USD = $perfect_rate PKR</h3>";
    } else {
        throw new Exception("Failed to update database settings.");
    }

} catch (Exception $e) {
    write_log("❌ ERROR: " . $e->getMessage());
    if (!$is_cli) echo "Error: " . $e->getMessage();
}

write_log("--- SYNC FINISHED ---");
?>