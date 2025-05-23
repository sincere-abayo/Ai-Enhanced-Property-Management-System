<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if maintenance request ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid maintenance request ID";
    header("Location: maintenance.php");
    exit;
}

$requestId = (int)$_GET['id'];

// Get maintenance request details
function getMaintenanceDetails($requestId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, p.property_name, p.address, p.city, p.state, p.landlord_id
        FROM maintenance_requests m
        JOIN properties p ON m.property_id = p.property_id
        WHERE m.request_id = :requestId AND p.landlord_id = :landlordId
    ");
    $stmt->execute([
        'requestId' => $requestId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
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

// Get maintenance request data
$maintenance = getMaintenanceDetails($requestId, $userId);

// Check if maintenance request exists and belongs to a property owned by the current landlord
if (!$maintenance) {
    $_SESSION['error'] = "Maintenance request not found or you don't have permission to edit it";
    header("Location: maintenance.php");
    exit;
}

// Get all properties
$properties = getLandlordProperties($userId);

// Get tenants for this property
$tenants = getPropertyTenants($maintenance['property_id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maintenance'])) {
    $propertyId = (int)$_POST['property_id'];
    $tenantId = isset($_POST['tenant_id']) && !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $estimatedCost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : null;
    
    // Validate input
    $errors = [];
    if (empty($propertyId)) $errors[] = "Property is required";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    
    // Verify property belongs to landlord
    $propertyExists = false;
    foreach ($properties as $property) {
        if ($property['property_id'] == $propertyId) {
            $propertyExists = true;
            break;
        }
    }
    
    if (!$propertyExists) {
        $errors[] = "Invalid property selected";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE maintenance_requests
                SET property_id = :propertyId,
                    tenant_id = :tenantId,
                    title = :title,
                    description = :description,
                    priority = :priority,
                    status = :status,
                    estimated_cost = :estimatedCost
                WHERE request_id = :requestId
            ");
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'tenantId' => $tenantId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'estimatedCost' => $estimatedCost,
                'requestId' => $requestId
            ]);
            
            // If maintenance is marked as high priority or emergency, update property status
            if (($priority === 'high' || $priority === 'emergency') && $status !== 'completed' && $status !== 'cancelled') {
                $updateStmt = $pdo->prepare("
                    UPDATE properties
                    SET status = 'maintenance'
                    WHERE property_id = :propertyId
                ");
                $updateStmt->execute(['propertyId' => $propertyId]);
            }
            
            $_SESSION['success'] = "Maintenance request updated successfully!";
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
    <title>Edit Maintenance Request - Property Management System</title>
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
            <a href="maintenance_details.php?id=<?php echo $requestId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Maintenance Request</h2>
                <p class="text-gray-600">Update maintenance request details</p>
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

        <!-- Edit Maintenance Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="edit_maintenance.php?id=<?php echo $requestId; ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" id="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['property_id']; ?>" <?php echo ($maintenance['property_id'] == $property['property_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($property['property_name'] . ' - ' . $property['address'] . ', ' . $property['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenant (Optional)</label>
                        <select name="tenant_id" id="tenant_id" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['user_id']; ?>" <?php echo ($maintenance['tenant_id'] == $tenant['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Issue Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($maintenance['title']); ?>" required placeholder="e.g., Leaking Faucet, Broken Heater" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="4" required placeholder="Provide detailed information about the maintenance issue..." class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"><?php echo htmlspecialchars($maintenance['description']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="low" <?php echo ($maintenance['priority'] === 'low') ? 'selected' : ''; ?>>Low - Can be scheduled</option>
                            <option value="medium" <?php echo ($maintenance['priority'] === 'medium') ? 'selected' : ''; ?>>Medium - Needs attention soon</option>
                            <option value="high" <?php echo ($maintenance['priority'] === 'high') ? 'selected' : ''; ?>>High - Urgent issue</option>
                            <option value="emergency" <?php echo ($maintenance['priority'] === 'emergency') ? 'selected' : ''; ?>>Emergency - Immediate attention required</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="pending" <?php echo ($maintenance['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="assigned" <?php echo ($maintenance['status'] === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo ($maintenance['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo ($maintenance['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($maintenance['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost (Optional)</label>
                        <input type="number" name="estimated_cost" value="<?php echo $maintenance['estimated_cost']; ?>" min="0" step="0.01" placeholder="0.00" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="maintenance_details.php?id=<?php echo $requestId; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="update_maintenance" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update Maintenance Request
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
                // Redirect to the same page with updated property_id parameter
                window.location.href = 'edit_maintenance.php?id=<?php echo $requestId; ?>&property_id=' + propertyId;
            }
        });
    </script>
</body>
</html>
