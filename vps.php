<?php
/**
 * VPS Plans Catalog (vps.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

$pageTitle = 'คลาวด์ VPS เซิร์ฟเวอร์ (Cloud VPS Plans) - ' . getSetting('site_name', SITE_NAME);

$api = new NamiResellerAPI();
$vpsRes = fetchVPSPackages($api);
$plans = ($vpsRes && !empty($vpsRes['plans'])) ? $vpsRes['plans'] : [];
$osOptions = ($vpsRes && !empty($vpsRes['os_options'])) ? $vpsRes['os_options'] : [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <span class="badge bg-info-subtle text-info fw-bold px-3 py-2 rounded-pill mb-2">
            CLOUD VPS HOSTING
        </span>
        <h1 class="fw-bold">คลาวด์ VPS ประสิทธิภาพสูง</h1>
        <p class="text-muted lead">KVM Virtualization แยกทรัพยากรเต็ม 100% สิทธิ์ Root Access รองรับทั้ง Linux และ Windows</p>
    </div>

    <!-- VPS Plans Grid -->
    <?php if (!empty($plans)): ?>
        <div class="row g-4 mb-5">
            <?php foreach ($plans as $plan): 
                $costM = (float)$plan['price_monthly'];
                $costY = (float)($plan['price_yearly'] ?? ($costM * 10));
                $pricing = getPackagePricing('vps', $plan['id'], $costM, $costY);
                if (!$pricing['is_active']) continue;
                $custMonthly = $pricing['sell_monthly'];
                $custYearly = $pricing['sell_yearly'];
                $displayName = $pricing['custom_name'] ?: $plan['name'];
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card-modern p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold mb-0"><?= htmlspecialchars($displayName) ?></h4>
                            <?php if ($pricing['is_featured']): ?>
                                <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-star-fill me-1"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'แนะนำ') ?></span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary fw-bold">Dedicated KVM</span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <div class="price-tag">
                                <?= formatMoney($custMonthly) ?>
                                <span class="price-period">/ เดือน</span>
                            </div>
                            <small class="text-success fw-bold">
                                หรือ <?= formatMoney($custYearly) ?> / ปี
                            </small>
                        </div>

                        <div class="d-flex flex-column gap-2 mb-4 flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                <span class="text-muted"><i class="bi bi-cpu me-2 text-primary"></i>Processor</span>
                                <span class="fw-bold"><?= $plan['vcpu'] ?> vCPU Core</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                <span class="text-muted"><i class="bi bi-memory me-2 text-success"></i>RAM Memory</span>
                                <span class="fw-bold"><?= ($plan['ram_mb'] >= 1024) ? ($plan['ram_mb']/1024) . ' GB' : $plan['ram_mb'] . ' MB' ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                <span class="text-muted"><i class="bi bi-hdd-rack me-2 text-warning"></i>NVMe Storage</span>
                                <span class="fw-bold"><?= $plan['disk_gb'] ?> GB SSD</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                <span class="text-muted"><i class="bi bi-globe me-2 text-info"></i>IPv4 Address</span>
                                <span class="fw-bold">1 Dedicated IPv4</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                <span class="text-muted"><i class="bi bi-shield-lock me-2 text-danger"></i>Control</span>
                                <span class="fw-bold">Root / SSH Access</span>
                            </div>
                        </div>

                        <div class="mt-auto">
                            <a href="order_vps.php?id=<?= $plan['id'] ?>" class="btn btn-primary-gradient w-100 py-2 fw-bold rounded-pill">
                                <i class="bi bi-lightning-charge me-1"></i> ปรับแต่งและสั่งซื้อ VPS
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="display-1 text-muted mb-3"><i class="bi bi-cpu"></i></div>
            <h4>ยังไม่มีข้อมูลแพ็กเกจ VPS หรือไม่สามารถเชื่อมต่อ API ได้</h4>
            <p class="text-muted">กรุณาตรวจสอบการตั้งค่า Reseller API Key ในหน้าแอดมิน</p>
        </div>
    <?php endif; ?>

    <!-- OS Options Info -->
    <?php if (!empty($osOptions)): ?>
    <div class="card-modern p-4 bg-white">
        <h4 class="fw-bold mb-3"><i class="bi bi-terminal-fill text-primary me-2"></i> ระบบปฏิบัติการ (Operating Systems) ที่รองรับ</h4>
        <p class="text-muted small mb-4">คุณสามารถเลือกระบบปฏิบัติการที่ต้องการติดตั้งได้ในขั้นตอนการสั่งซื้อ</p>
        <div class="row g-3">
            <?php foreach ($osOptions as $os): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-2">
                        <i class="bi bi-hdd-fill text-secondary"></i>
                        <span class="fw-semibold small"><?= htmlspecialchars($os['name'] ?? $os['os_name'] ?? 'OS Option') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
