<?php
include '_header.php';

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// --- 1. HANDLE DELETE (Single Category) ---
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
    echo '<script>history.pushState(null, "", "smm_categories.php");</script>';
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
            // Logic: Extract Main Name (e.g. "Instagram Likes" -> "Instagram")
            // Agar aap chahte hain ke sirf "Instagram" store ho, toh ye logic use karein:
            // $parts = explode(' ', trim($full_name));
            // $main_name = $parts[0]; 
            
            // LEKIN: SMM panel mein category poori hoti hai ("Instagram Likes").
            // Agar hum usay kaat denge toh services link nahi hongi.
            // ISLIYE: Hum Database mein poora naam rakhenge, lekin **Display** karte waqt Group karenge.
            
            $stmt_check->execute([$full_name]);
            if (!$stmt_check->fetch()) {
                $stmt_insert->execute([$full_name]);
                $inserted++;
            }
        }
        $success = "Sync Complete! $inserted new categories found.";
        echo '<script>history.pushState(null, "", "smm_categories.php");</script>';
    } catch (Exception $e) { $error = $e->getMessage(); }
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
                // e.g. "Instagram" icon will apply to "Instagram Likes", "Instagram Views" etc.
                $like_query = $main_name . '%';
                $stmt_update->execute([sanitize($icon_file), $like_query]);
            }
        }
        $db->commit();
        $success = "Icons updated for grouped categories!";
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

// --- 4. FETCH & GROUP DATA ---
// Hum Database se sab laayenge, par PHP mein group karenge
$cats = $db->query("SELECT * FROM smm_categories ORDER BY name ASC")->fetchAll();

$grouped_cats = [];
foreach ($cats as $c) {
    // Group by First Word (e.g. "Instagram", "YouTube", "TikTok")
    // Agar category name "TikTok - Likes" hai, toh "TikTok" key banegi.
    $parts = explode(' ', $c['name']);
    $parts2 = explode('-', $parts[0]); // Handle "Net-Flix" cases
    $group_key = trim($parts2[0]); 
    
    // Special Case for specific apps if needed
    if(stripos($c['name'], 'youtube') !== false) $group_key = 'YouTube';
    if(stripos($c['name'], 'instagram') !== false) $group_key = 'Instagram';
    if(stripos($c['name'], 'tiktok') !== false) $group_key = 'TikTok';
    if(stripos($c['name'], 'facebook') !== false) $group_key = 'Facebook';
    if(stripos($c['name'], 'telegram') !== false) $group_key = 'Telegram';
    
    if (!isset($grouped_cats[$group_key])) {
        $grouped_cats[$group_key] = [
            'icon' => $c['icon_filename'],
            'count' => 0,
            'example' => $c['name']
        ];
    }
    $grouped_cats[$group_key]['count']++;
    // Agar kisi ek child mein icon hai, toh usay group icon maan lo
    if(empty($grouped_cats[$group_key]['icon']) && !empty($c['icon_filename'])) {
        $grouped_cats[$group_key]['icon'] = $c['icon_filename'];
    }
}
?>

<div class="container-fluid" style="padding:30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight:800; color:#1f2937; margin:0;">📂 Main App Categories</h2>
            <p style="color:#6b7280; margin:0;">Assign icons to main apps (e.g. YouTube) and it will apply to all sub-services (Likes, Views).</p>
        </div>
        <div>
            <a href="?action=sync_categories" class="btn btn-success" onclick="return confirm('Sync now?')">🔄 Sync New</a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
        <form method="POST">
            <input type="hidden" name="update_icons" value="1">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>App Name</th>
                            <th>Current Icon</th>
                            <th>Set Icon Filename</th>
                            <th>Sub-Categories</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($grouped_cats as $name => $data): ?>
                        <tr>
                            <td style="font-weight:700; font-size:1rem;"><?= $name ?></td>
                            <td>
                                <?php if(!empty($data['icon'])): ?>
                                    <img src="../assets/img/icons/<?= $data['icon'] ?>" width="35" style="border-radius:6px;">
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="icons[<?= $name ?>]" class="form-control form-control-sm" 
                                       value="<?= $data['icon'] ?>" placeholder="e.g. youtube.png" style="max-width:200px;">
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= $data['count'] ?> Types</span>
                                <small class="text-muted d-block" style="font-size:0.7rem;">(e.g. <?= $data['example'] ?>)</small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-3 border-top bg-light text-end">
                <button type="submit" class="btn btn-primary fw-bold">💾 Save Icons</button>
            </div>
        </form>
    </div>
</div>

<?php include '_footer.php'; ?>