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

// Get active leases for this property
function getPropertyLeases($propertyId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT l.*, u.first_name, u.last_name, u.email, u.phone
        FROM leases l
        JOIN users u ON l.tenant_id = u.user_id
        WHERE l.property_id = :propertyId
        ORDER BY l.status ASC, l.end_date DESC
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    return $stmt->fetchAll();
}

// Get maintenance requests for this property
function getPropertyMaintenanceRequests($propertyId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name
        FROM maintenance_requests m
        JOIN users u ON m.tenant_id = u.user_id
        WHERE m.property_id = :propertyId
        ORDER BY m.created_at DESC
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    return $stmt->fetchAll();
}

// Get payment history for this property
function getPropertyPayments($propertyId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE l.property_id = :propertyId
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    
    return $stmt->fetchAll();
}

// Get property data
$property = getPropertyDetails($propertyId, $userId);

// Check if property exists and belongs to the current landlord
if (!$property) {
    $_SESSION['error'] = "Property not found or you don't have permission to view it";
    header("Location: properties.php");
    exit;
}

// Get related data
$leases = getPropertyLeases($propertyId);
$maintenanceRequests = getPropertyMaintenanceRequests($propertyId);
$payments = getPropertyPayments($propertyId);

// Calculate occupancy rate
$occupancyRate = 0;
if (!empty($leases)) {
    $activeLeases = array_filter($leases, function($lease) {
        return $lease['status'] === 'active';
    });
    $occupancyRate = count($activeLeases) > 0 ? 100 : 0;
}

