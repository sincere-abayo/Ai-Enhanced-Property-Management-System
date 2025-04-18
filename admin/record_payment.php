<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if property ID or tenant ID is provided
if (isset($_GET['property_id']) && is_numeric($_GET['property_id'])) {
    $propertyId = (int)$_GET['property_id'];
    
    // Verify property belongs to this landlord
    $stmt = $pdo->prepare("
        SELECT * FROM properties 
        WHERE property_id = :propertyId AND landlord_id = :landlordId
    ");
    $stmt->execute([
        'propertyId' => $propertyId,
        'landlordId' => $userId
    ]);
    
    $property = $stmt->fetch();
    
    if (!$property) {
        $_SESSION['error'] = "Property not found or you don't have permission to access it";
        header("Location: properties.php");
        exit;
    }
    
    // Get active leases for this property
    $stmt = $pdo->prepare("
        SELECT l.*, u.first_name, u.last_name
        FROM leases l
        JOIN users u ON l.tenant_id = u.user_id
        WHERE l.property_id = :propertyId AND l.status = 'active'
        ORDER BY l.start_date DESC
    ");
    $stmt->execute(['propertyId' => $propertyId]);
    $leases = $stmt->fetchAll();
    
    $returnUrl = "property_details.php?id=" . $propertyId;
    $pageTitle = "Record Payment for " . htmlspecialchars($property['property_name']);
    
} elseif (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
    $tenantId = (int)$_GET['tenant_id'];
    
    // Verify tenant belongs to this landlord
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
        LIMIT 1
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $userId
    ]);
    
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        $_SESSION['error'] = "Tenant not found or you don't have permission to access it";
        header("Location: tenants.php");
        exit;
    }
    
    // Get active leases for this tenant
    $stmt = $pdo->prepare("
        SELECT l.*, p.property_name
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.tenant_id = :tenantId AND l.status = 'active'
        ORDER BY l.start_date DESC
    ");
    $stmt->execute(['tenantId' => $tenantId]);
    $leases = $stmt->fetchAll();
    
    $returnUrl = "tenant_details.php?id=" . $tenantId;
    $pageTitle = "Record Payment for " . htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']);
    
} else {
    $_SESSION['error'] = "Invalid request. Please provide a valid property ID or tenant ID";
    header("Location: dashboard.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $leaseId = (int)$_POST['lease_id'];
    $amount = (float)$_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    $paymentMethod = $_POST['payment_method'];
    $paymentType = $_POST['payment_type'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    $errors = [];
    if (empty($leaseId)) $errors[] = "Lease is required";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero";
    if (empty($paymentDate)) $errors[] = "Payment date is required";
    
    // Verify lease belongs to this property
    $leaseExists = false;
    foreach ($leases as $lease) {
        if ($lease['lease_id'] == $leaseId) {
            $leaseExists = true;
            break;
        }
    }
    
    if (!$leaseExists) {
        $errors[] = "Invalid lease selected";
    }
    
    if (empty($errors)) {
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
            
            $_SESSION['success'] = "Payment recorded successfully!";
    // Determine where to redirect based on the parameters
    if (isset($_GET['property_id'])) {
        header("Location: property_details.php?id=" . $_GET['property_id']);
    } elseif (isset($_GET['tenant_id'])) {
        header("Location: tenant_details.php?id=" . $_GET['tenant_id']);
    } else {
        header("Location: dashboard.php");
    }
    exit;
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
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
    <title><?php echo $pageTitle; ?> - Property Management System</title>    <script src="https://cdn.tailwindcss.com"></script>
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
            <a href="property_details.php?id=<?php echo $propertyId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <title><?php echo $pageTitle; ?> - Property Management System</title>


<div>
    <h2 class="text-2xl font-bold text-gray-800">Record Payment</h2>
    <p class="text-gray-600"><?php echo $pageTitle; ?></p>
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

        <!-- No Active Leases Message -->
        <?php if (empty($leases)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                <p>There are no active leases for this property. You need an active lease to record a payment.</p>
                <p class="mt-2">
                    <a href="leases.php" class="text-primary hover:underline">Manage Leases</a> or 
                    <a href="property_details.php?id=<?php echo $propertyId; ?>" class="text-primary hover:underline">Return to Property</a>
                </p>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="record_payment.php?<?php echo isset($propertyId) ? 'property_id='.$propertyId : 'tenant_id='.$tenantId; ?>" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lease / Tenant</label>
                            <select name="lease_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                <option value="">Select a lease</option>
                                <?php foreach ($leases as $lease): ?>
                                    <option value="<?php echo $lease['lease_id']; ?>" data-rent="<?php echo $lease['monthly_rent']; ?>">
                                        <?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name'] . ' - ' . formatCurrency($lease['monthly_rent']) . '/month'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" name="amount" id="amount" min="0.01" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
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
                            <textarea name="notes" rows="3" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-8">
                    <a href="<?php echo $returnUrl; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
    Cancel
</a>

                        <button type="submit" name="record_payment" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-fill amount when lease is selected
        document.addEventListener('DOMContentLoaded', function() {
            const leaseSelect = document.querySelector('select[name="lease_id"]');
            const amountInput = document.getElementById('amount');
            
            if (leaseSelect && amountInput) {
                leaseSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.getAttribute('data-rent')) {
                        amountInput.value = selectedOption.getAttribute('data-rent');
                    }
                });
            }
        });
    </script>
</body>
</html>
