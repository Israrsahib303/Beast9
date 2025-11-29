<?php
session_start();
include 'includes/config.php';
include 'includes/db.php';
include 'includes/helpers.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$show_form = false;
$user_id = null;

// --- 1. TOKEN VALIDATION ---
if (empty($token)) {
    $error = "Invalid or missing reset token.";
} else {
    // Token verify karein aur expiry check karein
    $stmt = $db->prepare("SELECT id FROM users WHERE password_reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $show_form = true;
        $user_id = $user['id'];
    } else {
        $error = "This link is invalid or has expired. Please request a new one.";
    }
}

// --- 2. PASSWORD UPDATE LOGIC ---
if ($show_form && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf_token)) {
        $error = "Security Mismatch. Please refresh the page.";
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error = "Both password fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Hash Password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update Database (Fixed column name to password_hash)
            $stmt_update = $db->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
            
            if ($stmt_update->execute([$hashed_password, $user_id])) {
                redirect(SITE_URL . '/login.php?msg=reset_success');
            } else {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- 🎨 PREMIUM THEME VARIABLES --- */
        :root {
            --primary-grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --text-dark: #0f172a;
            --text-gray: #64748b;
            --danger: #ef4444;
            --success: #10b981;
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

        /* --- 🌀 BACKGROUND ANIMATIONS --- */
        .bg-orb {
            position: absolute; border-radius: 50%; filter: blur(90px); z-index: -1;
            animation: floatOrb 10s infinite alternate;
        }
        .orb-1 { width: 500px; height: 500px; background: #c7d2fe; top: -10%; left: -10%; }
        .orb-2 { width: 400px; height: 400px; background: #e9d5ff; bottom: -10%; right: -10%; animation-delay: -5s; }
        
        @keyframes floatOrb {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 50px); }
        }

        /* --- 💎 LIVE BORDER CARD --- */
        .auth-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 420px;
            z-index: 10;
            padding: 4px; /* Space for border */
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            animation: cardEntrance 0.6s ease-out;
        }

        /* Rotating Border Effect */
        .auth-card-wrapper::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: conic-gradient(transparent, transparent, transparent, #6366f1, #8b5cf6, #ec4899);
            animation: rotateBorder 4s linear infinite;
            z-index: 0;
        }

        @keyframes rotateBorder {
            100% { transform: rotate(360deg); }
        }

        .auth-card {
            position: relative;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 30px;
            z-index: 1;
            text-align: center;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- TYPOGRAPHY --- */
        .lock-icon-box {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #e0e7ff 0%, #fae8ff 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 1.8rem;
            color: #6366f1;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.15);
            animation: pulseIcon 3s infinite;
        }

        @keyframes pulseIcon {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(99, 102, 241, 0); }
        }

        .title { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; letter-spacing: -0.5px; }
        .subtitle { color: var(--text-gray); font-size: 0.95rem; margin-bottom: 30px; line-height: 1.5; }

        /* --- FORMS --- */
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-label { display: block; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 8px; margin-left: 5px; }
        
        .input-field {
            width: 100%; padding: 14px 15px 14px 45px;
            background: #f8fafc; border: 2px solid #e2e8f0;
            border-radius: 16px; font-size: 1rem; color: var(--text-dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-icon {
            position: absolute; left: 16px; top: 46px; /* Adjusted for label */
            color: #94a3b8; font-size: 1.1rem; transition: 0.3s;
        }

        .input-field:focus {
            background: #fff; border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none;
        }
        .input-field:focus + .input-icon { color: #6366f1; transform: scale(1.1); }

        /* --- BUTTON --- */
        .btn-submit {
            width: 100%; padding: 16px;
            background: var(--primary-grad);
            color: white; border: none;
            border-radius: 16px; font-size: 1.05rem; font-weight: 700;
            cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
            margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.5); }
        .btn-submit:active { transform: translateY(-1px); }

        /* --- ALERTS --- */
        .alert {
            padding: 14px; border-radius: 12px; margin-bottom: 25px;
            font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 10px; justify-content: center;
        }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }

    </style>
</head>
<body>

    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="auth-card-wrapper">
        <div class="auth-card">
            
            <div class="lock-icon-box">
                <i class="fa-solid fa-key"></i>
            </div>

            <h1 class="title">Secure Reset</h1>
            <p class="subtitle">Create a strong password to protect your account.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
                <a href="forgot_password.php" style="color:#6366f1; font-weight:600; text-decoration:none;">Request new link</a>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                    <div class="input-group">
                        <label class="input-label">New Password</label>
                        <input type="password" name="password" class="input-field" placeholder="Min 6 characters" required>
                        <i class="fa-solid fa-lock input-icon"></i>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="input-field" placeholder="Repeat new password" required>
                        <i class="fa-solid fa-shield-check input-icon"></i>
                    </div>

                    <button type="submit" class="btn-submit">
                        Update Password <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>