<?php
include '_header.php';
require_once __DIR__ . '/../includes/smm_api.class.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = $_GET['success'] ?? '';

// =========================================================
//      🔧 CORE LOGIC (UNCHANGED)
// =========================================================

// --- 1. DELETE ALL ---
if ($action == 'delete_all') {
    if (isAdmin()) {
        try {
            $db->query("SET FOREIGN_KEY_CHECKS = 0");
            $db->query("TRUNCATE TABLE smm_services");
            $db->query("TRUNCATE TABLE smm_categories");
            $db->query("SET FOREIGN_KEY_CHECKS = 1");
            $success = "🗑️ All services & categories wiped successfully.";
            echo "<script>window.location.href='smm_services.php?success=" . urlencode($success) . "';</script>";
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
}

// --- 2. DELETE SINGLE SERVICE ---
if ($action == 'delete_service' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->prepare("UPDATE smm_services SET is_active=0, manually_deleted=1 WHERE id=?")->execute([$id]);
    $success = "Service removed.";
}

// --- 3. DELETE CATEGORY ---
if ($action == 'delete_category' && isset($_GET['cat'])) {
    $cat = urldecode($_GET['cat']);
    $db->prepare("UPDATE smm_services SET is_active=0, manually_deleted=1 WHERE category=?")->execute([$cat]);
    $success = "Category removed.";
}

// --- 4. FETCH CATEGORIES FOR IMPORT (STEP 1) ---
if ($action == 'sync_step1') {
    try {
        $stmt = $db->query("SELECT * FROM smm_providers WHERE is_active=1 LIMIT 1");
        $provider = $stmt->fetch();
        
        if ($provider) {
            $api = new SmmApi($provider['api_url'], $provider['api_key']);
            $res = $api->getServices();

            if ($res['success']) {
                $categories = [];
                foreach ($res['services'] as $s) {
                    if (!empty($s['category'])) {
                        $categories[$s['category']] = true;
                    }
                }
                $categories = array_keys($categories);
            } else {
                $error = "API Error: " . ($res['error'] ?? 'Unknown');
            }
        } else {
            $error = "No active provider found. Please activate one.";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// --- 5. PROCESS IMPORT (STEP 2) ---
if ($action == 'sync_confirm' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $selected_cats = $_POST['cats'] ?? [];
        
        if (empty($selected_cats)) {
            $error = "No categories selected!";
        } else {
            $stmt = $db->query("SELECT * FROM smm_providers WHERE is_active=1 LIMIT 1");
            $provider = $stmt->fetch();
            
            if ($provider) {
                $usd = (float)($GLOBALS['settings']['currency_conversion_rate'] ?? 280.00);
                $api = new SmmApi($provider['api_url'], $provider['api_key']);
                $res = $api->getServices();
                
                if ($res['success']) {
                    $db->beginTransaction();
                    $cnt = 0;
                    
                    foreach ($res['services'] as $s) {
                        if (empty($s['service']) || !in_array($s['category'], $selected_cats)) continue;
                        
                        $rate_usd = (float)$s['rate'];
                        $base_price_pkr = $rate_usd * $usd; 
                        $selling_price = $base_price_pkr * (1 + ($provider['profit_margin'] / 100));

                        $check = $db->prepare("SELECT id FROM smm_services WHERE provider_id=? AND service_id=?");
                        $check->execute([$provider['id'], $s['service']]);
                        $id = $check->fetchColumn();
                        
                        $name = sanitize($s['name']); 
                        $cat = sanitize($s['category']);
                        $min = (int)$s['min']; 
                        $max = (int)$s['max'];
                        $avg = sanitize($s['average_time'] ?? $s['avg_time'] ?? 'N/A');
                        $desc = sanitize($s['description'] ?? $s['desc'] ?? '');
                        $refill = (!empty($s['refill'])) ? 1 : 0;
                        $cancel = (!empty($s['cancel'])) ? 1 : 0;
                        $drip = (!empty($s['dripfeed'])) ? 1 : 0;
                        $type = sanitize($s['type'] ?? 'Default');

                        if ($id) {
                            $sql = "UPDATE smm_services SET name=?, category=?, base_price=?, service_rate=?, min=?, max=?, avg_time=?, description=?, has_refill=?, has_cancel=?, service_type=?, dripfeed=?, is_active=1, manually_deleted=0 WHERE id=?";
                            $db->prepare($sql)->execute([$name, $cat, $base_price_pkr, $selling_price, $min, $max, $avg, $desc, $refill, $cancel, $type, $drip, $id]);
                        } else {
                            // --- FIX APPLIED HERE: Added '1' at the end of the array ---
                            $sql = "INSERT INTO smm_services (provider_id, service_id, name, category, base_price, service_rate, min, max, avg_time, description, has_refill, has_cancel, service_type, dripfeed, is_active, manually_deleted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)";
                            $db->prepare($sql)->execute([$provider['id'], $s['service'], $name, $cat, $base_price_pkr, $selling_price, $min, $max, $avg, $desc, $refill, $cancel, $type, $drip, 1]);
                        }
                        $cnt++;
                    }
                    $db->query("INSERT IGNORE INTO smm_categories (name, is_active) SELECT DISTINCT category, 1 FROM smm_services WHERE is_active=1");
                    
                    $db->commit();
                    $success = "Success! $cnt services imported.";
                    echo "<script>window.location.href='smm_services.php?success=" . urlencode($success) . "';</script>";
                    exit;
                }
            }
        }
    } catch (Exception $e) { if($db->inTransaction()) $db->rollBack(); $error = $e->getMessage(); }
}

// --- FETCH DATA FOR LIST ---
$show = $_GET['show'] ?? 'active';
$where = "WHERE s.manually_deleted = 0"; 
if ($show == 'active') $where .= " AND s.is_active = 1";
if ($show == 'disabled') $where .= " AND s.is_active = 0";

$search = $_GET['search'] ?? '';
if ($search) $where .= " AND (s.name LIKE '%$search%' OR s.category LIKE '%$search%')";

$services = $db->query("SELECT s.*, p.name as provider_name FROM smm_services s JOIN smm_providers p ON s.provider_id = p.id $where ORDER BY s.category ASC, s.name ASC")->fetchAll();
$grouped = [];
foreach($services as $s) $grouped[$s['category']][] = $s;
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #4f46e5;
        --primary-soft: #eef2ff;
        --secondary: #64748b;
        --bg-body: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --danger: #ef4444;
        --success: #10b981;
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: #1e293b; }

    /* --- ALERTS --- */
    .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .alert-suc { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

    /* --- IMPORT WIZARD (STEP 1) UI --- */
    .import-container {
        max-width: 900px; margin: 40px auto;
        background: #fff; border-radius: 20px;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
        border: 1px solid var(--border); overflow: hidden;
        animation: slideUp 0.4s ease-out;
    }
    .import-header {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        padding: 30px; text-align: center; color: white;
    }
    .import-header h2 { margin: 0; font-size: 1.8rem; font-weight: 800; }
    .import-header p { margin: 5px 0 0; opacity: 0.9; }

    .import-body { padding: 30px; background: #f8fafc; }
    .cat-selector-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 15px; max-height: 500px; overflow-y: auto; padding: 5px;
    }
    
    .cat-checkbox-card {
        background: #fff; border: 1px solid var(--border);
        border-radius: 12px; padding: 15px; cursor: pointer;
        display: flex; align-items: center; gap: 12px;
        transition: all 0.2s ease; position: relative;
    }
    .cat-checkbox-card:hover { transform: translateY(-2px); border-color: var(--primary); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1); }
    .cat-checkbox-card input {
        width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer;
    }
    .cat-checkbox-card span { font-weight: 600; font-size: 0.95rem; color: #334155; line-height: 1.4; }
    
    /* Checked State Highlight */
    .cat-checkbox-card.checked { background: var(--primary-soft); border-color: var(--primary); }

    .import-footer {
        padding: 20px 30px; background: #fff; border-top: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
        position: sticky; bottom: 0; z-index: 10;
    }

    /* --- MAIN SERVICE MANAGER UI --- */
    .page-title-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
    .page-title-bar h1 { font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .controls-panel {
        background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 30px;
        border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        display: flex; gap: 15px; flex-wrap: wrap; align-items: center; justify-content: space-between;
    }
    
    .btn-group { display: flex; gap: 10px; }
    .btn-core {
        padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.9rem;
        text-decoration: none; border: 1px solid transparent; transition: 0.2s;
        display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
    }
    .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); }
    .btn-primary:hover { background: #4338ca; transform: translateY(-2px); }
    
    .btn-danger { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
    .btn-danger:hover { background: #b91c1c; color: #fff; }

    .btn-filter { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-filter.active { background: #cbd5e1; color: #0f172a; border-color: #94a3b8; }

    .search-box { position: relative; flex-grow: 1; max-width: 300px; }
    .search-box input {
        width: 100%; padding: 10px 15px 10px 35px; border-radius: 10px;
        border: 1px solid var(--border); background: #f8fafc; outline: none;
        transition: 0.2s;
    }
    .search-box input:focus { background: #fff; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

    /* --- CATEGORY CARDS --- */
    .cat-wrapper {
        background: #fff; border-radius: 16px; margin-bottom: 25px;
        border: 1px solid var(--border); overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02); animation: fadeIn 0.5s ease-out;
    }
    .cat-header {
        background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
    }
    .cat-name { font-size: 1.1rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; }
    .count-badge { background: #e2e8f0; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; }

    /* TABLE */
    .table-responsive { overflow-x: auto; }
    .modern-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .modern-table th {
        text-align: left; padding: 15px; color: #64748b; font-weight: 600;
        border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    .modern-table td {
        padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle;
    }
    .modern-table tr:hover { background: #f8fafc; }
    .modern-table tr:last-child td { border-bottom: none; }

    .price-tag { font-weight: 700; color: var(--primary); background: var(--primary-soft); padding: 4px 8px; border-radius: 6px; }
    .status-badge {
        padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    }
    .st-active { background: #dcfce7; color: #16a34a; }
    .st-disabled { background: #fee2e2; color: #dc2626; }

    .action-btn {
        width: 32px; height: 32px; border-radius: 8px; display: inline-flex;
        align-items: center; justify-content: center; text-decoration: none;
        transition: 0.2s; margin-right: 5px;
    }
    .act-edit { background: #e0f2fe; color: #0284c7; }
    .act-edit:hover { background: #0284c7; color: #fff; }
    .act-del { background: #fee2e2; color: #dc2626; }
    .act-del:hover { background: #dc2626; color: #fff; }

    @keyframes slideUp { from { transform: translateY(20px); opacity:0; } to { transform: translateY(0); opacity:1; } }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    
    @media(max-width: 768px) {
        .controls-panel { flex-direction: column; align-items: stretch; }
        .search-box { max-width: 100%; }
        .btn-group { flex-wrap: wrap; }
    }
</style>

<?php if ($action == 'sync_step1' && !empty($categories)): ?>
    
    <div class="import-container">
        <div class="import-header">
            <h2>📥 Import Services</h2>
            <p>Select the categories you want to add to your panel.</p>
        </div>
        
        <form action="smm_services.php?action=sync_confirm" method="POST">
            <div class="import-body">
                
                <div style="display:flex; justify-content:space-between; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <div class="btn-group">
                        <button type="button" class="btn-core btn-filter" onclick="toggleAll(true)">✓ Select All</button>
                        <button type="button" class="btn-core btn-filter" onclick="toggleAll(false)">✕ Uncheck All</button>
                    </div>
                    <div style="color:#64748b; font-size:0.9rem;">
                        Found <b><?= count($categories) ?></b> Categories
                    </div>
                </div>

                <div class="cat-selector-grid">
                    <?php foreach($categories as $cat): ?>
                    <label class="cat-checkbox-card" onclick="this.classList.toggle('checked', this.querySelector('input').checked)">
                        <input type="checkbox" name="cats[]" value="<?= htmlspecialchars($cat) ?>" checked>
                        <span><?= htmlspecialchars($cat) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="import-footer">
                <a href="smm_services.php" class="btn-core btn-danger" style="background:transparent; border:none; color:#64748b;">Cancel</a>
                <button type="submit" class="btn-core btn-primary">
                    🚀 Confirm Import
                </button>
            </div>
        </form>
    </div>

    <script>
    function toggleAll(status) {
        document.querySelectorAll('input[type="checkbox"]').forEach(el => {
            el.checked = status;
            // Visual Update
            let card = el.closest('.cat-checkbox-card');
            if(status) card.classList.add('checked'); else card.classList.remove('checked');
        });
    }
    // Init Visuals
    document.querySelectorAll('input[type="checkbox"]').forEach(el => {
        if(el.checked) el.closest('.cat-checkbox-card').classList.add('checked');
    });
    </script>

<?php return; endif; // End Step 1 ?>


<div class="page-title-bar">
    <h1>📦 Services Manager</h1>
</div>

<?php if ($error): ?><div class="alert-box alert-err"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert-box alert-suc"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

<div class="controls-panel">
    <div class="btn-group">
        <a href="smm_services.php?action=sync_step1" class="btn-core btn-primary">
            <i class="fa-solid fa-cloud-arrow-down"></i> Sync Services
        </a>
        <a href="smm_services.php?action=delete_all" class="btn-core btn-danger" onclick="return confirm('⚠️ WARNING: This will DELETE ALL services & Categories! Are you sure?')">
            <i class="fa-solid fa-trash-can"></i> Delete All
        </a>
    </div>

    <div class="btn-group">
        <a href="smm_services.php?show=active" class="btn-core btn-filter <?= $show=='active'?'active':'' ?>">Active</a>
        <a href="smm_services.php?show=disabled" class="btn-core btn-filter <?= $show=='disabled'?'active':'' ?>">Disabled</a>
    </div>

    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <form><input type="text" name="search" placeholder="Search services..." value="<?= sanitize($search) ?>"></form>
    </div>
</div>

<?php if(empty($grouped)): ?>
    <div style="text-align:center; padding:50px; background:#fff; border-radius:16px; border:1px solid var(--border);">
        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="80" style="opacity:0.5; margin-bottom:15px;">
        <h3 style="color:#64748b; margin:0;">No Services Found</h3>
        <p style="color:#94a3b8;">Try syncing services from a provider first.</p>
    </div>
<?php else: ?>
    
    <?php foreach($grouped as $cat => $list): ?>
    <div class="cat-wrapper">
        <div class="cat-header">
            <div class="cat-name">
                <?= sanitize($cat) ?> <span class="count-badge"><?= count($list) ?></span>
            </div>
            <a href="smm_services.php?action=delete_category&cat=<?= urlencode($cat) ?>" class="btn-core btn-danger" style="padding:5px 12px; font-size:0.8rem;" onclick="return confirm('Remove entire category?')">
                Delete Cat
            </a>
        </div>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th>Service Name</th>
                        <th width="120">Your Rate</th>
                        <th width="120">Cost</th>
                        <th width="100">Min/Max</th>
                        <th width="80">Status</th>
                        <th width="100" style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list as $s): ?>
                    <tr>
                        <td><b><?= $s['service_id'] ?></b></td>
                        <td style="font-weight:500;"><?= sanitize($s['name']) ?></td>
                        <td><span class="price-tag"><?= formatCurrency($s['service_rate']) ?></span></td>
                        <td style="color:#64748b; font-size:0.85rem;"><?= formatCurrency($s['base_price']) ?></td>
                        <td style="font-size:0.85rem;"><?= $s['min'] ?> - <?= $s['max'] ?></td>
                        <td>
                            <span class="status-badge <?= $s['is_active']?'st-active':'st-disabled' ?>">
                                <?= $s['is_active']?'Active':'Off' ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <a href="smm_edit_service.php?id=<?= $s['id'] ?>" class="action-btn act-edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <a href="smm_services.php?action=delete_service&id=<?= $s['id'] ?>" class="action-btn act-del" onclick="return confirm('Remove this service?')" title="Delete"><i class="fa-solid fa-xmark"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php include '_footer.php'; ?>