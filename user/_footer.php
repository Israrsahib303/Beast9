<?php
// --- AUDIO GUIDE LOGIC ---
$current_page_file = basename($_SERVER['PHP_SELF']);
$audio_guide = null;

try {
    $stmt_audio = $db->prepare("SELECT * FROM page_audios WHERE page_key = ? AND is_active = 1");
    $stmt_audio->execute([$current_page_file]);
    $audio_guide = $stmt_audio->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { }
?>
<div style="height: 100px;"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    const driver = window.driver.js.driver;
    const path = window.location.pathname;
    
    // --- THEME CONFIG ---
    const tourConfig = {
        showProgress: true,
        animate: true,
        allowClose: true,
        doneBtnText: "Finish 🚀",
        nextBtnText: "Next ➔",
        prevBtnText: "Back",
        popoverClass: 'premium-tour-popover', // Custom Class
        progressText: 'Step {{current}} of {{total}}'
    };

    // Helper to run tour
    function runTour(key, steps) {
        if (!localStorage.getItem(key)) {
            const tour = driver({
                ...tourConfig,
                steps: steps,
                onDestroyStarted: () => {
                    localStorage.setItem(key, 'true');
                    tour.destroy();
                },
            });
            setTimeout(() => tour.drive(), 1000);
        }
    }

    /* ---------------------------------------------------------
       1. DASHBOARD TOUR
       --------------------------------------------------------- */
    if (path.includes('index.php') || path.endsWith('/user/') || path.endsWith('/user')) {
        runTour('seen_dashboard_v5', [
            { 
                element: '.wallet-card', 
                popover: { title: '💳 Digital Wallet', description: 'This is your live balance card. It updates instantly after deposit.' } 
            },
            { 
                element: '.stats-row', 
                popover: { title: '📊 Activity Stats', description: 'Track your total spending and orders in real-time here.' } 
            },
            { 
                element: '.concierge-box', 
                popover: { title: '🧞‍♂️ Request Center', description: 'Need a specific service? Just ask here and we will add it for you!' } 
            },
            { 
                element: '.filter-header', 
                popover: { title: '🏷️ Smart Filters', description: 'Filter services by category to find exactly what you need.' } 
            },
            { 
                element: '.prod-grid', 
                popover: { title: '🛍️ Premium Store', description: 'Browse our exclusive services cards. Click "Get Now" to buy.' } 
            }
        ]);
    }

    /* ---------------------------------------------------------
       2. NEW ORDER TOUR
       --------------------------------------------------------- */
    if (path.includes('smm_order.php')) {
        runTour('seen_smm_v5', [
            { 
                element: '#platform-grid', 
                popover: { title: '📱 Select App', description: 'Start by clicking the app icon (Instagram, TikTok, etc.) you want.' } 
            },
            { 
                element: '.search-box', 
                popover: { title: '🔍 Quick Search', description: 'Type "Likes" or "Followers" here to filter instantly.' } 
            },
            { 
                element: '#apps-container', 
                popover: { title: '📦 Service Cards', description: 'Click any service card to open the detailed order form.' } 
            }
        ]);
    }

    /* ---------------------------------------------------------
       3. ADD FUNDS TOUR
       --------------------------------------------------------- */
    if (path.includes('add-funds.php')) {
        runTour('seen_funds_v5', [
            { 
                element: '.pay-card', 
                popover: { title: '⚡ Instant Deposit', description: 'Use NayaPay/SadaPay for automatic funds addition within seconds.' } 
            },
            { 
                element: '.promo-box', 
                popover: { title: '🎟️ Promo Code', description: 'Have a coupon? Enter it here to claim your FREE Bonus cash!' } 
            },
            { 
                element: '.pay-card:last-child', 
                popover: { title: '📸 Manual Upload', description: 'For JazzCash/Easypaisa, upload your payment screenshot here.' } 
            }
        ]);
    }

    /* ---------------------------------------------------------
       4. TOOLS TOUR
       --------------------------------------------------------- */
    if (path.includes('tools.php')) {
        runTour('seen_tools_v5', [
            { 
                element: '.cat-tabs', 
                popover: { title: '🛠️ Tool Categories', description: 'Switch between Social, SEO, and Developer tools easily.' } 
            },
            { 
                element: '.tool-card', 
                popover: { title: '🎁 Free Forever', description: 'All these premium tools are free. Use them to boost your growth.' } 
            }
        ]);
    }
});

