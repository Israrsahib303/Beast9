<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>🛠️ Service Updates Table Fixer</h1>";

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Drop Old Table
    $pdo->exec("DROP TABLE IF EXISTS service_updates");
    echo "<div style='color:orange'>🗑️ Old table deleted (if existed).</div>";

    // 2. Create New Table
    $sql = "CREATE TABLE `service_updates` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `service_id` int(11) DEFAULT NULL,
      `service_name` varchar(255) NOT NULL,
      `category_name` varchar(255) NOT NULL,
      `rate` decimal(10,4) NOT NULL,
      `type` enum('new','removed','enabled','price_increase','price_decrease') NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "<h2 style='color:green'>✅ Fixed Successfully!</h2>";
    echo "<p>Ab aap <strong>Sync Services</strong> dobara chalayen, error nahi aayega.</p>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>