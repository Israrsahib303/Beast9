<?php
// --- SETUP & AUTH ---
require_once '../includes/db.php';
require_once '../includes/config.php';
include '_header.php'; // Tumhara Global Header
include '_nav.php';    // Tumhara Menu

// 1. Login Check
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='../login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$tool_price = 5.00; // Price $5 (Change kar lena)

// 2. Database & Balance Logic
// (Ye code wahi hai jo pehle diya tha, bas safe tareeke se likha hai)
try {
    $stmt = $db->prepare("SELECT balance, voice_access FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Auto-Fix: Agar column na ho to create karega
    if (!$user) {
        $db->exec("ALTER TABLE users ADD COLUMN voice_access TINYINT(1) DEFAULT 0");
        echo "<script>location.reload();</script>";
        exit;
    }
} catch (Exception $e) {
    // Agar error aaye to column add karo
    $db->exec("ALTER TABLE users ADD COLUMN voice_access TINYINT(1) DEFAULT 0");
    echo "<script>location.reload();</script>";
    exit;
}

// 3. PURCHASE LOGIC
$msg = "";
$error = "";

if (isset($_POST['unlock_tool'])) {
    if ($user['voice_access'] == 1) {
        $msg = "You already own this!";
    } elseif ($user['balance'] < $tool_price) {
        $error = "Insufficient Balance! Please add funds.";
    } else {
        $new_balance = $user['balance'] - $tool_price;
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE users SET balance = :bal WHERE id = :id")->execute(['bal' => $new_balance, 'id' => $user_id]);
            $db->prepare("UPDATE users SET voice_access = 1 WHERE id = :id")->execute(['id' => $user_id]);
            $db->commit();
            echo "<script>
                alert('Success! Tool Unlocked.'); 
                window.location.href='voice_tool.php';
            </script>";
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Transaction Failed.";
        }
    }
}
?>

<style>
    :root {
        --card-bg: #ffffff;
        --primary-grad: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --glass-bg: rgba(255, 255, 255, 0.85);
    }

    body { background-color: #f8fafc; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .tool-header {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.05);
        border: 1px solid rgba(255,255,255,0.6);
        text-align: center;
        margin-bottom: 2rem;
        animation: fadeInUp 0.5s ease-out;
    }

    .tool-card {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 40px 20px;
        text-align: center;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        border: 1px solid #eee;
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.5s ease-out 0.2s backwards;
    }

    .icon-box {
        width: 100px;
        height: 100px;
        background: #eef2ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 3rem;
        color: #4f46e5;
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);
    }

    .price-tag {
        font-size: 2.5rem;
        font-weight: 800;
        color: #1f2937;
        margin: 15px 0;
    }

    .btn-buy {
        background: var(--primary-grad);
        color: white;
        border: none;
        padding: 15px 40px;
        font-size: 1.1rem;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        width: 100%;
        max-width: 300px;
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }

    .btn-buy:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4);
    }

    .btn-launch {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        text-decoration: none;
        padding: 15px 40px;
        font-size: 1.1rem;
        border-radius: 50px;
        font-weight: 600;
        display: inline-block;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        transition: all 0.3s;
    }
    
    .btn-launch:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4); color: white;}

    .feature-list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
        color: #6b7280;
    }
    .feature-list li { margin-bottom: 10px; }
    .feature-list i { color: #10b981; margin-right: 8px; }

</style>

<div class="container-fluid mt-4" style="max-width: 900px; margin: 0 auto;">
    
    <div class="tool-header">
        <h1 style="font-weight: 800; color: #111;">🎙️ AI Voice Generator Pro</h1>
        <p class="text-muted">Generate unlimited realistic human-like voiceovers instantly.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm rounded-pill text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="tool-card">
                
                <?php if (isset($user['voice_access']) && $user['voice_access'] == 1): ?>
                    
                    <div class="unlocked-view">
                        <div class="icon-box" style="background: #ecfdf5; color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="mb-3">Access Granted!</h2>
                        <p class="text-muted mb-4">Your tool is ready. Click below to launch the secure generator.</p>
                        
                        <a href="#" onclick="launchSecureTool()" class="btn-launch">
                            <i class="fas fa-rocket me-2"></i> Launch Voice Tool
                        </a>
                        
                        <script>
                        function launchSecureTool() {
                            // Opens a clean popup window without address bar (User feels like it's an app)
                            window.open('launch_tool.php', 'VoiceTool', 'width=1200,height=800,menubar=no,toolbar=no,location=no,status=no');
                        }
                        </script>
                    </div>

                <?php else: ?>
                    
                    <div class="locked-view">
                        <div class="icon-box">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Unlock Premium Access</h3>
                        <div class="price-tag">$<?php echo number_format($tool_price, 2); ?></div>
                        
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Unlimited AI Voice Generation</li>
                            <li><i class="fas fa-check"></i> High Quality Neural Voices</li>
                            <li><i class="fas fa-check"></i> One-time Payment (Lifetime)</li>
                        </ul>

                        <form method="POST">
                            <button type="submit" name="unlock_tool" class="btn-buy" onclick="return confirm('Confirm purchase for $<?php echo $tool_price; ?>?');">
                                <i class="fas fa-unlock-alt me-2"></i> Pay & Unlock Now
                            </button>
                        </form>
                        
                        <div class="mt-3 text-muted" style="font-size: 0.9rem;">
                            Your Balance: <b>$<?php echo isset($user['balance']) ? number_format($user['balance'], 2) : '0.00'; ?></b>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include '_footer.php'; ?>