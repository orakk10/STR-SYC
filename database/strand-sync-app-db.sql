-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2026 at 01:15 PM
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
-- Database: `strand-sync-app-db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `affected_table` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `affected_table`, `ip_address`, `created_at`) VALUES
(1, 1, 'User Login', NULL, '::1', '2026-09-03 13:11:26'),
(2, 1, 'User Login', NULL, '::1', '2026-09-03 17:47:30'),
(3, 1, 'User Login', NULL, '::1', '2026-09-03 17:59:03'),
(4, 1, 'User Login', NULL, '::1', '2026-09-03 17:59:51'),
(5, 1, 'User Login', NULL, '::1', '2026-09-04 01:07:11'),
(7, 9, 'User Login', NULL, '::1', '2026-09-04 03:00:10'),
(8, 1, 'User Login', NULL, '::1', '2026-09-04 03:03:41'),
(9, 1, 'User Login', NULL, '::1', '2026-09-04 03:03:52'),
(10, 9, 'User Login', NULL, '::1', '2026-09-04 03:07:00'),
(11, 9, 'User Login', NULL, '::1', '2026-09-04 03:13:40'),
(12, 9, 'User Login', NULL, '::1', '2026-09-04 05:37:27'),
(13, 9, 'User Login', NULL, '::1', '2026-09-04 05:39:05'),
(14, 1, 'User Login', NULL, '::1', '2026-09-04 06:00:03'),
(15, 1, 'User Login', NULL, '::1', '2026-09-09 17:44:17'),
(16, 1, 'User Login', NULL, '::1', '2026-09-18 05:11:12'),
(17, 7, 'User Login', NULL, '::1', '2026-09-18 05:17:16'),
(18, 1, 'User Login', NULL, '::1', '2026-09-18 05:20:54'),
(19, 9, 'User Login', NULL, '::1', '2026-09-18 05:22:33'),
(20, 1, 'User Login', NULL, '::1', '2026-09-18 05:31:08'),
(21, 1, 'User Login', NULL, '::1', '2026-09-18 05:45:40'),
(22, 7, 'User Login', NULL, '::1', '2026-09-22 17:48:05'),
(23, 7, 'User Login', NULL, '::1', '2026-09-22 18:09:26'),
(24, 7, 'User Login', NULL, '::1', '2026-09-22 18:42:08'),
(25, 8, 'User Login', NULL, '::1', '2026-09-22 19:46:21'),
(26, 8, 'User Login', NULL, '::1', '2026-09-23 12:33:39'),
(27, 1, 'User Login', NULL, '::1', '2026-09-23 13:48:27'),
(28, 1, 'User Login', NULL, '::1', '2026-09-24 13:56:55'),
(29, 7, 'User Login', NULL, '::1', '2026-09-24 13:57:12'),
(30, 7, 'User Login', NULL, '::1', '2026-09-24 14:13:39'),
(31, 8, 'User Login', NULL, '::1', '2026-09-24 14:29:33'),
(32, 7, 'User Login', NULL, '::1', '2026-10-01 16:56:16'),
(33, 1, 'User Login', NULL, '::1', '2026-10-01 17:17:20'),
(34, 1, 'Created new user: 09876543210 (student)', 'users', '::1', '2026-10-01 17:18:48'),
(36, 1, 'User Login', NULL, '::1', '2026-10-01 17:20:04'),
(37, 7, 'User Login', NULL, '::1', '2026-10-01 17:21:45'),
(38, 1, 'User Login', NULL, '::1', '2026-10-01 17:29:00'),
(39, 8, 'User Login', NULL, '::1', '2026-10-01 17:36:04'),
(40, 1, 'User Login', NULL, '::1', '2026-10-01 17:37:18'),
(41, 1, 'Created new user: 09876543210 (student)', 'users', '::1', '2026-10-01 17:37:37'),
(42, 8, 'User Login', NULL, '::1', '2026-10-01 17:38:20'),
(43, 7, 'User Login', NULL, '::1', '2026-10-01 19:00:24'),
(44, 1, 'User Login', NULL, '::1', '2026-10-01 19:00:53'),
(45, 7, 'User Login', NULL, '::1', '2026-10-01 20:13:13'),
(46, 1, 'User Login', NULL, '::1', '2026-10-01 20:13:49'),
(47, 8, 'User Login', NULL, '::1', '2026-10-01 20:15:19'),
(48, 1, 'User Login', NULL, '::1', '2026-10-02 03:27:44'),
(49, 11, 'User Login', NULL, '::1', '2026-10-02 03:30:37'),
(50, 1, 'User Login', NULL, '::1', '2026-10-02 03:33:10'),
(51, 7, 'User Login', NULL, '::1', '2026-10-02 03:37:17'),
(52, 8, 'User Login', NULL, '::1', '2026-10-02 03:38:17'),
(53, 7, 'User Login', NULL, '::1', '2026-10-02 03:44:30'),
(54, 1, 'User Login', NULL, '::1', '2026-10-02 04:03:23'),
(55, 8, 'User Login', NULL, '::1', '2026-10-02 04:19:18'),
(57, 7, 'User Login', NULL, '::1', '2026-10-08 12:10:26'),
(58, 8, 'User Login', NULL, '::1', '2026-10-08 12:11:03'),
(59, 8, 'Saved scores and summaries for assignment_id: 1', NULL, '::1', '2026-10-08 13:14:30'),
(60, 1, 'User Login', NULL, '::1', '2026-10-13 16:02:59'),
(61, 11, 'User Login', NULL, '::1', '2026-10-13 16:08:30'),
(62, 7, 'User Login', NULL, '::1', '2026-10-13 16:09:20'),
(63, 8, 'User Login', NULL, '::1', '2026-10-13 16:10:04'),
(64, 8, 'Saved scores and summaries for assignment_id: 1', NULL, '::1', '2026-10-13 16:11:50'),
(65, 8, 'Saved scores and summaries for assignment_id: 1', NULL, '::1', '2026-10-13 16:11:56'),
(66, 11, 'User Login', NULL, '::1', '2026-10-13 16:12:34'),
(67, 1, 'User Login', NULL, '::1', '2026-10-13 16:14:21'),
(68, 7, 'User Login', NULL, '::1', '2026-10-14 15:38:38'),
(69, 1, 'User Login', NULL, '::1', '2026-10-14 15:45:38'),
(70, 8, 'User Login', NULL, '::1', '2026-10-14 15:45:52'),
(71, 7, 'User Login', NULL, '::1', '2026-10-14 17:08:58'),
(72, 8, 'User Login', NULL, '::1', '2026-10-14 17:20:07'),
(73, 8, 'User Login', NULL, '::1', '2026-10-15 15:38:08'),
(74, 7, 'User Login', NULL, '::1', '2026-10-15 18:19:42'),
(75, 8, 'User Login', NULL, '::1', '2026-10-15 18:22:21'),
(76, 8, 'User Login', NULL, '::1', '2026-10-15 23:50:02'),
(77, 8, 'User Login', NULL, '::1', '2026-10-16 00:49:06'),
(78, 8, 'User Login', NULL, '::1', '2026-10-16 01:05:46'),
(79, 8, 'User Login', NULL, '::1', '2026-10-16 01:06:23'),
(80, 8, 'User Login', NULL, '::1', '2026-10-16 03:19:38'),
(81, 1, 'User Login', NULL, '::1', '2026-10-17 11:33:34'),
(82, 8, 'User Login', NULL, '::1', '2026-10-17 14:30:20'),
(83, 7, 'User Login', NULL, '::1', '2026-10-19 13:53:08'),
(84, 8, 'User Login', NULL, '::1', '2026-10-19 13:53:38'),
(85, 8, 'User Login', NULL, '::1', '2026-10-19 15:45:54'),
(86, 1, 'User Login', NULL, '::1', '2026-10-20 15:10:57'),
(87, 1, 'User Login', NULL, '::1', '2026-10-21 02:30:51'),
(88, 1, 'Created new user: adv (adviser)', 'users', '::1', '2026-10-21 02:33:45'),
(89, 12, 'User Login', NULL, '::1', '2026-10-21 02:34:31'),
(90, 1, 'User Login', NULL, '::1', '2026-10-21 02:34:51'),
(91, 12, 'User Login', NULL, '::1', '2026-10-21 02:38:08'),
(92, 1, 'User Login', NULL, '::1', '2026-10-21 02:38:31'),
(93, 12, 'User Login', NULL, '::1', '2026-10-21 02:39:50'),
(94, 1, 'User Login', NULL, '::1', '2026-10-21 02:40:04'),
(95, 12, 'User Login', NULL, '::1', '2026-10-21 02:46:55'),
(96, 1, 'User Login', NULL, '::1', '2026-10-21 02:48:52'),
(97, 12, 'User Login', NULL, '::1', '2026-10-21 02:50:36'),
(98, 1, 'User Login', NULL, '::1', '2026-10-21 02:51:03'),
(99, 12, 'User Login', NULL, '::1', '2026-10-21 03:33:10'),
(100, 1, 'User Login', NULL, '::1', '2026-10-21 03:33:27'),
(101, 12, 'User Login', NULL, '::1', '2026-10-21 03:56:32'),
(102, 1, 'User Login', NULL, '::1', '2026-10-21 03:56:55'),
(103, 12, 'User Login', NULL, '::1', '2026-10-21 04:05:26'),
(104, 1, 'User Login', NULL, '::1', '2026-10-21 04:09:51'),
(105, 12, 'User Login', NULL, '::1', '2026-10-21 04:10:34'),
(106, 1, 'User Login', NULL, '::1', '2026-10-21 04:11:22'),
(107, 12, 'User Login', NULL, '::1', '2026-10-21 04:21:30'),
(108, 1, 'User Login', NULL, '::1', '2026-10-21 04:23:45'),
(109, 22, 'User Login', NULL, '::1', '2026-10-21 04:25:13'),
(110, 1, 'User Login', NULL, '::1', '2026-10-21 04:25:32'),
(111, 22, 'User Login', NULL, '::1', '2026-10-21 04:30:30'),
(112, 12, 'User Login', NULL, '::1', '2026-10-21 04:30:45'),
(113, 1, 'User Login', NULL, '::1', '2026-10-21 04:30:59'),
(114, 1, 'User Login', NULL, '::1', '2026-10-21 04:38:30'),
(115, 1, 'User Login', NULL, '::1', '2026-10-22 06:36:04'),
(116, 12, 'User Login', NULL, '::1', '2026-10-22 06:36:58'),
(117, 12, 'Saved scores and summaries for assignment_id: 3', NULL, '::1', '2026-10-22 06:37:34'),
(118, 1, 'User Login', NULL, '::1', '2026-10-22 06:37:52'),
(119, 22, 'User Login', NULL, '::1', '2026-10-22 06:38:24'),
(120, 12, 'User Login', NULL, '::1', '2026-10-22 06:38:50'),
(121, 1, 'User Login', NULL, '::1', '2026-10-22 06:39:29'),
(122, 12, 'User Login', NULL, '::1', '2026-10-22 06:43:23'),
(123, 1, 'User Login', NULL, '::1', '2026-10-22 06:54:26'),
(124, 12, 'User Login', NULL, '::1', '2026-10-22 06:55:00'),
(125, 1, 'User Login', NULL, '::1', '2026-10-22 07:00:30'),
(126, 1, 'User Login', NULL, '::1', '2026-10-22 07:07:37'),
(127, 12, 'User Login', NULL, '::1', '2026-10-22 07:07:45'),
(128, 1, 'User Login', NULL, '::1', '2026-10-22 07:11:19'),
(129, 12, 'User Login', NULL, '::1', '2026-10-22 07:11:44'),
(130, 22, 'User Login', NULL, '::1', '2026-10-22 10:44:48'),
(131, 1, 'User Login', NULL, '::1', '2026-10-22 10:46:17'),
(132, 1, 'Created new user: faculty.ella (faculty)', 'users', '::1', '2026-10-22 10:52:42'),
(133, 1, 'Created new user: faculty.ange (faculty)', 'users', '::1', '2026-10-22 10:53:10'),
(134, 33, 'User Login', NULL, '::1', '2026-10-22 10:55:51'),
(135, 1, 'User Login', NULL, '::1', '2026-10-22 10:56:51'),
(136, 22, 'User Login', NULL, '::1', '2026-10-22 10:58:26');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_type` enum('section','subject','all') NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_students`
--

CREATE TABLE `archived_students` (
  `id` int(11) NOT NULL,
  `original_id` int(11) DEFAULT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `strand_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `batch_year` year(4) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ecr_assessment_components`
--

CREATE TABLE `ecr_assessment_components` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `category` enum('WW','PT','QA','EX','TA') NOT NULL,
  `task_number` int(11) NOT NULL,
  `highest_possible_score` decimal(5,2) NOT NULL DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ecr_assessment_components`
