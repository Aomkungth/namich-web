<?php
/**
 * Admin All Customer Services (admin/services.php)
 * รายการบริการของลูกค้าทั้งหมดในระบบ และดูบริการสดทั้งหมดจาก Reseller API
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

$pageTitle = 'บริการของลูกค้าทั้งหมด & ข้อมูล API สด';
$pdo = getDB();
$api = new NamiResellerAPI();

$typeFilter = clean($_GET['type'] ?? 'all');
$query = "SELECT s.*, u.username, u.email FROM `services` s JOIN `users` u ON s.user_id = u.id";

if ($typeFilter === 'hosting') {
    $query .= " WHERE s.service_type = 'hosting'";
} elseif ($typeFilter === 'vps') {
    $query .= " WHERE s.service_type = 'vps'";
}
$query .= " ORDER BY s.created_at DESC";

$stmt = $pdo->query($query);
$services = $stmt->fetchAll();

// ดึงข้อมูลบริการสดจาก API เมื่อเลือกแท็บ API
$apiHostingServices = [];
$apiVPSServices = [];
$apiHostingRes = $api->getServices();
if ($apiHostingRes && !empty($apiHostingRes['ok']) && !empty($apiHostingRes['services'])) {
    $apiHostingServices = $apiHostingRes['services'];
}
$apiVPSRes = $api->getVPS();
if ($apiVPSRes && !empty($apiVPSRes['ok']) && !empty($apiVPSRes['vps'])) {
    $apiVPSServices = $apiVPSRes['vps'];
}

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-hdd-stack-fill text-primary me-2"></i> บริการของลูกค้า & ข้อมูลจาก API</h1>
        <p class="text-muted mb-0">ดูรายการบริการที่ลูกค้าสั่งซื้อในเว็บ และตรวจสอบเซิร์ฟเวอร์สดทั้งหมดในบัญชี Reseller API</p>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4 fw-bold" id="servicesTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active px-4" id="db-services-tab" data-bs-toggle="tab" data-bs-target="#db-services-pane" type="button" role="tab">
            <i class="bi bi-database me-2 text-primary"></i> บริการของลูกค้าในระบบเว็บ (<?= count($services) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4" id="api-services-tab" data-bs-toggle="tab" data-bs-target="#api-services-pane" type="button" role="tab">
            <i class="bi bi-cloud-check me-2 text-success"></i> บริการสดในบัญชี Reseller API (<?= count($apiHostingServices) + count($apiVPSServices) ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="servicesTabContent">
    <!-- TAB 1: Database Services -->
    <div class="tab-pane fade show active" id="db-services-pane" role="tabpanel">
        <div class="card-modern p-4">
            <!-- Filters -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="services.php?type=all" class="btn btn-sm <?= ($typeFilter === 'all') ? 'btn-dark' : 'btn-light border' ?> rounded-pill px-3">
                    ทั้งหมด (All)
                </a>
                <a href="services.php?type=hosting" class="btn btn-sm <?= ($typeFilter === 'hosting') ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
                    <i class="bi bi-server me-1"></i> เว็บโฮสติ้ง (Hosting)
                </a>
                <a href="services.php?type=vps" class="btn btn-sm <?= ($typeFilter === 'vps') ? 'btn-info text-white' : 'btn-light border' ?> rounded-pill px-3">
                    <i class="bi bi-cpu me-1"></i> คลาวด์ VPS
                </a>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>เจ้าของบริการ</th>
                            <th>ประเภท</th>
                            <th>โดเมน / Hostname</th>
                            <th>แพ็กเกจ</th>
                            <th>รอบบิล / ราคา</th>
                            <th>วันเริ่ม / ครบกำหนด</th>
                            <th>สถานะ</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $srv): 
                                $isHosting = ($srv['service_type'] === 'hosting');
                            ?>
                                <tr>
                                    <td class="text-muted">#<?= $srv['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($srv['username']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($srv['email']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($isHosting): ?>
                                            <span class="badge bg-primary-subtle text-primary"><i class="bi bi-server me-1"></i> Hosting</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-subtle text-info"><i class="bi bi-cpu me-1"></i> VPS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($srv['domain_or_hostname']) ?></div>
                                        <small class="text-muted"><?= $isHosting ? ('DA: ' . htmlspecialchars($srv['server_username'])) : ('OS: ' . htmlspecialchars($srv['os_name'])) ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($srv['package_name'] ?: 'Custom') ?></span></td>
                                    <td>
                                        <div class="fw-semibold"><?= formatMoney($srv['price']) ?></div>
                                        <small class="text-muted"><?= ($srv['billing_cycle'] === 'yearly') ? 'รายปี' : 'รายเดือน' ?></small>
                                    </td>
                                    <td class="small">
                                        <div>เริ่ม: <?= thaiDate($srv['start_date'], false) ?></div>
                                        <div class="text-danger">หมดอายุ: <?= thaiDate($srv['next_due_date'], false) ?></div>
                                    </td>
                                    <td><?= statusBadge($srv['status']) ?></td>
                                    <td class="text-end">
                                        <a href="../service_detail.php?id=<?= $srv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="bi bi-eye me-1"></i> ดูรายละเอียด
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">ยังไม่มีรายการสั่งซื้อบริการจากลูกค้า</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Live Reseller API Services -->
    <div class="tab-pane fade" id="api-services-pane" role="tabpanel">
        <div class="card-modern p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-server text-primary me-2"></i> รายการ DirectAdmin Hosting สดใน API (<?= count($apiHostingServices) ?>)</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>API Service ID</th>
                            <th>โดเมน (Domain)</th>
                            <th>DA Username</th>
                            <th>แพ็กเกจ API</th>
                            <th>IP เซิร์ฟเวอร์</th>
                            <th>รอบบิล</th>
                            <th>วันหมดอายุ</th>
                            <th>สถานะ API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($apiHostingServices)): ?>
                            <?php foreach ($apiHostingServices as $as): ?>
                                <tr>
                                    <td class="fw-bold font-monospace">#<?= $as['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($as['domain']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($as['username']) ?></span></td>
                                    <td><?= htmlspecialchars($as['package'] ?? '-') ?></td>
                                    <td class="small font-monospace"><?= htmlspecialchars($as['ip_address'] ?? '-') ?></td>
                                    <td><?= ($as['billing_cycle'] === 'yearly') ? 'รายปี' : 'รายเดือน' ?></td>
                                    <td class="small text-danger"><?= htmlspecialchars($as['next_due_date'] ?? '-') ?></td>
                                    <td><?= statusBadge($as['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูล DirectAdmin Hosting ในบัญชี API</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-modern p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-cpu text-info me-2"></i> รายการ Cloud VPS สดใน API (<?= count($apiVPSServices) ?>)</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>API VPS ID</th>
                            <th>Hostname</th>
                            <th>แพ็กเกจ VPS</th>
                            <th>OS Template</th>
                            <th>IP เซิร์ฟเวอร์</th>
                            <th>วันหมดอายุ</th>
                            <th>สถานะ API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($apiVPSServices)): ?>
                            <?php foreach ($apiVPSServices as $av): ?>
                                <tr>
                                    <td class="fw-bold font-monospace">#<?= $av['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($av['hostname'] ?? ('vps-' . $av['id'])) ?></td>
                                    <td><?= htmlspecialchars($av['plan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($av['os_name'] ?? '-') ?></td>
                                    <td class="small font-monospace text-primary fw-bold"><?= htmlspecialchars($av['ip_address'] ?? '-') ?></td>
                                    <td class="small text-danger"><?= htmlspecialchars($av['next_due_date'] ?? '-') ?></td>
                                    <td><?= statusBadge($av['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูล Cloud VPS ในบัญชี API</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
