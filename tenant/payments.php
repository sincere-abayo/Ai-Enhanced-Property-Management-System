<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/paypal_config.php';

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

// Get payment history
$stmt = $pdo->prepare("
    SELECT p.*, l.property_id, pr.property_name
    FROM payments p
    JOIN leases l ON p.lease_id = l.lease_id
    JOIN properties pr ON l.property_id = pr.property_id
    WHERE l.tenant_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$userId]);
$payments = $stmt->fetchAll();

// Calculate payment statistics
$totalPaid = 0;
$paymentMonths = [];

foreach ($payments as $payment) {
    if ($payment['payment_type'] == 'rent') {
        $totalPaid += $payment['amount'];
        
        // Track unique months for payment history length
        $month = date('Y-m', strtotime($payment['payment_date']));
        $paymentMonths[$month] = true;
    }
}

$paymentHistoryMonths = count($paymentMonths);

// Check for success or error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Tenant Portal</title>
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
    <div class="sm:ml-64 p-4 sm:p-8 transition-all duration-200">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Payments</h2>
                <p class="text-gray-600">Manage your rent payments and view payment history</p>
            </div>
            <!-- Hamburger for mobile -->
            <button id="openSidebarBtn" class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary mb-2" aria-label="Open sidebar" onclick="document.getElementById('tenantSidebar').classList.remove('-translate-x-full'); document.getElementById('sidebarBackdrop').classList.remove('hidden');">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="flex space-x-4">
                <button onclick="exportPaymentHistory()"
                    class="bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-download mr-2"></i>Download History
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Payment Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Next Payment</h3>
                        <?php if ($lease): ?>
                        <p class="text-2xl font-semibold" data-currency-value="<?= $nextPayment['amount'] ?>"
                            data-currency-original="USD"><?php echo formatCurrency($nextPayment['amount']); ?></p>
                        <p class="text-sm <?php echo $daysUntilDue <= 5 ? 'text-red-500' : 'text-green-500'; ?>">
                            Due in <?php echo $daysUntilDue; ?> days
                        </p>
                        <?php else: ?>
                        <p class="text-2xl font-semibold">N/A</p>
                        <p class="text-sm text-yellow-500">No active lease</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-history text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Payment History</h3>
                        <p class="text-2xl font-semibold"><?php echo $paymentHistoryMonths; ?> Months</p>
                        <?php if ($paymentHistoryMonths > 0): ?>
                        <p class="text-sm text-green-500">All payments on time</p>
                        <?php else: ?>
                        <p class="text-sm text-gray-500">No payment history</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-receipt text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Paid</h3>
                        <p class="text-2xl font-semibold" data-currency-value="<?= $totalPaid ?>"
                            data-currency-original="USD"><?php echo formatCurrency($totalPaid); ?></p>
                        <p class="text-sm text-green-500">Last 12 months</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($lease): ?>
        <!-- Make Payment Section -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Make Payment</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Payment Information -->
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium mb-2">Payment Details</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount Due:</span>
                            <span class="font-semibold" data-currency-value="<?= $nextPayment['amount'] ?>"
                                data-currency-original="USD"><?php echo formatCurrency($nextPayment['amount']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Due Date:</span>
                            <span><?php echo $nextPayment['formatted_date']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Property:</span>
                            <span><?php echo htmlspecialchars($lease['property_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment For:</span>
                            <span>Monthly Rent</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Options -->
                <div class="space-y-4">
                    <h4 class="font-medium">Choose Payment Method</h4>

                    <!-- PayPal Payment Button -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fab fa-paypal text-blue-600 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="font-medium">PayPal</h5>
                                    <p class="text-sm text-gray-600">Pay securely with PayPal</p>
                                </div>
                            </div>
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Secure</span>
                        </div>
                        <form action="process_paypal_payment.php" method="POST">
                            <input type="hidden" name="lease_id" value="<?php echo $lease['lease_id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo $nextPayment['amount']; ?>">
                            <input type="hidden" name="description"
                                value="Rent payment for <?php echo htmlspecialchars($lease['property_name']); ?>">
                            <button type="submit"
                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                                <i class="fab fa-paypal mr-2"></i>Pay with PayPal
                            </button>
                        </form>
                    </div>

                    <!-- Manual Payment Option -->
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <i class="fas fa-money-bill-wave text-green-600 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="font-medium">Other Payment Methods</h5>
                                    <p class="text-sm text-gray-600">Cash, Bank Transfer, etc.</p>
                                </div>
                            </div>
                        </div>
                        <button onclick="openManualPaymentModal()"
                            class="w-full bg-gray-600 text-white py-3 px-4 rounded-lg hover:bg-gray-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Record Manual Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Manual Payment Modal -->
        <div id="manualPaymentModal"
            class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Record Manual Payment</h3>
                    <button onclick="closeManualPaymentModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="process_manual_payment.php" method="POST" class="space-y-4">
                    <input type="hidden" name="lease_id" value="<?php echo $lease ? $lease['lease_id'] : ''; ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="check">Check</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="number" name="amount" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            value="<?php echo $lease ? $lease['monthly_rent'] : '0.00'; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number (Optional)</label>
                        <input type="text" name="reference"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Enter reference number">
                    </div>
                    <div class="flex space-x-4">
                        <button type="button" onclick="closeManualPaymentModal()"
                            class="flex-1 bg-white text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-50 border border-gray-300">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 bg-primary text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment History -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Payment History</h3>
            <?php if (empty($payments)): ?>
            <div class="text-center py-8">
                <p class="text-gray-500">No payment history available.</p>
                <?php if ($lease): ?>
                <p class="text-sm text-gray-500 mt-2">Make your first payment to see it here.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium">
                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium" data-currency-value="<?= $payment['amount'] ?>"
                                    data-currency-original="USD"><?php echo formatCurrency($payment['amount']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm flex items-center">
                                    <?php if ($payment['payment_method'] == 'paypal'): ?>
                                    <i class="fab fa-paypal text-blue-600 mr-2"></i>
                                    <?php elseif ($payment['payment_method'] == 'mobile_money'): ?>
                                    <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                                    <?php else: ?>
                                    <i class="fas fa-money-bill-wave text-gray-600 mr-2"></i>
                                    <?php endif; ?>
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Paid';
                                        
                                        if (isset($payment['gateway_status'])) {
                                            switch ($payment['gateway_status']) {
                                                case 'pending':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $statusText = 'Pending';
                                                    break;
                                                case 'failed':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    $statusText = 'Failed';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                    $statusText = 'Cancelled';
                                                    break;
                                            }
                                        }
                                        ?>
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-500">
                                    <?php echo $payment['gateway_transaction_id'] ? htmlspecialchars($payment['gateway_transaction_id']) : 'N/A'; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="payment_receipt.php?id=<?php echo $payment['payment_id']; ?>"
                                    class="text-primary hover:text-blue-700">
                                    <i class="fas fa-receipt"></i> Receipt
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <script>
    function openManualPaymentModal() {
        document.getElementById('manualPaymentModal').classList.remove('hidden');
        document.getElementById('manualPaymentModal').classList.add('flex');
    }

    function closeManualPaymentModal() {
        document.getElementById('manualPaymentModal').classList.add('hidden');
        document.getElementById('manualPaymentModal').classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('manualPaymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeManualPaymentModal();
        }
    });

    // Export payment history to PDF
    function exportPaymentHistory() {
        // Get the payment history table
        const table = document.querySelector('table');
        if (!table) {
            alert('No payment history to export');
            return;
        }

        // Create a new jsPDF instance
        const doc = new jspdf.jsPDF();

        // Set title
        doc.setFontSize(18);
        doc.text('Payment History', 14, 22);
        doc.setFontSize(11);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 30);

        // Extract table data (excluding the Actions column)
        const tableData = [];
        const headers = [];

        // Get headers (excluding Actions column)
        const headerCells = table.querySelectorAll('thead th');
        headerCells.forEach((cell, index) => {
            if (index < headerCells.length - 1) { // Skip the Actions column
                headers.push(cell.textContent.trim());
            }
        });
        tableData.push(headers);

        // Get rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (index < cells.length - 1) { // Skip the Actions column
                    // Get text content without HTML tags
                    let content = cell.textContent.trim();
                    rowData.push(content);
                }
            });
            tableData.push(rowData);
        });

        // Add table to PDF
        doc.autoTable({
            head: [tableData[0]],
            body: tableData.slice(1),
            startY: 40,
            theme: 'grid',
            styles: {
                fontSize: 9,
                cellPadding: 3
            },
            headStyles: {
                fillColor: [26, 86, 219],
                textColor: 255
            }
        });

        // Save the PDF
        doc.save('payment_history.pdf');
    }

    // Show loading state for PayPal payments
    document.querySelector('form[action="process_paypal_payment.php"]').addEventListener('submit', function() {
        const button = this.querySelector('button[type="submit"]');
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        button.disabled = true;
    });

    // Auto-refresh payment status (for pending payments)
    function checkPendingPayments() {
        const pendingPayments = document.querySelectorAll('.bg-yellow-100');
        if (pendingPayments.length > 0) {
            // Check every 30 seconds for payment status updates
            setTimeout(() => {
                fetch('check_payment_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated) {
                            location.reload();
                        }
                    })
                    .catch(error => console.log('Payment status check failed:', error));

                checkPendingPayments(); // Continue checking
            }, 30000);
        }
    }

    // Start checking for pending payments
    checkPendingPayments();
    </script>

</body>

</html>