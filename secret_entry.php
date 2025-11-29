<?php
// secret_entry.php - GHOST MODE ENTRY POINT
// Is file ko root folder (public_html) mein rakhein

// 1. Helpers include karein (Session start aur Config load karne ke liye)
require_once __DIR__ . '/includes/helpers.php';

// --- SECURITY KEY ---
$secret_key = "IsrarBoss786"; // Is key ko apne hisaab se change kar lein
// --------------------

// 2. Key Check Logic
if (isset($_GET['key']) && $_GET['key'] === $secret_key) {
    
    // Valid Key: Ghost Mode Activate
    $_SESSION['ghost_access'] = true;
    
    // IMPORTANT: Session ko foran save karein taake redirect ke baad lost na ho
    session_write_close(); 
    
    // Login Page par bhejein (Welcome message ke sath)
    redirect(SITE_URL . "/login.php?msg=welcome_boss");
    exit;

} else {
    // Invalid Key: Fake 404 Error dikhayein (Security)
    header("HTTP/1.0 404 Not Found");
    http_response_code(404);
    
    // Realistic 404 Page HTML (Hacker ko lagega page hai hi nahi)
    echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
    <html><head>
    <title>404 Not Found</title>
    </head><body>
    <h1>Not Found</h1>
    <p>The requested URL was not found on this server.</p>
    <p>Additionally, a 404 Not Found error was encountered while trying to use an ErrorDocument to handle the request.</p>
    </body></html>';
    
    exit;
}
?>