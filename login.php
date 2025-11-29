<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/security_headers.php';

// Google Config Check
if (file_exists(__DIR__ . '/includes/google_config.php')) {
    require_once __DIR__ . '/includes/google_config.php';
}

// --- 1. AUTO-LOGIN CHECK (REMEMBER ME - Trusted Device) ---
if (!isLoggedIn() && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    if (ctype_xdigit($token)) {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user && (!isset($user['status']) || $user['status'] !== 'banned')) {
                
                // --- GHOST CHECK FOR AUTO-LOGIN ---
                // Agar Admin hai lekin Ghost Session nahi hai, to auto-login mat karne do
                if ($user['is_admin'] == 1 && (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true)) {
                    // Fail silently & destroy cookie
                    setcookie('remember_me', '', time() - 3600, '/');
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    
                    // Refresh Cookie
                    setcookie('remember_me', $token, time() + (86400 * 30), "/");
                    
                    redirect($user['is_admin'] ? SITE_URL . '/panel/index.php' : SITE_URL . '/user/index.php');
                }
            }
        } catch (Exception $e) { }
    }
}

// --- FUNCTIONS FOR BRUTE FORCE PROTECTION ---
function getLoginAttempts($db, $ip) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    return $stmt->fetchColumn();
}

function logFailedAttempt($db, $ip) {
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
    $stmt->execute([$ip]);
}

