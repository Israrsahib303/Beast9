<?php
// includes/ai_tool_handler.php - Fixed & Optimized
require_once 'db.php';
header('Content-Type: application/json');

// Error Reporting Off (To prevent JSON breakage)
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['reply' => 'Error: Please login first.']); exit; }

// 1. FETCH SETTINGS
$stmt = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

// API Key Check (Groq)
$api_key = isset($settings['gemini_api_key']) ? trim($settings['gemini_api_key']) : '';
if (empty($api_key)) { echo json_encode(['reply' => 'Error: Admin has not set API Key.']); exit; }

$currency = $settings['currency_symbol'] ?? 'PKR';
$site_name = $settings['site_name'] ?? 'IsrarLiaqat.com';

$tool_id = $_POST['tool_id'] ?? '';
$user_input = trim($_POST['user_input'] ?? '');

if (empty($user_input)) { echo json_encode(['reply' => 'Please enter some details.']); exit; }

// --- HELPER: SEARCH DATABASE ---
function searchServices($db, $query, $currency) {
    // Keywords logic
    $terms = explode(' ', $query);
    $sql_parts = [];
    $params = [];
    
    foreach($terms as $term) {
        if(strlen($term) > 2) { // Ignore small words
            $sql_parts[] = "name LIKE ?";
            $params[] = "%$term%";
        }
    }
    
    if(empty($sql_parts)) return "No specific services found.";

    // SMM Services Search
    $sql = "SELECT id, name, service_rate, min, max FROM smm_services 
            WHERE is_active=1 AND (" . implode(' AND ', $sql_parts) . ") 
            ORDER BY service_rate ASC LIMIT 6";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result_text = "";
    if($services) {
        $result_text .= "FOUND SMM SERVICES:\n";
        foreach($services as $s) {
            $result_text .= "- ID: {$s['id']} | Name: {$s['name']} | Price: {$s['service_rate']} $currency | Min/Max: {$s['min']}/{$s['max']}\n";
        }
    }

    // Products Search (Subscriptions)
    $sql_p = "SELECT name FROM products WHERE is_active=1 AND (" . implode(' AND ', $sql_parts) . ") LIMIT 3";
    $stmt_p = $db->prepare($sql_p);
    $stmt_p->execute($params);
    $products = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    if($products) {
        $result_text .= "\nFOUND DIGITAL PRODUCTS:\n";
        foreach($products as $p) {
            $result_text .= "- Product: {$p['name']}\n";
        }
    }

    return empty($result_text) ? "No matching services found in DB." : $result_text;
}

// 2. DEFINE BASE INSTRUCTION GLOBALLY (Fixes Undefined Variable Error)
$base_instruction = "
You are an AI Sales Expert for '$site_name'.
Goal: Help users and SELL them our services.
Tone: Professional, Friendly, Hinglish (Urdu/English mix).
Format: Use HTML tags (<b>, <br>, <ul>) for styling.
";

// 3. TOOL PROMPTS
switch ($tool_id) {
    case 'finder': 
        // Real DB Search
        $db_results = searchServices($db, $user_input, $currency);
        
        $prompt = "$base_instruction
        User Needs: '$user_input'.
        
        [DATABASE RESULTS FROM OUR PANEL]
        $db_results
        
        TASK:
        1. If services are found above, recommend the best one by ID and Name. Tell them the Price.
        2. If NO services found, apologize and suggest checking the Categories page.
        3. Convince them why this service is best (Cheap/Fast).
        ";
        break;

    case 'audit': 
        // Audit logic
        $db_results = searchServices($db, "Followers Likes", $currency);
        
        $prompt = "$base_instruction
        User Profile/Issue: '$user_input'.
        Task: Audit this. Point out 3 mistakes (Engagement, Consistency, etc).
        SOLUTION: Strongly recommend buying services to fix this.
        
        [RECOMMEND THESE SERVICES]
        $db_results
        
        Format: Use <b>Problem:</b> and <b>Solution:</b> style.
        ";
        break;

    case 'hook': 
        $prompt = "$base_instruction
        Topic: '$user_input'.
        Task: Write 5 Viral Hooks (First 3 seconds lines) for TikTok/Reels.
        Format: HTML List (<ul>).
        End with: 'Pro Tip: Video dalte hi $site_name se Views buy karein taaki viral ho jaye!'";
        break;

    case 'plan': 
        $prompt = "$base_instruction
        Goal: '$user_input'.
        Task: 7-Day Growth Plan.
        Format: HTML Table or List.
        Strategy: On Day 1 & 4, tell them to buy specific services from $site_name.";
        break;

    case 'caption': 
        $prompt = "$base_instruction
        Context: '$user_input'.
        Task: Write an engaging Caption + 15 Hashtags.
        End with: '🚀 Boosted by $site_name'";
        break;

    case 'reply': 
        $prompt = "$base_instruction
        Comment to reply: '$user_input'.
        Task: Give 3 replies: 1. Funny, 2. Savage, 3. Professional.
        Format: Separate with <br><br>.";
        break;

    case 'bio': 
        $prompt = "$base_instruction
        Details: '$user_input'.
        Task: Create 3 Bio styles (Professional, Cool, Aesthetic). Use Emojis.";
        break;

    case 'idea': 
        $prompt = "$base_instruction
        Niche: '$user_input'.
        Task: 5 Viral Video Ideas.
        Pitch: 'Traffic kam hai? $site_name se Views le lo!'";
        break;

    case 'email': 
        $prompt = "$base_instruction
        Topic: '$user_input'.
        Task: Write a professional Cold Email or Sales Pitch.";
        break;

    case 'hashtag': 
        $prompt = "$base_instruction
        Niche: '$user_input'.
        Task: 3 Sets of Hashtags (Low, Medium, High Volume).";
        break;

    default:
        $prompt = "$base_instruction Question: '$user_input'";
}

// 4. CALL GROQ API (Llama 3.1)
$url = "https://api.groq.com/openai/v1/chat/completions";

$data = [
    "model" => "llama-3.1-8b-instant",
    "messages" => [
        ["role" => "system", "content" => "You are a helpful assistant."],
        ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.7
];

// CURL Setup
if (!function_exists('curl_init')) { echo json_encode(['reply' => 'SERVER ERROR: cURL is disabled.']); exit; }

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// 5. HANDLE RESPONSE
if ($curl_error) { echo json_encode(['reply' => 'Connection Error: ' . $curl_error]); exit; }

$result = json_decode($response, true);

if (isset($result['error'])) {
    $err_msg = $result['error']['message'] ?? 'Unknown API Error';
    echo json_encode(['reply' => "Groq API Error: " . $err_msg]);
    exit;
}

$final_reply = $result['choices'][0]['message']['content'] ?? 'AI gave empty response.';
$final_reply = nl2br($final_reply); 

echo json_encode(['reply' => $final_reply]);
?>