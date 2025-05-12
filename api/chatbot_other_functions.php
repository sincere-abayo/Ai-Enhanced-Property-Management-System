<?php


/**
 * Generate a bot response based on user message and context
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant's ID
 * @param int $conversationId The conversation ID
 * @param array $context Conversation context
 * @return string Bot response
 */
function generateBotResponse($userMessage, $tenantId, $conversationId, $context) {
    // Detect question type
    $questionType = detectQuestionType($userMessage);
    
    // Handle yes/no responses
    if ($questionType === 'yes_no') {
        $yesNoResponse = handleYesNoResponse($userMessage, $context, $tenantId);
        if ($yesNoResponse) {
            // Update context
            $context['last_bot_message'] = $yesNoResponse;
            updateConversationContext($conversationId, $context);
            return $yesNoResponse;
        }
    }
    
    // Handle follow-up questions
    if ($questionType === 'follow_up') {
        $followUpResponse = handleFollowUp($userMessage, $context, $tenantId);
        if ($followUpResponse) {
            // Update context
            $context['last_bot_message'] = $followUpResponse;
            updateConversationContext($conversationId, $context);
            return $followUpResponse;
        }
    }
    
    // Handle clarification requests
    if ($questionType === 'clarification') {
        $clarificationResponse = handleClarification($userMessage, $context);
        if ($clarificationResponse) {
            // Update context
            $context['last_bot_message'] = $clarificationResponse;
            updateConversationContext($conversationId, $context);
            return $clarificationResponse;
        }
    }
    
    // Extract topics from the current message
    $currentTopics = extractTopics($userMessage);
    
    // Update context with new topics
    if (!empty($currentTopics)) {
        foreach ($currentTopics as $topic) {
            if (!in_array($topic, $context['topics_discussed'])) {
                $context['topics_discussed'][] = $topic;
            }
        }
        $context['last_topic'] = $currentTopics[0];
    }
    
    // Increment question count
    $context['question_count']++;
    $context['last_response_time'] = time();
    
    // Process the message normally
    $response = processUserMessage($userMessage, $tenantId, $conversationId);
    
    // Update context with the bot's response
    $context['last_bot_message'] = $response;
    updateConversationContext($conversationId, $context);
    
    return $response;
}
/**
 * Process a user message and generate a response
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @param int $conversationId The conversation ID
 * @return string Bot response
 */
function processUserMessage($userMessage, $tenantId, $conversationId) {
    // Check for explicit escalation requests
    $escalationKeywords = ['human', 'agent', 'person', 'manager', 'support', 'help', 'speak', 'talk', 'escalate'];
    $message = strtolower($userMessage);
    $needsEscalation = false;
    
    foreach ($escalationKeywords as $keyword) {
        if (strpos($message, $keyword) !== false && 
            (strpos($message, 'speak') !== false || 
             strpos($message, 'talk') !== false || 
             strpos($message, 'need') !== false || 
             strpos($message, 'want') !== false || 
             strpos($message, 'connect') !== false)) {
            $needsEscalation = true;
            break;
        }
    }
    
    if ($needsEscalation) {
        return handleHumanEscalation($userMessage, $tenantId, $conversationId);
    }
    
    // Check if it's a property-related query
    $propertyResponse = handlePropertyQuery($userMessage, $tenantId);
    if ($propertyResponse) {
        return $propertyResponse;
    }
    
    // Check if it's a lease-related query
    $leaseResponse = handleLeaseQuery($userMessage, $tenantId);
    if ($leaseResponse) {
        return $leaseResponse;
    }
    
    // Check if it's a payment-related query
    $paymentResponse = handlePaymentQuery($userMessage, $tenantId);
    if ($paymentResponse) {
        return $paymentResponse;
    }
    
    // Check if it's a maintenance issue
    $maintenanceDiagnosis = diagnoseMaintenance($userMessage);
    if ($maintenanceDiagnosis) {
        $issues = $maintenanceDiagnosis['issues'];
        $urgency = $maintenanceDiagnosis['urgency'];
        
        $response = "I've identified this as a possible ";
        $response .= implode(' and ', $issues);
        $response .= " issue. ";
        
        if ($urgency === 'high') {
            $response .= "This appears to be urgent. Please contact emergency maintenance at 0788459654 immediately. ";
        } else {
            $response .= "Here are some quick troubleshooting steps:\n\n";
            
            // Add troubleshooting steps based on issue type
            if (in_array('plumbing', $issues)) {
                $response .= "- For leaks: Turn off the water supply valve if possible\n";
                $response .= "- For clogs: Try using a plunger\n";
            } elseif (in_array('electrical', $issues)) {
                $response .= "- Check if the circuit breaker has tripped\n";
                $response .= "- Try plugging the device into a different outlet\n";
            } elseif (in_array('hvac', $issues)) {
                $response .= "- Check if the thermostat is set correctly\n";
                $response .= "- Make sure vents aren't blocked\n";
            }
            
            $response .= "\nWould you like to submit a maintenance request now?";
        }
        
        return $response;
    }
    
    // Try to find a matching FAQ
    $faq = findMatchingFAQ($userMessage);
    if ($faq) {
        return $faq['answer'];
    }
    
    // Check confidence level
    $confidenceLevel = calculateConfidence($userMessage);
    if ($confidenceLevel < 0.3) {
        // Low confidence, suggest escalation
        return "I'm not sure I understand your question completely. Would you like me to connect you with a human support agent? If not, please try rephrasing your question.";
    }
    
    // Default response
    return "I'm not sure I understand. Could you please rephrase your question? You can ask about rent payments, maintenance requests, your lease, or property amenities.";
}


/**
 * Generate maintenance response (helper function)
 * 
 * @param array $diagnosis The maintenance diagnosis
 * @return string Response
 */
