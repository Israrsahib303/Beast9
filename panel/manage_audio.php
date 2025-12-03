<?php
include '_header.php';

$error = '';
$success = '';
$upload_dir = '../assets/uploads/audio/';

// --- UPDATED: Complete User Page List ---
$pages = [
    'index.php' => '🏠 User Dashboard (Main)',
    'smm_dashboard.php' => '🚀 SMM Dashboard',
    'smm_order.php' => '🛒 New SMM Order',
    'mass_order.php' => '📦 Mass Order',
    'smm_history.php' => '📜 Order History',
    'services.php' => '📋 SMM Services List',
    'sub_dashboard.php' => '👑 Subscriptions Dashboard',
    'sub_orders.php' => '📦 My Subscriptions',
    'add-funds.php' => '💰 Add Funds / Deposit',
    'downloads.php' => '📥 Digital Downloads Store',
    'my_downloads.php' => '📂 My Downloads',
    'ai_tools.php' => '🤖 AI Tools',
'about.php' => 'the owner', 
    'tickets.php' => '🎫 Support Tickets'
,
    'profile.php' => '👤 User Profile',
    'earn.php' => '💸 Affiliate / Earn',
    'transfer.php' => '💸 Transfer Funds',
    'spin_wheel.php' => '🎡 Spin & Win',
    'tools.php' => '🛠️ Helper Tools',
    'updates.php' => '📢 News & Updates',
    'tutorials.php' => '📚 Tutorials'
];

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_audio'])) {
    $page = $_POST['page_key'];
    $desc = sanitize($_POST['description']);
    
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
        $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
        $filename = $_FILES['audio_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = $page . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $upload_dir . $new_name)) {
                // Delete old if exists
                $old = $db->prepare("SELECT audio_file FROM page_audios WHERE page_key = ?");
                $old->execute([$page]);
                $old_file = $old->fetchColumn();
                if($old_file && file_exists($upload_dir.$old_file)) { @unlink($upload_dir.$old_file); }

                // Insert/Update DB
                $stmt = $db->prepare("INSERT INTO page_audios (page_key, audio_file, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE audio_file = ?, description = ?");
                $stmt->execute([$page, $new_name, $desc, $new_name, $desc]);
                
                $success = "Audio Guide set for " . $pages[$page];
            } else { $error = "Upload failed. Check folder permissions."; }
        } else { $error = "Invalid format! Only MP3, WAV, OGG allowed."; }
    } else { $error = "Please select a file."; }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT audio_file FROM page_audios WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetchColumn();
    if ($file) {
        if(file_exists($upload_dir . $file)) @unlink($upload_dir . $file);
        $db->prepare("DELETE FROM page_audios WHERE id = ?")->execute([$id]);
        $success = "Audio guide removed successfully.";
    }
}

