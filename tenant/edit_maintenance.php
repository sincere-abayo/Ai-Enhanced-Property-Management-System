<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

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
$stmt = $pdo->prepare("
    SELECT mr.*, 
           p.property_name, p.property_id
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.request_id = ? AND mr.tenant_id = ?
");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

// If request not found or doesn't belong to this tenant, redirect
if (!$request) {
    $_SESSION['error'] = "Maintenance request not found or you don't have permission to edit it";
    header("Location: maintenance.php");
    exit;
}

// Check if request is in a status that can be edited (only pending requests can be edited)
if ($request['status'] !== 'pending') {
    $_SESSION['error'] = "Only pending maintenance requests can be edited";
    header("Location: maintenance_details.php?id=" . $requestId);
    exit;
}

// Get tenant's properties
$stmt = $pdo->prepare("
    SELECT p.property_id, p.property_name, u.unit_id, u.unit_number
    FROM properties p
    JOIN leases l ON p.property_id = l.property_id
    LEFT JOIN units u ON l.unit_id = u.unit_id
    WHERE l.tenant_id = ? AND l.status = 'active'
    ORDER BY p.property_name, u.unit_number
");
$stmt->execute([$userId]);
$properties = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $propertyId = (int)$_POST['property_id'];
    $unitId = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($propertyId)) {
        $errors[] = "Property is required";
    }
    
    // Verify property belongs to tenant
    $propertyBelongsToTenant = false;
    foreach ($properties as $property) {
        if ($property['property_id'] == $propertyId) {
            $propertyBelongsToTenant = true;
            break;
        }
    }
    
    if (!$propertyBelongsToTenant) {
        $errors[] = "Invalid property selected";
    }
    
    // If no errors, update the maintenance request
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE maintenance_requests
                SET title = :title,
                    description = :description,
                    property_id = :propertyId,
                    unit_id = :unitId,
                    priority = :priority,
                    updated_at = NOW()
                WHERE request_id = :requestId AND tenant_id = :tenantId
            ");
            
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'propertyId' => $propertyId,
                'unitId' => $unitId,
                'priority' => $priority,
                'requestId' => $requestId,
                'tenantId' => $userId
            ]);
            
            // Create notification for landlord
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'maintenance', 0, NOW()
                )
            ");
            
            // Get landlord ID
            $stmt2 = $pdo->prepare("
                SELECT landlord_id 
                FROM properties 
                WHERE property_id = ?
            ");
            $stmt2->execute([$propertyId]);
            $landlordId = $stmt2->fetchColumn();
            
            if ($landlordId) {
                $stmt->execute([
                    'userId' => $landlordId,
                    'title' => 'Maintenance request updated',
                    'message' => 'A tenant has updated maintenance request #' . $requestId
                ]);
            }
            
            $_SESSION['success'] = "Maintenance request updated successfully";
            header("Location: maintenance_details.php?id=" . $requestId);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get units for the selected property (for AJAX)
if (isset($_GET['get_units']) && is_numeric($_GET['get_units'])) {
    $propertyId = (int)$_GET['get_units'];
    
    $stmt = $pdo->prepare("
        SELECT u.unit_id, u.unit_number
        FROM units u
        JOIN leases l ON u.unit_id = l.unit_id
        WHERE u.property_id = ? AND l.tenant_id = ? AND l.status = 'active'
        ORDER BY u.unit_number
    ");
    $stmt->execute([$propertyId, $userId]);
    $units = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($units);
    exit;
}

// Check for error and success messages
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;

// Clear session messages
unset($_SESSION['error']);
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Maintenance Request - Tenant Portal</title>
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
    <?php include 'tenant_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="maintenance_details.php?id=<?php echo $requestId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Maintenance Request</h2>
                <p class="text-gray-600">Update the details of your maintenance request</p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <form method="POST" action="edit_maintenance.php?id=<?php echo $requestId; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            value="<?php echo htmlspecialchars($request['title']); ?>" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                    </div>
                    
                    <div>
                        <label for="property_id" class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select 
                            id="property_id" 
                            name="property_id" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                            <option value="">Select Property</option>
                            <?php foreach ($properties as $property): ?>
                                <option 
                                    value="<?php echo $property['property_id']; ?>" 
                                    <?php echo ($property['property_id'] == $request['property_id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($property['property_name']); ?>
                                    <?php if ($property['unit_number']): ?>
                                        - Unit <?php echo htmlspecialchars($property['unit_number']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select 
                            id="priority" 
                            name="priority" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                            <option value="low" <?php echo ($request['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo ($request['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo ($request['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="emergency" <?php echo ($request['priority'] == 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="6" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        ><?php echo htmlspecialchars($request['description']); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            Please provide detailed information about the issue, including when it started and any troubleshooting you've already tried.
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <a 
                        href="maintenance_details.php?id=<?php echo $requestId; ?>" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Cancel
                    </a>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                    >
                        Update Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle property selection to load units
        document.getElementById('property_id').addEventListener('change', function() {
            const propertyId = this.value;
            if (propertyId) {
                fetch(`edit_maintenance.php?get_units=${propertyId}`)
                    .then(response => response.json())
                    .then(units => {
                        const unitSelect = document.getElementById('unit_id');
                        unitSelect.innerHTML = '<option value="">Select Unit</option>';
                        
                        units.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.unit_id;
                            option.textContent = `Unit ${unit.unit_number}`;
                            unitSelect.appendChild(option);
                        });
                        
                        // If there are no units, disable the select
                        unitSelect.disabled = units.length === 0;
                    })
                    .catch(error => console.error('Error loading units:', error));
            }
        });
    </script>
</body>
</html>