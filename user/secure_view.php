<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
session_start();

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];

// 2. Check Database for Payment
$stmt = $db->prepare("SELECT voice_access FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['voice_access'] != 1) {
    die("<h3 style='color:red;text-align:center;margin-top:20%'>Access Denied! Please purchase the tool first.</h3>");
}

// 3. THE HIDDEN LINK
// User ko ye URL source code mein nahi dikhega kyunki ye PHP variable mein hai
$secret_url = "https://gemini.google.com/share/9cc17a26ea7c";

// 4. Load Content
// Hum redirect use kar rahe hain taaki iframe ke andar wo page khule
header("Location: " . $secret_url);
exit;
?>