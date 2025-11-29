<?php
include '_header.php';
requireAdmin();

// Close Ticket
if(isset($_GET['close'])) {
    $db->prepare("UPDATE tickets SET status='closed' WHERE id=?")->execute([(int)$_GET['close']]);
}

// Reply
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['reply'])) {
    $msg = $_POST['message']; $tid = $_POST['id'];
    $db->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'admin', ?)")->execute([$tid, $msg]);
    $db->prepare("UPDATE tickets SET status='answered' WHERE id=?")->execute([$tid]);
}

$tickets = $db->query("SELECT t.*, u.email FROM tickets t JOIN users u ON t.user_id=u.id ORDER BY t.updated_at DESC")->fetchAll();
?>

<h1>🎫 Support Tickets</h1>
<div class="admin-table-responsive">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>User</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach($tickets as $t): ?>
            <tr>
                <td>#<?= $t['id'] ?></td>
                <td><?= $t['email'] ?></td>
                <td><?= $t['subject'] ?></td>
                <td><span class="badge" style="background:<?= $t['status']=='pending'?'#ffc107':($t['status']=='answered'?'#28a745':'#6c757d') ?>"><?= $t['status'] ?></span></td>
                <td>
                    <button onclick="openChat(<?= $t['id'] ?>)" class="btn-edit">View</button>
                    <a href="tickets.php?close=<?= $t['id'] ?>" class="btn-delete">Close</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>