-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2026 at 04:19 AM
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
-- Database: `asu_rems`
--

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(10) UNSIGNED NOT NULL,
  `college_name` varchar(150) NOT NULL,
  `college_code` varchar(30) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`college_id`, `college_name`, `college_code`, `status`, `created_at`, `updated_at`) VALUES
(2, '[ASU-Banga] College of Agriculture, Forestry, and Environmental Sciences', 'B-CAFES', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(3, '[ASU-Banga] College of Teacher Education', 'B-CTE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(4, '[ASU-Banga] College of Business and Management', 'B-CBM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(5, '[ASU-Banga] College of Arts and Sciences', 'B-CAS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(6, '[ASU-Banga] College of Nursing', 'B-CON', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(7, '[ASU-Banga] College of Veterinary Medicine', 'B-CVM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(8, '[ASU-Kalibo] College of Industrial Technology', 'K-CIT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(9, '[ASU-Kalibo] College of Industrial Education', 'K-CIE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(10, '[ASU-Kalibo] College of Computer Studies', 'K-CCS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(11, '[ASU-Kalibo] College of Engineering and Architecture', 'K-CEA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(12, '[ASU-Ibajay] College of Hospitality and Rural Resource Management', 'I-CHRRM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(13, '[ASU-Ibajay] College of Teacher Education', 'I-CTE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(14, '[ASU-New Washington] College of Fisheries and Marine Sciences', 'NW-CFMS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(15, '[ASU-New Washington] College of Hospitality and Rural Resource Management', 'NW-CHRRM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(16, '[ASU-New Washington] College of Criminal Justice Education', 'NW-CCJE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(17, '[ASU-New Washington] College of Teacher Education', 'NW-CTE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(18, '[ASU-Makato] College of Human Development and Performance Sciences', 'M-CHDPS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `department_code` varchar(30) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `college_id`, `department_name`, `department_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Bachelor in Agricultural Technology', 'B-BAT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(2, 2, 'Bachelor of Science in Agriculture', 'B-BSA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(3, 2, 'Bachelor of Science in Food Technology', 'B-BSFT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(4, 2, 'Bachelor of Science in Forestry', 'B-BSF', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(5, 2, 'Bachelor of Science in Home Technology', 'B-BSHT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(6, 3, 'Bachelor of Elementary Education', 'B-BEED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(7, 3, 'Bachelor of Secondary Education', 'B-BSED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(8, 4, 'Bachelor of Science in Business Administration', 'B-BSBA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(9, 4, 'Bachelor of Science in Entrepreneurship', 'B-BSENT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(10, 4, 'Bachelor of Science in Office Administration', 'B-BSOA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(11, 4, 'Bachelor of Science in Public Administration', 'B-BSPA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(12, 5, 'Bachelor of Arts in Communication', 'B-BACOM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(13, 5, 'Bachelor of Arts in English Language Studies', 'B-BAELS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(14, 5, 'Bachelor of Science in Applied Mathematics', 'B-BSAM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(15, 5, 'Bachelor of Science in Biology', 'B-BSBIO', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(16, 6, 'Bachelor of Science in Nursing', 'B-BSN', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(17, 7, 'Doctor of Veterinary Medicine', 'B-DVM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(18, 8, 'Bachelor of Science in Industrial Technology', 'K-BSINDT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(19, 8, 'Bachelor of Industrial Technology', 'K-BINDT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(20, 8, 'Bachelor of Science in Hospitality Management', 'K-BSHM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(21, 9, 'Bachelor of Technical Teacher Education', 'K-BTTE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(22, 9, 'Bachelor of Technical-Vocational Teacher Education', 'K-BTVTED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(23, 10, 'Bachelor of Science in Information Technology', 'K-BSIT', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(24, 10, 'Bachelor of Science in Entertainment and Multimedia Computing', 'K-BSEMC', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(25, 11, 'Bachelor of Science in Civil Engineering', 'K-BSCE', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(26, 11, 'Bachelor of Science in Architecture', 'K-BSARCH', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(27, 12, 'Master in Tourism Management', 'I-MTM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(28, 12, 'Bachelor of Science in Hospitality Management', 'I-BSHM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(29, 12, 'Bachelor of Science in Tourism Management', 'I-BSTM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(30, 12, 'Bachelor of Science in Environmental Science', 'I-BSES', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(31, 12, 'Bachelor of Science in Computer Science', 'I-BSCS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(32, 13, 'Bachelor of Elementary Education', 'I-BEED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(33, 13, 'Bachelor of Secondary Education', 'I-BSED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(34, 14, 'Bachelor of Science in Fisheries', 'NW-BSF', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(35, 14, 'Bachelor of Science in Marine Biology', 'NW-BSMB', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(36, 15, 'Bachelor of Science in Hospitality Management', 'NW-BSHM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(37, 15, 'Bachelor of Science in Customs Administration', 'NW-BSCA', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(38, 16, 'Bachelor of Science in Criminology', 'NW-BSCRIM', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(39, 17, 'Bachelor of Secondary Education', 'NW-BSED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(40, 17, 'Bachelor of Culture and Arts Education', 'NW-BCAED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(41, 18, 'Bachelor of Physical Education', 'M-BPED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(42, 18, 'Bachelor of Science in Exercise and Sports Science', 'M-BSESS', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(43, 18, 'Bachelor of Secondary Education', 'M-BSED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48'),
(44, 18, 'Bachelor of Technology and Livelihood Education', 'M-BTLED', 'Active', '2026-11-12 09:37:48', '2026-11-12 09:37:48');

-- --------------------------------------------------------

--
-- Table structure for table `extension_approvals`
--

CREATE TABLE `extension_approvals` (
  `approval_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `approval_stage` enum('LADCO_ADCO','BOR') NOT NULL,
  `decision` enum('Approved','Noted','Deferred','Returned for Revision','Not Approved') NOT NULL,
  `reference_number` varchar(150) DEFAULT NULL,
  `decision_date` date NOT NULL,
  `document_url` varchar(1000) NOT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_approvals`
--

INSERT INTO `extension_approvals` (`approval_id`, `extension_id`, `approval_stage`, `decision`, `reference_number`, `decision_date`, `document_url`, `remarks`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 5, 'LADCO_ADCO', 'Approved', '12345678', '2026-11-10', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-10 08:26:03', '2026-11-10 08:26:03'),
(2, 5, 'BOR', 'Noted', '12345678', '2026-11-10', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '1234rfh', 13, '2026-11-10 08:27:02', '2026-11-10 08:27:02'),
(3, 6, 'LADCO_ADCO', 'Approved', '1234', '2026-11-11', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '12345', 13, '2026-11-11 03:07:10', '2026-11-11 03:07:10'),
(4, 6, 'BOR', 'Noted', '12345678', '2026-11-11', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', 13, '2026-11-11 03:07:58', '2026-11-11 03:07:58'),
(5, 7, 'LADCO_ADCO', 'Approved', '123456', '2026-11-12', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-12 05:12:37', '2026-11-12 05:12:37'),
(6, 7, 'BOR', 'Noted', '123456', '2026-11-12', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '12345', 13, '2026-11-12 05:13:06', '2026-11-12 05:13:06'),
(7, 9, 'LADCO_ADCO', 'Approved', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwertyiop', 13, '2026-11-18 02:02:50', '2026-11-18 02:02:50'),
(8, 9, 'BOR', 'Noted', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'jdesjcdnhe', 13, '2026-11-18 02:03:21', '2026-11-18 02:03:21'),
(9, 10, 'LADCO_ADCO', 'Approved', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'fstteswrewghd', 13, '2026-11-18 06:08:36', '2026-11-18 06:08:36'),
(10, 10, 'BOR', 'Noted', '0352581653', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'fdhsdtsertetrrrr', 13, '2026-11-18 06:09:07', '2026-11-18 06:09:07'),
(11, 11, 'LADCO_ADCO', 'Approved', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-20 02:23:35', '2026-11-20 02:23:35'),
(12, 11, 'BOR', 'Noted', '0352581653', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-20 02:25:52', '2026-11-20 02:25:52'),
(13, 14, 'LADCO_ADCO', 'Approved', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-21 02:43:32', '2026-11-21 02:43:32'),
(14, 14, 'BOR', 'Noted', '12334', '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-21 02:45:22', '2026-11-21 02:45:22'),
(15, 13, 'LADCO_ADCO', 'Approved', NULL, '2026-11-21', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'gfeugwgegwuwu', 13, '2026-11-21 04:14:59', '2026-11-21 04:14:59'),
(16, 13, 'BOR', 'Noted', '1234567890', '2026-11-21', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'gfeugwgegwuwu', 13, '2026-11-21 04:14:59', '2026-11-21 04:14:59'),
(17, 17, 'LADCO_ADCO', 'Approved', NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'fgsgdjt', 13, '2026-11-21 04:51:47', '2026-11-21 04:51:47'),
(18, 17, 'BOR', 'Noted', '1234567890', '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'fgsgdjt', 13, '2026-11-21 04:51:47', '2026-11-21 04:51:47'),
(19, 18, 'LADCO_ADCO', 'Approved', NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', 13, '2026-11-21 06:05:22', '2026-11-21 06:05:22'),
(20, 18, 'BOR', 'Noted', '123145435664', '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', 13, '2026-11-21 06:05:22', '2026-11-21 06:05:22');

-- --------------------------------------------------------

--
-- Table structure for table `extension_calls`
--

CREATE TABLE `extension_calls` (
  `call_id` int(10) UNSIGNED NOT NULL,
  `memo_title` varchar(255) NOT NULL,
  `memo_number` varchar(100) DEFAULT NULL,
  `memo_url` varchar(1000) NOT NULL,
  `description` text DEFAULT NULL,
  `opens_on` date NOT NULL,
  `closes_on` date NOT NULL,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `issued_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_calls`
--

INSERT INTO `extension_calls` (`call_id`, `memo_title`, `memo_number`, `memo_url`, `description`, `opens_on`, `closes_on`, `status`, `issued_by`, `created_at`, `updated_at`) VALUES
(1, 'asu rems', '001', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, '2026-11-11', '2026-12-24', 'Open', 13, '2026-11-10 07:17:34', '2026-11-10 07:17:34'),
(2, 'dijsod', '002', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, '2026-11-10', '2026-11-11', 'Open', 13, '2026-11-10 07:19:09', '2026-11-10 07:19:09'),
(3, 'tezt 1', '003', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, '2026-11-10', '2026-11-19', 'Open', 13, '2026-11-10 07:41:27', '2026-11-10 07:41:27'),
(4, 'tezt 1', '003', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, '2026-11-10', '2026-11-19', 'Open', 13, '2026-11-10 07:43:01', '2026-11-10 07:43:01'),
(5, 'tezt 1', '003', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, '2026-11-10', '2026-11-19', 'Open', 13, '2026-11-10 07:43:06', '2026-11-10 07:43:06'),
(6, 'qwerty', '098', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', '2026-11-11', '2026-11-30', 'Open', 13, '2026-11-11 01:56:25', '2026-11-11 01:56:25'),
(7, 'qwerty', '098', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', '2026-11-11', '2026-11-30', 'Open', 13, '2026-11-11 01:59:08', '2026-11-11 01:59:08'),
(8, 'TEXT 1', '001', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'hello', '2026-11-12', '2026-11-20', 'Open', 13, '2026-11-12 03:57:20', '2026-11-12 03:57:20'),
(9, 'asurems', '123456', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', '2026-11-18', '2026-11-30', 'Open', 13, '2026-11-18 01:41:19', '2026-11-18 01:41:19'),
(10, 'asurems', '123456', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', '2026-11-18', '2026-11-30', 'Open', 13, '2026-11-18 01:54:14', '2026-11-18 01:54:14'),
(11, 'test 1', '1234567890', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'aghsaws', '2026-11-20', '2026-11-30', 'Open', 13, '2026-11-20 01:44:19', '2026-11-20 01:44:19'),
(12, 'test 1', '1234567890', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'aghsaws', '2026-11-20', '2026-11-30', 'Open', 13, '2026-11-20 01:48:19', '2026-11-20 01:48:19');

-- --------------------------------------------------------

--
-- Table structure for table `extension_campus_unit_map`
--

CREATE TABLE `extension_campus_unit_map` (
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `campus` varchar(100) NOT NULL,
  `unit_type` enum('college','department') NOT NULL,
  `unit_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_campus_unit_map`
--

INSERT INTO `extension_campus_unit_map` (`assignment_id`, `campus`, `unit_type`, `unit_id`, `created_at`) VALUES
(1, 'ASU Banga Campus', 'college', 2, '2026-11-20 17:31:29'),
(2, 'ASU Banga Campus', 'college', 3, '2026-11-20 17:31:29'),
(3, 'ASU Banga Campus', 'college', 4, '2026-11-20 17:31:29'),
(4, 'ASU Banga Campus', 'college', 5, '2026-11-20 17:31:29'),
(5, 'ASU Banga Campus', 'college', 6, '2026-11-20 17:31:29'),
(6, 'ASU Banga Campus', 'college', 7, '2026-11-20 17:31:29'),
(7, 'ASU Kalibo Campus', 'college', 8, '2026-11-20 17:31:29'),
(8, 'ASU Kalibo Campus', 'college', 9, '2026-11-20 17:31:29'),
(9, 'ASU Kalibo Campus', 'college', 10, '2026-11-20 17:31:29'),
(10, 'ASU Kalibo Campus', 'college', 11, '2026-11-20 17:31:29'),
(11, 'ASU Ibijay Campus', 'college', 12, '2026-11-20 17:31:29'),
(12, 'ASU Ibijay Campus', 'college', 13, '2026-11-20 17:31:29'),
(13, 'ASU New Washington Campus', 'college', 14, '2026-11-20 17:31:29'),
(14, 'ASU New Washington Campus', 'college', 15, '2026-11-20 17:31:29'),
(15, 'ASU New Washington Campus', 'college', 16, '2026-11-20 17:31:29'),
(16, 'ASU New Washington Campus', 'college', 17, '2026-11-20 17:31:29'),
(17, 'ASU Makato Campus', 'college', 18, '2026-11-20 17:31:29'),
(32, 'ASU Kalibo Campus', 'department', 19, '2026-11-20 17:31:29'),
(33, 'ASU Kalibo Campus', 'department', 20, '2026-11-20 17:31:29'),
(34, 'ASU Kalibo Campus', 'department', 18, '2026-11-20 17:31:29'),
(35, 'ASU Kalibo Campus', 'department', 21, '2026-11-20 17:31:29'),
(36, 'ASU Kalibo Campus', 'department', 22, '2026-11-20 17:31:29'),
(37, 'ASU Kalibo Campus', 'department', 24, '2026-11-20 17:31:29'),
(38, 'ASU Kalibo Campus', 'department', 23, '2026-11-20 17:31:29'),
(39, 'ASU Kalibo Campus', 'department', 26, '2026-11-20 17:31:29'),
(40, 'ASU Kalibo Campus', 'department', 25, '2026-11-20 17:31:29'),
(41, 'ASU Banga Campus', 'department', 1, '2026-11-20 17:31:29'),
(42, 'ASU Banga Campus', 'department', 2, '2026-11-20 17:31:29'),
(43, 'ASU Banga Campus', 'department', 3, '2026-11-20 17:31:29'),
(44, 'ASU Banga Campus', 'department', 4, '2026-11-20 17:31:29'),
(45, 'ASU Banga Campus', 'department', 5, '2026-11-20 17:31:29'),
(46, 'ASU Banga Campus', 'department', 6, '2026-11-20 17:31:29'),
(47, 'ASU Banga Campus', 'department', 7, '2026-11-20 17:31:29'),
(48, 'ASU Banga Campus', 'department', 8, '2026-11-20 17:31:29'),
(49, 'ASU Banga Campus', 'department', 9, '2026-11-20 17:31:29'),
(50, 'ASU Banga Campus', 'department', 10, '2026-11-20 17:31:29'),
(51, 'ASU Banga Campus', 'department', 11, '2026-11-20 17:31:29'),
(52, 'ASU Banga Campus', 'department', 12, '2026-11-20 17:31:29'),
(53, 'ASU Banga Campus', 'department', 13, '2026-11-20 17:31:29'),
(54, 'ASU Banga Campus', 'department', 14, '2026-11-20 17:31:29'),
(55, 'ASU Banga Campus', 'department', 15, '2026-11-20 17:31:29'),
(56, 'ASU Banga Campus', 'department', 16, '2026-11-20 17:31:29'),
(57, 'ASU Banga Campus', 'department', 17, '2026-11-20 17:31:29'),
(58, 'ASU Ibijay Campus', 'department', 31, '2026-11-20 17:31:29'),
(59, 'ASU Ibijay Campus', 'department', 30, '2026-11-20 17:31:29'),
(60, 'ASU Ibijay Campus', 'department', 28, '2026-11-20 17:31:29'),
(61, 'ASU Ibijay Campus', 'department', 29, '2026-11-20 17:31:29'),
(62, 'ASU Ibijay Campus', 'department', 27, '2026-11-20 17:31:29'),
(63, 'ASU Ibijay Campus', 'department', 32, '2026-11-20 17:31:29'),
(64, 'ASU Ibijay Campus', 'department', 33, '2026-11-20 17:31:29'),
(65, 'ASU Makato Campus', 'department', 41, '2026-11-20 17:31:29'),
(66, 'ASU Makato Campus', 'department', 42, '2026-11-20 17:31:29'),
(67, 'ASU Makato Campus', 'department', 43, '2026-11-20 17:31:29'),
(68, 'ASU Makato Campus', 'department', 44, '2026-11-20 17:31:29'),
(69, 'ASU New Washington Campus', 'department', 34, '2026-11-20 17:31:29'),
(70, 'ASU New Washington Campus', 'department', 35, '2026-11-20 17:31:29'),
(71, 'ASU New Washington Campus', 'department', 37, '2026-11-20 17:31:29'),
(72, 'ASU New Washington Campus', 'department', 36, '2026-11-20 17:31:29'),
(73, 'ASU New Washington Campus', 'department', 38, '2026-11-20 17:31:29'),
(74, 'ASU New Washington Campus', 'department', 40, '2026-11-20 17:31:29'),
(75, 'ASU New Washington Campus', 'department', 39, '2026-11-20 17:31:29');

-- --------------------------------------------------------

--
-- Table structure for table `extension_certifications`
--

CREATE TABLE `extension_certifications` (
  `certification_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('Notice of Approval','BOR Certification') NOT NULL,
  `reference_number` varchar(150) DEFAULT NULL,
  `certification_date` date NOT NULL,
  `document_url` varchar(1000) NOT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_certifications`
--

INSERT INTO `extension_certifications` (`certification_id`, `extension_id`, `document_type`, `reference_number`, `certification_date`, `document_url`, `remarks`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 5, 'BOR Certification', '12345678', '2026-11-10', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-10 08:36:30', '2026-11-10 08:36:30'),
(2, 6, 'BOR Certification', '1234567890', '2026-11-11', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '123456', 13, '2026-11-11 03:17:05', '2026-11-11 03:17:05'),
(3, 7, 'BOR Certification', '12345678', '2026-11-12', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-12 05:57:58', '2026-11-12 05:57:58'),
(4, 9, 'BOR Certification', '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'good job', 13, '2026-11-18 02:05:31', '2026-11-18 02:05:31'),
(5, 10, 'BOR Certification', '551550522552', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'jjfvujfjfufnfgfkfruifnhy', 13, '2026-11-18 06:10:46', '2026-11-18 06:10:46'),
(6, 11, 'BOR Certification', '551550522552', '2026-11-20', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-20 02:29:12', '2026-11-20 02:29:12'),
(7, 14, 'BOR Certification', '551550522552', '2026-11-20', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'ee', 13, '2026-11-21 02:45:39', '2026-11-21 02:45:39');

-- --------------------------------------------------------

--
-- Table structure for table `extension_coordinator_endorsements`
--

CREATE TABLE `extension_coordinator_endorsements` (
  `endorsement_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(11) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `stage` varchar(20) NOT NULL,
  `document_url` varchar(1000) NOT NULL,
  `endorsement_date` datetime NOT NULL,
  `remarks` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_coordinator_endorsements`
--

INSERT INTO `extension_coordinator_endorsements` (`endorsement_id`, `extension_id`, `coordinator_id`, `stage`, `document_url`, `endorsement_date`, `remarks`, `submitted_at`) VALUES
(1, 16, 8, 'pre_inhouse', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 11:16:00', 'fdf', '2026-11-21 11:17:02'),
(2, 16, 8, 'final', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 10:39:00', 'te', '2026-11-21 11:39:23'),
(3, 13, 8, 'pre_inhouse', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 10:43:00', NULL, '2026-11-21 11:44:17'),
(4, 13, 8, 'final', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 10:45:00', 'u et 9iyre', '2026-11-21 11:46:21'),
(5, 17, 8, 'pre_inhouse', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 12:49:00', 'hkhgfrdhihrgeihihrgeithigre', '2026-11-21 12:50:15'),
(6, 17, 8, 'final', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 12:50:00', 'fdh', '2026-11-21 12:51:05'),
(7, 18, 8, 'pre_inhouse', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 01:58:00', 'wert', '2026-11-21 13:58:14'),
(8, 18, 8, 'final', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-21 13:05:00', 'wert', '2026-11-21 14:03:50');

-- --------------------------------------------------------

--
-- Table structure for table `extension_documents`
--

CREATE TABLE `extension_documents` (
  `document_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_extension` varchar(20) NOT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_documents`
--

INSERT INTO `extension_documents` (`document_id`, `extension_id`, `uploaded_by`, `document_type`, `original_name`, `stored_name`, `file_path`, `file_extension`, `file_size`, `uploaded_at`) VALUES
(3, 3, 8, 'Program Proposal', 'Activity 1.1 Malumay_Joseph Andrei.docx', 'extension_3_20261029105804_6642291b38150e97.docx', 'uploads/extension/programs/extension_3_20261029105804_6642291b38150e97.docx', 'docx', 103552, '2026-10-29 02:58:04'),
(4, 4, 8, 'Program Proposal', '32 - ECS.docx', 'extension_4_20261110152118_c7df3ac8e6bea0e1.docx', 'uploads/extension/programs/extension_4_20261110152118_c7df3ac8e6bea0e1.docx', 'docx', 84171, '2026-11-10 07:21:18'),
(5, 5, 8, 'Program Proposal', '32 - ECS.docx', 'extension_5_20261110154254_5b6c951c0d5c5bd4.docx', 'uploads/extension/programs/extension_5_20261110154254_5b6c951c0d5c5bd4.docx', 'docx', 84171, '2026-11-10 07:42:54'),
(6, 6, 8, 'Program Proposal', '32 - ECS.docx', 'extension_6_20261111095822_a24b5f3b3d7231c9.docx', 'uploads/extension/programs/extension_6_20261111095822_a24b5f3b3d7231c9.docx', 'docx', 84171, '2026-11-11 01:58:22'),
(7, 7, 8, 'Program Proposal', 'Program Proposal Link', 'extension_7_external_link', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-12 04:15:06'),
(8, 8, 8, 'Program Proposal', 'Program Proposal Link', 'extension_8_external_link', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-12 04:58:35'),
(9, 9, 8, 'Program Proposal', 'Program Proposal Link', 'extension_9_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-18 01:54:03'),
(10, 10, 8, 'Program Proposal', 'Program Proposal Link', 'extension_10_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-18 05:29:59'),
(11, 11, 8, 'Program Proposal', 'Program Proposal Link', 'extension_11_external_link', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 01:47:53'),
(12, 12, 8, 'Program Proposal', 'Program Proposal Link', 'extension_12_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 16:32:22'),
(13, 13, 8, 'Program Proposal', 'Program Proposal Link', 'extension_13_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 02:15:15'),
(14, 14, 8, 'Program Proposal', 'Program Proposal Link', 'extension_14_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 02:40:27'),
(15, 15, 8, 'Program Proposal', 'Program Proposal Link', 'extension_15_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 02:54:27'),
(16, 16, 8, 'Program Proposal', 'Program Proposal Link', 'extension_16_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 03:10:03'),
(17, 17, 8, 'Program Proposal', 'Program Proposal Link', 'extension_17_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 04:42:08'),
(18, 18, 8, 'Program Proposal', 'Program Proposal Link', 'extension_18_external_link', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 05:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `extension_endorsements`
--

CREATE TABLE `extension_endorsements` (
  `endorsement_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `endorsement_reference` varchar(150) DEFAULT NULL,
  `endorsement_date` date NOT NULL,
  `endorsement_document_url` varchar(1000) NOT NULL,
  `endorsement_remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_endorsements`
--

INSERT INTO `extension_endorsements` (`endorsement_id`, `extension_id`, `endorsement_reference`, `endorsement_date`, `endorsement_document_url`, `endorsement_remarks`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 5, '1234567', '2026-11-10', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qweihv', 13, '2026-11-10 08:19:06', '2026-11-10 08:19:06'),
(2, 6, '1234567', '2026-11-11', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', 13, '2026-11-11 03:01:01', '2026-11-11 03:01:01'),
(3, 7, '123456', '2026-11-12', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-12 05:04:26', '2026-11-12 05:04:26'),
(4, 9, '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'djkchneuwh', 13, '2026-11-18 02:00:52', '2026-11-18 02:00:52'),
(5, 10, '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'hgddddddddytry', 13, '2026-11-18 06:08:18', '2026-11-18 06:08:18'),
(6, 11, '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-20 02:22:22', '2026-11-20 02:22:22'),
(7, 14, '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'rr', 13, '2026-11-21 02:43:05', '2026-11-21 02:43:05'),
(8, 16, '1234567890', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'htrtr', 13, '2026-11-21 03:41:51', '2026-11-21 03:41:51'),
(9, 13, NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-21 03:57:42', '2026-11-21 03:57:42'),
(10, 17, NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-21 04:51:27', '2026-11-21 04:51:27'),
(11, 18, NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'wert', 13, '2026-11-21 06:04:32', '2026-11-21 06:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `extension_monitoring_requests`
--

CREATE TABLE `extension_monitoring_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `request_code` varchar(30) DEFAULT NULL,
  `requester_id` int(11) NOT NULL,
  `project_code` varchar(80) DEFAULT NULL,
  `project_title` varchar(220) DEFAULT NULL,
  `college_unit` varchar(180) DEFAULT NULL,
  `target_community` varchar(220) DEFAULT NULL,
  `monitoring_purpose` text DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_location` varchar(255) DEFAULT NULL,
  `request_letter_url` text DEFAULT NULL,
  `office_remarks` text DEFAULT NULL,
  `assigned_unit` varchar(180) DEFAULT NULL,
  `assigned_personnel` varchar(180) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `scheduled_location` varchar(255) DEFAULT NULL,
  `me_form_url` text DEFAULT NULL,
  `monitoring_conducted_at` datetime DEFAULT NULL,
  `monitoring_conducted_by` int(11) DEFAULT NULL,
  `monitoring_report_url` text DEFAULT NULL,
  `report_submitted_at` datetime DEFAULT NULL,
  `report_received_at` datetime DEFAULT NULL,
  `report_received_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `status` enum('Draft','Submitted','Needs Information','Endorsed','Scheduled','Monitoring Conducted','Report Submitted','Report Received','Completed') NOT NULL DEFAULT 'Draft',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `program_title` varchar(220) DEFAULT NULL,
  `project_duration` varchar(220) DEFAULT NULL,
  `monitoring_entries_json` longtext DEFAULT NULL,
  `monitored_by` varchar(220) DEFAULT NULL,
  `reviewed_validated_by` varchar(220) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_monitoring_requests`
--

INSERT INTO `extension_monitoring_requests` (`request_id`, `request_code`, `requester_id`, `project_code`, `project_title`, `college_unit`, `target_community`, `monitoring_purpose`, `preferred_date`, `preferred_location`, `request_letter_url`, `office_remarks`, `assigned_unit`, `assigned_personnel`, `scheduled_date`, `scheduled_time`, `scheduled_location`, `me_form_url`, `monitoring_conducted_at`, `monitoring_conducted_by`, `monitoring_report_url`, `report_submitted_at`, `report_received_at`, `report_received_by`, `completed_at`, `completed_by`, `status`, `submitted_at`, `created_at`, `updated_at`, `program_title`, `project_duration`, `monitoring_entries_json`, `monitored_by`, `reviewed_validated_by`) VALUES
(1, 'MR-2026-00001', 8, 'dfghjkl;', 'test 1', 'ASU', 'ertyuioo', 'trytuiil', '2026-11-19', 'Kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'uyiop', 'dghjkl;', '\'dfghjkl;;', '2026-11-19', '14:28:00', 'Kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-11 14:31:21', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-11 14:31:45', '2026-11-11 14:32:00', 13, '2026-11-11 14:33:06', 13, 'Completed', '2026-11-11 14:08:16', '2026-11-11 14:08:16', '2026-11-11 14:33:06', NULL, NULL, NULL, NULL, NULL),
(2, 'MR-2026-00002', 8, 'ertyuiol;', 'test 2', 'asu', 'fghjkl', 'rtyuikl', '2026-11-12', 'kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'dfghjkl;', 'fhgjhkl', 'fghjk', '2026-11-12', '14:36:00', 'kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-11 14:36:59', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-11 14:37:22', '2026-11-11 14:37:40', 13, '2026-11-11 14:37:46', 13, 'Completed', '2026-11-11 14:35:31', '2026-11-11 14:35:31', '2026-11-11 14:37:46', NULL, NULL, NULL, NULL, NULL),
(3, 'MR-2026-00003', 8, NULL, 'test 3', 'asu', 'students', 'etryuuiokl', '2026-11-13', 'kalibo', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'wertyuiop', 'srdtfyuukjl;', '2026-11-13', '11:08:00', 'kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-12 15:05:31', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-12 15:06:31', '2026-11-12 15:06:51', 13, '2026-11-12 15:06:55', 13, 'Completed', '2026-11-12 11:02:39', '2026-11-12 11:02:39', '2026-11-12 15:06:55', NULL, NULL, NULL, NULL, NULL),
(4, 'MR-2026-00004', 8, '1dqasfja', 'QWERTYUIOP', 'QWERT', 'NEW BUSWANG', 'QWERTYU', '2026-11-06', 'ourok 6 sdfghjksdfghujikcvbnm', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwertyu', 'qwert', 'werty', '2026-11-12', '14:43:00', 'purok 6 sdfghjksdfghujikcvbnm', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-12 15:03:39', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-12 15:04:36', '2026-11-12 15:05:56', 13, '2026-11-12 15:05:59', 13, 'Completed', '2026-11-12 14:33:35', '2026-11-12 14:33:35', '2026-11-12 15:05:59', NULL, NULL, NULL, NULL, NULL),
(5, 'MR-2026-00005', 8, NULL, 'asurems', 'kalibo campus', 'kalibo', 'asdfgl', '2026-11-18', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'jssiwxjdedjxie', 'sdfghjk', 'asdfg', '2026-11-18', '10:20:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-18 10:25:48', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-18 10:27:04', '2026-11-18 10:28:28', 13, '2026-11-18 10:28:36', 13, 'Completed', '2026-11-18 10:19:10', '2026-11-18 10:19:10', '2026-11-18 10:28:36', NULL, NULL, NULL, NULL, NULL),
(6, 'MR-2026-00006', 8, NULL, 'sw', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'qwertyi', 'qwertyu', '2026-11-20', '11:11:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 11:14:21', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 11:15:34', '2026-11-20 11:16:04', 13, '2026-11-20 11:17:50', 13, 'Completed', '2026-11-20 11:06:06', '2026-11-20 11:06:06', '2026-11-20 11:17:50', 'ajajja', 'sij', '[{\"specific_learning_objectives\":\"sss\",\"planned_activities\":\"ssssss\",\"activities_conducted\":\"qq\",\"degree_of_accomplishment\":\"qq\",\"date_conducted\":\"2026-11-20\",\"project_activity_location\":\"pob\",\"responsible_person_unit_agency\":\"hhsh\",\"problems_encountered\":\"yuhun\",\"budget_allocation\":\"dfgh\",\"budget_utilization\":\"fg\",\"recommendation\":\"dfgh\"}]', 'dfghj', 'trew'),
(7, 'MR-2026-00007', 8, NULL, 'monitor test', NULL, NULL, NULL, NULL, NULL, NULL, 'good', 'sdfghjk', 'Jude', '2026-11-20', '23:17:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:23:39', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:24:45', '2026-11-20 23:28:43', 13, '2026-11-20 23:28:47', 13, 'Completed', '2026-11-20 22:57:39', '2026-11-20 22:57:39', '2026-11-20 23:28:47', 'monitor test', 'monitor1', '[{\"specific_learning_objectives\":\"fhd\",\"planned_activities\":\"edfgsd\",\"activities_conducted\":\"gdesdeg\",\"degree_of_accomplishment\":\"gdsgs\",\"date_conducted\":\"2026-11-20\",\"project_activity_location\":\"efse\",\"responsible_person_unit_agency\":\"ferf\",\"problems_encountered\":\"erfsef\",\"budget_allocation\":\"dgsgsdg\",\"budget_utilization\":\"dgsdsg\",\"recommendation\":\"gfsdegfds\"}]', 'gdsgds', 'gsdgds'),
(8, 'MR-2026-00008', 8, NULL, 'ASU12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CVCVNC', 'CVCXVCXV', '2026-11-20', '23:38:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:38:42', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-22 14:23:41', '2026-11-22 14:29:55', 13, '2026-11-22 14:30:01', 13, 'Completed', '2026-11-20 23:15:46', '2026-11-20 23:15:46', '2026-11-22 14:30:01', 'ASU12', 'asu', '[{\"specific_learning_objectives\":\"fdgdf\",\"planned_activities\":\"dfgfdg\",\"activities_conducted\":\"fdgfdg\",\"degree_of_accomplishment\":\"fdgfdgfd\",\"date_conducted\":\"2026-11-20\",\"project_activity_location\":\"fhgddfgh\",\"responsible_person_unit_agency\":\"fdgdfg\",\"problems_encountered\":\"gfdgfdg\",\"budget_allocation\":\"fgfdg\",\"budget_utilization\":\"gfdgfd\",\"recommendation\":\"fgfdg\"}]', 'Ronelyn Felipe', 'Richelle Bautista'),
(9, 'MR-2026-00009', 8, NULL, 'fdgdfg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'VBV', 'VBVB', '2026-11-20', '23:45:00', 'VVBV', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:45:59', 13, NULL, NULL, NULL, NULL, NULL, NULL, 'Monitoring Conducted', '2026-11-20 23:25:54', '2026-11-20 23:25:54', '2026-11-20 23:45:59', 'fdgdfg', 'fdgfd', '[{\"specific_learning_objectives\":\"fdgfdg\",\"planned_activities\":\"gfdgfd\",\"activities_conducted\":\"gfdgfd\",\"degree_of_accomplishment\":\"gfdgfd\",\"date_conducted\":\"2026-11-14\",\"project_activity_location\":\"pob\",\"responsible_person_unit_agency\":\"dfgfdg\",\"problems_encountered\":\"fgdfd\",\"budget_allocation\":\"gfdgfd\",\"budget_utilization\":\"gfdgfd\",\"recommendation\":\"gdfg\"}]', 'fdgfdd', 'fdgdfg'),
(10, 'MR-2026-00010', 8, NULL, 'RONETEST', NULL, NULL, NULL, NULL, NULL, NULL, 'FHDSHFKDHF', 'XFCXFV', 'CVCV', '2026-11-20', '23:47:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:47:53', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-20 23:48:20', '2026-11-20 23:48:29', 13, '2026-11-20 23:48:33', 13, 'Completed', '2026-11-20 23:28:29', '2026-11-20 23:28:29', '2026-11-20 23:48:33', 'RONETEST', 'RONETEST', '[{\"specific_learning_objectives\":\"FOSDJFSD\",\"planned_activities\":\"FOSDJFSD\",\"activities_conducted\":\"FOSDJFSD\",\"degree_of_accomplishment\":\"FOSDJFSD\",\"date_conducted\":\"2026-11-20\",\"project_activity_location\":\"FOSDJFSD\",\"responsible_person_unit_agency\":\"FOSDJFSD\",\"problems_encountered\":\"FOSDJFSD\",\"budget_allocation\":\"FOSDJFSD\",\"budget_utilization\":\"FOSDJFSD\",\"recommendation\":\"FOSDJFSD\"}]', 'Ronelyn Felipe', 'JOSEPH'),
(11, 'MR-2026-00011', 8, NULL, 'asurems', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'XFCXFV', 'dd', '2026-11-20', '14:18:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-22 14:21:30', 13, NULL, NULL, NULL, NULL, NULL, NULL, 'Monitoring Conducted', '2026-11-22 14:12:38', '2026-11-22 14:12:38', '2026-11-22 14:21:30', 'asurems', 'sij', '[{\"specific_learning_objectives\":\"jjj\",\"planned_activities\":\"ddd\",\"activities_conducted\":\"ddd\",\"degree_of_accomplishment\":\"dd\",\"date_conducted\":\"2026-11-26\",\"project_activity_location\":\"ddd\",\"responsible_person_unit_agency\":\"dd\",\"problems_encountered\":\"ddddddd\",\"budget_allocation\":\"dd\",\"budget_utilization\":\"dd\",\"recommendation\":\"dd\"}]', 'dd', 'dd'),
(12, 'MR-2026-00012', 8, NULL, 'gg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'XFCXFV', 'ss', '2026-11-20', '14:22:00', 'poblacion', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-22 14:25:41', 13, 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', '2026-11-22 14:26:21', '2026-11-22 14:27:39', 13, NULL, NULL, 'Report Received', '2026-11-22 14:14:57', '2026-11-22 14:14:57', '2026-11-22 14:27:39', 'gg', 'gggg', '[{\"specific_learning_objectives\":\"gg\",\"planned_activities\":\"gg\",\"activities_conducted\":\"gg\",\"degree_of_accomplishment\":\"ff\",\"date_conducted\":\"2026-11-22\",\"project_activity_location\":\"hhh\",\"responsible_person_unit_agency\":\"sssss\",\"problems_encountered\":\"ss\",\"budget_allocation\":\"12\",\"budget_utilization\":\"12\",\"recommendation\":\"ss\"}]', 'ss', 'ss');

-- --------------------------------------------------------

--
-- Table structure for table `extension_notices`
--

CREATE TABLE `extension_notices` (
  `notice_id` int(11) NOT NULL,
  `extension_id` int(11) NOT NULL,
  `notice_reference` varchar(150) DEFAULT NULL,
  `notice_date` date NOT NULL,
  `notice_url` varchar(1000) NOT NULL,
  `remarks` text DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_notices`
--

INSERT INTO `extension_notices` (`notice_id`, `extension_id`, `notice_reference`, `notice_date`, `notice_url`, `remarks`, `issued_by`, `issued_at`) VALUES
(1, 6, '123456', '2026-11-11', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-11 03:56:55'),
(2, 7, '123456', '2026-11-12', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-12 05:58:30'),
(3, 9, 'n/a', '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwerty', 13, '2026-11-18 02:08:22'),
(4, 10, NULL, '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'hgfdfdr', 13, '2026-11-18 06:11:14'),
(5, 11, NULL, '2026-11-18', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-20 02:31:45'),
(6, 14, NULL, '2026-11-21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 13, '2026-11-21 02:45:55'),
(7, 13, NULL, '2026-11-21', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'ryweytryytriew', 13, '2026-11-21 04:21:17'),
(8, 17, NULL, '2026-11-21', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'dtukgkh', 13, '2026-11-21 04:52:02'),
(9, 18, NULL, '2026-11-21', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'wert', 13, '2026-11-21 06:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `extension_programs`
--

CREATE TABLE `extension_programs` (
  `extension_id` int(10) UNSIGNED NOT NULL,
  `call_id` int(10) UNSIGNED DEFAULT NULL,
  `coordinator_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `program_title` varchar(255) NOT NULL,
  `program_category` varchar(150) NOT NULL,
  `program_type` varchar(150) NOT NULL,
  `target_community` varchar(255) NOT NULL,
  `municipality` varchar(150) DEFAULT NULL,
  `province` varchar(150) DEFAULT NULL,
  `objectives` text NOT NULL,
  `program_description` text NOT NULL,
  `expected_output` text NOT NULL,
  `funding_source` varchar(150) DEFAULT NULL,
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `target_participants` int(10) UNSIGNED DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','For Revision','Approved','Ongoing','Completed','Rejected','Archived','UERC Technical Review','For UERC Revision','University In-House Review','For In-House Revision','For Endorsement','For LADCO/ADCO Approval','For BOR Notation','For Certification','For Pre-In-House Endorsement') NOT NULL DEFAULT 'Draft',
  `progress_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_programs`
--

INSERT INTO `extension_programs` (`extension_id`, `call_id`, `coordinator_id`, `college_id`, `department_id`, `campus`, `program_title`, `program_category`, `program_type`, `target_community`, `municipality`, `province`, `objectives`, `program_description`, `expected_output`, `funding_source`, `estimated_budget`, `target_participants`, `start_date`, `end_date`, `status`, `progress_percentage`, `submitted_at`, `approved_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(3, NULL, 8, NULL, NULL, NULL, 'kjshna', 'saxqa', 'Community Outreach', 'xsdesfce', 'scxwdcsc', 'Aklan', 'sefwe', 'afcwseg', 'ascfwe', '1234', 1234.00, 1, '2026-10-29', '2026-11-07', 'Draft', 0, NULL, NULL, NULL, '2026-10-29 02:58:04', '2026-10-29 02:58:04'),
(4, 2, 8, NULL, NULL, NULL, 'asurems', 'acads', 'Training / Seminar', 'pob', 'kalibo', 'Aklan', 'hdihisauiy', 'hdihisauiy', 'hdihisauiy', 'CHED', 1000.00, 2, '2026-11-18', '2026-11-30', 'For UERC Revision', 0, '2026-11-10 15:28:40', NULL, NULL, '2026-11-10 07:21:18', '2026-11-10 07:31:42'),
(5, 3, 8, NULL, NULL, NULL, 'asu', 'acds', 'Training / Seminar', 'camanci', 'numancia', 'Aklan', 'wefhuiiwefyiyfwyei', 'wefhuiiwefyiyfwyei', 'wefhuiiwefyiyfwyei', 'CHED', 1000.00, 20, '2026-11-13', '2026-11-17', 'Approved', 0, '2026-11-10 15:43:22', NULL, NULL, '2026-11-10 07:42:54', '2026-11-10 08:36:30'),
(6, 6, 8, NULL, NULL, NULL, 'ASU REMS', 'QWERTY', 'Training / Seminar', 'POB', 'KALIBO', 'Aklan', 'QWERTY', 'QWERTY', 'QWERTY', 'CHED', 123455.83, 11, '2026-11-11', '2026-11-23', 'Approved', 0, '2026-11-11 10:06:31', NULL, NULL, '2026-11-11 01:58:22', '2026-11-11 03:17:05'),
(7, 8, 8, NULL, NULL, NULL, 'ASUREMS', 'ACAds', 'Training / Seminar', 'old buswang', 'kalibo', 'Aklan', 'sdfghjkljhyt', 'yguhjikljhtyredfg', 'ertyuijkmkliuytr', 'asu', 10000.00, 20, '2026-11-12', '2026-11-19', 'Approved', 0, '2026-11-12 12:45:05', NULL, NULL, '2026-11-12 04:15:06', '2026-11-12 05:57:58'),
(8, 8, 8, NULL, NULL, NULL, 'ertyuio', 'qwertyu', 'Training / Seminar', 'werty', 'kalibs', 'Aklan', 'qwertyui', 'qwertyui', 'qwertyui', 'ched', 12345.00, 12, '2026-11-12', '2026-11-13', 'For UERC Revision', 0, '2026-11-12 12:58:42', NULL, NULL, '2026-11-12 04:58:35', '2026-11-12 04:59:47'),
(9, 1, 8, NULL, NULL, NULL, 'asurems', 'qwerty', 'Training / Seminar', 'pob', 'kalibo', 'Aklan', 'qwerty', 'qwerty', 'qwerty', 'ched', 1999.00, 20, '2026-11-18', '2026-12-07', 'Approved', 0, '2026-11-18 09:54:32', NULL, NULL, '2026-11-18 01:54:03', '2026-11-18 02:05:31'),
(10, 5, 8, NULL, NULL, NULL, 'TEST 1', 'ACADS', 'Training / Seminar', 'POB', 'KALIBO', 'Aklan', 'QWERTYUU', 'QWERTY', 'QWERTY', 'asu', 1000.00, 20, '2026-11-18', '2026-11-28', 'Approved', 0, '2026-11-18 13:30:08', NULL, NULL, '2026-11-18 05:29:59', '2026-11-18 06:10:46'),
(11, 11, 8, NULL, NULL, NULL, 'asuremsdwsd', 'xsw', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'pob', 'kalibo', 'Aklan', 'qwertyu', 'qwertyu', 'qwertyu', 'ched', 100.00, 10, '2026-11-20', '2026-11-30', 'Approved', 0, '2026-11-20 09:48:06', NULL, NULL, '2026-11-20 01:47:53', '2026-11-20 02:29:12'),
(12, 12, 8, NULL, NULL, NULL, 'TEST 1', 'ONGOING', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'CALIZO', 'BALETE', 'AKLAN', 'To enhance the digital literacy and basic computer skills of community members by providing accessible training that promotes technological awareness, practical computer knowledge, and effective use of digital tools in everyday activities.', 'To enhance the digital literacy and basic computer skills of community members by providing accessible training that promotes technological awareness, practical computer knowledge, and effective use of digital tools in everyday activities.', 'To enhance the digital literacy and basic computer skills of community members by providing accessible training that promotes technological awareness, practical computer knowledge, and effective use of digital tools in everyday activities.', 'CHED', 20000.00, 10, '2026-11-21', '2026-11-30', 'For Pre-In-House Endorsement', 0, '2026-11-21 10:47:17', NULL, NULL, '2026-11-20 16:32:22', '2026-11-21 03:31:07'),
(13, 12, 8, 10, NULL, 'ASU Kalibo Campus', 'hulae', 'ongoing', 'HUMAN WELLNESS, EDUCATION AND DEVELOPMENT', 'pob', 'kalibo', 'Aklan', 'ddd', 'dd', 'dddd', 'ched', 123.00, 18, '2026-11-21', '2026-11-29', 'Approved', 0, '2026-11-21 10:15:40', '2026-11-21 12:21:17', NULL, '2026-11-21 02:15:15', '2026-11-21 04:21:17'),
(14, 1, 8, 9, 22, 'ASU Kalibo Campus', 'aaaa', 'aaa', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'pob', 'kalibo', 'Aklan', 'ddd', 'dddd', 'ddd', 'ddd', 123.00, 12, '2026-11-21', '2026-12-02', 'Approved', 0, '2026-11-21 10:40:32', '2026-11-21 10:45:55', NULL, '2026-11-21 02:40:27', '2026-11-21 02:45:55'),
(15, 12, 8, 18, NULL, 'ASU Makato Campus', 'TEST 111', 'HELOOOO', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'POB', 'KALIBO', 'Aklan', 'HDFRHDFRHFD', 'HFDHFDHFD', 'FDHDFHFDH', 'CHED', 10900000.00, 21, '2026-11-21', '2026-11-30', 'For Pre-In-House Endorsement', 0, '2026-11-21 10:54:34', NULL, NULL, '2026-11-21 02:54:27', '2026-11-21 02:59:15'),
(16, 11, 8, 8, 20, 'ASU Kalibo Campus', 'dgrdfgdf', 'gfdgfdg', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'fg', 'gf', 'Aklan', 'gfdd', 'dfg', 'fdgf', 'ched', 1000.00, 10, '2026-11-21', '2026-11-27', 'For LADCO/ADCO Approval', 0, '2026-11-21 11:10:07', NULL, NULL, '2026-11-21 03:10:03', '2026-11-21 03:41:51'),
(17, 12, 8, 8, 20, 'ASU Kalibo Campus', 'FINAL TESTING', 'ONGOING', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'POB', 'KALIBO', 'Aklan', 'GFFGEWUGUYWEF', 'IHFIEWYIYIYWEIY', 'GFRWEYYW4UW', 'ASU', 1000.00, 10, '2026-11-21', '2026-12-11', 'Approved', 0, '2026-11-21 12:42:12', '2026-11-21 12:52:02', NULL, '2026-11-21 04:42:08', '2026-11-21 04:52:02'),
(18, 12, 8, 10, NULL, 'ASU Kalibo Campus', 'test 1', 'ongoing', 'SUSTAINABLE COMMUNITIES AND ENVIRONMENTAL RESILIENCE', 'pob', 'kalibo', 'Aklan', 'wert', 'wert', 'wert', 'ched', 1000.00, 10, '2026-11-21', '2026-12-12', 'Approved', 0, '2026-11-21 13:42:25', '2026-11-21 14:05:43', NULL, '2026-11-21 05:41:50', '2026-11-21 06:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `extension_program_proponents`
--

CREATE TABLE `extension_program_proponents` (
  `proponent_id` int(11) NOT NULL,
  `extension_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_program_proponents`
--

INSERT INTO `extension_program_proponents` (`proponent_id`, `extension_id`, `full_name`, `sort_order`, `created_at`) VALUES
(1, 12, 'RICHELLE GAYNE BAUTISTA', 1, '2026-11-20 16:32:22'),
(2, 13, 'richelle gayne', 1, '2026-11-21 02:15:15'),
(3, 14, 'hulae', 1, '2026-11-21 02:40:27'),
(4, 15, 'LARRA JANE CASIDSID', 1, '2026-11-21 02:54:27'),
(5, 15, 'RONELYN MAE FELIPE', 2, '2026-11-21 02:54:27'),
(6, 17, 'RICHELLE BAUTISTA', 1, '2026-11-21 04:42:08'),
(7, 18, 'bogart', 1, '2026-11-21 05:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `extension_reviews`
--

CREATE TABLE `extension_reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `review_stage` enum('UERC','In-House') NOT NULL,
  `review_comments` text NOT NULL,
  `reviewed_at` datetime NOT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_reviews`
--

INSERT INTO `extension_reviews` (`review_id`, `extension_id`, `review_stage`, `review_comments`, `reviewed_at`, `recorded_by`, `created_at`) VALUES
(1, 4, 'UERC', 'fiioowueouueowu', '2026-11-10 14:34:00', 13, '2026-11-10 07:31:42'),
(2, 5, 'UERC', 'hello', '2026-11-10 14:44:00', 13, '2026-11-10 07:45:07'),
(3, 5, 'In-House', '123456yhhgfdreswedrtfgy', '2026-11-10 03:53:00', 13, '2026-11-10 07:54:16'),
(4, 6, 'UERC', 'qwerty', '2026-11-11 10:25:00', 13, '2026-11-11 02:34:02'),
(5, 6, 'In-House', 'qwerty', '2026-11-11 10:50:00', 13, '2026-11-11 02:55:19'),
(6, 7, 'UERC', 'rsdtyuiokljhtyresdfguj', '2026-11-12 12:51:00', 13, '2026-11-12 04:52:10'),
(7, 7, 'In-House', 'erdfuio;ljhtredfghkl;', '2026-11-12 12:54:00', 13, '2026-11-12 04:54:47'),
(8, 8, 'UERC', 'mali', '2026-11-12 12:59:00', 13, '2026-11-12 04:59:47'),
(9, 9, 'UERC', 'sfdhgvh', '2026-11-18 09:55:00', 13, '2026-11-18 01:57:29'),
(10, 9, 'In-House', 'qwerty', '2026-11-18 09:56:00', 13, '2026-11-18 01:59:47'),
(12, 10, 'UERC', 'sdgsgsgd', '2026-11-18 13:45:00', 13, '2026-11-18 05:54:13'),
(13, 10, 'In-House', 'gfdghgdhndudn', '2026-11-18 14:07:00', 13, '2026-11-18 06:07:51'),
(14, 11, 'UERC', 'sdfghikmn', '2026-11-20 09:37:00', 13, '2026-11-20 01:57:18'),
(15, 11, 'In-House', 'qwerty', '2026-11-20 09:07:00', 13, '2026-11-20 02:20:57'),
(16, 14, 'UERC', 'dddd', '2026-11-21 08:23:00', 13, '2026-11-21 02:41:02'),
(17, 14, 'In-House', 'fff', '2026-11-21 00:39:00', 13, '2026-11-21 02:42:36'),
(18, 15, 'UERC', 'GDRFGDFRGFDG', '2026-11-21 10:52:00', 13, '2026-11-21 02:59:15'),
(19, 16, 'UERC', 'fgf', '2026-11-21 07:10:00', 13, '2026-11-21 03:10:27'),
(20, 12, 'UERC', 'dfedf', '2026-11-21 04:31:00', 13, '2026-11-21 03:31:07'),
(21, 16, 'In-House', 'erfgwgugewffewi', '2026-11-21 10:38:00', 13, '2026-11-21 03:38:49'),
(22, 13, 'UERC', 'hfieyyueyryyertw', '2026-11-21 10:43:00', 13, '2026-11-21 03:43:38'),
(23, 13, 'In-House', 'fghrttryh', '2026-11-21 11:44:00', 13, '2026-11-21 03:44:58'),
(24, 17, 'UERC', 'FDEWUIHIREIYIUY', '2026-11-21 12:42:00', 13, '2026-11-21 04:42:42'),
(25, 17, 'In-House', 'dgvsgdrg', '2026-11-21 12:50:00', 13, '2026-11-21 04:50:47'),
(26, 18, 'UERC', 'wert', '2026-11-21 10:45:00', 13, '2026-11-21 05:43:55'),
(27, 18, 'In-House', 'wert', '2026-11-21 13:59:00', 13, '2026-11-21 06:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `extension_revisions`
--

CREATE TABLE `extension_revisions` (
  `revision_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `review_id` int(10) UNSIGNED NOT NULL,
  `revision_stage` enum('UERC','In-House') NOT NULL,
  `revised_proposal_url` varchar(1000) NOT NULL,
  `revision_remarks` text DEFAULT NULL,
  `submitted_by` int(10) UNSIGNED NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_revisions`
--

INSERT INTO `extension_revisions` (`revision_id`, `extension_id`, `review_id`, `revision_stage`, `revised_proposal_url`, `revision_remarks`, `submitted_by`, `submitted_at`) VALUES
(1, 5, 2, 'UERC', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'jhshhsd', 8, '2026-11-10 07:47:42'),
(2, 5, 3, 'In-House', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'qwertygfdxs', 8, '2026-11-10 08:01:31'),
(3, 18, 26, 'UERC', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'wert', 8, '2026-11-21 05:55:24'),
(4, 18, 27, 'In-House', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'wertt', 8, '2026-11-21 06:03:17');

-- --------------------------------------------------------

--
-- Table structure for table `extension_service_requests`
--

CREATE TABLE `extension_service_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `request_code` varchar(32) DEFAULT NULL,
  `requester_id` int(11) NOT NULL,
  `service_type` enum('Training','Technical Service') DEFAULT NULL,
  `organization_name` varchar(180) DEFAULT NULL,
  `contact_person` varchar(160) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `contact_number` varchar(40) DEFAULT NULL,
  `service_title` varchar(180) DEFAULT NULL,
  `service_description` text DEFAULT NULL,
  `target_participants` int(10) UNSIGNED DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_location` varchar(255) DEFAULT NULL,
  `request_letter_url` varchar(2048) DEFAULT NULL,
  `office_remarks` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Acknowledged','Needs Information','Assigned','Scheduled','Ongoing','Completed') NOT NULL DEFAULT 'Draft',
  `submitted_at` datetime DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  `assigned_unit` varchar(180) DEFAULT NULL,
  `assigned_personnel` varchar(180) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `scheduled_location` varchar(255) DEFAULT NULL,
  `extensionist_completed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `requested_services_json` longtext DEFAULT NULL,
  `other_service_details` varchar(500) DEFAULT NULL,
  `requester_signature_name` varchar(160) DEFAULT NULL,
  `assisted_by_name` varchar(160) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `extension_service_requests`
--

INSERT INTO `extension_service_requests` (`request_id`, `request_code`, `requester_id`, `service_type`, `organization_name`, `contact_person`, `contact_email`, `contact_number`, `service_title`, `service_description`, `target_participants`, `preferred_date`, `preferred_location`, `request_letter_url`, `office_remarks`, `status`, `submitted_at`, `acknowledged_at`, `acknowledged_by`, `assigned_unit`, `assigned_personnel`, `scheduled_date`, `scheduled_time`, `scheduled_location`, `extensionist_completed_at`, `completed_at`, `completed_by`, `created_at`, `updated_at`, `requested_services_json`, `other_service_details`, `requester_signature_name`, `assisted_by_name`) VALUES
(1, 'SR-2026-00001', 8, 'Training', 'test 1', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09223456788', 'test 1', 'cdudsuijc', 20, '2026-12-04', 'Kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Completed', '2026-11-11 13:21:57', '2026-11-11 13:22:55', 13, 'usiouoduo', 'uhweifiye', '2026-12-07', '13:24:00', 'kalibo', '2026-11-11 13:33:03', '2026-11-11 13:33:37', 13, '2026-11-11 13:21:57', '2026-11-11 13:33:37', NULL, NULL, NULL, NULL),
(2, 'SR-2026-00002', 8, 'Training', 'test 1', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09223456788', 'test 1', 'fsjdhakls;dk;aa', 20, NULL, 'Kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Completed', '2026-11-12 14:00:04', '2026-11-12 14:24:15', 13, '12345', 'larra jane casidsid', '2026-11-12', '14:25:00', 'Kalibo', '2026-11-12 14:28:28', '2026-11-12 14:28:42', 13, '2026-11-12 14:00:04', '2026-11-12 14:28:42', NULL, NULL, NULL, NULL),
(3, 'SR-2026-00003', 8, 'Training', 'uertyui', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '1234567890-', 'test 1', 'qwertyuiob', 123, NULL, 'kalibo', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Completed', '2026-11-12 14:17:45', '2026-11-12 14:18:08', 13, 'dghjkl;', 'uhweifiye', '2026-12-04', '14:18:00', 'kalibo', '2026-11-12 14:27:24', '2026-11-12 14:28:09', 13, '2026-11-12 14:01:32', '2026-11-12 14:28:09', NULL, NULL, NULL, NULL),
(4, 'SR-2026-00004', 8, 'Training', 'wertyu', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '098765432', '1ewrquu', 'qwerty', 12, NULL, 'kalib', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Completed', '2026-11-12 14:29:50', '2026-11-12 14:30:12', 13, 'gkahfas', 'wdhawkjdkla', '2026-11-12', '14:30:00', 'kalibo', '2026-11-12 14:30:54', '2026-11-12 14:31:14', 13, '2026-11-12 14:29:50', '2026-11-12 14:31:14', NULL, NULL, NULL, NULL),
(5, 'SR-2026-00005', 8, 'Technical Service', 'asu', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09773244389', 'Paper Editing', '2qewef', NULL, '2026-11-18', 'asu', NULL, NULL, 'Submitted', '2026-11-18 15:09:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-18 15:09:43', '2026-11-18 15:09:43', '[\"Paper Editing\"]', NULL, 'Sisa', 'Sisa Rone'),
(6, 'SR-2026-00006', 8, '', 'asu', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09773244389', 'Request Letter - asu', 'jflgjlfdg', NULL, '2026-11-20', 'asu', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Submitted', '2026-11-20 22:15:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-20 22:15:09', '2026-11-20 22:15:09', '[]', NULL, 'Sisa', 'Sisa'),
(7, 'SR-2026-00007', 8, '', 'kalibo', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09773244389', 'Request Letter - kalibo', 'dsfosd;pofksdfngn', NULL, '2026-11-20', 'kalibo', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', NULL, 'Completed', '2026-11-20 22:35:19', '2026-11-20 22:35:37', 13, 'qwertyi', 'asdfg', '2026-11-20', '22:36:00', 'poblacion', '2026-11-20 22:36:35', '2026-11-20 22:36:57', 13, '2026-11-20 22:17:01', '2026-11-20 22:36:57', '[]', NULL, 'Sisa', 'Richelle'),
(8, 'SR-2026-00008', 8, 'Technical Service', 'gdhdfh', 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', '09773244389', 'Poster Layout', 'gfhgfh', NULL, '2026-11-20', 'gdhdfh', NULL, NULL, 'Submitted', '2026-11-20 22:31:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-20 22:31:41', '2026-11-20 22:31:41', '[\"Poster Layout\"]', NULL, 'Sisa', 'Sisa1');

-- --------------------------------------------------------

--
-- Table structure for table `extension_status_history`
--

CREATE TABLE `extension_status_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `extension_id` int(10) UNSIGNED NOT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extension_status_history`
--

INSERT INTO `extension_status_history` (`history_id`, `extension_id`, `changed_by`, `old_status`, `new_status`, `remarks`, `changed_at`) VALUES
(5, 3, 8, NULL, 'Draft', 'Extension program draft created with supporting document.', '2026-10-29 02:58:04'),
(6, 4, 8, NULL, 'Draft', 'Extension program draft created with supporting document.', '2026-11-10 07:21:18'),
(7, 4, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-10 07:28:40'),
(8, 4, 13, 'Submitted', 'UERC Technical Review', 'Extension Service Office started UERC Technical Review.', '2026-11-10 07:30:15'),
(9, 4, 13, 'UERC Technical Review', 'For UERC Revision', 'UERC comments recorded. Proposal forwarded for revision.', '2026-11-10 07:31:42'),
(10, 5, 8, NULL, 'Draft', 'Extension program draft created with supporting document.', '2026-11-10 07:42:54'),
(11, 5, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-10 07:43:22'),
(12, 5, 13, 'Submitted', 'UERC Technical Review', 'Extension Service Office started UERC Technical Review.', '2026-11-10 07:44:42'),
(13, 5, 13, 'UERC Technical Review', 'For UERC Revision', 'UERC comments recorded. Proposal forwarded for revision.', '2026-11-10 07:45:07'),
(14, 5, 8, 'For UERC Revision', 'University In-House Review', 'Revised proposal submitted after UERC Technical Review.', '2026-11-10 07:47:42'),
(15, 5, 13, 'University In-House Review', 'For In-House Revision', 'ESO recorded In-House Review comments; proposal sent for In-House revision.', '2026-11-10 07:54:16'),
(16, 5, 8, 'For In-House Revision', 'For Endorsement', 'Revised proposal submitted after University In-House Review.', '2026-11-10 08:01:31'),
(17, 5, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-10 08:19:06'),
(18, 5, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-10 08:26:03'),
(19, 5, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-10 08:27:02'),
(20, 5, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-10 08:36:30'),
(21, 6, 8, NULL, 'Draft', 'Extension program draft created with supporting document.', '2026-11-11 01:58:22'),
(22, 6, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-11 02:06:31'),
(23, 6, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-11 02:12:10'),
(24, 6, 13, 'UERC Technical Review', 'University In-House Review', 'UERC Decision: Accepted. ESO recorded the official UERC result; proposal advanced to University In-House Review.', '2026-11-11 02:34:02'),
(25, 6, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-11 02:55:19'),
(26, 6, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-11 03:01:01'),
(27, 6, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-11 03:07:10'),
(28, 6, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-11 03:07:58'),
(29, 6, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-11 03:17:05'),
(30, 7, 8, NULL, 'Draft', 'Extension program draft created with supporting HTTPS link.', '2026-11-12 04:15:06'),
(31, 7, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-12 04:45:05'),
(32, 7, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-12 04:45:57'),
(33, 7, 13, 'UERC Technical Review', 'University In-House Review', 'UERC Decision: Accepted. ESO recorded the official UERC result; proposal advanced to University In-House Review.', '2026-11-12 04:52:10'),
(34, 7, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-12 04:54:47'),
(35, 8, 8, NULL, 'Draft', 'Extension program draft created with supporting HTTPS link.', '2026-11-12 04:58:35'),
(36, 8, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-12 04:58:42'),
(37, 8, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-12 04:59:31'),
(38, 8, 13, 'UERC Technical Review', 'For UERC Revision', 'UERC Decision: Accepted with Revision. ESO recorded official UERC comments; proposal returned to the proponent for revision.', '2026-11-12 04:59:47'),
(39, 7, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-12 05:04:26'),
(40, 7, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-12 05:12:37'),
(41, 7, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-12 05:13:06'),
(42, 7, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-12 05:57:58'),
(43, 9, 8, NULL, 'Draft', 'Extension program draft created with supporting HTTPS link.', '2026-11-18 01:54:03'),
(44, 9, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-18 01:54:32'),
(45, 9, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-18 01:55:16'),
(46, 9, 13, 'UERC Technical Review', 'University In-House Review', 'UERC Decision: Accepted. ESO recorded the official UERC result; proposal advanced to University In-House Review.', '2026-11-18 01:57:29'),
(47, 9, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-18 01:59:47'),
(48, 9, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-18 02:00:52'),
(49, 9, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-18 02:02:50'),
(50, 9, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-18 02:03:21'),
(51, 9, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-18 02:05:31'),
(52, 10, 8, NULL, 'Draft', 'Extension program draft created with supporting HTTPS link.', '2026-11-18 05:29:59'),
(53, 10, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-18 05:30:08'),
(54, 10, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-18 05:30:51'),
(55, 10, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. ESO recorded the official UERC result; proposal moved to Chairperson / Dean / CD Endorsement.', '2026-11-18 05:54:13'),
(56, 10, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-18 13:52:00): gtdsghdydgdgfdhhfyyf', '2026-11-18 05:54:43'),
(57, 10, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-18 06:07:51'),
(58, 10, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-18 06:08:18'),
(59, 10, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-18 06:08:36'),
(60, 10, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-18 06:09:07'),
(61, 10, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-18 06:10:46'),
(62, 11, 8, NULL, 'Draft', 'Extension program draft created with supporting HTTPS link.', '2026-11-20 01:47:53'),
(63, 11, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-20 01:48:06'),
(64, 11, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-20 01:48:42'),
(65, 11, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. ESO recorded the official UERC result; proposal moved to Chairperson / Dean / CD Endorsement.', '2026-11-20 01:57:18'),
(66, 11, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-20 09:57:00): sdfghj', '2026-11-20 02:06:45'),
(67, 11, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-20 02:20:57'),
(68, 11, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-20 02:22:22'),
(69, 11, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-20 02:23:35'),
(70, 11, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-20 02:25:52'),
(71, 11, 13, 'For Certification', 'Approved', 'ESO recorded official BOR Certification. Extension proposal approval process completed.', '2026-11-20 02:29:12'),
(72, 12, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-20 16:32:22'),
(73, 13, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 02:15:15'),
(74, 13, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 02:15:40'),
(75, 13, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 02:22:24'),
(76, 14, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 02:40:27'),
(77, 14, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 02:40:32'),
(78, 14, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 02:40:46'),
(79, 14, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 02:41:02'),
(80, 14, 8, 'For Pre-In-House Endorsement', 'For Pre-In-House Endorsement', 'COORDINATOR_INHOUSE_ENDORSED', '2026-11-21 02:42:14'),
(81, 14, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-21 06:38:00): fff', '2026-11-21 02:42:24'),
(82, 14, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-21 02:42:36'),
(83, 14, 8, 'For Endorsement', 'For Endorsement', 'COORDINATOR_FINAL_ENDORSED', '2026-11-21 02:42:59'),
(84, 14, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-21 02:43:05'),
(85, 14, 13, 'For LADCO/ADCO Approval', 'For BOR Notation', 'ESO recorded official LADCO/ADCO approval.', '2026-11-21 02:43:32'),
(86, 14, 13, 'For BOR Notation', 'For Certification', 'ESO recorded official BOR notation.', '2026-11-21 02:45:22'),
(87, 14, 13, 'For Certification', 'For Certification', 'ESO recorded official BOR Certification; awaiting Notice of Approval.', '2026-11-21 02:45:39'),
(88, 14, 13, 'For Certification', 'Approved', 'ESO recorded Notice of Approval after official BOR Certification.', '2026-11-21 02:45:55'),
(89, 12, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 02:47:17'),
(90, 12, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 02:47:24'),
(91, 15, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 02:54:27'),
(92, 15, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 02:54:34'),
(93, 15, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 02:59:02'),
(94, 15, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 02:59:15'),
(95, 16, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 03:10:03'),
(96, 16, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 03:10:07'),
(97, 16, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 03:10:13'),
(98, 16, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 03:10:27'),
(99, 16, 8, 'For Pre-In-House Endorsement', 'For Pre-In-House Endorsement', 'COORDINATOR_INHOUSE_ENDORSED: Document submitted', '2026-11-21 03:17:02'),
(100, 12, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 03:31:07'),
(101, 16, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-21 10:37:00): gyfeyuie', '2026-11-21 03:38:01'),
(102, 16, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-21 03:38:49'),
(103, 16, 8, 'For Endorsement', 'For Endorsement', 'COORDINATOR_FINAL_ENDORSED: Document submitted', '2026-11-21 03:39:23'),
(104, 16, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO recorded the official Extension proposal endorsement.', '2026-11-21 03:41:51'),
(105, 13, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 03:43:38'),
(106, 13, 8, 'For Pre-In-House Endorsement', 'For Pre-In-House Endorsement', 'COORDINATOR_INHOUSE_ENDORSED: Document submitted', '2026-11-21 03:44:17'),
(107, 13, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-21 10:44:00): fdhuifiureiw', '2026-11-21 03:44:37'),
(108, 13, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-21 03:44:58'),
(109, 13, 8, 'For Endorsement', 'For Endorsement', 'COORDINATOR_FINAL_ENDORSED: Document submitted', '2026-11-21 03:46:21'),
(110, 13, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO verified the Coordinator final endorsement document and forwarded the proposal for LADCO/UADCO/BOR processing.', '2026-11-21 03:57:42'),
(111, 13, 13, 'For LADCO/ADCO Approval', 'For Certification', 'ESO recorded official LADCO/UADCO approval and BOR notation together.', '2026-11-21 04:14:59'),
(112, 13, 13, 'For Certification', 'Approved', 'ESO issued the Notice of Approval / Certification after LADCO/UADCO/BOR.', '2026-11-21 04:21:17'),
(113, 17, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 04:42:08'),
(114, 17, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 04:42:12'),
(115, 17, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 04:42:29'),
(116, 17, 13, 'UERC Technical Review', 'For Pre-In-House Endorsement', 'UERC Decision: Accepted. Awaiting Coordinator endorsement for In-House Review.', '2026-11-21 04:42:42'),
(117, 17, 8, 'For Pre-In-House Endorsement', 'For Pre-In-House Endorsement', 'COORDINATOR_INHOUSE_ENDORSED: Document submitted', '2026-11-21 04:50:15'),
(118, 17, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-21 12:50:00): tgrjrt', '2026-11-21 04:50:34'),
(119, 17, 13, 'University In-House Review', 'For Endorsement', 'In-House Decision: Accepted. ESO recorded the official In-House Review result; proposal advanced to Review and Endorsement.', '2026-11-21 04:50:47'),
(120, 17, 8, 'For Endorsement', 'For Endorsement', 'COORDINATOR_FINAL_ENDORSED: Document submitted', '2026-11-21 04:51:05'),
(121, 17, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO verified the Coordinator final endorsement document and forwarded the proposal for LADCO/UADCO/BOR processing.', '2026-11-21 04:51:27'),
(122, 17, 13, 'For LADCO/ADCO Approval', 'For Certification', 'ESO recorded official LADCO/UADCO approval and BOR notation together.', '2026-11-21 04:51:47'),
(123, 17, 13, 'For Certification', 'Approved', 'ESO issued the Notice of Approval / Certification after LADCO/UADCO/BOR.', '2026-11-21 04:52:02'),
(124, 18, 8, NULL, 'Draft', 'Extension program/project draft created with supporting HTTPS link.', '2026-11-21 05:41:50'),
(125, 18, 8, 'Draft', 'Submitted', 'Extension proposal submitted to the Extension Service Office for UERC processing.', '2026-11-21 05:42:25'),
(126, 18, 13, 'Submitted', 'UERC Technical Review', 'ESO started UERC Technical Review.', '2026-11-21 05:43:14'),
(127, 18, 13, 'UERC Technical Review', 'For UERC Revision', 'UERC Decision: Accepted with Revision. ESO recorded official UERC comments; proposal returned to the proponent for revision.', '2026-11-21 05:43:55'),
(128, 18, 8, 'For UERC Revision', 'For Pre-In-House Endorsement', 'Coordinator submitted revised UERC proposal; awaiting the separate endorsement document.', '2026-11-21 05:55:24'),
(129, 18, 8, 'For Pre-In-House Endorsement', 'For Pre-In-House Endorsement', 'COORDINATOR_INHOUSE_ENDORSED: Document submitted', '2026-11-21 05:58:14'),
(130, 18, 13, 'For Pre-In-House Endorsement', 'University In-House Review', 'Chairperson endorsement verified (2026-11-21 13:00:00): wert', '2026-11-21 06:02:11'),
(131, 18, 13, 'University In-House Review', 'For In-House Revision', 'In-House Decision: Accepted with Revision. ESO recorded official In-House comments; proposal returned to the proponent for revision.', '2026-11-21 06:02:36'),
(132, 18, 8, 'For In-House Revision', 'For Endorsement', 'Coordinator submitted revised In-House proposal; awaiting the separate final endorsement document.', '2026-11-21 06:03:17'),
(133, 18, 8, 'For Endorsement', 'For Endorsement', 'COORDINATOR_FINAL_ENDORSED: Document submitted', '2026-11-21 06:03:50'),
(134, 18, 13, 'For Endorsement', 'For LADCO/ADCO Approval', 'ESO verified the Coordinator final endorsement document and forwarded the proposal for LADCO/UADCO/BOR processing.', '2026-11-21 06:04:32'),
(135, 18, 13, 'For LADCO/ADCO Approval', 'For Certification', 'ESO recorded official LADCO/UADCO approval and BOR notation together.', '2026-11-21 06:05:22'),
(136, 18, 13, 'For Certification', 'Approved', 'ESO issued the Notice of Approval / Certification after LADCO/UADCO/BOR.', '2026-11-21 06:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `login_activity_logs`
--

CREATE TABLE `login_activity_logs` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(254) NOT NULL DEFAULT '',
  `user_role` varchar(80) NOT NULL,
  `login_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_activity_logs`
--

INSERT INTO `login_activity_logs` (`log_id`, `user_id`, `full_name`, `email`, `user_role`, `login_at`) VALUES
(1, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-12 17:27:43'),
(2, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-12 22:44:59'),
(3, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-12 22:44:59'),
(4, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-12 22:45:09'),
(5, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-13 21:42:22'),
(6, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-13 22:20:50'),
(7, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-13 22:38:28'),
(8, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-13 22:38:52'),
(9, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-13 22:38:56'),
(10, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-15 22:45:50'),
(11, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-15 22:45:59'),
(12, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 14:29:38'),
(13, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 14:29:45'),
(14, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 19:37:39'),
(15, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 19:37:48'),
(16, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 20:09:24'),
(17, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 20:09:31'),
(18, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 20:16:06'),
(19, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 20:24:42'),
(20, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 20:36:21'),
(21, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 21:30:52'),
(22, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 21:30:59'),
(23, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 21:36:56'),
(24, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-16 22:29:21'),
(25, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-16 22:29:30'),
(26, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-16 22:31:28'),
(27, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-16 22:33:12'),
(28, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-16 22:33:15'),
(29, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-16 22:33:48'),
(30, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-16 22:40:43'),
(31, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-16 22:40:54'),
(32, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 22:43:56'),
(33, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-16 22:44:14'),
(34, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 22:56:29'),
(35, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 23:00:55'),
(36, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 23:11:18'),
(37, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-16 23:21:14'),
(38, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-16 23:57:21'),
(39, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-16 23:57:23'),
(40, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-17 00:01:14'),
(41, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 00:01:45'),
(42, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 09:15:40'),
(43, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 09:15:40'),
(44, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 09:16:00'),
(45, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 09:16:07'),
(46, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 09:41:48'),
(47, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 09:41:55'),
(48, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 09:57:55'),
(49, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-17 09:58:09'),
(50, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-17 09:58:18'),
(51, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 09:58:32'),
(52, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 09:59:17'),
(53, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-17 10:01:37'),
(54, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 10:02:47'),
(55, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:04:35'),
(56, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 10:04:49'),
(57, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-17 10:06:53'),
(58, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:07:15'),
(59, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:08:29'),
(60, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-17 10:09:12'),
(61, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:09:46'),
(62, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 10:13:36'),
(63, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 10:14:17'),
(64, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:14:43'),
(65, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 10:14:52'),
(66, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-17 10:25:05'),
(67, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:26:15'),
(68, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:54:36'),
(69, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 10:54:43'),
(70, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 11:13:09'),
(71, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 11:13:12'),
(72, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-17 12:29:29'),
(73, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 12:31:24'),
(74, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 12:36:36'),
(75, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 12:36:48'),
(76, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-17 12:37:08'),
(77, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 13:18:23'),
(78, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-17 13:35:26'),
(79, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-17 14:01:12'),
(80, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-17 14:01:40'),
(81, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-17 14:37:14'),
(82, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:08:56'),
(83, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:08:57'),
(84, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:08:59'),
(85, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:09:07'),
(86, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-18 09:09:29'),
(87, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 09:11:03'),
(88, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:11:27'),
(89, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 09:33:13'),
(90, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 09:33:13'),
(91, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-18 09:33:20'),
(92, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 09:35:44'),
(93, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 09:35:51'),
(94, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 09:36:11'),
(95, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 13:25:22'),
(96, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 13:25:34'),
(97, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 13:25:58'),
(98, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-18 13:28:23'),
(99, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 13:30:34'),
(100, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-18 13:30:40'),
(101, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-19 01:44:16'),
(102, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-19 01:44:23'),
(103, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-19 09:55:48'),
(104, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-19 09:57:33'),
(105, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-19 21:52:16'),
(106, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-19 21:52:16'),
(107, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-19 21:52:43'),
(108, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-19 21:52:51'),
(109, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-19 21:54:48'),
(110, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 00:33:43'),
(111, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 09:25:38'),
(112, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 09:25:38'),
(113, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 09:25:39'),
(114, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 09:26:02'),
(115, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 09:26:11'),
(116, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 09:26:33'),
(117, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 09:42:32'),
(118, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 10:32:12'),
(119, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 11:04:30'),
(120, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 15:01:18'),
(121, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 15:25:25'),
(122, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-20 15:25:29'),
(123, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 15:25:46'),
(124, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 15:26:03'),
(125, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 15:26:05'),
(126, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 16:10:03'),
(127, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 16:12:22'),
(128, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 16:12:24'),
(129, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:01:39'),
(130, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:01:40'),
(131, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:01:40'),
(132, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:01:59'),
(133, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:02:06'),
(134, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 20:03:25'),
(135, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 20:03:27'),
(136, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 20:32:06'),
(137, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-20 20:32:31'),
(138, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-20 20:33:59'),
(139, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-20 20:56:43'),
(140, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 21:05:28'),
(141, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 21:09:57'),
(142, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 21:48:48'),
(143, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-20 21:52:30'),
(144, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-20 22:10:02'),
(145, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-21 01:19:56'),
(146, 9, 'ASU REMS', 'asurems.edu.ph@gmail.com', 'admin', '2026-11-21 01:20:19'),
(147, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-21 01:22:46'),
(148, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-21 10:12:28'),
(149, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-21 10:12:39'),
(150, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-21 10:21:55'),
(151, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-21 10:21:59'),
(152, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-21 13:38:10'),
(153, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-21 13:42:47'),
(154, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-21 13:42:54'),
(155, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-21 14:17:03'),
(156, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-21 14:17:44'),
(157, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-22 14:11:25'),
(158, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-22 14:11:25'),
(159, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-22 14:11:40'),
(160, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-22 14:13:23'),
(161, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-22 14:13:51'),
(162, 6, 'Larra Jane D. Casidsid', 'larra_asurems@example.com', 'faculty', '2026-11-22 14:30:52'),
(163, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-22 14:56:40'),
(164, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-22 14:57:09'),
(165, 7, 'Richelle Gayne A. Bautista', 'rich_asurems@example.com', 'rps', '2026-11-22 15:02:10'),
(166, 8, 'Ronelyn Mae A. Felipe', 'rone_asurems@example.com', 'extension_coordinator', '2026-11-22 15:20:12'),
(167, 13, 'Extension Service Office', 'eso@example.test', 'extension_service_office', '2026-11-22 15:21:17');

-- --------------------------------------------------------

--
-- Table structure for table `manuscript_sequences`
--

CREATE TABLE `manuscript_sequences` (
  `sequence_year` smallint(5) UNSIGNED NOT NULL,
  `last_number` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `module` varchar(60) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `module`, `record_id`, `action_url`, `is_read`, `read_at`, `created_at`) VALUES
(473, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0049: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-12 11:39:36', '2026-10-06 12:04:10'),
(474, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0049: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 49, '/asu-rems/research/view.php?id=49', 0, NULL, '2026-10-06 12:04:10'),
(475, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" is now under UREC/IACUC processing by the Research Office.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-16 22:59:35', '2026-10-06 12:05:49'),
(476, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-16 22:59:35', '2026-10-06 12:07:02'),
(477, 7, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0049. Two UPE certificates are now required.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-12 11:39:36', '2026-10-06 12:07:19'),
(478, 9, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0049. Two UPE certificates are now required.', 'research', 49, '/asu-rems/research/view.php?id=49', 0, NULL, '2026-10-06 12:07:19'),
(479, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0049.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-12 11:39:36', '2026-10-06 12:15:02'),
(480, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0049.', 'research', 49, '/asu-rems/research/view.php?id=49', 0, NULL, '2026-10-06 12:15:02'),
(481, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0049. Both required UPE certificates are complete.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-11-12 11:39:36', '2026-10-06 12:15:08'),
(482, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0049. Both required UPE certificates are complete.', 'research', 49, '/asu-rems/research/view.php?id=49', 0, NULL, '2026-10-06 12:15:08'),
(483, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0049: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students.', 'research', 49, '/asu-rems/research/view.php?id=49', 1, '2026-10-06 20:28:14', '2026-10-06 12:15:20'),
(484, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0049: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students.', 'research', 49, '/asu-rems/research/view.php?id=49', 0, NULL, '2026-10-06 12:15:20'),
(485, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0050: Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-10-06 21:53:03', '2026-10-06 13:10:04'),
(486, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0050: Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production', 'research', 50, '/asu-rems/research/view.php?id=50', 0, NULL, '2026-10-06 13:10:04'),
(487, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production\" is now under UREC/IACUC processing by the Research Office.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-11-16 22:59:35', '2026-10-06 13:13:01'),
(488, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-11-16 22:59:35', '2026-10-06 13:15:56'),
(489, 7, 'Research forwarded for URRC processing', 'Research ASU-RES-2026-0050 has been forwarded by the faculty researcher for URRC processing.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-10-06 21:40:06', '2026-10-06 13:37:38'),
(490, 6, 'URRC review accepted', 'Your research \"Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production\" was accepted in URRC review and is now for In-House review.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-11-16 22:59:35', '2026-10-06 13:38:39'),
(491, 6, 'In-House review passed', 'Your research \"Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production\" passed the In-House review and is now ready for Notice to Proceed.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-11-16 22:59:35', '2026-10-06 13:39:00'),
(492, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"Integrated Nutrient Management Using Vermicompost and Reduced Inorganic Fertilizer Rates for Sustainable Pechay (Brassica rapa L.) Production\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 50, '/asu-rems/research/view.php?id=50', 1, '2026-10-06 21:40:33', '2026-10-06 13:39:09'),
(493, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0051: Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-12 11:39:36', '2026-10-06 14:30:26'),
(494, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0051: Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment', 'research', 51, '/asu-rems/research/view.php?id=51', 0, NULL, '2026-10-06 14:30:26'),
(495, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment\" is now under UREC/IACUC processing by the Research Office.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-16 22:59:35', '2026-10-06 14:33:22'),
(496, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-16 22:59:35', '2026-10-06 14:38:02'),
(497, 7, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0051. Two UPE certificates are now required.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-12 11:39:36', '2026-10-06 14:39:19'),
(498, 9, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0051. Two UPE certificates are now required.', 'research', 51, '/asu-rems/research/view.php?id=51', 0, NULL, '2026-10-06 14:39:19'),
(499, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0051.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-12 11:39:36', '2026-10-06 14:40:51'),
(500, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0051.', 'research', 51, '/asu-rems/research/view.php?id=51', 0, NULL, '2026-10-06 14:40:51'),
(501, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0051. Both required UPE certificates are complete.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-12 11:39:36', '2026-10-06 14:41:00'),
(502, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0051. Both required UPE certificates are complete.', 'research', 51, '/asu-rems/research/view.php?id=51', 0, NULL, '2026-10-06 14:41:00'),
(503, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0051: Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment.', 'research', 51, '/asu-rems/research/view.php?id=51', 1, '2026-11-12 11:39:36', '2026-10-06 14:42:58'),
(504, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0051: Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment.', 'research', 51, '/asu-rems/research/view.php?id=51', 0, NULL, '2026-10-06 14:42:58'),
(505, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-07 08:51:46', '2026-10-06 15:22:25'),
(506, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 0, NULL, '2026-10-06 15:22:25'),
(507, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" is now under UREC/IACUC processing by the Research Office.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-11-16 22:59:35', '2026-10-06 15:22:47'),
(509, 7, 'Research forwarded for URRC processing', 'Research ASU-RES-2026-0052 has been forwarded by the faculty researcher for URRC processing.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-06 23:37:29', '2026-10-06 15:23:58'),
(510, 6, 'URRC revision required', 'Your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: Accepted with minor revisions for further improvement of the study.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-11-16 22:59:35', '2026-10-06 15:26:47'),
(511, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-06 23:29:32', '2026-10-06 15:29:01'),
(512, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 0, NULL, '2026-10-06 15:29:01'),
(513, 6, 'URRC revision required', 'Your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: Accepted with minor revisions for further improvement of the study.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-06 23:30:18', '2026-10-06 15:29:08'),
(514, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-06 23:30:45', '2026-10-06 15:30:28'),
(515, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0052: Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'research', 52, '/asu-rems/research/view.php?id=52', 0, NULL, '2026-10-06 15:30:28'),
(516, 6, 'URRC review accepted', 'Your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" was accepted in URRC review and is now for In-House review.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-11-16 22:59:35', '2026-10-06 15:31:02'),
(517, 6, 'In-House review passed', 'Your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\" passed the In-House review and is now ready for Notice to Proceed.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-11-16 22:59:35', '2026-10-06 15:31:21'),
(518, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 52, '/asu-rems/research/view.php?id=52', 1, '2026-10-06 23:32:10', '2026-10-06 15:31:30'),
(521, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"ASU\" is now under UREC/IACUC processing by the Research Office.', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:55:48'),
(522, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASU\" has been completed with classification: Full-board. Your system-generated UREC/IACUC certificate is now available.', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:55:56'),
(524, 6, 'URRC review accepted', 'Your research \"ASU\" was accepted in URRC review and is now for In-House review. Remarks: ddc', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:56:31'),
(525, 6, 'In-House review passed', 'Your research \"ASU\" passed the In-House review and is now ready for Notice to Proceed. Remarks: dd', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:56:42'),
(526, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ASU\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:56:50'),
(529, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"ASU\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 53, '/asu-rems/research/view.php?id=53', 1, '2026-11-16 22:59:35', '2026-10-07 00:57:58'),
(532, 6, 'Research permit issued', 'A Permit for Publication has been issued for your research \"ASU\". You may view or print the permit from the research details page.', 'research', 53, '/asu-rems/research/view.php?id=53&certificate=publication_presentation_permit&permit_type=publication', 1, '2026-11-16 22:59:35', '2026-10-07 00:59:39'),
(535, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0054: jkdjkashdklas', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-12 11:39:36', '2026-10-13 13:36:32'),
(536, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0054: jkdjkashdklas', 'research', 54, '/asu-rems/research/view.php?id=54', 0, NULL, '2026-10-13 13:36:32'),
(537, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"jkdjkashdklas\" is now under UREC/IACUC processing by the Research Office.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-16 22:59:35', '2026-10-13 14:15:47'),
(538, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"jkdjkashdklas\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-16 22:59:35', '2026-10-13 14:16:00'),
(539, 7, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0054. Two UPE certificates are now required.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-12 11:39:36', '2026-10-13 14:16:21'),
(540, 9, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0054. Two UPE certificates are now required.', 'research', 54, '/asu-rems/research/view.php?id=54', 0, NULL, '2026-10-13 14:16:21'),
(541, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0054.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-12 11:39:36', '2026-10-13 14:17:13'),
(542, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0054.', 'research', 54, '/asu-rems/research/view.php?id=54', 0, NULL, '2026-10-13 14:17:13'),
(543, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0054. Both required UPE certificates are complete.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-12 11:39:36', '2026-10-13 14:41:36'),
(544, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0054. Both required UPE certificates are complete.', 'research', 54, '/asu-rems/research/view.php?id=54', 0, NULL, '2026-10-13 14:41:36'),
(545, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0054: jkdjkashdklas.', 'research', 54, '/asu-rems/research/view.php?id=54', 1, '2026-11-12 11:39:36', '2026-10-13 14:43:13'),
(546, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0054: jkdjkashdklas.', 'research', 54, '/asu-rems/research/view.php?id=54', 0, NULL, '2026-10-13 14:43:13'),
(547, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0055: IOSUUFSE', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-12 11:39:36', '2026-10-13 15:58:36'),
(548, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0055: IOSUUFSE', 'research', 55, '/asu-rems/research/view.php?id=55', 0, NULL, '2026-10-13 15:58:36'),
(549, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"IOSUUFSE\" is now under UREC/IACUC processing by the Research Office.', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-16 22:59:35', '2026-10-13 16:00:30'),
(550, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"IOSUUFSE\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-16 22:59:35', '2026-10-13 16:13:25'),
(551, 7, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0055. Two UPE certificates are now required.', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-12 11:39:36', '2026-10-13 16:13:34'),
(552, 9, 'Self-Initiated research selected', 'Larra Jane D. Casidsid selected the Self-Initiated track for research ASU-RES-2026-0055. Two UPE certificates are now required.', 'research', 55, '/asu-rems/research/view.php?id=55', 0, NULL, '2026-10-13 16:13:34'),
(553, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0055.', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-12 11:39:36', '2026-10-13 16:13:45'),
(554, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0055.', 'research', 55, '/asu-rems/research/view.php?id=55', 0, NULL, '2026-10-13 16:13:45'),
(555, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0055. Both required UPE certificates are complete.', 'research', 55, '/asu-rems/research/view.php?id=55', 1, '2026-11-12 11:39:36', '2026-10-13 16:37:33'),
(556, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0055. Both required UPE certificates are complete.', 'research', 55, '/asu-rems/research/view.php?id=55', 0, NULL, '2026-10-13 16:37:33'),
(557, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0056: TESTING 1', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-12 11:39:36', '2026-10-13 16:39:45'),
(558, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0056: TESTING 1', 'research', 56, '/asu-rems/research/view.php?id=56', 0, NULL, '2026-10-13 16:39:45'),
(559, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0056.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-12 11:39:36', '2026-10-13 16:39:48'),
(560, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0056.', 'research', 56, '/asu-rems/research/view.php?id=56', 0, NULL, '2026-10-13 16:39:48'),
(561, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0056.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-12 11:39:36', '2026-10-13 16:39:55'),
(562, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0056.', 'research', 56, '/asu-rems/research/view.php?id=56', 0, NULL, '2026-10-13 16:39:55'),
(563, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0056. Both required UPE certificates are complete.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-10-14 01:01:03', '2026-10-13 16:39:58'),
(564, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0056. Both required UPE certificates are complete.', 'research', 56, '/asu-rems/research/view.php?id=56', 0, NULL, '2026-10-13 16:39:58'),
(565, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"TESTING 1\" is now under UREC/IACUC processing by the Research Office.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-16 22:59:35', '2026-10-13 17:11:58'),
(566, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TESTING 1\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-16 22:59:35', '2026-10-13 17:12:37'),
(567, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0056: TESTING 1.', 'research', 56, '/asu-rems/research/view.php?id=56', 1, '2026-11-12 11:39:36', '2026-10-13 17:23:28'),
(568, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0056: TESTING 1.', 'research', 56, '/asu-rems/research/view.php?id=56', 0, NULL, '2026-10-13 17:23:28'),
(569, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0057: HATDOG', 'research', 57, '/asu-rems/research/view.php?id=57', 1, '2026-11-12 11:39:36', '2026-10-13 17:48:01'),
(570, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0057: HATDOG', 'research', 57, '/asu-rems/research/view.php?id=57', 0, NULL, '2026-10-13 17:48:01'),
(571, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0057.', 'research', 57, '/asu-rems/research/view.php?id=57', 1, '2026-11-12 11:39:36', '2026-10-13 17:48:06'),
(572, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0057.', 'research', 57, '/asu-rems/research/view.php?id=57', 0, NULL, '2026-10-13 17:48:06'),
(573, 6, 'Research forwarded to URRC Review', 'Your research \"HATDOG\" has passed initial RPS intake and is now for URRC Review.', 'research', 57, '/asu-rems/research/view.php?id=57', 1, '2026-11-16 22:59:35', '2026-10-13 18:00:08'),
(574, 6, 'URRC revision required', 'Your research \"HATDOG\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: 1234', 'research', 57, '/asu-rems/research/view.php?id=57', 1, '2026-10-14 02:36:09', '2026-10-13 18:02:41'),
(575, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0058: TESTING', 'research', 58, '/asu-rems/research/view.php?id=58', 1, '2026-11-12 11:39:36', '2026-10-13 18:47:48'),
(576, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0058: TESTING', 'research', 58, '/asu-rems/research/view.php?id=58', 0, NULL, '2026-10-13 18:47:48'),
(577, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0058.', 'research', 58, '/asu-rems/research/view.php?id=58', 1, '2026-11-12 11:39:36', '2026-10-13 18:48:24'),
(578, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0058.', 'research', 58, '/asu-rems/research/view.php?id=58', 0, NULL, '2026-10-13 18:48:24'),
(579, 6, 'Research forwarded to URRC Review', 'Your research \"TESTING\" has passed initial RPS intake and is now for URRC Review.', 'research', 58, '/asu-rems/research/view.php?id=58', 1, '2026-11-16 22:59:35', '2026-10-13 18:48:39'),
(580, 6, 'URRC revision required', 'Your research \"TESTING\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: tudwkhjlwd', 'research', 58, '/asu-rems/research/view.php?id=58', 1, '2026-11-16 22:59:35', '2026-10-13 18:52:06'),
(581, 6, 'URRC revision deadline expired', 'Your research \"TESTING\" was archived because the URRC revision was not resubmitted within 2 minutes (TEST ONLY). You may create a new proposal.', 'research', 58, '/asu-rems/research/view.php?id=58', 1, '2026-11-16 22:59:35', '2026-10-13 18:54:19'),
(582, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0059: TESTING 2', 'research', 59, '/asu-rems/research/view.php?id=59', 1, '2026-11-12 11:39:36', '2026-10-13 19:08:31'),
(583, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0059: TESTING 2', 'research', 59, '/asu-rems/research/view.php?id=59', 0, NULL, '2026-10-13 19:08:31'),
(584, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0059.', 'research', 59, '/asu-rems/research/view.php?id=59', 1, '2026-11-12 11:39:36', '2026-10-13 19:08:37'),
(585, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0059.', 'research', 59, '/asu-rems/research/view.php?id=59', 0, NULL, '2026-10-13 19:08:37'),
(586, 6, 'Research forwarded to URRC Review', 'Your research \"TESTING 2\" has passed initial RPS intake and is now for URRC Review.', 'research', 59, '/asu-rems/research/view.php?id=59', 1, '2026-11-16 22:59:35', '2026-10-13 19:09:08'),
(587, 6, 'URRC revision required', 'Your research \"TESTING 2\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: UIKUUOU', 'research', 59, '/asu-rems/research/view.php?id=59', 1, '2026-11-16 22:59:35', '2026-10-13 19:09:16'),
(588, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0060: TESTING 3 URRC', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 19:16:56'),
(589, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0060: TESTING 3 URRC', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 19:16:56'),
(590, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0060.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 19:16:59'),
(591, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0060.', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 19:16:59'),
(592, 6, 'Research forwarded to URRC Review', 'Your research \"TESTING 3 URRC\" has passed initial RPS intake and is now for URRC Review.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:32:17'),
(593, 6, 'URRC review accepted', 'Your research \"TESTING 3 URRC\" was accepted in URRC review and is now for In-House review.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:32:22'),
(594, 6, 'In-House revision required', 'Your research \"TESTING 3 URRC\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: KDJLDLA', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:32:30'),
(595, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0060: TESTING 3 URRC', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 19:51:40'),
(596, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0060: TESTING 3 URRC', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 19:51:40'),
(597, 6, 'In-House review passed', 'Your research \"TESTING 3 URRC\" passed In-House review and is now for UREC/IACUC processing.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:52:00'),
(598, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TESTING 3 URRC\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:52:08'),
(599, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"TESTING 3 URRC\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 19:52:12'),
(600, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TESTING 3 URRC.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 20:15:52'),
(601, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TESTING 3 URRC.', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 20:15:52'),
(602, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"TESTING 3 URRC\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 20:16:44'),
(603, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TESTING 3 URRC.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 20:17:04'),
(604, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TESTING 3 URRC.', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 20:17:04'),
(605, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"TESTING 3 URRC\". You may view and print each permit separately from the research details page.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-16 22:59:35', '2026-10-13 20:25:39'),
(606, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TESTING 3 URRC. The research record is now completed.', 'research', 60, '/asu-rems/research/view.php?id=60', 1, '2026-11-12 11:39:36', '2026-10-13 20:33:47'),
(607, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TESTING 3 URRC. The research record is now completed.', 'research', 60, '/asu-rems/research/view.php?id=60', 0, NULL, '2026-10-13 20:33:47'),
(608, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0061: ASU REMS', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-12 11:39:36', '2026-10-22 03:32:11'),
(609, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0061: ASU REMS', 'research', 61, '/asu-rems/research/view.php?id=61', 0, NULL, '2026-10-22 03:32:11'),
(610, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0061.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-12 11:39:36', '2026-10-22 03:36:03'),
(611, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0061.', 'research', 61, '/asu-rems/research/view.php?id=61', 0, NULL, '2026-10-22 03:36:03'),
(612, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0061.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-12 11:39:36', '2026-10-22 03:36:39'),
(613, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0061.', 'research', 61, '/asu-rems/research/view.php?id=61', 0, NULL, '2026-10-22 03:36:39'),
(614, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0061. Both required UPE certificates are complete.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-12 11:39:36', '2026-10-22 03:36:46'),
(615, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0061. Both required UPE certificates are complete.', 'research', 61, '/asu-rems/research/view.php?id=61', 0, NULL, '2026-10-22 03:36:46'),
(616, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"ASU REMS\" is now under UREC/IACUC processing by the Research Office.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-16 22:59:35', '2026-10-22 03:43:02'),
(617, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASU REMS\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-16 22:59:35', '2026-10-22 03:45:12'),
(618, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0061: ASU REMS.', 'research', 61, '/asu-rems/research/view.php?id=61', 1, '2026-11-12 11:39:36', '2026-10-22 03:47:58'),
(619, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0061: ASU REMS.', 'research', 61, '/asu-rems/research/view.php?id=61', 0, NULL, '2026-10-22 03:47:58'),
(620, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-12 11:39:36', '2026-10-29 03:48:58'),
(621, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:48:58'),
(622, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0062.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-12 11:39:36', '2026-10-29 03:49:14'),
(623, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0062.', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:49:14'),
(624, 6, 'Research forwarded to URRC Review', 'Your research \"ASU REMS\" has passed initial RPS intake and is now for URRC Review.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:49:52'),
(625, 6, 'URRC revision required', 'Your research \"ASU REMS\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: AMBOBO MO, KAMUKHA NG GAWA MO', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-10-29 11:53:30', '2026-10-29 03:50:27'),
(626, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-10-29 11:52:38', '2026-10-29 03:51:41'),
(627, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:51:41'),
(628, 6, 'URRC revision required', 'Your research \"ASU REMS\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: AMBOBO MO, KAMUKHA NG GAWA MO', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-10-29 11:53:17', '2026-10-29 03:51:49'),
(629, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-12 11:39:36', '2026-10-29 03:52:58'),
(630, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0062: ASU REMS', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:52:58'),
(631, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0063: ASU REMS', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-12 11:39:36', '2026-10-29 03:55:16'),
(632, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0063: ASU REMS', 'research', 63, '/asu-rems/research/view.php?id=63', 0, NULL, '2026-10-29 03:55:16'),
(633, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0063.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-12 11:39:36', '2026-10-29 03:55:21'),
(634, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0063.', 'research', 63, '/asu-rems/research/view.php?id=63', 0, NULL, '2026-10-29 03:55:21'),
(635, 6, 'URRC review accepted', 'Your research \"ASU REMS\" was accepted in URRC review and is now for In-House review. Remarks: GOOD JOB', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:55:40'),
(636, 6, 'In-House review passed', 'Your research \"ASU REMS\" passed In-House review and is now for UREC/IACUC processing. Remarks: YOURE SO BEAUTIFUL LIKE U', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:56:10'),
(637, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASU REMS\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:56:26'),
(638, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ASU REMS\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:56:31'),
(639, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASU REMS.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-12 11:39:36', '2026-10-29 03:57:26'),
(640, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASU REMS.', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:57:26'),
(641, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"ASU REMS\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-16 22:59:35', '2026-10-29 03:58:17'),
(642, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASU REMS.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-11-12 11:39:36', '2026-10-29 03:58:55'),
(643, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASU REMS.', 'research', 62, '/asu-rems/research/view.php?id=62', 0, NULL, '2026-10-29 03:58:55'),
(644, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"ASU REMS\". You may view and print each permit separately from the research details page.', 'research', 62, '/asu-rems/research/view.php?id=62', 1, '2026-10-29 12:14:18', '2026-10-29 03:59:57'),
(645, 6, 'Research forwarded to URRC Review', 'Your research \"ASU REMS\" has passed initial RPS intake and is now for URRC Review.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-16 22:59:35', '2026-10-29 04:15:10'),
(646, 6, 'URRC review accepted', 'Your research \"ASU REMS\" was accepted in URRC review and is now for In-House review.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-16 22:59:35', '2026-10-29 04:15:16'),
(647, 6, 'In-House review passed', 'Your research \"ASU REMS\" passed In-House review and is now for UREC/IACUC processing.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-16 22:59:35', '2026-10-29 04:15:21'),
(648, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASU REMS\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-16 22:59:35', '2026-10-29 04:15:26'),
(649, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ASU REMS\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 63, '/asu-rems/research/view.php?id=63', 1, '2026-11-16 22:59:35', '2026-10-29 04:15:39'),
(650, 6, 'URRC revision deadline expired', 'Your research \"HATDOG\" was archived because the URRC revision was not resubmitted within 21 days. You may create a new proposal.', 'research', 57, '/asu-rems/research/view.php?id=57', 1, '2026-11-16 22:59:35', '2026-11-05 15:16:33'),
(651, 6, 'URRC revision deadline expired', 'Your research \"TESTING 2\" was archived because the URRC revision was not resubmitted within 21 days. You may create a new proposal.', 'research', 59, '/asu-rems/research/view.php?id=59', 1, '2026-11-16 22:59:35', '2026-11-05 15:16:33'),
(652, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0064: ASUREMS14', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-12 11:39:36', '2026-11-05 16:22:31'),
(653, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0064: ASUREMS14', 'research', 64, '/asu-rems/research/view.php?id=64', 0, NULL, '2026-11-05 16:22:31'),
(654, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0064.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-12 11:39:36', '2026-11-05 16:24:01'),
(655, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0064.', 'research', 64, '/asu-rems/research/view.php?id=64', 0, NULL, '2026-11-05 16:24:01'),
(656, 6, 'Research forwarded to URRC Review', 'Your research \"ASUREMS14\" has passed initial RPS intake and is now for URRC Review.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 16:25:00'),
(657, 6, 'URRC review accepted', 'Your research \"ASUREMS14\" was accepted in URRC review and is now for In-House review. Remarks: riririririr', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 16:42:27'),
(658, 6, 'In-House review passed', 'Your research \"ASUREMS14\" passed In-House review and is now for UREC/IACUC processing.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 16:45:33'),
(659, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASUREMS14\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 16:48:41'),
(660, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ASUREMS14\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 16:48:45'),
(661, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASUREMS14.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-12 11:39:36', '2026-11-05 17:35:44'),
(662, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASUREMS14.', 'research', 64, '/asu-rems/research/view.php?id=64', 0, NULL, '2026-11-05 17:35:44'),
(663, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"ASUREMS14\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 17:36:56'),
(664, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASUREMS14.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-12 11:39:36', '2026-11-05 17:37:37'),
(665, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASUREMS14.', 'research', 64, '/asu-rems/research/view.php?id=64', 0, NULL, '2026-11-05 17:37:37'),
(666, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"ASUREMS14\". You may view and print each permit separately from the research details page.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-16 22:59:35', '2026-11-05 17:51:15'),
(667, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ASUREMS14. The research record is now completed.', 'research', 64, '/asu-rems/research/view.php?id=64', 1, '2026-11-12 11:39:36', '2026-11-05 18:39:47'),
(668, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ASUREMS14. The research record is now completed.', 'research', 64, '/asu-rems/research/view.php?id=64', 0, NULL, '2026-11-05 18:39:47'),
(669, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0065: ASUREMS', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-12 11:39:36', '2026-11-06 12:41:27');
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `module`, `record_id`, `action_url`, `is_read`, `read_at`, `created_at`) VALUES
(670, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0065: ASUREMS', 'research', 65, '/asu-rems/research/view.php?id=65', 0, NULL, '2026-11-06 12:41:27'),
(671, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0065.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-12 11:39:36', '2026-11-06 12:41:52'),
(672, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0065.', 'research', 65, '/asu-rems/research/view.php?id=65', 0, NULL, '2026-11-06 12:41:52'),
(673, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0065.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-12 11:39:36', '2026-11-06 12:42:01'),
(674, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0065.', 'research', 65, '/asu-rems/research/view.php?id=65', 0, NULL, '2026-11-06 12:42:01'),
(675, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0065. Both required UPE certificates are complete.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-12 11:39:36', '2026-11-06 12:42:09'),
(676, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0065. Both required UPE certificates are complete.', 'research', 65, '/asu-rems/research/view.php?id=65', 0, NULL, '2026-11-06 12:42:09'),
(677, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"ASUREMS\" is now under UREC/IACUC processing by the Research Office.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-16 22:59:35', '2026-11-06 13:07:41'),
(678, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASUREMS\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-16 22:59:35', '2026-11-06 13:08:11'),
(679, 7, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0065: ASUREMS.', 'research', 65, '/asu-rems/research/view.php?id=65', 1, '2026-11-12 11:39:36', '2026-11-06 13:28:34'),
(680, 9, 'Self-initiated completed research uploaded', 'Larra Jane D. Casidsid uploaded the completed research / whole manuscript for ASU-RES-2026-0065: ASUREMS.', 'research', 65, '/asu-rems/research/view.php?id=65', 0, NULL, '2026-11-06 13:28:34'),
(681, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 14:22:50'),
(682, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 14:22:50'),
(683, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0066.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 14:22:53'),
(684, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0066.', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 14:22:53'),
(685, 6, 'Research forwarded to URRC Review', 'Your research \"ASUASURONE\" has passed initial RPS intake and is now for URRC Review.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 14:23:12'),
(686, 6, 'URRC revision required', 'Your research \"ASUASURONE\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: REVISE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 14:23:54'),
(687, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 15:25:11'),
(688, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 15:25:11'),
(689, 6, 'URRC review accepted', 'Your research \"ASUASURONE\" was accepted in URRC review and is now for In-House review.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:25:42'),
(690, 6, 'In-House revision required', 'Your research \"ASUASURONE\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: qwerty', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:26:06'),
(691, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 15:26:25'),
(692, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 15:26:25'),
(693, 6, 'In-House revision required', 'Your research \"ASUASURONE\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: gajhuswhxikawjncokqjdiwsjcik', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:27:02'),
(694, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 15:27:13'),
(695, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 15:27:13'),
(696, 6, 'In-House revision required', 'Your research \"ASUASURONE\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: qwertyuiokjnbg', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:29:36'),
(697, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 15:33:15'),
(698, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 15:33:15'),
(699, 6, 'In-House revision required', 'Your research \"ASUASURONE\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: qwertyio', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:33:36'),
(700, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 15:34:36'),
(701, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0066: ASUASURONE', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 15:34:36'),
(702, 6, 'In-House review passed', 'Your research \"ASUASURONE\" passed In-House review and is now for UREC/IACUC processing. Remarks: mvkxm', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:34:52'),
(703, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASUASURONE\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:35:07'),
(704, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ASUASURONE\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 15:35:16'),
(705, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASUASURONE.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 16:13:08'),
(706, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ASUASURONE.', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 16:13:08'),
(707, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"ASUASURONE\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 16:13:44'),
(708, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASUASURONE.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 16:14:03'),
(709, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ASUASURONE.', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 16:14:03'),
(710, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"ASUASURONE\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-16 22:59:35', '2026-11-06 16:14:27'),
(711, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ASUASURONE. The research record is now completed.', 'research', 66, '/asu-rems/research/view.php?id=66', 1, '2026-11-12 11:39:36', '2026-11-06 16:17:01'),
(712, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ASUASURONE. The research record is now completed.', 'research', 66, '/asu-rems/research/view.php?id=66', 0, NULL, '2026-11-06 16:17:01'),
(713, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0067: ASU', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-12 11:39:36', '2026-11-08 13:31:37'),
(714, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0067: ASU', 'research', 67, '/asu-rems/research/view.php?id=67', 0, NULL, '2026-11-08 13:31:37'),
(715, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0067.', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-12 11:39:36', '2026-11-08 13:31:58'),
(716, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0067.', 'research', 67, '/asu-rems/research/view.php?id=67', 0, NULL, '2026-11-08 13:31:58'),
(717, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0067.', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-12 11:39:36', '2026-11-08 13:32:13'),
(718, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0067.', 'research', 67, '/asu-rems/research/view.php?id=67', 0, NULL, '2026-11-08 13:32:13'),
(719, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0067. Both required UPE certificates are complete.', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-12 11:39:36', '2026-11-08 13:32:22'),
(720, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0067. Both required UPE certificates are complete.', 'research', 67, '/asu-rems/research/view.php?id=67', 0, NULL, '2026-11-08 13:32:22'),
(721, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: asurems', 'extension', 4, '/asu-rems/extension/view.php?id=4', 1, '2026-11-10 15:40:39', '2026-11-10 07:28:40'),
(722, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: asu', 'extension', 5, '/asu-rems/extension/view.php?id=5', 1, '2026-11-10 15:43:42', '2026-11-10 07:43:22'),
(723, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: ASU REMS', 'extension', 6, '/asu-rems/extension/view.php?id=6', 1, '2026-11-11 10:39:17', '2026-11-11 02:06:31'),
(724, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for ASU REMS is now available in your Extension record.', 'extension', 6, '/asu-rems/extension/view.php?id=6', 1, '2026-11-11 11:58:16', '2026-11-11 03:56:55'),
(725, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: ASUREMS', 'extension', 7, '/asu-rems/extension/view.php?id=7', 1, '2026-11-12 12:45:32', '2026-11-12 04:45:05'),
(726, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: ertyuio', 'extension', 8, '/asu-rems/extension/view.php?id=8', 1, '2026-11-17 14:52:15', '2026-11-12 04:58:42'),
(727, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for ASUREMS is now available in your Extension record.', 'extension', 7, '/asu-rems/extension/view.php?id=7', 1, '2026-11-17 14:38:52', '2026-11-12 05:58:30'),
(728, 6, 'Research moved to UREC/IACUC processing', 'Your research proposal \"ASU\" is now under UREC/IACUC processing by the Research Office.', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-16 22:59:35', '2026-11-13 14:39:11'),
(729, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ASU\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 67, '/asu-rems/research/view.php?id=67', 1, '2026-11-16 22:59:35', '2026-11-13 15:00:31'),
(730, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: asurems', 'extension', 9, '/asu-rems/extension/view.php?id=9', 0, NULL, '2026-11-18 01:54:32'),
(731, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for asurems is now available in your Extension record.', 'extension', 9, '/asu-rems/extension/view.php?id=9', 0, NULL, '2026-11-18 02:08:22'),
(732, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: TEST 1', 'extension', 10, '/asu-rems/extension/view.php?id=10', 0, NULL, '2026-11-18 05:30:08'),
(733, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for TEST 1 is now available in your Extension record.', 'extension', 10, '/asu-rems/extension/view.php?id=10', 1, '2026-11-18 14:11:27', '2026-11-18 06:11:14'),
(734, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 22:28:23', '2026-11-19 13:54:30'),
(735, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 13:54:30'),
(736, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0068.', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 21:58:42', '2026-11-19 13:54:43'),
(737, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0068.', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 13:54:43'),
(738, 6, 'Research forwarded to URRC Review', 'Your research \"bugre\" has passed initial RPS intake and is now for URRC Review.', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 13:55:16'),
(739, 6, 'URRC revision required', 'Your research \"bugre\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 13:55:32'),
(740, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 21:56:47', '2026-11-19 13:56:04'),
(741, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 13:56:04'),
(742, 6, 'URRC revision required', 'Your research \"bugre\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 22:13:34', '2026-11-19 13:56:10'),
(743, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 22:11:14', '2026-11-19 14:11:05'),
(744, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:11:05'),
(745, 6, 'URRC review accepted', 'Your research \"bugre\" was accepted in URRC review and is now for In-House review. Remarks: QWERTY', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:17:16'),
(746, 6, 'In-House revision required', 'Your research \"bugre\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: QWER', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:17:23'),
(747, 7, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 22:18:58', '2026-11-19 14:18:30'),
(748, 9, 'Revised research proposal resubmitted', 'Larra Jane D. Casidsid resubmitted revised research proposal ASU-RES-2026-0068: bugre', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:18:30'),
(749, 6, 'In-House revision required', 'Your research \"bugre\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: QWER', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:18:36'),
(750, 7, 'Revised In-House manuscript submitted', 'Larra Jane D. Casidsid submitted a revised In-House manuscript for bugre. RPS may now verify it and proceed to UREC/IACUC Review.', 'research', 68, '/asu-rems/research/view.php?id=68', 1, '2026-11-19 22:37:21', '2026-11-19 14:28:55'),
(751, 9, 'Revised In-House manuscript submitted', 'Larra Jane D. Casidsid submitted a revised In-House manuscript for bugre. RPS may now verify it and proceed to UREC/IACUC Review.', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 14:28:55'),
(752, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0069: qwerty', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:05'),
(753, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0069: qwerty', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:05'),
(754, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0069.', 'research', 69, '/asu-rems/research/view.php?id=69', 1, '2026-11-19 22:31:16', '2026-11-19 14:31:07'),
(755, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0069.', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:07'),
(756, 6, 'Research forwarded to URRC Review', 'Your research \"qwerty\" has passed initial RPS intake and is now for URRC Review.', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:19'),
(757, 6, 'URRC review accepted', 'Your research \"qwerty\" was accepted in URRC review and is now for In-House review. Remarks: QWERT', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:28'),
(758, 6, 'In-House revision required', 'Your research \"qwerty\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: QWERTY', 'research', 69, '/asu-rems/research/view.php?id=69', 0, NULL, '2026-11-19 14:31:35'),
(759, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0070: ULIT', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:27'),
(760, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0070: ULIT', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:27'),
(761, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0070.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:29'),
(762, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0070.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:29'),
(763, 6, 'Research forwarded to URRC Review', 'Your research \"ULIT\" has passed initial RPS intake and is now for URRC Review.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:39'),
(764, 6, 'URRC revision required', 'Your research \"ULIT\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: QWERTY', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:45:45'),
(765, 7, 'Revised URRC manuscript submitted', 'Larra Jane D. Casidsid submitted a revised URRC manuscript for ULIT. RPS may now verify it and proceed to In-House Review.', 'research', 70, '/asu-rems/research/view.php?id=70', 1, '2026-11-19 22:47:00', '2026-11-19 14:46:43'),
(766, 9, 'Revised URRC manuscript submitted', 'Larra Jane D. Casidsid submitted a revised URRC manuscript for ULIT. RPS may now verify it and proceed to In-House Review.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:46:43'),
(767, 6, 'URRC revision required', 'Your research \"ULIT\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: QWERTY', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:46:53'),
(768, 7, 'Revised URRC manuscript submitted', 'Larra Jane D. Casidsid submitted a revised URRC manuscript for ULIT. RPS may now verify it and proceed to In-House Review.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:47:36'),
(769, 9, 'Revised URRC manuscript submitted', 'Larra Jane D. Casidsid submitted a revised URRC manuscript for ULIT. RPS may now verify it and proceed to In-House Review.', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:47:36'),
(770, 6, 'URRC review accepted', 'Your research \"ULIT\" was accepted in URRC review and is now for In-House review. Remarks: qwerty', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:47:48'),
(771, 6, 'In-House revision required', 'Your research \"ULIT\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: qwerty', 'research', 70, '/asu-rems/research/view.php?id=70', 0, NULL, '2026-11-19 14:47:56'),
(772, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0071: qwertyuio', 'research', 71, '/asu-rems/research/view.php?id=71', 1, '2026-11-19 23:18:57', '2026-11-19 14:58:02'),
(773, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0071: qwertyuio', 'research', 71, '/asu-rems/research/view.php?id=71', 0, NULL, '2026-11-19 14:58:02'),
(774, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0071.', 'research', 71, '/asu-rems/research/view.php?id=71', 0, NULL, '2026-11-19 14:58:05'),
(775, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0071.', 'research', 71, '/asu-rems/research/view.php?id=71', 0, NULL, '2026-11-19 14:58:05'),
(776, 6, 'Research forwarded to URRC Review', 'Your research \"qwertyuio\" has passed initial RPS intake and is now for URRC Review.', 'research', 71, '/asu-rems/research/view.php?id=71', 0, NULL, '2026-11-19 14:58:29'),
(777, 6, 'URRC revision required', 'Your research \"qwertyuio\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: qwertyu', 'research', 71, '/asu-rems/research/view.php?id=71', 0, NULL, '2026-11-19 14:58:37'),
(778, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0072: TEST 1', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:20:51'),
(779, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0072: TEST 1', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:20:51'),
(780, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0072.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:20:53'),
(781, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0072.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:20:53'),
(782, 6, 'Research forwarded to URRC Review', 'Your research \"TEST 1\" has passed initial RPS intake and is now for URRC Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:21:11'),
(783, 6, 'URRC revision required', 'Your research \"TEST 1\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: qwertyu', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:21:19'),
(784, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 1. RPS may now verify it for In-House Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 1, '2026-11-19 23:47:22', '2026-11-19 15:44:54'),
(785, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 1. RPS may now verify it for In-House Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:44:54'),
(786, 6, 'In-House review passed', 'Your research \"bugre\" passed In-House Review and is now for UREC/IACUC processing. Remarks: gew', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 15:45:06'),
(787, 6, 'URRC review accepted', 'Your research \"TEST 1\" was accepted in URRC review and is now for In-House review. Remarks: swd', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:45:43'),
(788, 6, 'In-House revision required', 'Your research \"TEST 1\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: fed', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:45:51'),
(789, 7, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 1. RPS may verify it for UREC/IACUC Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 1, '2026-11-19 23:46:51', '2026-11-19 15:46:27'),
(790, 9, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 1. RPS may verify it for UREC/IACUC Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:46:27'),
(791, 6, 'In-House revision required', 'Your research \"TEST 1\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: fed', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:46:33'),
(792, 7, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 1. RPS may verify it for UREC/IACUC Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:56:24'),
(793, 9, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 1. RPS may verify it for UREC/IACUC Review.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:56:24'),
(794, 6, 'In-House review passed', 'Your research \"TEST 1\" passed In-House Review and is now for UREC/IACUC processing.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 15:56:34'),
(795, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TEST 1\" has been completed with classification: Full-board. Your system-generated UREC/IACUC certificate is now available.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:00:23'),
(796, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"TEST 1\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:00:25'),
(797, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 1.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:18:25'),
(798, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 1.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:18:25'),
(799, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"TEST 1\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:18:51'),
(800, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 1.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:19:05'),
(801, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 1.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:19:05'),
(802, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"TEST 1\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:19:23'),
(803, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 1. The research record is now completed.', 'research', 72, '/asu-rems/research/view.php?id=72', 1, '2026-11-20 00:33:49', '2026-11-19 16:19:41'),
(804, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 1. The research record is now completed.', 'research', 72, '/asu-rems/research/view.php?id=72', 0, NULL, '2026-11-19 16:19:41'),
(805, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0073: TEST 2', 'research', 73, '/asu-rems/research/view.php?id=73', 1, '2026-11-20 00:24:12', '2026-11-19 16:21:49'),
(806, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0073: TEST 2', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:21:49'),
(807, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0073.', 'research', 73, '/asu-rems/research/view.php?id=73', 1, '2026-11-20 00:22:05', '2026-11-19 16:21:54'),
(808, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0073.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:21:54'),
(809, 6, 'Research forwarded to URRC Review', 'Your research \"TEST 2\" has passed initial RPS intake and is now for URRC Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:22:10'),
(810, 6, 'URRC revision required', 'Your research \"TEST 2\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: hjgj', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:22:27'),
(811, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 2. RPS may now verify it for In-House Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 1, '2026-11-20 00:23:26', '2026-11-19 16:23:06'),
(812, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 2. RPS may now verify it for In-House Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:23:06'),
(813, 6, 'URRC revision required', 'Your research \"TEST 2\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: hjgj', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:23:12'),
(814, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 2. RPS may now verify it for In-House Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:34:29'),
(815, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 2. RPS may now verify it for In-House Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:34:29'),
(816, 6, 'URRC review accepted', 'Your research \"TEST 2\" was accepted in URRC review and is now for In-House review. Remarks: hhhh', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:34:49'),
(817, 6, 'In-House revision required', 'Your research \"TEST 2\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: hhh', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:34:56'),
(818, 7, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 2. RPS may verify it for UREC/IACUC Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:35:14'),
(819, 9, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 2. RPS may verify it for UREC/IACUC Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:35:14'),
(820, 6, 'In-House revision required', 'Your research \"TEST 2\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: hhh', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:35:20'),
(821, 7, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 2. RPS may verify it for UREC/IACUC Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:42:32'),
(822, 9, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 2. RPS may verify it for UREC/IACUC Review.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:42:32'),
(823, 6, 'In-House review passed', 'Your research \"TEST 2\" passed In-House Review and is now for UREC/IACUC processing. Remarks: shhsshsh', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:43:09'),
(824, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TEST 2\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:43:16'),
(825, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"TEST 2\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:43:19'),
(826, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 2.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:43:50'),
(827, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 2.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:43:50'),
(828, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"TEST 2\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:00'),
(829, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 2.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:13'),
(830, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 2.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:13'),
(831, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"TEST 2\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:25'),
(832, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 2. The research record is now completed.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:40'),
(833, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 2. The research record is now completed.', 'research', 73, '/asu-rems/research/view.php?id=73', 0, NULL, '2026-11-19 16:44:40'),
(834, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0074: TEST 3', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:46:14'),
(835, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0074: TEST 3', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:46:14'),
(836, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0074.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:46:17'),
(837, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0074.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:46:17'),
(838, 6, 'Research forwarded to URRC Review', 'Your research \"TEST 3\" has passed initial RPS intake and is now for URRC Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:46:31'),
(839, 6, 'URRC revision required', 'Your research \"TEST 3\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:47:03'),
(840, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:47:30'),
(841, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:47:30'),
(842, 6, 'URRC revision required', 'Your research \"TEST 3\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:47:36'),
(843, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:48:25'),
(844, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:48:25'),
(845, 6, 'URRC revision required', 'Your research \"TEST 3\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:48:32'),
(846, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:59:29'),
(847, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:59:29'),
(848, 6, 'URRC revision required', 'Your research \"TEST 3\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 16:59:36'),
(849, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"bugre\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 17:00:14'),
(850, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"bugre\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 68, '/asu-rems/research/view.php?id=68', 0, NULL, '2026-11-19 17:00:17'),
(851, 7, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:00:49'),
(852, 9, 'URRC revision submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for TEST 3. RPS may now verify it for In-House Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:00:49'),
(853, 6, 'URRC review accepted', 'Your research \"TEST 3\" was accepted in URRC review and is now for In-House review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:01:11'),
(854, 6, 'In-House revision required', 'Your research \"TEST 3\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:01:23'),
(855, 7, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 3. RPS may verify it for UREC/IACUC Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:01:46'),
(856, 9, 'In-House revision submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for TEST 3. RPS may verify it for UREC/IACUC Review.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:01:46'),
(857, 6, 'In-House revision required', 'Your research \"TEST 3\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:01:52'),
(858, 7, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:11:04'),
(859, 9, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:11:04'),
(860, 6, 'In-House revision required', 'Your research \"TEST 3\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:11:10'),
(861, 7, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 1, '2026-11-20 01:16:50', '2026-11-19 17:16:31'),
(862, 9, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:16:31'),
(863, 6, 'In-House revision required', 'Your research \"TEST 3\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: HHH', 'research', 74, '/asu-rems/research/view.php?id=74', 1, '2026-11-20 01:19:00', '2026-11-19 17:16:39'),
(864, 7, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:27:51'),
(865, 9, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"TEST 3\". RPS may now verify it.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:27:51'),
(866, 6, 'In-House review passed', 'Your research \"TEST 3\" passed In-House Review and is now for UREC/IACUC processing. Remarks: dddddd', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:28:10'),
(867, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TEST 3\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:28:16'),
(868, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"TEST 3\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:28:36'),
(869, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 3.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:03'),
(870, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for TEST 3.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:03'),
(871, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"TEST 3\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:13'),
(872, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 3.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:23'),
(873, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for TEST 3.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:23'),
(874, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"TEST 3\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:34'),
(875, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 3. The research record is now completed.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:47'),
(876, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for TEST 3. The research record is now completed.', 'research', 74, '/asu-rems/research/view.php?id=74', 0, NULL, '2026-11-19 17:29:47'),
(877, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0075: test 4', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:30:55'),
(878, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0075: test 4', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:30:55');
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `module`, `record_id`, `action_url`, `is_read`, `read_at`, `created_at`) VALUES
(879, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0075.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:30:59'),
(880, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0075.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:30:59'),
(881, 6, 'Research forwarded to URRC Review', 'Your research \"test 4\" has passed initial RPS intake and is now for URRC Review.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:31:12'),
(882, 6, 'URRC revision required', 'Your research \"test 4\" was accepted with revision in URRC review. Please upload the revised manuscript. Remarks: ffffff', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:31:18'),
(883, 7, 'URRC revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for \"test 4\". RPS may now verify it.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:31:49'),
(884, 9, 'URRC revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised URRC manuscript for \"test 4\". RPS may now verify it.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:31:49'),
(885, 6, 'URRC review accepted', 'Your research \"test 4\" was accepted in URRC review and is now for In-House review. Remarks: ccc', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:00'),
(886, 6, 'In-House revision required', 'Your research \"test 4\" passed with revision in In-House review. Please upload the revised manuscript. Remarks: fff', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:04'),
(887, 7, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"test 4\". RPS may now verify it.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:23'),
(888, 9, 'In-House revised manuscript submitted', 'Larra Jane D. Casidsid submitted the revised In-House manuscript for \"test 4\". RPS may now verify it.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:23'),
(889, 6, 'In-House review passed', 'Your research \"test 4\" passed In-House Review and is now for UREC/IACUC processing. Remarks: fff', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:30'),
(890, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"test 4\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 75, '/asu-rems/research/view.php?id=75', 0, NULL, '2026-11-19 17:32:35'),
(891, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0076: test 5', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:27'),
(892, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0076: test 5', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:27'),
(893, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0076.', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:31'),
(894, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0076.', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:31'),
(895, 6, 'Research forwarded to URRC Review', 'Your research \"test 5\" has passed initial RPS intake and is now for URRC Review.', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:47'),
(896, 6, 'Research rejected in URRC review', 'Your research \"test 5\" was rejected in URRC review and has been archived. Remarks: sss', 'research', 76, '/asu-rems/research/view.php?id=76', 0, NULL, '2026-11-19 17:33:59'),
(897, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0077: test 6', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:43:16'),
(898, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0077: test 6', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:43:16'),
(899, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0077.', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:43:25'),
(900, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0077.', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:43:25'),
(901, 6, 'Research forwarded to URRC Review', 'Your research \"test 6\" has passed initial RPS intake and is now for URRC Review.', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:43:53'),
(902, 6, 'URRC review accepted', 'Your research \"test 6\" was accepted in URRC review and is now for In-House review.', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:44:07'),
(903, 6, 'Research failed In-House review', 'Your research \"test 6\" failed the In-House review and has been archived. Remarks: hjhfjsdhfjsdh', 'research', 77, '/asu-rems/research/view.php?id=77', 0, NULL, '2026-11-19 17:44:24'),
(904, 13, 'New extension program submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: asuremsdwsd', 'extension', 11, '/asu-rems/extension/view.php?id=11', 0, NULL, '2026-11-20 01:48:06'),
(905, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for asuremsdwsd is now available in your Extension record.', 'extension', 11, '/asu-rems/extension/view.php?id=11', 0, NULL, '2026-11-20 02:31:45'),
(906, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0078: asurems', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:28:46'),
(907, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0078: asurems', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:28:46'),
(908, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0078.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:30:23'),
(909, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0078.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:30:23'),
(910, 6, 'Research forwarded to URRC Review', 'Your research \"asurems\" has passed initial RPS intake and is now for URRC Review.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:30:38'),
(911, 6, 'URRC review accepted', 'Your research \"asurems\" was accepted in URRC review and is now for In-House review. Remarks: good job!', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:32:02'),
(912, 6, 'In-House review passed', 'Your research \"asurems\" passed In-House Review and is now for UREC/IACUC processing. Remarks: nice oone', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:32:40'),
(913, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"asurems\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:32:56'),
(914, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"asurems\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:33:04'),
(915, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for asurems.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:38:19'),
(916, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for asurems.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:38:19'),
(917, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"asurems\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:38:51'),
(918, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for asurems.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:39:21'),
(919, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for asurems.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:39:21'),
(920, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"asurems\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:40:08'),
(921, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for asurems. The research record is now completed.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:40:36'),
(922, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for asurems. The research record is now completed.', 'research', 78, '/asu-rems/research/view.php?id=78', 0, NULL, '2026-11-20 07:40:36'),
(923, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0079: TEST 2', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:10'),
(924, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0079: TEST 2', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:10'),
(925, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0079.', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:14'),
(926, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0079.', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:14'),
(927, 6, 'Research forwarded to URRC Review', 'Your research \"TEST 2\" has passed initial RPS intake and is now for URRC Review.', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:35'),
(928, 6, 'URRC review accepted', 'Your research \"TEST 2\" was accepted in URRC review and is now for In-House review. Remarks: nice one', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:47'),
(929, 6, 'In-House review passed', 'Your research \"TEST 2\" passed In-House Review and is now for UREC/IACUC processing. Remarks: fgtetue', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:12:55'),
(930, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"TEST 2\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:13:02'),
(931, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"TEST 2\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 79, '/asu-rems/research/view.php?id=79', 0, NULL, '2026-11-20 08:13:06'),
(932, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0080: TEST 3', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:19:09'),
(933, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0080: TEST 3', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:19:09'),
(934, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0080.', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:19:13'),
(935, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0080.', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:19:13'),
(936, 6, 'Research forwarded to URRC Review', 'Your research \"TEST 3\" has passed initial RPS intake and is now for URRC Review.', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:20:09'),
(937, 6, 'URRC review accepted', 'Your research \"TEST 3\" was accepted in URRC review and is now for In-House review. Remarks: SGDETGSW', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:20:16'),
(938, 6, 'In-House review passed', 'Your research \"TEST 3\" passed In-House Review and is now for UREC/IACUC processing. Remarks: HDHREWRETW', 'research', 80, '/asu-rems/research/view.php?id=80', 0, NULL, '2026-11-20 08:20:21'),
(939, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0081: hulae', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:03:13'),
(940, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0081: hulae', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:03:13'),
(941, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0081.', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:03:20'),
(942, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0081.', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:03:20'),
(943, 6, 'Research forwarded to URRC Review', 'Your research \"hulae\" has passed initial RPS intake and is now for URRC Review.', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:03:35'),
(944, 6, 'URRC review accepted', 'Your research \"hulae\" was accepted in URRC review and is now for In-House review. Remarks: qwerty', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:04:17'),
(945, 6, 'In-House review passed', 'Your research \"hulae\" passed In-House Review and is now for UREC/IACUC processing. Remarks: aqws', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:04:23'),
(946, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"hulae\" has been completed with classification: Exempt. Your system-generated UREC/IACUC certificate is now available.', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:04:28'),
(947, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"hulae\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 81, '/asu-rems/research/view.php?id=81', 0, NULL, '2026-11-20 12:04:33'),
(948, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0082: ddd', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-20 12:56:34'),
(949, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0082: ddd', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-20 12:56:34'),
(950, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: hulae', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 02:15:40'),
(951, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: aaaa', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:40:32'),
(952, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:41:02'),
(953, 13, 'Extension Coordinator endorsement submitted', 'Ronelyn Mae A. Felipe endorsed the proposal: aaaa', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:42:14'),
(954, 8, 'Extension proposal review update', 'Endorsement recorded. Proposal moved to University In-House Review.', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:42:24'),
(955, 8, 'Extension proposal review update', 'In-House result saved as Accepted. Proposal moved to Review and Endorsement.', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:42:36'),
(956, 13, 'Extension Coordinator endorsement submitted', 'Ronelyn Mae A. Felipe endorsed the proposal: aaaa', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:42:59'),
(957, 8, 'Extension Notice of Approval issued', 'The official Notice of Approval for aaaa is now available in your Extension record.', 'extension', 14, '/asu-rems/extension/view.php?id=14', 0, NULL, '2026-11-21 02:45:55'),
(958, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: TEST 1', 'extension', 12, '/asu-rems/extension/view.php?id=12', 0, NULL, '2026-11-21 02:47:17'),
(959, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: TEST 111', 'extension', 15, '/asu-rems/extension/view.php?id=15', 0, NULL, '2026-11-21 02:54:34'),
(960, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 15, '/asu-rems/extension/view.php?id=15', 0, NULL, '2026-11-21 02:59:15'),
(961, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: dgrdfgdf', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:10:07'),
(962, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:10:27'),
(963, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: dgrdfgdf', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:17:02'),
(964, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 12, '/asu-rems/extension/view.php?id=12', 0, NULL, '2026-11-21 03:31:07'),
(965, 8, 'Extension proposal review update', 'Endorsement recorded. Proposal moved to University In-House Review.', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:38:01'),
(966, 8, 'Extension proposal review update', 'In-House result saved as Accepted. Proposal moved to Review and Endorsement.', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:38:49'),
(967, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: dgrdfgdf', 'extension', 16, '/asu-rems/extension/view.php?id=16', 0, NULL, '2026-11-21 03:39:23'),
(968, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 13, '/asu-rems/extension/view.php?id=13', 1, '2026-11-21 11:43:54', '2026-11-21 03:43:38'),
(969, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: hulae', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 03:44:17'),
(970, 8, 'Extension proposal review update', 'Endorsement recorded. Proposal moved to University In-House Review.', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 03:44:37'),
(971, 8, 'Extension proposal review update', 'In-House result saved as Accepted. Proposal moved to Review and Endorsement.', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 03:44:58'),
(972, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: hulae', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 03:46:21'),
(973, 8, 'Extension Notice of Approval / Certification issued', 'The official Notice of Approval for hulae is now available in your Extension record.', 'extension', 13, '/asu-rems/extension/view.php?id=13', 0, NULL, '2026-11-21 04:21:17'),
(974, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: FINAL TESTING', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:42:12'),
(975, 8, 'Extension proposal review update', 'UERC result saved as Accepted. Awaiting Coordinator endorsement for In-House Review.', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:42:42'),
(976, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: FINAL TESTING', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:50:15'),
(977, 8, 'Extension proposal review update', 'Endorsement recorded. Proposal moved to University In-House Review.', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:50:34'),
(978, 8, 'Extension proposal review update', 'In-House result saved as Accepted. Proposal moved to Review and Endorsement.', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:50:47'),
(979, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: FINAL TESTING', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:51:05'),
(980, 8, 'Extension Notice of Approval / Certification issued', 'The official Notice of Approval for FINAL TESTING is now available in your Extension record.', 'extension', 17, '/asu-rems/extension/view.php?id=17', 0, NULL, '2026-11-21 04:52:02'),
(981, 13, 'New extension program/project submitted', 'Ronelyn Mae A. Felipe submitted an extension proposal for processing: test 1', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 05:42:25'),
(982, 8, 'Extension proposal review update', 'UERC result saved as Accepted with Revision. Proposal is now For UERC Revision.', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 05:43:55'),
(983, 13, 'Revised Extension proposal endorsed', 'Ronelyn Mae A. Felipe submitted the revised proposal for UERC processing: test 1', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 05:55:24'),
(984, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: test 1', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 05:58:14'),
(985, 8, 'Extension proposal review update', 'Endorsement recorded. Proposal moved to University In-House Review.', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 06:02:11'),
(986, 8, 'Extension proposal review update', 'In-House result saved as Accepted with Revision. Proposal is now For In-House Revision.', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 06:02:36'),
(987, 13, 'Revised Extension proposal endorsed', 'Ronelyn Mae A. Felipe submitted the revised proposal for In-House processing: test 1', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 06:03:17'),
(988, 13, 'Coordinator endorsement document submitted', 'Ronelyn Mae A. Felipe submitted the endorsement document for: test 1', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 06:03:50'),
(989, 8, 'Extension Notice of Approval / Certification issued', 'The official Notice of Approval for test 1 is now available in your Extension record.', 'extension', 18, '/asu-rems/extension/view.php?id=18', 0, NULL, '2026-11-21 06:05:43'),
(990, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0082.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-21 06:20:21'),
(991, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0082.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-21 06:20:21'),
(992, 6, 'Research forwarded to URRC Review', 'Your research \"ddd\" has passed initial RPS intake and is now for URRC Review.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-21 06:20:40'),
(993, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0083: asurems', 'research', 83, '/asu-rems/research/view.php?id=83', 0, NULL, '2026-11-21 06:32:11'),
(994, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0083: asurems', 'research', 83, '/asu-rems/research/view.php?id=83', 0, NULL, '2026-11-21 06:32:11'),
(995, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0083.', 'research', 83, '/asu-rems/research/view.php?id=83', 0, NULL, '2026-11-21 06:32:46'),
(996, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0083.', 'research', 83, '/asu-rems/research/view.php?id=83', 0, NULL, '2026-11-21 06:32:46'),
(997, 6, 'New RPS comment on your research', 'Research Processing Staff saved a private comment on Abstract for your research proposal.', 'research', 83, '/asu-rems/research/view.php?id=83#research-content', 0, NULL, '2026-11-21 06:33:11'),
(998, 6, 'Research forwarded to URRC Review', 'Your research \"asurems\" has passed initial RPS intake and is now for URRC Review.', 'research', 83, '/asu-rems/research/view.php?id=83', 0, NULL, '2026-11-21 06:33:17'),
(999, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0084: d', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:01:58'),
(1000, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0084: d', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:01:58'),
(1001, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0084.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:01'),
(1002, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected For URRC for research ASU-RES-2026-0084.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:01'),
(1003, 6, 'Research forwarded to URRC Review', 'Your research \"d\" has passed initial RPS intake and is now for URRC Review.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:19'),
(1004, 6, 'URRC review accepted', 'Your research \"d\" was accepted in URRC review and is now for In-House review.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:27'),
(1005, 6, 'In-House review passed', 'Your research \"d\" passed In-House Review and is now for UREC/IACUC processing.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:33'),
(1006, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"d\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:37'),
(1007, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"d\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:02:41'),
(1008, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for d.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:04:49'),
(1009, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for d.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:04:49'),
(1010, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"d\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:05:06'),
(1011, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for d.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:05:23'),
(1012, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for d.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:05:23'),
(1013, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"d\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:07:52'),
(1014, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for d. The research record is now completed.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:08:20'),
(1015, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for d. The research record is now completed.', 'research', 84, '/asu-rems/research/view.php?id=84', 0, NULL, '2026-11-22 07:08:20'),
(1016, 7, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0085: a', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:09:53'),
(1017, 9, 'New research proposal submitted', 'Larra Jane D. Casidsid submitted research proposal ASU-RES-2026-0085: a', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:09:53'),
(1018, 7, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0085.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:09:56'),
(1019, 9, 'Research pathway selected', 'Larra Jane D. Casidsid selected Self-Initiated for research ASU-RES-2026-0085.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:09:56'),
(1020, 7, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0085.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:10:05'),
(1021, 9, 'UPE certificate uploaded', 'Larra Jane D. Casidsid uploaded UPE Certificate 1 for research ASU-RES-2026-0085.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:10:05'),
(1022, 7, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0085. Both required UPE certificates are complete.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:10:14'),
(1023, 9, 'UPE certificates completed', 'Larra Jane D. Casidsid uploaded UPE Certificate 2 for research ASU-RES-2026-0085. Both required UPE certificates are complete.', 'research', 85, '/asu-rems/research/view.php?id=85', 0, NULL, '2026-11-22 07:10:14'),
(1024, 6, 'URRC review accepted', 'Your research \"ddd\" was accepted in URRC review and is now for In-House review.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:15:30'),
(1025, 6, 'In-House review passed', 'Your research \"ddd\" passed In-House Review and is now for UREC/IACUC processing.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:15:36'),
(1026, 6, 'UREC/IACUC review completed', 'The UREC/IACUC review for your research \"ddd\" has been completed with classification: Expedited. Your system-generated UREC/IACUC certificate is now available.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:15:40'),
(1027, 6, 'Notice to Proceed generated', 'Notice to Proceed has been generated for your research \"ddd\". You may now proceed with implementation. Remarks: Notice to Proceed generated by RPS.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:15:43'),
(1028, 7, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ddd.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:16:25'),
(1029, 9, 'Completed research manuscript submitted', 'Larra Jane D. Casidsid submitted the completed research manuscript for ddd.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:16:25'),
(1030, 6, 'Completed manuscript confirmed', 'Your completed research manuscript for \"ddd\" has been confirmed by the Research Office. You may proceed to the next requirement.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:16:35'),
(1031, 7, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ddd.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:16:54'),
(1032, 9, 'Terminal report submitted', 'Larra Jane D. Casidsid submitted the terminal report for ddd.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:16:54'),
(1033, 6, 'Publication and Presentation permits issued', 'Both the Permit for Publication and the Permit for Presentation have been issued for your research \"ddd\". Please submit proof of publication or presentation to complete the research record. Both permits remain available to view and print.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:17:06'),
(1034, 7, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ddd. The research record is now completed.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:17:24'),
(1035, 9, 'Research proof submitted', 'Larra Jane D. Casidsid submitted proof of publication / presentation for ddd. The research record is now completed.', 'research', 82, '/asu-rems/research/view.php?id=82', 0, NULL, '2026-11-22 07:17:24');

-- --------------------------------------------------------

--
-- Table structure for table `research_documents`
--

CREATE TABLE `research_documents` (
  `document_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_extension` varchar(20) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_documents`
--

INSERT INTO `research_documents` (`document_id`, `research_id`, `uploaded_by`, `document_type`, `original_name`, `stored_name`, `file_path`, `file_extension`, `file_size`, `uploaded_at`) VALUES
(171, 51, 6, 'Proposal', 'Larra_Casidsid_Faculty_Agriculture_Research_Proposal.docx', 'manuscript_51_20261006222854_9ff3293250f8f60b.docx', 'uploads/research/proposals/manuscript_51_20261006222854_9ff3293250f8f60b.docx', 'docx', 41042, '2026-10-06 14:28:54'),
(172, 51, 6, 'Payment Receipt', 'RECEIPT.png', 'receipt_51_20261006222854_0e375710f7cdf3c5.png', 'uploads/research/receipts/receipt_51_20261006222854_0e375710f7cdf3c5.png', 'png', 118045, '2026-10-06 14:28:54'),
(173, 51, 6, 'UPE Certificate 1', 'ASU_REMS_SAMPLE_UPE_Certificate_1.png', 'upe_certificate_1_51_20261006224051_6dc95795b1a95e77.png', 'uploads/research/upe_certificates/upe_certificate_1_51_20261006224051_6dc95795b1a95e77.png', 'png', 129563, '2026-10-06 14:40:51'),
(174, 51, 6, 'UPE Certificate 2', 'ASU_REMS_SAMPLE_UPE_Certificate_2.png', 'upe_certificate_2_51_20261006224100_f0b2eba1830bd5a2.png', 'uploads/research/upe_certificates/upe_certificate_2_51_20261006224100_f0b2eba1830bd5a2.png', 'png', 129961, '2026-10-06 14:41:00'),
(175, 51, 6, 'Self-Initiated Completed Research', 'Larra_Casidsid_Faculty_Agriculture-Manuscript Copy.docx', 'self_initiated_completed_research_51_20261006224258_467ed725597d187c.docx', 'uploads/research/self_initiated_completed_research/self_initiated_completed_research_51_20261006224258_467ed725597d187c.docx', 'docx', 41042, '2026-10-06 14:42:58'),
(176, 51, 6, 'Self-Initiated Terminal Report', 'ASU_REMS_SAMPLE_Terminal_Report.pdf', 'self_initiated_terminal_report_51_20261006224324_778987e4b867f823.pdf', 'uploads/research/self_initiated_terminal_reports/self_initiated_terminal_report_51_20261006224324_778987e4b867f823.pdf', 'pdf', 184463, '2026-10-06 14:43:24'),
(177, 51, 6, 'Self-Initiated Publication', 'ASU_REMS_SAMPLE_Publication_File.pdf', 'self_initiated_publication_proof_51_20261006224403_782d2b2217f1ff5b.pdf', 'uploads/research/self_initiated_publication_proofs/self_initiated_publication_proof_51_20261006224403_782d2b2217f1ff5b.pdf', 'pdf', 95649, '2026-10-06 14:44:03'),
(178, 52, 6, 'Proposal', 'Research Proposal.docx', 'manuscript_52_20261006232200_5504defa2d093bc3.docx', 'uploads/research/proposals/manuscript_52_20261006232200_5504defa2d093bc3.docx', 'docx', 51680, '2026-10-06 15:22:00'),
(179, 52, 6, 'Payment Receipt', 'RECEIPT.png', 'receipt_52_20261006232200_f8960937fe4c3f07.png', 'uploads/research/receipts/receipt_52_20261006232200_f8960937fe4c3f07.png', 'png', 118045, '2026-10-06 15:22:00'),
(180, 52, 6, 'Revised Proposal', 'Revised.docx', 'research_52_revision_20261006232848_b2b2372accf01a2b.docx', 'uploads/research/proposals/research_52_revision_20261006232848_b2b2372accf01a2b.docx', 'docx', 51680, '2026-10-06 15:28:48'),
(186, 54, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_54', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 13:36:05'),
(187, 54, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_54', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 13:36:05'),
(188, 54, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_54_e4107a5f6f', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 14:17:13'),
(189, 54, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_54_5076377275', 'https://1drv.ms/w/c/2567c2fe84df8232/IQCFjiE6uTiOTqimYlc-MQPTATrdCgrowFQNKXMKKYvT0lo?e=fBmisW', 'url', 0, '2026-10-13 14:41:36'),
(190, 54, 6, 'Self-Initiated Completed Research', 'Completed Research / Whole Manuscript Link', 'linked_completed_research_54_bede86d50d', 'https://drive.google.com/file/d/1BmVdQIKDsfTJ1LG_ROkHPilqlwedF8se/view?usp=drive_link', 'url', 0, '2026-10-13 14:43:13'),
(191, 55, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_55', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 15:53:17'),
(192, 55, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_55', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 15:53:17'),
(193, 55, 6, 'Proposal', 'Proposal Link', 'linked_revision_55_20261013235828_f0c716b2cc8e', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 15:58:28'),
(194, 55, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_55_363d48edf1', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:13:45'),
(195, 55, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_55_9254d848e8', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:37:33'),
(196, 56, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_56', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:39:40'),
(197, 56, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_56', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:39:40'),
(198, 56, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_56_cdde827ab3', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:39:55'),
(199, 56, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_56_beab982f27', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 16:39:58'),
(200, 56, 6, 'Self-Initiated Completed Research', 'Completed Research / Whole Manuscript Link', 'linked_completed_research_56_a42db848fb', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 17:23:28'),
(201, 56, 6, 'Self-Initiated Terminal Report', 'Terminal Report Link', 'linked_terminal_report_56_1b65cd7efa', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 17:23:40'),
(202, 56, 6, 'Self-Initiated Publication', 'Publication / Presentation Proof Link', 'linked_publication_proof_56_6425e377f7', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 17:23:46'),
(203, 57, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_57', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 17:47:56'),
(204, 57, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_57', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 17:47:56'),
(205, 58, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_58', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 18:47:42'),
(206, 58, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_58', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 18:47:42'),
(207, 59, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_59', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 19:08:27'),
(208, 59, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_59', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 19:08:27'),
(209, 60, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_60', 'https://drive.google.com/file/d/1BmVdQIKDsfTJ1LG_ROkHPilqlwedF8se/view?usp=drive_link', 'url', 0, '2026-10-13 19:16:52'),
(210, 60, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_60', 'https://drive.google.com/file/d/1BmVdQIKDsfTJ1LG_ROkHPilqlwedF8se/view?usp=drive_link', 'url', 0, '2026-10-13 19:16:52'),
(211, 60, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_60_20261014035128_aa846c9094e3', 'https://drive.google.com/file/d/1SbjJU8MGxmR_umseQbyfCIi8yTehnMDM/view?usp=drive_link', 'url', 0, '2026-10-13 19:51:28'),
(212, 60, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_60_eef8eddd21', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 20:15:52'),
(213, 60, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_60_15e4dda93e', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-13 20:17:04'),
(214, 60, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_60_18b551da65', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-AC', 'url', 0, '2026-10-13 20:33:47'),
(215, 61, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_61', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:32:02'),
(216, 61, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_61', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:32:02'),
(217, 61, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_61_63f6b2747d', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:36:39'),
(218, 61, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_61_383a43f2a3', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:36:46'),
(219, 61, 6, 'Self-Initiated Completed Research', 'Completed Research / Whole Manuscript Link', 'linked_completed_research_61_a93f015eca', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:47:58'),
(220, 61, 6, 'Self-Initiated Terminal Report', 'Terminal Report Link', 'linked_terminal_report_61_6f36d5e8dc', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:48:08'),
(221, 61, 6, 'Self-Initiated Publication', 'Publication / Presentation Proof Link', 'linked_publication_proof_61_133f52ba36', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-22 03:48:13'),
(222, 62, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_62', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:48:52'),
(223, 62, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_62', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:48:52'),
(224, 62, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_62_20261029115131_3944b7ee2def', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:51:31'),
(225, 63, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_63', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:55:11'),
(226, 63, 6, 'Payment Receipt', 'Payment Receipt Link', 'linked_receipt_63', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:55:11'),
(227, 62, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_62_78a5b3e94d', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:57:26'),
(228, 62, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_62_cbccfa52c9', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-10-29 03:58:55'),
(229, 64, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_64', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-05 15:57:23'),
(230, 64, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_64_20caabfca5', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-05 17:35:44'),
(231, 64, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_64_731d1e2d71', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-05 17:37:37'),
(232, 64, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_64_20bb22ea01', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-05 18:39:47'),
(233, 65, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_65', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 12:41:10'),
(234, 65, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_65_3e8a0bd734', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 12:42:01'),
(235, 65, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_65_c380c456a6', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 12:42:09'),
(236, 65, 6, 'Self-Initiated Completed Research', 'Completed Research / Whole Manuscript Link', 'linked_completed_research_65_d49dcb5f42', 'https://drive.google.com/file/d/1BmVdQIKDsfTJ1LG_ROkHPilqlwedF8se/view?usp=drive_link', 'url', 0, '2026-11-06 13:28:34'),
(237, 65, 6, 'Self-Initiated Terminal Report', 'Terminal Report Link', 'linked_terminal_report_65_ca9ba84834', 'https://drive.google.com/file/d/1BmVdQIKDsfTJ1LG_ROkHPilqlwedF8se/view', 'url', 0, '2026-11-06 13:47:34'),
(238, 65, 6, 'Self-Initiated Publication', 'Publication / Presentation Proof Link', 'linked_publication_proof_65_91782db043', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 13:59:26'),
(239, 66, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_66', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 14:22:45'),
(240, 66, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_66_20261106232501_fe190c9e7964', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 15:25:01'),
(241, 66, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_66_20261106233310_4ad501eba202', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 15:33:10'),
(242, 66, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_66_20261106233433_3dc2ef170c49', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 15:34:33'),
(243, 66, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_66_a2121e5def', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 16:13:08'),
(244, 66, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_66_ebb71e87e0', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-06 16:14:03'),
(245, 66, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_66_5d048a6869', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-AC', 'url', 0, '2026-11-06 16:17:01'),
(246, 67, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_67', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-08 13:31:26'),
(247, 67, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_67_254b7e9419', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-08 13:32:13'),
(248, 67, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_67_b5420c05c6', 'https://drive.google.com/drive/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-08 13:32:22'),
(249, 68, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_68', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 13:54:21'),
(250, 68, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_68_20261119215554_2079ca0571b4', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 13:55:54'),
(251, 68, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_68_20261119221823_3e12c0251a33', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:18:23'),
(252, 68, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_68_20261119222855_98c4cb830af9', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:28:55'),
(253, 69, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_69', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:31:02'),
(254, 70, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_70', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:45:22'),
(255, 70, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_70_20261119224643_72151a38b10f', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:46:43'),
(256, 70, 6, 'Revised Proposal', 'Revised Proposal Link', 'linked_revision_70_20261119224736_53b23e5f4117', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:47:36'),
(257, 71, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_71', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 14:57:59'),
(258, 72, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_72', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 15:20:44'),
(259, 72, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh605_r72_ad39c877d9c6', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 15:44:49'),
(260, 72, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih610_r72_4add0638b022', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 15:46:24'),
(261, 72, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih613_r72_ab44ae4b2d5e', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 15:56:19'),
(262, 72, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_72_e8d49287d8', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:18:25'),
(263, 72, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_72_3ab0b140ee', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:19:05'),
(264, 72, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_72_c5469e4724', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-AC', 'url', 0, '2026-11-19 16:19:41'),
(265, 73, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_73', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:21:46'),
(266, 73, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh629_r73_98cee4382342', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:23:02'),
(267, 73, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh632_r73_6dfe60c32a64', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:34:26'),
(268, 73, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih636_r73_a306b0b385b0', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:35:11'),
(269, 73, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih639_r73_d3ba992ef02f', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:42:25'),
(270, 73, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_73_596d7f9245', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:43:50'),
(271, 73, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_73_26a24f9e29', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:44:13'),
(272, 73, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_73_cfafc220df', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-AC', 'url', 0, '2026-11-19 16:44:40'),
(273, 74, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_74', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:46:10'),
(274, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh655_r74_c36ba8e734c7', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:47:28'),
(275, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh658_r74_8034569d83e3', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:48:22'),
(276, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh661_r74_0c5536873aee', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 16:59:27'),
(277, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh664_r74_f400639cfc94', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:00:46'),
(278, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih670_r74_564a2d144a9a', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:01:43'),
(279, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih673_r74_e53c64ca13b9', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:11:01'),
(280, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih676_r74_221dd17be028', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:16:29'),
(281, 74, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih679_r74_bd4c97f4d3fa', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:27:45'),
(282, 74, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_74_875a6be5ec', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:29:03'),
(283, 74, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_74_a692d552db', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:29:23'),
(284, 74, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_74_02b5e9da73', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:29:47'),
(285, 75, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_75', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:30:51'),
(286, 75, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvuh695_r75_ceafeefc258c', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:31:46'),
(287, 75, 6, 'Revised Proposal', 'Revised Proposal Link', 'rvih699_r75_ec01ec7447b1', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:32:21'),
(288, 76, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_76', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:33:24'),
(289, 77, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_77', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-19 17:43:11'),
(290, 78, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_78', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 07:28:21'),
(291, 78, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_78_7bfed8a1f3', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 07:38:19'),
(292, 78, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_78_8e039ae9f8', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 07:39:21'),
(293, 78, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_78_cf87f94797', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 07:40:36'),
(294, 79, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_79', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 08:12:06'),
(295, 80, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_80', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 08:19:05'),
(296, 81, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_81', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 12:03:06'),
(297, 82, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_82', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-20 12:56:18'),
(298, 83, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_83', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-21 06:32:04'),
(299, 84, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_84', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:01:53'),
(300, 84, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_84_a9261a39f2', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:04:49'),
(301, 84, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_84_3d4c88b243', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:05:23'),
(302, 84, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_84_4693c11726', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:08:20'),
(303, 85, 6, 'Proposal', 'Manuscript Link', 'linked_manuscript_85', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:09:49'),
(304, 85, 6, 'UPE Certificate 1', 'UPE Certificate 1 Link', 'linked_upe_certificate_1_85_8341aff2bb', 'https://1drv.ms/w/c/2567c2fe84df8232/IQCFjiE6uTiOTqimYlc-MQPTATrdCgrowFQNKXMKKYvT0lo?e=fBmisW', 'url', 0, '2026-11-22 07:10:05'),
(305, 85, 6, 'UPE Certificate 2', 'UPE Certificate 2 Link', 'linked_upe_certificate_2_85_7bb18e9abf', 'https://1drv.ms/w/c/2567c2fe84df8232/IQCFjiE6uTiOTqimYlc-MQPTATrdCgrowFQNKXMKKYvT0lo?e=fBmisW', 'url', 0, '2026-11-22 07:10:14'),
(306, 82, 6, 'Completed Manuscript', 'Completed research manuscript Link', 'linked_completed_manuscript_82_ffdb873227', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:16:25'),
(307, 82, 6, 'Terminal Report', 'Terminal report Link', 'linked_terminal_report_82_e047f278fe', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:16:54'),
(308, 82, 6, 'Proof of Publication/Presentation', 'Proof of Publication/Presentation Link', 'linked_publication_presentation_proof_82_1eaf36ec23', 'https://drive.google.com/drive/u/3/folders/1twkefOXh-dQtsxVQUYX7jzhIlNO8ugTp', 'url', 0, '2026-11-22 07:17:24');

-- --------------------------------------------------------

--
-- Table structure for table `research_document_view_confirmations`
--

CREATE TABLE `research_document_view_confirmations` (
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `research_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_document_view_confirmations`
--

INSERT INTO `research_document_view_confirmations` (`document_id`, `research_id`, `user_id`, `confirmed_at`) VALUES
(188, 54, 6, '2026-10-13 23:11:14'),
(192, 55, 7, '2026-10-14 00:00:21'),
(193, 55, 7, '2026-10-13 23:59:27'),
(212, 60, 6, '2026-10-14 04:16:07'),
(227, 62, 7, '2026-10-29 11:58:06'),
(228, 62, 6, '2026-10-29 11:59:09'),
(243, 66, 7, '2026-11-07 00:13:34'),
(291, 78, 7, '2026-11-20 15:38:42');

-- --------------------------------------------------------

--
-- Table structure for table `research_evaluations`
--

CREATE TABLE `research_evaluations` (
  `evaluation_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `evaluator_id` int(10) UNSIGNED NOT NULL,
  `decision` enum('Under Review','For Revision','Approved','Rejected') NOT NULL,
  `remarks` text DEFAULT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_evaluations`
--

INSERT INTO `research_evaluations` (`evaluation_id`, `research_id`, `evaluator_id`, `decision`, `remarks`, `evaluated_at`) VALUES
(58, 52, 7, '', 'Accepted with minor revisions for further improvement of the study.', '2026-10-06 15:26:47'),
(59, 52, 7, '', 'Accepted with minor revisions for further improvement of the study.', '2026-10-06 15:29:08'),
(60, 52, 7, '', NULL, '2026-10-06 15:31:02'),
(61, 52, 7, '', NULL, '2026-10-06 15:31:21'),
(66, 57, 7, '', '1234', '2026-10-13 18:02:41'),
(67, 58, 7, '', 'tudwkhjlwd', '2026-10-13 18:52:06'),
(68, 59, 7, '', 'UIKUUOU', '2026-10-13 19:09:16'),
(69, 60, 7, '', NULL, '2026-10-13 19:32:22'),
(70, 60, 7, '', 'KDJLDLA', '2026-10-13 19:32:30'),
(71, 60, 7, '', NULL, '2026-10-13 19:52:00'),
(72, 60, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-10-13 20:16:44'),
(73, 60, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-10-13 20:25:39'),
(74, 60, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-10-13 20:25:39'),
(75, 62, 7, '', 'AMBOBO MO, KAMUKHA NG GAWA MO', '2026-10-29 03:50:27'),
(76, 62, 7, '', 'AMBOBO MO, KAMUKHA NG GAWA MO', '2026-10-29 03:51:49'),
(77, 62, 7, '', 'GOOD JOB', '2026-10-29 03:55:40'),
(78, 62, 7, '', 'YOURE SO BEAUTIFUL LIKE U', '2026-10-29 03:56:10'),
(79, 62, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-10-29 03:58:17'),
(80, 62, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-10-29 03:59:57'),
(81, 62, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-10-29 03:59:57'),
(82, 63, 7, '', NULL, '2026-10-29 04:15:16'),
(83, 63, 7, '', NULL, '2026-10-29 04:15:21'),
(84, 64, 7, '', 'riririririr', '2026-11-05 16:42:27'),
(85, 64, 7, '', NULL, '2026-11-05 16:45:33'),
(86, 64, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-05 17:36:56'),
(87, 64, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-05 17:50:58'),
(88, 64, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-05 17:51:15'),
(89, 64, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-05 17:51:15'),
(90, 66, 7, '', 'REVISE', '2026-11-06 14:23:54'),
(91, 66, 7, '', NULL, '2026-11-06 15:25:42'),
(92, 66, 7, '', 'qwerty', '2026-11-06 15:26:06'),
(93, 66, 7, '', 'gajhuswhxikawjncokqjdiwsjcik', '2026-11-06 15:27:02'),
(94, 66, 7, '', 'qwertyuiokjnbg', '2026-11-06 15:29:36'),
(95, 66, 7, '', 'qwertyio', '2026-11-06 15:33:36'),
(96, 66, 7, '', 'mvkxm', '2026-11-06 15:34:52'),
(97, 66, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-06 16:13:44'),
(98, 66, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-06 16:14:20'),
(99, 66, 7, '', 'awertyujk', '2026-11-06 16:14:27'),
(100, 66, 7, '', 'awertyujk', '2026-11-06 16:14:27'),
(101, 68, 7, '', 'bugre', '2026-11-19 13:55:32'),
(102, 68, 7, '', 'bugre', '2026-11-19 13:56:10'),
(103, 68, 7, '', 'QWERTY', '2026-11-19 14:17:16'),
(104, 68, 7, '', 'QWER', '2026-11-19 14:17:23'),
(105, 68, 7, '', 'QWER', '2026-11-19 14:18:36'),
(106, 69, 7, '', 'QWERT', '2026-11-19 14:31:28'),
(107, 69, 7, '', 'QWERTY', '2026-11-19 14:31:35'),
(108, 70, 7, '', 'QWERTY', '2026-11-19 14:45:45'),
(109, 70, 7, '', 'QWERTY', '2026-11-19 14:46:53'),
(110, 70, 7, '', 'qwerty', '2026-11-19 14:47:48'),
(111, 70, 7, '', 'qwerty', '2026-11-19 14:47:56'),
(112, 71, 7, '', 'qwertyu', '2026-11-19 14:58:37'),
(113, 72, 7, '', 'qwertyu', '2026-11-19 15:21:19'),
(114, 68, 7, '', 'gew', '2026-11-19 15:45:06'),
(115, 72, 7, '', 'swd', '2026-11-19 15:45:43'),
(116, 72, 7, '', 'fed', '2026-11-19 15:45:51'),
(117, 72, 7, '', 'fed', '2026-11-19 15:46:33'),
(118, 72, 7, '', NULL, '2026-11-19 15:56:34'),
(119, 72, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-19 16:18:51'),
(120, 72, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-19 16:19:16'),
(121, 72, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 16:19:23'),
(122, 72, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 16:19:23'),
(123, 73, 7, '', 'hjgj', '2026-11-19 16:22:27'),
(124, 73, 7, '', 'hjgj', '2026-11-19 16:23:12'),
(125, 73, 7, '', 'hhhh', '2026-11-19 16:34:49'),
(126, 73, 7, '', 'hhh', '2026-11-19 16:34:56'),
(127, 73, 7, '', 'hhh', '2026-11-19 16:35:20'),
(128, 73, 7, '', 'shhsshsh', '2026-11-19 16:43:09'),
(129, 73, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-19 16:44:00'),
(130, 73, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-19 16:44:22'),
(131, 73, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 16:44:25'),
(132, 73, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 16:44:25'),
(133, 74, 7, '', 'HHH', '2026-11-19 16:47:03'),
(134, 74, 7, '', 'HHH', '2026-11-19 16:47:36'),
(135, 74, 7, '', 'HHH', '2026-11-19 16:48:32'),
(136, 74, 7, '', 'HHH', '2026-11-19 16:59:36'),
(137, 74, 7, '', NULL, '2026-11-19 17:01:11'),
(138, 74, 7, '', 'HHH', '2026-11-19 17:01:23'),
(139, 74, 7, '', 'HHH', '2026-11-19 17:01:52'),
(140, 74, 7, '', 'HHH', '2026-11-19 17:11:10'),
(141, 74, 7, '', 'HHH', '2026-11-19 17:16:39'),
(142, 74, 7, '', 'dddddd', '2026-11-19 17:28:10'),
(143, 74, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-19 17:29:13'),
(144, 74, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-19 17:29:30'),
(145, 74, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 17:29:34'),
(146, 74, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-19 17:29:34'),
(147, 75, 7, '', 'ffffff', '2026-11-19 17:31:18'),
(148, 75, 7, '', 'ccc', '2026-11-19 17:32:00'),
(149, 75, 7, '', 'fff', '2026-11-19 17:32:04'),
(150, 75, 7, '', 'fff', '2026-11-19 17:32:30'),
(151, 76, 7, '', 'sss', '2026-11-19 17:33:59'),
(152, 77, 7, '', NULL, '2026-11-19 17:44:07'),
(153, 77, 7, '', 'hjhfjsdhfjsdh', '2026-11-19 17:44:24'),
(154, 78, 7, '', 'good job!', '2026-11-20 07:32:02'),
(155, 78, 7, '', 'nice oone', '2026-11-20 07:32:40'),
(156, 78, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-20 07:38:51'),
(157, 78, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-20 07:39:39'),
(158, 78, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-20 07:40:08'),
(159, 78, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-20 07:40:08'),
(160, 79, 7, '', 'nice one', '2026-11-20 08:12:47'),
(161, 79, 7, '', 'fgtetue', '2026-11-20 08:12:55'),
(162, 80, 7, '', 'SGDETGSW', '2026-11-20 08:20:16'),
(163, 80, 7, '', 'HDHREWRETW', '2026-11-20 08:20:21'),
(164, 81, 7, '', 'qwerty', '2026-11-20 12:04:17'),
(165, 81, 7, '', 'aqws', '2026-11-20 12:04:23'),
(166, 84, 7, '', NULL, '2026-11-22 07:02:27'),
(167, 84, 7, '', NULL, '2026-11-22 07:02:33'),
(168, 84, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-22 07:05:06'),
(169, 84, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-22 07:07:49'),
(170, 84, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-22 07:07:52'),
(171, 84, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-22 07:07:52'),
(172, 82, 7, '', NULL, '2026-11-22 07:15:30'),
(173, 82, 7, '', NULL, '2026-11-22 07:15:36'),
(174, 82, 7, '', 'Completed research manuscript confirmed by Research Processing Staff.', '2026-11-22 07:16:35'),
(175, 82, 7, '', 'Submitted Terminal Report checked and confirmed by Research Processing Staff.', '2026-11-22 07:17:03'),
(176, 82, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-22 07:17:06'),
(177, 82, 7, '', 'Permit generated after review of the submitted Terminal Report.', '2026-11-22 07:17:06');

-- --------------------------------------------------------

--
-- Table structure for table `research_progress`
--

CREATE TABLE `research_progress` (
  `progress_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `submitted_by` int(10) UNSIGNED NOT NULL,
  `progress_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `progress_title` varchar(200) NOT NULL,
  `progress_description` text NOT NULL,
  `reporting_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_progress_updates`
--

CREATE TABLE `research_progress_updates` (
  `progress_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `researcher_id` int(10) UNSIGNED NOT NULL,
  `progress_title` varchar(255) NOT NULL,
  `reporting_date` date NOT NULL,
  `progress_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `progress_description` text NOT NULL,
  `challenges` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `document_original_name` varchar(255) DEFAULT NULL,
  `document_stored_name` varchar(255) DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `document_extension` varchar(20) DEFAULT NULL,
  `document_size` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_progress_updates`
--

INSERT INTO `research_progress_updates` (`progress_id`, `research_id`, `researcher_id`, `progress_title`, `reporting_date`, `progress_percentage`, `progress_description`, `challenges`, `next_steps`, `document_original_name`, `document_stored_name`, `document_path`, `document_extension`, `document_size`, `created_at`, `updated_at`) VALUES
(8, 52, 6, 'Research Implementation and Data Collection Progress', '2026-10-06', 60, 'The mobile-based study planner has been implemented and the research participants have completed the initial assessment. The intervention and monitoring activities are ongoing, while preliminary data on academic task management, engagement, and task completion have been collected and organized for analysis.', 'Minor challenges were encountered in participant scheduling and consistent use of the mobile study planner. These concerns were addressed through regular monitoring and coordination with the participants.', 'Complete the intervention period, conduct the post-intervention assessment, finalize data collection, and proceed with statistical analysis and interpretation of the results.', NULL, NULL, NULL, NULL, NULL, '2026-10-06 15:35:33', '2026-10-06 15:35:33'),
(9, 52, 6, 'Final Research Completion', '2026-10-06', 100, 'All research activities have been completed, including the implementation of the mobile-based study planner, pre-intervention and post-intervention assessments, data collection, statistical analysis, and interpretation of findings. The final research manuscript, conclusions, and recommendations have also been completed.', 'Minor issues involving participant scheduling and data collection were encountered during implementation but were successfully addressed and did not prevent completion of the study.', 'Submit the completed research manuscript and Terminal Report, secure the required permit to present or publish, and prepare the study for research presentation and possible publication.', NULL, NULL, NULL, NULL, NULL, '2026-10-06 15:37:09', '2026-10-06 15:37:09');

-- --------------------------------------------------------

--
-- Table structure for table `research_projects`
--

CREATE TABLE `research_projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `manuscript_no` varchar(40) DEFAULT NULL,
  `researcher_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `funding_source` varchar(255) DEFAULT NULL,
  `submission_track` enum('REGULAR','SELF_INITIATED') NOT NULL DEFAULT 'REGULAR',
  `current_status` varchar(64) NOT NULL DEFAULT 'DRAFT',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_reason` varchar(255) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_proposals`
--

CREATE TABLE `research_proposals` (
  `research_id` int(10) UNSIGNED NOT NULL,
  `manuscript_no` varchar(40) DEFAULT NULL,
  `researcher_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `research_title` varchar(255) NOT NULL,
  `research_category` varchar(150) DEFAULT NULL,
  `research_type` varchar(100) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `methodology` text DEFAULT NULL,
  `expected_output` text DEFAULT NULL,
  `funding_source` varchar(150) DEFAULT NULL,
  `submission_track` varchar(30) NOT NULL DEFAULT 'REGULAR',
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `target_completion_date` date DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','For Revision','Approved','Ongoing','Completed','Rejected','Archived') NOT NULL DEFAULT 'Draft',
  `workflow_status` varchar(64) NOT NULL DEFAULT 'DRAFT',
  `workflow_stage` varchar(64) NOT NULL DEFAULT 'SUBMISSION',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_reason` varchar(255) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `workflow_updated_at` datetime DEFAULT NULL,
  `ethics_result` varchar(30) DEFAULT NULL,
  `ethics_remarks` text DEFAULT NULL,
  `ethics_reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `ethics_reviewed_at` datetime DEFAULT NULL,
  `progress_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_proposals`
--

INSERT INTO `research_proposals` (`research_id`, `manuscript_no`, `researcher_id`, `college_id`, `department_id`, `research_title`, `research_category`, `research_type`, `abstract`, `objectives`, `methodology`, `expected_output`, `funding_source`, `submission_track`, `estimated_budget`, `start_date`, `target_completion_date`, `status`, `workflow_status`, `workflow_stage`, `is_archived`, `archived_reason`, `archived_at`, `workflow_updated_at`, `ethics_result`, `ethics_remarks`, `ethics_reviewed_by`, `ethics_reviewed_at`, `progress_percentage`, `submitted_at`, `approved_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(51, 'ASU-RES-2026-0051', 6, NULL, NULL, 'Growth and Yield Performance of Pechay (Brassica rapa L.) Using Vermicompost as an Organic Soil Amendment', 'Agriculture', 'Applied Research', 'This study aims to evaluate the effectiveness of vermicompost as an organic soil amendment in improving the growth and yield performance of pechay (Brassica rapa L.). The experiment will compare different application rates of vermicompost with a control treatment to determine its effects on plant height, number of leaves, leaf size, fresh weight, and overall marketable yield. A field experiment will be conducted using a randomized complete block design with several treatment levels and replications. Standard cultural management practices will be applied throughout the growing period, while growth and yield data will be recorded at scheduled intervals. The collected data will be analyzed using appropriate descriptive and inferential statistical methods to determine whether significant differences exist among treatments. The study is expected to provide practical information on the potential use of vermicompost as a sustainable and cost-effective soil amendment for vegetable production and may serve as a basis for recommendations to farmers and future agricultural researchers.', 'This study aims to determine the effects of different application rates of vermicompost on the growth and yield performance of pechay (Brassica rapa L.). Specifically, it seeks to evaluate the effects of vermicompost on plant height, number of leaves, leaf size, fresh weight, and marketable yield; compare the performance of pechay grown under different vermicompost treatments with plants grown without vermicompost; determine the application rate that produces the best growth and yield response; assess the potential of vermicompost as an alternative organic soil amendment for vegetable production; and develop recommendations regarding its possible use in sustainable pechay production.', 'The study will employ an experimental research design using a Randomized Complete Block Design (RCBD). Pechay plants will be assigned to four treatments consisting of a control treatment with no vermicompost application and three different levels of vermicompost application. Each treatment will be replicated to minimize the influence of environmental variation. Uniform planting materials and standard cultural management practices, including irrigation, weed control, and pest monitoring, will be applied throughout the experimental period. Data will be collected on plant height, number of leaves, leaf size, fresh weight, and marketable yield. Observations will be conducted at predetermined intervals until harvest. The collected data will be organized and analyzed using mean, standard deviation, and Analysis of Variance (ANOVA). When significant differences among treatments are observed, an appropriate post-hoc comparison test will be used to determine which treatment produced the best growth and yield performance.', 'The study is expected to produce a complete agricultural research report presenting the effects of vermicompost on the growth and yield of pechay, including statistical analysis, findings, conclusions, and recommendations. It is also expected to identify an appropriate vermicompost application rate that may improve vegetable production, develop practical recommendations for the use of organic soil amendments, and provide information that may benefit farmers, agriculture students, extension workers, and future researchers. The results may also be used as supporting material for agricultural extension activities, research presentations, and possible preparation of a research article for institutional or academic publication.', 'Self-Initiated', 'SELF_INITIATED', 20000.00, '2026-10-29', '2027-11-25', 'Completed', 'SELF_INITIATED_COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-10-06 22:44:03', 'EXEMPT', NULL, 7, '2026-10-06 22:38:02', 100, '2026-10-06 22:30:26', NULL, '2026-10-06 22:44:03', '2026-10-06 14:28:54', '2026-10-06 14:44:03'),
(52, 'ASU-RES-2026-0052', 6, NULL, NULL, 'Effectiveness of a Mobile-Based Study Planner on Academic Task Management and Engagement Among First-Year College Students', 'ICT', 'Applied Research', 'The increasing use of mobile technology among college students provides opportunities to improve the way students organize and manage their academic responsibilities. This study aims to determine the effectiveness of a mobile-based study planner in improving academic task management and engagement among first-year college students. The proposed application will allow students to record assignments, organize academic tasks, set priorities, monitor deadlines, and receive reminders for upcoming requirements. The study will use a quasi-experimental research design involving selected first-year college students who will be divided into an intervention group and a comparison group. The intervention group will use the mobile-based study planner, while the comparison group will continue using their usual academic planning methods. Data will be collected through questionnaires, task-completion records, and user evaluation forms. The study is expected to determine whether the use of a mobile-based study planner can improve students\' organization, time management, task completion, and academic engagement. The findings may serve as a basis for developing or adopting digital academic-support tools within the university.', 'This study aims to determine the effectiveness of a mobile-based study planner in improving academic task management and engagement among first-year college students. Specifically, it seeks to assess the students\' academic task-management practices and level of academic engagement before and after using the mobile-based study planner, evaluate changes in task organization, deadline monitoring, prioritization, and completion of academic requirements, compare the results of students who use the mobile-based study planner with those who continue using their usual academic-planning methods, determine the perceived usability and usefulness of the application, identify possible areas for improvement, and develop recommendations for the possible adoption of mobile-based academic-planning tools within the university.', 'The study will employ a quasi-experimental pretest-posttest comparison-group design involving approximately 60 selected first-year college students. The participants will be divided into an intervention group composed of 30 students who will use the mobile-based study planner for approximately six weeks and a comparison group composed of 30 students who will continue using their usual academic-planning practices. Both groups will undergo pre-intervention and post-intervention assessments to measure their academic task management and engagement.Data will be gathered using an Academic Task Management Questionnaire, Academic Engagement Questionnaire, Task Completion Monitoring Sheet, User Evaluation Questionnaire, and Open-Ended Feedback Form. The collected data will be organized, analyzed, and interpreted using appropriate statistical tools such as frequency, percentage, mean, standard deviation, paired-sample statistical tests, and independent-group statistical tests to determine whether significant differences or improvements occur after the intervention. Data will be gathered using an Academic Task Management Questionnaire, Academic Engagement Questionnaire, Task Completion Monitoring Sheet, User Evaluation Questionnaire, and Open-Ended Feedback Form. The collected data will be organized, analyzed, and interpreted using appropriate statistical tools such as frequency, percentage, mean, standard deviation, paired-sample statistical tests, and independent-group statistical tests to determine whether significant differences or improvements occur after the intervention.', 'The study is expected to produce a complete research report containing the findings, analysis, conclusions, and recommendations; a functional or improved mobile-based study planner prototype that can assist students in organizing academic requirements and monitoring deadlines; an academic task-management framework that may guide students in prioritizing, scheduling, and completing academic responsibilities; and recommendations for the possible adoption or integration of digital academic-planning tools into university student-support programs. The study may also result in a research presentation for university research forums, in-house reviews, or academic conferences, as well as a research article that may be prepared for possible submission to an institutional or academic journal.', 'ASU', 'REGULAR', 15000.00, '2026-11-09', '2027-05-26', 'Ongoing', 'NTP_ISSUED', 'IMPLEMENTATION', 0, NULL, NULL, '2026-10-06 23:31:30', 'EXPEDITED', NULL, 7, '2026-10-06 23:23:11', 100, '2026-10-06 23:30:28', '2026-10-06 23:31:21', NULL, '2026-10-06 15:22:00', '2026-10-06 15:37:09'),
(54, 'ASU-RES-2026-0054', 6, NULL, NULL, 'jkdjkashdklas', 'Technical', 'Basic Research', 'jkwahdlkaj;dja', 'jkwahdlkaj;dja', 'jkwahdlkaj;dja', 'jkwahdlkaj;dja', 'asu', 'SELF_INITIATED', 10000000.00, '2026-10-14', '2026-10-30', 'Under Review', 'SELF_INITIATED_COMPLETED_RESEARCH_SUBMITTED', 'COMPLETED_RESEARCH', 0, NULL, NULL, '2026-10-13 22:43:13', 'EXEMPT', NULL, 7, '2026-10-13 22:16:00', 0, '2026-10-13 21:36:32', NULL, NULL, '2026-10-13 13:36:05', '2026-10-13 14:43:13'),
(55, 'ASU-RES-2026-0055', 6, NULL, NULL, 'IOSUUFSE', 'Social Science', 'Applied Research', 'UDWIAUSHJFJUI', 'UDWIAUSHJFJUI', 'UDWIAUSHJFJUI', 'UDWIAUSHJFJUI', 'ASU', 'SELF_INITIATED', NULL, NULL, NULL, 'Under Review', 'SELF_INITIATED_UPE_SUBMITTED', 'UPE', 0, NULL, NULL, '2026-10-14 00:37:33', 'EXEMPT', NULL, 7, '2026-10-14 00:13:25', 0, '2026-10-13 23:58:36', NULL, NULL, '2026-10-13 15:53:17', '2026-10-13 16:37:33'),
(56, 'ASU-RES-2026-0056', 6, NULL, NULL, 'TESTING 1', 'Social Science', 'Other', 'F;LAKF;LK;', 'F;LAKF;LK;', 'F;LAKF;LK;', 'F;LAKF;LK;', 'CHED', 'SELF_INITIATED', 100000.00, '2026-10-06', '2026-10-30', 'Completed', 'SELF_INITIATED_COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-10-14 01:23:46', 'EXPEDITED', '1234', 7, '2026-10-14 01:12:37', 100, '2026-10-14 00:39:45', NULL, '2026-10-14 01:23:46', '2026-10-13 16:39:40', '2026-10-13 17:23:46'),
(57, 'ASU-RES-2026-0057', 6, NULL, NULL, 'HATDOG', 'Social Science', 'Basic Research', 'qwerty', 'qwerty', 'qwerty', 'qwerty', 'ASU', 'REGULAR', 1234.00, '2026-10-14', '2026-10-31', 'Archived', 'URRC_DID_NOT_REVISE', 'ARCHIVED', 1, 'Did Not Revise: 21-Day Deadline elapsed without a resubmitted URRC revision.', '2026-11-05 23:16:33', '2026-11-05 23:16:33', NULL, NULL, NULL, NULL, 0, '2026-10-14 01:48:01', NULL, NULL, '2026-10-13 17:47:56', '2026-11-05 15:16:33'),
(58, 'ASU-RES-2026-0058', 6, NULL, NULL, 'TESTING', 'Social Science', 'Basic Research', 'jjrhjskkk', 'jjrhjskkk', 'jjrhjskkk', 'jjrhjskkk', 'ASU', 'REGULAR', NULL, NULL, NULL, 'Archived', 'URRC_DID_NOT_REVISE', 'ARCHIVED', 1, 'Did Not Revise: 2-Minute TEST Deadline elapsed without a resubmitted URRC revision.', '2026-10-14 02:54:19', '2026-10-14 02:54:19', NULL, NULL, NULL, NULL, 0, '2026-10-14 02:47:47', NULL, NULL, '2026-10-13 18:47:42', '2026-10-13 18:54:19'),
(59, 'ASU-RES-2026-0059', 6, NULL, NULL, 'TESTING 2', 'Social Science', 'Basic Research', 'HUHDYWHAKJKJKJ', 'HUHDYWHAKJKJKJ', 'HUHDYWHAKJKJKJ', 'HUHDYWHAKJKJKJ', 'ASU', 'REGULAR', NULL, NULL, NULL, 'Archived', 'URRC_DID_NOT_REVISE', 'ARCHIVED', 1, 'Did Not Revise: 21-Day Deadline elapsed without a resubmitted URRC revision.', '2026-11-05 23:16:33', '2026-11-05 23:16:33', NULL, NULL, NULL, NULL, 0, '2026-10-14 03:08:31', NULL, NULL, '2026-10-13 19:08:27', '2026-11-05 15:16:33'),
(60, 'ASU-RES-2026-0060', 6, NULL, NULL, 'TESTING 3 URRC', 'Social Science', 'Basic Research', 'JOUOWRUUALJSJJLFJLJQ', 'JOUOWRUUALJSJJLFJLJQ', 'JOUOWRUUALJSJJLFJLJQ', 'JOUOWRUUALJSJJLFJLJQ', 'ASU', 'REGULAR', NULL, NULL, NULL, 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-10-14 04:33:47', 'EXEMPT', NULL, 7, '2026-10-14 03:52:08', 100, '2026-10-14 03:51:40', '2026-10-14 03:52:08', '2026-10-14 04:33:47', '2026-10-13 19:16:52', '2026-10-13 20:33:47'),
(61, 'ASU-RES-2026-0061', 6, NULL, NULL, 'ASU REMS', 'Social Science', 'Basic Research', 'SU-REMS is a web-based platform designed to centralize\r\nthe management of research and extension activities at\r\nAklan State University. It supports proposal submission,\r\nevaluation and approval, project monitoring, document\r\nmanagement, and report generation. \r\nThe project aims to make institutional records easier to\r\norganize, track, and retrieve. Its evaluation considers record\r\naccuracy, system response time, task completion, and user\r\nfeedback.', 'SU-REMS is a web-based platform designed to centralize\r\nthe management of research and extension activities at\r\nAklan State University. It supports proposal submission,\r\nevaluation and approval, project monitoring, document\r\nmanagement, and report generation. \r\nThe project aims to make institutional records easier to\r\norganize, track, and retrieve. Its evaluation considers record\r\naccuracy, system response time, task completion, and user\r\nfeedback.', 'SU-REMS is a web-based platform designed to centralize\r\nthe management of research and extension activities at\r\nAklan State University. It supports proposal submission,\r\nevaluation and approval, project monitoring, document\r\nmanagement, and report generation. \r\nThe project aims to make institutional records easier to\r\norganize, track, and retrieve. Its evaluation considers record\r\naccuracy, system response time, task completion, and user\r\nfeedback.', 'SU-REMS is a web-based platform designed to centralize\r\nthe management of research and extension activities at\r\nAklan State University. It supports proposal submission,\r\nevaluation and approval, project monitoring, document\r\nmanagement, and report generation. \r\nThe project aims to make institutional records easier to\r\norganize, track, and retrieve. Its evaluation considers record\r\naccuracy, system response time, task completion, and user\r\nfeedback.', 'ASU', 'SELF_INITIATED', 100.00, '2026-10-30', '2026-11-06', 'Completed', 'SELF_INITIATED_COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-10-22 11:48:13', 'EXEMPT', NULL, 7, '2026-10-22 11:45:12', 100, '2026-10-22 11:32:11', NULL, '2026-10-22 11:48:13', '2026-10-22 03:32:02', '2026-10-22 03:48:13'),
(62, 'ASU-RES-2026-0062', 6, NULL, NULL, 'ASU REMS', 'Social Science', 'Basic Research', 'ANG MGA BABOY SA KAPULUAN', 'TO GATHER STORIES', 'ETHNOGRAPHY', 'STORYBOOK', 'ASU', 'REGULAR', 100.00, '2026-10-29', '2026-11-07', 'Ongoing', 'PERMIT_ISSUED', 'PUBLICATION_PRESENTATION', 0, NULL, NULL, '2026-10-29 11:59:57', 'EXEMPT', NULL, 7, '2026-10-29 11:56:26', 100, '2026-10-29 11:52:58', '2026-10-29 11:56:26', NULL, '2026-10-29 03:48:52', '2026-10-29 03:59:57'),
(63, 'ASU-RES-2026-0063', 6, NULL, NULL, 'ASU REMS', 'Social Science', 'Basic Research', 'QWERTY', 'QWERTY', 'QWERTY', 'QWERTY', 'ASU', 'REGULAR', 100.00, '2026-11-06', '2026-11-07', 'Ongoing', 'NTP_ISSUED', 'IMPLEMENTATION', 0, NULL, NULL, '2026-10-29 12:15:39', 'EXEMPT', NULL, 7, '2026-10-29 12:15:26', 0, '2026-10-29 11:55:16', '2026-10-29 12:15:26', NULL, '2026-10-29 03:55:10', '2026-10-29 04:15:39'),
(64, 'ASU-RES-2026-0064', 6, NULL, NULL, 'ASUREMS14', 'Social Science', 'Basic Research', 'SKJKLSDJFKJSDKFJSDKLFJLDJFKDNVNVCN', 'SKJKLSDJFKJSDKFJSDKLFJLDJFKDNVNVCN', 'SKJKLSDJFKJSDKFJSDKLFJLDJFKDNVNVCN', 'SKJKLSDJFKJSDKFJSDKLFJLDJFKDNVNVCN', 'CHED', 'REGULAR', 1000000.00, '2026-11-05', '2026-11-28', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-06 02:39:47', 'EXEMPT', NULL, 7, '2026-11-06 00:48:41', 100, '2026-11-06 00:22:31', '2026-11-06 00:48:41', '2026-11-06 02:39:47', '2026-11-05 15:57:23', '2026-11-05 18:39:47'),
(65, 'ASU-RES-2026-0065', 6, NULL, NULL, 'ASUREMS', 'Social Science', 'Basic Research', 'qwerty', 'qwerty', 'qwerty', 'qwerty', 'ASU', 'SELF_INITIATED', 99.87, '2026-11-06', '2026-11-30', 'Completed', 'SELF_INITIATED_COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-06 21:59:26', 'EXEMPT', 'qwerty', 7, '2026-11-06 21:08:11', 100, '2026-11-06 20:41:26', NULL, '2026-11-06 21:59:26', '2026-11-06 12:41:10', '2026-11-06 13:59:26'),
(66, 'ASU-RES-2026-0066', 6, NULL, NULL, 'ASUASURONE', 'Social Science', 'Applied Research', 'JEOJWFJFJEFJELFJ', 'JEOJWFJFJEFJELFJ', 'JEOJWFJFJEFJELFJ', 'JEOJWFJFJEFJELFJ', 'CHED', 'REGULAR', 50000.00, '2026-11-04', '2026-11-28', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-07 00:17:01', 'EXEMPT', 'sdfghjk', 7, '2026-11-06 23:35:07', 100, '2026-11-06 23:34:36', '2026-11-06 23:35:07', '2026-11-07 00:17:01', '2026-11-06 14:22:45', '2026-11-06 16:17:01'),
(67, 'ASU-RES-2026-0067', 6, NULL, NULL, 'ASU', 'Technical', 'Basic Research', 'QWERTY', 'QWERTY', 'QWERTY', 'QWERTY', 'CHED', 'SELF_INITIATED', 123455.71, '2026-11-08', '2026-11-27', 'Under Review', 'SELF_INITIATED_UPE_SUBMITTED', 'COMPLETED_RESEARCH', 0, NULL, NULL, '2026-11-13 23:00:31', 'EXEMPT', '12', 7, '2026-11-13 23:00:31', 0, '2026-11-08 21:31:37', NULL, NULL, '2026-11-08 13:31:26', '2026-11-13 15:00:31'),
(68, 'ASU-RES-2026-0068', 6, NULL, NULL, 'bugre', 'Social Science', 'Basic Research', 'qwerty', 'qwerty', 'qwerty', 'qwerty', 'ched', 'REGULAR', 100.00, '2026-11-19', '2026-11-30', 'Ongoing', 'NTP_ISSUED', 'IMPLEMENTATION', 0, NULL, NULL, '2026-11-20 01:00:17', 'EXEMPT', NULL, 7, '2026-11-20 01:00:14', 0, '2026-11-19 22:28:55', '2026-11-20 01:00:14', NULL, '2026-11-19 13:54:21', '2026-11-19 17:00:17'),
(69, 'ASU-RES-2026-0069', 6, NULL, NULL, 'qwerty', 'Social Science', 'Applied Research', 'qwertyu', 'qwertyu', 'qwertyu', 'qwertyu', 'CHED', 'REGULAR', 10000.00, '2026-11-19', '2026-11-30', 'For Revision', 'INHOUSE_REVISION_REQUIRED', 'INHOUSE', 0, NULL, NULL, '2026-11-19 22:31:35', NULL, NULL, NULL, NULL, 0, '2026-11-19 22:31:05', NULL, NULL, '2026-11-19 14:31:02', '2026-11-19 14:31:35'),
(70, 'ASU-RES-2026-0070', 6, NULL, NULL, 'ULIT', 'Social Science', 'Basic Research', 'QWERTYUIOP', 'QWERTYUIOP', 'QWERTYUIOP', 'QWERTYUIOP', 'CHED', 'REGULAR', 10000.00, '2026-11-19', '2026-11-30', 'For Revision', 'INHOUSE_REVISION_REQUIRED', 'INHOUSE', 0, NULL, NULL, '2026-11-19 22:47:56', NULL, NULL, NULL, NULL, 0, '2026-11-19 22:47:36', NULL, NULL, '2026-11-19 14:45:22', '2026-11-19 14:47:56'),
(71, 'ASU-RES-2026-0071', 6, NULL, NULL, 'qwertyuio', 'Social Science', 'Applied Research', 'wertyuio', 'wertyuio', 'wertyuio', 'wertyuio', 'ched', 'REGULAR', 122333.00, '2026-11-19', '2026-11-30', 'For Revision', 'URRC_REVISION_REQUIRED', 'URRC', 0, NULL, NULL, '2026-11-19 22:58:37', NULL, NULL, NULL, NULL, 0, '2026-11-19 22:58:02', NULL, NULL, '2026-11-19 14:57:59', '2026-11-19 14:58:37'),
(72, 'ASU-RES-2026-0072', 6, NULL, NULL, 'TEST 1', 'Social Science', 'Basic Research', 'qwerty', 'qwertyu', 'qwerty', 'qwerty', 'ched', 'REGULAR', 1237778888.00, '2026-11-19', '2026-11-30', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-20 00:19:41', 'FULL_BOARD', NULL, 7, '2026-11-20 00:00:23', 100, '2026-11-19 23:56:24', '2026-11-20 00:00:23', '2026-11-20 00:19:41', '2026-11-19 15:20:44', '2026-11-19 16:19:41'),
(73, 'ASU-RES-2026-0073', 6, NULL, NULL, 'TEST 2', 'Social Science', 'Applied Research', 'qwerty', 'qwerty', 'qwerty', 'qwerty', 'ched', 'REGULAR', 123.00, '2026-11-20', '2026-11-30', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-20 00:44:40', 'EXEMPT', 'ddd', 7, '2026-11-20 00:43:16', 100, '2026-11-20 00:42:32', '2026-11-20 00:43:16', '2026-11-20 00:44:40', '2026-11-19 16:21:45', '2026-11-19 16:44:40'),
(74, 'ASU-RES-2026-0074', 6, NULL, NULL, 'TEST 3', 'Social Science', 'Basic Research', 'QWERTY', 'QWERTY', 'QWERTY', 'QWERTY', 'CHED', 'REGULAR', 100.00, '2026-11-20', '2026-11-30', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-20 01:29:47', 'EXPEDITED', 'ddd', 7, '2026-11-20 01:28:16', 100, '2026-11-20 01:27:51', '2026-11-20 01:28:16', '2026-11-20 01:29:47', '2026-11-19 16:46:10', '2026-11-19 17:29:47'),
(75, 'ASU-RES-2026-0075', 6, NULL, NULL, 'test 4', 'Social Science', 'Basic Research', 'wwww', 'www', 'wwww', 'wwww', 'ched', 'REGULAR', 123.00, '2026-11-20', '2026-11-30', 'Approved', 'READY_FOR_NTP', 'NOTICE_TO_PROCEED', 0, NULL, NULL, '2026-11-20 01:32:35', 'EXEMPT', NULL, 7, '2026-11-20 01:32:35', 0, '2026-11-20 01:32:23', '2026-11-20 01:32:35', NULL, '2026-11-19 17:30:51', '2026-11-19 17:32:35'),
(76, 'ASU-RES-2026-0076', 6, NULL, NULL, 'test 5', 'Social Science', 'Applied Research', 'dsdd', 'ddd', 'ddd', 'ddd', 'ddd', 'REGULAR', 123.00, '2026-11-20', '2026-11-30', 'Archived', 'URRC_REJECTED', 'ARCHIVED', 1, 'Research rejected during URRC review.', '2026-11-20 01:33:59', '2026-11-20 01:33:59', NULL, NULL, NULL, NULL, 0, '2026-11-20 01:33:27', NULL, NULL, '2026-11-19 17:33:24', '2026-11-19 17:33:59'),
(77, 'ASU-RES-2026-0077', 6, NULL, NULL, 'test 6', 'Technical', 'Basic Research', 'gfnfgh', 'gdfdf', 'fdgfd', 'gfdgdf', 'vhed', 'REGULAR', 10000.00, '2026-11-20', '2026-11-30', 'Archived', 'INHOUSE_FAILED', 'ARCHIVED', 1, 'Research failed during In-House review.', '2026-11-20 01:44:24', '2026-11-20 01:44:24', NULL, NULL, NULL, NULL, 0, '2026-11-20 01:43:16', NULL, NULL, '2026-11-19 17:43:11', '2026-11-19 17:44:24'),
(78, 'ASU-RES-2026-0078', 6, NULL, NULL, 'asurems', 'Social Science', 'Basic Research', 'qwerty', 'qwerty', 'qwerty', 'qwerty', 'ched', 'REGULAR', 100.00, '2026-11-20', '2026-12-12', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-20 15:40:36', 'EXEMPT', NULL, 7, '2026-11-20 15:32:56', 100, '2026-11-20 15:28:46', '2026-11-20 15:32:56', '2026-11-20 15:40:36', '2026-11-20 07:28:21', '2026-11-20 07:40:36'),
(79, 'ASU-RES-2026-0079', 6, NULL, NULL, 'TEST 2', 'Social Science', 'Basic Research', 'QWRSVKSKDSJLKLK', 'QWRSVKSKDSJLKLK', 'QWRSVKSKDSJLKLK', 'QWRSVKSKDSJLKLK', 'CHED', 'REGULAR', 10000.00, '2026-11-20', '2026-12-11', 'Ongoing', 'NTP_ISSUED', 'IMPLEMENTATION', 0, NULL, NULL, '2026-11-20 16:13:06', 'EXEMPT', 'fgdsrerer', 7, '2026-11-20 16:13:02', 0, '2026-11-20 16:12:10', '2026-11-20 16:13:02', NULL, '2026-11-20 08:12:06', '2026-11-20 08:13:06'),
(80, 'ASU-RES-2026-0080', 6, NULL, NULL, 'TEST 3', 'Social Science', 'Basic Research', 'SDFGHFTYTUYIIIUOUOUIO', 'SDFGHFTYTUYIIIUOUOUIO', 'SDFGHFTYTUYIIIUOUOUIO', 'SDFGHFTYTUYIIIUOUOUIO', 'ASU', 'REGULAR', 100000.00, '2026-11-23', '2026-12-11', 'Under Review', 'FOR_ETHICS_REVIEW', 'ETHICS', 0, NULL, NULL, '2026-11-20 16:20:21', NULL, NULL, NULL, NULL, 0, '2026-11-20 16:19:09', NULL, NULL, '2026-11-20 08:19:05', '2026-11-20 08:20:21'),
(81, 'ASU-RES-2026-0081', 6, NULL, NULL, 'hulae', 'Social Science', 'Applied Research', 'qweryty', 'qweryty', 'qweryty', 'qweryty', 'qweryty', 'REGULAR', 123.00, '2026-11-20', '2026-11-26', 'Ongoing', 'NTP_ISSUED', 'IMPLEMENTATION', 0, NULL, NULL, '2026-11-20 20:04:33', 'EXEMPT', 'sdw', 7, '2026-11-20 20:04:28', 0, '2026-11-20 20:03:13', '2026-11-20 20:04:28', NULL, '2026-11-20 12:03:06', '2026-11-20 12:04:33'),
(82, 'ASU-RES-2026-0082', 6, 2, 1, 'ddd', 'Social Science', 'Applied Research', 'dd', 'dddd', 'dd', 'dd', 'dd', 'REGULAR', 122.96, '2026-11-20', '2026-12-03', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-22 15:17:24', 'EXPEDITED', NULL, 7, '2026-11-22 15:15:40', 100, '2026-11-20 20:56:34', '2026-11-22 15:15:40', '2026-11-22 15:17:24', '2026-11-20 12:56:18', '2026-11-22 07:17:24'),
(83, 'ASU-RES-2026-0083', 6, 2, 1, 'asurems', 'Social Science', 'Basic Research', 'qwe', 'qwe', 'qwe', 'qwe', 'ched', 'REGULAR', 100.00, '2026-11-21', '2026-12-12', 'Under Review', 'FOR_URRC', 'URRC', 0, NULL, NULL, '2026-11-21 14:33:17', NULL, NULL, NULL, NULL, 0, '2026-11-21 14:32:11', NULL, NULL, '2026-11-21 06:32:04', '2026-11-21 06:33:17'),
(84, 'ASU-RES-2026-0084', 6, 18, 42, 'd', 'Social Science', 'Basic Research', 'd', 'dd', 'd', 'd', 'd', 'REGULAR', 3.00, '2026-11-22', '2026-11-25', 'Completed', 'COMPLETED', 'COMPLETED', 0, NULL, NULL, '2026-11-22 15:08:20', 'EXPEDITED', NULL, 7, '2026-11-22 15:02:37', 100, '2026-11-22 15:01:58', '2026-11-22 15:02:37', '2026-11-22 15:08:20', '2026-11-22 07:01:53', '2026-11-22 07:08:20'),
(85, 'ASU-RES-2026-0085', 6, 9, 21, 'a', 'Social Science', 'Applied Research', 'ww', 'ww', 'ww', 'ww', 'ww', 'SELF_INITIATED', 199.00, '2026-11-22', '2026-12-08', 'Submitted', 'SELF_INITIATED_UPE_SUBMITTED_AWAITING_ETHICS', 'ETHICS', 0, NULL, NULL, '2026-11-22 15:10:14', NULL, NULL, NULL, NULL, 0, '2026-11-22 15:09:53', NULL, NULL, '2026-11-22 07:09:49', '2026-11-22 07:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `research_proposals_workflow_backup`
--

CREATE TABLE `research_proposals_workflow_backup` (
  `research_id` int(10) UNSIGNED NOT NULL,
  `researcher_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `research_title` varchar(255) NOT NULL,
  `research_category` varchar(150) DEFAULT NULL,
  `research_type` varchar(100) DEFAULT NULL,
  `abstract` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `methodology` text DEFAULT NULL,
  `expected_output` text DEFAULT NULL,
  `funding_source` varchar(150) DEFAULT NULL,
  `estimated_budget` decimal(12,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `target_completion_date` date DEFAULT NULL,
  `status` enum('Draft','Submitted','Under Review','For Revision','Approved','Ongoing','Completed','Rejected','Archived') NOT NULL DEFAULT 'Draft',
  `progress_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_reviews`
--

CREATE TABLE `research_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `research_id` bigint(20) UNSIGNED NOT NULL,
  `review_stage` enum('UREC_IACUC','URRC','IN_HOUSE') NOT NULL,
  `result` varchar(64) NOT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED NOT NULL,
  `reviewed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_section_remarks`
--

CREATE TABLE `research_section_remarks` (
  `remark_id` bigint(20) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `section_key` varchar(40) NOT NULL,
  `remark_text` text NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_section_remarks`
--

INSERT INTO `research_section_remarks` (`remark_id`, `research_id`, `section_key`, `remark_text`, `created_by`, `created_at`, `updated_at`) VALUES
(7, 83, 'abstract', 'ertytrutyu', 7, '2026-11-21 06:33:11', '2026-11-21 06:33:11');

-- --------------------------------------------------------

--
-- Table structure for table `research_status_history`
--

CREATE TABLE `research_status_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `research_status_history`
--

INSERT INTO `research_status_history` (`history_id`, `research_id`, `changed_by`, `old_status`, `new_status`, `remarks`, `changed_at`) VALUES
(375, 51, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript file, and payment receipt. Manuscript No.: ASU-RES-2026-0051', '2026-10-06 14:28:54'),
(376, 51, 6, 'Draft', 'Submitted', 'Research proposal submitted and forwarded to the Research Office for workflow processing.', '2026-10-06 14:30:26'),
(377, 51, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-06 14:33:22'),
(378, 51, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-06 14:38:02'),
(379, 51, 6, 'Under Review', 'Under Review', 'Faculty researcher selected the Self-Initiated track. Two UPE certificates are now required.', '2026-10-06 14:39:19'),
(380, 51, 6, 'Under Review', 'Under Review', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-10-06 14:40:51'),
(381, 51, 6, 'Under Review', 'Under Review', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-10-06 14:41:00'),
(382, 51, 6, 'Under Review', 'Under Review', 'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.', '2026-10-06 14:42:58'),
(383, 51, 6, 'Under Review', 'Under Review', 'Terminal Report uploaded by the faculty researcher for the Self-Initiated workflow. The system-generated Permit to Publish / Present is now available.', '2026-10-06 14:43:24'),
(384, 51, 6, 'Under Review', 'Completed', 'Publication file uploaded by the faculty researcher. Self-Initiated research workflow completed.', '2026-10-06 14:44:03'),
(385, 52, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript file, and payment receipt. Manuscript No.: ASU-RES-2026-0052', '2026-10-06 15:22:00'),
(386, 52, 6, 'Draft', 'Submitted', 'Research proposal submitted and forwarded to the Research Office for workflow processing.', '2026-10-06 15:22:25'),
(387, 52, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-06 15:22:47'),
(388, 52, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Expedited. System-generated certificate is now available.', '2026-10-06 15:23:11'),
(389, 52, 6, 'Under Review', 'Under Review', 'Faculty researcher selected the normal For URRC pathway after completion of the UREC/IACUC review. Research forwarded for URRC processing.', '2026-10-06 15:23:58'),
(390, 52, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: Accepted with minor revisions for further improvement of the study.', '2026-10-06 15:26:47'),
(391, 52, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and uploaded a revised proposal document.', '2026-10-06 15:28:48'),
(392, 52, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-10-06 15:29:01'),
(393, 52, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: Accepted with minor revisions for further improvement of the study.', '2026-10-06 15:29:08'),
(394, 52, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-10-06 15:30:28'),
(395, 52, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-10-06 15:31:02'),
(396, 52, 7, 'Under Review', 'Approved', 'In-House result recorded: Passed. Research is ready for Notice to Proceed.', '2026-10-06 15:31:21'),
(397, 52, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-10-06 15:31:30'),
(411, 54, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0054', '2026-10-13 13:36:05'),
(412, 54, 6, 'Draft', 'Submitted', 'Research proposal submitted and forwarded to the Research Office for workflow processing.', '2026-10-13 13:36:32'),
(413, 54, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-13 14:15:47'),
(414, 54, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-13 14:16:00'),
(415, 54, 6, 'Under Review', 'Under Review', 'Faculty researcher selected the Self-Initiated track. Two UPE certificates are now required.', '2026-10-13 14:16:21'),
(416, 54, 6, 'Under Review', 'Under Review', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-10-13 14:17:13'),
(417, 54, 6, 'Under Review', 'Under Review', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-10-13 14:41:36'),
(418, 54, 6, 'Under Review', 'Under Review', 'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.', '2026-10-13 14:43:13'),
(419, 55, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0055', '2026-10-13 15:53:17'),
(420, 55, 6, 'Draft', 'Draft', 'Faculty updated the draft research proposal and provided a proposal document link.', '2026-10-13 15:58:28'),
(421, 55, 6, 'Draft', 'Submitted', 'Research proposal submitted and forwarded to the Research Office for workflow processing.', '2026-10-13 15:58:36'),
(422, 55, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-13 16:00:30'),
(423, 55, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-13 16:13:25'),
(424, 55, 6, 'Under Review', 'Under Review', 'Faculty researcher selected the Self-Initiated track. Two UPE certificates are now required.', '2026-10-13 16:13:34'),
(425, 55, 6, 'Under Review', 'Under Review', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-10-13 16:13:45'),
(426, 55, 6, 'Under Review', 'Under Review', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-10-13 16:37:33'),
(427, 56, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0056', '2026-10-13 16:39:40'),
(428, 56, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-13 16:39:45'),
(429, 56, 6, 'Submitted', 'Submitted', 'Faculty selected the Self-Initiated pathway after proposal submission.', '2026-10-13 16:39:48'),
(430, 56, 6, 'Submitted', 'Submitted', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-10-13 16:39:55'),
(431, 56, 6, 'Submitted', 'Submitted', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-10-13 16:39:58'),
(432, 56, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-13 17:11:58'),
(433, 56, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Expedited. System-generated certificate is now available. Remarks: 1234', '2026-10-13 17:12:37'),
(434, 56, 6, 'Under Review', 'Under Review', 'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.', '2026-10-13 17:23:28'),
(435, 56, 6, 'Under Review', 'Under Review', 'Terminal Report uploaded by the faculty researcher for the Self-Initiated workflow. Remarks: 1234 The system-generated Permit to Publish / Present is now available.', '2026-10-13 17:23:40'),
(436, 56, 6, 'Under Review', 'Completed', 'Publication file uploaded by the faculty researcher. Self-Initiated research workflow completed.', '2026-10-13 17:23:46'),
(437, 57, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0057', '2026-10-13 17:47:56'),
(438, 57, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-13 17:48:01'),
(439, 57, 6, 'Submitted', 'Under Review', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-13 17:48:06'),
(440, 57, 7, 'Under Review', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-13 18:00:08'),
(441, 57, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: 1234', '2026-10-13 18:02:41'),
(442, 58, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0058', '2026-10-13 18:47:42'),
(443, 58, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-13 18:47:48'),
(444, 58, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-13 18:48:24'),
(445, 58, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-13 18:48:39'),
(446, 58, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: tudwkhjlwd', '2026-10-13 18:52:06'),
(447, 58, 6, 'For Revision', 'Archived', 'System automatically archived this research after its 2-Minute TEST Deadline (Did Not Revise). Deadline: 2026-10-14 02:54:06.', '2026-10-13 18:54:19'),
(448, 59, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0059', '2026-10-13 19:08:27'),
(449, 59, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-13 19:08:31'),
(450, 59, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-13 19:08:37'),
(451, 59, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-13 19:09:08'),
(452, 59, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: UIKUUOU', '2026-10-13 19:09:16'),
(453, 60, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0060', '2026-10-13 19:16:52'),
(454, 60, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-13 19:16:56'),
(455, 60, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-13 19:16:59'),
(456, 60, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-13 19:32:17'),
(457, 60, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-10-13 19:32:22'),
(458, 60, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: KDJLDLA', '2026-10-13 19:32:30'),
(459, 60, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-10-13 19:51:28'),
(460, 60, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-10-13 19:51:40'),
(461, 60, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research is now for UREC/IACUC review before Notice to Proceed.', '2026-10-13 19:52:00'),
(462, 60, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-13 19:52:08'),
(463, 60, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-10-13 19:52:12'),
(464, 60, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-10-13 20:15:52'),
(465, 60, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-10-13 20:16:44'),
(466, 60, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-10-13 20:17:04'),
(467, 60, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-10-13 20:25:39'),
(468, 60, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-10-13 20:33:47'),
(469, 61, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0061', '2026-10-22 03:32:02'),
(470, 61, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-22 03:32:11'),
(471, 61, 6, 'Submitted', 'Submitted', 'Faculty selected the Self-Initiated pathway after proposal submission.', '2026-10-22 03:36:03'),
(472, 61, 6, 'Submitted', 'Submitted', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-10-22 03:36:39'),
(473, 61, 6, 'Submitted', 'Submitted', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-10-22 03:36:46'),
(474, 61, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-10-22 03:43:02'),
(475, 61, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-22 03:45:12'),
(476, 61, 6, 'Under Review', 'Under Review', 'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.', '2026-10-22 03:47:58'),
(477, 61, 6, 'Under Review', 'Under Review', 'Terminal Report uploaded by the faculty researcher for the Self-Initiated workflow. The system-generated Permit to Publish / Present is now available.', '2026-10-22 03:48:08'),
(478, 61, 6, 'Under Review', 'Completed', 'Publication file uploaded by the faculty researcher. Self-Initiated research workflow completed.', '2026-10-22 03:48:13'),
(479, 62, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0062', '2026-10-29 03:48:52'),
(480, 62, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-29 03:48:58'),
(481, 62, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-29 03:49:14'),
(482, 62, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-29 03:49:52'),
(483, 62, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: AMBOBO MO, KAMUKHA NG GAWA MO', '2026-10-29 03:50:27'),
(484, 62, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-10-29 03:51:31'),
(485, 62, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-10-29 03:51:41'),
(486, 62, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: AMBOBO MO, KAMUKHA NG GAWA MO', '2026-10-29 03:51:49'),
(487, 62, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-10-29 03:52:58'),
(488, 63, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, manuscript and payment receipt links. Manuscript No.: ASU-RES-2026-0063', '2026-10-29 03:55:11'),
(489, 63, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-10-29 03:55:16'),
(490, 63, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-10-29 03:55:21'),
(491, 62, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: GOOD JOB', '2026-10-29 03:55:40'),
(492, 62, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research is now for UREC/IACUC review before Notice to Proceed. Remarks: YOURE SO BEAUTIFUL LIKE U', '2026-10-29 03:56:10'),
(493, 62, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-29 03:56:26'),
(494, 62, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-10-29 03:56:31'),
(495, 62, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-10-29 03:57:26'),
(496, 62, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-10-29 03:58:17'),
(497, 62, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-10-29 03:58:55'),
(498, 62, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-10-29 03:59:57'),
(499, 63, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-10-29 04:15:10'),
(500, 63, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-10-29 04:15:16'),
(501, 63, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research is now for UREC/IACUC review before Notice to Proceed.', '2026-10-29 04:15:21'),
(502, 63, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-10-29 04:15:26'),
(503, 63, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-10-29 04:15:39'),
(504, 57, 6, 'For Revision', 'Archived', 'System automatically archived this research after its 21-Day Deadline (Did Not Revise). Deadline: 2026-11-04 02:02:41.', '2026-11-05 15:16:33'),
(505, 59, 6, 'For Revision', 'Archived', 'System automatically archived this research after its 21-Day Deadline (Did Not Revise). Deadline: 2026-11-04 03:09:16.', '2026-11-05 15:16:33'),
(506, 64, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0064', '2026-11-05 15:57:23'),
(507, 64, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-05 16:22:31'),
(508, 64, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-05 16:24:01'),
(509, 64, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-05 16:25:00'),
(510, 64, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: riririririr', '2026-11-05 16:42:27'),
(511, 64, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research is now for UREC/IACUC review before Notice to Proceed.', '2026-11-05 16:45:33'),
(512, 64, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-11-05 16:48:41'),
(513, 64, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-05 16:48:45'),
(514, 64, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-05 17:35:44'),
(515, 64, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-05 17:36:56'),
(516, 64, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-05 17:37:37'),
(517, 64, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-05 17:50:58'),
(518, 64, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-05 17:51:15'),
(519, 64, 7, 'Ongoing', 'Completed', 'Existing research completed after both permits were issued. [RESEARCH_COMPLETED_AFTER_BOTH_PERMITS]', '2026-11-05 18:08:09'),
(520, 64, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-05 18:39:47'),
(521, 65, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0065', '2026-11-06 12:41:10'),
(522, 65, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-06 12:41:26'),
(523, 65, 6, 'Submitted', 'Submitted', 'Faculty selected the Self-Initiated pathway after proposal submission.', '2026-11-06 12:41:52'),
(524, 65, 6, 'Submitted', 'Submitted', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-11-06 12:42:01'),
(525, 65, 6, 'Submitted', 'Submitted', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-11-06 12:42:09'),
(526, 65, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-11-06 13:07:41'),
(527, 65, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: qwerty', '2026-11-06 13:08:11'),
(528, 65, 6, 'Under Review', 'Under Review', 'Completed research / whole manuscript uploaded by the faculty researcher for the Self-Initiated workflow.', '2026-11-06 13:28:34'),
(529, 65, 6, 'Under Review', 'Under Review', 'Terminal Report uploaded by the faculty researcher for the Self-Initiated workflow. Remarks: chuchcuchcuc The system-generated Permit to Publish / Present is now available.', '2026-11-06 13:47:34'),
(530, 65, 6, 'Under Review', 'Completed', 'Publication file uploaded by the faculty researcher. Self-Initiated research workflow completed.', '2026-11-06 13:59:26'),
(531, 66, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0066', '2026-11-06 14:22:45'),
(532, 66, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-06 14:22:50'),
(533, 66, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-06 14:22:53'),
(534, 66, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-06 14:23:12'),
(535, 66, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: REVISE', '2026-11-06 14:23:54'),
(536, 66, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-11-06 15:25:01'),
(537, 66, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-06 15:25:11'),
(538, 66, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-11-06 15:25:42'),
(539, 66, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: qwerty', '2026-11-06 15:26:06'),
(540, 66, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-06 15:26:25'),
(541, 66, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: gajhuswhxikawjncokqjdiwsjcik', '2026-11-06 15:27:02'),
(542, 66, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-06 15:27:13'),
(543, 66, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: qwertyuiokjnbg', '2026-11-06 15:29:36'),
(544, 66, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-11-06 15:33:10'),
(545, 66, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-06 15:33:15'),
(546, 66, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: qwertyio', '2026-11-06 15:33:36'),
(547, 66, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-11-06 15:34:33'),
(548, 66, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-06 15:34:36'),
(549, 66, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research is now for UREC/IACUC review before Notice to Proceed. Remarks: mvkxm', '2026-11-06 15:34:52'),
(550, 66, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: sdfghjk', '2026-11-06 15:35:07'),
(551, 66, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-06 15:35:16'),
(552, 66, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-06 16:13:08'),
(553, 66, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-06 16:13:44'),
(554, 66, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-06 16:14:03'),
(555, 66, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-06 16:14:20'),
(556, 66, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH] Remarks: awertyujk', '2026-11-06 16:14:27'),
(557, 66, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-06 16:17:01'),
(558, 67, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0067', '2026-11-08 13:31:26'),
(559, 67, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-08 13:31:37'),
(560, 67, 6, 'Submitted', 'Submitted', 'Faculty selected the Self-Initiated pathway after proposal submission.', '2026-11-08 13:31:58'),
(561, 67, 6, 'Submitted', 'Submitted', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-11-08 13:32:13'),
(562, 67, 6, 'Submitted', 'Submitted', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-11-08 13:32:22'),
(563, 67, 7, 'Submitted', 'Under Review', 'Research Processing Staff started UREC/IACUC processing.', '2026-11-13 14:39:11'),
(564, 67, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: 12', '2026-11-13 15:00:31'),
(565, 68, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0068', '2026-11-19 13:54:21'),
(566, 68, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 13:54:30'),
(567, 68, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 13:54:43'),
(568, 68, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 13:55:16'),
(569, 68, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: bugre', '2026-11-19 13:55:32'),
(570, 68, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-11-19 13:55:54'),
(571, 68, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-19 13:56:04'),
(572, 68, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: bugre', '2026-11-19 13:56:10'),
(573, 68, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-19 14:11:05'),
(574, 68, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: QWERTY', '2026-11-19 14:17:16'),
(575, 68, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: QWER', '2026-11-19 14:17:23'),
(576, 68, 6, 'For Revision', 'For Revision', 'Faculty revised the research proposal and provided a revised proposal document link.', '2026-11-19 14:18:23'),
(577, 68, 6, 'For Revision', 'Submitted', 'Revised research proposal resubmitted to the Research Office for workflow processing.', '2026-11-19 14:18:30'),
(578, 68, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: QWER', '2026-11-19 14:18:36'),
(579, 68, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 14:28:55'),
(580, 69, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0069', '2026-11-19 14:31:02'),
(581, 69, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 14:31:05'),
(582, 69, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 14:31:07'),
(583, 69, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 14:31:19'),
(584, 69, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: QWERT', '2026-11-19 14:31:28'),
(585, 69, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: QWERTY', '2026-11-19 14:31:35'),
(586, 70, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0070', '2026-11-19 14:45:22'),
(587, 70, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 14:45:27'),
(588, 70, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 14:45:29'),
(589, 70, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 14:45:39'),
(590, 70, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: QWERTY', '2026-11-19 14:45:45'),
(591, 70, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 14:46:43'),
(592, 70, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: QWERTY', '2026-11-19 14:46:53'),
(593, 70, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 14:47:36'),
(594, 70, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: qwerty', '2026-11-19 14:47:48'),
(595, 70, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: qwerty', '2026-11-19 14:47:56'),
(596, 71, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0071', '2026-11-19 14:57:59'),
(597, 71, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 14:58:02'),
(598, 71, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 14:58:05'),
(599, 71, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 14:58:29'),
(600, 71, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: qwertyu', '2026-11-19 14:58:37'),
(601, 72, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0072', '2026-11-19 15:20:44'),
(602, 72, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 15:20:51'),
(603, 72, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 15:20:53'),
(604, 72, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 15:21:11'),
(605, 72, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: qwertyu', '2026-11-19 15:21:19'),
(606, 72, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 15:44:49'),
(607, 72, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 15:44:54'),
(608, 68, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: gew', '2026-11-19 15:45:06'),
(609, 72, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: swd', '2026-11-19 15:45:43'),
(610, 72, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: fed', '2026-11-19 15:45:51'),
(611, 72, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 15:46:24'),
(612, 72, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 15:46:27'),
(613, 72, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: fed', '2026-11-19 15:46:33'),
(614, 72, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 15:56:19'),
(615, 72, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 15:56:24'),
(616, 72, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review.', '2026-11-19 15:56:34'),
(617, 72, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Full-board. System-generated certificate is now available.', '2026-11-19 16:00:23'),
(618, 72, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-19 16:00:25'),
(619, 72, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-19 16:18:25'),
(620, 72, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-19 16:18:51'),
(621, 72, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-19 16:19:05'),
(622, 72, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-19 16:19:16'),
(623, 72, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-19 16:19:23'),
(624, 72, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-19 16:19:41'),
(625, 73, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0073', '2026-11-19 16:21:46'),
(626, 73, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 16:21:49'),
(627, 73, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 16:21:54'),
(628, 73, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 16:22:10'),
(629, 73, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: hjgj', '2026-11-19 16:22:27'),
(630, 73, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:23:02'),
(631, 73, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 16:23:06'),
(632, 73, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: hjgj', '2026-11-19 16:23:12'),
(633, 73, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:34:26'),
(634, 73, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 16:34:29'),
(635, 73, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: hhhh', '2026-11-19 16:34:49'),
(636, 73, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: hhh', '2026-11-19 16:34:56'),
(637, 73, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:35:11'),
(638, 73, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 16:35:14'),
(639, 73, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: hhh', '2026-11-19 16:35:20'),
(640, 73, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:42:25'),
(641, 73, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 16:42:32'),
(642, 73, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: shhsshsh', '2026-11-19 16:43:09'),
(643, 73, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: ddd', '2026-11-19 16:43:16'),
(644, 73, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-19 16:43:19'),
(645, 73, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-19 16:43:50'),
(646, 73, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-19 16:44:00'),
(647, 73, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-19 16:44:13'),
(648, 73, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-19 16:44:22'),
(649, 73, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-19 16:44:25'),
(650, 73, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-19 16:44:40'),
(651, 74, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0074', '2026-11-19 16:46:10'),
(652, 74, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 16:46:14'),
(653, 74, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 16:46:17'),
(654, 74, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 16:46:31'),
(655, 74, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 16:47:03'),
(656, 74, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:47:28'),
(657, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 16:47:30'),
(658, 74, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 16:47:36'),
(659, 74, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:48:22'),
(660, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 16:48:25'),
(661, 74, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 16:48:32'),
(662, 74, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 16:59:27'),
(663, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 16:59:29'),
(664, 74, 7, 'Submitted', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 16:59:36'),
(665, 68, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-11-19 17:00:14'),
(666, 68, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-19 17:00:17'),
(667, 74, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:00:46'),
(668, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification.', '2026-11-19 17:00:49'),
(669, 74, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-11-19 17:01:11'),
(670, 74, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 17:01:23'),
(671, 74, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:01:43'),
(672, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 17:01:46'),
(673, 74, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 17:01:52'),
(674, 74, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:11:01'),
(675, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 17:11:04'),
(676, 74, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 17:11:10'),
(677, 74, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:16:29'),
(678, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification.', '2026-11-19 17:16:31'),
(679, 74, 7, 'Submitted', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: HHH', '2026-11-19 17:16:39'),
(680, 74, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:27:45'),
(681, 74, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification. [REVISION_DOC_ID=281;ROUND=h679]', '2026-11-19 17:27:51'),
(682, 74, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: dddddd', '2026-11-19 17:28:10'),
(683, 74, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Expedited. System-generated certificate is now available. Remarks: ddd', '2026-11-19 17:28:16'),
(684, 74, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-19 17:28:36'),
(685, 74, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-19 17:29:03'),
(686, 74, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-19 17:29:13'),
(687, 74, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-19 17:29:23'),
(688, 74, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-19 17:29:30'),
(689, 74, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-19 17:29:34'),
(690, 74, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-19 17:29:47'),
(691, 75, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0075', '2026-11-19 17:30:51'),
(692, 75, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 17:30:55'),
(693, 75, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 17:30:59');
INSERT INTO `research_status_history` (`history_id`, `research_id`, `changed_by`, `old_status`, `new_status`, `remarks`, `changed_at`) VALUES
(694, 75, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 17:31:12'),
(695, 75, 7, 'Under Review', 'For Revision', 'URRC result recorded: Accepted with Revision. Researcher must submit a revised manuscript. Remarks: ffffff', '2026-11-19 17:31:18'),
(696, 75, 6, 'For Revision', 'For Revision', 'Faculty saved a URRC revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:31:46'),
(697, 75, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised URRC manuscript to RPS for verification. [REVISION_DOC_ID=286;ROUND=h695]', '2026-11-19 17:31:49'),
(698, 75, 7, 'Submitted', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: ccc', '2026-11-19 17:32:00'),
(699, 75, 7, 'Under Review', 'For Revision', 'In-House result recorded: Passed with Revision. Researcher must submit a revised manuscript. Remarks: fff', '2026-11-19 17:32:04'),
(700, 75, 6, 'For Revision', 'For Revision', 'Faculty saved an In-House revised manuscript link. Awaiting Submit Revision.', '2026-11-19 17:32:21'),
(701, 75, 6, 'For Revision', 'Submitted', 'Faculty saved and resubmitted the revised In-House manuscript to RPS for verification. [REVISION_DOC_ID=287;ROUND=h699]', '2026-11-19 17:32:23'),
(702, 75, 7, 'Submitted', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: fff', '2026-11-19 17:32:30'),
(703, 75, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-11-19 17:32:35'),
(704, 76, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0076', '2026-11-19 17:33:24'),
(705, 76, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 17:33:27'),
(706, 76, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 17:33:31'),
(707, 76, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 17:33:47'),
(708, 76, 7, 'Under Review', 'Archived', 'URRC result recorded: Rejected. Research archived. Remarks: sss', '2026-11-19 17:33:59'),
(709, 77, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0077', '2026-11-19 17:43:11'),
(710, 77, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-19 17:43:16'),
(711, 77, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-19 17:43:25'),
(712, 77, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-19 17:43:53'),
(713, 77, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-11-19 17:44:07'),
(714, 77, 7, 'Under Review', 'Archived', 'In-House result recorded: Failed. Research archived. Remarks: hjhfjsdhfjsdh', '2026-11-19 17:44:24'),
(715, 78, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0078', '2026-11-20 07:28:21'),
(716, 78, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-20 07:28:46'),
(717, 78, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-20 07:30:23'),
(718, 78, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-20 07:30:38'),
(719, 78, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: good job!', '2026-11-20 07:32:02'),
(720, 78, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: nice oone', '2026-11-20 07:32:40'),
(721, 78, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available.', '2026-11-20 07:32:56'),
(722, 78, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-20 07:33:04'),
(723, 78, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-20 07:38:19'),
(724, 78, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-20 07:38:51'),
(725, 78, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-20 07:39:21'),
(726, 78, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-20 07:39:39'),
(727, 78, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-20 07:40:08'),
(728, 78, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-20 07:40:36'),
(729, 79, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0079', '2026-11-20 08:12:06'),
(730, 79, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-20 08:12:10'),
(731, 79, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-20 08:12:14'),
(732, 79, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-20 08:12:35'),
(733, 79, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: nice one', '2026-11-20 08:12:47'),
(734, 79, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: fgtetue', '2026-11-20 08:12:55'),
(735, 79, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: fgdsrerer', '2026-11-20 08:13:02'),
(736, 79, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-20 08:13:06'),
(737, 80, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0080', '2026-11-20 08:19:05'),
(738, 80, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-20 08:19:09'),
(739, 80, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-20 08:19:13'),
(740, 80, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-20 08:20:09'),
(741, 80, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: SGDETGSW', '2026-11-20 08:20:16'),
(742, 80, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: HDHREWRETW', '2026-11-20 08:20:21'),
(743, 81, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0081', '2026-11-20 12:03:06'),
(744, 81, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-20 12:03:13'),
(745, 81, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-20 12:03:20'),
(746, 81, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-20 12:03:35'),
(747, 81, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review. Remarks: qwerty', '2026-11-20 12:04:17'),
(748, 81, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review. Remarks: aqws', '2026-11-20 12:04:23'),
(749, 81, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Exempt. System-generated certificate is now available. Remarks: sdw', '2026-11-20 12:04:28'),
(750, 81, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-20 12:04:33'),
(751, 82, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0082', '2026-11-20 12:56:18'),
(752, 82, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-20 12:56:34'),
(753, 82, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-21 06:20:21'),
(754, 82, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-21 06:20:40'),
(755, 83, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0083', '2026-11-21 06:32:04'),
(756, 83, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-21 06:32:11'),
(757, 83, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-21 06:32:46'),
(758, 83, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-21 06:33:17'),
(759, 84, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0084', '2026-11-22 07:01:53'),
(760, 84, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-22 07:01:58'),
(761, 84, 6, 'Submitted', 'Submitted', 'Faculty selected the For URRC pathway after proposal submission.', '2026-11-22 07:02:01'),
(762, 84, 7, 'Submitted', 'Under Review', 'RPS forwarded selected For URRC research from New Submissions to URRC Review.', '2026-11-22 07:02:19'),
(763, 84, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-11-22 07:02:27'),
(764, 84, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review.', '2026-11-22 07:02:33'),
(765, 84, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Expedited. System-generated certificate is now available.', '2026-11-22 07:02:37'),
(766, 84, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-22 07:02:41'),
(767, 84, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-22 07:04:49'),
(768, 84, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-22 07:05:06'),
(769, 84, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-22 07:05:23'),
(770, 84, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-22 07:07:49'),
(771, 84, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-22 07:07:52'),
(772, 84, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-22 07:08:20'),
(773, 85, 6, NULL, 'Draft', 'Research manuscript draft created with manuscript number, funding source, and manuscript link. Manuscript No.: ASU-RES-2026-0085', '2026-11-22 07:09:49'),
(774, 85, 6, 'Draft', 'Submitted', 'Research proposal submitted. Awaiting faculty pathway selection before review processing.', '2026-11-22 07:09:53'),
(775, 85, 6, 'Submitted', 'Submitted', 'Faculty selected the Self-Initiated pathway after proposal submission.', '2026-11-22 07:09:56'),
(776, 85, 6, 'Submitted', 'Submitted', 'UPE Certificate 1 uploaded by the faculty researcher. One more UPE certificate is still required.', '2026-11-22 07:10:05'),
(777, 85, 6, 'Submitted', 'Submitted', 'UPE Certificate 2 uploaded by the faculty researcher. Both required UPE certificates are now complete.', '2026-11-22 07:10:14'),
(778, 82, 7, 'Under Review', 'Under Review', 'URRC result recorded: Accepted. Research moved to In-House review.', '2026-11-22 07:15:30'),
(779, 82, 7, 'Under Review', 'Under Review', 'In-House result recorded: Passed. Research moved to UREC/IACUC review.', '2026-11-22 07:15:36'),
(780, 82, 7, 'Under Review', 'Under Review', 'UREC/IACUC result recorded: Expedited. System-generated certificate is now available.', '2026-11-22 07:15:40'),
(781, 82, 7, 'Approved', 'Ongoing', 'Notice to Proceed generated by Research Processing Staff. Research may now proceed to implementation. Remarks: Notice to Proceed generated by RPS.', '2026-11-22 07:15:43'),
(782, 82, 6, 'Ongoing', 'Ongoing', 'Completed research manuscript link submitted to the Research Office for next processing.', '2026-11-22 07:16:25'),
(783, 82, 7, 'Ongoing', 'Ongoing', 'Completed research manuscript checked and confirmed by Research Processing Staff. Researcher may proceed to terminal report submission.', '2026-11-22 07:16:35'),
(784, 82, 6, 'Ongoing', 'Ongoing', 'Terminal report link submitted to the Research Office for permit processing.', '2026-11-22 07:16:54'),
(785, 82, 7, 'Ongoing', 'Ongoing', 'Terminal Report checked and confirmed by Research Processing Staff. [TERMINAL_REPORT_RPS_CONFIRMED]', '2026-11-22 07:17:03'),
(786, 82, 7, 'Ongoing', 'Ongoing', 'Permit for publication and presentation issued by Research Processing Staff after Terminal Report review. [PERMIT_TYPE:BOTH]', '2026-11-22 07:17:06'),
(787, 82, 6, 'Ongoing', 'Completed', 'Proof of publication / presentation link submitted by the researcher. Research workflow completed.', '2026-11-22 07:17:24');

-- --------------------------------------------------------

--
-- Table structure for table `research_status_history_workflow_backup`
--

CREATE TABLE `research_status_history_workflow_backup` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_workflow_reviews`
--

CREATE TABLE `research_workflow_reviews` (
  `review_id` bigint(20) UNSIGNED NOT NULL,
  `research_id` int(10) UNSIGNED NOT NULL,
  `review_type` varchar(30) NOT NULL,
  `review_round` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `result` varchar(64) NOT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES
('academic_year', '2026-2027', 9, '2026-11-11 17:49:23'),
('institution_name', 'Aklan State University', 9, '2026-11-11 17:49:23'),
('maintenance_notice', '', 9, '2026-11-11 17:49:23'),
('office_name', 'Research & Extension Office', 9, '2026-11-11 17:49:23'),
('support_email', '', 9, '2026-11-11 17:49:23'),
('system_name', 'ASU-REMS', 9, '2026-11-11 17:49:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty','extension_coordinator','rps','extension_service_office') NOT NULL,
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `middle_name`, `last_name`, `username`, `email`, `password`, `role`, `college_id`, `department_id`, `status`, `last_login`, `created_at`, `updated_at`, `profile_photo`) VALUES
(6, 'Larra', 'Jane D.', 'Casidsid', 'larra_asurems', 'larra_asurems@example.com', '$2y$12$osRLR0wOOGqhXVQd5hD0UeuW/cEWpGUhU72fG2BY0Mml5rJg0kC3y', 'faculty', NULL, NULL, 'Active', '2026-11-22 14:30:52', '2026-10-06 09:37:11', '2026-11-22 06:30:52', 'uploads/profile_pictures/382d627b084a864ce333780a4ebe5485.png'),
(7, 'Richelle', 'Gayne A.', 'Bautista', 'rich_asurems', 'rich_asurems@example.com', '$2y$12$0vb6H2v0tQwjT6Qs2R.FSO3RuMNRCqT8S0wR5HhNHfY9qFN/HJTbq', 'rps', NULL, NULL, 'Active', '2026-11-22 15:02:10', '2026-10-06 09:37:11', '2026-11-22 07:02:10', 'uploads/profile_pictures/68886a57fcd4522894dae7c44a7e8bd9.jpg'),
(8, 'Ronelyn Mae', 'A.', 'Felipe', 'rone_asurems', 'rone_asurems@example.com', '$2y$12$W1aHMr0Fv9Gg9PaRsxTry.j87.fYUKhGDxKFof/Pggqw2QlKIBbFy', 'extension_coordinator', NULL, NULL, 'Active', '2026-11-22 15:20:12', '2026-10-06 09:37:11', '2026-11-22 07:20:12', 'uploads/profile_pictures/88cd97c5e832a0fd582c35fb1ac1c728.jpg'),
(9, 'ASU', NULL, 'REMS', 'asurems.edu.ph@gmail.com', 'asurems.edu.ph@gmail.com', '$2y$12$wZjZCGWU.SiietOFP77A4uE3ZC9m.qq199B3w/kxTXvVelbK6iKRy', 'admin', NULL, NULL, 'Active', '2026-11-21 01:20:19', '2026-10-06 09:37:11', '2026-11-20 17:20:19', NULL),
(10, 'Marie', NULL, 'Felipe', 'mamamarie737590303d9', 'mamamarie737@gmail.com', '$2y$10$m.43rZzHwYUl7qsi7TRsXesRlUs10r7ctIA2OvP2SVgRQFnClqhxS', '', NULL, NULL, 'Inactive', NULL, '2026-10-13 12:10:41', '2026-10-13 12:10:41', NULL),
(11, 'Sisa', NULL, 'Felipe', 'mssisa7bb546598', 'mssisa7@gmail.com', '$2y$10$wVzNLFHK0dELg10xjjyhmuq940b5iLVV4AekmgltDR6DP7MAI3/bW', '', NULL, NULL, 'Active', '2026-10-13 20:58:35', '2026-10-13 12:27:51', '2026-10-13 12:58:35', NULL),
(13, 'Extension', NULL, 'Service Office', 'eso_office', 'eso@example.test', '$2y$10$gh0mSBUeBpHeFvK.dr7CCebKIAL2fx1Ol./mPEhz32uuyyLBFjgwK', 'extension_service_office', NULL, NULL, 'Active', '2026-11-22 15:21:17', '2026-11-10 06:47:53', '2026-11-22 07:21:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_registration_profiles`
--

CREATE TABLE `user_registration_profiles` (
  `user_id` int(11) NOT NULL,
  `title` varchar(10) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `name_extension` varchar(20) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `civil_status` varchar(30) NOT NULL,
  `sex` varchar(20) DEFAULT NULL,
  `gender` varchar(60) DEFAULT NULL,
  `citizenship` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `street_address` varchar(190) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `contact_number` varchar(25) NOT NULL,
  `requested_role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_registration_profiles`
--

INSERT INTO `user_registration_profiles` (`user_id`, `title`, `first_name`, `middle_name`, `last_name`, `name_extension`, `birth_date`, `civil_status`, `sex`, `gender`, `citizenship`, `country`, `region`, `province`, `city`, `barangay`, `street_address`, `zip_code`, `contact_number`, `requested_role`, `created_at`) VALUES
(10, 'Ms.', 'Marie', 'Bautista', 'Felipe', '', '2002-06-20', 'Single', 'Female', NULL, 'filipino', 'Philippines', '6', 'aklan', 'kalibo', 'poblacion', 'c quipo', '5600', '09773244389', 'researcher', '2026-10-13 12:10:41'),
(11, 'Ms.', 'Sisa', '', 'Felipe', '', '2001-03-15', 'Single', 'Female', NULL, 'Filipino', 'Philippines', '6', 'Aklan', 'Kalibo', 'Poblacion', 'C Quimpo', '5600', '09773244389', 'researcher', '2026-10-13 12:27:51'),
(12, 'Mr.', 'ad', '', 'min', 'Jr.', '1993-06-15', 'Married', 'Male', NULL, 'Filipino', 'Philippines', '6', 'Aklan', 'Kalibo', 'Poblacion', 'C Quimpo', '5600', '09773244389', 'faculty', '2026-10-13 13:01:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`),
  ADD UNIQUE KEY `uq_college_name` (`college_name`),
  ADD KEY `idx_college_status` (`status`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `uq_department_college` (`college_id`,`department_name`),
  ADD KEY `idx_department_college` (`college_id`),
  ADD KEY `idx_department_status` (`status`);

--
-- Indexes for table `extension_approvals`
--
ALTER TABLE `extension_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `idx_ext_approval_program` (`extension_id`),
  ADD KEY `idx_ext_approval_stage` (`approval_stage`),
  ADD KEY `idx_ext_approval_decision` (`decision`),
  ADD KEY `idx_ext_approval_recorder` (`recorded_by`),
  ADD KEY `idx_ext_approval_program_stage` (`extension_id`,`approval_stage`);

--
-- Indexes for table `extension_calls`
--
ALTER TABLE `extension_calls`
  ADD PRIMARY KEY (`call_id`),
  ADD KEY `idx_extension_call_status` (`status`),
  ADD KEY `idx_extension_call_dates` (`opens_on`,`closes_on`),
  ADD KEY `idx_extension_call_issuer` (`issued_by`);

--
-- Indexes for table `extension_campus_unit_map`
--
ALTER TABLE `extension_campus_unit_map`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_extension_campus_unit` (`campus`,`unit_type`,`unit_id`),
  ADD KEY `idx_extension_unit` (`unit_type`,`unit_id`);

--
-- Indexes for table `extension_certifications`
--
ALTER TABLE `extension_certifications`
  ADD PRIMARY KEY (`certification_id`),
  ADD UNIQUE KEY `uq_ext_certification_program` (`extension_id`),
  ADD KEY `idx_ext_certification_recorder` (`recorded_by`),
  ADD KEY `idx_ext_certification_date` (`certification_date`);

--
-- Indexes for table `extension_coordinator_endorsements`
--
ALTER TABLE `extension_coordinator_endorsements`
  ADD PRIMARY KEY (`endorsement_id`),
  ADD UNIQUE KEY `uq_extension_coordinator_stage` (`extension_id`,`stage`),
  ADD KEY `idx_coordinator` (`coordinator_id`);

--
-- Indexes for table `extension_documents`
--
ALTER TABLE `extension_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_ext_document_program` (`extension_id`),
  ADD KEY `idx_ext_document_uploader` (`uploaded_by`);

--
-- Indexes for table `extension_endorsements`
--
ALTER TABLE `extension_endorsements`
  ADD PRIMARY KEY (`endorsement_id`),
  ADD KEY `idx_endorsement_extension` (`extension_id`),
  ADD KEY `idx_endorsement_recorder` (`recorded_by`);

--
-- Indexes for table `extension_monitoring_requests`
--
ALTER TABLE `extension_monitoring_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uq_extension_monitoring_request_code` (`request_code`),
  ADD KEY `idx_extension_monitoring_requester` (`requester_id`),
  ADD KEY `idx_extension_monitoring_status` (`status`),
  ADD KEY `idx_extension_monitoring_submitted` (`submitted_at`);

--
-- Indexes for table `extension_notices`
--
ALTER TABLE `extension_notices`
  ADD PRIMARY KEY (`notice_id`),
  ADD UNIQUE KEY `uq_extension_notice` (`extension_id`),
  ADD KEY `idx_extension_notice_issued_by` (`issued_by`);

--
-- Indexes for table `extension_programs`
--
ALTER TABLE `extension_programs`
  ADD PRIMARY KEY (`extension_id`),
  ADD KEY `idx_extension_coordinator` (`coordinator_id`),
  ADD KEY `idx_extension_college` (`college_id`),
  ADD KEY `idx_extension_department` (`department_id`),
  ADD KEY `idx_extension_status` (`status`),
  ADD KEY `idx_extension_dates` (`start_date`,`end_date`),
  ADD KEY `idx_extension_program_call` (`call_id`);

--
-- Indexes for table `extension_program_proponents`
--
ALTER TABLE `extension_program_proponents`
  ADD PRIMARY KEY (`proponent_id`),
  ADD KEY `idx_extension_program_proponents_extension` (`extension_id`,`sort_order`);

--
-- Indexes for table `extension_reviews`
--
ALTER TABLE `extension_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `idx_ext_review_program` (`extension_id`),
  ADD KEY `idx_ext_review_stage` (`review_stage`),
  ADD KEY `idx_ext_review_recorder` (`recorded_by`);

--
-- Indexes for table `extension_revisions`
--
ALTER TABLE `extension_revisions`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `idx_ext_revision_program` (`extension_id`),
  ADD KEY `idx_ext_revision_review` (`review_id`),
  ADD KEY `idx_ext_revision_submitter` (`submitted_by`);

--
-- Indexes for table `extension_service_requests`
--
ALTER TABLE `extension_service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uq_extension_service_request_code` (`request_code`),
  ADD KEY `idx_extension_service_requester` (`requester_id`),
  ADD KEY `idx_extension_service_status` (`status`),
  ADD KEY `idx_extension_service_submitted` (`submitted_at`);

--
-- Indexes for table `extension_status_history`
--
ALTER TABLE `extension_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_ext_history_program` (`extension_id`),
  ADD KEY `idx_ext_history_user` (`changed_by`);

--
-- Indexes for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_activity_login_at` (`login_at`),
  ADD KEY `idx_activity_user_login` (`user_id`,`login_at`),
  ADD KEY `idx_activity_role_login` (`user_role`,`login_at`);

--
-- Indexes for table `manuscript_sequences`
--
ALTER TABLE `manuscript_sequences`
  ADD PRIMARY KEY (`sequence_year`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notifications_created_at` (`created_at`),
  ADD KEY `idx_notifications_module_record` (`module`,`record_id`);

--
-- Indexes for table `research_documents`
--
ALTER TABLE `research_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `fk_document_user` (`uploaded_by`),
  ADD KEY `idx_document_research` (`research_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `research_document_view_confirmations`
--
ALTER TABLE `research_document_view_confirmations`
  ADD PRIMARY KEY (`document_id`,`user_id`),
  ADD KEY `idx_document_confirm_research` (`research_id`),
  ADD KEY `idx_document_confirm_user` (`user_id`);

--
-- Indexes for table `research_evaluations`
--
ALTER TABLE `research_evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `idx_evaluation_research` (`research_id`),
  ADD KEY `idx_evaluation_evaluator` (`evaluator_id`);

--
-- Indexes for table `research_progress`
--
ALTER TABLE `research_progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD KEY `fk_progress_user` (`submitted_by`),
  ADD KEY `idx_progress_research` (`research_id`),
  ADD KEY `idx_progress_date` (`reporting_date`);

--
-- Indexes for table `research_progress_updates`
--
ALTER TABLE `research_progress_updates`
  ADD PRIMARY KEY (`progress_id`),
  ADD KEY `idx_progress_research` (`research_id`),
  ADD KEY `idx_progress_researcher` (`researcher_id`),
  ADD KEY `idx_progress_reporting_date` (`reporting_date`),
  ADD KEY `idx_progress_percentage` (`progress_percentage`);

--
-- Indexes for table `research_projects`
--
ALTER TABLE `research_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `manuscript_no` (`manuscript_no`),
  ADD KEY `idx_researcher` (`researcher_id`),
  ADD KEY `idx_status` (`current_status`),
  ADD KEY `idx_archived` (`is_archived`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `research_proposals`
--
ALTER TABLE `research_proposals`
  ADD PRIMARY KEY (`research_id`),
  ADD UNIQUE KEY `uq_research_manuscript_no` (`manuscript_no`),
  ADD KEY `fk_research_department` (`department_id`),
  ADD KEY `idx_researcher_id` (`researcher_id`),
  ADD KEY `idx_research_status` (`status`),
  ADD KEY `idx_research_college` (`college_id`),
  ADD KEY `idx_research_created` (`created_at`),
  ADD KEY `idx_research_workflow_status` (`workflow_status`),
  ADD KEY `idx_research_workflow_stage` (`workflow_stage`),
  ADD KEY `idx_research_is_archived` (`is_archived`);

--
-- Indexes for table `research_proposals_workflow_backup`
--
ALTER TABLE `research_proposals_workflow_backup`
  ADD PRIMARY KEY (`research_id`),
  ADD KEY `fk_research_department` (`department_id`),
  ADD KEY `idx_researcher_id` (`researcher_id`),
  ADD KEY `idx_research_status` (`status`),
  ADD KEY `idx_research_college` (`college_id`),
  ADD KEY `idx_research_created` (`created_at`);

--
-- Indexes for table `research_reviews`
--
ALTER TABLE `research_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_research` (`research_id`,`review_stage`,`reviewed_at`);

--
-- Indexes for table `research_section_remarks`
--
ALTER TABLE `research_section_remarks`
  ADD PRIMARY KEY (`remark_id`),
  ADD UNIQUE KEY `uq_research_section_remark` (`research_id`,`section_key`),
  ADD KEY `idx_section_remark_creator` (`created_by`);

--
-- Indexes for table `research_status_history`
--
ALTER TABLE `research_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_user` (`changed_by`),
  ADD KEY `idx_history_research` (`research_id`),
  ADD KEY `idx_history_changed_at` (`changed_at`);

--
-- Indexes for table `research_status_history_workflow_backup`
--
ALTER TABLE `research_status_history_workflow_backup`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_user` (`changed_by`),
  ADD KEY `idx_history_research` (`research_id`),
  ADD KEY `idx_history_changed_at` (`changed_at`);

--
-- Indexes for table `research_workflow_reviews`
--
ALTER TABLE `research_workflow_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `idx_workflow_review_research` (`research_id`),
  ADD KEY `idx_workflow_review_type` (`review_type`),
  ADD KEY `idx_workflow_review_result` (`result`),
  ADD KEY `idx_workflow_review_reviewer` (`reviewed_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `idx_system_settings_updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_college` (`college_id`),
  ADD KEY `idx_users_department` (`department_id`),
  ADD KEY `idx_users_created_at` (`created_at`);

--
-- Indexes for table `user_registration_profiles`
--
ALTER TABLE `user_registration_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `extension_approvals`
--
ALTER TABLE `extension_approvals`
  MODIFY `approval_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `extension_calls`
--
ALTER TABLE `extension_calls`
  MODIFY `call_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `extension_campus_unit_map`
--
ALTER TABLE `extension_campus_unit_map`
  MODIFY `assignment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `extension_certifications`
--
ALTER TABLE `extension_certifications`
  MODIFY `certification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `extension_coordinator_endorsements`
--
ALTER TABLE `extension_coordinator_endorsements`
  MODIFY `endorsement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `extension_documents`
--
ALTER TABLE `extension_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `extension_endorsements`
--
ALTER TABLE `extension_endorsements`
  MODIFY `endorsement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `extension_monitoring_requests`
--
ALTER TABLE `extension_monitoring_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `extension_notices`
--
ALTER TABLE `extension_notices`
  MODIFY `notice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `extension_programs`
--
ALTER TABLE `extension_programs`
  MODIFY `extension_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `extension_program_proponents`
--
ALTER TABLE `extension_program_proponents`
  MODIFY `proponent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `extension_reviews`
--
ALTER TABLE `extension_reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `extension_revisions`
--
ALTER TABLE `extension_revisions`
  MODIFY `revision_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `extension_service_requests`
--
ALTER TABLE `extension_service_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `extension_status_history`
--
ALTER TABLE `extension_status_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1036;

--
-- AUTO_INCREMENT for table `research_documents`
--
ALTER TABLE `research_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=309;

--
-- AUTO_INCREMENT for table `research_evaluations`
--
ALTER TABLE `research_evaluations`
  MODIFY `evaluation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `research_progress`
--
ALTER TABLE `research_progress`
  MODIFY `progress_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_progress_updates`
--
ALTER TABLE `research_progress_updates`
  MODIFY `progress_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `research_projects`
--
ALTER TABLE `research_projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_proposals`
--
ALTER TABLE `research_proposals`
  MODIFY `research_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `research_proposals_workflow_backup`
--
ALTER TABLE `research_proposals_workflow_backup`
  MODIFY `research_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `research_reviews`
--
ALTER TABLE `research_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_section_remarks`
--
ALTER TABLE `research_section_remarks`
  MODIFY `remark_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `research_status_history`
--
ALTER TABLE `research_status_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=788;

--
-- AUTO_INCREMENT for table `research_status_history_workflow_backup`
--
ALTER TABLE `research_status_history_workflow_backup`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `research_workflow_reviews`
--
ALTER TABLE `research_workflow_reviews`
  MODIFY `review_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_approvals`
--
ALTER TABLE `extension_approvals`
  ADD CONSTRAINT `fk_ext_approval_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_approval_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_calls`
--
ALTER TABLE `extension_calls`
  ADD CONSTRAINT `fk_extension_call_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_certifications`
--
ALTER TABLE `extension_certifications`
  ADD CONSTRAINT `fk_ext_certification_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_certification_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_documents`
--
ALTER TABLE `extension_documents`
  ADD CONSTRAINT `fk_ext_document_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_document_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_endorsements`
--
ALTER TABLE `extension_endorsements`
  ADD CONSTRAINT `fk_endorsement_extension` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_endorsement_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_programs`
--
ALTER TABLE `extension_programs`
  ADD CONSTRAINT `fk_extension_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_extension_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_extension_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_extension_program_call` FOREIGN KEY (`call_id`) REFERENCES `extension_calls` (`call_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `extension_reviews`
--
ALTER TABLE `extension_reviews`
  ADD CONSTRAINT `fk_ext_review_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_review_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_revisions`
--
ALTER TABLE `extension_revisions`
  ADD CONSTRAINT `fk_ext_revision_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_revision_review` FOREIGN KEY (`review_id`) REFERENCES `extension_reviews` (`review_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_revision_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `extension_status_history`
--
ALTER TABLE `extension_status_history`
  ADD CONSTRAINT `fk_ext_history_program` FOREIGN KEY (`extension_id`) REFERENCES `extension_programs` (`extension_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ext_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_documents`
--
ALTER TABLE `research_documents`
  ADD CONSTRAINT `fk_document_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_document_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_evaluations`
--
ALTER TABLE `research_evaluations`
  ADD CONSTRAINT `fk_evaluation_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evaluation_user` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_progress`
--
ALTER TABLE `research_progress`
  ADD CONSTRAINT `fk_progress_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progress_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_progress_updates`
--
ALTER TABLE `research_progress_updates`
  ADD CONSTRAINT `fk_progress_update_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progress_update_researcher` FOREIGN KEY (`researcher_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_proposals`
--
ALTER TABLE `research_proposals`
  ADD CONSTRAINT `fk_research_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_research_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_research_researcher` FOREIGN KEY (`researcher_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_reviews`
--
ALTER TABLE `research_reviews`
  ADD CONSTRAINT `fk_review_research` FOREIGN KEY (`research_id`) REFERENCES `research_projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_section_remarks`
--
ALTER TABLE `research_section_remarks`
  ADD CONSTRAINT `fk_section_remark_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_section_remark_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `research_status_history`
--
ALTER TABLE `research_status_history`
  ADD CONSTRAINT `fk_history_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `research_workflow_reviews`
--
ALTER TABLE `research_workflow_reviews`
  ADD CONSTRAINT `fk_workflow_review_research` FOREIGN KEY (`research_id`) REFERENCES `research_proposals` (`research_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_workflow_review_user` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
