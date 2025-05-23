<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$lastName = $_SESSION['last_name'];

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $leaseId = isset($_POST['lease_id']) ? (int)$_POST['lease_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $paymentDate = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';
    
    // Validate lease belongs to this tenant
    $stmt = $pdo->prepare("
        SELECT l.*, p.landlord_id, p.property_name 
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.lease_id = ? AND l.tenant_id = ? AND l.status = 'active'
    ");
    $stmt->execute([$leaseId, $userId]);
    $lease = $stmt->fetch();
    
    if (!$lease) {
        $errors[] = "Invalid lease selected";
    }
    
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    if (empty($paymentDate)) {
        $errors[] = "Payment date is required";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Payment method is required";
    }
    
    // If no errors, record the payment
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Record the payment
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    lease_id, amount, payment_date, payment_method, payment_type, notes
                ) VALUES (
                    :leaseId, :amount, :paymentDate, :paymentMethod, 'rent', :notes
                )
            ");
            
            $stmt->execute([
                'leaseId' => $leaseId,
                'amount' => $amount,
                'paymentDate' => $paymentDate,
                'paymentMethod' => $paymentMethod,
                'notes' => "Payment recorded by tenant. Reference: " . $reference
            ]);
            
            $paymentId = $pdo->lastInsertId();
            
            // Create a notification for the landlord
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'payment', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'userId' => $lease['landlord_id'],
                'title' => 'Payment Received',
                'message' => "Payment of " . formatCurrency($amount) . " received from " . $firstName . " " . $lastName . " for " . $lease['property_name']
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success'] = "Payment of " . formatCurrency($amount) . " has been recorded successfully!";
            
            // Redirect to payment receipt
            header("Location: payment_receipt.php?id=" . $paymentId);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}



// If there are errors, redirect back to payments page with error messages
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header("Location: payments.php");
    exit;
}

// If not a POST request, redirect to payments page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: payments.php");
    exit;
}
?>