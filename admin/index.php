<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$title = 'Admin Dashboard';
$content = '<h2>Admin Dashboard</h2>';
$content .= '<div class="row">';
$content .= '<div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">Courses</h5><p>Manage courses and lessons</p><a href="courses.php" class="btn btn-primary">Manage Courses</a></div></div></div>';
$content .= '<div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">Users</h5><p>View user accounts</p><a href="users.php" class="btn btn-primary">Manage Users</a></div></div></div>';
$content .= '<div class="col-md-4"><div class="card"><div class="card-body"><h5 class="card-title">Statistics</h5><p>View platform stats</p><a href="stats.php" class="btn btn-primary">View Stats</a></div></div></div>';
$content .= '</div>';

$content .= '<div class="row mt-3">';
$content .= '<div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">Security</h5><p>View audit logs and security events</p><a href="audit_log.php" class="btn btn-warning">View Audit Log</a></div></div></div>';
$content .= '<div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">System Health</h5><p>Monitor system status</p><a href="#" class="btn btn-info">System Status</a></div></div></div>';
$content .= '</div>';

include '../includes/header.php';
?>