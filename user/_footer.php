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

    <footer style="text-align:center; padding:20px; color:#94a3b8; font-size:0.85rem; margin-top:auto;">
        &copy; <?= date('Y') ?> <?= sanitize($GLOBALS['settings']['site_name'] ?? 'SubHub') ?>. All rights reserved.
    </footer>
<?php include_once __DIR__ . '/_broadcast_modal.php'; ?>
<script>
// --- PWA INSTALL MANAGER ---
let deferredPrompt;

// 1. Install Prompt ko Capture karo
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Sirf tab dikhao jab prompt available ho
    showInstallButton(true);
});

// 2. Menu Item ko dhoond kar Setup karo
function showInstallButton(show) {
    // Pure page mein wo link dhoondo jiska href '#install-pwa' hai
    const menuLinks = document.querySelectorAll('a[href="#install-pwa"]');
    
    menuLinks.forEach(link => {
        if (show) {
            // Button dikhao aur Click Event lagao
            link.parentElement.style.display = 'block'; // Li ko show karo
            link.addEventListener('click', (e) => {
                e.preventDefault();
                triggerInstall();
            });
        } else {
            // Agar install nahi ho sakta (ya already installed hai), to button chupa do
            link.parentElement.style.display = 'none';
        }
    });
}

// 3. Asli Install Trigger
function triggerInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
        if (choiceResult.outcome === 'accepted') {
            console.log('User accepted install');
            showInstallButton(false); // Install hone ke baad button chupa do
        }
        deferredPrompt = null;
    });
}

// 4. Initial Check: Agar pehle se installed hai to mat dikhao
window.addEventListener('appinstalled', () => {
    showInstallButton(false);
});

// Default: Pehle chupa ke rakho jab tak check na ho jaye
document.addEventListener("DOMContentLoaded", () => {
    showInstallButton(false);
});
</script>
</body>
</html>