<?php
require_once 'security.php';
require_once __DIR__ . '/../config.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getUser($_SESSION['user_id']);
    return $user && $user['role'] === 'admin';
}

// Regenerate session ID periodically to prevent session hijacking
function regenerateSession() {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function getCourses() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, title, description FROM courses ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourse($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, description, editor_mode FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getLessons($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, order_num FROM lessons WHERE course_id = ? ORDER BY order_num");
    $stmt->execute([$course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLesson($lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, course_id, title, content, order_num, editor_mode FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserProgress($user_id, $lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT completed, completed_at FROM user_progress WHERE user_id = ? AND lesson_id = ?");
    $stmt->execute([$user_id, $lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function markLessonComplete($user_id, $lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, TRUE, NOW()) ON DUPLICATE KEY UPDATE completed = TRUE, completed_at = NOW()");
    $stmt->execute([$user_id, $lesson_id]);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function renderStatusMessage($message, $type = 'info') {
    if ($message === null || $message === '') {
        return '';
    }

    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $liveMode = $type === 'danger' ? 'assertive' : 'polite';
    $role = $type === 'danger' ? 'alert' : 'status';

    return '<div class="alert alert-' . $type . '" role="' . $role . '" aria-live="' . $liveMode . '">' . $message . '</div>';
}

// Sanitize HTML content for rich text (course/lesson descriptions)
function sanitizeHTML($html) {
    // Allowed tags set in config
    $allowed_tags = defined('ALLOWED_HTML_TAGS') ? ALLOWED_HTML_TAGS : '<p><br><strong><b><em><i><u><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre><span><div><table><thead><tbody><tr><th><td>';
    $safe = strip_tags($html, $allowed_tags);
    $safe = preg_replace('/ on\w+="[^"]*"/i', '', $safe);
    $safe = preg_replace('/ on\w+=\'[^\']*\'/i', '', $safe);
    $safe = preg_replace('/ on\w+=[^\s>]+/i', '', $safe);
    $safe = preg_replace('/javascript:/i', '', $safe);
    $safe = preg_replace_callback('/<(a|img)[^>]+>/i', function ($matches) {
        $tag = $matches[0];
        $tag = preg_replace('/(href|src)="\s*javascript:[^\"]*"/i', '$1="#"', $tag);
        return $tag;
    }, $safe);
    return $safe;
}

// Markdown support (Simple converter for admin content when enabled)
function markdownToHtml($markdown) {
    $html = htmlspecialchars($markdown);

    // Convert headings
    for ($i = 6; $i >= 1; $i--) {
        $pattern = '/^' . str_repeat('#', $i) . '\s*(.+)$/m';
        $html = preg_replace($pattern, '<h' . $i . '>$1</h' . $i . '>', $html);
    }

    // Convert bold/italic
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/_(.+?)_/s', '<em>$1</em>', $html);

    // Convert links [text](url)
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $html);

    // Convert unordered lists
    if (preg_match('/^\s*\*\s+/m', $markdown)) {
        $html = preg_replace_callback('/((?:^\s*\*\s+.+(?:\R|$))+)/m', function ($matches) {
            $items = preg_replace('/^\s*\*\s+(.+)/m', '<li>$1</li>', $matches[1]);
            return '<ul>' . $items . '</ul>';
        }, $html);
    }

    // Convert paragraphs
    $paragraphs = preg_split('/\n{2,}/', $html);
    $html = '';
    foreach ($paragraphs as $p) {
        $trimmed = trim($p);
        if ($trimmed !== '' && !preg_match('/^<h\d|<ul|<ol|<blockquote|<pre|<p|<table/', $trimmed)) {
            $html .= '<p>' . $trimmed . '</p>';
        } else {
            $html .= $trimmed;
        }
    }

    return $html;
}

// Rate limiting: Check if login attempt should be blocked
function isLoginBlocked($username, $ip) {
    global $pdo;
    
    // Clean old attempts (older than 1 hour)
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    
    // Count recent attempts (last 15 minutes)
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE (username = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$username, $ip]);
    $result = $stmt->fetch();
    
    return $result['attempts'] >= 5; // Block after 5 attempts
}

// Rate limiting: Record login attempt
function recordLoginAttempt($username, $ip) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $stmt->execute([$ip, $username]);
}

// IP validation: Check if session IP matches current IP
function validateSessionIP() {
    if (!isset($_SESSION['ip_address'])) {
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        return true;
    }
    
    // Allow some tolerance for dynamic IPs (first 3 octets for IPv4)
    $session_ip = $_SESSION['ip_address'];
    $current_ip = $_SERVER['REMOTE_ADDR'];
    
    // For IPv4, compare first 3 octets
    if (filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $session_parts = explode('.', $session_ip);
        $current_parts = explode('.', $current_ip);
        return $session_parts[0] === $current_parts[0] && 
               $session_parts[1] === $current_parts[1] && 
               $session_parts[2] === $current_parts[2];
    }
    
    // For IPv6 or exact match
    return $session_ip === $current_ip;
}

// Audit logging: Log security events
function logAuditEvent($event_type, $user_id = null, $details = []) {
    global $pdo;
    
    $details['timestamp'] = date('Y-m-d H:i:s');
    $details['session_id'] = session_id();
    
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, event_type, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user_id,
        $event_type,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode($details)
    ]);
}

// Session management: Store session in database
function storeUserSession($user_id) {
    global $pdo;
    
    // Clean old sessions (older than 30 days)
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    // Store current session
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    $stmt->execute([
        $user_id,
        session_id(),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Session management: Validate and update session
function validateUserSession() {
    if (!isLoggedIn()) return false;
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
    
    if ($stmt->fetch()) {
        // Update last activity
        $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
        return true;
    }
    
    return false;
}

// Password change: Invalidate all other sessions
function invalidateOtherSessions($user_id, $current_session_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
    $stmt->execute([$user_id, $current_session_id]);
    
    logAuditEvent('password_change_sessions_invalidated', $user_id, ['action' => 'invalidated_other_sessions']);
}

// Base32 encode/decode helpers for TOTP
function base32Encode($data) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $output = '';

    foreach (str_split($data) as $char) {
        $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    while (strlen($binary) % 5 !== 0) {
        $binary .= '0';
    }

    foreach (str_split($binary, 5) as $chunk) {
        $output .= $alphabet[bindec($chunk)];
    }

    while (strlen($output) % 8 !== 0) {
        $output .= '=';
    }

    return $output;
}

function base32Decode($base32) {
    $base32 = strtoupper($base32);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = rtrim($base32, '=');
    $binary = '';

    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            return false;
        }
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $output = '';
    foreach (str_split($binary, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $output .= chr(bindec($byte));
    }

    return $output;
}

// Two-factor authentication: Generate TOTP secret (Base32 for authenticator apps)
function generateTOTPSecret() {
    $randomBytes = random_bytes(20); // 160-bit secret
    return rtrim(base32Encode($randomBytes), '=');
}

// Two-factor authentication: Verify TOTP code
function verifyTOTPCode($secret, $code) {
    $secretKey = base32Decode($secret);
    if ($secretKey === false) {
        // fallback for old hex secrets
        $secretKey = hex2bin($secret);
    }

    if ($secretKey === false) {
        return false;
    }

    $time = floor(time() / 30); // 30-second windows
    for ($i = -1; $i <= 1; $i++) {
        $time_window = $time + $i;
        $time_bytes = pack('N*', 0, $time_window);
        $hash = hash_hmac('sha1', $time_bytes, $secretKey, true);
        $offset = ord($hash[19]) & 0x0f;

        $code_generated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        if (str_pad($code_generated, 6, '0', STR_PAD_LEFT) === $code) {
            return true;
        }
    }

    return false;
}

function getQuizByLesson($lesson_id, $includeUnpublished = false) {
    global $pdo;
    $sql = "SELECT * FROM quizzes WHERE lesson_id = ?";
    if (!$includeUnpublished) {
        $sql .= " AND is_published = TRUE";
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQuiz($quiz_id, $includeUnpublished = false) {
    global $pdo;
    $sql = "SELECT q.*, l.title AS lesson_title, l.course_id, c.title AS course_title
            FROM quizzes q
            JOIN lessons l ON q.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            WHERE q.id = ?";
    if (!$includeUnpublished) {
        $sql .= " AND q.is_published = TRUE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQuizQuestions($quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC, id ASC");
    $stmt->execute([$quiz_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQuizQuestionsWithAnswers($quiz_id) {
    global $pdo;
    $questions = getQuizQuestions($quiz_id);
    $answerStmt = $pdo->prepare("SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY order_num ASC, id ASC");

    foreach ($questions as &$question) {
        $answerStmt->execute([$question['id']]);
        $question['answers'] = $answerStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $questions;
}

function getQuizQuestion($question_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qq.*, q.lesson_id, q.title AS quiz_title
        FROM quiz_questions qq
        JOIN quizzes q ON qq.quiz_id = q.id
        WHERE qq.id = ?
    ");
    $stmt->execute([$question_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQuizAnswers($question_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY order_num ASC, id ASC");
    $stmt->execute([$question_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQuizAttemptHistory($user_id, $quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT *
        FROM quiz_attempts
        WHERE user_id = ? AND quiz_id = ?
        ORDER BY submitted_at DESC, id DESC
    ");
    $stmt->execute([$user_id, $quiz_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQuizLatestAttempt($user_id, $quiz_id) {
    $attempts = getQuizAttemptHistory($user_id, $quiz_id);
    return $attempts ? $attempts[0] : null;
}

function getQuizAttemptCount($user_id, $quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
    $stmt->execute([$user_id, $quiz_id]);
    return (int)$stmt->fetchColumn();
}

function canUserAttemptQuiz($user_id, $quiz) {
    if (!$quiz) {
        return false;
    }

    if (empty($quiz['max_attempts'])) {
        return true;
    }

    return getQuizAttemptCount($user_id, $quiz['id']) < (int)$quiz['max_attempts'];
}

function getQuizAttemptById($attempt_id, $user_id = null) {
    global $pdo;
    $sql = "
        SELECT qa.*, q.title AS quiz_title, q.passing_score, q.lesson_id, l.title AS lesson_title, l.course_id, c.title AS course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN lessons l ON q.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        WHERE qa.id = ?
    ";
    $params = [$attempt_id];

    if ($user_id !== null) {
        $sql .= " AND qa.user_id = ?";
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQuizAttemptResponses($attempt_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT qar.*, qq.question_text, qq.points, qa.answer_text AS selected_answer_text, correct.answer_text AS correct_answer_text
        FROM quiz_attempt_responses qar
        JOIN quiz_questions qq ON qar.question_id = qq.id
        LEFT JOIN quiz_answers qa ON qar.selected_answer_id = qa.id
        LEFT JOIN quiz_answers correct ON correct.question_id = qq.id AND correct.is_correct = TRUE
        WHERE qar.attempt_id = ?
        ORDER BY qq.order_num ASC, qq.id ASC
    ");
    $stmt->execute([$attempt_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveQuiz($lesson_id, $data, $quiz_id = null) {
    global $pdo;

    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $passing_score = (int)($data['passing_score'] ?? 70);
    $time_limit_seconds = trim((string)($data['time_limit_seconds'] ?? ''));
    $max_attempts = trim((string)($data['max_attempts'] ?? ''));
    $is_published = !empty($data['is_published']) ? 1 : 0;

    if ($title === '') {
        return ['success' => false, 'message' => 'Quiz title is required.'];
    }

    if ($passing_score < 0 || $passing_score > 100) {
        return ['success' => false, 'message' => 'Passing score must be between 0 and 100.'];
    }

    $time_limit_value = $time_limit_seconds !== '' ? max(1, (int)$time_limit_seconds) : null;
    $max_attempts_value = $max_attempts !== '' ? max(1, (int)$max_attempts) : null;

    if ($quiz_id) {
        $stmt = $pdo->prepare("
            UPDATE quizzes
            SET title = ?, description = ?, passing_score = ?, time_limit_seconds = ?, max_attempts = ?, is_published = ?
            WHERE id = ? AND lesson_id = ?
        ");
        $stmt->execute([$title, $description, $passing_score, $time_limit_value, $max_attempts_value, $is_published, $quiz_id, $lesson_id]);
        return ['success' => true, 'quiz_id' => $quiz_id];
    }

    $stmt = $pdo->prepare("
        INSERT INTO quizzes (lesson_id, title, description, passing_score, time_limit_seconds, max_attempts, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$lesson_id, $title, $description, $passing_score, $time_limit_value, $max_attempts_value, $is_published]);
    return ['success' => true, 'quiz_id' => (int)$pdo->lastInsertId()];
}

function saveQuizQuestion($quiz_id, $data, $question_id = null) {
    global $pdo;

    $question_text = trim($data['question_text'] ?? '');
    $points = max(1, (int)($data['points'] ?? 1));
    $order_num = max(1, (int)($data['order_num'] ?? 1));
    $answers = $data['answers'] ?? [];
    $correct_answer_index = isset($data['correct_answer']) ? (int)$data['correct_answer'] : -1;

    if ($question_text === '') {
        return ['success' => false, 'message' => 'Question text is required.'];
    }

    $normalizedAnswers = [];
    foreach ($answers as $index => $answerText) {
        $trimmed = trim($answerText);
        if ($trimmed !== '') {
            $normalizedAnswers[] = ['text' => $trimmed, 'original_index' => $index];
        }
    }

    if (count($normalizedAnswers) < 2) {
        return ['success' => false, 'message' => 'At least two answer options are required.'];
    }

    $hasCorrect = false;
    foreach ($normalizedAnswers as $answer) {
        if ($answer['original_index'] === $correct_answer_index) {
            $hasCorrect = true;
            break;
        }
    }

    if (!$hasCorrect) {
        return ['success' => false, 'message' => 'Select the correct answer for the question.'];
    }

    $pdo->beginTransaction();

    try {
        if ($question_id) {
            $stmt = $pdo->prepare("
                UPDATE quiz_questions
                SET question_text = ?, points = ?, order_num = ?
                WHERE id = ? AND quiz_id = ?
            ");
            $stmt->execute([$question_text, $points, $order_num, $question_id, $quiz_id]);

            $deleteAnswers = $pdo->prepare("DELETE FROM quiz_answers WHERE question_id = ?");
            $deleteAnswers->execute([$question_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, points, order_num)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$quiz_id, $question_text, $points, $order_num]);
            $question_id = (int)$pdo->lastInsertId();
        }

        $answerStmt = $pdo->prepare("
            INSERT INTO quiz_answers (question_id, answer_text, is_correct, order_num)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($normalizedAnswers as $position => $answer) {
            $answerStmt->execute([
                $question_id,
                $answer['text'],
                $answer['original_index'] === $correct_answer_index ? 1 : 0,
                $position + 1
            ]);
        }

        $pdo->commit();
        return ['success' => true, 'question_id' => $question_id];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Unable to save the quiz question.'];
    }
}

function deleteQuizQuestion($question_id, $quiz_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
    return $stmt->execute([$question_id, $quiz_id]);
}

function deleteQuiz($quiz_id, $lesson_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ? AND lesson_id = ?");
    return $stmt->execute([$quiz_id, $lesson_id]);
}

function gradeQuizSubmission($quiz_id, $user_id, $submitted_answers) {
    global $pdo;

    $quiz = getQuiz($quiz_id, true);
    if (!$quiz || !(bool)$quiz['is_published']) {
        return ['success' => false, 'message' => 'Quiz is not available.'];
    }

    if (!canUserAttemptQuiz($user_id, $quiz)) {
        return ['success' => false, 'message' => 'You have reached the attempt limit for this quiz.'];
    }

    $questions = getQuizQuestionsWithAnswers($quiz_id);
    if (!$questions) {
        return ['success' => false, 'message' => 'Quiz has no questions yet.'];
    }

    $score = 0;
    $max_score = 0;
    $responses = [];

    foreach ($questions as $question) {
        $max_score += (int)$question['points'];
        $selected_answer_id = isset($submitted_answers[$question['id']]) ? (int)$submitted_answers[$question['id']] : null;
        $selectedIsValid = false;
        $is_correct = false;

        foreach ($question['answers'] as $answer) {
            if ($selected_answer_id === (int)$answer['id']) {
                $selectedIsValid = true;
                $is_correct = (bool)$answer['is_correct'];
                break;
            }
        }

        if (!$selectedIsValid) {
            $selected_answer_id = null;
            $is_correct = false;
        }

        $points_awarded = $is_correct ? (int)$question['points'] : 0;
        $score += $points_awarded;

        $responses[] = [
            'question_id' => (int)$question['id'],
            'selected_answer_id' => $selected_answer_id,
            'is_correct' => $is_correct ? 1 : 0,
            'points_awarded' => $points_awarded
        ];
    }

    $percentage = $max_score > 0 ? round(($score / $max_score) * 100, 2) : 0;
    $passed = $percentage >= (int)$quiz['passing_score'];

    $pdo->beginTransaction();

    try {
        $attemptStmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, score, max_score, percentage, passed, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $attemptStmt->execute([$user_id, $quiz_id, $score, $max_score, $percentage, $passed ? 1 : 0]);
        $attempt_id = (int)$pdo->lastInsertId();

        $responseStmt = $pdo->prepare("
            INSERT INTO quiz_attempt_responses (attempt_id, question_id, selected_answer_id, is_correct, points_awarded)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($responses as $response) {
            $responseStmt->execute([
                $attempt_id,
                $response['question_id'],
                $response['selected_answer_id'],
                $response['is_correct'],
                $response['points_awarded']
            ]);
        }

        $pdo->commit();

        return [
            'success' => true,
            'attempt_id' => $attempt_id,
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'passed' => $passed
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Unable to submit the quiz right now.'];
    }
}
?>
