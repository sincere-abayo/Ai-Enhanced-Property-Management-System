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
        p.landlord_id,
        u.user_id AS tenant_id,
        u.first_name,
        u.last_name,
        l.lease_id,
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
    $_SESSION['error'] = "Payment not found or you don't have permission to edit it";
    header("Location: payments.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $amount = (float)$_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    $paymentMethod = $_POST['payment_method'];
    $paymentType = $_POST['payment_type'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    $errors = [];
    if ($amount <= 0) $errors[] = "Amount must be greater than zero";
    if (empty($paymentDate)) $errors[] = "Payment date is required";
    
    if (empty($errors)) {
        try {
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
            header("Location: payment_details.php?id=" . $paymentId);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
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
    <title>Edit Payment - Property Management System</title>
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
            <a href="payment_details.php?id=<?php echo $paymentId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Payment</h2>
                <p class="text-gray-600">
                    Payment #<?php echo $paymentId; ?> for 
                    <?php echo htmlspecialchars($payment['property_name']); ?> - 
                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                </p>
            </div>
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

        <!-- Payment Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="edit_payment.php?id=<?php echo $paymentId; ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input 
                            type="number" 
                            name="amount" 
                            min="0.01" 
                            step="0.01" 
                            value="<?php echo $payment['amount']; ?>" 
                            required 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input 
                            type="date" 
                            name="payment_date" 
                            value="<?php echo $payment['payment_date']; ?>" 
                            required 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="cash" <?php echo $payment['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="check" <?php echo $payment['payment_method'] === 'check' ? 'selected' : ''; ?>>Check</option>
                            <option value="bank_transfer" <?php echo $payment['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="credit_card" <?php echo $payment['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="other" <?php echo $payment['payment_method'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                        <select name="payment_type" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="rent" <?php echo $payment['payment_type'] === 'rent' ? 'selected' : ''; ?>>Rent</option>
                            <option value="security_deposit" <?php echo $payment['payment_type'] === 'security_deposit' ? 'selected' : ''; ?>>Security Deposit</option>
                            <option value="late_fee" <?php echo $payment['payment_type'] === 'late_fee' ? 'selected' : ''; ?>>Late Fee</option>
                            <option value="other" <?php echo $payment['payment_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea 
                            name="notes" 
                            rows="3" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        ><?php echo htmlspecialchars($payment['notes']); ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="payment_details.php?id=<?php echo $paymentId; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="update_payment" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update Payment
                    </button>
                </div>
            </form>
        </div>

        <!-- Related Information -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Property</h4>
                    <p class="text-gray-900"><?php echo htmlspecialchars($payment['property_name']); ?></p>
                    <a href="property_details.php?id=<?php echo $payment['property_id']; ?>" class="text-primary text-sm hover:text-blue-700">
                        View Property
                    </a>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Tenant</h4>
                    <p class="text-gray-900"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                    <a href="tenant_details.php?id=<?php echo $payment['tenant_id']; ?>" class="text-primary text-sm hover:text-blue-700">
                        View Tenant
                    </a>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Lease</h4>
                    <p class="text-gray-900">Monthly Rent: <?php echo formatCurrency($payment['monthly_rent']); ?></p>
                    <a href="lease_details.php?id=<?php echo $payment['lease_id']; ?>" class="text-primary text-sm hover:text-blue-700">
                        View Lease
                    </a>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Payment Record</h4>
                    <p class="text-gray-900">Created on <?php echo date('M j, Y', strtotime($payment['created_at'])); ?></p>
                    <a href="payment_details.php?id=<?php echo $paymentId; ?>" class="text-primary text-sm hover:text-blue-700">
                        View Payment Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
