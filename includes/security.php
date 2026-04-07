<?php
require_once __DIR__ . '/env.php';

// Security: Error display based on environment
$env = getenv('APP_ENV') ?: 'production';
$showErrors = ($env === 'development') ? 1 : 0;
ini_set('display_errors', $showErrors);
error_reporting(E_ALL);

// Detect whether the current request is HTTPS.
$https_request = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

$https_enabled = defined('HTTPS_ENABLED') && HTTPS_ENABLED;
$secure_cookies = $https_enabled || $https_request;

if (defined('HTTPS_REDIRECT') && HTTPS_REDIRECT && !$https_request) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host !== '') {
        header('Location: https://' . $host . $uri);
        exit;
    }
}

if (defined('HTTPS_HSTS') && HTTPS_HSTS && $https_request) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Start session with secure settings
session_start([
    'cookie_secure' => $secure_cookies, // Only send over HTTPS or when explicitly enabled
    'cookie_httponly' => true, // Prevent JavaScript access
    'cookie_samesite' => 'Strict', // CSRF protection
    'cookie_lifetime' => 3600 * 24, // 24 hours
    'cookie_path' => '/', // Available site-wide
    'gc_maxlifetime' => 3600 * 24 // Garbage collection
]);

// Include database connection
require_once 'db.php';
?>
