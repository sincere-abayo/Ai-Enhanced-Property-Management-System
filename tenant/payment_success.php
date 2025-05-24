<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/currency.php';
require_once '../includes/paypal_config.php';

// Require tenant role
requireRole('tenant');

$userId = $_SESSION['user_id'];

if (isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
    $paymentId = $_GET['paymentId'];
    $payerId = $_GET['PayerID'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Find the pending transaction
        $stmt = $pdo->prepare("
            SELECT pt.*, l.tenant_id, l.property_id, p.property_name
            FROM payment_transactions pt
            JOIN leases l ON pt.lease_id = l.lease_id
            JOIN properties p ON l.property_id = p.property_id
            WHERE pt.gateway_transaction_id = ? AND l.tenant_id = ? AND pt.status = 'pending'
        ");
        $stmt->execute([$paymentId, $userId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            $_SESSION['error'] = "Transaction not found or already processed";
            header("Location: payments.php");
            exit;
        }
        
        // Execute PayPal payment
        $result = executePayPalPayment($paymentId, $payerId);
        
        if ($result && $result['state'] === 'approved') {
            // Payment successful - update transaction
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'successful', gateway_response = ?, updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $stmt->execute([
                json_encode($result),
                $transaction['transaction_id']
            ]);
            
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    lease_id, amount, payment_date, payment_method, payment_type, 
                    gateway_transaction_id, gateway_name, gateway_status, gateway_response, 
                    notes, created_at
                ) VALUES (
                    ?, ?, CURDATE(), 'paypal', 'rent', ?, 'paypal', 'successful', ?, 
                    'PayPal payment processed successfully', NOW()
                )
            ");
            $stmt->execute([
                $transaction['lease_id'],
                $transaction['amount'],
                $paymentId,
                json_encode($result)
            ]);
            
            $paymentId = $pdo->lastInsertId();
            
            // Create notification for landlord
            $stmt = $pdo->prepare("
                SELECT landlord_id FROM properties WHERE property_id = ?
            ");
            $stmt->execute([$transaction['property_id']]);
            $landlordId = $stmt->fetchColumn();
            
            if ($landlordId) {
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, title, message, type, is_read, created_at
                    ) VALUES (
                        ?, 'Payment Received', 'Payment of " . formatCurrency($transaction['amount']) . " received for " . htmlspecialchars($transaction['property_name']) . "', 'payment', 0, NOW()
                    )
                ");
                $stmt->execute([$landlordId]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Clear pending transaction from session
            unset($_SESSION['pending_transaction_id']);
            
            $_SESSION['success'] = "Payment of " . formatCurrency($transaction['amount']) . " processed successfully via PayPal!";
            
        } else {
            // Payment failed
            $stmt = $pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'failed', gateway_response = ?, updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $stmt->execute([
                json_encode($result),
                $transaction['transaction_id']
            ]);
            
            $pdo->commit();
            $_SESSION['error'] = "PayPal payment failed. Please try again.";
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
} else {
    $_SESSION['error'] = "Invalid payment response from PayPal";
}

header("Location: payments.php");
exit;
?>