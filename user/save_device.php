<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $player_id = $_POST['player_id'] ?? '';
    
    if (!empty($player_id)) {
        $stmt = $db->prepare("UPDATE users SET one_signal_id = ? WHERE id = ?");
        $stmt->execute([$player_id, $_SESSION['user_id']]);
        echo "Device Saved";
    }
}
?>