// Fetch Existing Audios
$audios = $db->query("SELECT * FROM page_audios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root { --brand-color: #6366f1; --brand-dark: #4f46e5; }
    
    .audio-mgr-wrapper { max-width: 1100px; margin: 0 auto; }
    
    /* Header */
    .page-head { 
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 40px; 
        border-radius: 24px; color: white; margin-bottom: 30px; 
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.3); position: relative; overflow: hidden;
    }
    .page-head::after {
        content: '🔊'; position: absolute; right: 20px; bottom: -20px; font-size: 10rem; opacity: 0.1; transform: rotate(-20deg);
    }
    
    /* Upload Card */
    .upload-card {
        background: #fff; border-radius: 24px; padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
        display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center;
    }
    
    .form-col h3 { margin-top: 0; font-weight: 800; color: #1e293b; }
    .modern-input {
        width: 100%; padding: 15px; border: 2px solid #f1f5f9; border-radius: 12px;
        font-size: 1rem; margin-bottom: 15px; transition: 0.3s; outline: none;
    }
    .modern-input:focus { border-color: var(--brand-color); background: #f8fafc; }
    
    .file-drop-area {
        border: 2px dashed #cbd5e1; padding: 30px; text-align: center; border-radius: 16px;
        cursor: pointer; transition: 0.3s; background: #f8fafc;
    }
    .file-drop-area:hover { border-color: var(--brand-color); background: #eef2ff; }
    
    .btn-upload {
        width: 100%; padding: 15px; background: var(--brand-color); color: white;
        border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer;
        box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4); transition: 0.2s;
    }
    .btn-upload:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.5); }

    /* Table Card */
    .list-card {
        background: #fff; border-radius: 24px; padding: 0; overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-top: 30px;
    }
    .list-header { padding: 20px 30px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #64748b; }
    
    .audio-row { display: flex; align-items: center; padding: 20px 30px; border-bottom: 1px solid #f1f5f9; transition: 0.2s; }
    .audio-row:hover { background: #fcfaff; }
    .audio-row:last-child { border-bottom: none; }
    
    .page-info { flex: 1; }
    .page-name { font-weight: 700; color: #1e293b; display: block; font-size: 1rem; }
    .page-desc { font-size: 0.85rem; color: #64748b; }
    
    .audio-player { margin: 0 20px; }
    .btn-del { 
        background: #fee2e2; color: #ef4444; width: 40px; height: 40px; 
        display: flex; align-items: center; justify-content: center; 
        border-radius: 10px; text-decoration: none; transition: 0.2s; 
    }
    .btn-del:hover { background: #ef4444; color: white; }
</style>

<div class="audio-mgr-wrapper">
    
    <div class="page-head">
        <h1 style="margin:0;">🔊 Audio Guide Manager</h1>
        <p style="opacity:0.8; margin-top:5px;">Set voice instructions for specific pages to help your users.</p>
    </div>

    <?php if($error): ?><div class="message error"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="message success"><?= $success ?></div><?php endif; ?>

    <div class="upload-card">
        <div class="form-col">
            <h3>Upload New Guide</h3>
            <p style="color:#64748b; margin-bottom:20px;">Select a page and upload an MP3 file.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <label style="font-weight:600; font-size:0.9rem;">Target Page</label>
                <select name="page_key" class="modern-input" required>
                    <option value="">-- Choose Page --</option>
                    <?php foreach($pages as $key => $name): ?>
                        <option value="<?= $key ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>

                <label style="font-weight:600; font-size:0.9rem;">Title / Message</label>
                <input type="text" name="description" class="modern-input" placeholder="e.g. Listen: How to Deposit Funds" required>
                
                <input type="file" name="audio_file" id="fileInp" accept="audio/*" style="display:none;" required onchange="updateFileName()">
                <div class="file-drop-area" onclick="document.getElementById('fileInp').click()">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:var(--brand-color);"></i>
                    <p id="fileName" style="margin:10px 0 0 0; font-weight:600; color:#475569;">Click to select Audio File</p>
                </div>
                <br>
                <button type="submit" name="upload_audio" class="btn-upload">🚀 Publish Audio</button>
            </form>
        </div>
        <div class="img-col" style="text-align:center;">
            <img src="https://cdn-icons-png.flaticon.com/512/3048/3048374.png" width="250" style="opacity:0.8;">
        </div>
    </div>

    <div class="list-card">
        <div class="list-header">Active Audio Guides (<?= count($audios) ?>)</div>
        
        <?php if(empty($audios)): ?>
            <div style="padding:40px; text-align:center; color:#94a3b8;">No audio guides added yet.</div>
        <?php else: ?>
            <?php foreach($audios as $a): ?>
            <div class="audio-row">
                <div class="page-info">
                    <span class="page-name"><?= $pages[$a['page_key']] ?? $a['page_key'] ?></span>
                    <span class="page-desc">Note: <?= htmlspecialchars($a['description']) ?></span>
                </div>
                <div class="audio-player">
                    <audio controls style="height:35px; border-radius:20px; width:250px;">
                        <source src="../assets/uploads/audio/<?= htmlspecialchars($a['audio_file']) ?>" type="audio/mpeg">
                    </audio>
                </div>
                <a href="?delete=<?= $a['id'] ?>" class="btn-del" onclick="return confirm('Delete this audio?')" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function updateFileName() {
    const input = document.getElementById('fileInp');
    const nameDisplay = document.getElementById('fileName');
    if(input.files.length > 0) {
        nameDisplay.innerText = "Selected: " + input.files[0].name;
        nameDisplay.style.color = "#16a34a";
    }
}
</script>

<?php include '_footer.php'; ?>