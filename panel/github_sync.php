<?php
include '_header.php';

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    
    // Server Constraints Remove
    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    ignore_user_abort(true);
    header('Content-Type: application/json');

    // Settings
    $token = $_POST['gh_token'];
    $repo = $_POST['gh_repo'];
    $branch = $_POST['gh_branch'];
    $action = $_POST['ajax_action'];

    // Save Settings
    try {
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_token', ?)")->execute([$token]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_repo', ?)")->execute([$repo]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_branch', ?)")->execute([$branch]);
    } catch (Exception $e) {}

    // --- HELPER: GET GIT SHA1 (Smart Check) ---
    function getGitHash($content) {
        return sha1("blob " . strlen($content) . "\0" . $content);
    }

    // --- HELPER: UPLOAD FILE ---
    function uploadToGitHub($localPath, $remotePath, $token, $repo, $branch, $remoteSha = null) {
        $url = "https://api.github.com/repos/$repo/contents/$remotePath";
        $content = file_get_contents($localPath);
        $base64 = base64_encode($content);
        
        $data = [
            'message' => "Update: $remotePath",
            'content' => $base64,
            'branch' => $branch
        ];
        if ($remoteSha) {
            $data['sha'] = $remoteSha;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token $token",
            "User-Agent: Beast9-Sync",
            "Accept: application/vnd.github.v3+json"
        ]);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($code == 200 || $code == 201);
    }

    // --- FETCH REMOTE TREE (To check what changed) ---
    function getRemoteFileMap($token, $repo, $branch) {
        $url = "https://api.github.com/repos/$repo/git/trees/$branch?recursive=1";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token $token",
            "User-Agent: Beast9-Sync"
        ]);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $map = [];
        if (isset($res['tree'])) {
            foreach ($res['tree'] as $node) {
                if ($node['type'] === 'blob') {
                    $map[$node['path']] = $node['sha'];
                }
            }
        }
        return $map;
    }

    // --- MAIN SYNC LOGIC ---
    $logs = [];
    $synced_count = 0;

    // 1. DATABASE GENERATION
    if ($action == 'sync_sql' || $action == 'sync_all') {
        require_once __DIR__ . '/../includes/config.php';
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $tables = [];
        $q = $mysqli->query('SHOW TABLES');
        while($r = $q->fetch_row()) $tables[] = $r[0];
        
        $sql = "-- Auto Sync SQL: " . date('Y-m-d H:i') . "\n\n";
        foreach($tables as $t) {
            $row2 = $mysqli->query('SHOW CREATE TABLE '.$t)->fetch_row();
            $sql .= "\n\n".$row2[1].";\n\n";
            $res = $mysqli->query('SELECT * FROM '.$t);
            while($row = $res->fetch_row()) {
                $sql .= "INSERT INTO $t VALUES('" . implode("','", array_map([$mysqli, 'real_escape_string'], $row)) . "');\n";
            }
        }
        $tmpSql = __DIR__ . '/database_sync.sql';
        file_put_contents($tmpSql, $sql);
        
        // Upload SQL
        // We do a simple upload here (overwrite)
        // Get existing SQL sha first to update
        $map = getRemoteFileMap($token, $repo, $branch);
        $remoteSha = $map['database.sql'] ?? null;
        
        if (uploadToGitHub($tmpSql, 'database.sql', $token, $repo, $branch, $remoteSha)) {
            $logs[] = "✅ Database Synced";
        } else {
            $logs[] = "❌ Database Failed";
        }
        unlink($tmpSql);
    }

    // 2. TREE STRUCTURE GENERATION
    if ($action == 'sync_tree' || $action == 'sync_all') {
        require_once __DIR__ . '/generate_tree.php';
        $root = realpath(__DIR__ . '/../');
        $treeData = getDirectoryTree($root);
        $tmpTree = __DIR__ . '/tree_sync.txt';
        file_put_contents($tmpTree, $treeData);

        $map = $map ?? getRemoteFileMap($token, $repo, $branch);
        $remoteSha = $map['file_structure.txt'] ?? null;

        if (uploadToGitHub($tmpTree, 'file_structure.txt', $token, $repo, $branch, $remoteSha)) {
            $logs[] = "✅ Structure Synced";
        }
        unlink($tmpTree);
    }

    // 3. FILES SYNC (SMART CHECK)
    if ($action == 'sync_files' || $action == 'sync_all') {
        $root = realpath(__DIR__ . '/../');
        $map = $map ?? getRemoteFileMap($token, $repo, $branch); // Re-use map if fetched
        
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root), RecursiveIteratorIterator::LEAVES_ONLY);
        
        foreach ($iter as $file) {
            if (!$file->isDir()) {
                $path = $file->getRealPath();
                $rel = substr($path, strlen($root) + 1);
                $rel = str_replace('\\', '/', $rel); // GitHub uses forward slashes
                
                // Exclusions
                if (strpos($path, 'error_log') !== false || 
                    strpos($path, '.git') !== false || 
                    strpos($path, 'vendor') !== false || 
                    strpos($path, '.zip') !== false ||
                    filesize($path) > 50 * 1024 * 1024) { // Skip files > 50MB
                    continue;
                }

                // SMART CHECK: Compare Local SHA with Remote SHA
                $content = file_get_contents($path);
                $localSha = getGitHash($content);
                $remoteSha = $map[$rel] ?? null;

                if ($localSha === $remoteSha) {
                    // Content is same, SKIP
                    continue; 
                }

                // Content changed or new file -> UPLOAD
                if (uploadToGitHub($path, $rel, $token, $repo, $branch, $remoteSha)) {
                    $synced_count++;
                }
            }
        }
        $logs[] = "✅ Synced $synced_count Updated Files";
    }

    echo json_encode(['status' => 'success', 'msg' => implode("<br>", $logs)]);
    exit;
}

