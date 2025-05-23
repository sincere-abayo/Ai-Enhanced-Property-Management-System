<?php
require_once '../includes/currency.php';

// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="fixed inset-y-0 left-0 bg-white shadow-lg max-h-screen w-64">
    <div class="flex flex-col justify-between h-full">
        <div class="flex-grow">
            <div class="px-4 py-6 text-center border-b">
                <h1 class="text-xl font-bold leading-none"><span class="text-primary">Property</span> Landlord</h1>
            </div>
            <div class="p-4">
                <ul class="space-y-1">
                    <li>
                        <a href="dashboard.php" class="flex items-center <?php echo ($current_page == 'dashboard.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-home w-6"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="properties.php" class="flex items-center <?php echo ($current_page == 'properties.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-building w-6"></i>Properties
                        </a>
                    </li>
                    <li>
                        <a href="tenants.php" class="flex items-center <?php echo ($current_page == 'tenants.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-users w-6"></i>Tenants
                        </a>
                    </li>
                    <li>
                        <a href="payments.php" class="flex items-center <?php echo ($current_page == 'payments.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-money-bill-wave w-6"></i>Payments
                        </a>
                    </li>
                    <li>
                        <a href="maintenance.php" class="flex items-center <?php echo ($current_page == 'maintenance.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-tools w-6"></i>Maintenance
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center <?php echo ($current_page == 'messages.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-envelope w-6"></i>Messages
                        </a>
                    </li>
                      <!-- Add Chatbot Management Link -->
                      <li>
                        <a href="chatbot_management.php" class="flex items-center <?php echo ($current_page == 'chatbot_management.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-robot w-6"></i>Chatbot
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center <?php echo ($current_page == 'reports.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-xl font-bold text-sm py-3 px-4">
                            <i class="fas fa-chart-bar w-6"></i>Reports
                        </a>
                    </li>
                </ul>
                <div class="mt-auto p-4 border-t border-gray-700">
    <div class="flex items-center justify-between">
        <span class="text-gray-400 text-sm">Currency</span>
        <?php include '../includes/currency_switcher.php'; ?>
    </div>
</div>
            </div>
            
                        <!-- User Profile Section -->
                        <div class="border-t p-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                        <?php 
                        // Display user's initials
                        if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
                            echo substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1);
                        } else {
                            echo "U";
                        }
                        ?>
                    </div>
                    <div class="ml-3">
                        <a href="profile.php" class="text-sm font-medium text-gray-700 hover:text-primary">
                            <?php 
                            if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
                                echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
                            } else {
                                echo "User";
                            }
                            ?>
                        </a>
                        <p class="text-xs text-gray-500">
                            <?php 
                            if (isset($_SESSION['role'])) {
                                echo ucfirst(htmlspecialchars($_SESSION['role']));
                            }
                            ?>
                        </p>
                    </div>
                    
                </div>
                
                <!-- Profile and Logout Buttons -->
                <div class="mt-4 flex space-x-2">
                    <a href="profile.php" class="flex-1 block text-center py-2 px-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                        <i class="fas fa-user-cog mr-2"></i>Profile
                    </a>
                    <a href="../logout.php" class="flex-1 block text-center py-2 px-4 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 text-sm font-medium">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="../js/currency.js"></script>
<script>
    // Initialize currency display when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        const currentCurrency = '<?php echo getUserCurrency(); ?>';
        updatePageCurrencies(currentCurrency);
        
        // Listen for currency change events
        document.addEventListener('currencyChanged', function(e) {
            updatePageCurrencies(e.detail.currency);
        });
    });
</script>