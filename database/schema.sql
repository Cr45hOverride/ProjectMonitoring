-- =====================================================================
-- KISMEC Intern Task & Project Monitoring System
-- Database Schema Definition & Initial Seed Data
-- Dual-compatible with XAMPP MariaDB 10.4.32 and MySQL 5.7.44-cll-lve
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table 1: users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'hod', 'supervisor', 'staff', 'intern') NOT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `supervisor_id` INT NULL DEFAULT NULL,
  `intern_start_date` DATE NULL DEFAULT NULL,
  `intern_end_date` DATE NULL DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_supervisor_id` (`supervisor_id`),
  INDEX `idx_users_status` (`status`),
  CONSTRAINT `fk_users_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 2: tasks
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `task_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `supervisor_id` INT NOT NULL,
  `intern_id` INT NOT NULL,
  `current_stage` ENUM('ASSIGNED','PLANNING','BUDGET_PENDING','BUDGET_APPROVED','IN_EXECUTION','REVISION_REQUIRED','COMPLETED_PENDING_REVIEW','APPROVED') NOT NULL DEFAULT 'ASSIGNED',
  `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `start_date` DATETIME NOT NULL,
  `due_date` DATETIME NOT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tasks_supervisor` (`supervisor_id`),
  INDEX `idx_tasks_intern` (`intern_id`),
  INDEX `idx_tasks_stage` (`current_stage`),
  INDEX `idx_tasks_due_date` (`due_date`),
  CONSTRAINT `fk_tasks_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tasks_intern` FOREIGN KEY (`intern_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 3: task_process_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `task_process_logs`;
