<?php
// PayPal configuration
// define('PAYPAL_SANDBOX', true); // Set to false for live payments
const PAYPAL_SANDBOX = true;
// if (PAYPAL_SANDBOX) {
    // Sandbox credentials
    define('PAYPAL_CLIENT_ID', 'AbKnQis5FIRAQBIWTblDJ2x9z4tKNNJSFwoIC711ImorBwLBF5-STYB_4VHo7-GD9RNpP9M3fAWZ5r78');
    define('PAYPAL_CLIENT_SECRET', 'EP8smu5YJfXXvdYB9yXDrLOonDc7ALMMlTsWI-eXIajnTzqaO93oXpiWENIBQKNMAe0E-PU-uQBqGU0k');
    define('PAYPAL_BASE_URL', 'https://api.sandbox.paypal.com');
    define('PAYPAL_WEB_URL', 'https://sandbox.paypal.com');
// }

// Return URLs
const PAYPAL_RETURN_URL = 'http://localhost/utb/Ai-Enhanced-Property-Management-System/tenant/payment_success.php';
const PAYPAL_CANCEL_URL = 'http://localhost/utb/Ai-Enhanced-Property-Management-System/tenant/payment_cancel.php';

/**
 * Get PayPal access token
 */
function getPayPalAccessToken() {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: US'
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Debug logging
    error_log("PayPal Token Request - HTTP Code: $httpCode");
    error_log("PayPal Token Response: " . $result);
    
    if ($curlError) {
        error_log("PayPal cURL Error: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("PayPal Token Error - HTTP $httpCode: " . $result);
        return false;
    }
    
    $json = json_decode($result, true);
    
    if (!$json || !isset($json['access_token'])) {
        error_log("PayPal Token Error - Invalid response: " . $result);
        return false;
    }
    
    return $json['access_token'];
}

/**
 * Create PayPal payment
 */
function createPayPalPayment($amount, $currency, $description, $lease_id) {
    $access_token = getPayPalAccessToken();
    
    if (!$access_token) {
        error_log("PayPal Payment Error: Could not get access token");
        return false;
    }
    
    $payment_data = [
        'intent' => 'sale',
        'payer' => [
            'payment_method' => 'paypal'
        ],
        'transactions' => [
            [
                'amount' => [
                    'total' => number_format($amount, 2, '.', ''),
                    'currency' => $currency
                ],
                'description' => $description,
                'custom' => (string)$lease_id // Store lease_id for reference
            ]
        ],
        'redirect_urls' => [
            'return_url' => PAYPAL_RETURN_URL,
            'cancel_url' => PAYPAL_CANCEL_URL
        ]
    ];
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL . '/v1/payments/payment');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Debug logging
    error_log("PayPal Payment Request - HTTP Code: $httpCode");
    error_log("PayPal Payment Request Data: " . json_encode($payment_data));
    error_log("PayPal Payment Response: " . $result);
    
    if ($curlError) {
        error_log("PayPal Payment cURL Error: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 201) {
        error_log("PayPal Payment Error - HTTP $httpCode: " . $result);
        return false;
    }
    
    $response = json_decode($result, true);
    
    if (!$response || !isset($response['id'])) {
        error_log("PayPal Payment Error - Invalid response: " . $result);
        return false;
    }
    
    return $response;
}

/**
 * Execute PayPal payment
 */
function executePayPalPayment($payment_id, $payer_id) {
    $access_token = getPayPalAccessToken();
    
    if (!$access_token) {
        error_log("PayPal Execute Error: Could not get access token");
        return false;
    }
    
    $execute_data = [
        'payer_id' => $payer_id
    ];
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, PAYPAL_BASE_URL . '/v1/payments/payment/' . $payment_id . '/execute');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($execute_data));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Debug logging
    error_log("PayPal Execute Request - HTTP Code: $httpCode");
    error_log("PayPal Execute Response: " . $result);
    
    if ($curlError) {
        error_log("PayPal Execute cURL Error: " . $curlError);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("PayPal Execute Error - HTTP $httpCode: " . $result);
        return false;
    }
    
    return json_decode($result, true);
}
/**
 * Test PayPal connection
 */
function testPayPalConnection() {
    $token = getPayPalAccessToken();
    if ($token) {
        return ['success' => true, 'message' => 'PayPal connection successful'];
    } else {
        return ['success' => false, 'message' => 'PayPal connection failed'];
    }
}
?>