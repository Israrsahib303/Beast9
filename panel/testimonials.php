<?php
include '_header.php';

// --- 1. APPROVE REWARD ---
if (isset($_POST['approve_id'])) {
    $id = (int)$_POST['approve_id'];
    $amount = (float)$_POST['reward_amount'];
    $user_id = (int)$_POST['user_id'];

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE user_testimonials SET status = 'approved', reward_amount = ? WHERE id = ?");
        $stmt->execute([$amount, $id]);
        
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, ref_type, ref_id, note) VALUES (?, 'credit', ?, 'reward', ?, 'Video Reward')")->execute([$user_id, $amount, $id]);
        
        $db->commit();
        echo "<script>window.location='testimonials.php?msg=approved';</script>";
    } catch (Exception $e) { $db->rollBack(); echo "<script>alert('Error');</script>"; }
}

// --- 2. REVOKE & BAN (STRICT MODE) ---
if (isset($_POST['ban_id'])) {
    $id = (int)$_POST['ban_id'];
    $user_id = (int)$_POST['user_id'];
    
    // Get reward amount to deduct
    $stmt = $db->prepare("SELECT reward_amount FROM user_testimonials WHERE id = ?");
    $stmt->execute([$id]);
    $amount = $stmt->fetchColumn();

    try {
        $db->beginTransaction();
        
        // A. Update Status to Rejected (Delete nahi karna)
        $db->prepare("UPDATE user_testimonials SET status = 'rejected', reward_amount = 0 WHERE id = ?")->execute([$id]);
        
        // B. Deduct Funds (Penalty)
        if ($amount > 0) {
            $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $user_id]);
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, ref_type, ref_id, note) VALUES (?, 'debit', ?, 'penalty', ?, 'Penalty: Video Deleted')")->execute([$user_id, $amount, $id]);
        }

        // C. BAN USER (Update Status Only)
        $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$user_id]);
        
        $db->commit();
        echo "<script>window.location='testimonials.php?msg=banned';</script>";
    } catch (Exception $e) { $db->rollBack(); echo "<script>alert('Error: ".$e->getMessage()."');</script>"; }
}

// --- 3. UNBAN USER (FORGIVENESS) ---
if (isset($_POST['unban_id'])) {
    $user_id = (int)$_POST['unban_id'];
    $db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$user_id]);
    echo "<script>window.location='testimonials.php?msg=unbanned';</script>";
}

// --- 4. REJECT PENDING ---
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $db->prepare("UPDATE user_testimonials SET status = 'rejected' WHERE id = ?")->execute([$id]);
    echo "<script>window.location='testimonials.php';</script>";
}

// DATA FETCH (Ab hum banned users ko bhi fetch karenge)
$pending = $db->query("SELECT t.*, u.email, u.status as u_status FROM user_testimonials t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending' ORDER BY t.id DESC")->fetchAll();
$history = $db->query("SELECT t.*, u.email, u.status as u_status FROM user_testimonials t JOIN users u ON t.user_id = u.id WHERE t.status != 'pending' ORDER BY t.id DESC LIMIT 50")->fetchAll();
?>

