<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. REQUIRED FILES ---
if (file_exists(__DIR__ . '/../includes/helpers.php')) { require_once __DIR__ . '/../includes/helpers.php'; }
if (file_exists(__DIR__ . '/../includes/db.php')) { require_once __DIR__ . '/../includes/db.php'; }

// --- FIX: FETCH SETTINGS MANUALLY IF NOT LOADED ---
if (empty($GLOBALS['settings'])) {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $GLOBALS['settings'][$row['setting_key']] = $row['setting_value'];
    }
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
$primary_color = $GLOBALS['settings']['theme_primary'] ?? '#2563eb'; // Default fallback

// --- 4. CURRENCY SETUP ---
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if (!empty($GLOBALS['settings']['onesignal_app_id'])): ?>
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
          appId: "<?php echo $GLOBALS['settings']['onesignal_app_id']; ?>",
          <?php if(!empty($GLOBALS['settings']['onesignal_safari_id'])): ?>
          safari_web_id: "<?php echo $GLOBALS['settings']['onesignal_safari_id']; ?>",
          <?php endif; ?>
          notifyButton: {
            enable: false, /* Disable default bell to use custom one */
          },
          allowLocalhostAsSecureOrigin: true,
        });

        // Auto Prompt
        OneSignal.Slidedown.promptPush();

        // Subscription Change Listener
        OneSignal.User.PushSubscription.addEventListener("change", async function(event) {
            if (event.current.optedIn) {
                const playerId = OneSignal.User.PushSubscription.id;
                if(playerId) {
                    // Send to PHP
                    fetch('save_device.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'player_id=' + playerId
                    });
                    document.getElementById('custom-bell-btn').style.display = 'none';
                }
            }
        });

        // Check on load
        setTimeout(function(){
            if(OneSignal.User.PushSubscription.optedIn){
                document.getElementById('custom-bell-btn').style.display = 'none';
            }
        }, 2000);
      });

      // Custom Bell Click Action
      function triggerSubscribe() {
          OneSignalDeferred.push(async function(OneSignal) {
              await OneSignal.User.PushSubscription.optIn();
              OneSignal.Slidedown.promptPush({ force: true });
          });
      }
    </script>
    <?php endif; ?>

    <style>
        :root {
            --primary: <?= $primary_color ?>;
            --bg-body: <?= $GLOBALS['settings']['theme_bg'] ?? '#f8fafc' ?>;
            --text-main: <?= $GLOBALS['settings']['theme_text'] ?? '#0f172a' ?>;
            --radius: <?= $GLOBALS['settings']['theme_radius'] ?? '16' ?>px;
            --shadow-opacity: <?= $GLOBALS['settings']['theme_shadow'] ?? '0.05' ?>;
            --nav-height: -10px;         
            --container-width: 700px;
        }

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

        a { text-decoration: none; color: inherit; transition: 0.2s ease-in-out; }
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
        
        /* Custom Floating Bell - Forced Visible */
        #custom-bell-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 55px;
            height: 55px;
            background: var(--primary) !important;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            z-index: 999999;
            cursor: pointer;
            border: 3px solid #fff;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        <?= $GLOBALS['settings']['custom_css'] ?? '' ?>
    </style>
</head>
<body>

<?php include '_nav.php'; ?>

<?php if (!empty($GLOBALS['settings']['onesignal_app_id'])): ?>
<div id="custom-bell-btn" onclick="triggerSubscribe()" title="Enable Notifications">
    <i class="fa-solid fa-bell"></i>
</div>
<?php endif; ?>

<div class="main-content-wrapper">