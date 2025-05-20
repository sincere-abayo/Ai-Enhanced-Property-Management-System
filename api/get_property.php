<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set headers to allow JSON response
header('Content-Type: application/json');

// Check if property ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid property ID'
    ]);
    exit;
}

$propertyId = (int)$_GET['id'];

try {
    // Get property details
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name
        FROM properties p
        JOIN users u ON p.landlord_id = u.user_id
        WHERE p.property_id = ?
    ");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$property) {
        echo json_encode([
            'success' => false,
            'message' => 'Property not found'
        ]);
        exit;
    }
    
    // Get additional images for this property
    $stmt = $pdo->prepare("
        SELECT image_id, image_path as path, caption
        FROM property_images
        WHERE property_id = ?
        ORDER BY is_primary DESC, upload_date ASC
    ");
    $stmt->execute([$propertyId]);
    $additionalImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add additional images to property data
    $property['additional_images'] = $additionalImages;
    
    echo json_encode([
        'success' => true,
        'property' => $property
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
