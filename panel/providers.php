<?php
include '_header.php';
requireAdmin();
require_once __DIR__ . '/../includes/smm_api.class.php';

$error = '';
$success = '';

// --- ACTIONS ---

// 1. Add/Edit Provider
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    $key = sanitize($_POST['key']);
    $margin = (float)$_POST['margin'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE smm_providers SET name=?, api_url=?, api_key=?, profit_margin=?, is_active=? WHERE id=?");
        if ($stmt->execute([$name, $url, $key, $margin, $is_active, $id])) {
            $success = "Provider updated successfully!";
        } else {
            $error = "Failed to update provider.";
        }
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO smm_providers (name, api_url, api_key, profit_margin, is_active) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $url, $key, $margin, $is_active])) {
            $success = "New provider added successfully!";
        } else {
            $error = "Failed to add provider.";
        }
    }
}

// 2. Delete Provider (SMART SAFE DELETE)
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    
    try {
        // SAFETY TRICK: Foreign Key Checks ko Temporary Band karein
        // Is se hum Services delete kar payenge bina Orders ko delete kiye
        $db->query("SET FOREIGN_KEY_CHECKS = 0");

        // Step A: Delete all services linked to this provider
        $db->prepare("DELETE FROM smm_services WHERE provider_id = ?")->execute([$id]);

        // Step B: Clean up Categories (Jo Categories ab khaali hain unhein uda dein)
        // Logic: Delete category IF uska naam kisi bhi active service mein nahi hai
        $db->query("DELETE FROM smm_categories WHERE name NOT IN (SELECT DISTINCT category FROM smm_services)");

        // Step C: Delete the Provider
        $db->prepare("DELETE FROM smm_providers WHERE id = ?")->execute([$id]);

        // Re-enable Foreign Key Checks (Security wapas on)
        $db->query("SET FOREIGN_KEY_CHECKS = 1");

        $success = "Provider and Services deleted. Order History is SAFE.";
        
    } catch (Exception $e) {
        // Error aane par bhi checks wapas on karein
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        $error = "Error deleting provider: " . $e->getMessage();
    }
}

// 3. Toggle Status
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $current = $db->query("SELECT is_active FROM smm_providers WHERE id=$id")->fetchColumn();
    $new = $current ? 0 : 1;
    $db->prepare("UPDATE smm_providers SET is_active=? WHERE id=?")->execute([$new, $id]);
    echo "<script>window.location.href='providers.php';</script>";
}

// --- FETCH DATA ---
$providers = $db->query("SELECT * FROM smm_providers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* --- UI STYLES --- */
.panel-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
    background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb;
}
.panel-header h2 { margin: 0; color: #1f2937; font-size: 1.5rem; }

.btn-new {
    background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none;
    font-weight: 600; transition: 0.2s; display: flex; align-items: center; gap: 5px; cursor: pointer; border: none;
}
.btn-new:hover { background: #1d4ed8; }

.provider-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;
}

.provider-card {
    background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: 0.3s; position: relative;
}
.provider-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); border-color: #2563eb; }

