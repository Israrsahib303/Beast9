<?php
include '_header.php';

$error = '';
$success = '';
$notice = $_GET['notice'] ?? '';

// --- UPDATE SETTINGS LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    
    global $settings; 
    $upload_dir = __DIR__ . '/../assets/img/';
    $current_logo = $settings['site_logo'] ?? 'logo.png'; 
    $current_favicon = $settings['site_favicon'] ?? 'favicon.png';

    // 1. LOGO UPLOAD
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $file = $_FILES['site_logo'];
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
        $f_type = mime_content_type($file['tmp_name']);
        if (in_array($f_type, $allowed) && $file['size'] <= 2*1024*1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_logo_name = 'logo.' . $ext; 
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_logo_name)) {
                $current_logo = $new_logo_name; 
            }
        } else { $error = 'Invalid Logo (Max 2MB, PNG/JPG only).'; }
    }

    // 2. FAVICON UPLOAD
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] == 0) {
        $file = $_FILES['site_favicon'];
        $allowed = ['image/png', 'image/x-icon', 'image/svg+xml'];
        $f_type = mime_content_type($file['tmp_name']);
        if (in_array($f_type, $allowed) && $file['size'] <= 1*1024*1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_fav_name = 'favicon.' . $ext; 
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_fav_name)) {
                $current_favicon = $new_fav_name;
            }
        } else { $error = 'Invalid Favicon (PNG/ICO, Max 1MB).'; }
    }
    
    if (empty($error)) { 
        $data = [
            // GENERAL
            'site_name' => sanitize($_POST['site_name'] ?? ''),
            'site_logo' => $current_logo,
            'site_favicon' => $current_favicon,
            'whatsapp_number' => sanitize($_POST['whatsapp_number'] ?? ''),

            // FINANCE
            'currency_symbol' => sanitize($_POST['currency_symbol'] ?? ''),
            'currency_conversion_rate' => sanitize($_POST['currency_conversion_rate'] ?? '280.00'),
            'daily_spin_enabled' => isset($_POST['daily_spin_enabled']) ? '1' : '0',
            'daily_spin_cooldown_hours' => (int)($_POST['daily_spin_cooldown_hours'] ?? 24),
            
            // PWA
            'pwa_name' => sanitize($_POST['pwa_name'] ?? ''),
            'pwa_short_name' => sanitize($_POST['pwa_short_name'] ?? ''),

            // AI TOOLS (UPDATED)
            'ai_tools_enabled' => isset($_POST['ai_tools_enabled']) ? '1' : '0',
            'gemini_api_key' => sanitize($_POST['gemini_api_key'] ?? ''),
            'jarvis_personality' => sanitize($_POST['jarvis_personality'] ?? ''),

            // THEME COLORS (Fix: Added ?? '' to prevent undefined index)
            'theme_primary' => sanitize($_POST['theme_primary'] ?? '#4f46e5'), 
            'theme_hover' => sanitize($_POST['theme_hover'] ?? '#4338ca'),
            'bg_color' => sanitize($_POST['bg_color'] ?? '#f3f4f6'),
            'card_color' => sanitize($_POST['card_color'] ?? '#ffffff'),
            'text_color' => sanitize($_POST['text_color'] ?? '#1f2937'),
            'text_muted_color' => sanitize($_POST['text_muted_color'] ?? '#6b7280'),

            // EMAIL / SMTP (Fix: Added ?? '' to prevent undefined index)
            'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
            'smtp_port' => sanitize($_POST['smtp_port'] ?? ''),
            'smtp_user' => sanitize($_POST['smtp_user'] ?? ''),
            'smtp_pass' => sanitize($_POST['smtp_pass'] ?? ''),
            'smtp_secure' => sanitize($_POST['smtp_secure'] ?? ''),
            'smtp_from_email' => sanitize($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name' => sanitize($_POST['smtp_from_name'] ?? '')
        ];
        
        try {
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($data as $key => $val) {
                $stmt->execute([$key, $val]);
                $GLOBALS['settings'][$key] = $val;
            }
            $success = 'Settings updated successfully!';
        } catch (PDOException $e) { $error = 'DB Error: ' . $e->getMessage(); }
    }
}

