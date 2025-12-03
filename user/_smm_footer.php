<?php
// Fix: Undefined variable current_page logic to prevent error logs
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);
?>
    <nav class="smm-bottom-nav">
        <a href="smm_dashboard.php" class="<?php echo ($current_page == 'smm_dashboard.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m18.7 8-5.1 5.2-2.8-2.7L7 15.2"></path></svg>
            <span>Dash</span>
        </a>
        
        <a href="smm_order.php" class="<?php echo ($current_page == 'smm_order.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            <span>Order</span>
        </a>
        
        <a href="mass_order.php" class="<?php echo ($current_page == 'mass_order.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <span>Mass</span>
        </a>

        <a href="smm_history.php" class="<?php echo ($current_page == 'smm_history.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
            <span>History</span>
        </a>
        
        <a href="updates.php" class="<?php echo ($current_page == 'updates.php') ? 'active' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
            <span>Updates</span>
        </a>
    </nav>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script src="../assets/js/smm_main.js?v=2.9"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Check karein ke graph canvas mojood hai
        const ctx = document.getElementById('smm-spending-chart');
        if (ctx && typeof Chart !== 'undefined' && window.smmGraphLabels && window.smmGraphValues) {
            
            // Graph banayein
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: window.smmGraphLabels, // PHP se 'D, j M' format
                    datasets: [{
                        label: 'PKR Spent',
                        data: window.smmGraphValues, // PHP se [0, 0, 5.85, ...]
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4 // Line ko smooth karein
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                // 'PKR 100' likha aaye
                                callback: function(value, index, values) {
                                    return 'PKR ' + value;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // 'PKR Spent' label ko chupayein
                        }
                    }
                }
            });
        }
    });
    </script>

<?php 
// --- AUDIO GUIDE LOGIC FOR SMM SECTION ---
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
        display: flex;
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