function generateMaintenanceResponse($diagnosis) {
    $issues = $diagnosis['issues'];
    $urgency = $diagnosis['urgency'];
    
    $response = "I've identified this as a possible ";
    $response .= implode(' and ', $issues);
    $response .= " issue. ";
    
    if ($urgency === 'high') {
        $response .= "This appears to be urgent. Please contact emergency maintenance at [EMERGENCY_PHONE] immediately. ";
    } else {
        $response .= "Here are some quick troubleshooting steps:\n\n";
        
        // Add troubleshooting steps based on issue type
        if (in_array('plumbing', $issues)) {
            $response .= "- For leaks: Turn off the water supply valve if possible\n";
            $response .= "- For clogs: Try using a plunger\n";
        } elseif (in_array('electrical', $issues)) {
            $response .= "- Check if the circuit breaker has tripped\n";
            $response .= "- Try plugging the device into a different outlet\n";
        } elseif (in_array('hvac', $issues)) {
            $response .= "- Check if the thermostat is set correctly\n";
            $response .= "- Make sure vents aren't blocked\n";
        }
        
        $response .= "\nWould you like to submit a maintenance request now?";
    }
    
    return $response;
}
/**
 * Calculate confidence level for the response
 * 
 * @param string $userMessage The user's message
 * @return float Confidence level between 0 and 1
 */
function calculateConfidence($userMessage) {
    // This is a placeholder for a more sophisticated confidence calculation
    // In a real implementation, this would use NLP techniques to determine confidence
    
    $message = strtolower($userMessage);
    $knownTopics = ['rent', 'payment', 'lease', 'maintenance', 'repair', 'property', 'amenity'];
    
    $confidence = 0.2; // Base confidence
    
    foreach ($knownTopics as $topic) {
        if (strpos($message, $topic) !== false) {
            $confidence += 0.1;
        }
    }
    
    // Cap at 0.9 - never be 100% confident
    return min(0.9, $confidence);
}

/**
 * Find the best matching FAQ from the knowledge base
 * 
 * @param string $userMessage The user's message
 * @return array|null The matching FAQ entry or null if no match found
 */
function findMatchingFAQ($userMessage) {
    global $pdo;
    
    // Convert to lowercase for easier matching
    $message = strtolower(trim($userMessage));
    
    // Try exact match first
    $stmt = $pdo->prepare("
        SELECT entry_id, question, answer, category, keywords
        FROM chatbot_knowledge_base
        WHERE LOWER(question) = ?
        LIMIT 1
    ");
    $stmt->execute([$message]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result;
    }
    
    // Try partial question match
    $stmt = $pdo->prepare("
        SELECT entry_id, question, answer, category, keywords
        FROM chatbot_knowledge_base
        WHERE LOWER(question) LIKE ?
        LIMIT 1
    ");
    $stmt->execute(['%' . $message . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result;
    }
    
    // Extract keywords from user message
    $keywords = explode(' ', $message);
    $relevantKeywords = array_filter($keywords, function($word) {
        return strlen($word) > 3; // Only consider words longer than 3 characters
    });
    
    if (!empty($relevantKeywords)) {
        // Build query with multiple keyword matches
        $conditions = [];
        $params = [];
        
        foreach ($relevantKeywords as $keyword) {
            $conditions[] = "LOWER(keywords) LIKE ?";
            $params[] = '%' . $keyword . '%';
        }
        
        $stmt = $pdo->prepare("
            SELECT entry_id, question, answer, category, keywords,
                   (LENGTH(keywords) - LENGTH(REPLACE(LOWER(keywords), ?, ''))) / LENGTH(?) AS match_score
            FROM chatbot_knowledge_base
            WHERE " . implode(' OR ', $conditions) . "
            ORDER BY match_score DESC
            LIMIT 1
        ");
        
        // Add the main keyword twice (for the match_score calculation)
        array_unshift($params, $relevantKeywords[0], $relevantKeywords[0]);
        
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        }
    }
    
    return null;
}

/**
 * Track which FAQ was used to answer a question
 * 
 * @param int $messageId The message ID
 * @param int $entryId The FAQ entry ID
 */
function trackFAQUsage($messageId, $entryId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, 'provide_info', ?, 1)
    ");
    
    $actionDetails = json_encode(['faq_entry_id' => $entryId]);
    $stmt->execute([$messageId, $actionDetails]);
}
/**
 * Diagnose maintenance issues based on user description
 * 
 * @param string $userMessage The user's message
 * @return array|null Diagnosis result or null if not a maintenance issue
 */
function diagnoseMaintenance($userMessage) {
    $message = strtolower($userMessage);
    
    // Common maintenance issues and their keywords
    $issues = [
        'plumbing' => ['leak', 'water', 'drip', 'toilet', 'sink', 'faucet', 'drain', 'clog', 'pipe'],
        'electrical' => ['power', 'outlet', 'light', 'switch', 'electric', 'circuit', 'breaker', 'spark'],
        'hvac' => ['heat', 'ac', 'air conditioning', 'furnace', 'thermostat', 'cold', 'hot', 'temperature'],
        'appliance' => ['refrigerator', 'stove', 'oven', 'dishwasher', 'washer', 'dryer', 'microwave'],
        'structural' => ['wall', 'ceiling', 'floor', 'door', 'window', 'roof', 'crack', 'hole'],
        'pest' => ['bug', 'insect', 'rodent', 'mouse', 'rat', 'roach', 'ant', 'pest']
    ];
    
    $foundIssues = [];
    $urgency = 'low';
    
    // Check for emergency keywords
    $emergencyKeywords = ['flood', 'fire', 'smoke', 'gas', 'leak', 'emergency', 'dangerous', 'safety'];
    foreach ($emergencyKeywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $urgency = 'high';
            break;
        }
    }
    
    // Check for issue types
    foreach ($issues as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $foundIssues[$type] = true;
                break;
            }
        }
    }
    
    if (empty($foundIssues)) {
        return null; // Not a maintenance issue
    }
    
    return [
        'issues' => array_keys($foundIssues),
        'urgency' => $urgency
    ];
}
/**
 * Track maintenance diagnosis
 * 
 * @param int $messageId The message ID
 * @param array $diagnosis The diagnosis result
 */
function trackMaintenanceDiagnosis($messageId, $diagnosis) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, 'maintenance_diagnosis', ?, 1)
    ");
    
    $actionDetails = json_encode($diagnosis);
    $stmt->execute([$messageId, $actionDetails]);
}
/**
 * Get payment information for a tenant
 * 
 * @param int $tenantId The tenant ID
 * @return array Payment information
 */
