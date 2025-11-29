<?php
header('Content-Type: application/manifest+json');
require_once 'includes/db.php'; // Database connect karein

// --- FETCH SETTINGS FROM DB ---
$settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) { }

// --- VARIABLES ---
$app_name = !empty($settings['site_name']) ? $settings['site_name'] : 'SubHub';
$short_name = substr($app_name, 0, 12); // Short name for homescreen (max 12 chars recommended)
$theme_color = !empty($settings['theme_primary']) ? $settings['theme_primary'] : '#4f46e5';
$bg_color = !empty($settings['bg_color']) ? $settings['bg_color'] : '#f8fafc';

// Logo Path Logic
$logo_file = !empty($settings['site_logo']) ? $settings['site_logo'] : 'logo.png';
$icon_path = 'assets/img/' . $logo_file;

// --- MANIFEST ARRAY ---
$manifest = [
    "name" => $app_name,
    "short_name" => $short_name,
    "start_url" => "user/index.php",
    "display" => "standalone",
    "background_color" => $bg_color,
    "theme_color" => $theme_color,
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => $icon_path,
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => $icon_path,
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ]
];

// Output JSON
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>