<?php include '_header.php'; 
$my_files = $db->prepare("SELECT o.id, o.created_at, o.total_price, p.name, p.download_link, p.icon 
                           FROM orders o 
                           JOIN products p ON o.product_id = p.id 
                           WHERE o.user_id = ? AND p.is_digital = 1 
                           ORDER BY o.id DESC");
$my_files->execute([$user_id]);
$files = $my_files->fetchAll();
?>

<div class="main-content-wrapper">
    <h2>📂 My Downloads Library</h2>
    
    <div style="display:grid; gap:15px; margin-top:20px;">
        <?php foreach($files as $f): ?>
        <div style="background:#fff; padding:15px; border-radius:12px; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:15px;">
                <img src="../assets/img/<?= $f['icon'] ?>" style="width:50px; height:50px; border-radius:8px; object-fit:cover;">
                <div>
                    <h4 style="margin:0; color:#1e293b;"><?= $f['name'] ?></h4>
                    <small style="color:#64748b;">Purchased: <?= date('d M Y', strtotime($f['created_at'])) ?></small>
                </div>
            </div>
            <a href="<?= $f['download_link'] ?>" target="_blank" style="background:#4f46e5; color:#fff; padding:8px 20px; border-radius:8px; text-decoration:none; font-weight:700;">
                Download
            </a>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($files)): ?>
            <p class="text-muted">You haven't purchased any digital assets yet.</p>
        <?php endif; ?>
    </div>
</div>
<?php include '_footer.php'; ?>