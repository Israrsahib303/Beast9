<?php
// fix_database.php
include 'includes/db.php';

echo "<h1>🚀 Fixing Database Structure...</h1>";

try {
    // 1. Fix 'navigation' table missing
    $db->exec("CREATE TABLE IF NOT EXISTS `navigation` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `label` varchar(100) NOT NULL,
      `link` varchar(255) NOT NULL,
      `icon` varchar(255) DEFAULT NULL,
      `placement` enum('main','more') NOT NULL DEFAULT 'main',
      `sort_order` int(11) NOT NULL DEFAULT 0,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `parent_id` int(11) NOT NULL DEFAULT 0,
      `icon_color` varchar(20) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "<p>✅ Table 'navigation' checked/created.</p>";

    // 2. Fix 'products' missing 'price' column
    try {
        $db->exec("ALTER TABLE `products` ADD COLUMN `price` decimal(10,2) NOT NULL DEFAULT 0.00");
        echo "<p>✅ Column 'price' added to products.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ Column 'price' likely exists.</p>"; }

    // 3. Fix 'users' missing 'role' column
    try {
        $db->exec("ALTER TABLE `users` ADD COLUMN `role` enum('user','admin','staff') NOT NULL DEFAULT 'user'");
        echo "<p>✅ Column 'role' added to users.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ Column 'role' likely exists.</p>"; }

    // 4. Fix missing settings
    $settings_to_add = [
        'theme_hover' => '#000000',
        'card_color' => '#ffffff',
        'text_color' => '#000000',
        'text_muted_color' => '#666666',
        'smtp_secure' => 'ssl',
        'smtp_from_name' => 'Support'
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach($settings_to_add as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    echo "<p>✅ Missing settings inserted.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
echo "<h3>✨ Database Fix Complete. Delete this file now.</h3>";
?>