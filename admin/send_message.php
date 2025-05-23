<?php
// error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require landlord or admin role
requireRole('landlord');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize variables
$tenant = null;
$tenantId = null;
$allTenants = [];

// Check if tenant ID is provided
if (isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])) {
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
} else {
    // No specific tenant ID provided, get all tenants for this landlord
    function getAllTenants($landlordId) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.phone, p.property_name
            FROM users u
            JOIN leases l ON u.user_id = l.tenant_id
            JOIN properties p ON l.property_id = p.property_id
            WHERE p.landlord_id = :landlordId AND u.role = 'tenant'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute(['landlordId' => $landlordId]);
        
        return $stmt->fetchAll();
    }
    
    $allTenants = getAllTenants($userId);
    
    if (empty($allTenants)) {
        $_SESSION['error'] = "You don't have any tenants to message";
        header("Location: dashboard.php");
        exit;
    }
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
    
    // If no specific tenant was pre-selected, get the selected tenant from the form
    if (!$tenantId && isset($_POST['tenant_id'])) {
        $tenantId = (int)$_POST['tenant_id'];
        
        // Verify this tenant belongs to the landlord
        $validTenant = false;
        foreach ($allTenants as $t) {
            if ($t['user_id'] == $tenantId) {
                $validTenant = true;
                $tenant = $t;
                break;
            }
        }
        
        if (!$validTenant) {
            $errors[] = "Invalid tenant selected";
        }
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($tenantId)) {
        $errors[] = "Recipient is required";
    }
    
    // Validate message length for SMS
    if (($messageType == 'sms' || $messageType == 'both') && strlen($message) > 160) {
        $warnings[] = "SMS message exceeds 160 characters and will be truncated";
    }
    
    // If no errors, send the message
    if (empty($errors)) {
        try {
            // First, check if there's an existing thread between these users
            $stmt = $pdo->prepare("
                SELECT mt.thread_id 
                FROM message_threads mt
                JOIN thread_participants tp1 ON mt.thread_id = tp1.thread_id
                JOIN thread_participants tp2 ON mt.thread_id = tp2.thread_id
                WHERE tp1.user_id = :userId AND tp2.user_id = :tenantId
                LIMIT 1
            ");
            
            $stmt->execute([
                'userId' => $userId,
                'tenantId' => $tenantId
            ]);
            
            $existingThread = $stmt->fetch();
            $threadId = null;
            
            if ($existingThread) {
                // Use existing thread
                $threadId = $existingThread['thread_id'];
                
                // Update thread subject and updated_at
                $stmt = $pdo->prepare("
                    UPDATE message_threads 
                    SET subject = :subject, updated_at = NOW() 
                    WHERE thread_id = :threadId
                ");
                
                $stmt->execute([
                    'subject' => $subject,
                    'threadId' => $threadId
                ]);
            } else {
                // Create new thread
                $stmt = $pdo->prepare("
                    INSERT INTO message_threads (subject, created_at, updated_at)
                    VALUES (:subject, NOW(), NOW())
                ");
                
                $stmt->execute(['subject' => $subject]);
                $threadId = $pdo->lastInsertId();
                
                // Add participants
                $stmt = $pdo->prepare("
                    INSERT INTO thread_participants (thread_id, user_id, is_read)
                    VALUES (:threadId, :userId, 1), (:threadId, :tenantId, 0)
                ");
                
                $stmt->execute([
                    'threadId' => $threadId,
                    'userId' => $userId,
                    'tenantId' => $tenantId
                ]);
            }
            
            // Add message to thread
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    thread_id, sender_id, recipient_id, subject, message, message_type, created_at
                ) VALUES (
                    :threadId, :senderId, :recipientId, :subject, :message, :messageType, NOW()
                )
            ");
            
            $stmt->execute([
                'threadId' => $threadId,
                'senderId' => $userId,
                'recipientId' => $tenantId,
                'subject' => $subject,
                'message' => $message,
                'messageType' => $messageType
            ]);
            
            // Mark as unread for recipient
            $stmt = $pdo->prepare("
                UPDATE thread_participants
                SET is_read = 0
                WHERE thread_id = :threadId AND user_id = :tenantId
            ");
            
            $stmt->execute([
                'threadId' => $threadId,
                'tenantId' => $tenantId
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
            
            // Get sender information for email template
            $stmt = $pdo->prepare("
                SELECT first_name, last_name, role
                FROM users
                WHERE user_id = :userId
            ");
            
            $stmt->execute(['userId' => $userId]);
            $sender = $stmt->fetch();
            
            // Send via additional channels if selected
            $messagingErrors = [];
            
            // Include messaging helper
            require_once '../includes/messaging.php';
            
            // Send via email if selected
            if ($messageType == 'email' || $messageType == 'both') {
                if (!empty($tenant['email'])) {
                    // Generate email HTML
                    $emailHtml = getEmailTemplate($subject, nl2br($message), $sender);
                    
                    // Send email
                    $emailSent = sendEmail(
                        $tenant['email'],
                        "Property Message: $subject",
                        $emailHtml,
                        $message // Plain text alternative
                    );
                    
                    if (!$emailSent) {
                        $messagingErrors[] = "Failed to send email to " . $tenant['email'];
                    }
                } else {
                    $messagingErrors[] = "Cannot send email: tenant email address not available";
                }
            }
            
            // Send via SMS if selected
            if ($messageType == 'sms' || $messageType == 'both') {
                if (!empty($tenant['phone'])) {
                    // Prepare SMS message
                    $smsMessage = "Property Message: $subject\n\n" . substr($message, 0, 120);
                    if (strlen($message) > 120) {
                        $smsMessage .= "... (Login to portal for full message)";
                    }
                    
                    // Send SMS
                    $smsSent = sendSMS($tenant['phone'], $smsMessage);
                    
                    if (!$smsSent) {
                        $messagingErrors[] = "Failed to send SMS to " . $tenant['phone'];
                    }
                } else {
                    $messagingErrors[] = "Cannot send SMS: tenant phone number not available";
                }
            }
            
            // Record messaging status in the database
            if (!empty($messagingErrors)) {
                $stmt = $pdo->prepare("
                    INSERT INTO message_delivery_logs (
                        message_id, delivery_method, status, error_message, created_at
                    ) VALUES (
                        :messageId, :deliveryMethod, 'failed', :errorMessage, NOW()
                    )
                ");
                
                foreach ($messagingErrors as $error) {
                    $deliveryMethod = strpos($error, 'email') !== false ? 'email' : 'sms';
                    $stmt->execute([
                        'messageId' => $pdo->lastInsertId(),
                        'deliveryMethod' => $deliveryMethod,
                        'errorMessage' => $error
                    ]);
                }
                
                // Add warnings to display to the user
                $warnings = array_merge($warnings ?? [], $messagingErrors);
            }
            
            $success = true;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Error sending message: " . $e->getMessage();
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
            <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'messages.php'; ?>" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Send Message</h2>
                <?php if ($tenant): ?>
                <p class="text-gray-600">
                    To: <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                    (<?php echo htmlspecialchars($tenant['email']); ?>)
                </p>
                <?php else: ?>
                <p class="text-gray-600">Send a message to one of your tenants</p>
                <?php endif; ?>
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
                    <a href="messages.php" class="text-primary hover:underline">
                        Go to messages
                    </a>
                    <?php if ($tenant): ?>
                    or
                    <a href="tenant_details.php?id=<?php echo $tenantId; ?>" class="text-primary hover:underline">
                        Return to tenant details
                    </a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Message Form -->
        <?php if (!$success): ?>
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <div class="space-y-6">
                        <?php if (!$tenant): ?>
                        <!-- Tenant Selection (only shown when no tenant is pre-selected) -->
                        <div>
    <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-1">Recipient</label>
    <select 
        id="tenant_id" 
        name="tenant_id" 
        class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"
        required
        onchange="loadTenantDetails(this.value)"
    >
        <option value="">Select a tenant</option>
        <?php foreach ($allTenants as $t): ?>
            <option value="<?php echo $t['user_id']; ?>">
                <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?> 
                (<?php echo htmlspecialchars($t['property_name']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>
<!-- Tenant Details Container (will be populated via AJAX) -->
<div id="tenant_details_container" class="mt-3 hidden">
    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
        <h4 class="text-sm font-medium text-gray-700 mb-2">Recipient Details</h4>
        <div id="tenant_details" class="text-sm text-gray-600"></div>
    </div>
</div>

                        <?php else: ?>
                            <input type="hidden" name="tenant_id" value="<?php echo $tenant['user_id']; ?>">
                        <?php endif; ?>
                        
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
        <!-- Portal option (always available) -->
        <div class="flex items-center">
            <input 
                type="radio" 
                id="message_type_portal" 
                name="message_type" 
                value="portal" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                checked
                onchange="updateMessagePreview()"
            >
            <label for="message_type_portal" class="ml-2 block text-sm text-gray-700">
                Portal only (tenant will see it when they log in)
            </label>
        </div>
        
        <!-- Email option (initially hidden if no tenant is pre-selected) -->
        <div class="flex items-center <?php echo (!$tenant || empty($tenant['email'])) ? 'hidden' : ''; ?>" id="email_option">
            <input 
                type="radio" 
                id="message_type_email" 
                name="message_type" 
                value="email" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                onchange="updateMessagePreview()"
                <?php echo (!$tenant || empty($tenant['email'])) ? 'disabled' : ''; ?>
            >
            <label for="message_type_email" class="ml-2 block text-sm text-gray-700">
                Email <?php echo ($tenant && !empty($tenant['email'])) ? '(' . htmlspecialchars($tenant['email']) . ')' : ''; ?>
            </label>
        </div>
        
        <!-- SMS option (initially hidden if no tenant is pre-selected) -->
        <div class="flex items-center <?php echo (!$tenant || empty($tenant['phone'])) ? 'hidden' : ''; ?>" id="sms_option">
            <input 
                type="radio" 
                id="message_type_sms" 
                name="message_type" 
                value="sms" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                onchange="updateMessagePreview()"
                <?php echo (!$tenant || empty($tenant['phone'])) ? 'disabled' : ''; ?>
            >
            <label for="message_type_sms" class="ml-2 block text-sm text-gray-700">
                SMS <?php echo ($tenant && !empty($tenant['phone'])) ? '(' . htmlspecialchars($tenant['phone']) . ')' : ''; ?>
                <span class="text-xs text-gray-500">(160 chars max)</span>
            </label>
        </div>
        
        <!-- Both option (initially hidden if no tenant is pre-selected) -->
        <div class="flex items-center <?php echo (!$tenant || empty($tenant['email']) || empty($tenant['phone'])) ? 'hidden' : ''; ?>" id="both_option">
            <input 
                type="radio" 
                id="message_type_both" 
                name="message_type" 
                value="both" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300"
                onchange="updateMessagePreview()"
                <?php echo (!$tenant || empty($tenant['email']) || empty($tenant['phone'])) ? 'disabled' : ''; ?>
            >
            <label for="message_type_both" class="ml-2 block text-sm text-gray-700">
                Both email and SMS
            </label>
        </div>
        
        <!-- Message preview section -->
        <div id="message_preview" class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200 hidden">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Message Preview</h4>
            <div id="preview_content" class="text-sm text-gray-600"></div>
            <div id="character_count" class="text-xs text-gray-500 mt-2 hidden">
                Characters: <span id="char_count">0</span>/160
            </div>
        </div>
    </div>
</div>

                    </div>

                    <div class="flex justify-end space-x-4 mt-6">
                        <a 
                            href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'messages.php'; ?>" 
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
        <?php if ($tenant): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Message History</h3>
                
                <?php
                // Get previous messages with this tenant
                $stmt = $pdo->prepare("
                    SELECT m.*, u.first_name, u.last_name
                    FROM messages m
                    JOIN users u ON m.sender_id = u.user_id
                    WHERE (m.sender_id = :userId AND m.recipient_id = :tenantId)
                    OR (m.sender_id = :tenantId AND m.recipient_id = :userId)
                    ORDER BY m.created_at DESC
                    LIMIT 10
                ");
                
                $stmt->execute([
                    'userId' => $userId,
                    'tenantId' => $tenantId
                ]);
                
                $previousMessages = $stmt->fetchAll();
                ?>
                
                <?php if (empty($previousMessages)): ?>
                    <div class="text-center text-gray-500 py-6">
                        <p>No previous messages with this tenant</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($previousMessages as $prevMsg): ?>
                            <div class="p-4 rounded-lg <?php echo ($prevMsg['sender_id'] == $userId) ? 'bg-blue-50 ml-12' : 'bg-gray-50 mr-12'; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-medium">
                                            <?php echo ($prevMsg['sender_id'] == $userId) ? 'You' : htmlspecialchars($prevMsg['first_name'] . ' ' . $prevMsg['last_name']); ?>
                                        </span>
                                        <span class="text-xs text-gray-500 ml-2">
                                            <?php echo date('M j, Y g:i a', strtotime($prevMsg['created_at'])); ?>
                                        </span>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo ($prevMsg['sender_id'] == $userId) ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-800'; ?>">
                                        <?php echo ucfirst($prevMsg['message_type']); ?>
                                    </span>
                                </div>
                                <div class="text-sm">
                                    <p class="font-medium"><?php echo htmlspecialchars($prevMsg['subject']); ?></p>
                                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($prevMsg['message'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
                                function updateMessagePreview() {
                                    const messageType = document.querySelector('input[name="message_type"]:checked').value;
                                    const messageText = document.getElementById('message').value;
                                    const subject = document.getElementById('subject').value;
                                    const previewDiv = document.getElementById('message_preview');
                                    const previewContent = document.getElementById('preview_content');
                                    const charCount = document.getElementById('character_count');
                                    
                                    // Show preview for all message types
                                    previewDiv.classList.remove('hidden');
                                    
                                    if (messageType === 'sms' || messageType === 'both') {
                                        // For SMS, show character count and truncate preview if needed
                                        charCount.classList.remove('hidden');
                                        const smsText = messageText.substring(0, 160);
                                        document.getElementById('char_count').textContent = messageText.length;
                                        
                                        // Show warning if message is too long for SMS
                                        if (messageText.length > 160) {
                                            previewContent.innerHTML = `
                                                <div class="text-amber-600 mb-2">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    Message exceeds SMS character limit (160). It will be truncated.
                                                </div>
                                                <strong>SMS Preview:</strong><br>
                                                ${smsText}
                                            `;
                                        } else {
                                            previewContent.innerHTML = `<strong>SMS Preview:</strong><br>${messageText}`;
                                        }
                                        
                                        // If both is selected, also show email preview
                                        if (messageType === 'both') {
                                            previewContent.innerHTML += `
                                                <hr class="my-2">
                                                <strong>Email Preview:</strong><br>
                                                <strong>Subject:</strong> ${subject}<br>
                                                <strong>Message:</strong><br>${messageText.replace(/\n/g, '<br>')}
                                            `;
                                        }
                                    } else if (messageType === 'email') {
                                        // For email, show subject and message
                                        charCount.classList.add('hidden');
                                        previewContent.innerHTML = `
                                            <strong>Email Preview:</strong><br>
                                            <strong>Subject:</strong> ${subject}<br>
                                            <strong>Message:</strong><br>${messageText.replace(/\n/g, '<br>')}
                                        `;
                                    } else {
                                        // For portal only, just show basic preview
                                        charCount.classList.add('hidden');
                                        previewContent.innerHTML = `
                                            <strong>Portal Message:</strong><br>
                                            <strong>Subject:</strong> ${subject}<br>
                                            <strong>Message:</strong><br>${messageText.replace(/\n/g, '<br>')}
                                        `;
                                    }
                                }
                                
                                // Add event listeners to update preview when content changes
                                document.getElementById('message').addEventListener('input', updateMessagePreview);
                                document.getElementById('subject').addEventListener('input', updateMessagePreview);
                                
                                // Initialize preview on page load
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Set initial preview if a message type is already selected
                                    updateMessagePreview();
                                });
                                function loadTenantDetails(tenantId) {
        if (!tenantId) {
            document.getElementById('tenant_details_container').classList.add('hidden');
            
            // Hide all message type options except portal
            const messageTypeOptions = document.querySelectorAll('input[name="message_type"]');
            messageTypeOptions.forEach(option => {
                if (option.value !== 'portal') {
                    option.parentElement.classList.add('hidden');
                }
            });
            
            // Select portal option
            document.getElementById('message_type_portal').checked = true;
            updateMessagePreview();
            return;
        }
        
        // Show loading indicator
        const detailsContainer = document.getElementById('tenant_details_container');
        detailsContainer.classList.remove('hidden');
        document.getElementById('tenant_details').innerHTML = '<p>Loading tenant details...</p>';
        
        // Fetch tenant details via AJAX
        fetch('get_tenant_details.php?tenant_id=' + tenantId)
            .then(response => response.json())
            .then(tenant => {
                if (tenant.error) {
                    document.getElementById('tenant_details').innerHTML = 
                        `<p class="text-red-500">${tenant.error}</p>`;
                    return;
                }
                
                // Display tenant details
                document.getElementById('tenant_details').innerHTML = `
                    <p><strong>Name:</strong> ${tenant.first_name} ${tenant.last_name}</p>
                    <p><strong>Email:</strong> ${tenant.email || 'Not available'}</p>
                    <p><strong>Phone:</strong> ${tenant.phone || 'Not available'}</p>
                `;
                
                // Show/hide message type options based on available contact methods
                const emailOption = document.getElementById('message_type_email');
                const smsOption = document.getElementById('message_type_sms');
                const bothOption = document.getElementById('message_type_both');
                
                if (emailOption) {
                    if (tenant.email) {
                        emailOption.parentElement.classList.remove('hidden');
                        emailOption.nextElementSibling.innerHTML = 
                            `Email (${tenant.email})`;
                    } else {
                        emailOption.parentElement.classList.add('hidden');
                    }
                }
                
                if (smsOption) {
                    if (tenant.phone) {
                        smsOption.parentElement.classList.remove('hidden');
                        smsOption.nextElementSibling.innerHTML = 
                            `SMS (${tenant.phone}) <span class="text-xs text-gray-500">(160 chars max)</span>`;
                    } else {
                        smsOption.parentElement.classList.add('hidden');
                    }
                }
                
                if (bothOption) {
                    if (tenant.email && tenant.phone) {
                        bothOption.parentElement.classList.remove('hidden');
                    } else {
                        bothOption.parentElement.classList.add('hidden');
                    }
                }
                
                // Update message preview
                updateMessagePreview();
            })
            .catch(error => {
                document.getElementById('tenant_details').innerHTML = 
                    `<p class="text-red-500">Error loading tenant details: ${error.message}</p>`;
            });
    }
    function loadTenantDetails(tenantId) {
    if (!tenantId) {
        document.getElementById('tenant_details_container').classList.add('hidden');
        
        // Hide all message type options except portal
        document.getElementById('email_option').classList.add('hidden');
        document.getElementById('message_type_email').disabled = true;
        
        document.getElementById('sms_option').classList.add('hidden');
        document.getElementById('message_type_sms').disabled = true;
        
        document.getElementById('both_option').classList.add('hidden');
        document.getElementById('message_type_both').disabled = true;
        
        // Select portal option
        document.getElementById('message_type_portal').checked = true;
        updateMessagePreview();
        return;
    }
    
    // Show loading indicator
    const detailsContainer = document.getElementById('tenant_details_container');
    detailsContainer.classList.remove('hidden');
    document.getElementById('tenant_details').innerHTML = '<p>Loading tenant details...</p>';
    
    // Fetch tenant details via AJAX
    fetch('get_tenant_details.php?tenant_id=' + tenantId)
        .then(response => response.json())
        .then(tenant => {
            if (tenant.error) {
                document.getElementById('tenant_details').innerHTML = 
                    `<p class="text-red-500">${tenant.error}</p>`;
                return;
            }
            
            // Display tenant details
            document.getElementById('tenant_details').innerHTML = `
                <p><strong>Name:</strong> ${tenant.first_name} ${tenant.last_name}</p>
                <p><strong>Email:</strong> ${tenant.email || 'Not available'}</p>
                <p><strong>Phone:</strong> ${tenant.phone || 'Not available'}</p>
            `;
            
            // Show/hide message type options based on available contact methods
            const emailOption = document.getElementById('email_option');
            const emailRadio = document.getElementById('message_type_email');
            const emailLabel = emailRadio.nextElementSibling;
            
            const smsOption = document.getElementById('sms_option');
            const smsRadio = document.getElementById('message_type_sms');
            const smsLabel = smsRadio.nextElementSibling;
            
            const bothOption = document.getElementById('both_option');
            const bothRadio = document.getElementById('message_type_both');
            
            // Handle email option
            if (tenant.email) {
                emailOption.classList.remove('hidden');
                emailRadio.disabled = false;
                emailLabel.innerHTML = `Email (${tenant.email})`;
            } else {
                emailOption.classList.add('hidden');
                emailRadio.disabled = true;
            }
            
            // Handle SMS option
            if (tenant.phone) {
                smsOption.classList.remove('hidden');
                smsRadio.disabled = false;
                smsLabel.innerHTML = `SMS (${tenant.phone}) <span class="text-xs text-gray-500">(160 chars max)</span>`;
            } else {
                smsOption.classList.add('hidden');
                smsRadio.disabled = true;
            }
            
            // Handle both option
            if (tenant.email && tenant.phone) {
                bothOption.classList.remove('hidden');
                bothRadio.disabled = false;
            } else {
                bothOption.classList.add('hidden');
                bothRadio.disabled = true;
            }
            
            // If current selected option is now disabled, switch to portal
            const selectedOption = document.querySelector('input[name="message_type"]:checked');
            if (selectedOption && selectedOption.disabled) {
                document.getElementById('message_type_portal').checked = true;
            }
            
            // Update message preview
            updateMessagePreview();
        })
        .catch(error => {
            document.getElementById('tenant_details').innerHTML = 
                `<p class="text-red-500">Error loading tenant details: ${error.message}</p>`;
        });
}

                            </script>
</body>
</html>
