<?php
include '_header.php';
requireAdmin();

// --- CONFIGURATION ---
$log_files = [
    'smm_order_placer.log' => '📦 Order Placer (API Orders)',
    'smm_service_sync.log' => '🔄 Service Sync (Updates)',
    'smm_status_sync.log'  => '📊 Status Sync (Completed/Cancel)',
    'email_payments.log'   => '✉️ Email Payments (NayaPay)'
];

$log_dir = __DIR__ . '/../assets/logs/';
$selected_log = $_GET['log'] ?? 'smm_order_placer.log';

// Security Check
if (!array_key_exists($selected_log, $log_files)) {
    $selected_log = 'smm_order_placer.log';
}

$file_path = $log_dir . $selected_log;

// --- CLEAR LOG ACTION ---
if (isset($_POST['clear_log'])) {
    file_put_contents($file_path, ""); // Empty the file
    $success = "Log file cleared successfully!";
}

// --- READ LOGS ---
$logs_data = [];
if (file_exists($file_path)) {
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        // Show last 200 lines (Newest first)
        $lines = array_reverse(array_slice($lines, -200));
        
        foreach ($lines as $line) {
            // Parse Log Line: [Date Time] Message
            if (preg_match('/^\[(.*?)\] (.*)$/', $line, $matches)) {
                $status = 'info';
                $icon = 'ℹ️';
                $msg = $matches[2];

                // Detect Status based on keywords
                if (stripos($msg, 'error') !== false || stripos($msg, 'fail') !== false || stripos($msg, 'critical') !== false) {
                    $status = 'error';
                    $icon = '⚠️';
                } elseif (stripos($msg, 'success') !== false || stripos($msg, 'placed') !== false || stripos($msg, 'updated') !== false || stripos($msg, 'completed') !== false) {
                    $status = 'success';
                    $icon = '✅';
                } elseif (stripos($msg, 'start') !== false) {
                    $status = 'start';
                    $icon = '🚀';
                }

                $logs_data[] = [
                    'time' => $matches[1],
                    'msg'  => $msg,
                    'status' => $status,
                    'icon' => $icon
                ];
            } else {
                // Fallback for unformatted lines
                $logs_data[] = ['time' => '-', 'msg' => $line, 'status' => 'raw', 'icon' => '📝'];
            }
        }
    }
}
?>

<style>
/* --- MODERN UI VARIABLES --- */
:root {
    --bg-page: #f4f6f9;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    
    --success-bg: #dcfce7; --success-text: #166534; --success-border: #bbf7d0;
    --error-bg: #fee2e2; --error-text: #991b1b; --error-border: #fecaca;
    --info-bg: #f1f5f9; --info-text: #475569; --info-border: #e2e8f0;
    --start-bg: #e0f2fe; --start-text: #075985; --start-border: #bae6fd;
}

.log-container {
    max-width: 100%;
    margin: 0 auto;
}

/* Header & Filters */
.log-header {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
}

.log-title h2 { margin: 0; font-size: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
.log-title p { margin: 5px 0 0 0; font-size: 0.9rem; color: var(--text-secondary); }

.log-controls { display: flex; gap: 10px; align-items: center; }

.log-select {
    padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px;
    background: #fff; color: var(--text-primary); font-size: 0.95rem; cursor: pointer;
    min-width: 250px; outline: none; transition: 0.2s;
}
.log-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

.btn-clear {
    background: #fff; color: #ef4444; border: 1px solid #ef4444; padding: 9px 15px;
    border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s;
}
.btn-clear:hover { background: #ef4444; color: #fff; }

/* Search Bar */
.search-bar {
    width: 100%; margin-bottom: 20px;
}
.search-input {
    width: 100%; padding: 15px; border: 1px solid var(--border-color); border-radius: 10px;
    font-size: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); outline: none;
}
.search-input:focus { border-color: #3b82f6; }

/* Logs List */
.logs-wrapper {
    display: flex; flex-direction: column; gap: 10px;
}

.log-item {
    display: flex; align-items: flex-start; gap: 15px;
    background: #fff; padding: 15px; border-radius: 10px;
    border-left: 5px solid #ccc;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: transform 0.1s;
    animation: fadeIn 0.3s ease-out;
}
.log-item:hover { transform: translateX(5px); }

/* Status Styles */
.log-item.success { border-left-color: #22c55e; background: linear-gradient(to right, #f0fdf4, #fff); }
.log-item.error { border-left-color: #ef4444; background: linear-gradient(to right, #fef2f2, #fff); }
.log-item.info { border-left-color: #94a3b8; }
.log-item.start { border-left-color: #3b82f6; background: #eff6ff; }

.log-time {
    font-family: 'Courier New', monospace; font-size: 0.85rem; color: var(--text-secondary);
    min-width: 140px; padding-top: 3px;
}
.log-content { flex: 1; }
.log-msg { font-size: 0.95rem; color: var(--text-primary); line-height: 1.5; }

/* Highlighting Numbers */
.highlight-num { background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; font-weight: bold; font-family: monospace; }

/* Empty State */
.empty-state {
    text-align: center; padding: 50px; background: #fff; border-radius: 12px;
    border: 2px dashed var(--border-color); color: var(--text-secondary);
}

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="admin-container">

    <div class="log-header">
        <div class="log-title">
            <h2><i class="fas fa-file-alt"></i> System Logs</h2>
            <p>Monitor automated tasks and errors.</p>
        </div>

        <div class="log-controls">
            <form action="" method="GET" id="logForm">
                <select name="log" class="log-select" onchange="document.getElementById('logForm').submit()">
                    <?php foreach ($log_files as $file => $name): ?>
                        <option value="<?= $file ?>" <?= ($selected_log == $file) ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form action="" method="POST" onsubmit="return confirm('Are you sure you want to clear this log file?');">
                <input type="hidden" name="clear_log" value="1">
                <button type="submit" class="btn-clear" title="Delete all logs in this file">
                    <i class="fas fa-trash-alt"></i> Clear
                </button>
            </form>
        </div>
    </div>

    <div class="search-bar">
        <input type="text" id="logSearch" class="search-input" placeholder="🔍 Search logs (e.g. 'Error', 'Order ID', 'Success')..." onkeyup="filterLogs()">
    </div>

    <div class="logs-wrapper" id="logsContainer">
        <?php if (empty($logs_data)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                <h3>Log file is empty</h3>
                <p>No activities recorded yet for this section.</p>
            </div>
        <?php else: ?>
            <?php foreach ($logs_data as $log): 
                // Highlight numbers/IDs in message for better readability
                $formatted_msg = preg_replace('/(\d+)/', '<span class="highlight-num">$1</span>', htmlspecialchars($log['msg']));
            ?>
            <div class="log-item <?= $log['status'] ?>">
                <div class="log-time">
                    <?= $log['time'] ?><br>
                    <small><?= $log['icon'] ?> <?= strtoupper($log['status']) ?></small>
                </div>
                <div class="log-content">
                    <div class="log-msg"><?= $formatted_msg ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function filterLogs() {
    var input, filter, container, items, msg, i, txtValue;
    input = document.getElementById("logSearch");
    filter = input.value.toUpperCase();
    container = document.getElementById("logsContainer");
    items = container.getElementsByClassName("log-item");

    for (i = 0; i < items.length; i++) {
        msg = items[i].getElementsByClassName("log-msg")[0];
        if (msg) {
            txtValue = msg.textContent || msg.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                items[i].style.display = "";
            } else {
                items[i].style.display = "none";
            }
        }       
    }
}
</script>

<?php include '_footer.php'; ?>