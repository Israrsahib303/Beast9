<?php
include '_header.php'; 
requireAdmin();

// --- 0. AUTO-DB FIX (Add Missing Columns) ---
try {
    $cols = $db->query("SHOW COLUMNS FROM admin_logs")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('browser', $cols)) $db->exec("ALTER TABLE admin_logs ADD COLUMN browser VARCHAR(100) DEFAULT 'Unknown'");
    if (!in_array('os', $cols)) $db->exec("ALTER TABLE admin_logs ADD COLUMN os VARCHAR(50) DEFAULT 'Unknown'");
} catch (Exception $e) { /* Silent */ }

// --- 1. CLEAR LOGS ---
if (isset($_GET['clear_all'])) {
    $db->query("TRUNCATE TABLE admin_logs");
    echo "<script>window.location.href='activity_log.php';</script>";
    exit;
}

// --- 2. FETCH LOGS ---
$page = (int)($_GET['page'] ?? 1);
$per_page = 25; // Increased per page
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';

$where = "WHERE 1";
$params = [];

if ($search) {
    $where .= " AND (description LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_type) {
    $where .= " AND action_type = ?";
    $params[] = $filter_type;
}

// Join with Users table to get Admin Name
$sql = "SELECT l.*, u.name, u.email FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        $where ORDER BY l.id DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$total_logs = $db->prepare("SELECT COUNT(*) FROM admin_logs $where");
$total_logs->execute($params);
$total_count = $total_logs->fetchColumn();
$total_pages = ceil($total_count / $per_page);
?>

