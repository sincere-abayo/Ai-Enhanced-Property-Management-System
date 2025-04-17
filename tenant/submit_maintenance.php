<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $propertyId = isset($_POST['property_id']) ? (int)$_POST['property_id'] : 0;
    $unitId = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    $issueType = isset($_POST['issue_type']) ? $_POST['issue_type'] : '';
    $bestTime = isset($_POST['best_time']) ? $_POST['best_time'] : 'anytime';
    $permissionToEnter = isset($_POST['permission_to_enter']) ? true : false;
    
    // Validate required fields
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if (empty($propertyId)) {
        $errors[] = "Property is required";
    }
    
    // Validate property belongs to tenant's lease
    $stmt = $pdo->prepare("
        SELECT l.*, p.landlord_id, p.property_name 
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        WHERE l.property_id = ? AND l.tenant_id = ? AND l.status = 'active'
    ");
    $stmt->execute([$propertyId, $userId]);
    $lease = $stmt->fetch();
    
    if (!$lease) {
        $errors[] = "Invalid property selected";
    }
    
    // If no errors, create the maintenance request
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Calculate AI priority score (example algorithm)
            $aiPriorityScore = 0;
            
            // Emergency issues get highest score
            if ($priority === 'emergency') {
                $aiPriorityScore += 80;
            } elseif ($priority === 'high') {
                $aiPriorityScore += 60;
            } elseif ($priority === 'medium') {
                $aiPriorityScore += 40;
            } else {
                $aiPriorityScore += 20;
            }
            
            // Certain issue types may get priority boost
            if ($issueType === 'plumbing' || $issueType === 'electrical') {
                $aiPriorityScore += 10;
            }
            
            // Cap at 100
            $aiPriorityScore = min(100, $aiPriorityScore);
            
            // Add notes about permission to enter
            $notes = "Issue Type: " . ucfirst($issueType) . "\n";
            $notes .= "Best Time: " . ucfirst(str_replace('_', ' ', $bestTime)) . "\n";
            $notes .= "Permission to Enter: " . ($permissionToEnter ? "Yes" : "No");
            
            // Create the maintenance request
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_requests (
                    property_id, unit_id, tenant_id, title, description, 
                    priority, status, created_at, ai_priority_score
                ) VALUES (
                    :propertyId, :unitId, :tenantId, :title, :description, 
                    :priority, 'pending', NOW(), :aiPriorityScore
                )
            ");
            
            $stmt->execute([
                'propertyId' => $propertyId,
                'unitId' => $unitId,
                'tenantId' => $userId,
                'title' => $title,
                'description' => $description . "\n\n" . $notes,
                'priority' => $priority,
                'aiPriorityScore' => $aiPriorityScore
            ]);
            
            $requestId = $pdo->lastInsertId();
            
            // Create a notification for the landlord
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (
                    :userId, :title, :message, 'maintenance', 0, NOW()
                )
            ");
            
            $stmt->execute([
                'userId' => $lease['landlord_id'],
                'title' => 'New Maintenance Request',
                'message' => "New maintenance request from " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . 
                             " for " . $lease['property_name'] . ": " . $title
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success'] = "Your maintenance request has been submitted successfully!";
            
            // Redirect to maintenance page
            header("Location: maintenance.php");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// If there are errors, redirect back to maintenance page with error messages
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: maintenance.php");
    exit;
}

// If not a POST request, redirect to maintenance page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: maintenance.php");
    exit;
}
?>