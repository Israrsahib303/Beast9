<?php
// includes/security_headers.php

// 1. Clickjacking Protection (Site ko koi iframe mein nahi khol sakta)
header("X-Frame-Options: SAMEORIGIN");

// 2. XSS Protection (Browser ka filter on)
header("X-XSS-Protection: 1; mode=block");

// 3. MIME Type Sniffing Block
header("X-Content-Type-Options: nosniff");

// 4. Referrer Policy (User data leak na ho)
header("Referrer-Policy: strict-origin-when-cross-origin");

// 5. Secure Cookies (CRITICAL FIX: Sirf tab set karein agar Session start NAHI hua ho)
if (session_status() === PHP_SESSION_NONE) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
    } else {
        // Localhost ke liye sirf HTTPOnly
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
    }
}
?>