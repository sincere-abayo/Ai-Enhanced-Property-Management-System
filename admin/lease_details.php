<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if lease ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid lease ID";
    header("Location: leases.php");
    exit;
}

$leaseId = (int)$_GET['id'];

// Get lease details
function getLeaseDetails($leaseId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT l.*, 
               p.property_name, p.address, p.city, p.state, p.zip_code, p.landlord_id,
               u.first_name as tenant_first_name, u.last_name as tenant_last_name, 
               u.email as tenant_email, u.phone as tenant_phone
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE l.lease_id = :leaseId AND p.landlord_id = :landlordId
    ");
    $stmt->execute([
        'leaseId' => $leaseId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get payments for this lease
function getLeasePayments($leaseId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT *
        FROM payments
        WHERE lease_id = :leaseId
        ORDER BY payment_date DESC
    ");
    $stmt->execute(['leaseId' => $leaseId]);
    
    return $stmt->fetchAll();
}

// Get lease data
$lease = getLeaseDetails($leaseId, $userId);

// Check if lease exists and belongs to a property owned by the current landlord
if (!$lease) {
    $_SESSION['error'] = "Lease not found or you don't have permission to view it";
    header("Location: leases.php");
    exit;
}

// Get payments
$payments = getLeasePayments($leaseId);

// Calculate lease statistics
$totalPaid = 0;
$totalDue = 0;
$nextPaymentDate = null;

foreach ($payments as $payment) {
    if ($payment['payment_type'] === 'rent') {
        $totalPaid += $payment['amount'];
    }
}

// Calculate total due (monthly rent * lease duration in months)
$startDate = new DateTime($lease['start_date']);
$endDate = new DateTime($lease['end_date']);
$interval = $startDate->diff($endDate);
$leaseMonths = ($interval->y * 12) + $interval->m + ($interval->d > 0 ? 1 : 0);
$totalDue = $lease['monthly_rent'] * $leaseMonths;

// Calculate next payment date
$today = new DateTime();
$paymentDueDay = $lease['payment_due_day'];
$nextPaymentDate = new DateTime();
$nextPaymentDate->setDate($today->format('Y'), $today->format('m'), $paymentDueDay);

if ($today > $nextPaymentDate) {
    $nextPaymentDate->modify('+1 month');
}

// Process status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE leases
            SET status = :status
            WHERE lease_id = :leaseId
        ");
        
        $stmt->execute([
            'status' => $newStatus,
            'leaseId' => $leaseId
        ]);
        
        // If lease is terminated, update property status if needed
        if ($newStatus === 'terminated') {
            // Check if there are other active leases for this property
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM leases
                WHERE property_id = :propertyId 
                AND status = 'active'
                AND lease_id != :leaseId
            ");
            $stmt->execute([
                'propertyId' => $lease['property_id'],
                'leaseId' => $leaseId
            ]);
            
            if ($stmt->fetch()['count'] == 0) {
                // No other active leases, update property status to vacant
                $stmt = $pdo->prepare("
                    UPDATE properties
                    SET status = 'vacant'
                    WHERE property_id = :propertyId
                ");
                $stmt->execute(['propertyId' => $lease['property_id']]);
            }
        }
        
        $_SESSION['success'] = "Lease status updated successfully!";
        header("Location: lease_details.php?id=" . $leaseId);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
}

// Process add payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $amount = (float)$_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    $paymentMethod = $_POST['payment_method'];
    $paymentType = $_POST['payment_type'];
    $notes = trim($_POST['notes']);
    
    if ($amount <= 0) {
        $_SESSION['error'] = "Payment amount must be greater than zero";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    lease_id, amount, payment_date, payment_method, payment_type, notes
                ) VALUES (
                    :leaseId, :amount, :paymentDate, :paymentMethod, :paymentType, :notes
                )
            ");
            
            $stmt->execute([
                'leaseId' => $leaseId,
                'amount' => $amount,
                'paymentDate' => $paymentDate,
                'paymentMethod' => $paymentMethod,
                'paymentType' => $paymentType,
                'notes' => $notes
            ]);
            
            $_SESSION['success'] = "Payment added successfully!";
            header("Location: lease_details.php?id=" . $leaseId);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding payment: " . $e->getMessage();
        }
    }
}

