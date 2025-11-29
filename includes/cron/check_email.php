<?php
// --- CRON JOB: EMAIL PAYMENT CHECKER (SECURE & DEBUG MODE) ---

// 1. Session Start (Admin Check ke liye)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SECURITY CHECK (End Level)
// Allow only if running from CLI (Server) OR Logged in Admin (Ghost Mode)
$is_cli = (php_sapi_name() === 'cli' || !isset($_SERVER['REMOTE_ADDR']));
$is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1 && isset($_SESSION['ghost_access']) && $_SESSION['ghost_access'] === true);

if (!$is_cli && !$is_admin) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: You are not authorized to run this cron manually.");
}

// 3. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../assets/logs/php_error.log');

// 4. Time & Memory
set_time_limit(300); // 5 Minutes max (IMAP slow ho sakta hai)
ini_set('memory_limit', '512M');

// 5. Absolute Paths
$base_path = dirname(dirname(__DIR__));
$log_file = $base_path . '/assets/logs/email_payments.log';

function writeLog($msg) {
    global $log_file;
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    if (!@file_put_contents($log_file, $entry, FILE_APPEND)) {
        echo $msg . "<br>";
    }
}

writeLog("--- EMAIL CHECK STARTED ---");

try {
    if (!file_exists($base_path . '/includes/config.php')) {
        throw new Exception("Config file not found.");
    }
    
    // Directory Change
    chdir($base_path . '/includes');
    
    require_once 'config.php';
    require_once 'db.php';

    if (!$db) throw new Exception("Database connection failed.");

    // IMAP Check
    if (!function_exists('imap_open')) {
        throw new Exception("IMAP extension is not installed on this server.");
    }

    // 1. Fetch Auto Methods
    $stmt = $db->query("SELECT * FROM payment_methods WHERE is_auto = 1 AND is_active = 1");
    $auto_methods = $stmt->fetchAll();
    
    if (empty($auto_methods)) {
        writeLog("No active auto-payment methods found.");
    } else {
        writeLog("Found " . count($auto_methods) . " auto-methods.");

        foreach ($auto_methods as $method) {
            writeLog("Checking Method: " . $method['name']);
            
            $mail_server = $method['auto_mail_server'];
            $email_user = $method['auto_email_user'];
            $email_pass = $method['auto_email_pass'];
            
            if (empty($mail_server) || empty($email_user) || empty($email_pass)) {
                writeLog("  SKIPPED: Settings incomplete for " . $method['name']);
                continue; 
            }

            // Connection String (SSL/No-Validate is safer for most hosts)
            $email_host = "{" . $mail_server . ":993/imap/ssl/novalidate-cert}INBOX";
            
            // Connect to Mailbox
            $mbox = @imap_open($email_host, $email_user, $email_pass);

            if (!$mbox) {
                writeLog("  FAILED to connect IMAP ($email_user): " . imap_last_error());
                continue; 
            }

            // Search Unread Emails
            $emails = imap_search($mbox, 'UNSEEN');

            if ($emails) {
                writeLog("  Found " . count($emails) . " unread emails.");
                
                foreach ($emails as $email_id) {
                    // Fetch Body
                    $body = imap_fetchbody($mbox, $email_id, 1);
                    if(empty($body)) $body = imap_fetchbody($mbox, $email_id, 2); // Fallback to HTML part
                    
                    // Decode & Clean
                    $body = quoted_printable_decode($body); 
                    $clean_body = strip_tags($body); 

                    $txn_id = null;
                    $amount = null;
                    
                    // --- PARSING PATTERNS (NayaPay & Others) ---

                    // 1. Transaction ID Search
                    if (preg_match('/Transaction ID\s*[:\-]?\s*([a-zA-Z0-9]+)/i', $clean_body, $matches)) {
                        $txn_id = trim($matches[1]);
                    } elseif (preg_match('/Txn ID\s*[:\-]?\s*([a-zA-Z0-9]+)/i', $clean_body, $matches)) {
                        $txn_id = trim($matches[1]); // Alternative Pattern
                    }
                    
                    // 2. Amount Search (Rs. 500 or PKR 500)
                    if (preg_match('/Amount Received\s*(Rs\.|PKR)\s*([\d,\.]+)/i', $clean_body, $matches)) {
                        $amount = str_replace(',', '', $matches[2]);
                        $amount = (float)$amount;
                    } elseif (preg_match('/You have received\s*(Rs\.|PKR)\s*([\d,\.]+)/i', $clean_body, $matches)) {
                        $amount = str_replace(',', '', $matches[2]);
                        $amount = (float)$amount; // Alternative Pattern
                    }

                    if ($txn_id && $amount > 0) {
                        try {
                            $stmt_insert = $db->prepare("INSERT INTO email_payments (txn_id, amount, status, raw_email_data, created_at) VALUES (?, ?, 'pending', ?, NOW())");
                            $stmt_insert->execute([$txn_id, $amount, $clean_body]);
                            
                            writeLog("    SUCCESS: Saved TXN: $txn_id | Amount: $amount");
                            
                            // Mark as Read
                            imap_setflag_full($mbox, $email_id, "\\Seen");
                            
                        } catch (PDOException $e) {
                            if ($e->getCode() == 23000) { 
                                writeLog("    SKIPPED: Duplicate TXN ID: $txn_id");
                            } else {
                                writeLog("    DB ERROR: " . $e->getMessage());
                            }
                            // Mark read even if duplicate to avoid re-scan
                            imap_setflag_full($mbox, $email_id, "\\Seen");
                        }
                    } else {
                        writeLog("    FAILED: Could not parse email ID $email_id. (Txn: " . ($txn_id ?? 'Missing') . ", Amt: " . ($amount ?? 'Missing') . ")");
                        // Optional: Mark read to skip loop, or leave unread to try again
                        // imap_setflag_full($mbox, $email_id, "\\Seen"); 
                    }
                }
            } else {
                writeLog("  No new unread emails.");
            }
            imap_close($mbox);
        }
    }

} catch (Exception $e) {
    writeLog("CRITICAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}

writeLog("--- EMAIL CHECK FINISHED ---");
?>