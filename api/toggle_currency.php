<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Set headers to allow JSON response
header('Content-Type: application/json');

// Get the requested currency from POST or GET
$currency = $_POST['currency'] ?? $_GET['currency'] ?? null;

if (!$currency || !in_array($currency, ['USD', 'RWF'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid currency specified'
    ]);
    exit;
}

// Set the user's currency preference (works for both logged-in and non-logged-in users)
setUserCurrency($currency);

// Return success response
echo json_encode([
    'success' => true,
    'currency' => $currency,
    'message' => 'Currency updated successfully',
    'user_logged_in' => isLoggedIn()
]);
?>