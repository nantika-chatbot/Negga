-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 03, 2026 at 10:22 PM
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
-- Database: `negga_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `free_shipping_min` decimal(10,2) NOT NULL DEFAULT 0.00,
  `enable_shipping_rule` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `shipping_fee`, `free_shipping_min`, `enable_shipping_rule`) VALUES
(1, 'อาหารเสริม', '2026-04-03 19:33:29', 50.00, 3000.00, 1),
(2, 'เครื่องดื่ม', '2026-04-03 19:33:29', 0.00, 0.00, 0),
(3, 'เสื้อผ้า', '2026-04-03 19:33:29', 0.00, 0.00, 0),
(7, 'เกษตร', '2026-04-03 19:45:18', 100.00, 5000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `member_code` varchar(30) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `total_spent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `member_code`, `name`, `phone`, `address`, `points`, `total_spent`, `created_at`) VALUES
(1, NULL, 'ลูกค้าทั่วไป', '0000000000', NULL, 0, 0.00, '2026-04-03 19:33:29'),
(2, NULL, 'สมชาย ใจดี', '0812345678', NULL, 0, 0.00, '2026-04-03 19:33:29'),
(3, NULL, 'วิภา สุขใจ', '0899999999', NULL, 0, 0.00, '2026-04-03 19:33:29'),
(4, NULL, 'ลูกค้าทั่วไป', '0000000000', NULL, 0, 0.00, '2026-04-03 19:36:02'),
(5, NULL, 'สมชาย ใจดี', '0812345678', NULL, 0, 0.00, '2026-04-03 19:36:02'),
(6, NULL, 'วิภา สุขใจ', '0899999999', NULL, 0, 0.00, '2026-04-03 19:36:02'),
(9, '001', 'ตาลลี่', '0983439692', '', 0, 0.00, '2026-04-03 20:17:27');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `unit_name` varchar(50) NOT NULL DEFAULT 'ชิ้น',
  `category_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 7.00,
  `vat_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `barcode`, `name`, `unit_name`, `category_id`, `cost_price`, `vat_rate`, `vat_amount`, `price`, `min_stock`, `stock_qty`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(1, 'NG001', 'NG001', 'Bito Faem Negga 46', 'กล่อง', 7, 1168.22, 7.00, 81.78, 1250.00, 100, 100, 'uploads/products/product_20260404024809_f8d04ce8.png', 'active', '2026-04-03 19:33:29', '2026-04-03 19:48:09'),
(2, 'NG002', 'NG002', 'Bito Faem Negga 47', 'กล่อง', 7, 831.78, 7.00, 58.22, 890.00, 100, 88, 'uploads/products/product_20260404024818_6b23f9c8.png', 'active', '2026-04-03 19:33:29', '2026-04-03 20:19:19'),
(3, 'NG003', 'NG003', 'Bito Faem Negga 48', 'ขวด', 7, 644.86, 7.00, 45.14, 690.00, 100, 99, 'uploads/products/product_20260404024826_2962b049.png', 'active', '2026-04-03 19:33:29', '2026-04-03 20:17:51'),
(4, 'NG004', 'NG004', 'Nicoplus Nad Small', 'กระปุก', 1, 915.89, 7.00, 64.11, 980.00, 100, 99, 'uploads/products/product_20260404024836_bc852219.png', 'active', '2026-04-03 19:33:29', '2026-04-03 20:15:46'),
(5, 'NG005', 'NG005', 'Nicoplus Nad Big', 'กระปุก', 1, 2663.55, 7.00, 186.45, 2850.00, 100, 99, 'uploads/products/product_20260404024844_fe6d4412.png', 'active', '2026-04-03 19:33:29', '2026-04-03 20:15:46'),
(6, 'NG006', 'NG006', 'Midass Coffee', 'ถุง', 2, 327.10, 7.00, 22.90, 350.00, 100, 99, 'uploads/products/product_20260404024850_641d2281.png', 'active', '2026-04-03 19:33:29', '2026-04-03 20:15:46'),
(7, 'NG007', 'NG007', 'เสื้อยืดขาว NEGGA', 'ตัว', 3, 149.53, 7.00, 10.47, 160.00, 100, 101, 'uploads/products/product_20260404024857_fa154c4b.png', 'active', '2026-04-03 19:33:29', '2026-04-03 19:49:30'),
(8, 'NG008', 'NG008', 'เสื้อคอปกเขียว NEGGA', 'ตัว', 3, 233.64, 7.00, 16.36, 250.00, 100, 101, 'uploads/products/product_20260404024904_64ac194c.png', 'active', '2026-04-03 19:33:29', '2026-04-03 19:49:24');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `promo_type` varchar(50) NOT NULL DEFAULT 'buy_x_get_y',
  `product_id` int(10) UNSIGNED NOT NULL,
  `buy_qty` int(11) NOT NULL DEFAULT 1,
  `free_qty` int(11) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_no` varchar(30) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name_snapshot` varchar(255) DEFAULT NULL,
  `customer_phone_snapshot` varchar(50) DEFAULT NULL,
  `customer_address_snapshot` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `vat_amount` decimal(10,2) NOT NULL,
  `grand_total` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','transfer','card','qr') NOT NULL DEFAULT 'cash',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipping_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_method` varchar(30) DEFAULT NULL,
  `cash_received` decimal(10,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$2yTR84.kbxme0rASBOHctOC4uixzoLgoBUWJvGT1HKF9SeLyC0O4e', 'Administrator', 'admin', '2026-04-03 19:33:29'),
(3, 'tan', '$2y$10$2Z2ZDW5xWL.p6CXxGghkJO09hkW/n3PCrJADWZmar/NQk9zWL0fP2', 'ช่างตาล', 'staff', '2026-04-03 20:00:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_member_code` (`member_code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_no` (`sale_no`),
  ADD KEY `fk_sales_customer` (`customer_id`),
  ADD KEY `fk_sales_user` (`user_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_sale` (`sale_id`),
  ADD KEY `fk_sale_items_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
