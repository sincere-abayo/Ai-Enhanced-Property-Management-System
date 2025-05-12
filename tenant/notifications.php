<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();
// Require tenant role
requireRole('tenant');

// Get user ID
$userId = $_SESSION['user_id'];

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter settings
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$readStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query conditions
$conditions = ["user_id = :userId"];
$params = ['userId' => $userId];

if ($type !== 'all') {
    $conditions[] = "type = :type";
    $params['type'] = $type;
}

if ($readStatus !== 'all') {
    $conditions[] = "is_read = :isRead";
    $params['isRead'] = ($readStatus === 'read') ? 1 : 0;
}

$whereClause = implode(' AND ', $conditions);

// Get total count for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE $whereClause
");
$stmt->execute($params);
$totalCount = $stmt->fetch()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get notifications
$stmt = $pdo->prepare("
    SELECT * 
    FROM notifications 
    WHERE $whereClause
    ORDER BY created_at DESC
    LIMIT :offset, :perPage
");

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();

$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = :userId AND is_read = 0
");
$stmt->execute(['userId' => $userId]);
$unreadCount = $stmt->fetch()['count'];

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $selectedIds = isset($_POST['selected']) ? $_POST['selected'] : [];
    
    if (!empty($selectedIds)) {
        if ($_POST['action'] === 'mark_read') {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE notification_id IN ($placeholders) AND user_id = ?
            ");
            
            $params = array_merge($selectedIds, [$userId]);
            $stmt->execute($params);
            
            $_SESSION['success'] = count($selectedIds) . ' notification(s) marked as read';
            header('Location: notifications.php');
            exit;
        } elseif ($_POST['action'] === 'mark_unread') {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 0 
                WHERE notification_id IN ($placeholders) AND user_id = ?
            ");
            
            $params = array_merge($selectedIds, [$userId]);
            $stmt->execute($params);
            
            $_SESSION['success'] = count($selectedIds) . ' notification(s) marked as unread';
            header('Location: notifications.php');
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE notification_id IN ($placeholders) AND user_id = ?
            ");
            
            $params = array_merge($selectedIds, [$userId]);
            $stmt->execute($params);
            
            $_SESSION['success'] = count($selectedIds) . ' notification(s) deleted';
            header('Location: notifications.php');
            exit;
        }
    } else {
        $_SESSION['error'] = 'No notifications selected';
        header('Location: notifications.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Property Management System</title>
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
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Notifications</h2>
                <p class="text-gray-600">
                    Manage your notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="ml-2 px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                            <?php echo $unreadCount; ?> unread
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <?php if ($unreadCount > 0): ?>
                <a href="mark_all_read.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-check-double mr-2"></i>Mark All as Read
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" action="notifications.php" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="payment" <?php echo $type === 'payment' ? 'selected' : ''; ?>>Payment</option>
                        <option value="maintenance" <?php echo $type === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="lease" <?php echo $type === 'lease' ? 'selected' : ''; ?>>Lease</option>
                        <option value="general" <?php echo $type === 'general' ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                        <option value="all" <?php echo $readStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="unread" <?php echo $readStatus === 'unread' ? 'selected' : ''; ?>>Unread</option>
                        <option value="read" <?php echo $readStatus === 'read' ? 'selected' : ''; ?>>Read</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Notifications List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($notifications)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No notifications found.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="notifications.php">
                    <!-- Bulk Actions -->
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="selectAll" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                <label for="selectAll" class="ml-2 text-sm text-gray-700">Select All</label>
                            </div>
                            <div>
                                <select name="action" class="text-sm rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                    <option value="">Bulk Actions</option>
                                    <option value="mark_read">Mark as Read</option>
                                    <option value="mark_unread">Mark as Unread</option>
                                    <option value="delete">Delete</option>
                                </select>
                            </div>
                            <button type="submit" class="px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Notifications Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notification</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($notifications as $notification): ?>
                                <tr class="<?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" name="selected[]" value="<?php echo $notification['notification_id']; ?>" class="notification-checkbox h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                    </td>
                                    <td class="px-6 py-4">
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
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch ($notification['type']) {
                                                case 'payment':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'maintenance':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'lease':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                                    break;
                                            }
                                            ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo timeAgo($notification['created_at']); ?>
                                    </td>
                                                                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($notification['is_read']): ?>
                                            <button 
                                                type="button" 
                                                class="text-primary hover:text-blue-700 mr-3 notification-action" 
                                                data-id="<?php echo $notification['notification_id']; ?>" 
                                                data-action="unread"
                                            >
                                                Mark as Unread
                                            </button>
                                        <?php else: ?>
                                            <button 
                                                type="button" 
                                                class="text-primary hover:text-blue-700 mr-3 notification-action" 
                                                data-id="<?php echo $notification['notification_id']; ?>" 
                                                data-action="read"
                                            >
                                                Mark as Read
                                            </button>
                                        <?php endif; ?>
                                        <button 
                                            type="button" 
                                            class="text-red-600 hover:text-red-900 notification-action" 
                                            data-id="<?php echo $notification['notification_id']; ?>" 
                                            data-action="delete"
                                            data-confirm="Are you sure you want to delete this notification?"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalCount); ?></span> of 
                            <span class="font-medium"><?php echo $totalCount; ?></span> notifications
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type; ?>&status=<?php echo $readStatus; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>&status=<?php echo $readStatus; ?>" class="px-3 py-1 <?php echo $i === $page ? 'bg-primary text-white' : 'bg-white text-gray-700'; ?> border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type; ?>&status=<?php echo $readStatus; ?>" class="px-3 py-1 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Select all functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    
                    notificationCheckboxes.forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                });
            }
            
            // Update "Select All" checkbox state based on individual checkboxes
            notificationCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(notificationCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(notificationCheckboxes).some(cb => cb.checked);
                    
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                    }
                });
            });
            
            // Handle notification actions (mark as read/unread, delete)
            document.querySelectorAll('.notification-action').forEach(button => {
                button.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-id');
                    const action = this.getAttribute('data-action');
                    const confirmMessage = this.getAttribute('data-confirm');
                    
                    // If confirmation is required and user cancels, do nothing
                    if (confirmMessage && !confirm(confirmMessage)) {
                        return;
                    }
                    
                    // Show loading state
                    const originalText = this.textContent.trim();
                    this.textContent = 'Processing...';
                    this.disabled = true;
                    
                    // Get the notification row
                    const row = this.closest('tr');
                    
                    // Send AJAX request
                    fetch('handle_notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            notification_id: notificationId,
                            action: action
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (action === 'delete') {
                                // Remove the row with animation
                                row.style.transition = 'opacity 0.5s';
                                row.style.opacity = '0';
                                setTimeout(() => {
                                    row.remove();
                                    
                                    // If no more notifications, show empty state
                                    const tbody = document.querySelector('tbody');
                                    if (tbody && tbody.children.length === 0) {
                                        const table = tbody.closest('table');
                                        const container = table.closest('.bg-white');
                                        
                                        table.remove();
                                        
                                        const emptyState = document.createElement('div');
                                        emptyState.className = 'p-6 text-center';
                                        emptyState.innerHTML = '<p class="text-gray-500">No notifications found.</p>';
                                        container.appendChild(emptyState);
                                    }
                                }, 500);
                            } else if (action === 'read' || action === 'unread') {
                                // Update row styling
                                if (action === 'read') {
                                    row.classList.remove('bg-blue-50');
                                    this.textContent = 'Mark as Unread';
                                    this.setAttribute('data-action', 'unread');
                                } else {
                                    row.classList.add('bg-blue-50');
                                    this.textContent = 'Mark as Read';
                                    this.setAttribute('data-action', 'read');
                                }
                                
                                // Update unread count in header
                                const unreadCountElement = document.querySelector('.ml-2.px-2.py-1.text-xs.font-medium.bg-red-100.text-red-800.rounded-full');
                                if (unreadCountElement) {
                                    let unreadCount = parseInt(unreadCountElement.textContent);
                                    
                                    if (action === 'read') {
                                        unreadCount--;
                                    } else {
                                        unreadCount++;
                                    }
                                    
                                    if (unreadCount > 0) {
                                        unreadCountElement.textContent = unreadCount + ' unread';
                                    } else {
                                        unreadCountElement.remove();
                                    }
                                } else if (action === 'unread') {
                                    // Add unread count if it doesn't exist
                                    const headerText = document.querySelector('.text-gray-600');
                                    if (headerText) {
                                        const unreadBadge = document.createElement('span');
                                        unreadBadge.className = 'ml-2 px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full';
                                        unreadBadge.textContent = '1 unread';
                                        headerText.appendChild(unreadBadge);
                                    }
                                }
                                
                                this.disabled = false;
                            }
                        } else {
                            // Show error and revert button state
                            alert(data.message || 'An error occurred');
                            this.textContent = originalText;
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        this.textContent = originalText;
                        this.disabled = false;
                    });
                });
            });
        });
    </script>
</body>
</html>