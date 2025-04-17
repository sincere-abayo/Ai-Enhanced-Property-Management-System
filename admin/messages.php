<?php
// add error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Get all message threads for this user
$stmt = $pdo->prepare("
    SELECT 
        mt.thread_id,
        mt.subject,
        mt.updated_at,
        m.message,
        m.created_at AS last_message_time,
        u.first_name,
        u.last_name,
        u.user_id,
        tp.is_read
    FROM message_threads mt
    JOIN thread_participants tp ON mt.thread_id = tp.thread_id
    JOIN messages m ON m.thread_id = mt.thread_id
    JOIN users u ON u.user_id = (
        SELECT tp2.user_id 
        FROM thread_participants tp2 
        WHERE tp2.thread_id = mt.thread_id AND tp2.user_id != :userId
        LIMIT 1
    )
    WHERE tp.user_id = :userId
    AND m.created_at = (
        SELECT MAX(created_at) 
        FROM messages 
        WHERE thread_id = mt.thread_id
    )
    ORDER BY mt.updated_at DESC
");

$stmt->execute(['userId' => $userId]);
$threads = $stmt->fetchAll();

// Count unread messages
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count
    FROM thread_participants
    WHERE user_id = :userId AND is_read = 0
");
$stmt->execute(['userId' => $userId]);
$unreadCount = $stmt->fetch()['unread_count'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Property Management System</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Messages</h2>
                <p class="text-gray-600">Communicate with your tenants</p>
            </div>
            <a href="send_message.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-paper-plane mr-2"></i>New Message
            </a>
        </div>

        <!-- Messages List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($threads)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No messages yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Start a conversation with a tenant.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($threads as $thread): ?>
                                <tr class="<?php echo $thread['is_read'] ? '' : 'bg-blue-50'; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($thread['first_name'] . '+' . $thread['last_name']); ?>&background=random" alt="Tenant">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($thread['subject']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($thread['message']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo timeAgo($thread['last_message_time']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="message_thread.php?id=<?php echo $thread['thread_id']; ?>" class="text-primary hover:text-blue-700">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mark message as read when clicked
        document.querySelectorAll('a[href^="message_thread.php"]').forEach(link => {
            link.addEventListener('click', function() {
                const row = this.closest('tr');
                if (row.classList.contains('bg-blue-50')) {
                    row.classList.remove('bg-blue-50');
                }
            });
        });
    </script>
</body>
</html>