<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --bg-body: #f1f5f9;
        --card-bg: #ffffff;
        --text-main: #0f172a;
        --text-sub: #64748b;
        --border: #e2e8f0;
        
        --log-red: #fee2e2; --text-red: #ef4444;
        --log-green: #dcfce7; --text-green: #10b981;
        --log-blue: #e0f2fe; --text-blue: #0ea5e9;
        --log-orange: #ffedd5; --text-orange: #f97316;
        --log-gray: #f3f4f6; --text-gray: #475569;
    }
    body { background: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-main); }

    /* HEADER CARD */
    .audit-header {
        background: white; padding: 25px; border-radius: 20px;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05); border: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
        margin-bottom: 30px;
    }
    .audit-title h1 { margin: 0; font-size: 1.8rem; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
    .audit-title span { background: #f1f5f9; font-size: 0.9rem; padding: 4px 10px; border-radius: 8px; color: #64748b; font-weight: 600; }
    .audit-title p { margin: 5px 0 0; color: #94a3b8; font-size: 0.95rem; }

    /* FILTERS & SEARCH */
    .filter-bar {
        display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 8px; border-radius: 12px; border: 1px solid var(--border);
    }
    .search-input {
        background: transparent; border: none; padding: 10px; outline: none; font-size: 0.9rem; width: 250px; color: var(--text-main);
    }
    .filter-select {
        background: #fff; border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; 
        font-size: 0.85rem; color: var(--text-sub); outline: none; cursor: pointer; font-weight: 600;
    }
    .btn-clear {
        background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 20px;
        border-radius: 10px; font-weight: 700; text-decoration: none; transition: 0.2s;
        display: flex; align-items: center; gap: 8px; font-size: 0.9rem;
    }
    .btn-clear:hover { background: #b91c1c; color: white; transform: translateY(-2px); }

    /* LOGS CONTAINER */
    .logs-container {
        display: flex; flex-direction: column; gap: 15px; animation: fadeIn 0.5s ease-out;
    }
    
    /* INDIVIDUAL LOG CARD (Modern Row) */
    .log-item {
        background: white; border-radius: 16px; padding: 20px;
        border: 1px solid var(--border); box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        display: grid; grid-template-columns: 60px 1.5fr 1fr 1fr 150px;
        align-items: center; gap: 20px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative; overflow: hidden;
    }
    .log-item:hover { transform: translateX(5px); border-color: var(--primary); box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.1); }
    .log-item::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        background: var(--border); transition: 0.3s;
    }
    
    /* Dynamic Border Colors */
    .log-item[data-type="DELETE"]::before { background: var(--text-red); }
    .log-item[data-type="UPDATE"]::before { background: var(--text-orange); }
    .log-item[data-type="ADD"]::before { background: var(--text-green); }
    .log-item[data-type="LOGIN"]::before { background: var(--primary); }

    /* 1. ICON BOX */
    .log-icon-box {
        width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        background: var(--log-gray); color: var(--text-gray);
    }
    .icon-DELETE { background: var(--log-red); color: var(--text-red); }
    .icon-UPDATE { background: var(--log-orange); color: var(--text-orange); }
    .icon-ADD { background: var(--log-green); color: var(--text-green); }
    .icon-LOGIN { background: var(--log-blue); color: var(--text-blue); }

    /* 2. DESCRIPTION & ADMIN */
    .log-details h4 { margin: 0 0 5px 0; font-size: 1rem; color: var(--text-main); line-height: 1.4; }
    .log-details span { font-size: 0.85rem; color: var(--text-sub); display: flex; align-items: center; gap: 6px; }
    .admin-badge { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: var(--text-main); font-size: 0.75rem; text-transform: uppercase; }

    /* 3. TYPE BADGE */
    .type-badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
        border-radius: 30px; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;
        width: fit-content;
    }
    
    /* 4. IP & TECH */
    .ip-box { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-sub); cursor: pointer; display: flex; flex-direction: column; gap: 2px; }
    .ip-addr { color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 5px; }
    .ip-addr:hover { text-decoration: underline; }
    
    /* 5. TIME */
    .time-box { text-align: right; color: var(--text-sub); font-size: 0.9rem; font-weight: 600; }
    .time-ago { font-size: 0.75rem; color: #94a3b8; display: block; margin-top: 3px; }

    /* Pagination */
    .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 40px; }
    .page-btn {
        background: white; border: 1px solid var(--border); padding: 10px 16px; border-radius: 10px; color: var(--text-sub); font-weight: 700; text-decoration: none; transition: 0.2s;
    }
    .page-btn:hover { border-color: var(--primary); color: var(--primary); }
    .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

    /* Animations */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media(max-width: 900px) {
        .log-item { grid-template-columns: 1fr; gap: 10px; }
        .time-box { text-align: left; }
        .log-item::before { width: 100%; height: 4px; bottom: auto; }
        .audit-header { flex-direction: column; align-items: stretch; }
        .filter-bar { width: 100%; }
        .search-input { width: 100%; }
    }
</style>

