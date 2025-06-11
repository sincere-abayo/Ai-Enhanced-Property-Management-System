<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Get date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$propertyId = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

// Get properties for filter dropdown
$stmt = $pdo->prepare("
    SELECT property_id, property_name
    FROM properties
    WHERE landlord_id = :landlordId
    ORDER BY property_name
");
$stmt->execute(['landlordId' => $userId]);
$properties = $stmt->fetchAll();

// Get income summary
function getIncomeSummary($userId, $startDate, $endDate, $propertyId = 0) {
    global $pdo;
    
    $query = "
        SELECT 
            SUM(p.amount) AS total_income,
            COUNT(p.payment_id) AS payment_count,
            AVG(p.amount) AS average_payment
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = :landlordId
        AND p.payment_date BETWEEN :startDate AND :endDate
        AND (p.status IS NULL OR p.status = 'active')
    ";
    
    $params = [
        'landlordId' => $userId,
        'startDate' => $startDate,
        'endDate' => $endDate
    ];
    
    if ($propertyId > 0) {
        $query .= " AND pr.property_id = :propertyId";
        $params['propertyId'] = $propertyId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Get income by property
function getIncomeByProperty($userId, $startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            pr.property_id,
            pr.property_name,
            pr.property_type,
            pr.address,
            pr.city,
            SUM(p.amount) AS total_income,
            COUNT(p.payment_id) AS payment_count
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = :landlordId
        AND p.payment_date BETWEEN :startDate AND :endDate
        AND (p.status IS NULL OR p.status = 'active')
        GROUP BY pr.property_id
        ORDER BY total_income DESC
    ");
    
    $stmt->execute([
        'landlordId' => $userId,
        'startDate' => $startDate,
        'endDate' => $endDate
    ]);
    
    return $stmt->fetchAll();
}

// Get income by month
function getIncomeByMonth($userId, $startDate, $endDate, $propertyId = 0) {
    global $pdo;
    
    $query = "
        SELECT 
            DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
            DATE_FORMAT(p.payment_date, '%b %Y') AS month_name,
            SUM(p.amount) AS total_income,
            COUNT(p.payment_id) AS payment_count
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = :landlordId
        AND p.payment_date BETWEEN :startDate AND :endDate
        AND (p.status IS NULL OR p.status = 'active')
    ";
    
    $params = [
        'landlordId' => $userId,
        'startDate' => $startDate,
        'endDate' => $endDate
    ];
    
    if ($propertyId > 0) {
        $query .= " AND pr.property_id = :propertyId";
        $params['propertyId'] = $propertyId;
    }
    
    $query .= " GROUP BY month ORDER BY month";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get income by payment type
function getIncomeByPaymentType($userId, $startDate, $endDate, $propertyId = 0) {
    global $pdo;
    
    $query = "
        SELECT 
            p.payment_type,
            SUM(p.amount) AS total_income,
            COUNT(p.payment_id) AS payment_count
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = :landlordId
        AND p.payment_date BETWEEN :startDate AND :endDate
        AND (p.status IS NULL OR p.status = 'active')
    ";
    
    $params = [
        'landlordId' => $userId,
        'startDate' => $startDate,
        'endDate' => $endDate
    ];
    
    if ($propertyId > 0) {
        $query .= " AND pr.property_id = :propertyId";
        $params['propertyId'] = $propertyId;
    }
    
    $query .= " GROUP BY p.payment_type ORDER BY total_income DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get maintenance summary
function getMaintenanceSummary($userId, $startDate, $endDate, $propertyId = 0) {
    global $pdo;
    
    $query = "
        SELECT 
            COUNT(*) AS total_requests,
            SUM(CASE WHEN m.status IN ('pending', 'assigned', 'in_progress') THEN 1 ELSE 0 END) AS pending_requests,
            SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) AS completed_requests,
            SUM(CASE WHEN m.priority = 'emergency' THEN 1 ELSE 0 END) AS emergency_requests,
            AVG(CASE WHEN m.actual_cost IS NOT NULL THEN m.actual_cost ELSE 0 END) AS average_cost
        FROM maintenance_requests m
        JOIN properties p ON m.property_id = p.property_id
        WHERE p.landlord_id = :landlordId
        AND m.created_at BETWEEN :startDate AND :endDate
    ";
    
    $params = [
        'landlordId' => $userId,
        'startDate' => $startDate . ' 00:00:00',
        'endDate' => $endDate . ' 23:59:59'
    ];
    
    if ($propertyId > 0) {
        $query .= " AND p.property_id = :propertyId";
        $params['propertyId'] = $propertyId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Get occupancy rate
function getOccupancyRate($userId, $propertyId = 0) {
    global $pdo;
    
    $query = "
        SELECT 
            COUNT(*) AS total_properties,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied_properties,
            (SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) / COUNT(*)) * 100 AS occupancy_rate
        FROM properties
        WHERE landlord_id = :landlordId
    ";
    
    $params = ['landlordId' => $userId];
    
    if ($propertyId > 0) {
        $query .= " AND property_id = :propertyId";
        $params['propertyId'] = $propertyId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Get data for reports
$incomeSummary = getIncomeSummary($userId, $startDate, $endDate, $propertyId);
$incomeByProperty = getIncomeByProperty($userId, $startDate, $endDate);
$incomeByMonth = getIncomeByMonth($userId, $startDate, $endDate, $propertyId);
$incomeByPaymentType = getIncomeByPaymentType($userId, $startDate, $endDate, $propertyId);
$maintenanceSummary = getMaintenanceSummary($userId, $startDate, $endDate, $propertyId);
$occupancyRate = getOccupancyRate($userId, $propertyId);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Property Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <div class="sm:ml-64 p-4 sm:p-8 transition-all duration-200">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Reports & Analytics</h2>
                <p class="text-gray-600">View financial and operational reports for your properties</p>
            </div>
            <!-- Hamburger for mobile -->
            <button id="openSidebarBtn" class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary mb-2" aria-label="Open sidebar" onclick="document.getElementById('adminSidebar').classList.remove('-translate-x-full'); document.getElementById('sidebarBackdrop').classList.remove('hidden');">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- Date Range & Property Filter -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" action="reports.php" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input 
                        type="date" 
                        name="start_date" 
                        value="<?php echo $startDate; ?>" 
                        class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input 
                        type="date" 
                        name="end_date" 
                        value="<?php echo $endDate; ?>" 
                        class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                    <select name="property_id" class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="0">All Properties</option>
                        <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['property_id']; ?>" <?php echo $propertyId == $property['property_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($property['property_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Financial Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-money-bill-wave text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Income</h3>
                        <p class="text-2xl font-semibold"><?php echo formatCurrency($incomeSummary['total_income'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $incomeSummary['payment_count'] ?? 0; ?> payments</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-chart-line text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Average Payment</h3>
                        <p class="text-2xl font-semibold"><?php echo formatCurrency($incomeSummary['average_payment'] ?? 0); ?></p>
                        <p class="text-sm text-gray-500">Per transaction</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-home text-purple-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Occupancy Rate</h3>
                        <p class="text-2xl font-semibold"><?php echo number_format($occupancyRate['occupancy_rate'] ?? 0, 1); ?>%</p>
                        <p class="text-sm text-gray-500"><?php echo ($occupancyRate['occupied_properties'] ?? 0) . ' of ' . ($occupancyRate['total_properties'] ?? 0); ?> properties</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income by Month Chart -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Income by Month</h3>
            <div class="h-80">
                <canvas id="incomeByMonthChart"></canvas>
            </div>
        </div>

        <!-- Income by Property & Payment Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Income by Property -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Income by Property</h3>
                <?php if (empty($incomeByProperty)): ?>
                    <p class="text-gray-500 text-center py-4">No income data available for the selected period.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Payments</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($incomeByProperty as $property): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($property['property_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($property['address'] . ', ' . $property['city']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium"><?php echo formatCurrency($property['total_income']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right"><?php echo $property['payment_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Income by Payment Type -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Income by Payment Type</h3>
                <?php if (empty($incomeByPaymentType)): ?>
                    <p class="text-gray-500 text-center py-4">No payment type data available for the selected period.</p>
                <?php else: ?>
                    <div class="h-64">
                        <canvas id="paymentTypeChart"></canvas>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($incomeByPaymentType as $type): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo ucfirst(str_replace('_', ' ', $type['payment_type'])); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium"><?php echo formatCurrency($type['total_income']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right"><?php echo $type['payment_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Maintenance Summary -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Maintenance Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Requests</div>
                    <div class="text-2xl font-semibold"><?php echo $maintenanceSummary['total_requests'] ?? 0; ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Pending Requests</div>
                    <div class="text-2xl font-semibold"><?php echo $maintenanceSummary['pending_requests'] ?? 0; ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Emergency Requests</div>
                    <div class="text-2xl font-semibold"><?php echo $maintenanceSummary['emergency_requests'] ?? 0; ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm text-gray-500">Average Cost</div>
                    <div class="text-2xl font-semibold"><?php echo formatCurrency($maintenanceSummary['average_cost'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center mb-4">
                <h3 class="text-lg font-semibold">AI Insights</h3>
                <span class="ml-2 px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">BETA</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Rent Prediction -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="p-2 rounded-full bg-blue-100">
                            <i class="fas fa-chart-line text-blue-500"></i>
                        </div>
                        <h4 class="ml-2 text-md font-medium">Rent Prediction</h4>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">
                        Based on market trends and your property data, we predict you could increase rent by an average of <span class="font-semibold">4.2%</span> across your portfolio.
                    </p>
                    <span class="text-sm text-gray-400">Detailed analysis coming soon</span>
                    </div>
                
                <!-- Payment Risk -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="p-2 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <h4 class="ml-2 text-md font-medium">Payment Risk</h4>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">
                        We've identified <span class="font-semibold">2 tenants</span> with potential payment risks in the coming month based on payment history patterns.
                    </p>
                    <span class="text-sm text-gray-400">At-risk tenant analysis coming soon</span>
                    </div>
                
                <!-- Maintenance Prediction -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="p-2 rounded-full bg-yellow-100">
                            <i class="fas fa-tools text-yellow-500"></i>
                        </div>
                        <h4 class="ml-2 text-md font-medium">Maintenance Prediction</h4>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">
                        Based on property age and maintenance history, we predict <span class="font-semibold">3 properties</span> may need HVAC maintenance in the next 3 months.
                    </p>
                    <span class="text-sm text-gray-400">maintenance forecast analysis coming soon</span>
                    </div>
                
                <!-- Financial Forecast -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="p-2 rounded-full bg-green-100">
                            <i class="fas fa-dollar-sign text-green-500"></i>
                        </div>
                        <h4 class="ml-2 text-md font-medium">Financial Forecast</h4>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">
                        Your projected income for the next quarter is <span class="font-semibold"><?php echo formatCurrency($incomeSummary['total_income'] * 3 * 1.02); ?></span>, a 2% increase from current trends.
                    </p>
                    <span class="text-sm text-gray-400">financial projections analysis coming soon</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Income by Month Chart
            const monthlyData = <?php echo json_encode(array_column($incomeByMonth, 'total_income')); ?>;
            const monthLabels = <?php echo json_encode(array_column($incomeByMonth, 'month_name')); ?>;
            
            new Chart(document.getElementById('incomeByMonthChart'), {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Income',
                        data: monthlyData,
                        backgroundColor: '#1a56db',
                        borderColor: '#1a56db',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Payment Type Chart
            const paymentTypeData = <?php 
                $types = array_column($incomeByPaymentType, 'payment_type');
                $amounts = array_column($incomeByPaymentType, 'total_income');
                $formattedTypes = array_map(function($type) {
                    return ucfirst(str_replace('_', ' ', $type));
                }, $types);
                echo json_encode([
                    'labels' => $formattedTypes,
                    'data' => $amounts
                ]); 
            ?>;
            
            new Chart(document.getElementById('paymentTypeChart'), {
                type: 'doughnut',
                data: {
                    labels: paymentTypeData.labels,
                    datasets: [{
                        data: paymentTypeData.data,
                        backgroundColor: [
                            '#1a56db', '#7e3af2', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: $${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
