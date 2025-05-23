<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Require landlord or admin role
requireRole('landlord');


// Get user information
$userId = $_SESSION['user_id'];


// Initialize variables
$propertyId = null;
$tenantId = null;
$preSelectedProperty = null;
$preSelectedTenant = null;


// Check if property ID is provided
if (isset($_GET['property_id']) && is_numeric($_GET['property_id'])) {
$propertyId = (int)$_GET['property_id'];
// Get property details
$property = getPropertyDetails($propertyId, $userId);
// Check if property exists and belongs to the current landlord
if (!$property) {
$_SESSION['error'] = "Property not found or you don't have permission to add a lease";
header("Location: properties.php");
exit;
}
$preSelectedProperty = $property;
// Check if property already has a tenant assigned (from active lease)
$stmt = $pdo->prepare("
SELECT u.user_id, u.first_name, u.last_name, u.email
FROM users u
JOIN leases l ON u.user_id = l.tenant_id
WHERE l.property_id = :propertyId AND l.status = 'active'
ORDER BY l.start_date DESC
LIMIT 1
");
$stmt->execute(['propertyId' => $propertyId]);
$existingTenant = $stmt->fetch();

if ($existingTenant) {
$preSelectedTenant = $existingTenant;
}

$returnUrl = "property_details.php?id=" . $propertyId;
$pageTitle = "Add Lease for " . htmlspecialchars($property['property_name']);
}

// Check if tenant ID is provided
elseif (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
$tenantId = (int)$_GET['tenant_id'];
// Get tenant details
$stmt = $pdo->prepare("
SELECT u.user_id, u.first_name, u.last_name, u.email
FROM users u
WHERE u.user_id = :tenantId AND u.role = 'tenant'
");
$stmt->execute(['tenantId' => $tenantId]);
$tenant = $stmt->fetch();
// Check if tenant exists
if (!$tenant) {
$_SESSION['error'] = "Tenant not found";
header("Location: tenants.php");
exit;
}
$preSelectedTenant = $tenant;
// Check if tenant already has a property assigned (from active lease)
$stmt = $pdo->prepare("
SELECT p.property_id, p.property_name, p.address, p.city, p.state
FROM properties p
JOIN leases l ON p.property_id = l.property_id
WHERE l.tenant_id = :tenantId AND l.status = 'active' AND p.landlord_id = :landlordId
ORDER BY l.start_date DESC
LIMIT 1
");
$stmt->execute([
'tenantId' => $tenantId,
'landlordId' => $userId
]);
$existingProperty = $stmt->fetch();

if ($existingProperty) {
$preSelectedProperty = $existingProperty;
}

$returnUrl = "tenant_details.php?id=" . $tenantId;
$pageTitle = "Add Lease for " . htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']);
}
else {
$_SESSION['error'] = "Invalid request. Please provide a valid property ID or tenant ID";
header("Location: dashboard.php");
exit;
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


// Get available properties
function getAvailableProperties($landlordId) {
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


// Get available tenants and properties
$tenants = getAvailableTenants();
$properties = getAvailableProperties($userId);
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// Get form data
$selectedPropertyId = isset($_POST['property_id']) ? (int)$_POST['property_id'] : null;
$selectedTenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
$startDate = $_POST['start_date'];
$endDate = $_POST['end_date'];
$monthlyRent = (float)$_POST['monthly_rent'];
$securityDeposit = (float)$_POST['security_deposit'];
$paymentDueDay = (int)$_POST['payment_due_day'];
// Validate input
$errors = [];
if (empty($selectedPropertyId)) {
$errors[] = "Property is required";
}
if (empty($selectedTenantId)) {
$errors[] = "Tenant is required";
}
if (empty($startDate)) {
$errors[] = "Start date is required";
}
if (empty($endDate)) {
$errors[] = "End date is required";
} elseif ($endDate <= $startDate) {
$errors[] = "End date must be after start date";
}
if ($monthlyRent <= 0) {
$errors[] = "Monthly rent must be greater than zero";
}
if ($securityDeposit < 0) {
$errors[] = "Security deposit cannot be negative";
}
if ($paymentDueDay < 1 || $paymentDueDay > 31) {
$errors[] = "Payment due day must be between 1 and 31";
}
// If no errors, create the lease
if (empty($errors)) {
try {
// Check if tenant already has an active lease for this property
$stmt = $pdo->prepare("
SELECT COUNT(*) as count
FROM leases
WHERE property_id = ? AND tenant_id = ? AND status = 'active'
");
$stmt->execute([$selectedPropertyId, $selectedTenantId]);
$result = $stmt->fetch();
if ($result['count'] > 0) {
$errors[] = "This tenant already has an active lease for this property";
} else {
// Create the lease
$stmt = $pdo->prepare("
INSERT INTO leases (
property_id, tenant_id, start_date, end_date,
monthly_rent, security_deposit, payment_due_day, status
) VALUES (
?, ?, ?, ?, ?, ?, ?, 'active'
)
");
$stmt->execute([
$selectedPropertyId, $selectedTenantId, $startDate, $endDate,
$monthlyRent, $securityDeposit, $paymentDueDay
]);
// Update property status to occupied
$stmt = $pdo->prepare("
UPDATE properties
SET status = 'occupied'
WHERE property_id = ?
");
$stmt->execute([$selectedPropertyId]);
$_SESSION['success'] = "Lease created successfully";
// Redirect based on where we came from
if (isset($_GET['property_id'])) {
header("Location: property_details.php?id=" . $_GET['property_id']);
} elseif (isset($_GET['tenant_id'])) {
header("Location: tenant_details.php?id=" . $_GET['tenant_id']);
} else {
header("Location: leases.php");
}
exit;
}
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
<title><?php echo $pageTitle; ?> - Property Management System</title>
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
<p class="text-gray-600">For <?php echo htmlspecialchars( $pageTitle); ?></p>
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
<label class="block text-sm font-medium text-gray-700 mb-1">Property</label>
<select name="property_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
    <option value="">Select a property</option>
    <?php if ($preSelectedProperty): ?>
        <option value="<?php echo $preSelectedProperty['property_id']; ?>" selected>
            <?php echo htmlspecialchars($preSelectedProperty['property_name'] . ' - ' . $preSelectedProperty['address'] . ', ' . $preSelectedProperty['city']); ?>
        </option>
    <?php else: ?>
        <?php foreach ($properties as $property): ?>
            <option value="<?php echo $property['property_id']; ?>">
                <?php echo htmlspecialchars($property['property_name'] . ' - ' . $property['address'] . ', ' . $property['city']); ?>
            </option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>

</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Tenant</label>
<select name="tenant_id" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
    <option value="">Select a tenant</option>
    <?php if ($preSelectedTenant): ?>
        <option value="<?php echo $preSelectedTenant['user_id']; ?>" selected>
            <?php echo htmlspecialchars($preSelectedTenant['first_name'] . ' ' . $preSelectedTenant['last_name'] . ' - ' . $preSelectedTenant['email']); ?>
        </option>
    <?php else: ?>
        <?php foreach ($tenants as $tenant): ?>
            <option value="<?php echo $tenant['user_id']; ?>">
                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' - ' . $tenant['email']); ?>
            </option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>

</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent(in RWF)</label>
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
<a href="<?php echo $returnUrl; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
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