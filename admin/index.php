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
$content .= '<div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">System Health</h5><p>Monitor system status</p><a href="system_status.php" class="btn btn-info">System Status</a></div></div></div>';
$content .= '</div>';

$content .= '<div class="row mt-3">';
$content .= '<div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">Quizzes</h5><p>Quizzes are managed from each lesson. Open a course, then a lesson, then use the Quiz button to create settings and questions.</p><a href="courses.php" class="btn btn-outline-primary">Open Course Manager</a></div></div></div>';
$content .= '<div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">Gradebook</h5><p>Review course-level progress, quiz scores, and per-student outcomes.</p><a href="gradebook.php" class="btn btn-outline-success">Open Gradebook</a></div></div></div>';
$content .= '</div>';

$content .= '<div class="row mt-3">';
$content .= '<div class="col-md-6"><div class="card border-danger"><div class="card-body"><h5 class="card-title">Backup Database</h5><p>Export all tables as CSV files and download as an archive for safe backups.</p><a href="db_backup.php" class="btn btn-danger" id="backupCsvBtn" target="_blank" rel="noopener">Backup Database</a></div></div></div>';
$content .= '<div class="col-md-6"><div class="card border-success"><div class="card-body"><h5 class="card-title">Import Database</h5><p>Upload a backup archive (.zip, .tar, .tar.gz, .tgz) and import safely.</p><a href="db_import.php" class="btn btn-success">Import Database</a></div></div></div>';
$content .= '</div>';

$content .= '<div aria-live="polite" aria-atomic="true" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
  <div id="backupToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Backup</strong>
      <small>Now</small>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">Backup started, download should begin shortly. Keep this tab open until complete.</div>
  </div>
</div>';

$content .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var backupBtn = document.getElementById("backupCsvBtn");
    if (backupBtn) {
        backupBtn.addEventListener("click", function() {
            var toastEl = document.getElementById("backupToast");
            if (toastEl) {
                var toast = new bootstrap.Toast(toastEl, { delay: 7000 });
                toast.show();
            }
        });
    }
});
</script>';

include '../includes/header.php';
?>
