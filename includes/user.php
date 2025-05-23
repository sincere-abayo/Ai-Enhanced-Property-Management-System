<?php
require_once 'db_connect.php';
require_once 'auth.php';

/**
 * Get logged-in user information
 * 
 * @return array|false User information array or false if not logged in
 */
function getLoggedInUser() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare('SELECT user_id, email, first_name, last_name, phone, role FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Update user information
 * 
 * @param array $userData Associative array of user data to update
 * @return bool True if update successful, false otherwise
 */
function updateUserInfo($userData) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $userId = $_SESSION['user_id'];
    
    // Only allow updating specific fields
    $allowedFields = ['first_name', 'last_name', 'phone', 'email'];
    $fieldsToUpdate = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($userData[$field]) && !empty($userData[$field])) {
            $fieldsToUpdate[] = "$field = ?";
            $params[] = $userData[$field];
        }
    }
    
    if (empty($fieldsToUpdate)) {
        return false;
    }
    
    // Add user_id to params
    $params[] = $userId;
    
    $sql = "UPDATE users SET " . implode(', ', $fieldsToUpdate) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    
    try {
        $result = $stmt->execute($params);
        
        // Update session variables if email or name was changed
        if ($result) {
            if (isset($userData['email'])) {
                $_SESSION['email'] = $userData['email'];
            }
            if (isset($userData['first_name'])) {
                $_SESSION['first_name'] = $userData['first_name'];
            }
            if (isset($userData['last_name'])) {
                $_SESSION['last_name'] = $userData['last_name'];
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Change user password
 * 
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return bool True if password change successful, false otherwise
 */
function changeUserPassword($currentPassword, $newPassword) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $userId = $_SESSION['user_id'];
    
    // First verify current password
    $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    // For plain text password comparison (as requested)
    if ($currentPassword !== $user['password']) {
        return false;
    }
    
    // Update with new password (plain text as requested)
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    
    try {
        return $stmt->execute([$newPassword, $userId]);
    } catch (PDOException $e) {
        return false;
    }
}
?>
