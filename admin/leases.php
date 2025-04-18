<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Get all leases for this landlord
function getLandlordLeases($landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT l.*, 
               p.property_name, p.address, p.city, p.state, p.zip_code,
               u.first_name as tenant_first_name, u.last_name as tenant_last_name
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE p.landlord_id = :landlordId
        ORDER BY l.status ASC, l.end_date ASC
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

// Get leases
$leases = getLandlordLeases($userId);

// Get properties for filter and add lease form
$properties = getLandlordProperties($userId);

// Get tenants for add lease form
$tenants = getTenants();

// Process add lease form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lease'])) {
    $propertyId = (int)$_POST['property_id'];
    $tenantId = (int)$_POST['tenant_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $monthlyRent = (float)$_POST['monthly_rent'];
    $securityDeposit = (float)$_POST['security_deposit'];
    $paymentDueDay = (int)$_POST['payment_due_day'];
    
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
            header("Location: leases.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
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
    <title>Leases - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Leases</h2>
                <p class="text-gray-600">Manage your property lease agreements</p>
            </div>
            <button onclick="openAddLeaseModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Add Lease
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

        <!-- Lease Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status-filter" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                    <select id="property-filter" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All Properties</option>
                        <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['property_id']; ?>">
                                <?php echo htmlspecialchars($property['property_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                    <select id="date-filter" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all">All Dates</option>
                        <option value="current">Current Leases</option>
                        <option value="upcoming">Upcoming Leases</option>
                        <option value="ending-soon">Ending Soon (30 days)</option>
                        <option value="expired">Expired Leases</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search-filter" placeholder="Search leases..." class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                </div>
            </div>
        </div>

        <!-- Leases Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($leases)): ?>
                <div class="p-8 text-center">
                    <p class="text-gray-500 mb-2">No leases found</p>
                    <p class="text-sm text-gray-500">Click the "Add Lease" button to create a new lease agreement.</p>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Rent</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($leases as $lease): ?>
                            <tr class="lease-row" 
                                data-status="<?php echo $lease['status']; ?>"
                                data-property="<?php echo $lease['property_id']; ?>"
                                data-tenant="<?php echo $lease['tenant_first_name'] . ' ' . $lease['tenant_last_name']; ?>"
                                data-start="<?php echo $lease['start_date']; ?>"
                                data-end="<?php echo $lease['end_date']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lease['property_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($lease['address'] . ', ' . $lease['city']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($lease['tenant_first_name'] . ' ' . $lease['tenant_last_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($lease['start_date'])); ?></div>
                                    <div class="text-sm text-gray-500">to <?php echo date('M j, Y', strtotime($lease['end_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($lease['monthly_rent']); ?></div>
                                    <div class="text-xs text-gray-500">Due on day <?php echo $lease['payment_due_day']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusClass($lease['status']); ?>">
                                        <?php echo ucfirst($lease['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="lease_details.php?id=<?php echo $lease['lease_id']; ?>" class="text-primary hover:text-blue-700 mr-3">View</a>
                                    <a href="edit_lease.php?id=<?php echo $lease['lease_id']; ?>" class="text-yellow-600 hover:text-yellow-900 mr-3">Edit</a>
                                    <a href="#" onclick="confirmDelete(<?php echo $lease['lease_id']; ?>)" class="text-red-600 hover:text-red-900">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Lease Modal -->
    <div id="addLeaseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-4xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Add New Lease</h3>
                <button onclick="closeAddLeaseModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="leases.php" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
                        <select name="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            <option value="">Select a property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['property_id']; ?>">
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
                                <option value="<?php echo $tenant['user_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <input type="number" name="monthly_rent" min="0.01" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Security Deposit</label>
                        <input type="number" name="security_deposit" min="0" step="0.01" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Due Day</label>
                        <input type="number" name="payment_due_day" min="1" max="31" value="1" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <p class="text-xs text-gray-500 mt-1">Day of the month when rent is due (1-31)</p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeAddLeaseModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" name="add_lease" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Add Lease
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
                <p class="text-gray-600">Are you sure you want to delete this lease? This action cannot be undone.</p>
            </div>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <a id="confirmDeleteBtn" href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete Lease
                </a>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddLeaseModal() {
            document.getElementById('addLeaseModal').classList.remove('hidden');
            document.getElementById('addLeaseModal').classList.add('flex');
        }

        function closeAddLeaseModal() {
            document.getElementById('addLeaseModal').classList.add('hidden');
            document.getElementById('addLeaseModal').classList.remove('flex');
        }

        function confirmDelete(leaseId) {
            document.getElementById('confirmDeleteBtn').href = 'delete_lease.php?id=' + leaseId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Close modals when clicking outside
        document.getElementById('addLeaseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddLeaseModal();
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
            const dateFilter = document.getElementById('date-filter');
            const searchFilter = document.getElementById('search-filter');
            const leaseRows = document.querySelectorAll('.lease-row');

            function applyFilters() {
                const statusValue = statusFilter.value;
                const propertyValue = propertyFilter.value;
                const dateValue = dateFilter.value;
                const searchValue = searchFilter.value.toLowerCase();
                
                const today = new Date();
                const thirtyDaysFromNow = new Date();
                thirtyDaysFromNow.setDate(today.getDate() + 30);

                leaseRows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const property = row.getAttribute('data-property');
                    const tenant = row.getAttribute('data-tenant').toLowerCase();
                    const startDate = new Date(row.getAttribute('data-start'));
                    const endDate = new Date(row.getAttribute('data-end'));
                    
                    let statusMatch = statusValue === 'all' || status === statusValue;
                    let propertyMatch = propertyValue === 'all' || property === propertyValue;
                    let searchMatch = tenant.includes(searchValue);
                    
                    // Date filtering
                    let dateMatch = true;
                    if (dateValue === 'current') {
                        dateMatch = startDate <= today && endDate >= today;
                    } else if (dateValue === 'upcoming') {
                        dateMatch = startDate > today;
                    } else if (dateValue === 'ending-soon') {
                        dateMatch = endDate >= today && endDate <= thirtyDaysFromNow;
                    } else if (dateValue === 'expired') {
                        dateMatch = endDate < today;
                    }
                    
                    if (statusMatch && propertyMatch && dateMatch && searchMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', applyFilters);
            propertyFilter.addEventListener('change', applyFilters);
            dateFilter.addEventListener('change', applyFilters);
            searchFilter.addEventListener('input', applyFilters);
        });
    </script>
</body>
</html>
