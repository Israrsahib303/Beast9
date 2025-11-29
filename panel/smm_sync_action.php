<?php
// --- Sahi Files Include ki hain ---
include_once __DIR__ . '/../includes/config.php'; 
include_once __DIR__ . '/../includes/db.php'; 
include_once __DIR__ . '/../includes/helpers.php'; 
// --- NAYA: SMM API Class ko include kiya ---
include_once __DIR__ . '/../includes/smm_api.class.php'; 

// Check karein ke Admin logged in hai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$redirect_url = 'smm_services.php'; // Redirect URL


// --- 1. Delete/Deactivate Actions Handle Karein ---
if ($is_admin) {
    
    // A. Deactivate Single Service
    if (isset($_GET['deactivate_service'])) {
        $service_id = (int)$_GET['deactivate_service'];
        try {
            $stmt = $db->prepare("UPDATE smm_services SET is_active = 0 WHERE id = ?");
            $stmt->execute([$service_id]);
            header("Location: $redirect_url?show=active&success=" . urlencode("Service #$service_id deactivated successfully."));
            exit;
        } catch (PDOException $e) {
            header("Location: $redirect_url?show=active&error=" . urlencode("Failed to deactivate service: " . $e->getMessage()));
            exit;
        }
    }
    
    // B. Deactivate All Services in a Category
    if (isset($_GET['deactivate_category'])) {
        $category_name = sanitize($_GET['deactivate_category']);
        try {
            $stmt = $db->prepare("UPDATE smm_services SET is_active = 0 WHERE category = ?");
            $stmt->execute([$category_name]);
            header("Location: $redirect_url?show=active&success=" . urlencode("All services in category '{$category_name}' deactivated successfully."));
            exit;
        } catch (PDOException $e) {
            header("Location: $redirect_url?show=active&error=" . urlencode("Failed to deactivate category services: " . $e->getMessage()));
            exit;
        }
    }

    // C. PERMANENT Delete Single Service
    if (isset($_GET['delete_service'])) {
        $service_id = (int)$_GET['delete_service'];
        try {
            $stmt = $db->prepare("DELETE FROM smm_services WHERE id = ? AND is_active = 0");
            $stmt->execute([$service_id]);
            header("Location: $redirect_url?show=disabled&success=" . urlencode("Service #$service_id permanently deleted."));
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $error_msg = "Cannot delete service: It is linked to existing orders.";
            } else {
                $error_msg = "Failed to delete service: " . $e->getMessage();
            }
            header("Location: $redirect_url?show=disabled&error=" . urlencode($error_msg));
            exit;
        }
    }
}
// --- Delete Actions End ---



