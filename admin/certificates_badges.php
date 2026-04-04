<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$title = 'Certificates & Badges Management';
include '../includes/header.php';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_certificate'])) {
        // Create certificate
        $title = sanitizeInput($_POST['certificate_title']);
        $description = sanitizeInput($_POST['certificate_description']);
        $template_html = $_POST['template_html'];
        $award_criteria = $_POST['award_criteria'];
        $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
        $quiz_id = !empty($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : null;
        $passing_score = !empty($_POST['passing_score']) ? (int)$_POST['passing_score'] : null;

        $stmt = $pdo->prepare("INSERT INTO certificates (title, description, template_html, award_criteria, course_id, quiz_id, passing_score) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $template_html, $award_criteria, $course_id, $quiz_id, $passing_score])) {
            $message = 'Certificate created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error creating certificate.';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['create_badge'])) {
        // Create badge
        $name = sanitizeInput($_POST['badge_name']);
        $description = sanitizeInput($_POST['badge_description']);
        $award_criteria = $_POST['badge_award_criteria'];
        $course_id = !empty($_POST['badge_course_id']) ? (int)$_POST['badge_course_id'] : null;
        $quiz_id = !empty($_POST['badge_quiz_id']) ? (int)$_POST['badge_quiz_id'] : null;
        $lesson_id = !empty($_POST['badge_lesson_id']) ? (int)$_POST['badge_lesson_id'] : null;
        $passing_score = !empty($_POST['badge_passing_score']) ? (int)$_POST['badge_passing_score'] : null;
        $streak_days = !empty($_POST['streak_days']) ? (int)$_POST['streak_days'] : null;

        $stmt = $pdo->prepare("INSERT INTO badges (name, description, award_criteria, course_id, quiz_id, lesson_id, passing_score, streak_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $description, $award_criteria, $course_id, $quiz_id, $lesson_id, $passing_score, $streak_days])) {
            $message = 'Badge created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error creating badge.';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['award_certificate'])) {
        // Award certificate manually
        $user_id = (int)$_POST['user_id'];
        $certificate_id = (int)$_POST['certificate_id'];
        $verification_code = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT IGNORE INTO user_certificates (user_id, certificate_id, verification_code) VALUES (?, ?, ?)");
        if ($stmt->execute([$user_id, $certificate_id, $verification_code])) {
            $message = 'Certificate awarded successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error awarding certificate.';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['award_badge'])) {
        // Award badge manually
        $user_id = (int)$_POST['badge_user_id'];
        $badge_id = (int)$_POST['badge_id'];

        $stmt = $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)");
        if ($stmt->execute([$user_id, $badge_id])) {
            $message = 'Badge awarded successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error awarding badge.';
            $messageType = 'danger';
        }
    }
}

// Get all certificates
$certificates = $pdo->query("SELECT c.*, co.title as course_title, q.title as quiz_title FROM certificates c LEFT JOIN courses co ON c.course_id = co.id LEFT JOIN quizzes q ON c.quiz_id = q.id ORDER BY c.created_at DESC")->fetchAll();

// Get all badges
$badges = $pdo->query("SELECT b.*, co.title as course_title, q.title as quiz_title, l.title as lesson_title FROM badges b LEFT JOIN courses co ON b.course_id = co.id LEFT JOIN quizzes q ON b.quiz_id = q.id LEFT JOIN lessons l ON b.lesson_id = l.id ORDER BY b.created_at DESC")->fetchAll();

// Get users for manual awarding
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY username")->fetchAll();

// Get courses for dropdowns
$courses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();

// Get quizzes for dropdowns
$quizzes = $pdo->query("SELECT q.id, q.title, c.title as course_title FROM quizzes q JOIN lessons l ON q.lesson_id = l.id JOIN courses c ON l.course_id = c.id ORDER BY c.title, q.title")->fetchAll();

// Get lessons for dropdowns
$lessons = $pdo->query("SELECT l.id, l.title, c.title as course_title FROM lessons l JOIN courses c ON l.course_id = c.id ORDER BY c.title, l.title")->fetchAll();
?>

