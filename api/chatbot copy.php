<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/chatbot_functions.php';

// Only allow tenant access
requireRole('tenant');

// Set content type to JSON
header('Content-Type: application/json');

// Get user information
$userId = $_SESSION['user_id'];

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Handle new message from user
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['message']) || empty($data['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Message is required']);
            exit;
        }
        
        $userMessage = $data['message'];
        $conversationId = isset($data['conversation_id']) ? $data['conversation_id'] : null;
        
        // If no conversation ID, start a new conversation
        if (!$conversationId) {
            $conversationId = startChatbotConversation($userId);
        }
        
        // Save user message
        $messageId = saveChatbotMessage($conversationId, $userMessage, false);
        
        // Get context if available
        $context = getConversationContext($conversationId);
        
        // Process the message and generate a response
        $response = getKnowledgeBaseResponse($userMessage);
        
        if (!$response) {
            // Fallback response if no match found
            $botResponse = "I'm sorry, I don't have information about that. Would you like to speak with a property manager?";
            $intent = "fallback";
            $confidence = 0.0;
        } else {
            $botResponse = $response['answer'];
            $intent = $response['intent'];
            $confidence = $response['confidence'];
            
            // Record the successful match
            recordChatbotAction($messageId, 'provide_info', [
                'entry_id' => $response['entry_id'],
                'matched_question' => $response['matched_question']
            ], true);
        }
        
        // Save bot response
        $botMessageId = saveChatbotMessage($conversationId, $botResponse, true, $intent, $confidence);
        
        // Update context with this interaction
        $context['last_intent'] = $intent;
        $context['last_message_id'] = $botMessageId;
        saveConversationContext($conversationId, $context);
        
        // Return the response
        echo json_encode([
            'conversation_id' => $conversationId,
            'message' => $botResponse,
            'intent' => $intent,
            'confidence' => $confidence
        ]);
        break;
        
    case 'GET':
        // Get conversation history
        $conversationId = isset($_GET['conversation_id']) ? $_GET['conversation_id'] : null;
        
        if (!$conversationId) {
            // Check if user has any active conversations
            $stmt = $pdo->prepare("
                SELECT conversation_id 
                FROM chatbot_conversations 
                WHERE tenant_id = ? AND end_time IS NULL 
                ORDER BY start_time DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $conversationId = $result['conversation_id'];
            } else {
                // No active conversation
                echo json_encode(['messages' => []]);
                exit;
            }
        }
        
        // Verify this conversation belongs to the current user
        $stmt = $pdo->prepare("
            SELECT tenant_id 
            FROM chatbot_conversations 
            WHERE conversation_id = ?
        ");
        $stmt->execute([$conversationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['tenant_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to conversation']);
            exit;
        }
        
        // Get messages
        $messages = getChatbotConversationHistory($conversationId, 50);
        
        // Format messages for the frontend
        $formattedMessages = [];
        foreach (array_reverse($messages) as $message) {
            $formattedMessages[] = [
                'id' => $message['message_id'],
                'text' => $message['message_text'],
                'sender' => $message['is_from_bot'] ? 'bot' : 'user',
                'timestamp' => $message['timestamp']
            ];
        }
        
        echo json_encode([
            'conversation_id' => $conversationId,
            'messages' => $formattedMessages
        ]);
        break;
        
    case 'PUT':
        // Handle feedback
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['message_id']) || !isset($data['helpful'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Message ID and helpful status are required']);
            exit;
        }
        
        $messageId = $data['message_id'];
        $wasHelpful = $data['helpful'];
        $feedbackText = isset($data['feedback']) ? $data['feedback'] : null;
        
        // Verify this message belongs to a conversation of the current user
        $stmt = $pdo->prepare("
            SELECT c.tenant_id 
            FROM chatbot_messages m
            JOIN chatbot_conversations c ON m.conversation_id = c.conversation_id
            WHERE m.message_id = ?
        ");
        $stmt->execute([$messageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['tenant_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to message']);
            exit;
        }
        
        // Save feedback
        $feedbackId = saveChatbotFeedback($messageId, $wasHelpful, $feedbackText);
        
        echo json_encode(['success' => true, 'feedback_id' => $feedbackId]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
