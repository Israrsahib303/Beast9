<?php
include '_header.php';

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// --- 1. HANDLE DELETE (Single Category Record) ---
// Note: This logic only deletes a single record from smm_categories and checks dependency by name.
if ($action == 'delete_category' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    try {
        // Check dependency
        $cat_name = $db->query("SELECT name FROM smm_categories WHERE id=$category_id")->fetchColumn();
        $count = $db->query("SELECT COUNT(*) FROM smm_services WHERE category = '$cat_name'")->fetchColumn();
        
        if ($count > 0) {
            $error = "Cannot delete: This category has $count active services.";
        } else {
            $db->prepare("DELETE FROM smm_categories WHERE id = ?")->execute([$category_id]);
            $success = "Category deleted.";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
    // Refresh page without query params
    echo '<script>window.location.href="smm_categories.php?success=' . urlencode($success) . '";</script>';
    exit;
}

// --- NEW: HANDLE DELETE GROUPED CATEGORY (Soft Delete Services, Hard Delete Category Records) ---
if ($action == 'delete_grouped_category' && isset($_GET['group_name'])) {
    $group_name = urldecode($_GET['group_name']);
    try {
        // 1. SERVICES: Soft Delete/Disable ALL services that belong to this group (e.g., 'Instagram%')
        $db->prepare("UPDATE smm_services SET is_active=0, manually_deleted=1 WHERE category LIKE ?")
           ->execute([$group_name . '%']);
           
        // 2. CATEGORIES: Delete ALL category entries that belong to this group from smm_categories table
        $db->prepare("DELETE FROM smm_categories WHERE name LIKE ?")
           ->execute([$group_name . '%']);
           
        $success = "Successfully removed services and categories associated with <b>" . htmlspecialchars($group_name) . "</b> group.";
    } catch (Exception $e) { $error = $e->getMessage(); }
    echo '<script>window.location.href="smm_categories.php?success=' . urlencode($success) . '";</script>';
    exit;
}

// --- NEW: DELETE ALL CATEGORIES (Hard Reset) ---
if ($action == 'delete_all_cats') {
    if (isAdmin()) {
        try {
            $db->query("TRUNCATE TABLE smm_categories");
            $success = "🗑️ All category records deleted successfully.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    echo '<script>window.location.href="smm_categories.php?success=' . urlencode($success) . '";</script>';
    exit;
}

// --- 2. SYNC LOGIC (Smart Grouping) ---
if ($action == 'sync_categories') {
    try {
        // Fetch all unique categories from services
        $stmt = $db->query("SELECT DISTINCT category FROM smm_services WHERE is_active = 1");
        $all_cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $inserted = 0;
        $stmt_check = $db->prepare("SELECT id FROM smm_categories WHERE name = ?");
        $stmt_insert = $db->prepare("INSERT INTO smm_categories (name) VALUES (?)");

        foreach ($all_cats as $full_name) {
            $stmt_check->execute([$full_name]);
            if (!$stmt_check->fetch()) {
                $stmt_insert->execute([$full_name]);
                $inserted++;
            }
        }
        $success = "Sync Complete! $inserted new categories found.";
    } catch (Exception $e) { $error = $e->getMessage(); }
    echo '<script>window.location.href="smm_categories.php?success=' . urlencode($success) . '";</script>';
    exit;
}

// --- 3. UPDATE ICONS (Batch Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_icons'])) {
    $icons = $_POST['icons'] ?? [];
    
    try {
        $db->beginTransaction();
        $stmt_update = $db->prepare("UPDATE smm_categories SET icon_filename = ? WHERE name LIKE ?");
        
        foreach ($icons as $main_name => $icon_file) {
            if (!empty($icon_file)) {
                // Logic: Update ALL categories that start with this name
                $like_query = $main_name . '%';
                $stmt_update->execute([sanitize($icon_file), $like_query]);
            }
        }
        $db->commit();
        $success = "Icons updated for grouped categories!";
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
    echo '<script>window.location.href="smm_categories.php?success=' . urlencode($success) . '";</script>';
    exit;
}

// --- 4. FETCH & GROUP DATA ---
$cats = $db->query("SELECT * FROM smm_categories ORDER BY name ASC")->fetchAll();

$grouped_cats = [];
foreach ($cats as $c) {
    // Grouping Logic: Prioritize full platform names (YouTube, Instagram, TikTok)
    $group_key = 'Other';
    if(stripos($c['name'], 'youtube') !== false) $group_key = 'YouTube';
    else if(stripos($c['name'], 'instagram') !== false) $group_key = 'Instagram';
    else if(stripos($c['name'], 'tiktok') !== false) $group_key = 'TikTok';
    else if(stripos($c['name'], 'facebook') !== false) $group_key = 'Facebook';
    else if(stripos($c['name'], 'telegram') !== false) $group_key = 'Telegram';
    else {
        // If not a major platform, group by the first word (e.g. "Net-Flix" -> "Net-Flix")
        $parts = explode(' ', $c['name']);
        $parts2 = explode('-', $parts[0]);
        $group_key = trim($parts2[0]);
    }
    
    if (!isset($grouped_cats[$group_key])) {
        $grouped_cats[$group_key] = [
            'icon' => $c['icon_filename'],
            'count' => 0,
            'example' => $c['name']
        ];
    }
    $grouped_cats[$group_key]['count']++;
    if(empty($grouped_cats[$group_key]['icon']) && !empty($c['icon_filename'])) {
        $grouped_cats[$group_key]['icon'] = $c['icon_filename'];
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #4f46e5;
        --primary-soft: #eef2ff;
        --text-dark: #1f2937;
        --text-muted: #6b7280;
        --border: #e5e7eb;
        --danger: #dc2626;
        --danger-soft: #fee2e2;
        --success: #10b981;
        --card-bg: #fff;
    }

    body { font-family: 'Plus Jakarta Sans', sans-serif; }

    /* --- ALERTS --- */
    .alert-box { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .alert-danger { background: var(--danger-soft); color: var(--danger); border: 1px solid #fecaca; }

    /* --- LAYOUT & HEADER --- */
    .main-header {
        background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
        padding: 30px 0; margin-bottom: 30px;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .main-header-content {
        display: flex; justify-content: space-between; align-items: center;
        max-width: 1200px; margin: 0 auto; padding: 0 20px;
    }
    .main-header h2 { font-weight: 800; color: var(--text-dark); margin: 0; font-size: 2.2rem; }
    .main-header p { color: var(--text-muted); margin: 5px 0 0; }

    .btn-action-group { display: flex; gap: 10px; }
    .btn-sync { background: var(--success); color: white; padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; border: none; transition: 0.2s; }
    .btn-sync:hover { background: #059669; }
    .btn-delete-all { background: var(--danger); color: white; padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; border: none; transition: 0.2s; }
    .btn-delete-all:hover { background: #b91c1c; }

    /* --- MAIN CONTENT CARD --- */
    .content-card {
        background: var(--card-bg); border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    /* --- TABLE STYLES --- */
    .icon-input {
        width: 100%; padding: 8px 12px; border-radius: 8px;
        border: 1px solid var(--border); transition: 0.2s;
    }
    .icon-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); outline: none; }
    
    .table-responsive { overflow-x: auto; }
    .main-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .main-table thead th { 
        background: #f8fafc; color: var(--text-muted); font-weight: 600;
        padding: 15px 20px; border-bottom: 1px solid var(--border);
    }
    .main-table tbody td {
        padding: 15px 20px; border-bottom: 1px solid #f1f5f9;
    }
    .main-table tbody tr:hover { background: #fcfcfd; }
    
    .group-name { font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 8px; }
    .count-badge { background: #e0e7ff; color: #4f46e5; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; }

    /* --- FOOTER/SAVE AREA --- */
    .save-footer {
        padding: 20px; border-top: 1px solid var(--border);
        background: #f8fafc; text-align: right;
    }
    .btn-save { background: var(--primary); color: white; padding: 10px 25px; border-radius: 10px; font-weight: 700; border: none; transition: 0.2s; }
    .btn-save:hover { background: #4338ca; transform: translateY(-1px); }
    
    .delete-group-btn {
        background: var(--danger-soft); color: var(--danger); 
        padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; 
        text-decoration: none; font-weight: 600; border: 1px solid #fecaca;
        transition: 0.2s;
    }
    .delete-group-btn:hover { background: var(--danger); color: white; }

    @media(max-width: 768px) {
        .main-header-content { flex-direction: column; align-items: flex-start; gap: 15px; }
        .main-header h2 { font-size: 1.8rem; }
    }
</style>

<div class="main-header">
    <div class="main-header-content">
        <div>
            <h2><i class="fa-solid fa-layer-group"></i> Categories Manager</h2>
            <p>Assign icons to main apps (e.g., YouTube) which applies to all related services.</p>
        </div>
        <div class="btn-action-group">
            <a href="?action=sync_categories" class="btn-core btn-sync" onclick="return confirm('Start sync to find new categories?')">
                <i class="fa-solid fa-arrows-rotate"></i> Sync New
            </a>
            <a href="?action=delete_all_cats" class="btn-core btn-delete-all" onclick="return confirm('⚠️ WARNING: This will DELETE ALL category RECORDS (smm_categories table ONLY). Services will remain. Continue?')">
                <i class="fa-solid fa-trash"></i> Delete All Records
            </a>
        </div>
    </div>
</div>

<div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
    <?php if ($success): ?><div class="alert-box alert-success"><i class="fa-solid fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-box alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div><?php endif; ?>

    <div class="content-card">
        <form method="POST">
            <input type="hidden" name="update_icons" value="1">
            
            <div class="table-responsive">
                <table class="main-table">
                    <thead>
                        <tr>
                            <th width="20%">App Group</th>
                            <th width="15%">Current Icon</th>
                            <th width="30%">Set Icon Filename</th>
                            <th width="20%">Sub-Categories</th>
                            <th width="15%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($grouped_cats as $name => $data): ?>
                        <tr>
                            <td>
                                <div class="group-name">
                                    <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($name) ?>
                                </div>
                            </td>
                            <td>
                                <?php if(!empty($data['icon'])): ?>
                                    <img src="../assets/img/icons/<?= $data['icon'] ?>" onerror="this.src='../assets/img/icons/smm.png'" width="35" style="border-radius:6px;">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="icons[<?= htmlspecialchars($name) ?>]" class="icon-input" 
                                       value="<?= htmlspecialchars($data['icon']) ?>" placeholder="e.g. youtube.png">
                            </td>
                            <td>
                                <span class="count-badge"><?= $data['count'] ?> Services</span>
                                <small class="text-muted d-block" style="font-size:0.75rem;">(e.g. <?= htmlspecialchars($data['example']) ?>)</small>
                            </td>
                            <td>
                                <a href="smm_categories.php?action=delete_grouped_category&group_name=<?= urlencode($name) ?>" 
                                   class="delete-group-btn"
                                   onclick="return confirm('⚠️ WARNING: All <?= $name ?> services will be DISABLED and category entries deleted. Continue?')">
                                    <i class="fa-solid fa-trash"></i> Delete Group
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="save-footer">
                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Icons</button>
            </div>
        </form>
    </div>
</div>

<?php include '_footer.php'; ?>