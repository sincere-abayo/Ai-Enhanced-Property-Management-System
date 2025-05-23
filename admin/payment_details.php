<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_GET['id'];

// Get payment details
$stmt = $pdo->prepare("
    SELECT 
        pay.*,
        p.property_id,
        p.property_name,
        p.address,
        p.city,
        p.state,
        u.user_id AS tenant_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        l.lease_id,
        l.start_date AS lease_start,
        l.end_date AS lease_end,
        l.monthly_rent
    FROM payments pay
    JOIN leases l ON pay.lease_id = l.lease_id
    JOIN properties p ON l.property_id = p.property_id
    JOIN users u ON l.tenant_id = u.user_id
    WHERE pay.payment_id = :paymentId AND p.landlord_id = :landlordId
");

$stmt->execute([
    'paymentId' => $paymentId,
    'landlordId' => $userId
]);

$payment = $stmt->fetch();

// Redirect if payment not found or doesn't belong to this landlord
if (!$payment) {
    $_SESSION['error'] = "Payment not found or you don't have permission to view it";
    header("Location: payments.php");
    exit;
}



// Format payment status based on due date and payment date
function getPaymentStatus($paymentDate, $dueDate) {
    $paymentTimestamp = strtotime($paymentDate);
    $dueTimestamp = strtotime($dueDate);
    
    if ($paymentTimestamp <= $dueTimestamp) {
        return ['status' => 'on_time', 'label' => 'On Time', 'class' => 'bg-green-100 text-green-800'];
    } else {
        $daysDiff = floor(($paymentTimestamp - $dueTimestamp) / (60 * 60 * 24));
        return [
            'status' => 'late',
            'label' => 'Late (' . $daysDiff . ' days)',
            'class' => 'bg-red-100 text-red-800'
        ];
    }
}

// Calculate payment due date (typically the payment due day of the month)
$paymentDueDay = 1; // Default to 1st of the month if not specified in lease
if (isset($payment['payment_due_day'])) {
    $paymentDueDay = $payment['payment_due_day'];
}

// Get the month and year from the payment date
$paymentMonth = date('m', strtotime($payment['payment_date']));
$paymentYear = date('Y', strtotime($payment['payment_date']));

// Construct the due date
$dueDate = $paymentYear . '-' . $paymentMonth . '-' . str_pad($paymentDueDay, 2, '0', STR_PAD_LEFT);

// Get payment status
$paymentStatus = getPaymentStatus($payment['payment_date'], $dueDate);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Property Management System</title>
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
            <a href="payments.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Payment Details</h2>
                <p class="text-gray-600">
                    Payment #<?php echo $paymentId; ?> - 
                    <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                </p>
            </div>
        </div>
 <!-- Display success/error messages -->
 <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success']; ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['error']; ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Payment Information -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Main Payment Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Payment Information</h3>
                    </div>
                    <!-- At the top of the payment information section -->
<?php if (isset($payment['status']) && $payment['status'] === 'voided'): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-ban text-red-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-medium text-red-800">This payment has been voided</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>Voided on: <?php echo date('F j, Y, g:i a', strtotime($payment['voided_at'])); ?></p>
                    <p>Voided by: <?php echo htmlspecialchars($voidedByUser['first_name'] . ' ' . $voidedByUser['last_name']); ?></p>
                    <p>Reason: <?php echo htmlspecialchars($payment['void_reason']); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <!-- Add a "VOIDED" watermark to the payment amount -->
<div class="relative">
    <h4 class="text-sm font-medium text-gray-500">Amount</h4>
    <p class="text-2xl font-bold text-gray-900" data-currency-value="<?php echo $payment['amount']; ?>" data-currency-original="USD"><?php echo formatCurrency($payment['amount']); ?></p>
    <?php if (isset($payment['status']) && $payment['status'] === 'voided'): ?>
        <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-3xl font-bold text-red-500 opacity-30 transform -rotate-45">VOIDED</span>
        </div>
    <?php endif; ?>
</div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Payment Date</h4>
                                <p class="text-lg text-gray-900"><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></p>
                                <p class="text-sm text-gray-500">Due on <?php echo date('F j, Y', strtotime($dueDate)); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Payment Type</h4>
                                <p class="text-lg text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'])); ?></p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Payment Method</h4>
                                <p class="text-lg text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                            </div>
                            <?php if (!empty($payment['notes'])): ?>
                                <div class="md:col-span-2">
                                    <h4 class="text-sm font-medium text-gray-500">Notes</h4>
                                    <p class="text-gray-900 mt-1"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                       <!-- Modify the action buttons to disable editing for voided payments -->
<div class="mt-6 pt-6 border-t border-gray-200 flex justify-end space-x-4">
    <?php if (!isset($payment['status']) || $payment['status'] !== 'voided'): ?>
        <a href="edit_payment.php?id=<?php echo $paymentId; ?>" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-edit mr-2"></i>Edit Payment
        </a>
        <button onclick="confirmVoid()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
            <i class="fas fa-ban mr-2"></i>Void Payment
        </button>
    <?php else: ?>
        <button disabled class="px-4 py-2 bg-gray-300 text-gray-500 rounded-lg cursor-not-allowed">
            <i class="fas fa-edit mr-2"></i>Edit Payment
        </button>
        <a href="restore_payment.php?id=<?php echo $paymentId; ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700" onclick="return confirm('Are you sure you want to restore this payment?')">
            <i class="fas fa-undo mr-2"></i>Restore Payment
        </a>
    <?php endif; ?>
</div>
                    </div>
                </div>
            </div>
            
            <!-- Related Information -->
            <div>
                <!-- Property Information -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Property</h3>
                    </div>
                    <div class="p-6">
                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['property_name']); ?></h4>
                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($payment['address']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($payment['city'] . ', ' . $payment['state']); ?></p>
                        <div class="mt-4">
                            <a href="property_details.php?id=<?php echo $payment['property_id']; ?>" class="text-primary hover:text-blue-700">
                                View Property Details
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Tenant Information -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Tenant</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($payment['first_name'] . '+' . $payment['last_name']); ?>" alt="Tenant">
                            </div>
                            <div class="ml-4">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></h4>
                                <p class="text-gray-600"><?php echo htmlspecialchars($payment['email']); ?></p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="tenant_details.php?id=<?php echo $payment['tenant_id']; ?>" class="text-primary hover:text-blue-700">
                                View Tenant Details
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Lease Information -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Lease</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-2">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Lease Period</h4>
                                <p class="text-gray-900">
                                    <?php echo date('M j, Y', strtotime($payment['lease_start'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($payment['lease_end'])); ?>
                                </p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500">Monthly Rent</h4>
                                <p class="text-gray-900" data-currency-value="<?php echo $payment['monthly_rent']; ?>" data-currency-original="USD"><?php echo formatCurrency($payment['monthly_rent']); ?></p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="lease_details.php?id=<?php echo $payment['lease_id']; ?>" class="text-primary hover:text-blue-700">
                                View Lease Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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
                <form method="POST" action="delete_payment.php">
                    <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
<!-- Add this modal for voiding -->
<div id="voidModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Void Payment</h3>
        <p class="text-gray-700 mb-4">Voiding a payment will mark it as invalid but keep it in the system for record-keeping purposes.</p>
        
        <form method="POST" action="void_payment.php">
            <input type="hidden" name="payment_id" value="<?php echo $paymentId; ?>">
            
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
    <script>
        // Delete confirmation
        function confirmDelete() {
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
    function confirmVoid() {
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
    </script>
</body>
</html>
