<?php
include '_header.php';

$user_id = $_SESSION['user_id'];

// --- STATS FETCHING ---
$stmt_spent = $db->prepare("SELECT SUM(total_price) as total_spent FROM orders WHERE user_id = ? AND (status = 'completed' OR status = 'expired')");
$stmt_spent->execute([$user_id]);
$total_spent = $stmt_spent->fetchColumn() ?? 0;

$stmt_orders = $db->prepare("SELECT COUNT(id) as total_orders FROM orders WHERE user_id = ?");
$stmt_orders->execute([$user_id]);
$total_orders = $stmt_orders->fetchColumn() ?? 0;

$stmt_active = $db->prepare("SELECT COUNT(id) as total_active FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt_active->execute([$user_id]);
$total_active = $stmt_active->fetchColumn() ?? 0;

// --- FILTER LOGIC ---
$category_filter = $_GET['category_id'] ?? 'all';
$sql_where = "";
if ($category_filter != 'all' && is_numeric($category_filter)) {
    $sql_where = " AND p.category_id = " . (int)$category_filter;
}

$stmt_cats = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
$categories = $stmt_cats->fetchAll();

$stmt_prods = $db->query("SELECT p.* FROM products p WHERE p.is_active = 1 $sql_where ORDER BY p.name ASC");
$products = $stmt_prods->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* --- 🎨 THEME SETTINGS --- */
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --bg-body: #f3f4f6;
    --card-bg: #ffffff;
    --text-main: #1f2937;
    --text-sub: #6b7280;
    --border: #e5e7eb;
    --radius: 12px;
}

body {
    background-color: var(--bg-body);
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
    font-size: 14px;
}

/* --- SKELETON LOADING STYLES (NEW) --- */
.skeleton {
    background: #e2e8f0;
    background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
#realContent { display: none; animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* --- EXISTING STYLES --- */
.overview-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    margin-bottom: 30px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.balance-section {
    padding: 20px;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid var(--border);
    background: #fff;
}

.bal-info p { margin: 0; font-size: 11px; font-weight: 700; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; }
.bal-info h2 { margin: 5px 0 0 0; font-size: 28px; font-weight: 800; color: var(--primary); }

.btn-add {
    background: var(--primary); color: #fff; text-decoration: none;
    padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px;
    display: flex; align-items: center; gap: 8px; transition: 0.2s;
}
.btn-add:hover { background: var(--primary-dark); }

.stats-row {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    background: #f9fafb;
}
.stat-item {
    padding: 15px; text-align: center;
    border-right: 1px solid var(--border);
}
.stat-item:last-child { border-right: none; }
.stat-title { display: block; font-size: 11px; color: var(--text-sub); font-weight: 700; text-transform: uppercase; }
.stat-value { font-size: 16px; font-weight: 700; color: var(--text-main); margin-top: 4px; display: block; }


/* --- FILTER BAR --- */
.filter-bar {
    display: flex; gap: 10px; margin-bottom: 25px;
}
.category-select {
    flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius);
    font-size: 14px; background: #fff; color: var(--text-main); outline: none; cursor: pointer;
}
.category-select:focus { border-color: var(--primary); }


/* --- PREMIUM PRODUCT CARDS --- */
.prod-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;
}

.prod-card {
    background: #fff; border-radius: 20px; overflow: hidden;
    border: 1px solid var(--border); transition: all 0.3s ease;
    display: flex; flex-direction: column; position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.prod-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.08);
    border-color: #dbeafe;
}

/* Top Image Area */
.prod-top {
    padding: 20px; background: linear-gradient(to bottom, #f8fafc, #fff);
    display: flex; justify-content: center; align-items: center;
    height: 100px; border-bottom: 1px dashed var(--border);
}
.prod-icon {
    width: 64px; height: 64px; object-fit: contain;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.08)); transition: 0.3s;
}
.prod-card:hover .prod-icon { transform: scale(1.1); }

/* Content */
.prod-content {
    padding: 20px; flex: 1; display: flex; flex-direction: column;
}
.prod-title {
    margin: 0 0 10px 0; font-size: 16px; font-weight: 700; 
    color: var(--text-main); line-height: 1.4;
}
.prod-desc {
    font-size: 13px; color: var(--text-sub); line-height: 1.5;
    margin-bottom: 15px; flex: 1;
}

/* Price & Button */
.prod-footer {
    margin-top: auto; display: flex; justify-content: space-between; align-items: center;
}

