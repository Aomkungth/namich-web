<?php
/**
 * Admin System & API Settings (admin/settings.php)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

$pageTitle = 'ตั้งค่าระบบ & Reseller API Key';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = [
        'reseller_api_key',
        'reseller_api_url',
        'markup_percent',
        'markup_fixed',
        'site_name',
        'site_slogan',
        'promptpay_number',
        'promptpay_name',
        'truemoney_phone',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'contact_line',
        'contact_email'
    ];

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            setSetting($k, trim($_POST[$k]));
        }
    }

    setFlash('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
    header('Location: settings.php');
    exit;
}

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-gear-fill text-primary me-2"></i> ตั้งค่าระบบ & Reseller API</h1>
        <p class="text-muted mb-0">กำหนด API Key ช่องทางการชำระเงิน และข้อมูลการติดต่อ</p>
    </div>
</div>

<form method="POST" action="settings.php">
    <input type="hidden" name="save_settings" value="1">

    <div class="row g-4">
        <!-- Reseller API Configuration -->
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-key-fill text-warning"></i> การเชื่อมต่อ Reseller API (Nami-CH)
                </h5>
                <div class="alert alert-info py-2 small mb-4">
                    <i class="bi bi-info-circle me-1"></i> นำ API Key ที่ได้จากหน้า <a href="https://nami-ch.com/api-keys.php" target="_blank" class="alert-link">API Keys</a> มาใส่ที่นี่ โดยต้องมีสิทธิ์ <code>read</code>, <code>order</code>, <code>renew</code>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Reseller API Key (X-Api-Key) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-shield-lock"></i></span>
                        <input type="password" name="reseller_api_key" id="api_key_input" class="form-control font-monospace" value="<?= htmlspecialchars(getSetting('reseller_api_key', DEFAULT_RESELLER_API_KEY)) ?>" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="api_key_input">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">ระบบจะส่ง API Key ผ่าน HTTP Header <code>X-Api-Key</code> ในทุก Request</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">API Base URL</label>
                    <input type="url" name="reseller_api_url" class="form-control font-monospace" value="<?= htmlspecialchars(getSetting('reseller_api_url', RESELLER_API_BASE_URL)) ?>" required>
                    <div class="form-text">ค่าเริ่มต้น: <code>https://nami-ch.com/reseller-api/</code></div>
                </div>

                <!-- API Status Test Box -->
                <div class="p-3 bg-light rounded-3 border">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold">สถานะการเชื่อมต่อ API:</span>
                        <?php if ($apiCredit !== null): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> เชื่อมต่อสำเร็จ (เครดิต: <?= formatMoney($apiCredit) ?>)</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> ไม่สามารถเชื่อมต่อได้ (<?= htmlspecialchars($apiError) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit Markup Configuration -->
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-tags-fill text-success"></i> การตั้งราคาบวกกำไร (Price Markup)
                </h5>
                <p class="text-muted small mb-4">กำหนดอัตรากำไรที่จะบวกเพิ่มจากราคาทุนของ Reseller API ก่อนแสดงให้ลูกค้าเห็น</p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">บวกกำไรเป็นเปอร์เซ็นต์ (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0" name="markup_percent" class="form-control" value="<?= htmlspecialchars(getSetting('markup_percent', '0')) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">เช่น ใส่ 10 เพื่อบวกเพิ่ม 10% จากทุน</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">บวกกำไรจำนวนเงินคงที่ (บาท)</label>
                        <div class="input-group">
                            <input type="number" step="1" min="0" name="markup_fixed" class="form-control" value="<?= htmlspecialchars(getSetting('markup_fixed', '0')) ?>">
                            <span class="input-group-text">฿</span>
                        </div>
                        <div class="form-text">เช่น ใส่ 50 เพื่อบวกเพิ่ม 50 บาท</div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <div class="small text-muted mb-1">ตัวอย่างการคำนวณ:</div>
                    <div class="small">ราคาทุน API 100 บาท &rarr; ราคาขายลูกค้า = <strong><?= formatMoney(calculateCustomerPrice(100)) ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Payment Settings (PromptPay & Bank) -->
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-qr-code text-primary"></i> ข้อมูลการรับชำระเงิน (พร้อมเพย์ & ธนาคาร)
                </h5>

                <div class="mb-3">
                    <label class="form-label fw-bold">หมายเลขพร้อมเพย์ (เบอร์โทร / เลขบัตรประชาชน)</label>
                    <input type="text" name="promptpay_number" class="form-control font-monospace" value="<?= htmlspecialchars(getSetting('promptpay_number', '0812345678')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อบัญชีพร้อมเพย์</label>
                    <input type="text" name="promptpay_name" class="form-control" value="<?= htmlspecialchars(getSetting('promptpay_name', 'นายพร้อมเพย์ ตัวอย่าง')) ?>">
                </div>

                <hr class="my-3">

                <!-- TrueMoney Angpao Settings -->
                <div class="mb-3">
                    <label class="form-label fw-bold d-flex align-items-center gap-2">
                        <span class="badge bg-warning text-dark">TrueMoney</span>
                        <span>เบอร์ TrueMoney Wallet สำหรับรับซองอั่งเปา (10 หลัก)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
                        <input type="text" name="truemoney_phone" class="form-control font-monospace" placeholder="08xxxxxxxx" value="<?= htmlspecialchars(getSetting('truemoney_phone', '0801234567')) ?>">
                    </div>
                    <div class="form-text text-muted">
                        เบอร์ที่ใช้รับเงินจากระบบ TrueMoney Angpao API (https://api.xpluem.com) เพื่อเติมเงินเข้า Wallet ของลูกค้าอัตโนมัติ
                    </div>
                </div>

                <hr class="my-3">

                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อธนาคาร</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars(getSetting('bank_name', 'กสิกรไทย (KBANK)')) ?>">
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">เลขที่บัญชี</label>
                        <input type="text" name="bank_account_no" class="form-control font-monospace" value="<?= htmlspecialchars(getSetting('bank_account_no', '123-4-56789-0')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ชื่อบัญชีธนาคาร</label>
                        <input type="text" name="bank_account_name" class="form-control" value="<?= htmlspecialchars(getSetting('bank_account_name', 'บจก. โฮสต์โปร คลาวด์')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Site & Contact Information -->
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-globe2 text-info"></i> ข้อมูลเว็บไซต์และการติดต่อ
                </h5>

                <div class="mb-3">
                    <label class="form-label fw-bold">ชื่อเว็บไซต์ (Site Name)</label>
                    <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars(getSetting('site_name', SITE_NAME)) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">สโลแกนเว็บไซต์</label>
                    <input type="text" name="site_slogan" class="form-control" value="<?= htmlspecialchars(getSetting('site_slogan', SITE_SLOGAN)) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">LINE ID สำหรับติดต่อ</label>
                    <input type="text" name="contact_line" class="form-control" value="<?= htmlspecialchars(getSetting('contact_line', '@hostpro')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">อีเมลสำหรับติดต่อ</label>
                    <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars(getSetting('contact_email', 'support@example.com')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary-gradient btn-lg px-5 fw-bold">
            <i class="bi bi-save me-2"></i> บันทึกการตั้งค่าทั้งหมด
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
