<?php
// --- 1. SETUP & LOGIC ---
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/admin_lock.php';
require_once __DIR__ . '/_auth_check.php';

// --- AJAX HANDLER: SAVE ORDER ---
if (isset($_POST['action']) && $_POST['action'] == 'save_order') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $menu = json_decode($_POST['menu'], true);
    
    function updateMenuOrder($items, $parent_id = 0, $db) {
        foreach ($items as $index => $item) {
            $stmt = $db->prepare("UPDATE admin_menus SET parent_id = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$parent_id, $index + 1, $item['id']]);
            
            if (isset($item['children'])) {
                updateMenuOrder($item['children'], $item['id'], $db);
            }
        }
    }
    
    if($menu) {
        updateMenuOrder($menu, 0, $db);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

// --- HANDLE ADD / DELETE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ADD ITEM
    if (isset($_POST['add_item'])) {
        $type = $_POST['type'];
        $label = trim($_POST['label']);
        // Agar Parent hai to link '#' hoga
        $link = ($type == 'parent') ? '#' : trim($_POST['link']);
        $icon = empty($_POST['icon']) ? 'fa-circle' : trim($_POST['icon']);
        $color = empty($_POST['color']) ? '#4f46e5' : trim($_POST['color']);
        
        if(!empty($label)) {
            $stmt = $db->prepare("INSERT INTO admin_menus (label, link, icon, color, parent_id, sort_order, status) VALUES (?, ?, ?, ?, 0, 99, 1)");
            $stmt->execute([$label, $link, $icon, $color]);
            header("Location: admin_menu_manager.php?success=added");
            exit;
        }
    }
    
    // DELETE ITEM
    elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $db->prepare("DELETE FROM admin_menus WHERE id = ?")->execute([$id]);
        $db->prepare("DELETE FROM admin_menus WHERE parent_id = ?")->execute([$id]);
        header("Location: admin_menu_manager.php?success=deleted");
        exit;
    }
}

include '_header.php';

