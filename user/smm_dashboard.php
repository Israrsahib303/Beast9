<?php
// Naye SMM Header aur Footer files istemal karein
include '_smm_header.php'; 

$error = '';
$user_id = (int)$_SESSION['user_id']; // User ID yahan define karein

// --- SMM Dashboard Stats ke liye SQL Queries ---
try {
    // 1. Total SMM Spend
    $stmt_spend = $db->prepare("SELECT SUM(charge) FROM smm_orders WHERE user_id = ?");
    $stmt_spend->execute([$user_id]);
    $smm_total_spend = $stmt_spend->fetchColumn() ?? 0;

    // 2. Total SMM Orders
    $stmt_orders = $db->prepare("SELECT COUNT(id) FROM smm_orders WHERE user_id = ?");
    $stmt_orders->execute([$user_id]);
    $smm_total_orders = $stmt_orders->fetchColumn() ?? 0;

    // 3. In Progress Orders
    $stmt_progress = $db->prepare("SELECT COUNT(id) FROM smm_orders WHERE user_id = ? AND status = 'in_progress'");
    $stmt_progress->execute([$user_id]);
    $smm_in_progress = $stmt_progress->fetchColumn() ?? 0;
    
    // 4. Completed Orders
    $stmt_completed = $db->prepare("SELECT COUNT(id) FROM smm_orders WHERE user_id = ? AND status = 'completed'");
    $stmt_completed->execute([$user_id]);
    $smm_completed = $stmt_completed->fetchColumn() ?? 0;
    
    // 5. Graph ke liye (Last 7 Days Spend)
    $stmt_graph = $db->prepare("
        SELECT 
            DATE(created_at) as order_date, 
            SUM(charge) as total_spend
        FROM smm_orders
        WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 7 DAY
        GROUP BY DATE(created_at)
        ORDER BY order_date ASC
    ");
    $stmt_graph->execute([$user_id]);
    $graph_data = $stmt_graph->fetchAll();
    
    // Graph ke liye data tayyar karein
    $graph_labels = [];
    $graph_values = [];
    $dates = [];
    // Pehle 7 din ki dates set karein (0 spend ke sath)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[$date] = 0;
        $graph_labels[] = date('D, j M', strtotime($date));
    }
    // Database se milne wali values ko update karein
    foreach ($graph_data as $data) {
        if (isset($dates[$data['order_date']])) {
            $dates[$data['order_date']] = (float)$data['total_spend'];
        }
    }
    $graph_values = array_values($dates); // Sirf values nikal lein

} catch (PDOException $e) {
    $error = "Failed to load dashboard stats: " . $e->getMessage();
    $smm_total_spend = $smm_total_orders = $smm_in_progress = $smm_completed = 0;
    $graph_labels = [];
    $graph_values = [];
}
// --- SQL LOGIC KHATAM ---
?>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulseGlow {
        0% { box-shadow: 0 0 10px rgba(13, 110, 253, 0.2); }
        50% { box-shadow: 0 0 20px rgba(13, 110, 253, 0.5); }
        100% { box-shadow: 0 0 10px rgba(13, 110, 253, 0.2); }
    }

    .smm-header {
        background: var(--app-white);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        animation: fadeInUp 0.5s ease-out;
    }
    .smm-header .balance-info p {
        font-size: 0.9rem;
        color: var(--app-text-muted);
        margin: 0;
        font-weight: 600;
    }
    .smm-header .balance-info h2 {
        font-size: 2.2rem;
        color: var(--app-primary);
        margin: 0;
        font-weight: 700;
    }
    .smm-header .btn-add-funds-app {
        background: var(--app-grad-blue);
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 2rem;
        font-weight: 300;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulseGlow 2s infinite;
    }

    .smm-dashboard {
        margin-top: 2rem;
        animation: fadeInUp 0.5s ease-out 0.2s;
        opacity: 0;
        animation-fill-mode: forwards;
    }
    .smm-stat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr; /* 2 columns on mobile */
        gap: 15px;
        margin-bottom: 1rem;
    }
    @media (min-width: 768px) {
        .smm-stat-grid {
            grid-template-columns: 1fr 1fr 1fr 1fr; /* 4 columns on desktop */
        }
    }
    .smm-stat-card {
        background: var(--app-white);
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        transition: all 0.2s ease-in-out;
    }
    .smm-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .smm-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .smm-stat-icon svg { width: 20px; height: 20px; color: white; }
    .smm-stat-icon.icon-total-spent { background: var(--app-grad-blue); }
    .smm-stat-icon.icon-total-orders { background: var(--app-grad-orange); }
    .smm-stat-icon.icon-in-progress { background: linear-gradient(135deg, #198754 0%, #157347 100%); }
    .smm-stat-icon.icon-completed { background: linear-gradient(135deg, #6C757D 0%, #5A6268 100%); }
    
    .smm-stat-info p {
        font-size: 0.8rem;
        color: var(--app-text-muted);
        margin: 0;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .smm-stat-info h3 {
        font-size: 1.3rem;
        color: var(--app-dark);
        margin: 0;
        font-weight: 700;
    }
    .smm-graph-container {
        background: var(--app-white);
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }
</style>
<div class="smm-header">
    <div class="balance-info">
        <p>SMM Wallet Balance</p>
        <h2><?php echo formatCurrency($user_balance); ?></h2>
    </div>
    <a href="add-funds.php" class="btn-add-funds-app">+</a>
</div>

<?php if ($error): ?><div class="app-message app-error"><?php echo urldecode($error); ?></div><?php endif; ?>

<div class="smm-dashboard">
    <div class="smm-stat-grid">
        <div class="smm-stat-card">
            <div class="smm-stat-icon icon-total-spent">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div class="smm-stat-info">
                <p>Total Spent</p>
                <h3><?php echo formatCurrency($smm_total_spend); ?></h3>
            </div>
        </div>
        
        <div class="smm-stat-card">
            <div class="smm-stat-icon icon-total-orders">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            </div>
            <div class="smm-stat-info">
                <p>Total Orders</p>
                <h3><?php echo number_format($smm_total_orders); ?></h3>
            </div>
        </div>
        
        <div class="smm-stat-card">
            <div class="smm-stat-icon icon-in-progress">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
            </div>
            <div class="smm-stat-info">
                <p>In Progress</p>
                <h3><?php echo number_format($smm_in_progress); ?></h3>
            </div>
        </div>
        
        <div class="smm-stat-card">
            <div class="smm-stat-icon icon-completed">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="smm-stat-info">
                <p>Completed</p>
                <h3><?php echo number_format($smm_completed); ?></h3>
            </div>
        </div>
    </div>
    
    <div class="smm-graph-container">
        <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--app-dark);">Last 7 Days Spending</h3>
        <canvas id="smm-spending-chart"></canvas>
    </div>
</div>
<script>
    // Graph ka data PHP se JS mein lein
    window.smmGraphLabels = <?php echo json_encode($graph_labels); ?>;
    window.smmGraphValues = <?php echo json_encode($graph_values); ?>;
</script>

<?php include '_smm_footer.php'; // Naya SMM Footer istemal karein ?>