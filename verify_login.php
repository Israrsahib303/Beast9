<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/security_headers.php';

// Agar temp session nahi hai, to wapas login par bhejo
if (!isset($_SESSION['temp_user_id'])) {
    redirect('login.php');
}

$error = '';
$user_id = $_SESSION['temp_user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp_input = trim($_POST['otp']);
    
    // Check OTP
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->execute([$user_id, $otp_input]);
    $user = $stmt->fetch();

    if ($user) {
        // --- 4. OTP MATCHED! LOGIN SUCCESS ---
        
        // Setup Real Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        // Remove OTP to prevent reuse
        $db->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE id = ?")->execute([$user_id]);
        
        // Handle Remember Me
        if (isset($_SESSION['temp_remember']) && $_SESSION['temp_remember'] == true) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
            setcookie('remember_me', $token, time() + (86400 * 30), "/");
        }

        // Cleanup Temp Session
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_remember']);

        // Redirect
        redirect($user['is_admin'] ? SITE_URL . '/panel/index.php' : SITE_URL . '/user/index.php');

    } else {
        $error = "Invalid or Expired OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Security Check</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-grad: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
        body { background: #eef2ff; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .otp-card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .icon-box { width: 60px; height: 60px; background: #e0e7ff; color: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 20px; }
        .otp-input { width: 100%; padding: 15px; font-size: 1.5rem; letter-spacing: 5px; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; outline: none; transition: 0.3s; }
        .otp-input:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
        .btn-verify { width: 100%; padding: 14px; background: var(--primary-grad); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 1rem; }
        .error-msg { color: #ef4444; background: #fef2f2; padding: 10px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #fee2e2; }
    </style>
</head>
<body>
    <div class="otp-card">
        <div class="icon-box">🛡️</div>
        <h2 style="margin:0 0 10px; color:#1e293b;">2-Step Verification</h2>
        <p style="color:#64748b; margin-bottom:30px; font-size:0.95rem;">
            We sent a code to your email.<br>Enter it below to login.
        </p>

        <?php if($error): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" class="otp-input" placeholder="123456" maxlength="6" required autofocus>
            <button type="submit" class="btn-verify">Verify & Login</button>
        </form>
        
        <p style="margin-top:20px; font-size:0.9rem;">
            <a href="login.php" style="color:#64748b; text-decoration:none;">&larr; Back to Login</a>
        </p>
    </div>
</body>
</html>