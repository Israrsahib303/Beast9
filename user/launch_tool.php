<?php
require_once '../includes/db.php';
require_once '../includes/config.php';
session_start();

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    die("Please Login first.");
}

$user_id = $_SESSION['user_id'];

// 2. Check Payment Status Again (Double Security)
$stmt = $db->prepare("SELECT voice_access FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['voice_access'] != 1) {
    die("<h3>Access Denied! Please purchase the tool first.</h3>");
}

// 3. THE HIDDEN LINK (Yahan apna Google link daalo)
$secret_url = "https://gemini.google.com/share/9cc17a26ea7c";

// 4. Redirect (Javascript method taaki referrer hide ho sake)
?>
<!DOCTYPE html>
<html>
<head>
    <title>Launching Tool...</title>
    <style>
        body { background: #111; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; }
        .loader { border: 4px solid #333; border-top: 4px solid #4f46e5; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div style="text-align:center">
        <div class="loader" style="margin:0 auto 20px;"></div>
        <p>Connecting to secure server...</p>
    </div>
    <script>
        setTimeout(function() {
            // Referrer ko kill karke redirect karte hain
            var link = document.createElement('a');
            link.href = "<?php echo $secret_url; ?>";
            link.rel = 'noreferrer';
            document.body.appendChild(link);
            link.click();
        }, 1000);
    </script>
</body>
</html>