<?php
// --- 1. CORE HELPERS & SESSION ---
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/../includes/helpers.php';
}

// --- 2. SECURITY CHECKS ---
require_once __DIR__ . '/admin_lock.php';
require_once __DIR__ . '/_auth_check.php';

// --- 3. PAGE LOGIC ---
$current_page = basename($_SERVER['PHP_SELF']);

// --- 4. FETCH MENU ---
$admin_menu_tree = [];
try {
    $stmt = $db->query("SELECT * FROM admin_menus WHERE status = 1 ORDER BY sort_order ASC");
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_items as $item) {
        $admin_menu_tree[$item['parent_id']][] = $item;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($GLOBALS['settings']['site_name'] ?? 'Panel') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        :root { --primary-color: #4f46e5; --sidebar-width: 260px; }
        body { background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0;
            background: #fff; border-right: 1px solid #e5e7eb; z-index: 1000;
            display: flex; flex-direction: column; transition: transform 0.3s ease;
        }
        .sidebar-brand {
            padding: 25px; font-size: 1.5rem; font-weight: 800; color: var(--primary-color);
            border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px;
        }

        .nav-links { list-style: none; padding: 15px 0; margin: 0; flex: 1; overflow-y: auto; }
        .nav-item { position: relative; margin-bottom: 2px; }

        /* NAV LINK */
        .nav-link {
            color: #4b5563; padding: 12px 25px; font-weight: 600; display: flex; align-items: center; gap: 12px;
            transition: all 0.2s; border-left: 4px solid transparent; text-decoration: none; cursor: pointer; font-size: 0.95rem;
        }
        .nav-link:hover { background-color: #f9fafb; color: var(--primary-color); }
        .nav-link.active { background-color: #eef2ff; color: var(--primary-color); border-left-color: var(--primary-color); }
        
        .nav-link i.icon { width: 24px; text-align: center; font-size: 1.1rem; }
        .arrow { margin-left: auto; transition: transform 0.3s; font-size: 0.8rem; opacity: 0.5; }
        
        /* OPEN STATE */
        .nav-item.open .arrow { transform: rotate(180deg); }
        .nav-item.open > .nav-link { color: var(--primary-color); background: #f8fafc; }
        
        /* SUB-MENU */
        .sub-menu {
            list-style: none; padding: 0; background: #f8fafc;
            max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out;
            border-bottom: 1px solid #f1f5f9;
        }
        .sub-menu li a {
            display: block; padding: 10px 20px 10px 62px; font-size: 0.9rem; color: #64748b;
            text-decoration: none; transition: 0.2s;
        }
        .sub-menu li a:hover, .sub-menu li a.active { color: var(--primary-color); background: #e0e7ff; font-weight: 600; }

        .main-content { margin-left: var(--sidebar-width); padding: 30px; transition: 0.3s; }
        
        /* MOBILE */
        .mobile-toggle { display: none; position: fixed; top: 15px; right: 15px; z-index: 1100; border-radius: 50%; width: 45px; height: 45px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body>

    <button class="btn btn-primary mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="fas fa-bars"></i></button>

    <div class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-bolt"></i> Admin Panel</div>

        <ul class="nav-links">
            <?php if (!empty($admin_menu_tree[0])): ?>
                <?php foreach ($admin_menu_tree[0] as $menu): 
                    $children = $admin_menu_tree[$menu['id']] ?? [];
                    $has_children = !empty($children);
                    
                    // --- KEY LOGIC UPDATE ---
                    // Parent consider karenge agar:
                    // 1. Uske bachay (children) hain.
                    // 2. Ya uska link '#' hai (Empty Folder).
                    $is_parent_folder = ($menu['link'] === '#' || $menu['link'] === '');
                    $show_dropdown = ($has_children || $is_parent_folder);
                    
                    // Agar dropdown hai toh link disable karo
                    $menu_link = $show_dropdown ? 'javascript:void(0);' : $menu['link'];
                    $toggle_class = $show_dropdown ? 'has-dropdown' : '';

                    // Auto-Open Logic
                    $is_active = ($menu['link'] == $current_page);
                    $child_active = false;
                    if ($has_children) {
                        foreach ($children as $c) {
                            if ($c['link'] == $current_page) { $child_active = true; break; }
                        }
                    }
                ?>
                
                <li class="nav-item <?= $child_active ? 'open' : '' ?>">
                    <a href="<?= $menu_link ?>" 
                       class="nav-link <?= ($is_active || $child_active) ? 'active' : '' ?> <?= $toggle_class ?>">
                        
                        <i class="fa-solid <?= $menu['icon'] ?> icon" style="color: <?= $menu['color'] ?>"></i>
                        <span><?= htmlspecialchars($menu['label']) ?></span>
                        
                        <?php if ($show_dropdown): ?>
                            <i class="fa-solid fa-chevron-down arrow"></i>
                        <?php endif; ?>
                    </a>

                    <ul class="sub-menu" style="<?= $child_active ? 'max-height:2000px;' : '' ?>">
                        <?php if ($has_children): ?>
                            <?php foreach ($children as $sub): 
                                $sub_active = ($sub['link'] == $current_page) ? 'active' : '';
                            ?>
                            <li>
                                <a href="<?= $sub['link'] ?>" class="<?= $sub_active ?>">
                                    <i class="fa-solid <?= $sub['icon'] ?>" style="font-size:0.7rem; margin-right:8px; color:<?= $sub['color'] ?>"></i>
                                    <?= htmlspecialchars($sub['label']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 30px 20px; text-align: center; color: #94a3b8;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:1.5rem; margin-bottom:10px;"></i><br>
                    <small>Menu Empty</small>
                </div>
            <?php endif; ?>
        </ul>

        <div style="padding: 15px; border-top: 1px solid #f3f4f6; margin-top: auto;">
            <a class="nav-link text-danger" href="../logout.php" style="border-radius:8px;">
                <i class="fas fa-sign-out-alt icon"></i> Logout
            </a>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fa-solid fa-check-circle me-2"></i> Success!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> Error!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const dropdowns = document.querySelectorAll('.nav-link.has-dropdown');

    dropdowns.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const parent = this.parentElement;
            const submenu = parent.querySelector('.sub-menu');
            
            // Toggle Class
            parent.classList.toggle('open');
            
            // Toggle Height
            if (submenu.style.maxHeight && submenu.style.maxHeight !== '0px') {
                submenu.style.maxHeight = null; // Close
            } else {
                submenu.style.maxHeight = submenu.scrollHeight + "px"; // Open
            }
        });
    });
});
</script>
</body>
</html>