.card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
.prov-name { font-size: 1.1rem; font-weight: 700; color: #111; margin: 0; }
.prov-url { font-size: 0.8rem; color: #6b7280; word-break: break-all; }

.status-toggle {
    cursor: pointer; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    text-decoration: none; display: inline-block;
}
.st-active { background: #dcfce7; color: #15803d; }
.st-inactive { background: #fee2e2; color: #991b1b; }

.info-row { display: flex; gap: 15px; margin-bottom: 20px; background: #f9fafb; padding: 10px; border-radius: 8px; }
.info-item span { display: block; font-size: 0.7rem; color: #6b7280; font-weight: 600; text-transform: uppercase; }
.info-item b { color: #1f2937; font-size: 0.9rem; }

.actions { display: flex; gap: 10px; border-top: 1px solid #f3f4f6; padding-top: 15px; }
.btn-act {
    flex: 1; padding: 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;
    text-align: center; cursor: pointer; border: 1px solid transparent; text-decoration: none;
}
.btn-edit { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
.btn-delete { background: #fef2f2; color: #b91c1c; border-color: #fee2e2; }
.btn-check { background: #f0fdf4; color: #15803d; border-color: #dcfce7; }

/* Modal */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
}
.modal-overlay.active { display: flex; }
.modal-box { background: #fff; width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
.form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
</style>

<div class="admin-container">
    
    <div class="panel-header">
        <h2>🔌 API Providers</h2>
        <button class="btn-new" onclick="openModal()">+ Add New Provider</button>
    </div>

    <?php if ($error): ?><div class="message error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="message success"><?= $success ?></div><?php endif; ?>

    <div class="provider-grid">
        <?php if (empty($providers)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:40px; background:#fff; border-radius:10px;">
                No providers added yet. Click "Add New Provider".
            </div>
        <?php else: ?>
            <?php foreach ($providers as $p): ?>
                <div class="provider-card">
                    <div class="card-top">
                        <div>
                            <h3 class="prov-name"><?= sanitize($p['name']) ?></h3>
                            <span class="prov-url"><?= sanitize($p['api_url']) ?></span>
                        </div>
                        <a href="providers.php?toggle_id=<?= $p['id'] ?>" 
                           class="status-toggle <?= $p['is_active'] ? 'st-active' : 'st-inactive' ?>">
                           <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                        </a>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-item">
                            <span>Margin</span>
                            <b><?= $p['profit_margin'] ?>%</b>
                        </div>
                        <div class="info-item">
                            <span>Key</span>
                            <b title="<?= $p['api_key'] ?>"><?= substr($p['api_key'], 0, 10) ?>...</b>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <a href="provider_check.php" class="btn-act btn-check">💰 Balance</a>
                        <button class="btn-act btn-edit" onclick='editProvider(<?= json_encode($p) ?>)'>✏️ Edit</button>
                        <a href="providers.php?delete_id=<?= $p['id'] ?>" class="btn-act btn-delete" onclick="return confirm('⚠️ DELETE WARNING:\n\nThis will remove the provider AND its services.\nUser Orders will remain SAFE in history.\n\nAre you sure?')">🗑️ Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<div class="modal-overlay" id="provModal">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0;">Add Provider</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="id" id="p_id">
            
            <div class="form-group">
                <label>Provider Name</label>
                <input type="text" name="name" id="p_name" class="form-control" placeholder="e.g. SMMKing" required>
            </div>
            
            <div class="form-group">
                <label>API URL</label>
                <input type="url" name="url" id="p_url" class="form-control" placeholder="https://example.com/api/v2" required>
            </div>
            
            <div class="form-group">
                <label>API Key</label>
                <input type="text" name="key" id="p_key" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Profit Margin (%)</label>
                <input type="number" name="margin" id="p_margin" class="form-control" value="10" required>
                <small style="color:#666;">We will increase prices by this %.</small>
            </div>
            
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_active" id="p_active" checked>
                    Active
                </label>
            </div>
            
            <button class="btn-new" style="width:100%; justify-content:center;">Save Provider</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'Add New Provider';
    document.getElementById('p_id').value = '';
    document.getElementById('p_name').value = '';
    document.getElementById('p_url').value = '';
    document.getElementById('p_key').value = '';
    document.getElementById('p_margin').value = '10';
    document.getElementById('p_active').checked = true;
    document.getElementById('provModal').classList.add('active');
}

function editProvider(data) {
    document.getElementById('modalTitle').innerText = 'Edit Provider';
    document.getElementById('p_id').value = data.id;
    document.getElementById('p_name').value = data.name;
    document.getElementById('p_url').value = data.api_url;
    document.getElementById('p_key').value = data.api_key;
    document.getElementById('p_margin').value = data.profit_margin;
    document.getElementById('p_active').checked = (data.is_active == 1);
    document.getElementById('provModal').classList.add('active');
}

function closeModal() {
    document.getElementById('provModal').classList.remove('active');
}
</script>

<?php include '_footer.php'; ?>