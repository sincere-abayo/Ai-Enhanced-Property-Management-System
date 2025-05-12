-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 08:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `property_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_insights`
--

CREATE TABLE `ai_insights` (
  `insight_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `insight_type` enum('rent_prediction','payment_risk','maintenance_prediction','financial_forecast') NOT NULL,
  `insight_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`insight_data`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_insights`
--

INSERT INTO `ai_insights` (`insight_id`, `landlord_id`, `property_id`, `tenant_id`, `insight_type`, `insight_data`, `created_at`) VALUES
(1, 2, 1, NULL, 'payment_risk', '{\"risk_score\": 15, \"prediction\": \"Low risk of late payment\", \"confidence\": 85}', '2025-04-14 13:04:41'),
(2, 2, 2, 4, 'rent_prediction', '{\"current_rent\": 3200, \"suggested_rent\": 3400, \"market_analysis\": \"Rents in this area have increased 6% in the last year\"}', '2025-04-14 13:04:41'),
(3, 2, 3, 5, 'maintenance_prediction', '{\"prediction\": \"Water heater may need replacement within 6 months\", \"estimated_cost\": 800, \"priority\": \"medium\"}', '2025-04-14 13:04:41'),
(4, 2, NULL, NULL, 'financial_forecast', '{\"monthly_income\": 9700, \"annual_projection\": 116400, \"expense_ratio\": 32, \"roi\": 8.5}', '2025-04-14 13:04:41');

-- --------------------------------------------------------

--
-- Table structure for table `leases`
--

CREATE TABLE `leases` (
  `lease_id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leases`
--

INSERT INTO `leases` (`lease_id`, `property_id`, `unit_id`, `tenant_id`, `start_date`, `end_date`, `monthly_rent`, `security_deposit`, `payment_due_day`, `status`, `created_at`, `updated_at`) VALUES
(6, 8, NULL, 4, '2025-04-15', '2026-04-15', 4.00, 4.00, 1, 'active', '2025-04-15 15:59:02', '2025-04-15 17:17:15'),
(9, 2, NULL, 8, '2025-04-01', '2025-04-30', 3200.00, 3200.00, 1, 'active', '2025-04-27 18:21:35', '2025-04-27 18:21:35'),
(10, 10, NULL, 8, '2025-04-29', '2026-04-29', 400.00, 400.00, 1, 'active', '2025-04-29 17:22:20', '2025-04-29 17:22:20');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
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
  `ai_priority_score` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`request_id`, `property_id`, `unit_id`, `tenant_id`, `title`, `description`, `priority`, `status`, `created_at`, `updated_at`, `completed_at`, `estimated_cost`, `actual_cost`, `ai_priority_score`) VALUES
(2, 2, NULL, 4, 'Broken AC', 'Air conditioning unit is not cooling properly', 'high', 'in_progress', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL, 500.00, NULL, 85),
(3, 3, 4, 5, 'Clogged Drain', 'Bathroom sink is draining very slowly', 'low', 'completed', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL, 100.00, NULL, 40),
(7, 3, NULL, 5, 'Detailed information about the maintenance request', 'The page provides a comprehensive interface for managing maintenance requests, tracking tasks, and updating status information. It also includes proper navigation back to the maintenance list page.', 'emergency', 'completed', '2025-04-15 15:37:05', '2025-04-15 15:38:10', '2025-04-15 13:38:10', 205.00, NULL, NULL),
(8, 8, NULL, 4, 'Leaking Bathroom Sink', 'The sink in the master bathroom is leaking heavily from the pipe underneath. Water is pooling on the floor. Immediate plumbing service is required to avoid further damage.', 'high', 'cancelled', '2025-04-17 12:09:28', '2025-04-27 18:26:45', NULL, 122.00, NULL, 70),
(9, 8, NULL, 4, 'This completes the maintenance page with:', 'The full maintenance request form with all necessary fields\r\n\r\nIssue Type: Appliance\r\nBest Time: Morning\r\nPermission to Enter: Yes', 'high', 'completed', '2025-04-17 20:28:33', '2025-04-29 16:15:24', '2025-04-29 15:15:24', NULL, NULL, 60),
(10, 8, NULL, 4, 'Power Outage in Kitchen Area', 'The kitchen area has experienced a complete power outage. Electrical sockets and lighting are not functioning. Immediate inspection and repair are needed to restore', 'emergency', 'completed', '2025-04-17 21:06:24', '2025-04-29 17:28:55', '2025-04-29 16:28:33', 30.00, NULL, 70),
(11, 8, NULL, 4, 'Kitchen leakage', 'tgegegwga\n\nIssue Type: Pest\nBest Time: Afternoon\nPermission to Enter: Yes', 'high', 'pending', '2025-04-29 17:33:05', '2025-04-29 17:33:05', NULL, NULL, NULL, 60);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tasks`
--

CREATE TABLE `maintenance_tasks` (
  `task_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_tasks`
--

INSERT INTO `maintenance_tasks` (`task_id`, `request_id`, `assigned_to`, `description`, `status`, `created_at`, `updated_at`, `completed_at`) VALUES
(2, 2, 2, 'Inspect AC unit and recharge refrigerant if needed', 'in_progress', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL),
(3, 3, 2, 'Clear drain using snake and chemical cleaner', 'completed', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL),
(5, 7, 2, 'existing tasks as completed', 'pending', '2025-04-15 15:38:06', '2025-04-15 15:38:06', NULL),
(7, 8, NULL, 'Initial assessment needed', 'pending', '2025-04-17 12:09:28', '2025-04-17 12:09:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('portal','email','sms','both') NOT NULL DEFAULT 'portal',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `thread_id`, `sender_id`, `recipient_id`, `subject`, `message`, `message_type`, `is_read`, `created_at`) VALUES
(1, NULL, 2, 4, 'Ndashaka inama kuri it registration company', 'ghghghhggh', 'portal', 0, '2025-04-17 10:26:10'),
(2, NULL, 2, 4, 'test', 'ghghghghgh', 'email', 0, '2025-04-17 10:27:13'),
(3, NULL, 2, 4, 'Ndashaka inama kuri it registration company', 'Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company Ndashaka inama kuri it registration company', 'portal', 0, '2025-04-17 10:30:10'),
(6, 1, 2, 4, 'Ndashaka inama kuri it registration company', 'Ndashaka inama kuri it registration company', 'portal', 0, '2025-04-17 18:21:01'),
(7, 1, 2, 4, 'Ndashaka inama kuri it registration company', 'Ndashaka inama kuri it registration company', 'portal', 0, '2025-04-17 18:21:43'),
(8, 1, 2, 4, 'RE: Ndashaka inama kuri it registration company', 'ok, umz utese', 'portal', 0, '2025-04-17 18:34:10'),
(9, 1, 2, 4, 'RE: Ndashaka inama kuri it registration company', 'Scroll to the bottom', 'portal', 0, '2025-04-17 18:37:19'),
(10, 1, 4, 2, 'Ndashaka inama kuri it registration company', 'Database error: SQLSTATE[HY000]: General error: 1364 Field \'recipient_id\' doesn\'t have a default value', 'portal', 0, '2025-04-17 20:50:19'),
(11, 1, 4, 2, 'Ndashaka inama kuri it registration company', 'dfdfdfdfdfdf', 'portal', 0, '2025-04-17 20:52:22'),
(12, 1, 4, 2, 'Ndashaka inama kuri it registration company', 'Scroll to the bottom', 'portal', 0, '2025-04-17 20:52:32'),
(13, 1, 4, 2, 'Ndashaka inama kuri it registration company', 'dfdfdfdfdfdf', 'portal', 0, '2025-04-17 20:55:59'),
(14, NULL, 4, 2, 'Maintenance #8', 'I have cancelled this maintenance request.', 'portal', 0, '2025-04-17 21:21:06'),
(15, 1, 2, 4, 'This house  is under maintanance', 'This issue is in progress, wait patiently', 'portal', 0, '2025-04-27 18:30:02'),
(16, NULL, 4, 2, 'Maintenance #9', 'I appreciate to let me know', 'portal', 0, '2025-04-27 18:32:22'),
(17, NULL, 4, 2, 'Maintenance #9', 'This will be soon hopefully', 'portal', 0, '2025-04-27 18:32:51'),
(18, 2, 4, 2, 'Please checked my receipt', 'I have sent you an email of receipt', 'portal', 0, '2025-04-27 18:34:16'),
(19, 3, 8, 2, 'Delaying Of The Payment', 'Accept my apology for the delaying the payment', 'portal', 0, '2025-04-29 16:21:31'),
(21, 1, 2, 4, 'checkkk', 'hey chhhhh', 'portal', 0, '2025-04-29 17:26:01'),
(22, NULL, 4, 2, 'Maintenance #2', 'sggeryrfgthf', 'portal', 0, '2025-04-29 17:33:36'),
(23, 5, 4, 2, 'Delaying Of The Payment', 'wfefsvgv', 'portal', 0, '2025-04-29 17:34:54'),
(24, 3, 2, 8, 'Delaying Of The Payment', 'oooooio', 'portal', 0, '2025-04-29 17:40:06'),
(25, 5, 2, 4, 'RE: Delaying Of The Payment', 'ok', 'portal', 0, '2025-04-29 17:58:00'),
(26, 1, 2, 4, 'RE: checkkk', 'Hello landlord', 'portal', 0, '2025-04-29 18:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `message_delivery_logs`
--

CREATE TABLE `message_delivery_logs` (
  `log_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `delivery_method` enum('email','sms') NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_threads`
--

CREATE TABLE `message_threads` (
  `thread_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_threads`
--

INSERT INTO `message_threads` (`thread_id`, `subject`, `created_at`, `updated_at`) VALUES
(1, 'checkkk', '2025-04-17 18:21:01', '2025-04-29 18:42:08'),
(2, 'Please checked my receipt', '2025-04-27 18:34:16', '2025-04-27 18:34:16'),
(3, 'Delaying Of The Payment', '2025-04-29 16:21:31', '2025-04-29 17:40:06'),
(4, 'Please pay it is already the date', '2025-04-29 17:25:06', '2025-04-29 17:25:06'),
(5, 'Delaying Of The Payment', '2025-04-29 17:34:54', '2025-04-29 17:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('payment','maintenance','lease','general') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(4, 4, 'Rent Due Reminder', 'Your rent payment of $3200 is due in 3 days', 'payment', 0, '2025-04-14 13:04:41'),
(5, 4, 'New message from landlord', 'You have received a new message: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 10:26:10'),
(6, 4, 'New message from landlord', 'You have received a new message: test', 'general', 0, '2025-04-17 10:27:13'),
(7, 4, 'New message from landlord', 'You have received a new message: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 10:30:10'),
(10, 4, 'New message from landlord', 'You have received a new message: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 18:21:01'),
(11, 4, 'New message from landlord', 'You have received a new message: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 18:21:43'),
(12, 4, 'New message', 'You have received a new message in the conversation: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 18:34:10'),
(13, 4, 'New message', 'You have received a new message in the conversation: Ndashaka inama kuri it registration company', 'general', 0, '2025-04-17 18:37:19'),
(14, 2, 'Payment Received', 'Payment of $10.00 received from Mike Wilson for house gkh e', 'payment', 1, '2025-04-17 20:19:25'),
(15, 2, 'New Maintenance Request', 'New maintenance request from Mike Wilson for house gkh e: This completes the maintenance page with:', 'maintenance', 1, '2025-04-17 20:28:33'),
(16, 2, 'New message from Mike Wilson', 'You have received a new message regarding: Ndashaka inama kuri it registration company', 'general', 1, '2025-04-17 20:50:19'),
(17, 2, 'New message from Mike Wilson', 'You have received a new message regarding: Ndashaka inama kuri it registration company', 'general', 1, '2025-04-17 20:52:22'),
(18, 2, 'New message from Mike Wilson', 'You have received a new message regarding: Ndashaka inama kuri it registration company', 'general', 1, '2025-04-17 20:52:32'),
(19, 2, 'New message from tenant', 'You have received a new message: Ndashaka inama kuri it registration company', 'general', 1, '2025-04-17 20:55:59'),
(20, 2, 'New Maintenance Request', 'New maintenance request from Mike Wilson for house gkh e: tytytytytyt', 'maintenance', 1, '2025-04-17 21:06:24'),
(21, 2, 'Maintenance request updated', 'A tenant has updated maintenance request #10', 'maintenance', 1, '2025-04-17 21:19:09'),
(22, 2, 'Maintenance request cancelled', 'Maintenance request #8 has been cancelled by the tenant', 'maintenance', 1, '2025-04-17 21:21:06'),
(23, 4, 'New message from landlord', 'You have received a new message: This house  is under maintanance', 'general', 0, '2025-04-27 18:30:02'),
(24, 2, 'New comment on maintenance request', 'A tenant has added a comment to maintenance request #9', 'maintenance', 1, '2025-04-27 18:32:22'),
(25, 2, 'New comment on maintenance request', 'A tenant has added a comment to maintenance request #9', 'maintenance', 1, '2025-04-27 18:32:51'),
(26, 2, 'New message from tenant', 'You have received a new message: Please checked my receipt', 'general', 1, '2025-04-27 18:34:16'),
(27, 2, 'New message from tenant', 'You have received a new message: Delaying Of The Payment', 'general', 1, '2025-04-29 16:21:31'),
(29, 4, 'New message from landlord', 'You have received a new message: checkkk', 'general', 0, '2025-04-29 17:26:01'),
(30, 2, 'New Maintenance Request', 'New maintenance request from Mike Wilson for Modern 4-Bedroom Family House: Kitchen leakage', 'maintenance', 1, '2025-04-29 17:33:05'),
(31, 2, 'New comment on maintenance request', 'A tenant has added a comment to maintenance request #2', 'maintenance', 1, '2025-04-29 17:33:36'),
(32, 2, 'New message from tenant', 'You have received a new message: Delaying Of The Payment', 'general', 1, '2025-04-29 17:34:54'),
(33, 8, 'New message from landlord', 'You have received a new message: Delaying Of The Payment', 'general', 0, '2025-04-29 17:40:06'),
(34, 4, 'New message', 'You have received a new message in the conversation: Delaying Of The Payment', 'general', 0, '2025-04-29 17:58:00'),
(35, 4, 'New message', 'You have received a new message in the conversation: checkkk', 'general', 0, '2025-04-29 18:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_valid` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `is_valid`, `created_at`) VALUES
(1, 'abayosincere11@gmail.com', '2cee037c2331196647407de231af20a6194b42a96e92dc87f6c0902db9a1aac6', '2025-04-17 19:59:39', 0, '2025-04-17 18:59:39'),
(2, 'abayosincere11@gmail.com', '7ce4e1c77e2a5431b0638d1a1cbe2d5f06c8468001bd412af36536b429235c63', '2025-04-17 19:59:57', 0, '2025-04-17 18:59:57'),
(3, 'john@example.com', 'c718c342716b88d37a5968dcbf75ea89c4987df87f1213c95574d702238a0218', '2025-04-17 20:00:19', 0, '2025-04-17 19:00:19'),
(4, 'john@example.com', '6752c9559a884c923e389a0f5023e5f7e0636ac1ac92d7a00f168e21b50d21c1', '2025-04-17 20:01:21', 1, '2025-04-17 19:01:21'),
(5, 'abayosincere11@gmail.com', 'cd0a31ef6ab1261b454ac79424c30eeff9ac5629e2f53c4d7f9e668849e4609a', '2025-04-17 20:24:11', 0, '2025-04-17 19:24:11'),
(6, 'abayosincere11@gmail.com', 'b9a3a333e34a53febed9b48fc8cb856ed38fbb1fe622a87cee7c279efbd4dc38', '2025-04-17 20:24:30', 0, '2025-04-17 19:24:30'),
(7, 'abayosincere11@gmail.com', '2b2586af73ec14b3f6a81c7ca599b628d2bf809c40984b1ca1e5ee3b384efa51', '2025-04-17 20:31:58', 1, '2025-04-17 19:31:58');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
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
  `void_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `lease_id`, `amount`, `payment_date`, `payment_method`, `payment_type`, `notes`, `created_at`, `status`, `voided_at`, `voided_by`, `void_reason`) VALUES
(13, 6, 4.00, '2025-04-10', 'check', 'security_deposit', 'changes accomplish the following:', '2025-04-15 16:20:32', 'active', NULL, NULL, NULL),
(14, 6, 4.00, '2025-04-15', 'cash', 'rent', 'gjhjhhh', '2025-04-15 17:18:15', 'active', NULL, NULL, NULL),
(15, 6, 4.00, '2025-04-13', 'cash', 'late_fee', '', '2025-04-16 13:48:22', 'active', NULL, NULL, NULL),
(16, 6, 600.00, '2025-04-16', 'cash', 'other', 'ff', '2025-04-16 13:55:44', 'active', NULL, NULL, NULL),
(17, 6, 4.00, '2025-04-16', 'cash', 'late_fee', 'gff', '2025-04-16 13:56:22', 'active', NULL, NULL, NULL),
(18, 6, 1.00, '2025-04-15', 'cash', 'security_deposit', '', '2025-04-17 08:54:56', 'active', NULL, NULL, NULL),
(20, 6, 10.00, '2025-04-10', 'bank_transfer', 'rent', 'Payment recorded by tenant. Reference: 255656', '2025-04-17 20:19:25', 'active', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payment_audit`
--

CREATE TABLE `payment_audit` (
  `audit_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `action` enum('create','update','void','restore') NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_reason` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_audit`
--

INSERT INTO `payment_audit` (`audit_id`, `payment_id`, `action`, `action_by`, `action_reason`, `action_date`) VALUES
(2, 16, 'void', 2, 'ment will mark it as invalid but keep it in the system for record-k', '2025-04-17 11:22:33'),
(3, 16, 'restore', 2, 'Payment restored', '2025-04-17 11:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL,
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
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`property_id`, `landlord_id`, `property_name`, `address`, `city`, `state`, `zip_code`, `property_type`, `bedrooms`, `bathrooms`, `square_feet`, `monthly_rent`, `description`, `status`, `created_at`, `updated_at`, `image_path`) VALUES
(1, 2, 'Modern Apartment', 'KG St 102', 'Kigali City', 'Kigali', '10001', 'apartment', 5, 4.0, 1200, 2500.00, 'Spacious and affordable 4-bedroom house for rent in a quiet area of Gahanga. This family-friendly property features 4 bathrooms, a secure compound, and easy access to local amenities. A true home for growing families!', 'vacant', '2025-04-14 13:04:41', '2025-04-27 18:01:05', 'uploads/properties/property_680e70ca036af.jpeg'),
(2, 2, 'Cozy House', 'KG St 105', 'Kigali City', 'Kigali', '90001', 'apartment', 6, 3.0, 1800, 3200.00, 'This charming 4-bedroom home in Gahanga offers bright living spaces, a modern kitchen, and a beautiful backyard. Ideal for families seeking a peaceful lifestyle close to Kigali’s main attractions.', 'occupied', '2025-04-14 13:04:41', '2025-04-27 18:21:35', 'uploads/properties/property_680e71676a597.jpeg'),
(3, 2, 'Spacious House Retreat', '789 mk St', 'Musanze', 'Mukamira', '60601', 'house', 1, 1.0, 600, 1200.00, 'A beautifully finished 4-bedroom house with a private garden, located in the peaceful area of Gahanga. Features modern interiors, high ceilings, and easy access to schools and shops.', 'vacant', '2025-04-14 13:04:41', '2025-04-29 17:26:23', 'uploads/properties/property_6810fd170b178.jpeg'),
(4, 2, 'Luxury Condo', '101 River Rd', 'Rubavu', 'Gisenyi', '33101', 'condo', 3, 3.0, 2000, 4000.00, 'High-end condo with lake Kivu view', 'vacant', '2025-04-14 13:04:41', '2025-04-27 18:08:51', 'uploads/properties/property_680e72b34d0a0.jpeg'),
(5, 6, 'Suburban Home', '202 Maple Dr', 'Seattle', 'WA', '98101', 'house', 4, 3.0, 2400, 3500.00, 'Family home in quiet neighborhood', 'occupied', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL),
(6, 6, 'Downtown Loft', '303 Elm St', 'Denver', 'CO', '80201', 'apartment', 2, 2.0, 1500, 2800.00, 'Modern loft with city views', 'vacant', '2025-04-14 13:04:41', '2025-04-14 13:04:41', NULL),
(8, 2, 'Modern 4-Bedroom Family House', 'Rebero', 'Kigali City', 'Kigali', '0000', 'house', 4, 4.0, 4, 500.00, 'A beautiful, newly built 4-bedroom house located in the peaceful Gahanga area of Kigali. Features spacious bedrooms, modern bathrooms, a cozy living room, and a stylish kitchen. Perfect for families looking for comfort and convenience.', 'occupied', '2025-04-15 14:55:31', '2025-04-29 17:28:33', 'uploads/properties/property_680e703da01f1.jpeg'),
(9, 2, 'Crystal Gardens', 'KG St 102', 'Kigali City', 'Rebero', '2002', 'house', 5, 3.0, 1900, 350000.00, 'A beautifully finished 4-bedroom house with a private garden, located in the peaceful area of Gahanga. Features modern interiors, high ceilings, and easy access to schools and shops.', 'occupied', '2025-04-27 18:16:22', '2025-04-27 18:16:37', 'uploads/properties/property_680e7485a3d03.jpeg'),
(10, 2, 'Apartment', '789 mk St', 'kigali', 'kigali', '60601', 'apartment', 3, 2.0, 20000, 400.00, '', 'occupied', '2025-04-29 17:21:11', '2025-04-29 17:22:20', 'uploads/properties/property_68110a872e531.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `thread_participants`
--

CREATE TABLE `thread_participants` (
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `thread_participants`
--

INSERT INTO `thread_participants` (`thread_id`, `user_id`, `is_read`) VALUES
(1, 2, 1),
(1, 4, 0),
(2, 2, 0),
(2, 4, 1),
(3, 2, 1),
(3, 8, 0),
(4, 2, 1),
(5, 2, 1),
(5, 4, 0);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `unit_number` varchar(20) NOT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` decimal(3,1) DEFAULT NULL,
  `square_feet` int(11) DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `status` enum('vacant','occupied','maintenance') DEFAULT 'vacant',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `property_id`, `unit_number`, `bedrooms`, `bathrooms`, `square_feet`, `monthly_rent`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '101', 1, 1.0, 800, 1800.00, 'occupied', '2025-04-14 13:04:41', '2025-04-14 13:04:41'),
(2, 1, '102', 2, 2.0, 1200, 2500.00, 'vacant', '2025-04-14 13:04:41', '2025-04-14 13:04:41'),
(3, 1, '103', 1, 1.0, 850, 1900.00, 'occupied', '2025-04-14 13:04:41', '2025-04-14 13:04:41'),
(4, 3, 'A', 1, 1.0, 600, 1200.00, 'occupied', '2025-04-14 13:04:41', '2025-04-14 13:04:41'),
(5, 3, 'B', 1, 1.0, 620, 1250.00, 'vacant', '2025-04-14 13:04:41', '2025-04-14 13:04:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('landlord','tenant','admin') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `first_name`, `last_name`, `phone`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin@example.com', 'admin123', 'Admin', 'User', '555-123-4567', 'landlord', '2025-04-14 13:04:41', '2025-04-29 15:38:22'),
(2, 'larissaineza@gmail.om', 'admin123', 'Larissa', 'Ineza', '+250722345676', 'landlord', '2025-04-14 13:04:41', '2025-05-12 18:03:21'),
(4, 'Aboubakar@gmail.com', 'password123', 'Aboubakar Soudick', 'Mugisha', '+250 789 996 88', 'tenant', '2025-04-14 13:04:41', '2025-05-12 17:58:57'),
(5, 'lisa@example.com', 'password123', 'Lisa', 'Brown', '555-234-5678', 'tenant', '2025-04-14 13:04:41', '2025-04-15 07:37:24'),
(6, 'david@example.com', 'password123', 'David', 'Miller', '555-345-6789', 'landlord', '2025-04-14 13:04:41', '2025-04-15 07:37:24'),
(8, 'niwashikilvan@gmail.com', 'password123', 'IMBABAZI', 'Nilvan', '0790359334', 'tenant', '2025-04-27 18:21:35', '2025-04-29 16:16:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD PRIMARY KEY (`insight_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `leases`
--
ALTER TABLE `leases`
  ADD PRIMARY KEY (`lease_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `message_delivery_logs`
--
ALTER TABLE `message_delivery_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD PRIMARY KEY (`thread_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `lease_id` (`lease_id`),
  ADD KEY `voided_by` (`voided_by`);

--
-- Indexes for table `payment_audit`
--
ALTER TABLE `payment_audit`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`property_id`),
  ADD KEY `landlord_id` (`landlord_id`);

--
-- Indexes for table `thread_participants`
--
ALTER TABLE `thread_participants`
  ADD PRIMARY KEY (`thread_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_insights`
--
ALTER TABLE `ai_insights`
  MODIFY `insight_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leases`
--
ALTER TABLE `leases`
  MODIFY `lease_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `message_delivery_logs`
--
ALTER TABLE `message_delivery_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_threads`
--
ALTER TABLE `message_threads`
  MODIFY `thread_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `payment_audit`
--
ALTER TABLE `payment_audit`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD CONSTRAINT `ai_insights_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_insights_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ai_insights_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `leases`
--
ALTER TABLE `leases`
  ADD CONSTRAINT `leases_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leases_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leases_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD CONSTRAINT `maintenance_tasks_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `maintenance_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE;

--
-- Constraints for table `message_delivery_logs`
--
ALTER TABLE `message_delivery_logs`
  ADD CONSTRAINT `message_delivery_logs_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`lease_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_audit`
--
ALTER TABLE `payment_audit`
  ADD CONSTRAINT `payment_audit_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_audit_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `thread_participants`
--
ALTER TABLE `thread_participants`
  ADD CONSTRAINT `thread_participants_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `thread_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
