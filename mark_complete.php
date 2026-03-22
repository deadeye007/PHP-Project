<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lesson_id'])) {
    $lesson_id = (int)$_POST['lesson_id'];
    markLessonComplete($_SESSION['user_id'], $lesson_id);
    header('Location: lesson.php?id=' . $lesson_id);
    exit;
}

header('Location: courses.php');
?>