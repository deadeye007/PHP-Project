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
    $result = $stmt->execute([$user_id, $lesson_id]);

    if ($result) {
        // Check for lesson completion badges
        checkAndAwardCredentials($user_id, 'lesson_completed', $lesson_id);

        // Check if course is now completed
        $lesson = getLesson($lesson_id);
        if ($lesson && isCourseCompleted($user_id, $lesson['course_id'])) {
            checkAndAwardCredentials($user_id, 'course_completed', $lesson['course_id']);
        }
    }

    return $result;
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

        // Check for quiz passing awards if the quiz was passed
        if ($passed) {
            checkAndAwardCredentials($user_id, 'quiz_passed', $quiz_id);
        }

        // Send quiz result notification to student
        notifyQuizResultPosted($user_id, $quiz_id, $score, $max_score, $percentage, $passed);

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

function getCourseRoster($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.email
        FROM users u
        LEFT JOIN user_progress up ON up.user_id = u.id
        LEFT JOIN lessons l_progress ON up.lesson_id = l_progress.id AND l_progress.course_id = ?
        LEFT JOIN quiz_attempts qa ON qa.user_id = u.id
        LEFT JOIN quizzes q ON qa.quiz_id = q.id
        LEFT JOIN lessons l_quiz ON q.lesson_id = l_quiz.id AND l_quiz.course_id = ?
        WHERE u.role = 'user'
          AND (l_progress.id IS NOT NULL OR l_quiz.id IS NOT NULL)
        ORDER BY u.username ASC
    ");
    $stmt->execute([$course_id, $course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourseLessonsWithQuizzes($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT l.id AS lesson_id, l.title AS lesson_title, l.order_num, q.id AS quiz_id, q.title AS quiz_title, q.passing_score
        FROM lessons l
        LEFT JOIN quizzes q ON q.lesson_id = l.id
        WHERE l.course_id = ?
        ORDER BY l.order_num ASC, l.id ASC
    ");
    $stmt->execute([$course_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserCourseGradebook($user_id, $course_id) {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return null;
    }

    $lessons = getCourseLessonsWithQuizzes($course_id);
    $lessonIds = [];
    $quizIds = [];

    foreach ($lessons as $lesson) {
        $lessonIds[] = (int)$lesson['lesson_id'];
        if (!empty($lesson['quiz_id'])) {
            $quizIds[] = (int)$lesson['quiz_id'];
        }
    }

    $completedLessonIds = [];
    if ($lessonIds) {
        $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
        $params = array_merge([$user_id], $lessonIds);
        $stmt = $pdo->prepare("SELECT lesson_id FROM user_progress WHERE user_id = ? AND completed = TRUE AND lesson_id IN ($placeholders)");
        $stmt->execute($params);
        $completedLessonIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $quizStats = [];
    if ($quizIds) {
        $placeholders = implode(',', array_fill(0, count($quizIds), '?'));

        $bestParams = array_merge([$user_id], $quizIds);
        $bestStmt = $pdo->prepare("
            SELECT quiz_id, MAX(percentage) AS best_percentage, MAX(CASE WHEN passed = TRUE THEN 1 ELSE 0 END) AS passed_attempt
            FROM quiz_attempts
            WHERE user_id = ? AND quiz_id IN ($placeholders)
            GROUP BY quiz_id
        ");
        $bestStmt->execute($bestParams);
        foreach ($bestStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quizId = (int)$row['quiz_id'];
            if (!isset($quizStats[$quizId])) {
                $quizStats[$quizId] = [];
            }
            $quizStats[$quizId]['best_percentage'] = $row['best_percentage'] !== null ? (float)$row['best_percentage'] : null;
            $quizStats[$quizId]['passed_attempt'] = (int)$row['passed_attempt'] === 1;
        }

        $latestParams = array_merge([$user_id], $quizIds);
        $latestStmt = $pdo->prepare("
            SELECT qa.quiz_id, qa.percentage, qa.passed, qa.submitted_at
            FROM quiz_attempts qa
            INNER JOIN (
                SELECT quiz_id, MAX(id) AS latest_id
                FROM quiz_attempts
                WHERE user_id = ? AND quiz_id IN ($placeholders)
                GROUP BY quiz_id
            ) latest ON latest.latest_id = qa.id
        ");
        $latestStmt->execute($latestParams);
        foreach ($latestStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quizId = (int)$row['quiz_id'];
            if (!isset($quizStats[$quizId])) {
                $quizStats[$quizId] = [];
            }
            $quizStats[$quizId]['latest_percentage'] = (float)$row['percentage'];
            $quizStats[$quizId]['latest_passed'] = (int)$row['passed'] === 1;
            $quizStats[$quizId]['latest_submitted_at'] = $row['submitted_at'];
        }

        $countParams = array_merge([$user_id], $quizIds);
        $countStmt = $pdo->prepare("
            SELECT quiz_id, COUNT(*) AS attempts
            FROM quiz_attempts
            WHERE user_id = ? AND quiz_id IN ($placeholders)
            GROUP BY quiz_id
        ");
        $countStmt->execute($countParams);
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quizId = (int)$row['quiz_id'];
            if (!isset($quizStats[$quizId])) {
                $quizStats[$quizId] = [];
            }
            $quizStats[$quizId]['attempts'] = (int)$row['attempts'];
        }
    }

    $completedLessons = count($completedLessonIds);
    $totalLessons = count($lessons);
    $quizCount = 0;
    $passedQuizCount = 0;
    $bestQuizPercentages = [];
    $lessonBreakdown = [];

    foreach ($lessons as $lesson) {
        $lessonId = (int)$lesson['lesson_id'];
        $quizId = !empty($lesson['quiz_id']) ? (int)$lesson['quiz_id'] : null;
        $lessonCompleted = in_array($lessonId, $completedLessonIds, true);
        $lessonQuizStats = $quizId && isset($quizStats[$quizId]) ? $quizStats[$quizId] : null;

        if ($quizId) {
            $quizCount++;
            if (!empty($lessonQuizStats['passed_attempt'])) {
                $passedQuizCount++;
            }
            if (isset($lessonQuizStats['best_percentage'])) {
                $bestQuizPercentages[] = (float)$lessonQuizStats['best_percentage'];
            }
        }

        $lessonBreakdown[] = [
            'lesson_id' => $lessonId,
            'lesson_title' => $lesson['lesson_title'],
            'order_num' => (int)$lesson['order_num'],
            'completed' => $lessonCompleted,
            'quiz_id' => $quizId,
            'quiz_title' => $lesson['quiz_title'],
            'quiz_passing_score' => $lesson['passing_score'],
            'quiz_best_percentage' => $lessonQuizStats['best_percentage'] ?? null,
            'quiz_latest_percentage' => $lessonQuizStats['latest_percentage'] ?? null,
            'quiz_attempts' => $lessonQuizStats['attempts'] ?? 0,
            'quiz_passed' => $lessonQuizStats['passed_attempt'] ?? false
        ];
    }

    $lessonProgressPercent = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;
    $quizAveragePercent = $bestQuizPercentages ? round(array_sum($bestQuizPercentages) / count($bestQuizPercentages), 1) : null;

    return [
        'course' => $course,
        'lessons' => $lessonBreakdown,
        'summary' => [
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'lesson_progress_percent' => $lessonProgressPercent,
            'quizzes_passed' => $passedQuizCount,
            'total_quizzes' => $quizCount,
            'quiz_average_percent' => $quizAveragePercent
        ]
    ];
}

function getCourseGradebook($course_id) {
    $course = getCourse($course_id);
    if (!$course) {
        return null;
    }

    $roster = getCourseRoster($course_id);
    $students = [];

    foreach ($roster as $student) {
        $gradebook = getUserCourseGradebook((int)$student['id'], $course_id);
        if ($gradebook === null) {
            continue;
        }

        $students[] = [
            'id' => (int)$student['id'],
            'username' => $student['username'],
            'email' => $student['email'],
            'summary' => $gradebook['summary']
        ];
    }

    usort($students, function ($left, $right) {
        return strcmp($left['username'], $right['username']);
    });

    return [
        'course' => $course,
        'students' => $students
    ];
}

// ===== CERTIFICATES & BADGES SYSTEM =====

// Check if user has completed a course (all lessons marked complete)
function isCourseCompleted($user_id, $course_id) {
    global $pdo;

    // Get total lessons in course
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lessons WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $total_lessons = $stmt->fetch()['total'];

    if ($total_lessons == 0) return false;

    // Get completed lessons by user
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM user_progress up JOIN lessons l ON up.lesson_id = l.id WHERE up.user_id = ? AND l.course_id = ? AND up.completed = TRUE");
    $stmt->execute([$user_id, $course_id]);
    $completed_lessons = $stmt->fetch()['completed'];

    return $completed_lessons == $total_lessons;
}

// Check if user has passed a quiz with required score
function hasPassedQuiz($user_id, $quiz_id, $min_score = 70) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT percentage, passed FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$user_id, $quiz_id]);
    $attempt = $stmt->fetch();

    return $attempt && $attempt['passed'] && $attempt['percentage'] >= $min_score;
}

// Check if user has completed a lesson
function hasCompletedLesson($user_id, $lesson_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
    $stmt->execute([$user_id, $lesson_id]);
    $progress = $stmt->fetch();

    return $progress && $progress['completed'];
}

// Check learning streak (consecutive days with activity)
function getLearningStreak($user_id) {
    global $pdo;

    // Get user's activity dates (lesson completions or quiz attempts)
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(completed_at) as activity_date
        FROM user_progress
        WHERE user_id = ? AND completed = TRUE
        UNION
        SELECT DISTINCT DATE(submitted_at) as activity_date
        FROM quiz_attempts
        WHERE user_id = ?
        ORDER BY activity_date DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $activity_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($activity_dates)) return 0;

    $streak = 0;
    $current_date = new DateTime();

    foreach ($activity_dates as $date) {
        $activity_date = new DateTime($date);
        $days_diff = $current_date->diff($activity_date)->days;

        if ($days_diff == $streak) {
            $streak++;
        } elseif ($days_diff > $streak) {
            break; // Gap in streak
        }
    }

    return $streak;
}

// Award certificate automatically
function awardCertificate($user_id, $certificate_id) {
    global $pdo;

    // Check if already awarded
    $stmt = $pdo->prepare("SELECT id FROM user_certificates WHERE user_id = ? AND certificate_id = ?");
    $stmt->execute([$user_id, $certificate_id]);
    if ($stmt->fetch()) {
        return false; // Already awarded
    }

    // Generate verification code
    $verification_code = bin2hex(random_bytes(16));

    // Get certificate details for metadata
    $stmt = $pdo->prepare("SELECT c.*, co.title as course_title, q.title as quiz_title FROM certificates c LEFT JOIN courses co ON c.course_id = co.id LEFT JOIN quizzes q ON c.quiz_id = q.id WHERE c.id = ?");
    $stmt->execute([$certificate_id]);
    $certificate = $stmt->fetch();

    $metadata = [
        'awarded_at' => date('Y-m-d H:i:s'),
        'certificate_title' => $certificate['title']
    ];

    if ($certificate['award_criteria'] === 'course_completion' && $certificate['course_id']) {
        $metadata['course_id'] = $certificate['course_id'];
        $metadata['course_title'] = $certificate['course_title'];
    } elseif ($certificate['award_criteria'] === 'quiz_passing' && $certificate['quiz_id']) {
        $metadata['quiz_id'] = $certificate['quiz_id'];
        $metadata['quiz_title'] = $certificate['quiz_title'];
    }

    $stmt = $pdo->prepare("INSERT INTO user_certificates (user_id, certificate_id, verification_code, metadata) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $certificate_id, $verification_code, json_encode($metadata)]);

    logAuditEvent('certificate_awarded', $user_id, [
        'certificate_id' => $certificate_id,
        'certificate_title' => $certificate['title'],
        'verification_code' => $verification_code
    ]);

    // Send message to user about certificate
    $message_subject = "🎓 Congratulations! You earned a certificate";
    $message_content = "You have been awarded the certificate: " . $certificate['title'] . "\n\n" .
        "Verification Code: " . $verification_code . "\n\n" .
        "Visit your profile to view and download your certificate.";
    sendSystemMessage($user_id, $message_subject, $message_content);

    return $verification_code;
}

// Award badge automatically
function awardBadge($user_id, $badge_id) {
    global $pdo;

    // Check if already awarded
    $stmt = $pdo->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
    $stmt->execute([$user_id, $badge_id]);
    if ($stmt->fetch()) {
        return false; // Already awarded
    }

    // Get badge details for metadata
    $stmt = $pdo->prepare("SELECT b.*, co.title as course_title, q.title as quiz_title, l.title as lesson_title FROM badges b LEFT JOIN courses co ON b.course_id = co.id LEFT JOIN quizzes q ON b.quiz_id = q.id LEFT JOIN lessons l ON b.lesson_id = l.id WHERE b.id = ?");
    $stmt->execute([$badge_id]);
    $badge = $stmt->fetch();

    $metadata = [
        'awarded_at' => date('Y-m-d H:i:s'),
        'badge_name' => $badge['name']
    ];

    if ($badge['award_criteria'] === 'course_completion' && $badge['course_id']) {
        $metadata['course_id'] = $badge['course_id'];
        $metadata['course_title'] = $badge['course_title'];
    } elseif ($badge['award_criteria'] === 'quiz_passing' && $badge['quiz_id']) {
        $metadata['quiz_id'] = $badge['quiz_id'];
        $metadata['quiz_title'] = $badge['quiz_title'];
    } elseif ($badge['award_criteria'] === 'lesson_completion' && $badge['lesson_id']) {
        $metadata['lesson_id'] = $badge['lesson_id'];
        $metadata['lesson_title'] = $badge['lesson_title'];
    } elseif ($badge['award_criteria'] === 'streak') {
        $metadata['streak_days'] = $badge['streak_days'];
    }

    $stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id, metadata) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $badge_id, json_encode($metadata)]);

    logAuditEvent('badge_awarded', $user_id, [
        'badge_id' => $badge_id,
        'badge_name' => $badge['name']
    ]);

    // Send message to user about badge
    $message_subject = "🏆 You unlocked a badge!";
    $message_content = "Great job! You've earned the badge: " . $badge['name'] . "\n\n" .
        $badge['description'] . "\n\n" .
        "View all your badges on your profile.";
    sendSystemMessage($user_id, $message_subject, $message_content);

    return true;
}

// Check and award certificates/badges based on user actions
function checkAndAwardCredentials($user_id, $action_type, $target_id = null) {
    global $pdo;

    $awarded = [];

    // Get active certificates/badges that match the criteria
    if ($action_type === 'course_completed') {
        // Course completion certificates
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE award_criteria = 'course_completion' AND course_id = ? AND is_active = TRUE");
        $stmt->execute([$target_id]);
        $certificates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($certificates as $cert_id) {
            if (awardCertificate($user_id, $cert_id)) {
                $awarded['certificates'][] = $cert_id;
            }
        }

        // Course completion badges
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE award_criteria = 'course_completion' AND course_id = ? AND is_active = TRUE");
        $stmt->execute([$target_id]);
        $badges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($badges as $badge_id) {
            if (awardBadge($user_id, $badge_id)) {
                $awarded['badges'][] = $badge_id;
            }
        }

    } elseif ($action_type === 'quiz_passed') {
        // Quiz passing certificates
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE award_criteria = 'quiz_passing' AND quiz_id = ? AND is_active = TRUE");
        $stmt->execute([$target_id]);
        $certificates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($certificates as $cert_id) {
            // Check if user passed with required score
            $stmt = $pdo->prepare("SELECT passing_score FROM certificates WHERE id = ?");
            $stmt->execute([$cert_id]);
            $cert = $stmt->fetch();

            if (hasPassedQuiz($user_id, $target_id, $cert['passing_score'])) {
                if (awardCertificate($user_id, $cert_id)) {
                    $awarded['certificates'][] = $cert_id;
                }
            }
        }

        // Quiz passing badges
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE award_criteria = 'quiz_passing' AND quiz_id = ? AND is_active = TRUE");
        $stmt->execute([$target_id]);
        $badges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($badges as $badge_id) {
            // Check if user passed with required score
            $stmt = $pdo->prepare("SELECT passing_score FROM badges WHERE id = ?");
            $stmt->execute([$badge_id]);
            $badge = $stmt->fetch();

            if (hasPassedQuiz($user_id, $target_id, $badge['passing_score'])) {
                if (awardBadge($user_id, $badge_id)) {
                    $awarded['badges'][] = $badge_id;
                }
            }
        }

    } elseif ($action_type === 'lesson_completed') {
        // Lesson completion badges
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE award_criteria = 'lesson_completion' AND lesson_id = ? AND is_active = TRUE");
        $stmt->execute([$target_id]);
        $badges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($badges as $badge_id) {
            if (awardBadge($user_id, $badge_id)) {
                $awarded['badges'][] = $badge_id;
            }
        }

    } elseif ($action_type === 'daily_check') {
        // Check streak badges
        $current_streak = getLearningStreak($user_id);
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE award_criteria = 'streak' AND streak_days <= ? AND is_active = TRUE");
        $stmt->execute([$current_streak]);
        $badges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($badges as $badge_id) {
            if (awardBadge($user_id, $badge_id)) {
                $awarded['badges'][] = $badge_id;
            }
        }
    }

    return $awarded;
}

// Get user's certificates
function getUserCertificates($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT uc.*, c.title, c.description, c.template_html
        FROM user_certificates uc
        JOIN certificates c ON uc.certificate_id = c.id
        WHERE uc.user_id = ?
        ORDER BY uc.awarded_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's badges
function getUserBadges($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT ub.*, b.name, b.description, b.icon_path, b.color
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.awarded_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generate certificate PDF/HTML (basic HTML version for now)
function generateCertificateHTML($certificate_id, $user_id) {
    global $pdo;

    // Get certificate and user data
    $stmt = $pdo->prepare("
        SELECT uc.*, c.title, c.description, c.template_html, u.username
        FROM user_certificates uc
        JOIN certificates c ON uc.certificate_id = c.id
        JOIN users u ON uc.user_id = u.id
        WHERE uc.certificate_id = ? AND uc.user_id = ?
    ");
    $stmt->execute([$certificate_id, $user_id]);
    $data = $stmt->fetch();

    if (!$data) return null;

    $metadata = json_decode($data['metadata'], true);

    // Replace placeholders in template
    $html = $data['template_html'];
    $html = str_replace('{{student_name}}', htmlspecialchars($data['username']), $html);
    $html = str_replace('{{certificate_title}}', htmlspecialchars($data['title']), $html);
    $html = str_replace('{{completion_date}}', date('F j, Y', strtotime($metadata['awarded_at'])), $html);
    $html = str_replace('{{verification_code}}', $data['verification_code'], $html);

    // Add course/quiz info if available
    if (isset($metadata['course_title'])) {
        $html = str_replace('{{course_title}}', htmlspecialchars($metadata['course_title']), $html);
    }
    if (isset($metadata['quiz_title'])) {
        $html = str_replace('{{quiz_title}}', htmlspecialchars($metadata['quiz_title']), $html);
    }

    return $html;
}

// ===== MESSAGING SYSTEM =====

// Send a message to another user
function sendMessage($sender_id, $recipient_id, $subject, $content) {
    global $pdo;

    $subject = trim($subject);
    $content = trim($content);

    if (empty($subject) || empty($content)) {
        return ['success' => false, 'message' => 'Subject and message content are required.'];
    }

    if ($sender_id === $recipient_id) {
        return ['success' => false, 'message' => 'You cannot send a message to yourself.'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sender_id, $recipient_id, $subject, $content]);

        logAuditEvent('message_sent', $sender_id, [
            'recipient_id' => $recipient_id,
            'subject' => $subject
        ]);

        return ['success' => true, 'message_id' => (int)$pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Failed to send message.'];
    }
}

// Get user's inbox (messages received)
function getInbox($user_id, $limit = 50, $offset = 0) {
    global $pdo;

    $limit = (int)$limit;
    $offset = (int)$offset;

    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username, u.id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.recipient_id = ?
        ORDER BY m.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's sent messages
function getSentMessages($user_id, $limit = 50, $offset = 0) {
    global $pdo;

    $limit = (int)$limit;
    $offset = (int)$offset;

    $stmt = $pdo->prepare("
        SELECT m.*, u.username as recipient_username, u.id as recipient_id
        FROM messages m
        JOIN users u ON m.recipient_id = u.id
        WHERE m.sender_id = ?
        ORDER BY m.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get conversation between two users
function getConversation($user_id, $other_user_id, $limit = 100) {
    global $pdo;

    $limit = (int)$limit;

    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE WHEN m.sender_id = ? THEN u2.username ELSE u1.username END as other_username,
               CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as direction
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.recipient_id = u2.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $other_user_id, $other_user_id, $user_id]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Get message details
function getMessage($message_id, $user_id = null) {
    global $pdo;

    $sql = "
        SELECT m.*, u.username as sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ";
    $params = [$message_id];

    if ($user_id !== null) {
        $sql .= " AND (m.recipient_id = ? OR m.sender_id = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mark message as read
function markMessageAsRead($message_id, $user_id) {
    global $pdo;

    $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE id = ? AND recipient_id = ?");
    return $stmt->execute([$message_id, $user_id]);
}

// Get unread message count
function getUnreadMessageCount($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

// Get unread messages
function getUnreadMessages($user_id, $limit = 10) {
    global $pdo;

    $limit = (int)$limit;

    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.recipient_id = ? AND m.is_read = FALSE
        ORDER BY m.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Delete message
function deleteMessage($message_id, $user_id) {
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND (recipient_id = ? OR sender_id = ?)");
    return $stmt->execute([$message_id, $user_id, $user_id]);
}

// Get total messages count (inbox)
function getInboxCount($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

// Send system message (from system/admin to user)
function sendSystemMessage($recipient_id, $subject, $content) {
    global $pdo;

    // System messages come from the admin user (id=1)
    // You can change this to a different user if needed

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content) VALUES (1, ?, ?, ?)");
        $stmt->execute([$recipient_id, $subject, $content]);
        return ['success' => true, 'message_id' => (int)$pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Failed to send system message.'];
    }
}

// Get message recipients (for composer autocomplete/dropdown)
function getMessageRecipients($user_id, $search = '') {
    global $pdo;

    $search = '%' . $search . '%';
    $stmt = $pdo->prepare("
        SELECT id, username, email
        FROM users
        WHERE id != ? AND (username LIKE ? OR email LIKE ?)
        ORDER BY username
        LIMIT 20
    ");
    $stmt->execute([$user_id, $search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== NOTIFICATION MESSAGING SYSTEM =====

// Notify users of course announcement
function notifyCourseAnnouncement($course_id, $announcement_title, $announcement_content, $created_by_id) {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return false;
    }

    // Get all enrolled students (those with progress in the course)
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN lessons l ON up.lesson_id = l.id
        WHERE l.course_id = ? AND u.role = 'user'
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        return false;
    }

    $sender_id = $created_by_id;
    $subject = "📢 Course Announcement: " . $announcement_title;
    $content = "New announcement in " . $course['title'] . ":\n\n" . $announcement_content;

    $count = 0;
    foreach ($students as $student_id) {
        if (sendMessage($sender_id, $student_id, $subject, $content)['success']) {
            $count++;
        }
    }

    return $count > 0;
}

// Notify student of quiz result posted
function notifyQuizResultPosted($user_id, $quiz_id, $score, $max_score, $percentage, $passed) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT q.title, l.title as lesson_title, c.title as course_title
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        return false;
    }

    $status = $passed ? '✅ Passed' : '❌ Did not pass';
    $subject = "Quiz Result Posted: " . $quiz['title'];
    $content = "Your quiz result for \"" . $quiz['title'] . "\" in " . $quiz['course_title'] . " has been posted.\n\n" .
        "Score: $score / $max_score ($percentage%)\n" .
        "Status: $status\n\n" .
        "Visit your grades page to view more details.";

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify student of grade/lesson completion
function notifyLessonGraded($user_id, $lesson_id, $grade_info = []) {
    global $pdo;

    $lesson = getLesson($lesson_id);
    if (!$lesson) {
        return false;
    }

    $course = getCourse($lesson['course_id']);
    $subject = "📝 Grade Posted: " . $lesson['title'];
    $content = "Your grade for the lesson \"" . $lesson['title'] . "\" in " . $course['title'] . " has been posted.\n\n";

    if (!empty($grade_info)) {
        if (isset($grade_info['score']) && isset($grade_info['max_score'])) {
            $percentage = ($grade_info['max_score'] > 0) ? ($grade_info['score'] / $grade_info['max_score'] * 100) : 0;
            $content .= "Score: " . $grade_info['score'] . " / " . $grade_info['max_score'] . " (" . number_format($percentage, 1) . "%)\n";
        }
        if (isset($grade_info['feedback'])) {
            $content .= "\nInstructor Feedback:\n" . $grade_info['feedback'] . "\n";
        }
    }

    $content .= "\nVisit your grades page to view all your grades.";

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify student of assignment posted
function notifyAssignmentPosted($course_id, $assignment_title, $assignment_description, $due_date, $created_by_id) {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return false;
    }

    // Get all enrolled students
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN lessons l ON up.lesson_id = l.id
        WHERE l.course_id = ? AND u.role = 'user'
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        return false;
    }

    $sender_id = $created_by_id;
    $due_date_formatted = $due_date ? date('F j, Y', strtotime($due_date)) : 'Not specified';
    $subject = "📋 New Assignment: " . $assignment_title;
    $content = "A new assignment has been posted in " . $course['title'] . ".\n\n" .
        "Title: " . $assignment_title . "\n" .
        "Due Date: " . $due_date_formatted . "\n\n" .
        "Description:\n" . $assignment_description;

    $count = 0;
    foreach ($students as $student_id) {
        if (sendMessage($sender_id, $student_id, $subject, $content)['success']) {
            $count++;
        }
    }

    return $count > 0;
}

// Notify student of assignment feedback
function notifyAssignmentFeedback($user_id, $assignment_title, $feedback, $score = null, $max_score = null) {
    global $pdo;

    $subject = "📧 Feedback on Assignment: " . $assignment_title;
    $content = "You have received feedback on your assignment \"" . $assignment_title . "\".\n\n";

    if ($score !== null && $max_score !== null) {
        $percentage = ($max_score > 0) ? ($score / $max_score * 100) : 0;
        $content .= "Score: $score / $max_score (" . number_format($percentage, 1) . "%)\n\n";
    }

    $content .= "Feedback:\n" . $feedback . "\n\n" .
        "Please review the feedback and resubmit if necessary.";

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify users of course enrollment changes
function notifyCourseEnrollment($user_id, $course_id, $action = 'enrolled') {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return false;
    }

    if ($action === 'enrolled') {
        $subject = "✅ Welcome to: " . $course['title'];
        $content = "You have been enrolled in the course \"" . $course['title'] . "\".\n\n" .
            "Description:\n" . $course['description'] . "\n\n" .
            "Start learning now!";
    } elseif ($action === 'unenrolled') {
        $subject = "🚪 Unenrolled from: " . $course['title'];
        $content = "You have been unenrolled from the course \"" . $course['title'] . "\".";
    } else {
        return false;
    }

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify instructor of student submission (for assignments)
function notifyStudentSubmission($instructor_id, $student_id, $assignment_title, $submission_details = '') {
    global $pdo;

    $student = getUser($student_id);
    $subject = "📤 New Submission: " . $assignment_title;
    $content = "Student " . $student['username'] . " has submitted \"" . $assignment_title . "\".\n\n";

    if (!empty($submission_details)) {
        $content .= "Submission Details:\n" . $submission_details . "\n\n";
    }

    $content .= "Please review and provide feedback.";

    return sendMessage(1, $instructor_id, $subject, $content)['success'];
}

// Notify student of password reset
function notifyPasswordReset($user_id) {
    global $pdo;

    $subject = "🔐 Your password has been changed";
    $content = "Your Learning Platform password has been successfully changed.\n\n" .
        "If you did not make this change, please contact support immediately.\n\n" .
        "For security, all your other sessions have been logged out.";

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify user of course deadline approaching
function notifyCourseDeadlineApproaching($user_id, $course_id, $days_remaining) {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return false;
    }

    $subject = "⏰ Reminder: Course deadline approaching";
    $content = "The course \"" . $course['title'] . "\" has a deadline in $days_remaining days.\n\n" .
        "Please complete the remaining lessons and quizzes to earn your certificate.\n\n" .
        "Visit your course page now to continue learning.";

    return sendSystemMessage($user_id, $subject, $content)['success'];
}

// Notify instructor of student at-risk (low grades/progress)
function notifyStudentAtRisk($instructor_id, $student_id, $course_id, $risk_reason) {
    global $pdo;

    $student = getUser($student_id);
    $course = getCourse($course_id);

    $subject = "⚠️ Student At Risk: " . $student['username'];
    $content = "Student " . $student['username'] . " in course \"" . $course['title'] . "\" is at risk.\n\n" .
        "Reason: " . $risk_reason . "\n\n" .
        "Please consider reaching out to the student to provide additional support.";

    return sendMessage(1, $instructor_id, $subject, $content)['success'];
}

// Send bulk message to all course students
function sendBulkMessageToStudents($course_id, $subject, $content, $sender_id) {
    global $pdo;

    $course = getCourse($course_id);
    if (!$course) {
        return ['success' => false, 'count' => 0];
    }

    // Get all enrolled students
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id
        FROM users u
        LEFT JOIN user_progress up ON u.id = up.user_id
        LEFT JOIN lessons l ON up.lesson_id = l.id
        WHERE l.course_id = ? AND u.role = 'user'
    ");
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($students)) {
        return ['success' => false, 'count' => 0];
    }

    $count = 0;
    foreach ($students as $student_id) {
        if (sendMessage($sender_id, $student_id, $subject, $content)['success']) {
            $count++;
        }
    }

    return ['success' => $count > 0, 'count' => $count];
}

?>
