<?php
include '_header.php';

// --- HELPER: LOG READER & STATUS CHECKER ---
function getCronStatus($logName, $expectedIntervalMinutes) {
    $logPath = __DIR__ . '/../assets/logs/' . $logName;
    
    if (!file_exists($logPath)) {
        return ['status' => 'never', 'label' => 'Never Run', 'class' => 'bg-secondary', 'time' => 'N/A', 'content' => 'No logs found yet. Run manually once to generate logs.'];
    }

    $lastModified = filemtime($logPath);
    $timeDiff = time() - $lastModified;
    $timeAgo = humanTiming($lastModified) . ' ago';
    
    $content = tailCustom($logPath, 20);

    $gracePeriod = $expectedIntervalMinutes * 60 * 3; 
    
    if ($timeDiff < $gracePeriod) {
        return ['status' => 'running', 'label' => 'Healthy', 'class' => 'bg-success', 'time' => $timeAgo, 'content' => $content];
    } else {
        return ['status' => 'down', 'label' => 'Stopped / Late', 'class' => 'bg-danger', 'time' => $timeAgo, 'content' => $content];
    }
}

function humanTiming ($time) {
    $time = time() - $time; 
    $tokens = [
        31536000 => 'year', 2592000 => 'month', 604800 => 'week',
        86400 => 'day', 3600 => 'hour', 60 => 'minute', 1 => 'second'
    ];
    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
    }
    return 'just now';
}

function tailCustom($filepath, $lines = 10) {
    if (!is_readable($filepath)) return "Log file exists but cannot be read.";
    $data = ""; $fp = fopen($filepath, "r"); $block = 4096; $max = filesize($filepath);
    for($len = 0; $len < $max; $len += $block) {
        $seekSize = ($max - $len > $block) ? $block : $max - $len;
        fseek($fp, ($max - $len - $seekSize));
        $linesArr = explode("\n", fread($fp, $seekSize));
        if(count($linesArr) > $lines) return implode("\n", array_slice($linesArr, -$lines));
    }
    return file_get_contents($filepath);
}

// --- FIXED PHP PATH (As per your request) ---
$php_bin = '/usr/bin/php';

// --- CRON CONFIGURATION ---
$crons = [
    ['id' => 'order', 'title' => 'Order Placer', 'desc' => 'Sends pending orders to provider.', 'file' => 'smm_order_placer.php', 'log' => 'smm_order_placer.log', 'freq' => 'Every 1 Min', 'min' => 1, 'icon' => 'fa-rocket', 'color' => '#6366f1'],
    ['id' => 'status', 'title' => 'Status Sync', 'desc' => 'Updates order status & refunds.', 'file' => 'smm_status_sync.php', 'log' => 'smm_status_sync.log', 'freq' => 'Every 5 Mins', 'min' => 5, 'icon' => 'fa-rotate', 'color' => '#3b82f6'],
    ['id' => 'email', 'title' => 'Auto Payments', 'desc' => 'Checks emails for deposits.', 'file' => 'check_email.php', 'log' => 'email_payments.log', 'freq' => 'Every 5 Mins', 'min' => 5, 'icon' => 'fa-envelope-open-text', 'color' => '#ef4444'],
    ['id' => 'service', 'title' => 'Service Sync', 'desc' => 'Updates prices & services.', 'file' => 'smm_service_sync.php', 'log' => 'smm_service_sync.log', 'freq' => 'Once Daily', 'min' => 1440, 'icon' => 'fa-cloud-arrow-down', 'color' => '#f59e0b'],
    ['id' => 'sub', 'title' => 'Expire Subs', 'desc' => 'Marks expired subscriptions.', 'file' => 'expire_subscriptions.php', 'log' => 'subscriptions.log', 'freq' => 'Once Daily', 'min' => 1440, 'icon' => 'fa-clock', 'color' => '#8b5cf6'],
    ['id' => 'wallet', 'title' => 'Wallet Recalc', 'desc' => 'Auto-fixes balance issues.', 'file' => 'recalc_wallet.php', 'log' => 'wallet_recalc.log', 'freq' => 'Once Daily', 'min' => 1440, 'icon' => 'fa-calculator', 'color' => '#1e293b']
];
?>

