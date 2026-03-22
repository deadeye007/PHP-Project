<?php
// Security: Error display based on environment
$env = getenv('APP_ENV') ?: 'production';
$showErrors = ($env === 'development') ? 1 : 0;
ini_set('display_errors', $showErrors);
error_reporting(E_ALL);

// Start session with secure settings
session_start([
    'cookie_secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS
    'cookie_httponly' => true, // Prevent JavaScript access
    'cookie_samesite' => 'Strict' // CSRF protection
]);

// Include database connection
require_once 'db.php';
?>