function getTenantPaymentInfo($tenantId) {
    global $pdo;
    
    // Get active lease
    $stmt = $pdo->prepare("
        SELECT l.lease_id, l.monthly_rent, l.payment_due_day
        FROM leases l
        WHERE l.tenant_id = ? AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $lease = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lease) {
        return ['error' => 'No active lease found'];
    }
    
    // Get recent payments
    $stmt = $pdo->prepare("
        SELECT p.payment_id, p.amount, p.payment_date, p.payment_method, p.payment_type, p.status
        FROM payments p
        WHERE p.lease_id = ? AND p.status = 'active'
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$lease['lease_id']]);
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate next payment due date
    $currentMonth = date('n');
    $currentYear = date('Y');
    $dueDay = $lease['payment_due_day'];
    
    // If we're past the due day this month, next payment is next month
    if (date('j') > $dueDay) {
        $nextMonth = $currentMonth + 1;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $currentYear++;
        }
        $nextDueDate = date('Y-m-d', strtotime("$currentYear-$nextMonth-$dueDay"));
    } else {
        $nextDueDate = date('Y-m-d', strtotime("$currentYear-$currentMonth-$dueDay"));
    }
    
    return [
        'monthly_rent' => $lease['monthly_rent'],
        'next_due_date' => $nextDueDate,
        'payment_due_day' => $lease['payment_due_day'],
        'recent_payments' => $recentPayments
    ];
}
/**
 * Handle payment-related queries
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not a payment query
 */

 /**
 * Handle payment-related queries
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not a payment query
 */
function handlePaymentQuery($userMessage, $tenantId) {
    $message = strtolower($userMessage);
    
    // Check if it's a payment-related query
    $paymentKeywords = ['payment', 'rent', 'due', 'pay', 'paid', 'balance', 'owe', 'bill'];
    $isPaymentQuery = false;
    
    foreach ($paymentKeywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $isPaymentQuery = true;
            break;
        }
    }
    
    if (!$isPaymentQuery) {
        return null;
    }
    
    // Get payment information
    $paymentInfo = getTenantPaymentInfo($tenantId);
    
    if (isset($paymentInfo['error'])) {
        return "I couldn't find your payment information. " . $paymentInfo['error'];
    }
    
    // Determine the specific type of payment query
    if (strpos($message, 'due date') !== false || 
        (strpos($message, 'when') !== false && strpos($message, 'due') !== false)) {
        // Due date query
        $dueDate = date('F j, Y', strtotime($paymentInfo['next_due_date']));
        return "Your next rent payment of $" . number_format($paymentInfo['monthly_rent'], 2) . " is due on $dueDate.";
    } 
    else if (strpos($message, 'history') !== false || strpos($message, 'recent') !== false || 
             strpos($message, 'last') !== false) {
        // Payment history query
        $response = "Here are your recent payments:\n\n";
        
        if (empty($paymentInfo['recent_payments'])) {
            return "I couldn't find any recent payment records for you.";
        }
        
        foreach ($paymentInfo['recent_payments'] as $payment) {
            $date = date('M j, Y', strtotime($payment['payment_date']));
            $amount = number_format($payment['amount'], 2);
            $method = ucfirst($payment['payment_method']);
            $response .= "- $date: $$amount via $method\n";
        }
        
        return $response;
    }
    else if (strpos($message, 'balance') !== false || 
    strpos($message, 'owe') !== false || 
    strpos($message, 'behind') !== false || 
    strpos($message, 'paid') !== false || 
    strpos($message, 'current') !== false) {
// Balance query - check if there's an outstanding balance
$dueDate = date('F j, Y', strtotime($paymentInfo['next_due_date']));
$today = date('Y-m-d');
$dueDayWithSuffix = getOrdinalSuffix($paymentInfo['payment_due_day']);

if (strtotime($today) > strtotime($paymentInfo['next_due_date'])) {
   return "You have an outstanding balance of $" . number_format($paymentInfo['monthly_rent'], 2) . 
          " that was due on " . $dueDate . ". Please make your payment as soon as possible.";
} else {
   return "You don't have any outstanding balance. Your next payment of $" . 
          number_format($paymentInfo['monthly_rent'], 2) . " is due on " . $dueDate . ".";
}
}


    else if (strpos($message, 'how') !== false && strpos($message, 'pay') !== false) {
        // How to pay query
        return "You can pay your rent through the Payments section of your tenant dashboard. " .
               "We accept credit cards, bank transfers, and other payment methods. " .
               "Your monthly rent is $" . number_format($paymentInfo['monthly_rent'], 2) . ".";
    }
    else if (strpos($message, 'late fee') !== false || strpos($message, 'penalty') !== false) {
        // Late fee query
        return "Late fees are typically 5% of the monthly rent if paid after the 5th of the month. Please check your lease for specific details.";
    }
    else if (strpos($message, 'method') !== false || strpos($message, 'accept') !== false) {
        // Payment methods query
        return "We accept the following payment methods: credit/debit cards, bank transfers, checks, and cash payments at the office. You can make payments through the Payments section of your tenant portal.";
    }
    else if (strpos($message, 'balance') !== false || strpos($message, 'owe') !== false || strpos($message, 'outstanding') !== false) {
        
        // Balance query - would need to calculate actual balance
        // additional database queries to determine if there's an outstanding balance
        $dueDayWithSuffix = getOrdinalSuffix($paymentInfo['payment_due_day']);
        return "Your monthly rent is $" . number_format($paymentInfo['monthly_rent'], 2) . 
       ". It's due on the " . $dueDayWithSuffix . " day of each month.";

    }
    
    else {
        // General payment query
        $dueDate = date('F j, Y', strtotime($paymentInfo['next_due_date']));
        $dueDayWithSuffix = getOrdinalSuffix($paymentInfo['payment_due_day']);
        return "Your monthly rent is $" . number_format($paymentInfo['monthly_rent'], 2) . 
               " and is due on the " . $dueDayWithSuffix . 
               " of each month. Your next payment is due on $dueDate.";
    }
    
}
function getOrdinalSuffix($number) {
    if ($number % 100 >= 11 && $number % 100 <= 13) {
        return $number . 'th';
    }
    
    switch ($number % 10) {
        case 1: return $number . 'st';
        case 2: return $number . 'nd';
        case 3: return $number . 'rd';
        default: return $number . 'th';
    }
}



/**
 * Track payment information query
 * 
 * @param int $messageId The message ID
 * @param string $queryType The type of payment query
 */
function trackPaymentQuery($messageId, $queryType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, 'payment_info', ?, 1)
    ");
    
    $actionDetails = json_encode(['query_type' => $queryType]);
    $stmt->execute([$messageId, $actionDetails]);
}
/**
 * Get lease information for a tenant
 * 
 * @param int $tenantId The tenant ID
 * @return array Lease information
 */
