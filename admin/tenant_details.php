<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if tenant ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid tenant ID";
    header("Location: tenants.php");
    exit;
}

$tenantId = (int)$_GET['id'];

// Get tenant details
function getTenantDetails($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
        LIMIT 1
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get tenant leases
function getTenantLeases($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT l.*, p.property_name, p.address, p.city, p.state, p.zip_code
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.tenant_id = :tenantId AND p.landlord_id = :landlordId
        ORDER BY l.start_date DESC
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetchAll();
}

// Get tenant payments
function getTenantPayments($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, l.property_id, pr.property_name
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE l.tenant_id = :tenantId AND pr.landlord_id = :landlordId
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetchAll();
}

// Get tenant maintenance requests
function getTenantMaintenanceRequests($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, p.property_name
        FROM maintenance_requests m
        JOIN properties p ON m.property_id = p.property_id
        WHERE m.tenant_id = :tenantId AND p.landlord_id = :landlordId
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetchAll();
}

// Get tenant details
$tenant = getTenantDetails($tenantId, $userId);

// Redirect if tenant not found or doesn't belong to this landlord
if (!$tenant) {
    $_SESSION['error'] = "Tenant not found or you don't have permission to view this tenant";
    header("Location: tenants.php");
    exit;
}

// Get tenant leases, payments, and maintenance requests
$leases = getTenantLeases($tenantId, $userId);
$payments = getTenantPayments($tenantId, $userId);
$maintenanceRequests = getTenantMaintenanceRequests($tenantId, $userId);

// Calculate statistics
$totalPaid = 0;
$onTimePayments = 0;
$latePayments = 0;

foreach ($payments as $payment) {
    if ($payment['payment_type'] == 'rent') {
        $totalPaid += $payment['amount'];
        
        // Get the lease for this payment
        foreach ($leases as $lease) {
            if ($lease['lease_id'] == $payment['lease_id']) {
                $dueDate = new DateTime($payment['payment_date']);
                $dueDate->setDate($dueDate->format('Y'), $dueDate->format('m'), $lease['payment_due_day']);
                
                $paymentDate = new DateTime($payment['payment_date']);
                
                if ($paymentDate <= $dueDate) {
                    $onTimePayments++;
                } else {
                    $latePayments++;
                }
                
                break;
            }
        }
    }
}

// Calculate payment reliability score (0-100)
$totalPayments = $onTimePayments + $latePayments;
$reliabilityScore = $totalPayments > 0 ? round(($onTimePayments / $totalPayments) * 100) : 0;



// Get status class for styling
function getStatusClass($status) {
    switch ($status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'expired':
            return 'bg-yellow-100 text-yellow-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-blue-100 text-blue-800';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Details - Property Management System</title>
    
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
            <a href="tenants.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Tenant Details</h2>
                <p class="text-gray-600">View and manage tenant information</p>
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

        </div>

        <!-- Tenant Profile -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Tenant Information -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center mb-6">
                    <div class="h-20 w-20 rounded-full overflow-hidden bg-gray-200 mr-4">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($tenant['first_name'] . '+' . $tenant['last_name']); ?>&size=80&background=random" alt="<?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>">
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h3>
                        <p class="text-gray-600">Tenant since <?php echo date('M Y', strtotime($tenant['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center">
                        <i class="fas fa-envelope w-6 text-gray-500"></i>
                        <span class="ml-2"><?php echo htmlspecialchars($tenant['email']); ?></span>
                    </div>
                    <?php if (!empty($tenant['phone'])): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone w-6 text-gray-500"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($tenant['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center">
                        <i class="fas fa-home w-6 text-gray-500"></i>
                        <span class="ml-2">
                            <?php 
                                $activeLeases = array_filter($leases, function($lease) {
                                    return $lease['status'] == 'active';
                                });
                                
                                if (!empty($activeLeases)) {
                                    $lease = reset($activeLeases);
                                    echo htmlspecialchars($lease['property_name']);
                                } else {
                                    echo "No active lease";
                                }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Quick Actions</h4>
                    <div class="flex flex-wrap gap-2">
                        <a href="edit_tenant.php?id=<?php echo $tenantId; ?>" class="px-3 py-1 bg-primary text-white text-sm rounded-lg hover:bg-blue-700">
                            Edit Tenant
                        </a>
                        <a href="record_payment.php?tenant_id=<?php echo $tenantId; ?>" class="px-3 py-1 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                            Record Payment
                        </a>
                        <a href="send_message.php?tenant_id=<?php echo $tenantId; ?>" class="px-3 py-1 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700">
                            Send Message
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Payment Statistics -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Payment Statistics</h3>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Total Paid</p>
                        <p class="text-xl font-semibold"><?php echo formatCurrency($totalPaid); ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Current Rent</p>
                        <p class="text-xl font-semibold">
                            <?php 
                                $activeLeases = array_filter($leases, function($lease) {
                                    return $lease['status'] == 'active';
                                });
                                
                                if (!empty($activeLeases)) {
                                    $lease = reset($activeLeases);
                                    echo formatCurrency($lease['monthly_rent']);
                                } else {
                                    echo "N/A";
                                }
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Payment Reliability</h4>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-primary h-2.5 rounded-full" style="width: <?php echo $reliabilityScore; ?>%"></div>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-gray-500">Score: <?php echo $reliabilityScore; ?>/100</span>
                        <span class="text-xs text-gray-500"><?php echo $onTimePayments; ?> on-time, <?php echo $latePayments; ?> late</span>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Recent Payments</h4>
                    <?php if (empty($payments)): ?>
                        <p class="text-sm text-gray-500">No payment history available</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php 
                                $recentPayments = array_slice($payments, 0, 3);
                                foreach ($recentPayments as $payment): 
                            ?>
                                                              <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium"><?php echo formatCurrency($payment['amount']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $payment['payment_type'] == 'rent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($payments) > 3): ?>
                                <a href="#payment-history" class="text-primary text-sm hover:underline">View all payments</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lease Information -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Lease Information</h3>
                
                <?php if (empty($leases)): ?>
                    <p class="text-sm text-gray-500">No lease information available</p>
                <?php else: ?>
                    <?php 
                        $activeLeases = array_filter($leases, function($lease) {
                            return $lease['status'] == 'active';
                        });
                        
                        if (!empty($activeLeases)) {
                            $currentLease = reset($activeLeases);
                        } else {
                            $currentLease = reset($leases); // Get the most recent lease
                        }
                    ?>
                    
                    <div class="mb-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getStatusClass($currentLease['status']); ?>">
                            <?php echo ucfirst($currentLease['status']); ?> Lease
                        </span>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-building w-6 text-gray-500"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($currentLease['property_name']); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt w-6 text-gray-500"></i>
                            <span class="ml-2"><?php echo htmlspecialchars($currentLease['address'] . ', ' . $currentLease['city'] . ', ' . $currentLease['state'] . ' ' . $currentLease['zip_code']); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar w-6 text-gray-500"></i>
                            <span class="ml-2"><?php echo date('M j, Y', strtotime($currentLease['start_date'])); ?> - <?php echo date('M j, Y', strtotime($currentLease['end_date'])); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave w-6 text-gray-500"></i>
                            <span class="ml-2"><?php echo formatCurrency($currentLease['monthly_rent']); ?> / month</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt w-6 text-gray-500"></i>
                            <span class="ml-2">Security Deposit: <?php echo formatCurrency($currentLease['security_deposit']); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar-day w-6 text-gray-500"></i>
                            <span class="ml-2">Payment Due: Day <?php echo $currentLease['payment_due_day']; ?> of each month</span>
                        </div>
                    </div>
                    
                    <?php if (count($leases) > 1): ?>
                        <a href="#lease-history" class="text-primary text-sm hover:underline">View lease history</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="#payment-history" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-primary font-medium text-sm text-primary" data-tab="payment-history">
                        Payment History
                    </a>
                    <a href="#lease-history" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="lease-history">
                        Lease History
                    </a>
                    <a href="#maintenance-requests" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="maintenance-requests">
                        Maintenance Requests
                    </a>
                    <a href="#documents" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="documents">
                        Documents
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div>
            <!-- Payment History Tab -->
            <div id="payment-history" class="tab-content">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b">
                        <h3 class="text-lg font-semibold">Payment History</h3>
                        <a href="record_payment.php?tenant_id=<?php echo $tenantId; ?>" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            Record Payment
                        </a>
                    </div>
                    
                    <?php if (empty($payments)): ?>
                        <div class="p-6 text-center">
                            <p class="text-gray-500">No payment history available</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo formatCurrency($payment['amount']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $payment['payment_type'] == 'rent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($payment['property_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="payment_details.php?id=<?php echo $payment['payment_id']; ?>" class="text-primary hover:text-blue-700 mr-3">View</a>
                                                <a href="#" onclick="confirmDeletePayment(<?php echo $payment['payment_id']; ?>, '<?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>', '<?php echo formatCurrency($payment['amount']); ?>')" class="text-red-600 hover:text-red-900">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lease History Tab -->
            <div id="lease-history" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b">
                        <h3 class="text-lg font-semibold">Lease History</h3>
                        <a href="add_lease.php?tenant_id=<?php echo $tenantId; ?>" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            Add Lease
                        </a>
                    </div>
                    
                    <?php if (empty($leases)): ?>
                        <div class="p-6 text-center">
                            <p class="text-gray-500">No lease history available</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Rent</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($leases as $lease): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lease['property_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lease['address'] . ', ' . $lease['city']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($lease['start_date'])); ?> - <?php echo date('M j, Y', strtotime($lease['end_date'])); ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <?php 
                                                        $days = round((strtotime($lease['end_date']) - time()) / (60 * 60 * 24));
                                                        if ($days < 0) {
                                                            echo "Ended " . abs($days) . " days ago";
                                                        } elseif ($days == 0) {
                                                            echo "Ends today";
                                                        } else {
                                                            echo $days . " days remaining";
                                                        }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($lease['monthly_rent']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusClass($lease['status']); ?>">
                                                    <?php echo ucfirst($lease['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="lease_details.php?id=<?php echo $lease['lease_id']; ?>" class="text-primary hover:text-blue-700 mr-3">View</a>
                                                <a href="edit_lease.php?id=<?php echo $lease['lease_id']; ?>" class="text-primary hover:text-blue-700 mr-3">Edit</a>
                                                <?php if ($lease['status'] == 'active'): ?>
                                                    <a href="#" onclick="confirmTerminateLease(<?php echo $lease['lease_id']; ?>, '<?php echo htmlspecialchars(addslashes($lease['property_name'])); ?>')" class="text-red-600 hover:text-red-900">Terminate</a>
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
            
            <!-- Maintenance Requests Tab -->
            <div id="maintenance-requests" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b">
                        <h3 class="text-lg font-semibold">Maintenance Requests</h3>
                    </div>
                    
                    <?php if (empty($maintenanceRequests)): ?>
                        <div class="p-6 text-center">
                            <p class="text-gray-500">No maintenance requests available</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
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
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($request['property_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getPriorityClass($request['priority']); ?>">
                                                    <?php echo ucfirst($request['priority']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusClass($request['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="maintenance_details.php?id=<?php echo $request['request_id']; ?>" class="text-primary hover:text-blue-700">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Documents Tab -->
            <div id="documents" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b">
                        <h3 class="text-lg font-semibold">Documents</h3>
                        <button onclick="openUploadModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                            Upload Document
                        </button>
                    </div>
                    
                    <div class="p-6 text-center">
                        <p class="text-gray-500">No documents available</p>
                        <p class="text-sm text-gray-500 mt-2">Upload lease agreements, notices, or other important documents.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Payment Confirmation Modal -->
    <div id="deletePaymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-2">Confirm Payment Deletion</h3>
                <p class="text-gray-600">Are you sure you want to delete the payment of <span id="paymentAmount" class="font-semibold"></span> made on <span id="paymentDate" class="font-semibold"></span>?</p>
            </div>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeletePaymentModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <a id="confirmDeletePaymentBtn" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete Payment
                </a>
            </div>
        </div>
    </div>
    
    <!-- Terminate Lease Confirmation Modal -->
    <div id="terminateLeaseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-2">Confirm Lease Termination</h3>
                <p class="text-gray-600">Are you sure you want to terminate the lease for <span id="propertyName" class="font-semibold"></span>?</p>
            </div>
            <div class="flex justify-end space-x-4">
                <button onclick="closeTerminateLeaseModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <a id="confirmTerminateLeaseBtn" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Terminate Lease
                </a>
            </div>
        </div>
    </div>
    
    <!-- Upload Document Modal -->
    <div id="uploadDocumentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Upload Document</h3>
                <button onclick="closeUploadModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="upload_document.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                    <select name="document_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">Select Document Type</option>
                        <option value="lease_agreement">Lease Agreement</option>
                        <option value="notice">Notice</option>
                        <option value="inspection">Inspection Report</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                    <input type="text" name="title" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <input type="file" name="document" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    tabLinks.forEach(tab => {
                        tab.classList.remove('border-primary', 'text-primary');
                        tab.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('border-primary', 'text-primary');
                    this.classList.remove('border-transparent', 'text-gray-500');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });
                    
                    // Show the selected tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.remove('hidden');
                });
            });
            
            // Check if URL has a hash and activate that tab
            if (window.location.hash) {
                const hash = window.location.hash.substring(1);
                const tab = document.querySelector(`[data-tab="${hash}"]`);
                if (tab) {
                    tab.click();
                }
            }
        });
        
        // Modal functions
        function confirmDeletePayment(paymentId, date, amount) {
            document.getElementById('paymentDate').textContent = date;
            document.getElementById('paymentAmount').textContent = amount;
            document.getElementById('confirmDeletePaymentBtn').href = 'delete_payment.php?id=' + paymentId + '&tenant_id=<?php echo $tenantId; ?>';
            document.getElementById('deletePaymentModal').classList.remove('hidden');
            document.getElementById('deletePaymentModal').classList.add('flex');
        }
        
        function closeDeletePaymentModal() {
            document.getElementById('deletePaymentModal').classList.add('hidden');
            document.getElementById('deletePaymentModal').classList.remove('flex');
        }
        
        function confirmTerminateLease(leaseId, propertyName) {
            document.getElementById('propertyName').textContent = propertyName;
            document.getElementById('confirmTerminateLeaseBtn').href = 'terminate_lease.php?id=' + leaseId + '&tenant_id=<?php echo $tenantId; ?>';
            document.getElementById('terminateLeaseModal').classList.remove('hidden');
            document.getElementById('terminateLeaseModal').classList.add('flex');
        }
        
        function closeTerminateLeaseModal() {
            document.getElementById('terminateLeaseModal').classList.add('hidden');
            document.getElementById('terminateLeaseModal').classList.remove('flex');
        }
        
        function openUploadModal() {
            document.getElementById('uploadDocumentModal').classList.remove('hidden');
            document.getElementById('uploadDocumentModal').classList.add('flex');
        }
        
        function closeUploadModal() {
            document.getElementById('uploadDocumentModal').classList.add('hidden');
            document.getElementById('uploadDocumentModal').classList.remove('flex');
        }
        
        // Close modals when clicking outside
        document.getElementById('deletePaymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeletePaymentModal();
            }
        });
        
        document.getElementById('terminateLeaseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTerminateLeaseModal();
            }
        });
        
        document.getElementById('uploadDocumentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
    </script>
</body>
</html>
