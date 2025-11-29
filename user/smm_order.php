<?php
include '_smm_header.php'; 
$user_id = (int)$_SESSION['user_id'];

// --- 1. CURRENCY & SETTINGS ---
$curr_code = $_COOKIE['site_currency'] ?? 'PKR';
$curr_rate = 1; $curr_sym = 'Rs';

if ($curr_code != 'PKR') {
    $curr_rate = getCurrencyRate($curr_code);
    $symbols = ['PKR'=>'Rs','USD'=>'$','INR'=>'₹','EUR'=>'€','GBP'=>'£','SAR'=>'﷼','AED'=>'د.إ'];
    $curr_symbol = $symbols[$curr_code] ?? $curr_code;
} else {
    $curr_symbol = 'Rs'; // Default for PKR
}

$site_logo = $GLOBALS['settings']['site_logo'] ?? '';
$site_name = $GLOBALS['settings']['site_name'] ?? 'SubHub';
$admin_wa = $GLOBALS['settings']['whatsapp_number'] ?? '';

// --- 2. DATA FETCHING ---
try {
    $stmt = $db->query("
        SELECT s.*, f.id as is_favorite
        FROM smm_services s
        LEFT JOIN user_favorite_services f ON s.id = f.service_id AND f.user_id = $user_id
        WHERE s.is_active = 1
        ORDER BY s.category ASC, s.name ASC
    ");
    $all_services = $stmt->fetchAll();
    
    $grouped_apps = [];
    $services_json = [];
    
    $known_apps = [
        'Instagram', 'TikTok', 'Youtube', 'Facebook', 'Twitter', 'X', 
        'Spotify', 'Telegram', 'Whatsapp', 'LinkedIn', 'Snapchat', 
        'Pinterest', 'Twitch', 'Discord', 'Threads', 'Netflix', 'Pubg',
        'SoundCloud', 'Website Traffic', 'Google', 'Likee'
    ];

    foreach ($all_services as $s) {
        $full_cat = trim($s['category']);
        $app_name = 'Others'; 
        $found = false;

        foreach ($known_apps as $kApp) {
            if (stripos($full_cat, $kApp) !== false) {
                $app_name = $kApp;
                if(trim($kApp) == 'X ') $app_name = 'Twitter';
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $parts = explode(' - ', $full_cat);
            $app_name = (count($parts) > 1) ? trim($parts[0]) : $full_cat;
        }
        
        $grouped_apps[$app_name][$full_cat][] = $s;

        $is_comment = (stripos($s['name'], 'Comment') !== false || stripos($s['category'], 'Comment') !== false);
        
        $services_json[$s['id']] = [
            'rate' => (float)$s['service_rate'],
            'min' => (int)$s['min'],
            'max' => (int)$s['max'],
            'avg' => formatSmmAvgTime($s['avg_time']),
            'refill' => (bool)$s['has_refill'],
            'cancel' => (bool)$s['has_cancel'],
            'name' => sanitize($s['name']),
            'category' => sanitize($full_cat), 
            'desc' => nl2br($s['description'] ?? 'No details available.'),
            'is_comment' => $is_comment
        ];
    }
    ksort($grouped_apps);

} catch (Exception $e) { $error = $e->getMessage(); }

// Logo URL for JS
$logo_url = !empty($site_logo) ? "../assets/img/$site_logo" : "";
?>

<script>
    window.currConfig = { code: "<?=$curr_code?>", rate: <?=$curr_rate?>, sym: "<?=$curr_symbol?>" };
    window.svcData = <?= json_encode($services_json) ?>;
    window.siteData = { logo: "<?=$logo_url?>", name: "<?=$site_name?>", wa: "<?=$admin_wa?>" };
</script>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
/* --- 🎨 MAIN THEME --- */
:root {
    --primary: #4f46e5;        
    --bg-body: #f8fafc;        
    --card-bg: #ffffff;        
    --text-main: #0f172a;      
    --text-sub: #64748b;      
    --border: #e2e8f0;        
    --radius: 16px;
}

body {
    background-color: var(--bg-body);
    font-family: 'Outfit', sans-serif;
    color: var(--text-main);
    font-size: 15px;
}

/* --- GRID & CARDS --- */
.platform-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; margin-bottom: 30px; }

.platform-card {
    background: var(--card-bg); padding: 20px; border-radius: var(--radius);
    border: 1px solid var(--border); text-align: center; cursor: pointer; transition: 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}
.platform-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15); }
.platform-icon { width: 50px; height: 50px; object-fit: contain; margin-bottom: 10px; }
.platform-title { font-weight: 700; font-size: 0.9rem; display: block; }