function getTenantLeaseInfo($tenantId) {
    global $pdo;
    
    // Get active lease with property details
    $stmt = $pdo->prepare("
        SELECT 
            l.lease_id, l.start_date, l.end_date, l.monthly_rent, l.security_deposit,
            p.property_name, p.address, p.city, p.state, p.zip_code,
            u.unit_number
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        LEFT JOIN units u ON l.unit_id = u.unit_id
        WHERE l.tenant_id = ? AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $lease = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lease) {
        return ['error' => 'No active lease found'];
    }
    
    // Calculate days until lease end
    $today = new DateTime();
    $endDate = new DateTime($lease['end_date']);
    $daysRemaining = $today->diff($endDate)->days;
    $isExpiringSoon = $daysRemaining <= 60;
    
    return [
        'start_date' => $lease['start_date'],
        'end_date' => $lease['end_date'],
        'days_remaining' => $daysRemaining,
        'is_expiring_soon' => $isExpiringSoon,
        'monthly_rent' => $lease['monthly_rent'],
        'security_deposit' => $lease['security_deposit'],
        'property_name' => $lease['property_name'],
        'address' => $lease['address'],
        'city' => $lease['city'],
        'state' => $lease['state'],
        'zip_code' => $lease['zip_code'],
        'unit_number' => $lease['unit_number']
    ];
}
/**
 * Handle lease-related queries
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not a lease query
 */
/**
 * Handle lease-related queries
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not a lease query
 */
function handleLeaseQuery($userMessage, $tenantId) {
    try {
        $message = strtolower($userMessage);
        
        // Check if it's a lease-related query
        $leaseKeywords = ['lease', 'contract', 'agreement', 'term', 'renew', 'renewal', 'move out', 'moving out', 'security deposit', 'deposit'];
        $isLeaseQuery = false;
        
        foreach ($leaseKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $isLeaseQuery = true;
                break;
            }
        }
        
        if (!$isLeaseQuery) {
            return null;
        }
        
        // Get lease information
        $leaseInfo = getTenantLeaseInfo($tenantId);
        
        if (isset($leaseInfo['error'])) {
            return "I couldn't find your lease information. " . $leaseInfo['error'];
        }

        if (strpos($message, 'security deposit') !== false || 
        (strpos($message, 'deposit') !== false && !strpos($message, 'pet deposit') !== false)) {
        // Security deposit query
        return "Your security deposit is $" . number_format($leaseInfo['security_deposit'], 2) . ". " .
               "This deposit is refundable at the end of your lease, subject to deductions for damages beyond normal wear and tear, " .
               "unpaid rent, or other charges as specified in your lease agreement. " .
               "For more specific details about what's covered, please refer to your lease agreement.";
    }
        
        // Determine the specific type of lease query
        if ((strpos($message, 'start') !== false || strpos($message, 'begin') !== false) && 
            (strpos($message, 'when') !== false || strpos($message, 'date') !== false)) {
            // Lease start date query
            $startDate = date('F j, Y', strtotime($leaseInfo['start_date']));
            return "Your current lease began on $startDate.";
        } 
        else if (strpos($message, 'end') !== false || strpos($message, 'expir') !== false || 
            (strpos($message, 'when') !== false && !strpos($message, 'start') !== false)) {
            // Lease end date query
            $endDate = date('F j, Y', strtotime($leaseInfo['end_date']));
            $response = "Your lease ends on $endDate. ";
            
            if ($leaseInfo['is_expiring_soon']) {
                $response .= "That's only " . $leaseInfo['days_remaining'] . " days away. ";
                $response .= "If you're interested in renewing your lease, please contact the property manager soon.";
            } else {
                $response .= "You have " . $leaseInfo['days_remaining'] . " days remaining on your current lease.";
            }
            
            return $response;
        } 
        else if (strpos($message, 'renew') !== false) {
            // Renewal query
            $endDate = date('F j, Y', strtotime($leaseInfo['end_date']));
            $response = "Your current lease ends on $endDate. ";
            
            if ($leaseInfo['is_expiring_soon']) {
                $response .= "Since your lease is ending soon, you should contact the property manager to discuss renewal options. ";
                $response .= "Typically, lease renewals need to be confirmed at least 30 days before the end date.";
            } else {
                $response .= "When you're ready to discuss renewal, please contact the property manager. ";
                $response .= "Typically, lease renewals are discussed 60-90 days before the end date.";
            }
            
            return $response;
        }
        else if (strpos($message, 'move out') !== false || strpos($message, 'moving out') !== false) {
            // Move out query
            return "If you're planning to move out at the end of your lease, you typically need to provide written notice at least 30 days before your lease end date. " .
                   "Please check your lease agreement for the specific notice period required. " .
                   "You can submit your notice through the tenant portal or by contacting the property manager.";
        }
        else if (strpos($message, 'break') !== false || strpos($message, 'terminate') !== false || 
                 strpos($message, 'early') !== false) {
            // Early termination query
            return "Early lease termination typically involves penalties as specified in your lease agreement. " .
                   "Please review your lease or contact the property manager to discuss your specific situation. " .
                   "Generally, you may be responsible for rent until a new tenant is found, plus additional fees.";
        }
        else {
            // General lease query
            $startDate = date('F j, Y', strtotime($leaseInfo['start_date']));
            $endDate = date('F j, Y', strtotime($leaseInfo['end_date']));
            $address = $leaseInfo['address'];
            if ($leaseInfo['unit_number']) {
                $address .= ', Unit ' . $leaseInfo['unit_number'];
            }
            
            return "Your current lease for $address began on $startDate and ends on $endDate. " .
                   "Your monthly rent is $" . number_format($leaseInfo['monthly_rent'], 2) . " " .
                   "and your security deposit is $" . number_format($leaseInfo['security_deposit'], 2) . ".";
        }
    } catch (Exception $e) {
        // Log the error
        error_log("Error in handleLeaseQuery: " . $e->getMessage());
        return "I'm having trouble retrieving your lease information right now. Please try again later.";
    }
}

/**
 * Track lease information query
 * 
 * @param int $messageId The message ID
 * @param string $queryType The type of lease query
 */
