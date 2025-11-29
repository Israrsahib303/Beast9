<?php
// Nayi File: panel/test_email_action.php
// Yeh file test email bhejegi

require_once __DIR__ . '/_auth_check.php'; // Admin check
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php'; // helpers.php ab PHPMailer ko load karta hai

header('Content-Type: application/json');

// Check karein ke SMTP settings mojood hain
if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
    echo json_encode(['success' => false, 'message' => 'SMTP Host or User is not set. Please save your settings first.']);
    exit;
}

// Admin ko hi test email bhej dein
$admin_email = $settings['smtp_user'];
$admin_name = $settings['smtp_from_name'] ?? 'Admin';
$subject = 'Beast2 Panel - SMTP Test Email';
$body = 'This is a test email from your Beast2 panel.<br><br>If you received this, your SMTP settings are working correctly!';

// Naya function call karein
$result = sendEmail($admin_email, $admin_name, $subject, $body);

echo json_encode($result);
exit;
?>