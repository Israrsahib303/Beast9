<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. REQUIRED FILES ---
if (file_exists(__DIR__ . '/../includes/helpers.php')) {
    require_once __DIR__ . '/../includes/helpers.php';
}
if (file_exists(__DIR__ . '/../includes/db.php')) {
    require_once __DIR__ . '/../includes/db.php'; 
}

// --- 2. USER BALANCE LOGIC ---
$user_id = $_SESSION['user_id'] ?? 0;
$user_balance = 0.00;

if ($user_id > 0) {
    if (function_exists('getUserBalance')) {
        $user_balance = getUserBalance($user_id);
    } else {
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_balance = $stmt->fetchColumn() ?? 0.00;
    }
}

// --- 3. SITE SETTINGS ---
$site_name = $GLOBALS['settings']['site_name'] ?? 'SubHub';
$site_logo = $GLOBALS['settings']['site_logo'] ?? '';
$primary_color = '#2563eb'; 

// --- 4. CURRENCY SETUP ---
// Currency List get karein (helpers.php se)
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <meta name="theme-color" content="<?php echo $primary_color; ?>">
    <meta name="msapplication-navbutton-color" content="<?php echo $primary_color; ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title><?= htmlspecialchars($GLOBALS['settings']['seo_title'] ?? $GLOBALS['settings']['site_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($GLOBALS['settings']['seo_desc'] ?? '') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($GLOBALS['settings']['seo_keywords'] ?? '') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* --- Theme Colors --- */
            --primary: #2563eb;         
            --primary-dark: #1d4ed8;    
            --primary-light: #eff6ff;   
            
            --bg-body: #f8fafc;         
            --bg-card: #ffffff;         
            
            --text-main: #0f172a;       
            --text-sub: #64748b;        
            --border: #e2e8f0;          
            
            /* --- Layout Dimensions --- */
            --nav-height: -10px;         
            --container-width: 700px;
            
            /* --- Effects --- */
            --radius: 16px;
            --shadow: 0 4px 20px rgba(0,0,0,0.03);
            --transition: 0.2s ease-in-out;
        }

        /* --- CSS Reset --- */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; outline: none; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            font-size: 15px;
            line-height: 1.6;
            padding-top: calc(var(--nav-height) + 20px);
            min-height: 100vh;
            overflow-x: hidden; 
        }

        a { text-decoration: none; color: inherit; transition: var(--transition); }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }
        img { max-width: 100%; display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .main-content-wrapper {
            animation: fadeIn 0.5s ease-out forwards;
            width: 100%;
            max-width: var(--container-width);
            margin: 0 auto;
            padding: 0 20px; 
        }

        /* --- Custom Scrollbar --- */
        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    </style>
    <style>
    :root {
        --primary: <?= $GLOBALS['settings']['theme_primary'] ?? '#4f46e5' ?>;
        --secondary: <?= $GLOBALS['settings']['theme_secondary'] ?? '#7c3aed' ?>;
        --bg-body: <?= $GLOBALS['settings']['theme_bg'] ?? '#f8fafc' ?>;
        --card-bg: <?= $GLOBALS['settings']['theme_card_bg'] ?? '#ffffff' ?>;
        --text-main: <?= $GLOBALS['settings']['theme_text'] ?? '#0f172a' ?>;
        --radius: <?= $GLOBALS['settings']['theme_radius'] ?? '16' ?>px;
        --shadow-opacity: <?= $GLOBALS['settings']['theme_shadow'] ?? '0.05' ?>;
    }
    
    body { background-color: var(--bg-body) !important; color: var(--text-main) !important; }
    
    /* Apply Glassmorphism if enabled */
    <?php if(($GLOBALS['settings']['enable_glass'] ?? '1') == '1'): ?>
    .card, .modern-card, .tool-card {
        background: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.6);
    }
    <?php endif; ?>

    /* Apply Radius & Shadow */
    .card, .btn, .form-control, .modern-card {
        border-radius: var(--radius) !important;
        box-shadow: 0 10px 30px -5px rgba(0,0,0, var(--shadow-opacity)) !important;
    }

    /* Custom CSS from Admin */
    <?= $GLOBALS['settings']['custom_css'] ?? '' ?>
</style>
</head>
<body>

<?php include '_nav.php'; ?>

<div class="main-content-wrapper">