/* --- SERVICE LIST --- */
.app-container { display: none; animation: fadeIn 0.3s ease-out; }
.back-btn {
    background: #fff; border: 1px solid var(--border); padding: 8px 20px;
    border-radius: 50px; cursor: pointer; margin-bottom: 20px; font-weight: 700; color: var(--text-sub);
}
.back-btn:hover { border-color: var(--primary); color: var(--primary); }

.cat-group { margin-bottom: 20px; background: #fff; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.cat-header {
    padding: 18px 25px; cursor: pointer; font-weight: 700; background: #f8fafc;
    display: flex; justify-content: space-between; align-items: center;
}
.svc-list { display: none; border-top: 1px solid var(--border); }

.service-item {
    padding: 20px 25px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.2s;
}
.service-item:last-child { border-bottom: none; }
.service-item:hover { background: #fcfaff; padding-left: 30px; border-left: 3px solid var(--primary); }

.service-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.service-name { font-weight: 700; font-size: 0.95rem; color: var(--text-main); line-height: 1.4; flex: 1; padding-right: 15px; }
.service-price { background: #eff6ff; color: var(--primary); padding: 5px 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 800; white-space: nowrap; }

.service-meta { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.tag { padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.tag-time { background: #f1f5f9; color: var(--text-sub); }
.tag-refill { background: #dcfce7; color: #166534; }
.tag-cancel { background: #fee2e2; color: #991b1b; }

.btn-receipt {
    background: #fff; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 6px;
    cursor: pointer; font-size: 0.75rem; font-weight: 700; color: var(--text-sub);
    display: flex; align-items: center; gap: 5px; margin-left: auto;
}
.btn-receipt:hover { border-color: var(--primary); color: var(--primary); }

/* --- SEARCH --- */
.search-wrap { position: relative; margin-bottom: 30px; }
.search-input {
    width: 100%; padding: 16px 20px 16px 50px; border: 1px solid var(--border);
    border-radius: 50px; background: #fff; font-size: 1rem; outline: none;
    box-shadow: var(--shadow);
}
.search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
.search-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-sub); font-size: 1.2rem; }

/* --- MODAL --- */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 99999;
    justify-content: center; align-items: center; padding: 20px;
}
.modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }

.modal-content {
    background: #fff; width: 100%; max-width: 500px; border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column;
    max-height: 85vh; animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-header {
    padding: 20px; background: #fff; border-bottom: 1px solid #f1f5f9;
    display: flex; justify-content: space-between; align-items: center;
}
.modal-header h3 { margin: 0; font-size: 1.3rem; color: var(--text-main); font-weight: 800; }
.modal-close { background: #f8fafc; border: none; font-size: 1.2rem; cursor: pointer; color: #999; width: 35px; height: 35px; border-radius: 50%; }
.modal-close:hover { background: #fee2e2; color: #ef4444; }

.modal-body { padding: 25px; overflow-y: auto; }

.stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.stat-box { background: #f8fafc; border: 1px solid #eee; padding: 10px; border-radius: 10px; text-align: center; }
.stat-box small { display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-sub); text-transform: uppercase; margin-bottom: 3px; }
.stat-box b { font-size: 0.9rem; color: var(--text-main); }

/* --- Description Box (Auto Scroll) --- */
.desc-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
    font-size: 0.85rem; color: var(--text-sub); margin-bottom: 20px;
    height: 120px; overflow: hidden; position: relative; line-height: 1.5;
}
.desc-scroll-inner {
    padding: 15px; position: absolute; width: 100%;
    animation: scrollText 20s linear infinite; 
}
.desc-box:hover .desc-scroll-inner { animation-play-state: paused; }
@keyframes scrollText {
    0% { transform: translateY(0); }
    15% { transform: translateY(0); }
    100% { transform: translateY(-100%); }
}

/* --- Dynamic Hints --- */
.dynamic-hint {
    font-size: 0.8rem; color: var(--text-sub); margin-top: 8px;
    text-align: right; line-height: 1.4; display: flex; flex-direction: column; align-items: flex-end;
}
.hint-time { display: flex; align-items: center; gap: 4px; font-weight: 600; color: #64748b; }
.hint-promo { color: var(--primary); font-weight: 700; margin-top: 2px; }

/* --- Forms & Paste Icon --- */
.form-group { margin-bottom: 15px; }
.input-wrap { position: relative; }
.form-input { width: 100%; padding: 14px; border: 2px solid #f1f5f9; border-radius: 12px; font-size: 1rem; outline: none; transition: 0.3s; }
.form-input:focus { border-color: var(--primary); }

.paste-btn {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    cursor: pointer; opacity: 0.6; transition: 0.2s; font-size: 1.2rem;
    background: transparent; border: none; padding: 0;
}
.paste-btn:hover { opacity: 1; transform: translateY(-50%) scale(1.1); }

.btn-submit {
    width: 100%; padding: 15px; background: var(--primary); color: #fff; font-weight: 800;
    border: none; border-radius: 12px; cursor: pointer; font-size: 1rem; margin-top: 10px;
    box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25);
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(79, 70, 229, 0.35); }

/* =================================================
   🔥 NEW "MIDNIGHT PREMIUM" RECEIPT DESIGN 🔥
=================================================
*/
#receipt-node {
    position: fixed; left: -9999px; top: 0;
    width: 480px; /* Wider for better text fit */
    background: #0f172a; /* Dark background */
    background: linear-gradient(180deg, #1e1e2e 0%, #0f0f15 100%);
    color: #fff;
    font-family: 'Outfit', sans-serif;
    border-radius: 0;
    overflow: hidden;
    box-sizing: border-box;
}

/* 1. HEADER (Dark & Clean) */
.rec-header {
    padding: 30px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
/* White pill for logo to handle transparency issues */
.rec-logo-box {
    background: #fff; padding: 8px 15px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.rec-logo { height: 40px; object-fit: contain; display: block; }
.rec-site-name { font-size: 24px; font-weight: 800; color: #fff; letter-spacing: 0.5px; }

/* 2. BODY */
.rec-body { padding: 30px; }

/* Category Tag */
.rec-cat-tag {
    display: inline-block;
    background: rgba(79, 70, 229, 0.2); 
    color: #818cf8; border: 1px solid rgba(79, 70, 229, 0.4);
    padding: 6px 14px; border-radius: 50px;
    font-size: 12px; font-weight: 700; letter-spacing: 1px;
    text-transform: uppercase; margin-bottom: 15px;
}

/* Service Name (BIG TEXT) */
.rec-svc-name {
    font-size: 24px; /* Much bigger */
    font-weight: 700;
    line-height: 1.4;
    color: #fff;
    margin-bottom: 25px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
}

/* 3. STATS ROW (Modern) */
.rec-stats-row {
    display: flex; gap: 10px; margin-bottom: 25px;
}
.rec-stat-pill {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 12px 5px;
    text-align: center;
}
.rec-stat-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; }
.rec-stat-val { font-size: 14px; font-weight: 700; color: #fff; }

/* 4. PRICE (Highlight) */
.rec-price-row {
    background: rgba(16, 185, 129, 0.1); /* Green Tint */
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 16px;
    padding: 15px 25px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 25px;
}
.rec-p-lbl { font-size: 12px; color: #6ee7b7; font-weight: 700; text-transform: uppercase; }
.rec-p-val { font-size: 28px; font-weight: 800; color: #34d399; text-shadow: 0 0 20px rgba(52, 211, 153, 0.4); }

/* 5. DESCRIPTION */
.rec-desc-wrap {
    font-size: 14px; /* Increased Size */
    color: #cbd5e1;
    line-height: 1.6;
    background: rgba(0,0,0,0.2);
    padding: 20px;
    border-radius: 12px;
    border-left: 3px solid #6366f1;
}

/* 6. FOOTER */
.rec-footer {
    padding: 20px 30px;
    background: #000;
    display: flex; justify-content: space-between; align-items: center;
}
.rec-btn {
    background: #fff; color: #000; font-weight: 800; font-size: 12px;
    padding: 8px 16px; border-radius: 50px; text-transform: uppercase;
}
.rec-wa { color: #fff; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 8px; }

@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes zoomIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
</style>

<div class="container">
    
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="search" class="search-input" placeholder="Search services (e.g. Instagram, Likes)...">
    </div>

    <div id="platform-grid" class="platform-grid">
        <?php if(empty($grouped_apps)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:#999;">No services found.</div>
        <?php else: ?>
            <?php foreach($grouped_apps as $appName => $subCats): 
                $iconPath = "../assets/img/icons/" . $appName . ".png";
            ?>
            <div class="platform-card" onclick="openApp('<?= md5($appName) ?>')">
                <img src="<?= $iconPath ?>" class="platform-icon" onerror="this.style.display='none'">
                <div class="platform-placeholder" style="display:none"><?= strtoupper(substr($appName,0,1)) ?></div>
                <span class="platform-title"><?= sanitize($appName) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="apps-container">
        <?php foreach($grouped_apps as $appName => $subCats): ?>
        <div id="app-<?= md5($appName) ?>" class="app-container">
            <button class="back-btn" onclick="closeApp()">❮ Back</button>
            <h3 style="margin-bottom:20px;font-weight:800;color:var(--text-main);"><?= sanitize($appName) ?></h3>
            
            <?php foreach($subCats as $catName => $services): ?>
            <div class="cat-group">
                <div class="cat-header" onclick="toggleCat(this)">
                    <span><?= sanitize($catName) ?></span><span>▼</span>
                </div>
                <div class="svc-list">
                    <?php foreach($services as $s): 
                        $rate = (float)$s['service_rate'];
                        if($curr_code != 'PKR') $rate *= $curr_rate;
                    ?>
                    <div class="service-item" onclick="openModal(<?= $s['id'] ?>)">
                        <div class="service-top">
                            <span class="service-name"><?= sanitize($s['name']) ?></span>
                            <span class="service-price"><?= $curr_symbol . ' ' . number_format($rate, 2) . ' ' . $curr_code ?></span>
                        </div>
                        <div class="service-meta">
                            <span class="tag tag-time">⏱ <?= formatSmmAvgTime($s['avg_time']) ?></span>
                            <?php if($s['has_refill']): ?><span class="tag tag-refill">Refill</span><?php endif; ?>
                            <?php if($s['has_cancel']): ?><span class="tag tag-cancel">Cancel</span><?php endif; ?>
                            
                            <button class="btn-receipt" onclick="event.stopPropagation(); genReceipt(<?= $s['id'] ?>)">
                                📄 Info
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<div class="modal-overlay" id="order-modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Place Order</h3><button class="modal-close" onclick="closeModal()">✕</button></div>
        <div class="modal-body">
            <div id="m-stats" class="stats-grid"></div>
            <div id="m-desc" class="desc-box" style="display:none;"></div>

            <form action="smm_order_action.php" method="POST">
                <input type="hidden" name="service_id" id="m-id">
                
                <div class="form-group">
                    <label>Link</label>
                    <div class="input-wrap">
                        <input type="text" name="link" id="m-link" class="form-input" style="padding-right:40px;" placeholder="https://..." required>
                        <span class="paste-btn" onclick="pasteLink()" title="Paste Link">📋</span>
                    </div>
                </div>
                
                <div id="grp-qty" class="form-group">
                    <label>Quantity <span id="min-max" style="float:right;font-size:0.8rem;color:var(--primary)"></span></label>
                    <input type="number" name="quantity" id="m-qty" class="form-input" placeholder="1000" required>
                </div>

                <div id="grp-com" class="form-group" style="display:none">
                    <label>Comments</label>
                    <textarea name="comments" id="m-com" class="form-input" placeholder="One per line"></textarea>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-weight:700;">
                    <span>Total Charge</span><span id="m-total" style="color:var(--primary);font-size:1.2rem;">0.00</span>
                </div>
                <div id="m-hint" class="dynamic-hint"></div>
                
                <button class="btn-submit">Confirm Order</button>
            </form>
        </div>
    </div>
</div>

<div id="receipt-node">
    <div class="rec-header">
        <div class="rec-logo-box">
            <img id="rec-logo" src="" class="rec-logo" style="display:none;">
            <div id="rec-site" class="rec-site-name" style="color:#000;display:none;"><?= sanitize($GLOBALS['settings']['site_name'] ?? 'SubHub') ?></div>
        </div>
        <div style="text-align:right">
            <div style="font-size:10px;color:#94a3b8;font-weight:700;text-transform:uppercase;">Verified Service</div>
            <div style="color:#22c55e;font-size:16px;">★ ★ ★ ★ ★</div>
        </div>
    </div>
    
    <div class="rec-body">
        <div class="rec-cat-tag" id="r-cat">Category</div>
        <div class="rec-svc-name" id="r-name">Service Name Goes Here</div>

        <div class="rec-price-row">
            <div class="rec-p-lbl">Rate per 1000</div>
            <div class="rec-p-val" id="r-price">Rs 0.00</div>
        </div>

        <div class="rec-stats-row">
            <div class="rec-stat-pill">
                <div class="rec-stat-lbl">Avg Time</div>
                <div class="rec-stat-val" id="r-time">-</div>
            </div>
            <div class="rec-stat-pill">
                <div class="rec-stat-lbl">Refill</div>
                <div class="rec-stat-val" id="r-refill">-</div>
            </div>
            <div class="rec-stat-pill">
                <div class="rec-stat-lbl">Cancel</div>
                <div class="rec-stat-val" id="r-cancel">-</div>
            </div>
        </div>

        <div class="rec-desc-wrap" id="r-desc">
            Description text goes here...
        </div>
    </div>

    <div class="rec-footer">
        <div class="rec-btn">Order Now</div>
        <div class="rec-wa">
            <img src="../assets/img/icons/Whatsapp.png" style="width:18px;height:18px;">
            <span><?= $GLOBALS['settings']['whatsapp_number'] ?? '' ?></span>
        </div>
    </div>
</div>

<script>
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);

// UI
function openApp(id) { $('#platform-grid').style.display='none'; $$('.app-container').forEach(x=>x.style.display='none'); $('#app-'+id).style.display='block'; }
function closeApp() { $$('.app-container').forEach(x=>x.style.display='none'); $('#platform-grid').style.display='grid'; }
function toggleCat(el) { const l=el.nextElementSibling; l.style.display=l.style.display==='block'?'none':'block'; }

// Paste Link
async function pasteLink() {
    try { const text = await navigator.clipboard.readText(); $('#m-link').value = text; } 
    catch (err) { alert('Paste manually.'); }
}

// Helper: Time
function calcFinishTime(avgStr) {
    if(!avgStr || avgStr.toLowerCase().includes('instant')) return "Shortly";
    let now = new Date(); let mins = 0;
    let d = avgStr.match(/(\d+)\s*(day|days|d)/i);
    let h = avgStr.match(/(\d+)\s*(hour|hours|hr|hrs|h)/i);
    let m = avgStr.match(/(\d+)\s*(min|mins|minute|minutes|m)/i);
    if(d) mins += parseInt(d[1]) * 1440; 
    if(h) mins += parseInt(h[1]) * 60;
    if(m) mins += parseInt(m[1]);
    if(mins === 0) return null;
    now.setMinutes(now.getMinutes() + mins);
    if(mins > 1440) return now.toLocaleDateString() + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    return now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Modal
let currSvc = null;
function openModal(id) {
    let s = window.svcData[id]; if(!s) return;
    currSvc = s;
    $('#m-id').value=id; 
    $('#min-max').innerText = `Limit: ${s.min} - ${s.max}`;
    
    $('#m-desc').innerHTML = '<div class="desc-scroll-inner">' + s.desc + '</div>';
    $('#m-desc').style.display = 'block';

    let rC=s.refill?'#16a34a':'#dc2626', cC=s.cancel?'#16a34a':'#dc2626';
    $('#m-stats').innerHTML = `<div class="stat-box"><small>Time</small><b>${s.avg}</b></div><div class="stat-box" style="border-color:${rC}"><small>Refill</small><b style="color:${rC}">${s.refill?'YES':'NO'}</b></div><div class="stat-box" style="border-color:${cC}"><small>Cancel</small><b style="color:${cC}">${s.cancel?'YES':'NO'}</b></div>`;

    $('#m-qty').value=''; $('#m-com').value=''; $('#m-link').value='';
    $('#m-total').innerText = window.currConfig.sym + ' 0.00 ' + window.currConfig.code;
    $('#m-hint').innerHTML = '';

    if(s.is_comment) { $('#grp-qty').style.display='none'; $('#grp-com').style.display='block'; $('#m-qty').readOnly=true; }
    else { $('#grp-qty').style.display='block'; $('#grp-com').style.display='none'; $('#m-qty').readOnly=false; }

    $('.modal-overlay').classList.add('active');
    updatePrice(0);
}
function closeModal() { $('.modal-overlay').classList.remove('active'); }

// Price
function updatePrice(qty) {
    if(!currSvc) return;
    let p = (qty/1000)*currSvc.rate;
    if(window.currConfig.code!=='PKR') p*=window.currConfig.rate;
    
    $('#m-total').innerText = window.currConfig.sym + ' ' + p.toFixed(2) + ' ' + window.currConfig.code;

    let hints = '';
    let finishTime = calcFinishTime(currSvc.avg);
    if(finishTime) hints += `<span class="hint-time">🏁 Est. Completion: ~${finishTime}</span>`;
    if(qty > 0) {
        if(qty >= (currSvc.max * 0.5)) hints += `<span class="hint-promo">🔥 High Volume - Priority Order!</span>`;
        else if(qty >= 1000) hints += `<span class="hint-promo" style="color:#16a34a">🚀 Great Choice! Boosting Speed.</span>`;
    }
    $('#m-hint').innerHTML = hints;
}

$('#m-qty').addEventListener('input', function(){ updatePrice(parseInt(this.value)||0) });
$('#m-com').addEventListener('input', function(){ let c=this.value.split('\n').filter(x=>x.trim()!=='').length; $('#m-qty').value=c; updatePrice(c); });

// Search
$('#search').addEventListener('input', function(e){
    let q=e.target.value.toLowerCase();
    $$('.service-item').forEach(i=>{ i.style.display=i.dataset.name.includes(q)?'block':'none'; });
    if(q.length>0){ $('#platform-grid').style.display='none'; $$('.app-container,.svc-list').forEach(x=>x.style.display='block'); $$('.back-btn').forEach(x=>x.style.display='none'); }
    else { closeApp(); $$('.back-btn').forEach(x=>x.style.display='flex'); $$('.svc-list').forEach(x=>x.style.display='none'); }
});

// Receipt (UPDATED FOR DARK THEME)
window.genReceipt = function(id) {
    let s = window.svcData[id];
    
    // Logo Handling: Put inside white box
    if(window.siteData.logo) { 
        $('#rec-logo').src=window.siteData.logo; 
        $('#rec-logo').style.display='block'; 
        $('#rec-site').style.display='none'; 
    } else { 
        $('#rec-site').innerText=window.siteData.name; 
        $('#rec-logo').style.display='none'; 
        $('#rec-site').style.display='block'; 
    }

    // Data Fill
    $('#r-name').innerText=s.name; 
    $('#r-cat').innerText=s.category; 
    
    let p = s.rate; if(window.currConfig.code!=='PKR') p*=window.currConfig.rate;
    $('#r-price').innerText = window.currConfig.sym + ' ' + p.toFixed(2) + ' ' + window.currConfig.code;
    
    $('#r-time').innerText=s.avg; 
    $('#r-refill').innerText=s.refill ? 'YES' : 'NO';
    $('#r-cancel').innerText=s.cancel ? 'YES' : 'NO';
    
    // Clean Description 
    let cleanDesc = s.desc.replace(/<[^>]*>/g, '').substring(0, 180) + (s.desc.length>180?'...':'');
    $('#r-desc').innerText = cleanDesc;

    // High Quality Export with Dark Background
    html2canvas($('#receipt-node'), { 
        scale: 3, 
        useCORS: true, 
        allowTaint: true, 
        backgroundColor: '#1e1e2e' 
    }).then(c => {
        let a = document.createElement('a'); a.download = 'Service-Info-' + id + '.png'; a.href = c.toDataURL('image/png'); a.click();
    });
}
</script>

<?php include '_smm_footer.php'; ?>