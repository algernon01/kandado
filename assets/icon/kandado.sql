-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 02:34 PM
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
-- Database: `kandado`
--

-- --------------------------------------------------------

--
-- Table structure for table `locker_history`
--

CREATE TABLE `locker_history` (
  `id` int(11) NOT NULL,
  `locker_number` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `user_fullname` varchar(100) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locker_history`
--

INSERT INTO `locker_history` (`id`, `locker_number`, `code`, `user_fullname`, `user_email`, `expires_at`, `duration_minutes`, `used_at`, `archived`) VALUES
(1, 1, '115232', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:38:52', 60, '2025-09-29 03:39:50', 0),
(2, 1, '318083', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:40:05', 60, '2025-09-29 03:45:02', 0),
(3, 1, '811282', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:54:34', 60, '2025-09-29 03:55:17', 0),
(4, 1, '729569', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:00:33', 5, '2025-09-29 04:00:33', 0),
(5, 1, '842975', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:19:40', 5, '2025-09-29 04:19:41', 0),
(6, 1, '424454', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:24:59', 5, '2025-09-29 04:24:14', 0),
(7, 1, '524998', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:30:39', 5, '2025-09-29 04:30:39', 0),
(8, 1, '222493', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:35:58', 5, '2025-09-29 04:33:46', 0),
(9, 1, '519257', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:39:24', 5, '2025-09-29 04:39:24', 0),
(10, 1, '559657', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:45:20', 5, '2025-09-29 04:45:20', 0),
(11, 1, '691567', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 12:55:00', 5, '2025-09-29 04:55:00', 0),
(12, 1, '394457', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 13:08:01', 5, '2025-09-29 06:23:00', 0),
(13, 1, '944190', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 14:53:40', 5, '2025-09-29 06:53:41', 0),
(14, 2, '834460', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 15:18:48', 20, '2025-09-29 06:59:03', 0),
(15, 2, '785178', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:04:22', 5, '2025-09-29 07:01:37', 0),
(16, 1, '420622', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 15:02:18', 5, '2025-09-29 07:01:38', 0),
(17, 1, '345912', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 15:09:16', 5, '2025-09-29 07:04:44', 0),
(18, 2, '099829', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:09:16', 5, '2025-09-29 07:04:50', 0),
(19, 2, '764772', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:09:59', 5, '2025-09-29 07:05:27', 0),
(20, 1, '252931', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 15:09:59', 5, '2025-09-29 07:07:19', 0),
(21, 1, '918291', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:14:42', 5, '2025-09-29 07:10:02', 0),
(22, 2, '328844', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 15:14:42', 5, '2025-09-29 07:10:07', 0),
(23, 4, '588255', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:15:19', 5, '2025-09-29 07:10:46', 0),
(24, 1, '640655', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:16:43', 5, '2025-09-29 07:13:10', 0),
(25, 2, '076033', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-09-29 15:25:05', 5, '2025-09-29 07:24:35', 0),
(26, 1, '990298', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 15:24:56', 5, '2025-09-29 07:24:57', 0),
(27, 2, '361531', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 15:49:54', 25, '2025-09-29 07:36:43', 0),
(28, 1, '176954', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 17:07:15', 5, '2025-09-29 09:02:39', 0),
(29, 2, '594667', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 17:07:15', 5, '2025-09-29 09:02:39', 0),
(30, 2, '143054', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 17:07:55', 5, '2025-09-29 09:03:07', 0),
(31, 1, '139474', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 17:07:54', 5, '2025-09-29 09:03:09', 0),
(32, 2, '891432', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 17:08:18', 5, '2025-09-29 09:03:36', 0),
(33, 1, '255543', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 17:08:18', 5, '2025-09-29 09:03:38', 0),
(34, 1, '214315', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 17:08:51', 5, '2025-09-29 09:04:05', 0),
(35, 2, '231925', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:05:22', 5, '2025-09-29 10:00:38', 0),
(36, 1, '885832', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:05:22', 5, '2025-09-29 10:00:40', 0),
(37, 1, '507278', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:05:53', 5, '2025-09-29 10:01:13', 0),
(38, 2, '382757', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:05:53', 5, '2025-09-29 10:01:13', 0),
(39, 2, '249677', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:14:36', 5, '2025-09-29 10:09:53', 0),
(40, 1, '718671', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:14:36', 5, '2025-09-29 10:09:54', 0),
(41, 1, '758459', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:15:09', 5, '2025-09-29 10:10:25', 0),
(42, 2, '095528', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:15:09', 5, '2025-09-29 10:10:25', 0),
(43, 4, '156186', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:15:35', 5, '2025-09-29 10:10:53', 0),
(44, 3, '921417', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:15:35', 5, '2025-09-29 10:10:53', 0),
(45, 1, '964468', 'allen Sanchez', 'algernonangeles01@gmail.com', '2025-09-29 18:31:08', 20, '2025-09-30 16:53:08', 0),
(46, 2, '149997', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-09-29 18:31:08', 20, '2025-09-30 16:53:42', 0),
(47, 1, '524512', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:32:12', 20, '2025-10-01 03:12:34', 0),
(48, 2, '569880', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:33:18', 20, '2025-10-01 03:13:39', 0),
(49, 1, '282780', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:33:55', 20, '2025-10-01 03:14:13', 0),
(50, 2, '782415', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:33:56', 20, '2025-10-01 03:14:18', 0),
(51, 3, '417806', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 11:20:53', 5, '2025-10-01 03:16:17', 0),
(52, 1, '892416', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:36:50', 20, '2025-10-01 03:17:08', 0),
(53, 2, '623311', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:36:51', 20, '2025-10-01 03:17:08', 0),
(54, 1, '076976', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:38:42', 20, '2025-10-01 03:18:56', 0),
(55, 1, '805827', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:48:10', 20, '2025-10-01 03:28:23', 0),
(56, 2, '103812', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:48:30', 20, '2025-10-01 03:35:11', 0),
(57, 1, '294317', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:48:30', 20, '2025-10-01 03:39:12', 0),
(58, 1, '688685', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:59:21', 20, '2025-10-01 03:39:36', 0),
(59, 2, '971403', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:59:44', 20, '2025-10-01 03:40:07', 0),
(60, 1, '985360', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:59:44', 20, '2025-10-01 03:40:11', 0),
(61, 1, '711892', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:00:25', 20, '2025-10-01 03:40:43', 0),
(62, 2, '478630', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:00:25', 20, '2025-10-01 03:40:45', 0),
(63, 1, '125705', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:01:02', 20, '2025-10-01 03:41:20', 0),
(64, 2, '123296', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:01:33', 20, '2025-10-01 03:41:55', 0),
(65, 2, '036985', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:02:05', 20, '2025-10-01 03:42:20', 0),
(66, 2, '585295', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:48:19', 5, '2025-10-01 03:43:35', 0),
(67, 1, '181255', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:48:20', 5, '2025-10-01 03:43:40', 0),
(68, 2, '680075', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:48:53', 5, '2025-10-01 03:44:10', 0),
(69, 1, '046119', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:48:53', 5, '2025-10-01 03:44:12', 0),
(70, 3, '561943', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 11:49:58', 5, '2025-10-01 03:45:32', 0),
(71, 2, '053656', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 11:50:42', 5, '2025-10-01 03:50:42', 0),
(72, 1, '290776', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 11:50:43', 5, '2025-10-01 03:50:46', 0),
(73, 1, '031284', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:04:44', 5, '2025-10-01 04:00:10', 0),
(74, 2, '209967', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:05:20', 5, '2025-10-01 04:00:39', 0),
(75, 1, '066501', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:05:53', 5, '2025-10-01 04:01:12', 0),
(76, 1, '775739', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:06:22', 5, '2025-10-01 04:01:42', 0),
(77, 2, '057066', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:06:22', 5, '2025-10-01 04:01:42', 0),
(78, 1, '820523', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:07:23', 5, '2025-10-01 04:02:36', 0),
(79, 1, '900017', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:07:45', 5, '2025-10-01 04:03:03', 0),
(80, 2, '396451', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:07:45', 5, '2025-10-01 04:03:03', 0),
(81, 2, '511102', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:23:58', 5, '2025-10-01 04:19:17', 0),
(82, 1, '706554', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:23:58', 5, '2025-10-01 04:19:18', 0),
(83, 1, '657760', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:24:46', 5, '2025-10-01 04:20:00', 0),
(84, 1, '713200', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:25:15', 5, '2025-10-01 04:20:40', 0),
(85, 2, '011580', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 12:25:56', 5, '2025-10-01 04:21:16', 0),
(86, 2, '315337', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 12:26:28', 5, '2025-10-01 04:21:49', 0),
(87, 2, '150368', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:29:01', 5, '2025-10-01 04:24:38', 0),
(88, 1, '734550', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:35:01', 5, '2025-10-01 04:30:16', 0),
(89, 2, '249108', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:50:12', 5, '2025-10-01 04:45:40', 0),
(90, 1, '464386', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:50:12', 5, '2025-10-01 04:46:22', 0),
(91, 1, '152531', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 12:54:22', 5, '2025-10-01 04:54:22', 0),
(92, 2, '184239', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:54:22', 5, '2025-10-01 04:54:24', 0),
(93, 2, '184239', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 12:54:22', 5, '2025-10-01 04:54:25', 0),
(94, 1, '885810', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:12:02', 5, '2025-10-01 05:07:37', 0),
(95, 2, '739322', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:12:02', 5, '2025-10-01 05:11:08', 0),
(96, 3, '517871', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:13:01', 5, '2025-10-01 05:11:32', 0),
(97, 1, '443480', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:20:28', 5, '2025-10-01 05:16:20', 0),
(98, 1, '059593', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:21:25', 5, '2025-10-01 05:16:35', 0),
(99, 1, '324863', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:24:12', 5, '2025-10-01 05:19:22', 0),
(100, 1, '556749', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:26:44', 5, '2025-10-01 05:21:56', 0),
(101, 1, '578743', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:27:27', 5, '2025-10-01 05:23:06', 0),
(102, 2, '721298', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:27:27', 5, '2025-10-01 05:23:07', 0),
(103, 1, '673253', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:30:33', 5, '2025-10-01 05:25:50', 0),
(104, 1, '364499', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:31:15', 5, '2025-10-01 05:26:31', 0),
(105, 2, '981545', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:31:15', 5, '2025-10-01 05:26:35', 0),
(106, 3, '174118', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 13:32:41', 5, '2025-10-01 05:28:01', 0),
(107, 2, '231390', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:32:41', 5, '2025-10-01 05:28:01', 0),
(108, 1, '023172', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:32:41', 5, '2025-10-01 05:28:02', 0),
(109, 1, '597771', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:33:16', 5, '2025-10-01 05:36:37', 0),
(110, 2, '726426', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:33:16', 5, '2025-10-01 05:36:42', 0),
(111, 1, '338402', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:43:56', 5, '2025-10-01 05:39:16', 0),
(112, 2, '201469', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:44:38', 5, '2025-10-01 05:40:22', 0),
(113, 1, '901006', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:44:38', 5, '2025-10-01 05:40:25', 0),
(114, 1, '919460', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:45:50', 5, '2025-10-01 05:41:19', 0),
(115, 2, '364111', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:45:50', 5, '2025-10-01 05:41:20', 0),
(116, 1, '593579', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:46:37', 5, '2025-10-01 05:41:55', 0),
(117, 2, '318174', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:46:37', 5, '2025-10-01 05:41:57', 0),
(118, 3, '362364', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 13:46:37', 5, '2025-10-01 05:41:59', 0),
(119, 3, '104731', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 13:47:12', 5, '2025-10-01 05:42:24', 0),
(120, 2, '692977', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:47:12', 5, '2025-10-01 05:42:27', 0),
(121, 3, '534535', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 13:47:39', 5, '2025-10-01 05:42:59', 0),
(122, 1, '595050', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:47:39', 5, '2025-10-01 05:47:39', 0),
(123, 2, '387820', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:47:38', 5, '2025-10-01 05:47:41', 0),
(124, 2, '387820', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:47:38', 5, '2025-10-01 05:47:44', 0),
(125, 1, '338549', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:55:44', 5, '2025-10-01 05:51:04', 0),
(126, 2, '455515', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:56:51', 5, '2025-10-01 05:53:47', 0),
(127, 1, '577733', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 13:59:11', 5, '2025-10-01 05:56:02', 0),
(128, 3, '667999', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 13:59:11', 5, '2025-10-01 05:56:05', 0),
(129, 2, '120198', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 13:59:11', 5, '2025-10-01 05:56:06', 0),
(130, 4, '179245', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 14:01:21', 5, '2025-10-01 05:56:43', 0),
(131, 1, '804158', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 14:01:21', 5, '2025-10-01 05:56:48', 0),
(132, 2, '542313', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 14:01:21', 5, '2025-10-01 05:57:17', 0),
(133, 1, '622614', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 14:03:37', 5, '2025-10-01 05:59:00', 0),
(134, 2, '873745', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 14:03:37', 5, '2025-10-01 05:59:04', 0),
(135, 3, '194834', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-01 14:03:37', 5, '2025-10-01 06:00:22', 0),
(136, 4, '676334', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 14:03:37', 5, '2025-10-01 06:00:24', 0),
(137, 2, '333949', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 14:05:40', 5, '2025-10-01 06:05:41', 0),
(138, 2, '333949', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 14:05:40', 5, '2025-10-01 06:05:41', 0),
(139, 1, '507560', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 14:11:41', 5, '2025-10-01 06:11:43', 0),
(140, 1, '501676', 'Admin Account', 'admin@gmail.com', '2025-10-01 14:54:02', 5, '2025-10-01 06:54:03', 0),
(141, 2, '769296', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 14:54:02', 5, '2025-10-01 06:54:04', 0),
(142, 2, '769296', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 14:54:02', 5, '2025-10-01 06:54:07', 0),
(143, 1, '843231', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 15:38:17', 5, '2025-10-01 07:38:17', 0),
(144, 4, '310663', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 16:26:57', 5, '2025-10-01 08:47:15', 0),
(145, 1, '392570', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:12:19', 5, '2025-10-01 09:13:00', 0),
(146, 1, '392570', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:12:19', 5, '2025-10-01 09:13:00', 0),
(147, 1, '219378', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:18:12', 5, '2025-10-01 09:18:13', 0),
(148, 1, '219378', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:18:12', 5, '2025-10-01 09:18:13', 0),
(149, 2, '614465', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-01 17:25:54', 5, '2025-10-01 09:21:10', 0),
(150, 1, '690458', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 17:25:54', 5, '2025-10-01 09:25:54', 0),
(151, 3, '672974', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:25:54', 5, '2025-10-01 09:25:55', 0),
(152, 3, '672974', 'Allen Sanchez', 'allensnchz1424@gmail.com', '2025-10-01 17:25:54', 5, '2025-10-01 09:25:58', 0),
(153, 3, '900010', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 17:40:07', 5, '2025-10-01 09:35:38', 0),
(154, 1, '775697', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 17:40:43', 5, '2025-10-01 09:40:45', 0),
(155, 1, '216807', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 17:51:20', 5, '2025-10-01 09:47:06', 0),
(156, 1, '946706', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 19:17:09', 5, '2025-10-01 11:17:11', 0),
(157, 1, '383619', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 19:22:32', 5, '2025-10-01 11:32:41', 0),
(158, 1, '277136', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 19:37:58', 5, '2025-10-01 11:40:06', 0),
(159, 1, '035975', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-01 19:45:30', 5, '2025-10-01 11:43:05', 0),
(160, 1, '749040', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 09:32:52', 5, '2025-10-02 01:28:40', 0),
(161, 1, '155889', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 10:14:20', 5, '2025-10-02 02:27:57', 0),
(162, 1, '508796', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 10:35:10', 5, '2025-10-02 02:30:40', 0),
(163, 1, '493299', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 10:35:50', 5, '2025-10-02 02:35:18', 0),
(164, 2, '876191', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-02 10:35:49', 5, '2025-10-02 02:35:49', 0),
(165, 2, '876191', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-02 10:35:49', 5, '2025-10-02 02:35:49', 0),
(166, 3, '423160', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-02 10:41:09', 5, '2025-10-02 02:36:20', 0),
(167, 4, '754958', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-02 10:41:25', 5, '2025-10-02 02:36:36', 0),
(168, 1, '381102', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 10:42:47', 5, '2025-10-02 02:42:48', 0),
(169, 1, '544592', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-02 10:54:45', 5, '2025-10-02 02:51:34', 0),
(170, 1, '904524', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-02 10:56:54', 5, '2025-10-02 04:40:58', 0),
(171, 1, '420931', 'aaron manaloto', 'akmanaloto17@gmail.com', '2025-10-02 13:00:58', 5, '2025-10-02 05:00:58', 0),
(172, 1, '430557', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-04 14:26:50', 5, '2025-10-04 06:22:48', 0),
(173, 1, '493090', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-04 14:28:09', 5, '2025-10-04 06:26:45', 0),
(174, 1, '446873', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-04 14:47:00', 20, '2025-10-04 06:28:23', 0),
(175, 1, '753043', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-04 14:33:39', 5, '2025-10-04 06:29:08', 0),
(176, 1, '018262', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 09:44:24', 5, '2025-10-06 01:41:46', 0),
(177, 1, '179975', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 10:18:12', 20, '2025-10-06 02:07:41', 0),
(178, 3, '674218', 'L Rimorin', 'l.rimorin40694@mcc.edu.ph', '2025-10-06 10:53:39', 30, '2025-10-06 02:24:41', 0),
(179, 2, '572126', 'EL Nevi', 'melllllari@gmail.com', '2025-10-06 10:27:53', 5, '2025-10-06 02:24:43', 0),
(180, 4, '453873', 'Arriza Aparejo', 'a.aparejo40720@mcc.edu.ph', '2025-10-06 10:27:58', 5, '2025-10-06 02:24:45', 0),
(181, 1, '196265', 'Arriza Aparejo', 'a.aparejo40720@mcc.edu.ph', '2025-10-06 10:35:47', 10, '2025-10-06 02:27:34', 0),
(182, 1, '543550', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 12:19:35', 5, '2025-10-06 04:19:35', 0),
(183, 1, '869182', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 17:09:33', 30, '2025-10-06 08:40:11', 0),
(184, 2, '116282', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 16:45:20', 5, '2025-10-06 08:40:52', 0),
(185, 3, '901360', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 16:46:27', 5, '2025-10-06 08:42:09', 0),
(186, 4, '440990', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-06 16:47:15', 5, '2025-10-06 08:42:35', 0),
(187, 1, '109007', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 09:34:04', 5, '2025-10-08 01:38:50', 0),
(188, 1, '755476', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 09:49:34', 5, '2025-10-08 01:44:52', 0),
(189, 1, '289916', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:04:56', 5, '2025-10-08 02:00:10', 0),
(190, 1, '668290', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:24:23', 5, '2025-10-08 02:19:36', 0),
(191, 1, '057057', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:26:19', 5, '2025-10-08 02:21:30', 0),
(192, 1, '331716', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:27:23', 5, '2025-10-08 02:22:37', 0),
(193, 1, '336283', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:28:30', 5, '2025-10-08 02:24:05', 0),
(194, 1, '978117', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:51:10', 5, '2025-10-08 02:46:22', 0),
(195, 1, '983387', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:51:53', 5, '2025-10-08 02:47:08', 0),
(196, 1, '003655', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:52:38', 5, '2025-10-08 02:47:50', 0),
(197, 1, '947121', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 10:52:59', 5, '2025-10-08 02:48:12', 0),
(198, 1, '954282', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 11:43:49', 30, '2025-10-08 03:14:01', 0),
(199, 1, '786189', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 11:19:34', 5, '2025-10-08 03:14:48', 0),
(200, 1, '460426', 'algernon angeles', 'algernonangeles01@gmail.com', '2025-10-08 11:20:06', 5, '2025-10-08 03:15:19', 0),
(201, 1, '415128', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-08 16:28:20', 5, '2025-10-08 08:38:39', 0),
(202, 1, '060970', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-09 14:21:58', 5, '2025-10-09 06:23:25', 0),
(203, 2, '060970', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-09 14:31:32', 5, '2025-10-09 06:33:11', 0),
(204, 1, '920860', 'Al Angeles', 'algernonangeles3022@gmail.com', '2025-10-09 15:42:31', 5, '2025-10-09 07:37:54', 0),
(205, 1, '065324', 'Allen Sanchez', 'allensanchez1628@gmail.com', '2025-10-09 16:38:20', 5, '2025-10-09 08:35:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `locker_qr`
--

CREATE TABLE `locker_qr` (
  `id` int(11) NOT NULL,
  `locker_number` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL,
  `status` enum('available','occupied','hold') NOT NULL DEFAULT 'available',
  `maintenance` tinyint(1) NOT NULL DEFAULT 0,
  `item` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notify30_sent` tinyint(1) NOT NULL DEFAULT 0,
  `notify15_sent` tinyint(1) NOT NULL DEFAULT 0,
  `notify10_sent` tinyint(1) NOT NULL DEFAULT 0,
  `notify2_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locker_qr`
--

INSERT INTO `locker_qr` (`id`, `locker_number`, `user_id`, `code`, `status`, `maintenance`, `item`, `expires_at`, `duration_minutes`, `notify30_sent`, `notify15_sent`, `notify10_sent`, `notify2_sent`) VALUES
(1, 1, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0, 0),
(2, 2, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0, 0),
(3, 3, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0, 0),
(4, 4, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0, 0);

--
-- Triggers `locker_qr`
--
DELIMITER $$
CREATE TRIGGER `locker_qr_after_update_hold` AFTER UPDATE ON `locker_qr` FOR EACH ROW BEGIN
  /* Only when status changes to 'hold' and there is an owner */
  IF NEW.status = 'hold' AND (OLD.status <> 'hold') AND NEW.user_id IS NOT NULL THEN

    /* 1) record the 'hold' event */
    INSERT INTO violation_events (user_id, locker_number, event, details)
    VALUES (NEW.user_id, NEW.locker_number, 'hold', 'Locker moved to HOLD (item detected / expired).');

    /* 2) increment strike bucket */
    INSERT INTO user_bans (user_id, offense_count, holds_since_last_offense, banned_until, is_permanent)
    VALUES (NEW.user_id, 0, 1, NULL, 0)
    ON DUPLICATE KEY UPDATE holds_since_last_offense = holds_since_last_offense + 1;

    /* 3) escalate when 3 holds reached */
    BEGIN
      DECLARE v_off INT DEFAULT 0;
      DECLARE v_hsl INT DEFAULT 0;
      DECLARE v_perm TINYINT DEFAULT 0;

      SELECT offense_count, holds_since_last_offense, is_permanent
        INTO v_off, v_hsl, v_perm
      FROM user_bans
      WHERE user_id = NEW.user_id;

      IF v_perm = 0 AND v_hsl >= 3 THEN
        SET v_off = v_off + 1;

        IF v_off = 1 THEN
          /* 1st offense: ban 1 day */
          UPDATE user_bans
             SET offense_count = v_off,
                 holds_since_last_offense = 0,
                 banned_until = DATE_ADD(NOW(), INTERVAL 1 DAY)
           WHERE user_id = NEW.user_id;
          INSERT INTO violation_events (user_id, locker_number, event, details)
          VALUES (NEW.user_id, NEW.locker_number, 'ban_1d', 'Reached 3 holds: 1st offense (ban 1 day).');

        ELSEIF v_off = 2 THEN
          /* 2nd offense: ban 3 days */
          UPDATE user_bans
             SET offense_count = v_off,
                 holds_since_last_offense = 0,
                 banned_until = DATE_ADD(NOW(), INTERVAL 3 DAY)
           WHERE user_id = NEW.user_id;
          INSERT INTO violation_events (user_id, locker_number, event, details)
          VALUES (NEW.user_id, NEW.locker_number, 'ban_3d', 'Reached 6 holds total: 2nd offense (ban 3 days).');

        ELSE
          /* 3rd offense: permanent ban */
          UPDATE user_bans
             SET offense_count = v_off,
                 holds_since_last_offense = 0,
                 banned_until = NULL,
                 is_permanent = 1
           WHERE user_id = NEW.user_id;
          INSERT INTO violation_events (user_id, locker_number, event, details)
          VALUES (NEW.user_id, NEW.locker_number, 'ban_perm', 'Reached 9 holds total: 3rd offense (permanent ban).');
        END IF;
      END IF;
    END;

  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_number` int(11) NOT NULL,
  `method` enum('GCash','Maya','Wallet') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `duration` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `locker_number`, `method`, `amount`, `reference_no`, `duration`, `created_at`) VALUES
(44, 6, 1, 'Wallet', 2.00, 'WAL68dc9c0c0614b', '20', '2025-10-01 03:12:15'),
(45, 2, 2, 'Wallet', 2.00, 'WAL68dc9c4e642c3', '20', '2025-10-01 03:13:21'),
(46, 6, 1, 'Wallet', 2.00, 'WAL68dc9c73ca266', '20', '2025-10-01 03:13:59'),
(47, 2, 2, 'Wallet', 2.00, 'WAL68dc9c7437e0c', '20', '2025-10-01 03:14:01'),
(48, 7, 3, 'Wallet', 0.50, 'WAL68dc9ce91e6ab', '5', '2025-10-01 03:15:56'),
(49, 2, 1, 'Wallet', 2.00, 'WAL68dc9d22aedf8', '20', '2025-10-01 03:16:53'),
(50, 6, 2, 'Wallet', 2.00, 'WAL68dc9d2387e40', '20', '2025-10-01 03:16:55'),
(51, 6, 1, 'Wallet', 2.00, 'WAL68dc9d9285a01', '20', '2025-10-01 03:18:45'),
(52, 6, 1, 'Wallet', 2.00, 'WAL68dc9fca7077a', '20', '2025-10-01 03:28:13'),
(53, 2, 2, 'Wallet', 2.00, 'WAL68dc9fdead5ce', '20', '2025-10-01 03:28:34'),
(54, 6, 1, 'Wallet', 2.00, 'WAL68dc9fdead926', '20', '2025-10-01 03:28:40'),
(55, 6, 1, 'Wallet', 2.00, 'WAL68dca269b844f', '20', '2025-10-01 03:39:25'),
(56, 6, 2, 'Wallet', 2.00, 'WAL68dca280b68b2', '20', '2025-10-01 03:39:47'),
(57, 2, 1, 'Wallet', 2.00, 'WAL68dca280bbf83', '20', '2025-10-01 03:39:55'),
(58, 2, 2, 'Wallet', 2.00, 'WAL68dca2a953a58', '20', '2025-10-01 03:40:28'),
(59, 6, 1, 'Wallet', 2.00, 'WAL68dca2a98a257', '20', '2025-10-01 03:40:30'),
(60, 6, 1, 'Wallet', 2.00, 'WAL68dca2ce7bb4b', '20', '2025-10-01 03:41:05'),
(61, 2, 2, 'Wallet', 2.00, 'WAL68dca2ed864d0', '20', '2025-10-01 03:41:37'),
(62, 2, 2, 'Wallet', 2.00, 'WAL68dca30d1544a', '20', '2025-10-01 03:42:08'),
(63, 2, 2, 'Wallet', 0.50, 'WAL68dca357c2463', '5', '2025-10-01 03:43:23'),
(64, 6, 1, 'Wallet', 0.50, 'WAL68dca3580998c', '5', '2025-10-01 03:43:30'),
(65, 2, 2, 'Wallet', 0.50, 'WAL68dca379c05f3', '5', '2025-10-01 03:43:56'),
(66, 6, 1, 'Wallet', 0.50, 'WAL68dca379dd817', '5', '2025-10-01 03:43:58'),
(67, 7, 3, 'Wallet', 0.50, 'WAL68dca3bacc9b1', '5', '2025-10-01 03:45:02'),
(68, 2, 2, 'Wallet', 0.50, 'WAL68dca3e6cd3c1', '5', '2025-10-01 03:45:47'),
(69, 6, 1, 'Wallet', 0.50, 'WAL68dca3e7180c3', '5', '2025-10-01 03:45:48'),
(70, 6, 1, 'Wallet', 0.50, 'WAL68dca7306e7fe', '5', '2025-10-01 03:59:49'),
(71, 2, 2, 'Wallet', 0.50, 'WAL68dca75410782', '5', '2025-10-01 04:00:23'),
(72, 6, 1, 'Wallet', 0.50, 'WAL68dca775125f1', '5', '2025-10-01 04:01:00'),
(73, 2, 2, 'Wallet', 0.50, 'WAL68dca79242b62', '5', '2025-10-01 04:01:26'),
(74, 6, 1, 'Wallet', 0.50, 'WAL68dca7924b827', '5', '2025-10-01 04:01:31'),
(75, 6, 1, 'Wallet', 0.50, 'WAL68dca7cf44641', '5', '2025-10-01 04:02:26'),
(76, 2, 2, 'Wallet', 0.50, 'WAL68dca7e5307fd', '5', '2025-10-01 04:02:48'),
(77, 6, 1, 'Wallet', 0.50, 'WAL68dca7e57a8b2', '5', '2025-10-01 04:02:50'),
(78, 6, 1, 'Wallet', 0.50, 'WAL68dcabb2697df', '5', '2025-10-01 04:19:01'),
(79, 2, 2, 'Wallet', 0.50, 'WAL68dcabb28c533', '5', '2025-10-01 04:19:03'),
(80, 2, 1, 'Wallet', 0.50, 'WAL68dcabe2529e2', '5', '2025-10-01 04:19:49'),
(81, 6, 1, 'Wallet', 0.50, 'WAL68dcabff76d0b', '5', '2025-10-01 04:20:18'),
(82, 7, 2, 'Wallet', 0.50, 'WAL68dcac28423e6', '5', '2025-10-01 04:20:59'),
(83, 7, 2, 'Wallet', 0.50, 'WAL68dcac48b6ee6', '5', '2025-10-01 04:21:32'),
(84, 2, 2, 'Wallet', 0.50, 'WAL68dcace1e37a1', '5', '2025-10-01 04:24:05'),
(85, 6, 1, 'Wallet', 0.50, 'WAL68dcae497090c', '5', '2025-10-01 04:30:04'),
(86, 2, 2, 'Wallet', 0.50, 'WAL68dcb1d83a73f', '5', '2025-10-01 04:45:15'),
(87, 6, 1, 'Wallet', 0.50, 'WAL68dcb1d847ee0', '5', '2025-10-01 04:45:15'),
(88, 2, 1, 'Wallet', 0.50, 'WAL68dcb2d226d1e', '5', '2025-10-01 04:49:25'),
(89, 6, 2, 'Wallet', 0.50, 'WAL68dcb2d22a75e', '5', '2025-10-01 04:49:25'),
(90, 2, 1, 'Wallet', 0.50, 'WAL68dcb6f67301e', '5', '2025-10-01 05:07:05'),
(91, 6, 2, 'Wallet', 0.50, 'WAL68dcb6f6bfbe8', '5', '2025-10-01 05:07:06'),
(92, 2, 3, 'Wallet', 0.50, 'WAL68dcb731e27db', '5', '2025-10-01 05:08:04'),
(93, 2, 1, 'Wallet', 0.50, 'WAL68dcb8f058b33', '5', '2025-10-01 05:15:31'),
(94, 2, 1, 'Wallet', 0.50, 'WAL68dcb9299b0b8', '5', '2025-10-01 05:16:28'),
(95, 2, 1, 'Wallet', 0.50, 'WAL68dcb9d004611', '5', '2025-10-01 05:19:14'),
(96, 2, 1, 'Wallet', 0.50, 'WAL68dcba6895946', '5', '2025-10-01 05:21:47'),
(97, 2, 2, 'Wallet', 0.50, 'WAL68dcba939bd61', '5', '2025-10-01 05:22:30'),
(98, 6, 1, 'Wallet', 0.50, 'WAL68dcba93d768c', '5', '2025-10-01 05:22:30'),
(99, 6, 1, 'Wallet', 0.50, 'WAL68dcbb4d8111c', '5', '2025-10-01 05:25:36'),
(100, 2, 1, 'Wallet', 0.50, 'WAL68dcbb77b7d34', '5', '2025-10-01 05:26:18'),
(101, 6, 2, 'Wallet', 0.50, 'WAL68dcbb77b94c5', '5', '2025-10-01 05:26:20'),
(102, 7, 3, 'Wallet', 0.50, 'WAL68dcbbcd3506d', '5', '2025-10-01 05:27:44'),
(103, 2, 2, 'Wallet', 0.50, 'WAL68dcbbcd488a4', '5', '2025-10-01 05:27:46'),
(104, 6, 1, 'Wallet', 0.50, 'WAL68dcbbcd86734', '5', '2025-10-01 05:27:48'),
(105, 2, 1, 'Wallet', 0.50, 'WAL68dcbbf0658fe', '5', '2025-10-01 05:28:21'),
(106, 6, 2, 'Wallet', 0.50, 'WAL68dcbbf0afbec', '5', '2025-10-01 05:28:23'),
(107, 2, 1, 'Wallet', 0.50, 'WAL68dcbe70ae1e0', '5', '2025-10-01 05:39:00'),
(108, 6, 1, 'Wallet', 0.50, 'WAL68dcbe9ac00c3', '5', '2025-10-01 05:39:41'),
(109, 2, 2, 'Wallet', 0.50, 'WAL68dcbe9acf5e5', '5', '2025-10-01 05:39:44'),
(110, 6, 2, 'Wallet', 0.50, 'WAL68dcbee2b0e24', '5', '2025-10-01 05:40:54'),
(111, 2, 1, 'Wallet', 0.50, 'WAL68dcbee2b327d', '5', '2025-10-01 05:40:55'),
(112, 7, 3, 'Wallet', 0.50, 'WAL68dcbf11a59a5', '5', '2025-10-01 05:41:42'),
(113, 2, 1, 'Wallet', 0.50, 'WAL68dcbf11a8e90', '5', '2025-10-01 05:41:42'),
(114, 6, 2, 'Wallet', 0.50, 'WAL68dcbf11c29ec', '5', '2025-10-01 05:41:43'),
(115, 7, 3, 'Wallet', 0.50, 'WAL68dcbf3438e87', '5', '2025-10-01 05:42:15'),
(116, 2, 2, 'Wallet', 0.50, 'WAL68dcbf346ade3', '5', '2025-10-01 05:42:15'),
(117, 2, 2, 'Wallet', 0.50, 'WAL68dcbf4ebf312', '5', '2025-10-01 05:42:41'),
(118, 6, 1, 'Wallet', 0.50, 'WAL68dcbf4f07b48', '5', '2025-10-01 05:42:42'),
(119, 7, 3, 'Wallet', 0.50, 'WAL68dcbf4f1463b', '5', '2025-10-01 05:42:42'),
(120, 2, 1, 'Wallet', 0.50, 'WAL68dcc134e624c', '5', '2025-10-01 05:50:48'),
(121, 2, 2, 'Wallet', 0.50, 'WAL68dcc1775f078', '5', '2025-10-01 05:51:54'),
(122, 7, 3, 'Wallet', 0.50, 'WAL68dcc2037ccf8', '5', '2025-10-01 05:54:15'),
(123, 2, 2, 'Wallet', 0.50, 'WAL68dcc203872e4', '5', '2025-10-01 05:54:15'),
(124, 6, 1, 'Wallet', 0.50, 'WAL68dcc203c8c6c', '5', '2025-10-01 05:54:16'),
(125, 2, 2, 'Wallet', 0.50, 'WAL68dcc28561ccc', '5', '2025-10-01 05:56:24'),
(126, 6, 1, 'Wallet', 0.50, 'WAL68dcc2856e92c', '5', '2025-10-01 05:56:27'),
(127, 7, 4, 'Wallet', 0.50, 'WAL68dcc285855c4', '5', '2025-10-01 05:56:27'),
(128, 8, 4, 'Wallet', 0.50, 'WAL68dcc30d718ab', '5', '2025-10-01 05:58:41'),
(129, 7, 3, 'Wallet', 0.50, 'WAL68dcc30d77724', '5', '2025-10-01 05:58:42'),
(130, 6, 1, 'Wallet', 0.50, 'WAL68dcc30db6785', '5', '2025-10-01 05:58:42'),
(131, 2, 2, 'Wallet', 0.50, 'WAL68dcc30db6bd4', '5', '2025-10-01 05:58:42'),
(132, 6, 2, 'Wallet', 0.50, 'WAL68dcc388099bf', '5', '2025-10-01 06:00:44'),
(133, 2, 2, 'Wallet', 0.50, 'WAL68dccede093fc', '5', '2025-10-01 06:49:05'),
(134, 1, 1, 'Wallet', 0.50, 'WAL68dccede14c75', '5', '2025-10-01 06:49:05'),
(135, 2, 1, 'Wallet', 0.50, 'WAL68dcd93d570e7', '5', '2025-10-01 07:33:20'),
(136, 2, 4, 'Wallet', 0.50, 'WAL68dce4a5f3dd8', '5', '2025-10-01 08:22:01'),
(137, 8, 1, 'Wallet', 0.50, 'WAL68dcef472aaa3', '5', '2025-10-01 09:07:22'),
(138, 8, 1, 'Wallet', 0.50, 'WAL68dcf0a8db88c', '5', '2025-10-01 09:13:16'),
(139, 8, 3, 'Wallet', 0.50, 'WAL68dcf2766d78e', '5', '2025-10-01 09:20:57'),
(140, 6, 2, 'Wallet', 0.50, 'WAL68dcf276abdbb', '5', '2025-10-01 09:20:58'),
(141, 2, 1, 'Wallet', 0.50, 'WAL68dcf276bf563', '5', '2025-10-01 09:20:58'),
(142, 2, 3, 'Wallet', 0.50, 'WAL68dcf5cb68475', '5', '2025-10-01 09:35:13'),
(143, 2, 1, 'Wallet', 0.50, 'WAL68dcf5efba654', '5', '2025-10-01 09:35:46'),
(144, 2, 1, 'Wallet', 0.50, 'WAL68dcf86c5d80a', '5', '2025-10-01 09:46:24'),
(145, 2, 1, 'Wallet', 0.50, 'WXT-769938271', '5', '2025-10-01 11:47:08'),
(146, 6, 1, 'Wallet', 0.50, 'WAL68ddd5189fdb2', '5', '2025-10-02 01:27:56'),
(148, 6, 1, 'Wallet', 0.50, 'WAL68dde3b2c7957', '5', '2025-10-02 02:30:14'),
(149, 2, 2, 'Wallet', 0.50, 'WAL68dde3d9e66ac', '5', '2025-10-02 02:30:53'),
(150, 6, 1, 'Wallet', 0.50, 'WAL68dde3da0d875', '5', '2025-10-02 02:30:53'),
(151, 2, 3, 'Wallet', 0.50, 'WAL68dde519944bc', '5', '2025-10-02 02:36:12'),
(152, 2, 4, 'Wallet', 0.50, 'WAL68dde52936056', '5', '2025-10-02 02:36:28'),
(153, 6, 1, 'Wallet', 0.50, 'WAL68dde57b408be', '5', '2025-10-02 02:37:50'),
(154, 10, 1, 'Wallet', 0.50, 'WAL68de05de0652a', '5', '2025-10-02 04:56:01'),
(155, 2, 1, 'Wallet', 0.50, 'WAL68e31dccd1b1f', '5', '2025-10-06 01:39:29'),
(156, 2, 1, 'Wallet', 2.00, 'WAL68e322345aa48', '20', '2025-10-06 01:58:15'),
(157, 12, 2, 'Wallet', 0.50, 'WAL68e327fd6354e', '5', '2025-10-06 02:22:56'),
(158, 14, 4, 'Wallet', 0.50, 'WAL68e32802e95e8', '5', '2025-10-06 02:22:59'),
(160, 14, 1, 'Wallet', 0.50, 'WAL68e328abd6763', '5', '2025-10-06 02:25:51'),
(161, 14, 1, 'Wallet', 0.50, 'WXT-199675290', '5', '2025-10-06 02:26:50'),
(162, 2, 1, 'Wallet', 0.50, 'WAL68e3422b0a9aa', '5', '2025-10-06 04:14:38'),
(163, 2, 1, 'Wallet', 3.00, 'WAL68e38045b0a50', '30', '2025-10-06 08:39:36'),
(164, 2, 2, 'Wallet', 0.50, 'WAL68e380744702d', '5', '2025-10-06 08:40:23'),
(165, 2, 3, 'Wallet', 0.50, 'WAL68e380b7be5de', '5', '2025-10-06 08:41:31'),
(166, 2, 4, 'Wallet', 0.50, 'WAL68e380e7425ee', '5', '2025-10-06 08:42:18'),
(167, 6, 1, 'Wallet', 0.50, 'WAL68e5be6014acb', '5', '2025-10-08 01:29:07'),
(168, 6, 1, 'Wallet', 0.50, 'WAL68e5c202944e7', '5', '2025-10-08 01:44:37'),
(169, 6, 1, 'Wallet', 0.50, 'WAL68e5c59c6a509', '5', '2025-10-08 01:59:59'),
(170, 6, 1, 'Wallet', 0.50, 'WAL68e5ca2bc9c62', '5', '2025-10-08 02:19:27'),
(171, 6, 1, 'Wallet', 0.50, 'WAL68e5ca9f5ddcc', '5', '2025-10-08 02:21:22'),
(172, 6, 1, 'Wallet', 0.50, 'WAL68e5cadf03555', '5', '2025-10-08 02:22:26'),
(173, 6, 1, 'Wallet', 0.50, 'WAL68e5cb2287bdd', '5', '2025-10-08 02:23:33'),
(174, 6, 1, 'Wallet', 0.50, 'WAL68e5d07204873', '5', '2025-10-08 02:46:13'),
(175, 6, 1, 'Wallet', 0.50, 'WAL68e5d09d0e954', '5', '2025-10-08 02:46:56'),
(176, 6, 1, 'Wallet', 0.50, 'WAL68e5d0ca685ae', '5', '2025-10-08 02:47:41'),
(177, 6, 1, 'Wallet', 0.50, 'WAL68e5d0dfc1a60', '5', '2025-10-08 02:48:02'),
(178, 6, 1, 'Wallet', 3.00, 'WAL68e5d6ed4207f', '30', '2025-10-08 03:13:53'),
(179, 6, 1, 'Wallet', 0.50, 'WAL68e5d71a15757', '5', '2025-10-08 03:14:38'),
(180, 6, 1, 'Wallet', 0.50, 'WAL68e5d73ab36ef', '5', '2025-10-08 03:15:09'),
(181, 2, 1, 'Wallet', 0.50, 'WAL68e7535a3e253', '5', '2025-10-09 06:17:02'),
(182, 2, 2, 'Wallet', 0.50, 'WAL68e7559818412', '5', '2025-10-09 06:26:35'),
(183, 2, 1, 'Wallet', 0.50, 'WAL68e7663b2087d', '5', '2025-10-09 07:37:35'),
(184, 7, 1, 'Wallet', 0.50, 'WAL68e77350d7b06', '5', '2025-10-09 08:33:24');

-- --------------------------------------------------------

--
-- Table structure for table `security_alerts`
--

CREATE TABLE `security_alerts` (
  `id` int(11) NOT NULL,
  `locker_number` int(11) NOT NULL DEFAULT 0,
  `cause` enum('theft','door_slam','bump','tilt_only','other') NOT NULL DEFAULT 'other',
  `details` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_alerts`
--

INSERT INTO `security_alerts` (`id`, `locker_number`, `cause`, `details`, `meta`, `is_read`, `created_at`) VALUES
(372, 1, 'door_slam', NULL, '{\"ip\":\"192.168.1.107\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-08 02:21:23'),
(373, 1, 'door_slam', NULL, '{\"ip\":\"192.168.1.107\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-08 03:15:05'),
(374, 1, 'door_slam', NULL, '{\"ip\":\"192.168.1.107\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-08 03:15:05'),
(375, 1, 'door_slam', NULL, '{\"ip\":\"192.168.1.107\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-08 03:15:13'),
(376, 0, 'theft', NULL, '{\"ip\":\"192.168.1.109\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-09 08:30:38'),
(377, 0, 'theft', NULL, '{\"ip\":\"192.168.1.109\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-09 08:31:14'),
(378, 0, 'theft', NULL, '{\"ip\":\"192.168.1.109\",\"ua\":\"ESP32HTTPClient\"}', 0, '2025-10-09 08:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `profile_image`, `verification_token`, `verification_expires_at`, `created_at`, `role`, `archived`, `reset_token`, `reset_token_expires`) VALUES
(1, 'Admin', 'Account', 'admin@gmail.com', '$2y$10$xKFGP/npVtBPdlTawWWTsO9sbjrBk1NlOz9ULvCYbXHnpX.nsY/fW', 'default.jpg', NULL, NULL, '2025-08-02 19:45:11', 'admin', 0, NULL, NULL),
(2, 'Al', 'Angeles', 'algernonangeles3022@gmail.com', '$2y$10$Z5p8b.aChNnjMBhawlQBSuYTH.bPe83j8v/QpHr6qwcTHwJjcpgDW', '644f5e1e569b7a9d3a3da8b8807f939c.jpg', NULL, NULL, '2025-09-29 03:37:22', 'user', 0, NULL, NULL),
(6, 'algernon', 'angeles', 'algernonangeles01@gmail.com', '$2y$10$qasWEFcGeTMLqKfQYabKE.hFoLhfJBV7Ezky6tbJVaqUT27WgF54u', '050f47930e9ec50c940c98370798d163.jpg', NULL, NULL, '2025-09-29 06:46:30', 'user', 0, NULL, NULL),
(7, 'Allen', 'Sanchez', 'allensanchez1628@gmail.com', '$2y$10$z0nRi22UtCxPgQ5LiIyEPOMkS34yBr2QLtwNUCJ4vOFW3zNuJhvZu', NULL, NULL, NULL, '2025-09-29 06:58:16', 'user', 0, NULL, NULL),
(8, 'Allen', 'Sanchez', 'allensnchz1424@gmail.com', '$2y$10$BMtTBW9hf8kzwXNT9vybZe8YBcy3a3cRqN3LCxbVgHj0CnR8YxYxi', NULL, NULL, NULL, '2025-10-01 05:57:56', 'user', 0, NULL, NULL),
(10, 'aaron', 'manaloto', 'akmanaloto17@gmail.com', '$2y$10$FCAKTW4jTRzrX/uxoxE25u.UUzIREBZwzpKsPbccML.YbeT6QN5yq', NULL, NULL, NULL, '2025-10-02 04:53:55', 'user', 0, NULL, NULL),
(11, 'EL', 'Nevi', 'elnevi@gmail.com', '$2y$10$KjYTdQ1sjNU8YUj5jyX/nOU4qJ3sq4h87r37A2xcz0izgoSKS9sCK', NULL, NULL, NULL, '2025-10-06 02:15:40', 'user', 0, NULL, NULL),
(12, 'EL', 'Nevi', 'melllllari@gmail.com', '$2y$10$rX1.LE7mOeFoXlhVwZ25wu/AAnb3Y/0JBZL3eDPk9/4hH8kQfs/2i', NULL, NULL, NULL, '2025-10-06 02:16:11', 'user', 0, NULL, NULL),
(14, 'Arriza', 'Aparejo', 'a.aparejo40720@mcc.edu.ph', '$2y$10$NEef19dSRV0hyKI5V/tS/.YuWhAwRj8e/pS.t5FaRx4z4gnnKRHra', NULL, NULL, NULL, '2025-10-06 02:20:18', 'user', 0, NULL, NULL),
(15, 'Rapahael', 'Galang', 'galangjohnraphael@gmail.com', '$2y$10$6nvPk65pR7sPAsO0VGXcZ.N/t/PTdRiTNJx7vA/H.KtU5tHiNpkmW', NULL, NULL, NULL, '2025-10-06 02:20:40', 'user', 0, NULL, NULL),
(16, 'Raphael', 'Galang', 'johngabgalang@gmail.com', '$2y$10$zuopDFX352p..ml06UTZDOJ6W3SnyKo59fsE4qgyvTFACyZrsSU5W', NULL, NULL, NULL, '2025-10-06 02:24:11', 'user', 0, NULL, NULL),
(17, 'Raphael', 'Galang', 'kashunka123@gmail.com', '$2y$10$qNspJc8bZ37YYwET56zPye87asCwovbNYhfLiSRn102dl7CfKYFdu', NULL, NULL, NULL, '2025-10-06 02:25:28', 'user', 0, NULL, NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `users_after_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
  INSERT IGNORE INTO user_wallets (user_id, balance)
  VALUES (NEW.id, 0.00);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_bans`
--

CREATE TABLE `user_bans` (
  `user_id` int(11) NOT NULL,
  `offense_count` int(11) NOT NULL DEFAULT 0,
  `holds_since_last_offense` int(11) NOT NULL DEFAULT 0,
  `banned_until` datetime DEFAULT NULL,
  `is_permanent` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_bans`
--

INSERT INTO `user_bans` (`user_id`, `offense_count`, `holds_since_last_offense`, `banned_until`, `is_permanent`, `updated_at`) VALUES
(2, 1, 0, NULL, 0, '2025-10-08 10:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `user_wallets`
--

CREATE TABLE `user_wallets` (
  `user_id` int(11) NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_wallets`
--

INSERT INTO `user_wallets` (`user_id`, `balance`, `updated_at`) VALUES
(1, 499.50, '2025-10-01 06:49:05'),
(2, 143.00, '2025-10-09 07:37:35'),
(6, 650.50, '2025-10-08 03:15:09'),
(7, 495.50, '2025-10-09 08:33:24'),
(8, 3.00, '2025-10-01 09:20:57'),
(10, 4.50, '2025-10-02 04:56:01'),
(11, 0.00, '2025-10-06 02:15:40'),
(12, 4.50, '2025-10-06 02:22:56'),
(14, 3.50, '2025-10-06 02:26:50'),
(15, 0.00, '2025-10-06 02:20:40'),
(16, 0.00, '2025-10-06 02:24:11'),
(17, 0.00, '2025-10-06 02:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `violation_events`
--

CREATE TABLE `violation_events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_number` int(11) NOT NULL,
  `event` enum('hold','ban_1d','ban_3d','ban_perm','unban','manual') NOT NULL DEFAULT 'hold',
  `details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_events`
--

INSERT INTO `violation_events` (`id`, `user_id`, `locker_number`, `event`, `details`, `created_at`) VALUES
(39, 2, 0, 'ban_1d', 'Manual 1-day ban by admin.', '2025-10-08 08:18:48'),
(40, 2, 1, 'hold', 'Locker moved to HOLD (item detected / expired).', '2025-10-08 08:22:44'),
(41, 2, 1, 'hold', 'Locker moved to HOLD (item detected / expired).', '2025-10-08 08:23:42'),
(42, 2, 1, 'ban_1d', 'Reached 3 holds: 1st offense (ban 1 day).', '2025-10-08 08:23:42'),
(43, 2, 0, 'ban_3d', 'Manual 3-day ban by admin.', '2025-10-08 08:30:20'),
(44, 2, 0, 'ban_1d', 'Manual 1-day ban by admin.', '2025-10-08 08:30:26'),
(45, 2, 0, 'ban_3d', 'Manual 3-day ban by admin.', '2025-10-08 08:48:32'),
(46, 2, 0, 'ban_1d', 'Manual 1-day ban by admin.', '2025-10-08 08:48:39'),
(47, 2, 0, 'ban_1d', 'Manual 1-day ban by admin.', '2025-10-08 10:00:12'),
(48, 2, 0, 'ban_perm', 'Manual permanent ban by admin.', '2025-10-08 10:01:15'),
(49, 2, 0, 'ban_1d', 'Manual 1-day ban by admin.', '2025-10-08 10:01:47'),
(50, 2, 0, 'unban', 'Manual unban by admin.', '2025-10-08 10:06:36');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('topup','debit','refund','adjustment') NOT NULL,
  `method` enum('GCash','Maya','Wallet','Admin') NOT NULL DEFAULT 'Wallet',
  `amount` decimal(12,2) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `type`, `method`, `amount`, `reference_no`, `notes`, `meta`, `created_at`) VALUES
(7, 8, 'topup', 'Admin', 5.00, 'WELCOMEBONUS-8', 'Welcome bonus on verification', '{\"reason\":\"welcome_bonus\",\"source\":\"email_verification\",\"amount\":5,\"currency\":\"PHP\",\"awarded_at_tz\":\"Asia/Manila\"}', '2025-10-01 05:58:10'),
(8, 8, 'debit', 'Wallet', 2.00, 'WAL68dcf2766d78e', 'Reserve locker #3 (5min)', NULL, '2025-10-01 05:58:41'),
(9, 1, 'topup', 'GCash', 500.00, 'GC-342936688', 'Top-up via GCash', NULL, '2025-10-01 06:48:46'),
(10, 1, 'debit', 'Wallet', 0.50, 'WAL68dccede14c75', 'Reserve locker #1 (5min)', NULL, '2025-10-01 06:49:05'),
(11, 10, 'topup', 'Admin', 5.00, 'WELCOMEBONUS-10', 'Welcome bonus on verification', '{\"reason\":\"welcome_bonus\",\"source\":\"email_verification\",\"amount\":5,\"currency\":\"PHP\",\"awarded_at_tz\":\"Asia/Manila\"}', '2025-10-02 04:55:08'),
(12, 10, 'debit', 'Wallet', 0.50, 'WAL68de05de0652a', 'Reserve locker #1 (5min)', NULL, '2025-10-02 04:56:01'),
(13, 12, 'topup', 'Admin', 5.00, 'WELCOMEBONUS-12', 'Welcome bonus on verification', '{\"reason\":\"welcome_bonus\",\"source\":\"email_verification\",\"amount\":5,\"currency\":\"PHP\",\"awarded_at_tz\":\"Asia/Manila\"}', '2025-10-06 02:21:39'),
(14, 14, 'topup', 'Admin', 5.00, 'WELCOMEBONUS-14', 'Welcome bonus on verification', '{\"reason\":\"welcome_bonus\",\"source\":\"email_verification\",\"amount\":5,\"currency\":\"PHP\",\"awarded_at_tz\":\"Asia/Manila\"}', '2025-10-06 02:22:00'),
(15, 12, 'debit', 'Wallet', 0.50, 'WAL68e327fd6354e', 'Reserve locker #2 (5min)', NULL, '2025-10-06 02:22:56'),
(16, 14, 'debit', 'Wallet', 1.50, 'WXT-199675290', 'Extend locker #1 (5min)', NULL, '2025-10-06 02:22:59'),
(19, 6, 'debit', 'Wallet', 9.50, 'WAL68e5d73ab36ef', 'Reserve locker #1 (5min)', NULL, '2025-10-08 01:29:07'),
(20, 2, 'debit', 'Wallet', 1.50, 'WAL68e7663b2087d', 'Reserve locker #1 (5min)', NULL, '2025-10-09 06:17:01'),
(21, 7, 'debit', 'Wallet', 0.50, 'WAL68e77350d7b06', 'Reserve locker #1 (5min)', NULL, '2025-10-09 08:33:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `locker_history`
--
ALTER TABLE `locker_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_used_at` (`used_at`),
  ADD KEY `idx_locker` (`locker_number`);

--
-- Indexes for table `locker_qr`
--
ALTER TABLE `locker_qr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `locker_number` (`locker_number`),
  ADD KEY `fk_locker_qr_user` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_number` (`locker_number`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read_created` (`is_read`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_verif_token_expires` (`verification_token`,`verification_expires_at`);

--
-- Indexes for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `violation_events`
--
ALTER TABLE `violation_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_event` (`event`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ref` (`reference_no`),
  ADD UNIQUE KEY `uniq_wallet_user_ref` (`user_id`,`reference_no`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `locker_history`
--
ALTER TABLE `locker_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT for table `locker_qr`
--
ALTER TABLE `locker_qr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=185;

--
-- AUTO_INCREMENT for table `security_alerts`
--
ALTER TABLE `security_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=379;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `violation_events`
--
ALTER TABLE `violation_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `locker_qr`
--
ALTER TABLE `locker_qr`
  ADD CONSTRAINT `fk_locker_qr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`locker_number`) REFERENCES `locker_qr` (`locker_number`) ON DELETE CASCADE;

--
-- Constraints for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD CONSTRAINT `fk_user_bans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD CONSTRAINT `fk_user_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `violation_events`
--
ALTER TABLE `violation_events`
  ADD CONSTRAINT `fk_violation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