function trackLeaseQuery($messageId, $queryType) {
    global $pdo;
    
    try {
        // First check if the table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'chatbot_actions'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Table doesn't exist, log and return
            error_log("chatbot_actions table does not exist");
            return;
        }
        
        // Check if message_id exists in chatbot_messages
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM chatbot_messages WHERE message_id = ?
        ");
        $stmt->execute([$messageId]);
        if ($stmt->fetchColumn() == 0) {
            // Message doesn't exist, log and return
            error_log("Message ID $messageId does not exist in chatbot_messages");
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
            VALUES (?, 'lease_info', ?, 1)
        ");
        
        $actionDetails = json_encode(['query_type' => $queryType]);
        $stmt->execute([$messageId, $actionDetails]);
    } catch (Exception $e) {
        // Log the error but don't throw it further
        error_log("Error in trackLeaseQuery: " . $e->getMessage());
    }
}

/**
 * Get property information for a tenant
 * 
 * @param int $tenantId The tenant ID
 * @return array Property information
 */
function getPropertyInfo($tenantId) {
    global $pdo;
    
    try {
        // Get property details for tenant's active lease
        $stmt = $pdo->prepare("
            SELECT 
                p.property_id, p.property_name, p.address, p.city, p.state, p.zip_code,
                p.property_type, p.description, p.bedrooms, p.bathrooms, p.square_feet,
                u.unit_number, u.bedrooms as unit_bedrooms, u.bathrooms as unit_bathrooms, 
                u.square_feet as unit_square_feet,
                l.monthly_rent, l.security_deposit
            FROM leases l
            JOIN properties p ON l.property_id = p.property_id
            LEFT JOIN units u ON l.unit_id = u.unit_id
            WHERE l.tenant_id = ? AND l.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $property = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$property) {
            return ['error' => 'No property information found'];
        }
        
        // Use unit-specific details if available, otherwise use property details
        $bedrooms = $property['unit_bedrooms'] ? $property['unit_bedrooms'] : $property['bedrooms'];
        $bathrooms = $property['unit_bathrooms'] ? $property['unit_bathrooms'] : $property['bathrooms'];
        $squareFeet = $property['unit_square_feet'] ? $property['unit_square_feet'] : $property['square_feet'];
        
        // Extract amenities from the description
        $amenities = [];
        $description = strtolower($property['description'] ?? '');
        
        $commonAmenities = [
            'pool' => ['pool', 'swimming'],
            'gym' => ['gym', 'fitness center', 'workout', 'exercise'],
            'laundry' => ['laundry', 'washer', 'dryer'],
            'parking' => ['parking', 'garage', 'carport'],
            'pet friendly' => ['pet friendly', 'pets allowed', 'dog', 'cat'],
            'balcony' => ['balcony', 'patio', 'deck'],
            'dishwasher' => ['dishwasher'],
            'air conditioning' => ['ac', 'air conditioning', 'central air'],
            'heating' => ['heating', 'heat'],
            'wifi' => ['wifi', 'internet', 'high-speed internet'],
            'cable' => ['cable', 'tv', 'television'],
            'security' => ['security', 'gated', 'surveillance']
        ];
        
        foreach ($commonAmenities as $amenity => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    $amenities[] = $amenity;
                    break;
                }
            }
        }
        
        // If no amenities found in description, add some default ones based on property type
        if (empty($amenities)) {
            // Add basic amenities based on property type
            if ($property['property_type'] == 'apartment' || $property['property_type'] == 'condo') {
                $amenities = ['parking', 'laundry'];
            } else if ($property['property_type'] == 'house') {
                $amenities = ['parking', 'laundry', 'yard'];
            }
        }
        
        // Format the property information
        return [
            'property_name' => $property['property_name'],
            'address' => $property['address'],
            'city' => $property['city'],
            'state' => $property['state'],
            'zip_code' => $property['zip_code'],
            'property_type' => $property['property_type'],
            'unit_number' => $property['unit_number'],
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'square_feet' => $squareFeet,
            'monthly_rent' => $property['monthly_rent'],
            'security_deposit' => $property['security_deposit'],
            'description' => $property['description'],
            'amenities' => array_unique($amenities)
        ];
    } catch (Exception $e) {
        error_log("Error in getPropertyInfo: " . $e->getMessage());
        return ['error' => 'Error retrieving property information'];
    }
}


/**
 * Handle property-related queries
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not a property query
 */