// --- FETCH DATA ---
$menus = [];
try {
    $menus = $db->query("SELECT * FROM admin_menus ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$menuTree = [];
foreach ($menus as $m) {
    $menuTree[$m['parent_id']][] = $m;
}

// RECURSIVE RENDER FUNCTION
function renderMenuTree($parent_id, $tree) {
    if (!isset($tree[$parent_id])) return;
    
    echo '<ol class="dd-list">';
    foreach ($tree[$parent_id] as $item) {
        $isParent = ($item['link'] == '#' || isset($tree[$item['id']]));
        
        echo '<li class="dd-item" data-id="' . $item['id'] . '">';
        
        // --- CONTENT BOX ---
        echo '<div class="dd-content ' . ($isParent ? 'is-parent-item' : '') . '">';
            echo '<div class="dd-handle"><i class="fa-solid fa-grip-vertical"></i></div>';
            
            echo '<div class="item-info">';
                echo '<span class="icon-box" style="background:'.$item['color'].'15; color:'.$item['color'].';"><i class="fa-solid '.$item['icon'].'"></i></span>';
                echo '<div>';
                    echo '<span class="item-name">'.htmlspecialchars($item['label']).'</span>';
                    if($item['link'] == '#') echo '<span class="badge-parent">PARENT FOLDER</span>';
                    else echo '<span class="item-link">'.htmlspecialchars($item['link']).'</span>';
                echo '</div>';
            echo '</div>';

            echo '<div class="item-actions">';
                echo '<form method="POST" onsubmit="return confirm(\'Delete this menu?\')">';
                echo '<input type="hidden" name="delete_id" value="'.$item['id'].'">';
                echo '<button class="btn-trash" type="submit"><i class="fa-solid fa-trash-can"></i></button>';
                echo '</form>';
            echo '</div>';
        echo '</div>';
        // --- END BOX ---

        renderMenuTree($item['id'], $tree);
        echo '</li>';
    }
    echo '</ol>';
}
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestable2/1.6.0/jquery.nestable.min.js"></script>

<style>
    :root { --primary: #4f46e5; --bg: #f8fafc; --border: #e2e8f0; }
    
    .page-head { margin-bottom: 30px; }
    .page-head h1 { font-weight: 800; color: #1e293b; margin: 0; display:flex; align-items:center; gap:10px; font-size: 1.8rem; }

    .manager-layout { display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }

    /* FORM STYLE */
    .form-card { background: white; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05); position: sticky; top: 20px; overflow: hidden; }
    
    .type-tabs { display: flex; background: #f1f5f9; padding: 5px; border-bottom: 1px solid var(--border); }
    .tab-btn { flex: 1; border: none; background: none; padding: 10px; font-weight: 700; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.9rem; }
    .tab-btn:hover { background: rgba(255,255,255,0.5); }
    .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

    .form-body { padding: 20px; }
    .inp-group { margin-bottom: 15px; }
    .inp-label { display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
    .inp-field { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; outline: none; transition: 0.2s; background: #fff; }
    .inp-field:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

    .split-inp { display: flex; gap: 10px; }
    .icon-preview { width: 48px; flex-shrink: 0; background: #f8fafc; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.2rem; border: 1px solid var(--border); }
    
    .color-wrapper { position: relative; display: flex; align-items: center; }
    .color-picker { position: absolute; left: 5px; width: 35px; height: 35px; border: none; background: none; cursor: pointer; padding: 0; }
    .color-text { padding-left: 45px; font-family: monospace; font-weight: 600; }

    .btn-submit { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px; display: flex; justify-content: center; gap: 8px; }
    .btn-submit:hover { background: #4338ca; transform: translateY(-2px); }
    .btn-submit.btn-parent { background: #0f172a; }

    /* --- NESTABLE FIXES --- */
    .dd { max-width: 100%; }
    .dd-list { padding: 0; margin: 0; list-style: none; }
    
    /* Item Container: Padding-left zaroori hai buttons ke liye */
    .dd-item { margin-bottom: 12px; position: relative; display: block; padding-left: 30px; }
    
    /* --- EXPAND/COLLAPSE BUTTONS CSS --- */
    .dd-item > button {
        display: block; position: absolute; left: 0; top: 12px;
        width: 24px; height: 24px; padding: 0; white-space: nowrap; overflow: hidden;
        border: 0; background: transparent; font-size: 12px; line-height: 1; text-align: center;
        font-weight: bold; cursor: pointer; color: #64748b; z-index: 10;
    }
    /* Plus Icon */
    .dd-item > button:before { 
        content: '\f067'; font-family: "Font Awesome 6 Free"; font-weight: 900; 
        display: block; width: 100%; height: 100%; background: #fff; 
        border: 1px solid #cbd5e1; border-radius: 4px; display: flex; 
        align-items: center; justify-content: center;
    }
    /* Minus Icon */
    .dd-item > button[data-action="collapse"]:before { 
        content: '\f068'; color: var(--primary); background: #eef2ff; border-color: var(--primary); 
    }

    /* Content Styling */
    .dd-content { 
        display: flex; align-items: center; background: white; padding: 12px 15px; 
        border: 1px solid var(--border); border-radius: 12px; transition: 0.2s; 
        position: relative; z-index: 2; margin-left: 5px;
    }
    .dd-content:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    
    .is-parent-item { background: #f8fafc; border-left: 4px solid var(--primary); }
    .badge-parent { font-size: 0.65rem; background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: 700; letter-spacing: 0.5px; }

    .dd-handle { cursor: grab; padding: 0 15px 0 5px; color: #cbd5e1; font-size: 1.2rem; margin-right: 15px; border-right: 1px solid #f1f5f9; }
    .dd-handle:hover { color: var(--primary); }
    .dd-handle:active { cursor: grabbing; }

    .item-info { flex: 1; display: flex; align-items: center; gap: 15px; overflow: hidden; }
    .icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .item-name { font-weight: 700; color: #1e293b; font-size: 0.95rem; display: block; }
    .item-link { font-size: 0.8rem; color: #94a3b8; font-family: monospace; display: block; }

    .btn-trash { background: #fee2e2; color: #ef4444; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-trash:hover { background: #ef4444; color: white; }

    .dd-placeholder { background: #fffbeb; border: 2px dashed #fbbf24; border-radius: 12px; height: 60px; margin-bottom: 12px; opacity: 0.8; }
    .dd-dragel .dd-content { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: rotate(2deg); border-color: var(--primary); }

    /* Indentation for children */
    .dd-list .dd-list { padding-left: 30px; margin-top: 5px; }

    @media (max-width: 768px) { 
        .manager-layout { grid-template-columns: 1fr; }
        .form-card { position: relative; top: 0; margin-bottom: 20px; }
        .item-link { display: none; }
        .dd-handle { padding: 10px; }
    }
</style>

<div class="main-content">
    
    <div class="page-head">
        <h1><i class="fa-solid fa-layer-group" style="color:var(--primary);"></i> Menu Manager</h1>
        <p>Customize your admin sidebar. Drag to reorder, use tabs to create sections.</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0;">
        <i class="fa-solid fa-check-circle"></i> Success! Action completed.
    </div>
    <?php endif; ?>

    <div class="manager-layout">
        
        <div class="form-card">
            <div class="type-tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('link')" id="btnLink">
                    <i class="fa-regular fa-file"></i> Page Link
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('parent')" id="btnParent">
                    <i class="fa-regular fa-folder-open"></i> Parent Folder
                </button>
            </div>
            
            <div class="form-body">
                <form method="POST">
                    <input type="hidden" name="add_item" value="1">
                    <input type="hidden" name="type" id="inputType" value="link">
                    
                    <div class="inp-group">
                        <label class="inp-label">Label Name</label>
                        <input type="text" name="label" class="inp-field" placeholder="e.g. Users" required>
                    </div>

                    <div class="inp-group" id="groupLink">
                        <label class="inp-label">Target File / URL</label>
                        <input type="text" name="link" class="inp-field" placeholder="e.g. users.php">
                        <small style="color:#94a3b8; font-size:0.75rem;">Enter '#' if you want no link</small>
                    </div>

                    <div class="inp-group">
                        <label class="inp-label">Icon Class</label>
                        <div class="split-inp">
                            <div class="icon-preview"><i id="iconPreview" class="fa-solid fa-circle"></i></div>
                            <input type="text" name="icon" id="iconInput" class="inp-field" placeholder="fa-users" value="fa-circle" oninput="updateIcon()">
                        </div>
                    </div>

                    <div class="inp-group">
                        <label class="inp-label">Highlight Color</label>
                        <div class="color-wrapper">
                            <input type="color" id="colorPicker" class="color-picker" value="#4f46e5" oninput="syncColor(this.value)">
                            <input type="text" name="color" id="colorText" class="inp-field color-text" value="#4f46e5" oninput="syncColor(this.value)">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fa-solid fa-plus"></i> Add Link
                    </button>
                </form>
            </div>
        </div>

        <div class="menu-tree">
            <div class="dd" id="menuList">
                <?php if(empty($menuTree)): ?>
                    <div style="text-align:center; padding:50px; color:#94a3b8; border:2px dashed #e2e8f0; border-radius:16px; background:#fcfcfd;">
                        <i class="fa-solid fa-arrow-left" style="font-size:2rem; margin-bottom:15px; color:#cbd5e1;"></i><br>
                        <strong>Menu is empty!</strong><br>
                        Add items from the left.
                    </div>
                <?php else: ?>
                    <?php renderMenuTree(0, $menuTree); ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    $(document).ready(function() {
        $('#menuList').nestable({
            maxDepth: 2, 
            group: 1,
            handleClass: 'dd-handle' 
        }).on('change', function() {
            var json = window.JSON.stringify($('#menuList').nestable('serialize'));
            $.post('admin_menu_manager.php', { action: 'save_order', menu: json });
        });
    });

    function switchTab(type) {
        document.getElementById('inputType').value = type;
        document.getElementById('btnLink').className = (type === 'link') ? 'tab-btn active' : 'tab-btn';
        document.getElementById('btnParent').className = (type === 'parent') ? 'tab-btn active' : 'tab-btn';
        
        const linkGroup = document.getElementById('groupLink');
        const btn = document.getElementById('submitBtn');
        const iconInput = document.getElementById('iconInput');

        if (type === 'parent') {
            linkGroup.style.display = 'none';
            btn.innerHTML = '<i class="fa-solid fa-folder-plus"></i> Create Folder';
            btn.classList.add('btn-parent');
            
            if(iconInput.value === 'fa-circle' || iconInput.value === '') { 
                iconInput.value = 'fa-folder'; 
                updateIcon(); 
            }
        } else {
            linkGroup.style.display = 'block';
            btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Link';
            btn.classList.remove('btn-parent');
            
            if(iconInput.value === 'fa-folder') { 
                iconInput.value = 'fa-circle'; 
                updateIcon(); 
            }
        }
    }

    function updateIcon() {
        let val = document.getElementById('iconInput').value;
        let clean = val.replace('fa-solid ', '').replace('fa-regular ', '').replace('fab ', '');
        document.getElementById('iconPreview').className = "fa-solid " + clean;
    }

    function syncColor(val) {
        document.getElementById('colorPicker').value = val;
        document.getElementById('colorText').value = val;
    }
</script>

<?php include '_footer.php'; ?>