<?php
// Start Session manually if header is not included yet
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// 1. SESSION CHECK
if (!isset($_SESSION['last_order_id'])) {
    header("Location: sub_orders.php");
    exit;
}

$order_id = $_SESSION['last_order_id'];
$user_id = $_SESSION['user_id'];

// 2. FETCH ORDER DETAILS
try {
    $stmt = $db->prepare("
        SELECT o.*, p.name as product_name, p.icon as product_icon 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
} catch (Exception $e) {
    $order = false;
}

if (!$order) {
    header("Location: sub_orders.php");
    exit;
}

// 3. DATE LOGIC
$start_ts = strtotime($order['created_at']);
if ($start_ts <= 0) $start_ts = time();
$end_ts = strtotime($order['end_at']);
if ($end_ts <= 0) $end_ts = strtotime('+1 month', $start_ts);

$date_purchased = date('d M, Y', $start_ts);
$date_expires = date('d M, Y', $end_ts);

// 4. SETTINGS
$site_logo = $GLOBALS['settings']['site_logo'] ?? '';
$site_name = $GLOBALS['settings']['site_name'] ?? 'SubHub';
$admin_wa = $GLOBALS['settings']['whatsapp_number'] ?? '';
$wa_msg = urlencode("Salam! I purchased *{$order['product_name']}*.\nOrder ID: #{$order['code']}");
$wa_link = "https://wa.me/{$admin_wa}?text=$wa_msg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - <?= $site_name ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
    :root {
        --primary: #007AFF;
        --success: #34C759;
        --bg-body: #F2F2F7;
        --text-main: #1C1C1E;
        --text-sub: #8E8E93;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Outfit', sans-serif;
        color: var(--text-main);
        margin: 0; padding: 20px;
        min-height: 100vh;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }

    /* Success Animation */
    .success-icon-box {
        width: 80px; height: 80px; background: #fff; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 20px; box-shadow: 0 10px 30px rgba(52, 199, 89, 0.2);
        animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .checkmark { width: 40px; height: 40px; border-radius: 50%; stroke-width: 3; stroke: var(--success); stroke-miterlimit: 10; box-shadow: inset 0px 0px 0px var(--success); animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both; }
    .checkmark__circle { stroke-dasharray: 166; stroke-dashoffset: 166; stroke-width: 2; stroke-miterlimit: 10; stroke: var(--success); fill: none; animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards; }
    .checkmark__check { transform-origin: 50% 50%; stroke-dasharray: 48; stroke-dashoffset: 48; animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards; }

    /* Receipt Card */
    .receipt-card {
        background: #fff; width: 100%; max-width: 380px; border-radius: 24px;
        box-shadow: 0 20px 60px -10px rgba(0,0,0,0.1); position: relative;
        overflow: hidden; animation: slideUp 0.6s ease-out 0.2s backwards;
    }
    
    .receipt-header {
        text-align: center; padding: 30px; background: linear-gradient(to bottom, #fff, #fcfcfc);
        border-bottom: 2px dashed #eee;
    }
    .site-logo { height: 30px; object-fit: contain; margin-bottom: 10px; }
    .site-text { font-size: 16px; font-weight: 800; color: #333; letter-spacing: 1px; text-transform: uppercase; }

    .prod-display { margin-top: 20px; }
    .prod-icon { width: 60px; height: 60px; border-radius: 16px; object-fit: cover; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 10px; border: 1px solid #eee; }
    .prod-name { font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
    .status-pill { background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }

    .receipt-body { padding: 25px 30px; }
    .row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
    .lbl { color: var(--text-sub); font-weight: 600; }
    .val { color: var(--text-main); font-weight: 700; }
    .val-code { font-family: monospace; letter-spacing: 1px; background: #f3f4f6; padding: 2px 8px; border-radius: 6px; }

    .total-row { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
    .total-lbl { font-size: 15px; font-weight: 800; color: var(--text-main); }
    .total-val { font-size: 22px; font-weight: 900; color: var(--primary); }

    .receipt-footer { background: #f9fafb; padding: 15px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .wa-txt { font-size: 12px; color: var(--text-sub); font-weight: 600; }

    /* Buttons */
    .actions { display: flex; gap: 12px; margin-top: 30px; width: 100%; max-width: 380px; }
    .btn { flex: 1; padding: 14px; border-radius: 14px; border: none; text-decoration: none; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: 0.2s; }
    .btn-wa { background: #25D366; color: #fff; box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3); }
    .btn-wa:hover { transform: translateY(-2px); }
    .btn-dl { background: #1C1C1E; color: #fff; box-shadow: 0 5px 15px rgba(28, 28, 30, 0.3); }
    .btn-dl:hover { transform: translateY(-2px); }

    .back-link { margin-top: 25px; color: var(--text-sub); text-decoration: none; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 5px; }
    .back-link:hover { color: var(--primary); }

    @keyframes popIn { 0% { transform: scale(0); } 80% { transform: scale(1.1); } 100% { transform: scale(1); } }
    @keyframes stroke { 100% { stroke-dashoffset: 0; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(50px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="success-icon-box">
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/><path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
    </div>

    <h2 style="margin:0 0 20px 0; color:#1C1C1E; font-weight:800;">Order Successful!</h2>

    <div class="receipt-card" id="receipt">
        <div class="receipt-header">
            <?php if ($site_logo): ?>
                <img src="../assets/img/<?php echo sanitize($site_logo); ?>" class="site-logo" alt="Logo">
            <?php else: ?>
                <div class="site-text"><?php echo sanitize($site_name); ?></div>
            <?php endif; ?>
            
            <div class="prod-display">
                <img src="../assets/img/icons/<?php echo sanitize($order['product_icon']); ?>" class="prod-icon" onerror="this.style.display='none'">
                <div class="prod-name"><?php echo sanitize($order['product_name']); ?></div>
                <div class="status-pill">Active Subscription</div>
            </div>
        </div>

        <div class="receipt-body">
            <div class="row">
                <span class="lbl">Order ID</span>
                <span class="val val-code">#<?php echo $order['code']; ?></span>
            </div>
            <div class="row">
                <span class="lbl">Date</span>
                <span class="val"><?php echo $date_purchased; ?></span>
            </div>
            <div class="row">
                <span class="lbl">Valid Until</span>
                <span class="val" style="color:#007AFF"><?php echo $date_expires; ?></span>
            </div>

            <div class="total-row">
                <span class="total-lbl">Total Paid</span>
                <span class="total-val"><?php echo formatCurrency($order['total_price']); ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span class="wa-txt">Support: <?= sanitize($admin_wa); ?></span>
        </div>
    </div>

    <div class="actions">
        <a href="<?= $wa_link; ?>" target="_blank" class="btn btn-wa">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
            Share
        </a>
        <button onclick="downloadReceipt()" class="btn btn-dl">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Save
        </button>
    </div>

    <a href="index.php" class="back-link">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        Back to Dashboard
    </a>

    <script>
    function downloadReceipt() {
        const receipt = document.getElementById('receipt');
        const btn = document.querySelector('.btn-dl');
        const oldText = btn.innerHTML;
        btn.innerHTML = 'Saving...';
        
        html2canvas(receipt, { scale: 2, backgroundColor: null }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Receipt-<?= $order['code']; ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.innerHTML = oldText;
        });
    }
    </script>

</body>
</html>