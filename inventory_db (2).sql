-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2026 at 03:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
CREATE DATABASE IF NOT  EXISTS inventory_db;
USE inventory_db;



SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--


DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(64) DEFAULT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 3, 'create', 'users', 3, 'Initial administrator created via setup_admin.php', '::1', '2026-10-16 10:50:04'),
(2, 3, 'login', 'users', 3, NULL, '::1', '2026-10-16 10:50:22'),
(3, 3, 'auto_logout', 'users', 3, 'Inactivity timeout', '::1', '2026-10-16 14:24:31'),
(4, 3, 'login', 'users', 3, NULL, '::1', '2026-10-16 14:24:51'),
(5, 3, 'update', 'items', 3, 'Updated item: Ballpen Black (box of 12) (SKU PEN-BLK-12)', '::1', '2026-10-16 14:31:03'),
(6, 3, 'stock_in', 'stock_transactions', 1, 'Stock-in: item_id 3, qty 15', '::1', '2026-10-16 14:33:23'),
(7, 3, 'stock_in', 'stock_transactions', 2, 'Stock-in: item_id 3, qty 25', '::1', '2026-10-16 14:33:52'),
(8, 3, 'stock_in', 'stock_transactions', 3, 'Stock-in: item_id 3, qty 5', '::1', '2026-10-16 14:34:04'),
(9, 3, 'create', 'items', 8, 'Created item: Standard Wooden Pencil (SKU STA-0008)', '::1', '2026-10-16 14:55:08'),
(10, 3, 'create', 'items', 9, 'Created item: Long Coupon (SKU LON-0009)', '::1', '2026-10-16 14:56:22'),
(11, 3, 'login', 'users', 3, NULL, '::1', '2026-10-17 06:01:38'),
(14, 3, 'login', 'users', 3, NULL, '::1', '2026-10-17 09:57:22'),
(20, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as CSV (8 rows)', '::1', '2026-10-17 10:04:14'),
(21, 3, 'stock_in', 'stock_transactions', 8, 'Stock-in: item_id 2, qty 30', '::1', '2026-10-17 10:13:47'),
(22, 3, 'update', 'users', 3, 'Updated account: Admin123 (role admin)', '::1', '2026-10-17 10:14:59'),
(23, 3, 'create', 'users', 4, 'Created staff account: Maria23', '::1', '2026-10-17 10:22:38'),
(24, 3, 'logout', 'users', 3, NULL, '::1', '2026-10-17 10:22:44'),
(25, 4, 'login', 'users', 4, NULL, '::1', '2026-10-17 10:23:04'),
(26, 4, 'logout', 'users', 4, NULL, '::1', '2026-10-17 10:25:55'),
(27, 3, 'login', 'users', 3, NULL, '::1', '2026-10-17 10:26:00'),
(28, 3, 'stock_in', 'stock_transactions', 9, 'Stock-in: item_id 2, qty 30', '::1', '2026-10-17 10:28:28'),
(29, 3, 'create', 'distributions', 1, 'Distribution to school 1: 1 item(s)', '::1', '2026-10-17 10:44:31'),
(30, 4, 'login', 'users', 4, NULL, '192.168.0.27', '2026-10-17 10:58:48'),
(31, 4, 'logout', 'users', 4, NULL, '192.168.0.27', '2026-10-17 11:06:20'),
(32, 3, 'login', 'users', 3, NULL, '192.168.0.27', '2026-10-17 11:06:29'),
(33, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as CSV (8 rows)', '0.0.0.0', '2026-10-18 12:45:25'),
(34, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as CSV (6 rows)', '0.0.0.0', '2026-10-18 12:45:26'),
(35, 3, 'export', 'reports', NULL, 'Exported Distribution Report as CSV (1 rows)', '0.0.0.0', '2026-10-18 12:45:26'),
(36, 3, 'login', 'users', 3, NULL, '::1', '2026-10-18 12:45:35'),
(37, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as XLS (8 rows)', '::1', '2026-10-18 12:46:01'),
(38, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(39, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as XLS (8 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(40, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as PDF (6 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(41, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as XLS (6 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(42, 3, 'export', 'reports', NULL, 'Exported Distribution Report as PDF (1 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(43, 3, 'export', 'reports', NULL, 'Exported Distribution Report as XLS (1 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(44, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as CSV (0 rows)', '0.0.0.0', '2026-10-18 12:48:26'),
(45, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as CSV (8 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(46, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as CSV (6 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(47, 3, 'export', 'reports', NULL, 'Exported Distribution Report as CSV (1 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(48, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(49, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as PDF (6 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(50, 3, 'export', 'reports', NULL, 'Exported Distribution Report as PDF (1 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(51, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as XLS (8 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(52, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as XLS (6 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(53, 3, 'export', 'reports', NULL, 'Exported Distribution Report as XLS (1 rows)', '0.0.0.0', '2026-10-18 12:49:45'),
(54, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as CSV (0 rows)', '0.0.0.0', '2026-10-18 12:49:46'),
(55, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '::1', '2026-10-18 12:53:04'),
(56, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '0.0.0.0', '2026-10-18 12:55:05'),
(57, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as XLS (6 rows)', '0.0.0.0', '2026-10-18 12:55:05'),
(58, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as PDF (6 rows)', '0.0.0.0', '2026-10-18 12:55:05'),
(59, 3, 'export', 'reports', NULL, 'Exported Distribution Report as PDF (1 rows)', '0.0.0.0', '2026-10-18 12:55:05'),
(60, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '0.0.0.0', '2026-10-18 12:55:13'),
(61, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as PDF (6 rows)', '0.0.0.0', '2026-10-18 12:55:13'),
(62, 3, 'export', 'reports', NULL, 'Exported Distribution Report as PDF (1 rows)', '0.0.0.0', '2026-10-18 12:55:13'),
(63, 3, 'export', 'reports', NULL, 'Exported Stock Status Report as PDF (8 rows)', '0.0.0.0', '2026-10-18 12:55:33'),
(64, 3, 'export', 'reports', NULL, 'Exported Stock Transactions Report as PDF (6 rows)', '0.0.0.0', '2026-10-18 12:55:33'),
(65, 3, 'export', 'reports', NULL, 'Exported Distribution Report as PDF (1 rows)', '0.0.0.0', '2026-10-18 12:55:33');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Paper Products', 'Notebooks, bond paper, pad paper'),
(2, 'Writing Supplies', 'Pens, pencils, markers, crayons'),
(3, 'Art Supplies', 'Paint, brushes, colored paper'),
(4, 'Office Supplies', 'Staplers, folders, tape, scissors');

-- --------------------------------------------------------

--
-- Table structure for table `distributions`
--

DROP TABLE IF EXISTS `distributions`;
CREATE TABLE `distributions` (
  `distribution_id` int(10) UNSIGNED NOT NULL,
  `school_id` int(10) UNSIGNED NOT NULL,
  `distribution_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `distributions`
--

INSERT INTO `distributions` (`distribution_id`, `school_id`, `distribution_date`, `remarks`, `recorded_by`, `created_at`) VALUES
(1, 1, '2026-10-17', NULL, 3, '2026-10-17 10:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `distribution_items`
--

DROP TABLE IF EXISTS `distribution_items`;
CREATE TABLE `distribution_items` (
  `distribution_item_id` int(10) UNSIGNED NOT NULL,
  `distribution_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL
) ;

--
-- Dumping data for table `distribution_items`
--

INSERT INTO `distribution_items` (`distribution_item_id`, `distribution_id`, `item_id`, `quantity`) VALUES
(1, 1, 3, 10);

--
-- Triggers `distribution_items`
--
DROP TRIGGER IF EXISTS `trg_di_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_di_after_insert` AFTER INSERT ON `distribution_items` FOR EACH ROW BEGIN
    DECLARE v_user INT UNSIGNED;
    DECLARE v_date DATE;
    DECLARE v_remarks VARCHAR(255);

    SELECT recorded_by, distribution_date, remarks
      INTO v_user, v_date, v_remarks
      FROM distributions
     WHERE distribution_id = NEW.distribution_id;

    INSERT INTO stock_transactions
        (item_id, transaction_type, quantity_change, distribution_item_id,
         reason, remarks, transaction_date, created_by)
    VALUES
        (NEW.item_id, 'stock_out', -NEW.quantity, NEW.distribution_item_id,
         'Distribution to school', v_remarks,
         TIMESTAMP(v_date, CURTIME()), v_user);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `unit_of_measure` varchar(30) NOT NULL,
  `reorder_point` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_on_hand` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `sku`, `item_name`, `description`, `category_id`, `unit_of_measure`, `reorder_point`, `unit_cost`, `quantity_on_hand`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'NB-80L-001', 'Notebook 80 Leaves', 'Composition notebook', 1, 'pc', 100, 22.00, 0, 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(2, 'BP-A4-500', 'Bond Paper A4 (500 sheets)', '80gsm', 1, 'ream', 50, 235.00, 60, 1, '2026-10-14 14:06:02', '2026-10-17 10:28:28'),
(3, 'PEN-BLK-12', 'Ballpen Black (box of 12)', 'Medium tip', 2, 'box', 40, 60.00, 35, 1, '2026-10-14 14:06:02', '2026-10-17 10:44:31'),
(4, 'PNC-2-50', 'Pencil No. 2 (box of 50)', 'Wood-cased', 2, 'box', 30, 150.00, 0, 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(5, 'CRY-16-001', 'Crayons 16 colors', NULL, 3, 'box', 60, 35.00, 0, 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(6, 'SCS-6-001', 'Scissors 6 inch', 'Blunt tip', 4, 'pc', 25, 28.00, 0, 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(8, 'STA-0008', 'Standard Wooden Pencil', NULL, 2, 'box', 50, 100.00, 0, 1, '2026-10-16 14:55:08', '2026-10-16 14:55:08'),
(9, 'LON-0009', 'Long Coupon', NULL, 1, 'ream', 20, 300.00, 0, 1, '2026-10-16 14:56:21', '2026-10-16 14:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `item_suppliers`
--

DROP TABLE IF EXISTS `item_suppliers`;
CREATE TABLE `item_suppliers` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_suppliers`
--

INSERT INTO `item_suppliers` (`item_id`, `supplier_id`) VALUES
(1, 1),
(2, 1),
(3, 2),
(4, 2),
(5, 2),
(6, 2),
(8, 2),
(9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `low_stock_alerts`
--

DROP TABLE IF EXISTS `low_stock_alerts`;
CREATE TABLE `low_stock_alerts` (
  `alert_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity_at_alert` int(11) NOT NULL,
  `reorder_point` int(10) UNSIGNED NOT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `low_stock_alerts`
--

INSERT INTO `low_stock_alerts` (`alert_id`, `item_id`, `quantity_at_alert`, `reorder_point`, `is_resolved`, `created_at`, `resolved_at`) VALUES
(1, 3, 15, 40, 1, '2026-10-16 14:33:23', '2026-10-16 22:34:04'),
(5, 1, 0, 100, 0, '2026-10-16 15:05:22', NULL),
(6, 2, 0, 50, 1, '2026-10-16 15:05:22', '2026-10-17 18:28:28'),
(7, 4, 0, 30, 0, '2026-10-16 15:05:22', NULL),
(8, 5, 0, 60, 0, '2026-10-16 15:05:22', NULL),
(9, 6, 0, 25, 0, '2026-10-16 15:05:22', NULL),
(10, 8, 0, 50, 0, '2026-10-16 15:05:22', NULL),
(11, 9, 0, 20, 0, '2026-10-16 15:05:22', NULL),
(12, 3, 35, 40, 0, '2026-10-17 10:44:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
CREATE TABLE `schools` (
  `school_id` int(10) UNSIGNED NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Dagupan Central Elementary School', 'Ana Cruz', '0918-000-0001', 'dces@example.edu', 'Dagupan City', 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(2, 'Lingayen National High School', 'Pedro Lim', '0918-000-0002', 'lnhs@example.edu', 'Lingayen, Pangasinan', 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('stock_in','stock_out','adjustment') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL DEFAULT 0,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `distribution_item_id` int(10) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`transaction_id`, `item_id`, `transaction_type`, `quantity_change`, `quantity_after`, `supplier_id`, `distribution_item_id`, `reason`, `remarks`, `transaction_date`, `created_by`, `created_at`) VALUES
(1, 3, 'stock_in', 15, 15, 2, NULL, NULL, NULL, '2026-10-16 00:00:00', 3, '2026-10-16 14:33:23'),
(2, 3, 'stock_in', 25, 40, 2, NULL, NULL, NULL, '2026-10-16 00:00:00', 3, '2026-10-16 14:33:52'),
(3, 3, 'stock_in', 5, 45, NULL, NULL, NULL, NULL, '2026-10-16 00:00:00', 3, '2026-10-16 14:34:04'),
(8, 2, 'stock_in', 30, 30, 2, NULL, NULL, NULL, '2026-10-17 00:00:00', 3, '2026-10-17 10:13:47'),
(9, 2, 'stock_in', 30, 60, 1, NULL, NULL, NULL, '2026-10-17 00:00:00', 3, '2026-10-17 10:28:28'),
(10, 3, 'stock_out', -10, 35, NULL, 1, 'Distribution to school', NULL, '2026-10-17 18:44:31', 3, '2026-10-17 10:44:31');

--
-- Triggers `stock_transactions`
--
DROP TRIGGER IF EXISTS `trg_st_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_st_after_insert` AFTER INSERT ON `stock_transactions` FOR EACH ROW BEGIN
    DECLARE v_reorder INT;
    DECLARE v_open    INT;

    UPDATE items
       SET quantity_on_hand = NEW.quantity_after
     WHERE item_id = NEW.item_id;

    SELECT reorder_point INTO v_reorder
      FROM items WHERE item_id = NEW.item_id;

    SELECT COUNT(*) INTO v_open
      FROM low_stock_alerts
     WHERE item_id = NEW.item_id AND is_resolved = 0;

    IF NEW.quantity_after <= v_reorder THEN
        IF v_open = 0 THEN
            INSERT INTO low_stock_alerts (item_id, quantity_at_alert, reorder_point)
            VALUES (NEW.item_id, NEW.quantity_after, v_reorder);
        END IF;
    ELSE
        UPDATE low_stock_alerts
           SET is_resolved = 1, resolved_at = NOW()
         WHERE item_id = NEW.item_id AND is_resolved = 0;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_st_before_delete`;
DELIMITER $$
CREATE TRIGGER `trg_st_before_delete` BEFORE DELETE ON `stock_transactions` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock transactions are read-only and cannot be deleted.';
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_st_before_insert`;
DELIMITER $$
CREATE TRIGGER `trg_st_before_insert` BEFORE INSERT ON `stock_transactions` FOR EACH ROW BEGIN
    DECLARE v_qty    INT;
    DECLARE v_active TINYINT(1);

    SELECT quantity_on_hand, is_active
      INTO v_qty, v_active
      FROM items
     WHERE item_id = NEW.item_id
       FOR UPDATE;

    IF v_qty IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Item does not exist.';
    END IF;

    IF v_active = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This item is inactive and cannot be used in new transactions.';
    END IF;

    IF NEW.quantity_change = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quantity change cannot be zero.';
    END IF;

    IF NEW.transaction_type = 'stock_in' AND NEW.quantity_change < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock-in quantity must be positive.';
    END IF;

    IF NEW.transaction_type = 'stock_out' AND NEW.quantity_change > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock-out quantity must be negative.';
    END IF;

    IF v_qty + NEW.quantity_change < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient stock: this transaction would make the quantity on hand negative.';
    END IF;

    SET NEW.quantity_after = v_qty + NEW.quantity_change;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_st_before_update`;
DELIMITER $$
CREATE TRIGGER `trg_st_before_update` BEFORE UPDATE ON `stock_transactions` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock transactions are read-only and cannot be edited.';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `company_name`, `contact_person`, `phone`, `email`, `address`, `payment_terms`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Northern Paper Trading', 'Maria Santos', '0917-000-0001', 'sales@northernpaper.example', 'Dagupan City, Pangasinan', 'Net 30', 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02'),
(2, 'Luzon Stationery Corp.', 'Jose Reyes', '0917-000-0002', 'orders@luzonstationery.example', 'San Fernando, La Union', 'COD', 1, '2026-10-14 14:06:02', '2026-10-14 14:06:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `email`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `last_login`, `created_at`, `updated_at`) VALUES
(3, 'Admin', 'Admin123', 'Admin@gmail.com', '$2y$10$6LGaJv5yBYke8CX0bCFoSu4ZGOjIy3.A5qj2wQrgDHFyzQbG6ejDK', 'admin', 1, 0, NULL, '2026-10-18 20:45:35', '2026-10-16 10:50:04', '2026-10-18 12:45:35'),
(4, 'Maria Santos', 'Maria23', 'mariasantos@gmail.com', '$2y$10$fH6sxQw6/8FM8bAIKlURtur2i.33FYTlXt4BxgsiEMeHCMq4CDlRa', 'staff', 1, 0, NULL, '2026-10-17 18:58:48', '2026-10-17 10:22:38', '2026-10-17 10:58:48');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dashboard_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_dashboard_summary`;
CREATE TABLE `v_dashboard_summary` (
`total_active_items` bigint(21)
,`low_stock_items` bigint(21)
,`transactions_this_week` bigint(21)
,`open_alerts` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_distribution_report`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_distribution_report`;
CREATE TABLE `v_distribution_report` (
`distribution_id` int(10) unsigned
,`distribution_date` date
,`school_id` int(10) unsigned
,`school_name` varchar(150)
,`item_id` int(10) unsigned
,`sku` varchar(50)
,`item_name` varchar(150)
,`unit_of_measure` varchar(30)
,`quantity` int(11)
,`remarks` varchar(255)
,`recorded_by` int(10) unsigned
,`recorded_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stock_status`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_stock_status`;
CREATE TABLE `v_stock_status` (
`item_id` int(10) unsigned
,`sku` varchar(50)
,`item_name` varchar(150)
,`category_name` varchar(100)
,`unit_of_measure` varchar(30)
,`quantity_on_hand` int(11)
,`reorder_point` int(10) unsigned
,`status` varchar(6)
,`is_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_stock_transaction_report`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_stock_transaction_report`;
CREATE TABLE `v_stock_transaction_report` (
`transaction_id` int(10) unsigned
,`transaction_date` datetime
,`transaction_type` enum('stock_in','stock_out','adjustment')
,`item_id` int(10) unsigned
,`sku` varchar(50)
,`item_name` varchar(150)
,`quantity_change` int(11)
,`quantity_after` int(11)
,`supplier_id` int(10) unsigned
,`supplier_name` varchar(150)
,`reason` varchar(255)
,`remarks` varchar(255)
,`created_by` int(10) unsigned
,`recorded_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `v_dashboard_summary`
--
DROP TABLE IF EXISTS `v_dashboard_summary`;

DROP VIEW IF EXISTS `v_dashboard_summary`;
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_dashboard_summary`  AS SELECT (select count(0) from `items` where `items`.`is_active` = 1) AS `total_active_items`, (select count(0) from `items` where `items`.`is_active` = 1 and `items`.`quantity_on_hand` <= `items`.`reorder_point`) AS `low_stock_items`, (select count(0) from `stock_transactions` where `stock_transactions`.`transaction_date` >= curdate() - interval weekday(curdate()) day) AS `transactions_this_week`, (select count(0) from `low_stock_alerts` where `low_stock_alerts`.`is_resolved` = 0) AS `open_alerts` ;

-- --------------------------------------------------------

--
-- Structure for view `v_distribution_report`
--
DROP TABLE IF EXISTS `v_distribution_report`;

DROP VIEW IF EXISTS `v_distribution_report`;
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_distribution_report`  AS SELECT `d`.`distribution_id` AS `distribution_id`, `d`.`distribution_date` AS `distribution_date`, `d`.`school_id` AS `school_id`, `sc`.`school_name` AS `school_name`, `di`.`item_id` AS `item_id`, `i`.`sku` AS `sku`, `i`.`item_name` AS `item_name`, `i`.`unit_of_measure` AS `unit_of_measure`, `di`.`quantity` AS `quantity`, `d`.`remarks` AS `remarks`, `d`.`recorded_by` AS `recorded_by`, `u`.`full_name` AS `recorded_by_name` FROM ((((`distributions` `d` join `schools` `sc` on(`sc`.`school_id` = `d`.`school_id`)) join `distribution_items` `di` on(`di`.`distribution_id` = `d`.`distribution_id`)) join `items` `i` on(`i`.`item_id` = `di`.`item_id`)) join `users` `u` on(`u`.`user_id` = `d`.`recorded_by`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_stock_status`
--
DROP TABLE IF EXISTS `v_stock_status`;

DROP VIEW IF EXISTS `v_stock_status`;
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_stock_status`  AS SELECT `i`.`item_id` AS `item_id`, `i`.`sku` AS `sku`, `i`.`item_name` AS `item_name`, `c`.`category_name` AS `category_name`, `i`.`unit_of_measure` AS `unit_of_measure`, `i`.`quantity_on_hand` AS `quantity_on_hand`, `i`.`reorder_point` AS `reorder_point`, CASE WHEN `i`.`quantity_on_hand` <= `i`.`reorder_point` THEN 'Low' ELSE 'Normal' END AS `status`, `i`.`is_active` AS `is_active` FROM (`items` `i` join `categories` `c` on(`c`.`category_id` = `i`.`category_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_stock_transaction_report`
--
DROP TABLE IF EXISTS `v_stock_transaction_report`;

DROP VIEW IF EXISTS `v_stock_transaction_report`;
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_stock_transaction_report`  AS SELECT `t`.`transaction_id` AS `transaction_id`, `t`.`transaction_date` AS `transaction_date`, `t`.`transaction_type` AS `transaction_type`, `t`.`item_id` AS `item_id`, `i`.`sku` AS `sku`, `i`.`item_name` AS `item_name`, `t`.`quantity_change` AS `quantity_change`, `t`.`quantity_after` AS `quantity_after`, `t`.`supplier_id` AS `supplier_id`, `s`.`company_name` AS `supplier_name`, `t`.`reason` AS `reason`, `t`.`remarks` AS `remarks`, `t`.`created_by` AS `created_by`, `u`.`full_name` AS `recorded_by_name` FROM (((`stock_transactions` `t` join `items` `i` on(`i`.`item_id` = `t`.`item_id`)) join `users` `u` on(`u`.`user_id` = `t`.`created_by`)) left join `suppliers` `s` on(`s`.`supplier_id` = `t`.`supplier_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_al_user` (`user_id`),
  ADD KEY `idx_al_created` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_categories_name` (`category_name`);

--
-- Indexes for table `distributions`
--
ALTER TABLE `distributions`
  ADD PRIMARY KEY (`distribution_id`),
  ADD KEY `idx_dist_school` (`school_id`),
  ADD KEY `idx_dist_date` (`distribution_date`),
  ADD KEY `idx_dist_user` (`recorded_by`);

--
-- Indexes for table `distribution_items`
--
ALTER TABLE `distribution_items`
  ADD PRIMARY KEY (`distribution_item_id`),
  ADD KEY `idx_di_dist` (`distribution_id`),
  ADD KEY `idx_di_item` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uq_items_sku` (`sku`),
  ADD KEY `idx_items_category` (`category_id`),
  ADD KEY `idx_items_name` (`item_name`);

--
-- Indexes for table `item_suppliers`
--
ALTER TABLE `item_suppliers`
  ADD PRIMARY KEY (`item_id`,`supplier_id`),
  ADD KEY `idx_is_supplier` (`supplier_id`);

--
-- Indexes for table `low_stock_alerts`
--
ALTER TABLE `low_stock_alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `idx_lsa_item_resolved` (`item_id`,`is_resolved`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`school_id`),
  ADD UNIQUE KEY `uq_schools_name` (`school_name`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_st_item_date` (`item_id`,`transaction_date`),
  ADD KEY `idx_st_date` (`transaction_date`),
  ADD KEY `idx_st_type` (`transaction_type`),
  ADD KEY `idx_st_supplier` (`supplier_id`),
  ADD KEY `idx_st_user` (`created_by`),
  ADD KEY `fk_st_distitem` (`distribution_item_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_suppliers_name` (`company_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `distributions`
--
ALTER TABLE `distributions`
  MODIFY `distribution_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `distribution_items`
--
ALTER TABLE `distribution_items`
  MODIFY `distribution_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `low_stock_alerts`
--
ALTER TABLE `low_stock_alerts`
  MODIFY `alert_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `school_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `transaction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `distributions`
--
ALTER TABLE `distributions`
  ADD CONSTRAINT `fk_dist_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`school_id`),
  ADD CONSTRAINT `fk_dist_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `distribution_items`
--
ALTER TABLE `distribution_items`
  ADD CONSTRAINT `fk_di_dist` FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`distribution_id`),
  ADD CONSTRAINT `fk_di_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `item_suppliers`
--
ALTER TABLE `item_suppliers`
  ADD CONSTRAINT `fk_is_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_is_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE;

--
-- Constraints for table `low_stock_alerts`
--
ALTER TABLE `low_stock_alerts`
  ADD CONSTRAINT `fk_lsa_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `fk_st_distitem` FOREIGN KEY (`distribution_item_id`) REFERENCES `distribution_items` (`distribution_item_id`),
  ADD CONSTRAINT `fk_st_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_st_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `fk_st_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);
SET FOREIGN_KEY_CHECKS=1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
