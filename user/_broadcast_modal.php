<?php
// Fetch Active Broadcast
$stmt_br = $db->query("SELECT * FROM broadcasts WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$broadcast = $stmt_br->fetch(PDO::FETCH_ASSOC);

if ($broadcast):
    // Animation Icons Logic
    $icon_html = '';
    $color_theme = '';
    
    switch($broadcast['type']) {
        case 'offer':
            $color_theme = 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%)';
            $text_color = '#d63384';
            $icon_html = '<div class="b-icon anim-bounce">🎁</div>';
            break;
        case 'alert':
            $color_theme = 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)';
            $text_color = '#c2410c';
            $icon_html = '<div class="b-icon anim-shake">⚠️</div>';
            break;
        case 'update':
            $color_theme = 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)';
            $text_color = '#0891b2';
            $icon_html = '<div class="b-icon anim-ring">🔔</div>';
            break;
        default: // info
            $color_theme = 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)';
            $text_color = '#7c3aed';
            $icon_html = '<div class="b-icon anim-pulse">📢</div>';
            break;
    }
?>

<div id="broadcastModal" class="b-overlay">
    <div class="b-card">
        <button class="b-close" onclick="closeBroadcast()">✕</button>
        
        <div class="b-header" style="background: <?= $color_theme ?>;">
            <?= $icon_html ?>
        </div>

        <div class="b-body">
            <h3 class="b-title" style="color: <?= $text_color ?>"><?= sanitize($broadcast['title']) ?></h3>
            <div class="b-msg">
                <?= nl2br(sanitize($broadcast['message'])) ?>
            </div>

            <?php if(!empty($broadcast['btn_text'])): ?>
                <a href="<?= sanitize($broadcast['btn_link']) ?>" class="b-btn" style="background: <?= $text_color ?>;">
                    <?= sanitize($broadcast['btn_text']) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* --- ANIMATIONS --- */
@keyframes b-popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes b-bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
@keyframes b-shake { 0% { transform: rotate(0); } 25% { transform: rotate(-10deg); } 75% { transform: rotate(10deg); } 100% { transform: rotate(0); } }
@keyframes b-ring { 0% { transform: rotate(0); } 10% { transform: rotate(15deg); } 20% { transform: rotate(-15deg); } 30% { transform: rotate(0); } }
@keyframes b-pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

/* --- STYLES --- */
.b-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px); z-index: 10000;
    justify-content: center; align-items: center; padding: 20px;
}
.b-card {
    background: #fff; width: 100%; max-width: 400px; border-radius: 24px;
    overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    position: relative; animation: b-popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.b-close {
    position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.5);
    border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
    font-weight: bold; color: #555; transition: 0.2s; z-index: 2;
}
.b-close:hover { background: #fff; color: #000; transform: rotate(90deg); }

.b-header {
    height: 120px; display: flex; align-items: center; justify-content: center;
    position: relative;
}
.b-icon { font-size: 3.5rem; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2)); }

.anim-bounce { animation: b-bounce 2s infinite; }
.anim-shake { animation: b-shake 0.5s infinite; }
.anim-ring { animation: b-ring 1s infinite; }
.anim-pulse { animation: b-pulse 1.5s infinite; }

.b-body { padding: 25px; text-align: center; }
.b-title { margin: 0 0 10px 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
.b-msg { font-size: 0.95rem; color: #64748b; line-height: 1.6; margin-bottom: 20px; }

.b-btn {
    display: inline-block; padding: 12px 30px; color: #fff; text-decoration: none;
    border-radius: 50px; font-weight: 700; box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    transition: 0.3s;
}
.b-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.25); color: #fff; }
</style>

<script>
(function() {
    const modal = document.getElementById('broadcastModal');
    const bId = "<?= $broadcast['id'] ?>";
    
    // Check LocalStorage (Agar user ne close kiya hai toh wapas mat dikhana jab tak ID same hai)
    const seen = localStorage.getItem('seen_broadcast');
    
    if (seen !== bId) {
        // Show after 1 second delay for effect
        setTimeout(() => {
            modal.style.display = 'flex';
        }, 1000);
    }

    window.closeBroadcast = function() {
        modal.style.display = 'none';
        localStorage.setItem('seen_broadcast', bId); // ID save karlo taaki dobara na dikhe
    }
})();
</script>

<?php endif; ?>