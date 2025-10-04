-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 04, 2025 at 05:00 PM
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
-- Database: `u412552698_dbhanapbahay`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_auto_replies`
--

CREATE TABLE `chat_auto_replies` (
  `id` int(11) NOT NULL,
  `trigger_pattern` varchar(255) NOT NULL,
  `response_message` text NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_active` tinyint(1) DEFAULT 1,
  `match_type` enum('contains','starts_with','exact','regex') DEFAULT 'contains'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_auto_replies`
--

INSERT INTO `chat_auto_replies` (`id`, `trigger_pattern`, `response_message`, `category`, `is_active`, `match_type`) VALUES
(1, 'services', 'We offer residential rental services including property viewing, application processing, and tenant support. Our properties range from studio units to family homes with various amenities.', 'services', 1, 'contains'),
(2, 'viewing', 'I can help arrange a property viewing! Please let me know your preferred date and time, and I\'ll check availability with the property owner.', 'viewing', 1, 'contains'),
(3, 'schedule', 'I can help arrange a property viewing! Please let me know your preferred date and time, and I\'ll check availability with the property owner.', 'viewing', 1, 'contains'),
(4, 'rates', 'Property rates vary depending on location, size, and amenities. You can view specific pricing details on each property listing. Would you like information about a particular property?', 'pricing', 1, 'contains'),
(5, 'price', 'Property rates vary depending on location, size, and amenities. You can view specific pricing details on each property listing. Would you like information about a particular property?', 'pricing', 1, 'contains'),
(6, 'parking', 'Parking availability varies by property. Some include dedicated parking spaces while others may have street parking. Please check the specific property details or ask about a particular listing.', 'amenities', 1, 'contains'),
(7, 'utilities', 'Utility inclusions vary by property. Some rentals include water and electricity, while others may be separate. Please check the property details or ask about specific utilities for the property you\'re interested in.', 'utilities', 1, 'contains'),
(8, 'pets', 'Pet policies vary by property and owner preference. Some properties welcome pets while others don\'t allow them. Please ask about the specific pet policy for the property you\'re interested in.', 'policies', 1, 'contains'),
(9, 'hello', 'Hello! Welcome to HanapBahay. How can I help you find your ideal rental property today?', 'greeting', 1, 'contains'),
(10, 'hi', 'Hi there! Welcome to HanapBahay. How can I help you find your ideal rental property today?', 'greeting', 1, 'contains'),
(11, 'thank you', 'You\'re welcome! Feel free to ask if you have any other questions about our rental properties.', 'courtesy', 1, 'contains'),
(12, 'thanks', 'You\'re welcome! Feel free to ask if you have any other questions about our rental properties.', 'courtesy', 1, 'contains');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `thread_id`, `sender_id`, `body`, `created_at`, `read_at`) VALUES
(28, 7, 14, 'Are pets allowed?', '2025-10-01 18:12:03', NULL),
(29, 7, 35, 'Pet policies vary by property and owner preference. Some properties welcome pets while others don\'t allow them. Please ask about the specific pet policy for the property you\'re interested in.', '2025-10-01 18:12:04', NULL),
(30, 7, 14, 'Are pets allowed?', '2025-10-01 18:14:06', NULL),
(31, 7, 35, 'Pet policies vary by property and owner preference. Some properties welcome pets while others don\'t allow them. Please ask about the specific pet policy for the property you\'re interested in.', '2025-10-01 18:14:07', NULL),
(32, 7, 35, 'Yes, pets are allowed', '2025-10-01 18:14:38', NULL),
(33, 7, 35, 'How many pets do you have?', '2025-10-01 18:19:26', NULL),
(34, 8, 0, 'Chat started for this listing.', '2025-10-01 18:36:59', NULL),
(35, 8, 14, 'Are pets allowed?', '2025-10-01 18:37:03', NULL),
(36, 8, 19, 'Pet policies vary by property and owner preference. Some properties welcome pets while others don\'t allow them. Please ask about the specific pet policy for the property you\'re interested in.', '2025-10-01 18:37:04', NULL),
(37, 9, 0, 'Chat started for this listing.', '2025-10-02 22:21:42', NULL),
(38, 9, 44, 'Can I schedule a viewing?', '2025-10-02 22:21:48', NULL),
(39, 9, 20, 'I can help arrange a property viewing! Please let me know your preferred date and time, and I\'ll check availability with the property owner.', '2025-10-02 22:21:49', NULL),
(40, 9, 44, 'Hello', '2025-10-02 22:24:09', NULL),
(41, 9, 20, 'Hello! Welcome to HanapBahay. How can I help you find your ideal rental property today?', '2025-10-02 22:24:10', NULL),
(42, 2, 14, 'Are pets allowed?', '2025-10-03 12:27:00', NULL),
(43, 2, 20, 'Pet policies vary by property and owner preference. Some properties welcome pets while others don\'t allow them. Please ask about the specific pet policy for the property you\'re interested in.', '2025-10-03 12:27:01', NULL),
(44, 7, 14, 'When are you available?', '2025-10-03 12:28:12', NULL),
(45, 7, 14, 'Can I schedule a viewing?', '2025-10-03 12:29:13', NULL),
(46, 7, 35, 'I can help arrange a property viewing! Please let me know your preferred date and time, and I\'ll check availability with the property owner.', '2025-10-03 12:29:14', NULL),
(47, 7, 14, 'How much per unit?', '2025-10-03 12:31:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_participants`
--

