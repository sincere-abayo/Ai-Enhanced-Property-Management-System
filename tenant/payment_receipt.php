<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

$userId = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_GET['id'];

// Get payment details
$stmt = $pdo->prepare("
    SELECT p.*, l.*, pr.property_name, pr.address, pr.city, pr.state, pr.zip_code,
           u.first_name as tenant_first_name, u.last_name as tenant_last_name,
           landlord.first_name as landlord_first_name, landlord.last_name as landlord_last_name
    FROM payments p
    JOIN leases l ON p.lease_id = l.lease_id
    JOIN properties pr ON l.property_id = pr.property_id
    JOIN users u ON l.tenant_id = u.user_id
    JOIN users landlord ON pr.landlord_id = landlord.user_id
    WHERE p.payment_id = ? AND l.tenant_id = ?
");
$stmt->execute([$paymentId, $userId]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "Payment not found or you don't have permission to view it";
    header("Location: payments.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Tenant Portal</title>
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
        <!-- Header with Back Button -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <a href="payments.php" class="mr-4 text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Payment Receipt</h2>
                    <p class="text-gray-600">Receipt #<?php echo $payment['payment_id']; ?></p>
                </div>
            </div>
            <button onclick="printReceipt()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i>Print Receipt
            </button>
        </div>

        <!-- Receipt -->
        <div id="receipt" class="bg-white rounded-xl shadow-md p-8 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Payment Receipt</h1>
                <p class="text-gray-600">Property Management System</p>
                <div class="mt-4 p-4 bg-green-50 rounded-lg inline-block">
                    <p class="text-green-800 font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>Payment Confirmed
                    </p>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <!-- Payment Information -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Payment Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Receipt Number:</span>
                            <span class="font-medium">#<?php echo $payment['payment_id']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Date:</span>
                            <span
                                class="font-medium"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-bold text-lg text-green-600"
                                data-currency-value="<?= $payment['amount'] ?>" data-currency-original="USD">
                                <?php echo formatCurrency($payment['amount']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-medium flex items-center">
                                <?php if ($payment['payment_method'] == 'paypal'): ?>
                                <i class="fab fa-paypal text-blue-600 mr-2"></i>PayPal
                                <?php else: ?>
                                <i class="fas fa-money-bill-wave text-gray-600 mr-2"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($payment['gateway_transaction_id']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Transaction ID:</span>
                            <span
                                class="font-medium text-sm"><?php echo htmlspecialchars($payment['gateway_transaction_id']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Type:</span>
                            <span class="font-medium"><?php echo ucfirst($payment['payment_type']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Property Information -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Property Information</h3>
                    <div class="space-y-3">
                        <div>
                            <span class="text-gray-600 block">Property:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($payment['property_name']); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-600 block">Address:</span>
                            <span class="font-medium">
                                <?php echo htmlspecialchars($payment['address']); ?><br>
                                <?php echo htmlspecialchars($payment['city']); ?>,
                                <?php echo htmlspecialchars($payment['state']); ?>
                                <?php echo htmlspecialchars($payment['zip_code']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600 block">Lease Period:</span>
                            <span class="font-medium">
                                <?php echo date('M j, Y', strtotime($payment['start_date'])); ?> -
                                <?php echo date('M j, Y', strtotime($payment['end_date'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant and Landlord Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 pt-8 border-t">
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Tenant Information</h3>
                    <p class="font-medium">
                        <?php echo htmlspecialchars($payment['tenant_first_name'] . ' ' . $payment['tenant_last_name']); ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Landlord Information</h3>
                    <p class="font-medium">
                        <?php echo htmlspecialchars($payment['landlord_first_name'] . ' ' . $payment['landlord_last_name']); ?>
                    </p>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($payment['notes']): ?>
            <div class="pt-8 border-t">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Notes</h3>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="text-center mt-8 pt-8 border-t text-sm text-gray-500">
                <p>This is an official payment receipt generated by the Property Management System.</p>
                <p class="mt-2">Generated on <?php echo date('M j, Y g:i A'); ?></p>
            </div>
        </div>
    </div>

    <script>
    function printReceipt() {
        window.print();
    }

    // Print styles
    const style = document.createElement('style');
    style.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                #receipt, #receipt * {
                    visibility: visible;
                }
                #receipt {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    box-shadow: none;
                }
                .ml-64 {
                    margin-left: 0 !important;
                }
            }
        `;
    document.head.appendChild(style);
    </script>
</body>

</html>