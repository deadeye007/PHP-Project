<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$title = 'Security Audit Log';
$content = '<h2>Security Audit Log</h2>';

// Get recent audit events (last 100)
global $pdo;
$stmt = $pdo->query("SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content .= '<div class="table-responsive">';
$content .= '<table class="table table-striped table-sm" role="table" aria-label="Security Audit Log">';
$content .= '<thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Event</th><th scope="col">IP Address</th><th scope="col">Details</th></tr></thead>';
$content .= '<tbody>';

foreach ($events as $event) {
    $details = json_decode($event['details'], true);
    $detail_text = '';
    
    if ($details) {
        $detail_parts = [];
        foreach ($details as $key => $value) {
            if ($key !== 'timestamp' && $key !== 'session_id') {
                $detail_parts[] = "$key: $value";
            }
        }
        $detail_text = implode(', ', $detail_parts);
    }
    
    $event_class = 'table-light';
    if (strpos($event['event_type'], 'failed') !== false || strpos($event['event_type'], 'blocked') !== false) {
        $event_class = 'table-danger';
    } elseif (strpos($event['event_type'], '2fa_required') !== false) {
        $event_class = 'table-info';
    } elseif (strpos($event['event_type'], '2fa_verified') !== false || strpos($event['event_type'], 'success') !== false) {
        $event_class = 'table-success';
    } elseif (strpos($event['event_type'], 'login') !== false) {
        $event_class = 'table-warning';
    }
    
    $content .= '<tr class="' . $event_class . '">';
    $content .= '<td>' . htmlspecialchars($event['created_at']) . '</td>';
    $content .= '<td>' . htmlspecialchars($event['username'] ?: 'N/A') . '</td>';
    $content .= '<td>' . htmlspecialchars($event['event_type']) . '</td>';
    $content .= '<td>' . htmlspecialchars($event['ip_address'] ?: 'N/A') . '</td>';
    $content .= '<td>' . htmlspecialchars($detail_text) . '</td>';
    $content .= '</tr>';
}

$content .= '</tbody></table>';
$content .= '</div>';

include '../includes/header.php';
?>