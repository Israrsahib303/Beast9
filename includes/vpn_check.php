<?php
// includes/vpn_check.php - SAFE & CLI COMPATIBLE

// --- 1. CRON JOB CHECK (FIX) ---
// Agar script CLI (Command Line) se chal rahi hai, to VPN check mat karo.
if (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR'])) {
    return;
}

// --- 2. DIRECT ACCESS BLOCK ---
if (!isset($db)) {
    return; 
}

// --- 3. FETCH SETTINGS ---
$vpn_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('vpn_check_enabled', 'vpn_api_key', 'vpn_block_msg')");
    while ($row = $stmt->fetch()) {
        $vpn_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    return;
}

// --- 4. CHECK IF ENABLED ---
if (isset($vpn_settings['vpn_check_enabled']) && $vpn_settings['vpn_check_enabled'] == '1') {
    
    // Get IP Safely
    $user_ip = $_SERVER['REMOTE_ADDR'];
    
    // Whitelist Localhost
    if ($user_ip == '127.0.0.1' || $user_ip == '::1') {
        return; 
    }

    // --- 5. API LOGIC ---
    $api_key = $vpn_settings['vpn_api_key'] ?? '';
    $api_url = "http://proxycheck.io/v2/" . $user_ip . "?vpn=1&asn=1";
    
    if (!empty($api_key)) {
        $api_url .= "&key=" . $api_key;
    }

    // Set Timeout (2 Seconds)
    $ctx = stream_context_create(['http'=> ['timeout' => 2]]); 

    try {
        $response = @file_get_contents($api_url, false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            
            if (isset($data['status']) && $data['status'] == 'ok') {
                if (isset($data[$user_ip]['proxy']) && $data[$user_ip]['proxy'] == 'yes') {
                    
                    // --- 🚫 BLOCK UI ---
                    $msg = $vpn_settings['vpn_block_msg'] ?? 'Access Denied via VPN.';
                    
                    // Clear any previous output
                    while (ob_get_level()) { ob_end_clean(); }
                    
                    die('
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Security Alert</title>
                        <style>
                            body { background: #0f172a; color: #fff; font-family: system-ui, sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; text-align: center; }
                            .card { background: #1e293b; padding: 40px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); max-width: 450px; border: 1px solid #334155; }
                            .icon { font-size: 5rem; margin-bottom: 20px; animation: pulse 2s infinite; }
                            h1 { color: #f87171; margin: 0 0 10px 0; font-size: 2rem; }
                            p { color: #94a3b8; font-size: 1.1rem; line-height: 1.6; margin-bottom: 30px; }
                            .btn { background: #ef4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 12px; font-weight: bold; transition: 0.3s; display: inline-block; }
                            .btn:hover { background: #dc2626; transform: translateY(-2px); }
                            @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }
                        </style>
                    </head>
                    <body>
                        <div class="card">
                            <div class="icon">🛡️</div>
                            <h1>Access Denied</h1>
                            <p>' . htmlspecialchars($msg) . '</p>
                            <a href="javascript:location.reload()" class="btn">I Have Disconnected VPN</a>
                        </div>
                    </body>
                    </html>
                    ');
                }
            }
        }
    } catch (Exception $e) {
        // API Fail = Allow User
    }
}
?>