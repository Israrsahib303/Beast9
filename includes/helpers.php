<?php
// --- 1. SECURITY HEADERS & SESSION SETTINGS (MUST BE FIRST) ---
// Session start hone se pehle settings load karni hain taake error na aaye
if (file_exists(__DIR__ . '/security_headers.php')) {
    require_once __DIR__ . '/security_headers.php';
}

// --- 2. START SESSION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 3. DATABASE CONNECTION ---
require_once __DIR__ . '/db.php';

// --- 4. SECURITY: VPN CHECK (Requires DB) ---
// Database load hone ke baad hi check karega taake 500 Error na aaye
if (file_exists(__DIR__ . '/vpn_check.php')) {
    require_once __DIR__ . '/vpn_check.php';
}

// --- 5. PHP MAILER ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

// --- 6. GLOBAL SETTINGS ---
$GLOBALS['settings'] = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $GLOBALS['settings'][$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Silent fail if settings table missing during install
}

// ==========================================
//      NEW: CURRENCY CONVERTER SYSTEM
// ==========================================

function getCurrencyList() {
    // Base Currency is always PKR (Rate = 1)
    return [
        'PKR' => ['rate' => 1,        'symbol' => 'Rs',  'flag' => '🇵🇰', 'name' => 'Pakistani Rupee'],
        'USD' => ['rate' => 0.0036,   'symbol' => '$',   'flag' => '🇺🇸', 'name' => 'US Dollar'],
        'INR' => ['rate' => 0.30,     'symbol' => '₹',   'flag' => '🇮🇳', 'name' => 'Indian Rupee'],
        'GBP' => ['rate' => 0.0028,   'symbol' => '£',   'flag' => '🇬🇧', 'name' => 'British Pound'],
        'EUR' => ['rate' => 0.0033,   'symbol' => '€',   'flag' => '🇪🇺', 'name' => 'Euro'],
        'SAR' => ['rate' => 0.0135,   'symbol' => '﷼',   'flag' => '🇸🇦', 'name' => 'Saudi Riyal'],
        'AED' => ['rate' => 0.0132,   'symbol' => 'د.إ', 'flag' => '🇦🇪', 'name' => 'UAE Dirham'],
        'TRY' => ['rate' => 0.11,     'symbol' => '₺',   'flag' => '🇹🇷', 'name' => 'Turkish Lira'],
    ];
}

function getSelectedCurrency() {
    $list = getCurrencyList();
    $code = $_COOKIE['site_currency'] ?? 'PKR'; // Default PKR
    
    // Agar cookie mein koi invalid code hai, toh wapas PKR kar do
    return isset($list[$code]) ? $list[$code] : $list['PKR'];
}

// Helper to get just the rate (for JS)
function getCurrencyRate($code) {
    $list = getCurrencyList();
    return isset($list[$code]) ? $list[$code]['rate'] : 1;
}

// --- UPDATED formatCurrency Function (Auto Convert) ---
function formatCurrency($amount, $symbol = null) {
    // Get current selected currency
    $curr = getSelectedCurrency();
    
    // Convert Amount
    $converted_amount = (float)$amount * $curr['rate'];
    
    // Use provided symbol OR currency symbol
    $final_symbol = $symbol ?? $curr['symbol'];
    
    return $final_symbol . ' ' . number_format($converted_amount, 2);
}

// ==========================================
//      HELPER FUNCTIONS
// ==========================================

function sendEmail($to_email, $to_name, $subject, $body) {
    global $settings;
    
    if (empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
        return ['success' => false, 'message' => 'SMTP settings are not configured.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass'];
        $mail->SMTPSecure = $settings['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$settings['smtp_port'] ?? 587;

        $mail->setFrom($settings['smtp_from_email'] ?? $settings['smtp_user'], $settings['smtp_from_name'] ?? $settings['site_name']);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo];
    }
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']); 
        return true;
    }
    return false;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return (isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect('../login.php?error=auth');
    }
}

function formatDate($timestamp) {
    return date('d M, Y h:i A', strtotime($timestamp));
}

