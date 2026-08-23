<?php
/**
 * Utility Functions & Helpers
 * ฟังก์ชันช่วยเหลือระบบสมาชิก การเงิน ความปลอดภัย และการแสดงผล
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * ดึงข้อมูลผู้ใช้ที่กำลังล็อกอินอยู่
 */
function getLoggedInUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_credit'] = $user['credit'];
        $_SESSION['username'] = $user['username'];
    }
    return $user;
}

/**
 * บังคับให้ล็อกอินก่อนเข้าถึงหน้า
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('warning', 'กรุณาเข้าสู่ระบบก่อนทำรายการ');
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?return=' . $returnUrl);
        exit;
    }
}

/**
 * ตรวจสอบว่าเป็นแอดมินหรือไม่
 */
function isAdmin() {
    if (!isLoggedIn()) return false;
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * บังคับให้เฉพาะแอดมินเข้าถึงหน้า
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        header('Location: ../dashboard.php');
        exit;
    }
}

/**
 * ตั้งค่าข้อความแจ้งเตือน Flash Message
 */
function setFlash($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => in_array($type, ['success', 'danger', 'warning', 'info']) ? $type : 'info',
        'text' => $message
    ];
}

/**
 * ดึงข้อความ Flash Message แล้วลบออก
 */
function getFlash() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * แสดง HTML สำหรับ Flash Message
 */
function renderFlash() {
    $flash = getFlash();
    if (!$flash) return '';
    $type = htmlspecialchars($flash['type']);
    $text = htmlspecialchars($flash['text']);
    $icon = 'info-circle';
    if ($type === 'success') $icon = 'check-circle';
    elseif ($type === 'danger') $icon = 'exclamation-circle';
    elseif ($type === 'warning') $icon = 'exclamation-triangle';

    return "<div class=\"alert alert-{$type} alert-dismissible fade show shadow-sm\" role=\"alert\">
        <i class=\"bi bi-{$icon} me-2\"></i> {$text}
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
    </div>";
}

/**
 * ดึงการตั้งค่าแพ็กเกจทั้งหมดจากฐานข้อมูล (Cached)
 */
function getAllPackageSettings($itemType = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT * FROM `package_settings`");
            while ($row = $stmt->fetch()) {
                $cache[$row['item_type'] . '_' . $row['item_id']] = $row;
            }
        } catch (Exception $e) {
            // Ignore if table not yet created
        }
    }

    if ($itemType !== null) {
        $filtered = [];
        foreach ($cache as $k => $v) {
            if ($v['item_type'] === $itemType) {
                $filtered[$v['item_id']] = $v;
            }
        }
        return $filtered;
    }

    return $cache;
}

/**
 * คำนวณราคาขายลูกค้าและดึงสถานะแพ็กเกจอย่างละเอียด
 * รองรับทั้งสูตร Global Markup, Custom Markup รายแพ็กเกจ และ Direct Fixed Price
 * @param string $itemType 'hosting' หรือ 'vps'
 * @param int $itemId ID ของแพ็กเกจใน API
 * @param float $costMonthly ราคาทุนรายเดือนจาก API
 * @param float|null $costYearly ราคาทุนรายปีจาก API
 * @return array ข้อมูลราคาและสถานะแพ็กเกจ
 */
