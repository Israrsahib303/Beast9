<?php
include '_header.php'; 
requireAdmin();
require_once __DIR__ . '/../includes/push_helper.php';

// --- FIX: Initialize variables to prevent "Undefined variable" errors ---
$success = '';
$error = '';

// --- AUTO-FIX: Ensure Safari ID setting exists in Database ---
try {
    $chk = $db->query("SELECT count(*) FROM settings WHERE setting_key='onesignal_safari_id'")->fetchColumn();
    if($chk == 0) {
        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES ('onesignal_safari_id', '')");
    }
} catch (Exception $e) { /* Silent Fail */ }

// --- HELPER FUNCTION TO SAVE SETTINGS SAFELY ---
function save_setting($db, $key, $val) {
    $val = sanitize($val);
    // Check if key exists
    $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        // Update
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
    } else {
        // Insert
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
    }
}

// --- 1. SAVE KEYS ---
if (isset($_POST['save_keys'])) {
    save_setting($db, 'onesignal_app_id', $_POST['app_id']);
    save_setting($db, 'onesignal_api_key', $_POST['api_key']);
    save_setting($db, 'onesignal_safari_id', $_POST['safari_id']);
    
    // Refresh Globals
    $GLOBALS['settings']['onesignal_app_id'] = $_POST['app_id'];
    $GLOBALS['settings']['onesignal_api_key'] = $_POST['api_key'];
    $GLOBALS['settings']['onesignal_safari_id'] = $_POST['safari_id'];

    $success = "✅ API Keys Saved Successfully! Database updated.";
}

// --- 2. SEND NOTIFICATION ---
if (isset($_POST['send_push'])) {
    $title = $_POST['title'];
    $msg = $_POST['message'];
    $url = $_POST['url'];
    $img = $_POST['image'];
    $target = $_POST['target']; // 'all', 'active', 'inactive'
    
    // Action Buttons (JSON)
    $buttons = [];
    if(!empty($_POST['btn1_text'])) $buttons[] = ['id' => 'btn1', 'text' => $_POST['btn1_text'], 'url' => $_POST['btn1_url']];
    if(!empty($_POST['btn2_text'])) $buttons[] = ['id' => 'btn2', 'text' => $_POST['btn2_text'], 'url' => $_POST['btn2_url']];

    $res = sendPushNotification($target, $title, $msg, $url, $img, $buttons);
    
    if($res['status']) {
        $success = "🚀 Broadcast Sent Successfully!";
        // Log it
        $db->prepare("INSERT INTO admin_logs (admin_id, action_type, description, ip_address) VALUES (?, 'BROADCAST', ?, ?)")
           ->execute([$_SESSION['user_id'], "Sent Push: $title", $_SERVER['REMOTE_ADDR']]);
    } else {
        $error = "Failed: " . $res['msg'];
    }
}

// Keys & Icons
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'onesignal_%'");
$os_settings = [];
while($row = $stmt->fetch()) { $os_settings[$row['setting_key']] = $row['setting_value']; }

$app_id = $os_settings['onesignal_app_id'] ?? '';
$api_key = $os_settings['onesignal_api_key'] ?? '';
$safari_id = $os_settings['onesignal_safari_id'] ?? '';

