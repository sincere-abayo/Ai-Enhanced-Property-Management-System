<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();
// Require tenant role
requireRole('tenant');

// Get user ID
$userId = $_SESSION['user_id'];

// Check if notification ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid notification ID";
    header("Location: notifications.php");
    exit;
}

$notificationId = (int)$_GET['id'];

// Get notification details
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE notification_id = :notificationId AND user_id = :userId
");

$stmt->execute([
    'notificationId' => $notificationId,
    'userId' => $userId
]);

$notification = $stmt->fetch();

// Check if notification exists and belongs to the user
if (!$notification) {
    $_SESSION['error'] = "Notification not found or you don't have permission to view it";
    header("Location: notifications.php");
    exit;
}

// Mark notification as read if it's not already
if (!$notification['is_read']) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = :notificationId
    ");
    
    $stmt->execute(['notificationId' => $notificationId]);
    
    // Update the notification object to reflect the change
    $notification['is_read'] = 1;
}

// Get related content based on notification type
$relatedContent = null;

if ($notification['type'] === 'payment') {
    // Try to get payment details if this is a payment notification
    $stmt = $pdo->prepare("
        SELECT p.*, l.property_id, pr.property_name
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE p.payment_id = :paymentId AND l.tenant_id = :userId
        LIMIT 1
    ");
    
    // Extract payment ID from notification message if possible
    preg_match('/payment_id=(\d+)/', $notification['message'], $matches);
    $paymentId = isset($matches[1]) ? (int)$matches[1] : 0;
    
    if ($paymentId) {
        $stmt->execute([
            'paymentId' => $paymentId,
            'userId' => $userId
        ]);
        
        $relatedContent = $stmt->fetch();
    }
} elseif ($notification['type'] === 'maintenance') {
    // Try to get maintenance request details
    $stmt = $pdo->prepare("
        SELECT m.*, pr.property_name
        FROM maintenance_requests m
        JOIN properties pr ON m.property_id = pr.property_id
        WHERE m.request_id = :requestId AND m.tenant_id = :userId
        LIMIT 1
    ");
    
    // Extract request ID from notification message if possible
    preg_match('/request_id=(\d+)/', $notification['message'], $matches);
    $requestId = isset($matches[1]) ? (int)$matches[1] : 0;
    
    if ($requestId) {
        $stmt->execute([
            'requestId' => $requestId,
            'userId' => $userId
        ]);
        
        $relatedContent = $stmt->fetch();
    }
} elseif ($notification['type'] === 'lease') {
    // Try to get lease details
    $stmt = $pdo->prepare("
        SELECT l.*, pr.property_name
        FROM leases l
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE l.lease_id = :leaseId AND l.tenant_id = :userId
        LIMIT 1
    ");
    
    // Extract lease ID from notification message if possible
    preg_match('/lease_id=(\d+)/', $notification['message'], $matches);
    $leaseId = isset($matches[1]) ? (int)$matches[1] : 0;
    
    if ($leaseId) {
        $stmt->execute([
            'leaseId' => $leaseId,
            'userId' => $userId
        ]);
        
        $relatedContent = $stmt->fetch();
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Notification - Property Management System</title>
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
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="notifications.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">View Notification</h2>
                <p class="text-gray-600">Viewing notification details</p>
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

        <!-- Notification Details -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0 pt-1">
                        <?php if ($notification['type'] === 'payment'): ?>
                            <div class="p-3 rounded-full bg-green-100">
                                <i class="fas fa-money-bill-wave text-green-500 text-xl"></i>
                            </div>
                        <?php elseif ($notification['type'] === 'maintenance'): ?>
                            <div class="p-3 rounded-full bg-yellow-100">
                                <i class="fas fa-tools text-yellow-500 text-xl"></i>
                            </div>
                        <?php elseif ($notification['type'] === 'lease'): ?>
                            <div class="p-3 rounded-full bg-blue-100">
                                <i class="fas fa-file-contract text-blue-500 text-xl"></i>
                            </div>
                        <?php else: ?>
                            <div class="p-3 rounded-full bg-gray-100">
                                <i class="fas fa-bell text-gray-500 text-xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ml-5 flex-1">
                        <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <div class="flex items-center mt-1 mb-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full 
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
                            <span class="ml-3 text-sm text-gray-500">
                                <?php echo date('F j, Y \a\t g:i a', strtotime($notification['created_at'])); ?>
                                (<?php echo timeAgo($notification['created_at']); ?>)
                            </span>
                        </div>
                        <div class="text-gray-700 mb-4">
                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex mt-6">
                            <?php if ($notification['is_read']): ?>
                                <a href="handle_notification.php?id=<?php echo $notification['notification_id']; ?>&action=unread" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 mr-3">
                                    <i class="fas fa-envelope mr-2"></i>Mark as Unread
                                </a>
                            <?php else: ?>
                                <a href="handle_notification.php?id=<?php echo $notification['notification_id']; ?>&action=read" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 mr-3">
                                    <i class="fas fa-check mr-2"></i>Mark as Read
                                </a>
                            <?php endif; ?>
                            <a href="handle_notification.php?id=<?php echo $notification['notification_id']; ?>&action=delete" class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this notification?')">
                                <i class="fas fa-trash-alt mr-2"></i>Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Content -->
        <?php if ($relatedContent): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h3 class="text-lg font-semibold">Related Information</h3>
                </div>
                
                <?php if ($notification['type'] === 'payment'): ?>
                    <!-- Payment Details -->
                    <div class="p-6">
                        <h4 class="text-md font-medium mb-4">Payment Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Amount</p>
                                <p class="text-lg font-semibold"><?php echo formatCurrency($relatedContent['amount']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Date</p>
                                <p class="text-lg"><?php echo date('F j, Y', strtotime($relatedContent['payment_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Property</p>
                                <p class="text-lg"><?php echo htmlspecialchars($relatedContent['property_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Payment Method</p>
                                <p class="text-lg"><?php echo ucfirst(str_replace('_', ' ', $relatedContent['payment_method'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Payment Type</p>
                                <p class="text-lg"><?php echo ucfirst(str_replace('_', ' ', $relatedContent['payment_type'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($relatedContent['notes'])): ?>
                            <div class="mt-4">
                                <p class="text-sm text-gray-500">Notes</p>
                                <p class="text-lg"><?php echo nl2br(htmlspecialchars($relatedContent['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-6">
                            <a href="payment_details.php?id=<?php echo $relatedContent['payment_id']; ?>" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-eye mr-2"></i>View Full Payment Details
                            </a>
                        </div>
                    </div>
                <?php elseif ($notification['type'] === 'maintenance'): ?>
                    <!-- Maintenance Request Details -->
                    <div class="p-6">
                        <h4 class="text-md font-medium mb-4">Maintenance Request Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Title</p>
                                <p class="text-lg font-semibold"><?php echo htmlspecialchars($relatedContent['title']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="text-lg">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        switch ($relatedContent['status']) {
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'in_progress':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $relatedContent['status'])); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Property</p>
                                <p class="text-lg"><?php echo htmlspecialchars($relatedContent['property_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Priority</p>
                                <p class="text-lg">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                                                              switch ($relatedContent['priority']) {
                                            case 'low':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'medium':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'high':
                                                echo 'bg-orange-100 text-orange-800';
                                                break;
                                            case 'emergency':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>">
                                        <?php echo ucfirst($relatedContent['priority']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Date Reported</p>
                                <p class="text-lg"><?php echo date('F j, Y', strtotime($relatedContent['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <p class="text-sm text-gray-500">Description</p>
                            <p class="text-lg"><?php echo nl2br(htmlspecialchars($relatedContent['description'])); ?></p>
                        </div>
                        
                        <div class="mt-6">
                            <a href="maintenance_details.php?id=<?php echo $relatedContent['request_id']; ?>" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-eye mr-2"></i>View Full Maintenance Details
                            </a>
                        </div>
                    </div>
                <?php elseif ($notification['type'] === 'lease'): ?>
                    <!-- Lease Details -->
                    <div class="p-6">
                        <h4 class="text-md font-medium mb-4">Lease Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Property</p>
                                <p class="text-lg"><?php echo htmlspecialchars($relatedContent['property_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Start Date</p>
                                <p class="text-lg"><?php echo date('F j, Y', strtotime($relatedContent['start_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">End Date</p>
                                <p class="text-lg"><?php echo date('F j, Y', strtotime($relatedContent['end_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Monthly Rent</p>
                                <p class="text-lg font-semibold"><?php echo formatCurrency($relatedContent['monthly_rent']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="text-lg">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        switch ($relatedContent['status']) {
                                            case 'active':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'expired':
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                            case 'terminated':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>">
                                        <?php echo ucfirst($relatedContent['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <a href="lease_details.php?id=<?php echo $relatedContent['lease_id']; ?>" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-eye mr-2"></i>View Full Lease Details
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>