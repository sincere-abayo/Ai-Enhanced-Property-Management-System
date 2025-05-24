<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/paypal_config.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaseId = (int)$_POST['lease_id'];
    $amount = (float)$_POST['amount'];
    $description = $_POST['description'];
    
    // Validate input
    if (empty($leaseId) || $amount <= 0) {
        $_SESSION['error'] = "Invalid payment data provided";
        header("Location: payments.php");
        exit;
    }
    
    // Verify lease belongs to current user
    $stmt = $pdo->prepare("
        SELECT l.*, p.property_name 
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.lease_id = ? AND l.tenant_id = ? AND l.status = 'active'
    ");
    $stmt->execute([$leaseId, $userId]);
    $lease = $stmt->fetch();
    
    if (!$lease) {
        $_SESSION['error'] = "Invalid lease or you don't have permission to make this payment";
        header("Location: payments.php");
        exit;
    }
    
    // Test PayPal connection first
    $connectionTest = testPayPalConnection();
    if (!$connectionTest['success']) {
        $_SESSION['error'] = "PayPal service is currently unavailable. Please try again later or contact support.";
        error_log("PayPal connection test failed for user $userId");
        header("Location: payments.php");
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Create payment transaction record
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                lease_id, amount, currency, payment_method, gateway_name, status, created_at
            ) VALUES (
                ?, ?, 'USD', 'paypal', 'paypal', 'pending', NOW()
            )
        ");
        $stmt->execute([$leaseId, $amount]);
        $transactionId = $pdo->lastInsertId();
        
        // Log the payment attempt
        error_log("Creating PayPal payment for user $userId, lease $leaseId, amount $amount");
        
        // Create PayPal payment
        $paypalPayment = createPayPalPayment($amount, 'USD', $description, $leaseId);
        
        if ($paypalPayment && isset($paypalPayment['id'])) {
            // Update transaction with PayPal payment ID
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET gateway_transaction_id = ?, gateway_response = ?
                WHERE transaction_id = ?
            ");
            $stmt->execute([
                $paypalPayment['id'],
                json_encode($paypalPayment),
                $transactionId
            ]);
            
            // Store transaction ID in session for later use
            $_SESSION['pending_transaction_id'] = $transactionId;
            
            // Commit transaction
            $pdo->commit();
            
            // Find approval URL and redirect to PayPal
            foreach ($paypalPayment['links'] as $link) {
                if ($link['rel'] === 'approval_url') {
                    error_log("Redirecting user $userId to PayPal approval URL");
                    header("Location: " . $link['href']);
                    exit;
                }
            }
            
            // If we get here, approval URL wasn't found
            $pdo->rollBack();
            $_SESSION['error'] = "PayPal payment created but approval URL not found. Please try again.";
            error_log("PayPal approval URL not found in response: " . json_encode($paypalPayment));
            
        } else {
            // PayPal payment creation failed
            $pdo->rollBack();
            
            // Check if we have error details
            $errorMessage = "Failed to create PayPal payment. Please try again.";
            
            // Log detailed error for debugging
            error_log("PayPal payment creation failed for user $userId: " . json_encode($paypalPayment));
            
            $_SESSION['error'] = $errorMessage;
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database error occurred. Please try again.";
        error_log("Database error in PayPal payment process: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "An unexpected error occurred. Please try again.";
        error_log("Unexpected error in PayPal payment process: " . $e->getMessage());
    }
}

header("Location: payments.php");
exit;
?>