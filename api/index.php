<?php
/**
 * Reseller API Endpoint — api/index.php
 *
 * Authentication : X-Api-Key header (ห้ามส่งผ่าน ?key=)
 * Scopes         : read | order | renew
 * Rate Limit     : 120 requests / นาที / API Key
 * IP Whitelist   : ตั้งค่าในหน้า Profile → API Keys
 */

ob_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/api_helper.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// Response helpers
// ============================================================

function apiResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function apiOk(array $data = [], array $extra = [], int $code = 200): void {
    apiResponse(array_merge(['ok' => true], $extra, ['data' => $data]), $code);
}

function apiError(string $msg, int $code = 400): void {
    apiResponse(['ok' => false, 'error' => $msg], $code);
}

// ============================================================
// POST body — รองรับทั้ง JSON และ form-urlencoded
// ============================================================

$postBody = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $postBody = $decoded;
        }
    } else {
        // form-urlencoded / multipart
        $postBody = $_POST;
    }
}

/**
 * ดึง field จาก POST body (JSON หรือ form) แล้ว fallback ไป $_POST
 */
function post(string $key, $default = null) {
    global $postBody;
    return $postBody[$key] ?? $default;
}

// ============================================================
// Authentication
// ============================================================

$authUser    = null;
$authIsAdmin = false;
$authScopes  = [];
$authViaKey  = false;
$authKeyId   = null;

$apiKeyHeader = trim($_SERVER['HTTP_X_API_KEY'] ?? '');

