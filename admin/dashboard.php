<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];

// Get dashboard data
$propertyCount = getPropertyCount($userId);
$newPropertiesThisMonth = getNewPropertiesThisMonth($userId);
$tenantCount = getTenantCount($userId);
$newTenantsThisMonth = getNewTenantsThisMonth($userId);
$monthlyIncome = getMonthlyIncome($userId);
$incomePercentageChange = getIncomePercentageChange($userId);
$pendingPayments = getPendingPayments($userId);
$recentActivities = getRecentActivities($userId);
$upcomingTasks = getUpcomingTasks($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Property Management System</title>
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
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</h2>
                <p class="text-gray-600">Here's what's happening with your properties today</p>
            </div>
            <div class="flex space-x-4">
              <div>
                <div class="relative ml-3">
                    <button id="notificationButton" class="relative p-1 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        <?php
                        // Get unread notification count
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$userId]);
                        $unreadCount = $stmt->fetch()['count'];
                        
                        if ($unreadCount > 0):
                        ?>
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                            <?php echo $unreadCount; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Dropdown menu -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg py-1 z-10">
                        <div class="px-4 py-2 border-b border-gray-200">
                        <div class="flex justify-between items-center">
    <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
    <?php if ($unreadCount > 0): ?>
    <a href="#" id="markAllReadBtn" class="text-xs text-primary hover:text-blue-700">Mark all as read</a>
    <?php endif; ?>
