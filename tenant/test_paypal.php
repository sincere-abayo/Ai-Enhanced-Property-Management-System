<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/paypal_config.php';

// Require tenant role
requireRole('tenant');

$testResults = [];

// Test 1: Check if constants are defined
$testResults['constants'] = [
    'PAYPAL_CLIENT_ID' => defined('PAYPAL_CLIENT_ID') ? 'Defined' : 'Not defined',
    'PAYPAL_CLIENT_SECRET' => defined('PAYPAL_CLIENT_SECRET') ? 'Defined' : 'Not defined',
    'PAYPAL_BASE_URL' => defined('PAYPAL_BASE_URL') ? PAYPAL_BASE_URL : 'Not defined',
    'PAYPAL_SANDBOX' => defined('PAYPAL_SANDBOX') ? (PAYPAL_SANDBOX ? 'true' : 'false') : 'Not defined',
    'PAYPAL_RETURN_URL' => defined('PAYPAL_RETURN_URL') ? PAYPAL_RETURN_URL : 'Not defined',
    'PAYPAL_CANCEL_URL' => defined('PAYPAL_CANCEL_URL') ? PAYPAL_CANCEL_URL : 'Not defined'
];

// Test 2: Check cURL availability
$testResults['curl'] = extension_loaded('curl') ? 'Available' : 'Not available';

// Test 3: Test PayPal connection
$testResults['connection'] = testPayPalConnection();

// Test 4: Test access token
$token = getPayPalAccessToken();
$testResults['access_token'] = $token ? 'Successfully obtained' : 'Failed to obtain';

// Test 5: Test creating a small payment (without executing)
$testPayment = null;
if ($token) {
    $testPayment = createPayPalPayment(1.00, 'USD', 'Test payment', 1);
    $testResults['test_payment'] = $testPayment ? 'Payment creation successful' : 'Payment creation failed';
} else {
    $testResults['test_payment'] = 'Skipped (no access token)';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Test - Tenant Portal</title>
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
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex items-center mb-8">
            <a href="payments.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">PayPal Integration Test</h2>
                <p class="text-gray-600">Test PayPal configuration and connectivity</p>
            </div>
        </div>

        <!-- Test Results -->
        <div class="space-y-6">
            <!-- Constants Test -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Configuration Constants</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($testResults['constants'] as $constant => $value): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="font-medium"><?php echo $constant; ?>:</span>
                        <span class="<?php echo $value === 'Not defined' ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo $value; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- cURL Test -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">cURL Extension</h3>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <span class="font-medium">cURL Status:</span>
                    <span
                        class="<?php echo $testResults['curl'] === 'Available' ? 'text-green-600' : 'text-red-600'; ?>">
                        <i
                            class="fas <?php echo $testResults['curl'] === 'Available' ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                        <?php echo $testResults['curl']; ?>
                    </span>
                </div>
            </div>

            <!-- Connection Test -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">PayPal Connection</h3>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <span class="font-medium">Connection Status:</span>
                    <span
                        class="<?php echo $testResults['connection']['success'] ? 'text-green-600' : 'text-red-600'; ?>">
                        <i
                            class="fas <?php echo $testResults['connection']['success'] ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                        <?php echo $testResults['connection']['message']; ?>
                    </span>
                </div>
            </div>

            <!-- Access Token Test -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Access Token</h3>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <span class="font-medium">Token Status:</span>
                    <span class="<?php echo $token ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas <?php echo $token ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                        <?php echo $testResults['access_token']; ?>
                    </span>
                </div>
                <?php if ($token): ?>
                <div class="mt-3 p-3 bg-green-50 rounded">
                    <p class="text-sm text-green-800">
                        <strong>Token Preview:</strong> <?php echo substr($token, 0, 20) . '...'; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Creation Test -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Payment Creation Test</h3>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <span class="font-medium">Payment Creation:</span>
                    <span class="<?php echo $testPayment ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas <?php echo $testPayment ? 'fa-check' : 'fa-times'; ?> mr-2"></i>
                        <?php echo $testResults['test_payment']; ?>
                    </span>
                </div>
                <?php if ($testPayment): ?>
                <div class="mt-3 p-3 bg-green-50 rounded">
                    <p class="text-sm text-green-800">
                        <strong>Test Payment ID:</strong> <?php echo $testPayment['id']; ?>
                    </p>
                    <p class="text-sm text-green-800">
                        <strong>State:</strong> <?php echo $testPayment['state']; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Error Logs -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Error Logs</h3>
                <div class="bg-gray-50 rounded p-4">
                    <p class="text-sm text-gray-600 mb-2">Check your server error logs for detailed PayPal API
                        responses.</p>
                    <p class="text-xs text-gray-500">
                        Typical log location: <code>/var/log/apache2/error.log</code> or
                        <code>/var/log/nginx/error.log</code>
                    </p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Troubleshooting Steps</h3>
                <div class="space-y-3">
                    <div class="p-3 bg-blue-50 rounded">
                        <h4 class="font-medium text-blue-800 mb-2">1. Verify PayPal Credentials</h4>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Log into <a href="https://developer.paypal.com" target="_blank"
                                    class="underline">PayPal Developer</a></li>
                            <li>• Check your sandbox app credentials</li>
                            <li>• Ensure Client ID and Secret are correct</li>
                        </ul>
                    </div>

                    <div class="p-3 bg-yellow-50 rounded">
                        <h4 class="font-medium text-yellow-800 mb-2">2. Check Server Configuration</h4>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li>• Ensure cURL extension is enabled</li>
                            <li>• Check firewall settings for outbound HTTPS</li>
                            <li>• Verify SSL certificates are up to date</li>
                        </ul>
                    </div>

                    <div class="p-3 bg-green-50 rounded">
                        <h4 class="font-medium text-green-800 mb-2">3. Test with PayPal Sandbox</h4>
                        <ul class="text-sm text-green-700 space-y-1">
                            <li>• Use sandbox test accounts</li>
                            <li>• Test with small amounts ($1.00)</li>
                            <li>• Check PayPal sandbox dashboard for transactions</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Actions</h3>
                <div class="flex space-x-4">
                    <button onclick="location.reload()"
                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-sync mr-2"></i>Rerun Tests
                    </button>
                    <a href="payments.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Payments
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>