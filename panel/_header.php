<?php
// --- 1. CORE HELPERS & SESSION (Sabse Pehle) ---
require_once __DIR__ . '/../includes/helpers.php';

// --- 2. GHOST MODE LOCK (Security Check) ---
// Agar banda Secret Link se nahi aaya, toh usse 404 dikhao
require_once __DIR__ . '/admin_lock.php';

// --- 3. AUTH CHECK (Admin Login Hai?) ---
require_once __DIR__ . '/_auth_check.php';

// --- Page Logic Starts Below ---
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= htmlspecialchars($GLOBALS['settings']['site_name'] ?? 'SubHub') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --sidebar-width: 260px;
        }
        
        body {
            background-color: #f3f4f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            padding-top: 20px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 0 25px 20px;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            border-bottom: 1px solid #f3f4f6;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }

        .nav-link {
            color: #4b5563;
            padding: 12px 25px;
            font-weight: 500;
            display: flex; align-items: center; gap: 12px;
            transition: 0.2s;
            border-left: 4px solid transparent;
        }
        
        .nav-link:hover {
            background-color: #f9fafb;
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background-color: #eef2ff;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: 0.3s;
        }

        /* Mobile Toggle */
        .mobile-toggle { display: none; position: fixed; top: 15px; right: 15px; z-index: 1100; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block; }
        }
        
        /* Section Headers */
        .nav-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #9ca3af;
            padding: 20px 25px 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <button class="btn btn-primary mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-bolt"></i> Admin Panel
        </div>
        <a class="nav-link text-danger mt-3" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <nav class="nav flex-column">
            <div class="nav-header">Main</div>
            <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a class="nav-link <?= ($current_page == 'orders.php') ? 'active' : '' ?>" href="orders.php">
                <i class="fas fa-shopping-bag"></i> Sub Orders
            </a>
            <a class="nav-link <?= ($current_page == 'smm_orders.php') ? 'active' : '' ?>" href="smm_orders.php">
                <i class="fas fa-rocket"></i> SMM Orders
            </a>
            <a class="nav-link <?= ($current_page == 'users.php') ? 'active' : '' ?>" href="users.php">
                <i class="fas fa-users"></i> Users
            </a>
            
            <div class="nav-header">Finance</div>
            <a class="nav-link <?= ($current_page == 'payments.php') ? 'active' : '' ?>" href="payments.php">
                <i class="fas fa-wallet"></i> Payments
            </a>
            <a class="nav-link <?= ($current_page == 'methods.php') ? 'active' : '' ?>" href="methods.php">
                <i class="fas fa-university"></i> Methods
            </a>
             <a class="nav-link <?= ($current_page == 'promo_codes.php') ? 'active' : '' ?>" href="promo_codes.php">
                <i class="fas fa-tag"></i> Promo Codes
            </a>
            
            <div class="nav-header">Services</div>
            <a class="nav-link <?= ($current_page == 'categories.php') ? 'active' : '' ?>" href="categories.php">
                <i class="fas fa-list"></i> Categories
            </a>
            <a class="nav-link <?= ($current_page == 'products.php') ? 'active' : '' ?>" href="products.php">
                <i class="fas fa-box"></i> Products
            </a>
             <a class="nav-link <?= ($current_page == 'smm_categories.php') ? 'active' : '' ?>" href="smm_categories.php">
                <i class="fas fa-layer-group"></i> SMM Categories
            </a>
            <a class="nav-link <?= ($current_page == 'smm_services.php') ? 'active' : '' ?>" href="smm_services.php">
                <i class="fas fa-list-ul"></i> SMM Services
            </a>

            <div class="nav-header">Tools & Updates</div>
            
            <a class="nav-link <?= ($current_page == 'broadcast.php') ? 'active' : '' ?>" href="broadcast.php">
                <i class="fas fa-bullhorn"></i> Broadcasts
            </a>

            <a class="nav-link <?= ($current_page == 'updates_log.php') ? 'active' : '' ?>" href="updates_log.php">
                <i class="fas fa-history"></i> Updates Log
            </a>
            
            <a class="nav-link <?= ($current_page == 'wheel_prizes.php') ? 'active' : '' ?>" href="wheel_prizes.php">
                <i class="fas fa-dharmachakra"></i> Spin Wheel
            </a>
            <a class="nav-link <?= ($current_page == 'tickets.php') ? 'active' : '' ?>" href="tickets.php">
                <i class="fas fa-headset"></i> Support Tickets
            </a>

            <a class="nav-link <?= ($current_page == 'testimonials.php') ? 'active' : '' ?>" href="testimonials.php">
                <i class="fas fa-video"></i> Video Rewards
            </a>
            <a class="nav-link <?= ($current_page == 'tutorials.php') ? 'active' : '' ?>" href="tutorials.php">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> Tutorials Manage
            </a>
            
            <div class="nav-header">System</div>
            <a class="nav-link <?= ($current_page == 'providers.php') ? 'active' : '' ?>" href="providers.php">
                <i class="fas fa-server"></i> Providers
            </a>
             <a class="nav-link <?= ($current_page == 'cron_jobs.php') ? 'active' : '' ?>" href="cron_jobs.php">
                <i class="fas fa-clock"></i> Cron Jobs
            </a>
            <a class="nav-link <?= ($current_page == 'smm_logs.php') ? 'active' : '' ?>" href="smm_logs.php">
                <i class="fas fa-file-alt"></i> Logs
            </a>
            <a class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a class="nav-link <?= ($current_page == 'vpn_settings.php') ? 'active' : '' ?>" href="vpn_settings.php">
                <i class="fas fa-shield-alt"></i> VPN Settings
            </a>
            <a class="nav-link <?= ($current_page == 'google_settings.php') ? 'active' : '' ?>" href="google_settings.php">
                <i class="fab fa-google"></i> Google Login
            </a>
            <a class="nav-link <?= ($current_page == 'system_controls.php') ? 'active' : '' ?>" href="system_controls.php">
                <i class="fa-solid fa-play"></i> System controls
            </a>
            <a class="nav-link <?= ($current_page == 'downloads_manager.php') ? 'active' : '' ?>" href="downloads_manager.php">
                <i class="fas fa-download"></i> Downloads Manager
            </a>
            <a class="nav-link <?= ($current_page == 'menus.php') ? 'active' : '' ?>" href="menus.php">
                <i class="fa-solid fa-list"></i> User menu nav 
            </a>
            <a class="nav-link text-danger mt-3" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <div class="main-content">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Action completed successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                An error occurred!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>