<?php
include '_header.php';

$error = '';
$success = '';

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    // Get current user
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($new_pass !== $confirm_pass) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (password_verify($current_pass, $user['password_hash'])) {
        // Update password
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $_SESSION['user_id']]);
        $success = 'Password changed successfully!';
    } else {
        $error = 'Incorrect current password.';
    }
}
?>

<h1 class="section-title">My Profile</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="checkout-box" style="max-width: 600px;">
    <h2>Change Password</h2>
    <form action="profile.php" method="POST" class="checkout-form">
        <input type="hidden" name="change_password" value="1">
        
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php include '_footer.php'; ?>