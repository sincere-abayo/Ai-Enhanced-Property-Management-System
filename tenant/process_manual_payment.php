<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/currency.php';

// Require tenant role
requireRole('tenant');

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaseId = (int)$_POST['lease_id'];
    $paymentMethod = $_POST['payment_method'];
    $paymentDate = $_POST['payment_date'];
    $amount = (float)$_POST['amount'];
    $reference = trim($_POST['reference']);
    
    // Validate input
    $errors = [];
    
    if (empty($leaseId)) {
        $errors[] = "Lease ID is required";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (empty($paymentDate)) {
        $errors[] = "Payment date is required";
    }
    
    // Verify lease belongs to current user
    $stmt = $pdo->prepare("
        SELECT l.*, p.property_name, p.landlord_id
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.lease_id = ? AND l.tenant_id = ? AND l.status = 'active'
    ");
    $stmt->execute([$leaseId, $userId]);
    $lease = $stmt->fetch();
    
    if (!$lease) {
        $errors[] = "Invalid lease or you don't have permission to record this payment";
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Create payment record (pending landlord approval)
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    lease_id, amount, payment_date, payment_method, payment_type, 
                    gateway_name, gateway_status, notes, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'rent', 'manual', 'pending', ?, NOW()
                )
            ");
            
            $notes = "Manual payment recorded by tenant";
            if (!empty($reference)) {
                $notes .= " - Reference: " . $reference;
            }
            
            $stmt->execute([
                $leaseId,
                $amount,
                $paymentDate,
                $paymentMethod,
                $notes
            ]);
            
            // Create notification for landlord
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    ?, 'Payment Recorded by Tenant', 'Tenant has recorded a manual payment of " . formatCurrency($amount) . " for " . htmlspecialchars($lease['property_name']) . ". Please verify and approve.', 'payment', 0, NOW()
                )
            ");
            $stmt->execute([$lease['landlord_id']]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Payment recorded successfully! Your landlord will verify and approve the payment.";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode(', ', $errors);
    }
}

header("Location: payments.php");
exit;
?>