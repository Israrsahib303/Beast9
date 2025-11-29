console.log("SubHub SMM Panel JS v2.9 (Per-Order Timer Fix) Active");

// Helper function for DOM selection
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => parent.querySelectorAll(selector);

// Tamam services ka data PHP se JS mein lein (smm_order.php se)
const allServicesData = typeof window.allServicesData !== 'undefined' ? window.allServicesData : {};

// --- NAYA: Comments Generator Dictionaries ---
const commentPresets = {
    "eng-positive": [
        "Wow!", "Amazing!", "Great post!", "Love this!", "Awesome!", 
        "Nice one!", "So cool!", "Perfect!", "Beautiful!", "Stunning!"
    ],
    "hinglish-positive": [
        "Nice pic bhai", "Boht khoob", "Zabardast", "Aala!", "Kamaal kar dia",
        "Pyari post", "MashaAllah", "Nice pose", "Superb", "Kya baat hai"
    ],
    "urdu-positive": [
        "بہت خوب", "زبردست", "لاجواب", "کیا بات ہے", "ماشاءاللہ",
        "بہترین", "بہت اعلیٰ", "خوبصورت", "عمدہ"
    ]
};
// --- KHATAM ---


document.addEventListener("DOMContentLoaded", () => {
    
    // Check if we are on an SMM page
    if (!$('.smm-app-container')) return;

    // --- Platform Card Logic (Main Categories) ---
    const platformGrid = $('#platform-grid');
    const accordionContainer = $('#category-accordion-container');
    const backBtn = $('#back-to-platforms-btn');
    const searchInput = $("#service-search");

    // 1. Platform Card par click
    if (platformGrid) { // Check karein ke yeh element mojood hai
        $$(".platform-card").forEach(card => {
            card.addEventListener("click", () => {
                const platformName = card.dataset.platform; 
                
                if (platformGrid) platformGrid.style.display = 'none';
                if (accordionContainer) accordionContainer.style.display = 'block';
                
                const platformAccordion = $('#accordion-' + platformName);
                if (platformAccordion) {
                    platformAccordion.style.display = 'block';
                } else {
                    console.error('No accordion found for ID: #accordion-' + platformName);
                }
                
                if(searchInput) searchInput.value = ''; // Search reset karein
            });
        });
    }

    // 2. Back Button par click
    if (backBtn) {
        backBtn.addEventListener("click", () => {
            if (platformGrid) platformGrid.style.display = 'grid'; // 'grid' (ya 'block')
            if (accordionContainer) accordionContainer.style.display = 'none';
            $$('.category-accordion').forEach(acc => {
                acc.style.display = 'none';
            });
            if(searchInput) searchInput.value = ''; // Search reset karein
        });
    }


    // --- Accordion Logic (Sub-Categories) ---
    $$(".category-header").forEach(header => {
        header.addEventListener("click", () => {
            const isActive = header.classList.contains("active");
            const list = $('#category-' + header.dataset.category);
            
            const parentAccordion = header.closest('.category-accordion');
            if (parentAccordion) {
                parentAccordion.querySelectorAll(".category-header").forEach(h => h.classList.remove("active"));
                parentAccordion.querySelectorAll(".service-list").forEach(l => l.style.display = "none");
            }
            
            if (!isActive) {
                header.classList.add("active");
                if (list) {
                    list.style.display = "block";
                }
            }
        });
    });

    // --- Search Logic (Updated) ---
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase();

            if (query.length > 0) {
                if (platformGrid) platformGrid.style.display = 'none';
                if (accordionContainer) accordionContainer.style.display = 'block';
                $$('.category-accordion').forEach(acc => acc.style.display = 'block');
            } else {
                if (platformGrid) platformGrid.style.display = 'grid';
                if (accordionContainer) accordionContainer.style.display = 'none';
                $$('.category-accordion').forEach(acc => acc.style.display = 'none');
            }

            $$(".category-group").forEach(category => {
                let categoryVisible = false;
                
                category.querySelectorAll(".service-item").forEach(service => {
                    const name = service.dataset.serviceName.toLowerCase();
                    if (name.includes(query)) {
                        service.style.display = "block";
                        categoryVisible = true;
                    } else {
                        service.style.display = "none";
                    }
                });
                
                if (categoryVisible) {
                    category.style.display = "block";
                    category.querySelector('.service-list').style.display = 'block';
                    category.querySelector('.category-header').classList.add('active');
                } else {
                    category.style.display = "none";
                }
            });
        });
    }

    // --- Modal (Popup) Logic (Comments wala) ---
    const modal = $('#order-modal');
    if (modal) {
        const modalForm = $('#modal-order-form');
        const closeBtn = $('#modal-close-btn');
        const chaChingSound = $('#cha-ching-sound');
        const modalTotalCharge = $('#modal-total-charge');
        const placeOrderBtn = modalForm.querySelector('.btn-app-primary');
        
        // Form ke groups
        const normalGroup = $('.normal-group', modalForm);
        const commentsGroup = $('.comments-group', modalForm);
        
        // Fields
        const modalLink = $('#modal-link');
        const modalQuantity = $('#modal-quantity');
        const modalQuantityLabel = $('#modal-quantity-label');
        const modalMinMaxMsg = $('#modal-min-max-msg');
        const linkDetector = $('#link-detector-msg');
        const modalComments = $('#modal-comments');
        const openGeneratorBtn = $('#open-generator-btn');

        // Description fields
        const modalServiceDesc = $('#modal-service-desc');
        const modalServiceStats = $('#modal-service-stats');
        
        const modalServiceNameText = $('#modal-service-name-text'); 
        
        let currentService = null;

        // Function: Price calculate karein
        function calculatePrice() {
            if (!currentService) return;

            let quantity = 0;
            let charge = 0;
            const ratePer1000 = currentService.rate;

            if (currentService.is_comments_service) {
                // Comments ke liye quantity = lines
                const lines = modalComments.value.split('\n').filter(line => line.trim() !== '');
                quantity = lines.length;
                modalQuantity.value = quantity; // Quantity box ko update karein
                
                charge = (quantity / 1000) * ratePer1000;
                
                // Min/Max msg update karein
                modalMinMaxMsg.innerText = `Min: ${currentService.min} / Max: ${currentService.max} comments`;
                checkValidity(quantity, modalMinMaxMsg);

            } else {
                // Normal service
                quantity = parseInt(modalQuantity.value) || 0;
                charge = (quantity / 1000) * ratePer1000;
                
                // Min/Max msg update karein
                modalMinMaxMsg.innerText = `Min: ${currentService.min} / Max: ${currentService.max}`;
                checkValidity(quantity, modalMinMaxMsg);
            }
            
            modalTotalCharge.innerText = `PKR ${charge.toFixed(4)}`;
        }
        
        // Function: Check karein ke quantity min/max ke andar hai
        function checkValidity(quantity, messageElement) {
             if (quantity < currentService.min || quantity > currentService.max || quantity === 0) {
                placeOrderBtn.disabled = true;
                if (messageElement) messageElement.style.color = 'red';
            } else {
                placeOrderBtn.disabled = false;
                if (messageElement) messageElement.style.color = 'var(--app-text-muted)';
            }
        }

        // Service par click kar ke modal kholein
        $$(".service-item").forEach(item => {
            item.addEventListener("click", () => {
                const serviceId = item.dataset.serviceId;
                currentService = allServicesData[serviceId];
                
                if (!currentService) return;

                if(modalServiceNameText) {
                    modalServiceNameText.innerText = currentService.name;
                }
                
                $('#modal-service-id').value = serviceId;
                
                // 1. Description
                modalServiceDesc.innerHTML = `<p>${currentService.desc}</p>`;
                
                // 2. Stats (Time, Refill, Cancel)
                const refillText = currentService.has_refill ? 'Available' : 'Not Available';
                const refillClass = currentService.has_refill ? 'yes' : 'no';
                const cancelText = currentService.has_cancel ? 'Available' : 'Not Available';
                const cancelClass = currentService.has_cancel ? 'yes' : 'no';

                modalServiceStats.innerHTML = `
                    <div class="stat-item">
                        <div class="stat-icon stat-icon-time">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </div>
                        <div class="stat-info"><strong>Average Time</strong><span>${currentService.avg_time}</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon stat-icon-refill ${refillClass}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        </div>
                        <div class="stat-info"><strong>Refill</strong><span class="${refillClass}">${refillText}</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon stat-icon-cancel ${cancelClass}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"></path></svg>
                        </div>
                        <div class="stat-info"><strong>Cancel</strong><span class="${cancelClass}">${cancelText}</span></div>
                    </div>
                `;
                
                // 3. Form Reset karein
                modalLink.value = '';
                modalQuantity.value = '';
                modalComments.value = '';
                linkDetector.innerText = '';
                modalTotalCharge.innerText = 'PKR 0.00';
                placeOrderBtn.disabled = true;

                // --- NAYA LOGIC: Form switch karein (Aap ki request ke mutabiq) ---
                if (currentService.is_comments_service) {
                    // Comments Service
                    commentsGroup.style.display = 'block';
                    
                    modalQuantity.readOnly = true; // Quantity ko readonly banayein
                    modalQuantityLabel.innerText = 'Quantity (auto-counted from comments)';
                    
                    modalLink.required = true;
                    modalComments.required = true;
                    modalQuantity.required = false; // Yeh user nahi bharega
                    
                    modalMinMaxMsg.innerText = `Min: ${currentService.min} / Max: ${currentService.max} comments`;
                    modalLink.focus();

                } else {
                    // Normal Service
                    commentsGroup.style.display = 'none';
                    
                    modalQuantity.readOnly = false; // Quantity ko wapis writable banayein
                    modalQuantityLabel.innerText = 'Quantity';
                    
                    modalLink.required = true;
                    modalQuantity.required = true;
                    modalComments.required = false;

                    modalMinMaxMsg.innerText = `Min: ${currentService.min} / Max: ${currentService.max}`;
                    modalLink.focus();
                }

                if(chaChingSound) {
                     chaChingSound.currentTime = 0; 
                     chaChingSound.play().catch(e => console.log("Audio play failed"));
                }
                
                // --- NAYA BADLAAO: Popup ko CSS class se show karein ---
                modal.classList.add("active");
            });
        });
        
        // Modal band karein
        // --- NAYA BADLAAO: Popup ko CSS class se hide karein ---
        closeBtn.addEventListener("click", () => {
            modal.classList.remove("active");
        });
        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                 modal.classList.remove("active");
            }
        });

        // Link Detector (Sirf main link field ke liye)
        function detectLink(link, detectorEl) {
            if (link.includes("instagram.com")) {
                detectorEl.innerText = "✅ Instagram Link Detected";
            } else if (link.includes("tiktok.com")) {
                detectorEl.innerText = "✅ TikTok Link Detected";
            } else if (link.includes("youtube.com") || link.includes("youtu.be")) {
                detectorEl.innerText = "✅ YouTube Link Detected";
            } else if (link.length > 10) {
                 detectorEl.innerText = "⚠️ Link type not recognized";
            } else {
                detectorEl.innerText = "";
            }
        }
        
        modalLink.addEventListener("input", (e) => detectLink(e.target.value.toLowerCase(), linkDetector));

        // Live Price Calculator (Quantity aur Comments dono ke liye)
        modalQuantity.addEventListener("input", calculatePrice);
        modalComments.addEventListener("input", calculatePrice);
        
    } // End Main SMM Modal Logic
    
    
    // --- NAYA: Comments Generator Modal Logic ---
    const genModal = $('#generator-modal');
    if (genModal) {
        const openBtn = $('#open-generator-btn');
        const closeGenBtn = $('#generator-close-btn');
        const genOutput = $('#generator-output');
        const copyBtn = $('#copy-comments-btn');
        
        // Generator kholein
        openBtn.addEventListener('click', () => {
            // --- NAYA BADLAAO: CSS class se show karein ---
            genModal.classList.add("active");
            generateComments('hinglish-positive', 10);
        });
        
        // Generator band karein
        closeGenBtn.addEventListener('click', () => {
             genModal.classList.remove("active");
        });
        genModal.addEventListener('click', (e) => {
            if (e.target === genModal) {
                genModal.classList.remove("active");
            }
        });
        
        // Comments generate karein
        function generateComments(lang, count) {
            const list = commentPresets[lang];
            if (!list) return;
            
            let output = '';
            for (let i = 0; i < count; i++) {
                const randomComment = list[Math.floor(Math.random() * list.length)];
                output += randomComment + '\n';
            }
            genOutput.value = output;
        }
        
        // Preset buttons par click
        $$('.btn-gen').forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.dataset.lang;
                let quantity = parseInt(prompt("How many comments to generate?", "10")) || 10;
                generateComments(lang, quantity);
            });
        });
        
        // Comments copy karein
        copyBtn.addEventListener('click', () => {
            genOutput.select();
            document.execCommand('copy');
            copyBtn.innerText = 'Copied!';
            
            // Wapas main form mein paste karein aur modal band karein
            setTimeout(() => {
                $('#modal-comments').value = genOutput.value;
                // Calculate price trigger karein
                $('#modal-comments').dispatchEvent(new Event('input'));
                
                genModal.classList.remove("active");
                copyBtn.innerText = 'Copy to Clipboard';
            }, 500);
        });
    }


    // --- SMM REFILL COOLDOWN TIMER LOGIC (Fixed) ---
    // (Yeh logic ab .countdown class par chalegi jo smm_history.php mein add ki hai)
    
    const countdownElements = document.querySelectorAll(".countdown");

    if (countdownElements.length > 0) {
        
        const interval = setInterval(() => {
            let activeCountdowns = 0; // Zinda countdowns ka count

            countdownElements.forEach(el => {
                // Check karein ke yeh pehle hi 'Expired' to nahi ho gaya
                if (el.classList.contains('expired-check')) {
                    return;
                }

                const endDateString = el.getAttribute("data-end-at");
                
                // Check karein ke date string hai ya nahi
                if (!endDateString) {
                    el.innerHTML = "Invalid Date";
                    el.classList.add('expired-check'); // Isay dobara check na karein
                    return;
                }

                // MySQL ke 'YYYY-MM-DD HH:MM:SS' format ko ISO format 'YYYY-MM-DDTHH:MM:SS' mein badalna
                const compatibleDateString = endDateString.replace(" ", "T");
                const endDateTime = new Date(compatibleDateString).getTime();

                // Check karein ke date sahi se parse hui
                if (isNaN(endDateTime)) {
                    el.innerHTML = "Date Error";
                    el.classList.add('expired-check');
                    return;
                }

                const now = new Date().getTime();
                const distance = endDateTime - now;

                if (distance < 0) {
                    
                    // Check karein ke yeh refill button ka text hai
                    if (el.classList.contains('refill-button-text')) {
                        el.innerHTML = "Refill"; // Text ko 'Refill' set karein
                        
                        // Parent button (jo .btn-refill hai) ko dhoondein aur enable karein
                        const parentButton = el.closest('.btn-refill');
                        if (parentButton) {
                            parentButton.disabled = false;
                        }
                        el.classList.remove('countdown'); // Timer class hata dein
                        el.removeAttribute('data-end-at');
                    } else {
                        // Purana default behavior (dosre timers ke liye)
                        el.innerHTML = "Expired";
                        el.style.color = "#E50914"; // Brand Red
                    }
                    
                    el.classList.add('expired-check'); // Isay dobara check na karein
                    
                } else {
                    activeCountdowns++; // Yeh countdown abhi zinda hai
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    let output = "";
                    
                    if (el.classList.contains('refill-button-text')) {
                        // Yeh refill button ke liye naya, fix kiya gaya logic hai
                        output = "Wait ";
                        if (days > 0) output += `${days}d `; // Din sirf tab dikhaye jab 0 se zyada hon
                        output += `${hours}h ${minutes}m ${seconds}s`;
                    } else {
                        // Yeh doosre timers ke liye purana logic hai
                        if (days > 0) output += `${days}d `;
                        output += `${hours}h ${minutes}m ${seconds}s`;
                    }

                    el.innerHTML = output;
                }
            });

            // Agar koi bhi zinda countdown na bache to interval rok dein
            if (activeCountdowns === 0) {
                clearInterval(interval);
            }
        }, 1000);
    }
    // --- REFILL COOLDOWN LOGIC KHATAM ---

});