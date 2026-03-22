<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz = $quiz_id > 0 ? getQuiz($quiz_id) : null;

if (!$quiz) {
    $title = 'Quiz Not Found';
    $content = '<p>Quiz not found or not published.</p>';
    include 'includes/header.php';
    exit;
}

if (!canUserAttemptQuiz($_SESSION['user_id'], $quiz)) {
    header('Location: lesson.php?id=' . $quiz['lesson_id']);
    exit;
}

$questions = getQuizQuestionsWithAnswers($quiz_id);
if (!$questions) {
    $title = 'Quiz Unavailable';
    $content = '<p>This quiz has no questions yet.</p>';
    include 'includes/header.php';
    exit;
}

if (!isset($_SESSION['quiz_start_times'])) {
    $_SESSION['quiz_start_times'] = [];
}

if (!isset($_SESSION['quiz_start_times'][$quiz_id])) {
    $_SESSION['quiz_start_times'][$quiz_id] = time();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($quiz['time_limit_seconds'])) {
        $elapsed = time() - (int)($_SESSION['quiz_start_times'][$quiz_id] ?? time());
        if ($elapsed > (int)$quiz['time_limit_seconds']) {
            $message = renderStatusMessage('Time has expired for this quiz. Please start a new attempt.', 'danger');
            unset($_SESSION['quiz_start_times'][$quiz_id]);
        } else {
            $result = gradeQuizSubmission($quiz_id, $_SESSION['user_id'], $_POST['answers'] ?? []);
            if ($result['success']) {
                unset($_SESSION['quiz_start_times'][$quiz_id]);
                header('Location: quiz_results.php?attempt_id=' . $result['attempt_id']);
                exit;
            }
            $message = renderStatusMessage(htmlspecialchars($result['message']), 'danger');
        }
    } else {
        $result = gradeQuizSubmission($quiz_id, $_SESSION['user_id'], $_POST['answers'] ?? []);
        if ($result['success']) {
            unset($_SESSION['quiz_start_times'][$quiz_id]);
            header('Location: quiz_results.php?attempt_id=' . $result['attempt_id']);
            exit;
        }
        $message = renderStatusMessage(htmlspecialchars($result['message']), 'danger');
    }
}

$title = htmlspecialchars($quiz['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="lesson.php?id=' . $quiz['lesson_id'] . '" class="btn btn-secondary btn-sm">Back to Lesson</a></p>';
$content .= '<p><strong>Course:</strong> ' . htmlspecialchars($quiz['course_title']) . '<br><strong>Lesson:</strong> ' . htmlspecialchars($quiz['lesson_title']) . '<br><strong>Passing score:</strong> ' . (int)$quiz['passing_score'] . '%</p>';
if (!empty($quiz['description'])) {
    $content .= '<p>' . nl2br(htmlspecialchars($quiz['description'])) . '</p>';
}
if (!empty($quiz['time_limit_seconds'])) {
    $content .= '<p><strong>Time limit:</strong> ' . (int)$quiz['time_limit_seconds'] . ' seconds</p>';
}
$content .= $message;
$content .= '<form method="post">';

foreach ($questions as $index => $question) {
    $content .= '<div class="card mb-3"><div class="card-body">';
    $content .= '<h5 class="card-title">Question ' . ($index + 1) . '</h5>';
    $content .= '<p>' . nl2br(htmlspecialchars($question['question_text'])) . '</p>';
    $content .= '<p><small>Points: ' . (int)$question['points'] . '</small></p>';

    foreach ($question['answers'] as $answer) {
        $content .= '<div class="form-check">';
        $content .= '<input class="form-check-input" type="radio" name="answers[' . $question['id'] . ']" id="answer_' . $answer['id'] . '" value="' . $answer['id'] . '">';
        $content .= '<label class="form-check-label" for="answer_' . $answer['id'] . '">' . htmlspecialchars($answer['answer_text']) . '</label>';
        $content .= '</div>';
    }

    $content .= '</div></div>';
}

$content .= '<button type="submit" class="btn btn-primary">Submit Quiz</button>';
$content .= '</form>';

include 'includes/header.php';
?>