function handlePropertyQuery($userMessage, $tenantId) {
    try {
        $message = strtolower($userMessage);
        
        // Check if it's a property-related query
        $propertyKeywords = ['property', 'apartment', 'house', 'building', 'amenity', 'amenities', 
                             'facility', 'facilities', 'feature', 'features', 'gym', 'pool', 
                             'laundry', 'parking', 'pet', 'pets', 'rule', 'rules', 'policy', 'policies'];
        $isPropertyQuery = false;
        
        foreach ($propertyKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $isPropertyQuery = true;
                break;
            }
        }
        
        if (!$isPropertyQuery) {
            return null;
        }
        
        // Get property information
        $propertyInfo = getPropertyInfo($tenantId);
        
        if (isset($propertyInfo['error'])) {
            return "I couldn't find information about your property. " . $propertyInfo['error'];
        }
        
        // Determine the specific type of property query
        if (strpos($message, 'amenity') !== false || strpos($message, 'amenities') !== false || 
            strpos($message, 'feature') !== false || strpos($message, 'features') !== false) {
            // Amenities query
            if (empty($propertyInfo['amenities'])) {
                return "Based on your " . $propertyInfo['property_type'] . " at " . $propertyInfo['address'] . 
                       ", standard amenities would typically include basic utilities. For specific amenities, " +
                       "please check your lease agreement or contact the property manager.";
            }
            
            $amenitiesList = implode(', ', array_map('ucfirst', $propertyInfo['amenities']));
            return "Based on the information I have, your " . $propertyInfo['property_type'] . " at " . 
                   $propertyInfo['address'] . " includes the following amenities: " . $amenitiesList . 
                   ". For more details, please check your lease agreement or contact the property manager.";
        } 
        else if (strpos($message, 'pet') !== false || strpos($message, 'dog') !== false || 
                 strpos($message, 'cat') !== false || strpos($message, 'animal') !== false) {
            // Pet policy query
            if (in_array('pet friendly', $propertyInfo['amenities'])) {
                return "Based on the information I have, your " . $propertyInfo['property_type'] . " at " . 
                       $propertyInfo['address'] . " appears to be pet-friendly. However, there may be specific " +
                       "restrictions or pet deposits required. Please check your lease agreement or contact the " +
                       "property manager for details on the pet policy.";
            } else {
                return "I don't have specific information about the pet policy for your " . $propertyInfo['property_type'] . 
                       " at " . $propertyInfo['address'] . ". Please check your lease agreement or contact the property manager for details.";
            }
        }
        else if (strpos($message, 'parking') !== false || strpos($message, 'garage') !== false || 
                 strpos($message, 'car') !== false) {
            // Parking query
            if (in_array('parking', $propertyInfo['amenities']) || in_array('garage', $propertyInfo['amenities'])) {
                return "Based on the information I have, your " . $propertyInfo['property_type'] . " at " . 
                       $propertyInfo['address'] . " includes parking facilities. For specific details about " +
                       "parking assignments, guest parking, or parking rules, please check your lease agreement " +
                       "or contact the property manager.";
            } else {
                return "I don't have specific information about parking at your " . $propertyInfo['property_type'] . 
                       " at " . $propertyInfo['address'] . ". Please check your lease agreement or contact the property manager for details.";
            }
        }
        else if (strpos($message, 'laundry') !== false || strpos($message, 'washer') !== false || 
                 strpos($message, 'dryer') !== false) {
            // Laundry query
            if (in_array('laundry', $propertyInfo['amenities']) || 
                in_array('washer', $propertyInfo['amenities']) || 
                in_array('dryer', $propertyInfo['amenities'])) {
                return "Your property includes laundry facilities. For specific details about laundry room hours or in-unit washer/dryer information, please check your lease agreement or contact the property manager.";
            } else {
                return "I don't have specific information about laundry facilities at your property. Please check your lease agreement or contact the property manager for details.";
            }
        }
        else if (strpos($message, 'gym') !== false || strpos($message, 'fitness') !== false || 
                 strpos($message, 'exercise') !== false) {
            // Gym/fitness query
            if (in_array('gym', $propertyInfo['amenities']) || in_array('fitness', $propertyInfo['amenities'])) {
                return "Your property includes fitness facilities. For specific details about gym hours, equipment, or access, please check your lease agreement or contact the property manager.";
            } else {
                return "I don't have information about fitness facilities at your property. Please check your lease agreement or contact the property manager for details.";
            }
        }
        else if (strpos($message, 'pool') !== false || strpos($message, 'swimming') !== false) {
            // Pool query
            if (in_array('pool', $propertyInfo['amenities'])) {
                return "Your property includes a swimming pool. For specific details about pool hours, rules, or seasonal availability, please check your lease agreement or contact the property manager.";
            } else {
                return "I don't have information about a swimming pool at your property. Please check your lease agreement or contact the property manager for details.";
            }
        }
        else if (strpos($message, 'quiet') !== false || strpos($message, 'hour') !== false || 
                 strpos($message, 'noise') !== false) {
            // Quiet hours query
            return "Most properties have quiet hours between 10:00 PM and 8:00 AM. For the specific quiet hours and noise policies at your property, please check your lease agreement or contact the property manager.";
        }
        else if (strpos($message, 'rule') !== false || strpos($message, 'policy') !== false || 
                 strpos($message, 'policies') !== false || strpos($message, 'regulation') !== false) {
            // Rules/policies query
            return "For specific information about property rules and policies, please refer to your lease agreement. Common rules include quiet hours, guest policies, smoking restrictions, and maintenance procedures. If you have questions about a specific rule, please contact the property manager.";
        }
        else {
            // General property query
            $response = "You're living at " . $propertyInfo['property_name'] . ", located at " . 
                       $propertyInfo['address'] . ", " . $propertyInfo['city'] . ", " . 
                       $propertyInfo['state'] . " " . $propertyInfo['zip_code'] . ". ";
            
            if ($propertyInfo['unit_number']) {
                $response .= "Your unit number is " . $propertyInfo['unit_number'] . ". ";
            }
            
            $response .= "It's a " . $propertyInfo['property_type'] . " with " . 
                        $propertyInfo['bedrooms'] . " bedroom(s) and " . 
                        $propertyInfo['bathrooms'] . " bathroom(s), totaling " . 
                        $propertyInfo['square_feet'] . " square feet. ";
            
            $response .= "Your monthly rent is $" . number_format($propertyInfo['monthly_rent'], 2) . 
                        " with a security deposit of $" . number_format($propertyInfo['security_deposit'], 2) . ". ";
            
            if (!empty($propertyInfo['amenities'])) {
                $response .= "Amenities include: " . implode(', ', array_map('ucfirst', $propertyInfo['amenities'])) . ".";
            }
            
            return $response;
        }
    } catch (Exception $e) {
        // Log the error
        error_log("Error in handlePropertyQuery: " . $e->getMessage());
        return "I'm having trouble retrieving your property information right now. Please try again later.";
    }
}

/**
 * Track property information query
 * 
 * @param int $messageId The message ID
 * @param string $queryType The type of property query
 */
function trackPropertyQuery($messageId, $queryType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, 'property_info', ?, 1)
    ");
    
    $actionDetails = json_encode(['query_type' => $queryType]);
    $stmt->execute([$messageId, $actionDetails]);
}

/**
 * Handle escalation to human support
 * 
 * @param string $userMessage The user's message
 * @param int $tenantId The tenant ID
 * @param int $conversationId The conversation ID
 * @return string Response indicating escalation
 */
function handleHumanEscalation($userMessage, $tenantId, $conversationId) {
    global $pdo;
    
    // Get tenant information
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email
        FROM users
        WHERE user_id = ?
    ");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get property manager (landlord) for this tenant
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email
        FROM users u
        JOIN properties p ON u.user_id = p.landlord_id
        JOIN leases l ON p.property_id = l.property_id
        WHERE l.tenant_id = ? AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $landlord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$landlord) {
        // Fallback to system admin if no landlord found
        $stmt = $pdo->prepare("
            SELECT user_id, email
            FROM users
            WHERE role = 'admin'
            LIMIT 1
        ");
        $stmt->execute();
        $landlord = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Create a support ticket/message
    $subject = "Chatbot Escalation: Support Needed";
    $message = "Tenant " . $tenant['first_name'] . " " . $tenant['last_name'] . 
               " (" . $tenant['email'] . ") has requested human assistance.\n\n" .
               "Original query: " . $userMessage . "\n\n" .
               "Please review the conversation history and respond to the tenant.";
    
    // Insert into messages table
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, recipient_id, subject, message, message_type)
        VALUES (?, ?, ?, ?, 'portal')
    ");
    $stmt->execute([$tenantId, $landlord['user_id'], $subject, $message]);
    
    // Mark conversation as requiring human attention
    $stmt = $pdo->prepare("
        UPDATE chatbot_conversations
        SET context = JSON_SET(IFNULL(context, '{}'), '$.needs_human_attention', true)
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversationId]);
    
    return "I've escalated your query to a human support agent. Someone will get back to you soon through the messaging system. You can check for responses in your Messages section.";
}

