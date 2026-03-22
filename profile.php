<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUser($_SESSION['user_id']);
$title = 'Profile';
$content = '<h2>Your Profile</h2>';
$content .= '<p>Username: ' . htmlspecialchars($user['username']) . '</p>';
$content .= '<p>Email: ' . htmlspecialchars($user['email']) . '</p>';

// Add progress summary
global $pdo;
$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM user_progress WHERE user_id = ? AND completed = TRUE");
$stmt->execute([$_SESSION['user_id']]);
$completed = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
$content .= '<p>Lessons Completed: ' . $completed . '</p>';

include 'includes/header.php';
?>