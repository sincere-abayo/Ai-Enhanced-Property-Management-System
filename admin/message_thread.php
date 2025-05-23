<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/messaging.php'; // Add this line to include messaging functions

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
$messagingErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $message = trim($_POST['message']);
    $messageType = isset($_POST['message_type']) ? $_POST['message_type'] : 'portal';
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
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
            
            $messageId = $pdo->lastInsertId();
            
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
            
            // Send via email if selected
            if ($messageType === 'email' || $messageType === 'both') {
                // Get sender information for the email template
                $stmt = $pdo->prepare("
                    SELECT first_name, last_name, role FROM users WHERE user_id = :userId
                ");
                $stmt->execute(['userId' => $userId]);
                $sender = $stmt->fetch();
                
                // Generate email content
                $emailBody = getEmailTemplate(
                    'RE: ' . $thread['subject'],
                    $message,
                    $sender
                );
                
                // Send email
                $emailSent = sendEmail(
                    $otherParticipant['email'],
                    'RE: ' . $thread['subject'],
                    $emailBody,
                    strip_tags($message)
                );
                
                if (!$emailSent) {
                    $messagingErrors[] = "Failed to send email to " . $otherParticipant['email'];
                }
                
                // Log email delivery attempt
                $stmt = $pdo->prepare("
                    INSERT INTO message_delivery_logs (
                        message_id, delivery_method, status, error_message, created_at
                    ) VALUES (
                        :messageId, 'email', :status, :errorMessage, NOW()
                    )
                ");
                
                $stmt->execute([
                    'messageId' => $messageId,
                    'status' => $emailSent ? 'sent' : 'failed',
                    'errorMessage' => $emailSent ? null : 'Failed to send email'
                ]);
            }
            
            // Send via SMS if selected
            if ($messageType === 'sms' || $messageType === 'both') {
                // Check if recipient has a phone number
                if (!empty($otherParticipant['phone'])) {
                    // Prepare SMS message (shortened version of the message)
                    $smsText = "New message from " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ": ";
                    $smsText .= mb_substr($message, 0, 100) . (mb_strlen($message) > 100 ? "..." : "");
                    $smsText .= " Reply in your tenant portal.";
                    
                    // Send SMS
                    $smsSent = sendSMS($otherParticipant['phone'], $smsText);
                    
                    if (!$smsSent) {
                        $messagingErrors[] = "Failed to send SMS to " . $otherParticipant['phone'];
                    }
                    
                    // Log SMS delivery attempt
                    $stmt = $pdo->prepare("
                        INSERT INTO message_delivery_logs (
                            message_id, delivery_method, status, error_message, created_at
                        ) VALUES (
                            :messageId, 'sms', :status, :errorMessage, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        'messageId' => $messageId,
                        'status' => $smsSent ? 'sent' : 'failed',
                        'errorMessage' => $smsSent ? null : 'Failed to send SMS'
                    ]);
                } else {
                    $messagingErrors[] = "Recipient does not have a phone number for SMS";
                    
                    // Log the error
                    $stmt = $pdo->prepare("
                        INSERT INTO message_delivery_logs (
                            message_id, delivery_method, status, error_message, created_at
                        ) VALUES (
                            :messageId, 'sms', 'failed', :errorMessage, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        'messageId' => $messageId,
                        'errorMessage' => 'Recipient has no phone number'
                    ]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success = true;
            
            // Refresh the page to show the new message
            header("Location: message_thread.php?id=" . $threadId . "&sent=1" . 
                  (!empty($messagingErrors) ? "&warnings=" . urlencode(implode(", ", $messagingErrors)) : ""));
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
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
                    <?php if (isset($_GET['warnings'])): ?>
                        <div class="mt-2 text-yellow-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Warning: <?php echo htmlspecialchars(urldecode($_GET['warnings'])); ?>
                        </div>
                    <?php endif; ?>
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
                            <div class="max-w-[70%] <?php echo ($msg['sender_id'] == $userId) ? 'bg-primary text-white' : 'bg-gray-100 text-gray-800'; ?> rounded-lg p-4 shadow-sm">
                                <div class="flex items-center mb-1">
                                    <span class="font-medium"><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></span>
                                    <span class="text-xs <?php echo ($msg['sender_id'] == $userId) ? 'text-blue-200' : 'text-gray-500'; ?> ml-2">
                                        <?php echo formatMessageTime($msg['created_at']); ?>
                                    </span>
                                </div>
                                <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                
                                <?php if ($msg['message_type'] !== 'portal'): ?>
                                <div class="mt-2 text-xs <?php echo ($msg['sender_id'] == $userId) ? 'text-blue-200' : 'text-gray-500'; ?>">
                                    <i class="fas <?php echo ($msg['message_type'] === 'email' || $msg['message_type'] === 'both') ? 'fa-envelope' : ''; ?> <?php echo ($msg['message_type'] === 'sms' || $msg['message_type'] === 'both') ? 'fa-sms ml-2' : ''; ?>"></i>
                                    <?php 
                                    $methods = [];
                                    if ($msg['message_type'] === 'email' || $msg['message_type'] === 'both') $methods[] = 'Email';
                                    if ($msg['message_type'] === 'sms' || $msg['message_type'] === 'both') $methods[] = 'SMS';
                                    echo 'Sent via: ' . implode(' & ', $methods);
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Reply Form -->
            <form method="POST" action="message_thread.php?id=<?php echo $threadId; ?>" id="replyForm">
                <div class="space-y-4">
                    <!-- Message Type Selection -->
                    <div class="flex flex-wrap gap-4 mb-2">
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="portal" class="mr-2" checked>
                            <span>Portal Only</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="email" class="mr-2">
                            <span>Email</span>
                            <?php if (empty($otherParticipant['email'])): ?>
                                <span class="text-red-500 text-xs ml-1">(No email available)</span>
                            <?php endif; ?>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="sms" class="mr-2">
                            <span>SMS</span>
                            <?php if (empty($otherParticipant['phone'])): ?>
                                <span class="text-red-500 text-xs ml-1">(No phone available)</span>
                            <?php endif; ?>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="both" class="mr-2">
                            <span>Email & SMS</span>
                            <?php if (empty($otherParticipant['email']) || empty($otherParticipant['phone'])): ?>
                                <span class="text-red-500 text-xs ml-1">(Missing contact info)</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <!-- Message Preview -->
                    <div id="messagePreview" class="hidden bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold">Message Preview</h4>
                            <button type="button" id="closePreview" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div id="emailPreview" class="hidden mb-4">
                            <h5 class="text-sm font-semibold mb-1">Email Preview:</h5>
                            <div class="bg-white p-3 rounded border border-gray-200 text-sm">
                                <p><strong>To:</strong> <span id="previewEmailTo"><?php echo htmlspecialchars($otherParticipant['email']); ?></span></p>
                                <p><strong>Subject:</strong> RE: <?php echo htmlspecialchars($thread['subject']); ?></p>
                                <div class="mt-2 p-2 bg-gray-50 rounded">
                                    <p id="previewEmailContent"></p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="smsPreview" class="hidden">
                            <h5 class="text-sm font-semibold mb-1">SMS Preview:</h5>
                            <div class="bg-white p-3 rounded border border-gray-200 text-sm">
                                <p><strong>To:</strong> <span id="previewSmsTo"><?php echo htmlspecialchars($otherParticipant['phone']); ?></span></p>
                                <div class="mt-2 p-2 bg-gray-50 rounded">
                                    <p id="previewSmsContent"></p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span id="smsCharCount">0</span>/160 characters
                                    <span id="smsPartCount" class="hidden ml-2">(Multiple SMS parts)</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <textarea name="message" id="messageInput" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="Type your message here..."></textarea>
                    
                    <div class="flex justify-between items-center">
                        <button type="button" id="previewButton" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-eye mr-2"></i>Preview
                        </button>
                        
                        <button type="submit" name="reply" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Scroll to bottom of messages container on page load
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Message preview functionality
            const messageInput = document.getElementById('messageInput');
            const previewButton = document.getElementById('previewButton');
            const messagePreview = document.getElementById('messagePreview');
            const closePreview = document.getElementById('closePreview');
            const emailPreview = document.getElementById('emailPreview');
            const smsPreview = document.getElementById('smsPreview');
            const previewEmailContent = document.getElementById('previewEmailContent');
            const previewSmsContent = document.getElementById('previewSmsContent');
            const smsCharCount = document.getElementById('smsCharCount');
            const smsPartCount = document.getElementById('smsPartCount');
            
            // Message type radio buttons
            const messageTypeRadios = document.querySelectorAll('input[name="message_type"]');
            
            // Preview button click handler
            previewButton.addEventListener('click', function() {
                const message = messageInput.value.trim();
                if (message === '') {
                    alert('Please enter a message to preview');
                    return;
                }
                
                // Get selected message type
                let selectedType = 'portal';
                messageTypeRadios.forEach(radio => {
                    if (radio.checked) {
                        selectedType = radio.value;
                    }
                });
                
                // Show/hide appropriate preview sections
                messagePreview.classList.remove('hidden');
                
                if (selectedType === 'email' || selectedType === 'both') {
                    emailPreview.classList.remove('hidden');
                    previewEmailContent.innerHTML = message.replace(/\n/g, '<br>');
                } else {
                    emailPreview.classList.add('hidden');
                }
                
                if (selectedType === 'sms' || selectedType === 'both') {
                    smsPreview.classList.remove('hidden');
                    
                    // For SMS, we need to truncate the message if it's too long
                    const senderName = "<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>";
                    let smsText = "New message from " + senderName + ": ";
                    const remainingChars = 160 - smsText.length - 30; // 30 chars for "... Reply in your tenant portal."
                    
                    let displayText = message;
                    if (message.length > remainingChars) {
                        displayText = message.substring(0, remainingChars) + "...";
                    }
                    
                    smsText += displayText + " Reply in your tenant portal.";
                    previewSmsContent.textContent = smsText;
                    
                    // Update character count
                    smsCharCount.textContent = smsText.length;
                    
                    // Show warning if message will be split into multiple SMS
                    if (smsText.length > 160) {
                        smsPartCount.classList.remove('hidden');
                    } else {
                        smsPartCount.classList.add('hidden');
                    }
                } else {
                    smsPreview.classList.add('hidden');
                }
            });
            
            // Close preview button
            closePreview.addEventListener('click', function() {
                messagePreview.classList.add('hidden');
            });
            
            // Update preview when message type changes
            messageTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (messagePreview.classList.contains('hidden')) return;
                    previewButton.click(); // Refresh the preview
                });
            });
            
            // Disable email/SMS options if contact info is missing
            const emailOption = document.querySelector('input[value="email"]');
            const smsOption = document.querySelector('input[value="sms"]');
            const bothOption = document.querySelector('input[value="both"]');
            
            <?php if (empty($otherParticipant['email'])): ?>
                emailOption.disabled = true;
            <?php endif; ?>
            
            <?php if (empty($otherParticipant['phone'])): ?>
                smsOption.disabled = true;
            <?php endif; ?>
            
            <?php if (empty($otherParticipant['email']) || empty($otherParticipant['phone'])): ?>
                bothOption.disabled = true;
            <?php endif; ?>
        });
    </script>
</body>
</html>
