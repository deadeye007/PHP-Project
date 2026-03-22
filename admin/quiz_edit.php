<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($lesson_id <= 0) {
    header('Location: courses.php');
    exit;
}

$lesson = getLesson($lesson_id);
if (!$lesson) {
    $title = 'Quiz Not Found';
    $content = '<p>Lesson not found.</p>';
    include '../includes/header.php';
    exit;
}

$course = getCourse($lesson['course_id']);
$quiz = getQuizByLesson($lesson_id, true);
$message = '';

if (isset($_GET['delete']) && $quiz) {
    deleteQuiz($quiz['id'], $lesson_id);
    header('Location: quiz_edit.php?lesson_id=' . $lesson_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saveQuiz($lesson_id, $_POST, $quiz['id'] ?? null);

    if ($result['success']) {
        header('Location: quiz_questions.php?quiz_id=' . $result['quiz_id']);
        exit;
    }

    $message = renderStatusMessage(htmlspecialchars($result['message']), 'danger');
    $quiz = array_merge($quiz ?: [], $_POST);
}

$title = ($quiz ? 'Edit Quiz' : 'Create Quiz') . ' - ' . htmlspecialchars($lesson['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="lessons.php?course_id=' . $lesson['course_id'] . '" class="btn btn-secondary btn-sm">Back to Lessons</a></p>';
$content .= '<p><strong>Course:</strong> ' . htmlspecialchars($course['title'] ?? '') . '<br><strong>Lesson:</strong> ' . htmlspecialchars($lesson['title']) . '</p>';
$content .= $message;
$content .= '<form method="post">';
$content .= '<div class="mb-3"><label for="title" class="form-label">Quiz Title</label><input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($quiz['title'] ?? '') . '" required></div>';
$content .= '<div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4">' . htmlspecialchars($quiz['description'] ?? '') . '</textarea></div>';
$content .= '<div class="row">';
$content .= '<div class="col-md-4 mb-3"><label for="passing_score" class="form-label">Passing Score (%)</label><input type="number" class="form-control" id="passing_score" name="passing_score" min="0" max="100" value="' . htmlspecialchars((string)($quiz['passing_score'] ?? 70)) . '" required></div>';
$content .= '<div class="col-md-4 mb-3"><label for="time_limit_seconds" class="form-label">Time Limit (seconds)</label><input type="number" class="form-control" id="time_limit_seconds" name="time_limit_seconds" min="1" value="' . htmlspecialchars((string)($quiz['time_limit_seconds'] ?? '')) . '"><div class="form-text">Leave blank for no time limit.</div></div>';
$content .= '<div class="col-md-4 mb-3"><label for="max_attempts" class="form-label">Max Attempts</label><input type="number" class="form-control" id="max_attempts" name="max_attempts" min="1" value="' . htmlspecialchars((string)($quiz['max_attempts'] ?? '')) . '"><div class="form-text">Leave blank for unlimited attempts.</div></div>';
$content .= '</div>';
$content .= '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1"' . (!empty($quiz['is_published']) ? ' checked' : '') . '><label class="form-check-label" for="is_published">Published and visible to students</label></div>';
$content .= '<button type="submit" class="btn btn-primary">Save Quiz</button>';
if ($quiz) {
    $content .= ' <a href="quiz_questions.php?quiz_id=' . $quiz['id'] . '" class="btn btn-outline-primary">Manage Questions</a>';
    $content .= ' <a href="quiz_edit.php?lesson_id=' . $lesson_id . '&delete=1" class="btn btn-outline-danger" onclick="return confirm(\'Delete this quiz and all its questions?\')">Delete Quiz</a>';
}
$content .= '</form>';

include '../includes/header.php';
?>
