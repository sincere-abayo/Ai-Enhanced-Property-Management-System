-- Insert sample users (passwords are hashed version of 'password123')
INSERT INTO users (email, password, first_name, last_name, phone, role) VALUES
('admin@example.com', 'password123', 'Admin', 'User', '555-123-4567', 'admin'),
('john@example.com', 'password123', 'John', 'Smith', '555-987-6543', 'landlord'),
('sarah@example.com', 'password123', 'Sarah', 'Johnson', '555-456-7890', 'tenant'),
('mike@example.com', 'password123', 'Mike', 'Wilson', '555-789-0123', 'tenant'),
('lisa@example.com', 'password123', 'Lisa', 'Brown', '555-234-5678', 'tenant'),
('david@example.com', 'password123', 'David', 'Miller', '555-345-6789', 'landlord');

-- Insert sample properties
INSERT INTO properties (landlord_id, property_name, address, city, state, zip_code, property_type, bedrooms, bathrooms, square_feet, monthly_rent, description, status) VALUES
(2, 'Modern Apartment', '123 Main St', 'New York', 'NY', '10001', 'apartment', 2, 2.0, 1200, 2500.00, 'Beautiful modern apartment in downtown', 'occupied'),
(2, 'Cozy House', '456 Oak Ave', 'Los Angeles', 'CA', '90001', 'house', 3, 2.5, 1800, 3200.00, 'Spacious family home with backyard', 'occupied'),
(2, 'Studio Apartment', '789 Pine St', 'Chicago', 'IL', '60601', 'studio', 1, 1.0, 600, 1200.00, 'Compact studio in city center', 'occupied'),
(2, 'Luxury Condo', '101 River Rd', 'Miami', 'FL', '33101', 'condo', 3, 3.0, 2000, 4000.00, 'High-end condo with ocean view', 'vacant'),
(6, 'Suburban Home', '202 Maple Dr', 'Seattle', 'WA', '98101', 'house', 4, 3.0, 2400, 3500.00, 'Family home in quiet neighborhood', 'occupied'),
(6, 'Downtown Loft', '303 Elm St', 'Denver', 'CO', '80201', 'apartment', 2, 2.0, 1500, 2800.00, 'Modern loft with city views', 'vacant');

-- Insert sample units (for multi-unit properties)
INSERT INTO units (property_id, unit_number, bedrooms, bathrooms, square_feet, monthly_rent, status) VALUES
(1, '101', 1, 1.0, 800, 1800.00, 'occupied'),
(1, '102', 2, 2.0, 1200, 2500.00, 'vacant'),
(1, '103', 1, 1.0, 850, 1900.00, 'occupied'),
(3, 'A', 1, 1.0, 600, 1200.00, 'occupied'),
(3, 'B', 1, 1.0, 620, 1250.00, 'vacant');

-- Insert sample leases
INSERT INTO leases (property_id, unit_id, tenant_id, start_date, end_date, monthly_rent, security_deposit, payment_due_day, status) VALUES
(1, 1, 3, '2023-01-01', '2024-01-01', 1800.00, 1800.00, 1, 'active'),
(2, NULL, 4, '2023-02-15', '2024-02-15', 3200.00, 3200.00, 1, 'active'),
(3, 4, 5, '2023-03-01', '2024-03-01', 1200.00, 1200.00, 1, 'active'),
(5, NULL, 3, '2023-04-01', '2024-04-01', 3500.00, 3500.00, 1, 'active');

-- Insert sample payments
INSERT INTO payments (lease_id, amount, payment_date, payment_method, payment_type, notes) VALUES
(1, 1800.00, '2023-05-01', 'bank_transfer', 'rent', 'May rent payment'),
(1, 1800.00, '2023-06-01', 'bank_transfer', 'rent', 'June rent payment'),
(1, 1800.00, '2023-07-01', 'bank_transfer', 'rent', 'July rent payment'),
(2, 3200.00, '2023-05-01', 'check', 'rent', 'May rent payment'),
(2, 3200.00, '2023-06-01', 'check', 'rent', 'June rent payment'),
(2, 3200.00, '2023-07-01', 'check', 'rent', 'July rent payment'),
(3, 1200.00, '2023-05-01', 'cash', 'rent', 'May rent payment'),
(3, 1200.00, '2023-06-01', 'cash', 'rent', 'June rent payment'),
(4, 3500.00, '2023-05-01', 'bank_transfer', 'rent', 'May rent payment'),
(4, 3500.00, '2023-06-01', 'bank_transfer', 'rent', 'June rent payment');

-- Insert sample maintenance requests
INSERT INTO maintenance_requests (property_id, unit_id, tenant_id, title, description, priority, status, estimated_cost, ai_priority_score) VALUES
(1, 1, 3, 'Leaking Faucet', 'The kitchen faucet is leaking and needs repair', 'medium', 'pending', 150.00, 65),
(2, NULL, 4, 'Broken AC', 'Air conditioning unit is not cooling properly', 'high', 'in_progress', 500.00, 85),
(3, 4, 5, 'Clogged Drain', 'Bathroom sink is draining very slowly', 'low', 'completed', 100.00, 40),
(5, NULL, 3, 'Electrical Issue', 'Power outlets in living room not working', 'high', 'assigned', 300.00, 80);

-- Insert sample maintenance tasks
INSERT INTO maintenance_tasks (request_id, assigned_to, description, status) VALUES
(1, NULL, 'Replace faucet washer and check for leaks', 'pending'),
(2, 2, 'Inspect AC unit and recharge refrigerant if needed', 'in_progress'),
(3, 2, 'Clear drain using snake and chemical cleaner', 'completed'),
(4, 6, 'Check circuit breaker and inspect outlets', 'pending');

-- Insert sample notifications
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(2, 'New Maintenance Request', 'Sarah Johnson has submitted a new maintenance request for Modern Apartment', 'maintenance', FALSE),
(2, 'Rent Payment Received', 'Rent payment of $1800 received from Sarah Johnson', 'payment', TRUE),
(3, 'Maintenance Update', 'Your maintenance request for Leaking Faucet has been assigned', 'maintenance', FALSE),
(4, 'Rent Due Reminder', 'Your rent payment of $3200 is due in 3 days', 'payment', FALSE);

-- Insert sample AI insights
INSERT INTO ai_insights (landlord_id, property_id, tenant_id, insight_type, insight_data) VALUES
(2, 1, 3, 'payment_risk', '{"risk_score": 15, "prediction": "Low risk of late payment", "confidence": 85}'),
(2, 2, 4, 'rent_prediction', '{"current_rent": 3200, "suggested_rent": 3400, "market_analysis": "Rents in this area have increased 6% in the last year"}'),
(2, 3, 5, 'maintenance_prediction', '{"prediction": "Water heater may need replacement within 6 months", "estimated_cost": 800, "priority": "medium"}'),
(2, NULL, NULL, 'financial_forecast', '{"monthly_income": 9700, "annual_projection": 116400, "expense_ratio": 32, "roi": 8.5}');
