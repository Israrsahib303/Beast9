<?php
include '_header.php';
requireAdmin(); 

$success = '';
$error = '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_secret'])) {
    $title = sanitize($_POST['title']);
    $user = sanitize($_POST['username']);
    $pass = $_POST['secret_value'];
    $cat = sanitize($_POST['category']);
    
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE admin_vault SET title=?, username=?, secret_value=?, category=? WHERE id=?");
        if ($stmt->execute([$title, $user, $pass, $cat, $id])) $success = "Secret updated!";
    } else {
        $stmt = $db->prepare("INSERT INTO admin_vault (title, username, secret_value, category) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$title, $user, $pass, $cat])) $success = "New secret saved!";
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM admin_vault WHERE id=?")->execute([$id]);
    $success = "Item removed.";
}

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM admin_vault";
$params = [];
if ($search) { $sql .= " WHERE title LIKE ? OR category LIKE ?"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$secrets = $stmt->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700;900&display=swap" rel="stylesheet">
<style>
    :root { --vault-primary: #6366f1; --text-main: #1e293b; }
    body { font-family: 'Outfit', sans-serif; background: #f8fafc; }
    .vault-wrapper { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

    /* Header */
    .vault-header {
        display: flex; justify-content: space-between; align-items: center;
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        padding: 30px; border-radius: 24px; color: white; margin-bottom: 30px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4);
    }
    .vh-text h1 { margin: 0; font-size: 2rem; font-weight: 800; }

    /* Unique Button ID for JS Targeting */
    #btnOpenVault {
        background: var(--vault-primary); color: white; padding: 12px 25px;
        border-radius: 12px; font-weight: 700; border: none; cursor: pointer;
        display: flex; align-items: center; gap: 8px; transition: 0.3s;
        box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.5);
    }
    #btnOpenVault:hover { transform: translateY(-3px); }

    /* Search */
    .search-bar {
        background: white; padding: 15px; border-radius: 16px; margin-bottom: 30px;
        border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;
    }
    .search-input { border: none; width: 100%; font-size: 1rem; outline: none; }

    /* Grid */
    .vault-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

    /* Card */
    .secret-card {
        background: white; border-radius: 20px; padding: 25px;
        border: 1px solid #e2e8f0; transition: 0.3s; position: relative;
        display: flex; flex-direction: column; gap: 15px;
    }
    .secret-card:hover { transform: translateY(-5px); border-color: var(--vault-primary); }
    
    .sc-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .sc-icon { width: 50px; height: 50px; border-radius: 14px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .sc-cat { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; background: #eef2ff; color: #4f46e5; padding: 5px 10px; border-radius: 50px; }
    .sc-title { font-size: 1.2rem; font-weight: 800; color: #1e293b; margin: 10px 0 0; }
    
    .sc-field {
        background: #1e293b; border-radius: 12px; padding: 12px;
        display: flex; align-items: center; justify-content: space-between;
        color: #fff; margin-top: auto;
    }
    .sc-value { font-family: monospace; letter-spacing: 2px; font-size: 1.1rem; overflow: hidden; white-space: nowrap; width: 180px; }
    
    .btn-icon { background: rgba(255,255,255,0.1); border: none; width: 32px; height: 32px; border-radius: 8px; color: #fff; cursor: pointer; }
    .btn-icon:hover { background: rgba(255,255,255,0.3); }

    .card-opts { position: absolute; top: 20px; right: 20px; display: flex; gap: 5px; opacity: 0; transition: 0.2s; }
    .secret-card:hover .card-opts { opacity: 1; }
    .btn-opt { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; }

    /* --- CUSTOM MODAL (Unique Class Names) --- */
    .vault-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
        z-index: 999999; /* Highest Priority */
        display: none; /* Default Hidden */
        align-items: center; justify-content: center;
    }
    .vault-modal-box {
        background: white; width: 90%; max-width: 500px; padding: 30px; border-radius: 24px;
        box-shadow: 0 25px 50px -10px rgba(0,0,0,0.5); animation: vaultZoom 0.3s ease-out;
    }
    @keyframes vaultZoom { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .form-group { margin-bottom: 15px; }
    .form-label { display: block; font-weight: 700; margin-bottom: 5px; color: #475569; }
    .form-input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; }
    .form-input:focus { border-color: var(--vault-primary); }
</style>

<div class="vault-wrapper">
    <?php if($success): ?><div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; margin-bottom:20px;">✅ <?= $success ?></div><?php endif; ?>
    
    <div class="vault-header">
        <div class="vh-text">
            <h1>🔐 Secure Vault</h1>
            <p>Store sensitive keys & passwords.</p>
        </div>
        <button type="button" id="btnOpenVault"><i class="fa-solid fa-plus"></i> Add New</button>
    </div>

    <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
        <input type="text" class="search-input" placeholder="Search secrets..." onkeyup="filterSecrets(this.value)">
    </div>

    <div class="vault-grid">
        <?php if(empty($secrets)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:50px; color:#94a3b8;">Empty Vault. Add secrets securely.</div>
        <?php else: ?>
            <?php foreach($secrets as $s): 
                $icon = 'fa-key';
                if(stripos($s['category'], 'server')!==false) $icon='fa-server';
                if(stripos($s['category'], 'mail')!==false) $icon='fa-envelope';
                if(stripos($s['category'], 'db')!==false) $icon='fa-database';
            ?>
            <div class="secret-card" data-title="<?= strtolower($s['title']) ?>">
                <div class="card-opts">
                    <button type="button" class="btn-opt" onclick='editVaultSecret(<?= json_encode($s) ?>)'><i class="fa-solid fa-pen"></i></button>
                    <a href="?delete=<?= $s['id'] ?>" class="btn-opt" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></a>
                </div>
                
                <div class="sc-top">
                    <div class="sc-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                    <span class="sc-cat"><?= sanitize($s['category']) ?></span>
                </div>
                
                <div>
                    <h3 class="sc-title"><?= sanitize($s['title']) ?></h3>
                    <div style="color:#64748b; font-size:0.9rem;"><i class="fa-regular fa-user"></i> <?= sanitize($s['username']) ?></div>
                </div>

                <div class="sc-field">
                    <span class="sc-value" id="val_<?= $s['id'] ?>">••••••••••••</span>
                    <input type="hidden" id="real_<?= $s['id'] ?>" value="<?= sanitize($s['secret_value']) ?>">
                    <div style="display:flex; gap:5px;">
                        <button type="button" class="btn-icon" onclick="toggleViz(<?= $s['id'] ?>)"><i class="fa-regular fa-eye" id="icon_<?= $s['id'] ?>"></i></button>
                        <button type="button" class="btn-icon" onclick="copySecret(<?= $s['id'] ?>)"><i class="fa-regular fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="vault-modal-overlay" id="vaultModal">
    <div class="vault-modal-box">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTitle">Add New Secret</h3>
            <button type="button" id="btnCloseVault" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="save_secret" value="1">
            <input type="hidden" name="id" id="inp_id">

            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" id="inp_title" class="form-input" required>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="inp_cat" class="form-input">
                        <option value="General">General</option>
                        <option value="Server/Hosting">Server/Hosting</option>
                        <option value="Email/SMTP">Email/SMTP</option>
                        <option value="API Keys">API Keys</option>
                        <option value="Database">Database</option>
                        <option value="Wallet">Wallet/Bank</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="inp_user" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Secret / Password</label>
                <textarea name="secret_value" id="inp_val" class="form-input" rows="3" required style="font-family:monospace;"></textarea>
            </div>

            <button type="submit" style="width:100%; padding:15px; background:var(--vault-primary); color:white; border:none; border-radius:12px; font-weight:bold; cursor:pointer;">Save Securely</button>
        </form>
    </div>
</div>

<script>
// Direct Event Listeners (Conflict Proof)
document.addEventListener('DOMContentLoaded', function() {
    
    const modal = document.getElementById('vaultModal');
    const btnOpen = document.getElementById('btnOpenVault');
    const btnClose = document.getElementById('btnCloseVault');

    if(btnOpen) {
        btnOpen.addEventListener('click', function() {
            resetForm();
            modal.style.display = 'flex';
        });
    }

    if(btnClose) {
        btnClose.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target == modal) {
            modal.style.display = 'none';
        }
    });
});

// Helper Functions
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add New Secret';
    document.getElementById('inp_id').value = '';
    document.getElementById('inp_title').value = '';
    document.getElementById('inp_user').value = '';
    document.getElementById('inp_val').value = '';
}

function editVaultSecret(data) {
    document.getElementById('modalTitle').innerText = 'Edit Secret';
    document.getElementById('inp_id').value = data.id;
    document.getElementById('inp_title').value = data.title;
    document.getElementById('inp_user').value = data.username;
    document.getElementById('inp_val').value = data.secret_value;
    document.getElementById('inp_cat').value = data.category;
    document.getElementById('vaultModal').style.display = 'flex';
}

function toggleViz(id) {
    let field = document.getElementById('val_' + id);
    let real = document.getElementById('real_' + id).value;
    let icon = document.getElementById('icon_' + id);
    if (field.innerText.includes('•••')) {
        field.innerText = real;
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        field.innerText = '••••••••••••';
        icon.className = 'fa-regular fa-eye';
    }
}

function copySecret(id) {
    let real = document.getElementById('real_' + id).value;
    navigator.clipboard.writeText(real);
    alert('Copied!');
}

function filterSecrets(q) {
    let term = q.toLowerCase();
    document.querySelectorAll('.secret-card').forEach(card => {
        card.style.display = card.dataset.title.includes(term) ? 'flex' : 'none';
    });
}
</script>

<?php include '_footer.php'; ?>