<main class="container my-4">
    <div class="row">
        <div class="col-12">
            <h1>Certificates & Badges Management</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Certificates Section -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Certificates</h2>
                        </div>
                        <div class="card-body">
                            <!-- Create Certificate Form -->
                            <form method="post" class="mb-4">
                                <h3 class="h6">Create New Certificate</h3>
                                <div class="mb-3">
                                    <label for="certificate_title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="certificate_title" name="certificate_title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="certificate_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="certificate_description" name="certificate_description" rows="2"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="award_criteria" class="form-label">Award Criteria</label>
                                    <select class="form-select" id="award_criteria" name="award_criteria" required>
                                        <option value="course_completion">Course Completion</option>
                                        <option value="quiz_passing">Quiz Passing</option>
                                        <option value="manual">Manual Award</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="course_select" style="display: none;">
                                    <label for="course_id" class="form-label">Course</label>
                                    <select class="form-select" id="course_id" name="course_id">
                                        <option value="">Select Course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="quiz_select" style="display: none;">
                                    <label for="quiz_id" class="form-label">Quiz</label>
                                    <select class="form-select" id="quiz_id" name="quiz_id">
                                        <option value="">Select Quiz</option>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <option value="<?php echo $quiz['id']; ?>"><?php echo htmlspecialchars($quiz['course_title'] . ' - ' . $quiz['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="passing_score" style="display: none;">
                                    <label for="passing_score" class="form-label">Minimum Passing Score (%)</label>
                                    <input type="number" class="form-control" id="passing_score_input" name="passing_score" min="0" max="100" value="70">
                                </div>
                                <div class="mb-3">
                                    <label for="template_html" class="form-label">Certificate Template HTML</label>
                                    <textarea class="form-control" id="template_html" name="template_html" rows="10" placeholder="Use {{student_name}}, {{course_title}}, {{completion_date}}, {{certificate_title}} as placeholders"><?php echo htmlspecialchars('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
    <h1 style="color: #333; margin-bottom: 20px;">Certificate of Completion</h1>
    <p style="font-size: 18px; margin: 20px 0;">This certifies that</p>
    <h2 style="color: #007bff; margin: 30px 0;">{{student_name}}</h2>
    <p style="font-size: 16px; margin: 20px 0;">has successfully completed</p>
    <h3 style="margin: 30px 0;">{{course_title}}</h3>
    <p style="font-size: 14px; margin: 40px 0;">Awarded on {{completion_date}}</p>
    <div style="margin-top: 50px;">
        <p>Verification Code: {{verification_code}}</p>
    </div>
</div>'); ?></textarea>
                                </div>
                                <button type="submit" name="create_certificate" class="btn btn-primary">Create Certificate</button>
                            </form>

                            <!-- Existing Certificates -->
                            <h3 class="h6">Existing Certificates</h3>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Criteria</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($certificates as $cert): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cert['title']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($cert['award_criteria'] === 'course_completion') {
                                                        echo 'Course: ' . htmlspecialchars($cert['course_title'] ?? 'N/A');
                                                    } elseif ($cert['award_criteria'] === 'quiz_passing') {
                                                        echo 'Quiz: ' . htmlspecialchars($cert['quiz_title'] ?? 'N/A');
                                                    } else {
                                                        echo 'Manual';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary award-cert-btn" data-cert-id="<?php echo $cert['id']; ?>" data-cert-title="<?php echo htmlspecialchars($cert['title']); ?>">Award</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Badges Section -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2 class="h5 mb-0">Badges</h2>
                        </div>
                        <div class="card-body">
                            <!-- Create Badge Form -->
                            <form method="post" class="mb-4">
                                <h3 class="h6">Create New Badge</h3>
                                <div class="mb-3">
                                    <label for="badge_name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="badge_name" name="badge_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="badge_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="badge_description" name="badge_description" rows="2"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="badge_award_criteria" class="form-label">Award Criteria</label>
                                    <select class="form-select" id="badge_award_criteria" name="badge_award_criteria" required>
                                        <option value="course_completion">Course Completion</option>
                                        <option value="quiz_passing">Quiz Passing</option>
                                        <option value="lesson_completion">Lesson Completion</option>
                                        <option value="streak">Learning Streak</option>
                                        <option value="manual">Manual Award</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="badge_course_select" style="display: none;">
                                    <label for="badge_course_id" class="form-label">Course</label>
                                    <select class="form-select" id="badge_course_id" name="badge_course_id">
                                        <option value="">Select Course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="badge_quiz_select" style="display: none;">
                                    <label for="badge_quiz_id" class="form-label">Quiz</label>
                                    <select class="form-select" id="badge_quiz_id" name="badge_quiz_id">
                                        <option value="">Select Quiz</option>
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <option value="<?php echo $quiz['id']; ?>"><?php echo htmlspecialchars($quiz['course_title'] . ' - ' . $quiz['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="badge_lesson_select" style="display: none;">
                                    <label for="badge_lesson_id" class="form-label">Lesson</label>
                                    <select class="form-select" id="badge_lesson_id" name="badge_lesson_id">
                                        <option value="">Select Lesson</option>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <option value="<?php echo $lesson['id']; ?>"><?php echo htmlspecialchars($lesson['course_title'] . ' - ' . $lesson['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3" id="badge_passing_score" style="display: none;">
                                    <label for="badge_passing_score" class="form-label">Minimum Passing Score (%)</label>
                                    <input type="number" class="form-control" id="badge_passing_score_input" name="badge_passing_score" min="0" max="100" value="70">
                                </div>
                                <div class="mb-3" id="streak_days_select" style="display: none;">
                                    <label for="streak_days" class="form-label">Consecutive Days</label>
                                    <input type="number" class="form-control" id="streak_days" name="streak_days" min="1" value="7">
                                </div>
                                <button type="submit" name="create_badge" class="btn btn-primary">Create Badge</button>
                            </form>

                            <!-- Existing Badges -->
                            <h3 class="h6">Existing Badges</h3>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Criteria</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($badges as $badge): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($badge['name']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($badge['award_criteria'] === 'course_completion') {
                                                        echo 'Course: ' . htmlspecialchars($badge['course_title'] ?? 'N/A');
                                                    } elseif ($badge['award_criteria'] === 'quiz_passing') {
                                                        echo 'Quiz: ' . htmlspecialchars($badge['quiz_title'] ?? 'N/A');
                                                    } elseif ($badge['award_criteria'] === 'lesson_completion') {
                                                        echo 'Lesson: ' . htmlspecialchars($badge['lesson_title'] ?? 'N/A');
                                                    } elseif ($badge['award_criteria'] === 'streak') {
                                                        echo $badge['streak_days'] . ' day streak';
                                                    } else {
                                                        echo 'Manual';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary award-badge-btn" data-badge-id="<?php echo $badge['id']; ?>" data-badge-name="<?php echo htmlspecialchars($badge['name']); ?>">Award</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Awarding Modals -->
            <!-- Certificate Award Modal -->
            <div class="modal fade" id="awardCertificateModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Award Certificate</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <p>Awarding certificate: <strong id="cert-title-display"></strong></p>
                                <div class="mb-3">
                                    <label for="user_id" class="form-label">Select User</label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">Choose a user...</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" id="certificate_id" name="certificate_id">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="award_certificate" class="btn btn-primary">Award Certificate</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Badge Award Modal -->
            <div class="modal fade" id="awardBadgeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Award Badge</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <p>Awarding badge: <strong id="badge-name-display"></strong></p>
                                <div class="mb-3">
                                    <label for="badge_user_id" class="form-label">Select User</label>
                                    <select class="form-select" id="badge_user_id" name="badge_user_id" required>
                                        <option value="">Choose a user...</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" id="badge_id" name="badge_id">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="award_badge" class="btn btn-primary">Award Badge</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Show/hide form fields based on award criteria
document.getElementById('award_criteria').addEventListener('change', function() {
    const criteria = this.value;
    document.getElementById('course_select').style.display = criteria === 'course_completion' ? 'block' : 'none';
    document.getElementById('quiz_select').style.display = criteria === 'quiz_passing' ? 'block' : 'none';
    document.getElementById('passing_score').style.display = criteria === 'quiz_passing' ? 'block' : 'none';
});

document.getElementById('badge_award_criteria').addEventListener('change', function() {
    const criteria = this.value;
    document.getElementById('badge_course_select').style.display = criteria === 'course_completion' ? 'block' : 'none';
    document.getElementById('badge_quiz_select').style.display = criteria === 'quiz_passing' ? 'block' : 'none';
    document.getElementById('badge_lesson_select').style.display = criteria === 'lesson_completion' ? 'block' : 'none';
    document.getElementById('badge_passing_score').style.display = criteria === 'quiz_passing' ? 'block' : 'none';
    document.getElementById('streak_days_select').style.display = criteria === 'streak' ? 'block' : 'none';
});

// Modal handling
document.querySelectorAll('.award-cert-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const certId = this.dataset.certId;
        const certTitle = this.dataset.certTitle;
        document.getElementById('certificate_id').value = certId;
        document.getElementById('cert-title-display').textContent = certTitle;
        new bootstrap.Modal(document.getElementById('awardCertificateModal')).show();
    });
});

document.querySelectorAll('.award-badge-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const badgeId = this.dataset.badgeId;
        const badgeName = this.dataset.badgeName;
        document.getElementById('badge_id').value = badgeId;
        document.getElementById('badge-name-display').textContent = badgeName;
        new bootstrap.Modal(document.getElementById('awardBadgeModal')).show();
    });
});
</script>

<?php include '../includes/header.php'; ?></content>
<parameter name="filePath">/Users/asturm/Projects/PHP Project/admin/certificates.php