<?php
session_start();
require_once 'includes/db_connect.php';

// Initialize variables
$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$errors = [];
$success = false;
$email = '';

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists in the database
        $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $errors[] = "No account found with that email address";
        } else {
            // Store email in session for the reset step
            $_SESSION['reset_email'] = $email;
            
            // Redirect to reset password form
            header("Location: forgot_password.php?step=reset");
            exit;
        }
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    
    // Validate input
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    } elseif (empty($email)) {
        $errors[] = "Email not found. Please restart the password reset process.";
    } else {
        try {
           
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ? 
                WHERE email = ?
            ");
            $stmt->execute([$password, $email]);
            
            if ($stmt->rowCount() > 0) {
                // Password updated successfully
                $success = true;
                $step = 'complete';
                
                // Clear the reset email from session
                unset($_SESSION['reset_email']);
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Check if we have a reset email in session for the reset step
if ($step === 'reset' && !isset($_SESSION['reset_email'])) {
    // If no email in session, redirect back to request form
    header("Location: forgot_password.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Rental Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
  <!-- Main Container -->
  <div class="bg-white shadow-2xl rounded-lg overflow-hidden w-full max-w-md">
    <!-- Header Section -->
    <div class="bg-primary p-6">
      <h1 class="text-3xl font-bold text-white text-center">Reset Password</h1>
      <p class="text-sm text-blue-100 text-center mt-2">Rental Management System</p>
    </div>

    <!-- Content Section -->
    <div class="p-8">
      <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php foreach ($errors as $error): ?>
            <p><?php echo $error; ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($step === 'request'): ?>
        <!-- Request Password Reset Form -->
        <p class="mb-6 text-gray-600">Enter your email address to reset your password.</p>
        <form action="forgot_password.php" method="POST">
          <div class="mb-6">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input
              type="email"
              id="email"
              name="email"
              value="<?php echo htmlspecialchars($email); ?>"
              placeholder="Enter your email"
              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
              required
            />
          </div>

          <button
            type="submit"
            name="request_reset"
            class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
          >
            Continue
          </button>
        </form>
      <?php elseif ($step === 'reset'): ?>
        <!-- Reset Password Form -->
        <p class="mb-6 text-gray-600">Enter your new password below for <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>.</p>
        <form action="forgot_password.php?step=reset" method="POST">
          <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter new password"
              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
              required
              minlength="8"
            />
            <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
          </div>

          <div class="mb-6">
            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              placeholder="Confirm new password"
              class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary"
              required
              minlength="8"
            />
          </div>

          <button
            type="submit"
            name="reset_password"
            class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
          >
            Reset Password
          </button>
        </form>
      <?php elseif ($step === 'complete'): ?>
        <!-- Password Reset Complete -->
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
          <p>Your password has been reset successfully!</p>
        </div>
        <p class="mb-6 text-gray-600">You can now log in with your new password.</p>
        <a
          href="index.php"
          class="block w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-blue-700 text-center"
        >
          Return to Login
        </a>
      <?php endif; ?>

      <!-- Back to Login -->
      <div class="mt-6 text-center">
        <a href="index.php" class="text-primary hover:text-blue-700">Back to Login</a>
      </div>
    </div>
  </div>
</body>
</html>
