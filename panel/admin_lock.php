<?php
// panel/admin_lock.php

// Note: Session is already started by helpers.php in _header.php

// Check Ghost Access
if (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true) {
    
    // Fake 404 Response
    header("HTTP/1.0 404 Not Found");
    http_response_code(404);
    
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