<?php
include '_header.php'; 
requireAdmin();

// --- 1. DEFAULT SETTINGS SETUP ---
$defaults = [
    // Branding
    'clr_primary' => '#4f46e5', 'clr_secondary' => '#7c3aed', 
    'clr_body' => '#f8fafc', 'clr_card' => '#ffffff', 
    'clr_text' => '#0f172a', 'clr_muted' => '#64748b',
    'clr_nav' => '#ffffff', 'clr_border' => '#e2e8f0',
    
    // UI Config
    'ui_font' => 'Outfit', 'ui_radius' => '16', 
    'ui_shadow' => '0.05', 'ui_glass' => '1',
    'btn_radius' => '12',
    
    // Floating Widget
    'float_status' => '0', 'float_app' => 'whatsapp', 
    'float_num' => '', 'float_msg' => 'Hi!', 'float_pos' => 'right',
    'float_color' => '#25D366',
    
    // Advanced
    'custom_css' => '', 'custom_js' => ''
];

// Auto-Insert Missing Keys
try {
    $existing = $db->query("SELECT setting_key FROM settings")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach($defaults as $key => $val) {
        if (!in_array($key, $existing)) $stmt->execute([$key, $val]);
    }
} catch(Exception $e) {}

// --- 2. SAVE HANDLER ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Images
    $uploads = ['site_logo', 'site_favicon', 'login_bg'];
    foreach($uploads as $f) {
        if (!empty($_FILES[$f]['name'])) {
            $name = $f . '_' . time() . '.' . pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION);
            if(move_uploaded_file($_FILES[$f]['tmp_name'], '../assets/img/' . $name)) {
                $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")->execute([$name, $f]);
            }
        }
    }
    // Text Settings
    $update = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
    foreach($_POST as $key => $val) {
        if(array_key_exists($key, $defaults)) $update->execute([$val, $key]);
    }
    $msg = "Theme Updated! 🚀";
}

