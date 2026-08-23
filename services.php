<?php
/**
 * My Services List (services.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }

$pdo = getDB();
$userId = (int)$user['id'];

$filterType = clean($_GET['type'] ?? 'all');
$filterStatus = clean($_GET['status'] ?? 'all');

$query = "SELECT * FROM `services` WHERE `user_id` = ?";
$params = [$userId];

if ($filterType === 'hosting') {
    $query .= " AND `service_type` = 'hosting'";
} elseif ($filterType === 'vps') {
    $query .= " AND `service_type` = 'vps'";
}

if ($filterStatus === 'active') {
    $query .= " AND `status` = 'active'";
} elseif ($filterStatus === 'suspended') {
    $query .= " AND `status` = 'suspended'";
}

$query .= " ORDER BY `created_at` DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll();

$pageTitle = 'บริการทั้งหมดของฉัน - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-hdd-stack-fill text-primary me-2"></i> บริการทั้งหมดของฉัน</h2>
            <p class="text-muted mb-0">จัดการเว็บโฮสติ้งและคลาวด์ VPS เซิร์ฟเวอร์ของคุณ</p>
        </div>
        <div class="d-flex gap-2">
            <a href="packages.php" class="btn btn-outline-primary rounded-pill">
                <i class="bi bi-plus-lg me-1"></i> สั่งซื้อโฮสติ้งใหม่
            </a>
            <a href="vps.php" class="btn btn-outline-info rounded-pill">
                <i class="bi bi-plus-lg me-1"></i> สั่งซื้อ VPS ใหม่
            </a>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="services.php" class="btn btn-sm <?= ($filterType === 'all') ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-3">
            ทั้งหมด (All)
        </a>
        <a href="services.php?type=hosting" class="btn btn-sm <?= ($filterType === 'hosting') ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            <i class="bi bi-server me-1"></i> เว็บโฮสติ้ง (Hosting)
        </a>
        <a href="services.php?type=vps" class="btn btn-sm <?= ($filterType === 'vps') ? 'btn-info text-white' : 'btn-light border' ?> rounded-pill px-3">
            <i class="bi bi-cpu me-1"></i> คลาวด์ VPS
        </a>
    </div>

    <!-- Services Table/Cards -->
    <div class="card-modern p-4">
        <?php if (!empty($services)): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ประเภท</th>
                            <th>โดเมน / Hostname</th>
                            <th>แพ็กเกจ</th>
                            <th>รอบชำระ / ราคา</th>
                            <th>วันครบกำหนด</th>
                            <th>สถานะ</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $srv): 
                            $isHosting = ($srv['service_type'] === 'hosting');
                            $cycleLabel = ($srv['billing_cycle'] === 'yearly') ? 'รายปี' : 'รายเดือน';
                        ?>
                            <tr>
                                <td>
                                    <?php if ($isHosting): ?>
                                        <span class="badge bg-primary-subtle text-primary fw-bold p-2"><i class="bi bi-server me-1"></i> Hosting</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info fw-bold p-2"><i class="bi bi-cpu me-1"></i> VPS</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($srv['domain_or_hostname']) ?></div>
                                    <small class="text-muted">
                                        <?php if ($isHosting): ?>
                                            User: <?= htmlspecialchars($srv['server_username'] ?: '-') ?>
                                        <?php else: ?>
                                            OS: <?= htmlspecialchars($srv['os_name'] ?: 'Linux') ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($srv['package_name'] ?: 'Custom') ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= formatMoney($srv['price']) ?></div>
                                    <small class="text-muted"><?= $cycleLabel ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= thaiDate($srv['next_due_date'], false) ?></div>
                                    <?php 
                                    $daysLeft = (strtotime($srv['next_due_date']) - time()) / 86400;
                                    if ($daysLeft <= 3 && $daysLeft >= 0): 
                                    ?>
                                        <span class="badge bg-warning text-dark small">ใกล้หมดอายุ</span>
                                    <?php elseif ($daysLeft < 0): ?>
                                        <span class="badge bg-danger small">เลยกำหนด</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= statusBadge($srv['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="service_detail.php?id=<?= $srv['id'] ?>" class="btn btn-sm btn-primary-gradient rounded-pill px-3">
                                        <i class="bi bi-sliders me-1"></i> รายละเอียด / ต่ออายุ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="display-5 text-muted mb-3"><i class="bi bi-folder-x"></i></div>
                <h5>ไม่พบบริการในหมวดหมู่นี้</h5>
                <p class="text-muted mb-4">คุณยังไม่มีบริการโฮสติ้งหรือ VPS ที่ตรงกับเงื่อนไข</p>
                <a href="packages.php" class="btn btn-primary-gradient rounded-pill px-4">
                    <i class="bi bi-cart-plus me-1"></i> เลือกชมแพ็กเกจโฮสติ้ง
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
