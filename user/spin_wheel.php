<?php
include '_header.php';

$user_id = $_SESSION['user_id'];

// --- FETCH USER STATS ---
$stmt_stats = $db->prepare("SELECT COUNT(*) as total_spins, SUM(amount_won) as total_won FROM wheel_spins_log WHERE user_id = ?");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch();
$total_spins = $stats['total_spins'] ?? 0;
$total_won = $stats['total_won'] ?? 0.00;

// --- SERVER SIDE TIME CHECK ---
$stmt = $db->prepare("SELECT last_spin_time, balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$last_spin = $user_data['last_spin_time'] ? strtotime($user_data['last_spin_time']) : 0;
$current_time = time();
$cooldown_config = $GLOBALS['settings']['daily_spin_cooldown_hours'] ?? 24;
$cooldown = $cooldown_config * 60 * 60; 
$next_spin = $last_spin + $cooldown;
$seconds_left = max(0, $next_spin - $current_time);

// --- FETCH PRIZES ---
$prizes = $db->query("SELECT label, color, amount FROM wheel_prizes WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$segments = json_encode($prizes);
?>

<style>
/* --- 🎡 RESPONSIVE PREMIUM THEME --- */
:root {
    --bg-light: #f8faff;
    --primary-grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    --text-main: #1e293b;
    --card-shadow: 0 10px 30px -5px rgba(0,0,0,0.1);
}

.spin-page {
    min-height: 85vh;
    background: var(--bg-light);
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--text-main);
    padding: 1.5rem;
    overflow-x: hidden;
}

.spin-container {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr; /* Mobile First */
    gap: 3rem;
    align-items: center;
}

@media(min-width: 992px) {
    .spin-container { grid-template-columns: 1fr 1fr; }
}

/* --- STATS SECTION --- */
.info-section h1 {
    font-size: clamp(2rem, 5vw, 3rem); /* Responsive Font */
    font-weight: 800; margin-bottom: 0.5rem;
    background: var(--primary-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    line-height: 1.2;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;
}

.stat-card {
    background: #fff;
    border-radius: 20px; padding: 1.2rem;
    box-shadow: var(--card-shadow);
    text-align: center; border: 1px solid #e2e8f0;
}
.stat-icon { font-size: 1.5rem; display: block; margin-bottom: 5px; }
.stat-val { font-size: 1.2rem; font-weight: 800; color: #0f172a; word-break: break-all; }
.stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* --- WHEEL RESPONSIVE --- */
.wheel-section {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    position: relative;
}

.wheel-outer-ring {
    position: relative;
    width: 100%; 
    max-width: 350px; /* Max size for Desktop */
    aspect-ratio: 1/1; /* Keeps it perfectly round */
    border-radius: 50%;
    padding: 10px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(99, 102, 241, 0.2);
    display: flex; align-items: center; justify-content: center;
    border: 1px solid #e2e8f0;
}

.wheel-canvas-wrap {
    width: 100%; height: 100%;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    border: 4px solid #fff;
    box-shadow: inset 0 0 20px rgba(0,0,0,0.1);
}

#canvas { width: 100%; height: 100%; display: block; transition: transform 5s cubic-bezier(0.25, 0.1, 0.25, 1); }

/* Elements */
.stopper {
    position: absolute; top: -25px; left: 50%; transform: translateX(-50%);
    width: 50px; height: 60px; z-index: 20;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
}

.spin-btn {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 20%; height: 20%; /* Responsive Size */
    border-radius: 50%;
    background: var(--primary-grad);
    border: 3px solid #fff;
    color: white; font-weight: 800; font-size: clamp(0.8rem, 2vw, 1rem);
    cursor: pointer; z-index: 30;
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.4);
    display: flex; align-items: center; justify-content: center;
}
.spin-btn:disabled { background: #cbd5e1; cursor: not-allowed; box-shadow: none; }

/* Timer */
.timer-display {
    margin-top: 2rem;
    background: #fff; padding: 12px 25px; border-radius: 50px;
    box-shadow: var(--card-shadow); border: 1px solid #fee2e2;
    font-weight: 700; color: #ef4444; font-size: 0.9rem;
    display: inline-flex; align-items: center; gap: 8px;
}

/* Modal Fixes */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7); z-index: 200;
    display: none; align-items: center; justify-content: center;
    backdrop-filter: blur(5px); padding: 20px;
}
.modal-card {
    background: #fff; padding: 2rem; border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    text-align: center; width: 100%; max-width: 380px;
    animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes bounceIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.btn-awesome {
    background: var(--primary-grad); color: white; border: none;
    padding: 14px; border-radius: 12px; font-weight: 700;
    font-size: 1rem; cursor: pointer; width: 100%;
    margin-top: 15px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
}

#confetti { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 150; }
</style>

<div class="spin-page">
    <div class="spin-container">
        
        <div class="info-section">
            <h1>Daily Spin & Win</h1>
            <p style="color: #64748b; margin-bottom: 2rem;">Spin daily to win real cash rewards!</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-icon">🌀</span>
                    <div class="stat-val"><?php echo number_format($total_spins); ?></div>
                    <div class="stat-label">Spins</div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">🏆</span>
                    <div class="stat-val"><?php echo formatCurrency($total_won); ?></div>
                    <div class="stat-label">Won</div>
                </div>
            </div>

            <div class="stat-card" style="display: flex; align-items: center; justify-content: space-between; padding: 1.2rem;">
                <span class="stat-label">Balance</span>
                <span class="stat-val" style="color:#10b981;"><?php echo formatCurrency($user_data['balance']); ?></span>
            </div>
        </div>

        <div class="wheel-section">
            <div class="wheel-outer-ring">
                <svg class="stopper" viewBox="0 0 24 24" fill="#6366f1">
                    <path d="M12 22L12 2M12 22L6 16M12 22L18 16" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 2L4 16H20L12 2Z" fill="#6366f1" stroke="white" stroke-width="2"/>
                </svg>
                
                <div class="wheel-canvas-wrap">
                    <canvas id="canvas" width="400" height="400"></canvas>
                </div>

                <button id="spinBtn" class="spin-btn" onclick="spinWheel()" <?php echo ($seconds_left > 0) ? 'disabled' : ''; ?>>
                    <?php echo ($seconds_left > 0) ? '<i class="fa-solid fa-lock"></i>' : 'SPIN'; ?>
                </button>
            </div>

            <div id="timerContainer" style="display: <?php echo ($seconds_left > 0) ? 'block' : 'none'; ?>">
                <div class="timer-display">
                    <i class="fa-regular fa-clock"></i> 
                    Wait: <span id="countdown">Loading...</span>
                </div>
            </div>
        </div>

    </div>
</div>

<canvas id="confetti"></canvas>
<div class="modal-overlay" id="winModal">
    <div class="modal-card">
        <div style="font-size: 3.5rem; margin-bottom: 10px;" id="winIcon">🎉</div>
        <h2 style="font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0;" id="winTitle">Congratulations!</h2>
        <p style="font-size: 1rem; color: #64748b; margin: 10px 0 20px;" id="winMsg">You won 10 PKR</p>
        <button class="btn-awesome" onclick="location.reload()">Claim Reward</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
// --- JS Logic (Same as before but optimized) ---
const segments = <?php echo $segments; ?>;
const serverSeconds = <?php echo $seconds_left; ?>;

const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
const btn = document.getElementById('spinBtn');
let isSpinning = false;

// DRAW
const size = segments.length;
const arc = 2 * Math.PI / size;

function drawWheel() {
    if(size === 0) return;
    // High Res Clear
    ctx.clearRect(0, 0, 400, 400);
    ctx.translate(200, 200);
    
    for (let i = 0; i < size; i++) {
        const angle = i * arc;
        ctx.beginPath();
        ctx.fillStyle = segments[i].color;
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, 195, angle, angle + arc); // Radius 195 to fit 400px canvas
        ctx.lineTo(0, 0);
        ctx.fill();
        
        // Text
        ctx.save();
        ctx.translate(Math.cos(angle + arc / 2) * 140, Math.sin(angle + arc / 2) * 140);
        ctx.rotate(angle + arc / 2 + Math.PI / 2);
        ctx.fillStyle = "#fff";
        ctx.font = 'bold 16px Plus Jakarta Sans';
        ctx.shadowColor = "rgba(0,0,0,0.5)";
        ctx.shadowBlur = 4;
        ctx.textAlign = "center";
        
        // Truncate long text
        let text = segments[i].label;
        if(text.length > 12) text = text.substring(0, 10) + "..";
        
        ctx.fillText(text, 0, 0);
        ctx.restore();
    }
    ctx.translate(-200, -200);
}
drawWheel();

// SPIN
function spinWheel() {
    if (isSpinning) return;
    isSpinning = true;
    btn.disabled = true;
    btn.innerHTML = '...';

    fetch('spin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=spin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const winIndex = data.prize_index;
            const rotations = 8; 
            const segmentAngle = 360 / size;
            const stopAngle = (360 - (winIndex * segmentAngle) - (segmentAngle/2)) - 90; 
            const totalDeg = (rotations * 360) + stopAngle;

            canvas.style.transition = 'transform 5s cubic-bezier(0.15, 0, 0.15, 1)';
            canvas.style.transform = `rotate(${totalDeg}deg)`;

            setTimeout(() => { showResult(data); }, 5000);
        } else {
            alert(data.error);
            location.reload();
        }
    })
    .catch(() => {
        alert('Network Error');
        location.reload();
    });
}

function showResult(data) {
    const modal = document.getElementById('winModal');
    const title = document.getElementById('winTitle');
    const msg = document.getElementById('winMsg');
    const icon = document.getElementById('winIcon');

    if (parseFloat(data.amount) > 0) {
        title.innerText = "Congratulations!";
        msg.innerText = data.message;
        icon.innerText = "🎉";
        confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
    } else {
        title.innerText = "Bad Luck!";
        msg.innerText = data.message;
        icon.innerText = "😢";
    }
    modal.style.display = 'flex';
}

// TIMER
let timeLeft = serverSeconds;
const timerEl = document.getElementById('countdown');
if (timeLeft > 0) {
    const interval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(interval);
            document.getElementById('timerContainer').style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = 'SPIN';
            return;
        }
        let h = Math.floor(timeLeft / 3600);
        let m = Math.floor((timeLeft % 3600) / 60);
        let s = timeLeft % 60;
        timerEl.innerText = `${h}h ${m}m ${s}s`;
        timeLeft--;
    }, 1000);
}
</script>

<?php include '_footer.php'; ?>