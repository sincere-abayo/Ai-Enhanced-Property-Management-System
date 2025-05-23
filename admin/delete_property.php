<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if property ID is provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['property_id']) || !is_numeric($_POST['property_id'])) {
    $_SESSION['error'] = "Invalid request";
    header("Location: properties.php");
    exit;
}

$propertyId = (int)$_POST['property_id'];

// Verify property exists and belongs to the current landlord
function getPropertyDetails($propertyId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM properties 
        WHERE property_id = :propertyId AND landlord_id = :landlordId
    ");
    $stmt->execute([
        'propertyId' => $propertyId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get property data
$property = getPropertyDetails($propertyId, $userId);

// Check if property exists and belongs to the current landlord
if (!$property) {
    $_SESSION['error'] = "Property not found or you don't have permission to delete it";
    header("Location: properties.php");
    exit;
}

// Check if property has active leases
function hasActiveLeases($propertyId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM leases
        WHERE property_id = :propertyId AND status = 'active'
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    return $stmt->fetch()['count'] > 0;
}

// If property has active leases, don't allow deletion
if (hasActiveLeases($propertyId)) {
    $_SESSION['error'] = "Cannot delete property with active leases. Please terminate all leases first.";
    header("Location: property_details.php?id=" . $propertyId);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete maintenance requests associated with the property
    $stmt = $pdo->prepare("
        DELETE FROM maintenance_requests
        WHERE property_id = :propertyId
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    // Delete payments associated with leases for this property
    $stmt = $pdo->prepare("
        DELETE FROM payments
        WHERE lease_id IN (
            SELECT lease_id FROM leases WHERE property_id = :propertyId
        )
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    // Delete leases associated with the property
    $stmt = $pdo->prepare("
        DELETE FROM leases
        WHERE property_id = :propertyId
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    // Delete the property
    $stmt = $pdo->prepare("
        DELETE FROM properties
        WHERE property_id = :propertyId AND landlord_id = :landlordId
    ");
    $stmt->execute([
        'propertyId' => $propertyId,
        'landlordId' => $userId
    ]);
    
    // Delete property image if it exists
    if (!empty($property['image_path']) && file_exists(dirname(__DIR__) . '/' . $property['image_path'])) {
        unlink(dirname(__DIR__) . '/' . $property['image_path']);
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "Property deleted successfully";
    header("Location: properties.php");
    exit;
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error'] = "Error deleting property: " . $e->getMessage();
    header("Location: property_details.php?id=" . $propertyId);
    exit;
}