CREATE TABLE `chat_participants` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('tenant','owner') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_participants`
--

INSERT INTO `chat_participants` (`id`, `thread_id`, `user_id`, `role`) VALUES
(1, 1, 35, 'tenant'),
(2, 1, 32, 'owner'),
(3, 2, 14, 'tenant'),
(4, 2, 20, 'owner'),
(5, 3, 14, 'tenant'),
(6, 3, 19, 'owner'),
(7, 4, 14, 'tenant'),
(8, 4, 21, 'owner'),
(9, 5, 42, 'tenant'),
(10, 5, 18, 'owner'),
(11, 6, 42, 'tenant'),
(12, 6, 19, 'owner'),
(13, 7, 14, 'tenant'),
(14, 7, 35, 'owner'),
(15, 8, 14, 'tenant'),
(16, 8, 19, 'owner'),
(17, 9, 44, 'tenant'),
(18, 9, 20, 'owner');

-- --------------------------------------------------------

--
-- Table structure for table `chat_quick_replies`
--

CREATE TABLE `chat_quick_replies` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_quick_replies`
--

INSERT INTO `chat_quick_replies` (`id`, `message`, `category`, `display_order`, `is_active`) VALUES
(1, 'What services do you offer?', 'services', 1, 1),
(2, 'Can I schedule a viewing?', 'viewing', 2, 1),
(3, 'What are your rates?', 'pricing', 3, 1),
(4, 'Is parking available?', 'amenities', 4, 1),
(5, 'What utilities are included?', 'utilities', 5, 1),
(6, 'Are pets allowed?', 'policies', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `chat_threads`
--

CREATE TABLE `chat_threads` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_threads`
--

INSERT INTO `chat_threads` (`id`, `listing_id`, `created_at`) VALUES
(1, NULL, '2025-09-12 06:43:48'),
(2, 9, '2025-09-18 15:33:21'),
(3, NULL, '2025-09-18 15:34:14'),
(4, 12, '2025-09-18 15:34:20'),
(5, 5, '2025-09-18 20:40:40'),
(6, 17, '2025-09-20 13:36:23'),
(7, 19, '2025-09-26 09:23:37'),
(8, 17, '2025-10-01 18:36:59'),
(9, 9, '2025-10-02 22:21:42');

-- --------------------------------------------------------

--
-- Table structure for table `rental_requests`
--

CREATE TABLE `rental_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_option` varchar(10) DEFAULT NULL,
  `amount_due` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `receipt_file` varchar(255) DEFAULT NULL,
  `amount_to_pay` decimal(12,2) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_requests`
--

INSERT INTO `rental_requests` (`id`, `tenant_id`, `listing_id`, `payment_method`, `payment_option`, `amount_due`, `status`, `requested_at`, `receipt_file`, `amount_to_pay`, `receipt_path`) VALUES
(12, 44, 9, 'gcash', 'full', 8000.00, 'cancelled', '2025-10-03 14:59:51', NULL, 8000.00, 'uploads/receipts/20251003/rcpt_44_9_1759474791_583ba483.png'),
(13, 44, 9, 'gcash', 'full', 8000.00, 'approved', '2025-10-03 15:06:36', NULL, 8000.00, 'uploads/receipts/20251003/rcpt_44_9_1759475196_d8a157a8.png');

-- --------------------------------------------------------

--
-- Table structure for table `tbadmin`
--

CREATE TABLE `tbadmin` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('tenant','unit_owner','admin') NOT NULL DEFAULT 'tenant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_token` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_code` varchar(6) DEFAULT NULL,
  `code_expiry` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `reset_code` varchar(6) DEFAULT NULL,
  `gcash_name` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(32) DEFAULT NULL,
  `gcash_qr_path` varchar(255) DEFAULT NULL,
  `paymaya_name` varchar(255) DEFAULT NULL,
  `paymaya_number` varchar(20) DEFAULT NULL,
  `paymaya_qr_path` varchar(500) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_name` varchar(255) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbadmin`
--

INSERT INTO `tbadmin` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `created_at`, `verification_token`, `is_verified`, `verification_code`, `code_expiry`, `login_attempts`, `lock_until`, `profile_image`, `password_reset_token`, `token_expiry`, `reset_code`, `gcash_name`, `gcash_number`, `gcash_qr_path`, `paymaya_name`, `paymaya_number`, `paymaya_qr_path`, `bank_name`, `bank_account_name`, `bank_account_number`, `reset_expiry`, `last_verified_at`) VALUES
(14, 'Moja', 'Dog', 'mojadog21@gmail.com', '$2y$10$qCM5lar1dos4ERXDTFOlEOwFbYv5jGS6hXDIb1/iakT.T7nr7gVGG', 'tenant', '2025-04-25 18:45:11', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 09:18:08'),
(18, 'Owner1', '', 'doctoredre420@gmail.com', '$2y$10$Q056oHF7TcY4TNHf1AtPT.3R.05GvINU0pgJ/.KDFwq07wOp66Tgy', 'unit_owner', '2025-05-11 09:54:06', NULL, 1, '253287', '2025-06-10 07:43:28', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Mora', 'da', 'morada0813@gmail.com', '$2y$10$95au7TX/o2KOWkoVKq01x./Z2OHvLkhPB8e0BGfFcKo.XNVyrMM92', 'unit_owner', '2025-05-11 09:55:20', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'Angelo Hipolito Morada', '09989047371', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Gabriel', 'Gregorio', 'gabrieldg2728@gmail.com', '$2y$10$oN.4VnuZg3Zbtcd0nZdxMesdX/pzzcfpmIAzsBW2iVNfBIPa2ilgi', 'unit_owner', '2025-05-11 10:04:34', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, '<br /><b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string ', '<br /><b>Deprecated</b>:  htmlsp', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Owner4', '', 'alliannecris21@gmail.com', '$2y$10$762PhrO6ABKG7ggP1N/ODO0BdTNdq4i6VqJj32Mew.EbSp4XIKdCC', 'unit_owner', '2025-05-11 10:23:10', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'Allianne Cris', 'Amodia', 'eysie2@gmail.com', '$2y$10$uhvm/1UYWE9hCa4HQX7VyOAuwaIe0tZ.4NhOmeL6GX3BDUIZty2xm', 'unit_owner', '2025-07-07 02:39:13', '5b6a58b59550ccb03e133f00bac77dd0', 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 09:16:31'),
(42, 'Angelo', 'Moradius', 'angelo.morada@my.jru.edu', '$2y$10$K/fVtWZJf3X4oboB4Yl57.2ajKacPvcs1WllxHWNqkxZwbCtnI48S', 'tenant', '2025-09-06 10:30:25', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'System', 'Admin', 'ethos.cpstn@gmail.com', '$2y$10$yAlvGt65EtsiR/8JIsnCHeH3yqOk0sU1W7f9JfSS7myoNdvhPIRmO', 'admin', '2025-09-19 09:26:43', NULL, 0, '574931', '2025-10-03 21:05:35', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-19 09:39:02'),
(44, 'TGabriel', 'Gregorio', 'gabrielgreg27@gmail.com', '$2y$10$4SHbsWygZa2759ete52QUOLDc5Pcfl1Q0dAjh5Kw7Rdjzn3Q9J9fe', 'tenant', '2025-09-23 15:47:17', NULL, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tblistings`
--

CREATE TABLE `tblistings` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `capacity` int(11) DEFAULT 1,
  `gcash_name` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(32) DEFAULT NULL,
  `gcash_qr_path` varchar(255) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_available` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `gov_id_path` varchar(500) DEFAULT NULL,
  `property_photos` text DEFAULT NULL,
  `verification_status` varchar(20) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `bedroom` int(11) DEFAULT 1 COMMENT 'Number of bedrooms',
  `unit_sqm` decimal(10,2) DEFAULT 20.00 COMMENT 'Unit size in square meters',
  `kitchen` varchar(10) DEFAULT 'Yes' COMMENT 'Kitchen available (Yes/No)',
  `kitchen_type` varchar(20) DEFAULT 'Private' COMMENT 'Kitchen type (Private/Shared)',
  `gender_specific` varchar(20) DEFAULT 'Mixed' COMMENT 'Gender restriction',
  `pets` varchar(20) DEFAULT 'Allowed' COMMENT 'Pet policy'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblistings`
--

INSERT INTO `tblistings` (`id`, `title`, `description`, `address`, `latitude`, `longitude`, `price`, `capacity`, `gcash_name`, `gcash_number`, `gcash_qr_path`, `owner_id`, `created_at`, `is_available`, `is_verified`, `verification_notes`, `verified_at`, `verified_by`, `amenities`, `is_archived`, `gov_id_path`, `property_photos`, `verification_status`, `rejection_reason`, `bedroom`, `unit_sqm`, `kitchen`, `kitchen_type`, `gender_specific`, `pets`) VALUES
(5, 'Apartment Type', '1 Bedroom, no pets allowed', 'San Jose, Pasig', 14.56130330, 121.07364520, 12000.00, 4, NULL, NULL, NULL, 18, '2025-05-11 09:55:00', 1, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(9, 'Studio Type', '6 units available, Split payment allowed for multiple renters,18~sqm space, Pets allowed (Limited 1)', '2379 Oro-B, San Andres Bukid, Manila, ', 14.57333530, 121.00915790, 8000.00, 4, NULL, NULL, NULL, 20, '2025-05-11 10:09:53', 1, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(12, 'Apartment Type', '3 available units, 1 Bedroom, pets allowed (Limited to 1), 20 sqm space', '490 Dr. Sixto Antonio Ave., Pasig', 14.58124710, 121.08343830, 7000.00, 3, NULL, NULL, NULL, 21, '2025-05-17 00:10:51', 1, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(17, 'Apartment', '2 units available, 30~ sqm space, no pets allowed.', '325 J. P. Rizal St, Makati City, 1204 Metro Manila, Philippines', 14.57171800, 121.01519900, 13000.00, 3, NULL, NULL, NULL, 19, '2025-09-20 13:29:20', 1, 1, 'ok', '2025-09-20 13:30:46', 43, 'wifi, parking, furnished, electricity, water', 0, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(18, 'Apartment', 'Wide open space with clear view of the city', '324 Barangka Dr., Mandaluyong City, 1550 Kalakhang Maynila, Philippines', 14.57071830, 121.03720290, 12500.00, 3, NULL, NULL, NULL, 19, '2025-09-20 13:41:30', 1, 1, 'clear', '2025-09-20 13:43:12', 43, 'wifi, parking, furnished', 1, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(19, 'Condominium', 'Test', 'Rivergreen Residences, 2217 Pedro Gil St, Santa Ana, Manila, Metro Manila, Philippines', 14.58146360, 121.00977270, 8000.00, 3, NULL, NULL, NULL, 35, '2025-09-26 09:21:59', 1, 1, '', '2025-09-26 09:23:06', 43, 'wifi, parking, aircon, kitchen, laundry, furnished, balcony, pool', 0, NULL, NULL, NULL, NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(21, 'Condominium', '', '3523 V. Mapa Ext, Santa Mesa, Manila, 1016 Metro Manila, Philippines', 14.59796440, 121.02240370, 10000.00, 2, NULL, NULL, NULL, 20, '2025-10-03 08:25:26', 1, 1, NULL, NULL, NULL, 'wifi, kitchen, elevator', 0, 'uploads/gov_ids/20251003/gov_20_1759479926_9fccc464.png', '[\"uploads\\/property_photos\\/20251003\\/photo_20_1759479926_0_6df1afe7.jpg\",\"uploads\\/property_photos\\/20251003\\/photo_20_1759479926_1_e4b45032.jpg\",\"uploads\\/property_photos\\/20251003\\/photo_20_1759479926_2_e8fc4700.jpg\"]', 'approved', NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(22, 'Apartment', '.', '2336 Pasig Line, Santa Ana, Manila, 1009 Metro Manila, Philippines', 14.57707900, 121.00811850, 8499.00, 2, NULL, NULL, NULL, 35, '2025-10-03 10:15:17', 1, 0, NULL, NULL, NULL, 'wifi, kitchen', 1, 'uploads/gov_ids/20251003/gov_35_1759486517_af5e4f66.jpg', '[\"uploads\\/property_photos\\/20251003\\/photo_35_1759486517_0_f34670f1.jpg\"]', 'pending', NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed'),
(23, 'Apartment', '', '32nd St, Makati City, Metro Manila, Philippines', 14.55712490, 121.04197430, 11800.00, 5, NULL, NULL, NULL, 19, '2025-10-04 12:39:18', 1, 0, NULL, NULL, NULL, 'balcony, bathroom, electricity, water', 0, 'uploads/gov_ids/20251004/gov_19_1759581558_fb3ec269.jpg', '[\"uploads\\/property_photos\\/20251004\\/photo_19_1759581558_0_40b9c9e1.jpg\"]', 'pending', NULL, 1, 20.00, 'Yes', 'Private', 'Mixed', 'Allowed');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_auto_replies`
--
ALTER TABLE `chat_auto_replies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_id` (`thread_id`,`created_at`);

--
-- Indexes for table `chat_participants`
--
ALTER TABLE `chat_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_thread` (`thread_id`,`user_id`);

--
-- Indexes for table `chat_quick_replies`
--
ALTER TABLE `chat_quick_replies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_threads_listing` (`listing_id`);

--
-- Indexes for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `listing_id` (`listing_id`),
  ADD KEY `idx_rental_status` (`status`,`tenant_id`,`listing_id`);

--
-- Indexes for table `tbadmin`
--
ALTER TABLE `tbadmin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_owner_payment` (`id`,`gcash_qr_path`,`paymaya_qr_path`);

--
-- Indexes for table `tblistings`
--
ALTER TABLE `tblistings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `idx_owner_archived` (`owner_id`,`is_archived`),
  ADD KEY `idx_tblistings_verif` (`is_verified`),
  ADD KEY `idx_verification_status` (`verification_status`),
  ADD KEY `idx_is_verified_archived` (`is_verified`,`is_archived`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_is_archived` (`is_archived`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_capacity` (`capacity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_auto_replies`
--
ALTER TABLE `chat_auto_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `chat_participants`
--
ALTER TABLE `chat_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `chat_quick_replies`
--
ALTER TABLE `chat_quick_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat_threads`
--
ALTER TABLE `chat_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `rental_requests`
--
ALTER TABLE `rental_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tbadmin`
--
ALTER TABLE `tbadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `tblistings`
--
ALTER TABLE `tblistings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_participants`
--
ALTER TABLE `chat_participants`
  ADD CONSTRAINT `chat_participants_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD CONSTRAINT `fk_chat_threads_listing` FOREIGN KEY (`listing_id`) REFERENCES `tblistings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rental_requests`
--
ALTER TABLE `rental_requests`
  ADD CONSTRAINT `rental_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tbadmin` (`id`),
  ADD CONSTRAINT `rental_requests_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `tblistings` (`id`);

--
-- Constraints for table `tblistings`
--
ALTER TABLE `tblistings`
  ADD CONSTRAINT `tblistings_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `tbadmin` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
