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
$show_qr = false;
$qr_url = '';
$backup_codes_output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;

    if (isset($_POST['enable_2fa'])) {
        // Generate new TOTP secret and backup codes, but don't enable yet
        $secret = generateTOTPSecret();
        $backup_codes = [];
        for ($i = 0; $i < 10; $i++) {
            $backup_codes[] = bin2hex(random_bytes(4));
        }

        // Save in DB as disabled until verified
        $stmt = $pdo->prepare("INSERT INTO two_factor_secrets (user_id, secret, backup_codes, enabled) VALUES (?, ?, ?, FALSE) ON DUPLICATE KEY UPDATE secret = ?, backup_codes = ?, enabled = FALSE");
        $stmt->execute([
            $_SESSION['user_id'],
            $secret,
            json_encode($backup_codes),
            $secret,
            json_encode($backup_codes)
        ]);

        // Generate QR data and show it for manual pairing
        $qr_data = 'otpauth://totp/LearningPlatform:' . $user['username'] . '?secret=' . $secret . '&issuer=LearningPlatform';

        // Add both primary and fallback QR providers (some environments block google)
        $qr_url = 'https://chart.googleapis.com/chart?cht=qr&chs=250x250&chl=' . urlencode($qr_data) . '&choe=UTF-8';
        $qr_fallback_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qr_data);

        $show_qr = true;

        $backup_codes_output = '<ul>';
        foreach ($backup_codes as $code) {
            $backup_codes_output .= '<li>' . htmlspecialchars($code) . '</li>';
        }
        $backup_codes_output .= '</ul>';

        $message = '<div class="alert alert-info">Scan the QR code or use the secret key below, then verify a code to complete setup.</div>';
    } elseif (isset($_POST['disable_2fa'])) {
        $stmt = $pdo->prepare("DELETE FROM two_factor_secrets WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        logAuditEvent('2fa_disabled', $_SESSION['user_id']);
        $message = '<div class="alert alert-success">Two-factor authentication has been disabled.</div>';
    } elseif (isset($_POST['verify_code'])) {
        $code = trim($_POST['verification_code']);
        $stmt = $pdo->prepare("SELECT secret, enabled FROM two_factor_secrets WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $secret_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($secret_data && verifyTOTPCode($secret_data['secret'], $code)) {
            // Mark as enabled if not already
            if (!$secret_data['enabled']) {
                $stmt = $pdo->prepare("UPDATE two_factor_secrets SET enabled = TRUE WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                logAuditEvent('2fa_enabled', $_SESSION['user_id']);
            }
            $message = '<div class="alert alert-success">Code verified successfully! Two-factor authentication is enabled.</div>';
        } else {
            $message = '<div class="alert alert-danger">Invalid verification code. Try again.</div>';
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

if ($show_qr) {
    $content .= '<div class="alert alert-info">Scan the QR code with your authenticator app and enter a verification code to finish setup.</div>';
    $content .= '<h3>QR Code</h3>';
    $content .= '<img src="' . htmlspecialchars($qr_url) . '" alt="2FA QR Code" class="img-thumbnail" style="max-width:250px;">';
    $content .= '<p class="mt-2">If the Google QR image does not load, use the fallback image:</p>';
    $content .= '<img src="' . htmlspecialchars($qr_fallback_url) . '" alt="2FA QR Code Fallback" class="img-thumbnail" style="max-width:250px;">';
    $content .= '<p><strong>Manual secret:</strong> ' . htmlspecialchars($secret) . '</p>';
    $content .= '<h4>Backup Codes</h4>' . $backup_codes_output;

    $content .= '<h3>Verify a code</h3>';
    $content .= '<form method="post" class="mb-3">';
    $content .= '<div class="mb-3">';
    $content .= '<label for="verification_code" class="form-label">Enter TOTP Code</label>';
    $content .= '<input type="text" class="form-control" id="verification_code" name="verification_code" required maxlength="6" pattern="[0-9]{6}">';
    $content .= '</div>';
    $content .= '<button type="submit" name="verify_code" class="btn btn-primary">Verify Code</button>';
    $content .= '</form>';
    $content .= '<p><a href="2fa_setup.php" class="btn btn-outline-secondary">Cancel</a></p>';
}
elseif ($tfa_data && $tfa_data['enabled']) {
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