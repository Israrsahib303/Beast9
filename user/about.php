<?php include '_header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Founder Profile - Israr Liaqat</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #f8f9fa; /* Light Background */
            color: #333;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background - Light Mode */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .animated-bg::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            top: -200px;
            left: -200px;
            animation: float 20s infinite ease-in-out;
        }

        .animated-bg::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(118, 75, 162, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -150px;
            right: -150px;
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(50px, 50px) scale(1.1); }
        }

        /* Particles - Darker for visibility on light bg */
        .particle {
            position: absolute;
            width: 5px;
            height: 5px;
            background: rgba(102, 126, 234, 0.6);
            border-radius: 50%;
            animation: particle-float 10s infinite;
        }

        @keyframes particle-float {
            0% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path d="M0,50 Q300,100 600,50 T1200,50 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.1)"/></svg>') repeat-x;
            animation: wave 10s linear infinite;
            opacity: 0.4;
        }

        @keyframes wave {
            0% { background-position: 0 0; }
            100% { background-position: 1200px 0; }
        }

        .hero-content {
            text-align: center;
            color: white;
            z-index: 1;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            animation: glow 3s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { text-shadow: 0 0 10px rgba(255, 255, 255, 0.3); }
            50% { text-shadow: 0 0 20px rgba(255, 255, 255, 0.6); }
        }

        .hero-subtitle {
            font-size: 18px;
            font-weight: 400;
            opacity: 0.95;
            letter-spacing: 0.5px;
        }

        /* Main Container */
        .profile-wrapper {
            position: relative;
            z-index: 1;
            max-width: 1300px;
            margin: -120px auto 60px;
            padding: 0 30px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 35px;
        }

        /* Modern Card Style - Light Mode */
        .modern-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05); /* Soft Shadow */
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d; /* For 3D Tilt */
        }

        .modern-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.15);
        }

        /* Profile Card Specific */
        .profile-main {
            position: sticky;
            top: 20px;
        }

        .avatar-section {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 30px;
            transform: translateZ(20px); /* 3D Pop */
        }

        .avatar-ring {
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            animation: rotate 6s linear infinite;
        }

        @keyframes rotate {
            100% { transform: rotate(360deg); }
        }

        .avatar-inner {
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-img {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
        }

        .verified-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #1da1f2, #0d7cc1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            color: white;
            font-size: 20px;
            animation: bounce 2s ease-in-out infinite;
            z-index: 2;
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        .name-title {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 8px;
        }

        .role-badge {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            color: #667eea;
            margin-bottom: 25px;
            background: rgba(102, 126, 234, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            width: 100%;
        }

        .bio-text {
            font-size: 15px;
            line-height: 1.8;
            color: #555;
            text-align: center;
            margin-bottom: 35px;
            font-weight: 400;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
            transform: translateZ(10px);
        }

        .social-icon {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .social-icon:hover {
            transform: translateY(-5px) scale(1.1);
        }

        .whatsapp-link { background: linear-gradient(135deg, #25D366, #128C7E); }
        .instagram-link { background: linear-gradient(135deg, #f09433, #dc2743, #bc1888); }
        .website-link { background: linear-gradient(135deg, #667eea, #764ba2); }

        /* CTA Button */
        .cta-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 17px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .cta-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        /* Right Column Cards */
        .details-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-header {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header i {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 10px;
            border-radius: 12px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .stat-box {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            transform: translateZ(10px);
        }

        .stat-box:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.1);
        }

        .stat-icon {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Vision Text */
        .vision-text {
            font-size: 16px;
            line-height: 1.9;
            color: #555;
        }

        .vision-text strong {
            color: #667eea;
            font-weight: 700;
        }

        /* Expertise Tags */
        .tag-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .skill-tag {
            padding: 12px 20px;
            background: white;
            border: 1px solid rgba(102, 126, 234, 0.15);
            border-radius: 12px;
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .skill-tag:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-3px) scale(1.05);
            border-color: transparent;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .profile-main {
                position: relative;
                top: 0;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                height: 300px;
            }
            .profile-wrapper {
                margin-top: -80px;
                padding: 0 20px;
            }
            .modern-card {
                padding: 30px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .hero-title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>

    <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
    <div class="particle" style="left: 25%; animation-delay: 2s;"></div>
    <div class="particle" style="left: 45%; animation-delay: 4s;"></div>
    <div class="particle" style="left: 65%; animation-delay: 1s;"></div>
    <div class="particle" style="left: 85%; animation-delay: 3s;"></div>

    <div class="hero-section">
        <div class="hero-content animate__animated animate__fadeInDown">
            <h1 class="hero-title">Meet The Visionary</h1>
            <p class="hero-subtitle">Transforming Ideas Into Digital Reality</p>
        </div>
    </div>

    <div class="profile-wrapper">
        <div class="profile-grid">
            
            <div class="modern-card profile-main animate__animated animate__fadeInLeft" data-tilt>
                <div class="avatar-section">
                    <div class="avatar-ring"></div>
                    <div class="avatar-inner">
                        <img src="../assets/img/Mypic.jpeg" alt="Israr Liaqat" class="avatar-img">
                    </div>
                    <div class="verified-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>

                <h1 class="name-title">Israr Liaqat</h1>
                <div class="role-badge">🚀 Founder & CEO</div>
                <p class="bio-text">
                    Tech Enthusiast, Full Stack Developer & SMM Expert. Building digital empires and helping creators grow since 2020.
                </p>

                <div class="social-links">
                    <a href="https://wa.me/" class="social-icon whatsapp-link">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="https://instagram.com/" class="social-icon instagram-link">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://likexfollow.com" class="social-icon website-link">
                        <i class="fas fa-globe"></i>
                    </a>
                </div>

                <button class="cta-btn">
                    <span><i class="fas fa-comment-dots"></i> Chat with Founder</span>
                </button>
            </div>

            <div class="details-section">
                
                <div class="modern-card animate__animated animate__fadeInRight" data-tilt>
                    <h2 class="section-header">
                        <i class="fas fa-trophy"></i>
                        Achievements
                    </h2>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="stat-value">5+</div>
                            <div class="stat-label">Years Exp.</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div class="stat-value">10K+</div>
                            <div class="stat-label">Orders</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-value">500+</div>
                            <div class="stat-label">Happy Clients</div>
                        </div>
                    </div>
                </div>

                <div class="modern-card animate__animated animate__fadeInRight" style="animation-delay: 0.2s;" data-tilt>
                    <h2 class="section-header">
                        <i class="fas fa-lightbulb"></i>
                        My Vision
                    </h2>
                    <p class="vision-text">
                        My goal is to provide affordable, high-quality digital tools to everyone. Whether you are a creator or business owner, <strong>LikexFollow</strong> is built to accelerate your growth.
                    </p>
                </div>

                <div class="modern-card animate__animated animate__fadeInRight" style="animation-delay: 0.4s;" data-tilt>
                    <h2 class="section-header">
                        <i class="fas fa-code"></i>
                        Expertise
                    </h2>
                    <div class="tag-container">
                        <span class="skill-tag"><i class="fas fa-chart-line"></i> SMM Growth</span>
                        <span class="skill-tag"><i class="fas fa-laptop-code"></i> Web Development</span>
                        <span class="skill-tag"><i class="fas fa-rocket"></i> LikexFollow Panel</span>
                        <span class="skill-tag"><i class="fas fa-robot"></i> Automation</span>
                        <span class="skill-tag"><i class="fas fa-palette"></i> Graphic Design</span>
                        <span class="skill-tag"><i class="fas fa-briefcase"></i> Business Strategy</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Simple Vanilla JS implementation of Tilt effect
        document.querySelectorAll('[data-tilt]').forEach((element) => {
            element.addEventListener('mousemove', (e) => {
                const rect = element.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -5; // Max rotation 5deg
                const rotateY = ((x - centerX) / centerX) * 5;

                element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
            });

            element.addEventListener('mouseleave', () => {
                element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            });
        });
    </script>

</body>
</html>

<?php include '_footer.php'; ?>