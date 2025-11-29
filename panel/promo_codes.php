<?php
include '_header.php';
requireAdmin();

$error = ''; 
$success = '';

// --- DELETE CODE ---
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM promo_codes WHERE id=?")->execute([(int)$_GET['delete']]);
    $success = "Promo Code Deleted Successfully!";
}

// --- CREATE CODE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = strtoupper(sanitize($_POST['code']));
    $bonus = (float)$_POST['bonus'];
    $min = (float)$_POST['min'];
    $max_uses = (int)$_POST['max_uses'];
    
    if(empty($code)) {
        $error = "Code name is required.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO promo_codes (code, deposit_bonus, min_deposit, max_uses, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$code, $bonus, $min, $max_uses]);
            $success = "Promo Code Created! Users will get $bonus% Extra.";
        } catch (Exception $e) {
            $error = "Error: Code already exists.";
        }
    }
}

$codes = $db->query("SELECT * FROM promo_codes ORDER BY id DESC")->fetchAll();
?>

<style>
    .admin-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #eee; margin-bottom: 30px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .badge { padding: 5px 10px; border-radius: 50px; font-size: 12px; font-weight: bold; }
    .bg-purple { background: #e9d5ff; color: #7e22ce; }
    .bg-green { background: #dcfce7; color: #166534; }
</style>

<h1>🎟️ Promo Codes Manager</h1>

<?php if($error) echo "<div class='message error'>$error</div>"; ?>
<?php if($success) echo "<div class='message success'>$success</div>"; ?>

<div class="admin-card">
    <h3 style="margin-top:0;">Create New Coupon</h3>
    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Users can apply this code on the Add Funds page.</p>
    
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Promo Code Name</label>
                <input type="text" name="code" class="form-control" placeholder="e.g. SUPER10" required>
            </div>
            <div class="form-group">
                <label>Bonus Percentage (%)</label>
                <input type="number" name="bonus" class="form-control" placeholder="e.g. 10 (for 10%)" required step="0.01">
                <small style="color:#666">If user deposits 1000, they get 100 extra (10%).</small>
            </div>
            <div class="form-group">
                <label>Min Deposit Amount</label>
                <input type="number" name="min" class="form-control" placeholder="e.g. 500" required>
            </div>
            <div class="form-group">
                <label>Max Uses (0 = Unlimited)</label>
                <input type="number" name="max_uses" class="form-control" value="0" required>
            </div>
        </div>
        <button class="btn btn-primary" style="margin-top:15px;">Create Code</button>
    </form>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Bonus Type</th>
                <th>Min Deposit</th>
                <th>Usage</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($codes as $c): ?>
            <tr>
                <td><span class="badge bg-purple"><?= sanitize($c['code']) ?></span></td>
                <td><span class="badge bg-green">+<?= $c['deposit_bonus'] ?>% Extra</span></td>
                <td><?= formatCurrency($c['min_deposit']) ?></td>
                <td><?= $c['current_uses'] ?> / <?= $c['max_uses'] == 0 ? '∞' : $c['max_uses'] ?></td>
                <td>
                    <a href="promo_codes.php?delete=<?= $c['id'] ?>" class="btn-delete" onclick="return confirm('Delete this code?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>