<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if property ID is provided
$propertyId = null;
if (isset($_GET['property_id']) && is_numeric($_GET['property_id'])) {
    $propertyId = (int)$_GET['property_id'];
}

// Get all properties owned by the landlord
function getLandlordProperties($landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT property_id, property_name, address, city, state
        FROM properties 
        WHERE landlord_id = :landlordId
        ORDER BY property_name
    ");
    $stmt->execute(['landlordId' => $landlordId]);
    
    return $stmt->fetchAll();
}

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

// Get tenants for a specific property
function getPropertyTenants($propertyId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        WHERE l.property_id = :propertyId AND l.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    return $stmt->fetchAll();
}

// Get all properties
$properties = getLandlordProperties($userId);

// If property ID is provided, get property details and tenants
$property = null;
$tenants = [];
if ($propertyId) {
    $property = getPropertyDetails($propertyId, $userId);
    
    // Check if property exists and belongs to the current landlord
    if (!$property) {
        $_SESSION['error'] = "Property not found or you don't have permission to add maintenance";
        header("Location: maintenance.php");
        exit;
    }
    
    // Get tenants for this property
    $tenants = getPropertyTenants($propertyId);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
    $selectedPropertyId = (int)$_POST['property_id'];
    $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $estimatedCost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : null;
    
    // Validate input
    $errors = [];
    if (empty($selectedPropertyId)) $errors[] = "Property is required";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    
    // Verify property belongs to landlord
    $selectedProperty = getPropertyDetails($selectedPropertyId, $userId);
    if (!$selectedProperty) {
        $errors[] = "Invalid property selected";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_requests (
                    property_id, tenant_id, title, description, 
                    priority, status, estimated_cost
                ) VALUES (
                    :propertyId, :tenantId, :title, :description,
                    :priority, 'pending', :estimatedCost
                )
            ");
            
            $stmt->execute([
                'propertyId' => $selectedPropertyId,
                'tenantId' => $tenantId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'estimatedCost' => $estimatedCost
            ]);
            
            $requestId = $pdo->lastInsertId();
            
            // If maintenance is marked as high priority or emergency, update property status
            if ($priority === 'high' || $priority === 'emergency') {
                $updateStmt = $pdo->prepare("
                    UPDATE properties
                    SET status = 'maintenance'
                    WHERE property_id = :propertyId
                ");
                $updateStmt->execute(['propertyId' => $selectedPropertyId]);
            }
            
            $_SESSION['success'] = "Maintenance request created successfully!";
            
            // Redirect to the maintenance details page
            header("Location: maintenance_details.php?id=" . $requestId);
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
    <title>Add Maintenance Request - Property Management System</title>
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
            <a href="maintenance.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Add Maintenance Request</h2>
                <p class="text-gray-600">Create a new maintenance request for your property</p>
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

        <!-- Add Maintenance Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="add_maintenance.php" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" id="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a property</option>
                            <?php foreach ($properties as $prop): ?>
                                <option value="<?php echo $prop['property_id']; ?>" <?php echo ($propertyId && $propertyId == $prop['property_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prop['property_name'] . ' - ' . $prop['address'] . ', ' . $prop['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenant (Optional)</label>
                        <select name="tenant_id" id="tenant_id" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['user_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select the property first to see available tenants</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Issue Title</label>
                        <input type="text" name="title" required placeholder="e.g., Leaking Faucet, Broken Heater" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="4" required placeholder="Provide detailed information about the maintenance issue..." class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="low">Low - Can be scheduled</option>
                            <option value="medium" selected>Medium - Needs attention soon</option>
                            <option value="high">High - Urgent issue</option>
                            <option value="emergency">Emergency - Immediate attention required</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost (Optional)</label>
                        <input type="number" name="estimated_cost" min="0" step="0.01" placeholder="0.00" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="maintenance.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="add_maintenance" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Create Maintenance Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fetch tenants when property is selected
        document.getElementById('property_id').addEventListener('change', function() {
            const propertyId = this.value;
            const tenantSelect = document.getElementById('tenant_id');
            
            // Clear current options
            while (tenantSelect.options.length > 1) {
                tenantSelect.remove(1);
            }
            
            if (propertyId) {
                // Redirect to the same page with property_id parameter
                window.location.href = 'add_maintenance.php?property_id=' + propertyId;
            }
        });
    </script>
</body>
</html>