--

INSERT INTO `ecr_assessment_components` (`id`, `assignment_id`, `term`, `category`, `task_number`, `highest_possible_score`) VALUES
(161, 3, 'Term 1', 'WW', 1, 100.00),
(162, 3, 'Term 1', 'WW', 2, 100.00),
(163, 3, 'Term 1', 'WW', 3, 100.00),
(164, 3, 'Term 1', 'WW', 4, 100.00),
(165, 3, 'Term 1', 'WW', 5, 100.00),
(166, 3, 'Term 1', 'WW', 6, 100.00),
(167, 3, 'Term 1', 'WW', 7, 100.00),
(168, 3, 'Term 1', 'WW', 8, 100.00),
(169, 3, 'Term 1', 'PT', 1, 100.00),
(170, 3, 'Term 1', 'PT', 2, 100.00),
(171, 3, 'Term 1', 'PT', 3, 100.00),
(172, 3, 'Term 1', 'PT', 4, 100.00),
(173, 3, 'Term 1', 'PT', 5, 100.00),
(174, 3, 'Term 1', 'PT', 6, 100.00),
(175, 3, 'Term 1', 'QA', 1, 100.00),
(176, 4, 'Term 2', 'WW', 1, 100.00),
(177, 4, 'Term 2', 'WW', 2, 100.00),
(178, 4, 'Term 2', 'WW', 3, 100.00),
(179, 4, 'Term 2', 'WW', 4, 100.00),
(180, 4, 'Term 2', 'WW', 5, 100.00),
(181, 4, 'Term 2', 'WW', 6, 100.00),
(182, 4, 'Term 2', 'WW', 7, 100.00),
(183, 4, 'Term 2', 'WW', 8, 100.00),
(184, 4, 'Term 2', 'PT', 1, 100.00),
(185, 4, 'Term 2', 'PT', 2, 100.00),
(186, 4, 'Term 2', 'PT', 3, 100.00),
(187, 4, 'Term 2', 'PT', 4, 100.00),
(188, 4, 'Term 2', 'PT', 5, 100.00),
(189, 4, 'Term 2', 'PT', 6, 100.00),
(190, 4, 'Term 2', 'QA', 1, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `ecr_promotion_verifications_by_assignment`
--

CREATE TABLE `ecr_promotion_verifications_by_assignment` (
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `verified_by` bigint(20) UNSIGNED NOT NULL,
  `verified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ecr_score_drafts`
--

CREATE TABLE `ecr_score_drafts` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `term` varchar(10) NOT NULL,
  `draft_data` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ecr_student_scores`
--

CREATE TABLE `ecr_student_scores` (
  `id` int(11) NOT NULL,
  `component_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ecr_term_summaries`
--

CREATE TABLE `ecr_term_summaries` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `term1_transmuted` decimal(5,2) DEFAULT NULL,
  `term2_transmuted` decimal(5,2) DEFAULT NULL,
  `term3_transmuted` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `letter_grade` varchar(30) DEFAULT NULL,
  `remarks` enum('Passed','Failed') DEFAULT 'Passed',
  `is_locked` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `section_id`, `subject_id`, `day_of_week`, `start_time`, `end_time`, `room_number`) VALUES
(1, 8, 49, 'Monday', '08:30:00', '10:30:00', 'Room 201');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `strand_id` int(11) DEFAULT NULL,
  `grade_level` int(11) NOT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '2026-2027',
  `curriculum_version` enum('Legacy','Strengthened') NOT NULL DEFAULT 'Strengthened'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `strand_id`, `grade_level`, `adviser_id`, `academic_year`, `curriculum_version`) VALUES
(2, '11-Rizal', 4, 11, NULL, '2025-2026', 'Legacy'),
(3, '11-Bonifacio', 4, 11, NULL, '2025-2026', 'Legacy'),
(4, '12-Mabini', 4, 12, NULL, '2025-2026', 'Legacy'),
(5, '12-Luna', 4, 12, NULL, '2025-2026', 'Legacy'),
(6, '11-Aquino', NULL, 11, NULL, '2026-2027', 'Strengthened'),
(7, '11-Del Pilar', NULL, 11, 8, '2026-2027', 'Strengthened'),
(8, '12-Silang', NULL, 12, 12, '2026-2027', 'Strengthened'),
(9, '12-Jacinto', NULL, 12, NULL, '2026-2027', 'Strengthened');

-- --------------------------------------------------------

--
-- Table structure for table `strands`
--

CREATE TABLE `strands` (
  `id` int(11) NOT NULL,
  `strand_code` varchar(20) NOT NULL,
  `strand_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `strands`
--

INSERT INTO `strands` (`id`, `strand_code`, `strand_name`, `description`) VALUES
(1, 'STEM', 'Science, Technology, Engineering, and Mathematics', 'Academic Track focusing on advanced science and mathematics subjects.'),
(2, 'ABM', 'Accountancy, Business, and Management', 'Academic Track focusing on basic financial management, accounting, and business operations.'),
(3, 'HUMSS', 'Humanities and Social Sciences', 'Academic Track focusing on social research, journalism, politics, and human behavior.'),
(4, 'GAS', 'General Academic Strand', 'Academic Track providing a flexible combination of electives across different disciplines.'),
(5, 'TVL-ICT', 'TVL - Information and Communications Technology', 'Technical-Vocational Track covering computer systems, programming, and digital media.'),
(10, 'ASH', 'Arts, Social Sciences, and Humanities', 'Academic Cluster for Arts and Social Sciences'),
(11, 'BE', 'Business and Entrepreneurship', 'Academic Cluster for Business and Finance'),
(12, 'STEM-C', 'Science, Technology, Engineering, and Mathematics', 'Academic Cluster for STEM Electives'),
(13, 'SHW', 'Sports, Health, and Wellness', 'Academic Cluster for Sports and Physical Education'),
(14, 'FE', 'Field Experience', 'Academic Cluster for Apprenticeship and Immersion');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `user_id` int(11) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('Male','Female','N/A') DEFAULT 'N/A',
  `address` text DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`user_id`, `birthdate`, `gender`, `address`, `contact_no`, `guardian_name`, `guardian_contact`, `specialization`) VALUES
(7, '2010-09-02', 'Male', '', '', '', '', 'Mathematics'),
(9, '2005-11-15', 'Male', '', '', '', '', ''),
(11, '2005-11-15', 'Male', '', '', '', '', ''),
(12, '2005-02-10', 'Male', '', '', '', '', ''),
(13, '2009-02-14', 'Female', 'Sample Address, Barangay 1', '9170000001', 'Maria Anderson', '9180000001', 'N/A'),
(14, '2008-11-03', 'Male', 'Sample Address, Barangay 2', '9170000002', 'Jose Bautista', '9180000002', 'N/A'),
(15, '2009-06-22', 'Female', 'Sample Address, Barangay 3', '9170000003', 'Elena Castillo', '9180000003', 'N/A'),
(16, '2008-09-17', 'Male', 'Sample Address, Barangay 4', '9170000004', 'Ramon Dela Cruz', '9180000004', 'N/A'),
(17, '2008-01-30', 'Female', 'Sample Address, Barangay 5', '9170000005', 'Lourdes Evangelista', '9180000005', 'N/A'),
(18, '2007-12-08', 'Male', 'Sample Address, Barangay 6', '9170000006', 'Daniel Flores', '9180000006', 'N/A'),
(19, '2008-04-11', 'Female', 'Sample Address, Barangay 7', '9170000007', 'Teresa Garcia', '9180000007', 'N/A'),
(20, '2007-08-26', 'Male', 'Sample Address, Barangay 8', '9170000008', 'Antonio Hernandez', '9180000008', 'N/A'),
(21, '2009-03-09', 'Female', 'Sample Address, Barangay 9', '9170000009', 'Cecilia Ibarra', '9180000009', 'N/A'),
(22, '2008-07-19', 'Male', 'Sample Address, Barangay 10', '9170000010', 'Roberto Jimenez', '9180000010', 'N/A'),
(33, NULL, 'Male', NULL, NULL, NULL, NULL, NULL),
(34, NULL, 'Male', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `curriculum_version` enum('Legacy','Strengthened') NOT NULL DEFAULT 'Strengthened',
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `type` enum('Core','Applied','Specialized','Elective') NOT NULL DEFAULT 'Core',
  `strand_id` int(11) DEFAULT NULL,
  `grade_level` int(11) NOT NULL,
  `term` enum('1st','2nd','3rd','1st Sem','2nd Sem','Full Year') NOT NULL DEFAULT '1st',
  `units` int(11) DEFAULT 3,
  `hours` int(11) DEFAULT 80
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `curriculum_version`, `subject_code`, `subject_name`, `type`, `strand_id`, `grade_level`, `term`, `units`, `hours`) VALUES
(3, 'Strengthened', 'CORE-ORAL', 'Oral Communication in Context', 'Core', NULL, 11, '1st Sem', 3, 80),
(4, 'Strengthened', 'CORE-KOM', 'Komunikasyon at Pananaliksik sa Wika at Kulturang Pilipino', 'Core', NULL, 11, '1st Sem', 3, 80),
(5, 'Strengthened', 'CORE-GMATH', 'General Mathematics', 'Core', NULL, 11, '1st Sem', 3, 80),
(6, 'Strengthened', 'CORE-ELSCI', 'Earth and Life Science', 'Core', NULL, 11, '1st Sem', 3, 80),
(7, 'Strengthened', 'CORE-PED1', 'Physical Education and Health 1', 'Core', NULL, 11, '1st Sem', 1, 20),
(8, 'Strengthened', 'CORE-READ', 'Reading and Writing Skills', 'Core', NULL, 11, '2nd Sem', 3, 80),
(9, 'Strengthened', 'CORE-PAGB', 'Pagbasa at Pagsusuri ng Iba\'t Ibang Teksto Tungo sa Pananaliksik', 'Core', NULL, 11, '2nd Sem', 3, 80),
(10, 'Strengthened', 'CORE-STAT', 'Statistics and Probability', 'Core', NULL, 11, '2nd Sem', 3, 80),
(11, 'Strengthened', 'CORE-PSCI', 'Physical Science', 'Core', NULL, 11, '2nd Sem', 3, 80),
(12, 'Strengthened', 'CORE-PERDEV', 'Personal Development', 'Core', NULL, 11, '2nd Sem', 3, 80),
(13, 'Strengthened', 'CORE-PED2', 'Physical Education and Health 2', 'Core', NULL, 11, '2nd Sem', 1, 20),
(14, 'Strengthened', 'CORE-21ST', '21st Century Literature from the Philippines and the World', 'Core', NULL, 12, '1st Sem', 3, 80),
(15, 'Strengthened', 'CORE-PHILOS', 'Introduction to the Philosophy of the Human Person', 'Core', NULL, 12, '1st Sem', 3, 80),
(16, 'Strengthened', 'CORE-UCSP', 'Understanding Culture, Society, and Politics', 'Core', NULL, 12, '1st Sem', 3, 80),
(17, 'Strengthened', 'CORE-PED3', 'Physical Education and Health 3', 'Core', NULL, 12, '1st Sem', 1, 20),
(18, 'Strengthened', 'CORE-CPAR', 'Contemporary Philippine Arts from the Regions', 'Core', NULL, 12, '2nd Sem', 3, 80),
(19, 'Strengthened', 'CORE-MIL', 'Media and Information Literacy', 'Core', NULL, 12, '2nd Sem', 3, 80),
(20, 'Strengthened', 'CORE-PED4', 'Physical Education and Health 4', 'Core', NULL, 12, '2nd Sem', 1, 20),
(21, 'Strengthened', 'APP-EAPP', 'English for Academic and Professional Purposes', 'Applied', NULL, 11, '1st Sem', 3, 80),
(22, 'Strengthened', 'APP-ETECH', 'Empowerment Technologies (ICT)', 'Applied', NULL, 11, '1st Sem', 3, 80),
(23, 'Strengthened', 'APP-RDL1', 'Practical Research 1 (Qualitative)', 'Applied', NULL, 11, '2nd Sem', 3, 80),
(24, 'Strengthened', 'APP-FILIPINO', 'Filipino sa Piling Larangan (Akademik)', 'Applied', NULL, 11, '2nd Sem', 3, 80),
(25, 'Strengthened', 'APP-RDL2', 'Practical Research 2 (Quantitative)', 'Applied', NULL, 12, '1st Sem', 3, 80),
(26, 'Strengthened', 'APP-ENTREP', 'Entrepreneurship', 'Applied', NULL, 12, '1st Sem', 3, 80),
(27, 'Strengthened', 'APP-3I', 'Inquiries, Investigations, and Immersion', 'Applied', NULL, 12, '2nd Sem', 3, 80),
(46, 'Strengthened', 'GAS-HUM1', 'Humanities 1 (Creative Writing / Malikhaing Pagsulat)', 'Specialized', 4, 11, '1st Sem', 3, 80),
(47, 'Strengthened', 'GAS-ORGMAN', 'Organization and Management', 'Specialized', 4, 11, '2nd Sem', 3, 80),
(48, 'Strengthened', 'GAS-HUM2', 'Humanities 2 (Introduction to World Religions and Belief Systems)', 'Specialized', 4, 11, '2nd Sem', 3, 80),
(49, 'Strengthened', 'GAS-DRRR', 'Disaster Readiness and Risk Reduction', 'Specialized', 4, 12, '1st Sem', 3, 80),
(50, 'Strengthened', 'GAS-APPECON', 'Applied Economics', 'Specialized', 4, 12, '1st Sem', 3, 80),
(51, 'Strengthened', 'GAS-SOCSCI', 'Social Science 1 (Disciplines and Ideas in the Social Sciences)', 'Specialized', 4, 12, '1st Sem', 3, 80),
(52, 'Strengthened', 'GAS-ELECT1', 'Elective 1 (Selected Track/Strand Elective)', 'Specialized', 4, 12, '2nd Sem', 3, 80),
(53, 'Strengthened', 'GAS-ELECT2', 'Elective 2 (Selected Track/Strand Elective)', 'Specialized', 4, 12, '2nd Sem', 3, 80),
(54, 'Strengthened', 'GAS-IMMERSION', 'Work Immersion / Culminating Activity', 'Specialized', 4, 12, '2nd Sem', 3, 80),
(55, 'Strengthened', 'CORE-EFFCOMM', 'Effective Communication / Mabisang Komunikasyon', 'Core', NULL, 11, 'Full Year', 2, 80),
(56, 'Strengthened', 'CORE-GENMATH', 'General Mathematics', 'Core', NULL, 11, 'Full Year', 2, 80),
(57, 'Strengthened', 'CORE-GENSCI', 'General Science', 'Core', NULL, 11, 'Full Year', 2, 80),
(58, 'Strengthened', 'CORE-LCS', 'Life and Career Skills', 'Core', NULL, 11, '1st', 2, 80),
(59, 'Strengthened', 'CORE-PAKALP', 'Pag-aaral ng Kasaysayan at Lipunang Pilipino', 'Core', NULL, 11, '1st', 2, 80),
(60, 'Strengthened', 'ASH-ARTCRIT', 'Art Criticism and Creative Markets', 'Elective', 10, 11, '1st', 3, 80),
(61, 'Strengthened', 'ASH-CIVARTS', 'Creative Industries - Visual Arts', 'Elective', 10, 11, '1st', 3, 80),
(62, 'Strengthened', 'ASH-CILARTS', 'Creative Industries - Literary Arts', 'Elective', 10, 11, '1st', 3, 80),
(63, 'Strengthened', 'ASH-CIMARTS', 'Creative Industries - Media Arts', 'Elective', 10, 11, '1st', 3, 80),
(64, 'Strengthened', 'ASH-CIARTS', 'Creative Industries - Applied and Traditional Arts', 'Elective', 10, 11, '1st', 3, 80),
(65, 'Strengthened', 'ASH-CIMUSIC', 'Creative Industries - Music', 'Elective', 10, 11, '1st', 3, 80),
(66, 'Strengthened', 'ASH-CIDANCE', 'Creative Industries - Dance', 'Elective', 10, 11, '1st', 3, 80),
(67, 'Strengthened', 'ASH-CITHEATER', 'Creative Industries - Theater Arts', 'Elective', 10, 11, '1st', 3, 80),
(68, 'Strengthened', 'ASH-CITIZEN', 'Citizenship and Civic Engagement', 'Elective', 10, 11, '1st', 3, 80),
(69, 'Strengthened', 'ASH-CONLIT1', 'Contemporary Literature 1', 'Elective', 10, 11, '1st', 3, 80),
(70, 'Strengthened', 'ASH-CONLIT2', 'Contemporary Literature 2', 'Elective', 10, 11, '2nd', 3, 80),
(71, 'Strengthened', 'ASH-COMP1', 'Creative Composition 1', 'Elective', 10, 11, '1st', 3, 80),
(72, 'Strengthened', 'ASH-COMP2', 'Creative Composition 2', 'Elective', 10, 11, '2nd', 3, 80),
(73, 'Strengthened', 'ASH-PRODPRES', 'Creative Production and Presentation', 'Elective', 10, 12, '1st', 6, 160),
(74, 'Strengthened', 'ASH-FIL1', 'Filipino 1 (Wika at Komunikasyon sa Akademikong Filipino)', 'Elective', 10, 11, '1st', 3, 80),
(75, 'Strengthened', 'ASH-FIL2SPORTS', 'Filipino 2 (Filipino sa Isports)', 'Elective', 10, 11, '2nd', 3, 80),
(76, 'Strengthened', 'ASH-FIL2ARTS', 'Filipino 2 (Filipino sa Sining at Disenyo)', 'Elective', 10, 11, '2nd', 3, 80),
(77, 'Strengthened', 'ASH-FIL2TECH', 'Filipino 2 (Filipino sa Larang Teknikal-Propesyonal)', 'Elective', 10, 11, '2nd', 3, 80),
(78, 'Strengthened', 'ASH-FILIDENTITY', 'Filipino Identity Through the Arts', 'Elective', 10, 11, '1st', 3, 80),
(79, 'Strengthened', 'ASH-PHILOS', 'Introduction to the Philosophy of the Human Person', 'Elective', 10, 11, '1st', 3, 80),
(80, 'Strengthened', 'ASH-LEADARTS', 'Leadership and Management in the Arts', 'Elective', 10, 12, '1st', 3, 80),
(81, 'Strengthened', 'ASH-MALIKHAING', 'Malikhaing Pagsulat', 'Elective', 10, 11, '1st', 3, 80),
(82, 'Strengthened', 'ASH-PERFCRIT', 'Performance Criticism and Creative Markets', 'Elective', 10, 12, '1st', 3, 80),
(83, 'Strengthened', 'ASH-GOVERNANCE', 'Philippine Governance (Philippine Politics and Governance)', 'Elective', 10, 11, '1st', 3, 80),
(84, 'Strengthened', 'ASH-SOCSCI', 'Social Sciences (Theory and Practice)', 'Elective', 10, 11, '1st', 3, 80),
(85, 'Strengthened', 'BE-BUS1', 'Business 1 (Basic Accounting)', 'Elective', 11, 11, '1st', 3, 80),
(86, 'Strengthened', 'BE-BUS2', 'Business 2 (Business Finance and Income Taxation)', 'Elective', 11, 11, '2nd', 3, 80),
(87, 'Strengthened', 'BE-BUS3', 'Business 3 (Business Economics)', 'Elective', 11, 12, '1st', 3, 80),
(88, 'Strengthened', 'BE-MARKETING', 'Contemporary Marketing', 'Elective', 11, 11, '1st', 3, 80),
(89, 'Strengthened', 'BE-ENTREP', 'Entrepreneurship', 'Elective', 11, 11, '1st', 3, 80),
(90, 'Strengthened', 'BE-ORGMAN', 'Introduction to Organization and Management', 'Elective', 11, 11, '1st', 3, 80),
(91, 'Strengthened', 'STEM-ADVMATH', 'Advanced Mathematics', 'Elective', 12, 12, '1st', 3, 80),
(92, 'Strengthened', 'STEM-BASCALC', 'Basic Calculus', 'Elective', 12, 11, '2nd', 3, 80),
(93, 'Strengthened', 'STEM-BIO1', 'Biology 1', 'Elective', 12, 11, '1st', 3, 80),
(94, 'Strengthened', 'STEM-BIO2', 'Biology 2', 'Elective', 12, 11, '2nd', 3, 80),
(95, 'Strengthened', 'STEM-BIO3', 'Biology 3', 'Elective', 12, 12, '1st', 3, 80),
(96, 'Strengthened', 'STEM-BIO4', 'Biology 4', 'Elective', 12, 12, '2nd', 3, 80),
(97, 'Strengthened', 'STEM-CHEM1', 'Chemistry 1', 'Elective', 12, 11, '1st', 3, 80),
(98, 'Strengthened', 'STEM-CHEM2', 'Chemistry 2', 'Elective', 12, 11, '2nd', 3, 80),
(99, 'Strengthened', 'STEM-CHEM3', 'Chemistry 3', 'Elective', 12, 12, '1st', 3, 80),
(100, 'Strengthened', 'STEM-CHEM4', 'Chemistry 4', 'Elective', 12, 12, '2nd', 3, 80),
(101, 'Strengthened', 'STEM-CPCHEM', 'Conceptual Physics and Chemistry in Daily Life', 'Elective', 12, 11, '1st', 3, 80),
(102, 'Strengthened', 'STEM-CBEARTH', 'Conceptual Biology and Earth and Space Science', 'Elective', 12, 11, '1st', 3, 80),
(103, 'Strengthened', 'STEM-DBMAN', 'Database Management', 'Elective', 12, 11, '2nd', 3, 80),
(104, 'Strengthened', 'STEM-EARTH1', 'Earth and Space Science 1', 'Elective', 12, 11, '1st', 3, 80),
(105, 'Strengthened', 'STEM-EARTH2', 'Earth and Space Science 2', 'Elective', 12, 11, '2nd', 3, 80),
(106, 'Strengthened', 'STEM-EARTH3', 'Earth and Space Science 3', 'Elective', 12, 12, '1st', 3, 80),
(107, 'Strengthened', 'STEM-EARTH4', 'Earth and Space Science 4', 'Elective', 12, 12, '2nd', 3, 80),
(108, 'Strengthened', 'STEM-ETECH', 'Empowerment Technologies', 'Elective', 12, 11, '1st', 3, 80),
(109, 'Strengthened', 'STEM-FINMATH1', 'Finite Mathematics 1', 'Elective', 12, 11, '1st', 3, 80),
(110, 'Strengthened', 'STEM-FINMATH2', 'Finite Mathematics 2', 'Elective', 12, 11, '2nd', 3, 80),
(111, 'Strengthened', 'STEM-DATAANALYTICS', 'Fundamentals of Data Analytics', 'Elective', 12, 12, '1st', 3, 80),
(112, 'Strengthened', 'STEM-PHYS1', 'Physics 1', 'Elective', 12, 11, '1st', 3, 80),
(113, 'Strengthened', 'STEM-PHYS2', 'Physics 2', 'Elective', 12, 11, '2nd', 3, 80),
(114, 'Strengthened', 'STEM-PHYS3', 'Physics 3', 'Elective', 12, 12, '1st', 3, 80),
(115, 'Strengthened', 'STEM-PHYS4', 'Physics 4', 'Elective', 12, 12, '2nd', 3, 80),
(116, 'Strengthened', 'STEM-PRECALC', 'Pre-Calculus', 'Elective', 12, 11, '1st', 3, 80),
(117, 'Strengthened', 'SHW-EXPROG', 'Exercise and Sports Programming', 'Elective', 13, 11, '1st', 3, 80),
(118, 'Strengthened', 'SHW-FIRSTAID', 'First Aid', 'Elective', 13, 11, '1st', 3, 80),
(119, 'Strengthened', 'SHW-BLS', 'Fundamentals of Basic Life Support', 'Elective', 13, 11, '1st', 3, 80),
(120, 'Strengthened', 'SHW-HUMMOV1', 'Human Movement 1 (Basic Anatomy in Sports and Exercise)', 'Elective', 13, 11, '1st', 3, 80),
(121, 'Strengthened', 'SHW-HUMMOV2', 'Human Movement 2 (Motor Skills Development)', 'Elective', 13, 11, '2nd', 3, 80),
(122, 'Strengthened', 'SHW-PE1', 'Physical Education 1 (Fitness and Recreation)', 'Elective', 13, 11, '1st', 3, 80),
(123, 'Strengthened', 'SHW-PE2', 'Physical Education 2 (Sports and Dance)', 'Elective', 13, 11, '2nd', 3, 80),
(124, 'Strengthened', 'SHW-SPACTMAN', 'Sports Activity Management', 'Elective', 13, 12, '1st', 3, 80),
(125, 'Strengthened', 'SHW-SPCOACH', 'Sports Coaching', 'Elective', 13, 12, '1st', 3, 80),
(126, 'Strengthened', 'SHW-SPOFFICIAT', 'Sports Officiating', 'Elective', 13, 12, '2nd', 3, 80),
(127, 'Strengthened', 'FE-APPDANCE', 'Arts Apprenticeship – Dance', 'Elective', 14, 12, '2nd', 6, 160),
(128, 'Strengthened', 'FE-APPLIT', 'Arts Apprenticeship - Literary Arts', 'Elective', 14, 12, '2nd', 6, 160),
(129, 'Strengthened', 'FE-APPMEDIA', 'Arts Apprenticeship - Media Arts', 'Elective', 14, 12, '2nd', 6, 160),
(130, 'Strengthened', 'FE-APPMUSIC', 'Arts Apprenticeship – Music', 'Elective', 14, 12, '2nd', 6, 160),
(131, 'Strengthened', 'FE-APPTHEATER', 'Arts Apprenticeship – Theater Arts', 'Elective', 14, 12, '2nd', 6, 160),
(132, 'Strengthened', 'FE-APPTRAD', 'Arts Apprenticeship – Traditional Cultural Expressions', 'Elective', 14, 12, '2nd', 6, 160),
(133, 'Strengthened', 'FE-APPVISUAL', 'Arts Apprenticeship – Visual Arts', 'Elective', 14, 12, '2nd', 6, 160),
(134, 'Strengthened', 'FE-DESIGNINNOV', 'Design and Innovation', 'Elective', 14, 12, '1st', 3, 80),
(135, 'Strengthened', 'FE-RES1', 'Research 1', 'Elective', 14, 11, '2nd', 3, 80),
(136, 'Strengthened', 'FE-RES2', 'Research 2', 'Elective', 14, 12, '1st', 3, 80),
(137, 'Strengthened', 'FE-FIELDSPORTS', 'In-Campus Field Exposure for Sports', 'Elective', 14, 12, '1st', 3, 80),
(138, 'Strengthened', 'FE-FIELDOFF', 'Field Exposure (Off Campus)', 'Elective', 14, 12, '2nd', 3, 80),
(139, 'Strengthened', 'FE-WORKIMMERSION', 'Work Immersion for Academic Track', 'Elective', 14, 12, '2nd', 3, 80),
(140, 'Strengthened', 'CORE-EFFCOMM', 'Effective Communication / Mabisang Komunikasyon', 'Core', NULL, 11, 'Full Year', 2, 80),
(141, 'Strengthened', 'CORE-GENMATH', 'General Mathematics', 'Core', NULL, 11, 'Full Year', 2, 80),
(142, 'Strengthened', 'CORE-GENSCI', 'General Science', 'Core', NULL, 11, 'Full Year', 2, 80),
(143, 'Strengthened', 'CORE-LCS', 'Life and Career Skills', 'Core', NULL, 11, '1st', 2, 80),
(144, 'Strengthened', 'CORE-PAKALP', 'Pag-aaral ng Kasaysayan at Lipunang Pilipino', 'Core', NULL, 11, '1st', 2, 80),
(145, 'Legacy', 'CORE-ORAL', 'Oral Communication in Context', 'Core', NULL, 11, '1st Sem', 3, 80),
(146, 'Legacy', 'CORE-KOM', 'Komunikasyon at Pananaliksik sa Wika at Kulturang Pilipino', 'Core', NULL, 11, '1st Sem', 3, 80),
(147, 'Legacy', 'CORE-GMATH', 'General Mathematics', 'Core', NULL, 11, '1st Sem', 3, 80),
(148, 'Legacy', 'CORE-ELSCI', 'Earth and Life Science', 'Core', NULL, 11, '1st Sem', 3, 80),
(149, 'Legacy', 'CORE-PED1', 'Physical Education and Health 1', 'Core', NULL, 11, '1st Sem', 1, 20),
(150, 'Legacy', 'CORE-READ', 'Reading and Writing Skills', 'Core', NULL, 11, '2nd Sem', 3, 80),
(151, 'Legacy', 'CORE-PAGB', 'Pagbasa at Pagsusuri ng Iba\'t Ibang Teksto Tungo sa Pananaliksik', 'Core', NULL, 11, '2nd Sem', 3, 80),
(152, 'Legacy', 'CORE-STAT', 'Statistics and Probability', 'Core', NULL, 11, '2nd Sem', 3, 80),
(153, 'Legacy', 'CORE-PSCI', 'Physical Science', 'Core', NULL, 11, '2nd Sem', 3, 80),
(154, 'Legacy', 'CORE-PERDEV', 'Personal Development', 'Core', NULL, 11, '2nd Sem', 3, 80),
(155, 'Legacy', 'CORE-PED2', 'Physical Education and Health 2', 'Core', NULL, 11, '2nd Sem', 1, 20),
(156, 'Legacy', 'CORE-21ST', '21st Century Literature from the Philippines and the World', 'Core', NULL, 12, '1st Sem', 3, 80),
(157, 'Legacy', 'CORE-PHILOS', 'Introduction to the Philosophy of the Human Person', 'Core', NULL, 12, '1st Sem', 3, 80),
(158, 'Legacy', 'CORE-UCSP', 'Understanding Culture, Society, and Politics', 'Core', NULL, 12, '1st Sem', 3, 80),
(159, 'Legacy', 'CORE-PED3', 'Physical Education and Health 3', 'Core', NULL, 12, '1st Sem', 1, 20),
(160, 'Legacy', 'CORE-CPAR', 'Contemporary Philippine Arts from the Regions', 'Core', NULL, 12, '2nd Sem', 3, 80),
(161, 'Legacy', 'CORE-MIL', 'Media and Information Literacy', 'Core', NULL, 12, '2nd Sem', 3, 80),
(162, 'Legacy', 'CORE-PED4', 'Physical Education and Health 4', 'Core', NULL, 12, '2nd Sem', 1, 20),
(163, 'Legacy', 'APP-EAPP', 'English for Academic and Professional Purposes', 'Applied', NULL, 11, '1st Sem', 3, 80),
(164, 'Legacy', 'APP-ETECH', 'Empowerment Technologies (ICT)', 'Applied', NULL, 11, '1st Sem', 3, 80),
(165, 'Legacy', 'APP-RDL1', 'Practical Research 1 (Qualitative)', 'Applied', NULL, 11, '2nd Sem', 3, 80),
(166, 'Legacy', 'APP-FILIPINO', 'Filipino sa Piling Larangan (Akademik)', 'Applied', NULL, 11, '2nd Sem', 3, 80),
(167, 'Legacy', 'APP-RDL2', 'Practical Research 2 (Quantitative)', 'Applied', NULL, 12, '1st Sem', 3, 80),
(168, 'Legacy', 'APP-ENTREP', 'Entrepreneurship', 'Applied', NULL, 12, '1st Sem', 3, 80),
(169, 'Legacy', 'APP-3I', 'Inquiries, Investigations, and Immersion', 'Applied', NULL, 12, '2nd Sem', 3, 80),
(170, 'Legacy', 'GAS-HUM1', 'Humanities 1 (Creative Writing / Malikhaing Pagsulat)', 'Specialized', 4, 11, '1st Sem', 3, 80),
(171, 'Legacy', 'GAS-ORGMAN', 'Organization and Management', 'Specialized', 4, 11, '2nd Sem', 3, 80),
(172, 'Legacy', 'GAS-HUM2', 'Humanities 2 (Introduction to World Religions and Belief Systems)', 'Specialized', 4, 11, '2nd Sem', 3, 80),
(173, 'Legacy', 'GAS-DRRR', 'Disaster Readiness and Risk Reduction', 'Specialized', 4, 12, '1st Sem', 3, 80),
(174, 'Legacy', 'GAS-APPECON', 'Applied Economics', 'Specialized', 4, 12, '1st Sem', 3, 80),
(175, 'Legacy', 'GAS-SOCSCI', 'Social Science 1 (Disciplines and Ideas in the Social Sciences)', 'Specialized', 4, 12, '1st Sem', 3, 80),
(176, 'Legacy', 'GAS-ELECT1', 'Elective 1 (Selected Track/Strand Elective)', 'Specialized', 4, 12, '2nd Sem', 3, 80),
(177, 'Legacy', 'GAS-ELECT2', 'Elective 2 (Selected Track/Strand Elective)', 'Specialized', 4, 12, '2nd Sem', 3, 80),
(178, 'Legacy', 'GAS-IMMERSION', 'Work Immersion / Culminating Activity', 'Specialized', 4, 12, '2nd Sem', 3, 80);

-- --------------------------------------------------------

--
-- Table structure for table `subject_assignments`
--

CREATE TABLE `subject_assignments` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '2026-2027',
  `semester` enum('1st Term','2nd Term','3rd Term','1st Sem','2nd Sem','Full Year') NOT NULL DEFAULT '1st Term'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject_assignments`
--

INSERT INTO `subject_assignments` (`id`, `faculty_id`, `subject_id`, `section_id`, `academic_year`, `semester`) VALUES
(3, 12, 49, 8, '2026-2027', '1st Term'),
(4, 12, 52, 8, '2026-2027', '2nd Term');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `lrn` varchar(12) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','adviser','student','faculty','alumni') NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `reset_requested` tinyint(1) DEFAULT 0,
  `require_reset` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `lrn`, `username`, `password`, `full_name`, `profile_image`, `email`, `role`, `section_id`, `reset_requested`, `require_reset`) VALUES
(1, NULL, 'admin_root', '$2y$10$ZDnB/.cczTQZU7fqxmNRyOWo.mdcuof.liPBgDiQO4tD62XX/8yDe', 'System Administrator', NULL, 'admin@strandsync.edu.ph', 'admin', NULL, 0, 0),
(7, NULL, 'teacher.maria', '$2y$10$ZDnB/.cczTQZU7fqxmNRyOWo.mdcuof.liPBgDiQO4tD62XX/8yDe', 'Maria Santos', NULL, 'maria.santos@str-syc.edu.ph', 'faculty', NULL, 0, 0),
(8, NULL, 'adviser.juan', '$2y$10$ZDnB/.cczTQZU7fqxmNRyOWo.mdcuof.liPBgDiQO4tD62XX/8yDe', 'Juan Dela Cruz', NULL, 'juan.delacruz@str-syc.edu.ph', 'adviser', NULL, 0, 0),
(9, '123456789011', 'student.alex', '$2y$10$ZDnB/.cczTQZU7fqxmNRyOWo.mdcuof.liPBgDiQO4tD62XX/8yDe', 'Alex Reyes', NULL, 'alex.reyes@str-syc.edu.ph', 'student', NULL, 0, 0),
(11, '09876543210', '09876543210', '$2y$10$EjlOJ07.Ax9TrF/WBqgRu.UJjQDKgA.x4VHwakB7Z9GeMA7pUg8YW', 'Sumaoang, Christian M.', NULL, NULL, 'student', NULL, 0, 1),
(12, NULL, 'adviser.aron', '$2y$10$ffi8EzDFGnJrFljrSYjgU.q1D7XwEozHpwRFx1K.2VanfUGWpIRIG', 'Ralph Aron Cuaresma', NULL, NULL, 'adviser', NULL, 0, 1),
(13, '20260001', '20260001', '$2y$10$qUHo7NXYQ7Fjf7cQlj.VpexTNgJOxMnqHTLSoSz4RdmWMTyWzTXx6', 'Anderson, Mikaela Rose', NULL, NULL, 'student', 8, 0, 1),
(14, '20260002', '20260002', '$2y$10$I3fJG5vmJMPCfz7s.OSXL.r.VnSLc57UfsSi5fk25Vbew3VXPGOsK', 'Bautista, Joshua Miguel', NULL, NULL, 'student', 8, 0, 1),
(15, '20260003', '20260003', '$2y$10$101zXVHzszhOtNe//ARIKOq1EGjNcAUAm.4UXeC6bItYFjpM4MDmy', 'Castillo, Angela Mae', NULL, NULL, 'student', 8, 0, 1),
(16, '20260004', '20260004', '$2y$10$iIIdCl1DeMy/hOuOaYH36uyP1tI1q86FPN5mIkkJWYjscpUFWdLNO', 'Dela Cruz, Nathaniel', NULL, NULL, 'student', 8, 0, 1),
(17, '20260005', '20260005', '$2y$10$sJSIpzVHqY6yA8BiWg03MeATIabIjDT2ntRepZ2EOd36ntMXre8he', 'Evangelista, Sophia Claire', NULL, NULL, 'student', 8, 0, 1),
(18, '20260006', '20260006', '$2y$10$v2iIqM//oQfkdixC9o6mSu3j/7019DBxObxvyRUcEjAoKH.he4WC.', 'Flores, Gabriel Luis', NULL, NULL, 'student', 8, 0, 1),
(19, '20260007', '20260007', '$2y$10$4rzOG71hRWPWLY9eNx1Cx.C6UvJkEeN.58xjhwGo.AF4dXZj/RBue', 'Garcia, Camille Joy', NULL, NULL, 'student', 8, 0, 1),
(20, '20260008', '20260008', '$2y$10$JZ1i8rLZlQ45u6Fowi8JduV503uiRwk39NKFy3GmYIoJaOIrgdwaG', 'Hernandez, Paolo Andre', NULL, NULL, 'student', 8, 0, 1),
(21, '20260009', '20260009', '$2y$10$mu.Aaemb4Y8HZVgMedJ9Iec/SkxuJqryh8nUMOt8meEHcpRg2.uXG', 'Ibarra, Beatrice Anne', NULL, NULL, 'student', 8, 0, 1),
(22, '20260010', '20260010', '$2y$10$fu0M2kuoPoQKh10VrIdSsOgVwzPP5Dc979bkRVlhnsl/m3YSST.kG', 'Jimenez, Mark Elijah', 'uploads/profile_pics/student_22_1792665940.jpg', NULL, 'student', 8, 0, 1),
(33, NULL, 'faculty.ella', '$2y$10$CocDIUoM7n4XLn7hF9DKe.VG8QdQQLseLzf1iA3qqPuQTxYvthPIW', 'Ella Navarro', NULL, NULL, 'faculty', NULL, 0, 1),
(34, NULL, 'faculty.ange', '$2y$10$I17dFYmuUPIVSzmlGWMjUep7.wecrm4meR.Y7jgg0kqtg/mkaEw62', 'Angelica Eugenio', NULL, NULL, 'faculty', NULL, 0, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_user` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_announcements_sender` (`sender_id`),
  ADD KEY `fk_announcements_section` (`section_id`),
  ADD KEY `fk_announcements_subject` (`subject_id`);

--
-- Indexes for table `archived_students`
--
ALTER TABLE `archived_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_archived_strand` (`strand_id`),
  ADD KEY `fk_archived_section` (`section_id`);

--
-- Indexes for table `ecr_assessment_components`
--
ALTER TABLE `ecr_assessment_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ecr_comp_assignment` (`assignment_id`);

--
-- Indexes for table `ecr_promotion_verifications_by_assignment`
--
ALTER TABLE `ecr_promotion_verifications_by_assignment`
  ADD PRIMARY KEY (`student_id`,`assignment_id`);

--
-- Indexes for table `ecr_score_drafts`
--
ALTER TABLE `ecr_score_drafts`
  ADD PRIMARY KEY (`user_id`,`assignment_id`,`term`);

--
-- Indexes for table `ecr_student_scores`
--
ALTER TABLE `ecr_student_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ecr_score_component` (`component_id`),
  ADD KEY `fk_ecr_score_student` (`student_id`);

--
-- Indexes for table `ecr_term_summaries`
--
ALTER TABLE `ecr_term_summaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ecr_summary_student` (`student_id`),
  ADD KEY `fk_ecr_summary_assignment` (`assignment_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedules_section` (`section_id`),
  ADD KEY `fk_schedules_subject` (`subject_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section_per_year` (`section_name`,`grade_level`,`academic_year`),
  ADD KEY `fk_sections_strand` (`strand_id`),
  ADD KEY `fk_sections_adviser` (`adviser_id`);

--
-- Indexes for table `strands`
--
ALTER TABLE `strands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subjects_strand` (`strand_id`);

--
-- Indexes for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assignments_faculty` (`faculty_id`),
  ADD KEY `fk_assignments_subject` (`subject_id`),
  ADD KEY `fk_assignments_section` (`section_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `fk_users_section` (`section_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_students`
--
ALTER TABLE `archived_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ecr_assessment_components`
--
ALTER TABLE `ecr_assessment_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `ecr_student_scores`
--
ALTER TABLE `ecr_student_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `ecr_term_summaries`
--
ALTER TABLE `ecr_term_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `strands`
--
ALTER TABLE `strands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcements_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `archived_students`
--
ALTER TABLE `archived_students`
  ADD CONSTRAINT `fk_archived_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_archived_strand` FOREIGN KEY (`strand_id`) REFERENCES `strands` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ecr_assessment_components`
--
ALTER TABLE `ecr_assessment_components`
  ADD CONSTRAINT `fk_ecr_comp_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `subject_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ecr_student_scores`
--
ALTER TABLE `ecr_student_scores`
  ADD CONSTRAINT `fk_ecr_score_component` FOREIGN KEY (`component_id`) REFERENCES `ecr_assessment_components` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ecr_score_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ecr_term_summaries`
--
ALTER TABLE `ecr_term_summaries`
  ADD CONSTRAINT `fk_ecr_summary_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `subject_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ecr_summary_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedules_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedules_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sections_strand` FOREIGN KEY (`strand_id`) REFERENCES `strands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_strand` FOREIGN KEY (`strand_id`) REFERENCES `strands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subject_assignments`
--
ALTER TABLE `subject_assignments`
  ADD CONSTRAINT `fk_assignments_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
