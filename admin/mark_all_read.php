<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get user ID
$userId = $_SESSION['user_id'];

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Handle the request
try {
    // Mark all notifications as read
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = :userId AND is_read = 0
    ");
    
    $stmt->execute(['userId' => $userId]);
    
    // Get number of affected rows
    $affectedRows = $stmt->rowCount();
    
    // If it's an AJAX request, return JSON response
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $affectedRows
        ]);
        exit;
    }
    
    // For non-AJAX requests, redirect back with success message
    $_SESSION['success'] = 'All notifications marked as read';
    
    // Redirect back to the referring page or dashboard
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    header("Location: $redirect");
    exit;
    
} catch (PDOException $e) {
    // Handle database errors
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
    
    // For non-AJAX requests
    $_SESSION['error'] = 'Error marking notifications as read: ' . $e->getMessage();
    
    // Redirect back to the referring page or dashboard
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    header("Location: $redirect");
    exit;
}
