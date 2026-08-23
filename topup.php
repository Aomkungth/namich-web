<?php
/**
 * Wallet Top-up (topup.php)
 * รองรับ TrueMoney Angpao (อั่งเปาอัตโนมัติ) และ PromptPay QR / โอนผ่านธนาคาร
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$pdo = getDB();

$promptpayNo = getSetting('promptpay_number', '0812345678');
$promptpayName = getSetting('promptpay_name', 'นายพร้อมเพย์ ตัวอย่าง');
$truemoneyPhone = getSetting('truemoney_phone', '0801234567');
$bankName = getSetting('bank_name', 'กสิกรไทย (KBANK)');
$bankAccountNo = getSetting('bank_account_no', '123-4-56789-0');
$bankAccountName = getSetting('bank_account_name', 'บจก. โฮสต์โปร คลาวด์');

$errors = [];
$tmErrors = [];

// 1. จัดการเติมเงินผ่าน TrueMoney Angpao (อัตโนมัติ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup_truemoney') {
    $voucherLink = trim($_POST['voucher_link'] ?? '');

    if (empty($voucherLink)) {
        $tmErrors[] = 'กรุณากรอกลิงก์ซองของขวัญ TrueMoney Wallet';
    } elseif (empty($truemoneyPhone)) {
        $tmErrors[] = 'ระบบยังไม่ได้ตั้งค่าเบอร์ TrueMoney สำหรับรับเงิน กรุณาติดต่อผู้ดูแลระบบ';
    } else {
        // เรียกใช้งาน TrueMoney Angpao API ผ่าน api.xpluem.com
        $tmResult = redeemTrueMoneyVoucher($voucherLink, $truemoneyPhone);

        if (!empty($tmResult['success']) && (int)($tmResult['status'] ?? 0) === 200) {
            $receivedAmount = (float)($tmResult['data']['amount'] ?? 0);
            $senderName = $tmResult['data']['name'] ?? 'ผู้ส่ง TrueMoney';

            if ($receivedAmount > 0) {
                $topupNo = generateRefNo('TM-');

                // บันทึกรายการ Topup สถานะ approved
                $stmtTopup = $pdo->prepare("INSERT INTO `topups` (`user_id`, `topup_no`, `amount`, `payment_method`, `note`, `status`, `approved_at`) VALUES (?, ?, ?, 'truemoney', ?, 'approved', NOW())");
                $stmtTopup->execute([
                    $user['id'],
                    $topupNo,
                    $receivedAmount,
                    "TrueMoney Angpao: ผู้ส่ง {$senderName}"
                ]);
                $topupId = $pdo->lastInsertId();

                // เพิ่มเครดิตเข้า Wallet ผู้ใช้ทันที
                addUserCredit(
                    $user['id'],
                    $receivedAmount,
                    "เติมเงิน TrueMoney Wallet ({$senderName}) [{$topupNo}]",
                    'topup',
                    $topupId
                );

                setFlash('success', "เติมเงินผ่าน TrueMoney สำเร็จ! เติมเงินจำนวน " . formatMoney($receivedAmount) . " เข้ากระเป๋าของคุณเรียบร้อยแล้ว (ผู้ส่ง: {$senderName})");
                header('Location: topup.php');
                exit;
            } else {
                $tmErrors[] = 'ยอดเงินในซองของขวัญไม่ถูกต้องหรือเป็น 0 บาท';
            }
        } else {
            $apiErrorMsg = $tmResult['message'] ?? 'เกิดข้อผิดพลาดในการรับเงินจากซองของขวัญ';
            $tmErrors[] = 'เติมเงินไม่สำเร็จ: ' . htmlspecialchars($apiErrorMsg);
        }
    }
}

// 2. จัดการแจ้งโอนเงินแนบสลิป (PromptPay / Bank Transfer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup_slip') {
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = clean($_POST['payment_method'] ?? 'promptpay');
    $note = clean($_POST['note'] ?? '');

    if ($amount < 10) {
        $errors[] = 'ยอดเติมเงินขั้นต่ำคือ 10.00 บาท';
    }

    $uploadedSlipName = null;
    if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['slip_image']['tmp_name'];
        $fileName = $_FILES['slip_image']['name'];
        $fileSize = $_FILES['slip_image']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = 'ไฟล์สลิปต้องเป็นรูปภาพนามสกุล .jpg, .png หรือ .webp เท่านั้น';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errors[] = 'ขนาดไฟล์สลิปต้องไม่เกิน 5 MB';
        } else {
            $uploadedSlipName = 'slip_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($fileTmp, UPLOAD_DIR . $uploadedSlipName)) {
                $errors[] = 'ไม่สามารถอัปโหลดไฟล์สลิปได้ กรุณาลองใหม่อีกครั้ง';
                $uploadedSlipName = null;
            }
        }
    } else {
        $errors[] = 'กรุณาแนบรูปภาพสลิปการโอนเงินเพื่อการตรวจสอบ';
    }

    if (empty($errors)) {
        $topupNo = generateRefNo('TOP-');
        $stmt = $pdo->prepare("INSERT INTO `topups` (`user_id`, `topup_no`, `amount`, `payment_method`, `slip_image`, `note`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $topupNo, $amount, $paymentMethod, $uploadedSlipName, $note]);

        setFlash('success', 'ส่งคำขอเติมเงินเรียบร้อยแล้ว! เจ้าหน้าที่จะตรวจสอบสลิปและปรับยอดเงินให้โดยเร็วที่สุด');
        header('Location: topup.php');
        exit;
    }
}

// ดึงประวัติการเติมเงินล่าสุด 10 รายการ
$stmtTopups = $pdo->prepare("SELECT * FROM `topups` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT 10");
$stmtTopups->execute([$user['id']]);
$topups = $stmtTopups->fetchAll();

$pageTitle = 'เติมเงินเข้ากระเป๋า Wallet - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-wallet2 text-primary me-2"></i> เติมเงินเข้ากระเป๋า (Wallet Top-up)</h2>
            <p class="text-muted mb-0">เลือกช่องทางการเติมเงินที่คุณสะดวก เงินจะเข้ากระเป๋าเพื่อใช้สั่งซื้อหรือต่ออายุบริการ</p>
        </div>
        <div class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill">
            ยอดเงินปัจจุบัน: <strong><?= formatMoney($user['credit']) ?></strong>
        </div>
    </div>

    <!-- Navigation Tabs for Top-up Methods -->
    <ul class="nav nav-pills mb-4 gap-2" id="topupTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-bold d-flex align-items-center gap-2" id="truemoney-tab" data-bs-toggle="pill" data-bs-target="#truemoney-pane" type="button" role="tab">
                <span class="badge bg-warning text-dark">Auto</span>
                <i class="bi bi-gift-fill text-warning"></i> TrueMoney Wallet (ซองของขวัญ)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-bold d-flex align-items-center gap-2" id="promptpay-tab" data-bs-toggle="pill" data-bs-target="#promptpay-pane" type="button" role="tab">
                <i class="bi bi-qr-code-scan"></i> พร้อมเพย์ (PromptPay QR)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-bold d-flex align-items-center gap-2" id="bank-tab" data-bs-toggle="pill" data-bs-target="#bank-pane" type="button" role="tab">
                <i class="bi bi-bank"></i> โอนผ่านบัญชีธนาคาร
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="topupTabContent">
        <!-- ============================================== -->
        <!-- TAB 1: TrueMoney Angpao (Instant & Automatic)  -->
        <!-- ============================================== -->
        <div class="tab-pane fade show active" id="truemoney-pane" role="tabpanel">
            <div class="row g-4">
                <!-- TrueMoney Form -->
                <div class="col-lg-7">
                    <div class="card-modern p-4 p-md-5 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="stat-icon warning">
                                <i class="bi bi-gift-fill"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="fw-bold mb-0">เติมเงินด้วยซองของขวัญ TrueMoney</h4>
                                    <span class="badge bg-success small">ระบบอัตโนมัติ 24 ชม.</span>
                                </div>
                                <p class="text-muted small mb-0">เงินเข้ากระเป๋าทันที ไม่ต้องรอแอดมินอนุมัติ</p>
                            </div>
                        </div>

                        <?php if (!empty($tmErrors)): ?>
                            <div class="alert alert-danger py-2 small shadow-sm mb-4">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($tmErrors as $err): ?>
                                        <li><?= $err ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="topup.php">
                            <input type="hidden" name="action" value="topup_truemoney">

                            <div class="mb-4">
                                <label class="form-label fw-bold">ลิงก์ซองของขวัญ TrueMoney (Gift Link) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
                                    <input type="text" name="voucher_link" class="form-control" placeholder="https://gift.truemoney.com/campaign/?v=..." required autofocus>
                                </div>
                                <div class="form-text text-muted">
                                    วางลิงก์ซองของขวัญที่สร้างจากแอป TrueMoney Wallet ได้ทั้งลิงก์เต็ม หรือรหัสต่อท้าย <code>?v=...</code>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark py-3">
                                    <i class="bi bi-check-circle-fill me-2"></i> ยืนยันการเติมเงิน TrueMoney
                                </button>
                            </div>

                            <div class="p-3 bg-light rounded-3 border small text-muted">
                                <i class="bi bi-info-circle text-primary me-1"></i> ระบบจะดึงเงินจากซองของขวัญและเพิ่มเครดิตเข้ากระเป๋าของคุณทันทีตามจำนวนเงินจริงในซอง
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TrueMoney Instruction Guide -->
                <div class="col-lg-5">
                    <div class="card-modern p-4 p-md-5 h-100 bg-white">
                        <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-question-circle-fill text-warning"></i> วิธีสร้างซองของขวัญในแอป TrueMoney
                        </h5>

                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge bg-warning text-dark rounded-circle p-2 px-3 fw-bold">1</span>
                                <div>
                                    <strong class="d-block text-dark">เปิดแอป TrueMoney</strong>
                                    <small class="text-muted">เข้าเมนู <strong>"โอนเงิน"</strong> &rarr; เลือก <strong>"ส่งซองของขวัญ"</strong></small>
                                </div>
                            </div>

                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge bg-warning text-dark rounded-circle p-2 px-3 fw-bold">2</span>
                                <div>
                                    <strong class="d-block text-dark">กำหนดจำนวนเงิน</strong>
                                    <small class="text-muted">กรอกยอดเงินที่ต้องการเติมเข้าเว็บ</small>
                                </div>
                            </div>

                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge bg-warning text-dark rounded-circle p-2 px-3 fw-bold">3</span>
                                <div>
                                    <strong class="d-block text-dark">ตั้งค่าการรับซอง (สำคัญ)</strong>
                                    <small class="text-muted">
                                        เลือก <strong>"แบ่งจำนวนเงินเท่ากัน"</strong> และใส่จำนวนคนรับซองเป็น <strong>"1 คน"</strong>
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex gap-3 align-items-start">
                                <span class="badge bg-warning text-dark rounded-circle p-2 px-3 fw-bold">4</span>
                                <div>
                                    <strong class="d-block text-dark">คัดลอกลิงก์มาเติม</strong>
                                    <small class="text-muted">กดสร้างซอง แล้วกด <strong>"คัดลอกลิงก์"</strong> นำมาวางในช่องซ้ายมือแล้วกดยืนยัน</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 2: PromptPay QR Top-up                     -->
        <!-- ============================================== -->
        <div class="tab-pane fade" id="promptpay-pane" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card-modern p-4 p-md-5 text-center h-100">
                        <span class="badge bg-primary px-3 py-1 rounded-pill mb-3">Thai QR PromptPay</span>
                        <div class="bg-white p-3 d-inline-block rounded-3 shadow-sm border mb-3">
                            <img src="https://promptpay.io/<?= urlencode(str_replace('-', '', $promptpayNo)) ?>.png" 
                                 alt="PromptPay QR Code" class="img-fluid" style="max-width: 220px; height: auto;">
                        </div>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($promptpayName) ?></h5>
                        <p class="text-primary fw-bold font-monospace fs-5 mb-1"><?= htmlspecialchars($promptpayNo) ?></p>
                        <button class="btn btn-sm btn-outline-secondary btn-copy rounded-pill px-3" data-copy="<?= htmlspecialchars($promptpayNo) ?>">
                            <i class="bi bi-clipboard me-1"></i> คัดลอกหมายเลขพร้อมเพย์
                        </button>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card-modern p-4 p-md-5 h-100">
                        <h4 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i> แจ้งชำระเงินพร้อมเพย์</h4>
                        <p class="text-muted small mb-4">สแกน QR Code โอนเงิน แล้วแนบสลิปด้านล่าง</p>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger py-2 small shadow-sm mb-4">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="topup.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="topup_slip">
                            <input type="hidden" name="payment_method" value="promptpay">

                            <div class="mb-3">
                                <label class="form-label fw-bold">ยอดเงินที่โอน (บาท) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-currency-exchange"></i></span>
                                    <input type="number" step="0.01" min="10" name="amount" id="ppAmount" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" required>
                                    <span class="input-group-text bg-light">บาท</span>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-pp-amount" data-amount="100">+ 100 ฿</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-pp-amount" data-amount="300">+ 300 ฿</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-pp-amount" data-amount="500">+ 500 ฿</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-pp-amount" data-amount="1000">+ 1,000 ฿</button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">แนบรูปภาพสลิปโอนเงิน <span class="text-danger">*</span></label>
                                <input type="file" name="slip_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                                <div class="form-text">รองรับไฟล์ภาพ JPG, PNG, WEBP ขนาดไม่เกิน 5 MB</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">หมายเหตุเพิ่มเติม (ไม่บังคับ)</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="เช่น เวลาโอน 14:30 น."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-gradient btn-lg fw-bold">
                                    <i class="bi bi-send-fill me-1"></i> แจ้งชำระเงินพร้อมเพย์
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 3: Bank Transfer Top-up                    -->
        <!-- ============================================== -->
        <div class="tab-pane fade" id="bank-pane" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card-modern p-4 p-md-5 h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-bank text-primary me-2"></i> บัญชีธนาคารสำหรับโอนเงิน</h5>
                        <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-2 mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">ธนาคาร:</span>
                                <span class="fw-bold"><?= htmlspecialchars($bankName) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">เลขที่บัญชี:</span>
                                <span class="fw-bold font-monospace text-primary fs-6"><?= htmlspecialchars($bankAccountNo) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">ชื่อบัญชี:</span>
                                <span class="fw-semibold"><?= htmlspecialchars($bankAccountName) ?></span>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100 rounded-pill btn-copy" data-copy="<?= htmlspecialchars(str_replace('-', '', $bankAccountNo)) ?>">
                            <i class="bi bi-clipboard me-1"></i> คัดลอกเลขที่บัญชี
                        </button>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card-modern p-4 p-md-5 h-100">
                        <h4 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i> แจ้งโอนเงินผ่านธนาคาร</h4>
                        <p class="text-muted small mb-4">โอนเงินเข้าบัญชีธนาคาร แล้วแนบหลักฐานสลิปด้านล่าง</p>

                        <form method="POST" action="topup.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="topup_slip">
                            <input type="hidden" name="payment_method" value="bank_transfer">

                            <div class="mb-3">
                                <label class="form-label fw-bold">ยอดเงินที่โอน (บาท) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-currency-exchange"></i></span>
                                    <input type="number" step="0.01" min="10" name="amount" class="form-control form-control-lg fw-bold text-primary" placeholder="0.00" required>
                                    <span class="input-group-text bg-light">บาท</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">แนบรูปภาพสลิปโอนเงิน <span class="text-danger">*</span></label>
                                <input type="file" name="slip_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">หมายเหตุเพิ่มเติม (ไม่บังคับ)</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="เช่น โอนจากธนาคารไทยพาณิชย์ เวลา 15:00 น."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-gradient btn-lg fw-bold">
                                    <i class="bi bi-send-fill me-1"></i> แจ้งชำระเงินธนาคาร
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Topup Requests Table -->
    <div class="card-modern p-4 mt-5">
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-secondary me-2"></i> ประวัติการเติมเงินของคุณ</h5>
        <?php if (!empty($topups)): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>รหัสรายการ</th>
                            <th>ยอดเงิน</th>
                            <th>ช่องทาง</th>
                            <th>สลิป / รายละเอียด</th>
                            <th>วันที่ทำรายการ</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topups as $t): 
                            $methodBadge = '<span class="badge bg-primary">PromptPay</span>';
                            if ($t['payment_method'] === 'truemoney') {
                                $methodBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-gift-fill me-1"></i>TrueMoney</span>';
                            } elseif ($t['payment_method'] === 'bank_transfer') {
                                $methodBadge = '<span class="badge bg-info text-dark"><i class="bi bi-bank me-1"></i>ธนาคาร</span>';
                            }
                        ?>
                            <tr>
                                <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($t['topup_no']) ?></td>
                                <td class="fw-bold text-success fs-6">+<?= formatMoney($t['amount']) ?></td>
                                <td><?= $methodBadge ?></td>
                                <td>
                                    <?php if ($t['slip_image']): ?>
                                        <a href="<?= UPLOAD_URL . htmlspecialchars($t['slip_image']) ?>" target="_blank" class="btn btn-sm btn-light border py-1 px-2">
                                            <i class="bi bi-image me-1"></i> ดูสลิป
                                        </a>
                                    <?php elseif (!empty($t['note'])): ?>
                                        <span class="small text-muted"><?= htmlspecialchars($t['note']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= thaiDate($t['created_at']) ?></td>
                                <td><?= statusBadge($t['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-3 mb-0">ยังไม่มีประวัติการเติมเงิน</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Quick amount buttons for PromptPay
    const ppInput = document.getElementById('ppAmount');
    if (ppInput) {
        document.querySelectorAll('.btn-pp-amount').forEach(btn => {
            btn.addEventListener('click', () => {
                ppInput.value = btn.getAttribute('data-amount');
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