if ($apiKeyHeader !== '') {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT u.*, k.id AS key_id, k.api_key, k.scopes, k.ip_whitelist
        FROM `user_api_keys` k
        JOIN `users` u ON u.id = k.user_id
        WHERE k.api_key = ? AND k.is_active = 1 AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$apiKeyHeader]);
    $authUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($authUser) {
        $authKeyId   = (int)$authUser['key_id'];
        $authIsAdmin = ($authUser['role'] === 'admin');
        $authViaKey  = true;

        // ---- IP Whitelist ----
        $ipWhitelistRaw = trim($authUser['ip_whitelist'] ?? '');
        if ($ipWhitelistRaw !== '') {
            $clientIp      = $_SERVER['HTTP_CF_CONNECTING_IP']
                          ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                          ?? $_SERVER['REMOTE_ADDR']
                          ?? '';
            // รองรับ X-Forwarded-For ที่มีหลาย IP (เอาตัวแรก)
            $clientIp = trim(explode(',', $clientIp)[0]);
            $allowed  = array_filter(array_map('trim', explode(',', $ipWhitelistRaw)));
            if (!in_array($clientIp, $allowed, true)) {
                apiError('IP ' . $clientIp . ' ไม่อยู่ใน whitelist ของ API Key นี้', 403);
            }
        }

        // ---- Rate Limiting: 120 req/min ----
        $windowStart = date('Y-m-d H:i:00'); // round down to current minute
        $pdo->prepare("
            INSERT INTO `api_rate_limits` (`api_key_id`, `window_start`, `request_count`)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE `request_count` = `request_count` + 1
        ")->execute([$authKeyId, $windowStart]);

        $countStmt = $pdo->prepare("
            SELECT `request_count` FROM `api_rate_limits`
            WHERE `api_key_id` = ? AND `window_start` = ?
        ");
        $countStmt->execute([$authKeyId, $windowStart]);
        $reqCount = (int)$countStmt->fetchColumn();

        if ($reqCount > 120) {
            header('Retry-After: 60');
            header('X-RateLimit-Limit: 120');
            header('X-RateLimit-Remaining: 0');
            apiError('เกิน rate limit (120 requests/นาที) — กรุณารอ 1 นาทีแล้วลองใหม่', 429);
        }

        header('X-RateLimit-Limit: 120');
        header('X-RateLimit-Remaining: ' . max(0, 120 - $reqCount));

        // ---- Scopes ----
        $rawScopes  = $authUser['scopes'] ?? 'read';
        $authScopes = array_values(array_filter(array_map('trim', explode(',', strtolower($rawScopes)))));
        if ($authIsAdmin) {
            $authScopes = ['read', 'order', 'renew'];
        }

        // อัพเดท last_used_at
        $pdo->prepare("UPDATE `user_api_keys` SET `last_used_at` = NOW() WHERE `id` = ?")
            ->execute([$authKeyId]);

        // ทำความสะอาด rate limit rows เก่า (> 2 นาที) เป็นครั้งคราว
        if (mt_rand(1, 50) === 1) {
            $pdo->prepare("DELETE FROM `api_rate_limits` WHERE `window_start` < DATE_SUB(NOW(), INTERVAL 2 MINUTE)")
                ->execute();
        }
    }
}

// Fallback: session login (browser)
if (!$authUser && isLoggedIn()) {
    $authUser = getLoggedInUser();
    if ($authUser && is_array($authUser)) {
        $authIsAdmin = ($authUser['role'] === 'admin');
        $authScopes  = ['read', 'order', 'renew'];
    } else {
        $authUser = null;
    }
}

/**
 * ตรวจสอบ scope — exit 403 ทันทีหากไม่มีสิทธิ์
 */
function requireScope(string $scope): void {
    global $authScopes, $authViaKey;
    if (!$authViaKey) return; // session มีสิทธิ์ทั้งหมด
    if (!in_array($scope, $authScopes, true)) {
        apiError("API Key นี้ไม่มี scope '{$scope}' — สร้าง Key ใหม่ที่เปิด scope ที่ต้องการ", 403);
    }
}

// ============================================================
// Router
// ============================================================

$action = trim($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Admin API Key สามารถระบุ user_id เพื่อทำในนาม user อื่น
$targetUserId = null;
if (!empty($_GET['user_id']) && $authIsAdmin) {
    $targetUserId = (int)$_GET['user_id'];
} elseif ($authUser) {
    $targetUserId = (int)$authUser['id'];
}

// ============================================================
// PUBLIC ENDPOINTS
// ============================================================

// ---- categories ----
if ($action === 'categories') {
    $api = new NamiResellerAPI();
    $raw = $api->getCategories();

    if ($raw && !empty($raw['ok']) && !empty($raw['categories'])) {
        $catSettings = getAllCategorySettings();
        $out = [];
        foreach ($raw['categories'] as $cat) {
            $catId   = (int)($cat['id'] ?? 0);
            $setting = $catSettings[$catId] ?? null;
            if ($setting && isset($setting['is_active']) && !$setting['is_active']) continue;

            $out[] = [
                'id'          => $catId,
                'name'        => ($setting['custom_name'] ?? '') ?: ($cat['name'] ?? ''),
                'slug'        => $cat['slug'] ?? strtolower(str_replace(' ', '-', $cat['name'] ?? '')),
                'description' => $cat['description'] ?? '',
                'icon'        => $cat['icon'] ?? null,
                'sort_order'  => (int)($setting['sort_order'] ?? $cat['sort_order'] ?? 0),
            ];
        }
        apiResponse(['ok' => true, 'count' => count($out), 'data' => $out]);
    }

    // Fallback จาก cache
    $cached = getApiCache('categories');
    if (!empty($cached)) {
        apiResponse(['ok' => true, 'count' => count($cached), 'data' => $cached]);
    }

    apiResponse(['ok' => true, 'count' => 0, 'data' => []]);
}

// ---- packages ----
if ($action === 'packages') {
    $api        = new NamiResellerAPI();
    $rawGroups  = fetchHostingPackages($api);
    $catSettings = getAllCategorySettings();

    // กรองตาม ?category=slug ถ้าระบุ
    $filterCat = trim($_GET['category'] ?? '');

    $out = [];
    foreach ($rawGroups as $grp) {
        $catId      = (int)($grp['category_id'] ?? 0);
        $catSetting = $catSettings[$catId] ?? null;
        if ($catSetting && isset($catSetting['is_active']) && !$catSetting['is_active']) continue;

        $catSlug = $grp['category_slug'] ?? strtolower(str_replace(' ', '-', $grp['category_name'] ?? ''));
        if ($filterCat !== '' && $catSlug !== $filterCat) continue;

        $pkgs = [];
        foreach (($grp['packages'] ?? []) as $p) {
            $costM   = (float)$p['price_monthly'];
            $costY   = (float)($p['price_yearly'] ?? ($costM * 10));
            $pricing = getPackagePricing('hosting', $p['id'], $costM, $costY);
            if (!$pricing['is_active']) continue;

            $pkgs[] = [
                'id'            => $p['id'],
                'name'          => $pricing['custom_name'] ?: $p['name'],
                'slug'          => $p['slug'] ?? strtolower(str_replace(' ', '-', $p['name'])),
                'price_monthly' => $pricing['sell_monthly'],
                'price_yearly'  => $pricing['sell_yearly'],
                'disk_mb'       => (int)($p['disk_mb'] ?? 0),
                'bandwidth_mb'  => (int)($p['bandwidth_mb'] ?? 0),
                'domains'       => (int)($p['domains'] ?? 0),
                'databases'     => (int)($p['databases'] ?? 0),
                'emails'        => (int)($p['emails'] ?? 0),
                'is_featured'   => (int)$pricing['is_featured'],
                'badge'         => $pricing['badge_text'] ?: null,
                'account_type'  => $p['account_type'] ?? 'user',
            ];
        }

        if (empty($pkgs)) continue;

        $out[] = [
            'category_id'   => $catId,
            'category_name' => ($catSetting['custom_name'] ?? '') ?: ($grp['category_name'] ?? ''),
            'category_slug' => $catSlug,
            'packages'      => $pkgs,
        ];
    }

    $totalPkgs = array_sum(array_map(fn($c) => count($c['packages']), $out));
    apiResponse(['ok' => true, 'count' => $totalPkgs, 'categories' => $out]);
}

// ---- server-status ----
if ($action === 'server-status') {
    $api     = new NamiResellerAPI();
    $raw     = $api->request('server_status', 'GET', [], false);
    $servers = [];

    if ($raw && !empty($raw['ok']) && !empty($raw['servers'])) {
        foreach ($raw['servers'] as $srv) {
            $row = [
                'id'           => $srv['id'] ?? 0,
                'name'         => $srv['name'] ?? '',
                'status'       => $srv['status'] ?? 'active',
                'online'       => ($srv['status'] ?? '') === 'active',
                'accounts'     => (int)($srv['accounts'] ?? 0),
                'max_accounts' => (int)($srv['max_accounts'] ?? 100),
            ];
            if ($authUser) {
                $row['load']         = $srv['load'] ?? null;
                $row['uptime']       = $srv['uptime'] ?? null;
                $row['mem_percent']  = $srv['mem_percent'] ?? null;
                $row['disk_percent'] = $srv['disk_percent'] ?? null;
            }
            if ($authIsAdmin) {
                $row['hostname'] = $srv['hostname'] ?? null;
                $row['panel']    = $srv['panel'] ?? 'DirectAdmin';
                $row['users']    = $srv['users'] ?? null;
                $row['error']    = $srv['error'] ?? null;
            }
            $servers[] = $row;
        }
    } else {
        $servers[] = [
            'id'           => 1,
            'name'         => getSetting('site_name', SITE_NAME) . ' Server',
            'status'       => 'active',
            'online'       => true,
            'accounts'     => 0,
            'max_accounts' => 100,
        ];
    }
    apiResponse(['ok' => true, 'count' => count($servers), 'data' => $servers]);
}

// ---- news ----
if ($action === 'news') {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT `id`, `title`, `slug`, `excerpt`, `image`, `views`, `created_at` FROM `news` WHERE `is_published` = 1 ORDER BY `created_at` DESC LIMIT 20");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    apiResponse(['ok' => true, 'count' => count($news), 'data' => $news]);
}

// ---- vps_packages (public) ----
if ($action === 'vps_packages') {
    $data = fetchVPSPackages();
    if (!empty($data)) {
        $plans = $data['plans'] ?? [];
        apiResponse(['ok' => true, 'count' => count($plans), 'plans' => $plans, 'os_options' => $data['os_options'] ?? []]);
    }
    apiResponse(['ok' => true, 'count' => 0, 'plans' => [], 'os_options' => []]);
}

// ============================================================
// AUTHENTICATED ENDPOINTS
// ============================================================

if (!$authUser) {
    apiError('ยังไม่ล็อกอินหรือ API Key ไม่ถูกต้อง', 401);
}

// ---- balance ----
if ($action === 'balance') {
    requireScope('read');
    $api = new NamiResellerAPI();
    $raw = $api->getBalance();
    if (!$raw || empty($raw['ok'])) {
        apiError($raw['error'] ?? 'ดึง balance ไม่สำเร็จ', 502);
    }
    apiOk($raw['data'] ?? $raw);
}

// ---- me ----
if ($action === 'me') {
    $pdo = getDB();
    $uid = $targetUserId ?? (int)$authUser['id'];
    if (!$authIsAdmin && $uid !== (int)$authUser['id']) {
        apiError('ไม่มีสิทธิ์ดูข้อมูลของ user อื่น', 403);
    }
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) apiError('ไม่พบ user', 404);

    $stmtInv = $pdo->prepare("SELECT COUNT(*) FROM `topups` WHERE `user_id` = ? AND `status` = 'pending'");
    $stmtInv->execute([$uid]);

    apiOk([
        'id'              => (int)$u['id'],
        'name'            => $u['fullname'] ?: $u['username'],
        'email'           => $u['email'],
        'credit'          => (float)$u['credit'],
        'role'            => $u['role'],
        'status'          => $u['status'],
        'unpaid_invoices' => (int)$stmtInv->fetchColumn(),
        'created_at'      => $u['created_at'],
    ]);
}

// ---- services ----
if ($action === 'services') {
    requireScope('read');
    if (!$targetUserId) apiError('ระบุ user_id ไม่ถูกต้อง', 400);
    if (!$authIsAdmin && $targetUserId !== (int)$authUser['id']) apiError('ไม่มีสิทธิ์', 403);

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `user_id` = ? AND `service_type` = 'hosting' ORDER BY `created_at` DESC");
    $stmt->execute([$targetUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function($s) {
        $extra = json_decode($s['extra_info'] ?? '{}', true) ?: [];
        $ns  = !empty($s['nameservers']) ? explode(',', $s['nameservers']) : [];
        $ns1 = trim($ns[0] ?? '');
        $ns2 = trim($ns[1] ?? '');

        return [
            'id'              => (int)$s['id'],
            'domain'          => $s['domain_or_hostname'],
            'username'        => $s['server_username'],
            'password'        => $extra['password'] ?? null,
            'ip_address'      => $s['ip_address'],
            'status'          => $s['status'],
            'billing_cycle'   => $s['billing_cycle'],
            'price'           => (float)$s['price'],
            'disk_used_mb'    => (int)($extra['disk_used_mb'] ?? 0),
            'bw_used_mb'      => (int)($extra['bw_used_mb'] ?? 0),
            'start_date'      => $s['start_date'],
            'next_due_date'   => $s['next_due_date'],
            'usage_synced_at' => $extra['usage_synced_at'] ?? null,
            'package'         => $s['package_name'],
            'disk_mb'         => (int)($extra['disk_mb'] ?? 0),
            'bandwidth_mb'    => (int)($extra['bandwidth_mb'] ?? 0),
            'server_hostname' => $s['server_name'],
            'nameserver1'     => $ns1 ?: null,
            'nameserver2'     => $ns2 ?: null,
        ];
    }, $rows);

    apiResponse(['ok' => true, 'count' => count($out), 'data' => $out]);
}

// ---- service ----
if ($action === 'service') {
    requireScope('read');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) apiError('กรุณาระบุ id', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
        $stmt->execute([$id, (int)$authUser['id']]);
    }
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$s) apiError('ไม่พบบริการ', 404);

    $extra   = json_decode($s['extra_info'] ?? '{}', true) ?: [];
    $diskMb  = (int)($extra['disk_mb'] ?? 0);
    $bwMb    = (int)($extra['bandwidth_mb'] ?? 0);
    $diskUsed = (int)($extra['disk_used_mb'] ?? 0);
    $bwUsed   = (int)($extra['bw_used_mb'] ?? 0);

    // Fetch live data from Master API
    $liveData = [];
    if (!empty($s['api_service_id'])) {
        $namiApi = new NamiResellerAPI();
        $liveRes = $namiApi->getService($s['api_service_id']);
        if ($liveRes && !empty($liveRes['ok'])) {
            $liveData = $liveRes['data'] ?? [];
        }
    }

    $password = $liveData['password'] ?? $extra['password'] ?? null;
    $serverHostname = $liveData['server_hostname'] ?? $liveData['server_name'] ?? $s['server_name'];
    $ipAddress = $liveData['ip_address'] ?? $s['ip_address'];
    $ns1 = $liveData['nameserver1'] ?? trim(explode(',', $s['nameservers'] ?? '')[0] ?? '');
    $ns2 = $liveData['nameserver2'] ?? trim(explode(',', $s['nameservers'] ?? '')[1] ?? '');

    apiOk([
        'id'              => (int)$s['id'],
        'domain'          => $s['domain_or_hostname'],
        'username'        => $s['server_username'],
        'status'          => $liveData['status'] ?? $s['status'],
        'billing_cycle'   => $s['billing_cycle'],
        'price'           => (float)$s['price'],
        'disk_used_mb'    => $liveData['disk_used_mb'] ?? $diskUsed,
        'bw_used_mb'      => $liveData['bw_used_mb'] ?? $bwUsed,
        'disk_percent'    => $liveData['disk_percent'] ?? ($diskMb > 0 ? round($diskUsed / $diskMb * 100, 1) : 0),
        'bw_percent'      => $liveData['bw_percent'] ?? ($bwMb > 0 ? round($bwUsed / $bwMb * 100, 1) : 0),
        'start_date'      => $s['start_date'],
        'next_due_date'   => $liveData['next_due_date'] ?? $s['next_due_date'],
        'usage_synced_at' => $extra['usage_synced_at'] ?? null,
        'package'         => $s['package_name'],
        'disk_mb'         => $diskMb,
        'bandwidth_mb'    => $bwMb,
        'server_hostname' => $serverHostname,
        'ip_address'      => $ipAddress,
        'nameserver1'     => $ns1 ?: null,
        'nameserver2'     => $ns2 ?: null,
        'password'        => $password,
    ]);
}