// Get status class for styling
function getStatusClass($status) {
    switch ($status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'expired':
            return 'bg-yellow-100 text-yellow-800';
        case 'terminated':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Process payment deletion
if (isset($_GET['delete_payment']) && is_numeric($_GET['delete_payment'])) {
    $paymentId = (int)$_GET['delete_payment'];
    
    try {
        // First verify this payment belongs to the current lease
        $checkStmt = $pdo->prepare("
            SELECT payment_id FROM payments 
            WHERE payment_id = :paymentId AND lease_id = :leaseId
        ");
        $checkStmt->execute([
            'paymentId' => $paymentId,
            'leaseId' => $leaseId
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            // Payment belongs to this lease, proceed with deletion
            $deleteStmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = :paymentId");
            $deleteStmt->execute(['paymentId' => $paymentId]);
            
            $_SESSION['success'] = "Payment deleted successfully!";
        } else {
            $_SESSION['error'] = "Payment not found or does not belong to this lease";
        }
        
        header("Location: lease_details.php?id=" . $leaseId);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting payment: " . $e->getMessage();
        header("Location: lease_details.php?id=" . $leaseId);
        exit;
    }
}

// Process payment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $paymentId = (int)$_POST['payment_id'];
    $amount = (float)$_POST['edit_amount'];
    $paymentDate = $_POST['edit_payment_date'];
    $paymentMethod = $_POST['edit_payment_method'];
    $paymentType = $_POST['edit_payment_type'];
    $notes = trim($_POST['edit_notes']);
    
    if ($amount <= 0) {
        $_SESSION['error'] = "Payment amount must be greater than zero";
    } else {
        try {
            // First verify this payment belongs to the current lease
            $checkStmt = $pdo->prepare("
                SELECT payment_id FROM payments 
                WHERE payment_id = :paymentId AND lease_id = :leaseId
            ");
            $checkStmt->execute([
                'paymentId' => $paymentId,
                'leaseId' => $leaseId
            ]);
            
            if ($checkStmt->rowCount() > 0) {
                // Payment belongs to this lease, proceed with update
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET amount = :amount,
                        payment_date = :paymentDate,
                        payment_method = :paymentMethod,
                        payment_type = :paymentType,
                        notes = :notes
                    WHERE payment_id = :paymentId
                ");
                
                $stmt->execute([
                    'amount' => $amount,
                    'paymentDate' => $paymentDate,
                    'paymentMethod' => $paymentMethod,
                    'paymentType' => $paymentType,
                    'notes' => $notes,
                    'paymentId' => $paymentId
                ]);
                
                $_SESSION['success'] = "Payment updated successfully!";
            } else {
                $_SESSION['error'] = "Payment not found or does not belong to this lease";
            }
            
            header("Location: lease_details.php?id=" . $leaseId);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating payment: " . $e->getMessage();
        }
    }
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
    <title>Lease Details - Property Management System</title>
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
            <a href="leases.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Lease Details</h2>
                <p class="text-gray-600">Lease ID: #<?php echo $leaseId; ?></p>
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

        <!-- Lease Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Main Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-start mb-6">
                        <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($lease['property_name']); ?> Lease</h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusClass($lease['status']); ?>">
                            <?php echo ucfirst($lease['status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Property</h4>
                            <p class="text-gray-700"><?php echo htmlspecialchars($lease['property_name']); ?></p>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($lease['address'] . ', ' . $lease['city'] . ', ' . $lease['state'] . ' ' . $lease['zip_code']); ?>
                            </p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Tenant</h4>
                            <p class="text-gray-700">
                                <?php echo htmlspecialchars($lease['tenant_first_name'] . ' ' . $lease['tenant_last_name']); ?>
                            </p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($lease['tenant_email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Start Date</h4>
                            <p class="text-gray-700"><?php echo date('M j, Y', strtotime($lease['start_date'])); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">End Date</h4>
                            <p class="text-gray-700"><?php echo date('M j, Y', strtotime($lease['end_date'])); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Duration</h4>
                            <p class="text-gray-700"><?php echo $leaseMonths; ?> months</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Monthly Rent</h4>
                            <p class="text-gray-700"><?php echo formatCurrency($lease['monthly_rent']); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Security Deposit</h4>
                            <p class="text-gray-700"><?php echo formatCurrency($lease['security_deposit']); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Payment Due Day</h4>
                            <p class="text-gray-700"><?php echo $lease['payment_due_day']; ?> of each month</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Total Lease Value</h4>
                            <p class="text-gray-700"><?php echo formatCurrency($totalDue); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Total Paid</h4>
                            <p class="text-gray-700"><?php echo formatCurrency($totalPaid); ?></p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 mb-1">Next Payment Due</h4>
                            <p class="text-gray-700"><?php echo $nextPaymentDate->format('M j, Y'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Update Status Form -->
                    <div class="border-t pt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Update Lease Status</h4>
                        <form method="POST" action="lease_details.php?id=<?php echo $leaseId; ?>" class="flex items-center space-x-4">
                            <select name="status" class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="active" <?php echo $lease['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $lease['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="terminated" <?php echo $lease['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                            <button type="submit" name="update_status" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                                Update Status
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Details -->
            <div>
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="property_details.php?id=<?php echo $lease['property_id']; ?>" class="block w-full text-center px-4 py-2 bg-blue-50 text-primary rounded-lg hover:bg-blue-100">
                            View Property
                        </a>
                        <a href="tenant_details.php?id=<?php echo $lease['tenant_id']; ?>" class="block w-full text-center px-4 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100">
                            View Tenant
                        </a>
                        <button onclick="document.getElementById('addPaymentModal').classList.remove('hidden')" class="block w-full text-center px-4 py-2 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100">
                            Record Payment
                        </button>
                        <a href="edit_lease.php?id=<?php echo $leaseId; ?>" class="block w-full text-center px-4 py-2 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100">
                            Edit Lease
                        </a>
                    </div>
                </div>
                
                <!-- Lease Status -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">Lease Status</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-500">Lease Progress</span>
                                <?php 
                                    $startDate = new DateTime($lease['start_date']);
                                    $endDate = new DateTime($lease['end_date']);
                                    $today = new DateTime();
                                    $totalDays = $startDate->diff($endDate)->days;
                                    $daysElapsed = $startDate->diff($today)->days;
                                    $progress = min(100, max(0, ($daysElapsed / $totalDays) * 100));
                                ?>
                                <span class="text-sm text-gray-700"><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-primary h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-500">Payment Status</span>
                                <?php 
                                    $paymentProgress = min(100, max(0, ($totalPaid / $totalDue) * 100));
                                ?>
                                <span class="text-sm text-gray-700"><?php echo round($paymentProgress); ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $paymentProgress; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="pt-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium">Days Remaining</span>
                                <?php 
                                    $daysRemaining = max(0, $today->diff($endDate)->days);
                                ?>
                                <span class="text-sm font-semibold"><?php echo $daysRemaining; ?> days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payments History -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold">Payment History</h3>
                <button onclick="document.getElementById('addPaymentModal').classList.remove('hidden')" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Record Payment
                </button>
            </div>
            
            <?php if (empty($payments)): ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No payments have been recorded yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Use the "Record Payment" button to add a payment.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($payment['amount']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $payment['payment_type'] === 'rent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['notes'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editPayment(<?php echo $payment['payment_id']; ?>, '<?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?>', <?php echo $payment['amount']; ?>, '<?php echo $payment['payment_type']; ?>', '<?php echo $payment['payment_method']; ?>', '<?php echo htmlspecialchars(addslashes($payment['notes'] ?: '')); ?>')" class="text-primary hover:text-blue-700 mr-3">
                                            Edit
                                        </button>
                                        <a href="lease_details.php?id=<?php echo $leaseId; ?>&delete_payment=<?php echo $payment['payment_id']; ?>" onclick="return confirm('Are you sure you want to delete this payment?')" class="text-red-600 hover:text-red-900">Delete</a>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Payment Modal -->
    <div id="addPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-lg w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Record Payment</h3>
                <button onclick="document.getElementById('addPaymentModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="lease_details.php?id=<?php echo $leaseId; ?>" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="number" name="amount" min="0.01" step="0.01" value="<?php echo $lease['monthly_rent']; ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                        <select name="payment_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="rent">Rent</option>
                            <option value="security_deposit">Security Deposit</option>
                            <option value="late_fee">Late Fee</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="document.getElementById('addPaymentModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="add_payment" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Payment Modal -->
    <div id="editPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-lg w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Edit Payment</h3>
                <button onclick="document.getElementById('editPaymentModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="lease_details.php?id=<?php echo $leaseId; ?>" class="space-y-4">
                <input type="hidden" name="payment_id" id="edit_payment_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="number" name="edit_amount" id="edit_amount" min="0.01" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input type="date" name="edit_payment_date" id="edit_payment_date" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="edit_payment_method" id="edit_payment_method" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                        <select name="edit_payment_type" id="edit_payment_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="rent">Rent</option>
                            <option value="security_deposit">Security Deposit</option>
                            <option value="late_fee">Late Fee</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="edit_notes" id="edit_notes" rows="2" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="document.getElementById('editPaymentModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="update_payment" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    // Close modals when clicking outside
    document.getElementById('addPaymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    document.getElementById('editPaymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Function to open edit payment modal with data
    function editPayment(paymentId, paymentDate, amount, paymentType, paymentMethod, notes) {
        document.getElementById('edit_payment_id').value = paymentId;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_payment_date').value = paymentDate;
        document.getElementById('edit_payment_type').value = paymentType;
        document.getElementById('edit_payment_method').value = paymentMethod;
        document.getElementById('edit_notes').value = notes;
        
        document.getElementById('editPaymentModal').classList.remove('hidden');
        document.getElementById('editPaymentModal').classList.add('flex');
    }


        // Close modal when clicking outside
        document.getElementById('addPaymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
