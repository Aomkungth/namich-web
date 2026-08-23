<?php
/**
 * Order Hosting Wizard (order_hosting.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$api = new NamiResellerAPI();

$packageId = (int)($_GET['id'] ?? 0);
$selectedPackage = null;

// ดึงรายการแพ็กเกจทั้งหมดเพื่อค้นหาแพ็กเกจที่เลือก (API + Cache Fallback)
$categories = fetchHostingPackages($api);
if (!empty($categories)) {
    foreach ($categories as $cat) {
        foreach ($cat['packages'] as $p) {
            if ($p['id'] == $packageId) {
                $selectedPackage = $p;
                $selectedPackage['category_name'] = $cat['category_name'];
                break 2;
            }
        }
    }
}

// หากไม่พบแพ็กเกจ ให้ redirect กลับไปหน้าแพ็กเกจ
if (!$selectedPackage) {
    setFlash('warning', 'กรุณาเลือกแพ็กเกจโฮสติ้งที่ต้องการสั่งซื้อ');
    header('Location: packages.php');
    exit;
}

$costM = (float)$selectedPackage['price_monthly'];
$costY = (float)($selectedPackage['price_yearly'] ?? ($costM * 10));
$pricing = getPackagePricing('hosting', $selectedPackage['id'], $costM, $costY);

if (!$pricing['is_active']) {
    setFlash('warning', 'แพ็กเกจนี้ปิดให้บริการชั่วคราว');
    header('Location: packages.php');
    exit;
}

$monthlyPrice = $pricing['sell_monthly'];
$yearlyPrice = $pricing['sell_yearly'];
if (!empty($pricing['custom_name'])) {
    $selectedPackage['name'] = $pricing['custom_name'];
}

$errors = [];
$domain = '';
$daUsername = '';
$billingCycle = 'monthly';

// เมื่อส่งฟอร์มสั่งซื้อ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = strtolower(trim($_POST['domain'] ?? ''));
    $daUsername = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $billingCycle = in_array($_POST['billing_cycle'] ?? '', ['monthly', 'yearly']) ? $_POST['billing_cycle'] : 'monthly';

    // ทำความสะอาด Domain (ลบ http://, https://, www. ออก)
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = rtrim($domain, '/');

    // ตรวจสอบความถูกต้องของข้อมูล
    if (empty($domain) || !preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain)) {
        $errors[] = 'ชื่อโดเมนไม่ถูกต้อง กรุณากรอกในรูปแบบ เช่น mysite.com (ไม่ต้องใส่ http:// หรือ www.)';
    }

    if (empty($daUsername) || !preg_match('/^[a-z][a-z0-9]{3,15}$/', $daUsername)) {
        $errors[] = 'ชื่อผู้ใช้ DirectAdmin ต้องเป็นตัวพิมพ์เล็ก a-z และตัวเลข 0-9 ความยาว 4-16 ตัวอักษร และต้องขึ้นต้นด้วยตัวอักษร';
    }

    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่าน DirectAdmin ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    }

    // คำนวณราคาที่ต้องจ่าย
    $totalAmount = ($billingCycle === 'yearly') ? $yearlyPrice : $monthlyPrice;

    // ตรวจสอบยอดเงินในกระเป๋า
    if ($user['credit'] < $totalAmount) {
        $shortage = $totalAmount - $user['credit'];
        $errors[] = 'ยอดเงินในกระเป๋าของคุณไม่เพียงพอ (ต้องการ ' . formatMoney($totalAmount) . ' แต่มี ' . formatMoney($user['credit']) . ') ขาดอีก ' . formatMoney($shortage);
    }

    if (empty($errors)) {
        $orderNo = generateRefNo('ORD-HST-');

        // 1. ตัดเงินจาก Wallet
        $description = "สั่งซื้อโฮสติ้ง {$selectedPackage['name']} ({$billingCycle}) — {$domain}";
        $deducted = deductUserCredit($user['id'], $totalAmount, $description, 'order_hosting');

        if (!$deducted) {
            $errors[] = 'เกิดข้อผิดพลาดในการตัดยอดเงิน กรุณาลองใหม่อีกครั้ง';
        } else {
            // 2. เรียก API order_hosting
            $apiResult = $api->orderHosting(
                $selectedPackage['id'],
                $domain,
                $daUsername,
                $password,
                $billingCycle
            );

            $pdo = getDB();

            if ($apiResult && !empty($apiResult['ok'])) {
                // สั่งซื้อสำเร็จผ่าน API
                $apiData = $apiResult['data'] ?? [];
                $apiOrderId = $apiData['order_id'] ?? null;
                $apiInvoiceId = $apiData['invoice_id'] ?? null;
                
                $ipAddress = $apiData['ip_address'] ?? null;
                $serverName = $apiData['server_name'] ?? null;
                $nameservers = null;
                if (!empty($apiData['nameserver1']) && !empty($apiData['nameserver2'])) {
                    $nameservers = $apiData['nameserver1'] . ',' . $apiData['nameserver2'];
                }

                // วันที่เริ่มต้นและวันครบกำหนดรอบถัดไป
                $startDate = date('Y-m-d');
                $nextDueDate = ($billingCycle === 'yearly') ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));

                // บันทึก Service
                $stmtService = $pdo->prepare("INSERT INTO `services` (
                    `user_id`, `service_type`, `api_service_id`, `api_order_id`, `api_invoice_id`,
                    `domain_or_hostname`, `package_name`, `package_id`, `server_username`,
                    `billing_cycle`, `price`, `status`, `start_date`, `next_due_date`, `ip_address`, `server_name`, `nameservers`, `extra_info`
                ) VALUES (?, 'hosting', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?)");

                $extraInfo = json_encode([
                    'disk_mb' => $selectedPackage['disk_mb'],
                    'bandwidth_mb' => $selectedPackage['bandwidth_mb'],
                    'domains' => $selectedPackage['domains'],
                    'password' => $password,
                    'api_response' => $apiResult
                ], JSON_UNESCAPED_UNICODE);

                $stmtService->execute([
                    $user['id'],
                    $apiData['service_id'] ?? null,
                    $apiOrderId,
                    $apiInvoiceId,
                    $domain,
                    $selectedPackage['name'],
                    $selectedPackage['id'],
                    $daUsername,
                    $billingCycle,
                    $totalAmount,
                    $startDate,
                    $nextDueDate,
                    $ipAddress,
                    $serverName,
                    $nameservers,
                    $extraInfo
                ]);
                $newServiceId = $pdo->lastInsertId();

                // บันทึก Order
                $stmtOrder = $pdo->prepare("INSERT INTO `orders` (
                    `user_id`, `order_no`, `service_type`, `service_id`, `product_id`,
                    `product_name`, `domain_or_hostname`, `billing_cycle`, `amount`, `status`, `api_response`
                ) VALUES (?, ?, 'hosting', ?, ?, ?, ?, ?, ?, 'paid', ?)");
                $stmtOrder->execute([
                    $user['id'],
                    $orderNo,
                    $newServiceId,
                    $selectedPackage['id'],
                    $selectedPackage['name'],
                    $domain,
                    $billingCycle,
                    $totalAmount,
                    json_encode($apiResult, JSON_UNESCAPED_UNICODE)
                ]);

                setFlash('success', 'สั่งซื้อและติดตั้งโฮสติ้งสำเร็จเรียบร้อยแล้ว!');
                header('Location: service_detail.php?id=' . $newServiceId);
                exit;
            } else {
                // หาก API คืน error -> คืนเงินให้ผู้ใช้ทันที
                $apiErrorMsg = $apiResult['error'] ?? 'ระบบ API ไม่ตอบสนอง';
                $httpCode    = $apiResult['http_code'] ?? 0;

                addUserCredit($user['id'], $totalAmount, "คืนเงินคำสั่งซื้อล้มเหลว ({$orderNo}): {$apiErrorMsg}", 'refund');

                // แยกประเภท error เพื่อแจ้ง user อย่างถูกต้อง
                if ($httpCode === 403 || $httpCode === 401) {
                    $errors[] = 'ระบบไม่สามารถเชื่อมต่อ API ได้ในขณะนี้ (' . htmlspecialchars($apiErrorMsg) . ') — กรุณาติดต่อผู้ดูแลระบบ (ระบบได้คืนเงินเข้า Wallet ให้คุณแล้ว)';
                } elseif ($httpCode === 429) {
                    $errors[] = 'ระบบมีคำสั่งซื้อหนาแน่น กรุณารอสักครู่แล้วลองใหม่อีกครั้ง (ระบบได้คืนเงินเข้า Wallet ให้คุณแล้ว)';
                } elseif ($httpCode >= 500) {
                    $errors[] = 'Reseller API มีปัญหาชั่วคราว กรุณาลองใหม่ภายใน 5 นาที (ระบบได้คืนเงินเข้า Wallet ให้คุณแล้ว)';
                } else {
                    $errors[] = 'การสั่งซื้อผ่าน API ขัดข้อง: ' . htmlspecialchars($apiErrorMsg) . ' (ระบบได้คืนเงินเข้า Wallet ให้คุณแล้ว)';
                }
            }
        }
    }
}

$pageTitle = 'สั่งซื้อโฮสติ้ง ' . htmlspecialchars($selectedPackage['name']) . ' - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-modern p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="stat-icon primary">
                        <i class="bi bi-server"></i>
                    </div>
                    <div>
                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($selectedPackage['category_name']) ?></span>
                        <h3 class="fw-bold mb-0">สั่งซื้อแพ็กเกจ <?= htmlspecialchars($selectedPackage['name']) ?></h3>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> พบข้อผิดพลาด:</h6>
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= $err ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="order_hosting.php?id=<?= $selectedPackage['id'] ?>" id="orderForm">
                    <!-- Billing Cycle Selector -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">เลือกรอบการชำระเงิน (Billing Cycle)</label>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100 cycle-card">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="billing_cycle" id="cycle_monthly" value="monthly" <?= ($billingCycle === 'monthly') ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="cycle_monthly">
                                            <span class="fw-bold d-block">รายเดือน (Monthly)</span>
                                            <span class="text-primary fw-bold fs-5"><?= formatMoney($monthlyPrice) ?></span>
                                            <small class="text-muted d-block">ต่ออายุทุก 1 เดือน</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100 cycle-card border-primary">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="billing_cycle" id="cycle_yearly" value="yearly" <?= ($billingCycle === 'yearly') ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="cycle_yearly">
                                            <span class="fw-bold d-block">
                                                รายปี (Yearly) <span class="badge bg-success small">ประหยัดคุ้มค่า</span>
                                            </span>
                                            <span class="text-primary fw-bold fs-5"><?= formatMoney($yearlyPrice) ?></span>
                                            <small class="text-muted d-block">ต่ออายุทุก 1 ปี</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Domain Name -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อโดเมน (Domain Name) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                            <input type="text" name="domain" class="form-control" placeholder="เช่น yourdomain.com (ไม่ต้องใส่ http:// หรือ www.)" value="<?= htmlspecialchars($domain) ?>" required>
                        </div>
                        <div class="form-text text-muted">กรอกชื่อโดเมนที่คุณมีอยู่แล้ว หรือกำลังจะจดทะเบียน</div>
                    </div>

                    <!-- DirectAdmin Username -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผู้ใช้ DirectAdmin (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person-badge"></i></span>
                            <input type="text" name="username" id="da_username" class="form-control" placeholder="เช่น webmaster9" value="<?= htmlspecialchars($daUsername) ?>" maxlength="16" required>
                        </div>
                        <div id="da_username_help" class="form-text text-muted">ตัวพิมพ์เล็ก a-z และตัวเลข 0-9 ความยาว 4-16 ตัว (ต้องขึ้นต้นด้วยตัวอักษร)</div>
                    </div>

                    <!-- DirectAdmin Password -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">รหัสผ่าน DirectAdmin (Password) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                            <input type="password" name="password" id="da_password" class="form-control" placeholder="รหัสผ่านอย่างน้อย 8 ตัวอักษร" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="da_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted">รหัสผ่านนี้ใช้สำหรับเข้าสู่ระบบ DirectAdmin และ FTP</div>
                    </div>

                    <!-- Wallet & Order Summary -->
                    <div class="p-4 rounded-3 bg-light border mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-wallet2 text-primary me-2"></i> สรุปการชำระเงิน</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">แพ็กเกจ:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($selectedPackage['name']) ?> (พื้นที่ <?= ($selectedPackage['disk_mb'] >= 1024) ? ($selectedPackage['disk_mb']/1024).' GB' : $selectedPackage['disk_mb'].' MB' ?>)</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">ยอดเงินในกระเป๋าของคุณ:</span>
                            <span class="fw-bold text-success"><?= formatMoney($user['credit']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5">ยอดชำระสุทธิ:</span>
                            <span class="fw-bold fs-4 text-primary" id="displayTotal">
                                <?= formatMoney(($billingCycle === 'yearly') ? $yearlyPrice : $monthlyPrice) ?>
                            </span>
                        </div>

                        <?php if ($user['credit'] < $monthlyPrice): ?>
                            <div class="alert alert-warning mt-3 mb-0 py-2 small d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-exclamation-triangle-fill me-1"></i> ยอดเงินในกระเป๋าไม่พอชำระ</span>
                                <a href="topup.php" class="btn btn-sm btn-primary rounded-pill">เติมเงินตอนนี้</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary-gradient btn-lg fw-bold" <?= ($user['credit'] < $monthlyPrice) ? 'disabled' : '' ?>>
                            <i class="bi bi-check-circle-fill me-2"></i> ยืนยันการสั่งซื้อและตัดเงิน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const monthlyPrice = <?= (float)$monthlyPrice ?>;
    const yearlyPrice = <?= (float)$yearlyPrice ?>;
    const currency = '<?= SITE_CURRENCY ?>';
    const displayTotal = document.getElementById('displayTotal');

    document.querySelectorAll('input[name="billing_cycle"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.value === 'yearly') {
                displayTotal.textContent = yearlyPrice.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + currency;
            } else {
                displayTotal.textContent = monthlyPrice.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + currency;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
