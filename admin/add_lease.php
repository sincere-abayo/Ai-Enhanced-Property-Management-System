<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if property ID is provided
if (!isset($_GET['property_id']) || !is_numeric($_GET['property_id'])) {
    $_SESSION['error'] = "Invalid property ID";
    header("Location: properties.php");
    exit;
}

$propertyId = (int)$_GET['property_id'];

// Get property details
function getPropertyDetails($propertyId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM properties 
        WHERE property_id = :propertyId AND landlord_id = :landlordId
    ");
    $stmt->execute([
        'propertyId' => $propertyId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get available tenants (not currently in an active lease for this property)
function getAvailableTenants() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, phone
        FROM users
        WHERE role = 'tenant'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get property data
$property = getPropertyDetails($propertyId, $userId);

// Check if property exists and belongs to the current landlord
if (!$property) {
    $_SESSION['error'] = "Property not found or you don't have permission to add a lease";
    header("Location: properties.php");
    exit;
}

// Get available tenants
$tenants = getAvailableTenants();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lease'])) {
    $tenantId = (int)$_POST['tenant_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $monthlyRent = (float)$_POST['monthly_rent'];
    $securityDeposit = (float)$_POST['security_deposit'];
    $paymentDueDay = (int)$_POST['payment_due_day'];
    
    // Validate input
    $errors = [];
    if (empty($tenantId)) $errors[] = "Tenant is required";
    if (empty($startDate)) $errors[] = "Start date is required";
    if (empty($endDate)) $errors[] = "End date is required";
    if ($monthlyRent <= 0) $errors[] = "Monthly rent must be greater than zero";
    if ($securityDeposit < 0) $errors[] = "Security deposit cannot be negative";
    if ($paymentDueDay < 1 || $paymentDueDay > 31) $errors[] = "Payment due day must be between 1 and 31";
    
    // Validate dates
    $startDateTime = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    
    if ($endDateTime <= $startDateTime) {
        $errors[] = "End date must be after start date";
    }
    
    // Check if tenant already has an active lease for this property
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM leases
        WHERE property_id = :propertyId 
        AND tenant_id = :tenantId 
        AND status = 'active'
    ");
    $stmt->execute([
        'propertyId' => $propertyId,
        'tenantId' => $tenantId
    ]);
    
    if ($stmt->fetch()['count'] > 0) {
        $errors[] = "This tenant already has an active lease for this property";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO leases (
                    property_id, tenant_id, start_date, end_date, 
                    monthly_rent, security_deposit, payment_due_day, status
                ) VALUES (
                    :propertyId, :tenantId, :startDate, :endDate,
                    :monthlyRent, :securityDeposit, :paymentDueDay, 'active'
                )
            ");
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'tenantId' => $tenantId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'monthlyRent' => $monthlyRent,
                'securityDeposit' => $securityDeposit,
                'paymentDueDay' => $paymentDueDay
            ]);
            
            // Update property status to occupied
            $updateStmt = $pdo->prepare("
                UPDATE properties
                SET status = 'occupied'
                WHERE property_id = :propertyId
            ");
            $updateStmt->execute(['propertyId' => $propertyId]);
            
            $_SESSION['success'] = "Lease added successfully!";
            header("Location: property_details.php?id=" . $propertyId);
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lease - Property Management System</title>
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
            <a href="property_details.php?id=<?php echo $propertyId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Add New Lease</h2>
                <p class="text-gray-600">For <?php echo htmlspecialchars($property['property_name']); ?></p>
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

        <!-- Add Lease Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="add_lease.php?property_id=<?php echo $propertyId; ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                        <select name="tenant_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['user_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <a href="add_tenant.php" class="text-primary hover:text-blue-700">+ Add a new tenant</a>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <input type="number" name="monthly_rent" value="<?php echo $property['monthly_rent']; ?>" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Security Deposit</label>
                        <input type="number" name="security_deposit" value="<?php echo $property['monthly_rent']; ?>" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Due Day</label>
                        <input type="number" name="payment_due_day" value="1" min="1" max="31" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Day of the month when rent payment is due</p>
                    </div>
                </div>
                
                <div class="border-t pt-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Lease Terms and Conditions</h3>
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <p class="text-sm text-gray-600">
                            By creating this lease, you confirm that:
                        </p>
                        <ul class="list-disc list-inside text-sm text-gray-600 mt-2 space-y-1">
                            <li>The tenant has agreed to the terms of the lease</li>
                            <li>The monthly rent and security deposit amounts are correct</li>
                            <li>The lease dates are accurate</li>
                            <li>You have the legal right to lease this property</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="property_details.php?id=<?php echo $propertyId; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="add_lease" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Create Lease
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            // Set start date to today
            const today = new Date();
            const startDateInput = document.querySelector('input[name="start_date"]');
            startDateInput.valueAsDate = today;
            
            // Set end date to 1 year from today
            const endDate = new Date();
            endDate.setFullYear(endDate.getFullYear() + 1);
            const endDateInput = document.querySelector('input[name="end_date"]');
            endDateInput.valueAsDate = endDate;
        });
    </script>
</body>
</html>
