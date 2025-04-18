<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_GET['id'];

// Verify the payment belongs to this landlord and is voided
$stmt = $pdo->prepare("
    SELECT pay.payment_id, pay.status
    FROM payments pay
    JOIN leases l ON pay.lease_id = l.lease_id
    JOIN properties p ON l.property_id = p.property_id
    WHERE pay.payment_id = :paymentId AND p.landlord_id = :landlordId
");

$stmt->execute([
    'paymentId' => $paymentId,
    'landlordId' => $userId
]);

$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "Payment not found or you don't have permission to restore it";
    header("Location: payments.php");
    exit;
}

if ($payment['status'] !== 'voided') {
    $_SESSION['error'] = "This payment is not voided and cannot be restored";
    header("Location: payment_details.php?id=" . $paymentId);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Create a payment_audit record
    $stmt = $pdo->prepare("
        INSERT INTO payment_audit (
            payment_id, 
            action, 
            action_by, 
            action_reason, 
            action_date
        ) VALUES (
            :paymentId,
            'restore',
            :userId,
            'Payment restored',
            NOW()
        )
    ");
    
    $stmt->execute([
        'paymentId' => $paymentId,
        'userId' => $userId
    ]);
    
    // Update payment status to 'active'
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status = 'active',
            voided_at = NULL,
            voided_by = NULL,
            void_reason = NULL
        WHERE payment_id = :paymentId
    ");
    
    $stmt->execute([
        'paymentId' => $paymentId
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Payment has been successfully restored";
    header("Location: payment_details.php?id=" . $paymentId);
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: payment_details.php?id=" . $paymentId);
    exit;
}
?>
