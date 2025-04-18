<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
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
    
    // If no errors, create the message thread and send the message
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if a thread already exists between these users with this subject
            $stmt = $pdo->prepare("
                SELECT mt.thread_id
                FROM message_threads mt
                JOIN thread_participants tp1 ON mt.thread_id = tp1.thread_id
                JOIN thread_participants tp2 ON mt.thread_id = tp2.thread_id
                WHERE tp1.user_id = ? AND tp2.user_id = ? AND mt.subject = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $recipientId, $subject]);
            $existingThread = $stmt->fetch();
            
            if ($existingThread) {
                // Use existing thread
                $threadId = $existingThread['thread_id'];
                
                // Update thread timestamp
                $stmt = $pdo->prepare("
                    UPDATE message_threads
                    SET updated_at = NOW()
                    WHERE thread_id = ?
                ");
                $stmt->execute([$threadId]);
                
                // Mark as unread for recipient
                $stmt = $pdo->prepare("
                    UPDATE thread_participants
                    SET is_read = 0
                    WHERE thread_id = ? AND user_id = ?
                ");
                $stmt->execute([$threadId, $recipientId]);
            } else {
                // Create new message thread
                $stmt = $pdo->prepare("
                    INSERT INTO message_threads (
                        subject, created_at, updated_at
                    ) VALUES (
                        :subject, NOW(), NOW()
                    )
                ");
                $stmt->execute(['subject' => $subject]);
                $threadId = $pdo->lastInsertId();
                
                // Add participants to thread
                $stmt = $pdo->prepare("
                    INSERT INTO thread_participants (
                        thread_id, user_id, is_read
                    ) VALUES 
                    (:threadId, :userId, 1),
                    (:threadId, :recipientId, 0)
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
                    :threadId, :senderId, :recipientId, :subject, :message, 'portal', NOW()
                )
            ");
            $stmt->execute([
                'threadId' => $threadId,
                'senderId' => $userId,
                'recipientId' => $recipientId,
                'subject' => $subject,
                'message' => $message
            ]);
            
            // Create notification for recipient
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'general', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'userId' => $recipientId,
                'title' => 'New message from tenant',
                'message' => 'You have received a new message: ' . $subject
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success'] = "Your message has been sent successfully!";
            
            // Redirect to messages page
            header("Location: messages.php");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// If there are errors, redirect back to messages page with error messages
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: messages.php");
    exit;
}

// If not a POST request, redirect to messages page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages.php");
    exit;
}
?>