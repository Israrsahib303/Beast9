<?php
// includes/push_helper.php
// Engine to send Push Notifications via OneSignal (Fixed Authentication)

function sendPushNotification($user_id, $heading, $content, $url = null, $image = null, $buttons = []) {
    global $db;
    
    // 1. Fetch Settings
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('onesignal_app_id', 'onesignal_api_key')");
    while($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
    
    $app_id = trim($settings['onesignal_app_id'] ?? '');
    $api_key = trim($settings['onesignal_api_key'] ?? '');

    // Remove 'Basic ' prefix if user pasted it accidentally
    if (strpos($api_key, 'Basic ') === 0) {
        $api_key = substr($api_key, 6);
    }
    
    if(empty($app_id) || empty($api_key)) {
        return ['status' => false, 'msg' => 'API Keys Missing in Settings. Go to Settings > Notifications to add them.'];
    }

    // 2. Prepare Fields
    $fields = array(
        'app_id' => $app_id,
        'headings' => array("en" => $heading),
        'contents' => array("en" => $content),
        'url' => $url ?? SITE_URL // Default to home if no link
    );

    // Image
    if(!empty($image)) {
        $fields['big_picture'] = $image;
        $fields['chrome_web_image'] = $image;
    }

    // Buttons
    if(!empty($buttons)) {
        $fields['buttons'] = $buttons;
    }

    // 3. Targeting
    // Fix: Ensure user_id matches string 'all' perfectly
    if ($user_id == 'all' || $user_id == 'active' || $user_id == 'inactive') {
        $fields['included_segments'] = array('All'); 
    } else {
        // Fetch User's Device ID from Database
        $u = $db->prepare("SELECT one_signal_id FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $device_id = $u->fetchColumn();

        if (empty($device_id)) return ['status' => false, 'msg' => 'User not subscribed to notifications'];
        
        $fields['include_player_ids'] = array($device_id);
    }

    // 4. Send Request
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
    
    // Check for OneSignal Errors
    if(isset($res_data['errors'])) {
        $error_msg = is_array($res_data['errors']) ? json_encode($res_data['errors']) : $res_data['errors'];
        return ['status' => false, 'msg' => 'OneSignal Error: ' . $error_msg];
    }

    return ['status' => true, 'response' => $response];
}
?>