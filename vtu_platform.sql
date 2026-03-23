-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 21, 2026 at 10:36 PM
-- Server version: 5.7.34
-- PHP Version: 8.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vtu_platform`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_old_logs` (IN `days` INT)   BEGIN
    DELETE FROM api_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM error_logs WHERE created_at < NOW() - INTERVAL days DAY;
    DELETE FROM sessions WHERE last_activity < UNIX_TIMESTAMP(NOW() - INTERVAL days DAY);
    DELETE FROM remember_tokens WHERE expires_at < NOW();
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_dashboard_stats` (IN `p_user_id` INT)   BEGIN
    -- User stats
    SELECT 
        (SELECT COUNT(*) FROM transactions WHERE user_id = p_user_id) as total_transactions,
        (SELECT SUM(amount) FROM transactions WHERE user_id = p_user_id AND status = 'success') as total_spent,
        (SELECT COUNT(*) FROM transactions WHERE user_id = p_user_id AND DATE(created_at) = CURDATE()) as today_transactions,
        (SELECT SUM(amount) FROM transactions WHERE user_id = p_user_id AND DATE(created_at) = CURDATE()) as today_spent,
        (SELECT COUNT(*) FROM referrals WHERE referrer_id = p_user_id) as total_referrals;
    
    -- Recent transactions
    SELECT * FROM transactions 
    WHERE user_id = p_user_id 
    ORDER BY created_at DESC 
    LIMIT 10;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `process_daily_settlements` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_total_commission DECIMAL(15,2);
    
    DECLARE cur CURSOR FOR 
        SELECT referrer_id, SUM(commission_amount) as total
        FROM referrals 
        WHERE status = 'pending' 
        AND DATE(earned_at) = CURDATE() - INTERVAL 1 DAY
        GROUP BY referrer_id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_user_id, v_total_commission;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Update user bonus balance
        UPDATE users 
        SET bonus_balance = bonus_balance + v_total_commission 
        WHERE id = v_user_id;
        
        -- Update referral status
        UPDATE referrals 
        SET status = 'paid', paid_at = NOW() 
        WHERE referrer_id = v_user_id 
        AND status = 'pending' 
        AND DATE(earned_at) = CURDATE() - INTERVAL 1 DAY;
        
        -- Create transaction record
        INSERT INTO transactions (user_id, transaction_id, type, amount, status, created_at)
        VALUES (v_user_id, CONCAT('BONUS-', UNIX_TIMESTAMP()), 'referral_bonus', v_total_commission, 'success', NOW());
    END LOOP;
    
    CLOSE cur;
    
    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_data` text COLLATE utf8mb4_unicode_ci,
  `response_code` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `request_method`, `request_url`, `request_data`, `response_code`, `created_at`) VALUES
