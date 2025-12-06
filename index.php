<?php
require_once __DIR__ . '/includes/helpers.php';

// 1. Security & Redirects
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/panel/index.php');
    } else {
        redirect(SITE_URL . '/user/index.php');
    }
    exit;
}

$site_name = $GLOBALS['settings']['site_name'] ?? 'SubHub';
$site_logo = $GLOBALS['settings']['site_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LikexFollow | The Crazy SMM Panel</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Animation Libs -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Clash Display', 'sans-serif'],
                        cyber: ['Orbitron', 'sans-serif'],
                    },
                    colors: {
                        primary: '#4f46e5',
                    },
                    animation: {
                        'float': 'floating 3s ease-in-out infinite',
                        'wiggle': 'wiggle 1s ease-in-out infinite',
                        'spin-slow': 'spin 12s linear infinite',
                        'ultra-pulse': 'ultraPulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        floating: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        wiggle: {
                            '0%, 100%': { transform: 'rotate(-3deg)' },
                            '50%': { transform: 'rotate(3deg)' },
                        },
                        ultraPulse: {
                            '0%, 100%': { transform: 'scale(1)', filter: 'drop-shadow(0 0 0px rgba(79, 70, 229, 0))' },
                            '50%': { transform: 'scale(1.1)', filter: 'drop-shadow(0 0 15px rgba(79, 70, 229, 0.6)) hue-rotate(15deg)' }
                        }
                    }
                }
            }
        }
    </script>

    <!-- ==============================================
         HEAVY SECURITY PROTOCOLS - LEVEL 3 (END LEVEL)
         ============================================== -->
    <script>
    (function(){
        // 1. DISABLE CONSOLE (CONSOLE WIPER)
        // Console ko bar bar clear karega taake koi logs na dekh sake
        setInterval(function(){
            console.clear();
            console.log("%c STOP! This is a protected area.", "color: red; font-size: 30px; font-weight: bold;");
        }, 100);

        // 2. DOMAIN LOCK (SELF DESTRUCT)
        // Agar domain 'likexfollow.com' ya 'localhost' nahi hai, to site content delete ho jayega.
        var allowedDomains = ["likexfollow.com", "www.likexfollow.com", "localhost", "127.0.0.1"];
        var currentDomain = window.location.hostname;
        
        if (!allowedDomains.includes(currentDomain)) {
            document.documentElement.innerHTML = "<div style='display:flex;justify-content:center;align-items:center;height:100vh;background:black;color:red;font-size:30px;font-family:sans-serif;text-align:center;'><h1>ACCESS DENIED<br>THEFT DETECTED 🚫</h1></div>";
            throw new Error("Domain Access Violation");
        }

        // 3. DEBUGGER TRAP (FREEZE DEVTOOLS)
        // Jaise hi koi inspect element kholega, ye loop browser ko hang kar dega
        (function blockDevTools() {
            try {
                (function loop() {
                    // Uncommenting the next line activates the FREEZE trap. 
                    // Warning: Isse apka apna browser bhi atak sakta hai agar inspect khola.
                    // debugger; 
                    
                    // Simple infinite check
                    var start = new Date().getTime();
                    // Artificial Lag check
                    if (new Date().getTime() - start > 100) {
                        document.body.innerHTML = ""; // Clear body if lag detected (DevTools open)
                    }
                })();
            } catch (e) {}
            // setTimeout(blockDevTools, 100);
        })();

        // 4. DISABLE SHORTCUTS (CTRL+U, CTRL+SHIFT+I, F12, ETC)
        document.addEventListener('keydown', function(e) {
            // F12
            if(e.keyCode == 123) { e.preventDefault(); return false; }
            // CTRL+Shift+I (Inspect)
            if(e.ctrlKey && e.shiftKey && e.keyCode == 73) { e.preventDefault(); return false; }
            // CTRL+Shift+J (Console)
            if(e.ctrlKey && e.shiftKey && e.keyCode == 74) { e.preventDefault(); return false; }
            // CTRL+Shift+C (Inspect Element)
            if(e.ctrlKey && e.shiftKey && e.keyCode == 67) { e.preventDefault(); return false; }
            // CTRL+U (View Source)
            if(e.ctrlKey && e.keyCode == 85) { e.preventDefault(); return false; }
            // CTRL+S (Save Page)
            if(e.ctrlKey && e.keyCode == 83) { e.preventDefault(); return false; }
        });
    })();
    </script>

    <style>
        /* --- SECURITY CSS LAYERS --- */
        body {
            -webkit-user-select: none; /* Safari */
            -ms-user-select: none; /* IE 10 and IE 11 */
            user-select: none; /* Standard syntax */
            -webkit-touch-callout: none; /* iOS Safari */
        }
        
        /* Prevent Image Dragging (Save Image Prevention) */
        img {
            pointer-events: none;
            -webkit-user-drag: none;
            user-drag: none;
        }

        /* --- FIX ZOOM / SCROLL ISSUES --- */
        html, body {
            width: 100%;
            overflow-x: hidden; 
            position: relative;
            background-color: #f8fafc;
            color: #0f172a;
            cursor: none;
        }

        /* --- CUSTOM CURSOR --- */
        #cursor-dot, #cursor-outline {
            position: fixed;
            top: 0;
            left: 0;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            z-index: 9999;
            pointer-events: none;
        }
        #cursor-dot {
            width: 8px;
            height: 8px;
            background-color: #4f46e5;
        }
        #cursor-outline {
            width: 40px;
            height: 40px;
            border: 2px solid rgba(79, 70, 229, 0.5);
            transition: width 0.2s, height 0.2s, background-color 0.2s;
        }

        /* --- GLOWING MOUSE BLOB --- */
        #mouse-glow {
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: -1;
            transition: transform 0.1s ease-out;
        }

        /* --- LEGENDARY CYBER GLITCH EFFECT --- */
        .glitch-wrapper {
            position: relative;
            display: inline-block;
        }
        .glitch {
            position: relative;
            color: #4f46e5;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        /* Layer 1: Red Shift */
        .glitch::before {
            content: attr(data-text);
            position: absolute;
            left: -2px;
            text-shadow: 2px 0 #ff00c1;
            top: 0;
            color: #4f46e5;
            background: #f8fafc;
            overflow: hidden;
            clip: rect(0, 900px, 0, 0);
            animation: noise-anim-2 3s infinite linear alternate-reverse;
        }
        /* Layer 2: Blue Shift */
        .glitch::after {
            content: attr(data-text);
            position: absolute;
            left: 2px;
            text-shadow: -2px 0 #00fff9;
            top: 0;
            color: #4f46e5;
            background: #f8fafc;
            overflow: hidden;
            clip: rect(0, 900px, 0, 0);
            animation: noise-anim 2s infinite linear alternate-reverse;
        }

        @keyframes noise-anim {
            0% { clip: rect(21px, 9999px, 96px, 0); transform: skew(0.6deg); }
            5% { clip: rect(62px, 9999px, 35px, 0); transform: skew(0.6deg); }
            10% { clip: rect(93px, 9999px, 7px, 0); transform: skew(0.6deg); }
            15% { clip: rect(56px, 9999px, 16px, 0); transform: skew(0.6deg); }
            20% { clip: rect(69px, 9999px, 86px, 0); transform: skew(0.6deg); }
            25% { clip: rect(9px, 9999px, 57px, 0); transform: skew(0.6deg); }
            30% { clip: rect(54px, 9999px, 72px, 0); transform: skew(0.6deg); }
            35% { clip: rect(32px, 9999px, 88px, 0); transform: skew(0.6deg); }
            40% { clip: rect(87px, 9999px, 20px, 0); transform: skew(0.6deg); }
            45% { clip: rect(6px, 9999px, 60px, 0); transform: skew(0.6deg); }
            50% { clip: rect(48px, 9999px, 81px, 0); transform: skew(0.6deg); }
            55% { clip: rect(20px, 9999px, 24px, 0); transform: skew(0.6deg); }
            60% { clip: rect(67px, 9999px, 45px, 0); transform: skew(0.6deg); }
            65% { clip: rect(10px, 9999px, 62px, 0); transform: skew(0.6deg); }
            70% { clip: rect(39px, 9999px, 93px, 0); transform: skew(0.6deg); }
            75% { clip: rect(81px, 9999px, 12px, 0); transform: skew(0.6deg); }
            80% { clip: rect(29px, 9999px, 49px, 0); transform: skew(0.6deg); }
            85% { clip: rect(52px, 9999px, 8px, 0); transform: skew(0.6deg); }
            90% { clip: rect(74px, 9999px, 33px, 0); transform: skew(0.6deg); }
            95% { clip: rect(4px, 9999px, 95px, 0); transform: skew(0.6deg); }
            100% { clip: rect(59px, 9999px, 26px, 0); transform: skew(0.6deg); }
        }
        @keyframes noise-anim-2 {
            0% { clip: rect(15px, 9999px, 46px, 0); transform: skew(-0.6deg); }
            5% { clip: rect(34px, 9999px, 18px, 0); transform: skew(-0.6deg); }
            10% { clip: rect(78px, 9999px, 92px, 0); transform: skew(-0.6deg); }
            15% { clip: rect(45px, 9999px, 5px, 0); transform: skew(-0.6deg); }
            20% { clip: rect(82px, 9999px, 33px, 0); transform: skew(-0.6deg); }
            25% { clip: rect(27px, 9999px, 69px, 0); transform: skew(-0.6deg); }
            30% { clip: rect(90px, 9999px, 13px, 0); transform: skew(-0.6deg); }
            35% { clip: rect(41px, 9999px, 76px, 0); transform: skew(-0.6deg); }
            40% { clip: rect(63px, 9999px, 9px, 0); transform: skew(-0.6deg); }
            45% { clip: rect(19px, 9999px, 52px, 0); transform: skew(-0.6deg); }
            50% { clip: rect(73px, 9999px, 85px, 0); transform: skew(-0.6deg); }
            55% { clip: rect(5px, 9999px, 29px, 0); transform: skew(-0.6deg); }
            60% { clip: rect(38px, 9999px, 61px, 0); transform: skew(-0.6deg); }
            65% { clip: rect(94px, 9999px, 44px, 0); transform: skew(-0.6deg); }
            70% { clip: rect(51px, 9999px, 21px, 0); transform: skew(-0.6deg); }
            75% { clip: rect(11px, 9999px, 70px, 0); transform: skew(-0.6deg); }
            80% { clip: rect(66px, 9999px, 95px, 0); transform: skew(-0.6deg); }
            85% { clip: rect(31px, 9999px, 36px, 0); transform: skew(-0.6deg); }
            90% { clip: rect(89px, 9999px, 4px, 0); transform: skew(-0.6deg); }
            95% { clip: rect(23px, 9999px, 58px, 0); transform: skew(-0.6deg); }
            100% { clip: rect(55px, 9999px, 80px, 0); transform: skew(-0.6deg); }
        }

        /* --- FLOATING EMOJIS --- */
        .emoji-float {
            position: fixed;
            bottom: -50px;
            font-size: 24px;
            animation: floatUp 15s linear infinite;
            z-index: 0;
            opacity: 0.6;
        }
        @keyframes floatUp {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { transform: translateY(-110vh) rotate(360deg); opacity: 0; }
        }

        /* --- VERTICAL SCROLL --- */
        .vertical-slider { height: 40px; overflow: hidden; position: relative; }
        .slider-track { animation: scrollVertical 90s linear infinite; }
        .slider-item { height: 40px; display: flex; align-items: center; font-weight: 700; white-space: nowrap; }
        @keyframes scrollVertical { 0% { transform: translateY(0); } 100% { transform: translateY(-50%); } }

        /* --- JELLY CARD HOVER --- */
        .card-jelly:hover {
            animation: jelly 0.5s;
        }
        @keyframes jelly {
            0%, 100% { transform: scale(1, 1); }
            25% { transform: scale(0.95, 1.05); }
            50% { transform: scale(1.05, 0.95); }
            75% { transform: scale(0.98, 1.02); }
        }

        .service-icon { width: 24px; height: 24px; object-fit: contain; margin-right: 10px; }
        
        /* Hide scrollbar but keep functionality */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #4f46e5; }

        /* WhatsApp Pulse */
        .wa-pulse {
            animation: waPulse 2s infinite;
        }
        @keyframes waPulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(37, 211, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
        }
    </style>
</head>
<body class="antialiased relative overflow-x-hidden selection:bg-indigo-500 selection:text-white" oncontextmenu="return false;">

    <!-- Custom Cursor -->
    <div id="cursor-dot" class="hidden md:block"></div>
    <div id="cursor-outline" class="hidden md:block"></div>
    <!-- Glowing Blob behind cursor -->
    <div id="mouse-glow"></div>

    <!-- Floating Emojis Container -->
    <div id="emoji-container" class="pointer-events-none"></div>

    <!-- Live Notification -->
    <div id="live-notification" class="fixed bottom-4 left-4 z-50 transform translate-x-[-150%] transition-transform duration-500">
        <div class="bg-white/90 backdrop-blur border-2 border-indigo-100 p-4 rounded-2xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.2)] flex items-center gap-4 max-w-xs hover:scale-105 transition-transform cursor-pointer">
            <div class="relative">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center text-white shadow-lg">
                    <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                </div>
                <span class="absolute -top-1 -right-1 flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" id="notify-time">Just now</p>
                <p class="text-sm font-bold text-slate-800 leading-tight"><span id="notify-name">Ali</span> purchased <br><span class="text-indigo-600" id="notify-service">1k Followers</span> 🔥</p>
            </div>
        </div>
    </div>

    <!-- Floating WhatsApp Button (UPDATED IMAGE PATH) -->
    <a href="https://wa.me/923097856447" target="_blank" class="fixed bottom-6 right-6 z-50 group">
        <div class="relative">
             <img src="assets/img/icons/Whatsapp.png" class="w-16 h-16 wa-pulse hover:scale-110 transition-transform duration-300" alt="Chat on WhatsApp">
            <span class="absolute right-full mr-4 top-1/2 -translate-y-1/2 bg-white px-3 py-1 rounded-lg text-sm font-bold shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none text-slate-800">Chat with us!</span>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 bg-white/70 backdrop-blur-lg border-b border-slate-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- UPDATED: Flex container to push Logo Left and Login Right -->
            <div class="flex justify-between items-center h-28">
                
                <!-- Left: ULTRA LIVE LOGO (Moved Left & Big Size) -->
                <div class="flex-shrink-0 cursor-pointer" onclick="confettiBlast()">
                    <div class="relative">
                        <div class="absolute inset-0 bg-indigo-500 rounded-full blur-xl opacity-20 animate-pulse"></div>
                        <!-- Increased height to h-28 (mobile) and h-32 (desktop) -->
                        <img src="assets/img/logo.png" alt="LikexFollow" class="h-14 md:h-32 w-auto object-contain relative z-10 animate-ultra-pulse hover:rotate-6 transition-all duration-300">
                    </div>
                </div>

                <!-- Right: Login Button Only -->
                <div class="flex items-center gap-3">
                    <a href="login.php" class="px-6 py-2.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-indigo-600 transition-all shadow-lg hover:shadow-indigo-500/50 hover:-translate-y-0.5 active:scale-95 text-sm">
                        Login
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-36 pb-20 lg:pt-52 lg:pb-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-8 items-center">
                
                <!-- Text Content -->
                <div data-aos="fade-right">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-sm font-bold mb-8 hover:scale-105 transition-transform cursor-pointer shadow-sm">
                        <span class="animate-pulse">🔴</span> #1 Rated Panel • Instant Delivery
                    </div>
                    
                    <h1 class="font-display font-bold text-4xl lg:text-8xl leading-[0.9] text-slate-900 mb-6 tracking-tighter">
                        Social Media <br>
                        <!-- Legendary Glitch Effect -->
                        <span class="glitch-wrapper mt-2 block">
                            <span class="glitch font-cyber text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 tracking-wide" data-text="LikexFollow">Likexfollow.com</span>
                        </span>
                    </h1>
                    
                    <!-- Dynamic Service List -->
                    <div class="mb-10 transform -rotate-1 origin-left">
                        <div class="vertical-slider w-full bg-slate-100/50 p-2 rounded-lg border border-slate-200">
                            <div class="slider-track" id="dynamic-service-list"></div>
                        </div>
                    </div>

                    <p class="text-xl text-slate-600 mb-10 max-w-lg font-medium">
                        Join <span class="bg-indigo-100 text-indigo-700 px-1 rounded">50,000+ Agencies</span> using our secret API. Wholesale rates, instant start. 🚀
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="register.php" onclick="confettiBlast()" class="relative px-10 py-5 rounded-2xl bg-indigo-600 text-white font-bold text-xl shadow-[0_20px_50px_-12px_rgba(79,70,229,0.5)] transition-all transform hover:-translate-y-2 hover:shadow-[0_30px_60px_-15px_rgba(79,70,229,0.6)] flex items-center justify-center gap-3 group overflow-hidden active:scale-95">
                            <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full group-hover:animate-[shimmer_1s_infinite]"></div>
                            Create Account <i data-lucide="zap" class="w-6 h-6 group-hover:fill-yellow-300 group-hover:text-yellow-300 transition-colors"></i>
                        </a>
                        
                        <a href="services.php" class="px-10 py-5 rounded-2xl border-2 border-slate-200 text-slate-700 font-bold text-xl hover:border-slate-900 hover:bg-white hover:text-slate-900 transition-all flex items-center justify-center gap-2 hover:-translate-y-1">
                            View Services
                        </a>
                    </div>
                </div>

                <!-- 3D Mockup Visual -->
                <div class="relative h-[600px] w-full flex items-center justify-center perspective-[2000px]" data-aos="zoom-in" data-aos-duration="1000">
                    
                    <!-- Main Dashboard Card -->
                    <div class="absolute w-[95%] max-w-md bg-white/80 backdrop-blur-xl rounded-[2.5rem] p-8 shadow-[0_35px_60px_-15px_rgba(0,0,0,0.3)] border border-white/50 z-20 animate-float card-tilt cursor-pointer hover:shadow-indigo-500/20 transition-all duration-500 group">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-8">
                            <div class="flex gap-2">
                                <div class="w-4 h-4 rounded-full bg-red-400 group-hover:animate-bounce"></div>
                                <div class="w-4 h-4 rounded-full bg-yellow-400 group-hover:animate-bounce" style="animation-delay: 0.1s"></div>
                                <div class="w-4 h-4 rounded-full bg-green-400 group-hover:animate-bounce" style="animation-delay: 0.2s"></div>
                            </div>
                            <div class="h-3 w-24 bg-slate-100 rounded-full"></div>
                        </div>
                        
                        <!-- Chart -->
                        <div class="flex items-end gap-3 h-40 mb-8 px-2">
                            <div class="w-full bg-indigo-50 rounded-t-xl h-[40%] group-hover:h-[50%] transition-all duration-500"></div>
                            <div class="w-full bg-indigo-100 rounded-t-xl h-[60%] group-hover:h-[40%] transition-all duration-500"></div>
                            <div class="w-full bg-indigo-200 rounded-t-xl h-[50%] group-hover:h-[70%] transition-all duration-500"></div>
                            <div class="w-full bg-indigo-500 rounded-t-xl h-[80%] group-hover:h-[60%] transition-all duration-500 relative shadow-lg shadow-indigo-500/30">
                                <div class="absolute -top-12 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs font-bold py-2 px-3 rounded-lg whitespace-nowrap animate-bounce">
                                    +1M Views 🚀
                                    <div class="absolute bottom-[-6px] left-1/2 -translate-x-1/2 w-3 h-3 bg-slate-900 rotate-45"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Stats List -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm hover:scale-105 transition-transform hover:shadow-md hover:border-pink-200">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-yellow-400 to-pink-600 text-white flex items-center justify-center">
                                    <i data-lucide="instagram" class="w-6 h-6"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="h-2.5 w-24 bg-slate-200 rounded-full mb-2"></div>
                                    <div class="text-xs text-slate-400 font-bold uppercase">Followers Refill</div>
                                </div>
                                <span class="text-green-500 text-lg font-bold">+5,000</span>
                            </div>
                            
                            <div class="flex items-center gap-4 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm hover:scale-105 transition-transform hover:shadow-md hover:border-blue-200">
                                <div class="w-12 h-12 rounded-full bg-black text-white flex items-center justify-center">
                                    <i data-lucide="sparkles" class="w-6 h-6"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="h-2.5 w-24 bg-slate-200 rounded-full mb-2"></div>
                                    <div class="text-xs text-slate-400 font-bold uppercase">Canva Pro</div>
                                </div>
                                <span class="text-green-500 text-lg font-bold">Active</span>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Badge: Speed -->
                    <div class="absolute top-20 -right-8 bg-white p-5 rounded-3xl shadow-xl border border-slate-100 z-30 animate-float flex items-center gap-4 rotate-6 hover:rotate-0 transition-transform duration-300 hover:scale-110">
                        <div class="w-12 h-12 rounded-2xl bg-green-100 text-green-600 flex items-center justify-center">
                            <i data-lucide="zap" class="w-6 h-6 fill-current"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-bold uppercase tracking-wider">Speed</p>
                            <p class="font-bold text-xl text-slate-900">0.01s ⚡</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Infinite Marquee (Fast & Fun) -->
    <div class="w-full bg-slate-900 overflow-hidden py-6 border-y-4 border-indigo-500 rotate-1 scale-105 shadow-2xl z-20 relative">
        <div class="flex w-[200%] animate-scroll">
            <div class="flex items-center gap-16 whitespace-nowrap text-white/80 font-display font-bold text-3xl uppercase tracking-widest px-12">
                <span>🔥 YouTube Monetization</span> <span class="text-indigo-500">///</span>
                <span>🚀 TikTok Viral</span> <span class="text-indigo-500">///</span>
                <span>💎 Instagram Verified</span> <span class="text-indigo-500">///</span>
                <span>🎨 Canva Pro</span> <span class="text-indigo-500">///</span>
                <span>🍿 Netflix Premium</span> <span class="text-indigo-500">///</span>
                <span>🤖 Veo 3 AI</span> <span class="text-indigo-500">///</span>
            </div>
            <div class="flex items-center gap-16 whitespace-nowrap text-white/80 font-display font-bold text-3xl uppercase tracking-widest px-12">
                <span>🔥 YouTube Monetization</span> <span class="text-indigo-500">///</span>
                <span>🚀 TikTok Viral</span> <span class="text-indigo-500">///</span>
                <span>💎 Instagram Verified</span> <span class="text-indigo-500">///</span>
                <span>🎨 Canva Pro</span> <span class="text-indigo-500">///</span>
                <span>🍿 Netflix Premium</span> <span class="text-indigo-500">///</span>
                <span>🤖 Veo 3 AI</span> <span class="text-indigo-500">///</span>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="bg-white border-b border-slate-100 py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-100">
                <div class="text-center group hover:-translate-y-2 transition-transform duration-300">
                    <p class="text-5xl font-display font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">5.2M+</p>
                    <p class="text-sm font-bold text-slate-400 mt-2 uppercase tracking-widest">Orders Done</p>
                </div>
                <div class="text-center group hover:-translate-y-2 transition-transform duration-300">
                    <p class="text-5xl font-display font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">$0.001</p>
                    <p class="text-sm font-bold text-slate-400 mt-2 uppercase tracking-widest">Cheapest Rate</p>
                </div>
                <div class="text-center group hover:-translate-y-2 transition-transform duration-300">
                    <p class="text-5xl font-display font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">0.01s</p>
                    <p class="text-sm font-bold text-slate-400 mt-2 uppercase tracking-widest">Instant Speed</p>
                </div>
                <div class="text-center group hover:-translate-y-2 transition-transform duration-300">
                    <p class="text-5xl font-display font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">24/7</p>
                    <p class="text-sm font-bold text-slate-400 mt-2 uppercase tracking-widest">Human Support</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Choose Us Cards (Jelly Effect) -->
    <section id="features" class="py-32 relative">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-20" data-aos="fade-up">
                <h2 class="font-display font-bold text-5xl text-slate-900 mb-6">Why the Pros <span class="text-indigo-600 underline decoration-wavy decoration-indigo-300">Love Us</span></h2>
                <p class="text-slate-600 text-xl font-medium">We don't just sell services; we sell dominance.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-10">
                <div class="bg-white p-10 rounded-[2.5rem] shadow-xl border border-slate-100 card-jelly cursor-pointer group hover:border-indigo-500 transition-all" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-20 h-20 rounded-3xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-8 text-3xl group-hover:rotate-12 transition-transform duration-500">⚡</div>
                    <h3 class="font-display font-bold text-3xl text-slate-900 mb-4">Lightning Speed</h3>
                    <p class="text-slate-500 text-lg leading-relaxed">Stop waiting. Our API is faster than your crush's reply. Thousands of orders processed in seconds.</p>
                </div>

                <div class="bg-indigo-600 p-10 rounded-[2.5rem] shadow-2xl shadow-indigo-500/40 card-jelly cursor-pointer transform md:-translate-y-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="absolute -top-6 -right-6 bg-yellow-400 text-black font-black text-sm px-6 py-2 rounded-full shadow-lg rotate-12 animate-pulse">BEST SELLER</div>
                    <div class="w-20 h-20 rounded-3xl bg-white/20 text-white flex items-center justify-center mb-8 text-3xl backdrop-blur-sm">💎</div>
                    <h3 class="font-display font-bold text-3xl text-white mb-4">Wholesale Rates</h3>
                    <p class="text-indigo-100 text-lg leading-relaxed">Direct provider pricing. Resell our $0.001 services for $5.00 and keep 100% of the profit. Crazy margins.</p>
                </div>

                <div class="bg-white p-10 rounded-[2.5rem] shadow-xl border border-slate-100 card-jelly cursor-pointer group hover:border-green-500 transition-all" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-20 h-20 rounded-3xl bg-green-100 text-green-600 flex items-center justify-center mb-8 text-3xl group-hover:rotate-12 transition-transform duration-500">🛡️</div>
                    <h3 class="font-display font-bold text-3xl text-slate-900 mb-4">Non-Drop Guarantee</h3>
                    <p class="text-slate-500 text-lg leading-relaxed">Quality that sticks like glue. Includes a 30-day auto-refill button. No drops, zero headaches.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Grid (Pop) -->
    <section class="py-20 bg-slate-50 border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="font-display font-bold text-4xl mb-12">Crazy Variety. Crazy Prices.</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all hover:-translate-y-2 cursor-pointer group">
                    <i data-lucide="instagram" class="w-10 h-10 mx-auto mb-4 text-pink-500 group-hover:scale-125 transition-transform"></i>
                    <h4 class="font-bold text-xl">Instagram</h4>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all hover:-translate-y-2 cursor-pointer group">
                    <i data-lucide="music-2" class="w-10 h-10 mx-auto mb-4 text-black group-hover:scale-125 transition-transform"></i>
                    <h4 class="font-bold text-xl">TikTok</h4>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all hover:-translate-y-2 cursor-pointer group">
                    <i data-lucide="youtube" class="w-10 h-10 mx-auto mb-4 text-red-600 group-hover:scale-125 transition-transform"></i>
                    <h4 class="font-bold text-xl">YouTube</h4>
                </div>
                <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all hover:-translate-y-2 cursor-pointer group">
                    <i data-lucide="smartphone" class="w-10 h-10 mx-auto mb-4 text-blue-600 group-hover:scale-125 transition-transform"></i>
                    <h4 class="font-bold text-xl">Premium Apps</h4>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white pt-20 pb-10">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="font-display font-bold text-4xl mb-6">Ready to dominate?</h2>
            <button onclick="confettiBlast()" class="px-10 py-4 bg-slate-900 text-white font-bold rounded-full hover:bg-indigo-600 hover:scale-110 transition-all shadow-xl">Join Now Free</button>
            <p class="text-slate-400 mt-10">© 2024 LikexFollow. Made with 🔥 for Winners.</p>
        </div>
    </footer>

    <!-- SCRIPTS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, offset: 50, duration: 800 });
        lucide.createIcons();

        // --- 1. SERVICE DATA ---
        const services = [
            "Buy YouTube Subscribers", "Buy Instagram Followers", "Buy Canva Pro", "Buy TikTok Views", 
            "Buy Paid Courses", "Buy Facebook Followers", "Buy YouTube Monetization", "Buy Netflix Premium", 
            "Buy SEO Backlinks", "Buy Instagram Likes", "Buy Dropshipping Course", "Buy Spotify Premium", 
            "Buy YouTube Watch Time", "Buy Amazon Prime", "Buy TikTok Followers", "Buy Email Marketing Lists", 
            "Buy Graphic Designing Course", "Buy Instagram Reels Views", "Buy Google Ads Clicks", "Buy Snapchat Story Views", 
            "Buy Freelancing Course", "Buy Twitter Followers", "Buy WhatsApp Marketing Leads", "Buy Apple Music Subscription", 
            "Buy YouTube Shorts Views", "Buy Envato Elements", "Buy Instagram Impressions", "Buy Guest Posts", 
            "Buy Crypto Trading Course", "Buy Grammarly Premium", "Buy Facebook Video Views", "Buy YouTube Views", 
            "Buy AI Tools Mastery Course", "Buy Facebook Page Likes", "Buy Twitter Retweets", "Buy YouTube Automation Course", 
            "Buy Adobe Full Suite Cheap", "Buy Instagram Story Views", "Buy Tidal Premium", "Buy Snapchat Followers", 
            "Buy TikTok Likes", "Buy Instagram Comments", "Buy Apple Music Subscription", "Buy Website Traffic", 
            "Buy Facebook Comments", "Buy Twitter Likes"
        ];

        // --- 2. HELPERS ---
        function getBrandColor(text) {
            text = text.toLowerCase();
            if (text.includes("youtube")) return "#FF0000";
            if (text.includes("instagram")) return "#E1306C";
            if (text.includes("facebook")) return "#1877F2";
            if (text.includes("tiktok")) return "#000000";
            if (text.includes("spotify")) return "#1DB954";
            if (text.includes("canva")) return "#00C4CC";
            if (text.includes("netflix")) return "#E50914";
            if (text.includes("twitter")) return "#1DA1F2";
            return "#4f46e5"; 
        }

        function getIconName(text) {
            const brands = ["YouTube", "Instagram", "Facebook", "TikTok", "Spotify", "Canva", "Netflix", "Twitter", "Snapchat", "WhatsApp", "Google", "Amazon", "Adobe", "Envato", "Apple", "Grammarly"];
            for (let brand of brands) {
                if (text.toLowerCase().includes(brand.toLowerCase())) return `${brand}.png`; 
            }
            return "default.png";
        }

        // --- 3. GENERATE LIST ---
        const track = document.getElementById('dynamic-service-list');
        const contentToRender = [...services, ...services]; 

        contentToRender.forEach(item => {
            const div = document.createElement('div');
            div.className = 'slider-item text-xl md:text-2xl mr-8 hover:scale-110 transition-transform cursor-pointer'; 
            
            const color = getBrandColor(item);
            const iconName = getIconName(item);
            
            const img = document.createElement('img');
            img.src = `assets/img/icons/${iconName}`;
            img.className = 'service-icon';
            img.onerror = function() { this.style.display = 'none'; };

            const span = document.createElement('span');
            span.innerText = item;
            span.style.color = color;

            div.appendChild(img);
            div.appendChild(span);
            track.appendChild(div);
        });

        // --- 4. CUSTOM CURSOR LOGIC ---
        const cursorDot = document.getElementById('cursor-dot');
        const cursorOutline = document.getElementById('cursor-outline');
        const mouseGlow = document.getElementById('mouse-glow');

        window.addEventListener('mousemove', (e) => {
            const posX = e.clientX;
            const posY = e.clientY;

            // Simple cursor follow
            cursorDot.style.left = `${posX}px`;
            cursorDot.style.top = `${posY}px`;

            // Laggy follow for outline
            cursorOutline.animate({
                left: `${posX}px`,
                top: `${posY}px`
            }, { duration: 500, fill: "forwards" });

            // Glow blob follow
            mouseGlow.animate({
                left: `${posX}px`,
                top: `${posY}px`
            }, { duration: 2000, fill: "forwards" }); // Slower for effect
        });

        // Hover effects on links for cursor
        document.querySelectorAll('a, button, .slider-item, .card-jelly').forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursorOutline.style.width = '60px';
                cursorOutline.style.height = '60px';
                cursorOutline.style.backgroundColor = 'rgba(79, 70, 229, 0.1)';
            });
            el.addEventListener('mouseleave', () => {
                cursorOutline.style.width = '40px';
                cursorOutline.style.height = '40px';
                cursorOutline.style.backgroundColor = 'transparent';
            });
        });

        // --- 5. FLOATING EMOJIS ---
        const emojis = ["🚀", "🔥", "💸", "💖", "💎", "⚡", "📈"];
        const container = document.getElementById('emoji-container');

        function createEmoji() {
            const el = document.createElement('div');
            el.innerText = emojis[Math.floor(Math.random() * emojis.length)];
            el.className = 'emoji-float';
            el.style.left = Math.random() * 100 + 'vw';
            el.style.animationDuration = (Math.random() * 10 + 10) + 's';
            el.style.fontSize = (Math.random() * 20 + 20) + 'px';
            container.appendChild(el);

            setTimeout(() => { el.remove(); }, 15000);
        }
        setInterval(createEmoji, 1000); // Create one every second

        // --- 6. CONFETTI BLAST ---
        function confettiBlast() {
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#4f46e5', '#ec4899', '#eab308']
            });
        }

        // --- 7. TILT CARD ---
        const tiltCard = document.querySelector('.card-tilt');
        tiltCard.addEventListener('mousemove', (e) => {
            const rect = tiltCard.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const xRot = -((y - rect.height/2) / rect.height * 20); // More dramatic tilt
            const yRot = (x - rect.width/2) / rect.width * 20;
            tiltCard.style.transform = `perspective(1000px) rotateX(${xRot}deg) rotateY(${yRot}deg) scale(1.02)`;
        });
        tiltCard.addEventListener('mouseleave', () => {
            tiltCard.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
        });

        // --- 8. NOTIFICATIONS ---
        const nNames = ["Ali", "Sarah", "John", "Mike", "David", "Ahmed", "Zara"];
        const nServices = ["1k Followers", "Canva Pro", "Watchtime", "5k Views", "Netflix 1M"];
        
        function loopNotify() {
            setTimeout(() => {
                const toast = document.getElementById('live-notification');
                document.getElementById('notify-name').innerText = nNames[Math.floor(Math.random()*nNames.length)];
                document.getElementById('notify-service').innerText = nServices[Math.floor(Math.random()*nServices.length)];
                
                toast.classList.remove('translate-x-[-150%]'); // Show
                setTimeout(() => toast.classList.add('translate-x-[-150%]'), 4000); // Hide
                loopNotify();
            }, Math.random() * 5000 + 3000);
        }
        setTimeout(loopNotify, 2000);

    </script>
</body>
</html>