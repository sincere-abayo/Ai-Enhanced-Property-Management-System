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
        SELECT m.*, p.property_name, p.address, p.city, p.state, p.zip_code, p.landlord_id,
               u.first_name as tenant_first_name, u.last_name as tenant_last_name, u.email as tenant_email,
               u.phone as tenant_phone
        FROM maintenance_requests m
        JOIN properties p ON m.property_id = p.property_id
        LEFT JOIN users u ON m.tenant_id = u.user_id
        WHERE m.request_id = :requestId AND p.landlord_id = :landlordId
    ");
    $stmt->execute([
        'requestId' => $requestId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get maintenance tasks for this request
function getMaintenanceTasks($requestId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name
        FROM maintenance_tasks t
        LEFT JOIN users u ON t.assigned_to = u.user_id
        WHERE t.request_id = :requestId
        ORDER BY t.created_at DESC
    ");
    $stmt->execute(['requestId' => $requestId]);
    
    return $stmt->fetchAll();
}

// Get available maintenance staff
function getMaintenanceStaff() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name
        FROM users
        WHERE role = 'landlord' OR role = 'admin'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get maintenance request data
$maintenance = getMaintenanceDetails($requestId, $userId);

// Check if maintenance request exists and belongs to a property owned by the current landlord
if (!$maintenance) {
    $_SESSION['error'] = "Maintenance request not found or you don't have permission to view it";
    header("Location: maintenance.php");
    exit;
}

// Get maintenance tasks
$tasks = getMaintenanceTasks($requestId);

// Get maintenance staff
$staff = getMaintenanceStaff();

// Process status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $actualCost = !empty($_POST['actual_cost']) ? (float)$_POST['actual_cost'] : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE maintenance_requests
            SET status = :status, 
                actual_cost = :actualCost,
                completed_at = :completedAt
            WHERE request_id = :requestId
        ");
        
        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            'status' => $newStatus,
            'actualCost' => $actualCost,
            'completedAt' => $completedAt,
            'requestId' => $requestId
        ]);
        
        // If status is completed, update property status if needed
        if ($newStatus === 'completed') {
            // Check if there are other active maintenance requests for this property
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM maintenance_requests
                WHERE property_id = :propertyId 
                AND status != 'completed' 
                AND status != 'cancelled'
            ");
            $stmt->execute(['propertyId' => $maintenance['property_id']]);
            
            if ($stmt->fetch()['count'] == 0) {
                // No other active maintenance requests, update property status
                $stmt = $pdo->prepare("
                    UPDATE properties
                    SET status = CASE 
                        WHEN (SELECT COUNT(*) FROM leases WHERE property_id = :propertyId AND status = 'active') > 0 
                        THEN 'occupied' 
                        ELSE 'vacant' 
                    END
                    WHERE property_id = :propertyId
                ");
                $stmt->execute(['propertyId' => $maintenance['property_id']]);
            }
        }
        
        $_SESSION['success'] = "Maintenance request status updated successfully!";
        header("Location: maintenance_details.php?id=" . $requestId);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
}

// Process add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $description = trim($_POST['task_description']);
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    
    if (empty($description)) {
        $_SESSION['error'] = "Task description is required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_tasks (
                    request_id, assigned_to, description, status
                ) VALUES (
                    :requestId, :assignedTo, :description, 'pending'
                )
            ");
            
            $stmt->execute([
                'requestId' => $requestId,
                'assignedTo' => $assignedTo,
                'description' => $description
            ]);
            
            $_SESSION['success'] = "Task added successfully!";
            header("Location: maintenance_details.php?id=" . $requestId);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding task: " . $e->getMessage();
        }
    }
}

// Process update task status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $taskId = (int)$_POST['task_id'];
    $taskStatus = $_POST['task_status'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE maintenance_tasks
            SET status = :status,
                completed_at = :completedAt
            WHERE task_id = :taskId AND request_id = :requestId
        ");
        
        $completedAt = ($taskStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        
        $stmt->execute([
            'status' => $taskStatus,
            'completedAt' => $completedAt,
            'taskId' => $taskId,
            'requestId' => $requestId
        ]);
        
        $_SESSION['success'] = "Task status updated successfully!";
        header("Location: maintenance_details.php?id=" . $requestId);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating task status: " . $e->getMessage();
    }
}