$site_logo = !empty($GLOBALS['settings']['site_logo']) ? "../assets/img/".$GLOBALS['settings']['site_logo'] : '';
$site_favicon = !empty($GLOBALS['settings']['site_favicon']) ? "../assets/img/".$GLOBALS['settings']['site_favicon'] : '../assets/img/logo.png';
$default_icon = $site_favicon; 
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;800&display=swap" rel="stylesheet">
<style>
    :root { --primary: #6366f1; --bg: #f8fafc; --card: #fff; --text: #0f172a; }
    body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }
    
    .page-header { background: #fff; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .ph-title h2 { margin: 0; font-weight: 800; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
    
    .main-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 30px; padding: 30px; max-width: 1600px; margin: 0 auto; }
    
    /* CARDS */
    .n-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; overflow: hidden; }
    .nc-head { padding: 20px; border-bottom: 1px solid #f1f5f9; background: #fcfcfd; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
    .nc-body { padding: 25px; }

    /* FORM */
    .form-group { margin-bottom: 20px; position: relative; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
    .form-input { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: inherit; transition: 0.2s; outline: none; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }
    
    .ai-btn {
        position: absolute; top: 0; right: 0; background: linear-gradient(135deg, #8b5cf6, #d946ef);
        color: white; border: none; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem;
        font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px;
        transition: 0.2s; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);
    }
    .ai-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(139, 92, 246, 0.4); }

    .btn-send { width: 100%; padding: 15px; background: var(--primary); color: white; font-size: 1.1rem; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4); }
    .btn-send:hover { background: #4f46e5; transform: translateY(-3px); }

    /* PREVIEW AREA */
    .preview-tabs { display: flex; gap: 10px; margin-bottom: 20px; justify-content: center; }
    .p-tab { padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; color: #64748b; border: 1px solid transparent; transition: 0.2s; }
    .p-tab.active { background: #eef2ff; color: var(--primary); border-color: #e0e7ff; }
    
    /* DEVICE FRAMES */
    .device-frame { margin: 0 auto; transition: 0.3s; display: none; }
    .device-frame.active { display: block; animation: fadeIn 0.5s; }

    /* ANDROID PREVIEW */
    .android-wrap {
        width: 320px; background: #fff; border-radius: 20px; border: 1px solid #e2e8f0;
        box-shadow: 0 20px 50px -10px rgba(0,0,0,0.15); overflow: hidden; margin: 0 auto;
    }
    .android-notif {
        padding: 15px; display: flex; gap: 15px; border-bottom: 1px solid #f1f5f9;
        background: #fff; position: relative;
    }
    .an-icon img { width: 40px; height: 40px; border-radius: 50%; }
    .an-content { flex: 1; }
    .an-header { display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
    .an-title { font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 2px; }
    .an-msg { font-size: 0.85rem; color: #334155; line-height: 1.4; }
    .an-img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-top: 10px; display: none; }
    .an-actions { display: flex; gap: 15px; margin-top: 10px; }
    .an-btn { font-size: 0.85rem; font-weight: 600; color: var(--primary); text-transform: uppercase; }

    /* IOS PREVIEW */
    .ios-wrap {
        width: 320px; background: url('https://images.unsplash.com/photo-1616077168712-fc6c788ce4c8?w=400&q=80') no-repeat center;
        background-size: cover; height: 450px; border-radius: 35px; border: 8px solid #1e293b;
        margin: 0 auto; position: relative; padding: 50px 15px;
    }
    .ios-glass {
        background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px);
        border-radius: 18px; padding: 12px; display: flex; gap: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .ios-icon img { width: 38px; height: 38px; border-radius: 8px; }

    /* ANIMATION */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    /* LOADING */
    .ai-loading { display:none; font-size:0.8rem; color:var(--primary); margin-right:5px; }
    .ai-loading i { animation: spin 1s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }

    @media(max-width: 1000px) { .main-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div class="ph-title">
        <h2>🔔 Push Broadcast Center</h2>
        <p style="margin:0; color:#64748b;">Send instant alerts to all subscribed devices (Web, Android, iOS).</p>
    </div>
    <button onclick="document.getElementById('configBox').style.display = document.getElementById('configBox').style.display=='none'?'block':'none'" class="btn-send" style="width:auto; padding:10px 20px; background:#fff; color:#333; border:1px solid #ddd; font-size:0.9rem;">
        <i class="fa-solid fa-gear"></i> API Config
    </button>
</div>

<div class="container-fluid" style="max-width:1600px; margin:0 auto;">
    
    <?php if($success): ?><div style="margin:20px; padding:15px; background:#dcfce7; color:#166534; border-radius:10px; font-weight:600;"><?= $success ?></div><?php endif; ?>
    <?php if($error): ?><div style="margin:20px; padding:15px; background:#fee2e2; color:#b91c1c; border-radius:10px; font-weight:600;">⚠️ <?= $error ?></div><?php endif; ?>

    <div id="configBox" style="display:<?= (empty($app_id)) ? 'block' : 'none' ?>; margin:20px;">
        <div class="n-card" style="background:#f0f9ff; border-color:#bae6fd;">
            <div class="nc-head">🔧 OneSignal Configuration</div>
            <div class="nc-body">
                <form method="POST">
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px;">
                        <div>
                            <label class="form-label">OneSignal App ID</label>
                            <input type="text" name="app_id" class="form-input" value="<?= sanitize($app_id) ?>" placeholder="e.g. 27391ec5..." required>
                        </div>
                        <div>
                            <label class="form-label">REST API Key (Not User Key)</label>
                            <input type="text" name="api_key" class="form-input" value="<?= sanitize($api_key) ?>" placeholder="e.g. os_v2_app_..." required>
                        </div>
                        <div>
                            <label class="form-label">Safari Web ID</label>
                            <input type="text" name="safari_id" class="form-input" value="<?= sanitize($safari_id) ?>" placeholder="web.onesignal.auto...">
                        </div>
                    </div>
                    <button type="submit" name="save_keys" class="btn-send" style="margin-top:15px; width:auto;">Save Configuration</button>
                </form>
            </div>
        </div>
    </div>

    <div class="main-grid">
        
        <div class="n-card">
            <div class="nc-head">
                <span>✍️ Compose Notification</span>
                <span style="font-size:0.8rem; color:#64748b;">AI Powered <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i></span>
            </div>
            <div class="nc-body">
                <form method="POST">
                    <input type="hidden" name="send_push" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Campaign Title</label>
                        <button type="button" class="ai-btn" onclick="generateAI('title')"><span class="ai-loading" id="load_title"><i class="fa-solid fa-spinner"></i></span> ✨ AI Write</button>
                        <input type="text" name="title" id="in_title" class="form-input" placeholder="e.g. 🔥 Flash Sale Started!" required oninput="updatePreview()">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message Content</label>
                        <button type="button" class="ai-btn" onclick="generateAI('message')"><span class="ai-loading" id="load_message"><i class="fa-solid fa-spinner"></i></span> ✨ AI Rewrite</button>
                        <textarea name="message" id="in_msg" class="form-input" rows="3" placeholder="e.g. Get 50% bonus on all deposits today..." required oninput="updatePreview()"></textarea>
                    </div>

                    <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div>
                            <label class="form-label">Target Audience</label>
                            <select name="target" class="form-input">
                                <option value="all">📢 All Subscribers (Recommended)</option>
                                <option value="active">🟢 Active Users (7 Days)</option>
                                <option value="inactive">💤 Inactive Users (30 Days)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Destination Link</label>
                            <input type="text" name="url" class="form-input" placeholder="https://..." value="<?= SITE_URL ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Big Image URL (Optional)</label>
                        <input type="text" name="image" id="in_img" class="form-input" placeholder="https://..." oninput="updatePreview()">
                    </div>

                    <label class="form-label" style="margin-top:20px; border-top:1px dashed #eee; padding-top:10px;">🔘 Action Buttons (Optional)</label>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div><input type="text" name="btn1_text" id="in_btn1" class="form-input" placeholder="Btn 1 Text (e.g. Buy)" oninput="updatePreview()"></div>
                        <div><input type="text" name="btn1_url" class="form-input" placeholder="Btn 1 URL"></div>
                        <div><input type="text" name="btn2_text" id="in_btn2" class="form-input" placeholder="Btn 2 Text (e.g. Cancel)" oninput="updatePreview()"></div>
                        <div><input type="text" name="btn2_url" class="form-input" placeholder="Btn 2 URL"></div>
                    </div>

                    <button type="submit" class="btn-send" style="margin-top:25px;">
                        🚀 Launch Campaign
                    </button>
                </form>
            </div>
        </div>

        <div class="n-card">
            <div class="nc-head">📱 Live Preview</div>
            <div class="nc-body" style="background:#f8fafc; min-height:400px; display:flex; flex-direction:column; justify-content:center;">
                
                <div class="preview-tabs">
                    <div class="p-tab active" onclick="setPreview('android', this)"><i class="fa-brands fa-android"></i> Android</div>
                    <div class="p-tab" onclick="setPreview('ios', this)"><i class="fa-brands fa-apple"></i> iOS</div>
                    <div class="p-tab" onclick="setPreview('windows', this)"><i class="fa-brands fa-windows"></i> Windows</div>
                </div>

                <div id="prev_android" class="device-frame active">
                    <div class="android-wrap">
                        <div class="android-notif">
                            <div class="an-icon"><img src="<?= $default_icon ?>" id="pv_icon"></div>
                            <div class="an-content">
                                <div class="an-header">
                                    <span><?= htmlspecialchars($GLOBALS['settings']['site_name']) ?> • now</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div class="an-title" id="pv_title">Example Title</div>
                                <div class="an-msg" id="pv_msg">Your notification message will appear here...</div>
                                <img src="" class="an-img" id="pv_img">
                                <div class="an-actions" id="pv_btns" style="display:none;">
                                    <span class="an-btn" id="pv_btn1">BUTTON 1</span>
                                    <span class="an-btn" id="pv_btn2">BUTTON 2</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="prev_ios" class="device-frame">
                    <div class="ios-wrap">
                        <div class="ios-glass">
                            <div class="ios-icon"><img src="<?= $default_icon ?>"></div>
                            <div style="flex:1; color:#000;">
                                <div style="display:flex; justify-content:space-between; font-size:0.8rem; opacity:0.6; margin-bottom:3px;">
                                    <span style="font-weight:600;"><?= htmlspecialchars($GLOBALS['settings']['site_name']) ?></span>
                                    <span>now</span>
                                </div>
                                <div style="font-weight:700; font-size:0.95rem; margin-bottom:2px;" id="pv_title_ios">Example Title</div>
                                <div style="font-size:0.9rem; line-height:1.3;" id="pv_msg_ios">Message content...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="prev_windows" class="device-frame">
                    <div style="width:340px; background:#1f1f1f; color:#fff; padding:15px; border-radius:8px; margin:0 auto; box-shadow:0 10px 30px rgba(0,0,0,0.3); display:flex; gap:15px; border:1px solid #333;">
                        <img src="<?= $default_icon ?>" style="width:48px; height:48px; object-fit:contain;">
                        <div>
                            <div style="font-weight:700; font-size:0.95rem; margin-bottom:5px;" id="pv_title_win">Example Title</div>
                            <div style="font-size:0.85rem; opacity:0.8;" id="pv_msg_win">Message content...</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
// --- PREVIEW LOGIC ---
function updatePreview() {
    let t = document.getElementById('in_title').value || 'Example Title';
    let m = document.getElementById('in_msg').value || 'Notification message...';
    let img = document.getElementById('in_img').value;
    let b1 = document.getElementById('in_btn1').value;
    let b2 = document.getElementById('in_btn2').value;

    // Update All Views
    ['pv_title', 'pv_title_ios', 'pv_title_win'].forEach(id => document.getElementById(id).innerText = t);
    ['pv_msg', 'pv_msg_ios', 'pv_msg_win'].forEach(id => document.getElementById(id).innerText = m);

    // Image Logic
    let imgEl = document.getElementById('pv_img');
    if(img) { imgEl.src = img; imgEl.style.display = 'block'; } else { imgEl.style.display = 'none'; }

    // Buttons Logic
    let btnDiv = document.getElementById('pv_btns');
    if(b1 || b2) {
        btnDiv.style.display = 'flex';
        document.getElementById('pv_btn1').innerText = b1;
        document.getElementById('pv_btn2').innerText = b2;
        document.getElementById('pv_btn1').style.display = b1 ? 'block' : 'none';
        document.getElementById('pv_btn2').style.display = b2 ? 'block' : 'none';
    } else {
        btnDiv.style.display = 'none';
    }
}

// --- TAB SWITCHER ---
function setPreview(mode, btn) {
    document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.device-frame').forEach(f => f.classList.remove('active'));
    document.getElementById('prev_'+mode).classList.add('active');
}

// --- AI GENERATOR ---
function generateAI(type) {
    const loader = document.getElementById('load_' + type);
    loader.style.display = 'inline-block';
    
    let prompt = (type === 'title') ? "Write a short catchy notification title (max 5 words) for SMM panel sale." 
                                    : "Write a short notification message (max 15 words) urging user to buy followers.";

    let formData = new FormData();
    formData.append('tool_id', 'caption'); // Reusing caption logic
    formData.append('user_input', prompt);

    fetch('../includes/ai_tool_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        loader.style.display = 'none';
        if(data.reply) {
            // Clean HTML tags if any
            let cleanText = data.reply.replace(/<[^>]*>?/gm, '');
            if(type === 'title') document.getElementById('in_title').value = cleanText;
            else document.getElementById('in_msg').value = cleanText;
            updatePreview();
        }
    })
    .catch(() => { loader.style.display = 'none'; alert('AI Error'); });
}
</script>

<?php include '_footer.php'; ?>