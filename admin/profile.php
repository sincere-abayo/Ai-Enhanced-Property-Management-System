<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get user information
$userId = $_SESSION['user_id'];

// Get user details
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE user_id = :userId
");
$stmt->execute(['userId' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found";
    header("Location: dashboard.php");
    exit;
}

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile update
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
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
        
        // Check if email already exists for another user
        if ($email !== $user['email']) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already in use by another user";
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
                        phone = :phone
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
                
                $success = "Profile updated successfully";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :userId");
                $stmt->execute(['userId' => $userId]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Password change
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
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
        if (!empty($currentPassword) && $currentPassword === $newPassword) {
            $errors[] = "New password is the same as the current password";
        }
        
        // If no errors, update password
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = :password
                    WHERE user_id = :userId
                ");
                
                $stmt->execute([
                    'password' => $hashedPassword,
                    'userId' => $userId
                ]);
                
                $success = "Password changed successfully";
                
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Property Management System</title>
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
    <div class="sm:ml-64 p-4 sm:p-8 transition-all duration-200">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">My Profile</h2>
                <p class="text-gray-600">Manage your account information</p>
            </div>
            <!-- Hamburger for mobile -->
            <button id="openSidebarBtn" class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary mb-2" aria-label="Open sidebar" onclick="document.getElementById('adminSidebar').classList.remove('-translate-x-full'); document.getElementById('sidebarBackdrop').classList.remove('hidden');">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
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

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Profile Information</h3>
                <p class="text-sm text-gray-500">Update your account's profile information</p>
            </div>
            <div class="p-6">
                <form method="POST" action="profile.php">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                value="<?php echo htmlspecialchars($user['first_name']); ?>" 
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
                                value="<?php echo htmlspecialchars($user['last_name']); ?>" 
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
                                value="<?php echo htmlspecialchars($user['email']); ?>" 
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
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                            >
                        </div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <input 
                                type="text" 
                                id="role" 
                                value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" 
                                class="w-full rounded-lg border-gray-300 bg-gray-50"
                                readonly
                            >
                        </div>
                        <div>
                            <label for="created_at" class="block text-sm font-medium text-gray-700 mb-1">Member Since</label>
                            <input 
                                type="text" 
                                id="created_at" 
                                value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" 
                                class="w-full rounded-lg border-gray-300 bg-gray-50"
                                readonly
                            >
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button 
                            type="submit" 
                            name="update_profile" 
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Change Password</h3>
                <p class="text-sm text-gray-500">Ensure your account is using a secure password</p>
            </div>
            <div class="p-6">
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
                                minlength="8"
                            >
                            <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                required
                                minlength="8"
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
    </div>
</body>
</html>