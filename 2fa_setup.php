<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUser($_SESSION['user_id']);
$title = 'Two-Factor Authentication Setup';

// Handle 2FA setup
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_2fa'])) {
        // Generate new TOTP secret
        $secret = generateTOTPSecret();
        
        // Generate backup codes
        $backup_codes = [];
        for ($i = 0; $i < 10; $i++) {
            $backup_codes[] = bin2hex(random_bytes(4)); // 8-character codes
        }
        
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO two_factor_secrets (user_id, secret, backup_codes, enabled) VALUES (?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE secret = ?, backup_codes = ?, enabled = TRUE");
        $stmt->execute([
            $_SESSION['user_id'],
            $secret,
            json_encode($backup_codes),
            $secret,
            json_encode($backup_codes)
        ]);
        
        logAuditEvent('2fa_enabled', $_SESSION['user_id']);
        $message = '<div class="alert alert-success">Two-factor authentication has been enabled! Save your backup codes in a safe place.</div>';
        
        // Show QR code and backup codes
        $qr_data = 'otpauth://totp/LearningPlatform:' . $user['username'] . '?secret=' . $secret . '&issuer=LearningPlatform';
        
    } elseif (isset($_POST['disable_2fa'])) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM two_factor_secrets WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        logAuditEvent('2fa_disabled', $_SESSION['user_id']);
        $message = '<div class="alert alert-success">Two-factor authentication has been disabled.</div>';
    } elseif (isset($_POST['verify_code'])) {
        $code = $_POST['verification_code'];
        
        global $pdo;
        $stmt = $pdo->prepare("SELECT secret FROM two_factor_secrets WHERE user_id = ? AND enabled = TRUE");
        $stmt->execute([$_SESSION['user_id']]);
        $secret_data = $stmt->fetch();
        
        if ($secret_data && verifyTOTPCode($secret_data['secret'], $code)) {
            $message = '<div class="alert alert-success">Code verified successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Invalid verification code.</div>';
        }
    }
}

// Check current 2FA status
global $pdo;
$stmt = $pdo->prepare("SELECT secret, backup_codes, enabled FROM two_factor_secrets WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tfa_data = $stmt->fetch();

$content = '<h2>Two-Factor Authentication</h2>';
$content .= $message;

if ($tfa_data && $tfa_data['enabled']) {
    $content .= '<div class="alert alert-info">Two-factor authentication is currently <strong>enabled</strong>.</div>';
    
    $content .= '<h3>Test Authentication</h3>';
    $content .= '<form method="post" class="mb-3">';
    $content .= '<div class="mb-3">';
    $content .= '<label for="verification_code" class="form-label">Enter TOTP Code</label>';
    $content .= '<input type="text" class="form-control" id="verification_code" name="verification_code" required maxlength="6" pattern="[0-9]{6}">';
    $content .= '</div>';
    $content .= '<button type="submit" name="verify_code" class="btn btn-primary">Verify Code</button>';
    $content .= '</form>';
    
    $content .= '<form method="post">';
    $content .= '<button type="submit" name="disable_2fa" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to disable two-factor authentication?\')">Disable 2FA</button>';
    $content .= '</form>';
} else {
    $content .= '<div class="alert alert-warning">Two-factor authentication is currently <strong>disabled</strong>.</div>';
    
    $content .= '<p>Two-factor authentication adds an extra layer of security to your account by requiring a time-based code from an authenticator app in addition to your password.</p>';
    
    $content .= '<h3>Setup Instructions</h3>';
    $content .= '<ol>';
    $content .= '<li>Install an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator on your phone.</li>';
    $content .= '<li>Click "Enable 2FA" below to generate your secret key and QR code.</li>';
    $content .= '<li>Scan the QR code with your authenticator app or manually enter the secret key.</li>';
    $content .= '<li>Save your backup codes in a safe place - you\'ll need them if you lose access to your authenticator app.</li>';
    $content .= '</ol>';
    
    $content .= '<form method="post">';
    $content .= '<button type="submit" name="enable_2fa" class="btn btn-success">Enable 2FA</button>';
    $content .= '</form>';
}

include 'includes/header.php';
?>