.price-tag {
    display: flex; flex-direction: column;
}
.price-lbl { font-size: 10px; text-transform: uppercase; color: #9ca3af; font-weight: 700; }
.price-val { font-size: 18px; font-weight: 800; color: var(--primary); }

.btn-buy {
    background: var(--text-main); color: #fff; padding: 10px 20px;
    border-radius: 50px; font-weight: 600; font-size: 13px; text-decoration: none;
    transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.btn-buy:hover { background: var(--primary); transform: scale(1.05); }

/* Request Tool */
.request-box {
    background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
    padding: 15px; margin-bottom: 25px; display: flex; gap: 10px; align-items: center;
}
.req-input { flex: 1; border: 1px solid var(--border); padding: 10px; border-radius: 8px; outline: none; }
.btn-req { background: #eff6ff; color: var(--primary); border: 1px solid #dbeafe; padding: 10px 15px; border-radius: 8px; font-weight: 700; cursor: pointer; }

/* Mobile */
@media (max-width: 600px) {
    .prod-grid { grid-template-columns: 1fr; }
    .request-box { flex-direction: column; }
    .btn-req { width: 100%; }
}
</style>

<div id="skeletonLoader">
    <div class="skeleton" style="height: 150px; border-radius: 12px; margin-bottom: 30px;"></div>
    
    <div class="skeleton" style="height: 50px; border-radius: 12px; margin-bottom: 25px;"></div>
    
    <div class="skeleton" style="height: 45px; border-radius: 12px; margin-bottom: 25px;"></div>

    <div class="prod-grid">
        <?php for($i=0; $i<6; $i++): ?>
        <div class="prod-card" style="border: 1px solid #eee; height: 300px; padding: 0;">
            <div class="skeleton" style="height: 100px; width: 100%;"></div> <div style="padding: 20px;">
                <div class="skeleton" style="height: 20px; width: 70%; margin-bottom: 10px;"></div> <div class="skeleton" style="height: 12px; width: 100%; margin-bottom: 5px;"></div> <div class="skeleton" style="height: 12px; width: 80%; margin-bottom: 20px;"></div>
                <div style="display:flex; justify-content:space-between; align-items:end;">
                    <div class="skeleton" style="height: 30px; width: 50px;"></div>
                    <div class="skeleton" style="height: 35px; width: 80px; border-radius: 50px;"></div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div id="realContent">

    <div class="overview-card">
        <div class="balance-section">
            <div class="bal-info">
                <p>Available Balance</p>
                <h2><?php echo formatCurrency($user_balance); ?></h2>
            </div>
            <a href="add-funds.php" class="btn-add">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
                Add Funds
            </a>
        </div>
        
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-title">Total Spent</span>
                <span class="stat-value"><?php echo formatCurrency($total_spent); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-title">Total Orders</span>
                <span class="stat-value"><?php echo $total_orders; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-title">Active Subs</span>
                <span class="stat-value" style="color:#16a34a"><?php echo $total_active; ?></span>
            </div>
        </div>
    </div>

    <div class="request-box">
        <input type="text" id="tool-req" class="req-input" placeholder="Request a tool/subscription...">
        <button id="btn-req" class="btn-req" data-wa="<?php echo sanitize($GLOBALS['settings']['whatsapp_number'] ?? ''); ?>">Submit Request</button>
    </div>

    <div class="filter-bar">
        <form action="" method="GET" id="cat-form" style="width:100%">
            <select name="category_id" class="category-select" onchange="document.getElementById('cat-form').submit();">
                <option value="all" <?php echo ($category_filter == 'all') ? 'selected' : ''; ?>>📂 All Products</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($category_filter == $cat['id']) ? 'selected' : ''; ?>>
                        📦 <?php echo sanitize($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="prod-grid">
        <?php if (empty($products)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:50px; color:#999;">
                No products found.
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="prod-card">
                    <div class="prod-top">
                        <img src="../assets/img/icons/<?php echo sanitize($product['icon']); ?>" class="prod-icon" alt="icon">
                    </div>
                    
                    <div class="prod-content">
                        <h3 class="prod-title"><?php echo sanitize($product['name']); ?></h3>
                        
                        <div class="prod-desc">
                            <?php echo (!empty($product['description'])) ? strip_tags(sanitize($product['description'])) : "Premium access available."; ?>
                        </div>
                        
                        <div class="prod-footer">
                            <div class="price-tag">
                                <span class="price-lbl">Starting At</span>
                                <span class="price-val">View</span>
                            </div>
                            
                            <a href="checkout.php?product_id=<?php echo $product['id']; ?>" class="btn-buy">
                                Get Now &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
// SKELETON LOGIC
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('skeletonLoader').style.display = 'none';
        document.getElementById('realContent').style.display = 'block';
    }, 800); // 0.8s delay for smooth feel
});

document.getElementById('btn-req').addEventListener('click', function() {
    const text = document.getElementById('tool-req').value;
    const phone = this.getAttribute('data-wa');
    if(text.trim() === '') return;
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent("Request: " + text)}`, '_blank');
});
</script>

<?php include '_footer.php'; ?>