// ---- vps ----
if ($action === 'vps') {
    requireScope('read');
    if (!$targetUserId) apiError('ระบุ user_id ไม่ถูกต้อง', 400);
    if (!$authIsAdmin && $targetUserId !== (int)$authUser['id']) apiError('ไม่มีสิทธิ์', 403);

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `user_id` = ? AND `service_type` = 'vps' ORDER BY `created_at` DESC");
    $stmt->execute([$targetUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function($s) {
        $extra = json_decode($s['extra_info'] ?? '{}', true) ?: [];
        return [
            'id'            => (int)$s['id'],
            'hostname'      => $s['domain_or_hostname'],
            'ip_address'    => $s['ip_address'],
            'password'      => $extra['password'] ?? null,
            'os_name'       => $s['os_name'],
            'os_type'       => $extra['os_type'] ?? null,
            'status'        => $s['status'],
            'billing_cycle' => $s['billing_cycle'],
            'price'         => (float)$s['price'],
            'plan'          => $s['package_name'],
            'vcpu'          => isset($extra['vcpu'])      ? (int)$extra['vcpu']      : null,
            'ram_mb'        => isset($extra['ram_mb'])    ? (int)$extra['ram_mb']    : null,
            'disk_gb'       => isset($extra['disk_gb'])   ? (int)$extra['disk_gb']   : null,
            'bandwidth_gb'  => isset($extra['bandwidth_gb']) ? (int)$extra['bandwidth_gb'] : null,
            'port'          => isset($extra['port'])      ? (int)$extra['port']      : 22,
            'username'      => $extra['ssh_user'] ?? 'root',
            'start_date'    => $s['start_date'],
            'next_due_date' => $s['next_due_date'],
            'server'        => $s['server_name'],
        ];
    }, $rows);

    apiResponse(['ok' => true, 'count' => count($out), 'data' => $out]);
}

// ---- vps_service ----
if ($action === 'vps_service') {
    requireScope('read');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) apiError('กรุณาระบุ id', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `service_type` = 'vps' LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? AND `service_type` = 'vps' LIMIT 1");
        $stmt->execute([$id, (int)$authUser['id']]);
    }
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$s) apiError('ไม่พบบริการ VPS', 404);

    $extra = json_decode($s['extra_info'] ?? '{}', true) ?: [];

    // Fetch live data from Master API
    $liveData = [];
    if (!empty($s['api_service_id'])) {
        $namiApi = new NamiResellerAPI();
        $liveRes = $namiApi->getVPSService($s['api_service_id']);
        if ($liveRes && !empty($liveRes['ok'])) {
            $liveData = $liveRes['data'] ?? [];
        }
    }

    $password = $liveData['password'] ?? $extra['password'] ?? null;
    $ipAddress = $liveData['ip_address'] ?? $s['ip_address'];
    $serverHostname = $liveData['hostname'] ?? $s['domain_or_hostname'];

    apiOk([
        'id'            => (int)$s['id'],
        'hostname'      => $serverHostname,
        'ip_address'    => $ipAddress,
        'os_name'       => $s['os_name'],
        'os_type'       => $extra['os_type'] ?? null,
        'status'        => $liveData['status'] ?? $s['status'],
        'billing_cycle' => $s['billing_cycle'],
        'price'         => (float)$s['price'],
        'plan'          => $s['package_name'],
        'vcpu'          => isset($extra['vcpu'])      ? (int)$extra['vcpu']      : null,
        'ram_mb'        => isset($extra['ram_mb'])    ? (int)$extra['ram_mb']    : null,
        'disk_gb'       => isset($extra['disk_gb'])   ? (int)$extra['disk_gb']   : null,
        'bandwidth_gb'  => isset($extra['bandwidth_gb']) ? (int)$extra['bandwidth_gb'] : null,
        'port'          => isset($extra['port'])      ? (int)$extra['port']      : 22,
        'username'      => $extra['ssh_user'] ?? 'root',
        'start_date'    => $s['start_date'],
        'next_due_date' => $liveData['next_due_date'] ?? $s['next_due_date'],
        'server'        => $s['server_name'],
        'api_service_id'=> $s['api_service_id'],
        'password'      => $password,
    ]);
}

