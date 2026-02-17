-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 08:14 AM
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
-- Database: `nayl`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_deleted_users` ()   BEGIN
    -- حذف المستخدمين المحذوفين منذ أكثر من 90 يوم
    DELETE FROM users 
    WHERE deleted_at IS NOT NULL 
      AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_expired_sessions` ()   BEGIN
    DELETE FROM user_sessions 
    WHERE expires_at < NOW();
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_expired_tokens` ()   BEGIN
    DELETE FROM verification_tokens 
    WHERE expires_at < NOW() 
       OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 7 DAY));
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users`
-- (See below for the actual view)
--
CREATE TABLE `active_users` (
`id` int(10) unsigned
,`full_name` varchar(255)
,`email` varchar(255)
,`full_phone` varchar(25)
,`country` varchar(100)
,`city` varchar(100)
,`is_verified` tinyint(1)
,`whatsapp_verified` tinyint(1)
,`created_at` timestamp
,`last_login_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, '1', 'م/ عمرو نايل', 'rnayl23@gmail.com', '$2y$12$ZhUXzW52cYvzStV4B3o0rOsrK70vR3h6Ovx9fy6AcoXzjgyAh4Pm6', 'super_admin', 1, '2026-02-06 15:51:06', '2026-01-24 05:22:27', '2026-02-06 13:51:06');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'نوع الإشعار (recharge_request, new_user, etc)',
  `message` text NOT NULL COMMENT 'نص الإشعار',
  `reference_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف السجل المرتبط (user_id, request_id)',
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'هل تمت القراءة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='إشعارات لوحة التحكم';

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'آخر نشاط',
  `last_rotated_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'آخر تجديد للتوكن',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_sessions`
--

INSERT INTO `admin_sessions` (`id`, `admin_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `last_activity_at`, `last_rotated_at`, `created_at`) VALUES
(11, 1, 'd784a25e46b335231ea87c854635461796b503c89da4a1aa3fedfc6435c605ae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 23:38:48', '2026-02-04 02:32:23', '2026-02-04 01:38:48', '2026-02-03 23:38:48'),
(12, 1, '3883aae90ec150eb8ce13656c4f3b8b5b5f716a53e263590e939dabaac70a63a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 00:36:01', '2026-02-04 02:46:25', '2026-02-04 02:36:01', '2026-02-04 00:36:01'),
(15, 1, '115a94683376bfa6afe14d45a5920f4842c6074da652fbd8bb09bd7e9af5d89d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 20:46:45', '2026-02-05 23:27:20', '2026-02-05 22:46:45', '2026-02-05 20:46:45'),
(16, 1, '567c5c8feff7ff803213eecade0feebffe4d52f6678ff30cc7adf3cdbc338418', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 22:02:59', '2026-02-06 00:28:30', '2026-02-06 00:02:59', '2026-02-05 22:02:59'),
(17, 1, '9cf3bc76dfd33f954d3c3b60993aac11d5ee46f3eee45130e2b33325c7def434', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 12:56:49', '2026-02-06 15:28:34', '2026-02-06 14:56:49', '2026-02-06 12:56:49'),
(18, 1, 'ab764a43eee7e881384d510059522984074cc694bbf307c4c007b754c5ac2ed4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 13:51:06', '2026-02-06 16:11:20', '2026-02-06 15:51:06', '2026-02-06 13:51:06');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `subject` varchar(255) NOT NULL COMMENT 'عنوان الشكوى',
  `message` text NOT NULL COMMENT 'نص الشكوى',
  `status` enum('open','replied','closed') DEFAULT 'open' COMMENT 'حالة الشكوى',
  `admin_reply` text DEFAULT NULL COMMENT 'رد الإدارة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الشكاوي والمقترحات';

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'عنوان الكورس',
  `description` text DEFAULT NULL COMMENT 'وصف الكورس',
  `cover_image_url` varchar(500) DEFAULT NULL COMMENT 'صورة واجهة الكورس (البطاقة)',
  `price` decimal(10,2) NOT NULL DEFAULT 500.00 COMMENT 'سعر الكورس بالجنية',
  `status` enum('draft','published','archived') DEFAULT 'draft' COMMENT 'الحالة',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المشرف الذي أنشأ الكورس',
  `bunny_library_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف مكتبة Bunny CDN الخاصة بهذا الكورس',
  `bunny_library_api_key` varchar(255) DEFAULT NULL COMMENT 'مفتاح API لمكتبة Bunny CDN الخاصة بهذا الكورس',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الكورسات';

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `cover_image_url`, `price`, `status`, `created_by`, `bunny_library_id`, `bunny_library_api_key`, `created_at`, `updated_at`) VALUES
(17, 'الكورس التاني', 'احسن كورس صيانه', '/uploads/covers/course_17_698503f33affe.jpg', 5000.00, 'published', 1, 593559, 'f8f3cf9d-6182-4918-b4f66fc1f361-a4d9-4bbd', '2026-02-05 20:56:18', '2026-02-05 20:56:19');

-- --------------------------------------------------------

--
-- Table structure for table `course_codes`
--

CREATE TABLE `course_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف الكورس',
  `code` varchar(50) NOT NULL COMMENT 'كود التفعيل',
  `used_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المستخدم الذي استخدم الكود',
  `used_at` datetime DEFAULT NULL COMMENT 'تاريخ الاستخدام',
  `expires_at` datetime DEFAULT NULL COMMENT 'تاريخ انتهاء الصلاحية',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط',
  `bound_device_hash` varchar(64) DEFAULT NULL COMMENT 'بصمة الجهاز الوحيد المسموح لمشاهدة الكورس بهذا الكود',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المشرف الذي أنشأ الكود',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أكواد تفعيل الكورسات';

--
-- Dumping data for table `course_codes`
--

INSERT INTO `course_codes` (`id`, `course_id`, `code`, `used_by`, `used_at`, `expires_at`, `is_active`, `bound_device_hash`, `created_by`, `created_at`) VALUES
(1, 17, 'M5RJY2SXDC', 2, '2026-02-13 18:08:21', NULL, 1, '5e474c1159ffe0c449d7a6eb2b1b24522176470719588c335add578a766d44de', NULL, '2026-02-13 16:08:21');

-- --------------------------------------------------------

--
-- Table structure for table `course_videos`
--

CREATE TABLE `course_videos` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف الكورس',
  `title` varchar(255) NOT NULL COMMENT 'عنوان الفيديو',
  `description` text DEFAULT NULL COMMENT 'وصف الفيديو',
  `video_order` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ترتيب الفيديو في الكورس',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT 'رابط صورة الواجهة',
  `video_url` varchar(500) DEFAULT NULL COMMENT 'رابط الفيديو من Bunny CDN',
  `bunny_video_id` varchar(100) DEFAULT NULL COMMENT 'معرف الفيديو في Bunny CDN',
  `duration` int(10) UNSIGNED DEFAULT NULL COMMENT 'مدة الفيديو بالثواني',
  `file_size` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'حجم الملف بالبايت',
  `status` enum('uploading','processing','ready','failed') DEFAULT 'uploading' COMMENT 'حالة الفيديو',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فيديوهات الكورسات';

--
-- Dumping data for table `course_videos`
--

INSERT INTO `course_videos` (`id`, `course_id`, `title`, `description`, `video_order`, `thumbnail_url`, `video_url`, `bunny_video_id`, `duration`, `file_size`, `status`, `created_at`, `updated_at`) VALUES
(5, 17, '100', '123456', 1, '/uploads/thumbnails/thumb_17_1_698503f55b008.jpg', 'https://vz-593559.b-cdn.net/2af7467e-12d1-4a01-b901-2e47dc5e43e4', '2af7467e-12d1-4a01-b901-2e47dc5e43e4', 0, 0, 'ready', '2026-02-05 20:56:21', '2026-02-05 20:56:21'),
(6, 17, '124', 'الفيديو التاني', 2, '/uploads/thumbnails/thumb_17_2_698503f692b9a.jpg', 'https://vz-593559.b-cdn.net/da468468-07eb-4c77-bc13-69fe2f3f6862', 'da468468-07eb-4c77-bc13-69fe2f3f6862', 0, 0, 'ready', '2026-02-05 20:56:22', '2026-02-05 20:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `device_fingerprints`
--

CREATE TABLE `device_fingerprints` (
  `id` int(10) UNSIGNED NOT NULL,
  `fingerprint_hash` varchar(64) NOT NULL COMMENT 'هاش SHA-256 للبيانات الثابتة للجهاز',
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'أول ظهور',
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'آخر ظهور'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بصمات الأجهزة - تُسجّل مرة واحدة';

--
-- Dumping data for table `device_fingerprints`
--

INSERT INTO `device_fingerprints` (`id`, `fingerprint_hash`, `first_seen_at`, `last_seen_at`) VALUES
(1, '5e474c1159ffe0c449d7a6eb2b1b24522176470719588c335add578a766d44de', '2026-02-13 16:38:05', '2026-02-13 16:44:32');

-- --------------------------------------------------------

--
-- Table structure for table `discount_codes`
--

CREATE TABLE `discount_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'كود الخصم - فريد',
  `discount_amount` decimal(10,2) NOT NULL COMMENT 'مبلغ الخصم بالجنية',
  `course_id` int(10) UNSIGNED NOT NULL COMMENT 'الكورس الذي يُطبَّق عليه الخصم',
  `used_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المستخدم الذي استخدم الكود (NULL = لم يُستخدم)',
  `used_at` datetime DEFAULT NULL COMMENT 'تاريخ الاستخدام',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المشرف الذي أنشأ الكود',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أكواد الخصم - استخدام واحد لكل كود';

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) DEFAULT NULL COMMENT 'البريد',
  `full_phone` varchar(25) DEFAULT NULL COMMENT 'الهاتف',
  `device_uuid` varchar(36) NOT NULL COMMENT 'معرف الجهاز (UUID)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP - للتسجيل فقط',
  `attempt_count` int(10) UNSIGNED DEFAULT 1 COMMENT 'العدد',
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'آخر محاولة',
  `locked_until` datetime DEFAULT NULL COMMENT 'قفل حتى'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='محاولات الدخول';

-- --------------------------------------------------------

--
-- Table structure for table `recharge_requests`
--

CREATE TABLE `recharge_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `amount` decimal(10,2) NOT NULL COMMENT 'المبلغ المطلوب',
  `payment_method` varchar(50) NOT NULL COMMENT 'طريقة الدفع (instapay, vodafone_cash, etc.)',
  `account_number` varchar(255) DEFAULT NULL COMMENT 'رقم الحساب / اسم المستخدم',
  `transaction_image` varchar(500) DEFAULT NULL COMMENT 'رابط صورة إيصال التحويل',
  `transaction_message` text DEFAULT NULL COMMENT 'رسالة التحويل / رقم العملية',
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending' COMMENT 'الحالة',
  `admin_notes` text DEFAULT NULL COMMENT 'ملاحظات الإدارة',
  `processed_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف المشرف الذي قام بالمعالجة',
  `processed_at` datetime DEFAULT NULL COMMENT 'تاريخ المعالجة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='طلبات تعبئة الرصيد';

--
-- Dumping data for table `recharge_requests`
--

INSERT INTO `recharge_requests` (`id`, `user_id`, `amount`, `payment_method`, `account_number`, `transaction_image`, `transaction_message`, `status`, `admin_notes`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 5000.00, 'vodafone_cash', '01102289090', 'uploads/recharge_receipts/1_20260124_2_01102289090.jpg', '1213', 'pending', NULL, NULL, NULL, '2026-01-24 02:51:37', '2026-01-24 02:51:37'),
(2, 2, 1000.00, 'instapay', 'osama', 'uploads/recharge_receipts/2_20260124_2_.jpg', '121212', 'approved', NULL, 1, '2026-01-26 18:33:46', '2026-01-24 02:55:36', '2026-01-26 16:33:46'),
(3, 2, 111.00, 'vodafone_cash', '11212214545', 'uploads/recharge_receipts/3_20260124_2_11212214545.jpg', '111', 'rejected', 'كده', 1, '2026-01-27 01:31:08', '2026-01-24 03:00:33', '2026-01-26 23:31:08'),
(4, 2, 1000.00, 'instapay', 'jhbbbb', 'uploads/recharge_receipts/4_20260124_2_jhbbbb.jpg', '', 'rejected', NULL, 1, '2026-01-24 14:27:36', '2026-01-24 03:14:55', '2026-01-24 12:27:36'),
(5, 2, 1000.00, 'instapay', '12113', 'uploads/recharge_receipts/5_20260124_2_12113.jpg', '', 'approved', NULL, 1, '2026-01-24 14:27:32', '2026-01-24 03:17:54', '2026-01-24 12:27:32'),
(6, 2, 586.00, 'instapay', '444335', 'uploads/recharge_receipts/6_20260124_2_444335.jpg', '', 'approved', NULL, 1, '2026-01-24 14:26:28', '2026-01-24 03:20:16', '2026-01-24 12:26:28'),
(7, 2, 200000.00, 'vodafone_cash', '01062000001', 'uploads/recharge_receipts/7_20260205_2_01062000001.jpg', '', 'approved', NULL, 1, '2026-02-05 22:57:40', '2026-02-05 20:57:23', '2026-02-05 20:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `site_visits`
--

CREATE TABLE `site_visits` (
  `id` int(10) UNSIGNED NOT NULL,
  `v_id` varchar(20) NOT NULL COMMENT 'معرف الزائر (الجهاز)',
  `visit_count` int(10) UNSIGNED DEFAULT 1 COMMENT 'عدد مرات الزيارة',
  `last_visit_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'آخر زيارة',
  `user_agent` text DEFAULT NULL COMMENT 'معلومات المتصفح',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='زيارات الموقع وأجهزة الزوار';

--
-- Dumping data for table `site_visits`
--

INSERT INTO `site_visits` (`id`, `v_id`, `visit_count`, `last_visit_at`, `user_agent`, `created_at`) VALUES
(1, '9157b8b6-6685-491f-9', 11, '2026-02-13 16:37:58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 15:22:52'),
(2, '19a1a40a-190c-40fc-9', 2, '2026-02-14 22:28:57', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-13 16:01:08'),
(13, '5d88a22a-6a69-46e0-8', 11, '2026-02-13 16:45:04', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 16:38:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL COMMENT 'البريد الإلكتروني',
  `phone` varchar(20) NOT NULL COMMENT 'رقم الهاتف',
  `country_code` varchar(5) NOT NULL DEFAULT '+20' COMMENT 'كود الدولة',
  `full_phone` varchar(25) NOT NULL COMMENT 'الرقم بالكامل',
  `country` varchar(100) NOT NULL COMMENT 'الدولة',
  `city` varchar(100) NOT NULL COMMENT 'المدينة',
  `password_hash` varchar(255) NOT NULL COMMENT 'كلمة المرور',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط',
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'تم التحقق',
  `whatsapp_verified` tinyint(1) DEFAULT 0 COMMENT 'واتساب مفعل',
  `account_type` enum('free','vip') NOT NULL DEFAULT 'free' COMMENT 'نوع الحساب: مجاني أو VIP',
  `terms_accepted` tinyint(1) DEFAULT 0 COMMENT 'موافق على الشروط',
  `terms_accepted_at` datetime DEFAULT NULL COMMENT 'تاريخ الموافقة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL COMMENT 'آخر دخول',
  `deleted_at` datetime DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL COMMENT 'الاسم الكامل'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المستخدمين';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `phone`, `country_code`, `full_phone`, `country`, `city`, `password_hash`, `is_active`, `is_verified`, `whatsapp_verified`, `account_type`, `terms_accepted`, `terms_accepted_at`, `created_at`, `updated_at`, `last_login_at`, `deleted_at`, `full_name`) VALUES
(2, 'osamasaied9090@gmail.com', '01102289090', '+20', '+2001102289090', 'مصر', 'الإسكندرية', '$2y$12$J8CwdXFwv.CWMFRKVsOH.OSHo0v.2Bsq6YoEyhGJpVm3WtH4UWJyW', 1, 0, 0, 'vip', 1, '2026-01-23 14:00:52', '2026-01-23 12:00:52', '2026-02-13 16:44:24', '2026-02-13 18:44:24', NULL, 'اسامه السعيد');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `create_wallet_on_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO wallet (user_id, balance) 
    VALUES (NEW.id, 0.00)
    ON DUPLICATE KEY UPDATE balance = balance;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `set_full_phone_on_insert` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.full_phone IS NULL OR NEW.full_phone = '' THEN
        SET NEW.full_phone = CONCAT(NEW.country_code, NEW.phone);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_full_phone` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.phone != OLD.phone OR NEW.country_code != OLD.country_code THEN
        SET NEW.full_phone = CONCAT(NEW.country_code, NEW.phone);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_course_subscriptions`
--

CREATE TABLE `user_course_subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `course_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف الكورس',
  `code_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'معرف الكود المستخدم (إن وجد)',
  `status` enum('active','completed','cancelled') DEFAULT 'active' COMMENT 'حالة الاشتراك',
  `progress_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'نسبة الإنجاز',
  `last_watched_video_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'آخر فيديو تم مشاهدته',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اشتراكات المستخدمين في الكورسات';

--
-- Dumping data for table `user_course_subscriptions`
--

INSERT INTO `user_course_subscriptions` (`id`, `user_id`, `course_id`, `code_id`, `status`, `progress_percentage`, `last_watched_video_id`, `started_at`, `completed_at`) VALUES
(1, 2, 17, 1, 'active', 0.00, NULL, '2026-02-13 16:08:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL COMMENT 'التوكن',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP',
  `user_agent` text DEFAULT NULL COMMENT 'المتصفح',
  `expires_at` datetime NOT NULL COMMENT 'الانتهاء',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الجلسات';

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
(1, 2, 'a3ae6cdb98d8528b26ad36b601e7c806df84010f6b7f01fbc6030d5f4b18245c', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-14 15:22:56', '2026-02-13 15:22:56'),
(2, 2, '2dcd4de993f34fe7595d3792c0e6ebf078f1cb00adcaac0fade8ccc74fd3028d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-14 16:01:20', '2026-02-13 16:01:20');

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'الرمز',
  `token_type` enum('email_verification','password_reset','phone_verification') NOT NULL COMMENT 'النوع',
  `expires_at` datetime NOT NULL COMMENT 'الانتهاء',
  `used_at` datetime DEFAULT NULL COMMENT 'تاريخ الاستخدام',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='رموز التحقق';

-- --------------------------------------------------------

--
-- Table structure for table `wallet`
--

CREATE TABLE `wallet` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='محافظ المستخدمين';

--
-- Dumping data for table `wallet`
--

INSERT INTO `wallet` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 2, 162500.00, '2026-01-24 01:36:43', '2026-02-13 16:08:21'),
(2, 324994, 0.00, '2026-01-26 16:00:23', '2026-01-26 16:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `type` enum('credit','debit') NOT NULL COMMENT 'النوع: credit (إضافة) أو debit (خصم)',
  `amount` decimal(10,2) NOT NULL COMMENT 'المبلغ',
  `title` varchar(255) NOT NULL COMMENT 'عنوان المعاملة',
  `description` text DEFAULT NULL COMMENT 'وصف إضافي',
  `reference_id` varchar(255) DEFAULT NULL COMMENT 'معرف مرجعي (مثل معرف الكورس عند الشراء)',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'نوع المرجع (course, recharge, refund, etc.)',
  `balance_before` decimal(10,2) NOT NULL COMMENT 'الرصيد قبل المعاملة',
  `balance_after` decimal(10,2) NOT NULL COMMENT 'الرصيد بعد المعاملة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='معاملات المحفظة';

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `type`, `amount`, `title`, `description`, `reference_id`, `reference_type`, `balance_before`, `balance_after`, `created_at`) VALUES
(1, 2, 'credit', 586.00, 'شحن رصيد المحفظة', 'تمت الموافقة على طلب شحن رقم 6', '6', 'recharge', 0.00, 586.00, '2026-01-24 12:26:28'),
(2, 2, 'credit', 1000.00, 'شحن رصيد المحفظة', 'تمت الموافقة على طلب شحن رقم 5', '5', 'recharge', 586.00, 1586.00, '2026-01-24 12:27:32'),
(3, 2, 'credit', 1000.00, 'شحن رصيد المحفظة', 'تمت الموافقة على طلب شحن رقم 2', '2', 'recharge', 1586.00, 2586.00, '2026-01-26 16:33:46'),
(4, 2, 'credit', 200000.00, 'شحن رصيد المحفظة', 'تمت الموافقة على طلب شحن رقم 7', '7', 'recharge', 2500.00, 202500.00, '2026-02-05 20:57:40'),
(5, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 202500.00, 197500.00, '2026-02-05 20:58:30'),
(6, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 197500.00, 192500.00, '2026-02-13 15:25:45'),
(7, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 192500.00, 187500.00, '2026-02-13 15:44:30'),
(8, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 187500.00, 182500.00, '2026-02-13 15:48:14'),
(9, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 182500.00, 177500.00, '2026-02-13 15:52:30'),
(10, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 177500.00, 172500.00, '2026-02-13 15:56:35'),
(11, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 172500.00, 167500.00, '2026-02-13 16:00:36'),
(12, 2, 'debit', 5000.00, 'شراء كورس: الكورس التاني', NULL, '17', 'course', 167500.00, 162500.00, '2026-02-13 16:08:21');

-- --------------------------------------------------------

--
-- Table structure for table `webauthn_credentials`
--

CREATE TABLE `webauthn_credentials` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
  `credential_id` varchar(512) NOT NULL COMMENT 'معرف البصمة (base64url)',
  `public_key_x` varchar(256) DEFAULT NULL COMMENT 'مفتاح عام X (P-256)',
  `public_key_y` varchar(256) DEFAULT NULL COMMENT 'مفتاح عام Y (P-256)',
  `sign_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عداد التوقيع',
  `device_name` varchar(255) DEFAULT NULL COMMENT 'اسم الجهاز للمستخدم',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بيانات WebAuthn لتسجيل الدخول بالبصمة';

--
-- Dumping data for table `webauthn_credentials`
--

INSERT INTO `webauthn_credentials` (`id`, `user_id`, `credential_id`, `public_key_x`, `public_key_y`, `sign_count`, `device_name`, `created_at`) VALUES
(1, 2, 'xByaW91rHRuuhxBQXiVvnQ', 'hHUjfCnCjyMjorJ-SehDuLmpAIyPuZyCi2LG7AxgCYA', 'Hxv2xLA0vCtzZ6cw4kO6wyn030U_2nGPIJ-ldoOuJuE', 0, NULL, '2026-02-13 12:58:50');

-- --------------------------------------------------------

--
-- Structure for view `active_users`
--
DROP TABLE IF EXISTS `active_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users`  AS SELECT `users`.`id` AS `id`, `users`.`full_name` AS `full_name`, `users`.`email` AS `email`, `users`.`full_phone` AS `full_phone`, `users`.`country` AS `country`, `users`.`city` AS `city`, `users`.`is_verified` AS `is_verified`, `users`.`whatsapp_verified` AS `whatsapp_verified`, `users`.`created_at` AS `created_at`, `users`.`last_login_at` AS `last_login_at` FROM `users` WHERE `users`.`is_active` = 1 AND `users`.`deleted_at` is null ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_bunny_library_id` (`bunny_library_id`);

--
-- Indexes for table `course_codes`
--
ALTER TABLE `course_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_used_by` (`used_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_bound_device` (`bound_device_hash`);

--
-- Indexes for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_video_order` (`video_order`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_bunny_video_id` (`bunny_video_id`);

--
-- Indexes for table `device_fingerprints`
--
ALTER TABLE `device_fingerprints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fingerprint_hash` (`fingerprint_hash`),
  ADD KEY `idx_fingerprint_hash` (`fingerprint_hash`),
  ADD KEY `idx_last_seen_at` (`last_seen_at`);

--
-- Indexes for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_used_by` (`used_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_full_phone` (`full_phone`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_locked_until` (`locked_until`),
  ADD KEY `idx_device_uuid` (`device_uuid`);

--
-- Indexes for table `recharge_requests`
--
ALTER TABLE `recharge_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_payment_method` (`payment_method`);

--
-- Indexes for table `site_visits`
--
ALTER TABLE `site_visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `v_id` (`v_id`),
  ADD KEY `idx_v_id` (`v_id`),
  ADD KEY `idx_last_visit_at` (`last_visit_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `full_phone` (`full_phone`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_full_phone` (`full_phone`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `user_course_subscriptions`
--
ALTER TABLE `user_course_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_course` (`user_id`,`course_id`),
  ADD KEY `code_id` (`code_id`),
  ADD KEY `last_watched_video_id` (`last_watched_video_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_token_type` (`token_type`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_balance` (`balance`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_credential_id` (`credential_id`(255)),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `course_codes`
--
ALTER TABLE `course_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `course_videos`
--
ALTER TABLE `course_videos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `device_fingerprints`
--
ALTER TABLE `device_fingerprints`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recharge_requests`
--
ALTER TABLE `recharge_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `site_visits`
--
ALTER TABLE `site_visits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324995;

--
-- AUTO_INCREMENT for table `user_course_subscriptions`
--
ALTER TABLE `user_course_subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet`
--
ALTER TABLE `wallet`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_codes`
--
ALTER TABLE `course_codes`
  ADD CONSTRAINT `course_codes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_codes_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `course_codes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD CONSTRAINT `course_videos_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD CONSTRAINT `discount_codes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discount_codes_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `discount_codes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `recharge_requests`
--
ALTER TABLE `recharge_requests`
  ADD CONSTRAINT `recharge_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_course_subscriptions`
--
ALTER TABLE `user_course_subscriptions`
  ADD CONSTRAINT `user_course_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_course_subscriptions_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_course_subscriptions_ibfk_3` FOREIGN KEY (`code_id`) REFERENCES `course_codes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_course_subscriptions_ibfk_4` FOREIGN KEY (`last_watched_video_id`) REFERENCES `course_videos` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD CONSTRAINT `verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet`
--
ALTER TABLE `wallet`
  ADD CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD CONSTRAINT `webauthn_credentials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
