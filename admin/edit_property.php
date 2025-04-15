<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if property ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid property ID";
    header("Location: properties.php");
    exit;
}

$propertyId = (int)$_GET['id'];

// Get property details
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
    $_SESSION['error'] = "Property not found or you don't have permission to edit it";
    header("Location: properties.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_property'])) {
    $propertyName = trim($_POST['property_name']);
    $propertyType = $_POST['property_type'];
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zipCode = trim($_POST['zip_code']);
    $bedrooms = (int)$_POST['bedrooms'];
    $bathrooms = (float)$_POST['bathrooms'];
    $squareFeet = (int)$_POST['square_feet'];
    $monthlyRent = (float)$_POST['monthly_rent'];
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    // Validate input
    $errors = [];
    if (empty($propertyName)) $errors[] = "Property name is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($state)) $errors[] = "State is required";
    if (empty($zipCode)) $errors[] = "ZIP code is required";
    if ($monthlyRent <= 0) $errors[] = "Monthly rent must be greater than zero";
    
    // Handle image upload
    $imagePath = $property['image_path']; // Keep existing image by default
    
    if (isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        // Validate file type and size
        if (!in_array($_FILES['property_image']['type'], $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['property_image']['size'] > $maxSize) {
            $errors[] = "Image size should not exceed 2MB";
        } else {
            // Generate unique filename
            $fileExtension = pathinfo($_FILES['property_image']['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid('property_') . '.' . $fileExtension;
            $uploadDir = dirname(__DIR__) . '/uploads/properties/';
            
            // Make sure the upload directory exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $targetPath = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['property_image']['tmp_name'], $targetPath)) {
                // If successful, update image path
                $imagePath = 'uploads/properties/' . $newFilename;
                
                // Delete old image if it exists
                if (!empty($property['image_path']) && file_exists(dirname(__DIR__) . '/' . $property['image_path'])) {
                    unlink(dirname(__DIR__) . '/' . $property['image_path']);
                }
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE properties SET
                    property_name = :propertyName,
                    property_type = :propertyType,
                    address = :address,
                    city = :city,
                    state = :state,
                    zip_code = :zipCode,
                    bedrooms = :bedrooms,
                    bathrooms = :bathrooms,
                    square_feet = :squareFeet,
                    monthly_rent = :monthlyRent,
                    description = :description,
                    status = :status,
                    image_path = :imagePath
                WHERE property_id = :propertyId AND landlord_id = :landlordId
            ");
            
            $stmt->execute([
                'propertyName' => $propertyName,
                'propertyType' => $propertyType,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zipCode' => $zipCode,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'squareFeet' => $squareFeet,
                'monthlyRent' => $monthlyRent,
                'description' => $description,
                'status' => $status,
                'imagePath' => $imagePath,
                'propertyId' => $propertyId,
                'landlordId' => $userId
            ]);
            
            $_SESSION['success'] = "Property updated successfully!";
            header("Location: property_details.php?id=" . $propertyId);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property - Property Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1a56db',
                        secondary: '#7e3af2',
                        success: '#0ea5e9',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="property_details.php?id=<?php echo $propertyId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Property</h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($property['property_name']); ?></p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Edit Property Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="edit_property.php?id=<?php echo $propertyId; ?>" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Name</label>
                        <input type="text" name="property_name" value="<?php echo htmlspecialchars($property['property_name']); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                        <select name="property_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="apartment" <?php echo $property['property_type'] === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="house" <?php echo $property['property_type'] === 'house' ? 'selected' : ''; ?>>House</option>
                            <option value="condo" <?php echo $property['property_type'] === 'condo' ? 'selected' : ''; ?>>Condo</option>
                            <option value="studio" <?php echo $property['property_type'] === 'studio' ? 'selected' : ''; ?>>Studio</option>
                            <option value="commercial" <?php echo $property['property_type'] === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($property['address']); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($property['city']); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" value="<?php echo htmlspecialchars($property['state']); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code" value="<?php echo htmlspecialchars($property['zip_code']); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bedrooms</label>
                        <input type="number" name="bedrooms" value="<?php echo $property['bedrooms']; ?>" min="0" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bathrooms</label>
                        <input type="number" name="bathrooms" value="<?php echo $property['bathrooms']; ?>" min="0" step="0.5" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Square Feet</label>
                        <input type="number" name="square_feet" value="<?php echo $property['square_feet']; ?>" min="0" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <input type="number" name="monthly_rent" value="<?php echo $property['monthly_rent']; ?>" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Status</label>
                        <select name="status" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="vacant" <?php echo $property['status'] === 'vacant' ? 'selected' : ''; ?>>Vacant</option>
                            <option value="occupied" <?php echo $property['status'] === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="maintenance" <?php echo $property['status'] === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="4" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"><?php echo htmlspecialchars($property['description']); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property Image</label>
                    <?php if (!empty($property['image_path'])): ?>
                        <div class="mb-3">
                            <p class="text-sm text-gray-600 mb-2">Current Image:</p>
                            <img src="../<?php echo htmlspecialchars($property['image_path']); ?>" alt="Property Image" class="w-40 h-auto rounded-lg">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="property_image" accept="image/*" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    <p class="text-xs text-gray-500 mt-1">Upload a new image to replace the current one. Recommended size: 800x600px. Max file size: 2MB</p>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="property_details.php?id=<?php echo $propertyId; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="update_property" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update Property
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preview image before upload
        document.querySelector('input[name="property_image"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Check if there's already a preview image
                    let preview = document.querySelector('.image-preview');
                    
                    // If no preview exists, create one
                    if (!preview) {
                        const currentImage = document.querySelector('img.w-40');
                        if (currentImage) {
                            // Create preview element
                            preview = document.createElement('div');
                            preview.className = 'image-preview mt-3';
                            preview.innerHTML = `
                                <p class="text-sm text-gray-600 mb-2">New Image Preview:</p>
                                <img src="${event.target.result}" alt="Preview" class="w-40 h-auto rounded-lg">
                            `;
                            
                            // Insert after current image
                            currentImage.parentNode.appendChild(preview);
                        } else {
                            // No current image, create preview from scratch
                            preview = document.createElement('div');
                            preview.className = 'image-preview mt-3';
                            preview.innerHTML = `
                                <p class="text-sm text-gray-600 mb-2">Image Preview:</p>
                                <img src="${event.target.result}" alt="Preview" class="w-40 h-auto rounded-lg">
                            `;
                            
                            // Insert after file input
                            e.target.parentNode.appendChild(preview);
                        }
                    } else {
                        // Update existing preview
                        const previewImg = preview.querySelector('img');
                        previewImg.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