// ---- invoices ----
if ($action === 'invoices') {
    requireScope('read');
    if (!$targetUserId) apiError('ระบุ user_id ไม่ถูกต้อง', 400);
    if (!$authIsAdmin && $targetUserId !== (int)$authUser['id']) apiError('ไม่มีสิทธิ์', 403);

    $pdo    = getDB();
    $status = trim($_GET['status'] ?? '');
    $allowed = ['pending', 'approved', 'rejected'];

    $sql    = "SELECT * FROM `topups` WHERE `user_id` = ?";
    $params = [$targetUserId];
    if ($status !== '' && in_array($status, $allowed, true)) {
        $sql    .= " AND `status` = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY `created_at` DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(fn($t) => [
        'id'             => (int)$t['id'],
        'invoice_no'     => $t['topup_no'],
        'type'           => 'topup',
        'description'    => $t['note'] ?: 'เติมเงิน',
        'subtotal'       => (float)$t['amount'],
        'tax'            => 0.00,
        'total'          => (float)$t['amount'],
        'status'         => $t['status'],
        'payment_method' => $t['payment_method'],
        'due_date'       => null,
        'paid_at'        => $t['approved_at'],
        'created_at'     => $t['created_at'],
    ], $rows);

    apiResponse(['ok' => true, 'count' => count($out), 'data' => $out]);
}

