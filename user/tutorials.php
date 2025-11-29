<?php
include '_header.php';

// Fetch Videos
$videos = $db->query("SELECT * FROM tutorials ORDER BY id DESC")->fetchAll();

// Helper for YouTube ID
function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? '';
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-grad: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        --card-hover: 0 20px 40px -10px rgba(79, 70, 229, 0.3);
    }

    body { background: #f8fafc; font-family: 'Outfit', sans-serif; }

    .main-content-wrapper { padding-bottom: 80px; }

    /* --- HERO SECTION --- */
    .tut-hero {
        text-align: center; padding: 60px 20px;
        background: var(--primary-grad);
        border-radius: 30px; color: #fff; margin-bottom: 50px;
        position: relative; overflow: hidden;
        box-shadow: 0 20px 50px -15px rgba(79, 70, 229, 0.4);
        animation: fadeInDown 0.8s ease;
    }
    
    .tut-hero::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
        animation: rotate 20s linear infinite;
    }
    @keyframes rotate { from {transform: rotate(0deg);} to {transform: rotate(360deg);} }

    .hero-icon { font-size: 3.5rem; margin-bottom: 15px; display: inline-block; animation: float 3s ease-in-out infinite; }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

    .hero-title { font-size: 2.8rem; font-weight: 800; margin: 0 0 10px 0; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    .hero-sub { font-size: 1.1rem; opacity: 0.9; font-weight: 400; max-width: 600px; margin: 0 auto; }

    /* --- GRID LAYOUT (CENTERED) --- */
    .tut-grid {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(320px, 350px)); /* Fixed width cards */
        gap: 30px; 
        justify-content: center; /* CENTER THE GRID */
        padding: 0 10px;
    }

    /* --- VIDEO CARD --- */
    .vid-card {
        background: #fff; border-radius: 24px; overflow: hidden;
        border: 1px solid #f1f5f9; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex; flex-direction: column; cursor: pointer;
        box-shadow: 0 10px 20px rgba(0,0,0,0.03);
        position: relative;
    }
    
    .vid-card:hover { 
        transform: translateY(-10px) scale(1.02); 
        box-shadow: var(--card-hover);
        border-color: #c7d2fe;
    }

    /* Thumbnail Area */
    .thumb-box {
        height: 200px; position: relative; overflow: hidden;
        background: #000; display: flex; align-items: center; justify-content: center;
    }
    
    .thumb-img { 
        width: 100%; height: 100%; object-fit: cover; opacity: 0.85; 
        transition: 0.5s; 
    }
    .vid-card:hover .thumb-img { opacity: 0.6; transform: scale(1.1); }

    /* Play Button Overlay */
    .play-icon {
        position: absolute; font-size: 3.5rem; color: #fff;
        opacity: 0.8; transition: 0.3s; z-index: 2;
        text-shadow: 0 5px 15px rgba(0,0,0,0.5);
    }
    .vid-card:hover .play-icon { 
        transform: scale(1.2); opacity: 1; color: #ff0000; 
        filter: drop-shadow(0 0 10px rgba(255, 0, 0, 0.5));
    }

    /* Content Area */
    .vid-body { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
    
    .vid-title { 
        font-size: 1.2rem; font-weight: 800; color: #1e293b; 
        margin-bottom: 10px; line-height: 1.4; 
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    
    .vid-desc { 
        font-size: 0.9rem; color: #64748b; line-height: 1.6; 
        margin-bottom: 20px; flex-grow: 1; 
        display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }

    .watch-btn {
        width: 100%; padding: 12px; background: #f1f5f9; color: #334155;
        border-radius: 12px; text-align: center; font-weight: 700; font-size: 0.9rem;
        transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .vid-card:hover .watch-btn { background: #4f46e5; color: #fff; box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3); }

    /* --- SKELETON LOADER --- */
    .skeleton {
        background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
        background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 12px;
    }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
    
    #realContent { display: none; animation: fadeIn 0.6s ease-out; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    @keyframes fadeInDown { from{opacity:0; transform:translateY(-30px);} to{opacity:1; transform:translateY(0);} }

    /* --- MODAL --- */
    .vid-overlay {
        position: fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.85); backdrop-filter: blur(5px);
        z-index: 10000; display: none; align-items: center; justify-content: center;
        padding: 20px; animation: zoomIn 0.3s ease;
    }
    .vid-box {
        width: 100%; max-width: 900px; aspect-ratio: 16/9;
        background: #000; border-radius: 20px; overflow: hidden;
        box-shadow: 0 30px 60px rgba(0,0,0,0.5); position: relative;
    }
    .vid-close {
        position: absolute; top: -40px; right: 0; color: #fff; font-size: 2rem;
        background: none; border: none; cursor: pointer; transition: 0.2s;
    }
    .vid-close:hover { color: #ef4444; transform: rotate(90deg); }
    @keyframes zoomIn { from{transform:scale(0.9); opacity:0;} to{transform:scale(1); opacity:1;} }

    @media(max-width: 768px) {
        .tut-grid { grid-template-columns: 1fr; }
        .hero-title { font-size: 2rem; }
    }
</style>

<div class="main-content-wrapper">
    
    <div id="skeletonLoader">
        <div class="skeleton" style="height: 250px; border-radius: 30px; margin-bottom: 50px;"></div>
        <div class="tut-grid">
            <?php for($i=0; $i<3; $i++): ?>
            <div class="vid-card" style="height: 400px; cursor: default;">
                <div class="skeleton" style="width:100%; height:200px;"></div>
                <div style="padding:25px;">
                    <div class="skeleton" style="width:70%; height:25px; margin-bottom:15px;"></div>
                    <div class="skeleton" style="width:100%; height:15px; margin-bottom:10px;"></div>
                    <div class="skeleton" style="width:90%; height:15px; margin-bottom:30px;"></div>
                    <div class="skeleton" style="width:100%; height:45px;"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div id="realContent">
        
        <div class="tut-hero">
            <div class="hero-icon">🎓</div>
            <h1 class="hero-title">Learning Hub</h1>
            <p class="hero-sub">Master SMM strategies, tools, and tricks with our premium video tutorials.</p>
        </div>

        <div class="tut-grid">
            <?php if(empty($videos)): ?>
                <div style="grid-column:1/-1; text-align:center; padding:80px; color:#94a3b8;">
                    <i class="fas fa-clapperboard" style="font-size:4rem; margin-bottom:20px; opacity:0.5;"></i>
                    <h3>No tutorials yet</h3>
                    <p>Check back later for new content.</p>
                </div>
            <?php else: ?>
                <?php foreach($videos as $v): 
                    $vidID = getYoutubeId($v['video_link']);
                    $thumb = "https://img.youtube.com/vi/$vidID/maxresdefault.jpg"; 
                ?>
                <div class="vid-card" onclick="playVideo('<?= $vidID ?>')">
                    <div class="thumb-box">
                        <img src="<?= $thumb ?>" class="thumb-img" onerror="this.src='https://img.youtube.com/vi/<?= $vidID ?>/hqdefault.jpg'">
                        <i class="fas fa-play-circle play-icon"></i>
                    </div>
                    <div class="vid-body">
                        <div class="vid-title"><?= htmlspecialchars($v['title']) ?></div>
                        <div class="vid-desc"><?= htmlspecialchars($v['description']) ?></div>
                        
                        <div class="watch-btn">
                            <i class="fas fa-play"></i> Watch Now
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<div id="videoModal" class="vid-overlay" onclick="if(event.target===this) closeVideo()">
    <div style="position:relative; width:100%; max-width:900px;">
        <button class="vid-close" onclick="closeVideo()"><i class="fas fa-times"></i></button>
        <div class="vid-box">
            <iframe id="ytPlayer" width="100%" height="100%" src="" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
// SKELETON LOGIC
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('skeletonLoader').style.display = 'none';
        document.getElementById('realContent').style.display = 'block';
    }, 800);
});

// VIDEO PLAYER LOGIC
function playVideo(id) {
    const url = "https://www.youtube.com/embed/" + id + "?autoplay=1&rel=0&modestbranding=1";
    document.getElementById('ytPlayer').src = url;
    document.getElementById('videoModal').style.display = 'flex';
}

function closeVideo() {
    document.getElementById('videoModal').style.display = 'none';
    document.getElementById('ytPlayer').src = "";
}
</script>

<?php include '_footer.php'; ?>