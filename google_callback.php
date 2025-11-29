<?php
// google_callback.php
require_once 'includes/helpers.php';
require_once 'includes/google_config.php';

if (isset($_GET['code'])) {
    // 1. Get Token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        // 2. Get User Profile
        $user_info_url = 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $token_data['access_token'];
        $user_info = json_decode(file_get_contents($user_info_url), true);

        if (isset($user_info['email'])) {
            $email = $user_info['email'];
            $name = $user_info['name'];
            $google_id = $user_info['id'];

            // 3. Check Database
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // USER EXISTS -> LOGIN
                // Update Google ID if missing
                if (empty($user['google_id'])) {
                    $db->prepare("UPDATE users SET google_id = ?, name = COALESCE(name, ?) WHERE id = ?")->execute([$google_id, $name, $user['id']]);
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                redirect($user['is_admin'] ? 'panel/index.php' : 'user/index.php');

            } else {
                // USER NEW -> REGISTER (Auto)
                $random_pass = bin2hex(random_bytes(8)); // Random password
                $hash = password_hash($random_pass, PASSWORD_DEFAULT);

                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, google_id, is_admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                $stmt->execute([$name, $email, $hash, $google_id]);
                
                $new_user_id = $db->lastInsertId();

                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['email'] = $email;
                $_SESSION['is_admin'] = 0;

                redirect('user/index.php?welcome=1');
            }
        }
    }
}
redirect('login.php?error=google_failed');
?>