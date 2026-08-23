<?php
/**
 * Member Dashboard (dashboard.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$user = getLoggedInUser();

// Guard: ถ้าไม่เจอ user ใน DB ให้ logout แล้ว redirect
if (!$user || !is_array($user)) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$userId = (int)$user['id'];

// ดึงสถิติต่างๆ
$stmtHosting = $pdo->prepare("SELECT COUNT(*) FROM `services` WHERE `user_id` = ? AND `service_type` = 'hosting' AND `status` = 'active'");
$stmtHosting->execute([$userId]);
$countHosting = (int)$stmtHosting->fetchColumn();

$stmtVPS = $pdo->prepare("SELECT COUNT(*) FROM `services` WHERE `user_id` = ? AND `service_type` = 'vps' AND `status` = 'active'");
$stmtVPS->execute([$userId]);
$countVPS = (int)$stmtVPS->fetchColumn();

// ดึงบริการล่าสุด 5 รายการ
$stmtServices = $pdo->prepare("SELECT * FROM `services` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT 5");
$stmtServices->execute([$userId]);
$recentServices = $stmtServices->fetchAll();

// ดึงประวัติธุรกรรมล่าสุด 5 รายการ
$stmtTx = $pdo->prepare("SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT 5");
$stmtTx->execute([$userId]);
$recentTx = $stmtTx->fetchAll();

$pageTitle = 'แผงควบคุมสมาชิก - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <!-- Welcome Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">สวัสดีคุณ <?= htmlspecialchars($user['fullname'] ?: $user['username']) ?> 👋</h2>
            <p class="text-muted mb-0">ยินดีต้อนรับสู่แผงควบคุมโฮสติ้งและคลาวด์เซิร์ฟเวอร์ของคุณ</p>
        </div>
        <div class="d-flex gap-2">
            <a href="packages.php" class="btn btn-outline-primary rounded-pill fw-semibold">
                <i class="bi bi-server me-1"></i> สั่งซื้อโฮสติ้ง
            </a>
            <a href="vps.php" class="btn btn-outline-info rounded-pill fw-semibold">
                <i class="bi bi-cpu me-1"></i> สั่งซื้อ VPS
            </a>
            <a href="topup.php" class="btn btn-primary-gradient rounded-pill fw-semibold">
                <i class="bi bi-wallet2 me-1"></i> เติมเงิน
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="flex-grow-1">
                    <span class="text-muted small">ยอดเงินคงเหลือ (Wallet)</span>
                    <h3 class="fw-bold text-dark mb-0"><?= formatMoney($user['credit']) ?></h3>
                </div>
                <a href="topup.php" class="btn btn-sm btn-outline-success rounded-pill px-3">เติมเงิน</a>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-server"></i>
                </div>
                <div class="flex-grow-1">
                    <span class="text-muted small">โฮสติ้งที่ใช้งานอยู่</span>
                    <h3 class="fw-bold text-dark mb-0"><?= $countHosting ?> <span class="fs-6 fw-normal text-muted">บริการ</span></h3>
                </div>
                <a href="services.php?type=hosting" class="btn btn-sm btn-outline-primary rounded-pill px-3">ดูรายการ</a>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-cpu"></i>
                </div>
                <div class="flex-grow-1">
                    <span class="text-muted small">VPS ที่ใช้งานอยู่</span>
                    <h3 class="fw-bold text-dark mb-0"><?= $countVPS ?> <span class="fs-6 fw-normal text-muted">เครื่อง</span></h3>
                </div>
                <a href="services.php?type=vps" class="btn btn-sm btn-outline-secondary rounded-pill px-3">ดูรายการ</a>
            </div>
        </div>
    </div>

    <!-- Recent Services -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-layers-fill text-primary me-2"></i> บริการล่าสุด</h5>
                    <a href="services.php" class="btn btn-sm btn-link text-decoration-none fw-semibold">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
                </div>

                <?php if (!empty($recentServices)): ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>ประเภท</th>
                                    <th>โดเมน / Hostname</th>
                                    <th>แพ็กเกจ</th>
                                    <th>วันหมดอายุ</th>
                                    <th>สถานะ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentServices as $srv): ?>
                                    <tr>
                                        <td>
                                            <?php if ($srv['service_type'] === 'hosting'): ?>
                                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-server me-1"></i> Hosting</span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info"><i class="bi bi-cpu me-1"></i> VPS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars($srv['domain_or_hostname']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($srv['package_name'] ?: 'Standard') ?></span></td>
                                        <td class="small"><?= thaiDate($srv['next_due_date'], false) ?></td>
                                        <td><?= statusBadge($srv['status']) ?></td>
                                        <td>
                                            <a href="service_detail.php?id=<?= $srv['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                จัดการ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="display-6 text-muted mb-3"><i class="bi bi-hdd-network"></i></div>
                        <p class="text-muted mb-3">คุณยังไม่มีบริการโฮสติ้งหรือ VPS</p>
                        <a href="packages.php" class="btn btn-primary-gradient btn-sm px-4 rounded-pill">
                            <i class="bi bi-cart-plus me-1"></i> สั่งซื้อบริการแรกของคุณ
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-lg-4">
            <div class="card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-receipt text-success me-2"></i> รายการล่าสุด</h5>
                    <a href="transactions.php" class="btn btn-sm btn-link text-decoration-none fw-semibold">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
                </div>

                <?php if (!empty($recentTx)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentTx as $tx): 
                            $isCreditIn = in_array($tx['type'], ['topup', 'admin_adjust', 'refund']);
                        ?>
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($tx['description']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= thaiDate($tx['created_at']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold <?= $isCreditIn ? 'text-success' : 'text-danger' ?>">
                                        <?= $isCreditIn ? '+' : '-' ?><?= formatMoney($tx['amount']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small">
                        ยังไม่มีประวัติการทำรายการ
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
