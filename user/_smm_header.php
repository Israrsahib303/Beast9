<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- REQUIRED HELPERS ---
if (file_exists(__DIR__ . '/../includes/helpers.php')) {
    require_once __DIR__ . '/../includes/helpers.php';
}
requireLogin(); // SMM Panel ke liye login zaroori hai

// User Data
$user_balance = getUserBalance($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
$site_name = $GLOBALS['settings']['site_name'] ?? 'SubHub';
$logo = $GLOBALS['settings']['site_logo'] ?? '';

// --- CURRENCY SETUP ---
$curr_list = function_exists('getCurrencyList') ? getCurrencyList() : ['PKR' => ['rate'=>1, 'symbol'=>'Rs', 'flag'=>'🇵🇰', 'name'=>'Pakistani Rupee']];
$curr_code = $_COOKIE['site_currency'] ?? 'PKR';
if (!isset($curr_list[$curr_code])) $curr_code = 'PKR';

$curr_data = $curr_list[$curr_code];
$curr_flag = $curr_data['flag'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= sanitize($site_name) ?></title>
    
    <link rel="stylesheet" href="../assets/css/smm_style.css?v=3.2">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --app-primary: #4f46e5;
            --app-bg: #f8fafc;
            --app-text: #1e293b;
        }

        body {
            background-color: var(--app-bg);
            color: var(--app-text);
            font-family: 'Outfit', sans-serif;
            margin: 0; padding: 0;
        }

        /* --- FLOATING ROUNDED HEADER --- */
        .smm-header-wrapper {
            padding: 15px;
            position: sticky; top: 0; z-index: 1000;
        }

        .smm-header-inner {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 20px; 
            background: var(--app-primary); 
            color: #fff;
            border-radius: 50px; /* Fully Rounded */
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
            margin: 0 auto; max-width: 1200px;
        }

        /* Left Area: Back Button + Logo */
        .logo-area { display: flex; align-items: center; gap: 15px; }
        
        .btn-back-home {
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.2); color: #fff;
            width: 100px; height: 25px; border-radius: 50%; 
            text-decoration: none; backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.15); 
            transition: 0.2s;
        }
        .btn-back-home:hover { background: #fff; color: var(--app-primary); transform: scale(1.05); }
        .btn-back-home svg { width: 20px; height: 20px; stroke-width: 2.5; }

        .site-logo-img { height: 30px; object-fit: contain; }
        .site-title-text { font-size: 1.2rem; font-weight: 800; letter-spacing: -0.5px; }

        /* Right Area: Currency + Profile */
        .right-actions { display: flex; align-items: center; gap: 10px; }

        /* Currency Button Style */
        .btn-currency-header {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            padding: 12px 14px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.1rem;
            display: flex; align-items: center; gap: 6px;
            transition: 0.2s;
            height: 40px;
        }
        .btn-currency-header:hover {
            background: #fff; color: var(--app-primary); transform: translateY(-2px);
        }
        .curr-code-txt { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }

        .user-profile-icon {
            width: 40px; height: 40px; 
            background: rgba(255,255,255,0.2); 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            color: #fff; text-decoration: none;
            border: 1px solid rgba(255,255,255,0.15);
            transition: 0.2s;
        }
        .user-profile-icon:hover { background: #fff; color: var(--app-primary); transform: scale(1.05); }
        .user-profile-icon svg { width: 22px; height: 22px; }

        /* Modal Styles (Same as main site) */
        .curr-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); z-index: 9999; justify-content: center; align-items: center; animation: fadeIn 0.2s ease-out; }
        .curr-modal { background: #fff; width: 90%; max-width: 420px; border-radius: 24px; padding: 25px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); color: #333; animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .curr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 15px; }
        .curr-item { display: flex; align-items: center; gap: 12px; padding: 15px; border-radius: 16px; border: 1px solid #eee; cursor: pointer; transition: 0.2s; background: #f8fafc; }
        .curr-item:hover { border-color: var(--app-primary); background: #eff6ff; transform: scale(1.02); }
        .curr-item.active { border: 2px solid var(--app-primary); background: #eef2ff; }
        .curr-flag { font-size: 1.8rem; }
        .curr-info { display: flex; flex-direction: column; }
        .curr-name { font-size: 0.9rem; font-weight: 700; color: #1e293b; }
        .curr-sym { font-size: 0.75rem; color: #64748b; }
        
        @keyframes fadeIn { from {opacity:0} to {opacity:1} }
        @keyframes zoomIn { from {transform:scale(0.9)} to {transform:scale(1)} }

        /* Content Wrapper */
        .smm-content-wrapper {
            padding: 10px 20px 80px 20px; /* Bottom padding for footer nav */
            max-width: 1200px; margin: 0 auto;
        }
    </style>
</head>
<body class="smm-app-theme">

<div class="smm-header-wrapper">
    <div class="smm-header-inner">
        
        <div class="logo-area">
            <a href="index.php" class="btn-back-home" title="Back to Home">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M19 12H5m7 7l-7-7 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            
            <?php if($logo): ?>
                <img src="../assets/img/<?= sanitize($logo) ?>" class="site-logo-img" alt="Logo">
            <?php else: ?>
                <span class="site-title-text"><?= sanitize($site_name) ?></span>
            <?php endif; ?>
        </div>

        <div class="right-actions">
            <button class="btn-currency-header" onclick="openCurrModal()">
                <span><?= $curr_flag ?></span>
                <span class="curr-code-txt"><?= $curr_code ?></span>
            </button>

            <a href="profile.php" class="user-profile-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </a>
        </div>
        
    </div>
</div>

<div id="currModal" class="curr-modal-overlay" onclick="if(event.target===this) closeCurrModal()">
    <div class="curr-modal">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; font-weight:800;">Select Currency</h3>
            <button onclick="closeCurrModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <div class="curr-grid">
            <?php foreach($curr_list as $code => $c): ?>
            <div class="curr-item <?= ($code == $curr_code) ? 'active' : '' ?>" onclick="setCurrency('<?= $code ?>')">
                <span class="curr-flag"><?= $c['flag'] ?></span>
                <div class="curr-info">
                    <span class="curr-name"><?= $code ?></span>
                    <span class="curr-sym"><?= $c['name'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function openCurrModal() { document.getElementById('currModal').style.display = 'flex'; }
function closeCurrModal() { document.getElementById('currModal').style.display = 'none'; }

function setCurrency(code) {
    // Set Cookie for 30 days
    document.cookie = "site_currency=" + code + "; path=/; max-age=" + (30*24*60*60);
    location.reload(); // Reload to apply new prices
}
</script>

<div class="smm-content-wrapper">