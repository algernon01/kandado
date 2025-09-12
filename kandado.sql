-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 12, 2025 at 10:11 AM
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
  `notify10_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locker_qr`
--

INSERT INTO `locker_qr` (`id`, `locker_number`, `user_id`, `code`, `status`, `maintenance`, `item`, `expires_at`, `duration_minutes`, `notify30_sent`, `notify15_sent`, `notify10_sent`) VALUES
(1, 1, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0),
(2, 2, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0),
(3, 3, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0),
(4, 4, NULL, NULL, 'available', 0, 0, NULL, NULL, 0, 0, 0);

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
(1, 'Admin', 'Account', 'admin@gmail.com', '$2y$10$xKFGP/npVtBPdlTawWWTsO9sbjrBk1NlOz9ULvCYbXHnpX.nsY/fW', 'default.jpg', NULL, NULL, '2025-08-03 11:45:11', 'admin', 0, NULL, NULL),
(2, 'Alger', 'Angeles', 'algernonangeles3022@gmail.com', '$2y$10$tjUZpF82svItsmczNGqH7e/Gz0q2d.vfUrdb19679m/XfS/mXy9mS', '40d4bc2db30dac01f5fe56ad68daf04d.jpg', NULL, NULL, '2025-08-26 13:16:35', 'user', 0, NULL, NULL),


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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_verif_token_expires` (`verification_token`,`verification_expires_at`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`user_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locker_qr`
--
ALTER TABLE `locker_qr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD CONSTRAINT `fk_user_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `delete_old_locker_history` ON SCHEDULE EVERY 1 DAY STARTS '2025-08-18 22:22:00' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM locker_history
  WHERE used_at < NOW() - INTERVAL 30 DAY$$

CREATE DEFINER=`root`@`localhost` EVENT `cleanup_expired_verification_tokens` ON SCHEDULE EVERY 5 MINUTE STARTS '2025-09-06 22:42:24' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE users
  SET verification_token = NULL,
      verification_expires_at = NULL
  WHERE verification_expires_at IS NOT NULL
    AND verification_expires_at < NOW()$$

CREATE DEFINER=`root`@`localhost` EVENT `delete_old_payments` ON SCHEDULE EVERY 1 DAY STARTS '2025-09-12 12:38:10' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM payments
    WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)$$

CREATE DEFINER=`root`@`localhost` EVENT `delete_old_wallet_transactions` ON SCHEDULE EVERY 1 DAY STARTS '2025-09-12 12:43:10' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM wallet_transactions
    WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
