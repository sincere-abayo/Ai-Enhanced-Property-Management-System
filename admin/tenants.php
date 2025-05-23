<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Get all tenants for this landlord
function getLandlordTenants($landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.phone,
               l.lease_id, l.start_date, l.end_date, l.status as lease_status,
               p.property_id, p.property_name, p.address, p.city
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE p.landlord_id = :landlordId AND u.role = 'tenant'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute(['landlordId' => $landlordId]);
    
    return $stmt->fetchAll();
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

// Get tenant payment status
function getTenantPaymentStatus($tenantId) {
    global $pdo;
    
    // Get the most recent payment
    $stmt = $pdo->prepare("
        SELECT p.payment_date, l.monthly_rent, l.payment_due_day
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        WHERE l.tenant_id = :tenantId
        ORDER BY p.payment_date DESC
        LIMIT 1
    ");
    $stmt->execute(['tenantId' => $tenantId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        return 'unknown';
    }
    
    // Calculate when the next payment is due
    $lastPaymentDate = new DateTime($payment['payment_date']);
    $today = new DateTime();
    
    // Get the current month's due date
    $currentMonth = new DateTime();
    $currentMonth->setDate($currentMonth->format('Y'), $currentMonth->format('m'), $payment['payment_due_day']);
    
    // If today is past the due date and the last payment was before the due date
    if ($today > $currentMonth && $lastPaymentDate < $currentMonth) {
        return 'overdue';
    }
    
    // If the last payment was this month and after/on the due date
    if ($lastPaymentDate->format('Y-m') == $today->format('Y-m') && $lastPaymentDate->format('d') >= $payment['payment_due_day']) {
        return 'paid';
    }
    
    // If we're before the due date
    if ($today < $currentMonth) {
        return 'pending';
    }
    
    return 'unknown';
}

// Get tenants
$tenants = getLandlordTenants($userId);

// Get properties for filter and add tenant form
$properties = getLandlordProperties($userId);

// Process add tenant form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $propertyId = (int)$_POST['property_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    // $password = generateRandomPassword(); // Function to generate a random password
    $password = "tenant123"; // Function to generate a random password
    
    // Validate input
    $errors = [];
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($propertyId)) $errors[] = "Property is required";
    if (empty($startDate)) $errors[] = "Lease start date is required";
    if (empty($endDate)) $errors[] = "Lease end date is required";
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email already exists";
    }
    
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
            // Begin transaction
            $pdo->beginTransaction();
            
            // Create user account
            // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    email, password, first_name, last_name, phone, role
                ) VALUES (
                    :email, :password, :firstName, :lastName, :phone, 'tenant'
                )
            ");
            
            $stmt->execute([
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phone' => $phone
            ]);
            
            $tenantId = $pdo->lastInsertId();
            
            // Create lease
            $stmt = $pdo->prepare("
                INSERT INTO leases (
                    property_id, tenant_id, start_date, end_date, 
                    monthly_rent, security_deposit, payment_due_day, status
                ) VALUES (
                    :propertyId, :tenantId, :startDate, :endDate, 
                    :monthlyRent, :securityDeposit, :paymentDueDay, 'active'
                )
            ");
            
            // Get property details for rent amount
            $propertyStmt = $pdo->prepare("SELECT monthly_rent FROM properties WHERE property_id = :propertyId");
            $propertyStmt->execute(['propertyId' => $propertyId]);
            $property = $propertyStmt->fetch();
            $monthlyRent = $property ? $property['monthly_rent'] : 0;
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'tenantId' => $tenantId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'monthlyRent' => $monthlyRent,
                'securityDeposit' => $monthlyRent, // Default security deposit to one month's rent
                'paymentDueDay' => 1 // Default payment due day to 1st of month
            ]);
            
            // Update property status to occupied
            $updateStmt = $pdo->prepare("
                UPDATE properties
                SET status = 'occupied'
                WHERE property_id = :propertyId
            ");
            $updateStmt->execute(['propertyId' => $propertyId]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Tenant added successfully! Temporary password: " . $password;
            header("Location: tenants.php");
            exit;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Process delete tenant
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tenantId = (int)$_GET['delete'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get all leases for this tenant with properties owned by this landlord
        $stmt = $pdo->prepare("
            SELECT l.lease_id, l.property_id
            FROM leases l
            JOIN properties p ON l.property_id = p.property_id
            WHERE l.tenant_id = :tenantId AND p.landlord_id = :landlordId
        ");
        $stmt->execute([
            'tenantId' => $tenantId,
            'landlordId' => $userId
        ]);
        
        $leases = $stmt->fetchAll();
        
        if (empty($leases)) {
            throw new Exception("Tenant not found or you don't have permission to delete them");
        }
        
        // Delete payments for each lease
        foreach ($leases as $lease) {
            $stmt = $pdo->prepare("DELETE FROM payments WHERE lease_id = :leaseId");
            $stmt->execute(['leaseId' => $lease['lease_id']]);
            
            // Delete the lease
            $stmt = $pdo->prepare("DELETE FROM leases WHERE lease_id = :leaseId");
            $stmt->execute(['leaseId' => $lease['lease_id']]);
            
            // Check if there are other active leases for this property
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM leases
                WHERE property_id = :propertyId AND status = 'active'
            ");
            $stmt->execute(['propertyId' => $lease['property_id']]);
            
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
        
        // Delete the tenant (user)
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :userId AND role = 'tenant'");
        $stmt->execute(['userId' => $tenantId]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Tenant deleted successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: tenants.php");
    exit;
}

// Helper function to generate a random password


function generateRandomPassword($length = 8) {

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';

    for ($i = 0; $i < 8; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;

}
// Get payment status class for styling
function getPaymentStatusClass($status) {
    switch ($status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'overdue':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get lease status class for styling
function getLeaseStatusClass($status) {
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Tenants</h2>
                <p class="text-gray-600">Manage your tenants and their information</p>
            </div>
            <button onclick="openAddTenantModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Add Tenant
            </button>
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

        <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Tenant Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status-filter"
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                    <select id="property-filter"
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All Properties</option>
                        <?php foreach ($properties as $property): ?>
                        <option value="<?php echo $property['property_id']; ?>">
                            <?php echo htmlspecialchars($property['property_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select id="payment-filter"
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search-filter" placeholder="Search tenants..."
                        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
            </div>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($tenants)): ?>
            <div class="p-8 text-center">
                <p class="text-gray-500 mb-2">No tenants found</p>
                <p class="text-sm text-gray-500">Click the "Add Tenant" button to add a new tenant.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Property</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Lease Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Payment Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tenants as $tenant): ?>
                        <?php 
                                    $paymentStatus = getTenantPaymentStatus($tenant['user_id']);
                                    $leaseEnding = (strtotime($tenant['end_date']) - time()) / (60 * 60 * 24) <= 30;
                                ?>
                        <tr class="tenant-row" data-status="<?php echo $tenant['lease_status']; ?>"
                            data-property="<?php echo $tenant['property_id']; ?>"
                            data-payment="<?php echo $paymentStatus; ?>"
                            data-name="<?php echo strtolower($tenant['first_name'] . ' ' . $tenant['last_name']); ?>"
                            data-email="<?php echo strtolower($tenant['email']); ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full"
                                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($tenant['first_name'] . '+' . $tenant['last_name']); ?>&background=random"
                                            alt="Tenant">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($tenant['email']); ?></div>
                                        <?php if (!empty($tenant['phone'])): ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($tenant['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($tenant['property_name']); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($tenant['address'] . ', ' . $tenant['city']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M j, Y', strtotime($tenant['start_date'])); ?> -
                                    <?php echo date('M j, Y', strtotime($tenant['end_date'])); ?></div>
                                <div
                                    class="text-sm <?php echo $leaseEnding ? 'text-red-500 font-semibold' : 'text-gray-500'; ?>">
                                    <?php 
                                                $days = round((strtotime($tenant['end_date']) - time()) / (60 * 60 * 24));
                                                if ($days < 0) {
                                                    echo "Expired " . abs($days) . " days ago";
                                                } elseif ($days == 0) {
                                                    echo "Expires today";
                                                } else {
                                                    echo $days . " days remaining";
                                                }
                                            ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getPaymentStatusClass($paymentStatus); ?>">
                                    <?php echo ucfirst($paymentStatus); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="tenant_details.php?id=<?php echo $tenant['user_id']; ?>"
                                    class="text-primary hover:text-blue-700 mr-3">View</a>
                                <a href="edit_tenant.php?id=<?php echo $tenant['user_id']; ?>"
                                    class="text-primary hover:text-blue-700 mr-3">Edit</a>
                                <a href="#"
                                    onclick="confirmDelete(<?php echo $tenant['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($tenant['first_name'] . ' ' . $tenant['last_name'])); ?>')"
                                    class="text-red-600 hover:text-red-900">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Tenant Modal -->
    <div id="addTenantModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Add New Tenant </h3>
                <p class="text-sm text-gray-500 mb-2">NB: Tenant will get default password of "tenant123"</p>
                <button onclick="closeAddTenantModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="tenants.php" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone"
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select Property</option>
                            <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['property_id']; ?>">
                                <?php echo htmlspecialchars($property['property_name'] . ' - ' . $property['address']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lease Start Date</label>
                        <input type="date" name="start_date" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lease End Date</label>
                        <input type="date" name="end_date" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    <p>A temporary password will be generated for the tenant. They can change it after logging in.</p>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeAddTenantModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="add_tenant"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Add Tenant
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-2">Confirm Deletion</h3>
                <p class="text-gray-600">Are you sure you want to delete <span id="tenantName"
                        class="font-semibold"></span>? This will remove all their lease information and payment history.
                </p>
            </div>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <a id="confirmDeleteBtn" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete Tenant
                </a>
            </div>
        </div>
    </div>

    <script>
    // Modal functions
    function openAddTenantModal() {
        document.getElementById('addTenantModal').classList.remove('hidden');
        document.getElementById('addTenantModal').classList.add('flex');
    }

    function closeAddTenantModal() {
        document.getElementById('addTenantModal').classList.add('hidden');
        document.getElementById('addTenantModal').classList.remove('flex');
    }

    function confirmDelete(tenantId, tenantName) {
        document.getElementById('tenantName').textContent = tenantName;
        document.getElementById('confirmDeleteBtn').href = 'tenants.php?delete=' + tenantId;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    // Close modals when clicking outside
    document.getElementById('addTenantModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddTenantModal();
        }
    });

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // Filtering functionality
    document.addEventListener('DOMContentLoaded', function() {
        const statusFilter = document.getElementById('status-filter');
        const propertyFilter = document.getElementById('property-filter');
        const paymentFilter = document.getElementById('payment-filter');
        const searchFilter = document.getElementById('search-filter');
        const tenantRows = document.querySelectorAll('.tenant-row');

        function applyFilters() {
            const statusValue = statusFilter.value;
            const propertyValue = propertyFilter.value;
            const paymentValue = paymentFilter.value;
            const searchValue = searchFilter.value.toLowerCase();

            tenantRows.forEach(row => {
                const status = row.getAttribute('data-status');
                const property = row.getAttribute('data-property');
                const payment = row.getAttribute('data-payment');
                const name = row.getAttribute('data-name');
                const email = row.getAttribute('data-email');

                let statusMatch = statusValue === 'all' || status === statusValue;
                let propertyMatch = propertyValue === 'all' || property === propertyValue;
                let paymentMatch = paymentValue === 'all' || payment === paymentValue;
                let searchMatch = name.includes(searchValue) || email.includes(searchValue);

                if (statusMatch && propertyMatch && paymentMatch && searchMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        statusFilter.addEventListener('change', applyFilters);
        propertyFilter.addEventListener('change', applyFilters);
        paymentFilter.addEventListener('change', applyFilters);
        searchFilter.addEventListener('input', applyFilters);
    });
    </script>
</body>

</html>