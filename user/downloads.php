<?php
include '_header.php';

// --- 1. HANDLE PURCHASE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_product'])) {
    $p_id = (int)$_POST['product_id'];
    $price = (float)$_POST['price'];
    
    if ($user_balance < $price) {
        echo "<script>alert('Insufficient Balance! Please deposit funds.');</script>";
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$price, $user_id]);
            
            $code = 'DL-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $db->prepare("INSERT INTO orders (user_id, product_id, total_price, status, code, created_at) VALUES (?, ?, ?, 'completed', ?, NOW())");
            $stmt->execute([$user_id, $p_id, $price, $code]);
            
            $db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, ref_type, ref_id, note) VALUES (?, 'debit', ?, 'order', ?, 'Digital Download Purchase')")->execute([$user_id, $price, $db->lastInsertId()]);
            
            $db->commit();
            
            $purchased_item = $db->query("SELECT name, download_link FROM products WHERE id=$p_id")->fetch();
            echo "<script>
                window.onload = function() {
                    showReceipt('$code', '{$purchased_item['name']}', '$price', '{$purchased_item['download_link']}');
                };
            </script>";
            
        } catch (Exception $e) {
            $db->rollBack();
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    }
}

// --- 2. FETCH STATS ---
$stats_query = $db->prepare("SELECT COUNT(*) as count, SUM(o.total_price) as spent FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ? AND p.is_digital = 1 AND o.status = 'completed'");
$stats_query->execute([$user_id]);
$stats = $stats_query->fetch();
$dl_count = $stats['count'] ?? 0;
$dl_spent = $stats['spent'] ?? 0;

// --- 3. FETCH PRODUCTS ---
$products = $db->query("SELECT * FROM products WHERE is_digital = 1 AND is_active = 1")->fetchAll();
?>

