<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for maintenance requests
$query = "
    SELECT 
        m.request_id,
        m.title,
        m.description,
        m.priority,
        m.status,
        m.created_at,
        m.estimated_cost,
        m.ai_priority_score,
        p.property_id,
        p.property_name,
        u.user_id AS tenant_id,
        u.first_name,
        u.last_name,
        COALESCE(u2.first_name, '') AS assigned_first_name,
        COALESCE(u2.last_name, '') AS assigned_last_name
    FROM maintenance_requests m
    JOIN properties p ON m.property_id = p.property_id
    JOIN users u ON m.tenant_id = u.user_id
    LEFT JOIN maintenance_tasks t ON m.request_id = t.request_id
    LEFT JOIN users u2 ON t.assigned_to = u2.user_id
    WHERE p.landlord_id = :landlordId
";

// Add filters
$params = ['landlordId' => $userId];

if (!empty($status)) {
    $query .= " AND m.status = :status";
    $params['status'] = $status;
}

if (!empty($priority)) {
    $query .= " AND m.priority = :priority";
    $params['priority'] = $priority;
}

if ($propertyId > 0) {
    $query .= " AND p.property_id = :propertyId";
    $params['propertyId'] = $propertyId;
}

if (!empty($search)) {
    $query .= " AND (m.title LIKE :search OR m.description LIKE :search OR p.property_name LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY 
    CASE 
        WHEN m.status = 'pending' THEN 1
        WHEN m.status = 'assigned' THEN 2
        WHEN m.status = 'in_progress' THEN 3
        WHEN m.status = 'completed' THEN 4
        WHEN m.status = 'cancelled' THEN 5
    END,
    CASE 
        WHEN m.priority = 'emergency' THEN 1
        WHEN m.priority = 'high' THEN 2
        WHEN m.priority = 'medium' THEN 3
        WHEN m.priority = 'low' THEN 4
    END,
    m.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenanceRequests = $stmt->fetchAll();

// Get maintenance summary counts
function getMaintenanceCounts($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN m.status IN ('pending', 'assigned') THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN m.priority = 'emergency' OR m.priority = 'high' THEN 1 ELSE 0 END) AS urgent
        FROM maintenance_requests m
        JOIN properties p ON m.property_id = p.property_id
        WHERE p.landlord_id = :landlordId
    ");
    
    $stmt->execute(['landlordId' => $userId]);
    return $stmt->fetch();
}


$counts = getMaintenanceCounts($userId);

