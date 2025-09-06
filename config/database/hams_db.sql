-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2025 at 11:23 AM
-- Server version: 10.4.27-MariaDB-log
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hams_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_records`
--

CREATE TABLE `activity_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('login','logout','create','update','delete','delivery','report') NOT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `activity_records`
--

INSERT INTO `activity_records` (`id`, `user_id`, `action_type`, `table_affected`, `record_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 03:21:31'),
(2, 1, 'update', 'users', 1, 'Reset password for user ID: 1', '::1', '2025-09-01 03:22:47'),
(3, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 03:23:10'),
(4, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 04:22:56'),
(5, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 04:55:23'),
(6, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 06:32:03'),
(7, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-01 14:26:49'),
(8, 1, 'create', 'users', NULL, 'Created new user: assistant (field_worker)', '::1', '2025-09-01 14:39:30'),
(9, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 03:37:42'),
(10, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 03:42:53'),
(11, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 03:51:20'),
(12, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 04:08:56'),
(13, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 04:11:06'),
(14, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 04:13:39'),
(15, 1, 'delete', 'users', 2, 'Deleted user ID: 2', '::1', '2025-09-04 04:16:07'),
(16, 1, 'create', 'users', NULL, 'Created new user: Assistant (assistant)', '::1', '2025-09-04 04:24:22'),
(17, 1, 'create', 'users', NULL, 'Created new user: Coordinator (coordinator)', '::1', '2025-09-04 04:25:05'),
(18, 1, 'create', 'users', NULL, 'Created new user: Officer (officer)', '::1', '2025-09-04 04:25:55'),
(19, 3, 'login', 'users', 3, 'User logged in', '::1', '2025-09-04 04:26:41'),
(20, 5, 'login', 'users', 5, 'User logged in', '::1', '2025-09-04 04:29:03'),
(21, 4, 'login', 'users', 4, 'User logged in', '::1', '2025-09-04 04:29:34'),
(22, 1, 'login', 'users', 1, 'User logged in', '127.0.0.1', '2025-09-04 04:46:24'),
(23, 3, 'login', 'users', 3, 'User logged in', '::1', '2025-09-04 04:48:27'),
(24, 4, 'login', 'users', 4, 'User logged in', '::1', '2025-09-04 04:48:48'),
(25, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 05:22:56'),
(26, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 14:31:08'),
(27, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 14:32:21'),
(28, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 14:40:41'),
(29, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 14:54:25'),
(30, 1, 'create', 'users', NULL, 'Created new user: Abdalle (coordinator)', '::1', '2025-09-04 15:57:25'),
(31, 6, 'login', 'users', 6, 'User logged in', '::1', '2025-09-04 15:58:27'),
(32, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 15:59:52'),
(33, 3, 'login', 'users', 3, 'User logged in', '::1', '2025-09-04 16:17:42'),
(34, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 16:20:30'),
(35, 4, 'login', 'users', 4, 'User logged in', '::1', '2025-09-04 16:25:49'),
(36, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 17:44:04'),
(37, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-04 17:44:37'),
(38, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 04:01:50'),
(39, 1, 'update', 'users', 1, 'Updated user ID: 1', '::1', '2025-09-05 04:02:46'),
(40, 4, 'login', 'users', 4, 'User logged in', '::1', '2025-09-05 04:17:14'),
(41, 1, 'login', 'users', 1, 'User logged in', '127.0.0.1', '2025-09-05 04:30:28'),
(42, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:07:12'),
(43, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:10:59'),
(44, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:13:51'),
(45, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:01'),
(46, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:11'),
(47, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:19'),
(48, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:28'),
(49, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:38'),
(50, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:47'),
(51, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-05 05:14:58'),
(52, 1, 'login', 'users', 1, 'User logged in', '::1', '2025-09-06 08:55:32'),
(53, 1, 'create', 'users', 7, 'Created new user: 87483874 (officer)', '::1', '2025-09-06 09:01:43'),
(54, 1, 'delete', 'users', 7, 'Deleted user ID: 7', '::1', '2025-09-06 09:01:51'),
(55, 4, 'login', 'users', 4, 'User logged in', '::1', '2025-09-06 09:13:30'),
(56, 3, 'login', 'users', 3, 'User logged in', '::1', '2025-09-06 09:15:52'),
(57, 5, 'login', 'users', 5, 'User logged in', '::1', '2025-09-06 09:16:17');

-- --------------------------------------------------------

--
-- Table structure for table `aid_deliveries`
--

CREATE TABLE `aid_deliveries` (
  `id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `quantity_delivered` decimal(10,2) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `delivery_location` varchar(100) DEFAULT NULL,
  `delivered_by` int(11) NOT NULL,
  `receipt_signature` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `aid_deliveries`
--

INSERT INTO `aid_deliveries` (`id`, `delivery_date`, `recipient_id`, `supply_id`, `quantity_delivered`, `project_id`, `delivery_location`, `delivered_by`, `receipt_signature`, `notes`, `created_at`) VALUES
(1, '2025-09-01', 1, 5, '100.00', NULL, 'Washington, Garowe', 1, 1, 'Si fiican baa loo gaarsiiyey.', '2025-09-01 14:35:07'),
(2, '2025-09-04', 2, 6, '150.00', 1, 'Washington, Garowe', 4, 1, 'Waa la gaarsiiyey daawooyinkii uu u baahnaa', '2025-09-04 16:32:33'),
(3, '2025-09-05', 4, 7, '100.00', 1, 'Washington, Garowe', 1, 1, 'Waxaan guddoonsiinnay 100kg oo bariis ah', '2025-09-05 05:38:58'),
(4, '2025-09-06', 1, 7, '9850.00', 1, 'Washington, Garowe', 5, 1, 'Si weyn baa loo caawiyey', '2025-09-06 09:21:02'),
(5, '2025-09-06', 3, 7, '30.00', 1, 'Washington, Garowe', 5, 1, 'Si fiican baa loo gaarsiiyey', '2025-09-06 09:21:59');

-- --------------------------------------------------------

--
-- Table structure for table `aid_recipients`
--

CREATE TABLE `aid_recipients` (
  `id` int(11) NOT NULL,
  `recipient_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(100) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `household_size` int(11) DEFAULT 1,
  `displacement_status` enum('resident','idp','refugee','returnee') DEFAULT 'resident',
  `vulnerability_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `registration_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `aid_recipients`
--

INSERT INTO `aid_recipients` (`id`, `recipient_id`, `full_name`, `phone`, `location`, `district`, `household_size`, `displacement_status`, `vulnerability_level`, `registration_date`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Wash001', 'Mohamed Farah Ali', '907378287', 'Garoowe, Puntland, Somalia', 'Garowe', 7, 'resident', 'medium', '2025-09-01', '', 1, '2025-09-01 14:29:55', '2025-09-01 14:29:55'),
(2, 'Wash2025July', 'Salah Ahmed Said', '+252907372739', 'Washington, Garoowe, Puntland, Somalia', 'Garowe', 8, 'idp', 'high', '2025-09-04', 'Wuxuu u baahanyahay in xaaladdiisa caafimaad lala tacaalo.', 4, '2025-09-04 16:28:34', '2025-09-04 16:28:34'),
(3, 'WASH002', 'Sakariye Mohamed Abdi', '+252907382883', 'Washington, Garowe, Nugal, Somalia', 'Garowe', 10, 'idp', 'medium', '2025-09-05', 'Wuxuu u baahanyahay in laga caawiyo qoyskiisu inay helaan biyo nadiifa', 4, '2025-09-05 04:18:52', '2025-09-05 04:18:52'),
(4, 'WASH003', 'Shuayb Abdi Farah', '+252907382838', 'Washington, Garowe, Nugal, Somalia', 'Garowe', 15, 'idp', 'medium', '2025-09-05', 'Waxay u baahanyihiin qoyskiisu Raashin', 1, '2025-09-05 05:36:16', '2025-09-05 05:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(150) NOT NULL,
  `project_code` varchar(20) DEFAULT NULL,
  `donor_name` varchar(100) DEFAULT NULL,
  `target_location` varchar(100) DEFAULT NULL,
  `target_beneficiaries` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `status` enum('planning','active','completed','suspended') DEFAULT 'planning',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_name`, `project_code`, `donor_name`, `target_location`, `target_beneficiaries`, `start_date`, `end_date`, `budget`, `status`, `description`, `created_by`, `created_at`) VALUES
(1, 'WASH', 'Wash001', 'Nasiye', 'Garowe, Galkaio, Bosaso, Qardho, Burtinle, Galdogob', 1000, '2025-09-01', '2026-07-31', '100000.00', 'active', 'Mashruucaan waa mashruuc loogu talagalay in lagu horumariyo caafimaadka iyo badqabka Bulsheed', 4, '2025-09-04 04:45:41');

-- --------------------------------------------------------

--
-- Table structure for table `supplies`
--

CREATE TABLE `supplies` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('food','water','shelter','hygiene','medical','clothing','other') DEFAULT 'other',
  `unit_type` varchar(20) NOT NULL,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `minimum_stock` decimal(10,2) DEFAULT 0.00,
  `warehouse_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `supplies`
--

INSERT INTO `supplies` (`id`, `item_name`, `category`, `unit_type`, `current_stock`, `minimum_stock`, `warehouse_id`, `expiry_date`, `cost_per_unit`, `supplier`, `notes`, `created_at`, `updated_at`) VALUES
(5, 'Buskud Dhiiq', 'food', 'boxes', '400.00', '100.00', 1, '2027-11-25', '10.00', 'Nasiye', 'Si fiican baa loo gaarsiiyey', '2025-09-01 14:33:12', '2025-09-01 14:35:07'),
(6, 'Daawooyin', 'medical', 'pieces', '9850.00', '100.00', 1, '2026-12-31', '10.00', 'Nasiye', '', '2025-09-04 16:31:32', '2025-09-04 16:32:33'),
(7, 'Bariis', 'food', 'kg', '20.00', '25.00', 1, NULL, '20.00', 'Nasiye', 'Waa Bariis', '2025-09-05 05:37:59', '2025-09-06 09:21:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','coordinator','officer','assistant') DEFAULT 'assistant',
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `email`, `phone`, `created_at`, `last_login`, `is_active`) VALUES
(1, 'admin', '$2y$10$L9MAUl/5FGrb0O.tjbd22.ZUtiVI2hRExpxTAgkw8fIzpTIgnMUfa', 'System Administrator', 'admin', 'admin.hams@gmail.com', '0907263791', '2025-08-31 20:26:26', '2025-09-06 08:55:32', 1),
(3, 'Assistant', '$2y$10$6eMsKgtd1gGSLnrcRX3SMO72tCNCmKlKRjzXSeZ4WZjT71jRyL69K', 'Jama Abdi Abdullahi', 'assistant', 'assistant.jama@gmail.com', '0907808080', '2025-09-04 04:24:22', '2025-09-06 09:15:52', 1),
(4, 'Coordinator', '$2y$10$7NT/q2TgQha272k02S3N..AmZjdv/snCd70mltHxFhR6GUmZ9Nm/K', 'Farah Mohamed', 'coordinator', 'coordinator.farah@gmail.com', '0907888888', '2025-09-04 04:25:05', '2025-09-06 09:13:30', 1),
(5, 'Officer', '$2y$10$HhcUJTCX5JxCIxaxON/ieubGwD6zpZlh.I/hGqgM0ymvSUttFWF.6', 'Sahal Ali', 'officer', 'sahal.officer@gmail.com', '0907382818', '2025-09-04 04:25:55', '2025-09-06 09:16:17', 1),
(6, 'Abdalle', '$2y$10$dyOXXCZJ8ZAYTlrvXcrDB.EsRnz2Bn83x1l8HJyrkx1GQ6RzYt9Su', 'Abdalle Faysal', 'coordinator', 'abdalle@gmail.com', '0907808089', '2025-09-04 15:57:25', '2025-09-04 15:58:27', 1);

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `capacity_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `name`, `location`, `manager_name`, `capacity_description`, `is_active`, `created_at`) VALUES
(1, 'Main Warehouse', 'Mogadishu Central', 'Ahmed Hassan', NULL, 1, '2025-08-31 20:26:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_records`
--
ALTER TABLE `activity_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `aid_deliveries`
--
ALTER TABLE `aid_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `supply_id` (`supply_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `delivered_by` (`delivered_by`);

--
-- Indexes for table `aid_recipients`
--
ALTER TABLE `aid_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `recipient_id` (`recipient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `supplies`
--
ALTER TABLE `supplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_records`
--
ALTER TABLE `activity_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `aid_deliveries`
--
ALTER TABLE `aid_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `aid_recipients`
--
ALTER TABLE `aid_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplies`
--
ALTER TABLE `supplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_records`
--
ALTER TABLE `activity_records`
  ADD CONSTRAINT `activity_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `aid_deliveries`
--
ALTER TABLE `aid_deliveries`
  ADD CONSTRAINT `aid_deliveries_ibfk_1` FOREIGN KEY (`recipient_id`) REFERENCES `aid_recipients` (`id`),
  ADD CONSTRAINT `aid_deliveries_ibfk_2` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`),
  ADD CONSTRAINT `aid_deliveries_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `aid_deliveries_ibfk_4` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `aid_recipients`
--
ALTER TABLE `aid_recipients`
  ADD CONSTRAINT `aid_recipients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `supplies`
--
ALTER TABLE `supplies`
  ADD CONSTRAINT `supplies_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
