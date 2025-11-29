<?php
include '_smm_header.php'; 

$user_id = (int)$_SESSION['user_id'];

// --- 1. Currency Setup ---
$curr_code = $_COOKIE['site_currency'] ?? 'PKR';
$curr_rate = 1; 
$curr_symbol = 'Rs';

if ($curr_code != 'PKR') {
    $curr_rate = getCurrencyRate($curr_code);
    $symbols = ['PKR'=>'Rs','USD'=>'$','INR'=>'₹','EUR'=>'€','GBP'=>'£','SAR'=>'﷼','AED'=>'د.إ'];
    $curr_symbol = $symbols[$curr_code] ?? $curr_code;
}

// --- 2. Fetch Active Services ---
try {
    $stmt = $db->query("
        SELECT * FROM smm_services 
        WHERE is_active = 1 AND manually_deleted = 0 
        ORDER BY category ASC, service_rate ASC
    ");
    $services = $stmt->fetchAll();
    
    $grouped = [];
    foreach ($services as $s) {
        $grouped[$s['category']][] = $s;
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* --- 🎨 THEME STYLES --- */
:root {
    --primary: #4F46E5;
    --bg-body: #F8FAFC;
    --card-bg: #FFFFFF;
    --text-main: #1E293B;
    --text-sub: #64748B;
    --border: #E2E8F0;
    --radius: 12px;
}

body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; color: var(--text-main); }

/* --- HEADER --- */
.page-header {
    background: #fff; padding: 30px; border-radius: var(--radius); margin-bottom: 30px;
    text-align: center; border: 1px solid var(--border);
    box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05);
}
.page-title { font-size: 2rem; font-weight: 800; margin: 0; color: var(--primary); }
.page-subtitle { color: var(--text-sub); font-size: 0.9rem; margin-top: 5px; }

/* --- SEARCH --- */
.search-container { max-width: 600px; margin: 0 auto 40px auto; position: relative; }
.search-input {
    width: 100%; padding: 15px 25px 15px 50px; border: 2px solid transparent;
    border-radius: 50px; background: #fff; font-size: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; color: var(--text-main);
}
.search-input:focus { border-color: var(--primary); outline: none; }
.search-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--primary); }

/* --- CATEGORY CARD --- */
.cat-card {
    background: #fff; border-radius: var(--radius); margin-bottom: 25px;
    overflow: hidden; border: 1px solid var(--border); box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}
