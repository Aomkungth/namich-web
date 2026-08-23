<?php
/**
 * Database Connection & Auto Initialization
 * เชื่อมต่อฐานข้อมูล PDO และระบบสร้างตารางอัตโนมัติ
 */

require_once __DIR__ . '/config.php';

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // ตรวจสอบและสร้างตารางอัตโนมัติหากยังไม่มี
            initDatabaseTables($pdo);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('<div style="font-family:sans-serif;padding:20px;background:#fee;color:#c00;border:1px solid #f99;margin:20px;border-radius:8px;">' .
                    '<h3>Database Connection Error</h3>' .
                    '<p>' . htmlspecialchars($e->getMessage()) . '</p>' .
                    '<p>กรุณาตรวจสอบการตั้งค่าฐานข้อมูลในไฟล์ <code>config.php</code></p>' .
                    '</div>');
            } else {
                die('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ');
            }
        }
    }
    return $pdo;
}

/**
 * สร้างตารางอัตโนมัติหากยังไม่มีในฐานข้อมูล
 */
function initDatabaseTables(PDO $pdo) {
    static $initialized = false;
    if ($initialized) return;

    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `services` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `orders` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `topups` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `transactions` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `package_settings` (
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
            `custom_features` TEXT DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_item` (`item_type`, `item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `category_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_id` INT NOT NULL UNIQUE,
            `category_slug` VARCHAR(100) NOT NULL,
            `custom_name` VARCHAR(100) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `api_cache` (
            `cache_key` VARCHAR(50) PRIMARY KEY,
            `cache_data` LONGTEXT NOT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `user_api_keys` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `api_rate_limits` (
            `api_key_id` INT NOT NULL,
            `window_start` DATETIME NOT NULL,
            `request_count` INT NOT NULL DEFAULT 1,
            PRIMARY KEY (`api_key_id`, `window_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `news` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `excerpt` TEXT DEFAULT NULL,
            `content` LONGTEXT DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `views` INT DEFAULT 0,
            `is_published` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($queries as $q) {
        $pdo->exec($q);
    }

    // Migration: เพิ่ม scopes column ให้ user_api_keys หากยังไม่มี (รองรับ DB เก่า)
    try {
        $pdo->exec("ALTER TABLE `user_api_keys` ADD COLUMN `scopes` VARCHAR(255) NOT NULL DEFAULT 'read' AFTER `label`");
    } catch (PDOException $e) {
        // Column มีอยู่แล้ว — ไม่ต้องทำอะไร
    }

    // Migration: เพิ่ม ip_whitelist column
    try {
        $pdo->exec("ALTER TABLE `user_api_keys` ADD COLUMN `ip_whitelist` TEXT DEFAULT NULL AFTER `scopes`");
    } catch (PDOException $e) {
        // Column มีอยู่แล้ว
    }

    // Migration: เพิ่ม custom_features column
    try {
        $pdo->exec("ALTER TABLE `package_settings` ADD COLUMN `custom_features` TEXT DEFAULT NULL AFTER `badge_text`");
    } catch (PDOException $e) {
        // Column มีอยู่แล้ว
    }

    // ตรวจสอบว่ามีข้อมูล default settings หรือยัง
    $stmt = $pdo->query("SELECT COUNT(*) FROM `settings`");
    if ($stmt->fetchColumn() == 0) {
        $defaults = [
            'reseller_api_key' => DEFAULT_RESELLER_API_KEY,
            'reseller_api_url' => RESELLER_API_BASE_URL,
            'site_name' => SITE_NAME,
            'site_slogan' => SITE_SLOGAN,
            'markup_percent' => '0',
            'markup_fixed' => '0',
            'promptpay_number' => '0812345678',
            'promptpay_name' => 'นายพร้อมเพย์ ตัวอย่าง',
            'truemoney_phone' => '0801234567',
            'bank_name' => 'กสิกรไทย (KBANK)',
            'bank_account_no' => '123-4-56789-0',
            'bank_account_name' => 'บจก. โฮสต์โปร คลาวด์',
            'contact_line' => '@hostpro',
            'contact_email' => 'support@example.com'
        ];
        $insertStmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
        foreach ($defaults as $k => $v) {
            $insertStmt->execute([$k, $v]);
        }
    }

    // ตรวจสอบว่ามี admin หรือยัง ถ้ายังไม่มีให้สร้าง admin/password123
    $stmtUser = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'admin'");
    if ($stmtUser->fetchColumn() == 0) {
        $adminPass = password_hash('password123', PASSWORD_DEFAULT);
        $adminStmt = $pdo->prepare("INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `phone`, `credit`, `role`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $adminStmt->execute(['admin', 'admin@example.com', $adminPass, 'ผู้ดูแลระบบ', '0812345678', 5000.00, 'admin', 'active']);
    }

    $initialized = true;
}

/**
 * ดึงค่าตั้งค่าจากตาราง settings
 */
function getSetting($key, $default = null) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM `settings` WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * บันทึกค่าตั้งค่าในตาราง settings
 */
function setSetting($key, $value) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
    return $stmt->execute([$key, $value]);
}
