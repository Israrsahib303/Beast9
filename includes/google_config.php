<?php
require_once __DIR__ . '/db.php';

// 1. Fetch Settings from DB
if (!isset($GLOBALS['settings'])) {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $GLOBALS['settings'][$row['setting_key']] = $row['setting_value'];
    }
}

// 2. Define Constants dynamically
define('GOOGLE_ENABLED', ($GLOBALS['settings']['google_login'] ?? '0') === '1');
define('GOOGLE_CLIENT_ID', $GLOBALS['settings']['google_client_id'] ?? '');
define('GOOGLE_CLIENT_SECRET', $GLOBALS['settings']['google_client_secret'] ?? '');

// Auto-detect Redirect URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Assumes file is in root. Adjust if needed.
define('GOOGLE_REDIRECT_URL', $protocol . "://" . $host . "/google_callback.php");

// 3. Generate Login URL
function getGoogleLoginUrl() {
    if (!GOOGLE_ENABLED || empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
        return false; // Button show na karein agar disabled hai
    }
    
    $params = [
        'response_type' => 'code',
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
        'access_type' => 'online'
    ];
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}
?>