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

// Verify property belongs to the current landlord
$stmt = $pdo->prepare("
    SELECT * FROM properties 
    WHERE property_id = ? AND landlord_id = ?
");
$stmt->execute([$propertyId, $userId]);
$property = $stmt->fetch();

if (!$property) {
    $_SESSION['error'] = "Property not found or you don't have permission to access it";
    header("Location: properties.php");
    exit;
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    $caption = trim($_POST['caption'] ?? '');
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
    
    // Validate and upload image
    $imagePath = null;
    $errors = [];
    
    if (isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type and size
        if (!in_array($_FILES['property_image']['type'], $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['property_image']['size'] > $maxSize) {
            $errors[] = "Image size should not exceed 5MB";
        } else {
            // Generate unique filename
            $fileExtension = pathinfo($_FILES['property_image']['name'], PATHINFO_EXTENSION);
            $newFilename = uniqid('property_') . '.' . $fileExtension;
            
            // Create upload directory if it doesn't exist
            $uploadDir = dirname(__DIR__) . '/uploads/properties/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $targetPath = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['property_image']['tmp_name'], $targetPath)) {
                $imagePath = 'uploads/properties/' . $newFilename;
            } else {
                $uploadError = error_get_last();
                $errors[] = "Failed to upload image. Please try again. Error: " . ($uploadError ? $uploadError['message'] : 'Unknown error');
            }
        }
    } else {
        $errors[] = "Please select an image to upload";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            // If this is set as primary, unset any existing primary images
            if ($isPrimary) {
                $stmt = $pdo->prepare("
                    UPDATE property_images 
                    SET is_primary = 0 
                    WHERE property_id = ?
                ");
                $stmt->execute([$propertyId]);
                
                // Also update the main property image
                $stmt = $pdo->prepare("
                    UPDATE properties 
                    SET image_path = ? 
                    WHERE property_id = ?
                ");
                $stmt->execute([$imagePath, $propertyId]);
            }
            
            // Insert the new image
            $stmt = $pdo->prepare("
                INSERT INTO property_images (
                    property_id, image_path, is_primary, caption
                ) VALUES (
                    ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $propertyId,
                $imagePath,
                $isPrimary,
                $caption
            ]);
            
            $_SESSION['success'] = "Image uploaded successfully!";
            header("Location: property_gallery.php?id=$propertyId");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $imageId = (int)$_POST['image_id'];
    
    try {
        // Get image info first
        $stmt = $pdo->prepare("
            SELECT image_path, is_primary 
            FROM property_images 
            WHERE image_id = ? AND property_id = ?
        ");
        $stmt->execute([$imageId, $propertyId]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Delete the file from the server
            $fullPath = dirname(__DIR__) . '/' . $image['image_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("
                DELETE FROM property_images 
                WHERE image_id = ?
            ");
            $stmt->execute([$imageId]);
            
            // If this was the primary image, update the property
            if ($image['is_primary']) {
                $stmt = $pdo->prepare("
                    UPDATE properties 
                    SET image_path = NULL 
                    WHERE property_id = ? AND image_path = ?
                ");
                $stmt->execute([$propertyId, $image['image_path']]);
            }
            
            $_SESSION['success'] = "Image deleted successfully!";
        } else {
            $_SESSION['error'] = "Image not found";
        }
        
        header("Location: property_gallery.php?id=$propertyId");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Handle setting an image as primary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_primary'])) {
    $imageId = (int)$_POST['image_id'];
    
    try {
        // Get the image path
        $stmt = $pdo->prepare("
            SELECT image_path 
            FROM property_images 
            WHERE image_id = ? AND property_id = ?
        ");
        $stmt->execute([$imageId, $propertyId]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Unset all primary flags
            $stmt = $pdo->prepare("
                UPDATE property_images 
                SET is_primary = 0 
                WHERE property_id = ?
            ");
            $stmt->execute([$propertyId]);
            
            // Set this image as primary
            $stmt = $pdo->prepare("
                UPDATE property_images 
                SET is_primary = 1 
                WHERE image_id = ?
            ");
            $stmt->execute([$imageId]);
            
            // Update the main property image
            $stmt = $pdo->prepare("
                UPDATE properties 
                SET image_path = ? 
                WHERE property_id = ?
            ");
            $stmt->execute([$image['image_path'], $propertyId]);
            
            $_SESSION['success'] = "Primary image updated successfully!";
        } else {
            $_SESSION['error'] = "Image not found";
        }
        
        header("Location: property_gallery.php?id=$propertyId");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Get all images for this property
$stmt = $pdo->prepare("
    SELECT * FROM property_images 
    WHERE property_id = ? 
    ORDER BY is_primary DESC, upload_date DESC
");
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Gallery - <?php echo htmlspecialchars($property['property_name']); ?></title>
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
    <style>
        .image-container {
            position: relative;
            overflow: hidden;
            padding-bottom: 75%; /* 4:3 Aspect Ratio */
        }
        .image-container img {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-container:hover .image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Property Gallery</h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($property['property_name']); ?> - Manage Images</p>
            </div>
            <a href="properties.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-2"></i>Back to Properties
            </a>
        </div>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success']; ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['error']; ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Upload Image Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Upload New Image</h3>
            <form method="POST" action="property_gallery.php?id=<?php echo $propertyId; ?>" enctype="multipart/form-data" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image File</label>
                        <input type="file" name="property_image" accept="image/*" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Max file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Caption (Optional)</label>
                        <input type="text" name="caption" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="is_primary" name="is_primary" class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="is_primary" class="ml-2 text-sm text-gray-700">Set as primary image (will be displayed as the main property image)</label>
                </div>
                <div class="flex justify-end">
                    <button type="submit" name="upload_image" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-upload mr-2"></i>Upload Image
                    </button>
                </div>
            </form>
        </div>

        <!-- Image Gallery -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Property Images</h3>
            
                    <?php if (empty($images)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No images have been uploaded for this property yet.</p>
                    <p class="mt-2">Use the form above to add images to your property gallery.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($images as $image): ?>
                        <div class="rounded-lg overflow-hidden shadow-md">
                            <div class="image-container">
                                <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($image['caption'] ?: 'Property image'); ?>">
                                <div class="image-overlay">
                                    <div class="flex space-x-2 mb-2">
                                        <?php if (!$image['is_primary']): ?>
                                            <form method="POST" action="property_gallery.php?id=<?php echo $propertyId; ?>">
                                                <input type="hidden" name="image_id" value="<?php echo $image['image_id']; ?>">
                                                <button type="submit" name="set_primary" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600" title="Set as primary image">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="property_gallery.php?id=<?php echo $propertyId; ?>" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                            <input type="hidden" name="image_id" value="<?php echo $image['image_id']; ?>">
                                            <button type="submit" name="delete_image" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" title="Delete image">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 bg-gray-50">
                                <?php if ($image['is_primary']): ?>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mb-2">Primary Image</span>
                                <?php endif; ?>
                                <p class="text-sm text-gray-700 truncate"><?php echo htmlspecialchars($image['caption'] ?: 'No caption'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($image['upload_date'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Preview image before upload
        const imageInput = document.querySelector('input[name="property_image"]');
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // You could add image preview functionality here if desired
                        console.log('Image selected:', file.name);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