// Function to reset tours (for testing)
function resetTours() {
    Object.keys(localStorage).forEach(k => { if(k.startsWith('seen_')) localStorage.removeItem(k); });
    location.reload();
}
</script>

<style>
/* --- 🌟 PREMIUM DRIVER.JS THEME --- */

/* The Main Box */
.driver-popover.premium-tour-popover {
    background: rgba(15, 23, 42, 0.95); /* Dark Navy */
    backdrop-filter: blur(15px);
    color: #ffffff;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 
        0 0 0 1px rgba(255, 255, 255, 0.1),
        0 20px 50px -10px rgba(79, 70, 229, 0.5); /* Purple Glow */
    border: none;
    min-width: 300px;
    font-family: 'Outfit', sans-serif;
}

/* Title with Gradient */
.driver-popover-title {
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: 0.5px;
}

/* Description Text */
.driver-popover-description {
    font-size: 0.95rem;
    line-height: 1.6;
    color: #cbd5e1; /* Soft Grey */
    margin-bottom: 20px;
}

/* Buttons */
.driver-popover-footer {
    margin-top: 15px;
}

.driver-popover-footer button {
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    color: #fff !important;
    text-shadow: none !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 8px 18px !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    transition: 0.2s !important;
    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3) !important;
}

.driver-popover-footer button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.5) !important;
}

/* Previous Button (Muted) */
.driver-popover-prev-btn {
    background: rgba(255,255,255,0.1) !important;
    box-shadow: none !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
    display: none !important; /* Hide Previous to simplify */
}

/* Close Button */
.driver-popover-close-btn {
    color: #94a3b8 !important;
    transition: 0.2s;
}
.driver-popover-close-btn:hover {
    color: #fff !important;
    transform: rotate(90deg);
}

/* Arrows (Pointer) */
.driver-popover-arrow-side-left.driver-popover-arrow { border-left-color: #0f172a !important; }
.driver-popover-arrow-side-right.driver-popover-arrow { border-right-color: #0f172a !important; }
.driver-popover-arrow-side-top.driver-popover-arrow { border-top-color: #0f172a !important; }
.driver-popover-arrow-side-bottom.driver-popover-arrow { border-bottom-color: #0f172a !important; }
</style>

<footer style="text-align:center; padding:20px; color:var(--text-muted, #94a3b8); font-size:0.85rem; margin-top:auto;">
    &copy; <?= date('Y') ?> <?= sanitize($GLOBALS['settings']['site_name'] ?? 'SubHub') ?>. All rights reserved.
</footer>

<?php include_once __DIR__ . '/_broadcast_modal.php'; ?>

<script>
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    showInstallButton(true);
});

function showInstallButton(show) {
    const menuLinks = document.querySelectorAll('a[href="#install-pwa"]');
    menuLinks.forEach(link => {
        if (show) {
            link.parentElement.style.display = 'block';
            link.addEventListener('click', (e) => {
                e.preventDefault();
                triggerInstall();
            });
        } else {
            link.parentElement.style.display = 'none';
        }
    });
}

function triggerInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
            showInstallButton(false);
        }
        deferredPrompt = null;
    });
}

window.addEventListener('appinstalled', () => { showInstallButton(false); });
document.addEventListener("DOMContentLoaded", () => { showInstallButton(false); });
</script>

<?php if(($GLOBALS['settings']['float_status'] ?? '0') == '1'): 
    $f_app = $GLOBALS['settings']['float_app'] ?? 'whatsapp';
    $f_num = $GLOBALS['settings']['float_num'] ?? '';
    $f_msg = $GLOBALS['settings']['float_msg'] ?? 'Hi';
    $f_pos = ($GLOBALS['settings']['float_pos'] ?? 'right') == 'left' ? 'left:20px;' : 'right:20px;';
    
    // Updated: Uses Theme Color if set, otherwise defaults
    $f_col = $GLOBALS['settings']['float_color'] ?? '#25D366'; 
    if(empty($f_col)) $f_col = ($f_app == 'whatsapp') ? '#25D366' : '#0088cc';
    
    $f_link = ($f_app == 'whatsapp') 
        ? "https://wa.me/$f_num?text=".urlencode($f_msg) 
        : "https://t.me/$f_num";
    $f_icon = ($f_app == 'whatsapp') ? 'fa-whatsapp' : 'fa-telegram';
