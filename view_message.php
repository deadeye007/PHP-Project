<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$message_id) {
    header('Location: inbox.php');
    exit;
}

$message = getMessage($message_id, $user_id);

if (!$message) {
    header('Location: inbox.php');
    exit;
}

// Mark as read if recipient
if ($message['recipient_id'] === $user_id && !$message['is_read']) {
    markMessageAsRead($message_id, $user_id);
    $message['is_read'] = true;
}

$title = 'Message: ' . htmlspecialchars($message['subject']);

// Get conversation
$other_user_id = $message['sender_id'] === $user_id ? $message['recipient_id'] : $message['sender_id'];
$other_user = getUser($other_user_id);
$conversation = getConversation($user_id, $other_user_id);

include 'includes/header.php';
?>

<main class="container my-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo htmlspecialchars($message['subject']); ?></h1>
                <div>
                    <a href="send_message.php?reply_to=<?php echo $other_user_id; ?>" class="btn btn-primary">Reply</a>
                    <a href="inbox.php" class="btn btn-outline-secondary">Back to Inbox</a>
                </div>
            </div>

            <!-- Conversation Thread -->
            <div class="conversation-thread">
                <?php foreach ($conversation as $msg): ?>
                    <?php
                    $is_sent = $msg['sender_id'] === $user_id;
                    $msg_user = $is_sent ? 'admin' : $other_user['username'];
                    ?>
                    <div class="card mb-3 <?php echo $is_sent ? 'bg-light' : ''; ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between">
                                <strong>
                                    <?php echo htmlspecialchars($msg_user); ?>
                                    <span class="badge <?php echo $is_sent ? 'bg-success' : 'bg-primary'; ?> ms-2">
                                        <?php echo $is_sent ? 'You' : 'Them'; ?>
                                    </span>
                                </strong>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></small>
                            </div>
                            <?php if ($msg['subject']): ?>
                                <small class="text-muted d-block">Subject: <?php echo htmlspecialchars($msg['subject']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(htmlspecialchars($msg['content'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Form -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Reply to <?php echo htmlspecialchars($other_user['username']); ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="send_message.php">
                        <input type="hidden" name="recipient_id" value="<?php echo $other_user_id; ?>">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required value="Re: <?php echo htmlspecialchars($message['subject']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Message</label>
                            <textarea class="form-control" id="content" name="content" rows="6" required placeholder="Type your reply..."></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">Send Reply</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/header.php'; ?>