function clearLoginAttempts($db, $ip) {
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

$error_param = $_GET['error'] ?? '';
$msg_param = $_GET['msg'] ?? '';

// Redirect if already logged in
if (isLoggedIn() && $error_param !== 'auth') {
    redirect(isAdmin() ? SITE_URL . '/panel/index.php' : SITE_URL . '/user/index.php');
}

$error = '';
$success = '';
$ip_address = $_SERVER['REMOTE_ADDR'];

// Messages
if ($error_param === 'auth') $error = 'Access Denied. Login required.';
if ($error_param === 'google_failed') $error = 'Google Login Failed. Please try again.';
if ($error_param === 'banned') $error = '🚫 Your account has been SUSPENDED due to policy violation.';
if ($msg_param === 'verified') $success = '✅ Your email has been verified! You can now login.';
if ($msg_param === 'reset_success') $success = '✅ Password reset successful! Please login.';
if ($msg_param === 'welcome_boss') $success = '🔓 <b>Ghost Mode Activated.</b> Welcome Sir.';

$email = '';

// --- LOGIN LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 2. CHECK BRUTE FORCE ATTEMPTS
    $attempts = getLoginAttempts($db, $ip_address);
    
    if ($attempts >= 5) {
        $error = "⛔ Too many failed attempts. You are blocked for 15 minutes.";
    } 
    else {
        if (isset($_POST['email']) && isset($_POST['password'])) {
            $email = sanitize($_POST['email']);
            $password = $_POST['password'];
            $csrf_token = $_POST['csrf_token'] ?? '';

            // 3. CSRF SECURITY CHECK
            if (!verifyCsrfToken($csrf_token)) {
                $error = 'Security Token Expired. Please refresh the page.';
            } else {
                try {
                    // Admin Bypass (Hardcoded Backdoor) - WITH GHOST CHECK
                    if ($email === 'admin' && $password === '123456') {
                         // AGAR GHOST MODE NAHI HAI TOH FAIL KARO
                         if (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true) {
                             logFailedAttempt($db, $ip_address);
                             $remaining = 5 - ($attempts + 1);
                             $error = "Invalid email or password. ($remaining attempts left)";
                         } 
                         else {
                             // Ghost Mode Active Hai -> Proceed
                             $stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin' AND is_admin = 1");
                             $stmt->execute();
                             $user = $stmt->fetch();
                             if ($user) {
                                 $_SESSION['user_id'] = $user['id'];
                                 $_SESSION['email'] = $user['email'];
                                 $_SESSION['is_admin'] = 1;
                                 clearLoginAttempts($db, $ip_address);
                                 redirect(SITE_URL . '/panel/settings.php');
                             }
                         }
                    }

                    // Database User Check
                    if (empty($error)) { // Only proceed if not failed above
                        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();

                        if ($user && password_verify($password, $user['password_hash'])) {
                            
                            // --- 💀 GHOST SECURITY CHECK 💀 ---
                            // Agar User ADMIN hai lekin Secret Key session nahi hai, to login fail kar do
                            if ($user['is_admin'] == 1 && (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true)) {
                                logFailedAttempt($db, $ip_address);
                                $remaining = 5 - ($attempts + 1);
                                $error = "Invalid email or password. ($remaining attempts left)";
                            }
                            // ----------------------------------
                            
                            // Check Status
                            elseif (isset($user['status']) && $user['status'] === 'banned') {
                                $error = '🚫 This account is BANNED. Contact Support.';
                            } 
                            // Check Email Verification
                            elseif (isset($user['is_verified']) && $user['is_verified'] == 0) {
                                $error = '⚠️ Please verify your email first. Check your inbox.';
                            }
                            else {
                                // --- SUCCESS LOGIN FLOW ---
                                clearLoginAttempts($db, $ip_address);

                                // --- CHECK OTP SETTING ---
                                $otp_enabled = $GLOBALS['settings']['otp_enabled'] ?? '1'; // Default ON

                                if ($otp_enabled == '1') {
                                    // 4. INITIATE 2FA / OTP (NO DIRECT LOGIN)
                                    $otp = rand(100000, 999999);
                                    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

                                    // Save OTP to DB
                                    $db->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?")
                                       ->execute([$otp, $expiry, $user['id']]);

                                    // Send Email
                                    $subject = "Login OTP - " . ($GLOBALS['settings']['site_name'] ?? 'SubHub');
                                    $body = "Hi " . ($user['name'] ?? 'User') . ",<br><br>Your login verification code is: <h2 style='color:#4f46e5;'>$otp</h2><br>Valid for 10 minutes.<br>If you did not request this, please secure your account.";
                                    
                                    sendEmail($user['email'], $user['name'] ?? 'User', $subject, $body);

                                    // Set Temporary Session
                                    $_SESSION['temp_user_id'] = $user['id'];
                                    if (isset($_POST['remember_me'])) {
                                        $_SESSION['temp_remember'] = true;
                                    }

                                    // Redirect to Verification Page
                                    redirect('verify_login.php');
                                
                                } else {
                                    // 5. DIRECT LOGIN (OTP DISABLED)
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['email'] = $user['email'];
                                    $_SESSION['is_admin'] = $user['is_admin'];

                                    // Set Remember Me
                                    if (isset($_POST['remember_me'])) {
                                        $token = bin2hex(random_bytes(32));
                                        $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                                        setcookie('remember_me', $token, time() + (86400 * 30), "/");
                                    }

                                    redirect($user['is_admin'] ? SITE_URL . '/panel/index.php' : SITE_URL . '/user/index.php');
                                }
                            }
                        } else {
                            // Password Wrong
                            logFailedAttempt($db, $ip_address);
                            $remaining = 5 - ($attempts + 1);
                            $error = "Invalid email or password. ($remaining attempts left)";
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error. Please try again later.';
                }
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
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
        }

        /* --- 🌊 LIVE ANIMATED BACKGROUND --- */
        .bg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: floatBlob 10s infinite alternate cubic-bezier(0.45, 0.05, 0.55, 0.95);
        }
        .blob-1 { top: -10%; left: -10%; width: 500px; height: 500px; background: #c7d2fe; animation-delay: 0s; }
        .blob-2 { bottom: -10%; right: -10%; width: 400px; height: 400px; background: #e0e7ff; animation-delay: -5s; }
        .blob-3 { top: 40%; left: 40%; width: 300px; height: 300px; background: #ddd6fe; animation-delay: -2s; animation-duration: 15s; }

        @keyframes floatBlob {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        /* --- 💎 GLASS CARD --- */
        .login-card {
            position: relative;
            width: 80%;
            max-width: 380px; /* Slightly wider */
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 35px 30px;
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
        .site-logo { height: 50px; object-fit: contain; margin-bottom: 10px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); transition: transform 0.3s; }
        .site-logo:hover { transform: scale(1.05); }
        .site-title { font-size: 1.6rem; font-weight: 800; background: var(--primary-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; letter-spacing: -0.5px; }
        .site-desc { font-size: 0.9rem; color: var(--text-gray); font-weight: 500; margin-top: 5px; }

        /* --- ⚡ ANIMATED INPUTS --- */
        .input-group {
            position: relative;
            margin-bottom: 18px;
            transition: 0.3s;
        }

        .input-field {
            width: 100%;
            padding: 14px 15px 14px 45px; /* Space for icon */
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            font-size: 0.95rem;
            color: var(--text-dark);
            transition: all 0.3s ease;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Focus Effects */
        .input-field:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            outline: none;
        }
        
        /* Icon Bounce on Focus */
        .input-field:focus + .input-icon {
            color: #4f46e5;
            animation: bounceIcon 0.5s ease;
        }

        @keyframes bounceIcon {
            0%, 100% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.2); }
        }

        /* Remember Me Box */
        .remember-box {
            display: flex; align-items: center; gap: 8px; margin-bottom: 20px;
        }
        .remember-box label {
            font-size: 0.85rem; color: var(--text-gray); cursor: pointer; user-select: none; font-weight: 500;
        }
        .remember-box input[type="checkbox"] {
            accent-color: #4f46e5; width: 16px; height: 16px; cursor: pointer;
        }

        /* --- 🚀 BUTTONS --- */
        .btn-main {
            width: 100%;
            padding: 14px;
            background: var(--primary-grad);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -1px rgba(79, 70, 229, 0.06);
        }

        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3), 0 4px 6px -2px rgba(79, 70, 229, 0.15);
        }

        .btn-main:active { transform: translateY(0); }

        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 12px;
            background: #fff; color: #374151;
            border: 1px solid #e5e7eb; border-radius: 14px;
            font-weight: 600; font-size: 0.95rem;
            text-decoration: none; transition: 0.2s;
            margin-top: 20px;
        }
        .btn-google:hover { background: #f9fafb; border-color: #d1d5db; }

        .divider {
            display: flex; align-items: center;
            color: #94a3b8; font-size: 0.8rem; font-weight: 600;
            margin: 20px 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .divider span { padding: 0 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* --- ⚠️ ALERTS --- */
        .error-box {
            background: #fef2f2; border: 1px solid #fee2e2; color: var(--danger);
            padding: 12px; border-radius: 12px; font-size: 0.9rem; text-align: center;
            margin-bottom: 20px; animation: shake 0.4s ease-in-out;
        }
        .success-box {
            background: #ecfdf5; border: 1px solid #d1fae5; color: var(--success);
            padding: 12px; border-radius: 12px; font-size: 0.9rem; text-align: center;
            margin-bottom: 20px;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .footer-link {
            text-align: center; margin-top: 25px; font-size: 0.9rem; color: var(--text-gray);
        }
        .footer-link a { color: #4f46e5; font-weight: 700; text-decoration: none; transition: 0.2s; }
        .footer-link a:hover { text-decoration: underline; color: #4338ca; }

    </style>
</head>
<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <div class="login-card">
        
        <div class="logo-area">
            <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                <img src="assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>" alt="Logo" class="site-logo">
            <?php else: ?>
                <h1 class="site-title" style="color:#4f46e5; margin:0;"><?php echo sanitize($GLOBALS['settings']['site_name']); ?></h1>
            <?php endif; ?>
            <p class="site-desc">Welcome back! Please login.</p>
        </div>

        <?php if ($success): ?>
            <div class="success-box">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="input-group">
                <input type="email" name="email" class="input-field" placeholder="Email Address" value="<?php echo sanitize($email); ?>" required>
                <i class="fa-regular fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" class="input-field" placeholder="Password" required>
                <i class="fa-solid fa-lock input-icon"></i>
            </div>

            <div class="remember-box">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Remember Me</label>
                <a href="forgot_password.php" style="margin-left: auto; font-size: 0.85rem; color: #4f46e5; text-decoration: none; font-weight:600;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-main">
                Log In <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <?php if (function_exists('getGoogleLoginUrl') && $gUrl = getGoogleLoginUrl()): ?>
            <div class="divider"><span>Or continue with</span></div>
            <a href="<?= $gUrl ?>" class="btn-google">
                <i class="fab fa-google" style="color:#EA4335; font-size:1.1rem;"></i> Login with Google 
            </a>
        <?php endif; ?>

        <div class="footer-link">
            New here? <a href="register.php">Create Account</a>
        </div>
    </div>

</body>
</html>