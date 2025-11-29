<?php
include '_header.php'; 
requireAdmin();

$error = '';
$success = '';

// --- ACTIONS ---

// 1. Update Balance (Quick Action)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_balance'])) {
    $uid = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; // 'add' or 'deduct'
    $reason = sanitize($_POST['reason']);
    
    try {
        $db->beginTransaction();
        if ($type == 'add') {
            $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $uid]);
            // Ledger
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'credit', ?, ?)")->execute([$uid, $amount, "Admin Add: $reason"]);
            $success = "Added " . formatCurrency($amount) . " to User #$uid";
        } else {
            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $uid]);
            // Ledger
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, note) VALUES (?, 'debit', ?, ?)")->execute([$uid, $amount, "Admin Deduct: $reason"]);
            $success = "Deducted " . formatCurrency($amount) . " from User #$uid";
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// 2. Delete User
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    if ($id == $_SESSION['user_id']) {
        $error = "You cannot delete yourself!";
    } else {
        try {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $success = "User #$id deleted successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// --- FETCH USERS ---
$search_term = $_GET['search'] ?? '';
$params = [];
$where_clause = "";

if (!empty($search_term)) {
    $where_clause = " WHERE name LIKE ? OR email LIKE ?";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$total_stmt = $db->prepare("SELECT COUNT(id) FROM users" . $where_clause);
$total_stmt->execute($params);
$total_users = $total_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

$sql = "SELECT * FROM users" . $where_clause . " ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<style>
/* --- MODERN ADMIN UI --- */
.admin-header-flex {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
    background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;
}
.search-form { display: flex; gap: 10px; }
.search-form input { padding: 8px 15px; border: 1px solid #ddd; border-radius: 6px; width: 250px; }

.admin-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.admin-table th { background: #f8f9fa; text-align: left; padding: 15px; font-weight: 700; color: #4b5563; }
.admin-table td { padding: 15px; border-bottom: 1px solid #f3f4f6; color: #1f2937; }
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover { background: #f9fafb; }

.role-badge { padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
.role-admin { background: #fee2e2; color: #b91c1c; }
.role-user { background: #dbeafe; color: #1e40af; }
.role-staff { background: #fef3c7; color: #92400e; }

.balance-text { font-weight: 700; color: #059669; }

.btn-group { display: flex; gap: 5px; }
.btn-icon { 
    padding: 6px 10px; border-radius: 6px; font-size: 0.85rem; text-decoration: none; 
    display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none;
}
.btn-bal { background: #d1fae5; color: #065f46; }
.btn-bal:hover { background: #a7f3d0; }
.btn-edit { background: #e0f2fe; color: #0369a1; }
.btn-edit:hover { background: #bae6fd; }
.btn-del { background: #fee2e2; color: #b91c1c; }
.btn-del:hover { background: #fecaca; }

/* Modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal-content { background: #fff; padding: 25px; border-radius: 12px; width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #374151; }
.form-input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 15px; }
.btn-submit { width: 100%; padding: 10px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; }
</style>

<div class="admin-container">
    
    <div class="admin-header-flex">
        <h2 style="margin:0;">👥 Users Manager (Total: <?= $total_users ?>)</h2>
        
        <form action="" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search name or email..." value="<?= sanitize($search_term) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if($search_term): ?><a href="users.php" class="btn btn-secondary">Reset</a><?php endif; ?>
        </form>
    </div>

    <?php if ($error): ?><div class="message error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="message success"><?= $success ?></div><?php endif; ?>

    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User Info</th>
                    <th>Role</th>
                    <th>Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 30px;">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): 
                        $roleClass = 'role-user';
                        if($u['role']=='admin') $roleClass = 'role-admin';
                        if($u['role']=='staff') $roleClass = 'role-staff';
                    ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td>
                            <div style="font-weight:600;"><?= sanitize($u['name'] ?? 'No Name') ?></div>
                            <div style="font-size:0.85rem; color:#6b7280;"><?= sanitize($u['email']) ?></div>
                            <div style="font-size:0.75rem; color:#9ca3af;"><?= formatDate($u['created_at']) ?></div>
                        </td>
                        <td><span class="role-badge <?= $roleClass ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td class="balance-text"><?= formatCurrency($u['balance']) ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn-icon btn-bal" onclick="openBalModal(<?= $u['id'] ?>, '<?= sanitize($u['email']) ?>')">
                                    💰 Funds
                                </button>
                                <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn-icon btn-edit">✏️ Edit</a>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="users.php?delete_id=<?= $u['id'] ?>" class="btn-icon btn-del" onclick="return confirm('Delete this user?')">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination" style="margin-top:20px; text-align:center;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= $search_term ?>" class="btn btn-secondary">« Prev</a>
        <?php endif; ?>
        
        <span style="margin: 0 10px; font-weight:600;">Page <?= $page ?> of <?= $total_pages ?></span>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= $search_term ?>" class="btn btn-secondary">Next »</a>
        <?php endif; ?>
    </div>

</div>

<div class="modal-overlay" id="balModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0;">Manage Funds</h3>
            <button onclick="closeBalModal()" style="border:none; background:none; font-size:1.2rem; cursor:pointer;">✕</button>
        </div>
        <p id="modalUser" style="margin-bottom:15px; color:#6b7280;"></p>
        
        <form method="POST">
            <input type="hidden" name="update_balance" value="1">
            <input type="hidden" name="user_id" id="modalUserId">
            
            <div class="form-group">
                <label class="form-label">Action</label>
                <select name="type" class="form-input">
                    <option value="add">➕ Add Funds</option>
                    <option value="deduct">➖ Deduct Funds</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Amount</label>
                <input type="number" name="amount" class="form-input" placeholder="e.g. 500" required step="0.01">
            </div>

            <div class="form-group">
                <label class="form-label">Reason / Note</label>
                <input type="text" name="reason" class="form-input" placeholder="Bonus / Correction" required>
            </div>
            
            <button class="btn-submit">Update Balance</button>
        </form>
    </div>
</div>

<script>
function openBalModal(id, email) {
    document.getElementById('modalUserId').value = id;
    document.getElementById('modalUser').innerText = 'User: ' + email;
    document.getElementById('balModal').classList.add('active');
}
function closeBalModal() {
    document.getElementById('balModal').classList.remove('active');
}
</script>

<?php include '_footer.php'; ?>