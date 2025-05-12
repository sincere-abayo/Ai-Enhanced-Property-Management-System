<?php
// Set headers to allow JSON response
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'chatbot_other_functions.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Only tenants can use the chatbot
if ($_SESSION['role'] !== 'tenant') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied. Only tenants can use the chatbot.'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        // If user is not logged in, return empty messages
        if (!$userId) {
            echo json_encode(['status' => 'success', 'messages' => []]);
            exit;
        }
        
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
                echo json_encode(['status' => 'success', 'messages' => []]);
                exit;
            }
        }
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT message_id, message_text, is_from_bot, timestamp
            FROM chatbot_messages
            WHERE conversation_id = ?
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Check if this conversation needs human attention
$needsHumanAttention = conversationNeedsHumanAttention($conversationId);
if ($needsHumanAttention) {
    $response['needs_human_attention'] = true;
    $response['human_message'] = "This conversation has been escalated to a human support agent. Please check your messages for a response.";
}
        // Format messages for the frontend
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'id' => $message['message_id'],
                'text' => $message['message_text'],
                'sender' => $message['is_from_bot'] ? 'bot' : 'user',
                'timestamp' => $message['timestamp']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'conversation_id' => $conversationId,
            'messages' => $formattedMessages
        ]);
        break;
        
    case 'POST':
        // If user is not logged in, return error
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
            exit;
        }
        
        // Get JSON data from request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['message'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
            exit;
        }
        
        $userMessage = $data['message'];
        $conversationId = isset($data['conversation_id']) ? $data['conversation_id'] : null;
        
        // Start a new conversation if needed
        if (!$conversationId) {
            $stmt = $pdo->prepare("
                INSERT INTO chatbot_conversations (tenant_id)
                VALUES (?)
            ");
            $stmt->execute([$userId]);
            $conversationId = $pdo->lastInsertId();
        }
        
        // Store user message
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_messages (conversation_id, message_text, is_from_bot)
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$conversationId, $userMessage]);
       // Get recent messages for context
$stmt = $pdo->prepare("
SELECT message_id, is_from_bot, message_text, timestamp
FROM chatbot_messages
WHERE conversation_id = ?
ORDER BY timestamp DESC
LIMIT 5
");
$stmt->execute([$conversationId]);
$recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add to context
$context['recent_messages'] = array_reverse($recentMessages);

// Generate bot response with context
$botResponse = generateBotResponse($userMessage, $userId, $conversationId, $context);
        // Generate bot response
        // $botResponse = generateBotResponse($userMessage, $userId, $conversationId);
        
        // Store bot response
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_messages (conversation_id, message_text, is_from_bot)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([$conversationId, $botResponse]);
        $messageId = $pdo->lastInsertId();
        // Track maintenance diagnosis if applicable
    if ($maintenanceDiagnosis) {
    trackMaintenanceDiagnosis($messageId, $maintenanceDiagnosis);
    }
    // Track payment query if applicable
if (strpos(strtolower($userMessage), 'payment') !== false || 
strpos(strtolower($userMessage), 'rent') !== false) {
$queryType = 'general';
if (strpos(strtolower($userMessage), 'history') !== false) {
    $queryType = 'history';
} elseif (strpos(strtolower($userMessage), 'due') !== false) {
    $queryType = 'due_date';
} elseif (strpos(strtolower($userMessage), 'how') !== false) {
    $queryType = 'how_to_pay';
}
trackPaymentQuery($messageId, $queryType);
}
    // Track lease query if applicable
if (strpos(strtolower($userMessage), 'lease') !== false || 
strpos(strtolower($userMessage), 'contract') !== false ||
strpos(strtolower($userMessage), 'renew') !== false) {
$queryType = 'general';
if (strpos(strtolower($userMessage), 'end') !== false || 
    strpos(strtolower($userMessage), 'expir') !== false) {
    $queryType = 'end_date';
} elseif (strpos(strtolower($userMessage), 'renew') !== false) {
    $queryType = 'renewal';
} elseif (strpos(strtolower($userMessage), 'move out') !== false) {
    $queryType = 'move_out';
}
trackLeaseQuery($messageId, $queryType);
}    
// Track property query if applicable
if (strpos(strtolower($userMessage), 'property') !== false || 
    strpos(strtolower($userMessage), 'amenity') !== false ||
    strpos(strtolower($userMessage), 'facility') !== false) {
    $queryType = 'general';
    if (strpos(strtolower($userMessage), 'amenity') !== false || 
        strpos(strtolower($userMessage), 'feature') !== false) {
        $queryType = 'amenities';
    } elseif (strpos(strtolower($userMessage), 'pet') !== false) {
        $queryType = 'pet_policy';
    } elseif (strpos(strtolower($userMessage), 'park') !== false) {
        $queryType = 'parking';
    } elseif (strpos(strtolower($userMessage), 'rule') !== false || 
              strpos(strtolower($userMessage), 'policy') !== false) {
        $queryType = 'rules';
    }
    trackPropertyQuery($messageId, $queryType);
}

// Track escalation if applicable
$wasEscalated = false;
$escalationReason = '';

if (strpos(strtolower($botResponse), "I've escalated your query") !== false) {
    $wasEscalated = true;
    $escalationReason = 'explicit_request';
} else if (strpos(strtolower($botResponse), "connect you with a human") !== false) {
    $wasEscalated = true;
    $escalationReason = 'low_confidence';
}

if ($wasEscalated) {
    trackEscalation($messageId, $escalationReason);
    
    // Also update the conversation status
    $stmt = $pdo->prepare("
        UPDATE chatbot_conversations
        SET satisfaction_rating = -1 
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversationId]);
}
        echo json_encode([
            'status' => 'success',
            'message' => $botResponse,
            'message_id' => $messageId,
            'conversation_id' => $conversationId
        ]);
        break;
        
    case 'PUT':
        // Handle feedback
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['message_id']) || !isset($data['helpful'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid feedback data']);
            exit;
        }
        
        $messageId = $data['message_id'];
        $wasHelpful = $data['helpful'] ? 1 : 0;
        $feedbackText = isset($data['feedback_text']) ? $data['feedback_text'] : null;
        
        // Store feedback
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_feedback (message_id, was_helpful, feedback_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$messageId, $wasHelpful, $feedbackText]);
        
        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'Feedback received'
        ]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
