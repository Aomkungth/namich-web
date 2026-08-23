<?php
/**
 * User Profile & Security (profile.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$pdo = getDB();

// ค่าสูงสุดของ API Keys ต่อ user
const MAX_API_KEYS = 5;
$VALID_SCOPES = ['read', 'order', 'renew'];

$profileMsg = '';
$profileError = '';
$pwdMsg = '';
$pwdError = '';
$apiKeyMsg = '';
$apiKeyError = '';

// จัดการอัปเดตข้อมูลทั่วไป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $stmt = $pdo->prepare("UPDATE `users` SET `fullname` = ?, `phone` = ? WHERE `id` = ?");
    if ($stmt->execute([$fullname, $phone, $user['id']])) {
        setFlash('success', 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว');
        header('Location: profile.php');
        exit;
    } else {
        $profileError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    }
}

// จัดการเปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPassword, $user['password'])) {
        $pwdError = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif (strlen($newPassword) < 6) {
        $pwdError = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } elseif ($newPassword !== $confirmPassword) {
        $pwdError = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    } else {
        $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
        if ($stmt->execute([$newHashed, $user['id']])) {
            setFlash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
            header('Location: profile.php');
            exit;
        } else {
            $pwdError = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
        }
    }
}

// จัดการ API Keys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // สร้าง API Key ใหม่
    if ($_POST['action'] === 'create_api_key') {
        $label       = trim($_POST['label']        ?? '');
        $rawScopes   = $_POST['scopes']            ?? [];
        $ipWhitelist = trim($_POST['ip_whitelist'] ?? '');
        if (!is_array($rawScopes)) $rawScopes = [];

        // ตรวจสอบ IP whitelist format (ถ้าระบุ)
        $cleanIPs = '';
        if ($ipWhitelist !== '') {
            $ips = array_filter(array_map('trim', explode(',', $ipWhitelist)));
            $badIPs = [];
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $badIPs[] = $ip;
                }
            }
            if (!empty($badIPs)) {
                $apiKeyError = 'IP ไม่ถูกต้อง: ' . implode(', ', array_map('htmlspecialchars', $badIPs));
            } else {
                $cleanIPs = implode(',', $ips);
            }
        }

        $cleanScopes = array_values(array_intersect($rawScopes, $VALID_SCOPES));
        if (empty($apiKeyError) && empty($cleanScopes)) {
            $apiKeyError = 'กรุณาเลือก Scope อย่างน้อย 1 รายการ';
        }

        if (empty($apiKeyError)) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `user_api_keys` WHERE `user_id` = ? AND `is_active` = 1");
            $countStmt->execute([$user['id']]);
            if ((int)$countStmt->fetchColumn() >= MAX_API_KEYS) {
                $apiKeyError = 'คุณมี API Key ครบ ' . MAX_API_KEYS . ' รายการแล้ว กรุณาลบ Key เก่าก่อนสร้างใหม่';
            } else {
                $newKey   = 'rk_' . bin2hex(random_bytes(24));
                $scopeStr = implode(',', $cleanScopes);
                $insStmt  = $pdo->prepare("INSERT INTO `user_api_keys` (`user_id`, `api_key`, `label`, `scopes`, `ip_whitelist`) VALUES (?, ?, ?, ?, ?)");
                if ($insStmt->execute([$user['id'], $newKey, $label ?: null, $scopeStr, $cleanIPs ?: null])) {
                    setFlash('success', 'สร้าง API Key เรียบร้อย: <code class="text-break">' . htmlspecialchars($newKey) . '</code><br><small class="text-warning">⚠️ คัดลอก Key นี้เก็บไว้ทันที จะแสดงเพียงครั้งเดียว</small>');
                    header('Location: profile.php#api-keys');
                    exit;
                } else {
                    $apiKeyError = 'เกิดข้อผิดพลาดในการสร้าง API Key';
                }
            }
        }
    }

    // ลบ (revoke) API Key
    if ($_POST['action'] === 'revoke_api_key') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        if ($keyId) {
            $delStmt = $pdo->prepare("UPDATE `user_api_keys` SET `is_active` = 0 WHERE `id` = ? AND `user_id` = ?");
            $delStmt->execute([$keyId, $user['id']]);
            setFlash('success', 'ยกเลิก API Key เรียบร้อยแล้ว');
            header('Location: profile.php#api-keys');
            exit;
        }
    }
}

// ดึงรายการ API Keys ของ user
$stmtKeys = $pdo->prepare("SELECT `id`, `label`, `scopes`, `ip_whitelist`, `last_used_at`, `is_active`, `created_at` FROM `user_api_keys` WHERE `user_id` = ? ORDER BY `created_at` DESC");
$stmtKeys->execute([$user['id']]);
$apiKeys = $stmtKeys->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'ข้อมูลส่วนตัวและความปลอดภัย - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Summary -->
        <div class="col-lg-4">
            <div class="card-modern p-4 text-center mb-4">
                <div class="stat-icon primary mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['fullname'] ?: $user['username']) ?></h4>
                <p class="text-muted small mb-3">@<?= htmlspecialchars($user['username']) ?></p>
                
                <div class="p-3 bg-light rounded-3 text-start mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">ยอดเงินคงเหลือ:</span>
                        <span class="fw-bold text-success"><?= formatMoney($user['credit']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">สถานะบัญชี:</span>
                        <span><?= statusBadge($user['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">วันที่สมัคร:</span>
                        <span class="small fw-semibold"><?= thaiDate($user['created_at'], false) ?></span>
                    </div>
                </div>

                <a href="topup.php" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold">
                    <i class="bi bi-wallet2 me-1"></i> เติมเงิน Wallet
                </a>
            </div>
        </div>

        <!-- Main Settings Form -->
        <div class="col-lg-8">
            <!-- Edit Profile -->
            <div class="card-modern p-4 p-md-5 mb-4">
                <h4 class="fw-bold mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-person-lines-fill text-primary"></i> ข้อมูลส่วนตัว
                </h4>

                <?php if (!empty($profileError)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($profileError) ?></div>
                <?php endif; ?>

                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อผู้ใช้ (Username)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <div class="form-text">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">อีเมล (Email)</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            <div class="form-text">ติดต่อแอดมินหากต้องการเปลี่ยนอีเมล</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อ - นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" placeholder="สมชาย ใจดี">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08xxxxxxxx">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card-modern p-4 p-md-5 mb-4">
                <h4 class="fw-bold mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock-fill text-warning"></i> เปลี่ยนรหัสผ่าน
                </h4>

                <?php if (!empty($pwdError)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($pwdError) ?></div>
                <?php endif; ?>

                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสผ่านปัจจุบัน</label>
                        <input type="password" name="current_password" class="form-control" placeholder="กรอกรหัสผ่านเดิม" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning text-dark px-4 fw-bold">
                        <i class="bi bi-key-fill me-1"></i> อัปเดตรหัสผ่าน
                    </button>
                </form>
            </div>

            <!-- API Keys -->
            <div class="card-modern p-4 p-md-5" id="api-keys">
                <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-key-fill text-info"></i> API Keys
                </h4>
                <p class="text-muted small mb-4">ใช้สำหรับเชื่อมต่อระบบภายนอก / Bot — ส่ง Key ผ่าน HTTP Header <code>X-Api-Key</code> เท่านั้น</p>

                <?php if (!empty($apiKeyError)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($apiKeyError) ?></div>
                <?php endif; ?>

                <?php
                // แสดง flash (สำหรับ key ที่เพิ่งสร้าง)
                $flash = getFlash();
                if ($flash && $flash['type'] === 'success'):
                ?>
                    <div class="alert alert-success py-2 small"><?= $flash['text'] ?></div>
                <?php elseif ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> py-2 small"><?= htmlspecialchars($flash['text']) ?></div>
                <?php endif; ?>

                <!-- ฟอร์มสร้าง Key ใหม่ -->
                <?php $activeKeyCount = count(array_filter($apiKeys, fn($k) => $k['is_active'])); ?>
                <?php if ($activeKeyCount < MAX_API_KEYS): ?>
                <form method="POST" action="profile.php#api-keys" class="border rounded-3 p-3 bg-light mb-4">
                    <input type="hidden" name="action" value="create_api_key">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Label (ไม่บังคับ)</label>
                            <input type="text" name="label" class="form-control form-control-sm" placeholder="เช่น Bot Telegram, Reseller App" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">IP Whitelist <span class="text-muted fw-normal">(ไม่บังคับ)</span></label>
                            <input type="text" name="ip_whitelist" class="form-control form-control-sm" placeholder="1.2.3.4, 5.6.7.8 (เว้นว่าง = ทุก IP)">
                            <div class="form-text" style="font-size:.75rem">คั่นหลาย IP ด้วยเครื่องหมายจุลภาค</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Scopes <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 flex-wrap pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]" value="read" id="scope_read" checked>
                                    <label class="form-check-label small" for="scope_read">
                                        <span class="badge bg-primary">read</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]" value="order" id="scope_order">
                                    <label class="form-check-label small" for="scope_order">
                                        <span class="badge bg-success">order</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]" value="renew" id="scope_renew">
                                    <label class="form-check-label small" for="scope_renew">
                                        <span class="badge bg-warning text-dark">renew</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-info btn-sm text-white w-100 fw-bold">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-warning py-2 small mb-4">
                    <i class="bi bi-exclamation-triangle me-1"></i> คุณมี Active Key ครบ <?= MAX_API_KEYS ?> รายการแล้ว กรุณา Revoke Key เก่าก่อนสร้างใหม่
                </div>
                <?php endif; ?>

                <!-- ตารางรายการ Keys -->
                <?php if (empty($apiKeys)): ?>
                    <p class="text-muted text-center py-3 small">ยังไม่มี API Key — สร้างด้านบนเพื่อเริ่มใช้งาน</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Scopes</th>
                                <th>IP Whitelist</th>
                                <th>สร้างเมื่อ</th>
                                <th>ใช้ล่าสุด</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($apiKeys as $k): ?>
                            <tr class="<?= $k['is_active'] ? '' : 'text-muted opacity-50' ?>">
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($k['label'] ?: '—') ?></span>
                                </td>
                                <td>
                                    <?php
                                    $scopeBadges = ['read' => 'primary', 'order' => 'success', 'renew' => 'warning text-dark'];
                                    $scopes = array_filter(array_map('trim', explode(',', $k['scopes'] ?? 'read')));
                                    foreach ($scopes as $sc):
                                        $cls = $scopeBadges[$sc] ?? 'secondary';
                                    ?>
                                        <span class="badge bg-<?= $cls ?> me-1"><?= htmlspecialchars($sc) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="small text-muted" style="max-width:140px;word-break:break-all">
                                    <?php if (!empty($k['ip_whitelist'])): ?>
                                        <?php foreach (explode(',', $k['ip_whitelist']) as $ip): ?>
                                            <code class="d-block"><?= htmlspecialchars(trim($ip)) ?></code>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-success">ทุก IP</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= thaiDate($k['created_at'], false) ?></td>
                                <td class="small"><?= $k['last_used_at'] ? thaiDate($k['last_used_at']) : '<span class="text-muted">ยังไม่เคยใช้</span>' ?></td>
                                <td>
                                    <?php if ($k['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($k['is_active']): ?>
                                    <form method="POST" action="profile.php#api-keys" onsubmit="return confirm('ยืนยันการยกเลิก API Key นี้?')">
                                        <input type="hidden" name="action" value="revoke_api_key">
                                        <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash3"></i> Revoke
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