// ---- invoice (single) ----
if ($action === 'invoice') {
    requireScope('read');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) apiError('กรุณาระบุ id', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `topups` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `topups` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
        $stmt->execute([$id, (int)$authUser['id']]);
    }
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) apiError('ไม่พบใบแจ้งหนี้', 404);

    apiOk([
        'id'             => (int)$t['id'],
        'invoice_no'     => $t['topup_no'],
        'type'           => 'topup',
        'description'    => $t['note'] ?: 'เติมเงิน',
        'subtotal'       => (float)$t['amount'],
        'tax'            => 0.00,
        'total'          => (float)$t['amount'],
        'status'         => $t['status'],
        'payment_method' => $t['payment_method'],
        'slip_image'     => $t['slip_image'],
        'due_date'       => null,
        'paid_at'        => $t['approved_at'],
        'created_at'     => $t['created_at'],
    ]);
}

// ---- sync-usage ----
if ($action === 'sync-usage' && $method === 'POST') {
    requireScope('read');
    $id = (int)(post('id') ?? 0);
    if (!$id) apiError('กรุณาระบุ id ของบริการ', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
        $stmt->execute([$id, (int)$authUser['id']]);
    }
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) apiError('ไม่พบบริการหรือไม่มีสิทธิ์', 404);

    $api       = new NamiResellerAPI();
    $apiSvcId  = $service['api_service_id'];
    $usageData = ['disk_used_mb' => 0, 'bw_used_mb' => 0, 'usage_synced_at' => date('Y-m-d H:i:s')];

    if ($apiSvcId) {
        $raw = $api->getService($apiSvcId);
        if ($raw && !empty($raw['ok']) && !empty($raw['data'])) {
            $d = $raw['data'];
            $usageData['disk_used_mb'] = (int)($d['disk_used_mb'] ?? 0);
            $usageData['bw_used_mb']   = (int)($d['bw_used_mb']   ?? 0);
        }
    }

    $extra = json_decode($service['extra_info'] ?? '{}', true) ?: [];
    $extra = array_merge($extra, $usageData);
    $pdo->prepare("UPDATE `services` SET `extra_info` = ? WHERE `id` = ?")
        ->execute([json_encode($extra, JSON_UNESCAPED_UNICODE), $id]);

    apiOk($usageData, ['message' => 'ซิงค์สำเร็จ']);
}