// Get properties for filter dropdown
$stmt = $pdo->prepare("
    SELECT property_id, property_name
    FROM properties
    WHERE landlord_id = :landlordId
    ORDER BY property_name
");
$stmt->execute(['landlordId' => $userId]);
$properties = $stmt->fetchAll();

// Get tenants for new request form
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.first_name, u.last_name
    FROM users u
    JOIN leases l ON u.user_id = l.tenant_id
    JOIN properties p ON l.property_id = p.property_id
    WHERE p.landlord_id = :landlordId AND l.status = 'active'
    ORDER BY u.first_name, u.last_name
");
$stmt->execute(['landlordId' => $userId]);
$tenants = $stmt->fetchAll();

// Process form submission for new maintenance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    $propertyId = (int)$_POST['property_id'];
    $tenantId = (int)$_POST['tenant_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $estimatedCost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : null;
    
    // Validate input
    $errors = [];
    if (empty($propertyId)) $errors[] = "Property is required";
    if (empty($tenantId)) $errors[] = "Tenant is required";
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    
    // Verify property belongs to this landlord
    if ($propertyId > 0) {
        $stmt = $pdo->prepare("SELECT property_id FROM properties WHERE property_id = :propertyId AND landlord_id = :landlordId");
        $stmt->execute(['propertyId' => $propertyId, 'landlordId' => $userId]);
        if ($stmt->rowCount() === 0) {
            $errors[] = "Invalid property selected";
        }
    }
    
    if (empty($errors)) {
        try {
            // Calculate AI priority score (simplified example)
            $aiPriorityScore = 0;
            if ($priority === 'emergency') $aiPriorityScore = 90;
            elseif ($priority === 'high') $aiPriorityScore = 70;
            elseif ($priority === 'medium') $aiPriorityScore = 50;
            else $aiPriorityScore = 30;
            
            // Add keywords that might increase priority
            $keywordBoost = 0;
            $urgentKeywords = ['leak', 'water', 'flood', 'fire', 'smoke', 'electrical', 'gas', 'broken', 'dangerous'];
            foreach ($urgentKeywords as $keyword) {
                if (stripos($description, $keyword) !== false || stripos($title, $keyword) !== false) {
                    $keywordBoost += 5;
                }
            }
            $aiPriorityScore = min(100, $aiPriorityScore + $keywordBoost);
            
            // Insert maintenance request
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_requests (
                    property_id, tenant_id, title, description, priority, status, estimated_cost, ai_priority_score
                ) VALUES (
                    :propertyId, :tenantId, :title, :description, :priority, 'pending', :estimatedCost, :aiPriorityScore
                )
            ");
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'tenantId' => $tenantId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'estimatedCost' => $estimatedCost,
                'aiPriorityScore' => $aiPriorityScore
            ]);
            
            $requestId = $pdo->lastInsertId();
            
            // Create initial maintenance task
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_tasks (
                    request_id, description, status
                ) VALUES (
                    :requestId, :description, 'pending'
                )
            ");
            
            $stmt->execute([
                'requestId' => $requestId,
                'description' => 'Initial assessment needed'
            ]);
            
            $_SESSION['success'] = "Maintenance request created successfully!";
            header("Location: maintenance_details.php?id=" . $requestId);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Format priority for display
function formatPriority($priority) {
    switch ($priority) {
        case 'emergency':
            return ['label' => 'Urgent', 'class' => 'bg-red-100 text-red-800'];
        case 'high':
            return ['label' => 'High', 'class' => 'bg-yellow-100 text-yellow-800'];
        case 'medium':
            return ['label' => 'Medium', 'class' => 'bg-blue-100 text-blue-800'];
        case 'low':
            return ['label' => 'Low', 'class' => 'bg-gray-100 text-gray-800'];
        default:
            return ['label' => ucfirst($priority), 'class' => 'bg-gray-100 text-gray-800'];
    }
}

// Format status for display
function formatStatus($status) {
    switch ($status) {
        case 'pending':
            return ['label' => 'Pending', 'class' => 'bg-yellow-100 text-yellow-800'];
        case 'assigned':
            return ['label' => 'Assigned', 'class' => 'bg-blue-100 text-blue-800'];
        case 'in_progress':
            return ['label' => 'In Progress', 'class' => 'bg-purple-100 text-purple-800'];
        case 'completed':
            return ['label' => 'Completed', 'class' => 'bg-green-100 text-green-800'];
        case 'cancelled':
            return ['label' => 'Cancelled', 'class' => 'bg-red-100 text-red-800'];
        default:
            return ['label' => ucfirst($status), 'class' => 'bg-gray-100 text-gray-800'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Maintenance</h2>
                <p class="text-gray-600">Track and manage maintenance requests</p>
            </div>
            <button onclick="openNewRequestModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>New Request
            </button>
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

        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Maintenance Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-tasks text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Requests</h3>
                        <p class="text-2xl font-semibold"><?php echo $counts['total']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">Pending</h3>
                        <p class="text-2xl font-semibold"><?php echo $counts['pending']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Completed</h3>
                        <p class="text-2xl font-semibold"><?php echo $counts['completed']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Urgent</h3>
                        <p class="text-2xl font-semibold"><?php echo $counts['urgent']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" action="maintenance.php">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="assigned" <?php echo $status === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="emergency" <?php echo $priority === 'emergency' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                            <option value="0">All Properties</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['property_id']; ?>" <?php echo $propertyId == $property['property_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($property['property_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="flex">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search requests..." class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <button type="submit" class="ml-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Maintenance Requests Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($maintenanceRequests)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No maintenance requests found.</p>
                    <p class="text-sm text-gray-500 mt-1">Create a new request or adjust your filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($maintenanceRequests as $request): ?>
                                <?php 
                                    $priority = formatPriority($request['priority']);
                                    $status = formatStatus($request['status']);
                                ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['title']); ?></div>
                                        <div class="text-sm text-gray-500">Reported by <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['property_name']); ?></div>
                                        <?php if (!empty($request['assigned_first_name'])): ?>
                                            <div class="text-sm text-gray-500">Assigned to: <?php echo htmlspecialchars($request['assigned_first_name'] . ' ' . $request['assigned_last_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $priority['class']; ?>">
                                            <?php echo $priority['label']; ?>
                                        </span>
                                        <?php if ($request['ai_priority_score'] > 0): ?>
                                            <div class="text-xs text-gray-500 mt-1">AI Score: <?php echo $request['ai_priority_score']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status['class']; ?>">
                                            <?php echo $status['label']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="maintenance_details.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700 mr-3">View</a>
                                        <a href="edit_maintenance.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700 mr-3">Update</a>
                                        <a href="#" onclick="confirmDelete(<?php echo $request['request_id']; ?>)" class="text-red-600 hover:text-red-900">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Request Modal -->
    <div id="newRequestModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">New Maintenance Request</h3>
                <button onclick="closeNewRequestModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="maintenance.php" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select Property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['property_id']; ?>">
                                    <?php echo htmlspecialchars($property['property_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="emergency">Urgent</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Request Title</label>
                        <input type="text" name="title" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" placeholder="Brief description of the issue">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Issue Description</label>
                        <textarea name="description" rows="3" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" placeholder="Detailed description of the maintenance issue"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reported By</label>
                        <select name="tenant_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['user_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost (Optional)</label>
                        <input type="number" name="estimated_cost" step="0.01" min="0" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary" placeholder="0.00">
                    </div>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeNewRequestModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="create_request" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Delete Maintenance Request</h3>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this maintenance request? This action cannot be undone.</p>
            <div class="flex justify-end space-x-4">
            <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <a id="deleteRequestLink" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-center">
                    Delete Request
                </a>
            </div>
        </div>
    </div>

    <script>
        function openNewRequestModal() {
            document.getElementById('newRequestModal').classList.remove('hidden');
            document.getElementById('newRequestModal').classList.add('flex');
        }

        function closeNewRequestModal() {
            document.getElementById('newRequestModal').classList.add('hidden');
            document.getElementById('newRequestModal').classList.remove('flex');
        }

        function confirmDelete(requestId) {
            document.getElementById('deleteRequestLink').href = 'delete_maintenance.php?id=' + requestId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Close modals when clicking outside
        document.getElementById('newRequestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewRequestModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
