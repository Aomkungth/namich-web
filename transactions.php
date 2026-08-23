<?php
/**
 * Financial Transactions History (transactions.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `created_at` DESC");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

$pageTitle = 'ประวัติรายการและการเงิน - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-2"></i> ประวัติรายการและการเงิน</h2>
            <p class="text-muted mb-0">บันทึกธุรกรรมการเติมเงิน การสั่งซื้อบริการ และการต่ออายุทั้งหมด</p>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill">
                ยอดเงินคงเหลือ: <strong><?= formatMoney($user['credit']) ?></strong>
            </span>
        </div>
    </div>

    <div class="card-modern p-4">
        <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>วันที่ / เวลา</th>
                            <th>ประเภท</th>
                            <th>รายละเอียด</th>
                            <th class="text-end">จำนวนเงิน</th>
                            <th class="text-end">ยอดคงเหลือ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): 
                            $isCreditIn = in_array($tx['type'], ['topup', 'admin_adjust', 'refund']);
                            $typeBadges = [
                                'topup'         => '<span class="badge bg-success-subtle text-success">เติมเงิน</span>',
                                'order_hosting' => '<span class="badge bg-primary-subtle text-primary">ซื้อโฮสติ้ง</span>',
                                'order_vps'     => '<span class="badge bg-info-subtle text-info">ซื้อ VPS</span>',
                                'renew_hosting' => '<span class="badge bg-warning-subtle text-warning">ต่ออายุโฮสติ้ง</span>',
                                'renew_vps'     => '<span class="badge bg-warning-subtle text-warning">ต่ออายุ VPS</span>',
                                'admin_adjust'  => '<span class="badge bg-secondary-subtle text-secondary">ปรับยอดโดยแอดมิน</span>',
                                'refund'        => '<span class="badge bg-danger-subtle text-danger">คืนเงิน</span>',
                            ];
                        ?>
                            <tr>
                                <td class="small text-muted"><?= thaiDate($tx['created_at']) ?></td>
                                <td><?= $typeBadges[$tx['type']] ?? ('<span class="badge bg-light text-dark">' . htmlspecialchars($tx['type']) . '</span>') ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($tx['description']) ?></td>
                                <td class="text-end fw-bold <?= $isCreditIn ? 'text-success' : 'text-danger' ?> fs-6">
                                    <?= $isCreditIn ? '+' : '-' ?><?= formatMoney($tx['amount']) ?>
                                </td>
                                <td class="text-end font-monospace text-muted"><?= formatMoney($tx['balance_after']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="display-6 text-muted mb-3"><i class="bi bi-wallet2"></i></div>
                <h5>ยังไม่มีรายการธุรกรรม</h5>
                <p class="text-muted mb-3">เมื่อคุณเติมเงินหรือสั่งซื้อบริการ ประวัติจะแสดงที่นี่</p>
                <a href="topup.php" class="btn btn-primary-gradient rounded-pill px-4">เติมเงิน Wallet ตอนนี้</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
