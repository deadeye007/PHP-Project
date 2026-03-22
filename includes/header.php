<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?php echo $title ?? 'Learning Platform'; ?></title>
    <script>
        (function () {
            var storedTheme = null;
            try {
                storedTheme = localStorage.getItem('theme');
            } catch (error) {
                storedTheme = null;
            }

            var preferredTheme = storedTheme;
            if (preferredTheme !== 'light' && preferredTheme !== 'dark') {
                preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            var storedTextSize = null;
            try {
                storedTextSize = localStorage.getItem('text_size');
            } catch (error) {
                storedTextSize = null;
            }

            if (storedTextSize !== 'large' && storedTextSize !== 'x-large') {
                storedTextSize = 'default';
            }

            document.documentElement.setAttribute('data-bs-theme', preferredTheme);
            document.documentElement.setAttribute('data-text-size', storedTextSize);
        }());
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <header class="bg-primary text-white p-3">
        <div class="container">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h1><a href="../index.php" class="text-white text-decoration-none">Learning Platform</a></h1>
                    <nav aria-label="Primary">
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
                <div class="text-size-control">
                    <label for="text-size-selector" class="form-label mb-1">Text Size</label>
                    <select id="text-size-selector" class="form-select form-select-sm" aria-label="Text size">
                        <option value="default">Default</option>
                        <option value="large">Large</option>
                        <option value="x-large">Extra Large</option>
                    </select>
                </div>
            </div>
        </div>
    </header>
    <main id="main-content" class="container my-4" tabindex="-1">
        <?php echo $content ?? ''; ?>
    </main>
    <footer class="bg-dark text-white text-center p-3">
        <p>&copy; 2026 Learning Platform. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>
