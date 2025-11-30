<?php
include '_header.php'; 

// --- FETCH LIVE STATS (For Badges) ---
try {
    // SMM Pending Orders
    $smm_pending = $db->query("SELECT COUNT(*) FROM smm_orders WHERE status='pending'")->fetchColumn();
    
    // Active Subscriptions
    $active_subs = $db->query("SELECT COUNT(*) FROM orders WHERE status='completed' AND product_id IN (SELECT id FROM products WHERE is_digital=0)")->fetchColumn();
    
    // Total Digital Files
    $total_files = $db->query("SELECT COUNT(*) FROM products WHERE is_digital=1")->fetchColumn();
    
} catch (Exception $e) {
    $smm_pending = 0; $active_subs = 0; $total_files = 0;
}

// Greeting Logic
$hour = date('H');
if ($hour < 12) $greeting = "Good Morning";
elseif ($hour < 18) $greeting = "Good Afternoon";
else $greeting = "Good Evening";
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;900&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --bg-dark: #0f172a;
        --card-bg: #1e293b;
        --text-main: #f8fafc;
        --text-sub: #94a3b8;
        --glow: rgba(99, 102, 241, 0.5);
    }

    body {
        background-color: #f1f5f9; /* Light theme by default */
        font-family: 'Outfit', sans-serif;
        overflow-x: hidden;
    }

    /* HEADER */
    .hub-header {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        padding: 50px 40px 80px 40px; /* Extra bottom padding for overlap */
        border-radius: 0 0 40px 40px;
        color: white;
        margin-bottom: -60px;
        box-shadow: 0 20px 50px -20px rgba(49, 46, 129, 0.5);
        position: relative;
        overflow: hidden;
    }
    
    /* Background Animation Elements */
    .hub-header::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 50%);
        animation: rotate 20s linear infinite;
    }
    @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    .welcome-text h1 { font-size: 2.5rem; font-weight: 800; margin: 0; letter-spacing: -1px; }
    .welcome-text p { color: #a5b4fc; font-size: 1.1rem; margin-top: 5px; }
    
    .date-badge {
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 16px; border-radius: 50px;
        font-size: 0.9rem; font-weight: 600;
        backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1);
        display: inline-flex; align-items: center; gap: 8px;
    }

    /* GRID CONTAINER */
    .hub-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 40px 20px;
        position: relative;
        z-index: 10;
    }

    /* 3D CARDS */
    .hub-card {
        background: #fff;
        border-radius: 24px;
        padding: 35px;
        text-decoration: none;
        color: #1e293b;
        position: relative;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    .hub-card:nth-child(1) { animation-delay: 0.1s; }
    .hub-card:nth-child(2) { animation-delay: 0.2s; }
    .hub-card:nth-child(3) { animation-delay: 0.3s; }

    .hub-card:hover {
        transform: translateY(-15px) scale(1.02);
        box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.25);
        border-color: var(--primary);
    }

    /* ICONS */
    .icon-box {
        width: 90px; height: 90px;
        border-radius: 24px;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 20px;
        transition: 0.4s;
        position: relative;
        z-index: 2;
    }
    .hub-card:hover .icon-box { transform: scale(1.1) rotate(-5deg); }

    /* COLORS */
    .theme-smm .icon-box { background: #eef2ff; color: #4f46e5; }
    .theme-sub .icon-box { background: #fff7ed; color: #ea580c; }
    .theme-dl  .icon-box { background: #ecfdf5; color: #059669; }

    /* TEXT */
    .hub-card h2 { font-size: 1.6rem; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px; }
    .hub-card p { color: #64748b; font-size: 0.95rem; margin: 0; line-height: 1.5; }

    /* BADGES (Live Stats) */
    .live-badge {
        position: absolute; top: 20px; right: 20px;
        padding: 6px 12px; border-radius: 12px;
        font-size: 0.75rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.5px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .lb-red { background: #fee2e2; color: #ef4444; animation: pulse 2s infinite; }
    .lb-green { background: #dcfce7; color: #16a34a; }
    .lb-blue { background: #e0f2fe; color: #0284c7; }

    /* ACTION ARROW */
    .action-arrow {
        margin-top: 25px; width: 40px; height: 40px; border-radius: 50%;
        background: #f8fafc; color: #94a3b8;
        display: flex; align-items: center; justify-content: center;
        transition: 0.3s; opacity: 0.7;
    }
    .hub-card:hover .action-arrow {
        background: var(--primary); color: white; opacity: 1; transform: translateX(5px);
    }

    @keyframes fadeInUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

    @media(max-width: 768px) { .hub-grid { grid-template-columns: 1fr; } .hub-header { padding: 30px 20px 60px 20px; border-radius: 0 0 30px 30px; } }
</style>

<div class="hub-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div class="welcome-text">
            <span class="date-badge"><i class="far fa-clock"></i> <?= date('l, d M Y') ?></span>
            <h1 style="margin-top:15px;">👋 <?= $greeting ?>, Admin!</h1>
            <p>Select a module to manage your empire.</p>
        </div>
        </div>
</div>

<div class="hub-grid">

    <a href="smm_dashboard.php" class="hub-card theme-smm">
        <?php if($smm_pending > 0): ?>
            <div class="live-badge lb-red"><?= $smm_pending ?> Pending</div>
        <?php else: ?>
            <div class="live-badge lb-green">All Good</div>
        <?php endif; ?>
        
        <div class="icon-box"><i class="fas fa-rocket"></i></div>
        <h2>SMM Panel</h2>
        <p>Manage API orders, services, and social media growth tools.</p>
        <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
    </a>

    <a href="sub_dashboard.php" class="hub-card theme-sub">
        <div class="live-badge lb-blue"><?= number_format($active_subs) ?> Active</div>
        <div class="icon-box"><i class="fas fa-crown"></i></div>
        <h2>Subscriptions</h2>
        <p>Handle Netflix, Canva, VPNs and other recurring accounts.</p>
        <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
    </a>

    <a href="downloads_manager.php" class="hub-card theme-dl">
        <div class="live-badge lb-green"><?= $total_files ?> Files</div>
        <div class="icon-box"><i class="fas fa-cloud-arrow-down"></i></div>
        <h2>Downloads Manager</h2>
        <p>Upload assets, scripts, and digital files for instant delivery.</p>
        <div class="action-arrow"><i class="fas fa-arrow-right"></i></div>
    </a>

</div>

<?php include '_footer.php'; ?>