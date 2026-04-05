<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($assignment_id <= 0) {
    header('Location: courses.php');
    exit;
}

$assignment = getAssignment($assignment_id);
if (!$assignment || !$assignment['show_to_students']) {
    header('Location: courses.php');
    exit;
}

$message = '';
$error = '';

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $text_content = null;
    $file_path = null;
    $file_name = null;
    
    // Handle text submission
    if ($assignment['assignment_type'] === 'essay' || $assignment['assignment_type'] === 'discussion') {
        $text_content = trim($_POST['text_content'] ?? '');
        if (!$text_content) {
            $error = 'Please enter your submission content.';
        }
    }
    
    // Handle file upload
    if (!$error && $assignment['allow_file_upload'] && isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['submission_file'];
        $max_size = $assignment['max_file_size_mb'] * 1024 * 1024;
        
        if ($uploaded_file['size'] > $max_size) {
            $error = 'File is too large. Maximum size is ' . $assignment['max_file_size_mb'] . ' MB.';
        } else if ($assignment['allowed_file_types']) {
            $allowed = array_map('trim', explode(',', $assignment['allowed_file_types']));
            $ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = 'File type not allowed. Allowed types: ' . $assignment['allowed_file_types'];
            }
        }
        
        if (!$error) {
            $upload_dir = __DIR__ . '/submissions/' . $assignment_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = $uploaded_file['name'];
            $file_path = 'submissions/' . $assignment_id . '/' . $user_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
            
            if (!move_uploaded_file($uploaded_file['tmp_name'], __DIR__ . '/' . $file_path)) {
                $error = 'Failed to upload file.';
            }
        }
    }
    
    // Submit assignment if no errors
    if (!$error && ($text_content || $file_path)) {
        $result = submitAssignment($assignment_id, $user_id, $text_content, $file_path, $file_name);
        if ($result['success']) {
            $message = 'Assignment submitted successfully!';
            logAuditEvent('submit_assignment', $user_id, ['assignment_id' => $assignment_id]);
            // Redirect after delay
            header('refresh:2; url=assignments.php?id=' . $assignment['course_id']);
        } else {
            $error = 'Failed to submit assignment. Please try again.';
        }
    }
}

$lesson = getLesson($assignment['lesson_id']);
$course = getCourse($assignment['course_id']);
$existing_submission = getStudentSubmission($assignment_id, $user_id);