<style>
/* --- ⚡ PREMIUM DASHBOARD UI (CENTERED) --- */
:root {
    --bg-body: #f3f4f6; --bg-card: #ffffff; --text-main: #1f2937; --text-muted: #6b7280;
    --border: #e2e8f0; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.cron-wrapper { padding: 2rem; max-width: 1600px; margin: 0 auto; }

/* Header */
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.page-title { font-size: 1.8rem; font-weight: 800; color: #111827; margin: 0; letter-spacing: -0.5px; }
.page-desc { color: #64748b; margin-top: 5px; font-size: 0.95rem; }

/* Grid Layout (CENTERED FIX) */
.cron-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); /* Adaptive width */
    gap: 1.5rem;
    justify-content: center; /* Center Items Horizontally */
}

/* Card Design */
.cron-card {
    background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border);
    transition: all 0.3s ease; position: relative; overflow: hidden;
    display: flex; flex-direction: column; height: 100%;
    box-shadow: var(--shadow);
    max-width: 450px; /* Prevent too wide on huge screens */
    margin: 0 auto;   /* Center individually if single item */
    width: 90%;
}
.cron-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-color: #cbd5e1; }

/* Status Stripe */
.status-stripe { height: 5px; width: 100%; background: #e2e8f0; }
.status-running { background: linear-gradient(90deg, #10b981, #34d399); }
.status-down { background: linear-gradient(90deg, #ef4444, #f87171); }
.status-never { background: #94a3b8; }

.card-content { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; }

/* Top Section */
.card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.cron-icon-box {
    width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.status-badge {
    padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;
}
.badge-running { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.badge-down { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-never { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

.pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; animation: pulse 1.5s infinite; }
@keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }

.cron-title { font-size: 1.2rem; font-weight: 800; color: #1f2937; margin: 0 0 5px 0; }
.cron-desc { font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.4; min-height: 40px; }

/* Stats Box */
.stats-row {
    display: flex; justify-content: space-between; margin-bottom: 1.5rem;
    background: #f8fafc; padding: 12px; border-radius: 12px; border: 1px solid #f1f5f9;
}
.stat-label { display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
.stat-val { font-size: 0.9rem; font-weight: 700; color: #334155; }
.text-danger { color: #ef4444 !important; }

/* Command Input */
.cmd-box { position: relative; margin-bottom: 1rem; margin-top: auto; }
.cmd-input {
    width: 100%; padding: 10px 40px 10px 12px; background: #1e293b; color: #a5f3fc;
    border: 1px solid #334155; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.75rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.btn-copy {
    position: absolute; right: 5px; top: 5px; width: 28px; height: 28px; border: none; background: rgba(255,255,255,0.1);
    border-radius: 6px; cursor: pointer; color: #fff; display: flex; align-items: center; justify-content: center; transition: 0.2s;
}
.btn-copy:hover { background: rgba(255,255,255,0.2); }

/* Action Buttons */
.card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.btn-action {
    padding: 10px; border-radius: 10px; text-align: center; text-decoration: none;
    font-weight: 600; font-size: 0.9rem; cursor: pointer; border: 1px solid transparent;
    display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s;
}
.btn-run { background: #f0f9ff; color: #0284c7; border-color: #e0f2fe; }
.btn-run:hover { background: #0284c7; color: #fff; }
.btn-log { background: #fef2f2; color: #dc2626; border-color: #fee2e2; }
.btn-log:hover { background: #dc2626; color: #fff; }

/* Loading Overlay */
.loading-overlay {
    position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.8);
    z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column;
    backdrop-filter: blur(5px);
}
.spinner { width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Log Modal */
.modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; align-items: center; justify-content: center; }
.log-modal { background: #1e293b; width: 90%; max-width: 800px; height: 80vh; border-radius: 20px; display: flex; flex-direction: column; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
.modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; color: #fff; }
.modal-body { flex-grow: 1; padding: 1.5rem; overflow: auto; font-family: 'Courier New', monospace; color: #a5f3fc; white-space: pre-wrap; font-size: 0.9rem; }
.modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
</style>

<div class="cron-wrapper">
    <div class="page-header">
        <div>
            <h1 class="page-title">⚡ Automation Center</h1>
            <p class="page-desc">Manage background tasks. Copy commands to cPanel for automation.</p>
        </div>
        <div style="color: var(--text-muted); font-size: 0.9rem;">
            <i class="fa-regular fa-clock"></i> Server Time: <?= date('d M, H:i') ?>
        </div>
    </div>

    <div class="cron-grid">
        <?php foreach($crons as $cron): 
            $status = getCronStatus($cron['log'], $cron['min']);
            $stripeClass = 'status-' . $status['status'];
            $badgeClass = 'badge-' . $status['status'];
            
            // Fixed Path Construction
            $realPath = realpath(__DIR__ . '/../includes/cron/' . $cron['file']);
            $command = $php_bin . ' ' . $realPath;
            $runUrl = '../includes/cron/' . $cron['file'];
        ?>
        <div class="cron-card">
            <div class="status-stripe <?= $stripeClass ?>"></div>
            <div class="card-content">
                <div class="card-top">
                    <div class="cron-icon-box" style="background: <?= $cron['color'] ?>;">
                        <i class="fa-solid <?= $cron['icon'] ?>"></i>
                    </div>
                    <div class="status-badge <?= $badgeClass ?>">
                        <?php if($status['status'] == 'running'): ?><span class="pulse-dot"></span><?php endif; ?>
                        <?= $status['label'] ?>
                    </div>
                </div>

                <h3 class="cron-title"><?= $cron['title'] ?></h3>
                <p class="cron-desc"><?= $cron['desc'] ?></p>

                <div class="stats-row">
                    <div class="stat-item">
                        <span class="stat-label">Frequency</span>
                        <span class="stat-val"><?= $cron['freq'] ?></span>
                    </div>
                    <div class="stat-item" style="text-align: right;">
                        <span class="stat-label">Last Run</span>
                        <span class="stat-val <?= ($status['status']=='down') ? 'text-danger' : '' ?>">
                            <?= $status['time'] ?>
                        </span>
                    </div>
                </div>

                <div class="cmd-box">
                    <input type="text" class="cmd-input" value="<?= $command ?>" readonly id="cmd_<?= $cron['id'] ?>">
                    <button class="btn-copy" onclick="copyCmd('cmd_<?= $cron['id'] ?>')" title="Copy Path">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>

                <div class="card-actions">
                    <button class="btn-action btn-run" onclick="runCron('<?= $runUrl ?>', '<?= $cron['title'] ?>')">
                        <i class="fa-solid fa-play"></i> Run Now
                    </button>
                    <button class="btn-action btn-log" onclick="openLog('<?= htmlspecialchars(json_encode($status['content']), ENT_QUOTES) ?>', '<?= $cron['title'] ?>')">
                        <i class="fa-solid fa-terminal"></i> Logs
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <h3 style="margin-top: 20px; color: #4f46e5;">Running Cron Job...</h3>
    <p style="color: #6b7280;">Please wait while we execute the script.</p>
</div>

<div class="modal-overlay" id="logModal">
    <div class="log-modal">
        <div class="modal-header">
            <h4 style="margin:0;"><i class="fa-solid fa-file-code"></i> <span id="modalTitle">Logs</span></h4>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copyCmd(id) {
    var copyText = document.getElementById(id);
    copyText.select();
    document.execCommand("copy");
    
    var btn = copyText.nextElementSibling;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color:#10b981"></i>';
    setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1500);
}

// --- AJAX CRON RUNNER ---
function runCron(url, title) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';

    fetch(url)
    .then(response => response.text())
    .then(data => {
        overlay.style.display = 'none';
        
        Swal.fire({
            title: 'Success!',
            text: title + ' executed successfully.',
            icon: 'success',
            confirmButtonColor: '#6366f1'
        }).then(() => {
            location.reload();
        });
        console.log(data);
    })
    .catch(error => {
        overlay.style.display = 'none';
        Swal.fire({
            title: 'Error!',
            text: 'Failed to run cron. Check console.',
            icon: 'error'
        });
    });
}

function openLog(content, title) {
    let cleanContent = content.replace(/^"|"$/g, '');
    document.getElementById('modalTitle').innerText = title + ' Logs';
    document.getElementById('modalBody').innerText = cleanContent || "No logs found.";
    document.getElementById('logModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('logModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('logModal')) {
        closeModal();
    }
}
</script>

<?php include '_footer.php'; ?>