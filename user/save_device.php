<?php
// user/save_device.php
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['player_id']) && isset($_SESSION['user_id'])) {
    
    $player_id = trim($_POST['player_id']);
    $user_id = $_SESSION['user_id'];

    if (!empty($player_id)) {
        try {
            // Ensure column exists in table
            $check = $db->query("SHOW COLUMNS FROM users LIKE 'one_signal_id'")->fetch();
            if(!$check) {
                $db->exec("ALTER TABLE users ADD COLUMN one_signal_id VARCHAR(255) DEFAULT NULL");
            }

            // Save to DB
            $stmt = $db->prepare("UPDATE users SET one_signal_id = ? WHERE id = ?");
            $stmt->execute([$player_id, $user_id]);
            echo "Saved";
        } catch (Exception $e) {
            echo "Error";
        }
    }
}
?>