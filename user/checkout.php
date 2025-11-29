<?php
// --- ERROR DEBUGGING (Blank page fix) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- LOGIC BLOCK ---
require_once __DIR__ . '/../includes/helpers.php'; 
requireLogin(); 

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/../includes/wallet.class.php';
    require_once __DIR__ . '/../includes/order.class.php';
    
    $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
    
    $wallet = new Wallet($db);
    $order = new Order($db, $wallet);
    
    $result = $order->createOrderFromVariation($_SESSION['user_id'], $variation_id);
    
    if ($result['success']) {
        $_SESSION['last_order_id'] = $result['order']['id'];
        $_SESSION['last_product_name'] = $result['product_name'];
        redirect('order-success.php');
    } else {
        if ($result['error'] == 'insufficient_funds') {
            redirect('add-funds.php?error=insufficient_funds');
        } else {
            $error = $result['error'];
        }
    }
}
// --- LOGIC END ---

include '_header.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Fetch Product
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
} catch (PDOException $e) { $product = false; }

if (!$product) redirect('index.php');

// Fetch Variations
try {
    $stmt_vars = $db->prepare("SELECT * FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY type ASC, duration_months ASC");
    $stmt_vars->execute([$product_id]);
    $variations = $stmt_vars->fetchAll();
} catch (PDOException $e) { $variations = []; }
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* --- 🎨 PREMIUM CHECKOUT THEME --- */
:root {
    --primary: #4f46e5;
    --secondary: #8b5cf6;
    --bg-body: #f8fafc;
    --text-main: #1e293b;
    --border: #e2e8f0;
}

body {
    background: var(--bg-body);
    font-family: 'Outfit', sans-serif;
    color: var(--text-main);
}

/* Container */
.checkout-container {
    max-width: 550px; margin: 40px auto; padding: 0 20px;
    animation: fadeIn 0.5s ease-out;
}

/* 1. PRODUCT HEADER CARD */
.prod-header {
    background: #fff; border-radius: 24px; padding: 30px;
    text-align: center; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    border: 1px solid #fff; margin-bottom: 25px; position: relative;
    overflow: hidden;
}
.prod-header::before {
    content:''; position: absolute; top:0; left:0; width:100%; height:6px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.prod-img-box {
    width: 80px; height: 80px; margin: 0 auto 15px auto;
    background: #f1f5f9; border-radius: 20px; display: flex;
    align-items: center; justify-content: center; box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
}
.prod-img { width: 50px; height: 50px; object-fit: contain; }

.prod-title { font-size: 1.6rem; font-weight: 800; margin: 0; color: #0f172a; }
.prod-desc { color: #64748b; font-size: 0.95rem; margin-top: 5px; }

/* 2. VARIATION SELECTOR */
.plan-label {
    font-size: 0.9rem; font-weight: 700; color: #64748b; text-transform: uppercase;
    letter-spacing: 1px; margin-bottom: 15px; display: block; padding-left: 5px;
}

.plan-card {
    position: relative; display: block; margin-bottom: 15px; cursor: pointer;
}

/* Hide Default Radio */
.plan-card input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }

/* The Box Design */
.plan-content {
    background: #fff; border: 2px solid var(--border); border-radius: 16px;
    padding: 20px; display: flex; justify-content: space-between; align-items: center;
    transition: all 0.2s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
}

/* Hover Effect */
.plan-card:hover .plan-content { border-color: #a5b4fc; transform: translateY(-2px); }

/* Checked State (Magic) */
.plan-card input:checked ~ .plan-content {
    border-color: var(--primary);
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
}

/* Left Side Info */
.plan-info h4 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b; }
.plan-info p { margin: 4px 0 0 0; font-size: 0.85rem; color: #64748b; }
.plan-card input:checked ~ .plan-content .plan-info h4 { color: var(--primary); }

/* Right Side Price */
.plan-price { text-align: right; }
.current-price { display: block; font-size: 1.3rem; font-weight: 800; color: #0f172a; }
.old-price { 
    display: block; font-size: 0.85rem; color: #94a3b8; text-decoration: line-through; 
}
.plan-card input:checked ~ .plan-content .current-price { color: var(--primary); }

/* Discount Badge */
.save-badge {
    position: absolute; top: -10px; right: 15px; background: #10b981; color: #fff;
    font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); z-index: 5;
}

/* 3. CHECKOUT BUTTON */
.pay-btn {
    width: 100%; padding: 18px; border: none; border-radius: 16px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4); transition: 0.3s;
    margin-top: 20px;
}
.pay-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 35px -5px rgba(79, 70, 229, 0.5); }
.pay-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }

.wallet-bal {
    text-align: center; margin-top: 15px; font-size: 0.9rem; color: #64748b; font-weight: 500;
}

/* ALERTS */
.alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: 600; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="checkout-container">

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="prod-header">
        <div class="prod-img-box">
            <img src="../assets/img/icons/<?php echo sanitize($product['icon']); ?>" alt="Icon" class="prod-img">
        </div>
        <h2 class="prod-title"><?php echo sanitize($product['name']); ?></h2>
        <div class="prod-desc">Secure & Instant Delivery</div>
    </div>

    <form method="POST" id="checkout-form" action="checkout.php?product_id=<?php echo $product_id; ?>">
        
        <span class="plan-label">Choose a Plan</span>

        <div class="variations-list">
            <?php if (empty($variations)): ?>
                <div style="text-align:center; padding:30px; color:#999; background:#fff; border-radius:12px;">
                    🚫 No plans available currently.
                </div>
            <?php else: ?>
                <?php foreach ($variations as $index => $var): 
                    // Calculate Savings
                    $savings = 0;
                    if (!empty($var['original_price']) && $var['original_price'] > $var['price']) {
                        $savings = round((($var['original_price'] - $var['price']) / $var['original_price']) * 100);
                    }
                ?>
                    <label class="plan-card">
                        <input type="radio" name="variation_id" value="<?php echo $var['id']; ?>" required <?php echo ($index === 0) ? 'checked' : ''; ?>>
                        
                        <?php if($savings > 0): ?>
                            <span class="save-badge">SAVE <?php echo $savings; ?>%</span>
                        <?php endif; ?>

                        <div class="plan-content">
                            <div class="plan-info">
                                <h4><?php echo sanitize($var['type']); ?></h4>
                                <p>Duration: <?php echo $var['duration_months']; ?> Month(s)</p>
                            </div>
                            <div class="plan-price">
                                <span class="current-price"><?php echo formatCurrency($var['price']); ?></span>
                                <?php if ($savings > 0): ?>
                                    <span class="old-price"><?php echo formatCurrency($var['original_price']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="pay-btn" <?php if (empty($variations)) echo 'disabled'; ?>>
            Confirm & Pay
        </button>

        <div class="wallet-bal">
            Wallet Balance: <span style="color:#4f46e5; font-weight:bold;"><?php echo formatCurrency($user_balance); ?></span>
        </div>

    </form>
</div>

<?php include '_footer.php'; ?>