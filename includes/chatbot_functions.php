<?php
/**
 * Chatbot helper functions
 */

/**
 * Start a new conversation for a tenant
 * 
 * @param int $tenantId The tenant's user ID
 * @return int The new conversation ID
 */
function startChatbotConversation($tenantId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_conversations (tenant_id)
        VALUES (?)
    ");
    
    $stmt->execute([$tenantId]);
    return $pdo->lastInsertId();
}

/**
 * End a chatbot conversation
 * 
 * @param int $conversationId The conversation ID
 * @param string|null $summary Optional summary of the conversation
 * @return bool Success status
 */
function endChatbotConversation($conversationId, $summary = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE chatbot_conversations
        SET end_time = NOW(), conversation_summary = ?
        WHERE conversation_id = ?
    ");
    
    return $stmt->execute([$summary, $conversationId]);
}

/**
 * Save a message in the conversation
 * 
 * @param int $conversationId The conversation ID
 * @param string $message The message text
 * @param bool $isFromBot Whether the message is from the bot (true) or user (false)
 * @param string|null $intent The detected intent
 * @param float|null $confidence The confidence score
 * @param array|null $entities Detected entities
 * @return int The message ID
 */
function saveChatbotMessage($conversationId, $message, $isFromBot, $intent = null, $confidence = null, $entities = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_messages (conversation_id, message_text, is_from_bot, intent_detected, confidence_score, entities_detected)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $entitiesJson = $entities ? json_encode($entities) : null;
    
    $stmt->execute([$conversationId, $message, $isFromBot ? 1 : 0, $intent, $confidence, $entitiesJson]);
    return $pdo->lastInsertId();
}

/**
 * Record an action taken by the chatbot
 * 
 * @param int $messageId The message ID that triggered the action
 * @param string $actionType The type of action
 * @param array $actionDetails Details about the action
 * @param bool $success Whether the action was successful
 * @return int The action ID
 */
function recordChatbotAction($messageId, $actionType, $actionDetails, $success) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, ?, ?, ?)
    ");
    
    $actionDetailsJson = json_encode($actionDetails);
    
    $stmt->execute([$messageId, $actionType, $actionDetailsJson, $success ? 1 : 0]);
    return $pdo->lastInsertId();
}

/**
 * Save feedback about a chatbot response
 * 
 * @param int $messageId The message ID
 * @param bool $wasHelpful Whether the response was helpful
 * @param string|null $feedbackText Additional feedback text
 * @return int The feedback ID
 */
function saveChatbotFeedback($messageId, $wasHelpful, $feedbackText = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_feedback (message_id, was_helpful, feedback_text)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([$messageId, $wasHelpful ? 1 : 0, $feedbackText]);
    return $pdo->lastInsertId();
}

/**
 * Get a response from the knowledge base based on user input
 * 
 * @param string $userInput The user's message
 * @return array|null Response data or null if no match found
 */
function getKnowledgeBaseResponse($userInput) {
    global $pdo;
    
    // Simple keyword matching for now
    // In a real implementation, this would use NLP/ML
    $stmt = $pdo->prepare("
        SELECT entry_id, question, answer, category
        FROM chatbot_knowledge_base
        WHERE 
            MATCH(question, keywords) AGAINST (? IN NATURAL LANGUAGE MODE)
        ORDER BY 
            MATCH(question, keywords) AGAINST (? IN NATURAL LANGUAGE MODE) DESC
        LIMIT 1
    ");
    
    $stmt->execute([$userInput, $userInput]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return [
            'entry_id' => $result['entry_id'],
            'answer' => $result['answer'],
            'confidence' => 0.8, // Placeholder for now
            'intent' => $result['category'],
            'matched_question' => $result['question']
        ];
    }
    
    return null;
}

/**
 * Save context for a conversation
 * 
 * @param int $conversationId The conversation ID
 * @param array $contextData The context data to save
 * @return bool Success status
 */
function saveConversationContext($conversationId, $contextData) {
    global $pdo;
    
    // Check if context already exists
    $stmt = $pdo->prepare("
        SELECT context_id FROM chatbot_context
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversationId]);
    
    $contextJson = json_encode($contextData);
    
    if ($stmt->rowCount() > 0) {
        // Update existing context
        $contextId = $stmt->fetchColumn();
        $updateStmt = $pdo->prepare("
            UPDATE chatbot_context
            SET context_data = ?, updated_at = NOW()
            WHERE context_id = ?
        ");
        return $updateStmt->execute([$contextJson, $contextId]);
    } else {
        // Create new context
        $insertStmt = $pdo->prepare("
            INSERT INTO chatbot_context (conversation_id, context_data)
            VALUES (?, ?)
        ");
        return $insertStmt->execute([$conversationId, $contextJson]);
    }
}

/**
 * Get context for a conversation
 * 
 * @param int $conversationId The conversation ID
 * @return array The context data
 */
function getConversationContext($conversationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT context_data
        FROM chatbot_context
        WHERE conversation_id = ?
    ");
    
    $stmt->execute([$conversationId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return json_decode($result['context_data'], true);
    }
    
    return [];
}

/**
 * Get conversation history
 * 
 * @param int $conversationId The conversation ID
 * @param int $limit Maximum number of messages to retrieve
 * @return array The conversation messages
 */
function getChatbotConversationHistory($conversationId, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT message_id, timestamp, is_from_bot, message_text
        FROM chatbot_messages
        WHERE conversation_id = ?
        ORDER BY timestamp DESC
        LIMIT ?
    ");
    
    $stmt->execute([$conversationId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}