// Get priority class for styling
function getPriorityClass($priority) {
    switch ($priority) {
        case 'low':
            return 'bg-blue-100 text-blue-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'high':
            return 'bg-orange-100 text-orange-800';
        case 'emergency':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get status class for styling
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'assigned':
            return 'bg-blue-100 text-blue-800';
        case 'in_progress':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Request Details - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Maintenance Request Details</h2>
                <p class="text-gray-600">ID: #<?php echo $requestId; ?></p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Maintenance Request Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Main Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-start mb-6">
                        <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($maintenance['title']); ?></h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusClass($maintenance['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $maintenance['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Description</h4>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($maintenance['description'])); ?></p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Property</h4>
                            <p class="text-gray-700"><?php echo htmlspecialchars($maintenance['property_name']); ?></p>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($maintenance['address'] . ', ' . $maintenance['city'] . ', ' . $maintenance['state'] . ' ' . $maintenance['zip_code']); ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Priority</h4>
                            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?php echo getPriorityClass($maintenance['priority']); ?>">
                                <?php echo ucfirst($maintenance['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Created</h4>
                            <p class="text-gray-700"><?php echo date('M j, Y', strtotime($maintenance['created_at'])); ?></p>
                            <p class="text-sm text-gray-600"><?php echo date('g:i A', strtotime($maintenance['created_at'])); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Estimated Cost</h4>
                            <p class="text-gray-700">
                                <?php echo $maintenance['estimated_cost'] ? '$' . number_format($maintenance['estimated_cost'], 2) : 'Not specified'; ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Actual Cost</h4>
                            <p class="text-gray-700">
                                <?php echo $maintenance['actual_cost'] ? '$' . number_format($maintenance['actual_cost'], 2) : 'Not specified'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($maintenance['completed_at']): ?>
                        <div class="mb-6">
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Completed</h4>
                            <p class="text-gray-700"><?php echo date('M j, Y g:i A', strtotime($maintenance['completed_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                                     <!-- Update Status Form -->
                                     <div class="border-t pt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Update Request Status</h4>
                        <form method="POST" action="maintenance_details.php?id=<?php echo $requestId; ?>" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                        <option value="pending" <?php echo $maintenance['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="assigned" <?php echo $maintenance['status'] === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                        <option value="in_progress" <?php echo $maintenance['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $maintenance['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $maintenance['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Actual Cost (if completed)</label>
                                    <input type="number" name="actual_cost" value="<?php echo $maintenance['actual_cost']; ?>" min="0" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="update_status" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                                    Update Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Details -->
            <div>
                <!-- Tenant Information -->
                <?php if ($maintenance['tenant_id']): ?>
                    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                        <h3 class="text-lg font-semibold mb-4">Tenant Information</h3>
                        <div class="space-y-3">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Name</h4>
                                <p class="text-gray-700">
                                    <?php echo htmlspecialchars($maintenance['tenant_first_name'] . ' ' . $maintenance['tenant_last_name']); ?>
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 mb-1">Email</h4>
                                <p class="text-gray-700"><?php echo htmlspecialchars($maintenance['tenant_email']); ?></p>
                            </div>
                            <?php if ($maintenance['tenant_phone']): ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500 mb-1">Phone</h4>
                                    <p class="text-gray-700"><?php echo htmlspecialchars($maintenance['tenant_phone']); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="pt-3">
                                <a href="tenant_details.php?id=<?php echo $maintenance['tenant_id']; ?>" class="text-primary hover:text-blue-700 text-sm font-medium">
                                    View Tenant Profile <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="property_details.php?id=<?php echo $maintenance['property_id']; ?>" class="block w-full text-center px-4 py-2 bg-blue-50 text-primary rounded-lg hover:bg-blue-100">
                            View Property
                        </a>
                        <button onclick="document.getElementById('addTaskModal').classList.remove('hidden')" class="block w-full text-center px-4 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                            Add Task
                        </button>
                        <?php if ($maintenance['status'] !== 'completed' && $maintenance['status'] !== 'cancelled'): ?>
                            <form method="POST" action="maintenance_details.php?id=<?php echo $requestId; ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" name="update_status" class="block w-full text-center px-4 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                                    Mark as Completed
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Tasks -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold">Maintenance Tasks</h3>
                <button onclick="document.getElementById('addTaskModal').classList.remove('hidden')" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add Task
                </button>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No tasks have been added yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Add tasks to track the progress of this maintenance request.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-normal">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['description']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($task['assigned_to']): ?>
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($task['status'] !== 'completed'): ?>
                                            <form method="POST" action="maintenance_details.php?id=<?php echo $requestId; ?>" class="inline-block">
                                                <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                <input type="hidden" name="task_status" value="completed">
                                                <button type="submit" name="update_task_status" class="text-green-600 hover:text-green-900 mr-3">
                                                    Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="#" class="text-primary hover:text-blue-700">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Task Modal -->
    <div id="addTaskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-lg w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Add Maintenance Task</h3>
                <button onclick="document.getElementById('addTaskModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="maintenance_details.php?id=<?php echo $requestId; ?>" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Task Description</label>
                    <textarea name="task_description" rows="3" required placeholder="Describe what needs to be done..." class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign To (Optional)</label>
                    <select name="assigned_to" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">Select staff member</option>
                        <?php foreach ($staff as $person): ?>
                            <option value="<?php echo $person['user_id']; ?>">
                                <?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="document.getElementById('addTaskModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="add_task" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Add Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        document.getElementById('addTaskModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
