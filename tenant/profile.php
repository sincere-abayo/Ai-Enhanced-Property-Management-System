<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Get tenant details
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE user_id = ? AND role = 'tenant'
");
$stmt->execute([$userId]);
$tenant = $stmt->fetch();

if (!$tenant) {
    $_SESSION['error'] = "User not found";
    header("Location: dashboard.php");
    exit;
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    $errors = [];
    
    // Validate input
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
    
    // Check if email is already in use by another user
    if ($email !== $tenant['email']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email is already in use by another account";
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = :firstName,
                    last_name = :lastName,
                    email = :email,
                    phone = :phone,
                    updated_at = NOW()
                WHERE user_id = :userId
            ");
            
            $stmt->execute([
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'userId' => $userId
            ]);
            
            // Update session variables
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: profile.php");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate input
    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password
    if (!empty($currentPassword) && !password_verify($currentPassword, $tenant['password'])) {
        $errors[] = "Current password is incorrect";
    }
    
    // If no errors, update password
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = :password,
                    updated_at = NOW()
                WHERE user_id = :userId
            ");
            
            $stmt->execute([
                'password' => $hashedPassword,
                'userId' => $userId
            ]);
            
            $_SESSION['success'] = "Password changed successfully";
            header("Location: profile.php");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Check for error and success messages
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;

// Clear session messages
unset($_SESSION['error']);
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Tenant Portal</title>
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
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 sm:mb-0">My Profile</h2>
            <!-- Hamburger for mobile -->
            <button id="openSidebarBtn" class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary mb-2" aria-label="Open sidebar" onclick="document.getElementById('tenantSidebar').classList.remove('-translate-x-full'); document.getElementById('sidebarBackdrop').classList.remove('hidden');">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Profile Information -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Profile Information</h3>
                    
                    <form method="POST" action="profile.php">
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
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    value="<?php echo htmlspecialchars($tenant['phone']); ?>" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                >
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button 
                                type="submit" 
                                name="update_profile" 
                                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                            >
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">Change Password</h3>
                    
                    <form method="POST" action="profile.php">
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                <input 
                                    type="password" 
                                    id="current_password" 
                                    name="current_password" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                    required
                                >
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                    required
                                >
                                <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters long</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button 
                                type="submit" 
                                name="change_password" 
                                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                            >
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Summary -->
            <div>
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center justify-center mb-6">
                        <div class="w-24 h-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold">
                            <?php echo strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($tenant['email']); ?></p>
                        <?php if ($tenant['phone']): ?>
                            <p class="text-gray-600"><?php echo htmlspecialchars($tenant['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-600">Account Type</span>
                            <span class="text-sm font-medium">Tenant</span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-600">Member Since</span>
                            <span class="text-sm font-medium"><?php echo date('M j, Y', strtotime($tenant['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Last Updated</span>
                            <span class="text-sm font-medium"><?php echo date('M j, Y', strtotime($tenant['updated_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Get tenant's active leases -->
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT l.*, p.property_name, u.unit_number
                        FROM leases l
                        JOIN properties p ON l.property_id = p.property_id
                        LEFT JOIN units u ON l.unit_id = u.unit_id
                        WHERE l.tenant_id = ? AND l.status = 'active'
                        ORDER BY l.end_date
                    ");
                    $stmt->execute([$userId]);
                    $leases = $stmt->fetchAll();
                    ?>
                    
                    <?php if (!empty($leases)): ?>
                        <div class="border-t mt-4 pt-4">
                                                       <h4 class="text-sm font-medium text-gray-700 mb-2">Current Leases</h4>
                            <div class="space-y-3">
                                <?php foreach ($leases as $lease): ?>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <p class="text-sm font-medium">
                                            <?php echo htmlspecialchars($lease['property_name']); ?>
                                            <?php if ($lease['unit_number']): ?>
                                                - Unit <?php echo htmlspecialchars($lease['unit_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('M j, Y', strtotime($lease['start_date'])); ?> to 
                                            <?php echo date('M j, Y', strtotime($lease['end_date'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Rent: $<?php echo number_format($lease['monthly_rent'], 2); ?>/month
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                   
                    
                    <!-- Account Actions -->
                    <div class="border-t mt-4 pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Account Actions</h4>
                        <div class="space-y-2">
                            <a href="messages.php" class="flex items-center text-sm text-gray-700 hover:text-primary">
                                <i class="fas fa-envelope w-5"></i>
                                <span>Messages</span>
                            </a>
                           
                            <a href="../logout.php" class="flex items-center text-sm text-red-600 hover:text-red-800">
                                <i class="fas fa-sign-out-alt w-5"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
