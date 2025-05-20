<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$lastName = $_SESSION['last_name'];

// Get tenant's active lease
$stmt = $pdo->prepare("
    SELECT l.*, p.property_name, p.address, p.city, p.state, p.zip_code, p.image_path, u.unit_number
    FROM leases l
    JOIN properties p ON l.property_id = p.property_id
    LEFT JOIN units u ON l.unit_id = u.unit_id
    WHERE l.tenant_id = ? AND l.status = 'active'
    ORDER BY l.end_date DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$lease = $stmt->fetch();

// Get upcoming payment
$nextPayment = null;
$daysUntilDue = null;
if ($lease) {
    // Calculate next payment date based on payment_due_day
    $today = new DateTime();
    $currentMonth = $today->format('m');
    $currentYear = $today->format('Y');
    
    // Create a date for this month's due date
    $dueDate = new DateTime("$currentYear-$currentMonth-{$lease['payment_due_day']}");
    
    // If today is past the due date, move to next month
    if ($today > $dueDate) {
        $dueDate->modify('+1 month');
    }
    
    $nextPayment = [
        'amount' => $lease['monthly_rent'],
        'due_date' => $dueDate->format('Y-m-d'),
        'formatted_date' => $dueDate->format('M j, Y')
    ];
    
    // Calculate days until due
    $interval = $today->diff($dueDate);
    $daysUntilDue = $interval->days;
}

// Get recent maintenance requests
$stmt = $pdo->prepare("
    SELECT mr.*, p.property_name,
        CASE 
            WHEN mr.status = 'pending' THEN 'yellow'
            WHEN mr.status = 'in_progress' THEN 'blue'
            WHEN mr.status = 'completed' THEN 'green'
            WHEN mr.status = 'cancelled' THEN 'red'
            ELSE 'gray'
        END as status_color
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.tenant_id = ?
    ORDER BY mr.created_at DESC
    LIMIT 3
");
$stmt->execute([$userId]);
$maintenanceRequests = $stmt->fetchAll();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, l.property_id, pr.property_name
    FROM payments p
    JOIN leases l ON p.lease_id = l.lease_id
    JOIN properties pr ON l.property_id = pr.property_id
    WHERE l.tenant_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 3
");
$stmt->execute([$userId]);
$recentPayments = $stmt->fetchAll();

// Get unread notifications count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$userId]);
$unreadNotificationsCount = $stmt->fetch()['count'];

// Format currency function
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</h2>
                <p class="text-gray-600">Here's your property overview</p>
            </div>
            <div class="flex space-x-4">
              <div>
                <div class="relative ml-3">
                    <button id="notificationButton" class="relative p-1 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($unreadNotificationsCount > 0): ?>
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                            <?php echo $unreadNotificationsCount; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Dropdown menu -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg py-1 z-10">
                        <div class="px-4 py-2 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                                <?php if ($unreadNotificationsCount > 0): ?>
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

        <?php if (!$lease): ?>
            <!-- No Active Lease Message -->
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm">You don't have any active leases. Please contact your property manager for assistance.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
     <!-- Property Info Card -->
<div class="bg-white rounded-xl shadow-md p-6 mb-8">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <!-- Property Image -->
            <div class="mr-4 w-24 h-24 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                <?php if (!empty($lease['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars('../' . $lease['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($lease['property_name']); ?>"
                         class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-gray-200">
                        <i class="fas fa-home text-gray-400 text-3xl"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Property Details -->
            <div>
                <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($lease['property_name']); ?></h3>
                <p class="text-gray-600">
                    <?php 
                    echo htmlspecialchars(
                        ($lease['unit_number'] ? "Unit " . $lease['unit_number'] . ", " : "") . 
                        $lease['address'] . ", " . 
                        $lease['city'] . ", " . 
                        $lease['state'] . " " . 
                        $lease['zip_code']
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Lease Period</p>
            <p class="font-semibold">
                <?php 
                echo date('M j, Y', strtotime($lease['start_date'])) . ' - ' . 
                     date('M j, Y', strtotime($lease['end_date'])); 
                ?>
            </p>
        </div>
    </div>
</div>
<?php if ($lease): ?>
    <!-- Property Gallery -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold mb-4">Property Gallery</h3>
        
        <?php
        // Get all images for this property
        $stmt = $pdo->prepare("
            SELECT * FROM property_images 
            WHERE property_id = ? 
            ORDER BY is_primary DESC, upload_date DESC
        ");
        $stmt->execute([$lease['property_id']]);
        $propertyImages = $stmt->fetchAll();
        ?>
        
        <?php if (empty($propertyImages)): ?>
            <p class="text-gray-500 text-center py-4">No galley images available for this property.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($propertyImages as $image): ?>
                    <div class="relative aspect-square rounded-lg overflow-hidden bg-gray-200">
                        <img src="<?php echo htmlspecialchars('../uploads/' . $image['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($image['caption'] ?? $lease['property_name']); ?>"
                             class="w-full h-full object-cover hover:opacity-90 transition-opacity cursor-pointer"
                             onclick="openImageModal('<?php echo htmlspecialchars('../uploads/' . $image['image_path']); ?>', '<?php echo htmlspecialchars($image['caption'] ?? $lease['property_name']); ?>')">
                        <?php if ($image['caption']): ?>
                            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-1 text-xs">
                                <?php echo htmlspecialchars($image['caption']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="max-w-4xl w-full mx-4">
            <div class="relative">
                <img id="modalImage" src="" alt="" class="w-full max-h-[80vh] object-contain">
                <p id="modalCaption" class="text-white text-center mt-2"></p>
                <button onclick="closeImageModal()" class="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full w-8 h-8 flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>


            <!-- Payment Status -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Rent Payment Status</h3>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-500">Next Payment Due</p>
                        <p class="text-xl font-semibold"><?php echo formatCurrency($nextPayment['amount']); ?></p>
                        <p class="text-sm <?php echo $daysUntilDue <= 5 ? 'text-red-500' : 'text-gray-500'; ?>">
                            Due <?php echo $daysUntilDue > 0 ? "in $daysUntilDue days" : "today"; ?>
                            (<?php echo $nextPayment['formatted_date']; ?>)
                        </p>
                    </div>
                    <div class="flex space-x-4">
    <a href="payments.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
        <i class="fas fa-file-invoice mr-2"></i>View Payment History
    </a>
</div>

                </div>
            </div>
        <?php endif; ?>

              <!-- Latest Maintenance Requests -->
              <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Recent Maintenance Requests</h3>
                <a href="maintenance.php" class="text-primary text-sm hover:text-blue-700">View All</a>
            </div>
            
            <?php
            // Get recent maintenance requests for this tenant
            $stmt = $pdo->prepare("
                SELECT mr.*, p.property_name, 
                       CASE 
                           WHEN mr.status = 'pending' THEN 'bg-yellow-100 text-yellow-800'
                           WHEN mr.status = 'in_progress' THEN 'bg-blue-100 text-blue-800'
                           WHEN mr.status = 'completed' THEN 'bg-green-100 text-green-800'
                           WHEN mr.status = 'cancelled' THEN 'bg-red-100 text-red-800'
                           ELSE 'bg-gray-100 text-gray-800'
                       END as status_class
                FROM maintenance_requests mr
                JOIN properties p ON mr.property_id = p.property_id
                WHERE mr.tenant_id = ?
                ORDER BY mr.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$userId]);
            $maintenanceRequests = $stmt->fetchAll();
            ?>
            
            <?php if (empty($maintenanceRequests)): ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No maintenance requests found.</p>
                    <a href="maintenance.php" class="mt-2 inline-block text-primary hover:text-blue-700">
                        <i class="fas fa-plus-circle mr-1"></i> Submit a new request
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($maintenanceRequests as $request): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 rounded-full 
                                    <?php echo $request['priority'] == 'emergency' ? 'bg-red-100' : 
                                        ($request['priority'] == 'high' ? 'bg-orange-100' : 
                                        ($request['priority'] == 'medium' ? 'bg-yellow-100' : 'bg-blue-100')); ?>">
                                    <i class="fas fa-tools 
                                        <?php echo $request['priority'] == 'emergency' ? 'text-red-500' : 
                                            ($request['priority'] == 'high' ? 'text-orange-500' : 
                                            ($request['priority'] == 'medium' ? 'text-yellow-500' : 'text-blue-500')); ?>"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($request['title']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($request['property_name']); ?> • 
                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $request['status_class']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-center">
                    <a href="maintenance.php" class="text-primary hover:text-blue-700">
                        <i class="fas fa-plus-circle mr-1"></i> Submit a new request
                    </a>
                </div>
            <?php endif; ?>
        </div>


        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
            <div class="space-y-4">
                <?php if (empty($recentPayments) && empty($maintenanceRequests)): ?>
                    <p class="text-gray-500 text-center py-4">No recent activity to display.</p>
                <?php else: ?>
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="p-2 rounded-full bg-green-100">
                                <i class="fas fa-money-bill-wave text-green-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">
                                    Payment of <?php echo formatCurrency($payment['amount']); ?> recorded
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($maintenanceRequests as $request): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="p-2 rounded-full bg-<?php echo $request['status_color'] ?? 'pending' ?>-100">
                                <i class="fas fa-tools text-<?php echo $request['status_color']?? 'pending' ?>-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">
                                    Maintenance request: <?php echo htmlspecialchars($request['title']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Status: <?php echo ucfirst($request['status']); ?> - 
                                    <?php echo date('F j, Y', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    // Notification dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
        if (notificationButton && notificationDropdown) {
            // Toggle dropdown when clicking the notification button
            notificationButton.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target) && e.target !== notificationButton) {
                    notificationDropdown.classList.add('hidden');
                }
            });
            
            // Mark all as read functionality
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Send AJAX request to mark all as read
                    fetch('mark_all_read.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI to reflect all notifications are read
                            document.querySelectorAll('.bg-blue-50').forEach(el => {
                                el.classList.remove('bg-blue-50');
                            });
                            
                            // Remove notification count badge
                            const badge = document.querySelector('.absolute.top-0.right-0.inline-flex');
                            if (badge) {
                                badge.remove();
                            }
                            
                            // Hide the "Mark all as read" button
                            markAllReadBtn.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            }
        }
    });

    // Image modal functionality
    function openImageModal(imageSrc, caption) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('modalCaption').textContent = caption;
        document.getElementById('imageModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal() {
        document.getElementById('imageModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside the image
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeImageModal();
        }
    });
    
    // Close modal with escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('imageModal').classList.contains('hidden')) {
            closeImageModal();
        }
    });
</script>

</body>
</html>
