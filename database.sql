CREATE DATABASE property_management;
USE property_management;

-- Users table (for both landlords and tenants)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role ENUM('landlord', 'tenant') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Properties table
CREATE TABLE IF NOT EXISTS properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    property_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    property_type ENUM('apartment', 'house', 'condo', 'studio', 'commercial') NOT NULL,
    bedrooms INT,
    bathrooms DECIMAL(3,1),
    square_feet INT,
    monthly_rent DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('vacant', 'occupied', 'maintenance') DEFAULT 'vacant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Units table (for properties with multiple units)
CREATE TABLE IF NOT EXISTS units (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    bedrooms INT,
    bathrooms DECIMAL(3,1),
    square_feet INT,
    monthly_rent DECIMAL(10,2) NOT NULL,
    status ENUM('vacant', 'occupied', 'maintenance') DEFAULT 'vacant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
);

-- Leases table
CREATE TABLE IF NOT EXISTS leases (
    lease_id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_id INT,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    monthly_rent DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) NOT NULL,
    payment_due_day INT NOT NULL DEFAULT 1,
    status ENUM('active', 'expired', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    image_path  VARCHAR(255) NULL DEFAULT NULL,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    lease_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') NOT NULL,
    payment_type ENUM('rent', 'security_deposit', 'late_fee', 'other') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lease_id) REFERENCES leases(lease_id) ON DELETE CASCADE

);

-- Maintenance requests table
CREATE TABLE IF NOT EXISTS maintenance_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_id INT,
    tenant_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    estimated_cost DECIMAL(10,2),
    actual_cost DECIMAL(10,2),
    ai_priority_score INT,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(unit_id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Maintenance tasks table
CREATE TABLE IF NOT EXISTS maintenance_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    assigned_to INT,
    description TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('payment', 'maintenance', 'lease', 'general') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- AI Insights table
CREATE TABLE IF NOT EXISTS ai_insights (
    insight_id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    property_id INT,
    tenant_id INT,
    insight_type ENUM('rent_prediction', 'payment_risk', 'maintenance_prediction', 'financial_forecast') NOT NULL,
    insight_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES users(user_id) ON DELETE SET NULL
);


-- new tables
-- Messages table for communication between landlords and tenants
CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('portal', 'email', 'sms', 'both') NOT NULL DEFAULT 'portal',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

ALTER TABLE payments 
ADD COLUMN status ENUM('active', 'voided') DEFAULT 'active',
ADD COLUMN voided_at TIMESTAMP NULL,
ADD COLUMN voided_by INT NULL,
ADD COLUMN void_reason TEXT NULL,
ADD FOREIGN KEY (voided_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Create payment audit table for tracking changes
CREATE TABLE IF NOT EXISTS payment_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    action ENUM('create', 'update', 'void') NOT NULL,
    action_by INT NOT NULL,
    action_reason TEXT,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Update payment_audit table to include restore action
ALTER TABLE payment_audit 
MODIFY COLUMN action ENUM('create', 'update', 'void', 'restore') NOT NULL;

-- Message threads table
CREATE TABLE  IF NOT EXISTS message_threads (
    thread_id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Thread participants table
CREATE TABLE IF NOT EXISTS thread_participants (
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (thread_id, user_id),
    FOREIGN KEY (thread_id) REFERENCES message_threads(thread_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

ALTER TABLE messages 
ADD COLUMN thread_id INT AFTER message_id,
ADD FOREIGN KEY (thread_id) REFERENCES message_threads(thread_id) ON DELETE CASCADE;

-- Create password_resets table if it doesn't exist
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_valid BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (token)
);


-- chatbot related tables
-- Chatbot conversations table
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    conversation_summary TEXT NULL,
    satisfaction_rating INT NULL,
    FOREIGN KEY (tenant_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Individual message exchanges
CREATE TABLE IF NOT EXISTS chatbot_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_from_bot BOOLEAN NOT NULL,
    message_text TEXT NOT NULL,
    intent_detected VARCHAR(100) NULL, 
    confidence_score FLOAT NULL,
    entities_detected JSON NULL,
    FOREIGN KEY (conversation_id) REFERENCES chatbot_conversations(conversation_id) ON DELETE CASCADE
);

-- Actions taken by the chatbot
CREATE TABLE IF NOT EXISTS chatbot_actions (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    action_type ENUM('provide_info', 'create_maintenance', 'payment_info', 'escalate', 'other') NOT NULL,
    action_details JSON NOT NULL,
    success BOOLEAN NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chatbot_messages(message_id) ON DELETE CASCADE
);

-- FAQ knowledge base
CREATE TABLE IF NOT EXISTS chatbot_knowledge_base (
    entry_id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category ENUM('general', 'maintenance', 'payments', 'lease', 'property', 'amenities') NOT NULL,
    keywords TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Feedback on chatbot responses
CREATE TABLE IF NOT EXISTS chatbot_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    was_helpful BOOLEAN NOT NULL,
    feedback_text TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chatbot_messages(message_id) ON DELETE CASCADE
);

-- Chatbot training data for continuous improvement
CREATE TABLE IF NOT EXISTS chatbot_training_data (
    data_id INT AUTO_INCREMENT PRIMARY KEY,
    input_text TEXT NOT NULL,
    expected_intent VARCHAR(100) NOT NULL,
    expected_entities JSON NULL,
    source ENUM('manual', 'conversation', 'feedback') NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chatbot session context to maintain conversation state
CREATE TABLE IF NOT EXISTS chatbot_context (
    context_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    context_data JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chatbot_conversations(conversation_id) ON DELETE CASCADE
);

-- 
-- Initial seed data for FAQ knowledge base
INSERT INTO chatbot_knowledge_base (question, answer, category, keywords) VALUES
('When is my rent due?', 'Rent is typically due on the 1st of each month, but please check your specific lease agreement for details.', 'payments', 'rent, due date, payment, when'),
('How do I submit a maintenance request?', 'You can submit a maintenance request through your tenant portal by clicking on "Maintenance" and then "New Request".', 'maintenance', 'fix, repair, broken, maintenance, request'),
('How do I pay my rent?', 'You can pay your rent through the tenant portal using a credit card or bank transfer. You can also set up automatic payments.', 'payments', 'pay, rent, payment method, how to pay'),
('When does my lease end?', 'Your lease end date is specified in your lease agreement. You can also find this information in your tenant portal under "Lease Details".', 'lease', 'lease end, expiration, renewal, when'),
('What is the late fee for rent?', 'Late fees vary by property but are typically 5% of the monthly rent if paid after the 5th of the month. Please check your lease for specific details.', 'payments', 'late, fee, penalty, overdue'),
('How do I report an emergency maintenance issue?', 'For emergency maintenance issues like water leaks, electrical problems, or no heat, please call the emergency maintenance line at [PHONE NUMBER] immediately.', 'maintenance', 'emergency, urgent, leak, fire, flood'),
('Can I have pets in my unit?', 'Pet policies vary by property. Please check your lease agreement or contact your property manager for specific pet policies and any associated pet deposits or fees.', 'lease', 'pets, dogs, cats, animals, pet policy'),
('How do I give notice that I\`m moving out?', 'Typically, you need to provide 30-60 days written notice before moving out. Please check your lease for the specific notice period required and submit your notice through the tenant portal.', 'lease', 'moving out, vacate, leave, notice, termination');

-- otheer neew message for logs message delivery 
-- Message delivery logs table
CREATE TABLE IF NOT EXISTS message_delivery_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    delivery_method ENUM('email', 'sms') NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
);
