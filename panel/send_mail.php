<?php
// Nayi File: panel/send_mail.php
// User ko email bhejne ka form

include '_header.php'; 

$user_id = (int)($_GET['user_id'] ?? 0);
$error = '';
$success = '';

if ($user_id <= 0) {
    echo "<div class='message error'>Invalid User ID.</div>";
    include '_footer.php';
    exit;
}

// User ki details fetch karein
// Yeh query ab kaam karegi kyunki aapne column add kar diya hai
$stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='message error'>User not found.</div>";
    include '_footer.php';
    exit;
}

// Form submission handle karein
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $error = "CSRF token validation failed.";
    } else {
        $subject = sanitize($_POST['subject']);
        $message = nl2br(htmlspecialchars($_POST['message'])); // nl2br taake line breaks HTML <br> ban jaayein

        if (empty($subject) || empty($message)) {
            $error = "Subject and Message cannot be empty.";
        } else {
            // Naya function call karein
            $result = sendEmail($user['email'], $user['name'], $subject, $message);
            
            if ($result['success']) {
                $success = "Email sent successfully to " . sanitize($user['email']);
            } else {
                // --- BUG FIX: Yahaan se 'G' hata diya hai ---
                $error = "Failed to send email. Error: " . $result['message'];
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>

<h1>Send Email to User</h1>

<div style="margin-bottom: 20px;">
    <a href="users.php" class="btn btn-secondary">&laquo; Back to Users List</a>
</div>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<form action="" method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="form-section">
        <div class="form-group">
            <label for="to_email">To (User)</label>
            <input type="text" id="to_email" value="<?php echo sanitize($user['name']); ?> (<?php echo sanitize($user['email']); ?>)" class="form-control" disabled>
            <p style="font-size: 0.9em; color: var(--text-muted);">Email address is fetched from user's profile.</p>
        </div>
        
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="10" class="form-control" required></textarea>
            <p style="font-size: 0.9em; color: var(--text-muted);">Line breaks will be preserved.</p>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Send Email</button>
        </div>
    </div>
</form>

<?php include '_footer.php'; ?>