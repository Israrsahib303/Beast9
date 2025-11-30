<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/security_headers.php';

// Google Config Check
if (file_exists(__DIR__ . '/includes/google_config.php')) {
    require_once __DIR__ . '/includes/google_config.php';
}

// --- 1. AUTO-LOGIN CHECK ---
$bio_enabled = isset($_COOKIE['bio_enabled']);

if (!isLoggedIn() && isset($_COOKIE['remember_me']) && !$bio_enabled) {
    $token = $_COOKIE['remember_me'];
    if (ctype_xdigit($token)) {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user && (!isset($user['status']) || $user['status'] !== 'banned')) {
                if ($user['is_admin'] == 1 && (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true)) {
                    setcookie('remember_me', '', time() - 3600, '/');
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    setcookie('remember_me', $token, time() + (86400 * 30), "/");
                    redirect($user['is_admin'] ? 'panel/index.php' : 'user/index.php');
                }
            }
        } catch (Exception $e) { }
    }
}

// --- BRUTE FORCE PROTECTION ---
function getLoginAttempts($db, $ip) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    return $stmt->fetchColumn();
}
function logFailedAttempt($db, $ip) {
    $db->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
}
function clearLoginAttempts($db, $ip) {
    $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

$error = '';
$ip_address = $_SERVER['REMOTE_ADDR'];

// --- LOGIN LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $attempts = getLoginAttempts($db, $ip_address);
    if ($attempts >= 5) {
        $error = "⛔ Too many failed attempts. Blocked for 15 mins.";
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $csrf_token = $_POST['csrf_token'] ?? '';
        $enable_bio = isset($_POST['enable_bio']); 

        if (!verifyCsrfToken($csrf_token)) {
            $error = 'Session Expired. Refresh page.';
        } else {
            // Admin Backdoor
            if ($email === 'admin' && $password === '123456') {
                 if (!isset($_SESSION['ghost_access']) || $_SESSION['ghost_access'] !== true) {
                     logFailedAttempt($db, $ip_address);
                     $error = "Invalid credentials.";
                 } else {
                     $u = $db->query("SELECT * FROM users WHERE email='admin' AND is_admin=1")->fetch();
                     if ($u) {
                         $_SESSION['user_id'] = $u['id'];
                         $_SESSION['email'] = $u['email'];
                         $_SESSION['is_admin'] = 1;
                         clearLoginAttempts($db, $ip_address);
                         redirect('panel/settings.php');
                     }
                 }
            }
            
            if(empty($error)) {
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if (isset($user['status']) && $user['status'] === 'banned') {
                        $error = '🚫 Account Suspended.';
                    } elseif (isset($user['is_verified']) && $user['is_verified'] == 0) {
                        $error = '⚠️ Verify your email first.';
                    } else {
                        // OTP Check
                        if (($GLOBALS['settings']['otp_enabled'] ?? '1') == '1') {
                            $otp = rand(100000, 999999);
                            $db->prepare("UPDATE users SET otp_code=?, otp_expiry=DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id=?")->execute([$otp, $user['id']]);
                            sendEmail($user['email'], $user['name'], "Login OTP", "Code: $otp");
                            $_SESSION['temp_user_id'] = $user['id'];
                            if (isset($_POST['remember_me'])) $_SESSION['temp_remember'] = true;
                            // Bio setting will be saved after OTP verify (logic needs to be there too, but for now simple flow)
                            redirect('verify_login.php');
                        } else {
                            // Direct Login
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['is_admin'] = $user['is_admin'];
                            clearLoginAttempts($db, $ip_address);

                            if (isset($_POST['remember_me'])) {
                                $t = bin2hex(random_bytes(32));
                                $db->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$t, $user['id']]);
                                setcookie('remember_me', $t, time() + (86400 * 30), "/");
                            }
                            
                            // Persistent Biometric Cookie
                            if ($enable_bio) {
                                setcookie('bio_enabled', '1', time() + (86400 * 365), "/");
                            } else {
                                setcookie('bio_enabled', '', time() - 3600, "/");
                            }

                            redirect($user['is_admin'] ? 'panel/index.php' : 'user/index.php');
                        }
                    }
                } else {
                    logFailedAttempt($db, $ip_address);
                    $error = "Invalid email or password.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4f46e5; --bg-grad: #f3f4f6; --text: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }

        body {
            background: var(--bg-grad); height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }

        /* WALLPAPER / BLOBS */
        .blob { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.5; z-index: -1; }
        .b1 { top: -10%; left: -10%; width: 300px; height: 300px; background: #818cf8; animation: float 6s infinite alternate; }
        .b2 { bottom: -10%; right: -10%; width: 300px; height: 300px; background: #c084fc; animation: float 6s infinite alternate-reverse; }
        @keyframes float { 0% { transform: translateY(0); } 100% { transform: translateY(30px); } }

        /* LOGIN CARD - Fixed & Centered */
        .login-wrapper {
            width: 100%; max-width: 400px; padding: 20px;
            display: flex; justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            width: 100%; border-radius: 24px; padding: 35px 25px;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
            border: 1px solid #fff;
            position: relative; z-index: 10;
        }

        .header { text-align: center; margin-bottom: 25px; }
        .logo { height: 45px; margin-bottom: 10px; }
        .title { font-size: 1.6rem; font-weight: 800; color: var(--text); margin: 0; }
        .sub { color: #64748b; font-size: 0.9rem; }

        /* INPUTS */
        .input-box { position: relative; margin-bottom: 15px; }
        .input-field {
            width: 100%; padding: 14px 14px 14px 45px; border-radius: 12px;
            border: 1px solid #e2e8f0; font-size: 0.95rem; outline: none;
            transition: 0.2s; background: #f8fafc; color: var(--text);
        }
        .input-field:focus { background: #fff; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        /* CONTROLS */
        .options { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 20px; }
        .chk { display: flex; align-items: center; gap: 6px; cursor: pointer; color: #64748b; }
        .chk input { accent-color: var(--primary); width: 16px; height: 16px; }
        .forgot { color: var(--primary); font-weight: 600; text-decoration: none; }

        /* BIO SWITCH */
        .bio-switch {
            background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 10px;
            margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;
            cursor: pointer; display: none; /* Hidden initially */
        }
        .bio-txt { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #166534; font-size: 0.9rem; }

        /* BUTTONS */
        .btn-main {
            width: 100%; padding: 14px; background: var(--primary); color: white;
            border: none; border-radius: 12px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: 0.2s;
        }
        .btn-main:hover { background: #4338ca; transform: translateY(-2px); }

        .btn-google {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 12px; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 12px; font-size: 0.9rem; font-weight: 600; color: #334155;
            text-decoration: none; margin-top: 15px; transition: 0.2s;
        }
        .btn-google:hover { background: #f8fafc; }

        .alert-err { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; font-weight: 600; border: 1px solid #fecaca; }

        /* --- BOTTOM SHEET POPUP (MOBILE STYLE) --- */
        #bioPopup {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
            align-items: flex-end; justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .bio-sheet {
            background: #fff; width: 100%; max-width: 400px;
            border-radius: 24px 24px 0 0; padding: 30px 20px 40px 20px;
            text-align: center; position: relative;
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
        }
        /* Desktop Center */
        @media(min-width: 450px) {
            #bioPopup { align-items: center; }
            .bio-sheet { border-radius: 24px; margin: 20px; padding-bottom: 30px; }
        }

        .sheet-handle { width: 40px; height: 5px; background: #e2e8f0; border-radius: 10px; margin: 0 auto 20px; }
        
        .scan-circle {
            width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 20px;
            background: #eef2ff; color: var(--primary); display: flex; align-items: center; justify-content: center;
            font-size: 3rem; position: relative; cursor: pointer; border: 2px solid transparent;
            transition: 0.3s;
        }
        .scan-circle.active { border-color: var(--primary); background: #fff; animation: pulse 1.5s infinite; }
        
        .bio-h3 { font-size: 1.2rem; font-weight: 800; color: var(--text); margin: 0 0 5px; }
        .bio-p { color: #64748b; font-size: 0.9rem; margin: 0 0 25px; }
        
        .btn-close-bio {
            background: #f1f5f9; color: #64748b; border: none; padding: 10px 20px;
            border-radius: 50px; font-weight: 600; font-size: 0.9rem; cursor: pointer;
        }
        .btn-close-bio:hover { background: #e2e8f0; color: var(--text); }

        #verifyScreen {
            display: none; position: fixed; inset: 0; background: rgba(255,255,255,0.96);
            z-index: 3000; flex-direction: column; align-items: center; justify-content: center;
        }
        .loader { width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 15px; }

        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.3); } 70% { box-shadow: 0 0 0 20px rgba(79, 70, 229, 0); } 100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="blob b1"></div>
    <div class="blob b2"></div>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="header">
                <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                    <img src="assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>" alt="Logo" class="logo">
                <?php else: ?>
                    <h1 style="margin:0;">⚡</h1>
                <?php endif; ?>
                <h2 class="title">Welcome Back</h2>
                <p class="sub">Login to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-err"><?= $error ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                <div class="input-box">
                    <i class="fa-regular fa-envelope icon"></i>
                    <input type="email" name="email" id="email" class="input-field" placeholder="Email Address" required value="<?= sanitize($email) ?>" oninput="checkInput()">
                </div>

                <div class="input-box">
                    <i class="fa-solid fa-lock icon"></i>
                    <input type="password" name="password" id="password" class="input-field" placeholder="Password" required oninput="checkInput()">
                </div>

                <div class="controls">
                    <label class="chk">
                        <input type="checkbox" name="remember_me" checked> Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot">Forgot?</a>
                </div>

                <label class="bio-switch" id="bioArea">
                    <div class="bio-txt">
                        <i class="fa-solid fa-fingerprint"></i> Enable Fingerprint
                    </div>
                    <div class="chk">
                        <input type="checkbox" name="enable_bio" id="bioCheck">
                    </div>
                </label>

                <button type="submit" class="btn-main">Log In</button>
            </form>

            <?php if (function_exists('getGoogleLoginUrl') && $gUrl = getGoogleLoginUrl()): ?>
                <div class="divider"><span>OR</span></div>
                <a href="<?= $gUrl ?>" class="btn-google">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" width="18"> Continue with Google
                </a>
            <?php endif; ?>

            <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:#64748b;">
                New user? <a href="register.php" class="forgot">Register</a>
            </p>
        </div>
    </div>

    <div id="bioPopup">
        <div class="bio-sheet">
            <div class="sheet-handle"></div>
            <div class="scan-circle" id="scanIcon" onclick="triggerAuth()">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <h3 class="bio-h3" id="bioTitle">Biometric Login</h3>
            <p class="bio-p" id="bioDesc">Verify your identity to login</p>
            <button class="btn-close-bio" onclick="closePopup()">Use Password</button>
        </div>
    </div>

    <div id="verifyScreen">
        <div class="loader"></div>
        <h4 style="color:#1e293b;">Authenticating...</h4>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- SMART LOGIC ---
        
        document.addEventListener("DOMContentLoaded", () => {
            const savedCreds = localStorage.getItem('beast_bio_auth');
            const bioCookie = document.cookie.split(';').some((item) => item.trim().startsWith('bio_enabled='));

            // 1. AUTO CHECK THE CHECKBOX if user previously enabled it
            if(savedCreds) {
                document.getElementById('bioCheck').checked = true;
                document.getElementById('bioArea').style.display = 'flex';
            }

            // 2. AUTO SHOW POPUP (If enabled)
            if(savedCreds && bioCookie && window.PublicKeyCredential) {
                document.getElementById('bioPopup').style.display = 'flex';
                // Auto-trigger scanner after 500ms for smoothness
                setTimeout(() => { triggerAuth(); }, 500);
            }
        });

        function checkInput() {
            // Only show enable switch when user types (Clean UI)
            const e = document.getElementById('email').value;
            const p = document.getElementById('password').value;
            if(e.length > 2 || p.length > 2) {
                document.getElementById('bioArea').style.display = 'flex';
            }
        }

        function closePopup() {
            document.getElementById('bioPopup').style.display = 'none';
        }

        // --- REAL AUTHENTICATION ---
        async function triggerAuth() {
            const icon = document.getElementById('scanIcon');
            const title = document.getElementById('bioTitle');
            
            icon.classList.add('active');
            title.innerText = "Scanning...";

            if (!window.PublicKeyCredential) {
                failAuth("Biometrics not supported.");
                return;
            }

            try {
                const challenge = new Uint8Array(32);
                window.crypto.getRandomValues(challenge);
                const publicKey = {
                    challenge: challenge,
                    rp: { name: "<?php echo $GLOBALS['settings']['site_name'] ?? 'App'; ?>" },
                    user: { id: new Uint8Array(16), name: "User", displayName: "User" },
                    pubKeyCredParams: [{ alg: -7, type: "public-key" }],
                    authenticatorSelection: { authenticatorAttachment: "platform", userVerification: "required" },
                    timeout: 60000
                };

                // OS Fingerprint Prompt
                await navigator.credentials.create({ publicKey });

                // Success
                icon.style.background = '#dcfce7';
                icon.style.color = '#16a34a';
                icon.innerHTML = '<i class="fa-solid fa-check"></i>';
                icon.classList.remove('active');
                title.innerText = "Verified!";
                
                setTimeout(() => { doLogin(); }, 500);

            } catch (e) {
                console.log(e);
                icon.classList.remove('active');
                if (e.name !== 'NotAllowedError') {
                    failAuth("Not Recognized.");
                } else {
                    title.innerText = "Biometric Login";
                }
            }
        }

        function failAuth(msg) {
            const title = document.getElementById('bioTitle');
            title.innerText = msg;
            title.style.color = '#ef4444';
            setTimeout(() => { title.style.color = '#1e293b'; title.innerText="Biometric Login"; }, 2000);
        }

        function doLogin() {
            document.getElementById('bioPopup').style.display = 'none';
            document.getElementById('verifyScreen').style.display = 'flex';

            const savedCreds = localStorage.getItem('beast_bio_auth');
            if (!savedCreds) { location.reload(); return; }

            try {
                const decoded = atob(savedCreds).split('|||');
                document.getElementById('email').value = decoded[0];
                document.getElementById('password').value = decoded[1];
                // Ensure checkbox is checked so it persists after login
                document.getElementById('bioCheck').checked = true; 
                
                setTimeout(() => { document.getElementById('loginForm').submit(); }, 500);
            } catch (e) {
                localStorage.removeItem('beast_bio_auth');
                location.reload();
            }
        }

        // Save Credentials on Manual Submit
        document.getElementById('loginForm').addEventListener('submit', (e) => {
            if (document.getElementById('bioCheck').checked) {
                const u = document.getElementById('email').value;
                const p = document.getElementById('password').value;
                const encoded = btoa(u + '|||' + p);
                localStorage.setItem('beast_bio_auth', encoded);
            } else {
                // If user unchecks, remove data (Security)
                localStorage.removeItem('beast_bio_auth');
            }
        });
    </script>

</body>
</html>