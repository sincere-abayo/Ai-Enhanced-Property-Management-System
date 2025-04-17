<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Check if tenant ID is provided
if (!isset($_GET['tenant_id']) || !is_numeric($_GET['tenant_id'])) {
    $_SESSION['error'] = "Invalid tenant ID";
    header("Location: tenants.php");
    exit;
}

$tenantId = (int)$_GET['tenant_id'];

// Get tenant details
function getTenantDetails($tenantId, $landlordId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        JOIN leases l ON u.user_id = l.tenant_id
        JOIN properties p ON l.property_id = p.property_id
        WHERE u.user_id = :tenantId AND p.landlord_id = :landlordId AND u.role = 'tenant'
        LIMIT 1
    ");
    $stmt->execute([
        'tenantId' => $tenantId,
        'landlordId' => $landlordId
    ]);
    
    return $stmt->fetch();
}

// Get tenant details
$tenant = getTenantDetails($tenantId, $userId);

// Redirect if tenant not found or doesn't belong to this landlord
if (!$tenant) {
    $_SESSION['error'] = "Tenant not found or you don't have permission to message this tenant";
    header("Location: tenants.php");
    exit;
}

// Initialize errors array
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $messageType = $_POST['message_type'];
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
  // If no errors, send the message
if (empty($errors)) {
    try {
        // Record the message in the database
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, recipient_id, subject, message, message_type, created_at
            ) VALUES (
                :senderId, :recipientId, :subject, :message, 'portal', NOW()
            )
        ");
        
        $stmt->execute([
            'senderId' => $userId,
            'recipientId' => $tenantId,
            'subject' => $subject,
            'message' => $message
        ]);
        
        // Create a notification for the tenant
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, message, type, is_read, created_at
            ) VALUES (
                :userId, :title, :message, 'general', 0, NOW()
            )
        ");
        
        $stmt->execute([
            'userId' => $tenantId,
            'title' => 'New message from landlord',
            'message' => 'You have received a new message: ' . $subject
        ]);
        
        $success = true;
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - Property Management System</title>
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
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="tenant_details.php?id=<?php echo $tenantId; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Send Message</h2>
                <p class="text-gray-600">
                    To: <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                    (<?php echo htmlspecialchars($tenant['email']); ?>)
                </p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p>Message sent successfully!</p>
                <p class="mt-2">
                    <a href="tenant_details.php?id=<?php echo $tenantId; ?>" class="text-primary hover:underline">
                        Return to tenant details
                    </a>
                </p>
            </div>
        <?php endif; ?>

        <!-- Message Form -->
        <?php if (!$success): ?>
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="POST" action="send_message.php?tenant_id=<?php echo $tenantId; ?>">
                    <div class="space-y-6">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject" 
                                value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" 
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                required
                            >
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea 
                                id="message" 
                                name="message" 
                                rows="6" 
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
                                required
                            ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Send via</label>
    <div class="space-y-2">
        <div class="flex items-center">
            <input 
                type="radio" 
                id="message_type_portal" 
                name="message_type" 
                value="portal" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                checked
            >
            <label for="message_type_portal" class="ml-2 block text-sm text-gray-700">
                Portal only (tenant will see it when they log in)
            </label>
        </div>
    </div>
</div>

                    </div>

                    <div class="flex justify-end space-x-4 mt-6">
                        <a 
                            href="tenant_details.php?id=<?php echo $tenantId; ?>" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cancel
                        </a>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700"
                        >
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Message History -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Message History</h3>
            
            <div class="text-center text-gray-500 py-6">
                <p>No previous messages with this tenant</p>
            </div>
        </div>
    </div>
</body>
</html>
