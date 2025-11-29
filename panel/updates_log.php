<?php
include '_header.php';
requireAdmin();

if(isset($_POST['clear'])) {
    $db->query("TRUNCATE TABLE service_updates");
    echo "<script>window.location.href='updates_log.php';</script>";
}

$logs = $db->query("SELECT * FROM service_updates ORDER BY created_at DESC")->fetchAll();
?>
<h1>📢 Service Updates Log</h1>
<form method="POST" style="margin-bottom:20px;">
    <button name="clear" class="btn-delete" onclick="return confirm('Clear all logs?')">🗑️ Clear All History</button>
</form>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead><tr><th>Type</th><th>Service</th><th>Category</th><th>Time</th></tr></thead>
        <tbody>
            <?php foreach($logs as $l): ?>
            <tr>
                <td><span class="badge" style="background:<?= $l['type']=='removed'?'red':'green' ?>"><?= $l['type'] ?></span></td>
                <td><?= sanitize($l['service_name']) ?></td>
                <td><?= sanitize($l['category_name']) ?></td>
                <td><?= $l['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include '_footer.php'; ?>