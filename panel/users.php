<?php
include '_header.php'; 
requireAdmin();

// --- 0. AUTO-HEAL DATABASE (Add Missing Columns) ---
try {
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('custom_rate', $cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN custom_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00"); // e.g., -10 means 10% discount
    }
    if (!in_array('admin_note', $cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN admin_note TEXT DEFAULT NULL");
    }
    if (!in_array('status', $cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN status ENUM('active','banned') DEFAULT 'active'");
    }
} catch (Exception $e) { /* Silent */ }

$error = '';
$success = '';

// --- 1. HANDLE ACTIONS ---

// UPDATE USER DETAILS (Edit Modal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $uid = (int)$_POST['user_id'];
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $rate = (float)$_POST['custom_rate'];
    $note = sanitize($_POST['admin_note']);

    try {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, status=?, custom_rate=?, admin_note=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $status, $rate, $note, $uid]);
        $success = "User #$uid details updated successfully.";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// BULK ACTIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $ids = $_POST['ids'] ?? [];
    $action_type = $_POST['bulk_type'];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action_type == 'ban') {
            $db->prepare("UPDATE users SET status='banned' WHERE id IN ($placeholders)")->execute($ids);
            $success = count($ids) . " users banned.";
        } elseif ($action_type == 'activate') {
            $db->prepare("UPDATE users SET status='active' WHERE id IN ($placeholders)")->execute($ids);
            $success = count($ids) . " users activated.";
        } elseif ($action_type == 'delete') {
            $db->prepare("DELETE FROM users WHERE id IN ($placeholders) AND id != ?")->execute(array_merge($ids, [$_SESSION['user_id']])); // Prevent self-delete
            $success = count($ids) . " users deleted.";
        }
    }
}

// UPDATE BALANCE
if (isset($_POST['update_balance'])) {
    $uid = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; 
    $reason = sanitize($_POST['reason']);
    
    try {
        $db->beginTransaction();
        if ($type == 'add') {
            $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $uid]);
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'credit', ?, ?)")->execute([$uid, $amount, "Admin Add: $reason"]);
            $success = "Added " . formatCurrency($amount);
        } else {
            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $uid]);
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'debit', ?, ?)")->execute([$uid, $amount, "Admin Deduct: $reason"]);
            $success = "Deducted " . formatCurrency($amount);
        }
        $db->commit();
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

// CHANGE PASSWORD
if (isset($_POST['change_pass'])) {
    $uid = (int)$_POST['user_id'];
    $pass = $_POST['new_password'];
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $uid]);
    $success = "Password changed.";
}

// LOGIN AS USER
if (isset($_GET['login_as'])) {
    $target_id = (int)$_GET['login_as'];
    $u = $db->query("SELECT * FROM users WHERE id=$target_id")->fetch();
    if ($u) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['email'] = $u['email'];
        $_SESSION['is_admin'] = $u['is_admin'];
        echo "<script>window.location.href='../user/index.php';</script>";
        exit;
    }
}

// --- 2. FETCH STATS ---
$stats = $db->query("SELECT 
    COUNT(*) as total, 
    SUM(balance) as wallet_total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='banned' THEN 1 ELSE 0 END) as banned
FROM users")->fetch();

