<?php
session_start();
include 'includes/config.php';
include 'includes/db.php';
include 'includes/helpers.php'; 

$error = '';
$success = '';

// --- Logic Part (Same as before) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF CHECK
    if (!verifyCsrfToken($csrf_token)) {
        $error = 'Security Token Mismatch. Please refresh page.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = new DateTime('now');
            $expires->add(new DateInterval('PT1H')); 
            
            $stmt_token = $db->prepare("UPDATE users SET password_reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            if ($stmt_token->execute([$token, $expires->format('Y-m-d H:i:s'), $user['id']])) {
                
                $reset_link = SITE_URL . '/reset_password.php?token=' . $token;

                $subject = "Password Reset Request for " . ($GLOBALS['settings']['site_name'] ?? 'SubHub');
                $body = "Hi " . $user['name'] . ",<br><br>We received a request to reset your password. Click the link below to reset it:<br><br>";
                $body .= "<a href='$reset_link' style='background:#6366f1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>Reset Password</a><br><br>";
                $body .= "This link will expire in 1 hour.<br><br>If you did not request this, please ignore this email.";
                
                $email_result = sendEmail($email, $user['name'], $subject, $body);

                if ($email_result['success']) {
                    $success = "Check your email! We have sent a password reset link to <b>$email</b>.";
                } else {
                    $error = "Could not send email. " . $email_result['message'];
                }
            } else {
                $error = "Failed to generate reset token. Please try again.";
            }
        } else {
            // Security: Don't reveal if user exists
            $success = "If an account with that email exists, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- 🎨 THEME VARIABLES --- */
        :root {
            --primary-grad: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.6);
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --danger: #ef4444;
            --success: #10b981;
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: #eef2ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            padding: 20px;
        }

        /* --- 🌊 ANIMATED BACKGROUND --- */
        .bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: floatBlob 10s infinite alternate cubic-bezier(0.45, 0.05, 0.55, 0.95);
            z-index: -1;
        }
        .blob-1 { top: -10%; left: -10%; width: 500px; height: 500px; background: #c7d2fe; animation-delay: 0s; }
        .blob-2 { bottom: -10%; right: -10%; width: 400px; height: 400px; background: #e0e7ff; animation-delay: -5s; }
        .blob-3 { top: 40%; left: 40%; width: 300px; height: 300px; background: #ddd6fe; animation-delay: -2s; animation-duration: 15s; }

        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        /* --- 💎 GLASS CARD --- */
        .auth-card {
            position: relative;
            width: 100%;
            max-width: 400px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 30px;
            box-shadow: var(--shadow-lg);
            z-index: 10;
            animation: cardEntrance 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* --- HEADER --- */
        .logo-area { text-align: center; margin-bottom: 25px; }
        .site-logo { height: 50px; object-fit: contain; margin-bottom: 10px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .auth-title { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .auth-desc { font-size: 0.9rem; color: var(--text-gray); font-weight: 500; line-height: 1.5; }

        /* --- INPUTS --- */
        .input-group { position: relative; margin-bottom: 20px; transition: 0.3s; }
        .input-field {
            width: 100%; padding: 14px 15px 14px 45px; border: 2px solid #e2e8f0;
            border-radius: 14px; background: #fff; font-size: 0.95rem; color: var(--text-dark);
            transition: all 0.3s ease;
        }
        .input-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 1.1rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-field:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none; }
        .input-field:focus + .input-icon { color: #4f46e5; transform: translateY(-50%) scale(1.1); }

        /* --- BUTTON --- */
        .btn-main {
            width: 100%; padding: 14px; background: var(--primary-grad); color: white;
            border: none; border-radius: 14px; font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -1px rgba(79, 70, 229, 0.06);
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3); }
        
        /* --- ALERTS --- */
        .error-box {
            background: #fef2f2; border: 1px solid #fee2e2; color: var(--danger);
            padding: 12px; border-radius: 12px; font-size: 0.9rem; text-align: center;
            margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 8px;
            animation: shake 0.4s ease-in-out;
        }
        .success-box {
            background: #ecfdf5; border: 1px solid #d1fae5; color: var(--success);
            padding: 15px; border-radius: 12px; font-size: 0.95rem; text-align: center;
            margin-bottom: 20px; line-height: 1.5;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .back-link {
            text-align: center; margin-top: 25px; 
        }
        .back-link a {
            color: var(--text-gray); font-weight: 600; text-decoration: none; font-size: 0.9rem;
            display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .back-link a:hover { color: #4f46e5; transform: translateX(-3px); }

    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <div class="auth-card">
        
        <div class="logo-area">
            <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                <img src="assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>" alt="Logo" class="site-logo">
            <?php else: ?>
                <div style="font-size:2rem; margin-bottom:10px;">🔐</div>
            <?php endif; ?>
            <h1 class="auth-title">Forgot Password?</h1>
            <p class="auth-desc">No worries! Enter your email and we'll send you reset instructions.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-box">
                <i class="fa-solid fa-check-circle" style="font-size: 1.2rem; display:block; margin-bottom:8px;"></i>
                <?php echo $success; ?>
            </div>
            <div class="back-link">
                <a href="login.php" style="color: #4f46e5;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>
        <?php else: ?>

            <form action="forgot_password.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="input-group">
                    <input type="email" name="email" class="input-field" placeholder="Enter your email" required>
                    <i class="fa-regular fa-envelope input-icon"></i>
                </div>

                <button type="submit" class="btn-main">
                    Send Reset Link <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>

            <div class="back-link">
                <a href="login.php">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>