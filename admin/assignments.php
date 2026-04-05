<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

if ($lesson_id <= 0) {
    header('Location: courses.php');
    exit;
}

$lesson = getLesson($lesson_id);
if (!$lesson) {
    header('Location: courses.php');
    exit;
}

$course = getCourse($lesson['course_id']);

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_assignment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignment_type = $_POST['assignment_type'] ?? 'essay';
        $points_possible = (int)($_POST['points_possible'] ?? 100);
        $grading_type = $_POST['grading_type'] ?? 'points';
        $submission_deadline = isset($_POST['submission_deadline']) && !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : null;
        
        if (!$title) {
            $error = 'Assignment title is required';
        } else {
            $result = createAssignment($lesson_id, $lesson['course_id'], $title, $description, $assignment_type, $points_possible, $grading_type, $submission_deadline);
            if ($result['success']) {
                $message = 'Assignment created successfully!';
                logAuditEvent('create_assignment', $_SESSION['user_id'], ['lesson_id' => $lesson_id, 'title' => $title]);
            } else {
                $error = 'Failed to create assignment';
            }
        }
    }
    elseif ($action === 'update_assignment') {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $show_to_students = isset($_POST['show_to_students']) ? 1 : 0;
        
        $updates = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'assignment_type' => $_POST['assignment_type'] ?? 'essay',
            'points_possible' => (int)($_POST['points_possible'] ?? 100),
            'grading_type' => $_POST['grading_type'] ?? 'points',
            'submission_deadline' => (isset($_POST['submission_deadline']) && !empty($_POST['submission_deadline'])) ? $_POST['submission_deadline'] : null,
            'is_published' => $is_published,
            'show_to_students' => $show_to_students
        ];
        
        $result = updateAssignment($assignment_id, $updates);
        if ($result['success']) {
            $message = 'Assignment updated successfully!';
        } else {
            $error = 'Failed to update assignment';
        }
    }
}

// Get all assignments for this lesson
$assignments = getLessonAssignments($lesson_id);

$title = 'Lesson Assignments - ' . htmlspecialchars($lesson['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="lesson_edit.php?lesson_id=' . $lesson_id . '" class="btn btn-secondary btn-sm">Back to Lesson</a></p>';

if ($message) {
    $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($error) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$content .= '<div class="row">';
$content .= '<div class="col-md-8">';

// Create assignment form
$content .= '<div class="card mb-4"><div class="card-header"><h5 class="mb-0">📝 Create New Assignment</h5></div><div class="card-body">';
$content .= '<form method="POST"><input type="hidden" name="action" value="create_assignment">';
$content .= '<div class="mb-3"><label for="title" class="form-label">Assignment Title</label><input type="text" class="form-control" name="title" id="title" required></div>';
$content .= '<div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" name="description" id="description" rows="4" placeholder="Enter assignment instructions and details"></textarea></div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="assignment_type" class="form-label">Assignment Type</label>';
$content .= '<select class="form-select" name="assignment_type" id="assignment_type">';
$content .= '<option value="essay">📄 Essay/Text Submission</option>';
$content .= '<option value="file_submission">📁 File Submission</option>';
$content .= '<option value="project">🎯 Project</option>';
$content .= '<option value="discussion">💬 Discussion</option>';
$content .= '<option value="peer_review">👥 Peer Review</option>';
$content .= '<option value="quiz">❓ Quiz</option>';
$content .= '</select></div></div>';

$content .= '<div class="col-md-6"><div class="mb-3"><label for="grading_type" class="form-label">Grading Type</label>';
$content .= '<select class="form-select" name="grading_type" id="grading_type">';
$content .= '<option value="points">Points-Based</option>';
$content .= '<option value="pass_fail">Pass/Fail</option>';
$content .= '<option value="rubric">Rubric-Based</option>';
$content .= '</select></div></div>';
$content .= '</div>';

$content .= '<div class="row">';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="points_possible" class="form-label">Points Possible</label><input type="number" class="form-control" name="points_possible" id="points_possible" value="100" min="1"></div></div>';
$content .= '<div class="col-md-6"><div class="mb-3"><label for="submission_deadline" class="form-label">Submission Deadline (Optional)</label><input type="datetime-local" class="form-control" name="submission_deadline" id="submission_deadline"></div></div>';
$content .= '</div>';

$content .= '<button type="submit" class="btn btn-primary">Create Assignment</button>';
$content .= '</form></div></div>';

// List existing assignments
if ($assignments) {
    $content .= '<div class="card"><div class="card-header"><h5 class="mb-0">📋 Existing Assignments</h5></div><div class="table-responsive"><table class="table table-hover mb-0">';
    $content .= '<thead><tr><th>Title</th><th>Type</th><th>Points</th><th>Deadline</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    
    foreach ($assignments as $a) {
        $type_icons = [
            'essay' => '📄',
            'file_submission' => '📁',
            'project' => '🎯',
            'discussion' => '💬',
            'peer_review' => '👥',
            'quiz' => '❓'
        ];
        $icon = $type_icons[$a['assignment_type']] ?? '📝';
        $status = $a['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning">Draft</span>';
        $deadline = $a['submission_deadline'] ? date('M j, Y g:i A', strtotime($a['submission_deadline'])) : 'No deadline';
        
        $content .= '<tr>';
        $content .= '<td>' . htmlspecialchars($a['title']) . '</td>';
        $content .= '<td>' . $icon . ' ' . ucfirst(str_replace('_', ' ', $a['assignment_type'])) . '</td>';
        $content .= '<td>' . $a['points_possible'] . ' pts</td>';
        $content .= '<td><small class="text-muted">' . $deadline . '</small></td>';
        $content .= '<td>' . $status . '</td>';
        $content .= '<td><a href="assignment_edit.php?assignment_id=' . $a['id'] . '" class="btn btn-sm btn-primary">Edit</a> <a href="assignment_submissions.php?assignment_id=' . $a['id'] . '" class="btn btn-sm btn-info">Submissions</a></td>';
        $content .= '</tr>';
    }
    
    $content .= '</tbody></table></div></div>';
} else {
    $content .= '<p class="text-muted">No assignments yet for this lesson. Create one above to get started.</p>';
}

$content .= '</div>';

// Course info sidebar
$content .= '<div class="col-md-4"><div class="card"><div class="card-header"><h5 class="mb-0">ℹ️ Lesson Info</h5></div><div class="card-body">';
$content .= '<p><strong>Course:</strong> ' . htmlspecialchars($course['title']) . '</p>';
$content .= '<p><strong>Lesson:</strong> ' . htmlspecialchars($lesson['title']) . '</p>';
$content .= '<a href="lesson_edit.php?lesson_id=' . $lesson_id . '" class="btn btn-outline-secondary btn-sm">Edit Lesson</a>';
$content .= '</div></div>';
$content .= '</div>';

$content .= '</div>';

include '../includes/header.php';
?>
