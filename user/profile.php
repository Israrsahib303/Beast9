<?php
include '_header.php';

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// User Data Fetch
$stmt_user = $db->prepare("SELECT email, api_key FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// --- 1. Change Password Logic (SECURED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // CSRF CHECK
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security Token Mismatch. Please refresh page.';
    } else {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_pass = $stmt->fetch();

        if ($new_pass !== $confirm_pass) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (password_verify($current_pass, $user_pass['password_hash'])) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);
            $success = 'Password updated successfully!';
        } else {
            $error = 'Incorrect current password.';
        }
    }
}

// --- 2. Generate API Key Logic (SECURED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_api_key'])) {
    // CSRF CHECK
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security Token Mismatch. Please refresh page.';
    } else {
        try {
            $new_api_key = bin2hex(random_bytes(16)); 
            $db->prepare("UPDATE users SET api_key = ? WHERE id = ?")->execute([$new_api_key, $user_id]);
            // Update local variable to show new key immediately
            $user['api_key'] = $new_api_key;
            $success = 'New API Key generated!';
        } catch (PDOException $e) {
            $error = 'Error generating key. Please try again.';
        }
    }
}
?>

<style>
/* --- ☀️ LIGHT THEME & PREMIUM UI --- */
:root {
    --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    --secondary-gradient: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
    --bg-page: #f3f4f6;
    --card-bg: #ffffff;
    --text-main: #111827;
    --text-muted: #6b7280;
    --border-light: #e5e7eb;
    --shadow-soft: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
    --shadow-hover: 0 20px 40px -5px rgba(79, 70, 229, 0.15);
}

.profile-page {
    min-height: 85vh;
    background: var(--bg-page);
    color: var(--text-main);
    font-family: 'Plus Jakarta Sans', sans-serif;
    position: relative;
    padding: 2rem 0;
    overflow: hidden;
}

/* Background Blobs (Light Pastels) */
.bg-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    opacity: 0.6;
    animation: float 10s infinite alternate;
}
.blob-1 { top: -10%; left: -5%; width: 400px; height: 400px; background: #e0e7ff; }
.blob-2 { bottom: -10%; right: -5%; width: 350px; height: 350px; background: #fae8ff; animation-delay: -5s; }

@keyframes float {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(20px) scale(1.05); }
}

.profile-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 1.5rem;
    position: relative;
    z-index: 2;
}

/* Header Section */
.profile-header {
    background: var(--card-bg);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: var(--shadow-soft);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-light);
    transition: transform 0.3s ease;
}
.profile-header:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover); }

.avatar-box {
    width: 70px; height: 70px;
    background: var(--primary-gradient);
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.8rem;
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
}

.user-info h1 { font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main); letter-spacing: -0.5px; }
.user-info p { color: var(--text-muted); margin: 5px 0 0 0; font-weight: 500; font-size: 0.95rem; }

/* Grid Layout */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

/* Cards */
.setting-card {
    background: var(--card-bg);
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: var(--shadow-soft);
    border: 1px solid var(--border-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.setting-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #c7d2fe;
}

.card-top { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); }
.card-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 1.2rem;
}
.icon-security { background: var(--primary-gradient); box-shadow: 0 8px 16px rgba(79, 70, 229, 0.25); }
.icon-api { background: var(--secondary-gradient); box-shadow: 0 8px 16px rgba(236, 72, 153, 0.25); }

.card-title h3 { font-size: 1.25rem; font-weight: 700; margin: 0; }
.card-title span { font-size: 0.85rem; color: var(--text-muted); }

/* Inputs */
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem; }
.form-input {
    width: 100%; padding: 14px 16px;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem; color: var(--text-main);
    transition: all 0.2s;
    outline: none;
}
.form-input:focus {
    background: #fff;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

/* Copy Group */
.copy-group { position: relative; display: flex; }
.copy-group input { padding-right: 50px; background: #f3f4f6; color: #4b5563; font-family: monospace; font-weight: 600; }
.btn-copy {
    position: absolute; right: 8px; top: 8px;
    width: 36px; height: 36px;
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 8px; cursor: pointer;
    color: #6b7280; display: flex; align-items: center; justify-content: center;
    transition: 0.2s;
}
.btn-copy:hover { background: #f3f4f6; color: #4f46e5; border-color: #d1d5db; }

/* Buttons */
.btn-save {
    width: 100%; padding: 14px;
    background: var(--primary-gradient);
    color: white; border: none; border-radius: 12px;
    font-weight: 700; font-size: 1rem;
    cursor: pointer; transition: 0.3s;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}
.btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4); }

.btn-danger {
    background: #fff; color: #ef4444; border: 2px solid #fee2e2;
    box-shadow: none;
}
.btn-danger:hover {
    background: #fef2f2; border-color: #fca5a5; color: #dc2626;
    transform: translateY(-2px);
}

/* Alerts */
.alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

@media (max-width: 768px) {
    .settings-grid { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; text-align: center; }
}
</style>

<div class="profile-page">
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>

    <div class="profile-container">
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="avatar-box">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="user-info">
                <h1>Account Settings</h1>
                <p>Manage your security preferences and API access for <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
            </div>
        </div>

        <div class="settings-grid">
            
            <div class="setting-card">
                <div class="card-top">
                    <div class="card-icon icon-security">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="card-title">
                        <h3>Security</h3>
                        <span>Update your login password</span>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" placeholder="Enter current password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Repeat new password" required>
                    </div>
                    
                    <button type="submit" class="btn-save">Update Password</button>
                </form>
            </div>

            <div class="setting-card">
                <div class="card-top">
                    <div class="card-icon icon-api">
                        <i class="fa-solid fa-code"></i>
                    </div>
                    <div class="card-title">
                        <h3>Developer API</h3>
                        <span>Connect your apps via API</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Your API Key</label>
                    <div class="copy-group">
                        <input type="text" id="apiKey" class="form-input" value="<?php echo $user['api_key'] ? $user['api_key'] : 'Not Generated'; ?>" readonly>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('apiKey')" title="Copy Key">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">API Endpoint URL</label>
                    <div class="copy-group">
                        <input type="text" id="apiUrl" class="form-input" value="<?php echo SITE_URL; ?>/api_v2.php" readonly>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('apiUrl')" title="Copy URL">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>

                <form method="POST" onsubmit="return confirm('Generating a new key will stop the old one from working. Are you sure?');">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="generate_api_key" value="1">
                    
                    <button type="submit" class="btn-save btn-danger">
                        <i class="fa-solid fa-arrows-rotate"></i> 
                        <?php echo $user['api_key'] ? 'Regenerate Key' : 'Generate New Key'; ?>
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function copyToClipboard(id) {
    var copyText = document.getElementById(id);
    copyText.select();
    copyText.setSelectionRange(0, 99999); 
    document.execCommand("copy");
    
    // Change icon temporarily
    let btn = copyText.nextElementSibling;
    let originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color:#10b981"></i>';
    setTimeout(() => { btn.innerHTML = originalIcon; }, 1500);
}
</script>

<?php include '_footer.php'; ?>