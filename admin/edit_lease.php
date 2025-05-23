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
               p.property_name, p.address, p.city, p.state, p.landlord_id,
               u.first_name as tenant_first_name, u.last_name as tenant_last_name, 
               u.email as tenant_email
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

// Get all properties owned by the landlord
function getLandlordProperties($landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT property_id, property_name, address, city, state
        FROM properties 
        WHERE landlord_id = :landlordId
        ORDER BY property_name
    ");
    $stmt->execute(['landlordId' => $landlordId]);
    
    return $stmt->fetchAll();
}

// Get all tenants
function getTenants() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email
        FROM users
        WHERE role = 'tenant'
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get lease data
$lease = getLeaseDetails($leaseId, $userId);

// Check if lease exists and belongs to a property owned by the current landlord
if (!$lease) {
    $_SESSION['error'] = "Lease not found or you don't have permission to edit it";
    header("Location: leases.php");
    exit;
}

// Get all properties
$properties = getLandlordProperties($userId);

// Get all tenants
$tenants = getTenants();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lease'])) {
    $propertyId = (int)$_POST['property_id'];
    $tenantId = (int)$_POST['tenant_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $monthlyRent = (float)$_POST['monthly_rent'];
    $securityDeposit = (float)$_POST['security_deposit'];
    $paymentDueDay = (int)$_POST['payment_due_day'];
    $status = $_POST['status'];
    
    // Validate input
    $errors = [];
    if (empty($propertyId)) $errors[] = "Property is required";
    if (empty($tenantId)) $errors[] = "Tenant is required";
    if (empty($startDate)) $errors[] = "Start date is required";
    if (empty($endDate)) $errors[] = "End date is required";
    if ($monthlyRent <= 0) $errors[] = "Monthly rent must be greater than zero";
    if ($securityDeposit < 0) $errors[] = "Security deposit cannot be negative";
    if ($paymentDueDay < 1 || $paymentDueDay > 31) $errors[] = "Payment due day must be between 1 and 31";
    
    // Check if start date is before end date
    if (!empty($startDate) && !empty($endDate) && strtotime($startDate) >= strtotime($endDate)) {
        $errors[] = "Start date must be before end date";
    }
    
    // Verify property belongs to landlord
    $propertyExists = false;
    foreach ($properties as $property) {
        if ($property['property_id'] == $propertyId) {
            $propertyExists = true;
            break;
        }
    }
    
    if (!$propertyExists) {
        $errors[] = "Invalid property selected";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE leases
                SET property_id = :propertyId,
                    tenant_id = :tenantId,
                    start_date = :startDate,
                    end_date = :endDate,
                    monthly_rent = :monthlyRent,
                    security_deposit = :securityDeposit,
                    payment_due_day = :paymentDueDay,
                    status = :status
                WHERE lease_id = :leaseId
            ");
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'tenantId' => $tenantId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'monthlyRent' => $monthlyRent,
                'securityDeposit' => $securityDeposit,
                'paymentDueDay' => $paymentDueDay,
                'status' => $status,
                'leaseId' => $leaseId
            ]);
            
            // Update property status if lease is active
            if ($status === 'active') {
                $updateStmt = $pdo->prepare("
                    UPDATE properties
                    SET status = 'occupied'
                    WHERE property_id = :propertyId
                ");
                $updateStmt->execute(['propertyId' => $propertyId]);
            } elseif ($status !== 'active') {
                // Check if there are other active leases for this property
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM leases
                    WHERE property_id = :propertyId 
                    AND status = 'active'
                    AND lease_id != :leaseId
                ");
                $checkStmt->execute([
                    'propertyId' => $propertyId,
                    'leaseId' => $leaseId
                ]);
                
                if ($checkStmt->fetch()['count'] == 0) {
                    // No other active leases, update property status to vacant
                    $updateStmt = $pdo->prepare("
                        UPDATE properties
                        SET status = 'vacant'
                        WHERE property_id = :propertyId
                    ");
                    $updateStmt->execute(['propertyId' => $propertyId]);
                }
            }
            
            $_SESSION['success'] = "Lease updated successfully!";
            header("Location: lease_details.php?id=" . $leaseId);
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
    <title>Edit Lease - Property Management System</title>
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
            <a href="lease_details.php?id=<?php echo $leaseId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Lease</h2>
                <p class="text-gray-600">Update lease agreement details</p>
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

        <!-- Edit Lease Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="POST" action="edit_lease.php?id=<?php echo $leaseId; ?>" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['property_id']; ?>" <?php echo ($lease['property_id'] == $property['property_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($property['property_name'] . ' - ' . $property['address'] . ', ' . $property['city']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
                        <select name="tenant_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['user_id']; ?>" <?php echo ($lease['tenant_id'] == $tenant['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $lease['start_date']; ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $lease['end_date']; ?>" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <input type="number" name="monthly_rent" value="<?php echo $lease['monthly_rent']; ?>" min="0.01" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Security Deposit</label>
                        <input type="number" name="security_deposit" value="<?php echo $lease['security_deposit']; ?>" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Due Day</label>
                        <input type="number" name="payment_due_day" value="<?php echo $lease['payment_due_day']; ?>" min="1" max="31" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Day of the month when rent is due (1-31)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="active" <?php echo ($lease['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo ($lease['status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="terminated" <?php echo ($lease['status'] === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="lease_details.php?id=<?php echo $leaseId; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="update_lease" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Update Lease
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
