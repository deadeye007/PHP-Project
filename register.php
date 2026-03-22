<?php
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Basic validation
    if (strlen($username) < 3 || strlen($password) < 6) {
        $error = 'Username must be at least 3 characters, password at least 6.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Registration failed: Username or email already exists.';
        }
    }
}

$title = 'Register';
$content = '<h2>Register</h2>';
if (isset($error)) {
    $content .= '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
}
$content .= '
    <form method="post">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required maxlength="50">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="100">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p class="mt-3">Already have an account? <a href="login.php">Login here</a>.</p>
';

include 'includes/header.php';
?>