// ---- order_hosting ----
if ($action === 'order_hosting') {
    if ($method !== 'POST') apiError('ต้องใช้ method POST', 405);
    requireScope('order');

    $productId    = (int)(post('product_id')    ?? 0);
    $domain       = trim((string)(post('domain')       ?? ''));
    $username     = trim((string)(post('username')     ?? ''));
    $password     = (string)(post('password')          ?? '');
    $billingCycle = trim((string)(post('billing_cycle') ?? 'monthly'));

    if (!$productId)                             apiError('กรุณาระบุ product_id', 400);
    if ($domain === '')                          apiError('กรุณาระบุ domain', 400);
    if ($username === '')                        apiError('กรุณาระบุ username', 400);
    if (strlen($password) < 8)                   apiError('password ต้องมีอย่างน้อย 8 ตัวอักษร', 400);
    if (!preg_match('/^[a-z][a-z0-9]{3,15}$/', $username)) {
        apiError('username ต้องเริ่มด้วยตัวอักษร a-z และยาว 4-16 ตัว (a-z0-9 เท่านั้น)', 400);
    }
    if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
        apiError("billing_cycle ต้องเป็น 'monthly' หรือ 'yearly'", 400);
    }

    $api = new NamiResellerAPI();
    $raw = $api->orderHosting($productId, $domain, $username, $password, $billingCycle);

    if (!$raw || empty($raw['ok'])) {
        apiError($raw['error'] ?? 'สั่งซื้อ Hosting ไม่สำเร็จ', 502);
    }

    $pdo     = getDB();
    $orderNo = generateRefNo('H');
    $svcData = $raw['data'] ?? [];
    $amount  = (float)($svcData['amount'] ?? 0);

    $pdo->prepare("INSERT INTO `orders` (`user_id`, `order_no`, `service_type`, `product_id`, `product_name`, `domain_or_hostname`, `billing_cycle`, `amount`, `status`, `api_response`) VALUES (?, ?, 'hosting', ?, ?, ?, ?, ?, 'paid', ?)")
        ->execute([$targetUserId, $orderNo, $productId, $svcData['package_name'] ?? '', $domain, $billingCycle, $amount, json_encode($svcData, JSON_UNESCAPED_UNICODE)]);
    $localOrderId = (int)$pdo->lastInsertId();

    // HTTP 201 Created
    apiOk([
        'order_id'   => $svcData['order_id']   ?? $localOrderId,
        'order_no'   => $svcData['order_no']   ?? $orderNo,
        'service_id' => $svcData['service_id'] ?? null,
        'invoice_id' => $svcData['invoice_id'] ?? null,
        'amount'     => $amount,
        'status'     => $svcData['status']     ?? 'pending',
        'message'    => $svcData['message']    ?? 'สร้างคำสั่งซื้อสำเร็จ',
        'ip_address' => $svcData['ip_address'] ?? null,
        'server_name'=> $svcData['server_name']?? null,
        'nameservers'=> $svcData['nameservers']?? null,
        'password'   => $svcData['password']   ?? null,
    ], [], 201);
}

