-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 24, 2026 at 01:24 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `giftly_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `label` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `address`, `city`, `province`, `zip`, `created_at`, `label`) VALUES
(6, 4, 'Blk 17 lot 3 Evening Glow Street Ridgemont Executive Village San Isidro', 'Taytay, Rizal', 'RIZAL', '1920', '2026-08-21 06:09:45', 'Home'),
(7, 4, '1', '1', '1', '1', '2026-08-21 06:15:35', 'test'),
(8, 4, '2', '2', '2', '2', '2026-08-21 06:15:52', 'test2'),
(10, 12, 'Blk 17 lot 3 Evening Glow Street Ridgemont Executive Village San Isidro', 'Taytay, Rizal', 'RIZAL', '1920', '2026-08-23 05:48:16', 'Home');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `product_id`, `quantity`) VALUES
(165, 15, 10, 1),
(211, 13, 10, 2),
(216, 19, 23, 2),
(230, 12, 17, 1),
(231, 12, 16, 1),
(232, 12, 9, 1),
(278, 22, 16, 5),
(279, 22, 19, 1),
(281, 22, 17, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(21, 'Chocolates'),
(11, 'Hair Accessories'),
(13, 'Keychains'),
(12, 'Phone Accessories'),
(10, 'Plushies');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fullname` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cod',
  `gift_message` text NOT NULL,
  `sender_phone` varchar(20) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `delivery_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `category_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image`, `quantity`, `category_id`) VALUES
(9, 'Pompompurin Plushie', 'A soft and cuddly Pompompurin plushie made with premium materials, featuring embroidered details and Pompompurin\'s signature brown beret. Perfect for hugging, collecting, or gifting to Sanrio fans.', 499.00, 'product_1785892781.png', 0, 10),
(10, 'Korilakkuma Plushie', 'A soft and huggable Korilakkuma plushie with a fluffy finish and adorable embroidered details. Perfect for cuddling, displaying, or gifting to Rilakkuma fans.', 499.00, 'product_1785892813.png', 0, 10),
(16, 'Mofusand Bunny Cat Plushie', 'An adorable Mofusand plushie featuring a cute cat dressed in a fluffy bunny costume. Soft, cuddly, and perfect for collectors or as a charming gift.', 549.00, 'product_1785892848.png', 5, 10),
(17, 'Miffy Plushie', 'A soft and lovable Miffy plushie with a simple, timeless design. Perfect for cuddling, decorating your space, or gifting to Miffy fans.', 499.00, 'product_1785892884.png', 24, 10),
(19, 'Cinnamoroll Plushie', 'A fluffy Cinnamoroll plushie featuring its signature long ears and sweet smile. Perfect for cuddles, room décor, or adding to your Sanrio collection.', 499.00, 'product_1785892915.png', 25, 10),
(22, 'Sakura Hair Clips (Set of 2)', 'A pair of elegant sakura flower hair clips that add a cute and delicate touch to any hairstyle.', 129.00, 'product_1785893803.png', 25, 11),
(23, 'Korilakkuma Plush Hair Tie', 'A soft and fluffy Korilakkuma hair tie featuring an adorable plush design that adds a cute touch to ponytails and buns.', 169.00, 'product_1785893870.png', 24, 11),
(24, 'Pink Flower Hair Claw Clip', 'A stylish pink flower-shaped hair claw clip that\'s perfect for everyday wear and effortless hairstyles.', 149.00, 'product_1785894126.png', 20, 11),
(25, 'My Melody Plush Headband', 'A cute and comfortable My Melody headband made with soft plush material, perfect for skincare, makeup, or casual wear.', 299.00, 'product_1785894158.png', 24, 11),
(26, 'Korilakkuma Bunny Plush Hair Clip', 'An adorable Korilakkuma plush hair clip featuring bunny ears, designed to add a playful and kawaii touch to your look.', 159.00, 'product_1785894184.png', 23, 11),
(27, 'White Beaded Phone Charm', 'A stylish white beaded phone charm that adds a simple and elegant touch to your phone or accessories.', 149.00, 'product_1785894749.png', 23, 12),
(28, 'Korilakkuma Phone Strap', 'A cute Korilakkuma phone strap featuring a charming design, perfect for decorating your phone, bag, or keys.', 179.00, 'product_1785894779.png', 25, 12),
(29, 'Hello Kitty Heart Plush Charm', 'An adorable Hello Kitty plush charm with a heart design, perfect for accessorizing your bag, keys, or pouch.', 199.00, 'product_1785894811.png', 25, 12),
(30, 'Strawberry Phone Strap', 'A cute strawberry-themed phone strap that adds a playful and colorful touch to your phone or accessories.', 148.97, 'product_1785894842.png', 25, 12),
(31, 'Hello Kitty Plush Phone Strap', 'A soft Hello Kitty phone strap featuring a mini plush charm, perfect for adding a kawaii touch to your phone or bag.', 199.00, 'product_1785894865.png', 25, 12),
(32, 'My Sweet Piano Plush Keychain', 'A sweet My Sweet Piano plush keychain in soft pink and brown tones, perfect for decorating your keys, bag, or pouch.', 199.00, 'product_1785895550.png', 25, 13),
(33, 'Blue Beaded Keychain', 'A stylish blue beaded keychain that adds a simple yet charming touch to your keys, bag, or accessories.', 149.00, 'product_1785895672.png', 25, 13),
(34, 'Cinnamoroll × Mofusand Keychain', 'An adorable keychain featuring a Cinnamoroll-inspired Mofusand design, perfect for fans of cute collectibles.', 198.97, 'product_1785895796.png', 25, 13),
(35, 'Pink Bear Plush Keychain', 'A soft pink bear plush keychain that\'s perfect for accessorizing your keys, backpack, or handbag.', 179.00, 'product_1785895872.png', 25, 13),
(36, 'Chiikawa Keychain', 'A cute Chiikawa keychain featuring an adorable character design, ideal for decorating bags, keys, or pouches.', 179.00, 'product_1785895895.png', 25, 13),
(38, 'Strawberry & Milk Chocolate Hearts — 4 pcs', 'A delightful box of four heart-shaped chocolates featuring a sweet combination of strawberry and creamy milk chocolate flavors. Perfect as a simple yet thoughtful gift.', 299.00, 'product_1787026041.png', 17, 21),
(39, 'Ferrero Rocher Heart Box — 8 pcs', 'An elegant heart-shaped box filled with eight luxurious Ferrero Rocher chocolates, perfect for gifting on special occasions.', 599.00, 'product_1787026118.png', 17, 21),
(40, 'Dark Chocolate Heart Box — 8 pcs', 'Eight rich dark chocolate hearts presented in a beautiful red heart-shaped box, combining indulgent flavor with an elegant presentation.', 399.00, 'product_1787026152.png', 17, 21),
(41, 'Casa de Flores Dubai Chewy Cookies — 4 pcs', 'Four premium Dubai-style chewy cookies from Casa de Flores, offering a rich, indulgent texture and deliciously satisfying flavor.', 349.00, 'product_1787026190.png', 18, 21),
(42, 'The Grand Chocolate Collection — 16 pcs', 'A luxurious collection of 16 premium chocolates featuring an exquisite assortment of milk, dark, and specialty chocolate flavors, beautifully presented in an elegant gift box.', 899.00, 'product_1787026215.png', 18, 21),
(43, 'TEST', 't', 1.00, 'product_1787150621.png', 0, 21),
(44, 'burger', 'i am infatuated', 1.00, 'product_1787379310.jpg', 0, 10),
(45, 'jumbalia', 'krill', 192.00, 'product_1787379652.jpg', 25, 21);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer') NOT NULL DEFAULT 'customer',
  `reset_token` varchar(500) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `reset_token`, `token_expiry`, `phone`, `created_at`, `profile_pic`) VALUES
(4, 'PEATZIE ARABELA', 'qpacosino@tip.edu.ph', '$2y$10$BKWVZiU30ToeP.eRJCVxW.3YjNUhafQ9n6DJ5I6iAHHa4n/fvOgHK', 'admin', NULL, NULL, '', '2026-08-20 10:55:25', ''),
(12, 'PEATZIE COSINO', 'cosino@tip.edu.ph', '$2y$10$OD900faxI12dHW/2ZPO4v.RKr11wgUzXhrALWQwM7/I2SZUKkBH.6', 'customer', NULL, NULL, '09817161712', '2026-08-20 10:55:25', NULL),
(13, 'Giftly Admin', 'admin@giftly.com', '$2y$10$gLbX7sHYEM/Cf5sueQXei.bUfQHkesApUavJfisZbwNe1tiR99JtG', 'admin', NULL, NULL, '01234567890', '2026-08-20 10:55:25', NULL),
(17, 'gela cosino', 'spilledmilk1324@gmail.com', '$2y$10$/AvEn2/i9ccRSP/XKEzt.eZhjy4NLYjgBpGBGQBwf3qJu5g5URz.y', 'customer', NULL, NULL, '09123456678', '2026-08-22 03:00:41', NULL),
(23, 'TEST TEST', 'TEST@TEST.COM', '$2y$10$NTEMuaiqeHCwSgzquy6T2O5tR6cXlfXBBuC9sIDKmUQdDeoiKYDr2', 'customer', NULL, NULL, '01292910291', '2026-08-24 09:23:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=330;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
