<?php
require_once 'includes/functions.php';

// Check if we have temp session data
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit;
}

$title = 'Two-Factor Authentication';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['totp_code'];
    $backup_code = $_POST['backup_code'] ?? '';
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT secret, backup_codes FROM two_factor_secrets WHERE user_id = ? AND enabled = TRUE");
    $stmt->execute([$_SESSION['temp_user_id']]);
    $tfa_data = $stmt->fetch();
    
    $verified = false;
    
    if ($tfa_data) {
        // Try TOTP code first
        if (!empty($code) && verifyTOTPCode($tfa_data['secret'], $code)) {
            $verified = true;
            logAuditEvent('2fa_verified_totp', $_SESSION['temp_user_id']);
        }
        // Try backup code
        elseif (!empty($backup_code)) {
            $backup_codes = json_decode($tfa_data['backup_codes'], true);
            if (in_array($backup_code, $backup_codes)) {
                // Remove used backup code
                $backup_codes = array_diff($backup_codes, [$backup_code]);
                $stmt = $pdo->prepare("UPDATE two_factor_secrets SET backup_codes = ? WHERE user_id = ?");
                $stmt->execute([json_encode(array_values($backup_codes)), $_SESSION['temp_user_id']]);
                
                $verified = true;
                logAuditEvent('2fa_verified_backup', $_SESSION['temp_user_id']);
            }
        }
    }
    
    if ($verified) {
        // Complete login
        session_regenerate_id(true);
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['ip_address'] = $_SESSION['temp_ip'];
        
        // Store session in database
        storeUserSession($_SESSION['user_id']);
        
        // Clean up temp data
        unset($_SESSION['temp_user_id'], $_SESSION['temp_username'], $_SESSION['temp_ip']);
        
        // Log successful login
        logAuditEvent('login_success_2fa', $_SESSION['user_id'], ['username' => $_SESSION['temp_username']]);
        
        header('Location: index.php');
        exit;
    } else {
        logAuditEvent('2fa_failed', $_SESSION['temp_user_id'], ['code_attempted' => !empty($code), 'backup_attempted' => !empty($backup_code)]);
        $message = '<div class="alert alert-danger">Invalid authentication code. Please try again.</div>';
    }
}

$content = '<h2>Two-Factor Authentication</h2>';
$content .= '<p>Please enter the 6-digit code from your authenticator app or use a backup code.</p>';
$content .= $message;

$content .= '<form method="post">';
$content .= '<div class="mb-3">';
$content .= '<label for="totp_code" class="form-label">Authenticator Code</label>';
$content .= '<input type="text" class="form-control" id="totp_code" name="totp_code" maxlength="6" pattern="[0-9]{6}" placeholder="000000">';
$content .= '<div class="form-text">Enter the 6-digit code from your authenticator app</div>';
$content .= '</div>';

$content .= '<div class="mb-3">';
$content .= '<label for="backup_code" class="form-label">Or Backup Code</label>';
$content .= '<input type="text" class="form-control" id="backup_code" name="backup_code" placeholder="Enter backup code">';
$content .= '<div class="form-text">Use a backup code if you don\'t have access to your authenticator app</div>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">Verify</button>';
$content .= ' <a href="login.php" class="btn btn-secondary">Back to Login</a>';
$content .= '</form>';

include 'includes/header.php';
?>