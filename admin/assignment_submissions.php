<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($assignment_id <= 0) {
    header('Location: courses.php');
    exit;
}

$assignment = getAssignment($assignment_id);
if (!$assignment) {
    header('Location: courses.php');
    exit;
}

$message = '';
$error = '';

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'grade_submission') {
        $submission_id = (int)($_POST['submission_id'] ?? 0);
        $points_earned = (int)($_POST['points_earned'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        
        $result = gradeSubmission($submission_id, $_SESSION['user_id'], $points_earned, $assignment['points_possible'], $feedback);
        
        if ($result['success']) {
            $message = 'Submission graded and student notified!';
            logAuditEvent('grade_submission', $_SESSION['user_id'], ['submission_id' => $submission_id, 'points' => $points_earned]);
        } else {
            $error = 'Failed to grade submission';
        }
    }
}

$lesson = getLesson($assignment['lesson_id']);
$course = getCourse($assignment['course_id']);
$submissions = getAssignmentSubmissions($assignment_id);

$title = 'Submissions - ' . htmlspecialchars($assignment['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="assignment_edit.php?assignment_id=' . $assignment_id . '" class="btn btn-secondary btn-sm">Back to Assignment</a></p>';

if ($message) {
    $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($error) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$submitted_count = count(array_filter($submissions, function($s) { return $s['is_submitted']; }));
$graded_count = count(array_filter($submissions, function($s) { return $s['is_graded']; }));

$content .= '<div class="row mb-4">';
$content .= '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Total Submissions</h5><p class="fs-4">' . $submitted_count . '</p></div></div></div>';
$content .= '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Graded</h5><p class="fs-4">' . $graded_count . '</p></div></div></div>';
$content .= '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Pending</h5><p class="fs-4">' . ($submitted_count - $graded_count) . '</p></div></div></div>';
$content .= '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Points</h5><p class="fs-4">' . $assignment['points_possible'] . '</p></div></div></div>';
$content .= '</div>';

if ($submissions) {
    $type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
    $icon = $type_icons[$assignment['assignment_type']] ?? '📝';
    
    $content .= '<div class="card"><div class="card-header"><h5 class="mb-0">' . $icon . ' ' . htmlspecialchars($assignment['title']) . '</h5></div>';
    $content .= '<div class="table-responsive"><table class="table table-hover mb-0">';
    $content .= '<thead><tr><th>Student</th><th>Status</th><th>Submitted</th><th>Grade</th><th>Feedback</th><th>Actions</th></tr></thead><tbody>';
    
    foreach ($submissions as $sub) {
        $status = $sub['is_submitted'] ? '<span class="badge bg-success">Submitted</span>' : '<span class="badge bg-warning">Not Submitted</span>';
        $submitted_at = $sub['submitted_at'] ? date('M j, Y g:i A', strtotime($sub['submitted_at'])) : '-';
        $grade_display = $sub['grade'] !== null ? $sub['grade'] . ' / ' . $assignment['points_possible'] : '<em>Not Graded</em>';
        $feedback_preview = $sub['feedback_text'] ? substr($sub['feedback_text'], 0, 30) . '...' : '-';
        
        $content .= '<tr>';
        $content .= '<td><strong>' . htmlspecialchars($sub['username']) . '</strong></td>';
        $content .= '<td>' . $status . '</td>';
        $content .= '<td><small>' . $submitted_at . '</small></td>';
        $content .= '<td>' . $grade_display . '</td>';
        $content .= '<td><small>' . htmlspecialchars($feedback_preview) . '</small></td>';
        $content .= '<td><a href="#" onclick="showGradeModal(' . $sub['id'] . ', ' . htmlspecialchars(json_encode($sub)) . ')" class="btn btn-sm btn-primary">Grade</a></td>';
        $content .= '</tr>';
    }
    
    $content .= '</tbody></table></div></div>';
} else {
    $content .= '<p class="text-muted">No submissions yet.</p>';
}

// Modal for grading
$content .= '
<div class="modal fade" id="gradeModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Grade Submission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="grade_submission">
        <input type="hidden" name="submission_id" id="submission_id">
        <div class="modal-body">
          <div id="submission_info"></div>
          <div class="mb-3">
            <label for="points_earned" class="form-label">Points Earned</label>
            <input type="number" class="form-control" name="points_earned" id="points_earned" min="0" max="' . $assignment['points_possible'] . '" required>
          </div>
          <div class="mb-3">
            <label for="feedback" class="form-label">Feedback</label>
            <textarea class="form-control" name="feedback" id="feedback" rows="5" placeholder="Provide feedback to the student..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Grade</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showGradeModal(submissionId, submission) {
    document.getElementById("submission_id").value = submissionId;
    document.getElementById("feedback").value = submission.feedback_text || "";
    document.getElementById("points_earned").value = submission.grade || "";
    
    const infoHtml = `
        <div class="alert alert-info mb-3">
            <strong>Student:</strong> ${submission.username} (${submission.email})<br>
            <strong>Submitted:</strong> ${submission.submitted_at ? new Date(submission.submitted_at).toLocaleString() : "Not submitted"}<br>
            <strong>Assignment:</strong> ' . htmlspecialchars($assignment['title']) . ' (' . $assignment['points_possible'] . ' points)
        </div>
    `;
    document.getElementById("submission_info").innerHTML = infoHtml;
    new bootstrap.Modal(document.getElementById("gradeModal")).show();
}
</script>
';

include '../includes/header.php';
?>
