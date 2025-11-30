<?php
include '_header.php'; 
requireAdmin();

// --- 0. AUTO-DB FIX (Add Cost Columns) ---
try {
    $cols = $db->query("SHOW COLUMNS FROM smm_orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cost', $cols)) $db->exec("ALTER TABLE smm_orders ADD COLUMN cost DECIMAL(15,4) DEFAULT 0");
    
    $p_cols = $db->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cost_price', $p_cols)) $db->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(15,2) DEFAULT 0");
} catch (Exception $e) { /* Silent */ }

// --- 1. DATE FILTER LOGIC ---
$period = $_GET['period'] ?? 'all';
$date_condition_smm = "";
$date_condition_orders = "";

switch ($period) {
    case 'today':
        $date_condition_smm = "AND o.created_at >= CURDATE()";
        $date_condition_orders = "AND o.created_at >= CURDATE()";
        break;
    case 'yesterday':
        $date_condition_smm = "AND o.created_at >= CURDATE() - INTERVAL 1 DAY AND o.created_at < CURDATE()";
        $date_condition_orders = "AND o.created_at >= CURDATE() - INTERVAL 1 DAY AND o.created_at < CURDATE()";
        break;
    case 'month':
        $date_condition_smm = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $date_condition_orders = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    default: // All Time
        $date_condition_smm = "";
        $date_condition_orders = "";
}

// --- 2. CALCULATE SMM STATS ---
// Revenue = User se jo liya
// Cost = Provider ko jo diya (Agar 'cost' column 0 hai, toh live rate se estimate lagao)
$smm_query = "
    SELECT 
        COALESCE(SUM(o.charge),0) as revenue,
        COALESCE(SUM(CASE WHEN o.cost > 0 THEN o.cost ELSE (s.base_price * (o.quantity/1000)) END),0) as cost,
        COUNT(o.id) as count
    FROM smm_orders o
    LEFT JOIN smm_services s ON o.service_id = s.id
    WHERE o.status != 'canceled' AND o.status != 'refunded' $date_condition_smm
";
$smm = $db->query($smm_query)->fetch();
$smm_profit = $smm['revenue'] - $smm['cost'];
$smm_margin = ($smm['revenue'] > 0) ? round(($smm_profit / $smm['revenue']) * 100, 1) : 0;

// --- 3. CALCULATE DOWNLOADS (Digital Products) ---
$dl_query = "
    SELECT COALESCE(SUM(o.total_price),0) as revenue, COALESCE(SUM(p.cost_price),0) as cost, COUNT(o.id) as count
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.status = 'completed' AND p.is_digital = 1 $date_condition_orders
";
$dl = $db->query($dl_query)->fetch();
$dl_profit = $dl['revenue'] - $dl['cost'];
$dl_margin = ($dl['revenue'] > 0) ? round(($dl_profit / $dl['revenue']) * 100, 1) : 0;

// --- 4. CALCULATE SUBSCRIPTIONS ---
$sub_query = "
    SELECT COALESCE(SUM(o.total_price),0) as revenue, COALESCE(SUM(p.cost_price),0) as cost, COUNT(o.id) as count
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.status = 'completed' AND p.is_digital = 0 $date_condition_orders
";
$sub = $db->query($sub_query)->fetch();
$sub_profit = $sub['revenue'] - $sub['cost'];
$sub_margin = ($sub['revenue'] > 0) ? round(($sub_profit / $sub['revenue']) * 100, 1) : 0;

// --- 5. OVERALL TOTALS ---
$total_revenue = $smm['revenue'] + $dl['revenue'] + $sub['revenue'];
$total_cost = $smm['cost'] + $dl['cost'] + $sub['cost'];
$total_profit = $total_revenue - $total_cost;

// --- 6. GRAPH DATA (Last 7 Days Trend) ---
$dates = [];
$profits = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('d M', strtotime($date));
    
    // Simple Daily Query (Optimized)
    $day_smm = $db->query("SELECT SUM(charge) - SUM(cost) FROM smm_orders WHERE DATE(created_at) = '$date' AND status NOT IN ('canceled','refunded')")->fetchColumn();
    $day_store = $db->query("SELECT SUM(total_price) - SUM((SELECT cost_price FROM products WHERE id=orders.product_id)) FROM orders WHERE DATE(created_at) = '$date' AND status='completed'")->fetchColumn();
    
    $profits[] = max(0, (float)$day_smm + (float)$day_store);
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --secondary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --dark: #0f172a;
        --light: #64748b;
        --bg: #f8fafc;
        --card: #ffffff;
    }
    body { background: var(--bg); font-family: 'Outfit', sans-serif; color: var(--dark); }

    /* HEADER */
    .report-header {
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;
        background: #fff; padding: 20px 25px; border-radius: 16px; border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); flex-wrap: wrap; gap: 15px;
    }
    .rh-title h1 { margin: 0; font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .rh-title p { margin: 5px 0 0; color: var(--light); font-size: 0.95rem; }

    /* FILTER BUTTONS */
    .date-filter { background: #f1f5f9; padding: 5px; border-radius: 10px; display: inline-flex; }
    .df-btn {
        padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: var(--light);
        text-decoration: none; transition: 0.2s;
    }
    .df-btn:hover { color: var(--dark); background: #e2e8f0; }
    .df-btn.active { background: #fff; color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

    /* MAIN HERO CARD (TOTAL PROFIT) */
    .hero-card {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: white; padding: 35px; border-radius: 20px; text-align: center;
        margin-bottom: 40px; position: relative; overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(79, 70, 229, 0.4);
    }
    .hero-card::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%); }
    
    .hero-label { font-size: 1rem; font-weight: 600; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; }
    .hero-value { font-size: 3.5rem; font-weight: 800; margin: 10px 0; letter-spacing: -1px; }
    .hero-sub { font-size: 0.95rem; background: rgba(255,255,255,0.2); padding: 6px 15px; border-radius: 30px; display: inline-block; backdrop-filter: blur(5px); }

    /* BREAKDOWN GRID */
    .split-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 40px; }
    
    .profit-card {
        background: var(--card); border-radius: 16px; border: 1px solid #e2e8f0; padding: 25px;
        transition: 0.3s; position: relative; overflow: hidden;
    }
    .profit-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 15px 30px -5px rgba(0,0,0,0.08); }
    .pc-head { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
    .pc-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
    
    .pc-title { font-weight: 700; font-size: 1.1rem; color: var(--dark); }
    .pc-profit { font-size: 1.8rem; font-weight: 800; color: var(--success); margin-bottom: 5px; }
    
    .pc-row { display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--light); margin-bottom: 8px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 8px; }
    .pc-row span:last-child { font-weight: 600; color: var(--dark); }
    
    .pc-badge { position: absolute; top: 20px; right: 20px; background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; }

    /* CHART SECTION */
    .chart-box { background: var(--card); padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; height: 350px; }

    /* ICONS COLORS */
    .bg-smm { background: #eef2ff; color: #4f46e5; }
    .bg-sub { background: #fff7ed; color: #ea580c; }
    .bg-dl { background: #f0f9ff; color: #0284c7; }

    @media(max-width: 768px) { .report-header { flex-direction: column; align-items: stretch; } .hero-value { font-size: 2.5rem; } }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <div class="report-header">
        <div class="rh-title">
            <h1>💰 Business Intelligence</h1>
            <p>Track your earnings, expenses, and net profit in real-time.</p>
        </div>
        <div class="date-filter">
            <a href="?period=today" class="df-btn <?= $period=='today'?'active':'' ?>">Today</a>
            <a href="?period=yesterday" class="df-btn <?= $period=='yesterday'?'active':'' ?>">Yesterday</a>
            <a href="?period=month" class="df-btn <?= $period=='month'?'active':'' ?>">This Month</a>
            <a href="?period=all" class="df-btn <?= $period=='all'?'active':'' ?>">All Time</a>
        </div>
    </div>

    <div class="hero-card">
        <div class="hero-label">Total Net Profit (Bachat)</div>
        <div class="hero-value"><?= formatCurrency($total_profit) ?></div>
        <div class="hero-sub">
            Revenue: <?= formatCurrency($total_revenue) ?> &nbsp;|&nbsp; 
            Cost: <?= formatCurrency($total_cost) ?>
        </div>
    </div>

    <div class="split-grid">
        
        <div class="profit-card">
            <div class="pc-badge"><?= $smm_margin ?>% Margin</div>
            <div class="pc-head">
                <div class="pc-icon bg-smm"><i class="fa-solid fa-thumbs-up"></i></div>
                <div class="pc-title">SMM Services</div>
            </div>
            <div style="text-align:center; margin-bottom:20px;">
                <div style="font-size:0.85rem; color:#64748b; font-weight:600;">NET PROFIT</div>
                <div class="pc-profit"><?= formatCurrency($smm_profit) ?></div>
            </div>
            <div class="pc-row"><span>Total Sales</span> <span><?= formatCurrency($smm['revenue']) ?></span></div>
            <div class="pc-row"><span>Provider Cost</span> <span style="color:#ef4444;"><?= formatCurrency($smm['cost']) ?></span></div>
            <div class="pc-row" style="border:none;"><span>Total Orders</span> <span><?= number_format($smm['count']) ?></span></div>
        </div>

        <div class="profit-card">
            <div class="pc-badge"><?= $sub_margin ?>% Margin</div>
            <div class="pc-head">
                <div class="pc-icon bg-sub"><i class="fa-solid fa-crown"></i></div>
                <div class="pc-title">Subscriptions</div>
            </div>
            <div style="text-align:center; margin-bottom:20px;">
                <div style="font-size:0.85rem; color:#64748b; font-weight:600;">NET PROFIT</div>
                <div class="pc-profit"><?= formatCurrency($sub_profit) ?></div>
            </div>
            <div class="pc-row"><span>Total Sales</span> <span><?= formatCurrency($sub['revenue']) ?></span></div>
            <div class="pc-row"><span>Product Cost</span> <span style="color:#ef4444;"><?= formatCurrency($sub['cost']) ?></span></div>
            <div class="pc-row" style="border:none;"><span>Active Subs</span> <span><?= number_format($sub['count']) ?></span></div>
        </div>

        <div class="profit-card">
            <div class="pc-badge"><?= $dl_margin ?>% Margin</div>
            <div class="pc-head">
                <div class="pc-icon bg-dl"><i class="fa-solid fa-cloud-arrow-down"></i></div>
                <div class="pc-title">Digital Downloads</div>
            </div>
            <div style="text-align:center; margin-bottom:20px;">
                <div style="font-size:0.85rem; color:#64748b; font-weight:600;">NET PROFIT</div>
                <div class="pc-profit"><?= formatCurrency($dl_profit) ?></div>
            </div>
            <div class="pc-row"><span>Total Sales</span> <span><?= formatCurrency($dl['revenue']) ?></span></div>
            <div class="pc-row"><span>Asset Cost</span> <span style="color:#ef4444;"><?= formatCurrency($dl['cost']) ?></span></div>
            <div class="pc-row" style="border:none;"><span>Files Sold</span> <span><?= number_format($dl['count']) ?></span></div>
        </div>

    </div>

    <div class="chart-box">
        <h3 style="margin:0 0 15px 0; color:#1e293b;"><i class="fa-solid fa-chart-line"></i> Last 7 Days Profit Trend</h3>
        <canvas id="profitTrend"></canvas>
    </div>

</div>

<script>
const ctx = document.getElementById('profitTrend').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
            label: 'Daily Net Profit (<?= $GLOBALS['settings']['currency_symbol'] ?? 'PKR' ?>)',
            data: <?= json_encode($profits) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include '_footer.php'; ?>