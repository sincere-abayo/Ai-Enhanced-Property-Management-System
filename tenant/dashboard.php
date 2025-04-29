<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$lastName = $_SESSION['last_name'];

// Get tenant's active lease
$stmt = $pdo->prepare("
    SELECT l.*, p.property_name, p.address, p.city, p.state, p.zip_code, u.unit_number
    FROM leases l
    JOIN properties p ON l.property_id = p.property_id
    LEFT JOIN units u ON l.unit_id = u.unit_id
    WHERE l.tenant_id = ? AND l.status = 'active'
    ORDER BY l.end_date DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$lease = $stmt->fetch();

// Get upcoming payment
$nextPayment = null;
$daysUntilDue = null;
if ($lease) {
    // Calculate next payment date based on payment_due_day
    $today = new DateTime();
    $currentMonth = $today->format('m');
    $currentYear = $today->format('Y');
    
    // Create a date for this month's due date
    $dueDate = new DateTime("$currentYear-$currentMonth-{$lease['payment_due_day']}");
    
    // If today is past the due date, move to next month
    if ($today > $dueDate) {
        $dueDate->modify('+1 month');
    }
    
    $nextPayment = [
        'amount' => $lease['monthly_rent'],
        'due_date' => $dueDate->format('Y-m-d'),
        'formatted_date' => $dueDate->format('M j, Y')
    ];
    
    // Calculate days until due
    $interval = $today->diff($dueDate);
    $daysUntilDue = $interval->days;
}

// Get recent maintenance requests
$stmt = $pdo->prepare("
    SELECT mr.*, p.property_name,
        CASE 
            WHEN mr.status = 'pending' THEN 'yellow'
            WHEN mr.status = 'in_progress' THEN 'blue'
            WHEN mr.status = 'completed' THEN 'green'
            WHEN mr.status = 'cancelled' THEN 'red'
            ELSE 'gray'
        END as status_color
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.tenant_id = ?
    ORDER BY mr.created_at DESC
    LIMIT 3
");
$stmt->execute([$userId]);
$maintenanceRequests = $stmt->fetchAll();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, l.property_id, pr.property_name
    FROM payments p
    JOIN leases l ON p.lease_id = l.lease_id
    JOIN properties pr ON l.property_id = pr.property_id
    WHERE l.tenant_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 3
");
$stmt->execute([$userId]);
$recentPayments = $stmt->fetchAll();

// Get unread notifications count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$unreadNotificationsCount = $stmt->fetch()['count'];

// Format currency function
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</h2>
                <p class="text-gray-600">Here's your property overview</p>
            </div>
            <div class="flex space-x-4 hidden sm:hidden">
                <a href="notifications.php" class="relative bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-bell mr-2"></i>Notifications
                    <?php if ($unreadNotificationsCount > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo $unreadNotificationsCount; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <?php if (!$lease): ?>
            <!-- No Active Lease Message -->
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">You don't have any active leases. Please contact your property manager for assistance.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Property Info Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($lease['property_name']); ?></h3>
                        <p class="text-gray-600">
                            <?php 
                            echo htmlspecialchars(
                                ($lease['unit_number'] ? "Unit " . $lease['unit_number'] . ", " : "") . 
                                $lease['address'] . ", " . 
                                $lease['city'] . ", " . 
                                $lease['state'] . " " . 
                                $lease['zip_code']
                            ); 
                            ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Lease Period</p>
                        <p class="font-semibold">
                            <?php 
                            echo date('M j, Y', strtotime($lease['start_date'])) . ' - ' . 
                                 date('M j, Y', strtotime($lease['end_date'])); 
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Payment Status -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Rent Payment Status</h3>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-500">Next Payment Due</p>
                        <p class="text-xl font-semibold"><?php echo formatCurrency($nextPayment['amount']); ?></p>
                        <p class="text-sm <?php echo $daysUntilDue <= 5 ? 'text-red-500' : 'text-gray-500'; ?>">
                            Due <?php echo $daysUntilDue > 0 ? "in $daysUntilDue days" : "today"; ?>
                            (<?php echo $nextPayment['formatted_date']; ?>)
                        </p>
                    </div>
                    <div class="flex space-x-4">
    <a href="payments.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
        <i class="fas fa-file-invoice mr-2"></i>View Payment History
    </a>
</div>

                </div>
            </div>
        <?php endif; ?>

              <!-- Latest Maintenance Requests -->
              <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Recent Maintenance Requests</h3>
                <a href="maintenance.php" class="text-primary text-sm hover:text-blue-700">View All</a>
            </div>
            
            <?php
            // Get recent maintenance requests for this tenant
            $stmt = $pdo->prepare("
                SELECT mr.*, p.property_name, 
                       CASE 
                           WHEN mr.status = 'pending' THEN 'bg-yellow-100 text-yellow-800'
                           WHEN mr.status = 'in_progress' THEN 'bg-blue-100 text-blue-800'
                           WHEN mr.status = 'completed' THEN 'bg-green-100 text-green-800'
                           WHEN mr.status = 'cancelled' THEN 'bg-red-100 text-red-800'
                           ELSE 'bg-gray-100 text-gray-800'
                       END as status_class
                FROM maintenance_requests mr
                JOIN properties p ON mr.property_id = p.property_id
                WHERE mr.tenant_id = ?
                ORDER BY mr.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$userId]);
            $maintenanceRequests = $stmt->fetchAll();
            ?>
            
            <?php if (empty($maintenanceRequests)): ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No maintenance requests found.</p>
                    <a href="maintenance.php" class="mt-2 inline-block text-primary hover:text-blue-700">
                        <i class="fas fa-plus-circle mr-1"></i> Submit a new request
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($maintenanceRequests as $request): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full 
                                    <?php echo $request['priority'] == 'emergency' ? 'bg-red-100' : 
                                        ($request['priority'] == 'high' ? 'bg-orange-100' : 
                                        ($request['priority'] == 'medium' ? 'bg-yellow-100' : 'bg-blue-100')); ?>">
                                    <i class="fas fa-tools 
                                        <?php echo $request['priority'] == 'emergency' ? 'text-red-500' : 
                                            ($request['priority'] == 'high' ? 'text-orange-500' : 
                                            ($request['priority'] == 'medium' ? 'text-yellow-500' : 'text-blue-500')); ?>"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($request['title']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($request['property_name']); ?> • 
                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $request['status_class']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-center">
                    <a href="maintenance.php" class="text-primary hover:text-blue-700">
                        <i class="fas fa-plus-circle mr-1"></i> Submit a new request
                    </a>
                </div>
            <?php endif; ?>
        </div>


        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
            <div class="space-y-4">
                <?php if (empty($recentPayments) && empty($maintenanceRequests)): ?>
                    <p class="text-gray-500 text-center py-4">No recent activity to display.</p>
                <?php else: ?>
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="p-2 rounded-full bg-green-100">
                                <i class="fas fa-money-bill-wave text-green-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">
                                    Payment of <?php echo formatCurrency($payment['amount']); ?> recorded
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($maintenanceRequests as $request): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="p-2 rounded-full bg-<?php echo $request['status_color'] ?? 'pending' ?>-100">
                                <i class="fas fa-tools text-<?php echo $request['status_color']?? 'pending' ?>-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">
                                    Maintenance request: <?php echo htmlspecialchars($request['title']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Status: <?php echo ucfirst($request['status']); ?> - 
                                    <?php echo date('F j, Y', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