function getPackagePricing($itemType, $itemId, $costMonthly, $costYearly = null) {
    $costMonthly = (float)$costMonthly;
    $costYearly = ($costYearly !== null) ? (float)$costYearly : ($costMonthly * 10);

    $settings = getAllPackageSettings();
    $key = $itemType . '_' . $itemId;
    $row = $settings[$key] ?? null;

    $isActive = true;
    $isFeatured = false;
    $badgeText = '';
    $customName = '';
    $customFeatures = '';
    $pricingMode = 'global';
    $markupPercent = (float)getSetting('markup_percent', 0);
    $markupFixed = (float)getSetting('markup_fixed', 0);

    $sellMonthly = $costMonthly;
    $sellYearly = $costYearly;

    if ($row) {
        $isActive = (bool)($row['is_active'] ?? 1);
        $isFeatured = (bool)($row['is_featured'] ?? 0);
        $badgeText = trim($row['badge_text'] ?? '');
        $customName = trim($row['custom_name'] ?? '');
        $customFeatures = trim($row['custom_features'] ?? '');
        $pricingMode = $row['pricing_mode'] ?? 'global';

        if ($pricingMode === 'custom_price') {
            $sellMonthly = (float)($row['custom_price_monthly'] ?? $costMonthly);
            $sellYearly = (float)($row['custom_price_yearly'] ?? ($sellMonthly * 10));
        } elseif ($pricingMode === 'custom_markup') {
            $markupPercent = (float)($row['custom_markup_percent'] ?? 0);
            $markupFixed = (float)($row['custom_markup_fixed'] ?? 0);

            $sellMonthly = $costMonthly + ($costMonthly * ($markupPercent / 100)) + $markupFixed;
            $sellYearly = $costYearly + ($costYearly * ($markupPercent / 100)) + ($markupFixed * 10);
        } else { // global markup
            $sellMonthly = $costMonthly + ($costMonthly * ($markupPercent / 100)) + $markupFixed;
            $sellYearly = $costYearly + ($costYearly * ($markupPercent / 100)) + ($markupFixed * 10);
        }
    } else {
        // Global markup calculation
        $sellMonthly = $costMonthly + ($costMonthly * ($markupPercent / 100)) + $markupFixed;
        $sellYearly = $costYearly + ($costYearly * ($markupPercent / 100)) + ($markupFixed * 10);
    }

    $sellMonthly = round(max(0, $sellMonthly), 2);
    $sellYearly = round(max(0, $sellYearly), 2);

    return [
        'is_active'       => $isActive,
        'is_featured'     => $isFeatured,
        'badge_text'      => $badgeText,
        'custom_name'     => $customName,
        'custom_features' => $customFeatures,
        'pricing_mode'    => $pricingMode,
        'cost_monthly'    => $costMonthly,
        'cost_yearly'     => $costYearly,
        'sell_monthly'    => $sellMonthly,
        'sell_yearly'     => $sellYearly,
        'profit_monthly'  => $sellMonthly - $costMonthly,
        'profit_yearly'   => $sellYearly - $costYearly,
        'markup_percent'  => $markupPercent,
        'markup_fixed'    => $markupFixed,
    ];
}

/**
 * คำนวณราคาขายลูกค้าทั่วไป (Helper wrapper)
 * @param float $basePrice ราคาทุนจาก API
 * @return float ราคาขายลูกค้า
 */
function calculateCustomerPrice($basePrice) {
    $basePrice = (float)$basePrice;
    $percent = (float)getSetting('markup_percent', 0);
    $fixed = (float)getSetting('markup_fixed', 0);

    $price = $basePrice;
    if ($percent > 0) {
        $price += ($basePrice * ($percent / 100));
    }
    if ($fixed > 0) {
        $price += $fixed;
    }

    return round($price, 2);
}

/**
 * จัดรูปแบบการแสดงผลสเปกของแพ็กเกจ Hosting ให้ถูกต้องและสวยงาม
 * แก้ปัญหาค่า 0 จาก API ที่หมายถึง Unlimited หรือ 1 โดเมนหลัก
 * @param string $key 'disk_mb', 'bandwidth_mb', 'domains', 'databases', 'emails'
 * @param mixed $val ค่าที่ได้จาก API
 * @return string ข้อความที่จัดรูปแบบแล้ว
 */
