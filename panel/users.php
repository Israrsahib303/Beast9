<?php
include '_header.php'; 
requireAdmin();

// --- 0. AUTO-HEAL DATABASE (Self-Repairing) ---
try {
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    // Custom Rate
    if (!in_array('custom_rate', $cols)) $db->exec("ALTER TABLE users ADD COLUMN custom_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    // Admin Note
    if (!in_array('admin_note', $cols)) $db->exec("ALTER TABLE users ADD COLUMN admin_note TEXT DEFAULT NULL");
    // Status
    if (!in_array('status', $cols)) $db->exec("ALTER TABLE users ADD COLUMN status ENUM('active','banned') DEFAULT 'active'");
    // Tracking
    if (!in_array('last_login', $cols)) $db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
    if (!in_array('last_ip', $cols)) $db->exec("ALTER TABLE users ADD COLUMN last_ip VARCHAR(50) DEFAULT NULL");
    // Verified Badge
    if (!in_array('is_verified_badge', $cols)) $db->exec("ALTER TABLE users ADD COLUMN is_verified_badge TINYINT(1) DEFAULT 0");
    // Role (FIX FOR UNDEFINED INDEX ERROR)
    if (!in_array('role', $cols)) $db->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin','staff') NOT NULL DEFAULT 'user'");
} catch (Exception $e) { /* Silent */ }

$error = '';
$success = '';

// --- 1. ACTION HANDLERS ---

// A. EDIT USER (Simplified Rate Logic)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $uid = (int)$_POST['user_id'];
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $role = $_POST['role'];
    $note = sanitize($_POST['admin_note']);
    $badge = isset($_POST['is_verified_badge']) ? 1 : 0;

    // Easy Custom Rate Logic
    $rate_val = abs((float)$_POST['rate_value']); // Always positive
    $rate_type = $_POST['rate_type']; // 'discount' or 'premium'
    $final_rate = ($rate_type == 'discount') ? -$rate_val : $rate_val;

    try {
        $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, custom_rate=?, admin_note=?, is_verified_badge=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $final_rate, $note, $badge, $uid]);
        $success = "User #$uid updated successfully.";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// B. SEND SINGLE EMAIL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_single_mail'])) {
    $uid = (int)$_POST['user_id'];
    $subject = sanitize($_POST['subject']);
    $msg = $_POST['message']; // HTML allowed
    
    // Get User Email
    $u = $db->query("SELECT email, name FROM users WHERE id=$uid")->fetch();
    if ($u) {
        $mail_res = sendEmail($u['email'], $u['name'], $subject, $msg);
        if ($mail_res['success']) {
            $success = "Email sent to " . htmlspecialchars($u['email']);
        } else {
            $error = "Mail Failed: " . $mail_res['message'];
        }
    }
}

// C. QUICK BAN/UNBAN
if (isset($_GET['toggle_ban'])) {
    $uid = (int)$_GET['toggle_ban'];
    $current = $db->query("SELECT status FROM users WHERE id=$uid")->fetchColumn();
    $new_status = ($current == 'banned') ? 'active' : 'banned';
    $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $uid]);
    $success = "User " . ($new_status == 'banned' ? 'BANNED 🚫' : 'Activated ✅');
}

// D. UPDATE BALANCE
if (isset($_POST['update_balance'])) {
    $uid = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; 
    $reason = sanitize($_POST['reason']);
    
    try {
        $db->beginTransaction();
        if ($type == 'add') {
            $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $uid]);
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'credit', ?, ?)")->execute([$uid, $amount, "Admin: $reason"]);
            $success = "Added " . formatCurrency($amount);
        } else {
            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $uid]);
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'debit', ?, ?)")->execute([$uid, $amount, "Admin: $reason"]);
            $success = "Deducted " . formatCurrency($amount);
        }
        $db->commit();
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

// E. CHANGE PASSWORD
if (isset($_POST['change_pass'])) {
    $uid = (int)$_POST['user_id'];
    $pass = $_POST['new_password'];
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $uid]);
    $success = "Password changed.";
}