?>
<a href="<?= $f_link ?>" target="_blank" style="position:fixed; bottom:20px; <?= $f_pos ?> z-index:9999; background:<?= $f_col ?>; color:white; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:35px; box-shadow:0 5px 20px rgba(0,0,0,0.3); transition:0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
    <i class="fab <?= $f_icon ?>"></i>
</a>
<?php endif; ?>

<?php 
// --- AUDIO GUIDE LOGIC ---
if (!isset($audio_guide)) {
    $current_page_file = basename($_SERVER['PHP_SELF']);
    $audio_guide = null;
    try {
        if (isset($db)) {
            $stmt_audio = $db->prepare("SELECT * FROM page_audios WHERE page_key = ? AND is_active = 1");
            $stmt_audio->execute([$current_page_file]);
            $audio_guide = $stmt_audio->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) { }
}
?>

<?php if (isset($audio_guide) && $audio_guide): ?>
<button onclick="forcePlayAudio()" id="manualAudioBtn" title="Replay Guide" style="display:none; position:fixed; bottom:90px; left:20px; z-index:9998; background:rgba(255,255,255,0.9); border:1px solid #e2e8f0; border-radius:50%; width:45px; height:45px; box-shadow:0 5px 15px rgba(0,0,0,0.1); cursor:pointer; color:#4f46e5; align-items:center; justify-content:center; backdrop-filter:blur(5px); transition:all 0.3s ease;">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
</button>

<style>
    /* --- 🎧 ULTRA PREMIUM LIGHT THEME AUDIO POPUP --- */
    .audio-island-ai {
        position: fixed;
        /* Positioned to avoid header */
        top: 110px; 
        left: 50%;
        
        /* Initial State */
        transform: translateX(-50%) translateY(-20px);
        opacity: 0;
        display: none; /* Controlled by JS now */

        /* Light Glass Theme */
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        
        /* Layout */
        padding: 8px 12px 8px 16px;
        border-radius: 50px;
        align-items: center;
        gap: 12px;
        width: auto;
        min-width: 280px;
        max-width: 90vw;
        
        /* Soft, Clean Shadow & Border */
        border: 1px solid rgba(255, 255, 255, 1);
        box-shadow: 
            0 15px 40px rgba(0, 0, 0, 0.08),
            0 2px 5px rgba(0, 0, 0, 0.02);
            
        z-index: 2147483647 !important; 
        
        /* Smooth Transition */
        transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    /* Animation Visualizer */
    .ai-visualizer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 2px;
        height: 20px;
        min-width: 20px;
    }
    
    /* Colorful Bars for Light Theme */
    .ai-bar {
        width: 3px;
        background: linear-gradient(to top, #6366f1, #8b5cf6);
        border-radius: 2px;
        animation: equalizer 0.8s ease-in-out infinite;
    }
    .ai-bar:nth-child(1) { height: 8px; animation-delay: 0.0s; }
    .ai-bar:nth-child(2) { height: 16px; animation-delay: 0.1s; }
    .ai-bar:nth-child(3) { height: 12px; animation-delay: 0.2s; }
    .ai-bar:nth-child(4) { height: 18px; animation-delay: 0.3s; }

    .ai-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin-right: 5px;
        overflow: hidden;
    }

    .ai-label {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #6366f1; /* Indigo */
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .ai-title {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b; /* Dark Slate */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 180px;
    }

    /* Progress Bar */
    .ai-progress-container {
        width: 100%;
        height: 3px;
        background: #e2e8f0; /* Light Grey */
        border-radius: 10px;
        margin-top: 4px;
        overflow: hidden;
    }
    .ai-progress-fill {
        height: 100%;
        background: #6366f1;
        width: 0%; 
        transition: width 0.1s linear;
    }

    /* Close Button (Darker for Contrast) */
    .ai-close-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #64748b;
        flex-shrink: 0;
    }
    
    .ai-close-btn:hover {
        background: #fee2e2;
        border-color: #fecaca;
        color: #ef4444;
        transform: scale(1.1);
    }

    @keyframes equalizer {
        0%, 100% { transform: scaleY(0.4); }
        50% { transform: scaleY(1.0); }
    }
    
    @media (max-width: 600px) {
        .audio-island-ai {
            width: 85%;
            top: 110px; /* Safe distance from header */
        }
    }
</style>

<div class="audio-island-ai" id="aiPopup">
    <div class="ai-visualizer">
        <div class="ai-bar"></div>
        <div class="ai-bar"></div>
        <div class="ai-bar"></div>
        <div class="ai-bar"></div>
    </div>
    
    <div class="ai-content">
        <div class="ai-label">
            <span style="width:5px; height:5px; background:#10b981; border-radius:50%; display:inline-block;"></span> 
            AUDIO GUIDE
        </div>
        <div class="ai-title">
            <?= !empty($audio_guide['description']) ? htmlspecialchars($audio_guide['description']) : 'Playing Instruction...' ?>
        </div>
        
        <div class="ai-progress-container">
             <div class="ai-progress-fill" id="aiProgressBar"></div>
        </div>
    </div>

    <audio id="mainAudioPlayer" preload="auto">
        <source src="../assets/uploads/audio/<?= htmlspecialchars($audio_guide['audio_file']) ?>" type="audio/mpeg">
    </audio>

    <button class="ai-close-btn" onclick="closeAiPopup()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
</div>

<script>
// --- SMART PAGE TRACKING LOGIC ---
const PAGE_KEY = 'seen_audio_<?= md5($current_page_file) ?>';

function closeAiPopup() {
    const popup = document.getElementById('aiPopup');
    const audio = document.getElementById('mainAudioPlayer');
    const btn = document.getElementById('manualAudioBtn');

    if(audio) audio.pause();
    
    // Smooth Hide
    popup.style.opacity = "0";
    popup.style.transform = "translateX(-50%) translateY(-20px)";
    
    setTimeout(() => { 
        popup.style.display = 'none';
        if(btn) {
            btn.style.display = 'flex'; // Show Replay Button
        }
    }, 400);
}

function showAiPopup() {
    const popup = document.getElementById('aiPopup');
    const audio = document.getElementById('mainAudioPlayer');
    const btn = document.getElementById('manualAudioBtn');
    
    // Hide Replay Button
    if(btn) btn.style.display = 'none';

    // Show Popup
    popup.style.display = 'flex';
    setTimeout(() => {
        popup.style.opacity = "1";
        popup.style.transform = "translateX(-50%) translateY(0)";
    }, 10);

    if(audio) {
        audio.volume = 1.0; 
        
        audio.ontimeupdate = function() {
            if(audio.duration) {
                const percent = (audio.currentTime / audio.duration) * 100;
                const bar = document.getElementById("aiProgressBar");
                if(bar) bar.style.width = percent + "%";
            }
        };
        
        // Auto Close on End
        audio.onended = function() {
            setTimeout(closeAiPopup, 1000);
        };

        // --- FAST AUTOPLAY LOGIC ---
        var playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                // If blocked, wait for first click
                ['click', 'touchstart'].forEach(evt => {
                     document.addEventListener(evt, function() {
                         audio.play();
                     }, { once: true });
                });
            });
        }
    }
    
    // Save to LocalStorage
    localStorage.setItem(PAGE_KEY, 'true');
}

// Manual Replay
function forcePlayAudio() {
    showAiPopup();
}

document.addEventListener('DOMContentLoaded', function() {
    const hasSeen = localStorage.getItem(PAGE_KEY);
    const btn = document.getElementById('manualAudioBtn');

    if (!hasSeen) {
        // First Time Visit -> Show Popup
        setTimeout(showAiPopup, 600); 
    } else {
        // Already Seen -> Show Replay Button
        if(btn) btn.style.display = 'flex';
    }
});
</script>
<?php endif; ?>

</body>
</html>