/**
 * Track human escalation
 * 
 * @param int $messageId The message ID
 * @param string $reason The reason for escalation
 */
function trackEscalation($messageId, $reason) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_actions (message_id, action_type, action_details, success)
        VALUES (?, 'escalate', ?, 1)
    ");
    
    $actionDetails = json_encode(['reason' => $reason]);
    $stmt->execute([$messageId, $actionDetails]);
}
/**
 * Check if a conversation needs human attention
 * 
 * @param int $conversationId The conversation ID
 * @return bool True if needs human attention
 */
function conversationNeedsHumanAttention($conversationId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT JSON_EXTRACT(context, '$.needs_human_attention') as needs_attention
        FROM chatbot_conversations
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversationId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && isset($result['needs_attention']) && $result['needs_attention'] == true;
}


/**
 * Get conversation context
 * 
 * @param int $conversationId The conversation ID
 * @return array Context data
 */
function getConversationContext($conversationId) {
    global $pdo;
    
    // Check if context exists
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
    
    // Create new context if it doesn't exist
    $emptyContext = [
        'topics_discussed' => [],
        'last_topic' => null,
        'question_count' => 0,
        'entities_mentioned' => [],
        'last_response_time' => time()
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO chatbot_context (conversation_id, context_data)
        VALUES (?, ?)
    ");
    $stmt->execute([$conversationId, json_encode($emptyContext)]);
    
    return $emptyContext;
}

/**
 * Update conversation context
 * 
 * @param int $conversationId The conversation ID
 * @param array $contextData The updated context data
 */
function updateConversationContext($conversationId, $contextData) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE chatbot_context
        SET context_data = ?
        WHERE conversation_id = ?
    ");
    $stmt->execute([json_encode($contextData), $conversationId]);
}

/**
 * Extract topics from user message
 * 
 * @param string $userMessage The user's message
 * @return array Extracted topics
 */
function extractTopics($userMessage) {
    $message = strtolower($userMessage);
    $topics = [];
    
    $topicKeywords = [
        'payment' => ['payment', 'rent', 'pay', 'bill', 'due', 'money'],
        'lease' => ['lease', 'contract', 'agreement', 'term', 'renew', 'renewal'],
        'maintenance' => ['maintenance', 'repair', 'fix', 'broken', 'issue', 'problem'],
        'property' => ['property', 'apartment', 'house', 'building', 'amenity', 'facility'],
        'move_out' => ['move out', 'moving out', 'vacate', 'leave', 'end lease'],
        'move_in' => ['move in', 'moving in', 'start lease', 'begin lease'],
        'pet' => ['pet', 'dog', 'cat', 'animal'],
        'parking' => ['parking', 'car', 'vehicle', 'garage', 'spot'],
        'noise' => ['noise', 'loud', 'quiet', 'sound', 'neighbor'],
        'utility' => ['utility', 'electric', 'water', 'gas', 'bill', 'internet', 'wifi']
    ];
    
    foreach ($topicKeywords as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $topics[] = $topic;
                break;
            }
        }
    }
    
    return array_unique($topics);
}
/**
 * Detect the type of question
 * 
 * @param string $userMessage The user's message
 * @return string Question type
 */
function detectQuestionType($userMessage) {
    $message = strtolower($userMessage);
    
    // Check for yes/no questions
    if (preg_match('/^(yes|no|yeah|nope|sure|ok|okay|nah)(\W|$)/i', $message)) {
        return 'yes_no';
    }
    
    // Check for follow-up questions
    $followUpIndicators = ['what about', 'how about', 'and', 'also', 'what if', 'but', 'then'];
    foreach ($followUpIndicators as $indicator) {
        if (strpos($message, $indicator) === 0) {
            return 'follow_up';
        }
    }
    
    // Check for clarification questions
    $clarificationIndicators = ['what do you mean', 'can you explain', 'i don\'t understand', 'clarify'];
    foreach ($clarificationIndicators as $indicator) {
        if (strpos($message, $indicator) !== false) {
            return 'clarification';
        }
    }
    
    // Check for wh-questions
    if (preg_match('/^(what|where|when|who|why|how)(\W|$)/i', $message)) {
        return 'wh_question';
    }
    
    // Check for command/request
    if (preg_match('/^(please|can you|could you|would you|tell me|show me|help me|give me|find|get)/i', $message)) {
        return 'command';
    }
    
    // Default to general question
    return 'general';
}

/**
 * Handle yes/no responses based on conversation context
 * 
 * @param string $userMessage The user's message
 * @param array $context Conversation context
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not applicable
 */
function handleYesNoResponse($userMessage, $context, $tenantId) {
    $message = strtolower($userMessage);
    $isYes = preg_match('/^(yes|yeah|sure|ok|okay|yep|yup|correct|right)(\W|$)/i', $message);
    $isNo = preg_match('/^(no|nope|nah|not|don\'t|cant|cannot)(\W|$)/i', $message);
    
    if (!$isYes && !$isNo) {
        return null;
    }
    
    // Check what the last topic was
    $lastTopic = isset($context['last_topic']) ? $context['last_topic'] : null;
    $lastBotMessage = isset($context['last_bot_message']) ? $context['last_bot_message'] : '';
    
    // Handle maintenance request confirmation
    if (strpos($lastBotMessage, 'submit a maintenance request') !== false) {
        if ($isYes) {
            // Create a maintenance request
            $requestId = createMaintenanceRequest($tenantId, $context);
            if ($requestId) {
                return "Great! I've created maintenance request #$requestId for you. You can track its status in the Maintenance section of your tenant portal.";
            } else {
                return "I tried to create a maintenance request, but there was an issue. Please use the Maintenance section of your tenant portal to submit your request manually.";
            }
        } else {
            return "No problem. If you change your mind, you can always submit a maintenance request through the Maintenance section of your tenant portal.";
        }
    }
    
    // Handle human escalation confirmation
    if (strpos($lastBotMessage, 'connect you with a human') !== false) {
        if ($isYes) {
            return handleHumanEscalation("User requested human assistance", $tenantId, $context['conversation_id']);
        } else {
            return "Okay. Please feel free to ask your question in a different way, and I'll do my best to help you.";
        }
    }
    
    // Handle other yes/no responses based on last topic
    if ($lastTopic === 'payment') {
        if ($isYes) {
            return "Great! You can make a payment through the Payments section of your tenant portal. Would you like me to explain the payment process?";
        } else {
            return "Okay. Is there anything else I can help you with regarding your payments or other matters?";
        }
    } else if ($lastTopic === 'lease') {
        if ($isYes) {
            return "I recommend contacting your property manager directly to discuss your lease. You can send them a message through the Messages section of your tenant portal.";
        } else {
            return "Okay. Is there anything else you'd like to know about your lease or other matters?";
        }
    }
    
    // Default responses
    if ($isYes) {
        return "I'm glad to hear that. Is there anything else I can help you with?";
    } else {
        return "I understand. Is there something else I can assist you with today?";
    }
}
/**
 * Handle follow-up questions
 * 
 * @param string $userMessage The user's message
 * @param array $context Conversation context
 * @param int $tenantId The tenant ID
 * @return string|null Response or null if not applicable
 */