// F. DELETE USER
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    if ($id == $_SESSION['user_id']) { $error = "Self-delete not allowed!"; } 
    else {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $success = "User deleted.";
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
    $where .= " AND (name LIKE ? OR email LIKE ? OR id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = $search; // Allow search by ID directly
}
if ($role_filter) {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

$total_count = $db->prepare("SELECT COUNT(id) FROM users WHERE $where");
$total_count->execute($params);
$total_rows = $total_count->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
        (SELECT SUM(total_price) FROM orders WHERE user_id = u.id) as total_spent
        FROM users u WHERE $where ORDER BY u.id DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --bg-body: #f1f5f9;
        --card: #ffffff;
        --text: #0f172a;
        --border: #e2e8f0;
        --danger: #ef4444;
        --success: #10b981;
    }
    body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text); }

    /* STATS HEADER */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-box { background: var(--card); padding: 20px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid var(--border); transition: 0.3s; }
    .stat-box:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .s-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .s-blue { background: #e0e7ff; color: #4338ca; }
    .s-green { background: #dcfce7; color: #166534; }
    .s-red { background: #fee2e2; color: #991b1b; }
    
    /* TOOLBAR */
    .controls-wrap { background: var(--card); padding: 15px; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .search-group { display: flex; gap: 10px; flex: 1; max-width: 500px; }
    .inp-modern { padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; width: 100%; outline: none; transition: 0.2s; font-size: 0.9rem; }
    .inp-modern:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }
    
    .btn-x { padding: 10px 20px; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
    .bx-primary { background: var(--primary); color: white; }
    .bx-primary:hover { background: #4338ca; }
    .bx-white { background: white; border: 1px solid var(--border); color: #64748b; }
    .bx-white:hover { background: #f8fafc; color: var(--text); }

    /* MODERN TABLE */
    .table-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    .x-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
    .x-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; padding: 15px 20px; text-align: left; border-bottom: 1px solid var(--border); }
    .x-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; font-size: 0.9rem; }
    .x-table tr:hover { background: #fcfcfd; }

    /* USER PROFILE CELL */
    .user-flex { display: flex; align-items: center; gap: 12px; }
    .u-avatar { width: 42px; height: 42px; background: #e0e7ff; color: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .u-details h4 { margin: 0; font-size: 0.95rem; color: #1e293b; font-weight: 700; display: flex; align-items: center; gap: 5px; }
    .u-details span { font-size: 0.8rem; color: #64748b; display: block; }
    
    .badge-verified { color: #0ea5e9; font-size: 0.9rem; } /* Blue Tick */

    /* ACTION BUTTONS */
    .act-group { display: flex; gap: 6px; }
    .act-btn { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: white; color: #64748b; cursor: pointer; transition: 0.2s; text-decoration: none; }
    .act-btn:hover { background: #f1f5f9; color: var(--primary); border-color: var(--primary); }
    .act-ban { color: #ef4444; border-color: #fecaca; }
    .act-ban:hover { background: #ef4444; color: white; }
    .act-unban { color: #10b981; border-color: #a7f3d0; }
    .act-unban:hover { background: #10b981; color: white; }

    /* MODAL */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal-box { background: white; width: 100%; max-width: 500px; padding: 30px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; animation: slideUp 0.3s ease; }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .close-modal { position: absolute; top: 20px; right: 20px; font-size: 1.5rem; color: #94a3b8; cursor: pointer; }
    .close-modal:hover { color: #ef4444; }
    
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .dot-green { background: #10b981; }
    .dot-red { background: #ef4444; }
</style>

<div class="stats-row">
    <div class="stat-box">
        <div class="s-icon s-blue"><i class="fa-solid fa-users"></i></div>
        <div><h3><?= $stats['total'] ?></h3><p>Total Users</p></div>
    </div>
    <div class="stat-box">
        <div class="s-icon s-green"><i class="fa-solid fa-wallet"></i></div>
        <div><h3><?= formatCurrency($stats['wallet_total']) ?></h3><p>Total Wallet Funds</p></div>
    </div>
    <div class="stat-box">
        <div class="s-icon s-red"><i class="fa-solid fa-ban"></i></div>
        <div><h3><?= $stats['banned'] ?></h3><p>Banned Users</p></div>
    </div>
</div>

<div class="controls-wrap">
    <div class="search-group">
        <input type="text" class="inp-modern" placeholder="🔍 Search by Name, Email or ID..." id="searchInput" value="<?= htmlspecialchars($search) ?>" onkeypress="if(event.key==='Enter') window.location.href='?search='+this.value">
        <select class="inp-modern" style="width:150px;" onchange="window.location.href='?role='+this.value">
            <option value="">All Roles</option>
            <option value="user" <?= $role_filter=='user'?'selected':'' ?>>User</option>
            <option value="admin" <?= $role_filter=='admin'?'selected':'' ?>>Admin</option>
        </select>
    </div>
    </div>

<?php if($success): ?><div style="padding:15px; background:#ecfdf5; color:#065f46; border-radius:12px; margin-bottom:20px; border:1px solid #a7f3d0;"><i class="fa-solid fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
<?php if($error): ?><div style="padding:15px; background:#fef2f2; color:#991b1b; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div><?php endif; ?>

<div class="table-card">
    <div style="overflow-x:auto;">
    <table class="x-table">
        <thead>
            <tr>
                <th>User Profile</th>
                <th>Role / Rate</th>
                <th>Wallet Balance</th>
                <th>Status</th>
                <th>Last Seen</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($users)): ?>
                <tr><td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">No users found matching query.</td></tr>
            <?php else: ?>
                <?php foreach($users as $u): 
                    $initial = !empty($u['name']) ? strtoupper(substr($u['name'], 0, 1)) : '<i class="fa-solid fa-user"></i>';
                    $status_dot = ($u['status']=='active') ? 'dot-green' : 'dot-red';
                    
                    // Format Custom Rate
                    $rate_display = "Standard";
                    if($u['custom_rate'] < 0) $rate_display = "<span style='color:#10b981; font-weight:700;'>".abs($u['custom_rate'])."% OFF</span>";
                    if($u['custom_rate'] > 0) $rate_display = "<span style='color:#ef4444; font-weight:700;'>+".abs($u['custom_rate'])."% High</span>";
                    
                    // FIX: Safe Role Handling
                    $role_safe = $u['role'] ?? 'user';
                ?>
                <tr>
                    <td>
                        <div class="user-flex">
                            <div class="u-avatar"><?= $initial ?></div>
                            <div class="u-details">
                                <h4>
                                    <?= htmlspecialchars($u['name'] ?? 'No Name') ?> 
                                    <?php if($u['is_verified_badge']): ?><i class="fa-solid fa-circle-check badge-verified" title="Verified"></i><?php endif; ?>
                                </h4>
                                <span><?= htmlspecialchars($u['email']) ?></span>
                                <?php if(!empty($u['admin_note'])): ?>
                                    <small style="color:#f59e0b; display:block; margin-top:2px;"><i class="fa-solid fa-note-sticky"></i> <?= htmlspecialchars($u['admin_note']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:0.85rem; margin-bottom:2px; text-transform:uppercase; color:#64748b;"><?= ucfirst($role_safe) ?></div>
                        <div style="font-size:0.8rem;"><?= $rate_display ?></div>
                    </td>
                    <td>
                        <div style="font-weight:800; color:#059669; font-size:1rem;"><?= formatCurrency($u['balance']) ?></div>
                        <small style="color:#94a3b8;">Spent: <?= formatCurrency($u['total_spent']??0) ?></small>
                    </td>
                    <td>
                        <span class="status-dot <?= $status_dot ?>"></span> <?= ucfirst($u['status']) ?>
                    </td>
                    <td>
                        <div style="font-size:0.85rem; color:#334155;"><?= $u['last_login'] ? date('d M, h:i A', strtotime($u['last_login'])) : 'Never' ?></div>
                        <small style="color:#94a3b8;"><?= $u['last_ip'] ?? 'No IP' ?></small>
                    </td>
                    <td style="text-align:right;">
                        <div class="act-group" style="justify-content:flex-end;">
                            <button type="button" class="act-btn" title="Edit User" onclick='openEdit(<?= json_encode($u) ?>)'><i class="fa-solid fa-pen"></i></button>
                            
                            <button type="button" class="act-btn" title="Manage Funds" onclick="openFunds(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-coins"></i></button>
                            
                            <button type="button" class="act-btn" title="Send Email" onclick="openMail(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>')"><i class="fa-regular fa-envelope"></i></button>
                            
                            <?php if($u['status']=='active'): ?>
                                <a href="?toggle_ban=<?= $u['id'] ?>" class="act-btn act-ban" title="Ban User" onclick="return confirm('Ban this user?')"><i class="fa-solid fa-ban"></i></a>
                            <?php else: ?>
                                <a href="?toggle_ban=<?= $u['id'] ?>" class="act-btn act-unban" title="Activate User" onclick="return confirm('Activate user?')"><i class="fa-solid fa-check"></i></a>
                            <?php endif; ?>
                            
                            <div class="action-dropdown" style="position:relative;">
                                <button class="act-btn" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display=='block'?'none':'block'"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="dropdown-menu" style="display:none; position:absolute; right:0; top:40px; background:white; border:1px solid #ddd; border-radius:8px; width:160px; z-index:50; box-shadow:0 5px 15px rgba(0,0,0,0.1);">
                                    <a href="?login_as=<?= $u['id'] ?>" style="display:block; padding:10px; text-decoration:none; color:#333; font-size:0.85rem; hover:background:#f5f5f5;" onclick="return confirm('Login as user?')">👻 Login As User</a>
                                    <a href="#" onclick="openPass(<?= $u['id'] ?>)" style="display:block; padding:10px; text-decoration:none; color:#333; font-size:0.85rem;">🔑 Change Pass</a>
                                    <a href="?delete_id=<?= $u['id'] ?>" style="display:block; padding:10px; text-decoration:none; color:red; font-size:0.85rem;" onclick="return confirm('DELETE PERMANENTLY?')">🗑️ Delete User</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h3 style="margin-top:0; margin-bottom:20px;">✏️ Edit User Details</h3>
        
        <form method="POST">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" id="edit_uid">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="inp-modern" required>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="inp-modern" required>
                </div>
            </div>
            <br>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Role</label>
                    <select name="role" id="edit_role" class="inp-modern">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:600;">Status</label>
                    <select name="status" id="edit_status" class="inp-modern">
                        <option value="active">Active</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
            </div>
            <br>

            <label style="font-size:0.85rem; font-weight:600; color:#4f46e5;">💸 Custom Rate (VIP Settings)</label>
            <div style="display:flex; gap:10px; margin-top:5px; background:#f8fafc; padding:10px; border-radius:10px; border:1px solid #e2e8f0;">
                <select name="rate_type" id="edit_rate_type" class="inp-modern" style="width:40%;">
                    <option value="discount">Give Discount (-)</option>
                    <option value="premium">Increase Price (+)</option>
                </select>
                <input type="number" name="rate_value" id="edit_rate_val" class="inp-modern" placeholder="Percentage (e.g. 10)" step="0.01">
            </div>
            <br>

            <label style="font-size:0.85rem; font-weight:600;">Admin Note (Private)</label>
            <textarea name="admin_note" id="edit_note" class="inp-modern" rows="2" placeholder="Only admins can see this..."></textarea>
            
            <div style="margin-top:15px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_verified_badge" id="edit_badge" style="width:18px; height:18px;">
                    <span style="font-weight:600; color:#334155;">Give Verified Badge (Blue Tick)</span>
                </label>
            </div>

            <button type="submit" class="bx-primary btn-x" style="width:100%; justify-content:center; margin-top:20px;">Save Changes</button>
        </form>
    </div>
</div>

<div id="fundsModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal('fundsModal')">&times;</span>
        <h3 style="margin-top:0;">💰 Wallet Manager</h3>
        <p id="fundUser" style="color:#64748b; font-size:0.9rem; margin-bottom:20px;"></p>
        
        <form method="POST">
            <input type="hidden" name="update_balance" value="1">
            <input type="hidden" name="user_id" id="fund_uid">
            
            <label style="font-size:0.85rem; font-weight:600;">Action</label>
            <select name="type" class="inp-modern" style="margin-bottom:15px;">
                <option value="add">➕ Add Money (Credit)</option>
                <option value="deduct">➖ Remove Money (Debit)</option>
            </select>
            
            <label style="font-size:0.85rem; font-weight:600;">Amount</label>
            <input type="number" name="amount" class="inp-modern" placeholder="e.g. 500" step="any" required style="margin-bottom:15px;">
            
            <label style="font-size:0.85rem; font-weight:600;">Reason (For Logs)</label>
            <input type="text" name="reason" class="inp-modern" placeholder="e.g. Bonus / Refund" required style="margin-bottom:20px;">
            
            <button type="submit" class="bx-primary btn-x" style="width:100%; justify-content:center;">Update Balance</button>
        </form>
    </div>
</div>

<div id="mailModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal('mailModal')">&times;</span>
        <h3 style="margin-top:0;">✉️ Send Quick Email</h3>
        <p id="mailUser" style="color:#64748b; font-size:0.9rem; margin-bottom:20px;"></p>
        
        <form method="POST">
            <input type="hidden" name="send_single_mail" value="1">
            <input type="hidden" name="user_id" id="mail_uid">
            
            <label style="font-size:0.85rem; font-weight:600;">Subject</label>
            <input type="text" name="subject" class="inp-modern" placeholder="Important Notice..." required style="margin-bottom:15px;">
            
            <label style="font-size:0.85rem; font-weight:600;">Message</label>
            <textarea name="message" class="inp-modern" rows="4" placeholder="Type your message here..." required style="margin-bottom:20px;"></textarea>
            
            <button type="submit" class="bx-primary btn-x" style="width:100%; justify-content:center;">Send Email 🚀</button>
        </form>
    </div>
</div>

<div id="passModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-modal" onclick="closeModal('passModal')">&times;</span>
        <h3 style="margin-top:0;">🔑 Reset Password</h3>
        
        <form method="POST">
            <input type="hidden" name="change_pass" value="1">
            <input type="hidden" name="user_id" id="pass_uid">
            
            <label style="font-size:0.85rem; font-weight:600;">New Password</label>
            <input type="text" name="new_password" class="inp-modern" placeholder="Enter new strong password" required minlength="6" style="margin-bottom:20px;">
            
            <button type="submit" class="bx-primary btn-x" style="width:100%; justify-content:center;">Update Password</button>
        </form>
    </div>
</div>

<script>
// --- MODAL LOGIC ---
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Edit User Populate
function openEdit(u) {
    document.getElementById('edit_uid').value = u.id;
    document.getElementById('edit_name').value = u.name;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_role').value = u.role || 'user'; // FIX: Default to 'user' if undefined
    document.getElementById('edit_status').value = u.status;
    document.getElementById('edit_note').value = u.admin_note;
    document.getElementById('edit_badge').checked = (u.is_verified_badge == 1);

    // Rate Logic
    let rate = parseFloat(u.custom_rate);
    if(rate < 0) {
        document.getElementById('edit_rate_type').value = 'discount';
        document.getElementById('edit_rate_val').value = Math.abs(rate);
    } else {
        document.getElementById('edit_rate_type').value = 'premium';
        document.getElementById('edit_rate_val').value = rate;
    }
    
    openModal('editModal');
}

function openFunds(id, name) {
    document.getElementById('fund_uid').value = id;
    document.getElementById('fundUser').innerText = 'For: ' + name;
    openModal('fundsModal');
}

function openMail(id, email) {
    document.getElementById('mail_uid').value = id;
    document.getElementById('mailUser').innerText = 'To: ' + email;
    openModal('mailModal');
}

function openPass(id) {
    document.getElementById('pass_uid').value = id;
    openModal('passModal');
}

// Close Dropdowns on outside click
window.onclick = function(event) {
    if (!event.target.matches('.act-btn') && !event.target.matches('.fa-ellipsis-vertical')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.style.display === 'block') {
                openDropdown.style.display = 'none';
            }
        }
    }
    if(event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include '_footer.php'; ?>