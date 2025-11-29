<?php
require_once __DIR__ . '/includes/helpers.php';

// 1. Agar User Logged In hai, to DB se token hatao (Security)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    try {
        // Token NULL kar do taake ye cookie dobara kaam na kare
        $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$user_id]);
    } catch (Exception $e) {
        // Ignore error
    }
}

// 2. Browser ki Cookie Delete karo
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/'); // Time peeche kar ke expire kar do
    unset($_COOKIE['remember_me']);
}

// 3. Session Destroy karo
session_unset();
session_destroy();

// 4. Login Page par bhejo
redirect(SITE_URL . '/login.php');
?>