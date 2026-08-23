<?php
/**
 * Admin Dashboard Overview (admin/index.php)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

$pageTitle = 'แผงควบคุมระบบ (Admin Overview)';
require_once __DIR__ . '/header.php';

$pdo = getDB();

// 1. สถิติผู้ใช้งาน
$totalUsers = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$totalCredits = $pdo->query("SELECT SUM(credit) FROM `users`")->fetchColumn() ?: 0;

// 2. สถิติบอร์ดบริการ
$activeHosting = $pdo->query("SELECT COUNT(*) FROM `services` WHERE `service_type` = 'hosting' AND `status` = 'active'")->fetchColumn();
$activeVPS = $pdo->query("SELECT COUNT(*) FROM `services` WHERE `service_type` = 'vps' AND `status` = 'active'")->fetchColumn();

// 3. สถิติสลิปรอตรวจสอบ
$pendingTopups = $pdo->query("SELECT COUNT(*) FROM `topups` WHERE `status` = 'pending'")->fetchColumn();

// 4. คำสั่งซื้อล่าสุด 10 รายการ
$recentOrders = $pdo->query("
    SELECT o.*, u.username, u.email 
    FROM `orders` o 
    JOIN `users` u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1">แผงควบคุมระบบ (Admin Dashboard)</h1>
        <p class="text-muted mb-0">ภาพรวมการใช้งานเว็บไซต์ การเชื่อมต่อ Reseller API และคำสั่งซื้อล่าสุด</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <a href="settings.php" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-gear me-1"></i> ตั้งค่า API
        </a>
        <a href="topups.php" class="btn btn-primary-gradient btn-sm rounded-pill">
            <i class="bi bi-qr-code me-1"></i> สลิปรอตรวจ (<?= $pendingTopups ?>)
        </a>
    </div>
</div>

<!-- Alert If Pending Topups -->
<?php if ($pendingTopups > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center shadow-sm mb-4">
        <div>
            <i class="bi bi-bell-fill me-2"></i>
            มีรายการแจ้งเติมเงินที่รอการตรวจสอบและอนุมัติจำนวน <strong><?= $pendingTopups ?></strong> รายการ
        </div>
        <a href="topups.php" class="btn btn-warning btn-sm fw-bold">ไปที่หน้าตรวจสอบสลิป &raquo;</a>
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row g-4 mb-5">
    <!-- API Balance Card -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card border-info">
            <div class="stat-icon primary">
                <i class="bi bi-cloud-check-fill"></i>
            </div>
            <div>
                <span class="text-muted small">ยอดเครดิต Reseller API</span>
                <h4 class="fw-bold mb-0 text-primary">
                    <?= ($apiCredit !== null) ? formatMoney($apiCredit) : '<span class="text-danger fs-6">API Error</span>' ?>
                </h4>
                <small class="text-muted">
                    <?= !empty($apiBalRes['data']['name']) ? htmlspecialchars($apiBalRes['data']['name']) : 'Nami Reseller' ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Customer Balances Card -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <span class="text-muted small">ยอดเงินสมาชิกรวมในระบบ</span>
                <h4 class="fw-bold mb-0 text-success"><?= formatMoney($totalCredits) ?></h4>
                <small class="text-muted"><?= $totalUsers ?> สมาชิกทั้งหมด</small>
            </div>
        </div>
    </div>

    <!-- Active Hosting Card -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-server"></i>
            </div>
            <div>
                <span class="text-muted small">เว็บโฮสติ้งที่ Active</span>
                <h4 class="fw-bold mb-0 text-dark"><?= $activeHosting ?> <span class="fs-6 fw-normal text-muted">บริการ</span></h4>
                <small class="text-muted">DirectAdmin Accounts</small>
            </div>
        </div>
    </div>

    <!-- Active VPS Card -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="bi bi-cpu"></i>
            </div>
            <div>
                <span class="text-muted small">VPS ที่กำลังรันอยู่</span>
                <h4 class="fw-bold mb-0 text-dark"><?= $activeVPS ?> <span class="fs-6 fw-normal text-muted">เครื่อง</span></h4>
                <small class="text-muted">Cloud Instances</small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="card-modern p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0"><i class="bi bi-cart-check-fill text-primary me-2"></i> คำสั่งซื้อล่าสุด</h5>
        <a href="services.php" class="btn btn-sm btn-link text-decoration-none">ดูบริการทั้งหมด</a>
    </div>

    <?php if (!empty($recentOrders)): ?>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>รหัสคำสั่งซื้อ</th>
                        <th>ผู้ใช้งาน</th>
                        <th>ประเภท</th>
                        <th>บริการ / โดเมน</th>
                        <th>รอบชำระ</th>
                        <th>ยอดชำระ</th>
                        <th>วันที่สั่งซื้อ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $ord): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($ord['order_no']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($ord['username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($ord['email']) ?></small>
                            </td>
                            <td>
                                <?php if ($ord['service_type'] === 'hosting'): ?>
                                    <span class="badge bg-primary-subtle text-primary">Hosting</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info">VPS</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($ord['domain_or_hostname']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($ord['product_name']) ?></small>
                            </td>
                            <td class="small"><?= ($ord['billing_cycle'] === 'yearly') ? 'รายปี' : 'รายเดือน' ?></td>
                            <td class="fw-bold text-primary"><?= formatMoney($ord['amount']) ?></td>
                            <td class="small"><?= thaiDate($ord['created_at']) ?></td>
                            <td><?= statusBadge($ord['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-4 mb-0">ยังไม่มีคำสั่งซื้อในระบบ</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
