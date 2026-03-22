<?php
require_once 'security.php';

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCourses() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, title, description FROM courses ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCourse($course_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, title, description FROM courses WHERE id = ?");
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
    $stmt = $pdo->prepare("SELECT id, title, content FROM lessons WHERE id = ?");
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
?>