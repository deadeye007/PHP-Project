<?php
require_once 'includes/functions.php';

// Log logout event
if (isLoggedIn()) {
    logAuditEvent('logout', $_SESSION['user_id']);
    
    // Remove session from database
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
}

// Regenerate session ID before destroying to prevent session fixation
session_regenerate_id(true);
session_destroy();
header('Location: index.php');
?>