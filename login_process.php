<?php
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (hasRole('landlord') || hasRole('admin')) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: tenant/dashboard.php');
    }
    exit;
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Plain text password
    $role = $_POST['role'];
    
    // Validate input
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Email and password are required';
        header('Location: index.php');
        exit;
    }
    
    // Check if user exists with the given email
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Compare passwords directly without hashing
    if ($user && $password === $user['password']) {
        // Check if user role matches selected role
        if ($user['role'] !== $role) {
            $_SESSION['error'] = 'Invalid credentials for selected role';
            header('Location: index.php');
            exit;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        if ($role === 'landlord' || $role === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: tenant/dashboard.php');
        }
        exit;
    } else {
        $_SESSION['error'] = 'Invalid email or password';
        header('Location: index.php');
        exit;
    }
}

// If not a POST request, redirect to login page
header('Location: index.php');
exit;
?>
