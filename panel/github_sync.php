<?php
include '_header.php';

$msg = '';
$msg_type = '';

// --- HELPER: GET FILE CONTENT ---
function getFileContent($path) {
    return file_get_contents($path);
}

// --- HELPER: UPLOAD SINGLE FILE TO GITHUB ---
function uploadToGithub($localPath, $remotePath, $token, $repo, $branch) {
    $url = "https://api.github.com/repos/$repo/contents/$remotePath";
    $content = base64_encode(file_get_contents($localPath));
    
    // 1. Check if file exists (Get SHA)
    $ch = curl_init($url . "?ref=" . $branch);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $token",
        "User-Agent: Beast8-Panel"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response, true);
    $sha = $json['sha'] ?? null;

    // Skip if content hasn't changed (Optimization)
    // (Checking size/content match is hard without downloading, so we just overwrite for now to be safe)

    // 2. PUT Request
    $data = [
        'message' => "Sync: $remotePath",
        'content' => $content,
        'branch' => $branch
    ];
    if ($sha) $data['sha'] = $sha;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $token",
        "User-Agent: Beast8-Panel",
        "Accept: application/vnd.github.v3+json"
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode == 200 || $httpCode == 201);
}

// --- HELPER: SQL GENERATOR ---
function createSql($sqlPath) {
    require_once __DIR__ . '/../includes/config.php';
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) return false;

    $tables = [];
    $query = $mysqli->query('SHOW TABLES');
    while($row = $query->fetch_row()) { $tables[] = $row[0]; }
    
    $content = "-- Beast8 Auto-Backup\n-- Date: " . date('Y-m-d H:i') . "\n\n";
    foreach($tables as $table) {
        $res = $mysqli->query('SELECT * FROM '.$table);
        $row2 = $mysqli->query('SHOW CREATE TABLE '.$table)->fetch_row();
        $content .= "\n\n".$row2[1].";\n\n";
        while($row = $res->fetch_row()) {
            $content .= "INSERT INTO $table VALUES(";
            $esc = array_map([$mysqli, 'real_escape_string'], $row);
            $content .= "'" . implode("','", $esc) . "');\n";
        }
    }
    file_put_contents($sqlPath, $content);
    return true;
}

// --- MAIN HANDLE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_github'])) {
    
    // 1. Unlimited Time & Memory (Zaroori hai files loop ke liye)
    set_time_limit(0); 
    ini_set('memory_limit', '1024M');
    ignore_user_abort(true); // User tab band kare tab bhi chalta rahe

    $token = trim($_POST['gh_token']);
    $repo = trim($_POST['gh_repo']); 
    $branch = trim($_POST['gh_branch']) ?: 'main';
    $action = $_POST['action_type'];

    // Save Settings
    try {
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_token', ?)")->execute([$token]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_repo', ?)")->execute([$repo]);
        $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('gh_branch', ?)")->execute([$branch]);
    } catch (Exception $e) {}

    $successCount = 0;
    $failCount = 0;

    // --- A. DIRECT FILES SYNC ---
    if ($action == 'files') {
        $rootPath = realpath(__DIR__ . '/../');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                // Fix slashes for Windows/Linux consistency
                $relativePath = str_replace('\\', '/', $relativePath);

                // Skip Useless Files
                if (strpos($filePath, 'error_log') !== false) continue;
                if (strpos($filePath, '.git') !== false) continue;
                if (strpos($filePath, 'node_modules') !== false) continue;
                if (strpos($filePath, 'vendor') !== false) continue; // Skip heavy vendors
                if (strpos($filePath, 'backup_') !== false) continue;

                // Upload
                if (uploadToGithub($filePath, $relativePath, $token, $repo, $branch)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
        }
        $msg = "Sync Complete! Uploaded: $successCount files. Failed: $failCount.";
        $msg_type = ($successCount > 0) ? "success" : "danger";
    }

    // --- B. SQL BACKUP ---
    elseif ($action == 'sql') {
        $tempFile = __DIR__ . '/database.sql';
        if (createSql($tempFile)) {
            if (uploadToGithub($tempFile, 'database.sql', $token, $repo, $branch)) {
                $msg = "Database pushed successfully!"; $msg_type = "success";
            } else {
                $msg = "GitHub Upload Failed."; $msg_type = "danger";
            }
            unlink($tempFile);
        } else { $msg = "SQL Creation Failed."; $msg_type = "danger"; }
    }

    // --- C. TREE VIEW ---
    elseif ($action == 'tree') {
        require_once __DIR__ . '/generate_tree.php';
        if (!function_exists('getDirectoryTree')) { function getDirectoryTree($d){return "";} }
        $content = getDirectoryTree(realpath(__DIR__ . '/../'));
        
        $tempFile = __DIR__ . '/tree.txt';
        file_put_contents($tempFile, $content);

        if (uploadToGithub($tempFile, 'project_structure.txt', $token, $repo, $branch)) {
            $msg = "Tree structure uploaded!"; $msg_type = "success";
        } else {
            $msg = "GitHub Upload Failed."; $msg_type = "danger";
        }
        unlink($tempFile);
    }
}

