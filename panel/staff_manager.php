<?php
// --- 1. DEBUGGING ON (White Screen Fix) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 2. INCLUDE HEADER ---
if (!file_exists('_header.php')) { die("Error: _header.php not found in panel folder."); }
include '_header.php'; 

// --- 3. CHECK ADMIN ACCESS ---
if (!function_exists('requireAdmin')) { die("Error: requireAdmin function missing."); }
requireAdmin();

// =========================================================
//      🛠️ AUTO-FIX DATABASE (ONE-TIME MAGIC FIX)
// =========================================================
try {
    // 1. Check if 'role' column exists
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('role', $cols)) {
        // Create Role Column
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin','staff') NOT NULL DEFAULT 'user'");
        
        // Migrate Old Admins (is_admin = 1 -> role = 'admin')
        $db->exec("UPDATE users SET role = 'admin' WHERE is_admin = 1");
    }

    // 2. Check Permissions Column
    if (!in_array('permissions', $cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT NULL");
    }

} catch (Exception $e) {
    die("<div style='background:red;color:white;padding:20px;'>Database Fix Error: " . $e->getMessage() . "</div>");
}
// =========================================================

$success = '';
$error = '';

// --- 4. HANDLE SAVE PERMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_perms'])) {
    $uid = (int)$_POST['staff_id'];
    $perms = $_POST['perm'] ?? []; 
    $json_perms = json_encode($perms);
    
    try {
        $stmt = $db->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->execute([$json_perms, $uid]);
        $success = "Permissions updated successfully!";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// --- 5. FETCH STAFF MEMBERS ---
$search = $_GET['search'] ?? '';
// Ab 'role' column confirm hai, toh error nahi aayega
$sql = "SELECT * FROM users WHERE role IN ('admin', 'staff')";

if ($search) {
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
}
$sql .= " ORDER BY role ASC, name ASC";

try {
    $staff_members = $db->query($sql)->fetchAll();
} catch (Exception $e) {
    die("Query Error: " . $e->getMessage());
}

// --- 6. DEFINE PERMISSIONS LIST ---
$all_permissions = [
    'orders' => [
        'icon' => 'fa-box', 
        'label' => 'Order Management',
        'caps' => [
            'view_orders' => 'View Orders',
            'edit_orders' => 'Edit/Refill',
            'cancel_orders' => 'Cancel/Refund'
        ]
    ],
    'users' => [
        'icon' => 'fa-users', 
        'label' => 'User Management',
        'caps' => [
            'view_users' => 'View List',
            'edit_users' => 'Edit Users',
            'add_balance' => 'Add/Deduct Funds',
            'ban_users' => 'Ban/Delete Users'
        ]
    ],
    'services' => [
        'icon' => 'fa-list-check', 
        'label' => 'Services & Products',
        'caps' => [
            'manage_services' => 'Manage Services',
            'sync_services' => 'Sync API',
            'manage_products' => 'Digital Products'
        ]
    ],
    'tickets' => [
        'icon' => 'fa-headset', 
        'label' => 'Support System',
        'caps' => [
            'view_tickets' => 'Read Tickets',
            'reply_tickets' => 'Reply/Close'
        ]
    ],
    'system' => [
        'icon' => 'fa-gears', 
        'label' => 'System & Settings',
        'caps' => [
            'access_settings' => 'General Settings',
            'manage_providers' => 'API Providers',
            'view_logs' => 'Audit Logs'
        ]
    ]
];
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --secondary: #8b5cf6;
        --bg-body: #f8fafc;
        --card: #ffffff;
        --text-main: #0f172a;
        --text-sub: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
    }
    body { background: var(--bg-body); font-family: 'Outfit', sans-serif; color: var(--text-main); }

    /* HEADER */
    .page-header {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        padding: 40px 30px; border-radius: 20px; color: white; margin-bottom: -40px;
        box-shadow: 0 20px 40px -10px rgba(49, 46, 129, 0.3); position: relative; z-index: 1;
    }
    .ph-content h1 { margin: 0; font-size: 2rem; font-weight: 800; display: flex; align-items: center; gap: 15px; }
    .ph-content p { opacity: 0.7; margin: 5px 0 0 50px; font-size: 1rem; }

    /* GRID */
    .staff-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;
        padding: 60px 20px 40px 20px; max-width: 1400px; margin: 0 auto;
    }

    /* CARD */
    .s-card {
        background: var(--card); border-radius: 20px; border: 1px solid var(--border);
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05); overflow: hidden;
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative;
        width: 80%;
    }
    .s-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.15); border-color: #c7d2fe; }
    
    .s-head {
        padding: 20px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #f1f5f9;
        background: #fcfcfd;
    }
    .s-avatar {
        width: 55px; height: 55px; border-radius: 14px; background: #e0e7ff; color: var(--primary);
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
    }
    .s-info h3 { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
    .s-info span { font-size: 0.85rem; color: var(--text-sub); }
    
    .role-badge { 
        position: absolute; top: 20px; right: 20px; padding: 5px 12px; border-radius: 30px; 
        font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .rb-admin { background: #fef3c7; color: #b45309; }
    .rb-staff { background: #dbeafe; color: #1e40af; }

    .s-body { padding: 20px; }
    .perm-summary {
        display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .ps-tag {
        background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; color: #64748b; font-weight: 600;
    }
    .ps-tag.has-perm { background: #dcfce7; color: #166534; }

    .s-btn {
        width: 100%; padding: 12px; border-radius: 12px; background: var(--primary); color: white;
        border: none; font-weight: 700; cursor: pointer; transition: 0.2s;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .s-btn:hover { background: #4338ca; shadow: 0 4px 15px rgba(79, 70, 229, 0.4); }

    /* MODAL (PERMISSION MATRIX) */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(5px);
        z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;
    }
    .perm-box {
        background: white; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto;
        border-radius: 24px; box-shadow: 0 50px 100px -20px rgba(0,0,0,0.5);
        animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .pb-head {
        background: #f8fafc; padding: 25px 30px; border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;
    }
    .pb-body { padding: 30px; }

    /* MATRIX GRID */
    .matrix-group { margin-bottom: 25px; background: #fff; border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
    .mg-header {
        background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid var(--border);
        font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 10px;
    }
    .mg-icon { color: var(--primary); }
    
    .mg-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); padding: 15px; gap: 15px; }
    
    /* TOGGLE SWITCH */
    .toggle-label {
        display: flex; align-items: center; justify-content: space-between; cursor: pointer;
        padding: 10px; border-radius: 8px; transition: 0.2s; border: 1px solid transparent;
    }
    .toggle-label:hover { background: #f8fafc; border-color: #e2e8f0; }
    
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    input:checked + .slider { background-color: var(--success); }
    input:checked + .slider:before { transform: translateX(20px); }

    .btn-save { width: 100%; padding: 15px; background: var(--primary); color: white; font-size: 1.1rem; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; }
    
    @media(max-width: 768px) { .ph-content h1 { font-size: 1.5rem; } }
</style>

<div class="page-header">
    <div class="ph-content">
        <h1><i class="fa-solid fa-user-shield"></i> Staff Permissions Manager</h1>
        <p>Control exactly what your team can see and do. Security first.</p>
    </div>
</div>

<?php if ($success): ?>
    <div style="max-width:1400px; margin:20px auto 0; padding:0 20px;">
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; border:1px solid #bbf7d0; font-weight:600;">
            <i class="fa-solid fa-check-circle"></i> <?= $success ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="max-width:1400px; margin:20px auto 0; padding:0 20px;">
        <div style="background:#fef2f2; color:#b91c1c; padding:15px; border-radius:12px; border:1px solid #fecaca; font-weight:600;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?>
        </div>
    </div>
<?php endif; ?>

<div class="staff-grid">
    <?php foreach ($staff_members as $s): 
        $roleClass = ($s['role']=='admin') ? 'rb-admin' : 'rb-staff';
        $icon = ($s['role']=='admin') ? 'fa-crown' : 'fa-id-badge';
        
        // Decode Permissions
        $u_perms = json_decode($s['permissions'] ?? '{}', true);
        if(!is_array($u_perms)) $u_perms = [];
        
        // Count active perms
        $perm_count = count($u_perms);
    ?>
    <div class="s-card">
        <div class="role-badge <?= $roleClass ?>"><?= strtoupper($s['role']) ?></div>
        
        <div class="s-head">
            <div class="s-avatar"><i class="fa-solid <?= $icon ?>"></i></div>
            <div class="s-info">
                <h3><?= htmlspecialchars($s['name']) ?></h3>
                <span><?= htmlspecialchars($s['email']) ?></span>
            </div>
        </div>
        
        <div class="s-body">
            <p style="font-size:0.85rem; color:#64748b; margin-bottom:10px;">
                <i class="fa-solid fa-key"></i> Active Permissions: <b><?= $perm_count ?></b>
            </p>
            
            <div class="perm-summary">
                <span class="ps-tag <?= in_array('view_orders',$u_perms)?'has-perm':'' ?>">Orders</span>
                <span class="ps-tag <?= in_array('view_tickets',$u_perms)?'has-perm':'' ?>">Support</span>
                <span class="ps-tag <?= in_array('access_settings',$u_perms)?'has-perm':'' ?>">Settings</span>
                <span class="ps-tag <?= in_array('add_balance',$u_perms)?'has-perm':'' ?>">Payments</span>
            </div>

            <button class="s-btn" onclick='openPerms(<?= json_encode($s) ?>)'>
                <i class="fa-solid fa-sliders"></i> Configure Access
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if(empty($staff_members)): ?>
        <p style="text-align:center; grid-column: 1/-1; color:#94a3b8;">No staff members found. Go to 'Users' page and change a user role to 'Staff' or 'Admin'.</p>
    <?php endif; ?>
</div>

<div id="permModal" class="modal-overlay">
    <div class="perm-box">
        <form method="POST">
            <input type="hidden" name="save_perms" value="1">
            <input type="hidden" name="staff_id" id="modal_uid">
            
            <div class="pb-head">
                <div>
                    <h2 style="margin:0; font-size:1.4rem; font-weight:800;">🔒 Access Control</h2>
                    <p style="margin:0; color:#64748b; font-size:0.9rem;">Editing for: <b id="modal_uname" style="color:var(--primary);">User</b></p>
                </div>
                <button type="button" onclick="document.getElementById('permModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>

            <div class="pb-body">
                <?php foreach($all_permissions as $group_key => $group): ?>
                <div class="matrix-group">
                    <div class="mg-header">
                        <i class="fa-solid <?= $group['icon'] ?> mg-icon"></i> <?= $group['label'] ?>
                    </div>
                    <div class="mg-options">
                        <?php foreach($group['caps'] as $key => $label): ?>
                        <label class="toggle-label">
                            <span style="font-size:0.9rem; font-weight:500; color:#334155;"><?= $label ?></span>
                            <label class="switch">
                                <input type="checkbox" name="perm[]" value="<?= $key ?>" class="perm-check" id="p_<?= $key ?>">
                                <span class="slider"></span>
                            </label>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn-save">💾 Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPerms(user) {
    document.getElementById('modal_uid').value = user.id;
    document.getElementById('modal_uname').innerText = user.name;
    
    // Reset all checks
    document.querySelectorAll('.perm-check').forEach(el => el.checked = false);
    
    // Load existing
    let perms = [];
    try {
        perms = JSON.parse(user.permissions || '[]');
    } catch(e) {}
    
    if (user.role === 'admin') {
        // Optional: Pre-check all for admin visual
    }
    
    perms.forEach(p => {
        let el = document.getElementById('p_' + p);
        if(el) el.checked = true;
    });
    
    document.getElementById('permModal').style.display = 'flex';
}

// Close on outside click
window.onclick = function(e) {
    if(e.target == document.getElementById('permModal')) {
        document.getElementById('permModal').style.display = 'none';
    }
}
</script>

<?php include '_footer.php'; ?>