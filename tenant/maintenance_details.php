<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Check if maintenance request ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid maintenance request ID";
    header("Location: maintenance.php");
    exit;
}

$requestId = (int)$_GET['id'];

// Get maintenance request details
$stmt = $pdo->prepare("
    SELECT mr.*, 
           p.property_name, p.address, p.city, p.state, p.zip_code,
           u.unit_number,
           l.landlord_id
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.property_id
    LEFT JOIN units u ON mr.unit_id = u.unit_id
    JOIN (SELECT property_id, landlord_id FROM properties) l ON mr.property_id = l.property_id
    WHERE mr.request_id = ? AND mr.tenant_id = ?
");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

// If request not found or doesn't belong to this tenant, redirect
if (!$request) {
    $_SESSION['error'] = "Maintenance request not found or you don't have permission to view it";
    header("Location: maintenance.php");
    exit;
}

// Get maintenance tasks for this request
$stmt = $pdo->prepare("
    SELECT mt.*, 
           u.first_name, u.last_name
    FROM maintenance_tasks mt
    LEFT JOIN users u ON mt.assigned_to = u.user_id
    WHERE mt.request_id = ?
    ORDER BY mt.created_at DESC
");
$stmt->execute([$requestId]);
$tasks = $stmt->fetchAll();

// Get comments/updates for this request using the messages table
$stmt = $pdo->prepare("
    SELECT m.*, 
           u.first_name, u.last_name, u.role
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.subject = CONCAT('Maintenance #', ?)
    AND (m.sender_id = ? OR m.recipient_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->execute([$requestId, $userId, $userId]);
$comments = $stmt->fetchAll();

// Process comment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $commentText = trim($_POST['comment']);
    
    if (!empty($commentText)) {
        try {
            // Get landlord ID
            $landlordId = $request['landlord_id'];
            
            // Insert comment as a message
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    sender_id, recipient_id, subject, message, message_type, is_read, created_at
                ) VALUES (
                    :senderId, :recipientId, :subject, :message, 'portal', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'senderId' => $userId,
                'recipientId' => $landlordId,
                'subject' => 'Maintenance #' . $requestId,
                'message' => $commentText
            ]);
            
            // Create notification for landlord
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'maintenance', 0, NOW()
                )
            ");
            
            if ($landlordId) {
                $stmt->execute([
                    'userId' => $landlordId,
                    'title' => 'New comment on maintenance request',
                    'message' => 'A tenant has added a comment to maintenance request #' . $requestId
                ]);
            }
            
            // Redirect to refresh the page and avoid form resubmission
            header("Location: maintenance_details.php?id=" . $requestId);
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Comment cannot be empty";
    }
}

// Format priority class
function getPriorityClass($priority) {
    switch ($priority) {
        case 'emergency':
            return 'bg-red-100 text-red-800';
        case 'high':
            return 'bg-orange-100 text-orange-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Format status class
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'assigned':
        case 'in_progress':
            return 'bg-blue-100 text-blue-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Check for error messages
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear session messages
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Request Details - Tenant Portal</title>
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
            <a href="maintenance.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Maintenance Request #<?php echo $requestId; ?></h2>
                <p class="text-gray-600">
                    <?php echo htmlspecialchars($request['property_name']); ?>
                    <?php if ($request['unit_number']): ?>
                        - Unit <?php echo htmlspecialchars($request['unit_number']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Request Details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Main Details -->
            <div class="md:col-span-2 bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-start mb-6">
                    <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($request['title']); ?></h3>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusClass($request['status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                    </span>
                </div>
                
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Description</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Priority</p>
                        <p class="text-sm font-medium">
                            <span class="inline-block px-2 py-1 rounded-full <?php echo getPriorityClass($request['priority']); ?>">
                                <?php echo ucfirst($request['priority']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Submitted On</p>
                        <p class="text-sm font-medium"><?php echo date('F j, Y', strtotime($request['created_at'])); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($tasks)): ?>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Maintenance Tasks</h4>
                        <div class="space-y-3">
                            <?php foreach ($tasks as $task): ?>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-sm font-medium"><?php echo htmlspecialchars($task['description']); ?></p>
                                            <?php if ($task['assigned_to']): ?>
                                                <p class="text-xs text-gray-500">
                                                    Assigned to: <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusClass($task['status']); ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Comments Section -->
                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Updates & Comments</h4>
                    
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-500">No comments yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 mb-4">
                            <?php foreach ($comments as $comment): ?>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-500">
                                                    <?php echo strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium">
                                                    <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                                    <span class="text-xs font-normal text-gray-500">
                                                        (<?php echo ucfirst($comment['role']); ?>)
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-sm whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Add Comment Form -->
                    <form method="POST" action="maintenance_details.php?id=<?php echo $requestId; ?>" class="mt-4">
                        <div>
                            <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Add a Comment</label>
                            <textarea 
                                id="comment" 
                                name="comment" 
                                rows="3" 
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                placeholder="Type your comment here..."
                                required
                            ></textarea>
                        </div>
                        <div class="flex justify-end mt-2">
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                            >
                                Submit Comment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar Details -->
            <div class="bg-white rounded-xl shadow-md p-6 h-fit">
                <h3 class="text-lg font-semibold mb-4">Property Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Address</p>
                        <p class="text-sm font-medium">
                        <?php echo htmlspecialchars($request['address']); ?><br>
                            <?php echo htmlspecialchars($request['city'] . ', ' . $request['state'] . ' ' . $request['zip_code']); ?>
                        </p>
                    </div>
                    
                    <?php if ($request['unit_number']): ?>
                        <div>
                            <p class="text-sm text-gray-500">Unit</p>
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($request['unit_number']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <p class="text-sm text-gray-500">Request Date</p>
                        <p class="text-sm font-medium"><?php echo date('F j, Y', strtotime($request['created_at'])); ?></p>
                    </div>
                    
                    <?php if ($request['completed_at']): ?>
                        <div>
                            <p class="text-sm text-gray-500">Completed Date</p>
                            <p class="text-sm font-medium"><?php echo date('F j, Y', strtotime($request['completed_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="text-sm font-medium">
                            <span class="inline-block px-2 py-1 rounded-full <?php echo getStatusClass($request['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </p>
                    </div>
                    
                    <?php if ($request['estimated_cost']): ?>
                        <div>
                            <p class="text-sm text-gray-500">Estimated Cost</p>
                            <p class="text-sm font-medium">$<?php echo number_format($request['estimated_cost'], 2); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Status Timeline -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Request Timeline</h4>
                    <div class="space-y-4">
                        <div class="flex">
                            <div class="flex flex-col items-center mr-4">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <div class="w-0.5 h-full bg-gray-200"></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium">Request Submitted</p>
                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($request['status'] == 'assigned' || $request['status'] == 'in_progress' || $request['status'] == 'completed'): ?>
                            <div class="flex">
                                <div class="flex flex-col items-center mr-4">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                    <div class="w-0.5 h-full bg-gray-200"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Maintenance Assigned</p>
                                    <p class="text-xs text-gray-500">
                                        <?php 
                                        // Find the first task assignment date
                                        $assignmentDate = null;
                                        foreach ($tasks as $task) {
                                            if ($task['assigned_to']) {
                                                $assignmentDate = $task['created_at'];
                                                break;
                                            }
                                        }
                                        echo $assignmentDate ? date('M j, Y', strtotime($assignmentDate)) : 'Date not available';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] == 'in_progress' || $request['status'] == 'completed'): ?>
                            <div class="flex">
                                <div class="flex flex-col items-center mr-4">
                                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                    <div class="w-0.5 h-full bg-gray-200"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Work In Progress</p>
                                    <p class="text-xs text-gray-500">
                                        <?php 
                                        // Find the first in_progress task date
                                        $inProgressDate = null;
                                        foreach ($tasks as $task) {
                                            if ($task['status'] == 'in_progress') {
                                                $inProgressDate = $task['updated_at'];
                                                break;
                                            }
                                        }
                                        echo $inProgressDate ? date('M j, Y', strtotime($inProgressDate)) : 'Date not available';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] == 'completed'): ?>
                            <div class="flex">
                                <div class="flex flex-col items-center mr-4">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Request Completed</p>
                                    <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($request['completed_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] == 'cancelled'): ?>
                            <div class="flex">
                                <div class="flex flex-col items-center mr-4">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Request Cancelled</p>
                                    <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($request['updated_at'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions -->
                <?php if ($request['status'] == 'pending' || $request['status'] == 'assigned'): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Actions</h4>
                        <a href="cancel_maintenance.php?id=<?php echo $requestId; ?>" 
                           onclick="return confirm('Are you sure you want to cancel this maintenance request?')"
                           class="block w-full text-center px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
                            Cancel Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
