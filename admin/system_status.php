<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$title = 'System Status';
$content = '<h2>System Status</h2>';
$content .= '<p><a href="system_status.php" class="btn btn-sm btn-secondary">Refresh</a> <small class="text-muted">Auto-refresh every 30 seconds</small></p>';

$checks = [];

// PHP info check
$checks[] = [
    'name' => 'PHP Version',
    'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'OK' : 'Warning',
    'details' => 'Current PHP version: ' . PHP_VERSION
];

// Database health check
global $pdo;
try {
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        $checks[] = ['name' => 'Database Connection', 'status' => 'OK', 'details' => 'Connected to DB: ' . getenv('DB_NAME')];
    } else {
        $checks[] = ['name' => 'Database Connection', 'status' => 'Fail', 'details' => 'Query failed'];
    }
} catch (Exception $e) {
    $checks[] = ['name' => 'Database Connection', 'status' => 'Fail', 'details' => $e->getMessage()];
}

// Disk usage check
$disk_total = disk_total_space('/') ?: 0;
$disk_free = disk_free_space('/') ?: 0;
$disk_used = $disk_total - $disk_free;
$disk_usage_rate = $disk_total > 0 ? round(($disk_used / $disk_total) * 100, 2) : 0;
$checks[] = [
    'name' => 'Disk Usage',
    'status' => $disk_usage_rate < 90 ? 'OK' : 'Warning',
    'details' => "{$disk_usage_rate}% used (free: " . round($disk_free / 1024 / 1024, 2) . " MB)"
];

// Session status check
$session_status = session_status();
$session_status_text = ($session_status === PHP_SESSION_ACTIVE) ? 'Active' : 'Inactive';
$checks[] = ['name' => 'Session Status', 'status' => $session_status_text === 'Active' ? 'OK' : 'Fail', 'details' => $session_status_text];

// Add auth checking summary
$checks[] = ['name' => 'User Authenticated', 'status' => isLoggedIn() ? 'OK' : 'Warning', 'details' => isLoggedIn() ? 'Admin session active' : 'No user session'];

$content .= '<div class="table-responsive">';
$content .= '<table class="table table-bordered table-hover">';
$content .= '<thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>';
$content .= '<tbody>';
foreach ($checks as $check) {
    $class = $check['status'] === 'OK' ? 'table-success' : ($check['status'] === 'Warning' ? 'table-warning' : 'table-danger');
    $content .= '<tr class="' . $class . '">';
    $content .= '<td>' . htmlspecialchars($check['name']) . '</td>';
    $content .= '<td>' . htmlspecialchars($check['status']) . '</td>';
    $content .= '<td>' . htmlspecialchars($check['details']) . '</td>';
    $content .= '</tr>';
}
$content .= '</tbody></table>';
$content .= '</div>';

$content .= '<script>setTimeout(function() { window.location.reload(); }, 30000);</script>';

include '../includes/header.php';
?>