function formatHostingSpec($key, $val) {
    if ($key === 'disk_mb') {
        $val = (float)$val;
        if ($val >= 1024) {
            $gb = $val / 1024;
            return '<strong>' . ($gb == (int)$gb ? (int)$gb : number_format($gb, 1)) . ' GB</strong> NVMe SSD';
        }
        return ($val > 0) ? ('<strong>' . (int)$val . ' MB</strong> NVMe SSD') : '<span class="text-success fw-bold">ไม่จำกัด (Unlimited)</span>';
    }

    if ($key === 'bandwidth_mb') {
        if (empty($val) || $val == 0 || $val === 'unlimited' || $val < 0) {
            return '<span class="text-success fw-bold">ไม่จำกัด (Unlimited)</span>';
        }
        $val = (float)$val;
        if ($val >= 1024) {
            $gb = $val / 1024;
            return '<strong>' . ($gb == (int)$gb ? (int)$gb : number_format($gb, 1)) . ' GB</strong>';
        }
        return '<strong>' . (int)$val . ' MB</strong>';
    }

    if ($key === 'domains') {
        if ($val === 'unlimited' || $val < 0 || $val === '0' || $val === 0 || empty($val)) {
            return '<span class="text-success fw-bold">ไม่จำกัด (Unlimited)</span>';
        }
        return '<strong>' . (int)$val . '</strong> โดเมน';
    }

    if ($key === 'databases') {
        if ($val === 'unlimited' || $val < 0 || $val === '0' || $val === 0 || empty($val)) {
            return '<span class="text-success fw-bold">ไม่จำกัด (Unlimited)</span>';
        }
        return '<strong>' . (int)$val . '</strong> ฐานข้อมูล';
    }

    if ($key === 'emails') {
        if ($val === 'unlimited' || $val < 0 || $val === '0' || $val === 0 || empty($val)) {
            return '<span class="text-success fw-bold">ไม่จำกัด (Unlimited)</span>';
        }
        return '<strong>' . (int)$val . '</strong> บัญชี';
    }

    return htmlspecialchars((string)$val);
}


/**
 * บันทึกแคชข้อมูล API ลงตาราง api_cache
 */
function setApiCache($key, $data) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO `api_cache` (`cache_key`, `cache_data`, `updated_at`) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE `cache_data` = VALUES(`cache_data`), `updated_at` = NOW()");
        $stmt->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * ดึงแคชข้อมูล API จากตาราง api_cache
 */
