<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Check if maintenance request ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid maintenance request ID";
    header("Location: maintenance.php");
    exit;
}

$requestId = (int)$_GET['id'];

// Get maintenance request details to verify ownership and status
$stmt = $pdo->prepare("
    SELECT mr.*, p.landlord_id
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.request_id = ? AND mr.tenant_id = ?
");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

// If request not found or doesn't belong to this tenant, redirect
if (!$request) {
    $_SESSION['error'] = "Maintenance request not found or you don't have permission to cancel it";
    header("Location: maintenance.php");
    exit;
}

// Check if request is in a status that can be cancelled (only pending or assigned requests can be cancelled)
if ($request['status'] !== 'pending' && $request['status'] !== 'assigned') {
    $_SESSION['error'] = "Only pending or assigned maintenance requests can be cancelled";
    header("Location: maintenance_details.php?id=" . $requestId);
    exit;
}

// Process cancellation
try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update request status to cancelled
    $stmt = $pdo->prepare("
        UPDATE maintenance_requests
        SET status = 'cancelled', updated_at = NOW()
        WHERE request_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$requestId, $userId]);
    
    // Create notification for landlord
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id, title, message, type, is_read, created_at
        ) VALUES (
            :userId, :title, :message, 'maintenance', 0, NOW()
        )
    ");
    
    $landlordId = $request['landlord_id'];
    if ($landlordId) {
        $stmt->execute([
            'userId' => $landlordId,
            'title' => 'Maintenance request cancelled',
            'message' => 'Maintenance request #' . $requestId . ' has been cancelled by the tenant'
        ]);
    }
    
    // Add a message to record the cancellation
    $stmt = $pdo->prepare("
        INSERT INTO messages (
            sender_id, recipient_id, subject, message, message_type, is_read, created_at
        ) VALUES (
            :senderId, :recipientId, :subject, :message, 'portal', 0, NOW()
        )
    ");
    
    $stmt->execute([
        'senderId' => $userId,
        'recipientId' => $landlordId,
        'subject' => 'Maintenance #' . $requestId,
        'message' => 'I have cancelled this maintenance request.'
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Maintenance request has been cancelled successfully";
    header("Location: maintenance.php");
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: maintenance_details.php?id=" . $requestId);
    exit;
}
?>