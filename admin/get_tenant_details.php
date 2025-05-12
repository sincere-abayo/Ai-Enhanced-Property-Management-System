<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize response
$response = ['error' => null, 'tenant' => null];

// Check if tenant ID is provided
if (!isset($_GET['tenant_id']) || !is_numeric($_GET['tenant_id'])) {
    $response['error'] = "Invalid tenant ID";
    echo json_encode($response);
    exit;
}

$tenantId = (int)$_GET['tenant_id'];

// Get tenant details
function getTenantDetails($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
        LIMIT 1
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get tenant details
$tenant = getTenantDetails($tenantId, $userId);

// Check if tenant was found
if (!$tenant) {
    $response['error'] = "Tenant not found or you don't have permission to message this tenant";
    echo json_encode($response);
    exit;
}

// Return tenant details
echo json_encode($tenant);
exit;