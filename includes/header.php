<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Learning Platform'; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <header class="bg-primary text-white p-3">
        <div class="container">
            <h1><a href="../index.php" class="text-white text-decoration-none">Learning Platform</a></h1>
            <nav>
                <a href="../index.php" class="text-white me-3">Home</a>
                <a href="../courses.php" class="text-white me-3">Courses</a>
                <?php if (isLoggedIn()): ?>
                    <?php 
                    // Validate session and IP on each request
                    if (!validateUserSession() || !validateSessionIP()) {
                        logAuditEvent('session_invalidated', $_SESSION['user_id'], ['reason' => 'ip_change_or_invalid_session']);
                        session_destroy();
                        header('Location: login.php?error=session_expired');
                        exit;
                    }
                    regenerateSession(); // Regenerate session periodically 
                    ?>
                    <?php if (isAdmin()): ?>
                        <a href="../admin/index.php" class="text-white me-3">Admin</a>
                    <?php endif; ?>
                    <a href="../profile.php" class="text-white me-3">Profile</a>
                    <a href="../logout.php" class="text-white">Logout</a>
                <?php else: ?>
                    <a href="../login.php" class="text-white me-3">Login</a>
                    <a href="../register.php" class="text-white">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container my-4">
        <?php echo $content ?? ''; ?>
    </main>
    <footer class="bg-dark text-white text-center p-3">
        <p>&copy; 2026 Learning Platform. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>