.cat-header {
    padding: 15px 25px; background: #f8fafc; border-bottom: 1px solid var(--border);
    font-weight: 700; font-size: 1rem; color: var(--text-main);
    display: flex; justify-content: space-between; align-items: center;
}
.cat-badge { background: #e0e7ff; color: var(--primary); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; }

/* --- TABLE --- */
.table-responsive { overflow-x: auto; }
.svc-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.svc-table th {
    text-align: left; padding: 12px 20px; color: var(--text-sub);
    font-weight: 600; font-size: 0.75rem; text-transform: uppercase;
    background: #fff; border-bottom: 1px solid var(--border);
}
.svc-table td {
    padding: 15px 20px; border-bottom: 1px solid #f1f5f9;
    color: var(--text-main); vertical-align: middle;
}
.svc-table tr:last-child td { border-bottom: none; }

/* --- BADGES & BUTTONS --- */
.svc-name { font-weight: 600; color: #334155; font-size: 0.95rem; margin-bottom: 6px; display: block; }

.meta-tags { display: flex; gap: 6px; flex-wrap: wrap; }
.badge { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.bg-time { background: #f1f5f9; color: var(--text-sub); border: 1px solid #e2e8f0; }
.bg-refill { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.bg-cancel { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.price-tag {
    font-weight: 700; color: var(--primary); background: #eff6ff;
    padding: 6px 12px; border-radius: 50px; font-size: 0.85rem;
}

.btn-desc {
    background: #fff; border: 1px solid #e2e8f0; color: var(--text-sub);
    padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;
    transition: 0.2s; display: flex; align-items: center; gap: 5px;
}
.btn-desc:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

.id-pill {
    background: #f8fafc; color: var(--text-sub); padding: 4px 8px; border-radius: 6px;
    font-family: monospace; border: 1px solid #e2e8f0; font-size: 0.8rem;
}

/* --- MODAL POPUP --- */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;
    backdrop-filter: blur(4px);
}
.modal-overlay.active { display: flex; animation: fadeIn 0.2s ease-out; }

.modal-content {
    background: #fff; width: 90%; max-width: 500px; border-radius: 16px; padding: 25px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.2); position: relative;
    animation: zoomIn 0.3s ease-out;
}
.modal-close {
    position: absolute; top: 15px; right: 15px; background: #f1f5f9; border: none;
    width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 1.2rem; color: #64748b;
}
.modal-close:hover { background: #fee2e2; color: #ef4444; }

.modal-title { margin: 0 0 15px 0; font-size: 1.2rem; font-weight: 700; color: var(--text-main); }
.desc-text {
    font-size: 0.9rem; line-height: 1.6; color: var(--text-sub);
    background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;
    max-height: 300px; overflow-y: auto;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* Mobile */
@media (max-width: 768px) {
    .svc-table th, .svc-table td { padding: 12px 10px; }
    .meta-tags { gap: 4px; }
    .badge { font-size: 0.65rem; padding: 2px 6px; }
}
</style>

<div class="container" style="max-width:1100px; margin:0 auto; padding:20px;">

    <div class="page-header">
        <h1 class="page-title">Services List</h1>
        <p class="page-subtitle">Browse our complete list of high-quality services.</p>
    </div>

    <div class="search-container">
        <span class="search-icon">🔍</span>
        <input type="text" id="search" class="search-input" placeholder="Search services (e.g. Instagram, Likes)...">
    </div>

    <div id="services-wrapper">
        <?php if (empty($grouped)): ?>
            <div style="text-align:center; padding:40px; color:#999;">
                <h3>No Services Available</h3>
            </div>
        <?php else: ?>
            
            <?php foreach ($grouped as $catName => $list): ?>
                <div class="cat-card" data-name="<?= strtolower(sanitize($catName)) ?>">
                    <div class="cat-header">
                        <span><?= sanitize($catName) ?></span>
                        <span class="cat-badge"><?= count($list) ?> Services</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="svc-table">
                            <thead>
                                <tr>
                                    <th width="60">ID</th>
                                    <th>Service</th>
                                    <th width="100">Rate / 1k</th>
                                    <th width="100">Min / Max</th>
                                    <th width="100">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list as $s): 
                                    $rate = (float)$s['service_rate'];
                                    if ($curr_code != 'PKR') $rate *= $curr_rate;
                                ?>
                                <tr class="service-row" data-name="<?= strtolower(sanitize($s['name'])) ?>">
                                    <td>
                                        <span class="id-pill"><?= $s['id'] ?></span>
                                    </td>
                                    <td>
                                        <span class="svc-name"><?= sanitize($s['name']) ?></span>
                                        
                                        <div class="meta-tags">
                                            <span class="badge bg-time">⏱ <?= formatSmmAvgTime($s['avg_time']) ?></span>
                                            <?php if($s['has_refill']): ?><span class="badge bg-refill">♻️ Refill</span><?php endif; ?>
                                            <?php if($s['has_cancel']): ?><span class="badge bg-cancel">🚫 Cancel</span><?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="price-tag"><?= $curr_symbol . ' ' . number_format($rate, 2) ?></span>
                                    </td>
                                    <td>
                                        <span class="limit-tag"><?= $s['min'] ?> - <?= $s['max'] ?></span>
                                    </td>
                                    <td>
                                        <button class="btn-desc" onclick="showDesc(this)" data-title="<?= sanitize($s['name']) ?>">
                                            📄 View
                                            <div style="display:none"><?= nl2br(sanitize($s['description'] ?? 'No description.')) ?></div>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

</div>

<div class="modal-overlay" id="descModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h3 class="modal-title" id="modalTitle">Service Name</h3>
        <div class="desc-text" id="modalText"></div>
    </div>
</div>

<script>
// Search
document.getElementById('search').addEventListener('input', function(e) {
    const val = e.target.value.toLowerCase();
    document.querySelectorAll('.cat-card').forEach(card => {
        const catName = card.getAttribute('data-name');
        let hasVisible = false;
        card.querySelectorAll('.service-row').forEach(row => {
            const svcName = row.getAttribute('data-name');
            if (svcName.includes(val) || catName.includes(val) || val === '') {
                row.style.display = '';
                hasVisible = true;
            } else { row.style.display = 'none'; }
        });
        card.style.display = hasVisible ? 'block' : 'none';
    });
});

// Modal Logic
function showDesc(btn) {
    const title = btn.getAttribute('data-title');
    const desc = btn.querySelector('div').innerHTML;
    
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalText').innerHTML = desc;
    document.getElementById('descModal').classList.add('active');
}

function closeModal() {
    document.getElementById('descModal').classList.remove('active');
}

// Close on outside click
document.getElementById('descModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});
</script>

<?php include '_smm_footer.php'; ?>