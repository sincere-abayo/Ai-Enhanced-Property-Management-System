<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if payment ID is provided
if (!isset($_POST['payment_id']) || !is_numeric($_POST['payment_id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_POST['payment_id'];
$voidReason = isset($_POST['void_reason']) ? trim($_POST['void_reason']) : '';

// Verify the payment belongs to this landlord
$stmt = $pdo->prepare("
    SELECT pay.payment_id
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
    $_SESSION['error'] = "Payment not found or you don't have permission to void it";
    header("Location: payments.php");
    exit;
}

// Validate void reason
if (empty($voidReason)) {
    $_SESSION['error'] = "Please provide a reason for voiding this payment";
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
            'void',
            :userId,
            :reason,
            NOW()
        )
    ");
    
    $stmt->execute([
        'paymentId' => $paymentId,
        'userId' => $userId,
        'reason' => $voidReason
    ]);
    
    // Update payment status to 'voided'
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status = 'voided',
            voided_at = NOW(),
            voided_by = :userId,
            void_reason = :reason
        WHERE payment_id = :paymentId
    ");
    
    $stmt->execute([
        'userId' => $userId,
        'reason' => $voidReason,
        'paymentId' => $paymentId
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Payment has been voided successfully";
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
