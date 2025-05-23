<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/messaging.php'; // Add this line for messaging functions

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize errors array
$errors = [];
$messagingErrors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $messageType = isset($_POST['message_type']) ? $_POST['message_type'] : 'portal';
    
    // Validate required fields
    if (empty($recipientId)) {
        $errors[] = "Recipient is required";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    // Verify recipient is a landlord for one of tenant's properties
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM properties p
        JOIN leases l ON p.property_id = l.property_id
        WHERE p.landlord_id = ? AND l.tenant_id = ? AND l.status = 'active'
    ");
    $stmt->execute([$recipientId, $userId]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $errors[] = "Invalid recipient selected";
    }
    
    // Get recipient contact information if needed
    if ($messageType !== 'portal') {
        $stmt = $pdo->prepare("
            SELECT email, phone FROM users WHERE user_id = ?
        ");
               $stmt->execute([$recipientId]);
        $recipient = $stmt->fetch();
        
        // Validate contact information based on message type
        if (($messageType === 'email' || $messageType === 'both') && empty($recipient['email'])) {
            $errors[] = "Recipient does not have an email address";
        }
        
        if (($messageType === 'sms' || $messageType === 'both') && empty($recipient['phone'])) {
            $errors[] = "Recipient does not have a phone number";
        }
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if there's an existing thread between these users
            $stmt = $pdo->prepare("
                SELECT t.thread_id 
                FROM message_threads t
                JOIN thread_participants p1 ON t.thread_id = p1.thread_id
                JOIN thread_participants p2 ON t.thread_id = p2.thread_id
                WHERE p1.user_id = ? AND p2.user_id = ?
                ORDER BY t.updated_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$userId, $recipientId]);
            $existingThread = $stmt->fetch();
            
            $threadId = null;
            
            if ($existingThread) {
                // Use existing thread
                $threadId = $existingThread['thread_id'];
                
                // Update thread subject and timestamp
                $stmt = $pdo->prepare("
                    UPDATE message_threads 
                    SET subject = :subject, updated_at = NOW() 
                    WHERE thread_id = :threadId
                ");
                
                $stmt->execute([
                    'subject' => $subject,
                    'threadId' => $threadId
                ]);
            } else {
                // Create new thread
                $stmt = $pdo->prepare("
                    INSERT INTO message_threads (subject, created_at, updated_at)
                    VALUES (:subject, NOW(), NOW())
                ");
                
                $stmt->execute(['subject' => $subject]);
                $threadId = $pdo->lastInsertId();
                
                // Add participants
                $stmt = $pdo->prepare("
                    INSERT INTO thread_participants (thread_id, user_id, is_read)
                    VALUES (:threadId, :userId, 1), (:threadId, :recipientId, 0)
                ");
                
                $stmt->execute([
                    'threadId' => $threadId,
                    'userId' => $userId,
                    'recipientId' => $recipientId
                ]);
            }
            
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
                'recipientId' => $recipientId,
                'subject' => $subject,
                'message' => $message,
                'messageType' => $messageType
            ]);
            
            $messageId = $pdo->lastInsertId();
            
            // Mark thread as unread for recipient
            $stmt = $pdo->prepare("
                UPDATE thread_participants
                SET is_read = 0
                WHERE thread_id = :threadId AND user_id = :recipientId
            ");
            
            $stmt->execute([
                'threadId' => $threadId,
                'recipientId' => $recipientId
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
                'userId' => $recipientId,
                'title' => 'New message',
                'message' => 'You have received a new message from ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . ': ' . $subject
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
                    $subject,
                    $message,
                    $sender
                );
                
                // Send email
                $emailSent = sendEmail(
                    $recipient['email'],
                    $subject,
                    $emailBody,
                    strip_tags($message)
                );
                
                if (!$emailSent) {
                    $messagingErrors[] = "Failed to send email to " . $recipient['email'];
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
                // Prepare SMS message (shortened version of the message)
                $smsText = "New message from " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . ": ";
                $remainingChars = 160 - strlen($smsText) - 30; // 30 chars for "... Reply in your landlord portal."
                
                $smsContent = strlen($message) > $remainingChars 
                    ? substr($message, 0, $remainingChars) . "..." 
                    : $message;
                
                $smsText .= $smsContent . " Reply in your landlord portal.";
                
                // Send SMS
                $smsSent = sendSMS($recipient['phone'], $smsText);
                
                if (!$smsSent) {
                    $messagingErrors[] = "Failed to send SMS to " . $recipient['phone'];
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
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success'] = "Message sent successfully!";
            
            // Add warnings if any
            if (!empty($messagingErrors)) {
                $_SESSION['warnings'] = $messagingErrors;
            }
            
            // Redirect to message thread
            header("Location: message_thread.php?id=" . $threadId);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// If we get here, there were errors
$_SESSION['errors'] = $errors;
header("Location: messages.php");
exit;
?>