CREATE TABLE `task_process_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `actor_id` INT NOT NULL,
  `stage_name` VARCHAR(50) NOT NULL,
  `remarks` TEXT NULL,
  `reference_urls` TEXT NULL DEFAULT NULL,
  `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_task` (`task_id`),
  INDEX `idx_logs_actor` (`actor_id`),
  CONSTRAINT `fk_logs_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 4: task_media
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `task_media`;
CREATE TABLE `task_media` (
  `media_id` INT AUTO_INCREMENT PRIMARY KEY,
  `log_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` ENUM('image', 'video') NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_media_log` (`log_id`),
  CONSTRAINT `fk_media_log` FOREIGN KEY (`log_id`) REFERENCES `task_process_logs` (`log_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 5: budget_requests
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `budget_requests`;
CREATE TABLE `budget_requests` (
  `budget_id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `justification` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT NULL DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  INDEX `idx_budget_task` (`task_id`),
  INDEX `idx_budget_reviewer` (`reviewed_by`),
  INDEX `idx_budget_status` (`status`),
  CONSTRAINT `fk_budget_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_budget_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed Initial User Accounts
-- Password for all accounts:
-- Admin:      Admin@123
-- Supervisor: Supervisor@123
-- Intern:     Intern@123
-- Hash generated using password_hash('...', PASSWORD_BCRYPT)
-- ---------------------------------------------------------------------

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `department`, `supervisor_id`, `intern_start_date`, `intern_end_date`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@kismec.org.my', '$2y$10$rUerroVN8863xkZ6P9zAuu3uQArWlVtAdM2Y0GMhLlASkNMsDrJqW', 'admin', 'Centre Management & IT', NULL, NULL, NULL, 'active', NOW()),
(2, 'Ir. Ahmad Zulkifli Bin Othman', 'supervisor@kismec.org.my', '$2y$10$iMG7iWiLvSIYBZ51z9wsGubNpiCp1DBp1qxmzDVK58c6Niz2T2Sti', 'supervisor', 'Smart Automation & IoT', NULL, NULL, NULL, 'active', NOW()),
(3, 'Nurul Aisyah Binti Razak', 'intern@kismec.org.my', '$2y$10$2BpoF5mf2iEslltgRcesd.a/HYzBRknLWIlv/M4fwUOBX/Y4qnBXe', 'intern', 'Smart Automation & IoT', 2, '2026-09-01', '2027-02-28', 'active', NOW()),
(4, 'Muhammad Danial Bin Hafiz', 'danial@kismec.org.my', '$2y$10$2BpoF5mf2iEslltgRcesd.a/HYzBRknLWIlv/M4fwUOBX/Y4qnBXe', 'intern', 'Smart Automation & IoT', 2, '2026-10-01', '2027-03-31', 'active', NOW());

-- ---------------------------------------------------------------------
-- Seed Realistic Sample Tasks & Audit Logs
-- ---------------------------------------------------------------------

-- Task 1: Smart Warehouse Sensor Node Deployment (Stage: IN_EXECUTION, SLA: On Track)
INSERT INTO `tasks` (`task_id`, `title`, `description`, `supervisor_id`, `intern_id`, `current_stage`, `priority`, `start_date`, `due_date`, `completed_at`, `approved_at`, `created_at`) VALUES
(1, 'Industrial IoT Edge Gateway Setup & Modbus RTU Integration', 'Configure Raspberry Pi 4 CM4 carrier board with RS-485 Modbus RTU interface to poll factory floor energy meters and transmit telemetry via MQTT to KISMEC cloud broker.', 2, 3, 'IN_EXECUTION', 'high', '2026-11-10 09:00:00', '2026-11-28 17:00:00', NULL, NULL, '2026-11-10 09:00:00');

INSERT INTO `task_process_logs` (`log_id`, `task_id`, `actor_id`, `stage_name`, `remarks`, `logged_at`) VALUES
(1, 1, 2, 'ASSIGNED', 'Assigned task to Nurul Aisyah. Please prepare the schematic diagram and hardware bill of materials.', '2026-11-10 09:15:00'),
(2, 1, 3, 'PLANNING', 'Reviewed technical requirements. Drafted the Modbus polling architecture, baud rate specs (9600 8N1), and MQTT JSON payload schema.', '2026-11-12 11:30:00'),
(3, 1, 3, 'BUDGET_PENDING', 'Requested budget of RM 350.00 for isolated RS-485 transceiver HAT and DIN-rail 24V power supply module.', '2026-11-13 14:00:00'),
(4, 1, 2, 'BUDGET_APPROVED', 'Approved budget request. Components acquired from KISMEC central engineering inventory.', '2026-11-14 10:00:00'),
(5, 1, 3, 'IN_EXECUTION', 'Soldered headers on RS-485 HAT, flashed Debian Bookworm Lite, and successfully communicated with test Schneider energy meter via Python pymodbus.', '2026-11-18 16:45:00');

INSERT INTO `budget_requests` (`budget_id`, `task_id`, `amount`, `justification`, `status`, `reviewed_by`, `requested_at`, `reviewed_at`) VALUES
(1, 1, 350.00, 'Purchase of 1x Waveshare Industrial RS-485 CAN HAT with digital isolation and 1x MeanWell 24V 2.5A DIN-rail power unit.', 'approved', 2, '2026-11-13 14:00:00', '2026-11-14 10:00:00');

-- Task 2: Pneumatic Trainer Bench Calibration (Stage: COMPLETED_PENDING_REVIEW, SLA: Due Soon / Pending Review)
INSERT INTO `tasks` (`task_id`, `title`, `description`, `supervisor_id`, `intern_id`, `current_stage`, `priority`, `start_date`, `due_date`, `completed_at`, `approved_at`, `created_at`) VALUES
(2, 'Festo Pneumatics Training Rig Pressure Calibration & Valve Leakage Audit', 'Perform systematic 6-bar pressure leak tests across all double-acting cylinders, 5/2 solenoid valves, and lubricator units in Lab 3.', 2, 4, 'COMPLETED_PENDING_REVIEW', 'medium', '2026-11-15 08:30:00', '2026-11-21 17:00:00', '2026-11-19 16:20:00', NULL, '2026-11-15 08:30:00');

INSERT INTO `task_process_logs` (`log_id`, `task_id`, `actor_id`, `stage_name`, `remarks`, `logged_at`) VALUES
(6, 2, 2, 'ASSIGNED', 'Assigned pneumatic rig maintenance and safety pressure drop testing.', '2026-11-15 08:45:00'),
(7, 2, 4, 'PLANNING', 'Created calibration checklist following ISO 19973 pneumatic safety standards.', '2026-11-16 10:15:00'),
(8, 2, 4, 'IN_EXECUTION', 'Pressurized 8 test circuits to 6.2 bar. Replaced two worn O-rings on Cylinder C3.', '2026-11-18 14:30:00'),
(9, 2, 4, 'COMPLETED_PENDING_REVIEW', 'All tests completed. Pressure drop over 60 minutes held strictly below 0.05 bar. Ready for supervisor inspection.', '2026-11-19 16:20:00');

-- Task 3: PLC SCADA Dashboard HMI Screen (Stage: APPROVED, SLA: Completed On Time)
INSERT INTO `tasks` (`task_id`, `title`, `description`, `supervisor_id`, `intern_id`, `current_stage`, `priority`, `start_date`, `due_date`, `completed_at`, `approved_at`, `created_at`) VALUES
(3, 'Siemens S7-1200 WinCC HMI Animation & Alarm Banner Development', 'Develop 7-inch touch panel graphics for batch processing mixing tank, including low-level interlocks and high-temperature visual alarms.', 2, 3, 'APPROVED', 'medium', '2026-11-01 09:00:00', '2026-11-15 17:00:00', '2026-11-14 15:00:00', '2026-11-15 11:00:00', '2026-11-01 09:00:00');

INSERT INTO `task_process_logs` (`log_id`, `task_id`, `actor_id`, `stage_name`, `remarks`, `logged_at`) VALUES
(10, 3, 2, 'ASSIGNED', 'Assigned HMI screen project for Training Cell B.', '2026-11-01 09:30:00'),
(11, 3, 3, 'PLANNING', 'Created screen hierarchy, alarm table, and tag mapping for DB10 PLC variables.', '2026-11-03 14:00:00'),
(12, 3, 3, 'IN_EXECUTION', 'Finished graphical layouts in TIA Portal V18 WinCC Advanced.', '2026-11-08 17:00:00'),
(13, 3, 3, 'COMPLETED_PENDING_REVIEW', 'HMI runtime tested with PLC hardware simulation. All alarms latch properly.', '2026-11-14 15:00:00'),
(14, 3, 2, 'APPROVED', 'Excellent work. Color-coding matches KISMEC training guidelines. Approved and signed off.', '2026-11-15 11:00:00');
