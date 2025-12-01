<?php
// includes/push_helper.php
// Engine to send Push Notifications via OneSignal

function sendPushNotification($user_id, $heading, $content, $url = null, $image = null) {
    global $db;
    
    // 1. Fetch Settings
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('onesignal_app_id', 'onesignal_api_key')");
    while($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
    
    $app_id = $settings['onesignal_app_id'] ?? '';
    $api_key = $settings['onesignal_api_key'] ?? '';
    
    if(empty($app_id) || empty($api_key)) return ['status' => false, 'msg' => 'API Keys Missing'];

    // 2. Prepare Fields
    $fields = array(
        'app_id' => $app_id,
        'headings' => array("en" => $heading),
        'contents' => array("en" => $content),
        'url' => $url ?? SITE_URL // Default to home if no link
    );

    // Image
    if($image) {
        $fields['big_picture'] = $image;
        $fields['chrome_web_image'] = $image;
    }

    // 3. Targeting (All vs Single)
    if ($user_id === 'all') {
        $fields['included_segments'] = array('All');
    } else {
        // Fetch User's Device ID
        $u = $db->prepare("SELECT one_signal_id FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $device_id = $u->fetchColumn();

        if (empty($device_id)) return ['status' => false, 'msg' => 'User not subscribed'];
        
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

    return ['status' => true, 'response' => $response];
}
?>