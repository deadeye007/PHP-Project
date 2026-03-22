<?php
require_once 'security.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getUser($_SESSION['user_id']);
    return $user && $user['role'] === 'admin';
}

// Regenerate session ID periodically to prevent session hijacking
function regenerateSession() {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function getCourses() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, title, description FROM courses ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourse($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, description FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getLessons($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, order_num FROM lessons WHERE course_id = ? ORDER BY order_num");
    $stmt->execute([$course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLesson($lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, content FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserProgress($user_id, $lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT completed, completed_at FROM user_progress WHERE user_id = ? AND lesson_id = ?");
    $stmt->execute([$user_id, $lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function markLessonComplete($user_id, $lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, TRUE, NOW()) ON DUPLICATE KEY UPDATE completed = TRUE, completed_at = NOW()");
    $stmt->execute([$user_id, $lesson_id]);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Rate limiting: Check if login attempt should be blocked
function isLoginBlocked($username, $ip) {
    global $pdo;
    
    // Clean old attempts (older than 1 hour)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    
    // Count recent attempts (last 15 minutes)
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE (username = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$username, $ip]);
    $result = $stmt->fetch();
    
    return $result['attempts'] >= 5; // Block after 5 attempts
}

// Rate limiting: Record login attempt
function recordLoginAttempt($username, $ip) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $stmt->execute([$ip, $username]);
}

// IP validation: Check if session IP matches current IP
function validateSessionIP() {
    if (!isset($_SESSION['ip_address'])) {
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        return true;
    }
    
    // Allow some tolerance for dynamic IPs (first 3 octets for IPv4)
    $session_ip = $_SESSION['ip_address'];
    $current_ip = $_SERVER['REMOTE_ADDR'];
    
    // For IPv4, compare first 3 octets
    if (filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $session_parts = explode('.', $session_ip);
        $current_parts = explode('.', $current_ip);
        return $session_parts[0] === $current_parts[0] && 
               $session_parts[1] === $current_parts[1] && 
               $session_parts[2] === $current_parts[2];
    }
    
    // For IPv6 or exact match
    return $session_ip === $current_ip;
}

// Audit logging: Log security events
function logAuditEvent($event_type, $user_id = null, $details = []) {
    global $pdo;
    
    $details['timestamp'] = date('Y-m-d H:i:s');
    $details['session_id'] = session_id();
    
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, event_type, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $event_type,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode($details)
    ]);
}

// Session management: Store session in database
function storeUserSession($user_id) {
    global $pdo;
    
    // Clean old sessions (older than 30 days)
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    // Store current session
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    $stmt->execute([
        $user_id,
        session_id(),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Session management: Validate and update session
function validateUserSession() {
    if (!isLoggedIn()) return false;
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
    
    if ($stmt->fetch()) {
        // Update last activity
        $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
        return true;
    }
    
    return false;
}

// Password change: Invalidate all other sessions
function invalidateOtherSessions($user_id, $current_session_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
    $stmt->execute([$user_id, $current_session_id]);
    
    logAuditEvent('password_change_sessions_invalidated', $user_id, ['action' => 'invalidated_other_sessions']);
}

// Base32 encode/decode helpers for TOTP
function base32Encode($data) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $output = '';

    foreach (str_split($data) as $char) {
        $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    while (strlen($binary) % 5 !== 0) {
        $binary .= '0';
    }

    foreach (str_split($binary, 5) as $chunk) {
        $output .= $alphabet[bindec($chunk)];
    }

    while (strlen($output) % 8 !== 0) {
        $output .= '=';
    }

    return $output;
}

function base32Decode($base32) {
    $base32 = strtoupper($base32);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = rtrim($base32, '=');
    $binary = '';

    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            return false;
        }
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $output = '';
    foreach (str_split($binary, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $output .= chr(bindec($byte));
    }

    return $output;
}

// Two-factor authentication: Generate TOTP secret (Base32 for authenticator apps)
function generateTOTPSecret() {
    $randomBytes = random_bytes(20); // 160-bit secret
    return rtrim(base32Encode($randomBytes), '=');
}

// Two-factor authentication: Verify TOTP code
function verifyTOTPCode($secret, $code) {
    $secretKey = base32Decode($secret);
    if ($secretKey === false) {
        // fallback for old hex secrets
        $secretKey = hex2bin($secret);
    }

    if ($secretKey === false) {
        return false;
    }

    $time = floor(time() / 30); // 30-second windows
    for ($i = -1; $i <= 1; $i++) {
        $time_window = $time + $i;
        $time_bytes = pack('N*', 0, $time_window);
        $hash = hash_hmac('sha1', $time_bytes, $secretKey, true);
        $offset = ord($hash[19]) & 0x0f;

        $code_generated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        if (str_pad($code_generated, 6, '0', STR_PAD_LEFT) === $code) {
            return true;
        }
    }

    return false;
}
?>