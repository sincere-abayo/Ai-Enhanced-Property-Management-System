<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if tenant ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid tenant ID";
    header("Location: tenants.php");
    exit;
}

$tenantId = (int)$_GET['id'];

// Verify tenant belongs to this landlord
function verifyTenantOwnership($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

// Check if tenant belongs to this landlord
if (!verifyTenantOwnership($tenantId, $userId)) {
    $_SESSION['error'] = "Tenant not found or you don't have permission to delete this tenant";
    header("Location: tenants.php");
    exit;
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Delete tenant's payments
    $stmt = $pdo->prepare("
        DELETE p FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        WHERE l.tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    
    // Delete tenant's maintenance requests
    $stmt = $pdo->prepare("
        DELETE FROM maintenance_requests
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    
    // Delete tenant's leases
    $stmt = $pdo->prepare("
        DELETE FROM leases
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    
    // Delete tenant's notifications
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE user_id = ?
    ");
    $stmt->execute([$tenantId]);
    
    // Delete tenant user account
    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE user_id = ? AND role = 'tenant'
    ");
    $stmt->execute([$tenantId]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Tenant and all associated data have been deleted successfully";
    header("Location: tenants.php");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error'] = "An error occurred while deleting the tenant: " . $e->getMessage();
    header("Location: tenant_details.php?id=" . $tenantId);
    exit;
}
?>
