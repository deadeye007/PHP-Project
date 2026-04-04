<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$title = 'Send Message';
$message = '';
$messageType = '';
$reply_to_id = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : null;
$reply_to_user = null;

if ($reply_to_id) {
    $reply_to_user = getUser($reply_to_id);
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = (int)$_POST['recipient_id'];
    $subject = sanitizeInput($_POST['subject']);
    $content = sanitizeInput($_POST['content']);

    $result = sendMessage($user_id, $recipient_id, $subject, $content);

    if ($result['success']) {
        $message = 'Message sent successfully!';
        $messageType = 'success';
        // Clear form
        $_POST = [];
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

include 'includes/header.php';
?>

<main class="container my-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1>Send Message</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="recipient_id" class="form-label">To</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                <option value="">Select recipient...</option>
                                <?php
                                $recipients = getMessageRecipients($user_id);
                                foreach ($recipients as $recipient) {
                                    $selected = ($reply_to_user && $recipient['id'] === $reply_to_user['id']) ? 'selected' : '';
                                    echo '<option value="' . $recipient['id'] . '" ' . $selected . '>' . htmlspecialchars($recipient['username']) . ' (' . htmlspecialchars($recipient['email']) . ')</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required placeholder="Message subject">
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Message</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required placeholder="Type your message here..."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                            <a href="inbox.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/header.php'; ?>