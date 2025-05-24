<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/messaging.php';

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
    SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.role
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
    $messageType = $_POST['message_type'] ?? 'portal';
    $messagingErrors = [];
    
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
                'subject' => $thread['subject'],
                'message' => $messageText
            ]);

            $messageId = $pdo->lastInsertId();
            
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

            // Handle different message delivery types
            if ($messageType !== 'portal') {
                // Get sender information for the email/SMS template
                $stmt = $pdo->prepare("
                    SELECT first_name, last_name, role FROM users WHERE user_id = :userId
                ");
                $stmt->execute(['userId' => $userId]);
                $sender = $stmt->fetch();
                
                // Send via email if selected
                if ($messageType === 'email' || $messageType === 'both') {
                    if (!empty($otherParticipant['email'])) {
                        // Generate email content using template
                        $emailBody = getEmailTemplate(
                            'Re: ' . $thread['subject'],
                            $messageText,
                            $sender
                        );
                        
                        // Send email
                        $emailSent = sendEmail(
                            $otherParticipant['email'],
                            'Re: ' . $thread['subject'],
                            $emailBody,
                            strip_tags($messageText)
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
                    } else {
                        $messagingErrors[] = "No email address available for recipient";
                    }
                }
                
                // Send via SMS if selected
                if ($messageType === 'sms' || $messageType === 'both') {
                    if (!empty($otherParticipant['phone'])) {
                        // Generate SMS content
                        $senderName = $sender['first_name'] . ' ' . $sender['last_name'];
                        $smsText = "New message from " . $senderName . ": ";
                        $remainingChars = 160 - strlen($smsText) - 30; // 30 chars for "... Reply in your landlord portal."
                        
                        $truncatedMessage = $messageText;
                        if (strlen($messageText) > $remainingChars) {
                            $truncatedMessage = substr($messageText, 0, $remainingChars) . "...";
                        }
                        
                        $smsText .= $truncatedMessage . " Reply in your landlord portal.";
                        
                        // Send SMS
                        $smsSent = sendSMS(
                            $otherParticipant['phone'],
                            $smsText
                        );
                        
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
                        $messagingErrors[] = "No phone number available for recipient";
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message with any delivery warnings
            if (empty($messagingErrors)) {
                $_SESSION['success'] = "Message sent successfully!";
            } else {
                $_SESSION['success'] = "Message sent to portal successfully!";
                $_SESSION['warning'] = "Some delivery methods failed: " . implode(', ', $messagingErrors);
            }
            
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

// Check for success, warning, or error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$warning = isset($_SESSION['warning']) ? $_SESSION['warning'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['warning']);
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

        <!-- Success/Warning/Error Messages -->
        <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($warning): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($warning); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Message Thread -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <div class="space-y-6" id="messagesContainer">
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
            <form method="POST" action="message_thread.php?id=<?php echo $threadId; ?>" id="replyForm">
                <div class="mb-4">
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Reply</label>
                    <textarea id="message" name="message" rows="4"
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        placeholder="Type your reply here..." required></textarea>
                </div>

                <!-- Message Type Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Method</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="portal" class="mr-2" checked>
                            <span>Portal Only</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="email" class="mr-2" id="emailOption">
                            <span>Email</span>
                            <span
                                class="text-red-500 text-xs ml-1 recipient-email-warning <?php echo empty($otherParticipant['email']) ? '' : 'hidden'; ?>">(No
                                email available)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="sms" class="mr-2" id="smsOption">
                            <span>SMS</span>
                            <span
                                class="text-red-500 text-xs ml-1 recipient-phone-warning <?php echo empty($otherParticipant['phone']) ? '' : 'hidden'; ?>">(No
                                phone available)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="message_type" value="both" class="mr-2" id="bothOption">
                            <span>Email & SMS</span>
                            <span
                                class="text-red-500 text-xs ml-1 recipient-contact-warning <?php echo (!empty($otherParticipant['email']) && !empty($otherParticipant['phone'])) ? 'hidden' : ''; ?>">(Missing
                                contact info)</span>
                        </label>
                    </div>
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
                            <p><strong>To:</strong> <span
                                    id="previewEmailTo"><?php echo htmlspecialchars($otherParticipant['email'] ?? 'No email available'); ?></span>
                            </p>
                            <p><strong>Subject:</strong> <span id="previewEmailSubject"></span></p>
                            <div class="mt-2 p-2 bg-gray-50 rounded">
                                <p id="previewEmailContent"></p>
                            </div>
                        </div>
                    </div>

                    <div id="smsPreview" class="hidden">
                        <h5 class="text-sm font-semibold mb-1">SMS Preview:</h5>
                        <div class="bg-white p-3 rounded border border-gray-200 text-sm">
                            <p><strong>To:</strong> <span
                                    id="previewSmsTo"><?php echo htmlspecialchars($otherParticipant['phone'] ?? 'No phone available'); ?></span>
                            </p>
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

                <!-- Delivery Status (if available) -->
                <?php if (!empty($messages)): ?>
                <div class="mb-4">
                    <button type="button" id="toggleDeliveryStatus" class="text-sm text-gray-600 hover:text-gray-800">
                        <i class="fas fa-info-circle mr-1"></i>View Delivery Status
                    </button>
                    <div id="deliveryStatusPanel" class="hidden mt-2 p-3 bg-gray-50 rounded border">
                        <h6 class="text-sm font-semibold mb-2">Recent Message Delivery Status:</h6>
                        <div id="deliveryStatusContent">
                            <!-- This would be populated via AJAX or PHP -->
                            <p class="text-xs text-gray-500">Loading delivery status...</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex justify-end space-x-4">
                    <button type="button" id="previewButton"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-eye mr-2"></i>Preview
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Message preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        const messageInput = document.querySelector('[name="message"]');
        const previewButton = document.getElementById('previewButton');
        const messagePreview = document.getElementById('messagePreview');
        const closePreview = document.getElementById('closePreview');
        const emailPreview = document.getElementById('emailPreview');
        const smsPreview = document.getElementById('smsPreview');
        const previewEmailSubject = document.getElementById('previewEmailSubject');
        const previewEmailContent = document.getElementById('previewEmailContent');
        const previewSmsContent = document.getElementById('previewSmsContent');
        const smsCharCount = document.getElementById('smsCharCount');
        const smsPartCount = document.getElementById('smsPartCount');
        const toggleDeliveryStatus = document.getElementById('toggleDeliveryStatus');
        const deliveryStatusPanel = document.getElementById('deliveryStatusPanel');

        // Message type radio buttons
        const messageTypeRadios = document.querySelectorAll('input[name="message_type"]');
        const emailOption = document.getElementById('emailOption');
        const smsOption = document.getElementById('smsOption');
        const bothOption = document.getElementById('bothOption');

        // Recipient contact info
        const recipientEmail = '<?php echo htmlspecialchars($otherParticipant['email'] ?? ''); ?>';
        const recipientPhone = '<?php echo htmlspecialchars($otherParticipant['phone'] ?? ''); ?>';

        // Disable options if contact info is missing
        emailOption.disabled = (recipientEmail === '');
        smsOption.disabled = (recipientPhone === '');
        bothOption.disabled = (recipientEmail === '') || (recipientPhone === '');

        // Reset to portal if current selection is disabled
        messageTypeRadios.forEach(radio => {
            if (radio.checked && radio.disabled) {
                document.querySelector('input[value="portal"]').checked = true;
            }
        });

        // Preview button click handler
        previewButton.addEventListener('click', function() {
            const message = messageInput.value.trim();
            const threadSubject = '<?php echo htmlspecialchars($thread['subject']); ?>';

            if (message === '') {
                alert('Please enter a message');
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
                previewEmailSubject.textContent = 'Re: ' + threadSubject;

                const senderName =
                    "<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>";
                const emailContent =
                    `You have received a new message from ${senderName}:\n\n${message}\n\nPlease log in to your landlord portal to reply: http://localhost/utb/Ai-Enhanced-Property-Management-System/login.php`;
                previewEmailContent.innerHTML = emailContent.replace(/\n/g, '<br>');
            } else {
                emailPreview.classList.add('hidden');
            }

            if (selectedType === 'sms' || selectedType === 'both') {
                smsPreview.classList.remove('hidden');

                const senderName =
                    "<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>";
                let smsText = "New message from " + senderName + ": ";
                const remainingChars = 160 - smsText.length -
                    30; // 30 chars for "... Reply in your landlord portal."

                let displayText = message;
                if (message.length > remainingChars) {
                    displayText = message.substring(0, remainingChars) + "...";
                }

                smsText += displayText + " Reply in your landlord portal.";
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
                if (!messagePreview.classList.contains('hidden')) {
                    previewButton.click(); // Refresh the preview
                }
            });
        });

        // Toggle delivery status panel
        if (toggleDeliveryStatus) {
            toggleDeliveryStatus.addEventListener('click', function() {
                deliveryStatusPanel.classList.toggle('hidden');

                if (!deliveryStatusPanel.classList.contains('hidden')) {
                    // Load delivery status via AJAX
                    loadDeliveryStatus();
                }
            });
        }

        // Load delivery status function
        function loadDeliveryStatus() {
            const threadId = <?php echo $threadId; ?>;

            fetch(`get_delivery_status.php?thread_id=${threadId}`)
                .then(response => response.json())
                .then(data => {
                    const content = document.getElementById('deliveryStatusContent');
                    if (data.success && data.logs.length > 0) {
                        let html = '';
                        data.logs.forEach(log => {
                            const statusClass = log.status === 'sent' ? 'text-green-600' :
                                'text-red-600';
                            const icon = log.status === 'sent' ? 'fa-check-circle' :
                                'fa-times-circle';

                            html += `
                                <div class="flex items-center justify-between py-1 text-xs">
                                    <span>${log.delivery_method.toUpperCase()} to ${log.recipient_contact}</span>
                                    <span class="${statusClass}">
                                        <i class="fas ${icon} mr-1"></i>${log.status}
                                    </span>
                                </div>
                            `;
                        });
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p class="text-xs text-gray-500">No delivery logs found.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading delivery status:', error);
                    document.getElementById('deliveryStatusContent').innerHTML =
                        '<p class="text-xs text-red-500">Error loading delivery status.</p>';
                });
        }

        // Scroll to bottom of messages on page load
        const messageContainer = document.getElementById('messagesContainer');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }

        // Auto-scroll to bottom when new messages are added
        function scrollToBottom() {
            const messageContainer = document.getElementById('messagesContainer');
            if (messageContainer) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        }

        // Call scroll function after page load
        window.addEventListener('load', scrollToBottom);
    });
    </script>
</body>

</html>