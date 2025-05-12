<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();
// Require tenant role
requireRole('tenant');

// Get user ID
$userId = $_SESSION['user_id'];

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Get JSON data from request body
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// If not AJAX, try to get data from GET/POST
if (!$isAjax || !$data) {
    $data = [
        'notification_id' => isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0,
        'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : ''
    ];
}

// Validate input
if (empty($data['notification_id']) || !is_numeric($data['notification_id'])) {
    sendResponse(false, 'Invalid notification ID', $isAjax);
    exit;
}

if (empty($data['action']) || !in_array($data['action'], ['read', 'unread', 'delete'])) {
    sendResponse(false, 'Invalid action', $isAjax);
    exit;
}

$notificationId = (int)$data['notification_id'];
$action = $data['action'];

try {
    // First, verify the notification belongs to this user
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE notification_id = :notificationId AND user_id = :userId
    ");
    
    $stmt->execute([
        'notificationId' => $notificationId,
        'userId' => $userId
    ]);
    
    $notification = $stmt->fetch();
    
    if (!$notification) {
        sendResponse(false, 'Notification not found or you do not have permission to modify it', $isAjax);
        exit;
    }
    
    // Perform the requested action
    switch ($action) {
        case 'read':
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE notification_id = :notificationId
            ");
            $stmt->execute(['notificationId' => $notificationId]);
            $message = 'Notification marked as read';
            break;
            
        case 'unread':
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 0 
                WHERE notification_id = :notificationId
            ");
            $stmt->execute(['notificationId' => $notificationId]);
            $message = 'Notification marked as unread';
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE notification_id = :notificationId
            ");
            $stmt->execute(['notificationId' => $notificationId]);
            $message = 'Notification deleted';
            break;
    }
    
    sendResponse(true, $message, $isAjax);
    
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), $isAjax);
}

/**
 * Send response based on request type
 */
function sendResponse($success, $message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
    } else {
        if ($success) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = $message;
        }
        
        // Redirect back to the referring page or notifications page
        $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'notifications.php';
        header("Location: $redirect");
    }
    exit;
}