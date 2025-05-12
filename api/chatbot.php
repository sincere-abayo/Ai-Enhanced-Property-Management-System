<?php

// Set headers to allow JSON response
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'chatbot_other_functions.php';


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

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get conversation history
        $conversationId = null;
        
        // Check if user has an active conversation
        $stmt = $pdo->prepare("
            SELECT conversation_id 
            FROM chatbot_conversations 
            WHERE tenant_id = ? AND end_time IS NULL
            ORDER BY start_time DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conversation) {
            $conversationId = $conversation['conversation_id'];
        } else {
            // Create a new conversation
            $stmt = $pdo->prepare("
                INSERT INTO chatbot_conversations (tenant_id)
                VALUES (?)
            ");
            $stmt->execute([$userId]);
            $conversationId = $pdo->lastInsertId();
        }
        
        // Get messages for this conversation
        $stmt = $pdo->prepare("
            SELECT 
                message_id, 
                is_from_bot, 
                message_text as text, 
                timestamp,
                IF(is_from_bot, 'bot', 'user') as sender
            FROM chatbot_messages
            WHERE conversation_id = ?
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if this conversation needs human attention
        $needsHumanAttention = conversationNeedsHumanAttention($conversationId);
        
        $response = [
            'status' => 'success',
            'conversation_id' => $conversationId,
            'messages' => $messages
        ];
        
        if ($needsHumanAttention) {
            $response['needs_human_attention'] = true;
            $response['human_message'] = "This conversation has been escalated to a human support agent. Please check your messages for a response.";
        }
        
        echo json_encode($response);
        break;
        
    case 'POST':
        // Handle new message
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['message']) || trim($data['message']) === '') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Message is required'
            ]);
            exit;
        }
        
        $userMessage = trim($data['message']);
        $conversationId = isset($data['conversation_id']) ? $data['conversation_id'] : null;
        
        // If no conversation ID provided, check for active conversation
        if (!$conversationId) {
            $stmt = $pdo->prepare("
                SELECT conversation_id 
                FROM chatbot_conversations 
                WHERE tenant_id = ? AND end_time IS NULL
                ORDER BY start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conversation) {
                $conversationId = $conversation['conversation_id'];
            } else {
                // Create a new conversation
                $stmt = $pdo->prepare("
                    INSERT INTO chatbot_conversations (tenant_id)
                    VALUES (?)
                ");
                $stmt->execute([$userId]);
                $conversationId = $pdo->lastInsertId();
            }
        }
        
        // Store user message
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_messages (conversation_id, is_from_bot, message_text)
            VALUES (?, 0, ?)
        ");
        $stmt->execute([$conversationId, $userMessage]);
        $userMessageId = $pdo->lastInsertId();
        
        // Get recent messages for context
        $stmt = $pdo->prepare("
            SELECT message_id, is_from_bot, message_text as text, timestamp
            FROM chatbot_messages
            WHERE conversation_id = ?
            ORDER BY timestamp DESC
            LIMIT 5
        ");
        $stmt->execute([$conversationId]);
        $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get conversation context
        $context = getConversationContext($conversationId);
        
        // Add recent messages to context
        $context['recent_messages'] = array_reverse($recentMessages);
        
        // Generate bot response with context
        $botResponse = generateBotResponse($userMessage, $userId, $conversationId, $context);
        
        // Store bot response
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_messages (conversation_id, is_from_bot, message_text)
            VALUES (?, 1, ?)
        ");
        $stmt->execute([$conversationId, $botResponse]);
        $messageId = $pdo->lastInsertId();
        
       // Track topic if applicable
$currentTopics = extractTopics($userMessage);
if (!empty($currentTopics)) {
    $topicName = $currentTopics[0];
    
// Track lease query if applicable
if ($topicName === 'lease') {
    try {
        $queryType = 'general';
        if (strpos(strtolower($userMessage), 'security deposit') !== false || 
            strpos(strtolower($userMessage), 'deposit') !== false) {
            $queryType = 'security_deposit';
        } else if (strpos(strtolower($userMessage), 'start') !== false || 
            strpos(strtolower($userMessage), 'begin') !== false) {
            $queryType = 'start_date';
        } else if (strpos(strtolower($userMessage), 'end') !== false || 
            strpos(strtolower($userMessage), 'expir') !== false) {
            $queryType = 'end_date';
        } else if (strpos(strtolower($userMessage), 'renew') !== false) {
            $queryType = 'renewal';
        }
        trackLeaseQuery($messageId, $queryType);
    } catch (Exception $e) {
        // Log the error but continue processing
        error_log("Error tracking lease query: " . $e->getMessage());
    }
}

            // Track payment query if applicable
            if ($topicName === 'payment') {
                $queryType = 'general';
                if (strpos(strtolower($userMessage), 'due') !== false) {
                    $queryType = 'due_date';
                } elseif (strpos(strtolower($userMessage), 'history') !== false || 
                         strpos(strtolower($userMessage), 'past') !== false) {
                    $queryType = 'history';
                } elseif (strpos(strtolower($userMessage), 'method') !== false || 
                         strpos(strtolower($userMessage), 'how') !== false) {
                    $queryType = 'method';
                }
                trackPaymentQuery($messageId, $queryType);
            }
            
          // Track property query if applicable
if ($topicName === 'property') {
    try {
        $queryType = 'general';
        if (strpos(strtolower($userMessage), 'amenity') !== false || 
            strpos(strtolower($userMessage), 'feature') !== false) {
            $queryType = 'amenities';
        } else if (strpos(strtolower($userMessage), 'pet') !== false) {
            $queryType = 'pet_policy';
        } else if (strpos(strtolower($userMessage), 'park') !== false) {
            $queryType = 'parking';
        } else if (strpos(strtolower($userMessage), 'gym') !== false || 
                  strpos(strtolower($userMessage), 'fitness') !== false) {
            $queryType = 'gym';
        } else if (strpos(strtolower($userMessage), 'pool') !== false) {
            $queryType = 'pool';
        } else if (strpos(strtolower($userMessage), 'laundry') !== false) {
            $queryType = 'laundry';
        }
        trackPropertyQuery($messageId, $queryType);
    } catch (Exception $e) {
        // Log the error but continue processing
        error_log("Error tracking property query: " . $e->getMessage());
    }
}

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
        
        if (!isset($data['message_id']) || !isset($data['helpful'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Message ID and helpful status are required'
            ]);
            exit;
        }
        
        $messageId = $data['message_id'];
        $helpful = $data['helpful'] ? 1 : 0;
        $feedbackText = isset($data['feedback_text']) ? $data['feedback_text'] : null;
        
        // Store feedback
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_feedback (message_id, was_helpful, feedback_text)
            VALUES (?, ?, ?)
        ");
        $success = $stmt->execute([$messageId, $helpful, $feedbackText]);
        
        if ($success) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Feedback recorded successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to record feedback'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
}
