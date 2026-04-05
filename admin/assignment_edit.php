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

$lesson = getLesson($assignment['lesson_id']);
$course = getCourse($assignment['course_id']);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_assignment') {
        $updates = [
            'title' => trim($_POST['title'] ?? $assignment['title']),
            'description' => trim($_POST['description'] ?? $assignment['description']),
            'assignment_type' => $_POST['assignment_type'] ?? $assignment['assignment_type'],
            'points_possible' => (int)($_POST['points_possible'] ?? $assignment['points_possible']),
            'grading_type' => $_POST['grading_type'] ?? $assignment['grading_type'],
            'submission_deadline' => (isset($_POST['submission_deadline']) && !empty($_POST['submission_deadline'])) ? $_POST['submission_deadline'] : null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'show_to_students' => isset($_POST['show_to_students']) ? 1 : 0,
            'allow_file_upload' => isset($_POST['allow_file_upload']) ? 1 : 0,
            'allowed_file_types' => $_POST['allowed_file_types'] ?? null,
            'max_file_size_mb' => (int)($_POST['max_file_size_mb'] ?? 10)
        ];
        
        $result = updateAssignment($assignment_id, $updates);
        if ($result['success']) {
            $message = 'Assignment updated successfully!';
            $assignment = getAssignment($assignment_id); // Refresh
            logAuditEvent('update_assignment', $_SESSION['user_id'], ['assignment_id' => $assignment_id]);
        } else {
            $error = 'Failed to update assignment';
        }
    }
}

$title = 'Edit Assignment - ' . htmlspecialchars($assignment['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="assignments.php?lesson_id=' . $assignment['lesson_id'] . '" class="btn btn-secondary btn-sm">Back to Assignments</a></p>';

if ($message) {
    $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($error) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$content .= '<div class="row">';
$content .= '<div class="col-md-8">';

$content .= '<div class="card"><div class="card-header"><h4 class="mb-0">✏️ Edit Assignment Details</h4></div><div class="card-body">';
$content .= '<form method="POST"><input type="hidden" name="action" value="update_assignment">';

$content .= '<div class="mb-3"><label for="title" class="form-label">Assignment Title</label><input type="text" class="form-control" name="title" id="title" value="' . htmlspecialchars($assignment['title']) . '" required></div>';

$content .= '<div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" name="description" id="description" rows="5">' . htmlspecialchars($assignment['description'] ?? '') . '</textarea></div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="assignment_type" class="form-label">Assignment Type</label>';
$content .= '<select class="form-select" name="assignment_type" id="assignment_type">';
$types = ['essay' => '📄 Essay/Text', 'file_submission' => '📁 File Submission', 'project' => '🎯 Project', 'discussion' => '💬 Discussion', 'peer_review' => '👥 Peer Review', 'quiz' => '❓ Quiz'];
foreach ($types as $key => $label) {
    $selected = $assignment['assignment_type'] === $key ? 'selected' : '';
    $content .= '<option value="' . $key . '" ' . $selected . '>' . $label . '</option>';
}
$content .= '</select></div></div>';

$content .= '<div class="col-md-6"><div class="mb-3"><label for="grading_type" class="form-label">Grading Type</label>';
$content .= '<select class="form-select" name="grading_type" id="grading_type">';
$grading_types = ['points' => 'Points-Based', 'pass_fail' => 'Pass/Fail', 'rubric' => 'Rubric-Based'];
foreach ($grading_types as $key => $label) {
    $selected = $assignment['grading_type'] === $key ? 'selected' : '';
    $content .= '<option value="' . $key . '" ' . $selected . '>' . $label . '</option>';
}
$content .= '</select></div></div>';
$content .= '</div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="points_possible" class="form-label">Points Possible</label><input type="number" class="form-control" name="points_possible" id="points_possible" value="' . $assignment['points_possible'] . '" min="1"></div></div>';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="submission_deadline" class="form-label">Submission Deadline</label><input type="datetime-local" class="form-control" name="submission_deadline" id="submission_deadline" value="' . ($assignment['submission_deadline'] ? str_replace(' ', 'T', $assignment['submission_deadline']) : '') . '"></div></div>';
$content .= '</div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="allow_file_upload" class="form-label"><input type="checkbox" name="allow_file_upload" id="allow_file_upload" ' . ($assignment['allow_file_upload'] ? 'checked' : '') . '> Allow File Upload</label></div></div>';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="max_file_size_mb" class="form-label">Max File Size (MB)</label><input type="number" class="form-control" name="max_file_size_mb" id="max_file_size_mb" value="' . ($assignment['max_file_size_mb'] ?? 10) . '" min="1"></div></div>';
$content .= '</div>';

$content .= '<div class="mb-3"><label for="allowed_file_types" class="form-label">Allowed File Types (comma-separated, e.g., pdf,doc,docx,jpg,png)</label><input type="text" class="form-control" name="allowed_file_types" id="allowed_file_types" value="' . htmlspecialchars($assignment['allowed_file_types'] ?? '') . '" placeholder="Leave blank to allow all"></div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" name="show_to_students" id="show_to_students" ' . ($assignment['show_to_students'] ? 'checked' : '') . '><label class="form-check-label" for="show_to_students">Show to Students</label></div></div>';
$content .= '<div class="col-md-6"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_published" id="is_published" ' . ($assignment['is_published'] ? 'checked' : '') . '><label class="form-check-label" for="is_published">Published</label></div></div>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary mt-3">Save Changes</button>';
$content .= '</form></div></div>';

$content .= '<div class="card mt-4"><div class="card-header"><h5 class="mb-0">📊 Quick Actions</h5></div><div class="card-body">';
$submissions = getAssignmentSubmissions($assignment_id);
$submitted_count = count(array_filter($submissions, function($s) { return $s['is_submitted']; }));
$graded_count = count(array_filter($submissions, function($s) { return $s['is_graded']; }));

$content .= '<p><strong>Submissions:</strong> ' . $submitted_count . ' submitted, ' . $graded_count . ' graded</p>';
$content .= '<a href="assignment_submissions.php?assignment_id=' . $assignment_id . '" class="btn btn-info">View All Submissions & Grade</a>';
$content .= '</div></div>';

$content .= '</div>';

// Sidebar
$content .= '<div class="col-md-4">';
$content .= '<div class="card"><div class="card-header"><h5 class="mb-0">ℹ️ Assignment Info</h5></div><div class="card-body">';
$content .= '<p><strong>Course:</strong> ' . htmlspecialchars($course['title']) . '</p>';
$content .= '<p><strong>Lesson:</strong> ' . htmlspecialchars($lesson['title']) . '</p>';
$type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
$icon = $type_icons[$assignment['assignment_type']] ?? '📝';
$content .= '<p><strong>Type:</strong> ' . $icon . ' ' . ucfirst(str_replace('_', ' ', $assignment['assignment_type'])) . '</p>';
$content .= '<p><strong>Grading:</strong> ' . ucfirst($assignment['grading_type']) . '</p>';
$content .= '<p><strong>Points:</strong> ' . $assignment['points_possible'] . '</p>';
if ($assignment['submission_deadline']) {
    $content .= '<p><strong>Deadline:</strong> ' . date('M j, Y g:i A', strtotime($assignment['submission_deadline'])) . '</p>';
}
$content .= '<p><strong>Status:</strong> ' . ($assignment['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning">Draft</span>') . '</p>';
$content .= '</div></div>';
$content .= '</div>';

$content .= '</div>';

include '../includes/header.php';
?>