(1, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 09:28:44'),
(2, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:07:28'),
(3, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:07:33'),
(4, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:27:31'),
(5, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:28:11'),
(6, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:28:13'),
(7, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:33:54'),
(8, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 10:55:11'),
(9, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 11:03:01'),
(10, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-19 17:09:43'),
(11, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-20 10:12:09'),
(12, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-20 10:33:51'),
(13, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-20 10:33:55'),
(14, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 08:18:41'),
(15, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 13:42:26'),
(16, 7, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 14:09:43'),
(17, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 17:50:04'),
(18, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:13:08'),
(19, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:14:54'),
(20, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:21:54'),
(21, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:22:00'),
(22, 8, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:22:19'),
(23, 8, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:24:51'),
(24, 8, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:25:38'),
(25, 8, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:32:57'),
(26, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 19:33:00'),
(27, 6, 'manual_funding_rejected', 'Rejected manual funding request #1 for user 7 of ₦5,000.00 Reason: The Technology Incubation complex corporate', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-21 20:33:02'),
(28, 7, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 20:59:19'),
(29, 8, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 21:00:06'),
(30, 6, 'logout', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 21:08:45'),
(31, 6, 'login', 'User logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, '2026-03-21 21:12:57');

-- --------------------------------------------------------

--
-- Table structure for table `api_logs`
--

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_headers` text COLLATE utf8mb4_unicode_ci,
  `request_body` text COLLATE utf8mb4_unicode_ci,
  `response_headers` text COLLATE utf8mb4_unicode_ci,
  `response_body` text COLLATE utf8mb4_unicode_ci,
  `status_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transaction_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_providers`
--

CREATE TABLE `api_providers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `api_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_secret` text COLLATE utf8mb4_unicode_ci,
  `api_username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sandbox_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wallet_balance` decimal(15,2) DEFAULT '0.00',
  `balance_threshold` decimal(15,2) DEFAULT '10000.00',
  `balance_alert_sent` tinyint(1) DEFAULT '0',
  `priority` int(11) DEFAULT '1',
  `timeout` int(11) DEFAULT '30',
  `retry_count` int(11) DEFAULT '3',
  `retry_delay` int(11) DEFAULT '5',
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `settings` json DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `last_checked` datetime DEFAULT NULL,
  `last_success` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `error_count` int(11) DEFAULT '0',
  `success_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_providers`
--

INSERT INTO `api_providers` (`id`, `name`, `code`, `description`, `api_key`, `api_secret`, `api_username`, `api_password`, `api_url`, `sandbox_url`, `wallet_balance`, `balance_threshold`, `balance_alert_sent`, `priority`, `timeout`, `retry_count`, `retry_delay`, `is_active`, `is_default`, `settings`, `headers`, `last_checked`, `last_success`, `last_error`, `error_count`, `success_count`, `created_at`, `updated_at`) VALUES
(1, 'VTpass', 'vtpass', NULL, '', NULL, NULL, NULL, 'https://api-service.vtpass.com/api', 'https://sandbox.vtpass.com/api', 0.00, 10000.00, 0, 1, 30, 3, 5, 1, 1, NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(2, 'VTU.ng', 'vtung', NULL, '', NULL, NULL, NULL, 'https://vtu.ng/api/v1', 'https://sandbox.vtu.ng/api/v1', 0.00, 10000.00, 0, 2, 30, 3, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(3, 'RapidBills', 'rapidbills', NULL, '', NULL, NULL, NULL, 'https://rapidbills.com/api', 'https://sandbox.rapidbills.com/api', 0.00, 10000.00, 0, 3, 30, 3, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(4, 'ClubKonnect', 'clubkonnect', NULL, '', NULL, NULL, NULL, 'https://clubkonnect.com/api', 'https://sandbox.clubkonnect.com/api', 0.00, 10000.00, 0, 4, 30, 3, 5, 0, 0, NULL, NULL, NULL, NULL, NULL, 0, 0, '2026-03-18 03:28:19', '2026-03-18 03:28:19');

-- --------------------------------------------------------

--
-- Table structure for table `banks`
--

CREATE TABLE `banks` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banks`
--

INSERT INTO `banks` (`id`, `name`, `code`, `short_code`, `logo`, `is_active`, `created_at`) VALUES
(1, 'Access Bank', '044', 'ACCESS', NULL, 1, '2026-03-18 03:28:19'),
(2, 'Citibank', '023', 'CITI', NULL, 1, '2026-03-18 03:28:19'),
(3, 'Ecobank', '050', 'ECOBANK', NULL, 1, '2026-03-18 03:28:19'),
(4, 'Fidelity Bank', '070', 'FIDELITY', NULL, 1, '2026-03-18 03:28:19'),
(5, 'First Bank', '011', 'FIRST', NULL, 1, '2026-03-18 03:28:19'),
(6, 'First City Monument Bank', '214', 'FCMB', NULL, 1, '2026-03-18 03:28:19'),
(7, 'Guaranty Trust Bank', '058', 'GTB', NULL, 1, '2026-03-18 03:28:19'),
(8, 'Heritage Bank', '030', 'HERITAGE', NULL, 1, '2026-03-18 03:28:19'),
(9, 'Keystone Bank', '082', 'KEYSTONE', NULL, 1, '2026-03-18 03:28:19'),
(10, 'Polaris Bank', '076', 'POLARIS', NULL, 1, '2026-03-18 03:28:19'),
(11, 'Providus Bank', '101', 'PROVIDUS', NULL, 1, '2026-03-18 03:28:19'),
(12, 'Stanbic IBTC Bank', '221', 'STANBIC', NULL, 1, '2026-03-18 03:28:19'),
(13, 'Standard Chartered', '068', 'SCB', NULL, 1, '2026-03-18 03:28:19'),
(14, 'Sterling Bank', '232', 'STERLING', NULL, 1, '2026-03-18 03:28:19'),
(15, 'Suntrust Bank', '100', 'SUNTRUST', NULL, 1, '2026-03-18 03:28:19'),
(16, 'Union Bank', '032', 'UNION', NULL, 1, '2026-03-18 03:28:19'),
(17, 'United Bank for Africa', '033', 'UBA', NULL, 1, '2026-03-18 03:28:19'),
(18, 'Unity Bank', '215', 'UNITY', NULL, 1, '2026-03-18 03:28:19'),
(19, 'Wema Bank', '035', 'WEMA', NULL, 1, '2026-03-18 03:28:19'),
(20, 'Zenith Bank', '057', 'ZENITH', NULL, 1, '2026-03-18 03:28:19');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-folder',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#6366f1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `code`, `icon`, `color`, `description`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Airtime', 'airtime', 'fa-phone-alt', '#10b981', NULL, 1, 1, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(2, 'Data Bundles', 'data', 'fa-wifi', '#3b82f6', NULL, 1, 2, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(3, 'Electricity', 'electricity', 'fa-bolt', '#f59e0b', NULL, 1, 3, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(4, 'Cable TV', 'cable', 'fa-tv', '#ef4444', NULL, 1, 4, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(5, 'Exam Pins', 'exam', 'fa-graduation-cap', '#8b5cf6', NULL, 1, 5, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(6, 'Gift Cards', 'giftcard', 'fa-gift', '#ec4899', NULL, 1, 6, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(7, 'Insurance', 'insurance', 'fa-shield-alt', '#6366f1', NULL, 1, 7, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(8, 'Betting', 'betting', 'fa-gamepad', '#f97316', NULL, 1, 8, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(9, 'Education', 'education', 'fa-book', '#14b8a6', NULL, 1, 9, '2026-03-21 14:42:56', '2026-03-21 14:42:56'),
(13, 'CAC', 'cac', 'fa-folder', '#6366f1', '', 1, 10, '2026-03-21 15:09:39', '2026-03-21 15:09:39');

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales` (
`sale_date` date
,`type` enum('airtime','data','electricity','cable','exam','wallet_funding','wallet_transfer','referral_bonus','commission','withdrawal')
,`transaction_count` bigint(21)
,`total_amount` decimal(32,2)
,`total_fee` decimal(37,2)
,`total_discount` decimal(37,2)
,`net_amount` decimal(39,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `to_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_body` text COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `priority` int(11) DEFAULT '1',
  `status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `attempts` int(11) DEFAULT '0',
  `max_attempts` int(11) DEFAULT '3',
  `last_attempt` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `error_logs`
--

CREATE TABLE `error_logs` (
  `id` int(11) NOT NULL,
  `error_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `error_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_line` int(11) DEFAULT NULL,
  `stack_trace` text COLLATE utf8mb4_unicode_ci,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_data` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `funding_requests`
--

CREATE TABLE `funding_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wallet_id` int(11) DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) DEFAULT '0.00',
  `total` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_gateway` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_response` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','processing','success','failed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payment_details` text COLLATE utf8mb4_unicode_ci,
  `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deposit_slip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `funding_requests`
--

INSERT INTO `funding_requests` (`id`, `user_id`, `wallet_id`, `reference`, `amount`, `fee`, `total`, `currency`, `payment_method`, `payment_gateway`, `payment_reference`, `gateway_response`, `status`, `paid_at`, `expires_at`, `metadata`, `ip_address`, `user_agent`, `created_at`, `updated_at`, `payment_details`, `bank_name`, `account_name`, `account_number`, `deposit_slip`) VALUES
(1, 7, NULL, 'MANUAL_1774124511_8472', 5000.00, 0.00, NULL, 'NGN', 'manual', NULL, NULL, NULL, 'failed', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-21 20:21:51', '2026-03-21 20:33:02', NULL, 'Opay', 'ABDULAZIZ ADAMU', '1234567890', '/uploads/slips/MANUAL_1774124511_8472.jpg'),
(2, 7, NULL, 'MANUAL_1774125727_3855', 1000.00, 0.00, NULL, 'NGN', 'manual', NULL, NULL, NULL, 'success', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-21 20:42:07', '2026-03-21 20:50:13', NULL, 'Opay', 'ABDULAZIZ ADAMU', '1234567890', '/uploads/slips/MANUAL_1774125727_3855.jpg'),
(3, 8, NULL, 'MANUAL_1774126839_6706', 5000.00, 0.00, NULL, 'NGN', 'manual', NULL, NULL, NULL, 'pending', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-03-21 21:00:39', '2026-03-21 21:00:39', NULL, 'Opay', 'ABDULAZIZ ADAMU', '1234567890', '/uploads/slips/MANUAL_1774126839_6706.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_text` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `is_sent` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `data`, `action_url`, `action_text`, `is_read`, `is_sent`, `read_at`, `sent_at`, `expires_at`, `created_at`) VALUES
(1, 7, 'wallet', 'Manual Funding Request Rejected', 'Your manual funding request of ₦5,000.00 has been rejected. Reason: The Technology Incubation complex corporate', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, '2026-03-21 20:33:02');

-- --------------------------------------------------------

--
-- Table structure for table `payment_proofs`
--

CREATE TABLE `payment_proofs` (
  `id` int(11) NOT NULL,
  `funding_request_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `commission_type` enum('fixed','percentage') COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `commission_amount` decimal(15,2) DEFAULT '0.00',
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `total_commission` decimal(15,2) DEFAULT '0.00',
  `level` int(11) DEFAULT '1',
  `status` enum('pending','active','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `earned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_earnings`
--

CREATE TABLE `referral_earnings` (
  `id` int(11) NOT NULL,
  `referral_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('signup_bonus','purchase_commission','level_commission') COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int(11) DEFAULT '1',
  `status` enum('pending','credited','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `credited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('airtime','data','electricity','cable','exam','giftcard','insurance','betting','education') COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_amount` decimal(15,2) DEFAULT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT '0.00',
  `discount_rate` decimal(5,2) DEFAULT '0.00',
  `is_active` tinyint(1) DEFAULT '1',
  `is_popular` tinyint(1) DEFAULT '0',
  `requires_verification` tinyint(1) DEFAULT '0',
  `verification_fields` json DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `category_id`, `code`, `category`, `provider_id`, `description`, `logo`, `min_amount`, `max_amount`, `commission_rate`, `discount_rate`, `is_active`, `is_popular`, `requires_verification`, `verification_fields`, `settings`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 'MTN Airtime', 1, 'mtn_airtime', 'airtime', 1, NULL, NULL, 50.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(2, 'Glo Airtime', 1, 'glo_airtime', 'airtime', 1, NULL, NULL, 50.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(3, 'Airtel Airtime', 1, 'airtel_airtime', 'airtime', 1, NULL, NULL, 50.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(4, '9mobile Airtime', 1, '9mobile_airtime', 'airtime', 1, NULL, NULL, 50.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(5, 'MTN Data', NULL, 'mtn_data', 'data', 1, NULL, NULL, 100.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(6, 'Glo Data', NULL, 'glo_data', 'data', 1, NULL, NULL, 100.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(7, 'Airtel Data', NULL, 'airtel_data', 'data', 1, NULL, NULL, 100.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(8, '9mobile Data', NULL, '9mobile_data', 'data', 1, NULL, NULL, 100.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(9, 'IKEDC Electricity', 3, 'ikedc', 'electricity', 1, NULL, NULL, 500.00, 100000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(10, 'EKEDC Electricity', 3, 'ekedc', 'electricity', 1, NULL, NULL, 500.00, 100000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(11, 'AEDC Electricity', 3, 'aedc', 'electricity', 1, NULL, NULL, 500.00, 100000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(12, 'PHED Electricity', 3, 'phed', 'electricity', 1, NULL, NULL, 500.00, 100000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 14:42:56'),
(13, 'DStv', NULL, 'dstv', 'cable', 1, NULL, NULL, 1000.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(14, 'GOtv', NULL, 'gotv', 'cable', 1, NULL, NULL, 500.00, 20000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(15, 'StarTimes', NULL, 'startimes', 'cable', 1, NULL, NULL, 500.00, 30000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(16, 'WAEC PIN', NULL, 'waec', 'exam', 2, NULL, NULL, 5000.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(17, 'NECO PIN', NULL, 'neco', 'exam', 2, NULL, NULL, 5000.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(18, 'JAMB PIN', NULL, 'jamb', 'exam', 2, NULL, NULL, 5000.00, 50000.00, 0.00, 0.00, 1, 1, 0, NULL, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19');

-- --------------------------------------------------------

--
-- Table structure for table `service_variations`
--

CREATE TABLE `service_variations` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `provider_variation_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variation_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(15,2) NOT NULL,
  `wholesale_price` decimal(15,2) DEFAULT NULL,
  `retail_price` decimal(15,2) DEFAULT NULL,
  `commission_amount` decimal(15,2) DEFAULT '0.00',
  `bonus_amount` decimal(15,2) DEFAULT '0.00',
  `bonus_percentage` decimal(5,2) DEFAULT '0.00',
  `validity` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `network` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subcategory` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_popular` tinyint(1) DEFAULT '0',
  `is_recommended` tinyint(1) DEFAULT '0',
  `priority` int(11) DEFAULT '0',
  `min_quantity` int(11) DEFAULT '1',
  `max_quantity` int(11) DEFAULT '1',
  `stock` int(11) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_variations`
--

INSERT INTO `service_variations` (`id`, `service_id`, `provider_variation_id`, `variation_code`, `name`, `icon`, `color`, `description`, `amount`, `wholesale_price`, `retail_price`, `commission_amount`, `bonus_amount`, `bonus_percentage`, `validity`, `size`, `network`, `category`, `subcategory`, `region`, `is_active`, `is_popular`, `is_recommended`, `priority`, `min_quantity`, `max_quantity`, `stock`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 5, 'mtn-1gb', 'mtn-1gb', 'MTN 1GB Daily', NULL, NULL, NULL, 500.00, 450.00, 500.00, 0.00, 0.00, 0.00, '1 day', '1GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(2, 5, 'mtn-2gb', 'mtn-2gb', 'MTN 2GB Weekly', NULL, NULL, NULL, 1000.00, 900.00, 1000.00, 0.00, 0.00, 0.00, '7 days', '2GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(3, 5, 'mtn-3gb', 'mtn-3gb', 'MTN 3GB Weekly', NULL, NULL, NULL, 1500.00, 1350.00, 1500.00, 0.00, 0.00, 0.00, '7 days', '3GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(4, 5, 'mtn-5gb', 'mtn-5gb', 'MTN 5GB Monthly', NULL, NULL, NULL, 2500.00, 2250.00, 2500.00, 0.00, 0.00, 0.00, '30 days', '5GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(5, 5, 'mtn-10gb', 'mtn-10gb', 'MTN 10GB Monthly', NULL, NULL, NULL, 5000.00, 4500.00, 5000.00, 0.00, 0.00, 0.00, '30 days', '10GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(6, 5, 'mtn-20gb', 'mtn-20gb', 'MTN 20GB Monthly', NULL, NULL, NULL, 9500.00, 8550.00, 9500.00, 0.00, 0.00, 0.00, '30 days', '20GB', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(7, 6, 'glo-1gb', 'glo-1gb', 'Glo 1GB Daily', NULL, NULL, NULL, 450.00, 405.00, 450.00, 0.00, 0.00, 0.00, '1 day', '1GB', 'glo', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(8, 6, 'glo-2gb', 'glo-2gb', 'Glo 2GB Weekly', NULL, NULL, NULL, 900.00, 810.00, 900.00, 0.00, 0.00, 0.00, '7 days', '2GB', 'glo', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(9, 6, 'glo-3gb', 'glo-3gb', 'Glo 3GB Weekly', NULL, NULL, NULL, 1350.00, 1215.00, 1350.00, 0.00, 0.00, 0.00, '7 days', '3GB', 'glo', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(10, 6, 'glo-5gb', 'glo-5gb', 'Glo 5GB Monthly', NULL, NULL, NULL, 2200.00, 1980.00, 2200.00, 0.00, 0.00, 0.00, '30 days', '5GB', 'glo', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(11, 6, 'glo-10gb', 'glo-10gb', 'Glo 10GB Monthly', NULL, NULL, NULL, 4500.00, 4050.00, 4500.00, 0.00, 0.00, 0.00, '30 days', '10GB', 'glo', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(12, 7, 'airtel-1gb', 'airtel-1gb', 'Airtel 1GB Daily', NULL, NULL, NULL, 480.00, 432.00, 480.00, 0.00, 0.00, 0.00, '1 day', '1GB', 'airtel', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(13, 7, 'airtel-2gb', 'airtel-2gb', 'Airtel 2GB Weekly', NULL, NULL, NULL, 950.00, 855.00, 950.00, 0.00, 0.00, 0.00, '7 days', '2GB', 'airtel', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(14, 7, 'airtel-3gb', 'airtel-3gb', 'Airtel 3GB Weekly', NULL, NULL, NULL, 1450.00, 1305.00, 1450.00, 0.00, 0.00, 0.00, '7 days', '3GB', 'airtel', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(15, 7, 'airtel-5gb', 'airtel-5gb', 'Airtel 5GB Monthly', NULL, NULL, NULL, 2400.00, 2160.00, 2400.00, 0.00, 0.00, 0.00, '30 days', '5GB', 'airtel', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(16, 7, 'airtel-10gb', 'airtel-10gb', 'Airtel 10GB Monthly', NULL, NULL, NULL, 4800.00, 4320.00, 4800.00, 0.00, 0.00, 0.00, '30 days', '10GB', 'airtel', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(17, 8, '9mobile-1gb', '9mobile-1gb', '9mobile 1GB Daily', NULL, NULL, NULL, 460.00, 414.00, 460.00, 0.00, 0.00, 0.00, '1 day', '1GB', '9mobile', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(18, 8, '9mobile-2gb', '9mobile-2gb', '9mobile 2GB Weekly', NULL, NULL, NULL, 920.00, 828.00, 920.00, 0.00, 0.00, 0.00, '7 days', '2GB', '9mobile', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(19, 8, '9mobile-3gb', '9mobile-3gb', '9mobile 3GB Weekly', NULL, NULL, NULL, 1380.00, 1242.00, 1380.00, 0.00, 0.00, 0.00, '7 days', '3GB', '9mobile', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(20, 8, '9mobile-5gb', '9mobile-5gb', '9mobile 5GB Monthly', NULL, NULL, NULL, 2300.00, 2070.00, 2300.00, 0.00, 0.00, 0.00, '30 days', '5GB', '9mobile', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(21, 8, '9mobile-10gb', '9mobile-10gb', '9mobile 10GB Monthly', NULL, NULL, NULL, 4600.00, 4140.00, 4600.00, 0.00, 0.00, 0.00, '30 days', '10GB', '9mobile', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(22, 13, 'dstv-padi', 'dstv-padi', 'DStv Padi', NULL, NULL, NULL, 2500.00, 2300.00, 2500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(23, 13, 'dstv-yanga', 'dstv-yanga', 'DStv Yanga', NULL, NULL, NULL, 4200.00, 3900.00, 4200.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(24, 13, 'dstv-confam', 'dstv-confam', 'DStv Confam', NULL, NULL, NULL, 6200.00, 5800.00, 6200.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(25, 13, 'dstv-asia', 'dstv-asia', 'DStv Asia', NULL, NULL, NULL, 7500.00, 7000.00, 7500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(26, 13, 'dstv-premium', 'dstv-premium', 'DStv Premium', NULL, NULL, NULL, 18500.00, 17500.00, 18500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(27, 13, 'dstv-pawa', 'dstv-pawa', 'DStv Pawa', NULL, NULL, NULL, 1800.00, 1650.00, 1800.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(28, 14, 'gotv-small', 'gotv-small', 'GOtv Smallie', NULL, NULL, NULL, 1500.00, 1350.00, 1500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(29, 14, 'gotv-jinja', 'gotv-jinja', 'GOtv Jinja', NULL, NULL, NULL, 2500.00, 2300.00, 2500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(30, 14, 'gotv-max', 'gotv-max', 'GOtv Max', NULL, NULL, NULL, 3700.00, 3400.00, 3700.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(31, 14, 'gotv-supa', 'gotv-supa', 'GOtv Supa', NULL, NULL, NULL, 5700.00, 5300.00, 5700.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(32, 15, 'startimes-nova', 'startimes-nova', 'StarTimes Nova', NULL, NULL, NULL, 1500.00, 1350.00, 1500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(33, 15, 'startimes-basic', 'startimes-basic', 'StarTimes Basic', NULL, NULL, NULL, 2500.00, 2300.00, 2500.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(34, 15, 'startimes-classic', 'startimes-classic', 'StarTimes Classic', NULL, NULL, NULL, 3700.00, 3400.00, 3700.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(35, 15, 'startimes-super', 'startimes-super', 'StarTimes Super', NULL, NULL, NULL, 5700.00, 5300.00, 5700.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, 'cable', NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(36, 5, NULL, 'Mtngb', 'Yearly', NULL, NULL, NULL, 10000.00, 95000.00, 100000.00, 0.00, 0.00, 0.00, '362', '100', 'mtn', NULL, NULL, NULL, 1, 0, 0, 0, 1, 1, NULL, NULL, '2026-03-21 16:26:12', '2026-03-21 16:26:12');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `last_activity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` enum('text','number','boolean','json','file','email','phone','url') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_public` tinyint(1) DEFAULT '0',
  `is_encrypted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `type`, `group_name`, `description`, `is_public`, `is_encrypted`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'KData', 'text', 'general', 'Website name', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 20:36:03'),
(2, 'site_title', 'Best VTU Platform in Nigeria', 'text', 'general', 'Website title', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(3, 'site_description', 'Buy airtime, data, pay bills instantly', 'text', 'general', 'Website description', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(4, 'site_keywords', 'vtu, airtime, data, electricity, cable tv', 'text', 'general', 'SEO keywords', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(5, 'site_email', 'support@kdata.com', 'email', 'contact', 'Support email', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-21 20:36:03'),
(6, 'site_phone', '08012345678', 'phone', 'contact', 'Support phone', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(7, 'site_address', 'Lagos, Nigeria', 'text', 'contact', 'Office address', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(8, 'currency', '₦', 'text', 'financial', 'Currency symbol', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(9, 'currency_code', 'NGN', 'text', 'financial', 'Currency code', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(10, 'min_airtime', '50', 'number', 'limits', 'Minimum airtime purchase', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(11, 'max_airtime', '50000', 'number', 'limits', 'Maximum airtime purchase', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(12, 'min_data', '100', 'number', 'limits', 'Minimum data purchase', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(13, 'min_electricity', '500', 'number', 'limits', 'Minimum electricity payment', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(14, 'max_electricity', '100000', 'number', 'limits', 'Maximum electricity payment', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(15, 'referral_bonus', '100', 'number', 'referral', 'Referral signup bonus', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(16, 'referral_percentage', '5', 'number', 'referral', 'Referral commission percentage', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(17, 'referral_levels', '3', 'number', 'referral', 'Number of referral levels', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(18, 'maintenance_mode', 'false', 'boolean', 'system', 'Maintenance mode', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(19, 'version', '2.0.0', 'text', 'system', 'System version', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(20, 'allow_registration', 'true', 'boolean', 'system', 'Allow new registrations', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(21, 'email_verification', 'true', 'boolean', 'security', 'Require email verification', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(22, 'phone_verification', 'false', 'boolean', 'security', 'Require phone verification', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(23, 'two_factor_auth', 'false', 'boolean', 'security', 'Enable 2FA', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(24, 'session_lifetime', '120', 'number', 'security', 'Session lifetime in minutes', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(25, 'max_login_attempts', '5', 'number', 'security', 'Maximum login attempts', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(26, 'lockout_duration', '30', 'number', 'security', 'Lockout duration in minutes', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(27, 'timezone', 'Africa/Lagos', 'text', 'system', 'Default timezone', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(28, 'date_format', 'M d, Y h:i A', 'text', 'system', 'Date format', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(29, 'items_per_page', '20', 'number', 'system', 'Items per page', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(30, 'enable_api', 'true', 'boolean', 'api', 'Enable API access', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(31, 'api_rate_limit', '60', 'number', 'api', 'API rate limit per minute', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(32, 'enable_referrals', 'true', 'boolean', 'referral', 'Enable referral system', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(33, 'enable_withdrawals', 'true', 'boolean', 'financial', 'Enable withdrawals', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(34, 'min_withdrawal', '1000', 'number', 'financial', 'Minimum withdrawal amount', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(35, 'max_withdrawal', '500000', 'number', 'financial', 'Maximum withdrawal amount', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19'),
(36, 'withdrawal_fee', '50', 'number', 'financial', 'Withdrawal fee', 0, 0, NULL, NULL, '2026-03-18 03:28:19', '2026-03-18 03:28:19');

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int(11) DEFAULT '1',
  `status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `attempts` int(11) DEFAULT '0',
  `max_attempts` int(11) DEFAULT '3',
  `last_attempt` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `provider_response` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `status` enum('open','pending','resolved','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `attachments` json DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` json DEFAULT NULL,
  `is_staff_reply` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL DEFAULT '0',
  `transaction_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('airtime','data','electricity','cable','exam','wallet_funding','wallet_transfer','referral_bonus','commission','withdrawal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `service` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fee` decimal(15,2) DEFAULT '0.00',
  `discount` decimal(15,2) DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `network` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meter_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smart_card` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_plan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variation_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text COLLATE utf8mb4_unicode_ci,
  `customer_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `units` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','processing','success','failed','reversed','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_request` text COLLATE utf8mb4_unicode_ci,
  `api_response` text COLLATE utf8mb4_unicode_ci,
  `api_status_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int(11) DEFAULT '0',
  `webhook_url` text COLLATE utf8mb4_unicode_ci,
  `webhook_sent` tinyint(1) DEFAULT '0',
  `webhook_response` text COLLATE utf8mb4_unicode_ci,
  `webhook_attempts` int(11) DEFAULT '0',
  `callback_url` text COLLATE utf8mb4_unicode_ci,
  `callback_sent` tinyint(1) DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `after_transaction_insert` AFTER INSERT ON `transactions` FOR EACH ROW BEGIN
    IF NEW.status = 'success' AND NEW.type IN ('wallet_funding') THEN
        UPDATE wallets 
        SET balance = balance + NEW.amount 
        WHERE user_id = NEW.user_id AND currency = NEW.currency;
    END IF;
    
    IF NEW.status = 'success' AND NEW.type IN ('airtime', 'data', 'electricity', 'cable') THEN
        UPDATE wallets 
        SET balance = balance - NEW.total 
        WHERE user_id = NEW.user_id AND currency = NEW.currency;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_transaction_update` AFTER UPDATE ON `transactions` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO transaction_logs (transaction_id, action, old_status, new_status, old_data, new_data, ip_address, user_agent)
        VALUES (NEW.id, 'status_change', OLD.status, NEW.status, 
                JSON_OBJECT('amount', OLD.amount, 'reference', OLD.reference),
                JSON_OBJECT('amount', NEW.amount, 'reference', NEW.reference),
                NEW.ip_address, NEW.user_agent);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_logs`
--

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `transaction_summary`
-- (See below for the actual view)
--
CREATE TABLE `transaction_summary` (
`type` enum('airtime','data','electricity','cable','exam','wallet_funding','wallet_transfer','referral_bonus','commission','withdrawal')
,`total_count` bigint(21)
,`success_count` decimal(23,0)
,`failed_count` decimal(23,0)
,`pending_count` decimal(23,0)
,`total_amount` decimal(32,2)
,`total_fee` decimal(37,2)
,`average_amount` decimal(14,6)
,`min_amount` decimal(10,2)
,`max_amount` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Nigeria',
  `wallet_balance` decimal(15,2) DEFAULT '0.00',
  `bonus_balance` decimal(15,2) DEFAULT '0.00',
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `phone_verified` tinyint(1) DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_backup_codes` text COLLATE utf8mb4_unicode_ci,
  `role` enum('user','admin','super_admin') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `status` enum('active','suspended','banned','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_attempts` int(11) DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password`, `first_name`, `last_name`, `address`, `city`, `state`, `country`, `wallet_balance`, `bonus_balance`, `referral_code`, `referred_by`, `email_verified`, `phone_verified`, `email_verified_at`, `phone_verified_at`, `two_factor_enabled`, `two_factor_secret`, `two_factor_backup_codes`, `role`, `status`, `last_login`, `last_ip`, `login_attempts`, `locked_until`, `avatar`, `created_at`, `updated_at`, `deleted_at`, `reset_token`, `reset_expires`) VALUES
(6, 'Abdulx', 'info@mmkexpress.com', '09034095383', '$2y$10$xWbIye4OZC5vlZ7wdGTHBuS3ezuqCAcFlK7ZEHNA55s5XLsAqddQS', 'ABDULAZIZ', 'ISRAEL', NULL, NULL, NULL, 'Nigeria', 11000.00, 11000.00, '0BD71458', NULL, 0, 1, NULL, NULL, 0, NULL, NULL, 'admin', 'active', '2026-03-21 22:12:57', '127.0.0.1', 0, NULL, NULL, '2026-03-19 09:14:12', '2026-03-21 21:12:57', NULL, NULL, NULL),
(7, 'zuzu', 'kuyabetech@gmail.com', '09130773764', '$2y$10$0YSZV136k7h6.qHxlS/Tt.dpb7Sa0LZjcl8dv3Bnei9RCXaF6dzuK', 'Abdullah', 'Ibrahim', NULL, NULL, NULL, 'Nigeria', 10000.00, 0.00, 'C0080ABA', NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'user', 'active', '2026-03-21 15:09:43', '127.0.0.1', 0, NULL, NULL, '2026-03-21 14:08:30', '2026-03-21 16:01:10', NULL, NULL, NULL),
(8, 'kuyabetech', 'adamuusnan87@gmail.com', '09130685889', '$2y$10$thZtvhB73N5stPehBHZMaOUDtGlvLvRn/n783fyOOL7iXCYjPzT8.', '', '', 'Bosso', 'Minna', 'Niger', 'Nigeria', 0.00, 0.00, 'A1BFC771', NULL, 0, 0, NULL, NULL, 0, NULL, NULL, 'user', 'active', '2026-03-21 22:00:06', '127.0.0.1', 0, NULL, NULL, '2026-03-21 16:28:06', '2026-03-21 21:00:06', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_banks`
--

CREATE TABLE `user_banks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `account_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_verified` tinyint(1) DEFAULT '0',
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_statistics`
-- (See below for the actual view)
--
CREATE TABLE `user_statistics` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`phone` varchar(15)
,`registration_date` timestamp
,`wallet_balance` decimal(15,2)
,`bonus_balance` decimal(15,2)
,`total_transactions` bigint(21)
,`total_spent` decimal(32,2)
,`total_referrals` bigint(21)
,`total_commission` decimal(37,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `balance` decimal(15,2) DEFAULT '0.00',
  `locked_balance` decimal(15,2) DEFAULT '0.00',
  `last_credited_at` datetime DEFAULT NULL,
  `last_debited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `currency`, `balance`, `locked_balance`, `last_credited_at`, `last_debited_at`, `created_at`, `updated_at`) VALUES
(4, 6, 'NGN', 0.00, 0.00, NULL, NULL, '2026-03-19 09:14:12', '2026-03-19 09:14:12'),
(5, 7, 'NGN', 10000.00, 0.00, NULL, NULL, '2026-03-21 14:08:30', '2026-03-21 16:45:41'),
(6, 8, 'NGN', 0.00, 0.00, NULL, NULL, '2026-03-21 16:28:06', '2026-03-21 16:28:06');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','completed','failed','reversed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `funding_request_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_bank_id` int(11) NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `status` enum('pending','processing','success','failed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_response` text COLLATE utf8mb4_unicode_ci,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider` (`provider`),
  ADD KEY `idx_status` (`status_code`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `api_providers`
--
ALTER TABLE `api_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_active` (`is_active`);
ALTER TABLE `api_providers` ADD FULLTEXT KEY `idx_search` (`name`,`code`);

--
-- Indexes for table `banks`
--
ALTER TABLE `banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `error_logs`
--
ALTER TABLE `error_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`error_type`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `funding_requests`
--
ALTER TABLE `funding_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_wallet` (`wallet_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_gateway` (`payment_gateway`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request` (`funding_request_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_referral` (`referrer_id`,`referred_id`),
  ADD KEY `idx_referrer` (`referrer_id`),
  ADD KEY `idx_referred` (`referred_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_referral` (`referral_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_transaction` (`transaction_id`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_provider` (`provider_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `service_variations`
--
ALTER TABLE `service_variations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_variation` (`variation_code`),
  ADD KEY `idx_provider_var` (`provider_variation_id`),
  ADD KEY `idx_network` (`network`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_price` (`amount`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`),
  ADD KEY `idx_key` (`key`),
  ADD KEY `idx_group` (`group_name`),
  ADD KEY `idx_public` (`is_public`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_phone` (`phone`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_wallet_id` (`wallet_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_provider` (`provider`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_processed` (`processed_at`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_transactions_composite` (`user_id`,`status`,`created_at`);
ALTER TABLE `transactions` ADD FULLTEXT KEY `idx_search` (`transaction_id`,`reference`,`provider_reference`);

--
-- Indexes for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_referral` (`referral_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `referred_by` (`referred_by`);

--
-- Indexes for table `user_banks`
--
ALTER TABLE `user_banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_account` (`user_id`,`account_number`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_bank` (`bank_id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_currency` (`user_id`,`currency`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_currency` (`currency`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_wallet` (`wallet_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_funding_request` (`funding_request_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_bank` (`user_bank_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `approved_by` (`approved_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_providers`
--
ALTER TABLE `api_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `banks`
--
ALTER TABLE `banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `error_logs`
--
ALTER TABLE `error_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `funding_requests`
--
ALTER TABLE `funding_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `service_variations`
--
ALTER TABLE `service_variations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_banks`
--
ALTER TABLE `user_banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `daily_sales`
--
DROP TABLE IF EXISTS `daily_sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales`  AS SELECT cast(`transactions`.`created_at` as date) AS `sale_date`, `transactions`.`type` AS `type`, count(0) AS `transaction_count`, sum(`transactions`.`amount`) AS `total_amount`, sum(`transactions`.`fee`) AS `total_fee`, sum(`transactions`.`discount`) AS `total_discount`, ((sum(`transactions`.`amount`) - sum(`transactions`.`fee`)) - sum(`transactions`.`discount`)) AS `net_amount` FROM `transactions` WHERE (`transactions`.`status` = 'success') GROUP BY cast(`transactions`.`created_at` as date), `transactions`.`type` ;

-- --------------------------------------------------------

--
-- Structure for view `transaction_summary`
--
DROP TABLE IF EXISTS `transaction_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `transaction_summary`  AS SELECT `transactions`.`type` AS `type`, count(0) AS `total_count`, sum((case when (`transactions`.`status` = 'success') then 1 else 0 end)) AS `success_count`, sum((case when (`transactions`.`status` = 'failed') then 1 else 0 end)) AS `failed_count`, sum((case when (`transactions`.`status` = 'pending') then 1 else 0 end)) AS `pending_count`, sum(`transactions`.`amount`) AS `total_amount`, sum(`transactions`.`fee`) AS `total_fee`, avg(`transactions`.`amount`) AS `average_amount`, min(`transactions`.`amount`) AS `min_amount`, max(`transactions`.`amount`) AS `max_amount` FROM `transactions` GROUP BY `transactions`.`type` ;

-- --------------------------------------------------------

--
-- Structure for view `user_statistics`
--
DROP TABLE IF EXISTS `user_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_statistics`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`created_at` AS `registration_date`, `u`.`wallet_balance` AS `wallet_balance`, `u`.`bonus_balance` AS `bonus_balance`, (select count(0) from `transactions` where (`transactions`.`user_id` = `u`.`id`)) AS `total_transactions`, (select sum(`transactions`.`amount`) from `transactions` where ((`transactions`.`user_id` = `u`.`id`) and (`transactions`.`status` = 'success'))) AS `total_spent`, (select count(0) from `referrals` where (`referrals`.`referrer_id` = `u`.`id`)) AS `total_referrals`, (select sum(`referrals`.`commission_amount`) from `referrals` where ((`referrals`.`referrer_id` = `u`.`id`) and (`referrals`.`status` = 'paid'))) AS `total_commission` FROM `users` AS `u` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD CONSTRAINT `email_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `funding_requests`
--
ALTER TABLE `funding_requests`
  ADD CONSTRAINT `funding_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `funding_requests_ibfk_2` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_proofs`
--
ALTER TABLE `payment_proofs`
  ADD CONSTRAINT `payment_proofs_ibfk_1` FOREIGN KEY (`funding_request_id`) REFERENCES `funding_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_earnings`
--
ALTER TABLE `referral_earnings`
  ADD CONSTRAINT `referral_earnings_ibfk_1` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referral_earnings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referral_earnings_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `api_providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `services_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_variations`
--
ALTER TABLE `service_variations`
  ADD CONSTRAINT `service_variations_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD CONSTRAINT `sms_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`);

--
-- Constraints for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD CONSTRAINT `transaction_logs_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_logs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_banks`
--
ALTER TABLE `user_banks`
  ADD CONSTRAINT `user_banks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_banks_ibfk_2` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wallet_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawals_ibfk_2` FOREIGN KEY (`user_bank_id`) REFERENCES `user_banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawals_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
