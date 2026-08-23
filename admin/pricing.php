<?php
/**
 * Advanced Price Markup, Category & Package Control (admin/pricing.php)
 * ระบบควบคุมราคา กำไร หมวดหมู่ สถานะเปิด/ปิด และป้ายแนะนำของแพ็กเกจทั้งหมดผ่านหลังบ้าน
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

$pageTitle = 'จัดการหมวดหมู่ แพ็กเกจ และราคาขาย (Package & Pricing Control)';
$pdo = getDB();
$api = new NamiResellerAPI();

// 1. กดปุ่มซิงค์ข้อมูลจาก API (Sync API Data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_api_data') {
    $syncRes = syncAllDataFromAPI($api);
    if ($syncRes['ok']) {
        setFlash('success', "ซิงค์ข้อมูลสำเร็จ! ดึงได้ {$syncRes['categories_count']} หมวดหมู่, {$syncRes['packages_count']} แพ็กเกจโฮสติ้ง และ {$syncRes['vps_count']} แพ็กเกจ VPS จาก API เรียบร้อยแล้ว");
    } else {
        $errText = implode(', ', $syncRes['errors']);
        setFlash('warning', "ซิงค์ข้อมูลบางส่วนไม่สำเร็จ: {$errText} (กรุณาตรวจสอบการตั้งค่า API Key ในระบบ)");
    }
    header('Location: pricing.php');
    exit;
}

// 2. บันทึกสูตรกำไร Global Markup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_global_markup') {
    $markupPercent = (float)($_POST['markup_percent'] ?? 0);
    $markupFixed = (float)($_POST['markup_fixed'] ?? 0);

    setSetting('markup_percent', (string)$markupPercent);
    setSetting('markup_fixed', (string)$markupFixed);

    setFlash('success', 'บันทึกสูตรราคาบวกกำไรส่วนกลาง (Global Markup) เรียบร้อยแล้ว');
    header('Location: pricing.php');
    exit;
}

// 3. บันทึกการตั้งค่าหมวดหมู่ (Category Overrides)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category_settings') {
    $categories = $_POST['categories'] ?? [];
    foreach ($categories as $catId => $catData) {
        $catId = (int)$catId;
        $slug = clean($catData['slug'] ?? '');
        $customName = clean($catData['custom_name'] ?? '');
        $isActive = isset($catData['is_active']) ? 1 : 0;
        $sortOrder = (int)($catData['sort_order'] ?? 0);

        if ($catId > 0) {
            $stmt = $pdo->prepare("INSERT INTO `category_settings` 
                (`category_id`, `category_slug`, `custom_name`, `is_active`, `sort_order`) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    `custom_name` = VALUES(`custom_name`),
                    `is_active` = VALUES(`is_active`),
                    `sort_order` = VALUES(`sort_order`)");
            $stmt->execute([$catId, $slug, $customName ?: null, $isActive, $sortOrder]);
        }
    }
    setFlash('success', 'บันทึกการตั้งค่าหมวดหมู่สินค้าเรียบร้อยแล้ว');
    header('Location: pricing.php#categories-pane');
    exit;
}

// 4. บันทึกการปรับแต่งเฉพาะแพ็กเกจ (Custom Package Override)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_package_override') {
    $itemType = in_array($_POST['item_type'] ?? '', ['hosting', 'vps']) ? $_POST['item_type'] : 'hosting';
    $itemId = (int)($_POST['item_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $pricingMode = in_array($_POST['pricing_mode'] ?? '', ['global', 'custom_markup', 'custom_price']) ? $_POST['pricing_mode'] : 'global';
    
    $customMarkupPercent = (float)($_POST['custom_markup_percent'] ?? 0);
    $customMarkupFixed = (float)($_POST['custom_markup_fixed'] ?? 0);
    $customPriceMonthly = !empty($_POST['custom_price_monthly']) ? (float)$_POST['custom_price_monthly'] : null;
    $customPriceYearly = !empty($_POST['custom_price_yearly']) ? (float)$_POST['custom_price_yearly'] : null;
    
    $customName = clean($_POST['custom_name'] ?? '');
    $badgeText = clean($_POST['badge_text'] ?? '');
    $customFeatures = clean($_POST['custom_features'] ?? '');

    if ($itemId > 0) {
        $stmt = $pdo->prepare("INSERT INTO `package_settings` (
            `item_type`, `item_id`, `is_active`, `is_featured`, `pricing_mode`,
            `custom_markup_percent`, `custom_markup_fixed`, `custom_price_monthly`, `custom_price_yearly`,
            `custom_name`, `badge_text`, `custom_features`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `is_active` = VALUES(`is_active`),
            `is_featured` = VALUES(`is_featured`),
            `pricing_mode` = VALUES(`pricing_mode`),
            `custom_markup_percent` = VALUES(`custom_markup_percent`),
            `custom_markup_fixed` = VALUES(`custom_markup_fixed`),
            `custom_price_monthly` = VALUES(`custom_price_monthly`),
            `custom_price_yearly` = VALUES(`custom_price_yearly`),
            `custom_name` = VALUES(`custom_name`),
            `badge_text` = VALUES(`badge_text`),
            `custom_features` = VALUES(`custom_features`)");

        $stmt->execute([
            $itemType,
            $itemId,
            $isActive,
            $isFeatured,
            $pricingMode,
            $customMarkupPercent,
            $customMarkupFixed,
            $customPriceMonthly,
            $customPriceYearly,
            $customName ?: null,
            $badgeText ?: null,
            $customFeatures ?: null
        ]);

        setFlash('success', "บันทึกการปรับแต่งราคาและสถานะของแพ็กเกจ #{$itemId} เรียบร้อยแล้ว");
    }
    header('Location: pricing.php');
    exit;
}

// ดึงข้อมูล Categories & Packages (API + Cache fallback)
$hostingCategories = fetchHostingPackages($api);
$vpsData = fetchVPSPackages($api);
$vpsPlans = !empty($vpsData['plans']) ? $vpsData['plans'] : [];

// ดึงการตั้งค่า Custom ทั้งหมด
$savedSettings = getAllPackageSettings();
$categorySettings = getAllCategorySettings();

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom gap-2">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-tags-fill text-success me-2"></i> จัดการหมวดหมู่และแพ็กเกจสินค้า</h1>
        <p class="text-muted mb-0">ดึงข้อมูลจาก API อัตโนมัติ กำหนดว่าจะเปิด/ปิดหมวดหมู่หรือแพ็กเกจไหน และตั้งราคาขายได้อิสระ 100%</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="pricing.php" class="d-inline">
            <input type="hidden" name="action" value="sync_api_data">
            <button type="submit" class="btn btn-outline-primary fw-bold shadow-sm">
                <i class="bi bi-arrow-repeat me-1"></i> ซิงค์ข้อมูลล่าสุดจาก API
            </button>
        </form>
    </div>
</div>

<!-- 1. Global Markup Formula Card -->
<div class="card-modern p-4 mb-4 border-primary">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h5 class="fw-bold mb-0 text-primary">
            <i class="bi bi-sliders2 me-2"></i> สูตรกำไรส่วนกลาง (Global Markup Formula)
        </h5>
        <span class="badge bg-primary-subtle text-primary">มีผลกับทุกแพ็กเกจที่ตั้งค่าเป็นสูตรกลาง</span>
    </div>

    <form method="POST" action="pricing.php" class="row g-3 align-items-end">
        <input type="hidden" name="action" value="save_global_markup">

        <div class="col-md-4">
            <label class="form-label fw-bold">บวกกำไรตามเปอร์เซ็นต์ (%)</label>
            <div class="input-group">
                <input type="number" step="0.5" min="0" name="markup_percent" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars(getSetting('markup_percent', '0')) ?>">
                <span class="input-group-text bg-light">%</span>
            </div>
            <div class="form-text">เช่น ใส่ 20 = บวกกำไร 20% จากราคาทุน API</div>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">บวกกำไรจำนวนเงินคงที่ (บาท)</label>
            <div class="input-group">
                <input type="number" step="1" min="0" name="markup_fixed" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars(getSetting('markup_fixed', '0')) ?>">
                <span class="input-group-text bg-light">฿</span>
            </div>
            <div class="form-text">เช่น ใส่ 50 = บวกเพิ่ม 50 บาท ทุกแพ็กเกจ</div>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary-gradient btn-lg w-100 fw-bold">
                <i class="bi bi-save me-1"></i> บันทึกสูตรกำไรส่วนกลาง
            </button>
        </div>
    </form>
</div>

<!-- 2. Navigation Tabs -->
<ul class="nav nav-tabs mb-4 fw-bold" id="pricingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active px-4" id="hosting-tab" data-bs-toggle="tab" data-bs-target="#hosting-pane" type="button" role="tab">
            <i class="bi bi-server me-2 text-primary"></i> แพ็กเกจโฮสติ้ง DirectAdmin
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4" id="vps-tab" data-bs-toggle="tab" data-bs-target="#vps-pane" type="button" role="tab">
            <i class="bi bi-cpu me-2 text-info"></i> แพ็กเกจคลาวด์ VPS
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link px-4" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-pane" type="button" role="tab">
            <i class="bi bi-folder2-open me-2 text-warning"></i> จัดการหมวดหมู่สินค้า (Categories)
        </button>
    </li>
</ul>

<div class="tab-content" id="pricingTabsContent">
    <!-- ============================================== -->
    <!-- TAB 1: Hosting Packages Table                  -->
    <!-- ============================================== -->
    <div class="tab-pane fade show active" id="hosting-pane" role="tabpanel">
        <div class="card-modern p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">รายการแพ็กเกจเว็บโฮสติ้งทั้งหมด</h5>
                    <small class="text-muted">ดึงข้อมูลสดจาก API พร้อมระบบซ่อน/แสดง และปรับราคาแยกตามแพ็กเกจ</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>หมวดหมู่ / ชื่อแพ็กเกจ</th>
                            <th>สถานะการขาย</th>
                            <th>ราคาทุน API (ด/ป)</th>
                            <th>โหมดการคิดราคา</th>
                            <th>ราคาขายลูกค้า (ด/ป)</th>
                            <th>กำไรต่อเดือน</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($hostingCategories)): ?>
                            <?php foreach ($hostingCategories as $cat): ?>
                                <?php 
                                    $catSetting = $categorySettings[$cat['category_id'] ?? 0] ?? null;
                                    $catActive = ($catSetting['is_active'] ?? 1) == 1;
                                ?>
                                <tr class="table-secondary">
                                    <td colspan="8" class="fw-bold py-2">
                                        <i class="bi bi-folder-fill text-warning me-1"></i> หมวดหมู่: <?= htmlspecialchars($cat['category_name']) ?>
                                        <?php if (!$catActive): ?>
                                            <span class="badge bg-danger ms-2"><i class="bi bi-eye-slash me-1"></i>หมวดหมู่นี้ถูกซ่อนไว้ในหน้าเว็บ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php foreach ($cat['packages'] as $p): 
                                    $costM = (float)$p['price_monthly'];
                                    $costY = (float)($p['price_yearly'] ?? ($costM * 10));
                                    $pricing = getPackagePricing('hosting', $p['id'], $costM, $costY);
                                    $customSetting = $savedSettings['hosting_' . $p['id']] ?? null;
                                ?>
                                    <tr class="<?= (!$pricing['is_active'] || !$catActive) ? 'bg-light opacity-75' : '' ?>">
                                        <td class="text-muted">#<?= $p['id'] ?></td>
                                        <td>
                                            <div class="fw-bold text-dark fs-6">
                                                <?= htmlspecialchars($pricing['custom_name'] ?: $p['name']) ?>
                                                <?php if (!empty($pricing['custom_name']) && $pricing['custom_name'] !== $p['name']): ?>
                                                    <small class="text-muted fw-normal">(เดิม: <?= htmlspecialchars($p['name']) ?>)</small>
                                                <?php endif; ?>
                                                <?php if ($pricing['is_featured']): ?>
                                                    <span class="badge bg-warning text-dark ms-1">
                                                        <i class="bi bi-star-fill"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'แนะนำ') ?>
                                                    </span>
                                                <?php endif; ?>
                                            <small class="text-muted">
                                                Disk: <?= ($p['disk_mb']>=1024)?($p['disk_mb']/1024).'GB':$p['disk_mb'].'MB' ?> &bull; 
                                                Bandwidth: <?= (empty($p['bandwidth_mb']) || $p['bandwidth_mb'] <= 0)?'Unlimited':(($p['bandwidth_mb']>=1024)?($p['bandwidth_mb']/1024).'GB':$p['bandwidth_mb'].'MB') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($pricing['is_active'] && $catActive): ?>
                                                <span class="badge bg-success"><i class="bi bi-eye me-1"></i>เปิดขาย</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i>ซ่อนไว้</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted font-monospace">
                                            <?= formatMoney($costM) ?> / <?= formatMoney($costY) ?>
                                        </td>
                                        <td>
                                            <?php if ($pricing['pricing_mode'] === 'custom_price'): ?>
                                                <span class="badge bg-purple text-dark border"><i class="bi bi-pin-angle-fill me-1"></i>ราคาคงที่</span>
                                            <?php elseif ($pricing['pricing_mode'] === 'custom_markup'): ?>
                                                <span class="badge bg-info-subtle text-info border"><i class="bi bi-percent me-1"></i>บวกกำไรเฉพาะ (+<?= $pricing['markup_percent'] ?>% +<?= $pricing['markup_fixed'] ?>฿)</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border"><i class="bi bi-globe me-1"></i>Global Markup</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-primary fs-6"><?= formatMoney($pricing['sell_monthly']) ?> <small class="text-muted">/ด</small></div>
                                            <small class="text-success"><?= formatMoney($pricing['sell_yearly']) ?> /ปี</small>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success fs-6">+<?= formatMoney($pricing['profit_monthly']) ?></span>
                                            <small class="text-muted d-block">(+<?= formatMoney($pricing['profit_yearly']) ?> /ปี)</small>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 btn-edit-package"
                                                    data-type="hosting"
                                                    data-id="<?= $p['id'] ?>"
                                                    data-name="<?= htmlspecialchars($p['name']) ?>"
                                                    data-cost-m="<?= $costM ?>"
                                                    data-cost-y="<?= $costY ?>"
                                                    data-custom-name="<?= htmlspecialchars($customSetting['custom_name'] ?? '') ?>"
                                                    data-badge-text="<?= htmlspecialchars($customSetting['badge_text'] ?? '') ?>"
                                                    data-is-active="<?= $pricing['is_active'] ? '1' : '0' ?>"
                                                    data-is-featured="<?= $pricing['is_featured'] ? '1' : '0' ?>"
                                                    data-pricing-mode="<?= $pricing['pricing_mode'] ?>"
                                                    data-markup-percent="<?= $customSetting['custom_markup_percent'] ?? '0' ?>"
                                                    data-markup-fixed="<?= $customSetting['custom_markup_fixed'] ?? '0' ?>"
                                                    data-custom-price-m="<?= $customSetting['custom_price_monthly'] ?? '' ?>"
                                                    data-custom-price-y="<?= $customSetting['custom_price_yearly'] ?? '' ?>"
                                                    data-custom-features="<?= htmlspecialchars($customSetting['custom_features'] ?? '') ?>">
                                                <i class="bi bi-pencil-square me-1"></i> ปรับแต่งราคา & สถานะ
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    ยังไม่มีข้อมูลแพ็กเกจโฮสติ้ง กรุณากดปุ่ม <strong>"ซิงค์ข้อมูลล่าสุดจาก API"</strong> ด้านบน
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 2: VPS Packages Table                      -->
    <!-- ============================================== -->
    <div class="tab-pane fade" id="vps-pane" role="tabpanel">
        <div class="card-modern p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">รายการแพ็กเกจคลาวด์ VPS ทั้งหมด</h5>
                    <small class="text-muted">ดึงสเปกและแพ็กเกจเซิร์ฟเวอร์ VPS จาก API อัตโนมัติ</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อแพ็กเกจ VPS</th>
                            <th>สเปก (vCPU / RAM / Disk)</th>
                            <th>สถานะ</th>
                            <th>ราคาทุน API (ด/ป)</th>
                            <th>โหมดการคิดราคา</th>
                            <th>ราคาขายลูกค้า (ด/ป)</th>
                            <th>กำไรต่อเดือน</th>
                            <th class="text-end">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vpsPlans)): ?>
                            <?php foreach ($vpsPlans as $plan): 
                                $costM = (float)$plan['price_monthly'];
                                $costY = (float)($plan['price_yearly'] ?? ($costM * 10));
                                $pricing = getPackagePricing('vps', $plan['id'], $costM, $costY);
                                $customSetting = $savedSettings['vps_' . $plan['id']] ?? null;
                            ?>
                                <tr class="<?= !$pricing['is_active'] ? 'bg-light opacity-75' : '' ?>">
                                    <td class="text-muted">#<?= $plan['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6">
                                            <?= htmlspecialchars($pricing['custom_name'] ?: $plan['name']) ?>
                                            <?php if ($pricing['is_featured']): ?>
                                                <span class="badge bg-warning text-dark ms-1">
                                                    <i class="bi bi-star-fill"></i> <?= htmlspecialchars($pricing['badge_text'] ?: 'แนะนำ') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $plan['vcpu'] ?> Core &bull; 
                                            <?= ($plan['ram_mb']>=1024)?($plan['ram_mb']/1024).'GB':$plan['ram_mb'].'MB' ?> RAM &bull; 
                                            <?= $plan['disk_gb'] ?> GB SSD
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($pricing['is_active']): ?>
                                            <span class="badge bg-success"><i class="bi bi-eye me-1"></i>เปิดขาย</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i>ซ่อนไว้</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted font-monospace">
                                        <?= formatMoney($costM) ?> / <?= formatMoney($costY) ?>
                                    </td>
                                    <td>
                                        <?php if ($pricing['pricing_mode'] === 'custom_price'): ?>
                                            <span class="badge bg-purple text-dark border"><i class="bi bi-pin-angle-fill me-1"></i>ราคาคงที่</span>
                                        <?php elseif ($pricing['pricing_mode'] === 'custom_markup'): ?>
                                            <span class="badge bg-info-subtle text-info border"><i class="bi bi-percent me-1"></i>บวกกำไรเฉพาะ (+<?= $pricing['markup_percent'] ?>% +<?= $pricing['markup_fixed'] ?>฿)</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border"><i class="bi bi-globe me-1"></i>Global Markup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary fs-6"><?= formatMoney($pricing['sell_monthly']) ?> <small class="text-muted">/ด</small></div>
                                        <small class="text-success"><?= formatMoney($pricing['sell_yearly']) ?> /ปี</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success fs-6">+<?= formatMoney($pricing['profit_monthly']) ?></span>
                                        <small class="text-muted d-block">(+<?= formatMoney($pricing['profit_yearly']) ?> /ปี)</small>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 btn-edit-package"
                                                data-type="vps"
                                                data-id="<?= $plan['id'] ?>"
                                                data-name="<?= htmlspecialchars($plan['name']) ?>"
                                                data-cost-m="<?= $costM ?>"
                                                data-cost-y="<?= $costY ?>"
                                                data-custom-name="<?= htmlspecialchars($customSetting['custom_name'] ?? '') ?>"
                                                data-badge-text="<?= htmlspecialchars($customSetting['badge_text'] ?? '') ?>"
                                                data-is-active="<?= $pricing['is_active'] ? '1' : '0' ?>"
                                                data-is-featured="<?= $pricing['is_featured'] ? '1' : '0' ?>"
                                                data-pricing-mode="<?= $pricing['pricing_mode'] ?>"
                                                data-markup-percent="<?= $customSetting['custom_markup_percent'] ?? '0' ?>"
                                                data-markup-fixed="<?= $customSetting['custom_markup_fixed'] ?? '0' ?>"
                                                data-custom-price-m="<?= $customSetting['custom_price_monthly'] ?? '' ?>"
                                                data-custom-price-y="<?= $customSetting['custom_price_yearly'] ?? '' ?>"
                                                data-custom-features="<?= htmlspecialchars($customSetting['custom_features'] ?? '') ?>">
                                            <i class="bi bi-pencil-square me-1"></i> ปรับแต่งราคา & สถานะ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    ยังไม่มีข้อมูลแพ็กเกจ VPS กรุณากดปุ่ม <strong>"ซิงค์ข้อมูลล่าสุดจาก API"</strong> ด้านบน
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 3: Category Management Tab                 -->
    <!-- ============================================== -->
    <div class="tab-pane fade" id="categories-pane" role="tabpanel">
        <div class="card-modern p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">หมวดหมู่สินค้าที่ดึงมาจาก API (Categories)</h5>
                    <p class="text-muted small mb-0">กำหนดเปิด/ปิดการแสดงผลของแต่ละหมวดหมู่ หรือตั้งชื่อหมวดหมู่ที่แสดงหน้าเว็บใหม่</p>
                </div>
            </div>

            <form method="POST" action="pricing.php">
                <input type="hidden" name="action" value="save_category_settings">

                <div class="table-responsive mb-4">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ชื่อหมวดหมู่ตาม API</th>
                                <th>ชื่อที่แสดงหน้าเว็บ (Custom Name)</th>
                                <th>Slug</th>
                                <th>สถานะแสดงบนเว็บ</th>
                                <th style="width: 120px;">ลำดับ (Sort)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($hostingCategories)): ?>
                                <?php foreach ($hostingCategories as $cat): 
                                    $cid = (int)($cat['category_id'] ?? 0);
                                    $cSetting = $categorySettings[$cid] ?? null;
                                    $cActive = ($cSetting['is_active'] ?? 1) == 1;
                                    $cCustomName = $cSetting['custom_name'] ?? '';
                                    $cOrder = $cSetting['sort_order'] ?? 0;
                                ?>
                                    <tr>
                                        <td class="text-muted">#<?= $cid ?></td>
                                        <td class="fw-bold text-dark">
                                            <i class="bi bi-folder-fill text-warning me-1"></i> <?= htmlspecialchars($cat['category_name']) ?>
                                            <span class="badge bg-light text-secondary ms-1">(<?= count($cat['packages'] ?? []) ?> แพ็กเกจ)</span>
                                        </td>
                                        <td>
                                            <input type="text" name="categories[<?= $cid ?>][custom_name]" class="form-control form-control-sm" value="<?= htmlspecialchars($cCustomName) ?>" placeholder="<?= htmlspecialchars($cat['category_name']) ?>">
                                        </td>
                                        <td class="small font-monospace text-muted">
                                            <?= htmlspecialchars($cat['category_slug'] ?? '') ?>
                                            <input type="hidden" name="categories[<?= $cid ?>][slug]" value="<?= htmlspecialchars($cat['category_slug'] ?? '') ?>">
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" name="categories[<?= $cid ?>][is_active]" value="1" <?= $cActive ? 'checked' : '' ?>>
                                                <label class="form-check-label small fw-bold"><?= $cActive ? '<span class="text-success">แสดง</span>' : '<span class="text-muted">ซ่อน</span>' ?></label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="categories[<?= $cid ?>][sort_order]" class="form-control form-control-sm" value="<?= (int)$cOrder ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">ยังไม่มีข้อมูลหมวดหมู่ กรุณากดปุ่มซิงค์ข้อมูลจาก API</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary-gradient px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> บันทึกการตั้งค่าหมวดหมู่
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: Edit Package Pricing & Settings         -->
<!-- ============================================== -->
<div class="modal fade" id="editPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <form method="POST" action="pricing.php" id="packageOverrideForm">
                <input type="hidden" name="action" value="save_package_override">
                <input type="hidden" name="item_type" id="modal_item_type">
                <input type="hidden" name="item_id" id="modal_item_id">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-sliders text-primary me-2"></i> ปรับแต่งราคาและสถานะแพ็กเกจ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body py-4">
                    <div class="p-3 bg-light rounded-3 mb-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small d-block">แพ็กเกจจาก API:</span>
                            <h5 class="fw-bold mb-0 text-dark" id="modal_orig_name"></h5>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">ราคาทุนจาก API:</span>
                            <span class="fw-bold text-dark" id="modal_cost_display"></span>
                        </div>
                    </div>

                    <!-- Visibility & Featured Toggles -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="modal_is_active" value="1" checked>
                                    <label class="form-check-label fw-bold" for="modal_is_active">เปิดให้ลูกค้าสั่งซื้อ (Active)</label>
                                </div>
                                <small class="text-muted d-block">หากปิด แพ็กเกจนี้จะไม่แสดงบนหน้าเว็บไซต์</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white h-100">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_featured" id="modal_is_featured" value="1">
                                    <label class="form-check-label fw-bold" for="modal_is_featured">ตั้งเป็นแพ็กเกจแนะนำ (Featured)</label>
                                </div>
                                <input type="text" name="badge_text" id="modal_badge_text" class="form-control form-control-sm" placeholder="ข้อความป้าย เช่น ยอดนิยม, แนะนำ, ขายดี">
                            </div>
                        </div>
                    </div>

                    <!-- Custom Display Name -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">ชื่อแพ็กเกจที่แสดงบนหน้าเว็บ (Custom Display Name)</label>
                        <input type="text" name="custom_name" id="modal_custom_name" class="form-control" placeholder="เว้นว่างเพื่อใช้ชื่อตาม API">
                    </div>

                    <!-- Custom Features -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">รายละเอียดฟีเจอร์เพิ่มเติม (Custom Features)</label>
                        <textarea name="custom_features" id="modal_custom_features" class="form-control" rows="3" placeholder="เพิ่มรายละเอียด 1 ข้อต่อ 1 บรรทัด เช่น:
แถมฟรีโดเมน .com
ติดตั้ง WordPress ฟรี"></textarea>
                        <small class="text-muted d-block mt-1">จะแสดงต่อท้ายสเปกมาตรฐานที่ดึงจาก API ในหน้าเว็บหลัก</small>
                    </div>

                    <!-- Pricing Strategy Radio Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">เลือกรูปแบบการกำหนดราคา (Pricing Strategy)</label>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pricing_mode" id="pm_global" value="global" checked>
                                        <label class="form-check-label w-100" for="pm_global">
                                            <strong class="d-block text-dark">1. ใช้สูตรกลาง</strong>
                                            <small class="text-muted">บวกตาม % และจำนวนเงินของระบบ</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pricing_mode" id="pm_custom_markup" value="custom_markup">
                                        <label class="form-check-label w-100" for="pm_custom_markup">
                                            <strong class="d-block text-dark">2. กำหนดกำไรเฉพาะ</strong>
                                            <small class="text-muted">ระบุ % หรือจำนวนเงินของแพ็กเกจนี้</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="p-3 border rounded-3 bg-light cursor-pointer h-100">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pricing_mode" id="pm_custom_price" value="custom_price">
                                        <label class="form-check-label w-100" for="pm_custom_price">
                                            <strong class="d-block text-dark">3. ตั้งราคาขายตรง</strong>
                                            <small class="text-muted">กำหนดตัวเลขราคาขายเป๊ะๆ</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Markup Inputs Section -->
                    <div id="section_custom_markup" class="p-3 bg-light rounded-3 border mb-4 d-none">
                        <h6 class="fw-bold mb-3"><i class="bi bi-percent text-primary me-1"></i> ระบุกำไรเฉพาะแพ็กเกจนี้</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">บวกกำไร (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.5" min="0" name="custom_markup_percent" id="modal_custom_markup_percent" class="form-control" value="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">บวกกำไรคงที่ (บาท)</label>
                                <div class="input-group">
                                    <input type="number" step="1" min="0" name="custom_markup_fixed" id="modal_custom_markup_fixed" class="form-control" value="0">
                                    <span class="input-group-text">฿</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Direct Fixed Price Inputs Section -->
                    <div id="section_custom_price" class="p-3 bg-light rounded-3 border mb-4 d-none">
                        <h6 class="fw-bold mb-3"><i class="bi bi-pin-angle-fill text-purple me-1"></i> ระบุราคาขายลูกค้าโดยตรง</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">ราคาขายรายเดือน (บาท) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" name="custom_price_monthly" id="modal_custom_price_m" class="form-control fw-bold text-primary" placeholder="0.00">
                                    <span class="input-group-text">฿ / เดือน</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">ราคาขายรายปี (บาท)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" name="custom_price_yearly" id="modal_custom_price_y" class="form-control fw-bold text-success" placeholder="0.00">
                                    <span class="input-group-text">฿ / ปี</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculation Result Preview Box -->
                    <div class="p-3 rounded-3 bg-white border border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">ราคาขายลูกค้าหลังคำนวณ:</small>
                                <span class="fs-4 fw-bold text-primary" id="preview_sell_price">-</span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">กำไรสุทธิต่อเดือน:</small>
                                <span class="fs-5 fw-bold text-success" id="preview_profit">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gradient px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const globalPercent = <?= (float)getSetting('markup_percent', 0) ?>;
    const globalFixed = <?= (float)getSetting('markup_fixed', 0) ?>;

    let currentCostM = 0;
    let currentCostY = 0;

    const modal = document.getElementById('editPackageModal');
    const secCustomMarkup = document.getElementById('section_custom_markup');
    const secCustomPrice = document.getElementById('section_custom_price');

    const previewSell = document.getElementById('preview_sell_price');
    const previewProfit = document.getElementById('preview_profit');

    const inputMarkupPercent = document.getElementById('modal_custom_markup_percent');
    const inputMarkupFixed = document.getElementById('modal_custom_markup_fixed');
    const inputPriceM = document.getElementById('modal_custom_price_m');
    const inputPriceY = document.getElementById('modal_custom_price_y');

    function updatePreview() {
        const mode = document.querySelector('input[name="pricing_mode"]:checked')?.value || 'global';
        let sellM = 0;
        let sellY = 0;

        if (mode === 'custom_price') {
            sellM = parseFloat(inputPriceM.value) || currentCostM;
            sellY = parseFloat(inputPriceY.value) || (sellM * 10);
        } else if (mode === 'custom_markup') {
            const p = parseFloat(inputMarkupPercent.value) || 0;
            const f = parseFloat(inputMarkupFixed.value) || 0;
            sellM = currentCostM + (currentCostM * (p / 100)) + f;
            sellY = currentCostY + (currentCostY * (p / 100)) + (f * 10);
        } else { // global
            sellM = currentCostM + (currentCostM * (globalPercent / 100)) + globalFixed;
            sellY = currentCostY + (currentCostY * (globalPercent / 100)) + (globalFixed * 10);
        }

        const profitM = sellM - currentCostM;
        previewSell.textContent = sellM.toFixed(2) + ' ฿ / เดือน (รายปี: ' + sellY.toFixed(2) + ' ฿)';
        previewProfit.textContent = (profitM >= 0 ? '+' : '') + profitM.toFixed(2) + ' ฿';
    }

    function toggleModeSections() {
        const mode = document.querySelector('input[name="pricing_mode"]:checked')?.value || 'global';
        if (mode === 'custom_markup') {
            secCustomMarkup.classList.remove('d-none');
            secCustomPrice.classList.add('d-none');
        } else if (mode === 'custom_price') {
            secCustomMarkup.classList.add('d-none');
            secCustomPrice.classList.remove('d-none');
        } else {
            secCustomMarkup.classList.add('d-none');
            secCustomPrice.classList.add('d-none');
        }
        updatePreview();
    }

    document.querySelectorAll('input[name="pricing_mode"]').forEach(r => {
        r.addEventListener('change', toggleModeSections);
    });

    [inputMarkupPercent, inputMarkupFixed, inputPriceM, inputPriceY].forEach(el => {
        el.addEventListener('input', updatePreview);
    });

    // Populate Modal on click
    document.querySelectorAll('.btn-edit-package').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.getAttribute('data-type');
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            currentCostM = parseFloat(btn.getAttribute('data-cost-m')) || 0;
            currentCostY = parseFloat(btn.getAttribute('data-cost-y')) || (currentCostM * 10);

            document.getElementById('modal_item_type').value = type;
            document.getElementById('modal_item_id').value = id;
            document.getElementById('modal_orig_name').textContent = name + ' (ID #' + id + ')';
            document.getElementById('modal_cost_display').textContent = currentCostM.toFixed(2) + ' ฿/ด (ปีละ ' + currentCostY.toFixed(2) + ' ฿)';

            document.getElementById('modal_custom_name').value = btn.getAttribute('data-custom-name') || '';
            document.getElementById('modal_badge_text').value = btn.getAttribute('data-badge-text') || '';
            document.getElementById('modal_custom_features').value = btn.getAttribute('data-custom-features') || '';
            document.getElementById('modal_is_active').checked = (btn.getAttribute('data-is-active') === '1');
            document.getElementById('modal_is_featured').checked = (btn.getAttribute('data-is-featured') === '1');

            const mode = btn.getAttribute('data-pricing-mode') || 'global';
            const radio = document.querySelector(`input[name="pricing_mode"][value="${mode}"]`);
            if (radio) radio.checked = true;

            inputMarkupPercent.value = btn.getAttribute('data-markup-percent') || '0';
            inputMarkupFixed.value = btn.getAttribute('data-markup-fixed') || '0';
            inputPriceM.value = btn.getAttribute('data-custom-price-m') || '';
            inputPriceY.value = btn.getAttribute('data-custom-price-y') || '';

            toggleModeSections();
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });

    // Support Hash navigation
    if (window.location.hash) {
        const triggerEl = document.querySelector(`button[data-bs-target="${window.location.hash}"]`);
        if (triggerEl) {
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
