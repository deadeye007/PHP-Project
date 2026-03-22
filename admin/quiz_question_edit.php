<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$quiz = $quiz_id > 0 ? getQuiz($quiz_id, true) : null;

if (!$quiz) {
    header('Location: courses.php');
    exit;
}

$question = null;
$existingAnswers = ['', '', '', ''];
$selectedCorrectIndex = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $question = getQuizQuestion((int)$_GET['id']);
    if (!$question || (int)$question['quiz_id'] !== $quiz_id) {
        header('Location: quiz_questions.php?quiz_id=' . $quiz_id);
        exit;
    }

    $storedAnswers = getQuizAnswers($question['id']);
    foreach ($storedAnswers as $index => $answer) {
        if ($index < 4) {
            $existingAnswers[$index] = $answer['answer_text'];
        }
        if ((int)$answer['is_correct'] === 1) {
            $selectedCorrectIndex = $index;
        }
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saveQuizQuestion($quiz_id, $_POST, $question['id'] ?? null);

    if ($result['success']) {
        header('Location: quiz_questions.php?quiz_id=' . $quiz_id);
        exit;
    }

    $message = renderStatusMessage(htmlspecialchars($result['message']), 'danger');
    $question = array_merge($question ?: [], $_POST);
    $existingAnswers = $_POST['answers'] ?? $existingAnswers;
    $selectedCorrectIndex = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : null;
}

$title = ($question ? 'Edit Question' : 'Add Question') . ' - ' . htmlspecialchars($quiz['title']);
$content = '<h2>' . $title . '</h2>';
$content .= '<p><a href="quiz_questions.php?quiz_id=' . $quiz_id . '" class="btn btn-secondary btn-sm">Back to Questions</a></p>';
$content .= $message;
$content .= '<form method="post">';
$content .= '<div class="mb-3"><label for="question_text" class="form-label">Question</label><textarea class="form-control" id="question_text" name="question_text" rows="4" required>' . htmlspecialchars($question['question_text'] ?? '') . '</textarea></div>';
$content .= '<div class="row">';
$content .= '<div class="col-md-6 mb-3"><label for="points" class="form-label">Points</label><input type="number" class="form-control" id="points" name="points" min="1" value="' . htmlspecialchars((string)($question['points'] ?? 1)) . '" required></div>';
$content .= '<div class="col-md-6 mb-3"><label for="order_num" class="form-label">Display Order</label><input type="number" class="form-control" id="order_num" name="order_num" min="1" value="' . htmlspecialchars((string)($question['order_num'] ?? 1)) . '" required></div>';
$content .= '</div>';
$content .= '<h4>Answers</h4>';

for ($i = 0; $i < 4; $i++) {
    $content .= '<div class="input-group mb-2">';
    $content .= '<span class="input-group-text"><input type="radio" name="correct_answer" value="' . $i . '"' . ($selectedCorrectIndex === $i ? ' checked' : '') . '></span>';
    $content .= '<input type="text" class="form-control" name="answers[]" value="' . htmlspecialchars($existingAnswers[$i] ?? '') . '" placeholder="Answer option ' . ($i + 1) . '">';
    $content .= '</div>';
}

$content .= '<div class="form-text mb-3">Provide at least two answers and choose the one correct option.</div>';
$content .= '<button type="submit" class="btn btn-primary">Save Question</button>';
$content .= '</form>';

include '../includes/header.php';
?>
