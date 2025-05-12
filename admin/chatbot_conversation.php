<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role
requireRole('landlord');

// Get conversation ID from URL
$conversationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$conversationId) {
    // Redirect to conversations list if no ID provided
    header('Location: chatbot_management.php');
    exit;
}

// Fetch conversation details
$stmt = $pdo->prepare("
    SELECT c.*, u.first_name, u.last_name, u.email
    FROM chatbot_conversations c
    JOIN users u ON c.tenant_id = u.user_id
    WHERE c.conversation_id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    // Conversation not found
    $_SESSION['error'] = "Conversation not found.";
    header('Location: chatbot_management.php');
    exit;
}

// Fetch messages for this conversation
$stmt = $pdo->prepare("
    SELECT m.*, a.action_type, a.action_details, a.success
    FROM chatbot_messages m
    LEFT JOIN chatbot_actions a ON m.message_id = a.message_id
    WHERE m.conversation_id = ?
    ORDER BY m.timestamp ASC
");
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$pageTitle = "Chatbot Conversation";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Property Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1a56db',
                        secondary: '#7e3af2',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main content -->
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Conversation Details</h1>
                <p class="text-gray-600">
                    Conversation with 
                    <span class="font-medium"><?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?></span>
                    (<?php echo htmlspecialchars($conversation['email']); ?>)
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="chatbot_management.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Management
                </a>
                <?php if (!$conversation['end_time']): ?>
                <button class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors">
                    <i class="fas fa-times-circle mr-2"></i>End Conversation
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Conversation metadata -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Started</h3>
                        <p class="mt-1 text-lg font-semibold text-gray-800">
                            <?php echo date('M j, Y g:i A', strtotime($conversation['start_time'])); ?>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Status</h3>
                        <p class="mt-1">
                            <?php if ($conversation['end_time']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Ended on <?php echo date('M j, Y g:i A', strtotime($conversation['end_time'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Satisfaction Rating</h3>
                        <p class="mt-1 text-lg font-semibold text-gray-800">
                            <?php if ($conversation['satisfaction_rating']): ?>
                                <?php 
                                $rating = intval($conversation['satisfaction_rating']);
                                $ratingClass = $rating >= 4 ? 'text-green-500' : ($rating >= 3 ? 'text-yellow-500' : 'text-red-500');
                                ?>
                                <span class="<?php echo $ratingClass; ?>">
                                    <?php echo $rating; ?>/5
                                    <?php for ($i = 0; $i < $rating; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                    <?php for ($i = $rating; $i < 5; $i++): ?>
                                        <i class="far fa-star"></i>
                                    <?php endfor; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">Not rated</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversation messages -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-800">Conversation History</h2>
            </div>
            <div class="p-6 space-y-6">
                <?php if (empty($messages)): ?>
                    <p class="text-gray-500 text-center py-4">No messages found in this conversation.</p>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="flex <?php echo $message['is_from_bot'] ? 'justify-start' : 'justify-end'; ?>">
                            <div class="<?php echo $message['is_from_bot'] ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?> rounded-lg px-4 py-3 max-w-3xl">
                                <div class="flex items-center mb-1">
                                    <div class="h-8 w-8 rounded-full <?php echo $message['is_from_bot'] ? 'bg-blue-500' : 'bg-gray-500'; ?> flex items-center justify-center text-white">
                                        <i class="<?php echo $message['is_from_bot'] ? 'fas fa-robot' : 'fas fa-user'; ?>"></i>
                                    </div>
                                    <div class="ml-2">
                                        <p class="text-xs font-medium"><?php echo $message['is_from_bot'] ? 'Chatbot' : 'Tenant'; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($message['timestamp'])); ?></p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($message['message_text'])); ?></p>
                                </div>
                                <?php if ($message['is_from_bot'] && isset($message['action_type'])): ?>
                                    <div class="mt-2 pt-2 border-t border-gray-200">
                                        <p class="text-xs font-medium">
                                            <i class="fas fa-cog mr-1"></i>
                                            Action: <?php echo ucfirst(str_replace('_', ' ', $message['action_type'])); ?>
                                            <span class="<?php echo $message['success'] ? 'text-green-500' : 'text-red-500'; ?>">
                                                (<?php echo $message['success'] ? 'Success' : 'Failed'; ?>)
                                            </span>
                                        </p>
                                        <?php if ($message['action_details']): ?>
                                            <div class="mt-1 text-xs bg-gray-50 p-2 rounded">
                                                <?php 
                                                $details = json_decode($message['action_details'], true);
                                                foreach ($details as $key => $value): 
                                                ?>
                                                    <div><span class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span> <?php echo is_array($value) ? json_encode($value) : $value; ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Analysis and Insights -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-800">Analysis & Insights</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Topics Discussed -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Topics Discussed</h3>
                        <?php
                        $topics = [];
                        foreach ($messages as $message) {
                            if (!$message['is_from_bot'] && !empty($message['intent_detected'])) {
                                $topics[$message['intent_detected']] = ($topics[$message['intent_detected']] ?? 0) + 1;
                            }
                        }
                        ?>
                        <?php if (empty($topics)): ?>
                            <p class="text-sm text-gray-500">No topics detected</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($topics as $topic => $count): ?>
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo min(100, $count * 20); ?>%"></div>
                                        </div>
                                        <span class="ml-2 text-sm text-gray-600"><?php echo ucfirst($topic); ?> (<?php echo $count; ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions Taken -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Actions Taken</h3>
                        <?php
                        $actions = [];
                        foreach ($messages as $message) {
                            if ($message['is_from_bot'] && !empty($message['action_type'])) {
                                $actions[$message['action_type']] = ($actions[$message['action_type']] ?? 0) + 1;
                            }
                        }
                        ?>
                        <?php if (empty($actions)): ?>
                            <p class="text-sm text-gray-500">No actions taken</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($actions as $action => $count): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $action)); ?></span>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo $count; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for any interactive elements
    </script>
</body>
</html>