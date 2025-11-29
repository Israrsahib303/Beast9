<?php
include '_header.php';

$success = '';
$error = '';

// --- SAVE SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_vpn'])) {
    try {
        $enabled = isset($_POST['vpn_check_enabled']) ? '1' : '0';
        $api_key = trim($_POST['vpn_api_key']);
        $msg = trim($_POST['vpn_block_msg']);

        // Update DB
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vpn_check_enabled'")->execute([$enabled]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vpn_api_key'")->execute([$api_key]);
        $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vpn_block_msg'")->execute([$msg]);

        $success = "VPN Security settings updated!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- FETCH SETTINGS ---
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('vpn_check_enabled', 'vpn_api_key', 'vpn_block_msg')");
$vpn = [];
while ($row = $stmt->fetch()) {
    $vpn[$row['setting_key']] = $row['setting_value'];
}
?>

<style>
/* --- 🛡️ SECURITY PAGE CSS --- */
:root {
    --sec-primary: #ef4444; /* Red for Security */
    --sec-dark: #b91c1c;
    --bg-card: #ffffff;
    --text-main: #1f2937;
}

.admin-card {
    background: var(--bg-card); border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb; overflow: hidden;
    max-width: 800px; margin: 0 auto;
}

.card-header {
    padding: 1.5rem; background: #fff1f2; border-bottom: 1px solid #fecdd3;
    display: flex; align-items: center; gap: 15px;
}
.header-icon {
    width: 50px; height: 50px; background: var(--sec-primary); color: white;
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
}

.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-weight: 700; color: #374151; margin-bottom: 8px; font-size: 0.95rem; }
.form-input {
    width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px;
    font-size: 1rem; outline: none; transition: 0.3s;
}
.form-input:focus { border-color: var(--sec-primary); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }

/* Switch */
.switch { position: relative; display: inline-block; width: 60px; height: 34px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc; transition: .4s; border-radius: 34px;
}
.slider:before {
    position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px;
    background-color: white; transition: .4s; border-radius: 50%;
}
input:checked + .slider { background-color: var(--sec-primary); }
input:checked + .slider:before { transform: translateX(26px); }

.btn-save {
    width: 100%; padding: 14px; background: var(--sec-primary); color: white;
    border: none; border-radius: 10px; font-weight: 700; font-size: 1rem;
    cursor: pointer; transition: 0.3s;
}
.btn-save:hover { background: var(--sec-dark); transform: translateY(-2px); }

.info-box {
    background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
    padding: 1rem; border-radius: 10px; font-size: 0.9rem; margin-bottom: 1.5rem;
}
</style>

<div style="padding: 2rem;">

    <div class="admin-card">
        <div class="card-header">
            <div class="header-icon"><i class="fa-solid fa-shield-virus"></i></div>
            <div>
                <h2 style="margin:0; color:#881337;">VPN & Proxy Protection</h2>
                <p style="margin:5px 0 0 0; color:#9f1239; font-size:0.9rem;">Block malicious traffic automatically</p>
            </div>
        </div>

        <div style="padding: 2rem;">
            
            <?php if($success): ?><div style="padding:15px; background:#d1fae5; color:#065f46; border-radius:8px; margin-bottom:20px;">✅ <?= $success ?></div><?php endif; ?>
            <?php if($error): ?><div style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px;">⚠️ <?= $error ?></div><?php endif; ?>

            <div class="info-box">
                <i class="fa-solid fa-circle-info"></i> 
                <strong>Note:</strong> We use <b>ProxyCheck.io</b>. Free plan allows 1,000 checks/day. For higher traffic, enter your API Key below.
            </div>

            <form method="POST">
                
                <div style="display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:15px; border-radius:12px; margin-bottom:1.5rem; border:1px solid #e5e7eb;">
                    <div>
                        <strong style="display:block; color:#1f2937; font-size:1.1rem;">Enable Protection</strong>
                        <small style="color:#6b7280;">Block users accessing via VPN/Proxy</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="vpn_check_enabled" <?= ($vpn['vpn_check_enabled'] == '1') ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">API Key (Optional but Recommended)</label>
                    <input type="text" name="vpn_api_key" class="form-input" value="<?= htmlspecialchars($vpn['vpn_api_key']) ?>" placeholder="Enter ProxyCheck.io API Key">
                    <small style="color:#6b7280;">Leave empty to use Free Tier (1000 daily limit).</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Block Screen Message</label>
                    <textarea name="vpn_block_msg" class="form-input" rows="3" required><?= htmlspecialchars($vpn['vpn_block_msg']) ?></textarea>
                    <small style="color:#6b7280;">This message will be shown to blocked users.</small>
                </div>

                <button type="submit" name="update_vpn" class="btn-save">
                    <i class="fa-solid fa-save"></i> Save Security Settings
                </button>

            </form>
        </div>
    </div>

</div>

<?php include '_footer.php'; ?>