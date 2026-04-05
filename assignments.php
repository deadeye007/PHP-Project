<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    header('Location: courses.php');
    exit;
}

$course = getCourse($course_id);
if (!$course) {
    header('Location: courses.php');
    exit;
}

$title = 'Assignments - ' . htmlspecialchars($course['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="course.php?id=' . $course_id . '" class="btn btn-secondary btn-sm">Back to Course</a></p>';

// Get all assignments for this course that are visible to students
$lessons = getLessons($course_id);
$assignments = [];
foreach ($lessons as $lesson) {
    $lesson_assignments = getLessonAssignments($lesson['id']);
    foreach ($lesson_assignments as $a) {
        if ($a['show_to_students']) {
            $a['lesson_title'] = $lesson['title'];
            $a['lesson_id'] = $lesson['id'];
            $assignments[] = $a;
        }
    }
}

if (empty($assignments)) {
    $content .= '<p class="text-muted">No assignments available for this course yet.</p>';
} else {
    $content .= '<div class="row">';
    
    // Group by status
    $due_soon = [];
    $submitted = [];
    $graded = [];
    $overdue = [];
    
    foreach ($assignments as $a) {
        $submission = getStudentSubmission($a['id'], $user_id);
        $a['submission'] = $submission;
        
        if ($submission && $submission['is_graded']) {
            $graded[] = $a;
        } elseif ($submission && $submission['is_submitted']) {
            $submitted[] = $a;
        } elseif ($a['submission_deadline']) {
            if (strtotime($a['submission_deadline']) < time()) {
                $overdue[] = $a;
            } else {
                $due_soon[] = $a;
            }
        } else {
            $due_soon[] = $a;
        }
    }
    
    // Display sections
    if (!empty($due_soon)) {
        $content .= '<div class="col-12"><h4 class="mt-4">📌 Due Soon</h4>';
        foreach ($due_soon as $a) {
            $type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
            $icon = $type_icons[$a['assignment_type']] ?? '📝';
            $deadline = $a['submission_deadline'] ? date('M j, Y g:i A', strtotime($a['submission_deadline'])) : 'No deadline';
            $days_until = $a['submission_deadline'] ? ceil((strtotime($a['submission_deadline']) - time()) / 86400) : null;
            $urgency_badge = ($days_until && $days_until <= 3) ? '<span class="badge bg-danger ms-2">Due soon!</span>' : '';
            
            $content .= '<div class="card mb-3"><div class="card-body">';
            $content .= '<div class="d-flex justify-content-between align-items-start">';
            $content .= '<div>';
            $content .= '<h5>' . $icon . ' ' . htmlspecialchars($a['title']) . ' ' . $urgency_badge . '</h5>';
            $content .= '<p class="text-muted"><small>Lesson: ' . htmlspecialchars($a['lesson_title']) . '</small></p>';
            if ($a['description']) {
                $content .= '<p>' . htmlspecialchars(substr($a['description'], 0, 200)) . '...</p>';
            }
            $content .= '<p><strong>Due:</strong> ' . $deadline . ' | <strong>Points:</strong> ' . $a['points_possible'] . '</p>';
            $content .= '</div>';
            $content .= '<a href="submit_assignment.php?assignment_id=' . $a['id'] . '" class="btn btn-primary">Submit</a>';
            $content .= '</div></div></div>';
        }
        $content .= '</div>';
    }
    
    if (!empty($overdue)) {
        $content .= '<div class="col-12"><h4 class="mt-4">⏰ Overdue</h4>';
        foreach ($overdue as $a) {
            $type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
            $icon = $type_icons[$a['assignment_type']] ?? '📝';
            $deadline = $a['submission_deadline'] ? date('M j, Y g:i A', strtotime($a['submission_deadline'])) : 'No deadline';
            
            $content .= '<div class="card mb-3 border-danger"><div class="card-body">';
            $content .= '<div class="d-flex justify-content-between align-items-start">';
            $content .= '<div>';
            $content .= '<h5>' . $icon . ' ' . htmlspecialchars($a['title']) . ' <span class="badge bg-danger">Overdue</span></h5>';
            $content .= '<p class="text-muted"><small>Lesson: ' . htmlspecialchars($a['lesson_title']) . '</small></p>';
            $content .= '<p><strong>Was due:</strong> ' . $deadline . '</p>';
            $content .= '</div>';
            if ($a['allow_late_submission_allowed']) {
                $content .= '<a href="submit_assignment.php?assignment_id=' . $a['id'] . '" class="btn btn-warning">Submit Late</a>';
            }
            $content .= '</div></div></div>';
        }
        $content .= '</div>';
    }
    
    if (!empty($submitted)) {
        $content .= '<div class="col-12"><h4 class="mt-4">✅ Submitted (Awaiting Grades)</h4>';
        foreach ($submitted as $a) {
            $type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
            $icon = $type_icons[$a['assignment_type']] ?? '📝';
            $submitted_at = $a['submission']['submitted_at'] ? date('M j, Y g:i A', strtotime($a['submission']['submitted_at'])) : '-';
            
            $content .= '<div class="card mb-3 border-success"><div class="card-body">';
            $content .= '<div class="d-flex justify-content-between align-items-start">';
            $content .= '<div>';
            $content .= '<h5>' . $icon . ' ' . htmlspecialchars($a['title']) . ' <span class="badge bg-success">Submitted</span></h5>';
            $content .= '<p class="text-muted"><small>Lesson: ' . htmlspecialchars($a['lesson_title']) . '</small></p>';
            $content .= '<p><strong>Submitted:</strong> ' . $submitted_at . '</p>';
            $content .= '</div>';
            $content .= '<a href="view_submission.php?submission_id=' . $a['submission']['id'] . '" class="btn btn-info">View</a>';
            $content .= '</div></div></div>';
        }
        $content .= '</div>';
    }
    
    if (!empty($graded)) {
        $content .= '<div class="col-12"><h4 class="mt-4">🎓 Graded</h4>';
        foreach ($graded as $a) {
            $type_icons = ['essay' => '📄', 'file_submission' => '📁', 'project' => '🎯', 'discussion' => '💬', 'peer_review' => '👥', 'quiz' => '❓'];
            $icon = $type_icons[$a['assignment_type']] ?? '📝';
            $grade_percent = $a['submission']['grade'] && $a['points_possible'] > 0 ? round(($a['submission']['grade'] / $a['points_possible']) * 100) : 0;
            
            $content .= '<div class="card mb-3 border-info"><div class="card-body">';
            $content .= '<div class="d-flex justify-content-between align-items-start">';
            $content .= '<div>';
            $content .= '<h5>' . $icon . ' ' . htmlspecialchars($a['title']) . ' <span class="badge bg-info">Graded</span></h5>';
            $content .= '<p class="text-muted"><small>Lesson: ' . htmlspecialchars($a['lesson_title']) . '</small></p>';
            $content .= '<p><strong>Grade:</strong> ' . $a['submission']['grade'] . ' / ' . $a['points_possible'] . ' (' . $grade_percent . '%)</p>';
            if ($a['submission']['feedback_text']) {
                $content .= '<p><strong>Feedback:</strong> ' . nl2br(htmlspecialchars(substr($a['submission']['feedback_text'], 0, 200))) . '...</p>';
            }
            $content .= '</div>';
            $content .= '<a href="view_submission.php?submission_id=' . $a['submission']['id'] . '" class="btn btn-info">View Details</a>';
            $content .= '</div></div></div>';
        }
        $content .= '</div>';
    }
    
    $content .= '</div>';
}

include 'includes/header.php';
?>