// --- CHANGE PASSWORD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $cur_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $cnf_pass = $_POST['confirm_password'] ?? '';
    $admin_id = $_SESSION['user_id'];

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($cur_pass, $user['password_hash'])) {
        if ($new_pass === $cnf_pass) {
            if (strlen($new_pass) >= 6) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $admin_id]);
                $success = 'Password changed successfully.';
            } else { $error = 'New password must be at least 6 characters.'; }
        } else { $error = 'New passwords do not match.'; }
    } else { $error = 'Incorrect current password.'; }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { --primary: #4f46e5; --bg: #f3f4f6; --card: #fff; --text: #1f2937; --border: #e5e7eb; }
    
    * { box-sizing: border-box; outline: none; }
    body { background: var(--bg); font-family: 'Inter', sans-serif; color: var(--text); margin: 0; overflow-x: hidden; }
    
    .settings-wrapper {
        width: 100%; max-width: 1000px; margin: 30px auto; padding: 0 20px;
    }

    /* HEADER */
    .page-header { margin-bottom: 25px; text-align: center; }
    .page-title { font-size: 1.8rem; font-weight: 800; margin: 0; color: #111; }
    .page-desc { color: #6b7280; font-size: 0.95rem; margin-top: 5px; }

    /* TABS */
    .tabs { 
        display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; 
        border-bottom: 1px solid var(--border); white-space: nowrap; 
        -webkit-overflow-scrolling: touch; scrollbar-width: none;
    }
    .tabs::-webkit-scrollbar { display: none; }

    .tab-btn {
        background: #fff; border: 1px solid var(--border); padding: 10px 20px; 
        font-weight: 600; color: #6b7280; cursor: pointer;
        border-radius: 50px; transition: 0.2s; font-size: 0.9rem;
    }
    .tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }

    /* CARDS & FORMS */
    .s-card { background: var(--card); border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border); display: none; animation: fadeIn 0.3s ease-out; }
    .s-card.active { display: block; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #374151; }
    
    .form-control {
        width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;
        font-size: 0.95rem; transition: 0.2s; font-family: inherit; background: #fff;
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    
    .color-input { padding: 5px; height: 45px; cursor: pointer; }
    
    .preview-img { width: 60px; height: 60px; border-radius: 10px; object-fit: contain; border: 1px solid var(--border); background: #f9fafb; padding: 5px; margin-top: 10px; }

    /* SWITCH TOGGLE */
    .switch { position: relative; display: inline-block; width: 50px; height: 26px; vertical-align: middle; margin-right: 10px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary); }
    input:checked + .slider:before { transform: translateX(24px); }

    /* BUTTONS */
    .btn-save {
        background: var(--primary); color: #fff; border: none; padding: 14px; width: 100%;
        border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 20px;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2); transition: 0.2s;
    }
    .btn-save:hover { background: #4338ca; transform: translateY(-2px); }
    
    .btn-test { background: #64748b; color: #fff; border: none; padding: 8px 15px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem; }
    .btn-test:hover { background: #475569; }

    /* RESPONSIVE */
    @media(max-width: 768px) { 
        .form-grid { grid-template-columns: 1fr; } 
        .s-card { padding: 20px; }
        .page-title { font-size: 1.5rem; }
    }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="settings-wrapper">
    
    <div class="page-header">
        <h1 class="page-title">⚙️ Site Settings</h1>
        <p class="page-desc">Configure your app, payments, and appearance.</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success mb-4"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mb-4"><?= $error ?></div><?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="openTab('general')">🏠 General</button>
        <button class="tab-btn" onclick="openTab('aitools')">🤖 AI Tools</button>
        <button class="tab-btn" onclick="openTab('pwa')">📱 App & Theme</button>
        <button class="tab-btn" onclick="openTab('finance')">💰 Finance</button>
        <button class="tab-btn" onclick="openTab('email')">📧 Email (SMTP)</button>
        <button class="tab-btn" onclick="openTab('security')">🔒 Security</button>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="update_settings" value="1">

        <div id="general" class="s-card active">
            <div class="form-group">
                <label class="form-label">Website Name</label>
                <input type="text" name="site_name" class="form-control" value="<?= sanitize($GLOBALS['settings']['site_name'] ?? 'SubHub') ?>" required>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">WhatsApp Number</label>
                    <input type="text" name="whatsapp_number" class="form-control" value="<?= sanitize($GLOBALS['settings']['whatsapp_number'] ?? '') ?>" placeholder="+92300...">
                </div>
                <div class="form-group">
                    <label class="form-label">Site Logo</label>
                    <input type="file" name="site_logo" class="form-control">
                    <?php if(!empty($GLOBALS['settings']['site_logo'])): ?>
                        <img src="../assets/img/<?= sanitize($GLOBALS['settings']['site_logo']) ?>" class="preview-img">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="aitools" class="s-card">
            <h4 style="margin-top:0; color:var(--primary);">🤖 AI Tools Configuration</h4>
            <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">
                Configure Groq API settings for AI Tools (Chat, Generation, etc).
            </p>
            
            <div class="form-group">
                <div style="display:flex; align-items:center; background:#f9fafb; padding:15px; border-radius:12px; border:1px solid #e5e7eb;">
                    <label class="switch">
                        <input type="checkbox" name="ai_tools_enabled" value="1" <?= ($GLOBALS['settings']['ai_tools_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block; color:#374151;">Enable AI Tools</strong>
                        <small style="color:#6b7280;">Show AI features to users</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Groq API Key</label>
                <input type="text" name="gemini_api_key" class="form-control" placeholder="gsk_..." value="<?= sanitize($GLOBALS['settings']['gemini_api_key'] ?? '') ?>">
                <small style="color:#666; display:block; margin-top:5px;">Get your API key from <a href="https://console.groq.com/keys" target="_blank" style="color:var(--primary); font-weight:600;">Groq Console</a>.</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Jarvis Personality</label>
                <textarea name="jarvis_personality" class="form-control" rows="3"><?= sanitize($GLOBALS['settings']['jarvis_personality'] ?? '') ?></textarea>
                <small style="color:#666;">Set the personality for the AI Assistant.</small>
            </div>
        </div>

        <div id="pwa" class="s-card">
            <h4 style="margin-top:0; color:var(--primary);">📱 PWA (App) Settings</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">App Name (Long)</label>
                    <input type="text" name="pwa_name" class="form-control" value="<?= sanitize($GLOBALS['settings']['pwa_name'] ?? 'SubHub') ?>" placeholder="SubHub - SMM Panel">
                </div>
                <div class="form-group">
                    <label class="form-label">Short Name (Home Screen)</label>
                    <input type="text" name="pwa_short_name" class="form-control" value="<?= sanitize($GLOBALS['settings']['pwa_short_name'] ?? 'SubHub') ?>" placeholder="SubHub">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Favicon / App Icon (512x512 PNG)</label>
                <input type="file" name="site_favicon" class="form-control">
                <img src="../assets/img/<?= sanitize($GLOBALS['settings']['site_favicon'] ?? 'favicon.png') ?>" class="preview-img">
                <small style="color:#666; display:block; margin-top:5px;">Also used for Browser Tab & Google Search.</small>
            </div>

            <hr style="border-color:#eee; margin:25px 0;">
            
            <h4 style="margin-top:0; color:var(--primary);">🎨 Theme Colors</h4>
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Primary Color</label><input type="color" name="theme_primary" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['theme_primary'] ?? '#4f46e5') ?>"></div>
                <div class="form-group"><label class="form-label">Hover Color</label><input type="color" name="theme_hover" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['theme_hover'] ?? '#4338ca') ?>"></div>
                <div class="form-group"><label class="form-label">Background</label><input type="color" name="bg_color" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['bg_color'] ?? '#f3f4f6') ?>"></div>
                <div class="form-group"><label class="form-label">Card Color</label><input type="color" name="card_color" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['card_color'] ?? '#ffffff') ?>"></div>
                <div class="form-group"><label class="form-label">Text Color</label><input type="color" name="text_color" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['text_color'] ?? '#1f2937') ?>"></div>
                <div class="form-group"><label class="form-label">Muted Text</label><input type="color" name="text_muted_color" class="form-control color-input" value="<?= sanitize($GLOBALS['settings']['text_muted_color'] ?? '#6b7280') ?>"></div>
            </div>
        </div>

        <div id="finance" class="s-card">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control" value="<?= sanitize($GLOBALS['settings']['currency_symbol'] ?? 'PKR') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">USD Conversion Rate</label>
                    <input type="text" name="currency_conversion_rate" class="form-control" value="<?= sanitize($GLOBALS['settings']['currency_conversion_rate'] ?? '280.00') ?>">
                </div>
            </div>
            <div class="form-group">
                <div style="display:flex; align-items:center; background:#f9fafb; padding:15px; border-radius:12px; border:1px solid #e5e7eb;">
                    <label class="switch">
                        <input type="checkbox" name="daily_spin_enabled" value="1" <?= ($GLOBALS['settings']['daily_spin_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block; color:#374151;">Enable Daily Spin Wheel</strong>
                        <small style="color:#6b7280;">Allow users to spin daily</small>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Spin Cooldown (Hours)</label>
                <input type="number" name="daily_spin_cooldown_hours" class="form-control" value="<?= sanitize($GLOBALS['settings']['daily_spin_cooldown_hours'] ?? '24') ?>">
            </div>
            <a href="wheel_prizes.php" style="color:var(--primary); font-weight:600;">Manage Wheel Prizes &rarr;</a>
        </div>

        <div id="email" class="s-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="margin:0;">SMTP Configuration</h4>
                <button type="button" id="btn-test-email" class="btn-test">Send Test Email</button>
            </div>
            <p id="email-msg" style="font-weight:600; font-size:0.9rem;"></p>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_host'] ?? '') ?>" placeholder="mail.example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="text" name="smtp_port" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_port'] ?? '465') ?>">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Username (Email)</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="smtp_pass" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_pass'] ?? '') ?>">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Security</label>
                    <select name="smtp_secure" class="form-control">
                        <option value="ssl" <?= ($GLOBALS['settings']['smtp_secure'] ?? 'ssl')=='ssl'?'selected':'' ?>>SSL</option>
                        <option value="tls" <?= ($GLOBALS['settings']['smtp_secure'] ?? '')=='tls'?'selected':'' ?>>TLS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">From Name</label>
                    <input type="text" name="smtp_from_name" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_from_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">From Email</label>
                <input type="text" name="smtp_from_email" class="form-control" value="<?= sanitize($GLOBALS['settings']['smtp_from_email'] ?? '') ?>">
            </div>
        </div>

        <button type="submit" class="btn-save">💾 Save All Settings</button>
    </form>

    <div id="security" class="s-card" style="margin-top:20px;">
        <h4 style="margin-top:0; color:#ef4444;">Change Admin Password</h4>
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn-save" style="background:#ef4444;">Update Password</button>
        </form>
    </div>

</div>

<script>
function openTab(tabName) {
    document.querySelectorAll('.s-card').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Test Email Logic
document.getElementById('btn-test-email').addEventListener('click', function() {
    const btn = this;
    const msg = document.getElementById('email-msg');
    
    btn.innerText = 'Sending...';
    msg.innerText = '';
    
    fetch('test_email_action.php') 
    .then(res => res.json())
    .then(data => {
        btn.innerText = 'Test Email';
        msg.style.color = data.success ? '#166534' : '#dc2626';
        msg.innerText = data.message;
    })
    .catch(err => {
        btn.innerText = 'Test Email';
        msg.innerText = 'Request failed.';
    });
});
</script>

<?php include '_footer.php'; ?>