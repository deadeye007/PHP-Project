<?php
require_once 'includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: courses.php');
    exit;
}

$course_id = (int)$_GET['id'];
$course = getCourse($course_id);

if (!$course) {
    $title = 'Course Not Found';
    $content = '<p>Course not found.</p>';
} else {
    $title = htmlspecialchars($course['title']);
    $content = '<h2>' . $title . '</h2>';
    // description is sanitized at creation/edit-time
    $content .= '<div>' . $course['description'] . '</div>';
    
    // Add navigation for course sections
    if (isLoggedIn()) {
        $content .= '<div class="row my-4">';
        $content .= '<div class="col-md-4"><a href="assignments.php?id=' . $course_id . '" class="btn btn-outline-primary w-100">📝 Assignments</a></div>';
        $content .= '<div class="col-md-4"><a href="grades.php" class="btn btn-outline-info w-100">📊 My Grades</a></div>';
        $content .= '<div class="col-md-4"><a href="inbox.php" class="btn btn-outline-secondary w-100">💬 Messages</a></div>';
        $content .= '</div>';
    }

    $lessons = getLessons($course_id);
    if ($lessons) {
        $content .= '<h3>Lessons</h3><ul class="list-group">';
        foreach ($lessons as $lesson) {
            $progress = isLoggedIn() ? getUserProgress($_SESSION['user_id'], $lesson['id']) : null;
            $status = $progress && $progress['completed'] ? ' (Completed)' : '';
            $content .= '<li class="list-group-item"><a href="lesson.php?id=' . $lesson['id'] . '">' . htmlspecialchars($lesson['title']) . '</a>' . $status . '</li>';
        }
        $content .= '</ul>';
    } else {
        $content .= '<p>No lessons available.</p>';
    }
}

include 'includes/header.php';
?>