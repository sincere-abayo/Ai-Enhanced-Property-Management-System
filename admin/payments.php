<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize filters
$propertyFilter = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;
$tenantFilter = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$paymentType = isset($_GET['payment_type']) ? $_GET['payment_type'] : null;
$paymentMethod = isset($_GET['payment_method']) ? $_GET['payment_method'] : null;

// Get all properties for this landlord (for filter dropdown)
$stmt = $pdo->prepare("
    SELECT property_id, property_name
    FROM properties
    WHERE landlord_id = ?
    ORDER BY property_name
");
$stmt->execute([$userId]);
$properties = $stmt->fetchAll();

// Get all tenants for this landlord (for filter dropdown)
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.first_name, u.last_name
    FROM users u
    JOIN leases l ON u.user_id = l.tenant_id
    JOIN properties p ON l.property_id = p.property_id
    WHERE p.landlord_id = ? AND u.role = 'tenant'
    ORDER BY u.first_name, u.last_name
");
$stmt->execute([$userId]);
$tenants = $stmt->fetchAll();

// Build query for payments
// Build query for payments
$query = "
    SELECT 
        pay.payment_id,
        pay.amount,
        pay.payment_date,
        pay.payment_method,
        pay.payment_type,
        pay.notes,
        pay.status,
        p.property_id,
        p.property_name,
        u.user_id AS tenant_id,
        u.first_name,
        u.last_name,
        l.lease_id
    FROM payments pay
    JOIN leases l ON pay.lease_id = l.lease_id
    JOIN properties p ON l.property_id = p.property_id
    JOIN users u ON l.tenant_id = u.user_id
    WHERE p.landlord_id = :landlordId
";


$params = ['landlordId' => $userId];

// Apply filters
if ($propertyFilter) {
    $query .= " AND p.property_id = :propertyId";
    $params['propertyId'] = $propertyFilter;
}

if ($tenantFilter) {
    $query .= " AND u.user_id = :tenantId";
    $params['tenantId'] = $tenantFilter;
}

if ($startDate) {
    $query .= " AND pay.payment_date >= :startDate";
    $params['startDate'] = $startDate;
}

if ($endDate) {
    $query .= " AND pay.payment_date <= :endDate";
    $params['endDate'] = $endDate;
}

if ($paymentType) {
    $query .= " AND pay.payment_type = :paymentType";
    $params['paymentType'] = $paymentType;
}

if ($paymentMethod) {
    $query .= " AND pay.payment_method = :paymentMethod";
    $params['paymentMethod'] = $paymentMethod;
}

// Add order by
$query .= " ORDER BY pay.payment_date DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Calculate total amount
$totalAmount = 0;
foreach ($payments as $payment) {
    $totalAmount += $payment['amount'];
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Payments</h2>
                <p class="text-gray-600">Manage and track all payment records</p>
            </div>
            <div class="flex space-x-4">
                <a href="#" onclick="toggleFilters()" class="bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-filter mr-2"></i>Filters
                </a>
                <button onclick="exportPayments()" class="bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
                <a href="record_payment.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Record Payment
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div id="filtersContainer" class="bg-white rounded-xl shadow-md p-6 mb-8 hidden">
            <form method="GET" action="payments.php" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                    <select name="property_id" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">All Properties</option>
                        <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['property_id']; ?>" <?php echo $propertyFilter == $property['property_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($property['property_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                    <select name="tenant_id" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">All Tenants</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['user_id']; ?>" <?php echo $tenantFilter == $tenant['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                    <select name="payment_type" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">All Types</option>
                        <option value="rent" <?php echo $paymentType == 'rent' ? 'selected' : ''; ?>>Rent</option>
                        <option value="security_deposit" <?php echo $paymentType == 'security_deposit' ? 'selected' : ''; ?>>Security Deposit</option>
                        <option value="late_fee" <?php echo $paymentType == 'late_fee' ? 'selected' : ''; ?>>Late Fee</option>
                        <option value="other" <?php echo $paymentType == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo $paymentMethod == 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="check" <?php echo $paymentMethod == 'check' ? 'selected' : ''; ?>>Check</option>
                        <option value="bank_transfer" <?php echo $paymentMethod == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="credit_card" <?php echo $paymentMethod == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="other" <?php echo $paymentMethod == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
                <div class="md:col-span-3 flex justify-end space-x-4">
                    <a href="payments.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Clear Filters
                    </a>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Card -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Total Payments</h3>
                    <p class="text-2xl font-semibold"><?php echo count($payments); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Total Amount</h3>
                    <p class="text-2xl font-semibold text-green-600"><?php echo formatCurrency($totalAmount); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Date Range</h3>
                    <p class="text-lg font-semibold">
                        <?php 
                        if ($startDate && $endDate) {
                            echo date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
                        } elseif ($startDate) {
                            echo 'From ' . date('M j, Y', strtotime($startDate));
                        } elseif ($endDate) {
                            echo 'Until ' . date('M j, Y', strtotime($endDate));
                        } else {
                            echo 'All Time';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($payments)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No payment records found</p>
                    <p class="mt-2">
                        <a href="record_payment.php" class="text-primary hover:underline">Record a payment</a> or 
                        <a href="payments.php" class="text-primary hover:underline">clear filters</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($payments as $payment): ?>
                <tr class="<?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'bg-gray-100' : ''; ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-900'; ?>">
                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-900'; ?>">
                        <a href="property_details.php?id=<?php echo $payment['property_id']; ?>" class="<?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'hover:text-primary'; ?>">
                            <?php echo htmlspecialchars($payment['property_name']); ?>
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-900'; ?>">
                        <a href="tenant_details.php?id=<?php echo $payment['tenant_id']; ?>" class="<?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'hover:text-primary'; ?>">
                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-900'; ?>">
                        <?php if (isset($payment['status']) && $payment['status'] === 'voided'): ?>
                            <span class="line-through"><?php echo formatCurrency($payment['amount']); ?></span>
                        <?php else: ?>
                            <?php echo formatCurrency($payment['amount']); ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-500'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo (isset($payment['status']) && $payment['status'] === 'voided') ? 'text-gray-500' : 'text-gray-500'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if (isset($payment['status']) && $payment['status'] === 'voided'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                VOIDED
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                ACTIVE
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="payment_details.php?id=<?php echo $payment['payment_id']; ?>" class="text-primary hover:text-blue-700 mr-3">View</a>
                        
                        <?php if (!isset($payment['status']) || $payment['status'] !== 'voided'): ?>
                            <a href="edit_payment.php?id=<?php echo $payment['payment_id']; ?>" class="text-primary hover:text-blue-700 mr-3">Edit</a>
                            <a href="#" onclick="confirmVoid(<?php echo $payment['payment_id']; ?>)" class="text-red-600 hover:text-red-900">Void</a>
                        <?php else: ?>
                            <a href="#" onclick="confirmRestore(<?php echo $payment['payment_id']; ?>)" class="text-green-600 hover:text-green-700">
    Restore
</a>

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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Confirm Delete</h3>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this payment record? This action cannot be undone.</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" action="delete_payment.php">
                    <input type="hidden" id="deletePaymentId" name="payment_id" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
<!-- Void Confirmation Modal -->
<div id="voidModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Void Payment</h3>
        <p class="text-gray-700 mb-4">Voiding a payment will mark it as invalid but keep it in the system for record-keeping purposes.</p>
        
        <form id="voidForm" method="POST" action="void_payment.php">
            <input type="hidden" id="voidPaymentId" name="payment_id" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Voiding</label>
                <textarea 
                    name="void_reason" 
                    rows="3" 
                    required
                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                    placeholder="Please explain why this payment is being voided..."
                ></textarea>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeVoidModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Void Payment
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Restore Payment</h3>
        <p class="text-gray-700 mb-6">Are you sure you want to restore this payment? This will mark the payment as active again.</p>
        <div class="flex justify-end space-x-4">
            <button onclick="closeRestoreModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <a id="restorePaymentLink" href="#" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-center">
                Restore Payment
            </a>
        </div>
    </div>
</div>

    <script>
        // Toggle filters visibility
        function toggleFilters() {
            const filtersContainer = document.getElementById('filtersContainer');
            filtersContainer.classList.toggle('hidden');
        }

        // Show filters if any are applied
        <?php if ($propertyFilter || $tenantFilter || $startDate || $endDate || $paymentType || $paymentMethod): ?>
            document.getElementById('filtersContainer').classList.remove('hidden');
        <?php endif; ?>

        // Export payments to CSV
        function exportPayments() {
            window.location.href = 'export_payments.php<?php echo $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>';
        }

        // Delete confirmation
        function confirmDelete(paymentId) {
            document.getElementById('deletePaymentId').value = paymentId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        // Void confirmation
    function confirmVoid(paymentId) {
        document.getElementById('voidPaymentId').value = paymentId;
        document.getElementById('voidModal').classList.remove('hidden');
        document.getElementById('voidModal').classList.add('flex');
    }

    function closeVoidModal() {
        document.getElementById('voidModal').classList.add('hidden');
        document.getElementById('voidModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('voidModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVoidModal();
        }
    });
    // Restore confirmation
    function confirmRestore(paymentId) {
        document.getElementById('restorePaymentLink').href = 'restore_payment.php?id=' + paymentId;
        document.getElementById('restoreModal').classList.remove('hidden');
        document.getElementById('restoreModal').classList.add('flex');
    }

    function closeRestoreModal() {
        document.getElementById('restoreModal').classList.add('hidden');
        document.getElementById('restoreModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('restoreModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRestoreModal();
        }
    });
    </script>
</body>
</html>