</div>

                        </div>
                        
                        <div class="max-h-64 overflow-y-auto">
                            <?php
                            // Get recent notifications
                            $stmt = $pdo->prepare("
                                SELECT * FROM notifications 
                                WHERE user_id = ? 
                                ORDER BY created_at DESC 
                                LIMIT 5
                            ");
                            $stmt->execute([$userId]);
                            $notifications = $stmt->fetchAll();
                            
                            if (empty($notifications)):
                            ?>
                            <div class="px-4 py-3 text-sm text-gray-500 text-center">
                                No notifications
                            </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                <a href="view_notification.php?id=<?php echo $notification['notification_id']; ?>" class="block px-4 py-2 hover:bg-gray-200 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-1">
                                            <?php if ($notification['type'] === 'payment'): ?>
                                                <i class="fas fa-money-bill-wave text-green-500"></i>
                                            <?php elseif ($notification['type'] === 'maintenance'): ?>
                                                <i class="fas fa-tools text-yellow-500"></i>
                                            <?php elseif ($notification['type'] === 'lease'): ?>
                                                <i class="fas fa-file-contract text-blue-500"></i>
                                            <?php else: ?>
                                                <i class="fas fa-bell text-gray-500"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rounded-lg ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(mb_strimwidth($notification['title'], 0, 30, "...")); ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars(mb_strimwidth($notification['message'], 0, 20, "...")); ?></p>
                                            <p class="text-xs text-gray-400 mt-1"><?php echo timeAgo($notification['created_at']); ?></p>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="px-4 py-2 border-t border-gray-200">
                            <a href="notifications.php" class="block text-center text-sm text-primary hover:text-blue-700">
                                View all notifications
                            </a>
                        </div>
                    </div>
                </div>

              </div>
               
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-building text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Properties</h3>
                        <p class="text-2xl font-semibold"><?php echo $propertyCount; ?></p>
                        <p class="text-sm text-green-500">+<?php echo $newPropertiesThisMonth; ?> this month</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-users text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Active Tenants</h3>
                        <p class="text-2xl font-semibold"><?php echo $tenantCount; ?></p>
                        <p class="text-sm text-green-500">+<?php echo $newTenantsThisMonth; ?> this month</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-money-bill-wave text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Monthly Income</h3>
                        <p class="text-2xl font-semibold">$<?php echo number_format($monthlyIncome, 2); ?></p>
                        <p class="text-sm <?php echo $incomePercentageChange >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                            <?php echo $incomePercentageChange >= 0 ? '+' : ''; ?><?php echo $incomePercentageChange; ?>% vs last month
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Pending Payments</h3>
                        <p class="text-2xl font-semibold"><?php echo $pendingPayments['total']; ?></p>
                        <p class="text-sm text-red-500">$<?php echo number_format($pendingPayments['amount'], 2); ?> overdue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activities -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Recent Activities</h3>
                    <!-- <a href="activities.php" class="text-primary text-sm hover:text-blue-700">View All</a> -->
                </div>
                <div class="space-y-4">
                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-4 text-gray-500">No recent activities</div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <?php if ($activity['type'] === 'payment'): ?>
                                    <div class="p-2 rounded-full bg-green-100">
                                        <i class="fas fa-check text-green-500"></i>
                                    </div>
                                <?php elseif ($activity['type'] === 'maintenance'): ?>
                                    <div class="p-2 rounded-full bg-yellow-100">
                                        <i class="fas fa-tools text-yellow-500"></i>
                                    </div>
                                <?php elseif ($activity['type'] === 'lease'): ?>
                                    <div class="p-2 rounded-full bg-blue-100">
                                        <i class="fas fa-file-alt text-blue-500"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="ml-4">
                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo getRelativeTime($activity['date']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Tasks -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Upcoming Tasks</h3>
                    <!-- <a href="tasks.php" class="text-primary text-sm hover:text-blue-700">View All</a> -->
                </div>
                <div class="space-y-4">
                    <?php if (empty($upcomingTasks)): ?>
                        <div class="text-center py-4 text-gray-500">No upcoming tasks</div>
                    <?php else: ?>
                        <?php foreach ($upcomingTasks as $task): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <?php if ($task['type'] === 'lease'): ?>
                                        <div class="p-2 rounded-full bg-blue-100">
                                            <i class="fas fa-calendar text-blue-500"></i>
                                        </div>
                                    <?php elseif ($task['type'] === 'maintenance'): ?>
                                        <div class="p-2 rounded-full bg-yellow-100">
                                            <i class="fas fa-tools text-yellow-500"></i>
                                        </div>
                                    <?php elseif ($task['type'] === 'payment'): ?>
                                        <div class="p-2 rounded-full bg-green-100">
                                            <i class="fas fa-money-bill-wave text-green-500"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo getDueText($task['date']); ?></p>
                                    </div>
                                </div>
                               
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <!-- <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <a href="add_property.php" class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-blue-100 mb-2">
                            <i class="fas fa-plus text-blue-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Add Property</span>
                    </div>
                </a>
                <a href="add_tenant.php" class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-green-100 mb-2">
                            <i class="fas fa-user-plus text-green-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Add Tenant</span>
                    </div>
                </a>
                <a href="add_maintenance.php" class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-yellow-100 mb-2">
                            <i class="fas fa-tools text-yellow-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">New Maintenance</span>
                    </div>
                </a>
                <a href="record_payment.php" class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-purple-100 mb-2">
                            <i class="fas fa-file-invoice-dollar text-purple-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Record Payment</span>
                    </div>
                </a>
            </div>
        </div> -->
    </div>
    <script>
    // Toggle notification dropdown
    document.getElementById('notificationButton').addEventListener('click', function() {
        document.getElementById('notificationDropdown').classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('notificationDropdown');
        const button = document.getElementById('notificationButton');
        
        if (!dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });
    // Add this to the existing script section or create a new one
document.addEventListener('DOMContentLoaded', function() {
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show loading state
            markAllReadBtn.innerHTML = 'Processing...';
            markAllReadBtn.classList.add('opacity-50');
            
            // Send AJAX request to mark all as read
            fetch('mark_all_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to reflect all notifications are read
                    const notificationBadge = document.querySelector('.fa-bell + span');
                    if (notificationBadge) {
                        notificationBadge.style.display = 'none';
                    }
                    
                    // Remove highlighting from unread notifications
                    const unreadNotifications = document.querySelectorAll('.bg-blue-50');
                    unreadNotifications.forEach(notification => {
                        notification.classList.remove('bg-blue-50');
                    });
                    
                    // Hide the "Mark all as read" button
                    markAllReadBtn.style.display = 'none';
                    
                    // Show success message
                    const notificationHeader = document.querySelector('.px-4.py-2.border-b.border-gray-200');
                    const successMsg = document.createElement('div');
                    successMsg.className = 'text-xs text-green-600 mt-1';
                    successMsg.textContent = 'All notifications marked as read';
                    notificationHeader.appendChild(successMsg);
                    
                    // Remove success message after 3 seconds
                    setTimeout(() => {
                        successMsg.remove();
                    }, 3000);
                } else {
                    // Show error message
                    markAllReadBtn.innerHTML = 'Error. Try again';
                    setTimeout(() => {
                        markAllReadBtn.innerHTML = 'Mark all as read';
                        markAllReadBtn.classList.remove('opacity-50');
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                markAllReadBtn.innerHTML = 'Error. Try again';
                setTimeout(() => {
                    markAllReadBtn.innerHTML = 'Mark all as read';
                    markAllReadBtn.classList.remove('opacity-50');
                }, 2000);
            });
        });
    }
});

</script>
</body>
</html>