// Fetch Data
$gh_settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('gh_token', 'gh_repo', 'gh_branch')");
while($row = $stmt->fetch()) { $gh_settings[$row['setting_key']] = $row['setting_value']; }
?>

<style>
/* UI Styles */
.gh-wrapper { max-width: 850px; margin: 2rem auto; }
.gh-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.gh-head { background: #1e293b; color: #fff; padding: 2rem; text-align: center; }
.gh-icon { font-size: 3rem; margin-bottom: 10px; }
.gh-title { font-size: 1.8rem; font-weight: 800; margin: 0; }

.gh-body { padding: 2rem; }
.form-group { margin-bottom: 1.5rem; }
.form-label { font-weight: 700; color: #374151; display: block; margin-bottom: 6px; }
.form-control { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; transition: 0.2s; }
.form-control:focus { border-color: #6366f1; }

/* Options Grid */
.opt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
.opt-label { cursor: pointer; position: relative; }
.opt-label input { position: absolute; opacity: 0; }
.opt-box {
    border: 2px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; text-align: center;
    transition: 0.2s; display: flex; flex-direction: column; align-items: center; height: 100%;
}
.opt-label input:checked + .opt-box { border-color: #6366f1; background: #eef2ff; color: #4f46e5; }
.opt-icon { font-size: 2rem; margin-bottom: 10px; }
.opt-text { font-weight: 700; font-size: 1rem; }
.opt-sub { font-size: 0.8rem; opacity: 0.8; }

.btn-push {
    width: 100%; padding: 16px; background: #6366f1; color: white;
    border: none; border-radius: 12px; font-weight: 800; font-size: 1.1rem;
    cursor: pointer; margin-top: 2rem; transition: 0.2s;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}
.btn-push:hover { background: #4f46e5; transform: translateY(-2px); }
.alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; text-align: center; }
.alert-success { background: #dcfce7; color: #166534; }
.alert-danger { background: #fee2e2; color: #991b1b; }

/* Overlay Loading */
.loading-overlay {
    position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.9);
    z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column;
}
.loader { width: 60px; height: 60px; border: 6px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="gh-wrapper">
    <?php if($msg): ?><div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div><?php endif; ?>

    <div class="gh-card">
        <div class="gh-head">
            <div class="gh-icon"><i class="fa-brands fa-github"></i></div>
            <h2 class="gh-title">GitHub Direct Sync</h2>
            <p style="opacity:0.8; margin:5px 0 0;">Upload files directly to your repository.</p>
        </div>

        <form method="POST" class="gh-body" onsubmit="showLoading()">
            <input type="hidden" name="upload_github" value="1">

            <div class="form-group">
                <label class="form-label">Access Token</label>
                <input type="password" name="gh_token" class="form-control" value="<?= htmlspecialchars($gh_settings['gh_token'] ?? '') ?>" placeholder="ghp_xxxxxxxxxxxx" required>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Repository</label>
                    <input type="text" name="gh_repo" class="form-control" value="<?= htmlspecialchars($gh_settings['gh_repo'] ?? '') ?>" placeholder="user/repo" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Branch</label>
                    <input type="text" name="gh_branch" class="form-control" value="<?= htmlspecialchars($gh_settings['gh_branch'] ?? 'main') ?>">
                </div>
            </div>

            <label class="form-label">What to Upload?</label>
            <div class="opt-grid">
                <label class="opt-label">
                    <input type="radio" name="action_type" value="files" checked>
                    <div class="opt-box">
                        <i class="fa-solid fa-code opt-icon"></i>
                        <span class="opt-text">Direct Files</span>
                        <span class="opt-sub">Upload all files individually</span>
                    </div>
                </label>
                <label class="opt-label">
                    <input type="radio" name="action_type" value="sql">
                    <div class="opt-box">
                        <i class="fa-solid fa-database opt-icon"></i>
                        <span class="opt-text">Database</span>
                        <span class="opt-sub">SQL Backup only</span>
                    </div>
                </label>
                <label class="opt-label">
                    <input type="radio" name="action_type" value="tree">
                    <div class="opt-box">
                        <i class="fa-solid fa-sitemap opt-icon"></i>
                        <span class="opt-text">Structure</span>
                        <span class="opt-sub">File Tree List</span>
                    </div>
                </label>
            </div>

            <button type="submit" class="btn-push">
                <i class="fa-solid fa-cloud-arrow-up"></i> Start Upload
            </button>
        </form>
    </div>
</div>

<div class="loading-overlay" id="loadingBox">
    <div class="loader"></div>
    <h3 style="margin-top:20px; color:#1e293b;">Syncing with GitHub...</h3>
    <p style="color:#64748b;">This may take a few minutes. Do not close.</p>
</div>

<script>
function showLoading() {
    document.getElementById('loadingBox').style.display = 'flex';
}
</script>

<?php include '_footer.php'; ?>