<style>
    /* --- SKELETON LOADING ANIMATION --- */
    .skeleton {
        background: #e2e8f0;
        background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 8px;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* Skeleton Card Layout */
    .sk-card {
        background: #fff; border-radius: 20px; padding: 25px; border: 1px solid #f1f5f9;
        display: flex; flex-direction: column; gap: 15px; height: 350px;
    }
    .sk-img { width: 100%; height: 150px; border-radius: 12px; }
    .sk-title { width: 70%; height: 20px; }
    .sk-desc { width: 100%; height: 60px; }
    .sk-foot { width: 100%; height: 40px; margin-top: auto; }

    /* Hide Real Content Initially */
    #realContent { display: none; animation: fadeIn 0.5s ease-out; }

    /* --- STATS GRID --- */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .stat-card {
        background: #fff; border-radius: 20px; padding: 25px;
        border: 1px solid #f1f5f9; position: relative; overflow: hidden;
        transition: 0.3s; display: flex; flex-direction: column;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.1); border-color: #c7d2fe; }
    .st-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 15px; }
    .st-val { font-size: 2rem; font-weight: 800; color: #1e293b; line-height: 1; margin-bottom: 5px; }
    .st-label { color: #64748b; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .g-purple { background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #9333ea; }
    .g-green { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; }

    /* --- PRODUCT CARD --- */
    .store-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; padding: 20px 0; }
    .d-card {
        background: #fff; border-radius: 20px; overflow: hidden;
        border: 1px solid #e2e8f0; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative; display: flex; flex-direction: column;
    }
    .d-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(79, 70, 229, 0.15); border-color: #818cf8; }
    .d-img-box { height: 180px; overflow: hidden; position: relative; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }
    .d-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .d-card:hover .d-img { transform: scale(1.05); }
    .d-size-badge {
        position: absolute; top: 15px; right: 15px; background: rgba(15, 23, 42, 0.8); color: #fff;
        padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; backdrop-filter: blur(4px);
    }
    .d-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .d-title { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 10px; line-height: 1.4; }
    .d-desc-box {
        height: 60px; background: #f8fafc; border-radius: 10px;
        padding: 10px; margin-bottom: 15px; overflow: hidden; position: relative; border: 1px dashed #cbd5e1;
    }
    .d-desc-content { font-size: 0.85rem; color: #64748b; text-align: center; animation: scrollText 8s linear infinite alternate; }
    @keyframes scrollText { 0% { transform: translateY(0); } 20% { transform: translateY(0); } 80% { transform: translateY(calc(-100% + 40px)); } 100% { transform: translateY(calc(-100% + 40px)); } }
    .d-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #f1f5f9; }
    .d-price { font-size: 1.2rem; font-weight: 800; color: #4f46e5; }
    .d-btn {
        background: #10b981; color: #fff; border: none; padding: 10px 16px; border-radius: 10px;
        font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 6px;
    }
    .d-btn:hover { background: #059669; transform: translateY(-2px); }
    
    /* RECEIPT MODAL */
    .r-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 9999; display: none; align-items: center; justify-content: center; animation: fadeIn 0.3s ease; }
    .r-card { background: #fff; width: 380px; border-radius: 24px; padding: 0; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
    .r-header { background: #10b981; padding: 30px; text-align: center; color: #fff; }
    .r-icon { font-size: 3rem; margin-bottom: 10px; animation: popIn 0.5s ease; }
    .r-title { font-size: 1.5rem; font-weight: 800; margin: 0; }
    .r-body { padding: 30px; }
    .r-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.95rem; color: #475569; }
    .r-row b { color: #1e293b; }
    .r-total { border-top: 2px dashed #e2e8f0; margin-top: 15px; padding-top: 15px; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 800; color: #10b981; }
    .r-actions { padding: 0 30px 30px; display: grid; gap: 10px; }
    .r-btn-dl { background: #4f46e5; color: #fff; padding: 12px; border-radius: 12px; text-align: center; font-weight: 700; text-decoration: none; display: block; border: 1px solid #4338ca; }
    .r-btn-print { background: #f1f5f9; color: #334155; padding: 12px; border-radius: 12px; text-align: center; font-weight: 700; border: none; cursor: pointer; }
    @keyframes popIn { 0% { transform: scale(0); } 80% { transform: scale(1.2); } 100% { transform: scale(1); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="main-content-wrapper">
    
    <div style="text-align:center; margin-bottom:40px;">
        <h1 style="font-weight:800; font-size:2.2rem;">💎 Creative Assets Store</h1>
        <p style="color:#64748b;">Premium resources for Designers & Creators. Instant Delivery.</p>
        <a href="my_downloads.php" style="display:inline-block; margin-top:10px; color:#4f46e5; font-weight:700;">View My Downloads &rarr;</a>
    </div>

    <div id="skeletonLoader">
        <div class="stats-grid">
            <div class="stat-card"><div class="skeleton" style="width:50px; height:50px; margin-bottom:10px;"></div><div class="skeleton" style="width:60%; height:30px;"></div></div>
            <div class="stat-card"><div class="skeleton" style="width:50px; height:50px; margin-bottom:10px;"></div><div class="skeleton" style="width:60%; height:30px;"></div></div>
        </div>
        <div class="store-grid">
            <?php for($i=0; $i<6; $i++): ?>
            <div class="sk-card">
                <div class="skeleton sk-img"></div>
                <div class="skeleton sk-title"></div>
                <div class="skeleton sk-desc"></div>
                <div class="skeleton sk-foot"></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div id="realContent">
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="st-icon g-purple"><i class="fas fa-cloud-download-alt"></i></div>
                <div class="st-val"><?= number_format($dl_count) ?></div>
                <div class="st-label">Total Downloads</div>
            </div>
            <div class="stat-card">
                <div class="st-icon g-green"><i class="fas fa-coins"></i></div>
                <div class="st-val">Rs <?= number_format($dl_spent) ?></div>
                <div class="st-label">Value Purchased</div>
            </div>
        </div>

        <div class="store-grid">
            <?php foreach($products as $p): 
                $file_size = $p['file_size'] ?? '1.2 GB'; 
                $price = $p['price'] ?? 500; 
            ?>
            <div class="d-card">
                <div class="d-img-box">
                    <img src="../assets/img/<?= $p['icon'] ?>" alt="<?= $p['name'] ?>" class="d-img" onerror="this.src='../assets/img/default.png'">
                    <div class="d-size-badge">📦 <?= $file_size ?></div>
                </div>
                <div class="d-body">
                    <div class="d-title"><?= $p['name'] ?></div>
                    <div class="d-desc-box">
                        <div class="d-desc-content"><?= nl2br($p['description']) ?></div>
                    </div>
                    <div class="d-footer">
                        <div class="d-price">Rs <?= number_format($price) ?></div>
                        <form method="POST" onsubmit="return confirm('Confirm purchase for Rs <?= $price ?>?')">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="price" value="<?= $price ?>">
                            <button type="submit" name="buy_product" class="d-btn"><span>⚡ Pay & Get</span></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($products)): ?>
                <p style="text-align:center; width:100%; color:#666;">No digital products available right now.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<div id="receiptModal" class="r-overlay">
    <div class="r-card" id="receiptCard">
        <div class="r-header">
            <div class="r-icon">🎉</div>
            <h3 class="r-title">Payment Successful!</h3>
            <p style="margin:0; opacity:0.9;">Your file is ready.</p>
        </div>
        <div class="r-body">
            <div class="r-row"><span>Order ID</span> <b id="r_id">#DL-0000</b></div>
            <div class="r-row"><span>Product</span> <b id="r_name">Product Name</b></div>
            <div class="r-row"><span>Date</span> <b><?= date('d M Y') ?></b></div>
            <div class="r-total"><span>Total Paid</span> <span id="r_price">Rs 0</span></div>
        </div>
        <div class="r-actions">
            <a href="#" id="r_link" class="r-btn-dl" target="_blank">📥 Download Now</a>
            <button onclick="window.print()" class="r-btn-print">🖨️ Save Receipt</button>
            <button onclick="document.getElementById('receiptModal').style.display='none'" style="background:none; border:none; color:#94a3b8; margin-top:10px; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
// SKELETON LOADING LOGIC
window.addEventListener('load', function() {
    // Thoda delay taaki animation feel ho (0.8 seconds)
    setTimeout(function() {
        document.getElementById('skeletonLoader').style.display = 'none';
        document.getElementById('realContent').style.display = 'block';
    }, 800);
});

function showReceipt(id, name, price, link) {
    document.getElementById('r_id').innerText = '#' + id;
    document.getElementById('r_name').innerText = name;
    document.getElementById('r_price').innerText = 'Rs ' + price;
    document.getElementById('r_link').href = link;
    document.getElementById('receiptModal').style.display = 'flex';
}
</script>

<?php include '_footer.php'; ?>