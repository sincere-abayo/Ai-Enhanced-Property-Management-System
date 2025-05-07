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

try {
    // Mark all notifications as read
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = :userId AND is_read = 0
    ");
    
    $stmt->execute(['userId' => $userId]);
    
    $count = $stmt->rowCount();
    
    if ($isAjax) {
        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "$count notification(s) marked as read",
            'count' => $count
        ]);
        exit;
    } else {
        // Set session message for regular requests
        if ($count > 0) {
            $_SESSION['success'] = "$count notification(s) marked as read";
        } else {
            $_SESSION['info'] = "No unread notifications found";
        }
        
        // Redirect back to notifications page
        header("Location: notifications.php");
        exit;
    }
    
} catch (PDOException $e) {
    if ($isAjax) {
        // Return JSON error for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Database error: " . $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: notifications.php");
        exit;
    }
}
