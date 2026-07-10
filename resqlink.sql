-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2026 at 05:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `resqlink`
--

-- --------------------------------------------------------

--
-- Table structure for table `alert_notifications`
--

CREATE TABLE `alert_notifications` (
  `id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `channel` enum('in_app','sms','email') NOT NULL DEFAULT 'in_app',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity` varchar(80) DEFAULT NULL,
  `entity_id` varchar(60) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disaster_alerts`
--

CREATE TABLE `disaster_alerts` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `alert_type` varchar(80) NOT NULL,
  `location_text` varchar(180) NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `instructions` text NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'published',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_requests`
--

CREATE TABLE `emergency_requests` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `request_type` enum('medical','rescue','food','transport','other') NOT NULL DEFAULT 'other',
  `description` text NOT NULL,
  `address` varchar(255) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'high',
  `status` enum('pending','assigned','resolved','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergency_resources`
--

CREATE TABLE `emergency_resources` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `resource_name` varchar(120) NOT NULL,
  `resource_type` enum('food','medical','transport','shelter_kit','other') NOT NULL DEFAULT 'other',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(30) NOT NULL DEFAULT 'unit',
  `status` enum('available','allocated','out_of_stock') NOT NULL DEFAULT 'available',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evacuation_status`
--

CREATE TABLE `evacuation_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('safe','evacuated','need_help') NOT NULL,
  `shelter_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `missing_student_reports`
--

CREATE TABLE `missing_student_reports` (
  `id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `student_name` varchar(120) NOT NULL,
  `student_id_number` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `description` text NOT NULL,
  `last_seen_location` varchar(255) NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `reporter_relationship` varchar(80) NOT NULL,
  `reporter_contact` varchar(60) NOT NULL,
  `status` enum('pending','approved','rejected','found') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `found_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `missing_student_sightings`
--

CREATE TABLE `missing_student_sightings` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `sighted_by` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `sighted_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_disaster_instructions`
--

CREATE TABLE `post_disaster_instructions` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(160) NOT NULL,
  `content` text NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'published',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `report_type` enum('alerts','shelters','evacuation','rescue','resources','summary') NOT NULL DEFAULT 'summary',
  `params_text` longtext DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rescue_missions`
--

CREATE TABLE `rescue_missions` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `team_user_id` int(11) NOT NULL,
  `mission_status` enum('assigned','en_route','in_progress','completed','failed') NOT NULL DEFAULT 'assigned',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_allocations`
--

CREATE TABLE `resource_allocations` (
  `id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `allocated_qty` int(11) NOT NULL,
  `allocated_by` int(11) NOT NULL,
  `allocated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(2, 'admin'),
(1, 'citizen'),
(4, 'government'),
(3, 'rescue_team'),
(5, 'system_admin');

-- --------------------------------------------------------

--
-- Table structure for table `shelters`
--

CREATE TABLE `shelters` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `shelter_name` varchar(140) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(80) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `total_capacity` int(11) NOT NULL DEFAULT 0,
  `current_occupancy` int(11) NOT NULL DEFAULT 0,
  `status` enum('open','full','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alert_notifications`
--
ALTER TABLE `alert_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_alert` (`alert_id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_actor` (`actor_user_id`);

--
-- Indexes for table `disaster_alerts`
--
ALTER TABLE `disaster_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alert_creator` (`created_by`),
  ADD KEY `idx_alerts_status_time` (`status`,`published_at`);

--
-- Indexes for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_req_creator` (`created_by`),
  ADD KEY `idx_req_status_time` (`status`,`created_at`);

--
-- Indexes for table `emergency_resources`
--
ALTER TABLE `emergency_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_resource_creator` (`created_by`);

--
-- Indexes for table `evacuation_status`
--
ALTER TABLE `evacuation_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_evac_shelter` (`shelter_id`),
  ADD KEY `idx_evac_user_time` (`user_id`,`updated_at`);

--
-- Indexes for table `missing_student_reports`
--
ALTER TABLE `missing_student_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msr_reporter` (`reported_by`),
  ADD KEY `fk_msr_reviewer` (`reviewed_by`),
  ADD KEY `idx_msr_status_time` (`status`,`created_at`);

--
-- Indexes for table `missing_student_sightings`
--
ALTER TABLE `missing_student_sightings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sighting_report` (`report_id`),
  ADD KEY `fk_sighting_user` (`sighted_by`);

--
-- Indexes for table `post_disaster_instructions`
--
ALTER TABLE `post_disaster_instructions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pdi_admin` (`created_by`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reports_user` (`generated_by`);

--
-- Indexes for table `rescue_missions`
--
ALTER TABLE `rescue_missions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mission_request` (`request_id`),
  ADD KEY `idx_mission_team_status` (`team_user_id`,`mission_status`);

--
-- Indexes for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alloc_resource` (`resource_id`),
  ADD KEY `fk_alloc_shelter` (`shelter_id`),
  ADD KEY `fk_alloc_admin` (`allocated_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `shelters`
--
ALTER TABLE `shelters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_shelter_creator` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alert_notifications`
--
ALTER TABLE `alert_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disaster_alerts`
--
ALTER TABLE `disaster_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emergency_resources`
--
ALTER TABLE `emergency_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evacuation_status`
--
ALTER TABLE `evacuation_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `missing_student_reports`
--
ALTER TABLE `missing_student_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `missing_student_sightings`
--
ALTER TABLE `missing_student_sightings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_disaster_instructions`
--
ALTER TABLE `post_disaster_instructions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rescue_missions`
--
ALTER TABLE `rescue_missions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shelters`
--
ALTER TABLE `shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alert_notifications`
--
ALTER TABLE `alert_notifications`
  ADD CONSTRAINT `fk_notif_alert` FOREIGN KEY (`alert_id`) REFERENCES `disaster_alerts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `disaster_alerts`
--
ALTER TABLE `disaster_alerts`
  ADD CONSTRAINT `fk_alert_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `emergency_requests`
--
ALTER TABLE `emergency_requests`
  ADD CONSTRAINT `fk_req_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_resources`
--
ALTER TABLE `emergency_resources`
  ADD CONSTRAINT `fk_resource_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `evacuation_status`
--
ALTER TABLE `evacuation_status`
  ADD CONSTRAINT `fk_evac_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evac_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `missing_student_reports`
--
ALTER TABLE `missing_student_reports`
  ADD CONSTRAINT `fk_msr_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `missing_student_sightings`
--
ALTER TABLE `missing_student_sightings`
  ADD CONSTRAINT `fk_sighting_report` FOREIGN KEY (`report_id`) REFERENCES `missing_student_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sighting_user` FOREIGN KEY (`sighted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_disaster_instructions`
--
ALTER TABLE `post_disaster_instructions`
  ADD CONSTRAINT `fk_pdi_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `rescue_missions`
--
ALTER TABLE `rescue_missions`
  ADD CONSTRAINT `fk_mission_request` FOREIGN KEY (`request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mission_team` FOREIGN KEY (`team_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  ADD CONSTRAINT `fk_alloc_admin` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_alloc_resource` FOREIGN KEY (`resource_id`) REFERENCES `emergency_resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alloc_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shelters`
--
ALTER TABLE `shelters`
  ADD CONSTRAINT `fk_shelter_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
