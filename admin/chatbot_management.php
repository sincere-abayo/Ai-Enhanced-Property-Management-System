<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role
requireRole('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_faq'])) {
        // Add new FAQ
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $category = $_POST['category'];
        $keywords = $_POST['keywords'];
        
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_knowledge_base (question, answer, category, keywords)
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$question, $answer, $category, $keywords])) {
            $_SESSION['success'] = "FAQ added successfully";
        } else {
            $_SESSION['error'] = "Error adding FAQ";
        }
        
        header("Location: chatbot_management.php");
        exit;
    } elseif (isset($_POST['update_faq'])) {
        // Update existing FAQ
        $entryId = $_POST['entry_id'];
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $category = $_POST['category'];
        $keywords = $_POST['keywords'];
        
        $stmt = $pdo->prepare("
            UPDATE chatbot_knowledge_base
            SET question = ?, answer = ?, category = ?, keywords = ?
            WHERE entry_id = ?
        ");
        
        if ($stmt->execute([$question, $answer, $category, $keywords, $entryId])) {
            $_SESSION['success'] = "FAQ updated successfully";
        } else {
            $_SESSION['error'] = "Error updating FAQ";
        }
        
        header("Location: chatbot_management.php");
        exit;
    } elseif (isset($_POST['delete_faq'])) {
        // Delete FAQ
        $entryId = $_POST['entry_id'];
        
        $stmt = $pdo->prepare("
            DELETE FROM chatbot_knowledge_base
            WHERE entry_id = ?
        ");
        
        if ($stmt->execute([$entryId])) {
            $_SESSION['success'] = "FAQ deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting FAQ";
        }
        
        header("Location: chatbot_management.php");
        exit;
    }
}

// Get FAQs
$stmt = $pdo->query("
    SELECT * FROM chatbot_knowledge_base
    ORDER BY category, question
");
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get conversation statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT c.conversation_id) AS total_conversations,
        COUNT(m.message_id) AS total_messages,
        COUNT(CASE WHEN m.is_from_bot = 0 THEN m.message_id END) AS user_messages,
        COUNT(CASE WHEN m.is_from_bot = 1 THEN m.message_id END) AS bot_messages,
        COUNT(DISTINCT c.tenant_id) AS unique_users,
        (SELECT COUNT(*) FROM chatbot_feedback WHERE was_helpful = 1) AS positive_feedback,
        (SELECT COUNT(*) FROM chatbot_feedback WHERE was_helpful = 0) AS negative_feedback
    FROM chatbot_conversations c
    LEFT JOIN chatbot_messages m ON c.conversation_id = m.conversation_id
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent conversations
$stmt = $pdo->query("
    SELECT 
        c.conversation_id,
        c.tenant_id,
        u.first_name,
        u.last_name,
        c.start_time,
        c.end_time,
        COUNT(m.message_id) AS message_count
    FROM chatbot_conversations c
    JOIN users u ON c.tenant_id = u.user_id
    LEFT JOIN chatbot_messages m ON c.conversation_id = m.conversation_id
    GROUP BY c.conversation_id
    ORDER BY c.start_time DESC
    LIMIT 10
");
$recentConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$pageTitle = "Chatbot Management";
include_once 'admin_sidebar.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Chatbot Management</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Conversations</h3>
            <p class="text-3xl font-bold text-primary"><?php echo number_format($stats['total_conversations']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Messages</h3>
            <p class="text-3xl font-bold text-primary"><?php echo number_format($stats['total_messages']); ?></p>
            <div class="flex justify-between text-sm text-gray-500 mt-2">
                <span>User: <?php echo number_format($stats['user_messages']); ?></span>
                <span>Bot: <?php echo number_format($stats['bot_messages']); ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Unique Users</h3>
            <p class="text-3xl font-bold text-primary"><?php echo number_format($stats['unique_users']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Feedback</h3>
            <p class="text-3xl font-bold text-primary">
                <?php 
                $totalFeedback = $stats['positive_feedback'] + $stats['negative_feedback'];
                echo $totalFeedback > 0 
                    ? round(($stats['positive_feedback'] / $totalFeedback) * 100) . '%' 
                    : 'N/A'; 
                ?>
            </p>
            <div class="flex justify-between text-sm text-gray-500 mt-2">
                <span class="text-green-500">
                    <i class="fas fa-thumbs-up"></i> <?php echo number_format($stats['positive_feedback']); ?>
                </span>
                <span class="text-red-500">
                    <i class="fas fa-thumbs-down"></i> <?php echo number_format($stats['negative_feedback']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex">
                <button id="faqTabBtn" class="tab-btn active py-4 px-6 border-b-2 border-primary font-medium text-sm text-primary focus:outline-none">
                    Knowledge Base
                </button>
                <button id="conversationsTabBtn" class="tab-btn py-4 px-6 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none">
                    Recent Conversations
                </button>
            </nav>
        </div>
    </div>
    
    <!-- FAQ Tab Content -->
    <div id="faqTab" class="tab-content">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Knowledge Base</h2>
            <button 
                onclick="openAddFaqModal()" 
                class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
            >
                <i class="fas fa-plus mr-2"></i> Add New FAQ
            </button>
        </div>
        
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Question
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Answer
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($faqs as $faq): ?>
                    <tr>
                    <td class="px-6 py-4 whitespace-normal">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($faq['question']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-normal">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars(substr($faq['answer'], 0, 100) . (strlen($faq['answer']) > 100 ? '...' : '')); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo ucfirst(htmlspecialchars($faq['category'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button 
                                onclick="openEditFaqModal(<?php echo $faq['entry_id']; ?>, '<?php echo addslashes(htmlspecialchars($faq['question'])); ?>', '<?php echo addslashes(htmlspecialchars($faq['answer'])); ?>', '<?php echo $faq['category']; ?>', '<?php echo addslashes(htmlspecialchars($faq['keywords'])); ?>')" 
                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                            >
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button 
                                onclick="openDeleteFaqModal(<?php echo $faq['entry_id']; ?>, '<?php echo addslashes(htmlspecialchars($faq['question'])); ?>')" 
                                class="text-red-600 hover:text-red-900"
                            >
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Conversations Tab Content -->
    <div id="conversationsTab" class="tab-content hidden">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Recent Conversations</h2>
        </div>
        
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tenant
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Start Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            End Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Messages
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recentConversations as $conversation): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">ID: <?php echo $conversation['tenant_id']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo date('M j, Y g:i A', strtotime($conversation['start_time'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo $conversation['end_time'] ? date('M j, Y g:i A', strtotime($conversation['end_time'])) : 'Active'; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $conversation['message_count']; ?> messages
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="chatbot_conversation.php?id=<?php echo $conversation['conversation_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add FAQ Modal -->
<div id="addFaqModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Add New FAQ</h3>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                <input type="text" name="question" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Answer</label>
                <textarea name="answer" rows="4" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    <option value="general">General</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="payments">Payments</option>
                    <option value="lease">Lease</option>
                    <option value="property">Property</option>
                    <option value="amenities">Amenities</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keywords (comma separated)</label>
                <input type="text" name="keywords" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                <p class="text-xs text-gray-500 mt-1">Enter keywords that will help match this FAQ to user questions</p>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeAddFaqModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" name="add_faq" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                    Add FAQ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit FAQ Modal -->
<div id="editFaqModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Edit FAQ</h3>
        
        <form method="POST" action="">
            <input type="hidden" id="editEntryId" name="entry_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                <input type="text" id="editQuestion" name="question" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Answer</label>
                <textarea id="editAnswer" name="answer" rows="4" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="editCategory" name="category" required class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                    <option value="general">General</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="payments">Payments</option>
                    <option value="lease">Lease</option>
                    <option value="property">Property</option>
                    <option value="amenities">Amenities</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Keywords (comma separated)</label>
                <input type="text" id="editKeywords" name="keywords" class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeEditFaqModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" name="update_faq" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700">
                    Update FAQ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete FAQ Confirmation Modal -->
<div id="deleteFaqModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4">Confirm Delete</h3>
        <p class="mb-6">Are you sure you want to delete the FAQ: <span id="deleteQuestion" class="font-semibold"></span>?</p>
        
        <div class="flex justify-end space-x-4">
            <button type="button" onclick="closeDeleteFaqModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <form method="POST" action="">
                <input type="hidden" id="deleteEntryId" name="entry_id">
                <button type="submit" name="delete_faq" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete FAQ
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            tabBtns.forEach(b => {
                b.classList.remove('active', 'border-primary', 'text-primary');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Add active class to clicked button
            this.classList.add('active', 'border-primary', 'text-primary');
            this.classList.remove('border-transparent', 'text-gray-500');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show corresponding tab content
            const tabId = this.id.replace('Btn', '');
            document.getElementById(tabId).classList.remove('hidden');
        });
    });
});

// Modal functionality
function openAddFaqModal() {
    document.getElementById('addFaqModal').classList.remove('hidden');
    document.getElementById('addFaqModal').classList.add('flex');
}

function closeAddFaqModal() {
    document.getElementById('addFaqModal').classList.add('hidden');
    document.getElementById('addFaqModal').classList.remove('flex');
}

function openEditFaqModal(entryId, question, answer, category, keywords) {
    document.getElementById('editEntryId').value = entryId;
    document.getElementById('editQuestion').value = question;
    document.getElementById('editAnswer').value = answer;
    document.getElementById('editCategory').value = category;
    document.getElementById('editKeywords').value = keywords;
    
    document.getElementById('editFaqModal').classList.remove('hidden');
    document.getElementById('editFaqModal').classList.add('flex');
}

function closeEditFaqModal() {
    document.getElementById('editFaqModal').classList.add('hidden');
    document.getElementById('editFaqModal').classList.remove('flex');
}

function openDeleteFaqModal(entryId, question) {
    document.getElementById('deleteEntryId').value = entryId;
    document.getElementById('deleteQuestion').textContent = question;
    
    document.getElementById('deleteFaqModal').classList.remove('hidden');
    document.getElementById('deleteFaqModal').classList.add('flex');
}

function closeDeleteFaqModal() {
    document.getElementById('deleteFaqModal').classList.add('hidden');
    document.getElementById('deleteFaqModal').classList.remove('flex');
}
</script>

