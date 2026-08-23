<?php
/**
 * Homepage (index.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

$pageTitle = getSetting('site_name', SITE_NAME) . ' - เว็บโฮสติ้งและคลาวด์ VPS คุณภาพสูง';
require_once __DIR__ . '/includes/header.php';

$api = new NamiResellerAPI();
$categorySettings = getAllCategorySettings();

// ดึงข้อมูลแพ็กเกจ Hosting & VPS (API + Cache Fallback)
$packages = fetchHostingPackages($api);
$vpsData = fetchVPSPackages($api);
$vpsPlans = !empty($vpsData['plans']) ? array_slice($vpsData['plans'], 0, 3) : [];
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center text-lg-start position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="hero-badge">
                    <i class="bi bi-stars me-1 text-warning"></i> Next-Gen Cloud Platform
                </span>
                <h1 class="hero-title mb-3">
                    บริการโฮสติ้งและ VPS <br>
                    <span class="text-info">เร็ว แรง มั่นใจได้ 24 ชั่วโมง</span>
                </h1>
                <p class="lead text-light opacity-75 mb-4" style="font-size: 1.15rem;">
                    ควบคุมด้วย DirectAdmin ลิขสิทธิ์แท้ 100% สตอเรจ Enterprise NVMe SSD ระบบสั่งซื้อและติดตั้งอัตโนมัติผ่าน API พร้อมใช้งานทันทีใน 1 นาที
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <a href="packages.php" class="btn btn-primary-gradient btn-lg px-4 rounded-pill">
                        <i class="bi bi-server me-2"></i> ดูแพ็กเกจโฮสติ้ง
                    </a>
                    <a href="vps.php" class="btn btn-outline-light btn-lg px-4 rounded-pill">
                        <i class="bi bi-cpu me-2"></i> คลาวด์ VPS
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center">
                <div class="p-4 rounded-4 bg-dark bg-opacity-50 border border-secondary shadow-lg">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success rounded-pill px-2 py-1">● Online</span>
                            <span class="small text-white-50">DirectAdmin Cluster</span>
                        </div>
                        <span class="text-info fw-bold small">Uptime 99.9%</span>
                    </div>
                    <div class="p-3 bg-dark rounded-3 border border-dark mb-3 text-start">
                        <div class="text-white-50 small mb-2">Server Features:</div>
                        <div class="text-white small mb-1"><i class="bi bi-check2 text-success me-2"></i>NVMe SSD Storage Array</div>
                        <div class="text-white small mb-1"><i class="bi bi-check2 text-success me-2"></i>Free SSL Let's Encrypt</div>
                        <div class="text-white small mb-1"><i class="bi bi-check2 text-success me-2"></i>PHP 7.4 - 8.3 Switcher</div>
                        <div class="text-white small mb-1"><i class="bi bi-check2 text-success me-2"></i>MariaDB & phpMyAdmin</div>
                        <div class="text-white small"><i class="bi bi-check2 text-success me-2"></i>Automated Daily Backup</div>
                    </div>
                    <div class="d-grid">
                        <a href="packages.php" class="btn btn-sm btn-info fw-bold py-2">
                            เริ่มต้นใช้งานเพียง <?= formatMoney(calculateCustomerPrice(99)) ?> / เดือน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Highlights Section -->
<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">ติดตั้งทันที</h6>
                        <small class="text-muted">ระบบเปิดบริการอัตโนมัติผ่าน API</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">ฟรี SSL Certificate</h6>
                        <small class="text-muted">ติดตั้งง่าย ปลอดภัยระดับสากล</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-speedometer"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">NVMe Storage</h6>
                        <small class="text-muted">อ่านเขียนไวกว่า SSD ทั่วไป 5 เท่า</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-headset"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">บริการช่วยเหลือ</h6>
                        <small class="text-muted">พร้อมให้คำแนะนำและดูแล 24 ชม.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Hosting Packages Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-2">
                WEB HOSTING PACKAGES
            </span>
            <h2 class="fw-bold">แพ็กเกจเว็บโฮสติ้งยอดนิยม</h2>
            <p class="text-muted">เลือกแพ็กเกจที่เหมาะกับเว็บไซต์ของคุณ เริ่มต้นได้ง่ายๆ พร้อมอัปเกรดได้ตลอดเวลา</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php 
            $count = 0;
            if (!empty($packages)):
                foreach ($packages as $cat):
                    $catId = (int)($cat['category_id'] ?? 0);
                    $catSetting = $categorySettings[$catId] ?? null;
                    if ($catSetting && isset($catSetting['is_active']) && $catSetting['is_active'] == 0) {
                        continue;
                    }
                    $catName = !empty($catSetting['custom_name']) ? $catSetting['custom_name'] : $cat['category_name'];

                    foreach ($cat['packages'] as $pkg):
                        $costM = (float)$pkg['price_monthly'];
                        $costY = (float)($pkg['price_yearly'] ?? ($costM * 10));
                        $pricing = getPackagePricing('hosting', $pkg['id'], $costM, $costY);
                        if (!$pricing['is_active']) continue;
                        if ($count >= 3) break 2;
                        $count++;
                        $custMonthly = $pricing['sell_monthly'];
                        $custYearly = $pricing['sell_yearly'];
                        $isFeatured = $pricing['is_featured'] || !empty($pkg['is_featured']);
                        $displayName = $pricing['custom_name'] ?: $pkg['name'];
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
                        <?php if ($isFeatured): ?>
                            <span class="pricing-badge"><i class="bi bi-fire me-1"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'แนะนำ') ?></span>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <span class="badge bg-light text-secondary border mb-2"><?= htmlspecialchars($catName) ?></span>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($displayName) ?></h4>
                        </div>

                        <div class="mb-4">
                            <div class="price-tag">
                                <?= formatMoney($custMonthly) ?>
                                <span class="price-period">/ เดือน</span>
                            </div>
                            <small class="text-success fw-semibold">
                                หรือ <?= formatMoney($custYearly) ?> / ปี
                            </small>
                        </div>

                        <ul class="feature-list">
                            <li>
                                <i class="bi bi-hdd-fill text-success"></i>
                                <span>พื้นที่: <?= formatHostingSpec('disk_mb', $pkg['disk_mb']) ?></span>
                            </li>
                            <li>
                                <i class="bi bi-arrow-left-right text-primary"></i>
                                <span>Bandwidth: <?= formatHostingSpec('bandwidth_mb', $pkg['bandwidth_mb']) ?></span>
                            </li>
                            <li>
                                <i class="bi bi-globe text-info"></i>
                                <span>จำนวนโดเมน: <?= formatHostingSpec('domains', $pkg['domains']) ?></span>
                            </li>
                            <li>
                                <i class="bi bi-database text-warning"></i>
                                <span>MySQL Database: <?= formatHostingSpec('databases', $pkg['databases']) ?></span>
                            </li>
                            <li>
                                <i class="bi bi-envelope text-danger"></i>
                                <span>Email Account: <?= formatHostingSpec('emails', $pkg['emails']) ?></span>
                            </li>
                            <li>
                                <i class="bi bi-shield-check text-success"></i>
                                <span>ฟรี SSL Certificate & DirectAdmin</span>
                            </li>
                            <?php
                            if (!empty($pricing['custom_features'])) {
                                $cfList = array_filter(array_map('trim', explode("\n", $pricing['custom_features'])));
                                foreach ($cfList as $cfItem):
                            ?>
                                <li>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <span><?= htmlspecialchars($cfItem) ?></span>
                                </li>
                            <?php 
                                endforeach;
                            } 
                            ?>
                        </ul>

                        <a href="order_hosting.php?id=<?= $pkg['id'] ?>" class="btn <?= $isFeatured ? 'btn-primary-gradient' : 'btn-outline-primary' ?> w-100 py-2 fw-bold rounded-pill">
                            <i class="bi bi-cart-plus me-1"></i> สั่งซื้อแพ็กเกจนี้
                        </a>
                    </div>
                </div>
            <?php 
                    endforeach;
                endforeach;
            else: 
            ?>
                <!-- Fallback card if API is offline -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card featured">
                        <span class="pricing-badge">ยอดนิยม</span>
                        <div class="mb-3">
                            <h4 class="fw-bold mb-1">Starter Cloud</h4>
                            <p class="text-muted small">เหมาะสำหรับเว็บไซต์ทั่วไปและบล็อกส่วนตัว</p>
                        </div>
                        <div class="mb-4">
                            <div class="price-tag"><?= formatMoney(calculateCustomerPrice(99)) ?><span class="price-period">/ เดือน</span></div>
                        </div>
                        <ul class="feature-list">
                            <li><i class="bi bi-check-circle-fill"></i> 1 โดเมนหลัก</li>
                            <li><i class="bi bi-check-circle-fill"></i> 5 GB NVMe SSD Storage</li>
                            <li><i class="bi bi-check-circle-fill"></i> Unlimited Bandwidth</li>
                            <li><i class="bi bi-check-circle-fill"></i> DirectAdmin Control Panel</li>
                        </ul>
                        <a href="packages.php" class="btn btn-primary-gradient w-100 py-2 fw-bold rounded-pill">ดูรายละเอียดทั้งหมด</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="packages.php" class="btn btn-link text-decoration-none fw-bold">
                ดูแพ็กเกจโฮสติ้งทั้งหมด <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- Featured VPS Section -->
<?php if (!empty($vpsPlans)): ?>
<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-info-subtle text-info fw-bold px-3 py-2 rounded-pill mb-2">
                HIGH PERFORMANCE VPS
            </span>
            <h2 class="fw-bold">คลาวด์ VPS เซิร์ฟเวอร์ส่วนตัว</h2>
            <p class="text-muted">ควบคุมเต็มรูปแบบด้วย Root Access เลือกระบบปฏิบัติการ Linux หรือ Windows ได้ตามต้องการ</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($vpsPlans as $vps): 
                $costM = (float)$vps['price_monthly'];
                $costY = (float)($vps['price_yearly'] ?? ($costM * 10));
                $pricing = getPackagePricing('vps', $vps['id'], $costM, $costY);
                if (!$pricing['is_active']) continue;
                $custVpsPrice = $pricing['sell_monthly'];
                $displayName = $pricing['custom_name'] ?: $vps['name'];
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card-modern p-4 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($displayName) ?></h4>
                        <?php if ($pricing['is_featured']): ?>
                            <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-star-fill me-1"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'แนะนำ') ?></span>
                        <?php else: ?>
                            <span class="badge bg-primary-subtle text-primary fw-bold">KVM Virtualization</span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <div class="price-tag"><?= formatMoney($custVpsPrice) ?><span class="price-period">/ เดือน</span></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="spec-chip"><i class="bi bi-cpu text-primary"></i> <?= $vps['vcpu'] ?> vCPU</span>
                        <span class="spec-chip"><i class="bi bi-memory text-success"></i> <?= ($vps['ram_mb'] >= 1024) ? ($vps['ram_mb']/1024) . ' GB' : $vps['ram_mb'] . ' MB' ?> RAM</span>
                        <span class="spec-chip"><i class="bi bi-hdd-rack text-warning"></i> <?= $vps['disk_gb'] ?> GB SSD</span>
                        <span class="spec-chip"><i class="bi bi-shield-lock text-danger"></i> Full Root Access</span>
                    </div>

                    <div class="mt-auto">
                        <a href="order_vps.php?id=<?= $vps['id'] ?>" class="btn btn-outline-primary w-100 py-2 fw-bold rounded-pill">
                            <i class="bi bi-cpu me-1"></i> ปรับแต่งและสั่งซื้อ VPS
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Step Process Guide -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">เริ่มต้นใช้งานง่ายๆ ใน 3 ขั้นตอน</h2>
            <p class="text-muted">สั่งซื้อและเริ่มสร้างเว็บไซต์ของคุณได้ในไม่กี่นาที</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
                    <div class="display-6 fw-bold text-primary mb-3">1</div>
                    <h5 class="fw-bold">สมัครสมาชิกและเติมเงิน</h5>
                    <p class="text-muted small">สร้างบัญชีผู้ใช้ และเติมเงินเข้ากระเป๋า Wallet ผ่านระบบ PromptPay QR Code หรือโอนเงิน</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
                    <div class="display-6 fw-bold text-primary mb-3">2</div>
                    <h5 class="fw-bold">เลือกแพ็กเกจที่ต้องการ</h5>
                    <p class="text-muted small">เลือกโฮสติ้งหรือ VPS ระบุชื่อโดเมนและข้อมูลเข้าสู่ระบบที่ต้องการ</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-4 border h-100 shadow-sm">
                    <div class="display-6 fw-bold text-primary mb-3">3</div>
                    <h5 class="fw-bold">ระบบเปิดบริการอัตโนมัติ</h5>
                    <p class="text-muted small">ระบบเชื่อมต่อ API และติดตั้งโฮสติ้งทันที คุณสามารถเข้าจัดการ DirectAdmin ได้ทันที</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
