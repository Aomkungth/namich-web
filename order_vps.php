<?php
/**
 * Order VPS Wizard (order_vps.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$api = new NamiResellerAPI();

$planId = (int)($_GET['id'] ?? 0);
$selectedPlan = null;
$osOptions = [];

$vpsRes = fetchVPSPackages($api);
if ($vpsRes) {
    $plans = $vpsRes['plans'] ?? [];
    $osOptions = $vpsRes['os_options'] ?? [];

    foreach ($plans as $p) {
        if ($p['id'] == $planId) {
            $selectedPlan = $p;
            break;
        }
    }
    // ถ้าไม่ได้ระบุ id มา ให้เลือกแผนแรกเป็น default
    if (!$selectedPlan && !empty($plans)) {
        $selectedPlan = $plans[0];
    }
}

if (!$selectedPlan) {
    setFlash('warning', 'กรุณาเลือกแพ็กเกจ VPS ที่ต้องการสั่งซื้อ');
    header('Location: vps.php');
    exit;
}

$costM = (float)$selectedPlan['price_monthly'];
$costY = (float)($selectedPlan['price_yearly'] ?? ($costM * 10));
$pricing = getPackagePricing('vps', $selectedPlan['id'], $costM, $costY);

if (!$pricing['is_active']) {
    setFlash('warning', 'แพ็กเกจนี้ปิดให้บริการชั่วคราว');
    header('Location: vps.php');
    exit;
}

$monthlyPrice = $pricing['sell_monthly'];
$yearlyPrice = $pricing['sell_yearly'];
if (!empty($pricing['custom_name'])) {
    $selectedPlan['name'] = $pricing['custom_name'];
}

$errors = [];
$hostname = '';
$selectedOsId = 0;
$billingCycle = 'monthly';

// เมื่อส่งฟอร์มสั่งซื้อ VPS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedOsId = (int)($_POST['os_id'] ?? 0);
    $hostname = trim($_POST['hostname'] ?? '');
    $billingCycle = in_array($_POST['billing_cycle'] ?? '', ['monthly', 'yearly']) ? $_POST['billing_cycle'] : 'monthly';

    if ($selectedOsId <= 0) {
        $errors[] = 'กรุณาเลือกระบบปฏิบัติการ (OS) ที่ต้องการติดตั้ง';
    }

    $totalAmount = ($billingCycle === 'yearly') ? $yearlyPrice : $monthlyPrice;

    if ($user['credit'] < $totalAmount) {
        $shortage = $totalAmount - $user['credit'];
        $errors[] = 'ยอดเงินในกระเป๋าของคุณไม่เพียงพอ (ต้องการ ' . formatMoney($totalAmount) . ' แต่มี ' . formatMoney($user['credit']) . ') ขาดอีก ' . formatMoney($shortage);
    }

    if (empty($errors)) {
        $orderNo = generateRefNo('ORD-VPS-');

        // ค้นหาชื่อ OS
        $selectedOsName = 'Linux/Windows OS';
        foreach ($osOptions as $os) {
            if ($os['id'] == $selectedOsId) {
                $selectedOsName = $os['name'] ?? $os['os_name'] ?? 'OS Option';
                break;
            }
        }

        // 1. ตัดเงินจาก Wallet
        $description = "สั่งซื้อ VPS {$selectedPlan['name']} ({$billingCycle}) — {$selectedOsName}";
        $deducted = deductUserCredit($user['id'], $totalAmount, $description, 'order_vps');

        if (!$deducted) {
            $errors[] = 'เกิดข้อผิดพลาดในการตัดยอดเงิน กรุณาลองใหม่อีกครั้ง';
        } else {
            // 2. เรียก API order_vps
            $apiResult = $api->orderVPS(
                $selectedPlan['id'],
                $selectedOsId,
                $billingCycle,
                $hostname ?: null
            );

            $pdo = getDB();

            if ($apiResult && !empty($apiResult['ok'])) {
                $apiData = $apiResult['data'] ?? [];
                $apiOrderId = $apiData['order_id'] ?? null;
                $apiInvoiceId = $apiData['invoice_id'] ?? null;

                $ipAddress = $apiData['ip_address'] ?? null;
                $serverName = $apiData['server_name'] ?? null;
                $password = $apiData['password'] ?? $apiData['root_password'] ?? null;

                $startDate = date('Y-m-d');
                $nextDueDate = ($billingCycle === 'yearly') ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));

                // บันทึก Service
                $stmtService = $pdo->prepare("INSERT INTO `services` (
                    `user_id`, `service_type`, `api_service_id`, `api_order_id`, `api_invoice_id`,
                    `domain_or_hostname`, `package_name`, `package_id`, `os_name`,
                    `billing_cycle`, `price`, `status`, `start_date`, `next_due_date`, `ip_address`, `server_name`, `extra_info`
                ) VALUES (?, 'vps', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)");

                $extraInfo = json_encode([
                    'vcpu' => $selectedPlan['vcpu'],
                    'ram_mb' => $selectedPlan['ram_mb'],
                    'disk_gb' => $selectedPlan['disk_gb'],
                    'password' => $password,
                    'api_response' => $apiResult
                ], JSON_UNESCAPED_UNICODE);

                $finalHostname = !empty($hostname) ? $hostname : ('vps-' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6)) . '.server');

                $stmtService->execute([
                    $user['id'],
                    $apiData['service_id'] ?? null,
                    $apiOrderId,
                    $apiInvoiceId,
                    $finalHostname,
                    $selectedPlan['name'],
                    $selectedPlan['id'],
                    $selectedOsName,
                    $billingCycle,
                    $totalAmount,
                    $startDate,
                    $nextDueDate,
                    $ipAddress,
                    $serverName,
                    $extraInfo
                ]);
                $newServiceId = $pdo->lastInsertId();

                // บันทึก Order
                $stmtOrder = $pdo->prepare("INSERT INTO `orders` (
                    `user_id`, `order_no`, `service_type`, `service_id`, `product_id`,
                    `product_name`, `domain_or_hostname`, `billing_cycle`, `amount`, `status`, `api_response`
                ) VALUES (?, ?, 'vps', ?, ?, ?, ?, ?, ?, 'paid', ?)");
                $stmtOrder->execute([
                    $user['id'],
                    $orderNo,
                    $newServiceId,
                    $selectedPlan['id'],
                    $selectedPlan['name'],
                    $finalHostname,
                    $billingCycle,
                    $totalAmount,
                    json_encode($apiResult, JSON_UNESCAPED_UNICODE)
                ]);

                setFlash('success', 'สั่งซื้อและสร้างคลาวด์ VPS สำเร็จเรียบร้อยแล้ว!');
                header('Location: service_detail.php?id=' . $newServiceId);
                exit;
            } else {
                $apiErrorMsg = $apiResult['error'] ?? 'ระบบ API ไม่ตอบสนอง';
                $httpCode    = $apiResult['http_code'] ?? 0;
                addUserCredit($user['id'], $totalAmount, "คืนเงินสั่งซื้อ VPS ล้มเหลว ({$orderNo}): {$apiErrorMsg}", 'refund');

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

$pageTitle = 'สั่งซื้อ Cloud VPS ' . htmlspecialchars($selectedPlan['name']) . ' - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-modern p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="stat-icon purple">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <div>
                        <span class="badge bg-info-subtle text-info fw-bold">KVM CLOUD VPS</span>
                        <h3 class="fw-bold mb-0">สั่งซื้อ <?= htmlspecialchars($selectedPlan['name']) ?></h3>
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

                <form method="POST" action="order_vps.php?id=<?= $selectedPlan['id'] ?>" id="vpsOrderForm">
                    <!-- Specs Banner -->
                    <div class="p-3 bg-light rounded-3 mb-4 border d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <div class="d-flex gap-2 align-items-center">
                            <span class="spec-chip"><i class="bi bi-cpu text-primary"></i> <?= $selectedPlan['vcpu'] ?> vCPU</span>
                            <span class="spec-chip"><i class="bi bi-memory text-success"></i> <?= ($selectedPlan['ram_mb'] >= 1024) ? ($selectedPlan['ram_mb']/1024).' GB' : $selectedPlan['ram_mb'].' MB' ?> RAM</span>
                            <span class="spec-chip"><i class="bi bi-hdd-rack text-warning"></i> <?= $selectedPlan['disk_gb'] ?> GB SSD</span>
                        </div>
                        <a href="vps.php" class="small text-decoration-none">เปลี่ยนแพ็กเกจ</a>
                    </div>

                    <!-- Billing Cycle -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">รอบการชำระเงิน (Billing Cycle)</label>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="billing_cycle" id="cycle_monthly" value="monthly" <?= ($billingCycle === 'monthly') ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="cycle_monthly">
                                            <span class="fw-bold d-block">รายเดือน (Monthly)</span>
                                            <span class="text-primary fw-bold fs-5"><?= formatMoney($monthlyPrice) ?></span>
                                            <small class="text-muted d-block">ต่ออายุทุกเดือน</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100 border-primary">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="billing_cycle" id="cycle_yearly" value="yearly" <?= ($billingCycle === 'yearly') ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="cycle_yearly">
                                            <span class="fw-bold d-block">
                                                รายปี (Yearly) <span class="badge bg-success small">ประหยัดคุ้มค่า</span>
                                            </span>
                                            <span class="text-primary fw-bold fs-5"><?= formatMoney($yearlyPrice) ?></span>
                                            <small class="text-muted d-block">ต่ออายุรายปี</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OS Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">เลือกระบบปฏิบัติการ (Operating System) <span class="text-danger">*</span></label>
                        <select name="os_id" class="form-select form-select-lg" required>
                            <option value="">-- กรุณาเลือก OS Template --</option>
                            <?php if (!empty($osOptions)): ?>
                                <?php foreach ($osOptions as $os): ?>
                                    <option value="<?= $os['id'] ?>" <?= ($selectedOsId == $os['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($os['name'] ?? $os['os_name'] ?? 'OS Option #' . $os['id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="1">Ubuntu 22.04 LTS (64-bit)</option>
                                <option value="2">Ubuntu 24.04 LTS (64-bit)</option>
                                <option value="3">Debian 12 Bookworm</option>
                                <option value="4">AlmaLinux 9</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">ระบบจะติดตั้ง OS Template อัตโนมัติและส่งข้อมูล Root Access ให้คุณ</div>
                    </div>

                    <!-- Hostname -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">ชื่อ Hostname (ไม่บังคับ)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-hdd-network"></i></span>
                            <input type="text" name="hostname" class="form-control" placeholder="เช่น vps01.myserver.com (เว้นว่างเพื่อให้ระบบสร้างให้)" value="<?= htmlspecialchars($hostname) ?>">
                        </div>
                    </div>

                    <!-- Wallet & Summary -->
                    <div class="p-4 rounded-3 bg-light border mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-wallet2 text-primary me-2"></i> สรุปการชำระเงิน</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">VPS Plan:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($selectedPlan['name']) ?> (<?= $selectedPlan['vcpu'] ?> Core, <?= $selectedPlan['ram_mb'] ?> MB RAM)</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">ยอดเงินในกระเป๋าของคุณ:</span>
                            <span class="fw-bold text-success"><?= formatMoney($user['credit']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5">ยอดชำระสุทธิ:</span>
                            <span class="fw-bold fs-4 text-primary" id="displayVpsTotal">
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
                            <i class="bi bi-rocket-takeoff-fill me-2"></i> ยืนยันการสั่งซื้อและเริ่มสร้าง VPS
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
    const displayTotal = document.getElementById('displayVpsTotal');

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
