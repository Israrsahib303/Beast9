<?php
include '_header.php';

// --- FETCH STATS ---
$stmt = $db->prepare("SELECT COUNT(*) FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ? AND o.status = 'completed' AND p.is_digital = 0");
$stmt->execute([$user_id]);
$active_subs = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM smm_orders WHERE user_id = ? AND status IN ('pending','processing','in_progress')");
$stmt->execute([$user_id]);
$smm_active = $stmt->fetchColumn();

$total_spent = $db->prepare("SELECT SUM(amount) FROM wallet_ledger WHERE user_id = ? AND type='debit'");
$total_spent->execute([$user_id]);
$total_spent_amount = $total_spent->fetchColumn() ?? 0;

// Greeting
$hour = date('H');
$greeting = ($hour < 12) ? "Good Morning" : (($hour < 18) ? "Good Afternoon" : "Good Evening");

// User Name
$stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_real_name = $stmt->fetchColumn();
$display_name = !empty($user_real_name) ? htmlspecialchars($user_real_name) : 'User';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --hover-shadow: 0 20px 40px -5px rgba(99, 102, 241, 0.3);
    }

    body { font-family: 'Outfit', sans-serif; background: #f1f5f9; overflow-x: hidden; }
    
    /* --- SKELETON STYLES --- */
    .skeleton {
        background: #e2e8f0;
        background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 12px;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    
    #realContent { display: none; animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* --- HERO SECTION --- */
    .hub-hero {
        text-align: center; padding: 60px 20px;
        background: var(--primary-gradient);
        border-radius: 30px; color: #fff; margin-bottom: 40px;
        position: relative; overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(79, 70, 229, 0.5);
        animation: fadeInDown 0.8s ease;
    }
    
    .hub-hero::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
        animation: rotate 20s linear infinite;
    }
    @keyframes rotate { from {transform: rotate(0deg);} to {transform: rotate(360deg);} }
    
    .hero-content { position: relative; z-index: 2; }
    
    .hero-badge {
        background: rgba(255,255,255,0.25); padding: 6px 18px; border-radius: 50px;
        font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
        backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3);
        display: inline-block; margin-bottom: 15px;
    }
    
    .hero-title { font-size: 3rem; font-weight: 800; margin: 0; text-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .hero-sub { font-size: 1.2rem; opacity: 0.95; margin-top: 10px; font-weight: 400; }
    
    .balance-box {
        margin-top: 25px; display: inline-block;
        background: #fff; color: #4f46e5; padding: 12px 30px;
        border-radius: 50px; font-weight: 800; font-size: 1.2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        transition: 0.3s;
    }
    .balance-box:hover { transform: scale(1.05); box-shadow: 0 15px 40px rgba(0,0,0,0.2); }

    /* --- GRID (CENTERED) --- */
    .hub-grid {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px; 
        padding: 0 10px; 
        animation: fadeInUp 1s ease;
        justify-content: center; /* Center items */
    }

/* --- CARD DESIGN (FIXED WITH BORDER) --- */
    .hub-card {
        border-radius: 24px; position: relative; overflow: hidden; text-decoration: none;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex; flex-direction: column; justify-content: space-between;
        min-height: 280px;
        box-shadow: 0 30px 20px rgba(0,0,0,0.05);
        background-color: #fff;
        padding: 25px;
        max-width: 400px; /* Prevent too wide on large screens */
        margin: 0 auto; /* Center in grid cell */
        width: 100%;
        
        /* --- NEW BORDER ADDED --- */
        border: 4px solid #28282b; 
    }

    .hub-card:hover { 
        transform: translateY(-10px); 
        box-shadow: var(--hover-shadow); 
        
        /* --- HOVER BORDER COLOR (Primary Color) --- */
        border-color: #6366f1; 
    }

    /* Background Images (Full Fit, Higher Opacity) */
    .hub-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background-size: 100% 100%; /* Force Fit Full Card */
        background-position: center;
        background-repeat: no-repeat;
        transition: 0.5s; z-index: 0; 
        opacity: 0.; /* Increased Opacity */
        filter: grayscale(0%); /* Show Colors */
    }
    .hc-smm::before { background-image: url('../assets/img/icons/smm.png'); }
    .hc-store::before { background-image: url('../assets/img/icons/sub.png'); }
    .hc-dl::before { background-image: url('../assets/img/icons/down.png'); }
    .hc-ai::before { background-image: url('../assets/img/icons/ai.png'); }

    .hub-card:hover::before { transform: scale(1.05); opacity: 0.4; }

    /* Content Wrapper (Left Aligned) */
    .card-content {
        position: relative; z-index: 2; 
        display: flex; flex-direction: column; align-items: flex-start; 
        height: 100%; width: 100%;
    }

    /* Icon Box */
    .hc-icon-box {
        width: 60px; height: 60px; border-radius: 16px; 
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; margin-bottom: 20px; transition: 0.4s;
        background: rgba(255,255,255,0.9); backdrop-filter: blur(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: #64748b;
    }
    /* Icon Colors */
    .hc-smm .hc-icon-box { color: #3b82f6; }
    .hc-store .hc-icon-box { color: #f97316; }
    .hc-dl .hc-icon-box { color: #10b981; }
    .hc-ai .hc-icon-box { color: #a855f7; }

    .hub-card:hover .hc-icon-box { background: #fff; transform: rotate(-10deg) scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }

    /* Text Styling (White Background for Clarity) */
    .text-wrapper {
        background: rgba(255, 255, 255, 0.85); /* Stronger background */
        backdrop-filter: blur(8px);
        padding: 12px 15px; border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.6);
        margin-bottom: auto;
        width: 100%;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    .hc-title { font-size: 1.4rem; font-weight: 800; color: #1e293b; margin: 0 0 5px 0; }
    
    /* Scroller */
    .scroller { height: 24px; overflow: hidden; position: relative; margin-top: 5px; }
    .scroller-inner { display: flex; flex-direction: column; animation: scrollUp 6s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
    .scroller span { 
        height: 24px; display: flex; align-items: center; gap: 8px;
        font-size: 0.9rem; color: #475569; font-weight: 600;
    }
    .scroller i { color: #10b981; font-size: 0.8rem; }

    @keyframes scrollUp {
        0%, 20% { transform: translateY(0); }
        25%, 45% { transform: translateY(-24px); }
        50%, 70% { transform: translateY(-48px); }
        75%, 95% { transform: translateY(-72px); }
        100% { transform: translateY(0); }
    }

    /* Button */
    .hc-btn {
        background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 12px;
        font-size: 0.95rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center;
        transition: 0.3s; width: 100%; margin-top: 20px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .hub-card:hover .hc-btn { background: #4f46e5; padding-right: 15px; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4); }
    
    /* Animations */
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-30px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }

    @media(max-width: 768px) {
        .hero-title { font-size: 2.2rem; }
        .hub-grid { padding: 0 5px; }
    }
</style>

<div class="main-content-wrapper">

    <div id="skeletonLoader">
        <div class="skeleton" style="height: 250px; border-radius: 30px; margin-bottom: 40px;"></div>
        
        <div class="hub-grid">
            <?php for($i=0; $i<4; $i++): ?>
            <div class="skeleton" style="height: 280px; border-radius: 24px; width: 100%; max-width: 400px; margin: 0 auto;"></div>
            <?php endfor; ?>
        </div>
    </div>

    <div id="realContent">

        <div class="hub-hero">
            <div class="hero-content">
                <span class="hero-badge">👋 <?= $greeting ?></span>
                <h1 class="hero-title"><?= $display_name ?></h1>
                <p class="hero-sub">Let's grow your digital presence today.</p>
                
                <a href="add-funds.php" class="balance-box">
                    💰 <?= formatCurrency($user_balance) ?> <span style="font-size:0.8rem; opacity:0.7;">(Add Funds)</span>
                </a>
            </div>
        </div>

        <div class="hub-grid">
            
            <a href="smm_order.php" class="hub-card hc-smm">
                <div class="card-content">
                    <div class="hc-icon-box"><i class="fa-solid fa-rocket animate__animated animate__pulse animate__infinite"></i></div>
                    
                    <div class="text-wrapper">
                        <div class="hc-title">Social Media Panel</div>
                        <div class="scroller">
                            <div class="scroller-inner">
                                <span><i class="fa-solid fa-check"></i> Buy Instagram Followers</span>
                                <span><i class="fa-solid fa-check"></i> Buy TikTok Likes</span>
                                <span><i class="fa-solid fa-check"></i> Buy YouTube Views</span>
                                <span><i class="fa-solid fa-check"></i> Instant Delivery ⚡</span>
                            </div>
                        </div>
                    </div>

                    <div class="hc-btn">Start Boosting <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="sub_dashboard.php" class="hub-card hc-store">
                <div class="card-content">
                    <div class="hc-icon-box"><i class="fa-solid fa-crown animate__animated animate__tada animate__infinite" style="animation-duration: 2s;"></i></div>
                    
                    <div class="text-wrapper">
                        <div class="hc-title">Premium Accounts</div>
                        <div class="scroller">
                            <div class="scroller-inner">
                                <span><i class="fa-solid fa-check"></i> Netflix 4K Private</span>
                                <span><i class="fa-solid fa-check"></i> ChatGPT Plus</span>
                                <span><i class="fa-solid fa-check"></i> Canva Pro Admin</span>
                                <span><i class="fa-solid fa-check"></i> Full Warranty 🛡️</span>
                            </div>
                        </div>
                    </div>

                    <div class="hc-btn">Buy Account <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="downloads.php" class="hub-card hc-dl">
                <div class="card-content">
                    <div class="hc-icon-box"><i class="fa-solid fa-cloud-arrow-down animate__animated animate__bounce animate__infinite" style="animation-duration: 3s;"></i></div>
                    
                    <div class="text-wrapper">
                        <div class="hc-title">Digital Assets</div>
                        <div class="scroller">
                            <div class="scroller-inner">
                                <span><i class="fa-solid fa-check"></i> Video Editing Packs</span>
                                <span><i class="fa-solid fa-check"></i> Premium Softwares</span>
                                <span><i class="fa-solid fa-check"></i> Graphic Templates</span>
                                <span><i class="fa-solid fa-check"></i> Instant Link 📥</span>
                            </div>
                        </div>
                    </div>

                    <div class="hc-btn">Download Now <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>

            <a href="ai_tools.php" class="hub-card hc-ai">
                <div class="card-content">
                    <div class="hc-icon-box"><i class="fa-solid fa-wand-magic-sparkles animate__animated animate__pulse animate__infinite"></i></div>
                    
                    <div class="text-wrapper">
                        <div class="hc-title">AI Growth Tools</div>
                        <div class="scroller">
                            <div class="scroller-inner">
                                <span><i class="fa-solid fa-check"></i> Viral Hook Gen</span>
                                <span><i class="fa-solid fa-check"></i> Caption Writer</span>
                                <span><i class="fa-solid fa-check"></i> Profile Auditor</span>
                                <span><i class="fa-solid fa-check"></i> 100% Free Tools 🤖</span>
                            </div>
                        </div>
                    </div>

                    <div class="hc-btn">Use Tools <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>

        </div>

    </div>

</div>

<script>
// Skeleton Loader Logic
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('skeletonLoader').style.display = 'none';
        document.getElementById('realContent').style.display = 'block';
    }, 800);
});
</script>

<?php include '_footer.php'; ?>