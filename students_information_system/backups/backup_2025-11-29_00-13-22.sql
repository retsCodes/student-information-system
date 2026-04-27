-- Student Information System Database Backup
-- Created on: 2025-11-29 00:13:23
-- Database: student_info_tracker
-- Includes all system tables and data

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `user_status` enum('active','locked') DEFAULT 'active',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier','student') NOT NULL,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `users`
LOCK TABLES `users` WRITE;
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (1, 'ADMIN001', 'active', 'System Administrator', 'admin@system.com', '$2y$10$52K4NNdlJ4kAxiD6Ls5MzexJig2IGVeWoyWtszcD8zbb3ewZO4PEu', 'admin', '2025-11-29 07:13:22', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (2, 'CASH001', 'active', 'Maria Santos', 'maria.santos@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (3, 'CASH002', 'active', 'Juan Dela Cruz', 'juan.delacruz@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (4, 'C23-01-1001-BSIT101', 'active', 'John Michael Santos', 'john.santos@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (5, 'C23-01-1002-BSIT101', 'active', 'Maria Cristina Reyes', 'maria.reyes@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (6, 'C23-01-1003-BSIT101', 'active', 'Carlos Antonio Lim', 'carlos.lim@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (7, 'C23-01-1004-BSIT101', 'active', 'Andrea Marie Tan', 'andrea.tan@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (8, 'C23-01-1005-BSIT101', 'active', 'James Patrick Cruz', 'james.cruz@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (9, 'C22-02-2001-BSIT201', 'active', 'Sarah Jane Gonzales', 'sarah.gonzales@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (10, 'C22-02-2002-BSIT201', 'active', 'Daniel Robert Sy', 'daniel.sy@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (11, 'C22-02-2003-BSIT201', 'active', 'Michelle Anne Wong', 'michelle.wong@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (12, 'C22-02-2004-BSIT201', 'active', 'Mark Anthony Chen', 'mark.chen@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (13, 'C22-02-2005-BSIT201', 'active', 'Jennifer Lee Co', 'jennifer.co@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (14, 'C21-03-3001-BSIT301', 'active', 'Robert John Ong', 'robert.ong@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (15, 'C21-03-3002-BSIT301', 'active', 'Catherine Rose Yu', 'catherine.yu@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (16, 'C21-03-3003-BSIT301', 'active', 'Paul Vincent Uy', 'paul.uy@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (17, 'C21-03-3004-BSIT301', 'active', 'Angela Marie Chua', 'angela.chua@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (18, 'C21-03-3005-BSIT301', 'active', 'Christopher John Go', 'christopher.go@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (19, 'C20-04-4001-BSIT401', 'active', 'Stephanie Anne Lim', 'stephanie.lim@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (20, 'C20-04-4002-BSIT401', 'active', 'Kevin Michael Tan', 'kevin.tan@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (21, 'C20-04-4003-BSIT401', 'active', 'Nicole Elizabeth Sy', 'nicole.sy@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (22, 'C20-04-4004-BSIT401', 'active', 'Brian Joseph Co', 'brian.co@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (23, 'C20-04-4005-BSIT401', 'active', 'Patricia Ann Wong', 'patricia.wong@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-28 22:46:08', '2025-11-28 22:46:08');
INSERT INTO `users` (`id`, `user_id`, `user_status`, `name`, `email`, `password`, `role`, `last_active`, `created_at`) VALUES (24, 'C25-11-5250-MAN121', 'active', 'Rich Cuizon', 'richcuizon2@gmail.com', '$2y$10$9YP.HmNBDi9scL1..FYHM.OEXEc8suLwwlauYcKZ33WQKIduhUU2i', 'student', '2025-11-29 06:54:03', '2025-11-29 06:50:50');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `students_info`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `students_info`;
CREATE TABLE `students_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `student_type` enum('regular','irregular') DEFAULT 'regular',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `student_status` enum('new','old') DEFAULT 'new',
  `enrollment_status` enum('enrolled','dropped','graduated') DEFAULT 'enrolled',
  `status` enum('active','inactive') DEFAULT 'active',
  `enrollment_date` date DEFAULT NULL,
  `total_units` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `students_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `students_info`
LOCK TABLES `students_info` WRITE;
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (1, 'C23-01-1001-BSIT101', 'regular', 'John Michael Santos', 'john.santos@student.com', 09171234567, '123 Main St, Manila', NULL, 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-15', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (2, 'C23-01-1002-BSIT101', 'regular', 'Maria Cristina Reyes', 'maria.reyes@student.com', 09172234567, '456 Oak St, Quezon City', NULL, 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-16', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (3, 'C23-01-1003-BSIT101', 'regular', 'Carlos Antonio Lim', 'carlos.lim@student.com', 09173234567, '789 Pine St, Makati', NULL, 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-17', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (4, 'C23-01-1004-BSIT101', 'regular', 'Andrea Marie Tan', 'andrea.tan@student.com', 09174234567, '321 Elm St, Mandaluyong', NULL, 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-18', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (5, 'C23-01-1005-BSIT101', 'regular', 'James Patrick Cruz', 'james.cruz@student.com', 09175234567, '654 Maple St, Pasig', NULL, 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-19', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (6, 'C22-02-2001-BSIT201', 'regular', 'Sarah Jane Gonzales', 'sarah.gonzales@student.com', 09176234567, '987 Cedar St, Taguig', NULL, 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-15', 27);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (7, 'C22-02-2002-BSIT201', 'regular', 'Daniel Robert Sy', 'daniel.sy@student.com', 09177234567, '147 Birch St, Paranaque', NULL, 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-16', 27);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (8, 'C22-02-2003-BSIT201', 'regular', 'Michelle Anne Wong', 'michelle.wong@student.com', 09178234567, '258 Walnut St, Las Pinas', NULL, 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-17', 27);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (9, 'C22-02-2004-BSIT201', 'regular', 'Mark Anthony Chen', 'mark.chen@student.com', 09179234567, '369 Spruce St, Muntinlupa', NULL, 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-18', 27);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (10, 'C22-02-2005-BSIT201', 'regular', 'Jennifer Lee Co', 'jennifer.co@student.com', 09180234567, '741 Aspen St, Valenzuela', NULL, 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-19', 27);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (11, 'C21-03-3001-BSIT301', 'regular', 'Robert John Ong', 'robert.ong@student.com', 09181234567, '852 Poplar St, Caloocan', NULL, 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-15', 30);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (12, 'C21-03-3002-BSIT301', 'regular', 'Catherine Rose Yu', 'catherine.yu@student.com', 09182234567, '963 Willow St, Malabon', NULL, 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-16', 30);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (13, 'C21-03-3003-BSIT301', 'irregular', 'Paul Vincent Uy', 'paul.uy@student.com', 09183234567, '159 Redwood St, Navotas', NULL, 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-17', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (14, 'C21-03-3004-BSIT301', 'regular', 'Angela Marie Chua', 'angela.chua@student.com', 09184234567, '357 Sequoia St, Pasay', NULL, 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-18', 30);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (15, 'C21-03-3005-BSIT301', 'regular', 'Christopher John Go', 'christopher.go@student.com', 09185234567, '486 Fir St, San Juan', NULL, 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-19', 30);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (16, 'C20-04-4001-BSIT401', 'regular', 'Stephanie Anne Lim', 'stephanie.lim@student.com', 09186234567, '753 Cypress St, Marikina', NULL, 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-15', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (17, 'C20-04-4002-BSIT401', 'regular', 'Kevin Michael Tan', 'kevin.tan@student.com', 09187234567, '864 Magnolia St, Antipolo', NULL, 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-16', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (18, 'C20-04-4003-BSIT401', 'regular', 'Nicole Elizabeth Sy', 'nicole.sy@student.com', 09188234567, '975 Dogwood St, Cainta', NULL, 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-17', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (19, 'C20-04-4004-BSIT401', 'regular', 'Brian Joseph Co', 'brian.co@student.com', 09189234567, '186 Hickory St, Taytay', NULL, 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-18', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (20, 'C20-04-4005-BSIT401', 'regular', 'Patricia Ann Wong', 'patricia.wong@student.com', 09190234567, '297 Palm St, Angono', NULL, 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-19', 24);
INSERT INTO `students_info` (`id`, `user_id`, `student_type`, `name`, `email`, `number`, `address`, `profile_picture`, `program`, `year_level`, `student_status`, `enrollment_status`, `status`, `enrollment_date`, `total_units`) VALUES (21, 'C25-11-5250-MAN121', 'regular', 'Rich Cuizon', 'richcuizon2@gmail.com', NULL, NULL, NULL, '', 0, 'new', 'enrolled', 'active', NULL, 0);
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `employee_info`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `employee_info`;
CREATE TABLE `employee_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `number` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `employee_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `employee_info`
LOCK TABLES `employee_info` WRITE;
INSERT INTO `employee_info` (`id`, `user_id`, `name`, `email`, `number`, `profile_picture`, `role`) VALUES (1, 'ADMIN001', 'System Administrator', 'admin@system.com', NULL, NULL, 'System Administrator');
INSERT INTO `employee_info` (`id`, `user_id`, `name`, `email`, `number`, `profile_picture`, `role`) VALUES (2, 'CASH001', 'Maria Santos', 'maria.santos@school.com', NULL, NULL, 'Senior Cashier');
INSERT INTO `employee_info` (`id`, `user_id`, `name`, `email`, `number`, `profile_picture`, `role`) VALUES (3, 'CASH002', 'Juan Dela Cruz', 'juan.delacruz@school.com', NULL, NULL, 'Cashier');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `subjects`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `units` int(11) NOT NULL,
  `sections` text DEFAULT NULL,
  `exception` text DEFAULT NULL,
  `addition` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT '1st',
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `subjects`
LOCK TABLES `subjects` WRITE;
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (1, 'CC101', 'Introduction to Computing', 3, NULL, NULL, NULL, 'Fundamentals of computing and computer systems', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (2, 'CC102', 'Computer Programming 1', 3, NULL, NULL, NULL, 'Introduction to programming concepts', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (3, 'MATH101', 'College Algebra', 3, NULL, NULL, NULL, 'Algebraic concepts and applications', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (4, 'ENG101', 'Purposive Communication', 3, NULL, NULL, NULL, 'Effective communication skills', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (5, 'FIL101', 'Komunikasyon sa Akademikong Filipino', 3, NULL, NULL, NULL, 'Filipino language in academic context', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (6, 'PE101', 'Physical Fitness', 2, NULL, NULL, NULL, 'Physical education and fitness', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (7, 'NSTP101', 'National Service Training Program 1', 3, NULL, NULL, NULL, 'Civic welfare training service', 'BS Information Technology', 1, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (8, 'CC103', 'Computer Programming 2', 3, NULL, NULL, NULL, 'Advanced programming concepts', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (9, 'CC104', 'Data Structures and Algorithms', 3, NULL, NULL, NULL, 'Fundamental data structures and algorithms', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (10, 'MATH102', 'Calculus 1', 3, NULL, NULL, NULL, 'Differential and integral calculus', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (11, 'SCI101', 'Science, Technology and Society', 3, NULL, NULL, NULL, 'Impact of science and technology', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (12, 'SOC101', 'Understanding the Self', 3, NULL, NULL, NULL, 'Psychological perspectives of self', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (13, 'PE102', 'Rhythmic Activities', 2, NULL, NULL, NULL, 'Dance and movement education', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (14, 'NSTP102', 'National Service Training Program 2', 3, NULL, NULL, NULL, 'Advanced civic welfare training', 'BS Information Technology', 1, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (15, 'CC201', 'Object-Oriented Programming', 3, NULL, NULL, NULL, 'Principles of OOP using Java', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (16, 'CC202', 'Discrete Mathematics', 3, NULL, NULL, NULL, 'Mathematical structures in computing', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (17, 'CC203', 'Database Management System 1', 3, NULL, NULL, NULL, 'Introduction to database concepts', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (18, 'CC204', 'Platform Technologies', 3, NULL, NULL, NULL, 'Operating systems and platforms', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (19, 'MATH201', 'Calculus 2', 3, NULL, NULL, NULL, 'Advanced calculus topics', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (20, 'ETH201', 'Ethics', 3, NULL, NULL, NULL, 'Moral philosophy and ethical principles', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (21, 'PE201', 'Individual/Dual Sports', 2, NULL, NULL, NULL, 'Sports and recreational activities', 'BS Information Technology', 2, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (22, 'CC205', 'Information Management', 3, NULL, NULL, NULL, 'Data and information systems management', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (23, 'CC206', 'Networking 1', 3, NULL, NULL, NULL, 'Computer networks fundamentals', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (24, 'CC207', 'Web Development', 3, NULL, NULL, NULL, 'Web programming and development', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (25, 'CC208', 'Database Management System 2', 3, NULL, NULL, NULL, 'Advanced database concepts', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (26, 'RIZAL', 'Life and Works of Rizal', 3, NULL, NULL, NULL, 'Study of Jose Rizals life and works', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (27, 'PE202', 'Team Sports', 2, NULL, NULL, NULL, 'Team-based sports activities', 'BS Information Technology', 2, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (28, 'CC301', 'Advanced Database Systems', 3, NULL, NULL, NULL, 'Enterprise database systems', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (29, 'CC302', 'Networking 2', 3, NULL, NULL, NULL, 'Advanced networking concepts', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (30, 'CC303', 'Systems Administration and Maintenance', 3, NULL, NULL, NULL, 'IT infrastructure management', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (31, 'CC304', 'Systems Integration and Architecture', 3, NULL, NULL, NULL, 'System design and integration', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (32, 'CC305', 'Web Programming', 3, NULL, NULL, NULL, 'Server-side web development', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (33, 'IT301', 'Information Assurance and Security 1', 3, NULL, NULL, NULL, 'Cybersecurity fundamentals', 'BS Information Technology', 3, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (34, 'CC306', 'Mobile Programming', 3, NULL, NULL, NULL, 'Mobile application development', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (35, 'CC307', 'Game Development', 3, NULL, NULL, NULL, 'Game design and programming', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (36, 'CC308', 'Software Engineering 1', 3, NULL, NULL, NULL, 'Software development methodologies', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (37, 'IT302', 'Information Assurance and Security 2', 3, NULL, NULL, NULL, 'Advanced cybersecurity', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (38, 'IT303', 'Quantitative Methods', 3, NULL, NULL, NULL, 'Statistical methods in IT', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (39, 'IT304', 'IT Elective 1', 3, NULL, NULL, NULL, 'Specialized IT track subject', 'BS Information Technology', 3, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (40, 'CC401', 'Software Engineering 2', 3, NULL, NULL, NULL, 'Advanced software engineering', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (41, 'CC402', 'IT Project Management', 3, NULL, NULL, NULL, 'Project management in IT', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (42, 'CC403', 'Systems Analysis and Design', 3, NULL, NULL, NULL, 'System development lifecycle', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (43, 'IT401', 'IT Elective 2', 3, NULL, NULL, NULL, 'Specialized IT track subject', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (44, 'IT402', 'IT Elective 3', 3, NULL, NULL, NULL, 'Specialized IT track subject\r\ntest', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (45, 'IT403', 'Practicum', 3, NULL, NULL, NULL, 'Industry immersion program', 'BS Information Technology', 4, '1st');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (46, 'CC404', 'Capstone Project 1', 3, NULL, NULL, NULL, 'Thesis project part 1', 'BS Information Technology', 4, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (47, 'CC405', 'Capstone Project 2', 3, NULL, NULL, NULL, 'Thesis project part 2', 'BS Information Technology', 4, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (48, 'IT404', 'Social and Professional Issues', 3, NULL, NULL, NULL, 'IT ethics and professional practice', 'BS Information Technology', 4, '2nd');
INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `units`, `sections`, `exception`, `addition`, `description`, `program`, `year_level`, `semester`) VALUES (49, 'IT405', 'IT Elective 4', 3, NULL, NULL, NULL, 'Specialized IT track subject', 'BS Information Technology', 4, '2nd');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `sections`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_code` varchar(20) NOT NULL,
  `section_name` varchar(100) DEFAULT NULL,
  `user_id` text DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `semester` enum('1st','2nd','summer') DEFAULT '1st',
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_code` (`section_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `sections`
LOCK TABLES `sections` WRITE;
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (1, 'BSIT1A', 'BSIT 1-A', NULL, 1, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (2, 'BSIT1B', 'BSIT 1-B', NULL, 1, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (3, 'BSIT1C', 'BSIT 1-C', NULL, 1, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (4, 'BSIT2A', 'BSIT 2-A', NULL, 2, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (5, 'BSIT2B', 'BSIT 2-B', NULL, 2, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (6, 'BSIT3A', 'BSIT 3-A', NULL, 3, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (7, 'BSIT3B', 'BSIT 3-B', NULL, 3, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (8, 'BSIT4A', 'BSIT 4-A', NULL, 4, 'BS Information Technology', 'active', '1st');
INSERT INTO `sections` (`id`, `section_code`, `section_name`, `user_id`, `year_level`, `program`, `status`, `semester`) VALUES (9, 'BSIT4B', 'BSIT 4-B', NULL, 4, 'BS Information Technology', 'active', '1st');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `student_sections`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_sections`;
CREATE TABLE `student_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `section_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `student_sections_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `student_sections_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `student_sections`
LOCK TABLES `student_sections` WRITE;
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (1, 'C23-01-1001-BSIT101', 1, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (2, 'C23-01-1002-BSIT101', 1, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (3, 'C23-01-1003-BSIT101', 1, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (4, 'C23-01-1004-BSIT101', 2, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (5, 'C23-01-1005-BSIT101', 2, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (6, 'C22-02-2001-BSIT201', 4, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (7, 'C22-02-2002-BSIT201', 4, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (8, 'C22-02-2003-BSIT201', 4, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (9, 'C22-02-2004-BSIT201', 5, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (10, 'C22-02-2005-BSIT201', 5, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (11, 'C21-03-3001-BSIT301', 6, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (12, 'C21-03-3002-BSIT301', 6, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (13, 'C21-03-3003-BSIT301', 6, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (14, 'C21-03-3004-BSIT301', 7, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (15, 'C21-03-3005-BSIT301', 7, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (16, 'C20-04-4001-BSIT401', 8, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (17, 'C20-04-4002-BSIT401', 8, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (18, 'C20-04-4003-BSIT401', 8, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (19, 'C20-04-4004-BSIT401', 9, '2025-11-28 22:46:08');
INSERT INTO `student_sections` (`id`, `student_id`, `section_id`, `assigned_at`) VALUES (20, 'C20-04-4005-BSIT401', 9, '2025-11-28 22:46:08');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `student_subjects`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_subjects`;
CREATE TABLE `student_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `student_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table `student_subjects` is empty

-- --------------------------------------------------------
-- Table structure for `payments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `permit_number` varchar(6) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `amount_text` varchar(255) DEFAULT NULL,
  `remaining_balance` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('paid','unpaid','partial') DEFAULT 'unpaid',
  `description` text DEFAULT NULL,
  `issued_date` date NOT NULL,
  `issued_by` varchar(50) NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `payment_type` enum('regular','bulk') DEFAULT 'regular',
  `bulk_reference` varchar(50) DEFAULT NULL,
  `payment_category` enum('tuition','exam','misc','other') DEFAULT 'other',
  `units` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permit_number` (`permit_number`),
  KEY `student_id` (`student_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `payments`
LOCK TABLES `payments` WRITE;
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (1, 'C23-01-1001-BSIT101', 552078, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (2, 'C23-01-1002-BSIT101', 868834, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (3, 'C23-01-1003-BSIT101', 302905, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (4, 'C23-01-1004-BSIT101', 876806, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (5, 'C23-01-1005-BSIT101', 603747, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (6, 'C22-02-2001-BSIT201', 153622, 64800.00, 'sixty four thousand eight hundred pesos', 0.00, 'paid', 'Finals Examination fees - 27 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 27, '2025-11-28 23:26:47');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (7, 'C22-02-2002-BSIT201', 651677, 64800.00, 'sixty four thousand eight hundred pesos', 64800.00, 'unpaid', 'Finals Examination fees - 27 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 27, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (8, 'C22-02-2003-BSIT201', 939461, 64800.00, 'sixty four thousand eight hundred pesos', 64800.00, 'unpaid', 'Finals Examination fees - 27 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 27, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (9, 'C22-02-2004-BSIT201', 731381, 64800.00, 'sixty four thousand eight hundred pesos', 64800.00, 'unpaid', 'Finals Examination fees - 27 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 27, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (10, 'C22-02-2005-BSIT201', 134031, 64800.00, 'sixty four thousand eight hundred pesos', 64800.00, 'unpaid', 'Finals Examination fees - 27 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 27, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (11, 'C21-03-3001-BSIT301', 248372, 72000.00, 'seventy two thousand pesos', 72000.00, 'unpaid', 'Finals Examination fees - 30 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 30, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (12, 'C21-03-3002-BSIT301', 904903, 72000.00, 'seventy two thousand pesos', 72000.00, 'unpaid', 'Finals Examination fees - 30 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 30, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (13, 'C21-03-3003-BSIT301', 673973, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (14, 'C21-03-3004-BSIT301', 947999, 72000.00, 'seventy two thousand pesos', 72000.00, 'unpaid', 'Finals Examination fees - 30 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 30, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (15, 'C21-03-3005-BSIT301', 736477, 72000.00, 'seventy two thousand pesos', 72000.00, 'unpaid', 'Finals Examination fees - 30 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 30, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (16, 'C20-04-4001-BSIT401', 788151, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (17, 'C20-04-4002-BSIT401', 967577, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (18, 'C20-04-4003-BSIT401', 516404, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (19, 'C20-04-4004-BSIT401', 430085, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-28 23:10:08');
INSERT INTO `payments` (`id`, `student_id`, `permit_number`, `amount`, `amount_text`, `remaining_balance`, `payment_status`, `description`, `issued_date`, `issued_by`, `school_year`, `payment_type`, `bulk_reference`, `payment_category`, `units`, `updated_at`) VALUES (20, 'C20-04-4005-BSIT401', 281461, 57600.00, 'fifty seven thousand six hundred pesos', 57600.00, 'unpaid', 'Finals Examination fees - 24 units', '2025-11-28', 'ADMIN001', '2025-2026', 'regular', NULL, 'exam', 24, '2025-11-29 06:47:13');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `payment_installments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payment_installments`;
CREATE TABLE `payment_installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `payment_installments_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table `payment_installments` is empty

-- --------------------------------------------------------
-- Table structure for `transaction_history`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transaction_history`;
CREATE TABLE `transaction_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(20) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `transaction_type` enum('payment','refund','adjustment','waiver','bulk_payment') DEFAULT 'payment',
  `amount` decimal(10,2) NOT NULL,
  `previous_balance` decimal(10,2) DEFAULT 0.00,
  `new_balance` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `reference_id` varchar(50) DEFAULT NULL,
  `bulk_reference` varchar(50) DEFAULT NULL,
  `issued_by` varchar(50) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `student_id` (`student_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `transaction_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `transaction_history_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `transaction_history`
LOCK TABLES `transaction_history` WRITE;
INSERT INTO `transaction_history` (`id`, `transaction_id`, `student_id`, `transaction_type`, `amount`, `previous_balance`, `new_balance`, `description`, `status`, `reference_id`, `bulk_reference`, `issued_by`, `transaction_date`, `notes`) VALUES (1, 'TXN20251128162647853', 'C22-02-2001-BSIT201', 'payment', 64800.00, 64800.00, 0.00, 'Payment received: Finals Examination fees - 27 units', 'completed', 153622, NULL, 'ADMIN001', '2025-11-28 23:26:47', 'Method: cash');
INSERT INTO `transaction_history` (`id`, `transaction_id`, `student_id`, `transaction_type`, `amount`, `previous_balance`, `new_balance`, `description`, `status`, `reference_id`, `bulk_reference`, `issued_by`, `transaction_date`, `notes`) VALUES (2, 'TXN20251128232117831', 'C20-04-4005-BSIT401', 'payment', 57600.00, 57600.00, 0.00, 'Payment received: Finals Examination fees - 24 units', 'completed', 281461, NULL, 'ADMIN001', '2025-11-29 06:21:17', 'Method: cash');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `bulk_payment_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bulk_payment_logs`;
CREATE TABLE `bulk_payment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulk_reference` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('paid','unpaid','partial') DEFAULT 'unpaid',
  `school_year` varchar(20) DEFAULT NULL,
  `total_students` int(11) NOT NULL,
  `successful_creations` int(11) NOT NULL DEFAULT 0,
  `failed_creations` int(11) NOT NULL DEFAULT 0,
  `issued_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bulk_reference` (`bulk_reference`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `bulk_payment_logs_ibfk_1` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table `bulk_payment_logs` is empty

-- --------------------------------------------------------
-- Table structure for `activity_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_id` varchar(50) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `log_id` (`log_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table `activity_logs`
LOCK TABLES `activity_logs` WRITE;
INSERT INTO `activity_logs` (`id`, `log_id`, `user_id`, `user_name`, `action`, `description`, `created_at`) VALUES (2, '', 'ADMIN001', NULL, 'Logs Cleared', 'Cleared 1 logs (type: all)', '2025-11-29 07:06:34');
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for `login_attempts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table `login_attempts` is empty

-- --------------------------------------------------------
-- Table structure for `class_schedule`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_schedule`;
CREATE TABLE `class_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT 'TBA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `class_schedule_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_schedule_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Table `class_schedule` is empty

-- --------------------------------------------------------
-- Database Functions and Procedures
-- --------------------------------------------------------
-- --------------------------------------------------------
-- Indexes and Constraints
-- --------------------------------------------------------
-- Indexes for table `users`
-- INDEX: user_id on user_id
-- INDEX: email on email

-- Indexes for table `students_info`
-- INDEX: user_id on user_id

-- Indexes for table `employee_info`
-- INDEX: user_id on user_id

-- Indexes for table `subjects`
-- INDEX: subject_code on subject_code

-- Indexes for table `sections`
-- INDEX: section_code on section_code

-- Indexes for table `student_sections`
-- INDEX: student_id on student_id
-- INDEX: section_id on section_id

-- Indexes for table `student_subjects`
-- INDEX: student_id on student_id
-- INDEX: subject_id on subject_id

-- Indexes for table `payments`
-- INDEX: permit_number on permit_number
-- INDEX: student_id on student_id
-- INDEX: issued_by on issued_by

-- Indexes for table `payment_installments`
-- INDEX: payment_id on payment_id

-- Indexes for table `transaction_history`
-- INDEX: transaction_id on transaction_id
-- INDEX: student_id on student_id
-- INDEX: issued_by on issued_by

-- Indexes for table `bulk_payment_logs`
-- INDEX: bulk_reference on bulk_reference
-- INDEX: issued_by on issued_by

-- Indexes for table `activity_logs`
-- INDEX: log_id on log_id
-- INDEX: idx_user_id on user_id
-- INDEX: idx_created_at on created_at
-- INDEX: idx_action on action

-- Indexes for table `login_attempts`

-- Indexes for table `class_schedule`
-- INDEX: subject_id on subject_id
-- INDEX: section_id on section_id

-- --------------------------------------------------------
-- Cleanup and Finalization
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS=1;
COMMIT;
SET AUTOCOMMIT = 1;
-- Backup completed successfully
