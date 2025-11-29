<?php
include '_header.php';
require_once __DIR__ . '/../includes/smm_api.class.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = $_GET['success'] ?? '';

// --- 1. DELETE ALL (Hard Reset) ---
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

// --- 4. MANUAL SYNC ---
if ($action == 'sync') {
    try {
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
                    if (empty($s['service'])) continue;
                    
                    $rate_usd = (float)$s['rate'];
                    $base_price_pkr = $rate_usd * $usd; // Provider Price in PKR
                    $selling_price = $base_price_pkr * (1 + ($provider['profit_margin'] / 100)); // User Price

                    // Check DB
                    $check = $db->prepare("SELECT id FROM smm_services WHERE provider_id=? AND service_id=?");
                    $check->execute([$provider['id'], $s['service']]);
                    $id = $check->fetchColumn();
                    
                    // Data
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
                        // Update & Restore (manually_deleted=0)
                        $sql = "UPDATE smm_services SET name=?, category=?, base_price=?, service_rate=?, min=?, max=?, avg_time=?, description=?, has_refill=?, has_cancel=?, service_type=?, dripfeed=?, is_active=1, manually_deleted=0 WHERE id=?";
                        $db->prepare($sql)->execute([$name, $cat, $base_price_pkr, $selling_price, $min, $max, $avg, $desc, $refill, $cancel, $type, $drip, $id]);
                    } else {
                        // Insert
                        $sql = "INSERT INTO smm_services (provider_id, service_id, name, category, base_price, service_rate, min, max, avg_time, description, has_refill, has_cancel, service_type, dripfeed, is_active, manually_deleted) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)";
                        $db->prepare($sql)->execute([$provider['id'], $s['service'], $name, $cat, $base_price_pkr, $selling_price, $min, $max, $avg, $desc, $refill, $cancel, $type, $drip]);
                    }
                    $cnt++;
                }
                // Update Categories
                $db->query("INSERT IGNORE INTO smm_categories (name, is_active) SELECT DISTINCT category, 1 FROM smm_services WHERE is_active=1");
                
                $db->commit();
                $success = "Synced $cnt services successfully!";
            } else { $error = "API Error: " . ($res['error']??'Unknown'); }
        } else { $error = "No active provider found."; }
    } catch (Exception $e) { if($db->inTransaction()) $db->rollBack(); $error = $e->getMessage(); }
}

// --- DISPLAY DATA ---
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

<style>
/* --- FIXED CSS FOR WHITE CARD/DARK TEXT --- */
.controls-bar { 
    display: flex; gap: 10px; margin-bottom: 20px; padding: 15px; 
    background: #fff; /* White BG */
    color: #333; /* Dark Text */
    border: 1px solid #e0e0e0; border-radius: 8px; 
    flex-wrap: wrap; align-items: center; 
}
.search-wrap { flex: 1; min-width: 200px; }
.search-wrap input { 
    width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; 
    background: #fff; color: #333;
}

.cat-card { 
    background: #fff; /* White BG */
    color: #333; /* Dark Text */
    border: 1px solid #e0e0e0; border-radius: 8px; 
    margin-bottom: 20px; overflow: hidden; 
}
.cat-head { 
    background: #f8f9fa; color: #333; /* Light Grey Header, Dark Text */
    padding: 12px 15px; border-bottom: 1px solid #eee; 
    display: flex; justify-content: space-between; align-items: center; 
}
.cat-title { font-weight: 700; font-size: 1rem; color: #222; }

.svc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.svc-table th { 
    background: #f1f1f1; color: #444; /* Grey Header, Dark Text */
    text-align: left; padding: 10px; font-weight: 700;
}
.svc-table td { 
    padding: 10px; border-bottom: 1px solid #eee; 
    color: #333; /* Data Cells Dark Text */
    vertical-align: middle;
}
.svc-table tr:last-child td { border-bottom: none; }

/* Buttons */
.btn-danger-custom { background: #dc3545 !important; color: #fff !important; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; display: inline-block; border: 1px solid #b02a37; }
.btn-del-cat { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; padding: 4px 10px; font-size: 0.75rem; border-radius: 4px; text-decoration: none; font-weight: bold; }
.btn-sm { padding: 4px 8px; font-size: 0.75rem; border-radius: 3px; text-decoration: none; display: inline-block; margin-right: 3px; }
.btn-edit { background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
.btn-del { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

/* Status Badges */
.badge { padding: 3px 8px; border-radius: 3px; font-size: 0.75rem; font-weight: bold; }
.bg-active { background: #d1e7dd; color: #0f5132; }
.bg-disabled { background: #f8d7da; color: #721c24; }

/* Scroll for Mobile */
.table-wrap { overflow-x: auto; }
</style>

<h1>📦 Services Manager</h1>
<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="controls-bar">
    <div style="display:flex; gap:10px;">
        <a href="smm_services.php?action=delete_all" class="btn-danger-custom" onclick="return confirm('⚠️ EXTREME WARNING:\n\nThis will DELETE ALL services and categories from the database.\n\nAre you sure?')">🗑️ Delete All</a>
        <a href="smm_services.php?action=sync" class="btn-new" onclick="return confirm('Sync now?')">🔄 Sync Now</a>
    </div>
    <div style="display:flex; gap:5px; margin-left: auto; margin-right: auto;">
        <a href="smm_services.php?show=active" class="btn btn-secondary">Active</a>
        <a href="smm_services.php?show=disabled" class="btn btn-secondary">Disabled</a>
    </div>
    <div class="search-wrap">
        <form><input type="text" name="search" placeholder="Search services..." value="<?= sanitize($search) ?>"></form>
    </div>
</div>

<?php if(empty($grouped)): ?>
    <p style="text-align:center; padding:30px; color:#777; background:#fff; border-radius:8px;">No services found.</p>
<?php else: ?>
    <?php foreach($grouped as $cat => $list): ?>
    <div class="cat-card">
        <div class="cat-head">
            <span class="cat-title"><?= sanitize($cat) ?> (<?= count($list) ?>)</span>
            <a href="smm_services.php?action=delete_category&cat=<?= urlencode($cat) ?>" class="btn-del-cat" onclick="return confirm('Remove entire category?')">🗑️ Remove Category</a>
        </div>
        <div class="table-wrap">
            <table class="svc-table">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Service Name</th>
                        <th width="100">Your Rate</th>
                        <th width="100">Prov. Rate</th>
                        <th width="80">Min/Max</th>
                        <th width="60">Status</th>
                        <th width="80">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list as $s): ?>
                    <tr>
                        <td><?= $s['service_id'] ?></td>
                        <td><?= sanitize($s['name']) ?></td>
                        <td style="font-weight:bold; color:#007bff;"><?= formatCurrency($s['service_rate']) ?></td>
                        <td style="color:#666;"><?= formatCurrency($s['base_price']) ?></td>
                        <td><?= $s['min'] ?>/<?= $s['max'] ?></td>
                        <td><span class="badge <?= $s['is_active']?'bg-active':'bg-disabled' ?>"><?= $s['is_active']?'Active':'Disabled' ?></span></td>
                        <td>
                            <a href="smm_edit_service.php?id=<?= $s['id'] ?>" class="btn-sm btn-edit">✏️</a>
                            <a href="smm_services.php?action=delete_service&id=<?= $s['id'] ?>" class="btn-sm btn-del" onclick="return confirm('Remove service?')">✕</a>
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