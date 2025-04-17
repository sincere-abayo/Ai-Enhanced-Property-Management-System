<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get user information
$userId = $_SESSION['user_id'];

// Check if thread ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid thread ID";
    header("Location: messages.php");
    exit;
}

$threadId = (int)$_GET['id'];

// Verify the user is a participant in this thread
$stmt = $pdo->prepare("
    SELECT * FROM thread_participants 
    WHERE thread_id = :threadId AND user_id = :userId
");

$stmt->execute([
    'threadId' => $threadId,
    'userId' => $userId
]);

if (!$stmt->fetch()) {
    $_SESSION['error'] = "You don't have permission to view this conversation";
    header("Location: messages.php");
    exit;
}

// Get thread details
$stmt = $pdo->prepare("
    SELECT * FROM message_threads 
    WHERE thread_id = :threadId
");

$stmt->execute(['threadId' => $threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    $_SESSION['error'] = "Thread not found";
    header("Location: messages.php");
    exit;
}

// Get the other participant's details
$stmt = $pdo->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.role
    FROM thread_participants tp
    JOIN users u ON tp.user_id = u.user_id
    WHERE tp.thread_id = :threadId AND tp.user_id != :userId
");

$stmt->execute([
    'threadId' => $threadId,
    'userId' => $userId
]);

$otherParticipant = $stmt->fetch();

// Mark thread as read for current user
$stmt = $pdo->prepare("
    UPDATE thread_participants
    SET is_read = 1
    WHERE thread_id = :threadId AND user_id = :userId
");

$stmt->execute([
    'threadId' => $threadId,
    'userId' => $userId
]);

// Get messages in this thread
$stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.role
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.thread_id = :threadId
    ORDER BY m.created_at ASC
");

$stmt->execute(['threadId' => $threadId]);
$messages = $stmt->fetchAll();

// Process new message submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $message = trim($_POST['message']);
    $messageType = isset($_POST['message_type']) ? $_POST['message_type'] : 'portal';
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    }
    
    if (empty($errors)) {
        try {
            // Add message to thread
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    thread_id, sender_id, recipient_id, subject, message, message_type, created_at
                ) VALUES (
                    :threadId, :senderId, :recipientId, :subject, :message, :messageType, NOW()
                )
            ");
            
            $stmt->execute([
                'threadId' => $threadId,
                'senderId' => $userId,
                'recipientId' => $otherParticipant['user_id'],
                'subject' => 'RE: ' . $thread['subject'],
                'message' => $message,
                'messageType' => $messageType
            ]);
            
            // Update thread timestamp
            $stmt = $pdo->prepare("
                UPDATE message_threads
                SET updated_at = NOW()
                WHERE thread_id = :threadId
            ");
            
            $stmt->execute(['threadId' => $threadId]);
            
            // Mark as unread for recipient
            $stmt = $pdo->prepare("
                UPDATE thread_participants
                SET is_read = 0
                WHERE thread_id = :threadId AND user_id = :recipientId
            ");
            
            $stmt->execute([
                'threadId' => $threadId,
                'recipientId' => $otherParticipant['user_id']
            ]);
            
            // Create a notification for the recipient
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'general', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'userId' => $otherParticipant['user_id'],
                'title' => 'New message',
                'message' => 'You have received a new message in the conversation: ' . $thread['subject']
            ]);
            
            $success = true;
            
            // Refresh the page to show the new message
            header("Location: message_thread.php?id=" . $threadId . "&sent=1");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Format time function
function formatMessageTime($datetime) {
    $now = new DateTime();
    $messageTime = new DateTime($datetime);
    $diff = $now->diff($messageTime);
    
    if ($diff->d == 0) {
        // Today
        return 'Today at ' . $messageTime->format('g:i a');
    } elseif ($diff->d == 1) {
        // Yesterday
        return 'Yesterday at ' . $messageTime->format('g:i a');
    } else {
        // Other days
        return $messageTime->format('M j, Y g:i a');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - Property Management System</title>
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
    <?php include 'admin_sidebar.php'; ?>  

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="messages.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-gray-800">Conversation</h2>
                <p class="text-gray-600">
                    With: <?php echo htmlspecialchars($otherParticipant['first_name'] . ' ' . $otherParticipant['last_name']); ?>
                    <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full ml-2">
                        <?php echo ucfirst($otherParticipant['role']); ?>
                    </span>
                </p>
            </div>
            <div>
                <a href="tenant_details.php?id=<?php echo $otherParticipant['user_id']; ?>" class="text-primary hover:text-blue-700">
                    <i class="fas fa-user mr-1"></i> View Profile
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['sent']) && $_GET['sent'] == 1): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center justify-between">
                <div>
                    <i class="fas fa-check-circle mr-2"></i>
                    Message sent successfully!
                </div>
                <button type="button" class="text-green-700 hover:text-green-900 focus:outline-none" onclick="this.parentElement.style.display='none';">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Thread Subject -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($thread['subject']); ?></h3>
            <p class="text-sm text-gray-500">
                Started on <?php echo date('F j, Y', strtotime($thread['created_at'])); ?>
            </p>
        </div>

        <!-- Messages Container -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <div class="space-y-6 max-h-[600px] overflow-y-auto mb-6" id="messages-container">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-gray-500 py-6">
                        <p>No messages in this conversation yet.</p>
                        <p class="text-sm">Start the conversation by sending a message below.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="flex <?php echo ($msg['sender_id'] == $userId) ? 'justify-end' : 'justify-start'; ?>">
                            <div class="max-w-[75%] <?php echo ($msg['sender_id'] == $userId) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-800'; ?> rounded-lg p-4 shadow">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">
                                        <?php echo ($msg['sender_id'] == $userId) ? 'You' : htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                    </span>
                                    <span class="text-xs <?php echo ($msg['sender_id'] == $userId) ? 'text-blue-100' : 'text-gray-500'; ?> ml-2">
                                        <?php echo formatMessageTime($msg['created_at']); ?>
                                    </span>
                                </div>
                                <div class="text-sm">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                                <div class="text-xs mt-2 <?php echo ($msg['sender_id'] == $userId) ? 'text-blue-100' : 'text-gray-500'; ?>">
                                    via <?php echo ucfirst($msg['message_type']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <form method="POST" action="message_thread.php?id=<?php echo $threadId; ?>" class="mt-6 border-t pt-6">
                <div class="space-y-4">
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Reply</label>
                        <textarea 
                            id="message" 
                            name="message" 
                            rows="4" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Send via</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input 
                                    type="radio" 
                                    id="message_type_portal" 
                                    name="message_type" 
                                    value="portal" 
                                    class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                                    checked
                                >
                                <label for="message_type_portal" class="ml-2 block text-sm text-gray-700">
                                    Portal only
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-4">
                    <button 
                        type="submit" 
                        name="reply" 
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                    >
                        <i class="fas fa-paper-plane mr-2"></i>Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>

                </div>
               
            </form>
        </div>
    </div>

    <script>
         // Scroll to the bottom of the messages container when page loads
         document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    </script>
</body>
</html>
