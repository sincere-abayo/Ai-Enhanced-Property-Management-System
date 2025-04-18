<?php
// add error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
?>

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
<?php include 'admin_sidebar.php'; ?>  


<!-- Add this to the top of each admin page, perhaps in a header.php include -->
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
                <a href="mark_all_read.php" class="text-xs text-primary hover:text-blue-700">Mark all as read</a>
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
                <a href="view_notification.php?id=<?php echo $notification['notification_id']; ?>" class="block px-4 py-2 hover:bg-gray-50 <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
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
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo 6//echo timeAgo($notification['created_at']); ?></p>
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
</body>
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
</script>
