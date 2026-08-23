<?php
/**
 * Admin Topup Slips Verification (admin/topups.php)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageTitle = 'ตรวจสอบสลิปการโอนเงิน (Top-up Slips)';
$pdo = getDB();
$adminUser = getLoggedInUser();

// จัดการการอนุมัติ / ปฏิเสธ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $topupId = (int)$_POST['topup_id'];
    $action = $_POST['action']; // 'approve' หรือ 'reject'

    $stmt = $pdo->prepare("SELECT * FROM `topups` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$topupId]);
    $topup = $stmt->fetch();

    if ($topup && $topup['status'] === 'pending') {
        if ($action === 'approve') {
            // อนุมัติ: เติมเงินเข้ากระเป๋าผู้ใช้
            $credited = addUserCredit(
                $topup['user_id'],
                $topup['amount'],
                "อนุมัติเติมเงินผ่านสลิป [{$topup['topup_no']}]",
                'topup',
                $topup['id']
            );

            if ($credited) {
                $upStmt = $pdo->prepare("UPDATE `topups` SET `status` = 'approved', `approved_by` = ?, `approved_at` = NOW() WHERE `id` = ?");
                $upStmt->execute([$adminUser['id'], $topup['id']]);
                setFlash('success', "อนุมัติรายการ {$topup['topup_no']} และเติมเงิน " . formatMoney($topup['amount']) . " ให้สมาชิกเรียบร้อยแล้ว");
            } else {
                setFlash('danger', 'เกิดข้อผิดพลาดในการเติมเงินให้ผู้ใช้');
            }
        } elseif ($action === 'reject') {
            // ปฏิเสธ
            $upStmt = $pdo->prepare("UPDATE `topups` SET `status` = 'rejected', `approved_by` = ?, `approved_at` = NOW() WHERE `id` = ?");
            $upStmt->execute([$adminUser['id'], $topup['id']]);
            setFlash('warning', "ปฏิเสธรายการแจ้งเติมเงิน {$topup['topup_no']} เรียบร้อยแล้ว");
        }
    }
    header('Location: topups.php');
    exit;
}

$statusFilter = clean($_GET['status'] ?? 'pending');
$query = "SELECT t.*, u.username, u.email, u.fullname FROM `topups` t JOIN `users` u ON t.user_id = u.id";
if ($statusFilter === 'pending') {
    $query .= " WHERE t.status = 'pending'";
} elseif ($statusFilter === 'approved') {
    $query .= " WHERE t.status = 'approved'";
} elseif ($statusFilter === 'rejected') {
    $query .= " WHERE t.status = 'rejected'";
}
$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->query($query);
$topups = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan text-success me-2"></i> ตรวจสอบสลิปการโอนเงิน</h1>
        <p class="text-muted mb-0">ตรวจสอบหลักฐานสลิปการชำระเงิน และกดอนุมัติเพื่อเพิ่มเครดิตให้อัตโนมัติ</p>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="topups.php?status=pending" class="btn btn-sm <?= ($statusFilter === 'pending') ? 'btn-warning text-dark fw-bold' : 'btn-light border' ?> rounded-pill px-3">
        <i class="bi bi-clock-history me-1"></i> รอดำเนินการ (Pending)
    </a>
    <a href="topups.php?status=approved" class="btn btn-sm <?= ($statusFilter === 'approved') ? 'btn-success fw-bold' : 'btn-light border' ?> rounded-pill px-3">
        <i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว (Approved)
    </a>
    <a href="topups.php?status=rejected" class="btn btn-sm <?= ($statusFilter === 'rejected') ? 'btn-danger fw-bold' : 'btn-light border' ?> rounded-pill px-3">
        <i class="bi bi-x-circle me-1"></i> ปฏิเสธ (Rejected)
    </a>
    <a href="topups.php?status=all" class="btn btn-sm <?= ($statusFilter === 'all') ? 'btn-dark fw-bold' : 'btn-light border' ?> rounded-pill px-3">
        ทั้งหมด (All)
    </a>
</div>

<!-- Topup Requests Table -->
<div class="card-modern p-4">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>รหัสรายการ</th>
                    <th>สมาชิก</th>
                    <th>ยอดเงินที่โอน</th>
                    <th>ช่องทาง</th>
                    <th>รูปภาพสลิป</th>
                    <th>หมายเหตุ</th>
                    <th>วันที่แจ้ง</th>
                    <th>สถานะ</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($topups)): ?>
                    <?php foreach ($topups as $t): ?>
                        <tr>
                            <td class="font-monospace fw-bold"><?= htmlspecialchars($t['topup_no']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($t['username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($t['fullname'] ?: $t['email']) ?></small>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-5">+<?= formatMoney($t['amount']) ?></span>
                            </td>
                            <td>
                                <?php if ($t['payment_method'] === 'truemoney'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-gift-fill me-1"></i>TrueMoney</span>
                                <?php elseif ($t['payment_method'] === 'promptpay'): ?>
                                    <span class="badge bg-primary">PromptPay</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">ธนาคาร</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['slip_image']): ?>
                                    <a href="../<?= UPLOAD_URL . htmlspecialchars($t['slip_image']) ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                        <i class="bi bi-image me-1"></i> ดูสลิป
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">ไม่มีสลิป (Auto)</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($t['note'] ?: '-') ?></td>
                            <td class="small text-muted"><?= thaiDate($t['created_at']) ?></td>
                            <td><?= statusBadge($t['status']) ?></td>
                            <td class="text-end">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <form method="POST" action="topups.php" class="d-inline-flex gap-1">
                                        <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="return confirm('ยืนยันอนุมัติการเติมเงินจำนวน <?= formatMoney($t['amount']) ?> ใช่หรือไม่?');">
                                            <i class="bi bi-check-lg"></i> อนุมัติ
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="return confirm('ยืนยันปฏิเสธรายการนี้หรือไม่?');">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <small class="text-muted"><?= $t['approved_at'] ? thaiDate($t['approved_at']) : '-' ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">ไม่พบรายการแจ้งเติมเงินในหมวดนี้</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