// --- 3. FETCH USERS ---
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$total_users = $db->prepare("SELECT COUNT(id) FROM users WHERE $where");
$total_users->execute($params);
$total_count = $total_users->fetchColumn();
$total_pages = ceil($total_count / $per_page);

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
        (SELECT SUM(total_price) FROM orders WHERE user_id = u.id) as total_spent
        FROM users u WHERE $where ORDER BY u.id DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #4f46e5;
        --bg-body: #f8fafc;
        --card: #ffffff;
        --text: #0f172a;
        --text-light: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }
    body { background: var(--bg-body); font-family: 'Inter', sans-serif; color: var(--text); }

    /* STAT CARDS */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: var(--card); padding: 20px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 15px; }
    .st-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .st-blue { background: #eef2ff; color: #4f46e5; }
    .st-green { background: #ecfdf5; color: #10b981; }
    .st-red { background: #fef2f2; color: #ef4444; }
    .st-orange { background: #fffbeb; color: #f59e0b; }
    .st-info h3 { margin: 0; font-size: 1.5rem; font-weight: 800; }
    .st-info p { margin: 0; color: var(--text-light); font-size: 0.9rem; }

    /* CONTROLS */
    .controls-bar { background: var(--card); padding: 15px; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .search-wrap { display: flex; gap: 10px; flex: 1; min-width: 250px; }
    .form-input { padding: 10px 15px; border: 1px solid var(--border); border-radius: 8px; outline: none; width: 100%; transition: 0.2s; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }
    
    .btn { padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem; transition: 0.2s; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: #4338ca; }
    .btn-white { background: white; border: 1px solid var(--border); color: var(--text); }
    .btn-white:hover { background: #f8fafc; }
    .btn-danger { background: var(--danger); color: white; }

    /* TABLE */
    .table-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .user-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .user-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-size: 0.85rem; font-weight: 600; color: var(--text-light); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    .user-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: var(--text); }
    .user-table tr:hover { background: #fcfcfd; }

    .user-info { display: flex; align-items: center; gap: 12px; }
    .avatar { width: 40px; height: 40px; background: #e0e7ff; color: #4f46e5; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; }
    .u-name { font-weight: 600; display: block; color: var(--text); }
    .u-email { font-size: 0.85rem; color: var(--text-light); }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .bg-active { background: #dcfce7; color: #166534; }
    .bg-banned { background: #fee2e2; color: #991b1b; }
    .bg-admin { background: #f3e8ff; color: #6b21a8; }

    /* ACTIONS */
    .action-btn { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: white; color: var(--text-light); cursor: pointer; transition: 0.2s; }
    .action-btn:hover { border-color: var(--primary); color: var(--primary); }

    /* MODAL */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-box { background: white; width: 450px; border-radius: 16px; padding: 25px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: popIn 0.3s ease; }
    @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    .input-group { margin-bottom: 15px; }
    .input-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-light); margin-bottom: 5px; }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="st-icon st-blue"><i class="fa-solid fa-users"></i></div>
        <div class="st-info"><h3><?= $stats['total'] ?></h3><p>Total Users</p></div>
    </div>
    <div class="stat-card">
        <div class="st-icon st-green"><i class="fa-solid fa-wallet"></i></div>
        <div class="st-info"><h3><?= formatCurrency($stats['wallet_total']) ?></h3><p>User Funds</p></div>
    </div>
    <div class="stat-card">
        <div class="st-icon st-orange"><i class="fa-solid fa-user-check"></i></div>
        <div class="st-info"><h3><?= $stats['active'] ?></h3><p>Active Users</p></div>
    </div>
    <div class="stat-card">
        <div class="st-icon st-red"><i class="fa-solid fa-ban"></i></div>
        <div class="st-info"><h3><?= $stats['banned'] ?></h3><p>Banned Users</p></div>
    </div>
</div>

<form method="POST" id="bulkForm">
<div class="controls-bar">
    <div class="search-wrap">
        <input type="text" name="dummy_search" class="form-input" placeholder="Search user..." value="<?= htmlspecialchars($search) ?>" onkeypress="if(event.key==='Enter'){event.preventDefault(); window.location.href='?search='+this.value;}">
        <select class="form-input" style="width:150px;" onchange="window.location.href='?role='+this.value">
            <option value="">All Roles</option>
            <option value="user" <?= $role_filter=='user'?'selected':'' ?>>User</option>
            <option value="admin" <?= $role_filter=='admin'?'selected':'' ?>>Admin</option>
        </select>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <select name="bulk_type" class="form-input" style="width:140px;">
            <option value="">Bulk Action</option>
            <option value="ban">🚫 Ban</option>
            <option value="activate">✅ Activate</option>
            <option value="delete">🗑️ Delete</option>
        </select>
        <button type="submit" name="bulk_action" class="btn btn-white" onclick="return confirm('Apply to selected?')">Apply</button>
        <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')">➕ Add User</button>
    </div>
</div>

<?php if($success): ?><div style="padding:15px; background:#ecfdf5; color:#065f46; border-radius:10px; margin-bottom:20px; font-weight:600;"><i class="fa-solid fa-check"></i> <?= $success ?></div><?php endif; ?>
<?php if($error): ?><div style="padding:15px; background:#fef2f2; color:#991b1b; border-radius:10px; margin-bottom:20px; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div><?php endif; ?>

<div class="table-card">
    <div style="overflow-x:auto;">
    <table class="user-table">
        <thead>
            <tr>
                <th width="40"><input type="checkbox" onclick="toggleAll(this)"></th>
                <th>User Details</th>
                <th>Role / Rate</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Joined</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): 
                $rate_badge = ($u['custom_rate'] != 0) ? "<span class='badge bg-admin'>{$u['custom_rate']}%</span>" : "";
            ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= $u['id'] ?>"></td>
                <td>
                    <div class="user-info">
                        <div class="avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                        <div>
                            <span class="u-name"><?= htmlspecialchars($u['name']) ?></span>
                            <span class="u-email"><?= htmlspecialchars($u['email']) ?></span>
                            <?php if(!empty($u['admin_note'])): ?>
                                <small style="color:#f59e0b; display:block;"><i class="fa-solid fa-note-sticky"></i> <?= htmlspecialchars($u['admin_note']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge <?= $u['role']=='admin'?'bg-admin':'bg-active' ?>" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;"><?= ucfirst($u['role']) ?></span>
                    <?= $rate_badge ?>
                </td>
                <td>
                    <div style="font-weight:700; color:#059669;"><?= formatCurrency($u['balance']) ?></div>
                    <div style="font-size:0.75rem; color:#64748b;">Orders: <?= number_format($u['order_count']) ?></div>
                </td>
                <td>
                    <span class="badge <?= $u['status']=='active'?'bg-active':'bg-banned' ?>">
                        <?= ucfirst($u['status']) ?>
                    </span>
                </td>
                <td style="font-size:0.85rem; color:#64748b;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td style="text-align:right;">
                    <div style="display:flex; justify-content:flex-end; gap:5px;">
                        <button type="button" class="action-btn" title="Manage Funds" onclick="openFunds(<?= $u['id'] ?>, '<?= $u['name'] ?>')"><i class="fa-solid fa-coins"></i></button>
                        <button type="button" class="action-btn" title="Edit User" onclick='openEdit(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i></button>
                        <a href="?login_as=<?= $u['id'] ?>" class="action-btn" title="Login As User" onclick="return confirm('Login as <?= $u['name'] ?>?')"><i class="fa-solid fa-ghost"></i></a>
                        <button type="button" class="action-btn" title="Change Password" onclick="openPass(<?= $u['id'] ?>)"><i class="fa-solid fa-key"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</form>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0;">✏️ Edit User</h3>
            <span onclick="closeModal('editModal')" style="cursor:pointer;">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="edit_uid">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="input-group">
                    <label class="input-label">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                <div class="input-group">
                    <label class="input-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="input-group">
                    <label class="input-label">Role</label>
                    <select name="role" id="edit_role" class="form-input">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="input-group">
                    <label class="input-label">Status</label>
                    <select name="status" id="edit_status" class="form-input">
                        <option value="active">Active</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Custom Rate % (e.g. -10 for discount, 10 for profit)</label>
                <input type="number" name="custom_rate" id="edit_rate" class="form-input" step="0.01">
            </div>

            <div class="input-group">
                <label class="input-label">Admin Note (Private)</label>
                <textarea name="admin_note" id="edit_note" class="form-input" rows="2" placeholder="Write internal note..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<div id="fundsModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top:0;">💰 Manage Wallet</h3>
        <p id="fundUser" style="color:var(--text-light); font-size:0.9rem; margin-bottom:15px;"></p>
        <form method="POST">
            <input type="hidden" name="update_balance" value="1">
            <input type="hidden" name="user_id" id="fund_uid">
            
            <div class="input-group">
                <label class="input-label">Action</label>
                <select name="type" class="form-input">
                    <option value="add">➕ Add Funds</option>
                    <option value="deduct">➖ Deduct Funds</option>
                </select>
            </div>
            <div class="input-group">
                <label class="input-label">Amount</label>
                <input type="number" name="amount" class="form-input" step="any" required placeholder="0.00">
            </div>
            <div class="input-group">
                <label class="input-label">Reason</label>
                <input type="text" name="reason" class="form-input" required placeholder="Bonus, Refund etc.">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-white" onclick="closeModal('fundsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<div id="passModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top:0;">🔑 Reset Password</h3>
        <form method="POST">
            <input type="hidden" name="change_pass" value="1">
            <input type="hidden" name="user_id" id="pass_uid">
            
            <div class="input-group">
                <label class="input-label">New Password</label>
                <input type="text" name="new_password" class="form-input" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Set Password</button>
            <button type="button" class="btn btn-white" style="width:100%; margin-top:10px;" onclick="closeModal('passModal')">Cancel</button>
        </form>
    </div>
</div>

<script>
function toggleAll(source) {
    document.querySelectorAll('input[name="ids[]"]').forEach(el => el.checked = source.checked);
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openModal(id) { document.getElementById(id).style.display = 'flex'; }

function openEdit(u) {
    document.getElementById('edit_uid').value = u.id;
    document.getElementById('edit_name').value = u.name;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_role').value = u.role;
    document.getElementById('edit_status').value = u.status;
    document.getElementById('edit_rate').value = u.custom_rate;
    document.getElementById('edit_note').value = u.admin_note;
    openModal('editModal');
}

function openFunds(id, name) {
    document.getElementById('fund_uid').value = id;
    document.getElementById('fundUser').innerText = 'User: ' + name;
    openModal('fundsModal');
}

function openPass(id) {
    document.getElementById('pass_uid').value = id;
    openModal('passModal');
}
</script>

<?php include '_footer.php'; ?>