// Fetch Data
$set = [];
$s_query = $db->query("SELECT * FROM settings");
while($r = $s_query->fetch()) $set[$r['setting_key']] = $r['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Theme Beast Editor</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root { --sidebar-w: 360px; --accent: #4f46e5; --dark: #0f172a; --border: #e2e8f0; }
    body { margin: 0; padding: 0; font-family: 'Manrope', sans-serif; background: #f1f5f9; overflow: hidden; height: 100vh; }

    /* LAYOUT */
    .studio-layout { display: flex; height: 100%; width: 100%; }

    /* --- SIDEBAR --- */
    .settings-panel {
        width: var(--sidebar-w); background: #fff; border-right: 1px solid var(--border);
        display: flex; flex-direction: column; z-index: 100; flex-shrink: 0;
    }
    
    .sp-header {
        padding: 15px 20px; border-bottom: 1px solid var(--border); background: #fff;
        display: flex; justify-content: space-between; align-items: center;
    }
    .sp-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 8px; }
    .sp-header a { color: #64748b; text-decoration: none; font-size: 0.9rem; }

    .sp-content { flex: 1; overflow-y: auto; padding: 0; scrollbar-width: thin; }
    
    /* ACCORDION SECTIONS */
    .section-group { border-bottom: 1px solid var(--border); }
    .sg-title {
        padding: 15px 20px; background: #f8fafc; cursor: pointer; font-weight: 700; color: #334155;
        font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .sg-title:hover { background: #f1f5f9; color: var(--accent); }
    .sg-body { padding: 20px; display: none; background: #fff; }
    .sg-body.open { display: block; animation: slideDown 0.2s ease-out; }

    /* CONTROLS */
    .control-row { margin-bottom: 15px; }
    .control-label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 6px; }
    
    .color-picker-wrapper {
        display: flex; align-items: center; border: 1px solid var(--border); border-radius: 8px; padding: 4px; gap: 10px;
    }
    input[type="color"] { border: none; width: 35px; height: 35px; border-radius: 6px; cursor: pointer; background: none; padding: 0; }
    input[type="text"], select, input[type="number"] {
        border: none; outline: none; font-size: 0.9rem; color: var(--dark); width: 100%; font-family: monospace; background: transparent;
    }
    .text-input { border: 1px solid var(--border); padding: 10px; border-radius: 8px; width: 100%; box-sizing: border-box; }
    
    /* FILE UPLOAD */
    .file-drop {
        border: 2px dashed var(--border); border-radius: 8px; padding: 15px; text-align: center;
        font-size: 0.8rem; color: #64748b; cursor: pointer; transition: 0.2s; position: relative;
    }
    .file-drop:hover { border-color: var(--accent); background: #eef2ff; color: var(--accent); }
    .file-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

    /* FOOTER */
    .sp-footer {
        padding: 20px; border-top: 1px solid var(--border); background: #fff;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.05); z-index: 10;
    }
    .btn-save {
        width: 100%; padding: 14px; background: linear-gradient(135deg, #4f46e5, #4338ca); color: white;
        border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer;
        display: flex; justify-content: center; align-items: center; gap: 8px;
        transition: 0.3s; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4); }

    /* --- PREVIEW AREA --- */
    .preview-stage { flex: 1; display: flex; flex-direction: column; background: #e2e8f0; position: relative; }
    
    .toolbar {
        height: 50px; background: #fff; border-bottom: 1px solid var(--border); display: flex;
        justify-content: center; align-items: center; gap: 20px;
    }
    .device-btn {
        background: none; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer; padding: 8px; transition: 0.2s;
    }
    .device-btn.active { color: var(--accent); transform: scale(1.1); }
    
    .iframe-wrapper { flex: 1; display: flex; justify-content: center; align-items: center; overflow: hidden; padding: 20px; }
    .device-frame {
        background: #fff; border-radius: 12px; border: 1px solid #d1d5db;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15); transition: 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        width: 100%; height: 100%;
    }
    
    /* Device Sizes */
    .device-frame.desktop { width: 100%; height: 100%; border-radius: 8px; }
    .device-frame.tablet { width: 768px; height: 95%; border: 10px solid #1e293b; border-radius: 20px; }
    .device-frame.mobile { width: 375px; height: 90%; border: 10px solid #1e293b; border-radius: 30px; }

    iframe { width: 100%; height: 100%; border: none; border-radius: inherit; background: white; }

    /* TOAST */
    .toast {
        position: fixed; top: 20px; right: 20px; background: #10b981; color: white;
        padding: 12px 20px; border-radius: 8px; font-weight: 600; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: none; animation: slideIn 0.3s ease; z-index: 9999;
    }
    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    @keyframes slideDown { from { height: 0; opacity: 0; } to { height: auto; opacity: 1; } }
</style>
</head>
<body>

<form method="POST" enctype="multipart/form-data" class="studio-layout">
    
    <div class="settings-panel">
        <div class="sp-header">
            <h3>🎨 Theme Beast</h3>
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Exit</a>
        </div>

        <div class="sp-content">
            
            <div class="section-group">
                <div class="sg-title" onclick="toggleSection(this)">🌍 Global Colors <i class="fa-solid fa-chevron-down"></i></div>
                <div class="sg-body open">
                    <div class="control-row">
                        <label class="control-label">Primary Brand Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_primary" id="clr_primary" value="<?= $set['clr_primary'] ?>" oninput="updateVar('--primary', this.value)">
                            <input type="text" value="<?= $set['clr_primary'] ?>" onchange="document.getElementById('clr_primary').value=this.value">
                        </div>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Secondary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_secondary" id="clr_secondary" value="<?= $set['clr_secondary'] ?>" oninput="updateVar('--secondary', this.value)">
                            <input type="text" value="<?= $set['clr_secondary'] ?>">
                        </div>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Page Background</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_body" id="clr_body" value="<?= $set['clr_body'] ?>" oninput="updateVar('--bg-body', this.value)">
                            <input type="text" value="<?= $set['clr_body'] ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-group">
                <div class="sg-title" onclick="toggleSection(this)">💠 UI Components <i class="fa-solid fa-chevron-down"></i></div>
                <div class="sg-body">
                    <div class="control-row">
                        <label class="control-label">Card Background</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_card" id="clr_card" value="<?= $set['clr_card'] ?>" oninput="updateVar('--card-bg', this.value)">
                            <input type="text" value="<?= $set['clr_card'] ?>">
                        </div>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Navbar Background</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_nav" id="clr_nav" value="<?= $set['clr_nav'] ?>" oninput="updateVar('--nav-bg', this.value)">
                            <input type="text" value="<?= $set['clr_nav'] ?>">
                        </div>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Text Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="clr_text" id="clr_text" value="<?= $set['clr_text'] ?>" oninput="updateVar('--text-main', this.value)">
                            <input type="text" value="<?= $set['clr_text'] ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-group">
                <div class="sg-title" onclick="toggleSection(this)">📐 Shapes & Fonts <i class="fa-solid fa-chevron-down"></i></div>
                <div class="sg-body">
                    <div class="control-row">
                        <label class="control-label">Font Family</label>
                        <select name="ui_font" class="text-input" onchange="reloadFrame()">
                            <option value="Outfit" <?= ($set['ui_font']=='Outfit')?'selected':'' ?>>Outfit (Modern Round)</option>
                            <option value="Inter" <?= ($set['ui_font']=='Inter')?'selected':'' ?>>Inter (Clean)</option>
                            <option value="Poppins" <?= ($set['ui_font']=='Poppins')?'selected':'' ?>>Poppins (Bold)</option>
                            <option value="Roboto" <?= ($set['ui_font']=='Roboto')?'selected':'' ?>>Roboto (Standard)</option>
                        </select>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Corner Roundness (px)</label>
                        <input type="range" name="ui_radius" min="0" max="30" value="<?= $set['ui_radius'] ?>" style="width:100%" oninput="updateVar('--radius', this.value+'px')">
                    </div>
                    <div class="control-row">
                        <label class="control-label">Glass Effect</label>
                        <select name="ui_glass" class="text-input" onchange="reloadFrame()">
                            <option value="1" <?= ($set['ui_glass']=='1')?'selected':'' ?>>Enabled (Blurry)</option>
                            <option value="0" <?= ($set['ui_glass']=='0')?'selected':'' ?>>Disabled (Flat)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-group">
                <div class="sg-title" onclick="toggleSection(this)">💬 Floating Widget <i class="fa-solid fa-chevron-down"></i></div>
                <div class="sg-body">
                    <div class="control-row">
                        <label class="control-label">Enable Widget?</label>
                        <select name="float_status" class="text-input">
                            <option value="1" <?= ($set['float_status']=='1')?'selected':'' ?>>Yes, Show it</option>
                            <option value="0" <?= ($set['float_status']=='0')?'selected':'' ?>>No, Hide it</option>
                        </select>
                    </div>
                    <div class="control-row">
                        <label class="control-label">App Type</label>
                        <select name="float_app" class="text-input">
                            <option value="whatsapp" <?= ($set['float_app']=='whatsapp')?'selected':'' ?>>WhatsApp</option>
                            <option value="telegram" <?= ($set['float_app']=='telegram')?'selected':'' ?>>Telegram</option>
                        </select>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Number / Username</label>
                        <input type="text" name="float_num" class="text-input" value="<?= $set['float_num'] ?>" placeholder="923001234567">
                    </div>
                    <div class="control-row">
                        <label class="control-label">Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" name="float_color" value="<?= $set['float_color'] ?? '#25D366' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-group">
                <div class="sg-title" onclick="toggleSection(this)">🖼️ Brand Assets <i class="fa-solid fa-chevron-down"></i></div>
                <div class="sg-body">
                    <div class="control-row">
                        <label class="control-label">Logo</label>
                        <label class="file-drop">
                            <input type="file" name="site_logo">
                            <span>Click to Upload Logo</span>
                        </label>
                    </div>
                    <div class="control-row">
                        <label class="control-label">Favicon</label>
                        <label class="file-drop">
                            <input type="file" name="site_favicon">
                            <span>Click to Upload Icon</span>
                        </label>
                    </div>
                </div>
            </div>

        </div>

        <div class="sp-footer">
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-floppy-disk"></i> Save & Publish Theme
            </button>
        </div>
    </div>

    <div class="preview-stage">
        <div class="toolbar">
            <button type="button" class="device-btn active" onclick="setDevice('desktop', this)"><i class="fa-solid fa-desktop"></i></button>
            <button type="button" class="device-btn" onclick="setDevice('tablet', this)"><i class="fa-solid fa-tablet-screen-button"></i></button>
            <button type="button" class="device-btn" onclick="setDevice('mobile', this)"><i class="fa-solid fa-mobile-screen"></i></button>
            <span style="color:#cbd5e1;">|</span>
            <button type="button" class="device-btn" onclick="reloadFrame()" title="Refresh Preview"><i class="fa-solid fa-rotate-right"></i></button>
        </div>
        
        <div class="iframe-wrapper">
            <div id="deviceFrame" class="device-frame desktop">
                <iframe id="previewFrame" src="../user/index.php"></iframe>
            </div>
        </div>
    </div>

</form>

<?php if($msg): ?>
<div class="toast" id="toast">✅ Changes Saved Successfully!</div>
<script>
    document.getElementById('toast').style.display = 'block';
    setTimeout(()=>{ document.getElementById('toast').style.display='none'; }, 3000);
</script>
<?php endif; ?>

<script>
// --- ACCORDION LOGIC ---
function toggleSection(el) {
    let body = el.nextElementSibling;
    body.classList.toggle('open');
    let icon = el.querySelector('i');
    icon.classList.toggle('fa-chevron-down');
    icon.classList.toggle('fa-chevron-up');
}

// --- DEVICE TOGGLE ---
function setDevice(type, btn) {
    document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const frame = document.getElementById('deviceFrame');
    frame.className = 'device-frame ' + type;
}

// --- LIVE CSS UPDATE ---
function updateVar(name, value) {
    const frame = document.getElementById('previewFrame');
    const doc = frame.contentDocument || frame.contentWindow.document;
    if(doc) {
        doc.documentElement.style.setProperty(name, value);
    }
}

function reloadFrame() {
    document.getElementById('previewFrame').contentWindow.location.reload();
}

// Apply settings on load
window.onload = function() {
    // Wait for iframe to load
    const frame = document.getElementById('previewFrame');
    frame.onload = function() {
        updateVar('--primary', document.getElementById('clr_primary').value);
        updateVar('--secondary', document.getElementById('clr_secondary').value);
        updateVar('--bg-body', document.getElementById('clr_body').value);
        // ... more can be added
    };
};
</script>

</body>
</html>