<style>
    :root { --admin-primary: #4f46e5; }
    
    .page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .page-title { font-size: 1.8rem; font-weight: 800; color: #1f2937; margin: 0; display: flex; align-items: center; gap: 10px; }
    
    /* Cards */
    .data-card {
        background: #fff; border-radius: 16px; border: 1px solid #e5e7eb;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 30px;
    }
    .dc-head {
        padding: 15px 25px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;
        display: flex; justify-content: space-between; align-items: center; font-weight: 700;
    }
    
    /* Table */
    .custom-table { width: 100%; border-collapse: collapse; }
    .custom-table th { text-align: left; padding: 15px 20px; background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .custom-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #374151; font-size: 0.9rem; }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover { background: #f8fafc; }

    /* Banned Row Highlight */
    .row-banned { background-color: #fef2f2 !important; }
    .row-banned td { color: #991b1b; }

    /* Buttons */
    .btn-act { padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; }
    .btn-approve { background: #dcfce7; color: #166534; } .btn-approve:hover { background: #16a34a; color: #fff; }
    .btn-reject { background: #fee2e2; color: #991b1b; } .btn-reject:hover { background: #dc2626; color: #fff; }
    .btn-view { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .btn-unban { background: #059669; color: #fff; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.2); }
    
    .inp-reward { width: 80px; padding: 5px 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-right: 5px; }
</style>

<div class="container-fluid" style="padding:30px;">
    
    <div class="page-head">
        <h1 class="page-title"><i class="fas fa-video" style="color:#6366f1;"></i> Video Rewards Manager</h1>
    </div>

    <div class="data-card">
        <div class="dc-head" style="border-left: 5px solid #f59e0b;">
            <span><i class="fas fa-clock text-warning me-2"></i> Pending Requests</span>
            <span class="badge bg-warning text-dark"><?= count($pending) ?></span>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead><tr><th>User</th><th>Platform</th><th>Link</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if(empty($pending)): ?><tr><td colspan="5" class="text-center p-4 text-muted">No pending videos.</td></tr><?php endif; ?>
                    <?php foreach($pending as $row): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= $row['email'] ?></div>
                            <small style="color:#6b7280;">ID: #<?= $row['user_id'] ?></small>
                        </td>
                        <td><span class="badge bg-dark"><?= $row['platform'] ?></span></td>
                        <td><a href="<?= $row['video_link'] ?>" target="_blank" class="btn-act btn-view"><i class="fas fa-external-link-alt"></i> Watch</a></td>
                        <td><?= date('d M, h:i A', strtotime($row['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:flex; align-items:center;">
                                <input type="hidden" name="approve_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                <input type="number" name="reward_amount" class="inp-reward" placeholder="PKR" required>
                                <button type="submit" class="btn-act btn-approve"><i class="fas fa-check"></i> Pay</button>
                                <a href="?reject=<?= $row['id'] ?>" class="btn-act btn-reject ms-2" onclick="return confirm('Reject?')"><i class="fas fa-times"></i></a>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="data-card">
        <div class="dc-head" style="border-left: 5px solid #10b981;">
            <span><i class="fas fa-history text-success me-2"></i> History & Audit</span>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead><tr><th>User</th><th>Status</th><th>Reward / Penalty</th><th>Link</th><th>Audit Action</th></tr></thead>
                <tbody>
                    <?php foreach($history as $row): 
                        $is_banned = ($row['u_status'] == 'banned');
                        $row_class = $is_banned ? 'row-banned' : '';
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td>
                            <div style="font-weight:600;"><?= $row['email'] ?></div>
                            <?php if($is_banned): ?><span class="badge bg-danger">BANNED USER</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 'approved'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif($row['status'] == 'rejected'): ?>
                                <span class="badge bg-secondary">Rejected/Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;">
                            <?php if($row['reward_amount'] > 0): ?>
                                <span class="text-success">+ <?= number_format($row['reward_amount']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= $row['video_link'] ?>" target="_blank" class="btn-act btn-view">Check Video</a></td>
                        <td>
                            <?php if($is_banned): ?>
                                <form method="POST" onsubmit="return confirm('Unban this user? They will be able to login again.')">
                                    <input type="hidden" name="unban_id" value="<?= $row['user_id'] ?>">
                                    <button type="submit" class="btn-act btn-unban">
                                        <i class="fas fa-unlock"></i> Unban User
                                    </button>
                                </form>
                            <?php elseif($row['status'] == 'approved'): ?>
                                <form method="POST" onsubmit="return confirm('⚠️ DANGER: Deduct money & BAN user?')">
                                    <input type="hidden" name="ban_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                    <button type="submit" class="btn-act btn-reject">
                                        <i class="fas fa-ban"></i> Revoke & Ban
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '_footer.php'; ?>