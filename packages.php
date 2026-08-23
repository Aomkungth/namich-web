<?php
/**
 * Hosting Packages Catalog (packages.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

$pageTitle = 'แพ็กเกจเว็บโฮสติ้ง (Web Hosting Plans) - ' . getSetting('site_name', SITE_NAME);

$api = new NamiResellerAPI();
$categorySettings = getAllCategorySettings();

// หมวดหมู่ที่เลือกผ่าน Query string
$selectedCat = clean($_GET['category'] ?? '');

// ดึงรายการกลุ่มแพ็กเกจ (API + Cache fallback)
$allGroups = fetchHostingPackages($api);

// ดึงรายชื่อหมวดหมู่ที่เปิดใช้งาน (is_active = 1)
$categoriesList = [];
$packageGroups = [];

foreach ($allGroups as $grp) {
    $catId = (int)($grp['category_id'] ?? 0);
    $catSlug = $grp['category_slug'] ?? '';
    $catSetting = $categorySettings[$catId] ?? null;

    // ข้ามหมวดหมู่ที่ถูกปิดใช้งาน
    if ($catSetting && isset($catSetting['is_active']) && $catSetting['is_active'] == 0) {
        continue;
    }

    $customCatName = !empty($catSetting['custom_name']) ? $catSetting['custom_name'] : $grp['category_name'];
    $grp['category_name'] = $customCatName;

    $categoriesList[] = [
        'id' => $catId,
        'slug' => $catSlug,
        'name' => $customCatName
    ];

    // กรองตามหมวดหมู่ที่เลือกถ้ามี
    if (!empty($selectedCat) && $selectedCat !== $catSlug) {
        continue;
    }

    // กรองเฉพาะแพ็กเกจที่เปิดใช้งาน (is_active = 1)
    $activePackages = [];
    foreach ($grp['packages'] as $p) {
        $costM = (float)$p['price_monthly'];
        $costY = (float)($p['price_yearly'] ?? ($costM * 10));
        $pricing = getPackagePricing('hosting', $p['id'], $costM, $costY);
        if ($pricing['is_active']) {
            $activePackages[] = $p;
        }
    }

    if (!empty($activePackages)) {
        $grp['packages'] = $activePackages;
        $packageGroups[] = $grp;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-2">
            WEB HOSTING
        </span>
        <h1 class="fw-bold">แพ็กเกจเว็บโฮสติ้ง DirectAdmin</h1>
        <p class="text-muted lead">สตอเรจ NVMe SSD ความเร็วสูง ระบบเสถียร รองรับ Node.js / Python / PHP ทุกเวอร์ชัน</p>
    </div>

    <!-- Category Filter Tabs -->
    <?php if (!empty($categoriesList) && count($categoriesList) > 1): ?>
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
        <a href="packages.php" class="btn <?= empty($selectedCat) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-4">
            <i class="bi bi-grid-fill me-1"></i> ทุกหมวดหมู่
        </a>
        <?php foreach ($categoriesList as $cat): ?>
            <a href="packages.php?category=<?= urlencode($cat['slug']) ?>" 
               class="btn <?= ($selectedCat === $cat['slug']) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-4">
                <i class="bi bi-folder2-open me-1"></i>
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Packages List -->
    <?php if (!empty($packageGroups)): ?>
        <?php foreach ($packageGroups as $group): ?>
            <div class="mb-5">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <h3 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-hdd-network text-primary me-2"></i><?= htmlspecialchars($group['category_name']) ?>
                    </h3>
                    <span class="badge bg-secondary"><?= count($group['packages']) ?> แพ็กเกจ</span>
                </div>

                <div class="row g-4">
                    <?php foreach ($group['packages'] as $pkg): 
                        $costM = (float)$pkg['price_monthly'];
                        $costY = (float)($pkg['price_yearly'] ?? ($costM * 10));
                        $pricing = getPackagePricing('hosting', $pkg['id'], $costM, $costY);
                        $custMonthly = $pricing['sell_monthly'];
                        $custYearly = $pricing['sell_yearly'];
                        $isFeatured = $pricing['is_featured'] || !empty($pkg['is_featured']);
                        $displayName = $pricing['custom_name'] ?: $pkg['name'];
                    ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
                                <?php if ($isFeatured): ?>
                                    <span class="pricing-badge"><i class="bi bi-star-fill me-1"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'ยอดนิยม') ?></span>
                                <?php endif; ?>

                                <h4 class="fw-bold mb-2"><?= htmlspecialchars($displayName) ?></h4>
                                <p class="text-muted small mb-3">รหัสแพ็กเกจ: #<?= $pkg['id'] ?></p>

                                <div class="mb-4">
                                    <div class="price-tag">
                                        <?= formatMoney($custMonthly) ?>
                                        <span class="price-period">/ เดือน</span>
                                    </div>
                                    <div class="text-success fw-bold small mt-1">
                                        <i class="bi bi-tag-fill me-1"></i> รายปีเพียง <?= formatMoney($custYearly) ?> / ปี
                                    </div>
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
                                        <i class="bi bi-envelope-at text-danger"></i>
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
                                    <i class="bi bi-cart-check me-1"></i> สั่งซื้อแพ็กเกจนี้
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <div class="display-1 text-muted mb-3"><i class="bi bi-inbox"></i></div>
            <h4>ยังไม่มีข้อมูลแพ็กเกจ หรือไม่สามารถเชื่อมต่อ API ได้</h4>
            <p class="text-muted">กรุณาตรวจสอบการตั้งค่า Reseller API Key หรือซิงค์ข้อมูลในหน้าแอดมิน</p>
            <?php if (isAdmin()): ?>
                <a href="admin/pricing.php" class="btn btn-primary">ไปที่หน้าจัดการแพ็กเกจ (Admin)</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