// ---- order_vps ----
if ($action === 'order_vps') {
    if ($method !== 'POST') apiError('ต้องใช้ method POST', 405);
    requireScope('order');

    $productId    = (int)(post('product_id')    ?? 0);
    $osId         = (int)(post('os_id')          ?? 0);
    $billingCycle = trim((string)(post('billing_cycle') ?? 'monthly'));
    $hostname     = trim((string)(post('hostname')      ?? ''));

    if (!$productId) apiError('กรุณาระบุ product_id', 400);
    if (!$osId)      apiError('กรุณาระบุ os_id', 400);
    if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
        apiError("billing_cycle ต้องเป็น 'monthly' หรือ 'yearly'", 400);
    }

    $api = new NamiResellerAPI();
    $raw = $api->orderVPS($productId, $osId, $billingCycle, $hostname ?: null);

    if (!$raw || empty($raw['ok'])) {
        apiError($raw['error'] ?? 'สั่งซื้อ VPS ไม่สำเร็จ', 502);
    }

    $pdo     = getDB();
    $orderNo = generateRefNo('V');
    $svcData = $raw['data'] ?? [];
    $amount  = (float)($svcData['amount'] ?? 0);

    $pdo->prepare("INSERT INTO `orders` (`user_id`, `order_no`, `service_type`, `product_id`, `product_name`, `domain_or_hostname`, `billing_cycle`, `amount`, `status`, `api_response`) VALUES (?, ?, 'vps', ?, ?, ?, ?, ?, 'paid', ?)")
        ->execute([$targetUserId, $orderNo, $productId, $svcData['package_name'] ?? '', $hostname ?: 'vps-' . $targetUserId, $billingCycle, $amount, json_encode($svcData, JSON_UNESCAPED_UNICODE)]);
    $localOrderId = (int)$pdo->lastInsertId();

    apiOk([
        'order_id'   => $svcData['order_id']   ?? $localOrderId,
        'order_no'   => $svcData['order_no']   ?? $orderNo,
        'service_id' => $svcData['service_id'] ?? null,
        'invoice_id' => $svcData['invoice_id'] ?? null,
        'amount'     => $amount,
        'status'     => $svcData['status']     ?? 'pending',
        'message'    => $svcData['message']    ?? 'สร้างคำสั่งซื้อ VPS สำเร็จ',
        'ip_address' => $svcData['ip_address'] ?? null,
        'server_name'=> $svcData['server_name']?? null,
        'password'   => $svcData['root_password'] ?? $svcData['password'] ?? null,
    ], [], 201);
}

