<?php
include '_header.php';

$success = '';
$error = '';

// --- 1. AUTO SYNC (LOGIC UNCHANGED) ---
if (isset($_GET['action']) && $_GET['action'] == 'sync_defaults') {
    try {
        $defaults = [
            ['label'=>'Dashboard', 'link'=>'index.php', 'icon'=>'fas fa-home', 'placement'=>'main', 'sort'=>1, 'icon_color'=>'#4f46e5'],
            ['label'=>'✨ New Order', 'link'=>'smm_order.php', 'icon'=>'fas fa-rocket', 'placement'=>'main', 'sort'=>2, 'icon_color'=>'#f59e0b'],
            ['label'=>'Orders', 'link'=>'sub_orders.php', 'icon'=>'fas fa-box-open', 'placement'=>'main', 'sort'=>3, 'icon_color'=>'#10b981'],
            ['label'=>'Deposit', 'link'=>'add-funds.php', 'icon'=>'fas fa-wallet', 'placement'=>'main', 'sort'=>4, 'icon_color'=>'#3b82f6'],
            ['label'=>'⚡ Services', 'link'=>'services.php', 'icon'=>'fas fa-bolt', 'placement'=>'more', 'sort'=>1, 'icon_color'=>'#eab308'],
            ['label'=>'Downloads', 'link'=>'downloads.php', 'icon'=>'fas fa-download', 'placement'=>'more', 'sort'=>2, 'icon_color'=>'#6366f1'],
            ['label'=>'🤖 AI Tools', 'link'=>'ai_tools.php', 'icon'=>'fas fa-robot', 'placement'=>'more', 'sort'=>3, 'icon_color'=>'#8b5cf6'],
            ['label'=>'💬 Support', 'link'=>'tickets.php', 'icon'=>'fas fa-headset', 'placement'=>'more', 'sort'=>4, 'icon_color'=>'#ec4899'],
            ['label'=>'💰 Earn', 'link'=>'earn.php', 'icon'=>'fas fa-sack-dollar', 'placement'=>'more', 'sort'=>5, 'icon_color'=>'#16a34a'],
            ['label'=>'🛠️ Tools', 'link'=>'tools.php', 'icon'=>'fas fa-tools', 'placement'=>'more', 'sort'=>6, 'icon_color'=>'#64748b'],
            ['label'=>'👑 The Owner', 'link'=>'about.php', 'icon'=>'fas fa-crown', 'placement'=>'more', 'sort'=>99, 'icon_color'=>'#d97706']
        ];

        $db->query("TRUNCATE TABLE navigation");
        $stmt = $db->prepare("INSERT INTO navigation (label, link, icon, placement, sort_order, icon_color, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        foreach ($defaults as $d) {
            $stmt->execute([$d['label'], $d['link'], $d['icon'], $d['placement'], $d['sort'], $d['icon_color']]);
        }
        $success = "✅ System Menus Synced Successfully!";
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// --- 2. ADD / EDIT MENU (LOGIC UNCHANGED) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_menu'])) {
    $label = sanitize($_POST['label']);
    $link = trim($_POST['link']);
    $icon = trim($_POST['icon']);
    $placement = $_POST['placement'];
    $sort = (int)$_POST['sort_order'];
    $parent_id = (int)$_POST['parent_id'];
    $icon_color = trim($_POST['icon_color']); 
    
    if (empty($label) || empty($link)) {
        $error = "Label and Link are required fields.";
    } else {
        if (!empty($_POST['edit_id'])) {
            if ($_POST['edit_id'] == $parent_id) $parent_id = 0;
            $stmt = $db->prepare("UPDATE navigation SET label=?, link=?, icon=?, placement=?, sort_order=?, parent_id=?, icon_color=? WHERE id=?");
            $stmt->execute([$label, $link, $icon, $placement, $sort, $parent_id, $icon_color, $_POST['edit_id']]);
            $success = "Menu item updated successfully!";
        } else {
            $stmt = $db->prepare("INSERT INTO navigation (label, link, icon, placement, sort_order, parent_id, icon_color) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$label, $link, $icon, $placement, $sort, $parent_id, $icon_color]);
            $success = "New menu item created successfully!";
        }
    }
}

// --- 3. DELETE MENU (LOGIC UNCHANGED) ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("UPDATE navigation SET parent_id = 0 WHERE parent_id = ?")->execute([$id]); 
    $db->prepare("DELETE FROM navigation WHERE id = ?")->execute([$id]);
    echo "<script>window.location='menus.php';</script>";
}

// FETCH DATA
$all_menus = $db->query("SELECT * FROM navigation ORDER BY placement ASC, sort_order ASC")->fetchAll();
$parents = [];
foreach($all_menus as $m) { if($m['parent_id'] == 0) $parents[] = $m; }
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-color: #4f46e5;
        --secondary-bg: #f3f4f6;
        --card-bg: #ffffff;
        --text-dark: #1f2937;
        --text-muted: #6b7280;
    }
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f9fafb;
    }
    
    /* Animations */
    .fade-in { animation: fadeIn 0.4s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* Card Styling */
    .modern-card {
        background: var(--card-bg);
        border-radius: 20px;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.02);
        overflow: hidden;
    }

    /* Table Styling */
    .custom-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #e2e8f0;
        padding: 16px 24px;
    }
    .custom-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
    }
    .custom-table tbody tr:last-child { border-bottom: none; }
    .custom-table tbody tr:hover {
        background-color: #f8fafc;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        z-index: 10;
        position: relative;
    }
    .custom-table td {
        padding: 18px 24px;
        vertical-align: middle;
        color: var(--text-dark);
        font-size: 0.95rem;
    }

    /* Icon Box */
    .icon-wrapper {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        transition: transform 0.2s;
    }
    .icon-wrapper:hover { transform: scale(1.1) rotate(5deg); }

    /* Tree View Lines */
    .child-indicator {
        display: inline-flex;
        align-items: center;
        margin-right: 12px;
        color: #cbd5e1;
    }
    .child-line {
        width: 20px;
        height: 25px;
        border-bottom: 2px solid #e2e8f0;
        border-left: 2px solid #e2e8f0;
        border-bottom-left-radius: 10px;
        margin-right: 8px;
        transform: translateY(-8px);
    }

    /* Buttons & Badges */
    .btn-gradient {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        transition: all 0.3s ease;
    }
    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
        color: white;
    }
    .badge-soft {
        padding: 6px 12px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .badge-main { background: #dcfce7; color: #166534; }
    .badge-drop { background: #e0e7ff; color: #3730a3; }
    
    /* Action Buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        background: white;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    .action-btn:hover { background: #f1f5f9; color: var(--primary-color); }
    .action-btn.delete:hover { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

    /* Inputs */
    .form-control, .form-select {
        padding: 0.7rem 1rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: none;
    }
    .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
</style>

<div class="container-fluid" style="padding: 40px; max-width: 1400px;">
    
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3 fade-in">
        <div>
            <h2 class="fw-bold mb-1" style="color: #111827; letter-spacing: -0.5px;">🧭 Menu Manager</h2>
            <p class="text-muted mb-0">Customize your application navigation structure.</p>
        </div>
        <div class="d-flex gap-3">
            <a href="?action=sync_defaults" class="btn btn-white border fw-bold text-dark shadow-sm" style="background:white;" onclick="return confirm('⚠️ Are you sure? This will reset all menus to default.')">
                <i class="fas fa-sync-alt me-2 text-warning"></i> Restore Defaults
            </a>
            <button class="btn btn-gradient fw-bold px-4 py-2 rounded-3" onclick="openAddModal()">
                <i class="fas fa-plus me-2"></i> Add Custom Link
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center shadow-sm border-0 rounded-3 mb-4 fade-in" style="background: #ecfdf5; color: #065f46;">
            <i class="fas fa-check-circle me-3 fs-5"></i> <?= $success ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center shadow-sm border-0 rounded-3 mb-4 fade-in" style="background: #fef2f2; color: #991b1b;">
            <i class="fas fa-exclamation-circle me-3 fs-5"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="modern-card fade-in">
        <div class="table-responsive">
            <table class="table custom-table mb-0">
                <thead>
                    <tr>
                        <th width="35%">Navigation Label</th>
                        <th width="25%">Target Link</th>
                        <th width="15%">Placement</th>
                        <th width="10%">Sort</th>
                        <th width="15%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_menus as $m): 
                        $is_child = $m['parent_id'] > 0;
                        $icon_color = $m['icon_color'] ?: '#6b7280';
                        $bg_color = $m['icon_color'] ? $m['icon_color'].'15' : '#f3f4f6'; // 15 is roughly 10% opacity in hex
                    ?>
                    <tr class="<?= $is_child ? 'bg-light-subtle' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if($is_child): ?>
                                    <div class="ps-3 d-flex align-items-end" style="height: 100%;">
                                        <div class="child-line"></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="icon-wrapper me-3" style="background-color: <?= $bg_color ?>; color: <?= $icon_color ?>;">
                                    <i class="<?= $m['icon'] ?>"></i>
                                </div>
                                
                                <div>
                                    <div class="fw-bold text-dark fs-6"><?= $m['label'] ?></div>
                                    <?php if($is_child): ?>
                                        <div class="small text-muted" style="font-size: 0.75rem;"><i class="fas fa-level-up-alt fa-rotate-90 me-1"></i> Sub-item</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code class="text-primary bg-light px-2 py-1 rounded small"><?= $m['link'] ?></code>
                        </td>
                        <td>
                            <?php if($m['placement']=='main'): ?>
                                <span class="badge badge-soft badge-main"><i class="fas fa-desktop me-1"></i> Main Menu</span>
                            <?php else: ?>
                                <span class="badge badge-soft badge-drop"><i class="fas fa-list me-1"></i> Dropdown</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-bold text-muted">#<?= $m['sort_order'] ?></span>
                        </td>
                        <td class="text-end">
                            <button class="action-btn me-1" onclick="editMenu(
                                '<?= $m['id'] ?>', 
                                '<?= addslashes($m['label']) ?>', 
                                '<?= $m['link'] ?>', 
                                '<?= $m['icon'] ?>', 
                                '<?= $m['placement'] ?>', 
                                '<?= $m['sort_order'] ?>', 
                                '<?= $m['parent_id'] ?>',
                                '<?= $m['icon_color'] ?>'
                            )" title="Edit">
                                <i class="fas fa-pen small"></i>
                            </button>
                            <a href="?delete=<?= $m['id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this menu item?')" title="Delete">
                                <i class="fas fa-trash small"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="menuModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <h5 class="modal-title fw-bold fs-4" id="modalTitle">Menu Item</h5>
                    <p class="text-muted small mb-0">Configure your navigation settings.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <input type="hidden" name="save_menu" value="1">
                <input type="hidden" name="edit_id" id="edit_id">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Label Name</label>
                        <input type="text" name="label" id="m_label" class="form-control" placeholder="e.g. Dashboard" required>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Target Link</label>
                        <input type="text" name="link" id="m_link" class="form-control" placeholder="e.g. page.php" required>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <label class="fw-bold small text-dark mb-2 d-block">Icon Appearance</label>
                    <div class="d-flex gap-3 align-items-center">
                        <div id="iconPreview" class="d-flex align-items-center justify-content-center shadow-sm" 
                             style="width: 50px; height: 50px; background: white; border-radius: 12px; font-size: 1.2rem; border:1px solid #eee;">
                            <i class="fas fa-cube" id="previewIcon"></i>
                        </div>
                        
                        <div class="flex-grow-1">
                             <div class="input-group mb-2">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-icons text-muted"></i></span>
                                <input type="text" name="icon" id="m_icon" class="form-control border-start-0" placeholder="fas fa-home" onkeyup="updatePreview()">
                            </div>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="m_color_picker" value="#4f46e5" title="Choose color" oninput="syncColor('picker')">
                                <input type="text" name="icon_color" id="m_color_text" class="form-control" placeholder="#4f46e5" onkeyup="syncColor('text')">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Menu Placement</label>
                        <select name="placement" id="m_place" class="form-select">
                            <option value="main">Main Header</option>
                            <option value="more">Dropdown Menu</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="m_sort" class="form-control" value="1">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="fw-bold small text-muted mb-1">Parent Category (Optional)</label>
                    <select name="parent_id" id="m_parent" class="form-select">
                        <option value="0">None (Top Level Item)</option>
                        <?php foreach($parents as $p): ?>
                            <option value="<?= $p['id'] ?>">📂 Inside: <?= $p['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-light fw-bold text-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gradient px-4 rounded-3 fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var menuModal;
document.addEventListener('DOMContentLoaded', function() {
    menuModal = new bootstrap.Modal(document.getElementById('menuModal'));
});

// Sync Color Picker <-> Text Input
function syncColor(source) {
    const picker = document.getElementById('m_color_picker');
    const text = document.getElementById('m_color_text');
    const preview = document.getElementById('iconPreview');
    const icon = document.getElementById('previewIcon');

    if (source === 'picker') {
        text.value = picker.value;
    } else {
        picker.value = text.value;
    }
    
    // Update Preview
    preview.style.backgroundColor = text.value + '20'; // 20 opacity
    icon.style.color = text.value;
}

// Update Icon Preview on Typing
function updatePreview() {
    const iconClass = document.getElementById('m_icon').value;
    const previewIcon = document.getElementById('previewIcon');
    if(iconClass) {
        previewIcon.className = iconClass;
    } else {
        previewIcon.className = 'fas fa-cube';
    }
}

function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add New Item';
    document.getElementById('edit_id').value = '';
    document.getElementById('m_label').value = '';
    document.getElementById('m_link').value = '';
    document.getElementById('m_icon').value = 'fas fa-circle';
    document.getElementById('m_color_text').value = '#6366f1';
    document.getElementById('m_color_picker').value = '#6366f1';
    document.getElementById('m_parent').value = '0';
    document.getElementById('m_sort').value = '99';
    updatePreview();
    syncColor('picker');
    menuModal.show();
}

function editMenu(id, label, link, icon, place, sort, parent, color) {
    document.getElementById('modalTitle').innerText = 'Edit Menu Item';
    document.getElementById('edit_id').value = id;
    document.getElementById('m_label').value = label;
    document.getElementById('m_link').value = link;
    document.getElementById('m_icon').value = icon;
    
    let hexColor = color || '#6366f1';
    document.getElementById('m_color_text').value = hexColor;
    document.getElementById('m_color_picker').value = hexColor;

    document.getElementById('m_place').value = place;
    document.getElementById('m_sort').value = sort;
    document.getElementById('m_parent').value = parent;
    
    updatePreview();
    syncColor('text'); // Force preview update
    menuModal.show();
}
</script>
<?php include '_footer.php'; ?>