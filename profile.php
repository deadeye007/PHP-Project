<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUser($_SESSION['user_id']);
$title = 'Profile';

// Handle password change
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $message = renderStatusMessage('Current password is incorrect.', 'danger');
    } elseif (strlen($new_password) < 8) {
        $message = renderStatusMessage('New password must be at least 8 characters long.', 'danger');
    } elseif ($new_password !== $confirm_password) {
        $message = renderStatusMessage('New passwords do not match.', 'danger');
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        
        // Invalidate all other sessions
        invalidateOtherSessions($_SESSION['user_id'], session_id());
        
        // Log password change
        logAuditEvent('password_changed', $_SESSION['user_id']);
        
        $message = renderStatusMessage('Password changed successfully. All other sessions have been logged out.', 'success');
        
        // Refresh user data
        $user = getUser($_SESSION['user_id']);
    }
}

$content = '<h2>Your Profile</h2>';
$content .= $message;
$content .= '<div class="row">';
$content .= '<div class="col-md-6">';
$content .= '<h3>Account Information</h3>';
$content .= '<p><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</p>';
$content .= '<p><strong>Email:</strong> ' . htmlspecialchars($user['email']) . '</p>';
$content .= '<p><strong>Role:</strong> ' . htmlspecialchars($user['role']) . '</p>';
$content .= '<p><strong>Member since:</strong> ' . htmlspecialchars($user['created_at']) . '</p>';

// Add progress summary
global $pdo;
$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM user_progress WHERE user_id = ? AND completed = TRUE");
$stmt->execute([$_SESSION['user_id']]);
$completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
$content .= '<p><strong>Lessons Completed:</strong> ' . $completed . '</p>';
$content .= '</div>';

$content .= '<div class="col-md-6">';
$content .= '<h3>Change Password</h3>';
$content .= '<form method="post">';
$content .= '<div class="mb-3">';
$content .= '<label for="current_password" class="form-label">Current Password</label>';
$content .= '<input type="password" class="form-control" id="current_password" name="current_password" required>';
$content .= '</div>';
$content .= '<div class="mb-3">';
$content .= '<label for="new_password" class="form-label">New Password</label>';
$content .= '<input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">';
$content .= '</div>';
$content .= '<div class="mb-3">';
$content .= '<label for="confirm_password" class="form-label">Confirm New Password</label>';
$content .= '<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>';
$content .= '</div>';
$content .= '<button type="submit" name="change_password" class="btn btn-primary">Change Password</button>';
$content .= '</form>';

$content .= '<hr>';
$content .= '<h3>Security Settings</h3>';
$content .= '<p><a href="2fa_setup.php" class="btn btn-outline-primary">Setup Two-Factor Authentication</a></p>';
$content .= '</div>';
$content .= '</div>';

include 'includes/header.php';
?>
