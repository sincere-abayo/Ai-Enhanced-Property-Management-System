<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Require tenant role
requireRole('tenant');

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$updated = false;

try {
    // Check for any pending transactions that might have been updated
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count
        FROM payment_transactions pt
        JOIN leases l ON pt.lease_id = l.lease_id
        WHERE l.tenant_id = ? AND pt.status = 'pending' AND pt.updated_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    if ($result['pending_count'] == 0) {
        // No pending transactions, check if any were recently completed
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_count
            FROM payment_transactions pt
            JOIN leases l ON pt.lease_id = l.lease_id
            WHERE l.tenant_id = ? AND pt.status IN ('successful', 'failed') AND pt.updated_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($result['recent_count'] > 0) {
            $updated = true;
        }
    }
    
} catch (PDOException $e) {
    // Log error but don't expose to client
    error_log("Payment status check error: " . $e->getMessage());
}

echo json_encode(['updated' => $updated]);
?>