// Get Settings
$gh = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gh_token', 'gh_repo', 'gh_branch')");
while($r = $stmt->fetch()) { $gh[$r['setting_key']] = $r['setting_value']; }
?>

<style>
.gh-wrapper { max-width: 1000px; margin: 2rem auto; font-family: 'Plus Jakarta Sans', sans-serif; }
.gh-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0; margin-bottom: 25px; }
.gh-header { background: #24292f; padding: 1.5rem; color: white; display: flex; align-items: center; justify-content: space-between; }
.gh-header h2 { margin: 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 12px; }

.gh-config { padding: 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.config-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; }
.form-control { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.95rem; }

/* MASTER BUTTON */
.master-sync-area { padding: 2rem; text-align: center; background: linear-gradient(180deg, #fff 0%, #f1f5f9 100%); border-bottom: 1px solid #e2e8f0; }
.btn-master {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: white; font-size: 1.2rem; padding: 15px 40px; border-radius: 50px;
    border: none; cursor: pointer; font-weight: 800; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
    transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 12px;
}
.btn-master:hover { transform: translateY(-3px); box-shadow: 0 15px 35px -5px rgba(37, 99, 235, 0.5); }
.btn-master:disabled { opacity: 0.7; cursor: wait; transform: none; }

/* GRID BUTTONS */
.sync-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 2rem; }
.sync-box { 
    border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center; 
    transition: 0.2s; background: #fff; 
}
.sync-box:hover { border-color: #94a3b8; transform: translateY(-2px); }
.sync-title { font-weight: 700; color: #334155; margin-bottom: 5px; }
.btn-sm { 
    padding: 8px 15px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; 
    color: #475569; font-weight: 600; cursor: pointer; margin-top: 10px; width: 100%;
    transition: 0.2s;
}
.btn-sm:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }

.spinner { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

@media(max-width: 768px) { 
    .config-grid { grid-template-columns: 1fr; } 
    .sync-grid { grid-template-columns: 1fr; }
}
</style>

<div class="gh-wrapper">
    <div class="gh-card">
        <div class="gh-header">
            <h2><i class="fa-brands fa-github"></i> GitHub Cloud Sync</h2>
            <div style="font-size:0.8rem; background:rgba(255,255,255,0.15); padding:5px 12px; border-radius:20px;">V3.0 Smart Sync</div>
        </div>

        <div class="gh-config">
            <div class="config-grid">
                <input type="password" id="gh_token" class="form-control" placeholder="GitHub Token (ghp_...)" value="<?= htmlspecialchars($gh['gh_token']??'') ?>">
                <input type="text" id="gh_repo" class="form-control" placeholder="username/repo" value="<?= htmlspecialchars($gh['gh_repo']??'') ?>">
                <input type="text" id="gh_branch" class="form-control" placeholder="main" value="<?= htmlspecialchars($gh['gh_branch']??'main') ?>">
            </div>
        </div>

        <div class="master-sync-area">
            <p style="color:#64748b; margin-bottom:15px;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> 
                Automatically detects changes and pushes <b>Files, Database & Structure</b>.
            </p>
            <button class="btn-master" onclick="runSync('sync_all', this)">
                <i class="fa-solid fa-cloud-arrow-up"></i> SYNC EVERYTHING TO GITHUB
            </button>
        </div>

        <div class="sync-grid">
            <div class="sync-box">
                <i class="fa-solid fa-code" style="font-size:2rem; color:#6366f1;"></i>
                <div class="sync-title">Files Only</div>
                <button class="btn-sm" onclick="runSync('sync_files', this)">Sync Files</button>
            </div>
            <div class="sync-box">
                <i class="fa-solid fa-database" style="font-size:2rem; color:#ec4899;"></i>
                <div class="sync-title">Database Only</div>
                <button class="btn-sm" onclick="runSync('sync_sql', this)">Sync DB</button>
            </div>
            <div class="sync-box">
                <i class="fa-solid fa-folder-tree" style="font-size:2rem; color:#f59e0b;"></i>
                <div class="sync-title">Structure Only</div>
                <button class="btn-sm" onclick="runSync('sync_tree', this)">Sync Tree</button>
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
        Swal.fire('Settings Missing', 'Please enter GitHub Token and Repo Name', 'warning');
        return;
    }

    // Lock Button
    let originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch spinner"></i> Processing...';
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
        btn.innerHTML = originalHTML;
        btn.disabled = false;

        if(data.status === 'success') {
            Swal.fire({
                title: 'Sync Complete!',
                html: data.msg,
                icon: 'success'
            });
        } else {
            Swal.fire('Failed', data.msg || 'Unknown Error', 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
        Swal.fire('Error', 'Connection Error (Check Console)', 'error');
        console.error(err);
    });
}
</script>

<?php include '_footer.php'; ?>