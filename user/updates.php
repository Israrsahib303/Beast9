<?php
include '_smm_header.php';
$curr_code = $_COOKIE['site_currency'] ?? 'PKR';
// Currency Rate function check (fallback to 1)
$curr_rate = (function_exists('getCurrencyRate')) ? getCurrencyRate($curr_code) : 1;
// FIX 1: Variable name corrected to $curr_symbol
$curr_symbol = ($curr_code != 'PKR') ? $curr_code : 'Rs';

// Fetch Updates
$updates = $db->query("SELECT * FROM service_updates ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #4f46e5;
    --success: #10b981;
    --danger: #ef4444;
    --bg: #f8fafc;
    --card: #ffffff;
}
body { background: var(--bg); color: #1e293b; font-family: 'Outfit', sans-serif; }

.updates-container { max-width: 900px; margin: 30px auto; padding: 0 20px; }

.page-head { text-align: center; margin-bottom: 40px; }
.page-title { font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; }
.page-sub { color: #64748b; margin-top: 5px; }

.update-card {
    background: var(--card); border-radius: 16px; padding: 20px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
    display: flex; align-items: center; gap: 20px; transition: 0.3s;
    position: relative; overflow: hidden;
}
.update-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(79, 70, 229, 0.15); }

/* Status Strip */
.update-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; }
.type-new { border-left: 5px solid var(--success); }
.type-removed { border-left: 5px solid var(--danger); opacity: 0.8; }
.type-enabled { border-left: 5px solid var(--primary); }

.icon-box {
    width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
}
.icon-new { background: #dcfce7; color: var(--success); }
.icon-removed { background: #fee2e2; color: var(--danger); }
.icon-enabled { background: #e0e7ff; color: var(--primary); }

.content { flex: 1; }
.cat-name { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.svc-name { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 3px 0; line-height: 1.4; }
.time-ago { font-size: 0.75rem; color: #64748b; display: flex; align-items: center; gap: 5px; margin-top: 5px; }

.price-badge {
    background: #f1f5f9; padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; color: var(--primary); white-space: nowrap;
}

.empty-state { text-align: center; padding: 60px; color: #94a3b8; }

@media (max-width: 600px) {
    .update-card { flex-direction: column; align-items: flex-start; gap: 15px; }
    .icon-box { width: 40px; height: 40px; font-size: 20px; }
    .price-badge { align-self: flex-start; }
}
</style>

<div class="updates-container">
    <div class="page-head">
        <h1 class="page-title">Service Updates</h1>
        <p class="page-sub">Track new additions and removed services (Auto-cleared after 3 days).</p>
    </div>

    <?php if (empty($updates)): ?>
        <div class="empty-state">
            <div style="font-size:50px; margin-bottom:10px;">💤</div>
            <h3>No Recent Updates</h3>
            <p>Everything is stable. Check back later!</p>
        </div>
    <?php else: ?>
        <?php foreach($updates as $u): 
            $rate = (float)$u['rate'];
            if($curr_code != 'PKR') $rate *= $curr_rate;
            
            $typeClass = 'type-new'; $iconClass = 'icon-new'; $icon = '✨'; $label = 'New Service';
            
            if($u['type'] == 'removed') {
                $typeClass = 'type-removed'; $iconClass = 'icon-removed'; $icon = '🗑️'; $label = 'Service Removed';
            }
            if($u['type'] == 'enabled') {
                $typeClass = 'type-enabled'; $iconClass = 'icon-enabled'; $icon = '🔄'; $label = 'Restocked / Enabled';
            }
        ?>
        <div class="update-card <?= $typeClass ?>">
            <div class="icon-box <?= $iconClass ?>"><?= $icon ?></div>
            <div class="content">
                <div class="cat-name"><?= $label ?> &bull; <?= sanitize($u['category_name']) ?></div>
                <div class="svc-name"><?= sanitize($u['service_name']) ?></div>
                <div class="time-ago">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= time_elapsed_string($u['created_at']) ?>
                </div>
            </div>
            <div class="price-badge">
                <?= $curr_symbol ?> <?= number_format($rate, 2) ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php 
// FIX 2: Updated Function to handle 'w' correctly
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $diff->d -= $weeks * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        // Determine value based on key
        if ($k === 'w') {
            $value = $weeks;
        } else {
            $value = $diff->$k;
        }
        
        if ($value) {
            $v = $value . ' ' . $v . ($value > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
include '_smm_footer.php'; 
?>