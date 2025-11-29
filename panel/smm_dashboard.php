<?php
include '_header.php'; 
require_once __DIR__ . '/../includes/smm_api.class.php';

// --- NAYI SQL Queries (Sirf SMM ke liye) ---
// Note: Logic is strictly preserved as per request
try {
    // 1. Total SMM Revenue
    $smm_total_revenue = $db->query("SELECT SUM(charge) FROM smm_orders")->fetchColumn() ?? 0;

    // 2. Total SMM Orders
    $smm_total_orders = $db->query("SELECT COUNT(id) FROM smm_orders")->fetchColumn() ?? 0;

    // 3. SMM Orders (Pending)
    $smm_pending_orders = $db->query("SELECT COUNT(id) FROM smm_orders WHERE status = 'pending'")->fetchColumn() ?? 0;

    // 4. SMM Orders (In Progress)
    $smm_in_progress = $db->query("SELECT COUNT(id) FROM smm_orders WHERE status = 'in_progress'")->fetchColumn() ?? 0;
    
    // 5. Total SMM Profit
    $stmt_profit = $db->query("
        SELECT SUM(o.charge - ( (o.quantity / 1000) * s.base_price )) 
        FROM smm_orders o
        JOIN smm_services s ON o.service_id = s.id
        WHERE o.status = 'completed' OR o.status = 'partial'
    ");
    $smm_total_profit = $stmt_profit->fetchColumn() ?? 0;
    
    // 6. Provider Balance (Live API Call)
    $provider_balance = 'N/A';
    $provider_currency = '';
    $stmt_provider = $db->query("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
    $provider = $stmt_provider->fetch();
    if ($provider) {
        $api = new SmmApi($provider['api_url'], $provider['api_key']);
        $balance_result = $api->getBalance();
        if ($balance_result['success']) {
            $provider_balance = number_format($balance_result['balance'], 2);
            $provider_currency = $balance_result['currency'];
        } else {
            $provider_balance = 'Error';
        }
    }
    
    // 7. Recent SMM Orders
    $stmt_recent = $db->query("
        SELECT o.*, u.email, s.name 
        FROM smm_orders o
        JOIN users u ON o.user_id = u.id
        JOIN smm_services s ON o.service_id = s.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_smm_orders = $stmt_recent->fetchAll();

} catch (PDOException $e) {
    echo "<div class='message error'>Failed to load SMM dashboard stats: " . $e->getMessage() . "</div>";
}

// --- NAYA FEATURE: System Health Function ---
function getCronJobStatus() {
    $log_files = [
        'Order Placer' => 'smm_order_placer.log',
        'Status Sync' => 'smm_status_sync.log',
        'Service Sync' => 'smm_service_sync.log',
        'Email Payments' => 'email_payments.log'
    ];
    
    $log_dir = __DIR__ . '/../assets/logs/';
    $status_data = [];
    $now = new DateTime();

    foreach ($log_files as $name => $file) {
        $file_path = $log_dir . $file;
        $status_text = 'Not Run Yet';
        $status_class = 'status-unknown';

        if (file_exists($file_path)) {
            $file_mod_time = filemtime($file_path);
            $last_run = new DateTime('@' . $file_mod_time);
            $diff = $now->getTimestamp() - $last_run->getTimestamp(); // seconds

            if ($diff < 300) { // 5 minutes
                $status_text = 'Running OK';
                $status_class = 'status-ok';
            } elseif ($diff < 3600) { // 1 hour
                $status_text = 'Warning';
                $status_class = 'status-warning';
            } else { // Over 1 hour
                $status_text = 'CRITICAL DOWN';
                $status_class = 'status-down';
            }
            
            // Time formatting
            if ($diff < 60) {
                 $time_ago = $diff . ' sec ago';
            } elseif ($diff < 3600) {
                 $time_ago = floor($diff / 60) . ' min ago';
            } else {
                 $time_ago = floor($diff / 3600) . ' hr ago';
            }

            $status_data[] = [
                'name' => $name,
                'time_ago' => $time_ago,
                'status_text' => $status_text,
                'status_class' => $status_class
            ];

        } else {
            $status_data[] = [
                'name' => $name,
                'time_ago' => 'N/A',
                'status_text' => 'Log File Missing',
                'status_class' => 'status-down'
            ];
        }
    }
    return $status_data;
}

$system_health = getCronJobStatus();
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        /* LIGHT MODE PALETTE */
        --bg-body: #f3f4f6;       /* Soft Light Gray */
        --bg-card: #ffffff;       /* Pure White */
        --text-main: #1f2937;     /* Dark Slate */
        --text-muted: #6b7280;    /* Muted Gray */
        --border-color: #e5e7eb;  /* Light Border */
        
        /* BRAND COLORS */
        --primary: #4f46e5;       /* Indigo/Purple AI feel */
        --success: #10b981;       /* Green */
        --warning: #f59e0b;       /* Orange */
        --danger: #ef4444;        /* Red */
        
        /* EFFECTS */
        --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        --radius: 12px;
    }

    * { box-sizing: border-box; }

    body {
        background-color: var(--bg-body);
        font-family: 'Outfit', sans-serif;
        color: var(--text-main);
        margin: 0;
        padding-bottom: 40px;
    }

    /* Wrapper that fits nicely */
    .dashboard-wrapper {
        width: 100%;
        max-width: 1600px; /* Limits width on ultra-wide screens */
        margin: 0 auto;
        padding: 30px 20px;
    }

    /* Header */
    .dash-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        background: var(--bg-card);
        padding: 20px 25px;
        border-radius: var(--radius);
        box-shadow: var(--shadow-card);
    }
    .dash-header h1 {
        margin: 0;
        font-size: 24px;
        color: var(--text-main);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* GRID SYSTEM - FIX FOR BOXES FITTING */
    .stats-container {
        display: grid;
        /* Auto-fit magic: Boxes won't be too small or too big */
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    /* Card Design */
    .stat-card {
        background: var(--bg-card);
        padding: 25px;
        border-radius: var(--radius);
        box-shadow: var(--shadow-card);
        border: 1px solid var(--border-color);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
        border-color: var(--primary);
    }

    /* Colored Top Strip for AI feel */
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), transparent);
    }

    .stat-card .icon-box {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 24px;
        opacity: 0.1;
        color: var(--text-main);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: var(--text-muted);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .stat-card .value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }

    /* Color Helpers */
    .text-success { color: var(--success); }
    .text-danger { color: var(--danger); }
    .text-warning { color: var(--warning); }
    .text-primary { color: var(--primary); }

    /* Live Pulse */
    .live-pulse {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
        box-shadow: 0 0 0 rgba(79, 70, 229, 0.4);
        animation: pulse 2s infinite;
        margin-right: 6px;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
        100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
    }

    /* Content Split (Health + Table) */
    .content-grid {
        display: grid;
        grid-template-columns: 350px 1fr; /* Fixed Sidebar for Health, Flexible for Table */
        gap: 20px;
    }

    /* Responsive for Tablets/Phones */
    @media (max-width: 1024px) {
        .content-grid {
            grid-template-columns: 1fr; /* Stack them */
        }
    }

    /* Section Boxes */
    .section-box {
        background: var(--bg-card);
        border-radius: var(--radius);
        box-shadow: var(--shadow-card);
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .section-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        background: rgba(249, 250, 251, 0.5);
    }
    .section-header h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Health List */
    .health-list { list-style: none; margin: 0; padding: 0; }
    .health-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    .health-item:last-child { border-bottom: none; }
    .health-item .meta { text-align: right; font-size: 12px; color: var(--text-muted); }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-ok { background: #dcfce7; color: #166534; }
    .badge-warn { background: #fef3c7; color: #92400e; }
    .badge-down { background: #fee2e2; color: #991b1b; }

    /* Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px; /* Prevents squishing */
    }
    .modern-table th {
        text-align: left;
        padding: 15px 20px;
        background: #f9fafb;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .modern-table td {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 14px;
    }
    .modern-table tr:last-child td { border-bottom: none; }
    .modern-table tr:hover { background-color: #f9fafb; }
    
    /* Status Dots in Table */
    .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .dot-completed { background: var(--success); }
    .dot-pending { background: var(--warning); }
    .dot-processing { background: var(--primary); }
    .dot-canceled { background: var(--danger); }

</style>

<div class="dashboard-wrapper">
    
    <div class="dash-header">
        <h1><i class="fa-solid fa-cube text-primary"></i> SMM Nexus</h1>
        <div style="font-size: 14px; color: var(--text-muted);">
            <i class="fa-solid fa-calendar"></i> <?php echo date('d M Y'); ?>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <i class="fa-solid fa-chart-pie icon-box"></i>
            <h3>Total Revenue</h3>
            <p class="value text-success"><?php echo formatCurrency($smm_total_revenue); ?></p>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-money-bill-trend-up icon-box"></i>
            <h3>Net Profit</h3>
            <p class="value text-success"><?php echo formatCurrency($smm_total_profit); ?></p>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-cart-shopping icon-box"></i>
            <h3>Total Orders</h3>
            <p class="value"><?php echo number_format($smm_total_orders); ?></p>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-clock icon-box"></i>
            <h3>Pending</h3>
            <p class="value text-warning"><?php echo number_format($smm_pending_orders); ?></p>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-spinner icon-box"></i>
            <h3>In Progress</h3>
            <p class="value text-primary"><?php echo number_format($smm_in_progress); ?></p>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-wallet icon-box"></i>
            <h3>Provider Balance</h3>
            <p class="value" style="font-size: 24px;">
                <span class="live-pulse"></span>
                <?php echo $provider_balance . ' ' . $provider_currency; ?>
            </p>
        </div>
    </div>

    <div class="content-grid">
        
        <div class="section-box">
            <div class="section-header">
                <h2><i class="fa-solid fa-heart-pulse text-danger"></i> System Health</h2>
            </div>
            <ul class="health-list">
                <?php foreach ($system_health as $job): ?>
                <li class="health-item">
                    <div style="font-weight: 500;"><?php echo $job['name']; ?></div>
                    <div class="meta">
                        <span class="badge <?php 
                            if($job['status_class'] == 'status-ok') echo 'badge-ok';
                            elseif($job['status_class'] == 'status-warning') echo 'badge-warn';
                            else echo 'badge-down';
                        ?>">
                            <?php echo $job['status_text']; ?>
                        </span>
                        <div style="margin-top: 4px;"><?php echo $job['time_ago']; ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section-box">
            <div class="section-header">
                <h2><i class="fa-solid fa-list text-primary"></i> Recent Orders</h2>
            </div>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Service</th>
                            <th>Charge</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_smm_orders)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 30px;">No recent orders.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_smm_orders as $order): ?>
                            <tr>
                                <td><b>#<?php echo $order['id']; ?></b></td>
                                <td><?php echo sanitize($order['email']); ?></td>
                                <td><?php echo mb_strimwidth(sanitize($order['name']), 0, 25, "..."); ?></td>
                                <td><?php echo formatCurrency($order['charge']); ?></td>
                                <td>
                                    <?php 
                                        $st = strtolower($order['status']); 
                                        $dot = 'dot-completed';
                                        if($st == 'pending') $dot = 'dot-pending';
                                        if($st == 'processing' || $st == 'in_progress') $dot = 'dot-processing';
                                        if($st == 'canceled') $dot = 'dot-canceled';
                                    ?>
                                    <span class="status-dot <?php echo $dot; ?>"></span>
                                    <?php echo ucfirst($order['status']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include '_footer.php'; ?>