<div style="max-width: 1100px; margin: 0 auto; padding: 20px;">

    <div class="audit-header">
        <div class="audit-title">
            <h1>🕵️ Security Audit Log <span><?= $total_count ?> Events</span></h1>
            <p>Track every move. Monitor staff activity, logins, and system changes.</p>
        </div>
        
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <div class="filter-bar">
                <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8; padding-left:10px;"></i>
                <input type="text" id="searchInp" class="search-input" placeholder="Search IP, Action or Name..." value="<?= htmlspecialchars($search) ?>" onkeypress="if(event.key==='Enter') window.location.href='?search='+this.value">
                
                <select class="filter-select" onchange="window.location.href='?type='+this.value">
                    <option value="">All Types</option>
                    <option value="LOGIN" <?= $filter_type=='LOGIN'?'selected':'' ?>>🟣 Logins</option>
                    <option value="UPDATE" <?= $filter_type=='UPDATE'?'selected':'' ?>>🟠 Updates</option>
                    <option value="ADD" <?= $filter_type=='ADD'?'selected':'' ?>>🟢 Additions</option>
                    <option value="DELETE" <?= $filter_type=='DELETE'?'selected':'' ?>>🔴 Deletions</option>
                </select>
            </div>
            
            <a href="?clear_all=1" class="btn-clear" onclick="return confirm('⚠️ ARE YOU SURE? This will wipe all history permanently.')">
                <i class="fa-solid fa-trash-can"></i> Wipe Logs
            </a>
        </div>
    </div>

    <div class="logs-container">
        
        <?php if(empty($logs)): ?>
            <div style="text-align:center; padding: 60px; color:#94a3b8; background:white; border-radius:16px; border:1px solid var(--border);">
                <i class="fa-solid fa-shield-cat" style="font-size:3rem; margin-bottom:15px; opacity:0.5;"></i>
                <h3>No Activity Recorded</h3>
                <p>System is quiet... for now.</p>
            </div>
        <?php else: ?>

            <?php foreach($logs as $l): 
                // Determine Styles based on Action Type keywords
                $type = 'INFO';
                $icon = 'fa-info';
                
                $act = strtoupper($l['action_type']);
                if(strpos($act, 'DELETE') !== false) { $type = 'DELETE'; $icon = 'fa-trash'; }
                elseif(strpos($act, 'UPDATE') !== false || strpos($act, 'EDIT') !== false) { $type = 'UPDATE'; $icon = 'fa-pen-to-square'; }
                elseif(strpos($act, 'ADD') !== false || strpos($act, 'CREATE') !== false) { $type = 'ADD'; $icon = 'fa-plus'; }
                elseif(strpos($act, 'LOGIN') !== false) { $type = 'LOGIN'; $icon = 'fa-right-to-bracket'; }
                elseif(strpos($act, 'BAN') !== false) { $type = 'DELETE'; $icon = 'fa-gavel'; }
                
                // Admin Name Initials
                $initial = strtoupper(substr($l['name'] ?? 'S', 0, 1));
            ?>
            
            <div class="log-item" data-type="<?= $type ?>">
                
                <div class="log-icon-box icon-<?= $type ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>

                <div class="log-details">
                    <h4><?= htmlspecialchars($l['description']) ?></h4>
                    <span>
                        <span class="admin-badge"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($l['name'] ?? 'System') ?></span>
                        &bull; <?= htmlspecialchars($l['email'] ?? 'Auto-Task') ?>
                    </span>
                </div>

                <div>
                    <div class="type-badge" style="background:var(--log-<?php 
                        if($type=='DELETE') echo 'red';
                        elseif($type=='UPDATE') echo 'orange';
                        elseif($type=='ADD') echo 'green';
                        else echo 'blue';
                    ?>); color:var(--text-<?php 
                        if($type=='DELETE') echo 'red';
                        elseif($type=='UPDATE') echo 'orange';
                        elseif($type=='ADD') echo 'green';
                        else echo 'blue';
                    ?>);">
                        <?= $l['action_type'] ?>
                    </div>
                </div>

                <div class="ip-box" onclick="copyToClipboard('<?= $l['ip_address'] ?>')" title="Click to Copy IP">
                    <div class="ip-addr"><i class="fa-solid fa-network-wired"></i> <?= $l['ip_address'] ?></div>
                    <?php if(!empty($l['browser']) && $l['browser'] != 'Unknown'): ?>
                        <div style="font-size:0.75rem; opacity:0.8;"><i class="fa-brands fa-chrome"></i> <?= substr($l['browser'], 0, 15) ?>...</div>
                    <?php endif; ?>
                </div>

                <div class="time-box">
                    <div><?= date('h:i A', strtotime($l['created_at'])) ?></div>
                    <div class="time-ago"><?= date('d M Y', strtotime($l['created_at'])) ?></div>
                </div>

            </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <div class="pagination">
        <?php if($page > 1): ?>
            <a href="?page=<?=$page-1?>" class="page-btn">« Previous</a>
        <?php endif; ?>
        
        <?php if($page < $total_pages): ?>
            <a href="?page=<?=$page+1?>" class="page-btn">Next »</a>
        <?php endif; ?>
    </div>

</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    // Simple toast notification
    let toast = document.createElement('div');
    toast.innerText = 'IP Copied: ' + text;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.background = '#1e293b';
    toast.style.color = '#fff';
    toast.style.padding = '10px 20px';
    toast.style.borderRadius = '8px';
    toast.style.zIndex = '1000';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
}
</script>

<?php include '_footer.php'; ?>