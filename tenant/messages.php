<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

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

// Get landlord information for new message
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.first_name, u.last_name
    FROM users u
    JOIN properties p ON u.user_id = p.landlord_id
    JOIN leases l ON p.property_id = l.property_id
    WHERE l.tenant_id = :tenantId
    AND (l.status = 'active' OR l.end_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH))
");
$stmt->execute(['tenantId' => $userId]);
$landlords = $stmt->fetchAll();

// Check for success or error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Tenant Portal</title>
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
                <h2 class="text-2xl font-bold text-gray-800">Messages</h2>
                <p class="text-gray-600">Communicate with your landlord</p>
            </div>
            <button onclick="openNewMessageModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-paper-plane mr-2"></i>New Message
            </button>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Messages List -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <?php if (empty($threads)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No messages yet.</p>
                    <p class="text-sm text-gray-500 mt-1">Start a conversation with your landlord.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Landlord</th>
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
                                                <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($thread['first_name'] . '+' . $thread['last_name']); ?>&background=random" alt="Landlord">
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

    <!-- New Message Modal -->
    <div id="newMessageModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">New Message</h3>
                <button onclick="closeNewMessageModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="send_message.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <select name="recipient_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="">Select Recipient</option>
                        <?php foreach ($landlords as $landlord): ?>
                            <option value="<?php echo $landlord['user_id']; ?>">
                                <?php echo htmlspecialchars($landlord['first_name'] . ' ' . $landlord['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="Enter message subject">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea name="message" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary" rows="4" placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeNewMessageModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('hidden');
            document.getElementById('newMessageModal').classList.add('flex');
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('hidden');
            document.getElementById('newMessageModal').classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('newMessageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewMessageModal();
            }
        });

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