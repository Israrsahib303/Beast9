<?php
include '_header.php';

$success = '';
$error = '';

// --- SAVE SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $google_login = isset($_POST['google_login']) ? '1' : '0';
    $client_id = trim($_POST['client_id']);
    $client_secret = trim($_POST['client_secret']);

    try {
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$google_login, 'google_login']);
        $stmt->execute([$client_id, 'google_client_id']);
        $stmt->execute([$client_secret, 'google_client_secret']);
        
        // Update Global Var
        $GLOBALS['settings']['google_login'] = $google_login;
        $GLOBALS['settings']['google_client_id'] = $client_id;
        $GLOBALS['settings']['google_client_secret'] = $client_secret;

        $success = "Settings saved successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Current Values
$g_enabled = $GLOBALS['settings']['google_login'] ?? '0';
$g_id = $GLOBALS['settings']['google_client_id'] ?? '';
$g_secret = $GLOBALS['settings']['google_client_secret'] ?? '';

// Auto-detect Redirect URI for Display
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$redirect_uri = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/google_callback.php";
?>

<div class="container-fluid" style="padding:30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="font-weight:800; color:#1f2937; margin:0;">🔑 Google Login Settings</h2>
            <p style="color:#6b7280; margin:0;">Manage Sign in with Google configuration.</p>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius:16px; padding:20px;">
        <form method="POST">
            
            <div class="form-check form-switch mb-4" style="background:#f0fdf4; padding:15px 20px 15px 50px; border-radius:10px; border:1px solid #bbf7d0;">
                <input class="form-check-input" type="checkbox" id="google_login" name="google_login" style="width: 50px; height: 25px; margin-left: -40px;" <?= $g_enabled == '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold ms-2" for="google_login" style="font-size:1.1rem; color:#166534;">Enable Sign in with Google</label>
            </div>

            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Redirect URI (Copy this to Google Console)</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light" value="<?= $redirect_uri ?>" readonly id="redirectUrl">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyUrl()">Copy</button>
                    </div>
                    <small class="text-muted">Paste this exact URL in "Authorized redirect URIs" in Google Cloud Console.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Client ID</label>
                    <input type="text" name="client_id" class="form-control" value="<?= $g_id ?>" placeholder="xxxxxxxx.apps.googleusercontent.com" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Client Secret</label>
                    <input type="text" name="client_secret" class="form-control" value="<?= $g_secret ?>" placeholder="GOCSPX-xxxxxxx" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">💾 Save Settings</button>
            </div>
        </form>
    </div>
    
    <div class="mt-4 p-3" style="background:#fff; border-radius:16px; border:1px solid #e5e7eb;">
        <h5 class="fw-bold">❓ How to setup?</h5>
        <ol style="color:#4b5563; line-height:1.6;">
            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
            <li>Create a new Project & go to <b>APIs & Services > Credentials</b>.</li>
            <li>Create <b>OAuth Client ID</b> (Application Type: Web application).</li>
            <li>Add your website URL in "Authorized JavaScript origins".</li>
            <li>Add the <b>Redirect URI</b> (from above) in "Authorized redirect URIs".</li>
            <li>Copy <b>Client ID</b> & <b>Client Secret</b> and paste them here.</li>
        </ol>
    </div>
</div>

<script>
function copyUrl() {
    var copyText = document.getElementById("redirectUrl");
    copyText.select();
    document.execCommand("copy");
    alert("URL Copied! Paste this in Google Cloud Console.");
}
</script>

<?php include '_footer.php'; ?>