<?php
// Enable Error Reporting (For Debugging White Page)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '_header.php';

$error = '';
$success = '';

// --- 1. HANDLE ADD / EDIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_download'])) {
    
    $name = sanitize($_POST['name']);
    $cat_id = (int)$_POST['category_id'];
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $download_link = trim($_POST['download_link']);
    $file_size = trim($_POST['file_size']);
    
    // Image Upload Logic
    $icon_path = $_POST['old_icon'] ?? 'default.png';
    if (!empty($_FILES['icon']['name'])) {
        $ext = pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $new_name = 'dl-' . uniqid() . '.' . $ext;
            // Ensure directory exists
            if (!is_dir("../assets/img/")) { mkdir("../assets/img/", 0755, true); }
            
            if (move_uploaded_file($_FILES['icon']['tmp_name'], "../assets/img/" . $new_name)) {
                $icon_path = $new_name;
            } else {
                $error = "Failed to upload image. Check folder permissions.";
            }
        } else {
            $error = "Invalid Image Format! Only JPG, PNG, WEBP allowed.";
        }
    }

    if (empty($error)) {
        try {
            if (!empty($_POST['edit_id'])) {
                // UPDATE EXISTING
                $stmt = $db->prepare("UPDATE products SET name=?, category_id=?, description=?, icon=?, price=?, download_link=?, file_size=? WHERE id=?");
                $stmt->execute([$name, $cat_id, $desc, $icon_path, $price, $download_link, $file_size, $_POST['edit_id']]);
                $success = "Digital Product updated!";
            } else {
                // CREATE NEW
                // Slug generation
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                
                // Insert Query (Explicitly setting is_digital=1 and is_active=1)
                $stmt = $db->prepare("INSERT INTO products (name, slug, category_id, description, icon, price, is_digital, download_link, file_size, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 1, NOW())");
                
                if($stmt->execute([$name, $slug, $cat_id, $desc, $icon_path, $price, $download_link, $file_size])) {
                    $success = "New Digital Product Created Successfully!";
                } else {
                    $error = "Database Error: Could not insert product.";
                }
            }
        } catch (Exception $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}

// --- 2. HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM products WHERE id = ? AND is_digital = 1")->execute([$_GET['delete']]);
    echo "<script>window.location='downloads_manager.php';</script>";
}

// --- 3. FETCH DATA ---
$downloads = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_digital = 1 ORDER BY p.id DESC")->fetchAll();
$categories = $db->query("SELECT * FROM categories")->fetchAll();
?>

<div class="container-fluid" style="padding:30px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight:800; color:#1f2937; margin:0;">📥 Digital Downloads Manager</h2>
            <p style="color:#6b7280; margin:0;">Manage your digital assets (Software, Files, Courses).</p>
        </div>
        <button class="btn btn-success px-4" data-bs-toggle="modal" data-bs-target="#dlModal" onclick="resetForm()">
            <i class="fas fa-plus-circle"></i> Add New File
        </button>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius:16px; overflow:hidden;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Thumbnail</th>
                        <th>Product Name</th>
                        <th>File Size</th>
                        <th>Link Status</th>
                        <th>Price</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($downloads)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No digital products found. Click 'Add New File' to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($downloads as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <img src="../assets/img/<?= $p['icon'] ?>" style="width:50px; height:50px; border-radius:10px; object-fit:cover; background:#f3f4f6;">
                            </td>
                            <td>
                                <div style="font-weight:700; color:#111;"><?= htmlspecialchars($p['name']) ?></div>
                                <small class="text-muted"><?= $p['cat_name'] ?? 'Uncategorized' ?></small>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['file_size']) ?></span></td>
                            <td>
                                <?php if(!empty($p['download_link'])): ?>
                                    <span class="badge bg-success">Link Set</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Missing Link</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:700; color:#10b981;">Rs <?= number_format($p['price']) ?></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-primary" onclick='editDownload(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
                                <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this file product?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="dlModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Digital Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="save_download" value="1">
                <input type="hidden" name="edit_id" id="edit_id">
                <input type="hidden" name="old_icon" id="old_icon">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Product Name</label>
                        <input type="text" name="name" id="d_name" class="form-control" placeholder="e.g. 1000+ Premiere Pro Transitions" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Price (PKR)</label>
                        <input type="number" name="price" id="d_price" class="form-control" placeholder="500" required>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Category</label>
                        <select name="category_id" id="d_cat" class="form-select">
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Thumbnail Image</label>
                        <input type="file" name="icon" class="form-control">
                        <small class="text-muted">Best size: 600x400 px</small>
                    </div>
                </div>

                <div class="mt-3 p-3 rounded" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-link"></i> Download Settings</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Download Link (Secure)</label>
                        <input type="text" name="download_link" id="d_link" class="form-control" placeholder="Paste Google Drive / Mega link here..." required>
                        <small class="text-muted">This link is hidden. Users only see it after payment.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">File Size Label</label>
                        <input type="text" name="file_size" id="d_size" class="form-control" placeholder="e.g. 2.5 GB">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" id="d_desc" class="form-control" rows="3" placeholder="What is included in this pack?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success px-4">Save & Publish</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add New Digital Product';
    document.getElementById('edit_id').value = '';
    document.getElementById('d_name').value = '';
    document.getElementById('d_price').value = '';
    document.getElementById('d_link').value = '';
    document.getElementById('d_size').value = '';
    document.getElementById('d_desc').value = '';
}

function editDownload(p) {
    var modal = new bootstrap.Modal(document.getElementById('dlModal'));
    document.getElementById('modalTitle').innerText = 'Edit Product';
    
    document.getElementById('edit_id').value = p.id;
    document.getElementById('old_icon').value = p.icon;
    document.getElementById('d_name').value = p.name;
    document.getElementById('d_price').value = p.price;
    document.getElementById('d_cat').value = p.category_id;
    
    document.getElementById('d_link').value = p.download_link;
    document.getElementById('d_size').value = p.file_size;
    document.getElementById('d_desc').value = p.description;
    
    modal.show();
}
</script>

<?php include '_footer.php'; ?>