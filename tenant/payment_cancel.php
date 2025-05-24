<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require tenant role
requireRole('tenant');

$userId = $_SESSION['user_id'];

// Update any pending transactions to cancelled
if (isset($_SESSION['pending_transaction_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE payment_transactions 
            SET status = 'cancelled', updated_at = NOW()
            WHERE transaction_id = ?
        ");
        $stmt->execute([$_SESSION['pending_transaction_id']]);
        
        unset($_SESSION['pending_transaction_id']);
    } catch (PDOException $e) {
        // Log error but don't show to user
        error_log("Error updating cancelled transaction: " . $e->getMessage());
    }
}

$_SESSION['error'] = "Payment was cancelled. You can try again anytime.";
header("Location: payments.php");
exit;
?>