function getApiCache($key, $maxAgeMinutes = 1440) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT `cache_data`, `updated_at` FROM `api_cache` WHERE `cache_key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $decoded = json_decode($row['cache_data'], true);
        return $decoded;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * ดึงการตั้งค่าหมวดหมู่ทั้งหมดจากฐานข้อมูล
 */
function getAllCategorySettings() {
    static $catCache = null;
    if ($catCache === null) {
        $catCache = [];
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT * FROM `category_settings`");
            while ($row = $stmt->fetch()) {
                $catCache[$row['category_id']] = $row;
            }
        } catch (Exception $e) {
            // table might not exist yet
        }
    }
    return $catCache;
}

/**
 * ซิงค์ข้อมูลหมวดหมู่, แพ็กเกจ Hosting และ VPS จาก API เข้าสู่ระบบ Cache
 * @param NamiResellerAPI|null $api
 * @return array ข้อมูลสรุปผลการซิงค์
 */
function syncAllDataFromAPI($api = null) {
    if (!$api) {
        require_once __DIR__ . '/api_helper.php';
        $api = new NamiResellerAPI();
    }

    $res = [
        'ok' => true,
        'categories_count' => 0,
        'packages_count' => 0,
        'vps_count' => 0,
        'errors' => []
    ];

    // 1. ดึง Categories
    $catRes = $api->getCategories();
    if ($catRes && !empty($catRes['ok']) && !empty($catRes['categories'])) {
        setApiCache('categories', $catRes['categories']);
        $res['categories_count'] = count($catRes['categories']);
    } elseif ($catRes && !empty($catRes['error'])) {
        $res['errors'][] = 'Categories: ' . $catRes['error'];
    }

    // 2. ดึง Hosting Packages
    $pkgRes = $api->getPackages();
    if ($pkgRes && !empty($pkgRes['ok']) && !empty($pkgRes['categories'])) {
        setApiCache('packages', $pkgRes['categories']);
        $totalPkg = 0;
        foreach ($pkgRes['categories'] as $c) {
            $totalPkg += count($c['packages'] ?? []);
        }
        $res['packages_count'] = $totalPkg;
    } elseif ($pkgRes && !empty($pkgRes['error'])) {
        $res['errors'][] = 'Packages: ' . $pkgRes['error'];
    }

    // 3. ดึง VPS Packages
    $vpsRes = $api->getVPSPackages();
    if ($vpsRes && !empty($vpsRes['ok']) && !empty($vpsRes['plans'])) {
        setApiCache('vps_packages', $vpsRes);
        $res['vps_count'] = count($vpsRes['plans']);
    } elseif ($vpsRes && !empty($vpsRes['error'])) {
        $res['errors'][] = 'VPS: ' . $vpsRes['error'];
    }

    if (!empty($res['errors']) && $res['categories_count'] == 0 && $res['vps_count'] == 0) {
        $res['ok'] = false;
    }

    return $res;
}

/**
 * ดึงรายการ Hosting Packages พร้อม Cache Fallback
 */
function fetchHostingPackages($api = null) {
    if (!$api) {
        require_once __DIR__ . '/api_helper.php';
        $api = new NamiResellerAPI();
    }

    $pkgRes = $api->getPackages();
    if ($pkgRes && !empty($pkgRes['ok']) && !empty($pkgRes['categories'])) {
        setApiCache('packages', $pkgRes['categories']);
        return $pkgRes['categories'];
    }

    // Fallback to cache
    $cached = getApiCache('packages');
    if (!empty($cached)) {
        return $cached;
    }

    return [];
}

/**
 * ดึงรายการ VPS Packages พร้อม Cache Fallback
 */
function fetchVPSPackages($api = null) {
    if (!$api) {
        require_once __DIR__ . '/api_helper.php';
        $api = new NamiResellerAPI();
    }

    $vpsRes = $api->getVPSPackages();
    if ($vpsRes && !empty($vpsRes['ok']) && !empty($vpsRes['plans'])) {
        setApiCache('vps_packages', $vpsRes);
        return $vpsRes;
    }

    // Fallback to cache
    $cached = getApiCache('vps_packages');
    if (!empty($cached)) {
        return $cached;
    }

    return [];
}



/**
 * หักเงินจาก Wallet ของผู้ใช้
 * @return bool สำเร็จหรือไม่
 */
function deductUserCredit($userId, $amount, $description, $type, $refId = null) {
    $amount = (float)$amount;
    if ($amount <= 0) return false;

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        // Lock row เพื่อป้องกัน race condition
        $stmt = $pdo->prepare("SELECT credit FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentCredit = $stmt->fetchColumn();

        if ($currentCredit === false || (float)$currentCredit < $amount) {
            $pdo->rollBack();
            return false;
        }

        $balanceBefore = (float)$currentCredit;
        $balanceAfter = $balanceBefore - $amount;

        // หักเงิน
        $updateStmt = $pdo->prepare("UPDATE `users` SET `credit` = ? WHERE `id` = ?");
        $updateStmt->execute([$balanceAfter, $userId]);

        // บันทึก Transaction
        $txStmt = $pdo->prepare("INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `description`, `ref_id`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $txStmt->execute([$userId, $type, $amount, $balanceBefore, $balanceAfter, $description, $refId]);

        $pdo->commit();
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            $_SESSION['user_credit'] = $balanceAfter;
        }
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * เติมเงินเข้า Wallet ของผู้ใช้
 */
function addUserCredit($userId, $amount, $description, $type = 'topup', $refId = null) {
    $amount = (float)$amount;
    if ($amount <= 0) return false;

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT credit FROM `users` WHERE `id` = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentCredit = (float)$stmt->fetchColumn();

        $balanceBefore = $currentCredit;
        $balanceAfter = $balanceBefore + $amount;

        $updateStmt = $pdo->prepare("UPDATE `users` SET `credit` = ? WHERE `id` = ?");
        $updateStmt->execute([$balanceAfter, $userId]);

        $txStmt = $pdo->prepare("INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `description`, `ref_id`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $txStmt->execute([$userId, $type, $amount, $balanceBefore, $balanceAfter, $description, $refId]);

        $pdo->commit();
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            $_SESSION['user_credit'] = $balanceAfter;
        }
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * แสดงจำนวนเงินพร้อมสัญลักษณ์
 */
function formatMoney($amount) {
    return number_format((float)$amount, 2) . ' ' . SITE_CURRENCY;
}

/**
 * ฟอร์แมตวันที่ภาษาไทย
 */
function thaiDate($datetime, $showTime = true) {
    if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) return $datetime;

    $thaiMonths = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    $day = date('j', $ts);
    $month = $thaiMonths[(int)date('n', $ts)];
    $year = date('Y', $ts) + 543; // ปี พ.ศ.

    if ($showTime) {
        $time = date('H:i', $ts) . ' น.';
        return "{$day} {$month} {$year} {$time}";
    }
    return "{$day} {$month} {$year}";
}

/**
 * แสดง Badge สถานะสวยงาม
 */
function statusBadge($status) {
    $status = strtolower($status);
    $badges = [
        'active'    => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>ใช้งานอยู่</span>',
        'pending'   => '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>รอดำเนินการ</span>',
        'approved'  => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>อนุมัติแล้ว</span>',
        'rejected'  => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>ปฏิเสธ</span>',
        'paid'      => '<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>ชำระแล้ว</span>',
        'unpaid'    => '<span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>ยังไม่ชำระ</span>',
        'suspended' => '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>ถูกระงับ</span>',
        'expired'   => '<span class="badge bg-dark"><i class="bi bi-calendar-x me-1"></i>หมดอายุ</span>',
        'cancelled' => '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>ยกเลิก</span>',
        'failed'    => '<span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i>ล้มเหลว</span>',
    ];
    return $badges[$status] ?? ('<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>');
}

/**
 * สร้างรหัสคำสั่งซื้อ / รหัสธุรกรรม
 */
function generateRefNo($prefix = 'ORD') {
    return $prefix . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/**
 * ทำความสะอาด String
 */
function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * เติมเงินผ่าน TrueMoney Angpao (ซองของขวัญ) อัตโนมัติ
 * API: https://api.xpluem.com/:link/:phone
 * @param string $voucherInput ลิงก์ซองของขวัญ เช่น https://gift.truemoney.com/campaign/?v=xxx หรือโค้ด xxx
 * @param string $phone เบอร์โทรศัพท์ผู้รับเงิน (10 หลัก)
 * @return array ผลลัพธ์จาก API [ 'success' => bool, 'status' => int, 'message' => string, 'data' => [...] ]
 */
function redeemTrueMoneyVoucher($voucherInput, $phone) {
    $voucherInput = trim($voucherInput);
    $code = '';

    // แยก Voucher Code จาก URL
    if (preg_match('/[?&]v=([a-zA-Z0-9]+)/', $voucherInput, $matches)) {
        $code = $matches[1];
    } elseif (preg_match('/^[a-zA-Z0-9]+$/', $voucherInput)) {
        $code = $voucherInput;
    } else {
        $parsed = parse_url($voucherInput);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (isset($q['v'])) {
                $code = $q['v'];
            }
        }
    }

    if (empty($code)) {
        return [
            'success' => false,
            'status'  => 400,
            'message' => 'ลิงก์ซองของขวัญไม่ถูกต้อง กรุณาใส่ลิงก์ เช่น https://gift.truemoney.com/campaign/?v=...',
            'data'    => null
        ];
    }

    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) !== 10) {
        return [
            'success' => false,
            'status'  => 400,
            'message' => 'เบอร์โทรศัพท์ผู้รับเงินของระบบไม่ถูกต้อง (ต้องเป็นเบอร์ 10 หลัก)',
            'data'    => null
        ];
    }

    $url = 'https://api.xpluem.com/' . urlencode($code) . '/' . urlencode($phone);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'TrueMoneyAngpaoPHP/1.0',
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'status'  => 500,
            'message' => 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ TrueMoney API ได้: ' . $curlErr,
            'data'    => null
        ];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [
            'success' => false,
            'status'  => $httpCode,
            'message' => 'การตอบกลับจาก TrueMoney API ไม่ถูกต้อง',
            'raw'     => $response,
            'data'    => null
        ];
    }

    return $data;
}

/**
 * Generate CSRF Token and store in session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF HTML Hidden Field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Validate Redirect URL to prevent Open Redirect attacks.
 * Only allows relative paths or the same host.
 */
function safeRedirectUrl($url, $default = 'dashboard.php') {
    $url = trim($url);
    if (empty($url)) {
        return $default;
    }
    // Parse the URL
    $parsed = parse_url($url);
    // If it has a scheme (http/https) or host, check if host matches our server
    if (!empty($parsed['host'])) {
        $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if (strtolower($parsed['host']) !== strtolower($serverHost)) {
            return $default; // External link blocked
        }
    } elseif (preg_match('/^\/\//', $url)) {
        // Blocks protocol-relative URLs like //evil.com
        return $default;
    }
    
    // Check for dangerous schemes
    if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
        return $default;
    }
    
    return $url;
}

