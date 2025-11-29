<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include '_header.php';

// Fetch stats (Yeh aapka purana code hai)
try {
    $total_users = $db->query("SELECT COUNT(id) FROM users WHERE is_admin = 0")->fetchColumn();
    $total_orders = $db->query("SELECT COUNT(id) FROM orders")->fetchColumn();
    $total_products = $db->query("SELECT COUNT(id) FROM products")->fetchColumn();
    $total_revenue = $db->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed' OR status = 'expired'")->fetchColumn();
    $pending_payments = $db->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'")->fetchColumn();
    $expired_subs = $db->query("SELECT COUNT(id) FROM orders WHERE status = 'expired'")->fetchColumn();
    
    // Recent Orders
    $stmt = $db->query("
        SELECT o.*, u.email, p.name 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN products p ON o.product_id = p.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

} catch (PDOException $e) {
    // Handle error
    echo "<div class='message error'>Failed to load dashboard stats: " . $e->getMessage() . "</div>";
}
?>

<h1>Subscriptions Dashboard</h1>

<div class="stat-grid">
    <div class="stat-card">
        <h3>Total Users</h3>
        <p><?php echo $total_users ?? 0; ?></p>
    </div>
    <div class="stat-card">
        <h3>Sub. Orders</h3>
        <p><?php echo $total_orders ?? 0; ?></p>
    </div>
    <div class="stat-card">
        <h3>Sub. Revenue</h3>
        <p class="positive"><?php echo formatCurrency($total_revenue ?? 0); ?></p>
    </div>
    <div class="stat-card">
        <h3>Pending Payments</h3>
        <p class="negative"><?php echo $pending_payments ?? 0; ?></p>
    </div>
    <div class="stat-card">
        <h3>Active Products</h3>
        <p><?php echo $total_products ?? 0; ?></p>
    </div>
     <div class="stat-card">
        <h3>Expired Subscriptions</h3>
        <p><?php echo $expired_subs ?? 0; ?></p>
    </div>
</div>

<h2>Recent Subscription Orders</h2>
<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_orders)): ?>
                <tr><td colspan="6" style="text-align: center;">No recent orders.</td></tr>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['code']; ?></strong></td>
                    <td><?php echo sanitize($order['email']); ?></td>
                    <td><?php echo sanitize($order['name']); ?></td>
                    <td><?php echo formatCurrency($order['total_price']); ?></td>
                    <td><span class="status-badge status-<?php echo $order['status'] == 'completed' ? 'active' : $order['status']; ?>"><?php echo $order['status'] == 'completed' ? 'Active' : ucfirst($order['status']); ?></span></td>
                    <td><?php echo formatDate($order['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>