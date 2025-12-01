<?php
// includes/push_helper.php
// FIXED: OneSignal "No Subscribed Users" Error Handling

function sendPushNotification($user_id, $heading, $content, $url = null, $image = null, $buttons = []) {
    global $db;
    
    // 1. Settings Fetch Karein
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('onesignal_app_id', 'onesignal_api_key')");
    while($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
    
    $app_id = trim($settings['onesignal_app_id'] ?? '');
    $api_key = trim($settings['onesignal_api_key'] ?? '');

    // Agar user ne ghalti se 'Basic ' prefix laga diya ho
    if (strpos($api_key, 'Basic ') === 0) {
        $api_key = substr($api_key, 6);
    }
    
    if(empty($app_id) || empty($api_key)) {
        return ['status' => false, 'msg' => 'API Keys Missing in Settings.'];
    }

    // 2. Data Prepare Karein
    $fields = array(
        'app_id' => $app_id,
        'headings' => array("en" => $heading),
        'contents' => array("en" => $content),
        'url' => $url ?? SITE_URL
    );

    if(!empty($image)) {
        $fields['big_picture'] = $image;
        $fields['chrome_web_image'] = $image;
    }

    if(!empty($buttons)) {
        $fields['buttons'] = $buttons;
    }

    // 3. Targeting Logic (FIXED)
    if ($user_id == 'all' || $user_id == 'active' || $user_id == 'inactive') {
        // Naye OneSignal accounts mein "Subscribed Users" segment hota hai
        $fields['included_segments'] = array('Subscribed Users'); 
    } else {
        // Specific User
        $u = $db->prepare("SELECT one_signal_id FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $device_id = $u->fetchColumn();

        if (empty($device_id)) {
            return ['status' => false, 'msg' => 'User is not subscribed (Player ID missing).'];
        }
        $fields['include_player_ids'] = array($device_id);
    }

    // 4. Request Send Karein
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    curl_close($ch);
    
    $res_data = json_decode($response, true);
    
    // 5. Error Handling
    if(isset($res_data['errors'])) {
        $error_msg = is_array($res_data['errors']) ? json_encode($res_data['errors']) : $res_data['errors'];
        
        // Friendly Error for Admin
        if (strpos($error_msg, 'All included players are not subscribed') !== false) {
            return ['status' => false, 'msg' => 'Abhi tak koi user subscribed nahi hai. Please website par ja kar pehle khud Allow karein.'];
        }
        
        return ['status' => false, 'msg' => 'OneSignal Error: ' . $error_msg];
    }

    return ['status' => true, 'response' => $response];
}
?>