function generateCode($prefix = 'SH-') {
    return $prefix . strtoupper(bin2hex(random_bytes(4)));
}

function getUserBalance($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? (float)$user['balance'] : 0.0;
}

function generateWhatsAppLink($order_data, $product_name) {
    global $settings;
    $admin_phone = $settings['whatsapp_number'] ?? '';
    
    $message = "🎉 *Order Receipt - SubHub* 🎉\n\n";
    $message .= "Order ID: *#" . $order_data['code'] . "*\n";
    $message .= "Service: *" . $product_name . "*\n";
    $message .= "Duration: *" . $order_data['duration_months'] . " Month(s)*\n";
    $message .= "Total Paid: *" . formatCurrency($order_data['total_price']) . "*\n";
    $message .= "Starts: *" . formatDate($order_data['start_at']) . "*\n";
    $message .= "Ends: *" . formatDate($order_data['end_at']) . "*\n\n";
    $message .= "Status: *Active*";

    $encoded_message = urlencode($message);
    return "https://wa.me/{$admin_phone}?text={$encoded_message}";
}

function formatSmmAvgTime($minutesStr) {
    if (empty($minutesStr) || !is_numeric($minutesStr)) {
        return sanitize($minutesStr ?? 'N/A');
    }

    $totalMinutes = (int)$minutesStr;
    if ($totalMinutes == 0) {
        return 'N/A';
    }

    $days = floor($totalMinutes / 1440);
    $remainingMinutes = $totalMinutes % 1440;
    $hours = floor($remainingMinutes / 60);
    $minutes = $remainingMinutes % 60;

    $result = '';
    if ($days > 0) $result .= $days . 'd ';
    if ($hours > 0) $result .= $hours . 'h ';
    if ($minutes > 0 || ($days == 0 && $hours == 0)) $result .= $minutes . 'm';

    return trim($result);
}
// --- MAINTENANCE MODE CHECK ---
// 1. Agar Maintenance Mode ON hai
if (isset($GLOBALS['settings']['maintenance_mode']) && $GLOBALS['settings']['maintenance_mode'] == '1') {
    
    $currentPage = basename($_SERVER['PHP_SELF']);
    $currentDir = dirname($_SERVER['PHP_SELF']);
    
    // 2. Admin Panel, Login Page, aur Maintenance Page ko chhor kar sabko block karo
    if (strpos($currentDir, '/panel') === false && 
        $currentPage != 'login.php' && 
        $currentPage != 'maintenance.php' && 
        $currentPage != 'secret_entry.php' && // Ghost entry allowed
        $currentPage != 'verify_login.php' && // OTP Allowed
        $currentPage != 'admin_login.php') {
            
        // Agar user ADMIN nahi hai, to Maintenance Page par bhejo
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
            header("Location: " . SITE_URL . "/maintenance.php");
            exit;
        }
    }
}
// --- ADMIN LOGGER FUNCTION ---
function logActivity($action, $desc) {
    global $db;
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // Sirf agar admin logged in hai tab hi log karo
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $admin_id = $_SESSION['user_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        try {
            $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_id, $action, $desc, $ip]);
        } catch (Exception $e) { /* Silent Fail */ }
    }
}
// --- STAFF PERMISSION CHECKER ---
function hasPermission($key) {
    global $db;
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 1. If Super Admin (ID 1), allow everything
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) return true;

    // 2. Fetch User Permissions from DB
    if (isset($_SESSION['user_id'])) {
        // Optimization: You can store permissions in SESSION during login to avoid DB calls
        $stmt = $db->prepare("SELECT permissions, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // If Role is Admin, allow (Optional: Or enforce strict perms for admins too)
        if ($user && $user['role'] === 'admin') return true; 

        // Check Specific Permission
        if ($user && !empty($user['permissions'])) {
            $perms = json_decode($user['permissions'], true);
            if (is_array($perms) && in_array($key, $perms)) {
                return true;
            }
        }
    }
    return false;
}
?>