<?php

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Check if thread ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid thread ID";
    header("Location: messages.php");
    exit;
}

$threadId = (int)$_GET['id'];
 
// Verify thread exists and user is a participant
$stmt = $pdo->prepare("
    SELECT mt.*, tp.is_read
    FROM message_threads mt
    JOIN thread_participants tp ON mt.thread_id = tp.thread_id
    WHERE mt.thread_id = ? AND tp.user_id = ?
");
$stmt->execute([$threadId, $userId]);
$thread = $stmt->fetch();

if (!$thread) {
    $_SESSION['error'] = "Thread not found or you don't have permission to view it";
    header("Location: messages.php");
    exit;
}

// Get the other participant (landlord)
$stmt = $pdo->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.role
    FROM thread_participants tp
    JOIN users u ON tp.user_id = u.user_id
    WHERE tp.thread_id = ? AND tp.user_id != ?
");
$stmt->execute([$threadId, $userId]);
$otherParticipant = $stmt->fetch();

// Get messages in this thread
$stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.role
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.thread_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$threadId]);
$messages = $stmt->fetchAll();

// Mark thread as read
if (!$thread['is_read']) {
    $stmt = $pdo->prepare("
        UPDATE thread_participants
        SET is_read = 1
        WHERE thread_id = ? AND user_id = ?
    ");
    $stmt->execute([$threadId, $userId]);
}

// Process reply form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageText = trim($_POST['message']);
    
    if (!empty($messageText)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
           // Add message to thread
$stmt = $pdo->prepare("
INSERT INTO messages (
    thread_id, sender_id, recipient_id, subject, message, created_at
) VALUES (
    :threadId, :senderId, :recipientId, :subject, :message, NOW()
)
");

$stmt->execute([
'threadId' => $threadId,
'senderId' => $userId,
'recipientId' => $otherParticipant['user_id'],
'subject' => $thread['subject'],  // Use the thread's subject for the reply
'message' => $messageText
]);

            
            // Update thread timestamp
            $stmt = $pdo->prepare("
                UPDATE message_threads
                SET updated_at = NOW()
                WHERE thread_id = ?
            ");
            $stmt->execute([$threadId]);
            
            // Mark as unread for other participant
            $stmt = $pdo->prepare("
                UPDATE thread_participants
                SET is_read = 0
                WHERE thread_id = ? AND user_id != ?
            ");
            $stmt->execute([$threadId, $userId]);
            
            // Create notification for other participant
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'general', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'userId' => $otherParticipant['user_id'],
                'title' => 'New message from ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
                'message' => 'You have received a new message regarding: ' . $thread['subject']
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to refresh the page and avoid form resubmission
            header("Location: message_thread.php?id=" . $threadId);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty";
    }
}

// Check for error messages
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['error']);

// Format date/time
function formatMessageTime($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        return 'Today at ' . $date->format('g:i A');
    } elseif ($diff->days == 1) {
        return 'Yesterday at ' . $date->format('g:i A');
    } else {
        return $date->format('M j, Y g:i A');
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Thread - Tenant Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#1a56db',
                    secondary: '#7e3af2',
                    success: '#0ea5e9',
                }
            }
        }
    }
    </script>
</head>

<body class="bg-gray-50">
    <!-- Sidebar -->
    <?php include 'tenant_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="messages.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($thread['subject']); ?></h2>
                <p class="text-gray-600">
                    Conversation with
                    <?php echo htmlspecialchars($otherParticipant['first_name'] . ' ' . $otherParticipant['last_name']); ?>
                </p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Message Thread -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <div class="space-y-6">
                <?php foreach ($messages as $message): ?>
                <div class="flex <?php echo $message['sender_id'] == $userId ? 'justify-end' : 'justify-start'; ?>">
                    <div
                        class="<?php echo $message['sender_id'] == $userId ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?> rounded-lg px-4 py-3 max-w-md">
                        <div class="flex items-center mb-1">
                            <?php if ($message['sender_id'] != $userId): ?>
                            <div class="flex-shrink-0 h-8 w-8 mr-2">
                                <img class="h-8 w-8 rounded-full"
                                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($message['first_name'] . '+' . $message['last_name']); ?>&background=random"
                                    alt="User">
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium">
                                    <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        <?php echo formatMessageTime($message['created_at']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="text-sm whitespace-pre-wrap">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reply Form -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <form method="POST" action="message_thread.php?id=<?php echo $threadId; ?>">
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Reply</label>
                    <textarea id="message" name="message" rows="4"
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        placeholder="Type your reply here..." required></textarea>
                </div>
                <div class="flex justify-end mt-4">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Scroll to bottom of messages on page load
    window.onload = function() {
        const messageContainer = document.querySelector('.space-y-6');
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
    </script>
</body>

</html>