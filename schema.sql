-- -------------------------------------------------------------
-- SQL Schema for Hosting & VPS Reseller System
-- สามารถนำเข้า (Import) ใน phpMyAdmin บน DirectAdmin ได้ทันที
-- -------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- ตารางผู้ใช้งาน (Users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `credit` DECIMAL(12, 2) DEFAULT 0.00,
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `status` ENUM('active', 'banned') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางบริการของลูกค้า (Services: Hosting & VPS)
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `service_type` ENUM('hosting', 'vps') NOT NULL,
  `api_service_id` INT DEFAULT NULL,
  `api_order_id` INT DEFAULT NULL,
  `api_invoice_id` INT DEFAULT NULL,
  `domain_or_hostname` VARCHAR(255) NOT NULL,
  `package_name` VARCHAR(100) DEFAULT NULL,
  `package_id` INT DEFAULT NULL,
  `os_name` VARCHAR(100) DEFAULT NULL,
  `ip_address` VARCHAR(100) DEFAULT NULL,
  `server_username` VARCHAR(50) DEFAULT NULL,
  `billing_cycle` ENUM('monthly', 'yearly') DEFAULT 'monthly',
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) DEFAULT 'active',
  `start_date` DATE DEFAULT NULL,
  `next_due_date` DATE DEFAULT NULL,
  `nameservers` TEXT DEFAULT NULL,
  `server_name` VARCHAR(100) DEFAULT NULL,
  `extra_info` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_api_service_id` (`api_service_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางคำสั่งซื้อ (Orders)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `order_no` VARCHAR(50) NOT NULL UNIQUE,
  `service_type` ENUM('hosting', 'vps') NOT NULL,
  `service_id` INT DEFAULT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(100) NOT NULL,
  `domain_or_hostname` VARCHAR(255) NOT NULL,
  `billing_cycle` ENUM('monthly', 'yearly') DEFAULT 'monthly',
  `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('paid', 'pending', 'cancelled', 'failed') DEFAULT 'paid',
  `api_response` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางรายการแจ้งเติมเงิน (Topups)
CREATE TABLE IF NOT EXISTS `topups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `topup_no` VARCHAR(50) NOT NULL UNIQUE,
  `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) DEFAULT 'promptpay',
  `slip_image` VARCHAR(255) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางประวัติธุรกรรมการเงิน (Transactions)
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('topup', 'order_hosting', 'order_vps', 'renew_hosting', 'renew_vps', 'admin_adjust', 'refund') NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `balance_before` DECIMAL(12, 2) NOT NULL,
  `balance_after` DECIMAL(12, 2) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `ref_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางตั้งค่าระบบ (Settings)
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางปรับแต่งราคาและสถานะแพ็กเกจ (Package Settings & Custom Pricing)
CREATE TABLE IF NOT EXISTS `package_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_type` ENUM('hosting', 'vps') NOT NULL,
  `item_id` INT NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `pricing_mode` ENUM('global', 'custom_markup', 'custom_price') DEFAULT 'global',
  `custom_markup_percent` DECIMAL(6, 2) DEFAULT 0.00,
  `custom_markup_fixed` DECIMAL(10, 2) DEFAULT 0.00,
  `custom_price_monthly` DECIMAL(10, 2) DEFAULT NULL,
  `custom_price_yearly` DECIMAL(10, 2) DEFAULT NULL,
  `custom_name` VARCHAR(100) DEFAULT NULL,
  `badge_text` VARCHAR(50) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_item` (`item_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางปรับแต่งหมวดหมู่ (Category Settings)
CREATE TABLE IF NOT EXISTS `category_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL UNIQUE,
  `category_slug` VARCHAR(100) NOT NULL,
  `custom_name` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง API Keys ของผู้ใช้ (User API Keys)
CREATE TABLE IF NOT EXISTS `user_api_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `api_key` VARCHAR(64) NOT NULL UNIQUE,
  `label` VARCHAR(100) DEFAULT NULL,
  `scopes` VARCHAR(255) DEFAULT 'read',
  `ip_whitelist` TEXT DEFAULT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง Rate Limiting สำหรับ API (API Rate Limits)
CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `api_key_id` INT NOT NULL,
  `window_start` DATETIME NOT NULL,
  `request_count` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`api_key_id`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตารางเก็บแคชข้อมูลจาก API (API Data Cache)
CREATE TABLE IF NOT EXISTS `api_cache` (
  `cache_key` VARCHAR(50) PRIMARY KEY,
  `cache_data` LONGTEXT NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มข้อมูลตั้งค่าเริ่มต้น (Default Settings)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('reseller_api_key', 'rk_your_key_here'),
('reseller_api_url', 'https://nami-ch.com/reseller-api/'),
('site_name', 'HostPro Cloud'),
('site_slogan', 'บริการเว็บโฮสติ้งและ VPS ความเร็วสูง เสถียร ปลอดภัย 24 ชม.'),
('markup_percent', '0'),
('markup_fixed', '0'),
('promptpay_number', '0812345678'),
('promptpay_name', 'นายพร้อมเพย์ ตัวอย่าง'),
('truemoney_phone', '0801234567'),
('bank_name', 'กสิกรไทย (KBANK)'),
('bank_account_no', '123-4-56789-0'),
('bank_account_name', 'บจก. โฮสต์โปร คลาวด์'),
('contact_line', '@hostpro'),
('contact_email', 'support@example.com')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

-- สร้างบัญชีแอดมินเริ่มต้น (Username: admin / Password: password123)
-- hash ของ password123
INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `phone`, `credit`, `role`, `status`) VALUES
('admin', 'admin@example.com', '$2y$10$wTfV5Yk3qR5y7W5TfQ8i.ODgC5f0W1uJ0X2e1f2.d0N8k9l0m1n2o', 'ผู้ดูแลระบบ', '0812345678', 10000.00, 'admin', 'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