// --- Main Sync Function (REPLACED with GitHub version logic) ---
function syncServices($db) {
    $log = function($msg) {
        $timestamp = date('Y-m-d H:i:s');
        
        // --- BUG FIX: Path ../../ se ../ kar diya ---
        // Ghalat path: __DIR__ . '/../../assets/logs/smm_service_sync.log'
        // Sahi path:
        $log_file = __DIR__ . '/../assets/logs/smm_service_sync.log';
        // --- FIX END ---
        
        file_put_contents($log_file, "[$timestamp] " . $msg . "\n", FILE_APPEND);
        echo $msg . "<br>\n"; 
    };
    
    ob_start();
    $log("--- Service Sync Started ---");
    
    try {
        // 1. Settings se Currency Rate uthayein (From GitHub file)
        $usd_to_pkr_rate = (float)($GLOBALS['settings']['currency_conversion_rate'] ?? 280.00);
        $log("   - Using Currency Rate: 1 USD = " . $usd_to_pkr_rate . " PKR");

        // 2. Tamam Active Providers ko fetch karein
        $stmt_providers = $db->query("SELECT * FROM smm_providers WHERE is_active = 1");
        $providers = $stmt_providers->fetchAll(PDO::FETCH_ASSOC);

        if (empty($providers)) {
            throw new Exception('No active API providers found to sync.');
        }

        $total_new_services = 0;
        $total_updated_services = 0;
        $total_removed_services = 0;

        foreach ($providers as $provider) {
            $log("-> Syncing Provider: {$provider['name']} (ID: {$provider['id']})");
            
            $provider_id = $provider['id'];
            $profit_margin = (float)$provider['profit_margin']; // Sahi profit margin
            $log("   - Provider Profit Margin set to: {$profit_margin}%");

            // 3. API class ko call karein (From GitHub file)
            // (SSL Bypass SmmApi class ke andar hi hona chahiye, agar nahi hai toh wahan add karna hoga)
            $api = new SmmApi($provider['api_url'], $provider['api_key']);
            $result = $api->getServices(); // This uses the SmmApi class
            
            if (!$result['success'] || !is_array($result['services'])) {
                $log("   ERROR: Failed to fetch services from API. Provider said: " . ($result['error'] ?? 'Invalid response'));
                continue; // Agle provider ko check karein
            }
            
            $services_from_api = $result['services'];
            $provider_service_ids = []; // API se milne wali IDs

            $db->beginTransaction();

            // 4. Har service ko check/update/insert karein
            foreach ($services_from_api as $service) {
                if (empty($service['service']) || empty($service['name']) || !isset($service['rate'])) {
                    continue; // Ghalat data ko ignore karein
                }

                $service_id_from_api = (int)$service['service'];
                $provider_service_ids[] = $service_id_from_api; 
                
                $name = sanitize($service['name']);
                $category = sanitize($service['category']);
                
                // --- MUKAMMAL PRICE FIX ---
                $base_price_usd = (float)$service['rate'];
                $base_price_pkr = $base_price_usd * $usd_to_pkr_rate; // Convert to PKR
                $profit_amount = $base_price_pkr * ($profit_margin / 100); // Calculate profit
                $service_rate_pkr = $base_price_pkr + $profit_amount; // Final user price
                // --- FIX KHATAM ---
                
                $min = (int)$service['min'];
                $max = (int)$service['max'];
                $has_refill = (isset($service['refill']) && $service['refill'] == 1) ? 1 : 0;
                $has_cancel = (isset($service['cancel']) && $service['cancel'] == 1) ? 1 : 0;
                $avg_time = sanitize($service['average_time'] ?? null);
                $description = sanitize($service['description'] ?? null); 

                // 5. Check karein ke service pehle se hai ya nahi
                $stmt_check = $db->prepare("SELECT id FROM smm_services WHERE provider_id = ? AND service_id = ?");
                $stmt_check->execute([$provider_id, $service_id_from_api]);
                $existing_service_id = $stmt_check->fetchColumn();

                if ($existing_service_id) {
                    // 6. Agar hai to UPDATE karein
                    $stmt_update = $db->prepare("
                        UPDATE smm_services 
                        SET name = ?, category = ?, base_price = ?, service_rate = ?, min = ?, max = ?, 
                            avg_time = ?, has_refill = ?, has_cancel = ?, description = ?, is_active = 1
                        WHERE id = ?
                    ");
                    $stmt_update->execute([
                        $name, $category, $base_price_pkr, $service_rate_pkr, $min, $max,
                        $avg_time, $has_refill, $has_cancel, $description,
                        $existing_service_id
                    ]);
                    $total_updated_services++;
                } else {
                    // 7. Agar nahi hai to INSERT karein
                    $stmt_insert = $db->prepare("
                        INSERT INTO smm_services 
                        (provider_id, service_id, name, category, base_price, service_rate, min, max, avg_time, has_refill, has_cancel, description, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt_insert->execute([
                        $provider_id, $service_id_from_api, $name, $category, $base_price_pkr, $service_rate_pkr, $min, $max,
                        $avg_time, $has_refill, $has_cancel, $description
                    ]);
                    $total_new_services++;
                }
            }
            
            // 8. Purani (Remove shuda) services ko 'disable' karein
            if (!empty($provider_service_ids)) {
                $placeholders = implode(',', array_fill(0, count($provider_service_ids), '?'));
                $params = array_merge([$provider_id], $provider_service_ids);
                
                $stmt_disable = $db->prepare("
                    UPDATE smm_services 
                    SET is_active = 0 
                    WHERE provider_id = ? AND service_id NOT IN ($placeholders)
                ");
                $stmt_disable->execute($params);
                $total_removed_services += $stmt_disable->rowCount();
            }

            $db->commit();
            $log("   -> {$total_new_services} New, {$total_updated_services} Updated, {$total_removed_services} Deactivated.");
            
        } // End Providers loop

        $final_message = "--- Service Sync Complete (Summary: New: $total_new_services, Updated: $total_updated_services, Removed/Deactivated: $total_removed_services) ---";
        $log($final_message);
        $output = ob_get_clean(); 
        return $output; 

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $final_message = "FATAL DB ERROR: " . $e->getMessage();
        $log($final_message); $output = ob_get_clean(); return $output; 
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $final_message = "FATAL ERROR: " . $e->getMessage();
        $log($final_message); $output = ob_get_clean(); return $output; 
    }
}


// --- NAYA Sync Trigger ---
$is_ajax_request = ($is_admin && isset($_GET['job']) && $_GET['job'] == 'smm_service_sync');
$is_cron_request = (!$is_admin && php_sapi_name() === 'cli'); 

if ($is_ajax_request || $is_cron_request) {
    
    if($is_ajax_request) {
        requireAdmin(); // helpers.php se
    }

    @set_time_limit(0); 
    @ignore_user_abort(true);

    $sync_result_log = syncServices($db);
    
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $sync_result_log]);
        exit;
    } else {
        echo "SMM Service Sync Completed. \n" . str_replace('<br>', '', $sync_result_log);
        exit;
    }
}

// Agar koi action match nahi hua
if ($is_admin) {
    header("Location: $redirect_url?error=" . urlencode("No valid action specified."));
    exit;
}
?>