// Calculate monthly income
$monthlyIncome = 0;
foreach ($leases as $lease) {
    if ($lease['status'] === 'active') {
        $monthlyIncome += $lease['monthly_rent'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['property_name']); ?> - Property Management System</title>
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
          <!-- Display success/error messages -->
          <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success']; ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            
            <a href="properties.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($property['property_name']); ?></h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($property['address']); ?>, <?php echo htmlspecialchars($property['city']); ?>, <?php echo htmlspecialchars($property['state']); ?> <?php echo htmlspecialchars($property['zip_code']); ?></p>
            </div>
        </div>

        <!-- Property Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Property Image and Details -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md overflow-hidden">
                <div class="relative h-64">
                    <?php if (!empty($property['image_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($property['image_path']); ?>" alt="<?php echo htmlspecialchars($property['property_name']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Property" class="w-full h-full object-cover">
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
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="text-center">
                            <p class="text-gray-500 text-sm">Property Type</p>
                            <p class="font-semibold capitalize"><?php echo htmlspecialchars($property['property_type']); ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-sm">Bedrooms</p>
                            <p class="font-semibold"><?php echo $property['bedrooms'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-sm">Bathrooms</p>
                            <p class="font-semibold"><?php echo $property['bathrooms'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-sm">Square Feet</p>
                            <p class="font-semibold"><?php echo $property['square_feet'] ? number_format($property['square_feet']) : 'N/A'; ?></p>
                        </div>
                    </div>
                    
                    <h3 class="text-lg font-semibold mb-2">Description</h3>
                    <p class="text-gray-600 mb-6">
                        <?php echo !empty($property['description']) ? nl2br(htmlspecialchars($property['description'])) : 'No description available.'; ?>
                    </p>
                    
                    <div class="flex space-x-4">
                        <a href="edit_property.php?id=<?php echo $property['property_id']; ?>" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-edit mr-2"></i>Edit Property
                        </a>
                        <a href="add_lease.php?property_id=<?php echo $property['property_id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-file-contract mr-2"></i>Add Lease
                        </a>
                        <button onclick="confirmDelete(<?php echo $property['property_id']; ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            <i class="fas fa-trash-alt mr-2"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Financial Overview -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Financial Overview</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-500 text-sm">Monthly Rent</p>
                        <p class="text-2xl font-semibold">$<?php echo number_format($property['monthly_rent'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Current Income</p>
                        <p class="text-2xl font-semibold">$<?php echo number_format($monthlyIncome, 2); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Occupancy Rate</p>
                        <p class="text-2xl font-semibold"><?php echo $occupancyRate; ?>%</p>
                    </div>
                    <div class="pt-4 border-t">
                        <p class="text-gray-500 text-sm mb-2">Quick Actions</p>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="add_maintenance.php?property_id=<?php echo $property['property_id']; ?>" class="bg-yellow-100 text-yellow-800 px-3 py-2 rounded-lg text-sm text-center hover:bg-yellow-200">
                                <i class="fas fa-tools mr-1"></i>Maintenance
                            </a>
                            <a href="record_payment.php?property_id=<?php echo $property['property_id']; ?>" class="bg-green-100 text-green-800 px-3 py-2 rounded-lg text-sm text-center hover:bg-green-200">
                                <i class="fas fa-money-bill-wave mr-1"></i>Payment
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs for Leases, Maintenance, Payments -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="border-b">
                <div class="flex">
                    <button onclick="showTab('leases')" id="leasesTab" class="tab-button px-6 py-3 font-medium text-sm focus:outline-none active">
                        <i class="fas fa-file-contract mr-2"></i>Leases
                    </button>
                    <button onclick="showTab('maintenance')" id="maintenanceTab" class="tab-button px-6 py-3 font-medium text-sm focus:outline-none">
                        <i class="fas fa-tools mr-2"></i>Maintenance
                    </button>
                    <button onclick="showTab('payments')" id="paymentsTab" class="tab-button px-6 py-3 font-medium text-sm focus:outline-none">
                        <i class="fas fa-money-bill-wave mr-2"></i>Payments
                    </button>
                </div>
            </div>
            
            <!-- Leases Tab Content -->
            <div id="leasesContent" class="tab-content p-6">
                <?php if (empty($leases)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No leases found for this property.</p>
                        <a href="add_lease.php?property_id=<?php echo $property['property_id']; ?>" class="inline-block mt-4 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add New Lease
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Rent</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($leases as $lease): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($lease['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M d, Y', strtotime($lease['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($lease['end_date'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php 
                                                    $startDate = new DateTime($lease['start_date']);
                                                    $endDate = new DateTime($lease['end_date']);
                                                    $interval = $startDate->diff($endDate);
                                                    echo $interval->format('%m months');
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">$<?php echo number_format($lease['monthly_rent'], 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($lease['status'] === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php elseif ($lease['status'] === 'expired'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Expired
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Terminated
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="lease_details.php?id=<?php echo $lease['lease_id']; ?>" class="text-primary hover:text-blue-700 mr-3">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_lease.php?id=<?php echo $lease['lease_id']; ?>" class="text-gray-600 hover:text-gray-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- <a href="#" onclick="confirmTerminateLease(<?php //echo $lease['lease_id']; ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times-circle"></i>
                                            </a> -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Maintenance Tab Content -->
            <div id="maintenanceContent" class="tab-content p-6 hidden">
                <?php if (empty($maintenanceRequests)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No maintenance requests found for this property.</p>
                        <a href="add_maintenance.php?property_id=<?php echo $property['property_id']; ?>" class="inline-block mt-4 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Add Maintenance Request
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported By</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($maintenanceRequests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['title']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo substr(htmlspecialchars($request['description']), 0, 50) . (strlen($request['description']) > 50 ? '...' : ''); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('h:i A', strtotime($request['created_at'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($request['priority'] === 'emergency'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Emergency
                                                </span>
                                            <?php elseif ($request['priority'] === 'high'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                                    High
                                                </span>
                                            <?php elseif ($request['priority'] === 'medium'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Medium
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Low
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            <?php elseif ($request['status'] === 'in_progress'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    In Progress
                                                </span>
                                            <?php elseif ($request['status'] === 'completed'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    Cancelled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="maintenance_details.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700 mr-3">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_maintenance.php?id=<?php echo $request['request_id']; ?>" class="text-gray-600 hover:text-gray-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Payments Tab Content -->
            <div id="paymentsContent" class="tab-content p-6 hidden">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No payment records found for this property.</p>
                        <a href="record_payment.php?property_id=<?php echo $property['property_id']; ?>" class="inline-block mt-4 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>Record Payment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">$<?php echo number_format($payment['amount'], 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 capitalize">
                                                <?php echo str_replace('_', ' ', $payment['payment_type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 capitalize"><?php echo str_replace('_', ' ', $payment['payment_method']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="payment_details.php?id=<?php echo $payment['payment_id']; ?>" class="text-primary hover:text-blue-700 mr-3">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_payment.php?id=<?php echo $payment['payment_id']; ?>" class="text-gray-600 hover:text-gray-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="confirmDeletePayment(<?php echo $payment['payment_id']; ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Property Confirmation Modal -->
    <div id="deletePropertyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Confirm Delete</h3>
            <p class="mb-6">Are you sure you want to delete this property? This action cannot be undone and will remove all associated leases, maintenance requests, and payment records.</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <form id="deletePropertyForm" method="POST" action="delete_property.php">
                    <input type="hidden" id="deletePropertyId" name="property_id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete Property
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Terminate Lease Confirmation Modal -->
    <div id="terminateLeaseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Confirm Lease Termination</h3>
            <p class="mb-6">Are you sure you want to terminate this lease? This will mark the lease as terminated and may affect your property's occupancy status.</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeTerminateLeaseModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <form id="terminateLeaseForm" method="POST" action="terminate_lease.php">
                    <input type="hidden" id="terminateLeaseId" name="lease_id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Terminate Lease
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Payment Confirmation Modal -->
    <div id="deletePaymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Confirm Delete Payment</h3>
            <p class="mb-6">Are you sure you want to delete this payment record? This action cannot be undone.</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeletePaymentModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <form id="deletePaymentForm" method="POST" action="delete_payment.php">
                    <input type="hidden" id="deletePaymentId" name="payment_id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('text-primary', 'border-b-2', 'border-primary');
                button.classList.add('text-gray-500');
            });
            
            // Show selected tab content
            document.getElementById(tabName + 'Content').classList.remove('hidden');
            
            // Add active class to selected tab button
            document.getElementById(tabName + 'Tab').classList.add('text-primary', 'border-b-2', 'border-primary');
            document.getElementById(tabName + 'Tab').classList.remove('text-gray-500');
        }
        
        // Delete property confirmation
        function confirmDelete(propertyId) {
            document.getElementById('deletePropertyId').value = propertyId;
            document.getElementById('deletePropertyModal').classList.remove('hidden');
            document.getElementById('deletePropertyModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deletePropertyModal').classList.add('hidden');
            document.getElementById('deletePropertyModal').classList.remove('flex');
        }
        
        // Terminate lease confirmation
        // function confirmTerminateLease(leaseId) {
        //     document.getElementById('terminateLeaseId').value = leaseId;
        //     document.getElementById('terminateLeaseModal').classList.remove('hidden');
        //     document.getElementById('terminateLeaseModal').classList.add('flex');
        // }
        
        function closeTerminateLeaseModal() {
            document.getElementById('terminateLeaseModal').classList.add('hidden');
            document.getElementById('terminateLeaseModal').classList.remove('flex');
        }
        
        // Delete payment confirmation
        function confirmDeletePayment(paymentId) {
            document.getElementById('deletePaymentId').value = paymentId;
            document.getElementById('deletePaymentModal').classList.remove('hidden');
            document.getElementById('deletePaymentModal').classList.add('flex');
        }
        
        function closeDeletePaymentModal() {
            document.getElementById('deletePaymentModal').classList.add('hidden');
            document.getElementById('deletePaymentModal').classList.remove('flex');
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('#deletePropertyModal, #terminateLeaseModal, #deletePaymentModal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        });
    </script>
</body>
</html>
