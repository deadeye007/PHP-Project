<?php
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // Don't sanitize password
        $ip = $_SERVER['REMOTE_ADDR'];

        // Check rate limiting
    if (isLoginBlocked($username, $ip)) {
        logAuditEvent('login_blocked_rate_limit', null, ['username' => $username, 'ip' => $ip]);
        $error = 'Too many login attempts. Please try again later.';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if 2FA is enabled
            global $pdo;
            $stmt = $pdo->prepare("SELECT enabled FROM two_factor_secrets WHERE user_id = ? AND enabled = TRUE");
            $stmt->execute([$user['id']]);
            $tfa_enabled = $stmt->fetch();
            
            if ($tfa_enabled) {
                // Store temp session data for 2FA verification
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_username'] = $username;
                $_SESSION['temp_ip'] = $ip;
                logAuditEvent('login_2fa_required', $user['id'], ['username' => $username]);
                header('Location: 2fa_verify.php');
                exit;
            }
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['ip_address'] = $ip;
            
            // Store session in database
            storeUserSession($user['id']);
            
            // Log successful login
            logAuditEvent('login_success', $user['id'], ['username' => $username]);
            
            header('Location: index.php');
            exit;
        } else {
            // Record failed attempt
            recordLoginAttempt($username, $ip);
            logAuditEvent('login_failed', null, ['username' => $username, 'ip' => $ip]);
            $error = 'Invalid username or password.';
        }
    }
}
}

$title = 'Login';
$content = '<h2>Login</h2>';
if (isset($error)) {
    $content .= renderStatusMessage(htmlspecialchars($error), 'danger');
}
$content .= '
    <form method="post">
        ' . csrfInputField() . '
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required maxlength="50">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p class="mt-3">Don\'t have an account? <a href="register.php">Register here</a>.</p>
';

include 'includes/header.php';
?>
