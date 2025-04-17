<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Get tenant's active lease
$stmt = $pdo->prepare("
    SELECT l.*, p.property_id, p.property_name, p.address, p.city, p.state, p.zip_code, u.unit_number
    FROM leases l
    JOIN properties p ON l.property_id = p.property_id
    LEFT JOIN units u ON l.unit_id = u.unit_id
    WHERE l.tenant_id = ? AND l.status = 'active'
    ORDER BY l.end_date DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$lease = $stmt->fetch();

// Get maintenance requests
$stmt = $pdo->prepare("
    SELECT mr.*, p.property_name,
        CASE 
            WHEN mr.priority = 'emergency' THEN 'red'
            WHEN mr.priority = 'high' THEN 'orange'
            WHEN mr.priority = 'medium' THEN 'yellow'
            WHEN mr.priority = 'low' THEN 'blue'
            ELSE 'gray'
        END as priority_color,
        CASE 
            WHEN mr.status = 'pending' THEN 'yellow'
            WHEN mr.status = 'assigned' THEN 'blue'
            WHEN mr.status = 'in_progress' THEN 'blue'
            WHEN mr.status = 'completed' THEN 'green'
            WHEN mr.status = 'cancelled' THEN 'red'
            ELSE 'gray'
        END as status_color
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.tenant_id = ?
    ORDER BY 
        CASE 
            WHEN mr.status = 'pending' THEN 1
            WHEN mr.status = 'assigned' THEN 2
            WHEN mr.status = 'in_progress' THEN 3
            WHEN mr.status = 'completed' THEN 4
            WHEN mr.status = 'cancelled' THEN 5
        END,
        CASE 
            WHEN mr.priority = 'emergency' THEN 1
            WHEN mr.priority = 'high' THEN 2
            WHEN mr.priority = 'medium' THEN 3
            WHEN mr.priority = 'low' THEN 4
        END,
        mr.created_at DESC
");
$stmt->execute([$userId]);
$maintenanceRequests = $stmt->fetchAll();

// Count requests by status
$pendingCount = 0;
$inProgressCount = 0;
$completedCount = 0;

foreach ($maintenanceRequests as $request) {
    if ($request['status'] == 'pending') {
        $pendingCount++;
    } elseif ($request['status'] == 'assigned' || $request['status'] == 'in_progress') {
        $inProgressCount++;
    } elseif ($request['status'] == 'completed') {
        $completedCount++;
    }
}

// Check for success or error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Tenant Portal</title>
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
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Maintenance Requests</h2>
                <p class="text-gray-600">View and manage your maintenance requests</p>
            </div>
            <button onclick="openNewRequestModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>New Request
            </button>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Maintenance Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Pending</h3>
                        <p class="text-2xl font-semibold"><?php echo $pendingCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-tools text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">In Progress</h3>
                        <p class="text-2xl font-semibold"><?php echo $inProgressCount; ?></p>
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
                        <p class="text-2xl font-semibold"><?php echo $completedCount; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Requests List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($maintenanceRequests)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No maintenance requests found.</p>
                    <p class="text-sm text-gray-500 mt-1">Click "New Request" to submit a maintenance request.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($maintenanceRequests as $request): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['title']); ?></div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars(substr($request['description'], 0, 50) . (strlen($request['description']) > 50 ? '...' : '')); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['property_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($request['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $request['priority_color']; ?>-100 text-<?php echo $request['priority_color']; ?>-800">
                                            <?php echo ucfirst($request['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $request['status_color']; ?>-100 text-<?php echo $request['status_color']; ?>-800">
                                            <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="maintenance_details.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700">View</a>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <a href="edit_maintenance.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700 ml-3">Edit</a>
                                            <a href="cancel_maintenance.php?id=<?php echo $request['request_id']; ?>" class="text-red-600 hover:text-red-900 ml-3" onclick="return confirm('Are you sure you want to cancel this request?')">Cancel</a>
                                        <?php endif; ?>
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
            <form action="submit_maintenance.php" method="POST" class="space-y-4">
                <?php if ($lease): ?>
                    <input type="hidden" name="property_id" value="<?php echo $lease['property_id']; ?>">
                    <?php if ($lease['unit_id']): ?>
                        <input type="hidden" name="unit_id" value="<?php echo $lease['unit_id']; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issue Title</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="Brief title of the issue">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issue Type</label>
                    <select name="issue_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="plumbing">Plumbing</option>
                        <option value="electrical">Electrical</option>
                        <option value="hvac">HVAC</option>
                        <option value="appliance">Appliance</option>
                        <option value="structural">Structural</option>
                        <option value="pest">Pest Control</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" rows="4" placeholder="Describe the issue in detail..."></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select name="priority" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="low">Low - Not urgent, can be scheduled</option>
                        <option value="medium" selected>Medium - Needs attention soon</option>
                        <option value="high">High - Requires prompt attention</option>
                        <option value="emergency">Emergency - Immediate attention needed</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Best Time to Enter</label>
                    <select name="best_time" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="anytime">Anytime</option>
                        <option value="morning">Morning (8am - 12pm)</option>
                        <option value="afternoon">Afternoon (12pm - 5pm)</option>
                        <option value="evening">Evening (5pm - 8pm)</option>
                        <option value="contact_first">Please contact me first</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Permission to Enter</label>
                    <div class="flex items-center">
                        <input type="checkbox" name="permission_to_enter" id="permission_to_enter" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" checked>
                        <label for="permission_to_enter" class="ml-2 block text-sm text-gray-700">
                            I give permission for maintenance staff to enter my unit to address this issue
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeNewRequestModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Submit Request
                    </button>
                </div>
            </form>
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

        // Close modal when clicking outside
        document.getElementById('newRequestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewRequestModal();
            }
        });
    </script>
</body>
</html>
