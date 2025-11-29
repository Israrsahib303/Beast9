<?php
// --- SETUP & AUTH ---
require_once __DIR__ . '/../includes/helpers.php'; 
requireLogin(); 
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$error = '';
$success = '';

// --- HELPER FUNCTION: Validate Promo Code (Backend) ---
function validatePromoCode($db, $code, $amount, $user_id) {
    if (empty($code)) return ['status' => false];

    $stmt = $db->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) return ['status' => false, 'msg' => 'Invalid Promo Code'];
    if ($promo['max_uses'] > 0 && $promo['current_uses'] >= $promo['max_uses']) return ['status' => false, 'msg' => 'Promo Limit Reached'];
    if ($amount < $promo['min_deposit']) return ['status' => false, 'msg' => 'Min deposit ' . formatCurrency($promo['min_deposit']) . ' required'];

    // Calculate Bonus
    $bonus = ($amount * $promo['deposit_bonus']) / 100;
    
    return [
        'status' => true,
        'bonus' => $bonus,
        'id' => $promo['id'],
        'code' => $promo['code'],
        'percent' => $promo['deposit_bonus']
    ];
}

// --- AJAX HANDLER: Check Promo (New Integrated Logic) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'check_promo') {
    // Output clean JSON only
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $code = strtoupper(sanitize($_POST['code'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (empty($code)) { echo json_encode(['valid'=>false, 'error'=>'Enter promo code']); exit; }
    if ($amount <= 0) { echo json_encode(['valid'=>false, 'error'=>'Enter valid amount']); exit; }

    $result = validatePromoCode($db, $code, $amount, $user_id);

    if ($result['status']) {
        echo json_encode([
            'valid' => true,
            'bonus_amount' => formatCurrency($result['bonus']),
            'total_amount' => formatCurrency($amount + $result['bonus']),
            'percent' => $result['percent'] ?? 0
        ]);
    } else {
        echo json_encode(['valid' => false, 'error' => $result['msg']]);
    }
    exit; // Stop script here for AJAX
}

// --- SUCCESS MESSAGES ---
if (isset($_GET['success']) && $_GET['success'] == 'claimed') {
    $msg = 'Payment verified! ' . formatCurrency($_GET['amount']) . ' added.';
    if(isset($_GET['bonus']) && $_GET['bonus'] > 0) {
        $msg .= ' (Inc. ' . formatCurrency($_GET['bonus']) . ' Bonus)';
    }
    $success = $msg;
}
if (isset($_GET['success']) && $_GET['success'] == 'manual') {
    $success = 'Deposit submitted for approval! Admin will verify soon.';
}

// --- LOGIC 1: NayaPay Auto-Claim (SECURED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'claim_payment') {
    
    // CSRF CHECK
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security Token Mismatch. Please refresh the page.';
    } else {
        $txn_id = sanitize($_POST['nayapay_txn_id']);
        $amount = (float)($_POST['nayapay_amount']);
        $promo_code_input = strtoupper(sanitize($_POST['promo_code'] ?? ''));
        $user_id = $_SESSION['user_id'];

        if (empty($txn_id) || $amount <= 0) {
            $error = 'Please enter a valid TXN ID and Amount.';
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM email_payments WHERE txn_id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$txn_id]);
                $unclaimed_payment = $stmt->fetch();

                if (!$unclaimed_payment) {
                    $error = 'Transaction ID not found or already claimed. Wait 5 mins if just paid.';
                } 
                elseif (abs((float)$unclaimed_payment['amount'] - $amount) > 0.01) {
                    $error = 'Amount mismatch! System found: ' . formatCurrency($unclaimed_payment['amount']);
                } else {
                    // Check Limits
                    $stmt_method = $db->prepare("SELECT min_amount, max_amount FROM payment_methods WHERE name LIKE ?");
                    $stmt_method->execute(['%NayaPay%']);
                    $method_limits = $stmt_method->fetch();
                    
                    if ($method_limits && $method_limits['min_amount'] > 0 && $amount < $method_limits['min_amount']) {
                         $error = 'Minimum deposit is ' . formatCurrency($method_limits['min_amount']);
                    } elseif ($method_limits && $method_limits['max_amount'] > 0 && $amount > $method_limits['max_amount']) {
                         $error = 'Maximum deposit is ' . formatCurrency($method_limits['max_amount']);
                    } else {
                        // VALIDATE PROMO CODE
                        $bonus_amount = 0;
                        $promo_data = [];
                        if (!empty($promo_code_input)) {
                            $promo_check = validatePromoCode($db, $promo_code_input, $amount, $user_id);
                            if ($promo_check['status']) {
                                $bonus_amount = $promo_check['bonus'];
                                $promo_data = $promo_check;
                            }
                        }

                        $db->beginTransaction();
                        
                        // 1. Add Original Amount
                        $wallet->addCredit($user_id, $amount, 'payment', $unclaimed_payment['id'], 'NayaPay Claim: ' . $txn_id);
                        
                        // 2. Add Bonus (if valid)
                        if ($bonus_amount > 0) {
                            $wallet->addCredit($user_id, $bonus_amount, 'bonus', $promo_data['id'], 'Promo Bonus: ' . $promo_data['code']);
                            // Update Promo Usage
                            $db->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE id = ?")->execute([$promo_data['id']]);
                        }

                        // 3. Mark Claimed
                        $stmt_claim = $db->prepare("UPDATE email_payments SET status = 'claimed', claimed_by_user_id = ?, claimed_at = NOW() WHERE id = ?");
                        $stmt_claim->execute([$user_id, $unclaimed_payment['id']]);
                        
                        // 4. Log Payment
                        $stmt_log = $db->prepare("INSERT INTO payments (user_id, method, amount, txn_id, status, gateway_ref, created_at, approved_at) VALUES (?, 'NayaPay-Auto', ?, ?, 'approved', ?, NOW(), NOW())");
                        $stmt_log->execute([$user_id, $amount, $txn_id, 'email_payment_id:' . $unclaimed_payment['id']]);
                        
                        $db->commit();
                        
                        redirect("add-funds.php?success=claimed&amount=" . $amount . "&bonus=" . $bonus_amount);
                    }
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// --- LOGIC 2: Manual Deposit (SECURED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'manual_deposit') {
    
    // CSRF CHECK
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security Token Mismatch. Please refresh the page.';
    } else {
        $amount = (float)($_POST['manual_amount'] ?? 0);
        $method = sanitize($_POST['manual_method'] ?? '');
        $txn_id = sanitize($_POST['txn_id'] ?? '');
        $promo_code_input = strtoupper(sanitize($_POST['promo_code'] ?? ''));
        $screenshot = $_FILES['screenshot'];

        $stmt_method = $db->prepare("SELECT min_amount, max_amount FROM payment_methods WHERE name = ?");
        $stmt_method->execute([$method]);
        $method_limits = $stmt_method->fetch();

        if ($amount <= 0 || empty($method) || empty($txn_id) || $screenshot['error'] == 4) {
            $error = 'Please fill all fields and upload screenshot.';
        } elseif ($method_limits && $method_limits['min_amount'] > 0 && $amount < $method_limits['min_amount']) {
             $error = 'Minimum deposit for '.$method.' is ' . formatCurrency($method_limits['min_amount']);
        } elseif ($method_limits && $method_limits['max_amount'] > 0 && $amount > $method_limits['max_amount']) {
             $error = 'Max deposit is ' . formatCurrency($method_limits['max_amount']);
        } elseif ($screenshot['size'] > 2 * 1024 * 1024) {
            $error = 'Screenshot too large (Max 2MB).';
        } else {
            // Promo Check for Manual (Visual only for Admin)
            if (!empty($promo_code_input)) {
                $promo_check = validatePromoCode($db, $promo_code_input, $amount, $_SESSION['user_id']);
                if ($promo_check['status']) {
                    $txn_id .= " | Promo: " . $promo_code_input . " (Bonus: " . number_format($promo_check['bonus'], 2) . ")";
                } else {
                    $txn_id .= " | Invalid Promo: " . $promo_code_input;
                }
            }

            $upload_dir = __DIR__ . '/../assets/uploads/';
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $f_type = mime_content_type($screenshot['tmp_name']);
            
            if (in_array($f_type, $allowed)) {
                $ext = pathinfo($screenshot['name'], PATHINFO_EXTENSION);
                $fname = uniqid('ss_', true) . '.' . $ext;
                
                if (move_uploaded_file($screenshot['tmp_name'], $upload_dir . $fname)) {
                    try {
                        $stmt = $db->prepare("INSERT INTO payments (user_id, method, amount, txn_id, screenshot_path, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $stmt->execute([$_SESSION['user_id'], $method, $amount, $txn_id, $fname]);
                        redirect("add-funds.php?success=manual");
                    } catch (PDOException $e) { $error = 'DB Error: ' . $e->getMessage(); }
                } else { $error = 'Upload failed. Permission denied.'; }
            } else { $error = 'Invalid image format.'; }
        }
    }
}

include '_header.php';

$stmt_methods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");
$methods = $stmt_methods->fetchAll();
?>

<style>
    /* Global Reset fixes */
    * { box-sizing: border-box; }
    
    body, .main-content { 
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 30%, #e9d5ff 60%, #ddd6fe 100%) !important; 
        padding: 0 !important; 
        margin: 0 !important;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes borderGlow { 0%, 100% { border-color: #10b981; box-shadow: 0 0 25px rgba(147, 51, 234, 0.4); } 50% { border-color: #9333ea; box-shadow: 0 0 40px rgba(147, 51, 234, 0.6); } }
    @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.15); opacity: 0.8; } }
    @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }

    /* Layout Wrapper */
    .page-wrapper {
        min-height: 100vh;
        padding: 1rem;
        position: relative;
        overflow-x: hidden;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Background Blobs */
    .blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(60px);
        z-index: 0;
        pointer-events: none;
    }
    .blob-1 { top: 5%; left: 0%; width: 40vw; height: 40vw; max-width: 350px; max-height: 350px; background: radial-gradient(circle, rgba(147,51,234,0.25) 0%, transparent 70%); animation: pulse 4s infinite; }
    .blob-2 { bottom: 5%; right: 0%; width: 50vw; height: 50vw; max-width: 450px; max-height: 450px; background: radial-gradient(circle, rgba(16,185,129,0.25) 0%, transparent 70%); animation: pulse 6s infinite 1s; }

    /* Main Container */
    .content-container {
        position: relative;
        z-index: 1;
        width: 100%;
    }

    /* Headers */
    .page-title {
        text-align: center; 
        margin-bottom: 2rem; 
        animation: fadeInUp 0.6s ease-out;
    }
    .page-title h1 {
        font-size: clamp(1.8rem, 5vw, 3rem); 
        font-weight: 800; 
        background: linear-gradient(135deg, #9333ea 0%, #10b981 50%, #059669 100%); 
        -webkit-background-clip: text; 
        -webkit-text-fill-color: transparent; 
        margin-bottom: 0.5rem;
    }
    .page-title p { color: #6b7280; font-weight: 500; font-size: 0.95rem; padding: 0 10px; }

    /* Responsive Grid System */
    .main-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: 1fr; /* Mobile Default */
        animation: fadeInUp 0.6s ease-out 0.2s backwards;
    }

    @media (min-width: 992px) {
        .main-grid {
            grid-template-columns: 1.4fr 1fr; /* Desktop: Left side wider */
            align-items: start;
        }
        .sidebar-sticky {
            position: sticky;
            top: 2rem;
        }
    }

    /* Cards */
    .glass-card {
        background: #fff;
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .card-nayapay {
        border: 2px solid #9333ea;
        box-shadow: 0 15px 40px rgba(147,51,234,0.2);
        animation: borderGlow 3s infinite;
    }

    .card-manual {
        border: 2px solid #f59e0b;
        box-shadow: 0 15px 40px rgba(245,158,11,0.15);
    }

    .card-accounts {
        border: 2px solid #818cf8;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        padding: 1.2rem;
    }

    /* Form Elements */
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-weight: 700; margin-bottom: 0.5rem; font-size: 0.95rem; }
    
    .input-field {
        width: 100%;
        padding: 0.9rem;
        border-radius: 12px;
        outline: none;
        transition: 0.3s;
        font-size: 1rem;
    }
    
    /* Buttons */
    .btn-action {
        width: 100%;
        padding: 1rem;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-action:active { transform: scale(0.98); }

    /* Utilities */
    .flex-center { display: flex; align-items: center; gap: 1rem; }
    .icon-box { padding: 0.8rem; border-radius: 12px; color: #fff; display: flex; align-items: center; justify-content: center; }
    
    .promo-container { display: flex; gap: 10px; }
    .promo-input { flex: 1; }
    .btn-check { color: #fff; border: none; padding: 0 1.2rem; border-radius: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; }

    /* Alert Boxes */
    .alert-box { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; }
</style>

<div class="page-wrapper">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    
    <div class="content-container">
        
        <div class="page-title">
            <h1>💰 Add Funds to Wallet</h1>
            <p>Choose payment method & apply promo codes for bonus!</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-box" style="background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b;">
            ⚠️ <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert-box" style="background: #d1fae5; border-left: 4px solid #10b981; color: #065f46;">
            ✅ <?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>

        <div class="main-grid">
            
            <div>
                
                <div class="glass-card card-nayapay">
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, rgba(147,51,234,0.8), transparent); animation: shimmer 3s linear infinite;"></div>
                    
                    <div class="flex-center" style="margin-bottom: 1.2rem;">
                        <div class="icon-box" style="background: linear-gradient(135deg, #9333ea, #10b981);">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div>
                            <h3 style="color: #9333ea; font-size: 1.2rem; font-weight: 700; margin: 0;">NayaPay Auto</h3>
                            <p style="color: #7c3aed; font-size: 0.85rem; margin: 0; font-weight: 600;">⚡ Instant Verification</p>
                        </div>
                    </div>

                    <div style="background: #f3e8ff; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border-left: 4px solid #9333ea;">
                        <ol style="margin: 0; padding-left: 1.2rem; color: #6b7280; font-size: 0.85rem; font-weight: 500; line-height: 1.5;">
                            <li>Send money to NayaPay account (details on right/below).</li>
                            <li>Enter <strong>Transaction ID</strong> & <strong>Amount</strong>.</li>
                            <li>Funds + Bonus added instantly!</li>
                        </ol>
                    </div>
                    
                    <form action="add-funds.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <input type="hidden" name="action" value="claim_payment">
                        
                        <div class="form-group">
                            <label class="form-label" style="color:#7c3aed;">Transaction ID</label>
                            <input type="text" class="input-field" name="nayapay_txn_id" placeholder="e.g. 123456789" required style="border:2px solid #e9d5ff;" onfocus="this.style.borderColor='#9333ea'">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" style="color:#7c3aed;">Amount (PKR)</label>
                            <input type="number" class="input-field" name="nayapay_amount" id="np_amount" placeholder="e.g. 500" required style="border:2px solid #e9d5ff;" onfocus="this.style.borderColor='#9333ea'">
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="color:#059669;">Promo Code (Optional)</label>
                            <div class="promo-container">
                                <input type="text" class="input-field promo-input" name="promo_code" id="np_promo" placeholder="Enter code" style="border:2px solid #a7f3d0; text-transform:uppercase;" onfocus="this.style.borderColor='#059669'">
                                <button type="button" class="btn-check" onclick="checkPromo('np')" style="background:#10b981;">Check</button>
                            </div>
                            <small id="np_promo_msg" style="display:block; margin-top:5px; font-weight:600; min-height:20px; font-size:0.8rem;"></small>
                        </div>
                        
                        <button type="submit" class="btn-action" style="background: linear-gradient(135deg, #9333ea, #7c3aed); color:#fff; box-shadow: 0 5px 15px rgba(147,51,234,0.3);">
                            Verify & Claim Funds
                        </button>
                    </form>
                </div>

                <div class="glass-card card-manual">
                    <div class="flex-center" style="margin-bottom: 1.2rem;">
                        <div class="icon-box" style="background: linear-gradient(135deg, #f59e0b, #ea580c);">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <h3 style="color: #d97706; font-size: 1.2rem; font-weight: 700; margin: 0;">Manual Deposit</h3>
                            <p style="color: #b45309; font-size: 0.85rem; margin: 0; font-weight: 600;">⏳ Approval Required</p>
                        </div>
                    </div>

                    <form action="add-funds.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <input type="hidden" name="action" value="manual_deposit">
                        
                        <div class="form-group">
                            <label class="form-label" style="color:#d97706;">Payment Method</label>
                            <select name="manual_method" class="input-field" required style="border:2px solid #fed7aa; background: #fff;">
                                <option value="">Select Method</option>
                                <?php foreach ($methods as $m): if($m['is_auto']==0): ?>
                                <option value="<?= sanitize($m['name']) ?>"><?= sanitize($m['name']) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" style="color:#d97706;">Amount (PKR)</label>
                            <input type="number" class="input-field" name="manual_amount" id="mn_amount" placeholder="1000" required style="border:2px solid #fed7aa;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="color:#d97706;">Transaction ID</label>
                            <input type="text" class="input-field" name="txn_id" placeholder="Txn ID" required style="border:2px solid #fed7aa;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="color:#059669;">Promo Code (Optional)</label>
                            <div class="promo-container">
                                <input type="text" class="input-field promo-input" name="promo_code" id="mn_promo" placeholder="Enter code" style="border:2px solid #a7f3d0; text-transform:uppercase;">
                                <button type="button" class="btn-check" onclick="checkPromo('mn')" style="background:#10b981;">Check</button>
                            </div>
                            <small id="mn_promo_msg" style="display:block; margin-top:5px; font-weight:600; color:#666; font-size: 0.8rem;">Note: Bonus added after admin approval.</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="color:#d97706;">Screenshot</label>
                            <input type="file" class="input-field" name="screenshot" accept="image/*" required style="padding:0.7rem; background:#fff7ed; border:2px solid #fed7aa;">
                        </div>
                        
                        <button type="submit" class="btn-action" style="background: linear-gradient(135deg, #f59e0b, #d97706); color:#fff; box-shadow: 0 5px 15px rgba(245,158,11,0.3);">
                            Submit Deposit
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="sidebar-sticky">
                <div class="glass-card card-accounts">
                    <h3 style="color: #4338ca; font-size: 1.2rem; font-weight: 700; margin-bottom: 1.2rem; border-bottom: 2px solid #e0e7ff; padding-bottom: 0.8rem;">
                        💳 Payment Accounts
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($methods as $m): ?>
                        <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0;">
                            <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.5rem;">
                                <img src="../assets/img/methods/<?= sanitize($m['icon_path']) ?>" style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                                <div>
                                    <span style="display:block; font-weight:800; color:#1e293b; font-size:1rem;"><?= sanitize($m['name']) ?></span>
                                    <?php if($m['is_auto']): ?><span style="font-size:0.65rem; background:#10b981; color:#fff; padding:2px 6px; border-radius:4px; font-weight:bold;">AUTO</span><?php endif; ?>
                                </div>
                            </div>
                            <div style="font-family: monospace; background: #fff; padding: 8px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 5px;">
                                <p style="margin:0; color:#64748b; font-size:0.75rem;">Title: <strong><?= sanitize($m['account_name']) ?></strong></p>
                                <p style="margin:3px 0 0 0; color:#334155; font-size:1rem; font-weight:700; word-break: break-all;"><?= sanitize($m['account_number']) ?></p>
                            </div>
                            <?php if($m['note']): ?><p style="margin:8px 0 0 0; font-size:0.75rem; color:#ef4444; font-style:italic; line-height: 1.3;"><?= sanitize($m['note']) ?></p><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function checkPromo(prefix) {
    let code = document.getElementById(prefix + '_promo').value;
    let amount = document.getElementById(prefix + '_amount').value;
    let msgBox = document.getElementById(prefix + '_promo_msg');

    if(!code) { msgBox.innerHTML = '<span style="color:red">Enter a code first!</span>'; return; }
    if(!amount || amount <= 0) { msgBox.innerHTML = '<span style="color:red">Enter amount first!</span>'; return; }

    msgBox.innerHTML = '<span style="color:#666">Checking...</span>';

    let formData = new FormData();
    formData.append('action', 'check_promo'); // Important: Send action to current page
    formData.append('code', code);
    formData.append('amount', amount);

    // Fetch from current page instead of includes
    fetch('add-funds.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.valid) {
            msgBox.innerHTML = `<span style="color:#059669">✨ Valid! Bonus: ${data.bonus_amount} (Total: ${data.total_amount})</span>`;
        } else {
            msgBox.innerHTML = `<span style="color:#dc2626">❌ ${data.error}</span>`;
        }
    })
    .catch(err => {
        msgBox.innerHTML = '<span style="color:red">Error checking code.</span>';
        console.error(err);
    });
}
</script>

<?php include '_footer.php'; ?>