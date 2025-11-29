<?php
include '_header.php';

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    
    // Increase Limits
    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    ignore_user_abort(true);
    header('Content-Type: application/json');

    // Load Settings
    $token = $_POST['gh_token'];
    $repo = $_POST['gh_repo'];
    $branch = $_POST['gh_branch'];
    $action = $_POST['ajax_action'];

    // Save Settings Update
    try {
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_token', ?)")->execute([$token]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_repo', ?)")->execute([$repo]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_branch', ?)")->execute([$branch]);
    } catch (Exception $e) {}

    $response = ['status' => 'error', 'msg' => 'Unknown Error'];

    // --- HELPER FUNCTIONS ---
    function uploadAPI($localPath, $remotePath, $token, $repo, $branch) {
        $url = "https://api.github.com/repos/$repo/contents/$remotePath";
        $content = base64_encode(file_get_contents($localPath));
        
        // Get SHA
        $ch = curl_init($url . "?ref=" . $branch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token $token", "User-Agent: Beast8"]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $sha = $res['sha'] ?? null;

        // PUT
        $data = ['message' => "Sync: $remotePath", 'content' => $content, 'branch' => $branch];
        if ($sha) $data['sha'] = $sha;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token $token", "User-Agent: Beast8", "Accept: application/vnd.github.v3+json"]);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($code == 200 || $code == 201);
    }

    // --- ACTIONS ---
    if ($action == 'sync_files') {
        $root = realpath(__DIR__ . '/../');
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root), RecursiveIteratorIterator::LEAVES_ONLY);
        $count = 0;
        
        foreach ($iter as $file) {
            if (!$file->isDir()) {
                $path = $file->getRealPath();
                $rel = substr($path, strlen($root) + 1);
                $rel = str_replace('\\', '/', $rel);
                
                if (strpos($path, 'error_log') !== false || strpos($path, '.git') !== false || strpos($path, 'vendor') !== false) continue;

                if (uploadAPI($path, $rel, $token, $repo, $branch)) $count++;
            }
        }
        $response = ['status' => 'success', 'msg' => "$count Files Synced!"];
    }

    if ($action == 'sync_sql') {
        // SQL Generation Logic
        require_once __DIR__ . '/../includes/config.php';
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $tables = [];
        $q = $mysqli->query('SHOW TABLES');
        while($r = $q->fetch_row()) $tables[] = $r[0];
        
        $sql = "-- Sync: " . date('Y-m-d H:i') . "\n\n";
        foreach($tables as $t) {
            $row2 = $mysqli->query('SHOW CREATE TABLE '.$t)->fetch_row();
            $sql .= "\n\n".$row2[1].";\n\n";
            $res = $mysqli->query('SELECT * FROM '.$t);
            while($row = $res->fetch_row()) {
                $sql .= "INSERT INTO $t VALUES('" . implode("','", array_map([$mysqli, 'real_escape_string'], $row)) . "');\n";
            }
        }
        $tmp = __DIR__ . '/temp.sql';
        file_put_contents($tmp, $sql);
        
        if (uploadAPI($tmp, 'database.sql', $token, $repo, $branch)) {
            $response = ['status' => 'success', 'msg' => "Database Synced!"];
        } else {
            $response = ['status' => 'error', 'msg' => "GitHub Upload Failed"];
        }
        unlink($tmp);
    }

    if ($action == 'sync_tree') {
        require_once __DIR__ . '/generate_tree.php';
        $tree = getDirectoryTree(realpath(__DIR__ . '/../'));
        $tmp = __DIR__ . '/tree.txt';
        file_put_contents($tmp, $tree);
        
        if (uploadAPI($tmp, 'file_structure.txt', $token, $repo, $branch)) {
            $response = ['status' => 'success', 'msg' => "Structure Synced!"];
        } else {
            $response = ['status' => 'error', 'msg' => "GitHub Upload Failed"];
        }
        unlink($tmp);
    }

    echo json_encode($response);
    exit;
}

// Fetch Saved Data
$gh = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gh_token', 'gh_repo', 'gh_branch')");
while($r = $stmt->fetch()) { $gh[$r['setting_key']] = $r['setting_value']; }
?>

