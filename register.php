<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/google_config.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
$success = '';
$email = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $csrf_token = $_POST['csrf_token'] ?? '';

    // --- 1. CSRF SECURITY CHECK (New Addition) ---
    if (!verifyCsrfToken($csrf_token)) {
        $error = 'Security check failed. Please refresh and try again.';
    }
    // --- Validations (Old Same) ---
    elseif (empty($name) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email is already registered.';
            } else {
                // --- 2. EMAIL VERIFICATION LOGIC (New Edition) ---
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32)); // Generate unique token
                
                // Insert with is_verified = 0
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, is_admin, created_at, is_verified, verification_token) VALUES (?, ?, ?, 0, NOW(), 0, ?)");
                
                if ($stmt->execute([$name, $email, $password_hash, $verification_token])) {
                    
                    // Send Verification Email
                    $verify_link = SITE_URL . '/verify.php?token=' . $verification_token;
                    $subject = "Verify Your Account - " . ($GLOBALS['settings']['site_name'] ?? 'SubHub');
                    $body = "Hi $name,<br><br>Thank you for registering. Please click the link below to verify your account:<br><br>";
                    $body .= "<a href='$verify_link' style='background:#6366f1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;'>Verify Account</a><br><br>";
                    $body .= "Or copy this link: $verify_link";

                    $mailSent = sendEmail($email, $name, $subject, $body);

                    if ($mailSent['success']) {
                        $success = "Registration successful! We have sent a verification link to <b>$email</b>. Please check your inbox (and spam folder).";
                        // Auto-login removed for security until verified
                    } else {
                        // Agar email fail ho jaye toh user ko verify kar do manually (fallback)
                        // $db->query("UPDATE users SET is_verified=1 WHERE email='$email'");
                        $error = "Account created but failed to send email. Error: " . $mailSent['message'];
                    }

                } else {
                    $error = 'Failed to create account.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
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
            --success: #10b981; /* Added Success Color */
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: #eef2ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            padding: 20px;
        }

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

        .auth-card {
            position: relative;
            width: 90%;
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

        .logo-area { text-align: center; margin-bottom: 20px; }
        .site-logo { height: 45px; object-fit: contain; margin-bottom: 8px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .site-title { font-size: 1.4rem; font-weight: 800; background: var(--primary-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .site-desc { font-size: 0.8rem; color: var(--text-gray); font-weight: 500; }

        .input-group { position: relative; margin-bottom: 15px; transition: 0.3s; }
        .input-field {
            width: 100%; padding: 12px 15px 12px 45px; border: 2px solid #e2e8f0;
            border-radius: 14px; background: #fff; font-size: 0.95rem; color: var(--text-dark);
            transition: all 0.3s ease;
        }
        .input-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 1rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-field:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none; }
        .input-field:focus + .input-icon { color: #4f46e5; animation: bounceIcon 0.5s ease; }

        @keyframes bounceIcon {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.2); }
        }

        .btn-main {
            width: 100%; padding: 12px; background: var(--primary-grad); color: white;
            border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px;
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(79, 70, 229, 0.5); }
        .btn-main::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-main:hover::after { left: 100%; }

        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 12px; background: #fff; color: #374151;
            border: 1px solid #e5e7eb; border-radius: 12px; font-weight: 600; font-size: 0.9rem;
            text-decoration: none; transition: 0.2s; margin-top: 15px;
        }
        .btn-google:hover { background: #f9fafb; border-color: #d1d5db; }

        .divider { display: flex; align-items: center; color: #94a3b8; font-size: 0.75rem; font-weight: 600; margin: 15px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .divider span { padding: 0 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        .error-box {
            background: #fef2f2; border: 1px solid #fee2e2; color: var(--danger);
            padding: 10px; border-radius: 10px; font-size: 0.85rem; text-align: center;
            margin-bottom: 15px; animation: shake 0.4s ease-in-out;
        }
        .success-box {
            background: #ecfdf5; border: 1px solid #d1fae5; color: var(--success);
            padding: 15px; border-radius: 10px; font-size: 0.9rem; text-align: center;
            margin-bottom: 15px;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .footer-link { text-align: center; margin-top: 20px; font-size: 0.9rem; color: var(--text-gray); }
        .footer-link a { color: #4f46e5; font-weight: 700; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }

    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <div class="auth-card">
        
        <div class="logo-area">
            <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                <img src="assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>?v=<?php echo time(); ?>" 
                     alt="Logo" class="site-logo">
            <?php else: ?>
                <h1 class="site-title"><?php echo sanitize($GLOBALS['settings']['site_name']); ?></h1>
            <?php endif; ?>
            <p class="site-desc">Create your account to get started.</p>
        </div>

        <?php if ($success): ?>
            <div class="success-box">
                <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php elseif ($error): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): // Only show form if verification email not sent ?>
        <form action="register.php" method="POST">
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="input-group">
                <input type="text" name="name" class="input-field" placeholder="Full Name" value="<?php echo sanitize($name); ?>" required>
                <i class="fa-regular fa-user input-icon"></i>
            </div>

            <div class="input-group">
                <input type="email" name="email" class="input-field" placeholder="Email Address" value="<?php echo sanitize($email); ?>" required>
                <i class="fa-regular fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" class="input-field" placeholder="Password (Min 6 chars)" required>
                <i class="fa-solid fa-lock input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password_confirm" class="input-field" placeholder="Confirm Password" required>
                <i class="fa-solid fa-key input-icon"></i>
            </div>

            <button type="submit" class="btn-main">
                Sign Up <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <?php if (function_exists('getGoogleLoginUrl') && $gUrl = getGoogleLoginUrl()): ?>
            <div class="divider"><span>Or join with</span></div>
            <a href="<?= $gUrl ?>" class="btn-google">
                <i class="fab fa-google" style="color:#EA4335"></i> Countinue with Google
            </a>
        <?php endif; ?>
        <?php endif; ?>

        <div class="footer-link">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>

</body>
</html>