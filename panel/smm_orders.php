<?php
include '_header.php';
// --- NAYA: Wallet class ko include karein refund ke liye ---
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$error = '';
$success = '';

// --- NAYA: Manual Actions (Complete/Cancel) Handle Karein ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    
    try {
        if ($_POST['action'] == 'force_complete') {
            // Zabardasti 'Completed' mark karein
            $stmt = $db->prepare("UPDATE smm_orders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$order_id]);
            $success = "Order #$order_id has been manually marked as Completed.";

        } elseif ($_POST['action'] == 'force_cancel') {
            // Order ko fetch karein taake user_id aur charge mil sakay
            $stmt = $db->prepare("SELECT user_id, charge, status FROM smm_orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if ($order && $order['status'] != 'cancelled' && $order['status'] != 'refunded') {
                $db->beginTransaction();
                
                // 1. Order ko 'cancelled' mark karein
                $stmt_update = $db->prepare("UPDATE smm_orders SET status = 'cancelled' WHERE id = ?");
                $stmt_update->execute([$order_id]);
                
                // 2. User ko refund karein
                $refund_note = "Admin Manual Refund for SMM Order #$order_id";
                $wallet->addCredit($order['user_id'], (float)$order['charge'], 'admin_adjust', $order_id, $refund_note);
                
                $db->commit();
                $success = "Order #$order_id has been Cancelled and " . formatCurrency($order['charge']) . " refunded to user.";
            } else {
                $error = "Order is already refunded or does not exist.";
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "An error occurred: " . $e->getMessage();
    }
}
// --- NAYA LOGIC KHATAM ---


// Pagination
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Search/Filter Logic
$where = '';
$params = [];
$search_query = '';

if (!empty($_GET['search'])) {
    $search_query = sanitize($_GET['search']);
    // Search by Order ID, Link, or User Email
    $where = 'WHERE (o.id = ? OR o.link LIKE ? OR u.email LIKE ?)';
    $params[] = $search_query;
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

try {
    // Total records for pagination
    $total_records_stmt = $db->prepare("SELECT COUNT(o.id) FROM smm_orders o LEFT JOIN users u ON o.user_id = u.id $where");
    foreach ($params as $key => $value) {
        $total_records_stmt->bindValue($key + 1, $value);
    }
    $total_records_stmt->execute();
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch orders
    // --- NAYA BADLAAO: `o.start_count`, `o.remains` ko select karein ---
    $stmt = $db->prepare("
        SELECT o.*, u.email as user_email, s.name as service_name, p.name as provider_name
        FROM smm_orders o
        JOIN users u ON o.user_id = u.id
        JOIN smm_services s ON o.service_id = s.id
        JOIN smm_providers p ON s.provider_id = p.id
        $where
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $smm_orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to fetch SMM orders: ' . $e->getMessage();
    $smm_orders = [];
    $total_pages = 0;
}
?>

<h1>SMM Orders</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="search-box">
    <form action="smm_orders.php" method="GET">
        <input type="text" name="search" class="form-control" placeholder="Search by Order ID, Link, or User Email..." value="<?php echo sanitize($search_query); ?>">
    </form>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Provider ID</th>
                <th>User</th>
                <th>Service</th>
                <th>Charge</th>
                <th>Link</th>
                <th>Status</th>
                <th>Start Count</th>
                <th>Remains</th>
                <th style="width: 150px;">Actions</th>
                </tr>
        </thead>
        <tbody>
            <?php if (empty($smm_orders)): ?>
                <tr><td colspan="10" style="text-align: center;">No SMM orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($smm_orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                    <td><?php echo $order['provider_order_id'] ?? 'N/A'; ?></td>
                    <td><?php echo sanitize($order['user_email']); ?></td>
                    <td><?php echo sanitize($order['service_name']); ?></td>
                    <td><?php echo formatCurrency($order['charge']); ?></td>
                    <td style="word-break: break-all; max-width: 150px;"><a href="<?php echo sanitize($order['link']); ?>" target="_blank"><?php echo substr(sanitize($order['link']), 0, 30); ?>...</a></td>
                    <td>
                        <span class="status-badge status-<?php echo str_replace(' ', '_', strtolower($order['status'])); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    
                    <td class="smm-counts"><span class="start"><?php echo number_format($order['start_count'] ?? 0); ?></span></td>
                    <td class="smm-counts"><span class="remains"><?php echo number_format($order['remains'] ?? 0); ?></span></td>
                    
                    <td class="action-buttons">
                        <?php if ($order['provider_order_id']): ?>
                            <button class="btn-action btn-info btn-live-check" data-order-id="<?php echo $order['id']; ?>" data-loading-text="Checking...">
                                <i class="fas fa-sync-alt"></i> Check Status
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] != 'completed'): ?>
                            <form action="smm_orders.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="action" value="force_complete" class="btn-action btn-success">
                                    <i class="fas fa-check-circle"></i> Force Complete
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] != 'cancelled' && $order['status'] != 'refunded'): ?>
                            <form action="smm_orders.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="action" value="force_cancel" class="btn-action btn-delete" 
                                        onclick="return confirm('Are you SURE you want to CANCEL this order and REFUND <?php echo formatCurrency($order['charge']); ?> to the user?');">
                                    <i class="fas fa-times-circle"></i> Cancel & Refund
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top: 1rem; text-align: center;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&search=<?php echo sanitize($search_query); ?>" 
           style="display: inline-block; padding: 5px 10px; background: #333; color: #fff; text-decoration: none; margin: 2px; <?php echo ($i == $page) ? 'background: var(--brand-red);' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<?php include '_footer.php'; ?>