$title = 'Submit: ' . htmlspecialchars($assignment['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="assignments.php?id=' . $assignment['course_id'] . '" class="btn btn-secondary btn-sm">Back to Assignments</a></p>';

if ($message) {
    $content .= '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if ($error) {
    $content .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$content .= '<div class="row">';
$content .= '<div class="col-md-8">';

// Assignment details
$type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
$icon = $type_icons[$assignment['assignment_type']] ?? '📝';

$content .= '<div class="card mb-4"><div class="card-header"><h4 class="mb-0">' . $icon . ' ' . htmlspecialchars($assignment['title']) . '</h4></div><div class="card-body">';
if ($assignment['description']) {
    $content .= '<div class="mb-3">' . nl2br(htmlspecialchars($assignment['description'])) . '</div>';
}
$content .= '<p class="mb-2"><strong>Course:</strong> ' . htmlspecialchars($course['title']) . '</p>';
$content .= '<p class="mb-2"><strong>Lesson:</strong> ' . htmlspecialchars($lesson['title']) . '</p>';
$content .= '<p class="mb-2"><strong>Points Possible:</strong> ' . $assignment['points_possible'] . '</p>';
if ($assignment['submission_deadline']) {
    $deadline = date('M j, Y g:i A', strtotime($assignment['submission_deadline']));
    $is_overdue = time() > strtotime($assignment['submission_deadline']);
    $deadline_badge = $is_overdue ? '<span class="badge bg-danger">OVERDUE</span>' : '';
    $content .= '<p class="mb-0"><strong>Deadline:</strong> ' . $deadline . ' ' . $deadline_badge . '</p>';
}
$content .= '</div></div>';

// Submission form
$content .= '<div class="card"><div class="card-header"><h5 class="mb-0">📤 Submit Your Work</h5></div><div class="card-body">';
$content .= '<form method="POST" enctype="multipart/form-data">';

if (in_array($assignment['assignment_type'], ['essay', 'discussion'])) {
    $existing_text = $existing_submission['text_content'] ?? '';
    $content .= '<div class="mb-3"><label for="text_content" class="form-label">Your Answer</label>';
    $content .= '<textarea class="form-control" name="text_content" id="text_content" rows="10" required>' . htmlspecialchars($existing_text) . '</textarea>';
    $content .= '</div>';
}

if ($assignment['allow_file_upload']) {
    $content .= '<div class="mb-3"><label for="submission_file" class="form-label">Upload File</label>';
    $content .= '<input type="file" class="form-control" name="submission_file" id="submission_file"';
    if ($assignment['allowed_file_types']) {
        $accept = implode(',', array_map(function($ext) { return '.' . trim($ext); }, explode(',', $assignment['allowed_file_types'])));
        $content .= ' accept="' . htmlspecialchars($accept) . '"';
    }
    $content .= '>';
    $content .= '<small class="text-muted">Max size: ' . $assignment['max_file_size_mb'] . ' MB';
    if ($assignment['allowed_file_types']) {
        $content .= ' | Allowed types: ' . htmlspecialchars($assignment['allowed_file_types']);
    }
    $content .= '</small></div>';
}

$content .= '<button type="submit" name="submit_assignment" class="btn btn-primary">Submit Assignment</button>';
if ($existing_submission) {
    $content .= '<small class="text-muted ms-2">You have ' . $existing_submission['submission_number'] . ' submission(s). Resubmitting will create a new version.</small>';
}
$content .= '</form></div></div>';

$content .= '</div>';

// Sidebar
$content .= '<div class="col-md-4">';

if ($existing_submission) {
    $content .= '<div class="card mb-4"><div class="card-header"><h5 class="mb-0">✅ Previous Submission</h5></div><div class="card-body">';
    $submitted_at = $existing_submission['submitted_at'] ? date('M j, Y g:i A', strtotime($existing_submission['submitted_at'])) : 'Not submitted';
    $content .= '<p><strong>Submitted:</strong> ' . $submitted_at . '</p>';
    if ($existing_submission['is_graded']) {
        $content .= '<p class="mb-0"><strong>Grade:</strong> ' . htmlspecialchars($existing_submission['grade']) . ' / ' . $assignment['points_possible'] . '</p>';
        if ($existing_submission['feedback_text']) {
            $content .= '<p class="mt-2"><strong>Feedback:</strong></p><p>' . nl2br(htmlspecialchars($existing_submission['feedback_text'])) . '</p>';
        }
    } else {
        $content .= '<p class="text-muted">Awaiting grading...</p>';
    }
    $content .= '</div></div>';
}

$content .= '<div class="card"><div class="card-header"><h5 class="mb-0">ℹ️ Assignment Info</h5></div><div class="card-body">';
$content .= '<p><strong>Type:</strong> ' . ucfirst(str_replace('_', ' ', $assignment['assignment_type'])) . '</p>';
$content .= '<p><strong>Grading:</strong> ' . ucfirst($assignment['grading_type']) . '</p>';
if ($assignment['allow_resubmission']) {
    $max_resub = $assignment['max_resubmissions'] ? $assignment['max_resubmissions'] . ' times' : 'unlimited times';
    $content .= '<p class="mb-0 text-success"><small>✓ You can resubmit up to ' . $max_resub . '</small></p>';
} else {
    $content .= '<p class="mb-0 text-danger"><small>✗ No resubmissions allowed</small></p>';
}
$content .= '</div></div>';

$content .= '</div>';

$content .= '</div>';

include 'includes/header.php';
?>
