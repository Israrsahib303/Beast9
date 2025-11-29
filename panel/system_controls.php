<?php
include '_header.php';

$success = '';
$error = '';

// --- 1. SAVE SECURITY SETTINGS ---
if (isset($_POST['save_security'])) {
    $otp = isset($_POST['otp_enabled']) ? '1' : '0';
    $maint = isset($_POST['maintenance_mode']) ? '1' : '0';
    
    try {
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'otp_enabled'")->execute([$otp]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'")->execute([$maint]);
        
        $success = "Security settings updated!";
        $GLOBALS['settings']['otp_enabled'] = $otp;
        $GLOBALS['settings']['maintenance_mode'] = $maint;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- 2. SAVE SEO SETTINGS ---
if (isset($_POST['save_seo'])) {
    $title = sanitize($_POST['seo_title']);
    $desc = sanitize($_POST['seo_desc']);
    $keys = sanitize($_POST['seo_keywords']);
    
    try {
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'seo_title'")->execute([$title]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'seo_desc'")->execute([$desc]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'seo_keywords'")->execute([$keys]);
        
        $success = "SEO settings updated!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Fresh Data
$s = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
while($row = $stmt->fetch()){ $s[$row['setting_key']] = $row['setting_value']; }
?>

<style>
/* --- 🌌 ULTIMATE CONTROL PANEL CSS --- */
:root {
    --primary: #4f46e5;
    --primary-glow: rgba(79, 70, 229, 0.4);
    --dark-bg: #f8fafc;
    --card-bg: #ffffff;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --border: #e2e8f0;
    
    --gradient-sec: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    --gradient-seo: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    --gradient-db:  linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
}

body { background: var(--dark-bg); }

.control-wrapper {
    width: 80%; /* Requested Change */
    max-width: 1800px;
    margin: 2rem auto;
}

/* Header Section */
.page-header {
    display: flex; justify-content: space-between; align-items: center;
    background: #fff; padding: 2rem; border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);
    margin-bottom: 2.5rem;
}
.header-title h1 { font-size: 2rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -1px; }
.header-title p { color: var(--text-muted); margin-top: 5px; font-size: 1rem; }

.status-indicator {
    display: flex; gap: 15px;
}
.stat-badge {
    padding: 8px 16px; background: #f1f5f9; border-radius: 12px;
    font-size: 0.85rem; font-weight: 600; color: var(--text-muted);
    display: flex; align-items: center; gap: 8px;
}
.dot { width: 8px; height: 8px; border-radius: 50%; }
.dot.green { background: #10b981; box-shadow: 0 0 10px #10b981; }
.dot.red { background: #ef4444; box-shadow: 0 0 10px #ef4444; }

/* Grid System */
.panel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

/* Premium Cards */
.sys-card {
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
    overflow: hidden;
    display: flex; flex-direction: column;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    width: 70%;
}
.sys-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
    border-color: #cbd5e1;
}

/* Card Header */
.card-head {
    padding: 1.8rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 1.2rem;
    background: radial-gradient(circle at top right, #f8fafc, transparent);
}

.head-icon {
    width: 55px; height: 55px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; color: white;
    box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
}
.icon-sec { background: var(--gradient-sec); box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4); }
.icon-seo { background: var(--gradient-seo); box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.4); }
.icon-db { background: var(--gradient-db); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); }

.head-text h3 { margin: 0; font-size: 1.3rem; font-weight: 800; color: var(--text-main); }
.head-text span { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

.card-body { padding: 2rem; flex-grow: 1; }

/* Toggle Switches (Big & Modern) */
.toggle-item {
    background: #fff; border: 1px solid var(--border);
    padding: 1.2rem; border-radius: 16px; margin-bottom: 1.2rem;
    display: flex; justify-content: space-between; align-items: center;
    transition: 0.2s;
}
.toggle-item:hover { border-color: #94a3b8; background: #f8fafc; }

.toggle-info strong { display: block; font-size: 1.05rem; color: var(--text-main); margin-bottom: 4px; }
.toggle-info span { font-size: 0.85rem; color: var(--text-muted); }

/* The Switch */
.switch { position: relative; display: inline-block; width: 64px; height: 34px; flex-shrink: 0; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #e2e8f0; transition: .4s; border-radius: 34px;
}
.slider:before {
    position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px;
    background-color: white; transition: .4s; border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
input:checked + .slider { background-color: #10b981; }
input:checked + .slider:before { transform: translateX(30px); }

/* Backup Buttons */
.backup-actions { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
.btn-bk {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 1.5rem 1rem; border-radius: 16px; text-decoration: none; transition: 0.3s;
    border: 1px solid transparent; position: relative; overflow: hidden;
}
.btn-bk i { font-size: 2rem; margin-bottom: 10px; transition: 0.3s; }
.btn-bk span { font-weight: 700; font-size: 0.95rem; }
.btn-bk small { font-size: 0.75rem; opacity: 0.8; margin-top: 4px; }

.bk-sql { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.bk-sql:hover { background: #2563eb; color: white; transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4); }

.bk-zip { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.bk-zip:hover { background: #16a34a; color: white; transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(22, 163, 74, 0.4); }

.bk-tree { background: #fff7ed; color: #ea580c; border-color: #fed7aa; }
.bk-tree:hover { background: #ea580c; color: white; transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(234, 88, 12, 0.4); }

/* Inputs */
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-weight: 700; margin-bottom: 8px; color: #475569; font-size: 0.9rem; }
.form-input {
    width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px;
    font-size: 0.95rem; outline: none; transition: 0.3s; background: #f8fafc;
}
.form-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }

/* Save Button */
.btn-save {
    width: 100%; padding: 15px; border-radius: 14px; font-weight: 800; font-size: 1rem;
    border: none; cursor: pointer; background: #1e293b; color: white;
    transition: 0.3s; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px;
}
.btn-save:hover { background: #0f172a; transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3); }

/* Alerts */
.alert { padding: 16px; border-radius: 12px; margin-bottom: 2rem; font-weight: 600; display: flex; align-items: center; gap: 10px; animation: slideDown 0.4s ease; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
    .control-wrapper { width: 95%; padding: 1rem; }
    .backup-actions { grid-template-columns: 1fr; }
    .header-title h1 { font-size: 1.5rem; }
    .status-indicator { display: none; }
}
</style>

<div class="control-wrapper">

    <div class="page-header">
        <div class="header-title">
            <h1>🚀 System Control Center</h1>
            <p>Master controls for Security, Backups & SEO configuration.</p>
        </div>
        <div class="status-indicator">
            <div class="stat-badge"><div class="dot <?= ($s['otp_enabled']??'1')=='1'?'green':'red' ?>"></div> OTP System</div>
            <div class="stat-badge"><div class="dot <?= ($s['maintenance_mode']??'0')=='1'?'red':'green' ?>"></div> Maintenance</div>
        </div>
    </div>

    <?php if($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div><?php endif; ?>

    <div class="panel-grid">

        <div class="sys-card">
            <div class="card-head">
                <div class="head-icon icon-sec"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="head-text">
                    <h3>Security Shield</h3>
                    <span>Access Protection</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <strong>OTP Login System</strong>
                            <span>Require email code for login</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="otp_enabled" <?= ($s['otp_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-item">
                        <div class="toggle-info">
                            <strong>Maintenance Mode</strong>
                            <span>Block users, allow admins only</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" <?= ($s['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <button type="submit" name="save_security" class="btn-save" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
                        Update Security
                    </button>
                </form>
            </div>
        </div>

        <div class="sys-card">
            <div class="card-head">
                <div class="head-icon icon-db"><i class="fa-solid fa-server"></i></div>
                <div class="head-text">
                    <h3>Backup Center</h3>
                    <span>Disaster Recovery</span>
                </div>
            </div>
            <div class="card-body">
                <div class="backup-actions">
                    <a href="db_backup.php" class="btn-bk bk-sql">
                        <i class="fa-solid fa-database"></i>
                        <span>SQL Data</span>
                        <small>Database Only</small>
                    </a>
                    <a href="full_backup.php" class="btn-bk bk-zip" onclick="return confirm('⚠️ Generating Full Backup takes time (30-60s).\nDo not close the tab until download starts.');">
                        <i class="fa-solid fa-file-zipper"></i>
                        <span>Full Site</span>
                        <small>All Files (Zip)</small>
                    </a>
                    <a href="generate_tree.php" target="_blank" class="btn-bk bk-tree">
                        <i class="fa-solid fa-folder-tree"></i>
                        <span>File Tree</span>
                        <small>Structure View</small>
                    </a>
                </div>
                <p style="text-align:center; color:#94a3b8; font-size:0.8rem; margin-top:20px; font-weight:500;">
                    <i class="fa-solid fa-clock"></i> Last Auto-Backup: <b>Not Configured</b>
                </p>
            </div>
        </div>

        <div class="sys-card" style="grid-column: 1 / -1;">
            <div class="card-head">
                <div class="head-icon icon-seo"><i class="fa-solid fa-magnifying-glass"></i></div>
                <div class="head-text">
                    <h3>SEO Manager</h3>
                    <span>Search Engine Optimization</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                        <div class="form-group">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="seo_title" class="form-input" value="<?= sanitize($s['seo_title'] ?? '') ?>" placeholder="My Website Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keywords</label>
                            <input type="text" name="seo_keywords" class="form-input" value="<?= sanitize($s['seo_keywords'] ?? '') ?>" placeholder="keyword1, keyword2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="seo_desc" class="form-input" rows="2" placeholder="Short description for Google..."><?= sanitize($s['seo_desc'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="save_seo" class="btn-save" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        Update SEO Config
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include '_footer.php'; ?>