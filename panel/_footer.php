</main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js?v=<?php echo time(); ?>"></script>

<script>
// --- PWA INSTALL MANAGER (Keep Safe) ---
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
            link.parentElement.style.display = 'block'; 
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