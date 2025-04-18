<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Handle property filters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$propertyType = isset($_GET['property_type']) ? $_GET['property_type'] : 'all';
$priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get properties
function getProperties($landlordId, $status = 'all', $propertyType = 'all', $priceRange = 'all', $search = '') {
    global $pdo;
    
    $query = "
        SELECT 
            p.*, 
            COUNT(DISTINCT l.lease_id) as tenant_count
        FROM 
            properties p
        LEFT JOIN 
            leases l ON p.property_id = l.property_id AND l.status = 'active'
        WHERE 
            p.landlord_id = :landlordId
    ";
    
    $params = ['landlordId' => $landlordId];
    
    // Add filters
    if ($status !== 'all') {
        $query .= " AND p.status = :status";
        $params['status'] = $status;
    }
    
    if ($propertyType !== 'all') {
        $query .= " AND p.property_type = :propertyType";
        $params['propertyType'] = $propertyType;
    }
    
    if ($priceRange !== 'all') {
        switch ($priceRange) {
            case '0-500':
                $query .= " AND p.monthly_rent BETWEEN 0 AND 500";
                break;
            case '501-1000':
                $query .= " AND p.monthly_rent BETWEEN 501 AND 1000";
                break;
            case '1001+':
                $query .= " AND p.monthly_rent > 1000";
                break;
        }
    }
    
    if (!empty($search)) {
        $query .= " AND (p.property_name LIKE :search OR p.address LIKE :search OR p.city LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    $query .= " GROUP BY p.property_id ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Handle add property form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_property'])) {
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
$imagePath = null;
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
        
  // Use:
$uploadDir = dirname(__DIR__) . '/uploads/properties/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $targetPath = $uploadDir . $newFilename;
        
        // Debug information
        error_log("Attempting to upload file to: " . $targetPath);
        error_log("File tmp_name: " . $_FILES['property_image']['tmp_name']);
        error_log("File exists: " . (file_exists($_FILES['property_image']['tmp_name']) ? 'Yes' : 'No'));
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['property_image']['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/properties/' . $newFilename;
            error_log("File uploaded successfully to: " . $targetPath);
        } else {
            $uploadError = error_get_last();
            error_log("Upload failed: " . ($uploadError ? $uploadError['message'] : 'Unknown error'));
            $errors[] = "Failed to upload image. Please try again. Error: " . ($uploadError ? $uploadError['message'] : 'Unknown error');
        }
    }
}

    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO properties (
                    landlord_id, property_name, address, city, state, zip_code, 
                    property_type, bedrooms, bathrooms, square_feet, monthly_rent, 
                    description, status, image_path
                ) VALUES (
                    :landlordId, :propertyName, :address, :city, :state, :zipCode,
                    :propertyType, :bedrooms, :bathrooms, :squareFeet, :monthlyRent,
                    :description, :status, :imagePath
                )
            ");
            
            $stmt->execute([
                'landlordId' => $userId,
                'propertyName' => $propertyName,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zipCode' => $zipCode,
                'propertyType' => $propertyType,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'squareFeet' => $squareFeet,
                'monthlyRent' => $monthlyRent,
                'description' => $description,
                'status' => $status,
                'imagePath' => $imagePath
            ]);
            
            $_SESSION['success'] = "Property added successfully!";
            header("Location: properties.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get properties based on filters
$properties = getProperties($userId, $status, $propertyType, $priceRange, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - Property Management System</title>
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
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Properties</h2>
                <p class="text-gray-600">Manage your rental properties</p>
            </div>
            <button onclick="openAddPropertyModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Add Property
            </button>
        </div>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success']; ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
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

        <!-- Property Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" action="properties.php">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="occupied" <?php echo $status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="vacant" <?php echo $status === 'vacant' ? 'selected' : ''; ?>>Vacant</option>
                            <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                        <select name="property_type" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="all" <?php echo $propertyType === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="apartment" <?php echo $propertyType === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="house" <?php echo $propertyType === 'house' ? 'selected' : ''; ?>>House</option>
                            <option value="condo" <?php echo $propertyType === 'condo' ? 'selected' : ''; ?>>Condo</option>
                            <option value="studio" <?php echo $propertyType === 'studio' ? 'selected' : ''; ?>>Studio</option>
                            <option value="commercial" <?php echo $propertyType === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price Range</label>
                        <select name="price_range" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="all" <?php echo $priceRange === 'all' ? 'selected' : ''; ?>>Any Price</option>
                            <option value="0-500" <?php echo $priceRange === '0-500' ? 'selected' : ''; ?>>$0 - $500</option>
                            <option value="501-1000" <?php echo $priceRange === '501-1000' ? 'selected' : ''; ?>>$501 - $1000</option>
                            <option value="1001+" <?php echo $priceRange === '1001+' ? 'selected' : ''; ?>>$1001+</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="flex">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search properties..." class="w-full rounded-l-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-r-lg hover:bg-blue-700">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Properties Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($properties)): ?>
                <div class="col-span-3 text-center py-8">
                    <p class="text-gray-500">No properties found. Add your first property!</p>
                </div>
            <?php else: ?>
                <?php foreach ($properties as $property): ?>
                    <!-- Property Card -->
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="relative">
    <?php if (!empty($property['image_path'])): ?>
        <img src="../<?php echo htmlspecialchars($property['image_path']); ?>" alt="<?php echo htmlspecialchars($property['property_name']); ?>" class="w-full h-48 object-cover">
    <?php else: ?>
        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Property" class="w-full h-48 object-cover">
    <?php endif; ?>
    <div class="absolute top-4 right-4">
        <?php if ($property['status'] === 'occupied'): ?>
            <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm">Occupied</span>
        <?php elseif ($property['status'] === 'vacant'): ?>
            <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm">Vacant</span>
        <?php else: ?>
            <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm">Maintenance</span>
        <?php endif; ?>
    </div>
</div>

                        <div class="p-6">
                            <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($property['property_name']); ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($property['address']); ?>, <?php echo htmlspecialchars($property['city']); ?></p>
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <p class="text-sm text-gray-500">Monthly Rent</p>
                                    <p class="text-lg font-semibold">$<?php echo number_format($property['monthly_rent'], 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Tenants</p>
                                    <p class="text-lg font-semibold"><?php echo $property['tenant_count']; ?></p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                            <a href="property_details.php?id=<?php echo $property['property_id']; ?>" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-center">
                                View Details
                            </a>

                                <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Property Modal -->
    <div id="addPropertyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4 max-h-90vh overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Add New Property</h3>
                <button onclick="closeAddPropertyModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="properties.php" enctype="multipart/form-data" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Name</label>
                        <input type="text" name="property_name" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                        <select name="property_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="apartment">Apartment</option>
                            <option value="house">House</option>
                            <option value="condo">Condo</option>
                            <option value="studio">Studio</option>
                            <option value="commercial">Commercial</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bedrooms</label>
                        <input type="number" name="bedrooms" min="0" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bathrooms</label>
                        <input type="number" name="bathrooms" min="0" step="0.5" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Square Feet</label>
                        <input type="number" name="square_feet" min="0" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <input type="number" name="monthly_rent" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Status</label>
                        <select name="status" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="vacant">Vacant</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Under Maintenance</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                </div>
                <!-- Add this new field for image upload -->
         <div class="col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Property Image</label>
        <input type="file" name="property_image" accept="image/*" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
        <p class="text-xs text-gray-500 mt-1">Recommended size: 800x600px. Max file size: 2MB</p>
         </div>
          
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeAddPropertyModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="add_property" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Add Property
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddPropertyModal() {
            document.getElementById('addPropertyModal').classList.remove('hidden');
            document.getElementById('addPropertyModal').classList.add('flex');
        }

        function closeAddPropertyModal() {
            document.getElementById('addPropertyModal').classList.add('hidden');
            document.getElementById('addPropertyModal').classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('addPropertyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddPropertyModal();
            }
        });
    </script>
</body>
</html>