<style>
.gh-wrapper { max-width: 900px; margin: 2rem auto; }
.gh-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; }
.gh-header { background: #24292f; padding: 1.5rem; color: white; display: flex; align-items: center; justify-content: space-between; }
.gh-header h2 { margin: 0; font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

.gh-config { padding: 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
.config-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; }
.form-control { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.9rem; }

.sync-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; padding: 2rem; }
.sync-box { 
    border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; text-align: center; 
    transition: 0.3s; background: #fff; position: relative;
}
.sync-box:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border-color: #6366f1; }
.sync-icon { font-size: 2.5rem; margin-bottom: 15px; color: #64748b; }
.sync-title { font-weight: 800; color: #1e293b; margin-bottom: 5px; }
.sync-desc { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem; }

.btn-sync {
    width: 100%; padding: 10px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer;
    transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-code { background: #e0e7ff; color: #4338ca; }
.btn-code:hover { background: #4338ca; color: white; }

.btn-sql { background: #eff6ff; color: #1d4ed8; }
.btn-sql:hover { background: #1d4ed8; color: white; }

.btn-tree { background: #fff7ed; color: #c2410c; }
.btn-tree:hover { background: #c2410c; color: white; }

/* Loading State */
.btn-sync.loading { opacity: 0.7; cursor: wait; }
.spinner { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

@media(max-width: 768px) { .config-grid { grid-template-columns: 1fr; } }
</style>

<div class="gh-wrapper">
    <div class="gh-card">
        <div class="gh-header">
            <h2><i class="fa-brands fa-github"></i> GitHub Live Sync</h2>
            <span style="font-size:0.8rem; background:rgba(255,255,255,0.1); padding:5px 10px; border-radius:20px;">Connected</span>
        </div>

        <div class="gh-config">
            <div class="config-grid">
                <input type="password" id="gh_token" class="form-control" placeholder="Access Token" value="<?= htmlspecialchars($gh['gh_token']??'') ?>">
                <input type="text" id="gh_repo" class="form-control" placeholder="user/repo" value="<?= htmlspecialchars($gh['gh_repo']??'') ?>">
                <input type="text" id="gh_branch" class="form-control" placeholder="Branch" value="<?= htmlspecialchars($gh['gh_branch']??'main') ?>">
            </div>
            <small style="color:#64748b; display:block; margin-top:5px;">* Settings auto-save on sync.</small>
        </div>

        <div class="sync-grid">
            
            <div class="sync-box">
                <i class="fa-solid fa-code sync-icon"></i>
                <div class="sync-title">Sync Code</div>
                <div class="sync-desc">Push all PHP/HTML/CSS files.</div>
                <button class="btn-sync btn-code" onclick="runSync('sync_files', this)">
                    <i class="fa-solid fa-rotate"></i> Sync Files
                </button>
            </div>

            <div class="sync-box">
                <i class="fa-solid fa-database sync-icon"></i>
                <div class="sync-title">Sync Database</div>
                <div class="sync-desc">Push latest SQL dump.</div>
                <button class="btn-sync btn-sql" onclick="runSync('sync_sql', this)">
                    <i class="fa-solid fa-rotate"></i> Sync DB
                </button>
            </div>

            <div class="sync-box">
                <i class="fa-solid fa-folder-tree sync-icon"></i>
                <div class="sync-title">Sync Structure</div>
                <div class="sync-desc">Update file list for AI.</div>
                <button class="btn-sync btn-tree" onclick="runSync('sync_tree', this)">
                    <i class="fa-solid fa-rotate"></i> Sync Tree
                </button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function runSync(action, btn) {
    let token = document.getElementById('gh_token').value;
    let repo = document.getElementById('gh_repo').value;
    let branch = document.getElementById('gh_branch').value;

    if(!token || !repo) {
        Swal.fire('Missing Info', 'Please enter Token and Repo Name', 'warning');
        return;
    }

    // Loading State
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner spinner"></i> Syncing...';
    btn.classList.add('loading');
    btn.disabled = true;

    let formData = new FormData();
    formData.append('ajax_action', action);
    formData.append('gh_token', token);
    formData.append('gh_repo', repo);
    formData.append('gh_branch', branch);

    fetch('github_sync.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.classList.remove('loading');
        btn.disabled = false;

        if(data.status === 'success') {
            Swal.fire('Synced!', data.msg, 'success');
        } else {
            Swal.fire('Failed', data.msg, 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        Swal.fire('Error', 'Network or Server Error', 'error');
    });
}
</script>

<?php include '_footer.php'; ?>