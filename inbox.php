<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$title = 'Messages';

// Handle message actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = (int)$_POST['message_id'];
    if (deleteMessage($message_id, $user_id)) {
        $message = 'Message deleted.';
        $messageType = 'success';
    } else {
        $message = 'Failed to delete message.';
        $messageType = 'danger';
    }
}

// Get current tab from URL
$tab = $_GET['tab'] ?? 'inbox';
if (!in_array($tab, ['inbox', 'sent'])) {
    $tab = 'inbox';
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get messages
if ($tab === 'inbox') {
    $messages = getInbox($user_id, $limit, $offset);
    $total = getInboxCount($user_id);
} else {
    $messages = getSentMessages($user_id, $limit, $offset);
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
    $stmt->execute([$user_id]);
    $total = (int)$stmt->fetchColumn();
}

$total_pages = ceil($total / $limit);

include 'includes/header.php';
?>

<main class="container my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Messages</h1>
                <a href="send_message.php" class="btn btn-primary">New Message</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $tab === 'inbox' ? 'active' : ''; ?>" href="?tab=inbox" role="tab">
                        Inbox
                        <?php
                        $unread_count = getUnreadMessageCount($user_id);
                        if ($unread_count > 0) {
                            echo '<span class="badge bg-danger ms-2">' . $unread_count . '</span>';
                        }
                        ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $tab === 'sent' ? 'active' : ''; ?>" href="?tab=sent" role="tab">Sent</a>
                </li>
            </ul>

            <!-- Messages List -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>From/To</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr class="<?php echo $tab === 'inbox' && !$msg['is_read'] ? 'table-light' : ''; ?>">
                                        <td>
                                            <strong>
                                                <?php
                                                if ($tab === 'inbox') {
                                                    echo '<a href="send_message.php?reply_to=' . htmlspecialchars($msg['sender_id']) . '">' . htmlspecialchars($msg['sender_username']) . '</a>';
                                                } else {
                                                    echo htmlspecialchars($msg['recipient_username']);
                                                }
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <a href="view_message.php?id=<?php echo $msg['id']; ?>" class="text-decoration-none">
                                                <?php
                                                if ($tab === 'inbox' && !$msg['is_read']) {
                                                    echo '<strong>';
                                                }
                                                echo htmlspecialchars($msg['subject']);
                                                if ($tab === 'inbox' && !$msg['is_read']) {
                                                    echo '</strong>';
                                                }
                                                ?>
                                            </a>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($tab === 'inbox'): ?>
                                                <?php if ($msg['is_read']): ?>
                                                    <span class="badge bg-success">Read</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Unread</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-info">Sent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" name="delete_message" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No messages yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=1">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $total_pages; ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/header.php'; ?>