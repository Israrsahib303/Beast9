<?php
include '_header.php'; 

// --- BACKEND LOGIC (Same) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_broadcast'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $type = $_POST['type'];
    $btn_text = trim($_POST['btn_text']);
    $btn_link = trim($_POST['btn_link']);

    $db->query("UPDATE broadcasts SET is_active = 0");
    $stmt = $db->prepare("INSERT INTO broadcasts (title, message, type, btn_text, btn_link, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$title, $message, $type, $btn_text, $btn_link]);
    echo "<script>window.location='broadcast.php?success=1';</script>";
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM broadcasts WHERE id = ?")->execute([$_GET['delete']]);
    echo "<script>window.location='broadcast.php';</script>";
}
if (isset($_GET['activate'])) {
    $db->query("UPDATE broadcasts SET is_active = 0");
    $db->prepare("UPDATE broadcasts SET is_active = 1 WHERE id = ?")->execute([$_GET['activate']]);
    echo "<script>window.location='broadcast.php';</script>";
}
if (isset($_GET['deactivate'])) {
    $db->prepare("UPDATE broadcasts SET is_active = 0 WHERE id = ?")->execute([$_GET['deactivate']]);
    echo "<script>window.location='broadcast.php';</script>";
}

$broadcasts = $db->query("SELECT * FROM broadcasts ORDER BY id DESC")->fetchAll();
?>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* --- RESET & BASICS --- */
    :root { 
        --primary: #6366f1; 
        --bg: #f1f5f9; 
        --text: #0f172a; 
        --card-bg: #ffffff; 
        --border: #e2e8f0;
    }
    * { box-sizing: border-box; outline: none; }
    body { 
        background: var(--bg); 
        font-family: 'Outfit', sans-serif; 
        color: var(--text); 
        margin: 0; padding: 0; 
        overflow-x: hidden; /* Prevent horizontal scroll on body */
    }

    /* --- MAIN CONTAINER --- */
    .main-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        width: 100%;
    }

    .page-header {
        margin-bottom: 25px;
        text-align: center;
    }
    .page-title { font-size: 1.8rem; font-weight: 800; color: #1e293b; margin: 0; letter-spacing: -0.5px; }
    .page-sub { color: #64748b; font-size: 0.95rem; margin-top: 5px; }

    /* --- GRID LAYOUT (Responsive) --- */
    .content-grid {
        display: flex;
        flex-direction: column-reverse; /* Mobile: History first, then Form */
        gap: 25px;
    }
    
    /* Desktop Layout */
    @media (min-width: 992px) {
        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr; /* Form gets more space */
            align-items: start;
            flex-direction: row; /* Reset */
        }
    }

    /* --- CARDS --- */
    .b-card {
        background: var(--card-bg);
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        border: 1px solid var(--border);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .b-card-head {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        background: #f8fafc;
        display: flex; justify-content: space-between; align-items: center;
    }
    .b-card-head h3 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #334155; }
    .b-card-body { padding: 24px; }

    /* --- FORM ELEMENTS --- */
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #475569; }
    
    .form-control {
        width: 100%; padding: 12px 16px;
        border: 2px solid #e2e8f0; border-radius: 12px;
        font-size: 1rem; transition: 0.2s;
        font-family: inherit; background: #fff;
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }

    /* Type Selector (Radio Cards) */
    .type-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .type-label { cursor: pointer; position: relative; }
    .type-input { position: absolute; opacity: 0; height: 0; width: 0; }
    .type-box {
        border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px;
        text-align: center; transition: 0.2s; background: #fff;
    }
    .type-icon { font-size: 1.5rem; display: block; margin-bottom: 4px; }
    .type-name { font-size: 0.85rem; font-weight: 700; display: block; }
    
    .type-input:checked + .type-box { border-color: var(--primary); background: #eef2ff; color: var(--primary); }

    .btn-submit {
        width: 100%; padding: 14px; background: var(--primary); color: #fff;
        border: none; border-radius: 12px; font-size: 1rem; font-weight: 700;
        cursor: pointer; transition: 0.2s; margin-top: 10px;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    .btn-submit:hover { background: #4f46e5; transform: translateY(-2px); }

    /* --- LIVE PREVIEW AREA --- */
    .preview-container {
        background: linear-gradient(135deg, #e0e7ff 0%, #f1f5f9 100%);
        border-radius: 20px; padding: 30px;
        display: flex; justify-content: center; align-items: center;
        min-height: 350px; position: relative;
        border: 2px dashed #cbd5e1;
    }
    .preview-badge { position: absolute; top: 15px; background: #333; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }

    /* Actual Popup CSS (Scaled for fit) */
    .mock-popup {
        background: #fff; width: 100%; max-width: 300px;
        border-radius: 24px; overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        font-family: 'Outfit', sans-serif;
        transition: 0.3s;
        /* Ensure it fits on small screens */
        transform-origin: center;
    }
    
    .mock-header { height: 100px; display: flex; align-items: center; justify-content: center; }
    .mock-icon { font-size: 3rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
    .mock-body { padding: 20px; text-align: center; }
    .mock-title { margin: 0 0 8px 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; line-height: 1.2; }
    .mock-msg { font-size: 0.9rem; color: #64748b; line-height: 1.5; margin-bottom: 15px; }
    .mock-btn {
        display: inline-block; padding: 10px 24px; color: #fff; text-decoration: none;
        border-radius: 50px; font-weight: 700; font-size: 0.9rem;
        box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2); opacity: 0.5;
    }
    .mock-btn.active { opacity: 1; }

    /* --- TABLE STYLES (Scrollable) --- */
    .table-wrapper { 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
        border-radius: 0 0 20px 20px;
    }
    .nice-table { width: 100%; border-collapse: collapse; min-width: 600px; /* Force scroll on mobile */ }
    .nice-table th { text-align: left; padding: 15px 20px; background: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border); }
    .nice-table td { padding: 15px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .nice-table tr:last-child td { border-bottom: none; }
    
    .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
    .st-on { background: #dcfce7; color: #166534; }
    .st-off { background: #f1f5f9; color: #64748b; }

    .action-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 8px; border: none;
        cursor: pointer; transition: 0.2s; text-decoration: none; font-size: 1rem;
    }
    .btn-edit { background: #eff6ff; color: var(--primary); }
    .btn-del { background: #fef2f2; color: #ef4444; }
    .btn-del:hover { background: #ef4444; color: #fff; }

</style>

<div class="main-wrapper">
    
    <div class="page-header">
        <h1 class="page-title">📢 Broadcast Manager</h1>
        <p class="page-sub">Create engaging popups for your users.</p>
    </div>

    <div class="content-grid">
        
        <div class="form-col">
            <div class="b-card">
                <div class="b-card-head">
                    <h3>Create New</h3>
                </div>
                <div class="b-card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Broadcast Type</label>
                            <div class="type-grid">
                                <label class="type-label">
                                    <input type="radio" name="type" value="offer" class="type-input" checked onchange="renderPreview()">
                                    <div class="type-box"><span class="type-icon">🎁</span><span class="type-name">Offer</span></div>
                                </label>
                                <label class="type-label">
                                    <input type="radio" name="type" value="update" class="type-input" onchange="renderPreview()">
                                    <div class="type-box"><span class="type-icon">🔔</span><span class="type-name">Update</span></div>
                                </label>
                                <label class="type-label">
                                    <input type="radio" name="type" value="alert" class="type-input" onchange="renderPreview()">
                                    <div class="type-box"><span class="type-icon">⚠️</span><span class="type-name">Alert</span></div>
                                </label>
                                <label class="type-label">
                                    <input type="radio" name="type" value="info" class="type-input" onchange="renderPreview()">
                                    <div class="type-box"><span class="type-icon">📢</span><span class="type-name">Info</span></div>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" id="in_title" class="form-control" placeholder="e.g. Flash Sale!" required oninput="renderPreview()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" id="in_msg" class="form-control" rows="3" placeholder="Enter details here..." required oninput="renderPreview()"></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label class="form-label">Button Text</label>
                                <input type="text" name="btn_text" id="in_btn" class="form-control" placeholder="Optional" oninput="renderPreview()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Button Link</label>
                                <input type="text" name="btn_link" class="form-control" placeholder="https://...">
                            </div>
                        </div>

                        <button type="submit" name="add_broadcast" class="btn-submit">🚀 Publish Broadcast</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="preview-col">
            
            <div class="b-card">
                <div class="b-card-head"><h3>📱 Live Preview</h3></div>
                <div class="b-card-body" style="padding: 0;">
                    <div class="preview-container">
                        <span class="preview-badge">Preview</span>
                        
                        <div class="mock-popup" id="mock_card">
                            <div class="mock-header" id="mock_header">
                                <div class="mock-icon" id="mock_icon">🎁</div>
                            </div>
                            <div class="mock-body">
                                <div class="mock-title" id="mock_title">Flash Sale!</div>
                                <div class="mock-msg" id="mock_msg">Get 50% discount on all services. Limited time offer!</div>
                                <a href="#" class="mock-btn active" id="mock_btn">Check Now</a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="b-card">
                <div class="b-card-head"><h3>📜 History</h3></div>
                <div class="table-wrapper">
                    <table class="nice-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Title</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($broadcasts)): ?>
                                <tr><td colspan="3" style="text-align:center; padding:20px; color:#999;">No broadcasts created yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($broadcasts as $b): ?>
                                <tr>
                                    <td>
                                        <?php if($b['is_active']): ?>
                                            <span class="status-badge st-on">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge st-off">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($b['title']) ?></div>
                                        <div style="font-size:0.8rem; color:#64748b;"><?= substr(htmlspecialchars($b['message']), 0, 40) ?>...</div>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if(!$b['is_active']): ?>
                                            <a href="?activate=<?= $b['id'] ?>" class="action-btn btn-edit">✔️</a>
                                        <?php else: ?>
                                            <a href="?deactivate=<?= $b['id'] ?>" class="action-btn btn-edit">⏸️</a>
                                        <?php endif; ?>
                                        <a href="?delete=<?= $b['id'] ?>" class="action-btn btn-del" onclick="return confirm('Delete?')">🗑</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function renderPreview() {
    // Get Inputs
    const type = document.querySelector('input[name="type"]:checked').value;
    const title = document.getElementById('in_title').value || 'Title Here';
    const msg = document.getElementById('in_msg').value || 'Your message description will appear here...';
    const btnTxt = document.getElementById('in_btn').value;

    // Get Mock Elements
    const mHeader = document.getElementById('mock_header');
    const mIcon = document.getElementById('mock_icon');
    const mTitle = document.getElementById('mock_title');
    const mMsg = document.getElementById('mock_msg');
    const mBtn = document.getElementById('mock_btn');

    // Update Content
    mTitle.innerText = title;
    mMsg.innerText = msg;

    if(btnTxt) {
        mBtn.innerText = btnTxt;
        mBtn.classList.add('active');
    } else {
        mBtn.innerText = 'No Button';
        mBtn.classList.remove('active');
    }

    // Apply Styles based on Type
    let bg='', color='', icon='';
    switch(type) {
        case 'offer': 
            bg = 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'; 
            color = '#d63384'; icon='🎁'; 
            break;
        case 'alert': 
            bg = 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)'; 
            color = '#c2410c'; icon='⚠️'; 
            break;
        case 'update': 
            bg = 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)'; 
            color = '#0891b2'; icon='🔔'; 
            break;
        default: 
            bg = 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)'; 
            color = '#7c3aed'; icon='📢'; 
            break;
    }

    mHeader.style.background = bg;
    mIcon.innerText = icon;
    mTitle.style.color = color;
    mBtn.style.background = color;
}

// Run on Load
document.addEventListener('DOMContentLoaded', renderPreview);
</script>

<?php include '_footer.php'; ?>