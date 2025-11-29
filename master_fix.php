<?php
// master_fix.php - The Ultimate Database Repair Tool
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>🛠️ Master Database Repair Kit</h1>";
echo "Connected to: <strong>" . DB_NAME . "</strong><br><hr>";

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- LIST OF ALL REQUIRED COLUMNS ---
    $tasks = [
        // 1. Fix SMM SERVICES Table
        "ALTER TABLE `smm_services` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE `smm_services` ADD COLUMN `has_refill` TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE `smm_services` ADD COLUMN `has_cancel` TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE `smm_services` ADD COLUMN `avg_time` VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE `smm_services` ADD COLUMN `description` TEXT DEFAULT NULL",
        "ALTER TABLE `smm_services` ADD COLUMN `service_type` VARCHAR(50) DEFAULT 'Default'",
        "ALTER TABLE `smm_services` ADD COLUMN `dripfeed` TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE `smm_services` ADD COLUMN `last_synced_at` DATETIME DEFAULT NULL",
        
        // 2. Fix SMM CATEGORIES Table (Yehi Error ki Jadd hai)
        "ALTER TABLE `smm_categories` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE `smm_categories` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0"
    ];

    echo "<h3>🔍 Checking & Fixing Columns...</h3>";
    
    foreach ($tasks as $sql) {
        try {
            $pdo->exec($sql);
            echo "<div style='color:green'>✔ Fixed: $sql</div>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate column") !== false) {
                // Column already exists - Good!
                echo "<div style='color:gray'>ℹ OK (Already exists): " . substr($sql, 30, 20) . "...</div>";
            } else {
                // Real Error
                echo "<div style='color:red'>❌ Error: " . $e->getMessage() . "</div>";
            }
        }
    }

    echo "<hr><h2>🎉 SYSTEM REPAIRED SUCCESSFULLY!</h2>";
    echo "<p>Ab aapka <strong>Sync Cron Job</strong> bina kisi error ke chalega.</p>";
    echo "<a href='panel/smm_sync_action.php?job=smm_service_sync' target='_blank' style='background:blue; color:white; padding:10px; text-decoration:none; border-radius:5px;'>👉 Test Sync Now</a>";

} catch (Exception $e) {
    echo "<h1>🔥 FATAL ERROR</h1>" . $e->getMessage();
}
?>