<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if tenant ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid tenant ID";
    header("Location: tenants.php");
    exit;
}

$tenantId = (int)$_GET['id'];

// Get tenant details
function getTenantDetails($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
        LIMIT 1
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get tenant details
$tenant = getTenantDetails($tenantId, $userId);

// Redirect if tenant not found or doesn't belong to this landlord
if (!$tenant) {
    $_SESSION['error'] = "Tenant not found or you don't have permission to edit this tenant";
    header("Location: tenants.php");
    exit;
}

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists for another user
    if ($email !== $tenant['email']) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $tenantId]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already in use by another user";
        }
    }
    
    // If no errors, update tenant information
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$firstName, $lastName, $email, $phone, $tenantId]);
            
            $_SESSION['success'] = "Tenant information updated successfully";
            header("Location: tenant_details.php?id=" . $tenantId);
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
    <title>Edit Tenant - Property Management System</title>
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
            <a href="tenant_details.php?id=<?php echo $tenantId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Edit Tenant</h2>
                <p class="text-gray-600">Update tenant information</p>
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

        <!-- Edit Tenant Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <form method="POST" action="edit_tenant.php?id=<?php echo $tenantId; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            value="<?php echo htmlspecialchars($tenant['first_name']); ?>" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            value="<?php echo htmlspecialchars($tenant['last_name']); ?>" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($tenant['email']); ?>" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            required
                        >
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($tenant['phone']); ?>" 
                            class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                        >
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Password Management</h3>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                id="reset_password" 
                                name="reset_password" 
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                            >
                            <label for="reset_password" class="ml-2 block text-sm text-gray-700">
                                Reset tenant's password
                            </label>
                        </div>
                        <div id="password_fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                >
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                >
                            </div>
                        </div>
                    </div>
                </div>


                <div class="flex justify-end space-x-4 mt-6">
                    <a 
                        href="tenant_details.php?id=<?php echo $tenantId; ?>" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                    >
                        Cancel
                    </a>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-medium text-red-600 mb-4">Danger Zone</h3>
            <div class="border border-red-200 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="text-base font-medium text-gray-900">Delete Tenant</h4>
                        <p class="text-sm text-gray-500">
                            This will permanently delete the tenant and all associated data. This action cannot be undone.
                        </p>
                    </div>
                    <button 
                        onclick="confirmDelete()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    >
                        Delete Tenant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-2">Confirm Deletion</h3>
                <p class="text-gray-600">
                    Are you sure you want to delete <span class="font-semibold"><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></span>? 
                    This will remove all their lease information, payment history, and other data. This action cannot be undone.
                </p>
            </div>
            <div class="flex justify-end space-x-4">
                <button 
                    onclick="closeDeleteModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    Cancel
                </button>
                <a 
                    href="delete_tenant.php?id=<?php echo $tenantId; ?>" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Delete Tenant
                </a>
            </div>
        </div>
    </div>

    <script>
        // Toggle password fields visibility
        document.getElementById('reset_password').addEventListener('change', function() {
            const passwordFields = document.getElementById('password_fields');
            if (this.checked) {
                passwordFields.classList.remove('hidden');
            } else {
                passwordFields.classList.add('hidden');
            }
        });

        // Delete modal functions
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
    </script>
</body>
</html>
