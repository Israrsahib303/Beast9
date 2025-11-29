<?php
include '_header.php';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $amt = (float)$_POST['amount'];
    $pass = $_POST['password'];
    $uid = $_SESSION['user_id'];
    
    $sender = $db->query("SELECT * FROM users WHERE id=$uid")->fetch();
    
    if (!password_verify($pass, $sender['password_hash'])) {
        $msg = "<div class='alert alert-danger'>
                    <svg width='20' height='20' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'></path></svg>
                    <span>Incorrect Password!</span>
                </div>";
    } elseif ($amt <= 0 || $sender['balance'] < $amt) {
        $msg = "<div class='alert alert-danger'>
                    <svg width='20' height='20' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path d='M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'></path></svg>
                    <span>Insufficient Balance.</span>
                </div>";
    } else {
        $receiver = $db->prepare("SELECT * FROM users WHERE email=?");
        $receiver->execute([$email]);
        $rcv = $receiver->fetch();
        
        if (!$rcv) {
            $msg = "<div class='alert alert-danger'><span>User not found.</span></div>";
        } elseif ($rcv['id'] == $uid) {
            $msg = "<div class='alert alert-danger'><span>Cannot send to yourself.</span></div>";
        } else {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE users SET balance = balance - ? WHERE id=?")->execute([$amt, $uid]);
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$amt, $rcv['id']]);
                $db->prepare("INSERT INTO transfers (sender_id, receiver_id, amount) VALUES (?,?,?)")->execute([$uid, $rcv['id'], $amt]);
                $db->commit();
                $msg = "<div class='alert alert-success'><span>Sent <b>".formatCurrency($amt)."</b> to ".sanitize($email)."</span></div>";
                $user_balance -= $amt;
            } catch (Exception $e) {
                $db->rollBack();
                $msg = "<div class='alert alert-danger'>Transaction Failed.</div>";
            }
        }
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* --- 🎨 THEME VARIABLES --- */
:root {
    --primary: #4F46E5;       
    --primary-dark: #4338CA;
    --accent: #8B5CF6;        
    --bg-body: #F8FAFC;
    --border: #E2E8F0;
    --radius: 24px;
    --shadow: 0 20px 40px -5px rgba(0,0,0,0.1);
}

/* FORCE LIGHT BACKGROUND FOR THIS PAGE ONLY */
body {
    background-color: var(--bg-body) !important;
    color: #1E293B !important; /* Dark Text */
    font-family: 'Outfit', sans-serif;
    background-image: 
        radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.08) 0px, transparent 50%),
        radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.08) 0px, transparent 50%);
    background-attachment: fixed;
}

.transfer-container {
    max-width: 550px; margin: 40px auto; padding: 0 20px;
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

/* --- WALLET CARD (Mini) --- */
.wallet-mini {
    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
    padding: 25px 30px; border-radius: var(--radius); color: #fff !important;
    display: flex; justify-content: space-between; align-items: center;
    box-shadow: 0 15px 30px rgba(79, 70, 229, 0.25);
    margin-bottom: 30px; position: relative; overflow: hidden;
}
.wallet-mini * { color: #fff !important; } /* Ensure text inside wallet is white */

.wallet-mini::before {
    content:''; position: absolute; top: -50%; right: -10%; width: 150px; height: 150px;
    background: rgba(255,255,255,0.1); border-radius: 50%;
}
.wal-label { font-size: 0.8rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
.wal-amount { font-size: 2rem; font-weight: 800; margin: 2px 0 0 0; letter-spacing: -0.5px; }
.wal-icon { font-size: 2rem; opacity: 0.8; }

/* --- TRANSFER FORM CARD --- */
.transfer-card {
    background: #ffffff !important; /* Force White */
    border-radius: var(--radius); padding: 35px;
    box-shadow: var(--shadow); border: 1px solid var(--border);
}

.card-header { text-align: center; margin-bottom: 30px; }
.card-header h2 { margin: 0; font-size: 1.8rem; font-weight: 800; color: #111 !important; }
.card-header p { margin: 8px 0 0 0; color: #666 !important; font-size: 0.95rem; }

/* Form Inputs */
.form-group { margin-bottom: 20px; position: relative; }
.form-label {
    display: block; font-weight: 600; font-size: 0.9rem; color: #333 !important;
    margin-bottom: 8px;
}
.input-wrapper { position: relative; }
.input-icon {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    color: #888 !important; font-size: 1.2rem; transition: 0.3s;
}
.form-input {
    width: 100%; padding: 16px 20px 16px 50px; border: 2px solid var(--border);
    border-radius: 16px; font-size: 1rem; transition: all 0.3s ease;
    background: #F8FAFC !important; color: #333 !important; font-family: inherit;
}
.form-input:focus {
    border-color: var(--primary); background: #fff !important; outline: none;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}
.form-input:focus + .input-icon { color: var(--primary) !important; }

/* Button */
.btn-send {
    width: 100%; padding: 18px; border: none; border-radius: 16px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff !important; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    transition: all 0.3s; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25);
    display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-send:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.35); }

/* Alerts */
.alert {
    padding: 15px 20px; border-radius: 12px; margin-bottom: 25px;
    display: flex; align-items: center; gap: 12px; font-size: 0.9rem; font-weight: 500;
}
.alert-danger { background: #FEF2F2; color: #991B1B; border: 1px solid #FEE2E2; }
.alert-success { background: #F0FDF4; color: #166534; border: 1px solid #DCFCE7; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="transfer-container">
    
    <div class="wallet-mini">
        <div>
            <p class="wal-label">Available Balance</p>
            <h1 class="wal-amount"><?php echo formatCurrency($user_balance); ?></h1>
        </div>
        <div class="wal-icon">💸</div>
    </div>

    <div class="transfer-card">
        <div class="card-header">
            <h2>Send Money</h2>
            <p>Transfer funds instantly to friends.</p>
        </div>

        <?php echo $msg; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label class="form-label">Receiver Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉️</span>
                    <input type="email" name="email" class="form-input" placeholder="friend@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Amount (<?= $GLOBALS['curr_symbol'] ?? 'Rs' ?>)</label>
                <div class="input-wrapper">
                    <span class="input-icon">💰</span>
                    <input type="number" name="amount" class="form-input" placeholder="100.00" min="1" step="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn-send">
                Transfer Now
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
        </form>
    </div>
</div>

<?php include '_footer.php'; ?>