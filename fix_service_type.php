<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>🛠️ Service Type Fixer</h1>";

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Database se connect ho gaya: <strong>" . DB_NAME . "</strong><br><br>";
    
    // 1. Service Type Add Karein
    try {
        $pdo->exec("ALTER TABLE `smm_services` ADD COLUMN `service_type` VARCHAR(50) DEFAULT 'Default'");
        echo "<div style='color:green'>✔ Added: service_type</div>";
    } catch (Exception $e) {
        echo "<div style='color:orange'>ℹ service_type pehle se tha.</div>";
    }

    // 2. Dripfeed Add Karein (Aksar ye bhi missing hota hai)
    try {
        $pdo->exec("ALTER TABLE `smm_services` ADD COLUMN `dripfeed` TINYINT(1) NOT NULL DEFAULT 0");
        echo "<div style='color:green'>✔ Added: dripfeed</div>";
    } catch (Exception $e) {
        echo "<div style='color:orange'>ℹ dripfeed pehle se tha.</div>";
    }

    echo "<h3>✅ Masla Hal! Ab Cron check karein.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>