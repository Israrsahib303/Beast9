<?php
include '_header.php';

$success = '';
$error = '';

// --- HANDLE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_video'])) {
    $link = trim($_POST['video_link']);
    $platform = sanitize($_POST['platform']);
    
    if (empty($link) || !filter_var($link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid Video URL.";
    } else {
        $check = $db->prepare("SELECT id FROM user_testimonials WHERE video_link = ?");
        $check->execute([$link]);
        if($check->rowCount() > 0) {
            $error = "This video has already been submitted.";
        } else {
            $stmt = $db->prepare("INSERT INTO user_testimonials (user_id, video_link, platform, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $link, $platform]);
            $success = "Submitted! Admin will review it soon.";
        }
    }
}

// Fetch History
$history = $db->prepare("SELECT * FROM user_testimonials WHERE user_id = ? ORDER BY id DESC");
$history->execute([$user_id]);
$logs = $history->fetchAll();
?>

<style>
    /* --- COMPACT LAYOUT --- */
    .earn-container { max-width: 900px; margin: 20px auto; padding: 0 15px; }
    
    /* 1. HERO SECTION (Small & Clean) */
    .earn-hero {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-radius: 16px; padding: 25px; color: #fff;
        margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
    }
    .eh-text h1 { font-size: 1.5rem; font-weight: 800; margin: 0; }
    .eh-text p { font-size: 0.9rem; opacity: 0.9; margin: 5px 0 0; }
    .eh-badge { background: #fff; color: #6366f1; padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 0.9rem; }

    /* 2. STEPS (4 in 1 Row) */
    .steps-grid { 
        display: grid; grid-template-columns: repeat(4, 1fr); 
        gap: 15px; margin-bottom: 25px; 
    }
    .step-card {
        background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;
        text-align: center; position: relative;
    }
    .step-num { 
        position: absolute; top: 5px; right: 8px; font-size: 1.5rem; 
        font-weight: 900; color: #f1f5f9; line-height: 1;
    }
    .step-icon { font-size: 1.5rem; margin-bottom: 5px; display: block; }
    .step-title { font-weight: 700; font-size: 0.85rem; color: #1e293b; display: block; }
    .step-desc { font-size: 0.7rem; color: #64748b; line-height: 1.2; }

    /* 3. FORM & SELECTOR */
    .submit-box {
        background: #fff; padding: 25px; border-radius: 16px; 
        border: 1px solid #e2e8f0; margin-bottom: 30px;
    }
    .sb-title { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 15px; display: block; }
    
    /* Platform Selector (Visual) */
    .plat-grid { display: flex; gap: 15px; margin-bottom: 20px; }
    .plat-item {
        flex: 1; border: 2px solid #f1f5f9; border-radius: 12px; padding: 10px;
        cursor: pointer; text-align: center; transition: 0.2s; opacity: 0.7;
    }
    .plat-item:hover { opacity: 1; background: #f8fafc; }
    .plat-item.active { border-color: #6366f1; background: #eef2ff; opacity: 1; }
    
    .plat-img { width: 28px; height: 28px; object-fit: contain; margin-bottom: 5px; display: block; margin: 0 auto 5px auto; }
    .plat-name { font-size: 0.75rem; font-weight: 700; color: #334155; }

    .form-input {
        width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px;
        font-size: 0.9rem; outline: none; transition: 0.2s; margin-bottom: 15px;
    }
    .form-input:focus { border-color: #6366f1; }

    .btn-submit {
        width: 100%; padding: 12px; background: #1e293b; color: #fff; border: none;
        border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 0.95rem;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-submit:hover { background: #4f46e5; }

    /* 4. HISTORY LIST (Compact) */
    .h-list { display: flex; flex-direction: column; gap: 10px; }
    .h-item {
        background: #fff; padding: 10px 15px; border-radius: 10px; border: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between;
    }
    .hi-left { display: flex; align-items: center; gap: 12px; }
    .hi-icon { width: 32px; height: 32px; object-fit: contain; border-radius: 6px; }
    .hi-info h4 { margin: 0; font-size: 0.85rem; font-weight: 700; color: #334155; }
    .hi-info a { font-size: 0.75rem; color: #6366f1; text-decoration: none; }
    
    .h-status { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; }
    .st-pending { background: #fff7ed; color: #c2410c; }
    .st-approved { background: #dcfce7; color: #15803d; }
    .st-rejected { background: #fef2f2; color: #b91c1c; }
    .h-amt { font-weight: 700; color: #16a34a; font-size: 0.9rem; }

    @media (max-width: 600px) {
        .earn-hero { flex-direction: column; text-align: center; gap: 15px; }
        .steps-grid { grid-template-columns: 1fr 1fr; } /* 2x2 on mobile */
        .plat-grid { overflow-x: auto; }
        .plat-item { min-width: 80px; }
    }
</style>

<div class="main-content-wrapper">
    <div class="earn-container">
        
        <div class="earn-hero">
            <div class="eh-text">
                <h1>Make Video & Earn</h1>
                <p>Review our site and get free balance instantly.</p>
            </div>
            <div class="eh-badge">💰 Reward: Rs 500+</div>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <span class="step-num">1</span>
                <span class="step-icon">🎥</span>
                <span class="step-title">Create</span>
                <span class="step-desc">Record a positive video review.</span>
            </div>
            <div class="step-card">
                <span class="step-num">2</span>
                <span class="step-icon">📤</span>
                <span class="step-title">Upload</span>
                <span class="step-desc">Post on TikTok or YouTube.</span>
            </div>
            <div class="step-card">
                <span class="step-num">3</span>
                <span class="step-icon">🔗</span>
                <span class="step-title">Submit</span>
                <span class="step-desc">Send us the video link below.</span>
            </div>
            <div class="step-card">
                <span class="step-num">4</span>
                <span class="step-icon">🤑</span>
                <span class="step-title">Earn</span>
                <span class="step-desc">Get funds in your wallet.</span>
            </div>
        </div>

        <?php if($success): ?><div class="alert alert-success mb-3" style="font-size:0.9rem; padding:10px;">✅ <?= $success ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger mb-3" style="font-size:0.9rem; padding:10px;">⚠️ <?= $error ?></div><?php endif; ?>

        <div class="submit-box">
            <span class="sb-title">Submit Video Link</span>
            <form method="POST">
                <input type="hidden" name="platform" id="selected_platform" value="TikTok">

                <div class="plat-grid">
                    <div class="plat-item active" onclick="selectPlatform(this, 'TikTok')">
                        <img src="../assets/img/icons/TikTok.png" class="plat-img" onerror="this.src='../assets/img/default.png'">
                        <span class="plat-name">TikTok</span>
                    </div>
                    <div class="plat-item" onclick="selectPlatform(this, 'YouTube')">
                        <img src="../assets/img/icons/Youtube.png" class="plat-img" onerror="this.src='../assets/img/default.png'">
                        <span class="plat-name">YouTube</span>
                    </div>
                    <div class="plat-item" onclick="selectPlatform(this, 'Instagram')">
                        <img src="../assets/img/icons/Instagram.png" class="plat-img" onerror="this.src='../assets/img/default.png'">
                        <span class="plat-name">Instagram</span>
                    </div>
                    <div class="plat-item" onclick="selectPlatform(this, 'Facebook')">
                        <img src="../assets/img/icons/Facebook.png" class="plat-img" onerror="this.src='../assets/img/default.png'">
                        <span class="plat-name">Facebook</span>
                    </div>
                </div>

                <input type="url" name="video_link" class="form-input" placeholder="Paste video link here..." required>
                
                <button type="submit" name="submit_video" class="btn-submit">Submit for Review</button>
            </form>

            <div style="margin-top:15px; font-size:0.8rem; color:#c2410c; background:#fff7ed; padding:10px; border-radius:8px; border:1px dashed #fdba74;">
                <b>Note:</b> Do not delete the video after getting paid. If you do, money will be deducted & account banned.
            </div>
        </div>

        <h4 style="margin-bottom:15px; font-size:1rem; color:#334155; font-weight:800;">My Submissions</h4>
        <div class="h-list">
            <?php if(empty($logs)): ?>
                <p style="text-align:center; color:#94a3b8; font-size:0.9rem;">No submissions yet.</p>
            <?php else: ?>
                <?php foreach($logs as $log): 
                    $iconName = ($log['platform'] == 'YouTube') ? 'Youtube.png' : $log['platform'].'.png';
                ?>
                <div class="h-item">
                    <div class="hi-left">
                        <img src="../assets/img/icons/<?= $iconName ?>" class="hi-icon" onerror="this.style.display='none'">
                        <div class="hi-info">
                            <h4><?= $log['platform'] ?> Video</h4>
                            <a href="<?= $log['video_link'] ?>" target="_blank">Open Link</a>
                        </div>
                    </div>
                    <div class="hi-right">
                        <?php if($log['reward_amount'] > 0): ?>
                            <span class="h-amt">+<?= number_format($log['reward_amount']) ?></span><br>
                        <?php endif; ?>
                        <span class="h-status st-<?= $log['status'] ?>"><?= strtoupper($log['status']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function selectPlatform(el, val) {
    document.getElementById('selected_platform').value = val;
    document.querySelectorAll('.plat-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
}
</script>

<?php include '_footer.php'; ?>