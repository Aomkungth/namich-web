<?php
/**
 * Configuration File
 * ระบบเช่าโฮสติ้งและ VPS เชื่อมต่อ Reseller API (DirectAdmin Ready)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// โหมดการทำงาน (true = แสดง error สำหรับพัฒนา, false = ซ่อน error สำหรับใช้งานจริง)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- การตั้งค่าฐานข้อมูล (Database Configuration) ---
// สำหรับ DirectAdmin: เปลี่ยนค่าตาม Database ที่สร้างในหน้า MySQL Management
define('DB_HOST', 'localhost');
define('DB_NAME', 'reseller_hosting_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- การตั้งค่า Reseller API ---
// ระบุ API Key ที่ได้จากหน้า https://nami-ch.com/api-keys.php
// *** เปลี่ยน rk_your_key_here เป็น API Key จริงของคุณ ***
define('DEFAULT_RESELLER_API_KEY', 'rk_your_key_here');

// URL สำหรับเชื่อมต่อ Reseller API
// - ถ้าเว็บรัน server คนละเครื่องกับ nami-ch.com → ใช้ https://nami-ch.com/reseller-api/
// - ถ้าเว็บรัน server เดียวกับ nami-ch.com (loopback) → ใช้ http://127.0.0.1/reseller-api/
//   เพื่อข้าม Firewall/mod_security ที่บล็อก self-request
define('RESELLER_API_BASE_URL', 'https://nami-ch.com/reseller-api/');

// --- การตั้งค่าเว็บไซต์ (Site Settings) ---
define('SITE_NAME', 'HostPro Cloud');
define('SITE_SLOGAN', 'บริการเว็บโฮสติ้งและ VPS คุณภาพสูง รวดเร็ว เสถียร 24 ชม.');
define('SITE_CURRENCY', '฿');
define('CURRENCY_CODE', 'THB');

// การตั้งค่าการอัปโหลดไฟล์สลิป
define('UPLOAD_DIR', __DIR__ . '/uploads/slips/');
define('UPLOAD_URL', 'uploads/slips/');

// สร้างโฟลเดอร์สำหรับเก็บสลิปอัตโนมัติหากยังไม่มี
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