// ---- renew ----
if ($action === 'renew') {
    if ($method !== 'POST') apiError('ต้องใช้ method POST', 405);
    requireScope('renew');

    $serviceId = (int)(post('service_id') ?? 0);
    if (!$serviceId) apiError('กรุณาระบุ service_id', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `service_type` = 'hosting' LIMIT 1");
        $stmt->execute([$serviceId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? AND `service_type` = 'hosting' LIMIT 1");
        $stmt->execute([$serviceId, (int)$authUser['id']]);
    }
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service)                        apiError('ไม่พบบริการ Hosting หรือไม่มีสิทธิ์', 404);
    if (empty($service['api_service_id'])) apiError('บริการนี้ยังไม่ได้ผูกกับ API service_id', 400);

    $api = new NamiResellerAPI();
    $raw = $api->renewHosting((int)$service['api_service_id']);
    if (!$raw || empty($raw['ok'])) {
        apiError($raw['error'] ?? 'ต่ออายุ Hosting ไม่สำเร็จ', 502);
    }

    if (!empty($raw['data']['next_due_date'])) {
        $pdo->prepare("UPDATE `services` SET `next_due_date` = ? WHERE `id` = ?")
            ->execute([$raw['data']['next_due_date'], $serviceId]);
    }

    apiOk($raw['data'] ?? [], ['message' => 'ต่ออายุ Hosting สำเร็จ']);
}

// ---- renew_vps ----
if ($action === 'renew_vps') {
    if ($method !== 'POST') apiError('ต้องใช้ method POST', 405);
    requireScope('renew');

    $serviceId = (int)(post('service_id') ?? 0);
    if (!$serviceId) apiError('กรุณาระบุ service_id', 400);

    $pdo = getDB();
    if ($authIsAdmin) {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `service_type` = 'vps' LIMIT 1");
        $stmt->execute([$serviceId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? AND `service_type` = 'vps' LIMIT 1");
        $stmt->execute([$serviceId, (int)$authUser['id']]);
    }
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service)                        apiError('ไม่พบบริการ VPS หรือไม่มีสิทธิ์', 404);
    if (empty($service['api_service_id'])) apiError('บริการนี้ยังไม่ได้ผูกกับ API service_id', 400);

    $api = new NamiResellerAPI();
    $raw = $api->renewVPS((int)$service['api_service_id']);
    if (!$raw || empty($raw['ok'])) {
        apiError($raw['error'] ?? 'ต่ออายุ VPS ไม่สำเร็จ', 502);
    }

    if (!empty($raw['data']['next_due_date'])) {
        $pdo->prepare("UPDATE `services` SET `next_due_date` = ? WHERE `id` = ?")
            ->execute([$raw['data']['next_due_date'], $serviceId]);
    }

    apiOk($raw['data'] ?? [], ['message' => 'ต่ออายุ VPS สำเร็จ']);
}

// ============================================================
// ADMIN ONLY ENDPOINTS
// ============================================================

if (!$authIsAdmin) {
    apiError('ไม่มีสิทธิ์ (ต้องเป็น Admin หรือใช้ Admin API Key)', 403);
}

if ($action === 'stats') {
    $pdo  = getDB();
    $data = [
        'users'              => (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'user'")->fetchColumn(),
        'active_services'    => (int)$pdo->query("SELECT COUNT(*) FROM `services` WHERE `status` = 'active'")->fetchColumn(),
        'suspended_services' => (int)$pdo->query("SELECT COUNT(*) FROM `services` WHERE `status` = 'suspended'")->fetchColumn(),
        'pending_payments'   => (int)$pdo->query("SELECT COUNT(*) FROM `topups` WHERE `status` = 'pending'")->fetchColumn(),
        'unpaid_amount'      => number_format((float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM `topups` WHERE `status` = 'pending'")->fetchColumn(), 2),
        'month_income'       => number_format((float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM `topups` WHERE `status` = 'approved' AND MONTH(approved_at)=MONTH(NOW()) AND YEAR(approved_at)=YEAR(NOW())")->fetchColumn(), 2),
    ];
    apiOk($data);
}

apiError('ไม่พบ action นี้ในระบบ', 404);