function handleFollowUp($userMessage, $context, $tenantId) {
    // Check if this is a follow-up question
    $questionType = detectQuestionType($userMessage);
    if ($questionType !== 'follow_up') {
        return null;
    }
    
    // Get the last topic
    $lastTopic = isset($context['last_topic']) ? $context['last_topic'] : null;
    if (!$lastTopic) {
        return null; // No context to follow up on
    }
    
    // Remove follow-up indicators from the message
    $message = strtolower($userMessage);
    $followUpIndicators = ['what about', 'how about', 'and', 'also', 'what if', 'but', 'then'];
    foreach ($followUpIndicators as $indicator) {
        if (strpos($message, $indicator) === 0) {
            $message = trim(substr($message, strlen($indicator)));
            break;
        }
    }
    
    // Combine the last topic with the follow-up question
    $expandedMessage = $lastTopic . ' ' . $message;
    
    // Process the expanded message
    if ($lastTopic === 'payment') {
        return handlePaymentQuery($expandedMessage, $tenantId);
    } else if ($lastTopic === 'lease') {
        return handleLeaseQuery($expandedMessage, $tenantId);
    } else if ($lastTopic === 'property') {
        return handlePropertyQuery($expandedMessage, $tenantId);
    } else if ($lastTopic === 'maintenance') {
        $diagnosis = diagnoseMaintenance($expandedMessage);
        if ($diagnosis) {
            return generateMaintenanceResponse($diagnosis);
        }
    }
    
    // If we couldn't handle the follow-up with context, try without context
    return null;
}
/**
 * Handle clarification requests
 * 
 * @param string $userMessage The user's message
 * @param array $context Conversation context
 * @return string|null Response or null if not applicable
 */
function handleClarification($userMessage, $context) {
    // Check if this is a clarification request
    $questionType = detectQuestionType($userMessage);
    if ($questionType !== 'clarification') {
        return null;
    }
    
    // Get the last bot message
    $lastBotMessage = isset($context['last_bot_message']) ? $context['last_bot_message'] : '';
    if (empty($lastBotMessage)) {
        return null;
    }
    
    // Provide clarification based on the last topic
    $lastTopic = isset($context['last_topic']) ? $context['last_topic'] : null;
    
    if ($lastTopic === 'payment') {
        return "I was referring to your rent payment. You can make payments through the tenant portal, and your rent is typically due on the 1st of each month. Would you like more specific information about your payment history or upcoming payments?";
    } else if ($lastTopic === 'lease') {
        return "I was referring to your lease agreement. This is the contract that specifies the terms of your tenancy, including rent amount, lease period, and other policies. Would you like specific information about your current lease?";
    } else if ($lastTopic === 'maintenance') {
        return "I was referring to maintenance requests for repairs or issues in your unit. You can submit these through the tenant portal, and they'll be addressed by the maintenance team. Would you like to know how to submit a maintenance request?";
    } else if ($lastTopic === 'property') {
        return "I was referring to your rental property and its features or amenities. Would you like specific information about your property or the available amenities?";
    }
    
    // Default clarification
    return "I apologize for any confusion. Could you please ask your question again, and I'll try to provide a clearer answer?";
}
/**
 * Create a maintenance request from chatbot conversation
 * 
 * @param int $tenantId The tenant ID
 * @param array $context Conversation context
 * @return int|bool Request ID or false on failure
 */
function createMaintenanceRequest($tenantId, $context) {
    global $pdo;
    
    // Get tenant's active lease and property information
    $stmt = $pdo->prepare("
        SELECT l.lease_id, l.property_id, l.unit_id
        FROM leases l
        WHERE l.tenant_id = ? AND l.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $lease = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lease) {
        return false;
    }
    
    // Extract maintenance issue from context
    $maintenanceIssue = '';
    $maintenanceDescription = '';
    
    if (isset($context['maintenance_issue'])) {
        $maintenanceIssue = $context['maintenance_issue'];
    } else {
        // Try to determine from conversation history
        $messages = isset($context['recent_messages']) ? $context['recent_messages'] : [];
        foreach ($messages as $message) {
            if (!$message['is_from_bot']) {
                $diagnosis = diagnoseMaintenance($message['text']);
                if ($diagnosis) {
                    $maintenanceIssue = implode(' and ', $diagnosis['issues']);
                    $maintenanceDescription = $message['text'];
                    break;
                }
            }
        }
    }
    
    if (empty($maintenanceIssue)) {
        $maintenanceIssue = 'Issue reported via chatbot';
    }
    
    if (empty($maintenanceDescription)) {
        $maintenanceDescription = 'Maintenance issue reported through the chatbot. Please contact tenant for more details.';
    }
    
    // Determine priority
    $priority = 'medium';
    if (isset($context['maintenance_priority'])) {
        $priority = $context['maintenance_priority'];
    }
    
    // Create the maintenance request
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_requests 
        (property_id, unit_id, tenant_id, title, description, priority, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $success = $stmt->execute([
        $lease['property_id'],
        $lease['unit_id'],
        $tenantId,
        $maintenanceIssue,
        $maintenanceDescription,
        $priority
    ]);
    
    if ($success) {
        return $pdo->lastInsertId();
    }
    
    return false;
}
