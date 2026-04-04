<?php
session_start();
require_once '../includes/env.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

// Check admin access
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_course_announcement') {
        $course_id = (int)($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!$course_id || !$title || !$content) {
            $error = 'All fields are required';
        } else {
            $course = getCourse($course_id);
            if (!$course) {
                $error = 'Course not found';
            } else {
                if (notifyCourseAnnouncement($course_id, $title, $content, $_SESSION['user_id'])) {
                    $message = 'Announcement sent to all students in ' . htmlspecialchars($course['title']);
                    // Log to audit log
                    logAuditEvent('send_course_announcement', $_SESSION['user_id'], ['course_id' => $course_id, 'title' => $title]);
                } else {
                    $error = 'No students enrolled in this course or sending failed';
                }
            }
        }
    } elseif ($action === 'send_platform_announcement') {
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target_role = $_POST['target_role'] ?? 'all';

        if (!$subject || !$content) {
            $error = 'Subject and content are required';
        } else {
            // Get target users
            if ($target_role === 'all') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id != 1 ORDER BY username");
            } elseif ($target_role === 'students') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'user' ORDER BY username");
            } elseif ($target_role === 'instructors') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'instructor' ORDER BY username");
            } else {
                $error = 'Invalid target role';
                $stmt = null;
            }

            if ($stmt) {
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($users)) {
                    $error = 'No users found';
                } else {
                    $count = 0;
                    foreach ($users as $user_id) {
                        if (sendMessage($_SESSION['user_id'], $user_id, $subject, $content)['success']) {
                            $count++;
                        }
                    }
                    $message = "Platform announcement sent to $count users";
                    logAuditEvent('send_platform_announcement', $_SESSION['user_id'], ['target_role' => $target_role, 'recipient_count' => $count]);
                }
            }
        }
    } elseif ($action === 'send_grade_notification') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $lesson_id = (int)($_POST['lesson_id'] ?? 0);
        $score = (float)($_POST['score'] ?? 0);
        $max_score = (float)($_POST['max_score'] ?? 100);

        if (!$user_id || !$lesson_id || $score < 0 || $max_score <= 0) {
            $error = 'Invalid input';
        } else {
            $user = getUser($user_id);
            $lesson = getLesson($lesson_id);

            if (!$user || !$lesson) {
                $error = 'User or lesson not found';
            } else {
                $grade_info = [
                    'score' => $score,
                    'max_score' => $max_score,
                ];
                if (notifyLessonGraded($user_id, $lesson_id, $grade_info)) {
                    $message = 'Grade notification sent to ' . htmlspecialchars($user['username']);
                    logAuditEvent('send_grade_notification', $_SESSION['user_id'], ['student_id' => $user_id, 'lesson_id' => $lesson_id, 'score' => $score, 'max_score' => $max_score]);
                } else {
                    $error = 'Failed to send notification';
                }
            }
        }
    }
}

// Get all courses
$stmt = $pdo->prepare("SELECT id, title FROM courses ORDER BY title");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all lessons
$stmt = $pdo->prepare("SELECT id, title, course_id FROM lessons ORDER BY title");
$stmt->execute();
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'user' ORDER BY username");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements & Notifications - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .nav-tabs .nav-link {
            cursor: pointer;
            color: var(--text-secondary);
            border: 1px solid transparent;
        }
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-4">
        <h1 class="mb-4">📢 Announcements & Notifications</h1>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" href="#course-announcement" role="tab" onclick="showTab('course-announcement')">
                    📚 Course Announcement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#platform-announcement" role="tab" onclick="showTab('platform-announcement')">
                    🌐 Platform Announcement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#grade-notification" role="tab" onclick="showTab('grade-notification')">
                    📝 Post Grade
                </a>
            </li>
        </ul>

        <!-- Tab 1: Course Announcement -->
        <div id="course-announcement" class="tab-pane active">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Send Course Announcement</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send a message to all students enrolled in a course.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_course_announcement">

                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course</label>
                            <select name="course_id" id="course_id" class="form-select" required>
                                <option value="">-- Select a course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Announcement Title</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="e.g., Important Update" required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Message Content</label>
                            <textarea name="content" id="content" class="form-control" rows="5" placeholder="Write your announcement..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Announcement</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 2: Platform Announcement -->
        <div id="platform-announcement" class="tab-pane">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Send Platform-Wide Announcement</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send a message to specific user groups across the entire platform.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_platform_announcement">

                        <div class="mb-3">
                            <label for="target_role" class="form-label">Send To</label>
                            <select name="target_role" id="target_role" class="form-select" required>
                                <option value="all">All Users</option>
                                <option value="students">All Students</option>
                                <option value="instructors">All Instructors</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" name="subject" id="subject" class="form-control" placeholder="Announcement subject" required>
                        </div>

                        <div class="mb-3">
                            <label for="content_platform" class="form-label">Message Content</label>
                            <textarea name="content" id="content_platform" class="form-control" rows="5" placeholder="Write your platform announcement..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Platform Announcement</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 3: Grade Notification -->
        <div id="grade-notification" class="tab-pane">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Notify Student of Grade Posted</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Send a notification when you post a grade for a lesson (requires separate grading in gradebook).</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="send_grade_notification">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="user_id" class="form-label">Student</label>
                                    <select name="user_id" id="user_id" class="form-select" required>
                                        <option value="">-- Select a student --</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['username']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lesson_id" class="form-label">Lesson</label>
                                    <select name="lesson_id" id="lesson_id" class="form-select" required>
                                        <option value="">-- Select a lesson --</option>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <option value="<?php echo $lesson['id']; ?>">
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="score" class="form-label">Score</label>
                                    <input type="number" name="score" id="score" class="form-control" step="0.01" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_score" class="form-label">Max Score</label>
                                    <input type="number" name="max_score" id="max_score" class="form-control" step="0.01" value="100" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Send Grade Notification</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabId).classList.add('active');

            // Add active to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
