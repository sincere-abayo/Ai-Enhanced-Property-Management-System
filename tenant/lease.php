<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Get tenant's active leases
$stmt = $pdo->prepare("
    SELECT l.*, 
           p.property_name, p.address, p.city, p.state, p.zip_code, p.property_type,p.landlord_id,
           u.unit_number, u.bedrooms, u.bathrooms,
           o.first_name as landlord_first_name, o.last_name as landlord_last_name,
           o.email as landlord_email, o.phone as landlord_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.property_id
    LEFT JOIN units u ON l.unit_id = u.unit_id
    JOIN users o ON p.landlord_id = o.user_id
    WHERE l.tenant_id = ?
    ORDER BY l.status = 'active' DESC, l.end_date DESC
");
$stmt->execute([$userId]);
$leases = $stmt->fetchAll();

// Get active lease (if any)
$activeLease = null;
foreach ($leases as $lease) {
    if ($lease['status'] == 'active') {
        $activeLease = $lease;
        break;
    }
}

// Get payment history for active lease
$payments = [];
if ($activeLease) {
    $stmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE lease_id = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$activeLease['lease_id']]);
    $payments = $stmt->fetchAll();
    
    // Calculate next payment date
    $today = new DateTime();
    $currentMonth = $today->format('m');
    $currentYear = $today->format('Y');
    
    // Create a date for this month's due date
    $dueDate = new DateTime("$currentYear-$currentMonth-{$activeLease['payment_due_day']}");
    
    // If today is past the due date, move to next month
    if ($today > $dueDate) {
        $dueDate->modify('+1 month');
    }
    
    $nextPaymentDate = $dueDate->format('F j, Y');
    $daysUntilDue = $today->diff($dueDate)->days;
    
    // Calculate lease remaining time
    $endDate = new DateTime($activeLease['end_date']);
    $interval = $today->diff($endDate);
    $daysRemaining = $interval->days;
    $monthsRemaining = floor($daysRemaining / 30);
}

// Format currency function
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Format date function
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lease - Tenant Portal</title>
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
                <h2 class="text-2xl font-bold text-gray-800">My Lease</h2>
                <p class="text-gray-600">View your lease details and payment schedule</p>
            </div>
            <?php if ($activeLease): ?>
            <div class="flex space-x-4">
                <button onclick="window.print()" class="bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-print mr-2"></i>Print Lease
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($leases)): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            You don't have any active leases. Please contact your property manager for assistance.
                        </p>
                    </div>
                </div>
            </div>
        <?php elseif ($activeLease): ?>
            <!-- Active Lease Summary -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($activeLease['property_name']); ?></h3>
                        <p class="text-gray-600">
                            <?php 
                            echo htmlspecialchars($activeLease['address']);
                            if ($activeLease['unit_number']) {
                                echo ', Unit ' . htmlspecialchars($activeLease['unit_number']);
                            }
                            echo ', ' . htmlspecialchars($activeLease['city']) . ', ' . htmlspecialchars($activeLease['state']) . ' ' . htmlspecialchars($activeLease['zip_code']);
                            ?>
                        </p>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                        Active Lease
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Monthly Rent</p>
                        <p class="text-xl font-semibold"><?php echo formatCurrency($activeLease['monthly_rent']); ?></p>
                        <p class="text-sm text-gray-500">Due on the <?php echo $activeLease['payment_due_day']; ?><sup>th</sup> of each month</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Lease Period</p>
                        <p class="text-xl font-semibold"><?php echo formatDate($activeLease['start_date']); ?> - <?php echo formatDate($activeLease['end_date']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $monthsRemaining; ?> months remaining</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500">Security Deposit</p>
                        <p class="text-xl font-semibold"><?php echo formatCurrency($activeLease['security_deposit']); ?></p>
                        <p class="text-sm text-gray-500">Refundable upon move-out</p>
                    </div>
                </div>
                
                <div class="border-t pt-6">
                    <h4 class="text-lg font-medium mb-4">Next Payment</h4>
                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                        <div>
                            <p class="text-sm text-gray-500">Due Date</p>
                            <p class="text-lg font-medium"><?php echo $nextPaymentDate; ?></p>
                            <p class="text-sm <?php echo ($daysUntilDue < 5) ? 'text-red-500' : 'text-blue-500'; ?>">
                                <?php echo $daysUntilDue; ?> days remaining
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Amount Due</p>
                            <p class="text-xl font-semibold"><?php echo formatCurrency($activeLease['monthly_rent']); ?></p>
                        </div>
                        <div>
                            <a href="payments.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Make Payment
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Landlord Information -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Landlord Information</h3>
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-500">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-lg font-medium"><?php echo htmlspecialchars($activeLease['landlord_first_name'] . ' ' . $activeLease['landlord_last_name']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($activeLease['landlord_email']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($activeLease['landlord_phone']); ?></p>
                        <div class="mt-3">
                            <a href="send_message.php?landlord_id=<?php echo $activeLease['landlord_id']; ?>" class="text-primary hover:text-blue-700">
                                <i class="fas fa-envelope mr-1"></i> Send Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Payments -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Recent Payments</h3>
                    <a href="payments.php" class="text-primary text-sm hover:text-blue-700">View All</a>
                </div>
                
                <?php if (empty($payments)): ?>
                    <div class="text-center py-6">
                        <p class="text-gray-500">No payment history available.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $count = 0;
                                foreach ($payments as $payment): 
                                    if ($count >= 5) break; // Show only 5 most recent payments
                                    $count++;
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium"><?php echo formatDate($payment['payment_date']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium"><?php echo formatCurrency($payment['amount']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Completed
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Lease Terms -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Lease Terms</h3>
                <div class="prose max-w-none">
                    <?php if (!empty($activeLease['terms'])): ?>
                        <?php echo nl2br(htmlspecialchars($activeLease['terms'])); ?>
                    <?php else: ?>
                        <p class="text-gray-500">Please contact your landlord for a detailed copy of your lease agreement.</p>
                        
                        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-medium mb-2">Standard Terms</h4>
                            <ul class="list-disc pl-5 space-y-2 text-sm text-gray-600">
                                <li>Rent is due on the <?php echo $activeLease['payment_due_day']; ?><sup>th</sup> day of each month</li>
                                <li>Late fees may apply for payments received after the due date</li>
                                <li>Security deposit of <?php echo formatCurrency($activeLease['security_deposit']); ?> is refundable upon move-out, subject to inspection</li>
                                <li>Tenant is responsible for utilities as specified in the lease agreement</li>
                                <li>Maintenance requests should be submitted through the tenant portal</li>
                                <li>Lease renewal must be requested at least 30 days before the end date</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Past Leases -->
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            You don't have any active leases. Below are your past lease agreements.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Past Lease List -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lease Period</th>
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
                                        <div class="text-xs text-gray-500">
                                            <?php 
                                            if ($lease['unit_number']) {
                                                echo 'Unit ' . htmlspecialchars($lease['unit_number']);
                                            } else {
                                                echo htmlspecialchars($lease['property_type']);
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo formatDate($lease['start_date']); ?> - <?php echo formatDate($lease['end_date']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium"><?php echo formatCurrency($lease['monthly_rent']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($lease['status'] == 'active'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        <?php elseif ($lease['status'] == 'expired'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Expired
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Terminated
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="lease_details.php?id=<?php echo $lease['lease_id']; ?>" class="text-primary hover:text-blue-700">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
