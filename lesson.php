<?php
require_once 'includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: courses.php');
    exit;
}

$lesson_id = (int)$_GET['id'];

// Get lesson details (assuming lesson table has course_id)
global $pdo;
$stmt = $pdo->prepare("SELECT l.*, c.title as course_title FROM lessons l JOIN courses c ON l.course_id = c.id WHERE l.id = ?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    $title = 'Lesson Not Found';
    $content = '<p>Lesson not found.</p>';
} else {
    $title = htmlspecialchars($lesson['title']);
    $content = '<h2>' . $title . '</h2>';
    $content .= '<p>Course: ' . htmlspecialchars($lesson['course_title']) . '</p>';
    $content .= '<div>' . nl2br(htmlspecialchars($lesson['content'])) . '</div>';

    if (isLoggedIn()) {
        $progress = getUserProgress($_SESSION['user_id'], $lesson_id);
        if (!$progress || !$progress['completed']) {
            $content .= '<form method="post" action="mark_complete.php">';
            $content .= '<input type="hidden" name="lesson_id" value="' . $lesson_id . '">';
            $content .= '<button type="submit" class="btn btn-success mt-3">Mark as Complete</button>';
            $content .= '</form>';
        } else {
            $content .= '<p class="text-success mt-3">Lesson completed!</p>';
        }
    }
}

include 'includes/header.php';
?>