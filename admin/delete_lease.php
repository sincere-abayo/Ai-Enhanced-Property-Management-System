<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if lease ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid lease ID";
    header("Location: leases.php");
    exit;
}

$leaseId = (int)$_GET['id'];

// Verify lease belongs to a property owned by this landlord
$stmt = $pdo->prepare("
    SELECT l.lease_id, l.property_id, p.landlord_id
    FROM leases l
    JOIN properties p ON l.property_id = p.property_id
    WHERE l.lease_id = :leaseId AND p.landlord_id = :landlordId
");
$stmt->execute([
    'leaseId' => $leaseId,
    'landlordId' => $userId
]);

$lease = $stmt->fetch();

if (!$lease) {
    $_SESSION['error'] = "Lease not found or you don't have permission to delete it";
    header("Location: leases.php");
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete payments associated with this lease
    $stmt = $pdo->prepare("DELETE FROM payments WHERE lease_id = :leaseId");
    $stmt->execute(['leaseId' => $leaseId]);
    
    // Delete the lease
    $stmt = $pdo->prepare("DELETE FROM leases WHERE lease_id = :leaseId");
    $stmt->execute(['leaseId' => $leaseId]);
    
    // Check if there are other active leases for this property
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM leases
        WHERE property_id = :propertyId AND status = 'active'
    ");
    $stmt->execute(['propertyId' => $lease['property_id']]);
    
    if ($stmt->fetch()['count'] == 0) {
        // No other active leases, update property status to vacant
        $stmt = $pdo->prepare("
            UPDATE properties
            SET status = 'vacant'
            WHERE property_id = :propertyId
        ");
        $stmt->execute(['propertyId' => $lease['property_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Lease deleted successfully!";
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error'] = "Error deleting lease: " . $e->getMessage();
}

header("Location: leases.php");
exit;
?>
