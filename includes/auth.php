<?php
session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user has specific role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Function to require login for protected pages
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

// Function to require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ../unauthorized.php');
        exit;
    }
}

include_once 'db_connect.php';
?>
