<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if ($role === 'admin' && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    if ($role === 'landlord' && ($_SESSION['role'] === 'landlord' || $_SESSION['role'] === 'admin')) {
        return true;
    }
    
    if ($role === 'tenant' && $_SESSION['role'] === 'tenant') {
        return true;
    }
    
    return false;
}

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'You must be logged in to access this page';
        header('Location: ../index.php');
        exit;
    }

}

/**
 * Require user to have a specific role, redirect if not
 */
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        $_SESSION['error'] = 'You do not have permission to access this page';
        
        // Redirect to appropriate dashboard based on current role
        if ($_SESSION['role'] === 'tenant') {
            header('Location: ../tenant/dashboard.php');
        } else {
            header('Location: ../admin/dashboard.php');
        }
        exit;
    }
    
}

/**
 * Log out the current user
 */
function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: ../index.php');
    exit;
}
// Add this to the existing auth.php file

/**
 * Set user's preferred currency
 * 
 * @param string $currency Currency code (USD or RWF)
 */
function setUserCurrency($currency) {
    if ($currency === 'USD' || $currency === 'RWF') {
        $_SESSION['currency'] = $currency;
    }
}

/**
 * Get user's preferred currency
 * 
 * @return string Currency code (USD or RWF)
 */
function getUserCurrency() {
    return $_SESSION['currency'] ?? 'USD'; // Default to USD
}    // Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$lastName = $_SESSION['last_name'];