/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.4.4-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: property_management
-- ------------------------------------------------------
-- Server version	11.4.4-MariaDB-3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `ai_insights`
--

DROP TABLE IF EXISTS `ai_insights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_insights` (
  `insight_id` int(11) NOT NULL AUTO_INCREMENT,
  `landlord_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `insight_type` enum('rent_prediction','payment_risk','maintenance_prediction','financial_forecast') NOT NULL,
  `insight_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`insight_data`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`insight_id`),
  KEY `landlord_id` (`landlord_id`),
  KEY `property_id` (`property_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `ai_insights_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_insights_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL,
  CONSTRAINT `ai_insights_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_insights`
--

LOCK TABLES `ai_insights` WRITE;
/*!40000 ALTER TABLE `ai_insights` DISABLE KEYS */;
INSERT INTO `ai_insights` VALUES
(1,2,1,NULL,'payment_risk','{\"risk_score\": 15, \"prediction\": \"Low risk of late payment\", \"confidence\": 85}','2025-04-14 13:04:41'),
(2,2,2,4,'rent_prediction','{\"current_rent\": 3200, \"suggested_rent\": 3400, \"market_analysis\": \"Rents in this area have increased 6% in the last year\"}','2025-04-14 13:04:41'),
(3,2,3,5,'maintenance_prediction','{\"prediction\": \"Water heater may need replacement within 6 months\", \"estimated_cost\": 800, \"priority\": \"medium\"}','2025-04-14 13:04:41'),
(4,2,NULL,NULL,'financial_forecast','{\"monthly_income\": 9700, \"annual_projection\": 116400, \"expense_ratio\": 32, \"roi\": 8.5}','2025-04-14 13:04:41');
/*!40000 ALTER TABLE `ai_insights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leases`
--

DROP TABLE IF EXISTS `leases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leases` (
  `lease_id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) NOT NULL,
  `payment_due_day` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','expired','terminated') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lease_id`),
  KEY `property_id` (`property_id`),
  KEY `unit_id` (`unit_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `leases_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE,
  CONSTRAINT `leases_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL,
  CONSTRAINT `leases_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leases`
--

LOCK TABLES `leases` WRITE;
/*!40000 ALTER TABLE `leases` DISABLE KEYS */;
INSERT INTO `leases` VALUES
(6,8,NULL,4,'2025-04-15','2026-04-15',4.00,4.00,1,'active','2025-04-15 15:59:02','2025-04-15 17:17:15'),
(7,3,NULL,7,'2025-04-14','2025-04-16',1200.00,1200.00,1,'active','2025-04-15 17:24:42','2025-04-15 17:24:42');
/*!40000 ALTER TABLE `leases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_requests`
--

DROP TABLE IF EXISTS `maintenance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
  `status` enum('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `ai_priority_score` int(11) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `property_id` (`property_id`),
  KEY `unit_id` (`unit_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_requests`
--

LOCK TABLES `maintenance_requests` WRITE;
/*!40000 ALTER TABLE `maintenance_requests` DISABLE KEYS */;
INSERT INTO `maintenance_requests` VALUES
(2,2,NULL,4,'Broken AC','Air conditioning unit is not cooling properly','high','in_progress','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL,500.00,NULL,85),
(3,3,4,5,'Clogged Drain','Bathroom sink is draining very slowly','low','completed','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL,100.00,NULL,40),
(7,3,NULL,5,'Detailed information about the maintenance request','The page provides a comprehensive interface for managing maintenance requests, tracking tasks, and updating status information. It also includes proper navigation back to the maintenance list page.','emergency','completed','2025-04-15 15:37:05','2025-04-15 15:38:10','2025-04-15 13:38:10',205.00,NULL,NULL),
(8,8,NULL,4,'erere','ererwerer','high','cancelled','2025-04-17 12:09:28','2025-04-17 21:21:06',NULL,122.00,NULL,70),
(9,8,NULL,4,'This completes the maintenance page with:','The full maintenance request form with all necessary fields\n\nIssue Type: Appliance\nBest Time: Morning\nPermission to Enter: Yes','high','pending','2025-04-17 20:28:33','2025-04-17 20:28:33',NULL,NULL,NULL,60),
(10,8,NULL,4,'tytytytytyt','tyyyyyyyyyy\r\n\r\nIssue Type: Electrical\r\nBest Time: Morning\r\nPermission to Enter: Yes','emergency','pending','2025-04-17 21:06:24','2025-04-17 21:19:09',NULL,NULL,NULL,70);
/*!40000 ALTER TABLE `maintenance_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_tasks`
--

DROP TABLE IF EXISTS `maintenance_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `request_id` (`request_id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `maintenance_tasks_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_tasks`
--

LOCK TABLES `maintenance_tasks` WRITE;
/*!40000 ALTER TABLE `maintenance_tasks` DISABLE KEYS */;
INSERT INTO `maintenance_tasks` VALUES
(2,2,2,'Inspect AC unit and recharge refrigerant if needed','in_progress','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL),
(3,3,2,'Clear drain using snake and chemical cleaner','completed','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL),
(5,7,2,'existing tasks as completed','pending','2025-04-15 15:38:06','2025-04-15 15:38:06',NULL),
(7,8,NULL,'Initial assessment needed','pending','2025-04-17 12:09:28','2025-04-17 12:09:28',NULL);
/*!40000 ALTER TABLE `maintenance_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_threads`
--

DROP TABLE IF EXISTS `message_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_threads` (
  `thread_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`thread_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_threads`
--

LOCK TABLES `message_threads` WRITE;
/*!40000 ALTER TABLE `message_threads` DISABLE KEYS */;
INSERT INTO `message_threads` VALUES
(1,'Ndashaka inama kuri it registration company','2025-04-17 18:21:01','2025-04-17 20:55:59');
/*!40000 ALTER TABLE `message_threads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('portal','email','sms','both') NOT NULL DEFAULT 'portal',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `thread_id` (`thread_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES
(1,NULL,2,4,'Ndashaka inama kuri it registration company','ghghghhggh','portal',0,'2025-04-17 10:26:10'),
(2,NULL,2,4,'test','ghghghghgh','email',0,'2025-04-17 10:27:13'),
(3,NULL,2,4,'Ndashaka inama kuri it registration company','Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company','portal',0,'2025-04-17 10:30:10'),
(4,NULL,2,7,'Ndashaka inama kuri it registration company','Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company','portal',0,'2025-04-17 10:30:27'),
(5,NULL,2,7,'Ndashaka inama kuri it registration company','// If no errors, send the message\r\nif (empty($errors)) {\r\n    try {\r\n        // Record the message in the database\r\n        $stmt = $pdo->prepare(\"\r\n            INSERT INTO messages (\r\n                sender_id, recipient_id, subject, message, message_type, created_at\r\n            ) VALUES (\r\n                :senderId, :recipientId, :subject, :message, \'portal\', NOW()\r\n            )\r\n        \");\r\n        \r\n        $stmt->execute([\r\n            \'senderId\' => $userId,\r\n            \'recipientId\' => $tenantId,\r\n            \'subject\' => $subject,\r\n            \'message\' => $message\r\n        ]);\r\n        \r\n        // Create a notification for the tenant\r\n        $stmt = $pdo->prepare(\"\r\n            INSERT INTO notifications (\r\n                user_id, title, message, type, is_read, created_at\r\n            ) VALUES (\r\n                :userId, :title, :message, \'general\', 0, NOW()\r\n            )\r\n        \");\r\n        \r\n        $stmt->execute([\r\n            \'userId\' => $tenantId,\r\n            \'title\' => \'New message from landlord\',\r\n            \'message\' => \'You have received a new message: \' . $subject\r\n        ]);\r\n        \r\n        $success = true;\r\n        \r\n    } catch (PDOException $e) {\r\n        $errors[] = \"Database error: \" . $e->getMessage();\r\n    }\r\n}','portal',0,'2025-04-17 10:32:53'),
(6,1,2,4,'Ndashaka inama kuri it registration company','Ndashaka inama kuri it registration company','portal',0,'2025-04-17 18:21:01'),
(7,1,2,4,'Ndashaka inama kuri it registration company','Ndashaka inama kuri it registration company','portal',0,'2025-04-17 18:21:43'),
(8,1,2,4,'RE: Ndashaka inama kuri it registration company','ok, umz utese','portal',0,'2025-04-17 18:34:10'),
(9,1,2,4,'RE: Ndashaka inama kuri it registration company','Scroll to the bottom','portal',0,'2025-04-17 18:37:19'),
(10,1,4,2,'Ndashaka inama kuri it registration company','Database error: SQLSTATE[HY000]: General error: 1364 Field \'recipient_id\' doesn\'t have a default value','portal',0,'2025-04-17 20:50:19'),
(11,1,4,2,'Ndashaka inama kuri it registration company','dfdfdfdfdfdf','portal',0,'2025-04-17 20:52:22'),
(12,1,4,2,'Ndashaka inama kuri it registration company','Scroll to the bottom','portal',0,'2025-04-17 20:52:32'),
(13,1,4,2,'Ndashaka inama kuri it registration company','dfdfdfdfdfdf','portal',0,'2025-04-17 20:55:59'),
(14,NULL,4,2,'Maintenance #8','I have cancelled this maintenance request.','portal',0,'2025-04-17 21:21:06');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('payment','maintenance','lease','general') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES
(4,4,'Rent Due Reminder','Your rent payment of $3200 is due in 3 days','payment',0,'2025-04-14 13:04:41'),
(5,4,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 10:26:10'),
(6,4,'New message from landlord','You have received a new message: test','general',0,'2025-04-17 10:27:13'),
(7,4,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 10:30:10'),
(8,7,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 10:30:27'),
(9,7,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 10:32:53'),
(10,4,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 18:21:01'),
(11,4,'New message from landlord','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 18:21:43'),
(12,4,'New message','You have received a new message in the conversation: Ndashaka inama kuri it registration company','general',0,'2025-04-17 18:34:10'),
(13,4,'New message','You have received a new message in the conversation: Ndashaka inama kuri it registration company','general',0,'2025-04-17 18:37:19'),
(14,2,'Payment Received','Payment of $10.00 received from Mike Wilson for house gkh e','payment',0,'2025-04-17 20:19:25'),
(15,2,'New Maintenance Request','New maintenance request from Mike Wilson for house gkh e: This completes the maintenance page with:','maintenance',0,'2025-04-17 20:28:33'),
(16,2,'New message from Mike Wilson','You have received a new message regarding: Ndashaka inama kuri it registration company','general',0,'2025-04-17 20:50:19'),
(17,2,'New message from Mike Wilson','You have received a new message regarding: Ndashaka inama kuri it registration company','general',0,'2025-04-17 20:52:22'),
(18,2,'New message from Mike Wilson','You have received a new message regarding: Ndashaka inama kuri it registration company','general',0,'2025-04-17 20:52:32'),
(19,2,'New message from tenant','You have received a new message: Ndashaka inama kuri it registration company','general',0,'2025-04-17 20:55:59'),
(20,2,'New Maintenance Request','New maintenance request from Mike Wilson for house gkh e: tytytytytyt','maintenance',0,'2025-04-17 21:06:24'),
(21,2,'Maintenance request updated','A tenant has updated maintenance request #10','maintenance',0,'2025-04-17 21:19:09'),
(22,2,'Maintenance request cancelled','Maintenance request #8 has been cancelled by the tenant','maintenance',0,'2025-04-17 21:21:06');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_valid` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES
(1,'abayosincere11@gmail.com','2cee037c2331196647407de231af20a6194b42a96e92dc87f6c0902db9a1aac6','2025-04-17 19:59:39',0,'2025-04-17 18:59:39'),
(2,'abayosincere11@gmail.com','7ce4e1c77e2a5431b0638d1a1cbe2d5f06c8468001bd412af36536b429235c63','2025-04-17 19:59:57',0,'2025-04-17 18:59:57'),
(3,'john@example.com','c718c342716b88d37a5968dcbf75ea89c4987df87f1213c95574d702238a0218','2025-04-17 20:00:19',0,'2025-04-17 19:00:19'),
(4,'john@example.com','6752c9559a884c923e389a0f5023e5f7e0636ac1ac92d7a00f168e21b50d21c1','2025-04-17 20:01:21',1,'2025-04-17 19:01:21'),
(5,'abayosincere11@gmail.com','cd0a31ef6ab1261b454ac79424c30eeff9ac5629e2f53c4d7f9e668849e4609a','2025-04-17 20:24:11',0,'2025-04-17 19:24:11'),
(6,'abayosincere11@gmail.com','b9a3a333e34a53febed9b48fc8cb856ed38fbb1fe622a87cee7c279efbd4dc38','2025-04-17 20:24:30',0,'2025-04-17 19:24:30'),
(7,'abayosincere11@gmail.com','2b2586af73ec14b3f6a81c7ca599b628d2bf809c40984b1ca1e5ee3b384efa51','2025-04-17 20:31:58',1,'2025-04-17 19:31:58');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_audit`
--

DROP TABLE IF EXISTS `payment_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_audit` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `action` enum('create','update','void','restore') NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_reason` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `payment_id` (`payment_id`),
  KEY `action_by` (`action_by`),
  CONSTRAINT `payment_audit_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE CASCADE,
  CONSTRAINT `payment_audit_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_audit`
--

LOCK TABLES `payment_audit` WRITE;
/*!40000 ALTER TABLE `payment_audit` DISABLE KEYS */;
INSERT INTO `payment_audit` VALUES
(1,19,'void',2,'Voiding a payment will m','2025-04-17 11:06:21'),
(2,16,'void',2,'ment will mark it as invalid but keep it in the system for record-k','2025-04-17 11:22:33'),
(3,16,'restore',2,'Payment restored','2025-04-17 11:30:00'),
(4,19,'restore',2,'Payment restored','2025-04-17 11:48:59');
/*!40000 ALTER TABLE `payment_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `lease_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','other') NOT NULL,
  `payment_type` enum('rent','security_deposit','late_fee','other') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('active','voided') DEFAULT 'active',
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `lease_id` (`lease_id`),
  KEY `voided_by` (`voided_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`lease_id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES
(13,6,4.00,'2025-04-10','check','security_deposit','changes accomplish the following:','2025-04-15 16:20:32','active',NULL,NULL,NULL),
(14,6,4.00,'2025-04-15','cash','rent','gjhjhhh','2025-04-15 17:18:15','active',NULL,NULL,NULL),
(15,6,4.00,'2025-04-13','cash','late_fee','','2025-04-16 13:48:22','active',NULL,NULL,NULL),
(16,6,4.00,'2025-04-16','cash','other','ff','2025-04-16 13:55:44','active',NULL,NULL,NULL),
(17,6,4.00,'2025-04-16','cash','late_fee','gff','2025-04-16 13:56:22','active',NULL,NULL,NULL),
(18,6,1.00,'2025-04-15','cash','security_deposit','','2025-04-17 08:54:56','active',NULL,NULL,NULL),
(19,7,1200.00,'2025-04-17','credit_card','late_fee','','2025-04-17 10:13:30','active',NULL,NULL,NULL),
(20,6,10.00,'2025-04-10','bank_transfer','rent','Payment recorded by tenant. Reference: 255656','2025-04-17 20:19:25','active',NULL,NULL,NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL AUTO_INCREMENT,
  `landlord_id` int(11) NOT NULL,
  `property_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `property_type` enum('apartment','house','condo','studio','commercial') NOT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` decimal(3,1) DEFAULT NULL,
  `square_feet` int(11) DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('vacant','occupied','maintenance') DEFAULT 'vacant',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`property_id`),
  KEY `landlord_id` (`landlord_id`),
  CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
INSERT INTO `properties` VALUES
(1,2,'Modern Apartment','123 Main St','New York','NY','10001','apartment',2,2.0,1200,2500.00,'Beautiful modern apartment in downtown','maintenance','2025-04-14 13:04:41','2025-04-15 15:30:40',NULL),
(2,2,'Cozy House','456 Oak Ave','Los Angeles','CA','90001','house',3,2.5,1800,3200.00,'Spacious family home with backyard','occupied','2025-04-14 13:04:41','2025-04-15 17:42:06',NULL),
(3,2,'Studio Apartment','789 Pine St','Chicago','IL','60601','studio',1,1.0,600,1200.00,'Compact studio in city center','occupied','2025-04-14 13:04:41','2025-04-15 15:55:06','uploads/properties/property_67fe815a576dd.jpg'),
(4,2,'Luxury Condo','101 River Rd','Miami','FL','33101','condo',3,3.0,2000,4000.00,'High-end condo with ocean view','vacant','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL),
(5,6,'Suburban Home','202 Maple Dr','Seattle','WA','98101','house',4,3.0,2400,3500.00,'Family home in quiet neighborhood','occupied','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL),
(6,6,'Downtown Loft','303 Elm St','Denver','CO','80201','apartment',2,2.0,1500,2800.00,'Modern loft with city views','vacant','2025-04-14 13:04:41','2025-04-14 13:04:41',NULL),
(8,2,'house gkh e','gahanga','Kigali','kigali','0000','house',4,4.0,4,4.00,'ererereer','vacant','2025-04-15 14:55:31','2025-04-15 15:59:33','uploads/properties/property_67fe7363b5c3b.jpg');
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `thread_participants`
--

DROP TABLE IF EXISTS `thread_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thread_participants` (
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`thread_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `thread_participants_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE,
  CONSTRAINT `thread_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `thread_participants`
--

LOCK TABLES `thread_participants` WRITE;
/*!40000 ALTER TABLE `thread_participants` DISABLE KEYS */;
INSERT INTO `thread_participants` VALUES
(1,2,0),
(1,4,1);
/*!40000 ALTER TABLE `thread_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `unit_number` varchar(20) NOT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` decimal(3,1) DEFAULT NULL,
  `square_feet` int(11) DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `status` enum('vacant','occupied','maintenance') DEFAULT 'vacant',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`unit_id`),
  KEY `property_id` (`property_id`),
  CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES
(1,1,'101',1,1.0,800,1800.00,'occupied','2025-04-14 13:04:41','2025-04-14 13:04:41'),
(2,1,'102',2,2.0,1200,2500.00,'vacant','2025-04-14 13:04:41','2025-04-14 13:04:41'),
(3,1,'103',1,1.0,850,1900.00,'occupied','2025-04-14 13:04:41','2025-04-14 13:04:41'),
(4,3,'A',1,1.0,600,1200.00,'occupied','2025-04-14 13:04:41','2025-04-14 13:04:41'),
(5,3,'B',1,1.0,620,1250.00,'vacant','2025-04-14 13:04:41','2025-04-14 13:04:41');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('landlord','tenant','admin') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 ;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'admin@example.com','password123','Admin','User','555-123-4567','landlord','2025-04-14 13:04:41','2025-04-15 07:39:11'),
(2,'john@example.com','password123','John','Smith','555-987-6543','landlord','2025-04-14 13:04:41','2025-04-26 21:17:31'),
(4,'mike@example.com','password123','Mike','Wilson','555-789-0123','tenant','2025-04-14 13:04:41','2025-04-17 21:30:10'),
(5,'lisa@example.com','password123','Lisa','Brown','555-234-5678','tenant','2025-04-14 13:04:41','2025-04-15 07:37:24'),
(6,'david@example.com','password123','David','Miller','555-345-6789','landlord','2025-04-14 13:04:41','2025-04-15 07:37:24'),
(7,'abayosincere11@gmail.com','$2y$10$YDD5KaUjPdw1R5zEAfq2X.sOf9zoTIkp3zcHyDlLoKfaAZF1KUtCu','Margot','Margot','0732286284','tenant','2025-04-15 17:24:42','2025-04-17 19:38:54');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-04-26 23:21:12
