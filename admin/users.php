<?php
/**
 * Admin User Management (admin/users.php)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageTitle = 'จัดการสมาชิก & ยอดเงินเครดิต';
$pdo = getDB();

// ปรับยอดเงิน (Add / Deduct Credit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_credit') {
    $targetUserId = (int)$_POST['user_id'];
    $adjustType = clean($_POST['adjust_type']); // 'add' หรือ 'deduct'
    $amount = (float)$_POST['amount'];
    $reason = clean($_POST['reason'] ?? 'ปรับยอดโดยผู้ดูแลระบบ');

    if ($amount > 0 && $targetUserId > 0) {
        if ($adjustType === 'add') {
            addUserCredit($targetUserId, $amount, "แอดมินเพิ่มเครดิต: {$reason}", 'admin_adjust');
            setFlash('success', 'เพิ่มเครดิตให้สมาชิกเรียบร้อยแล้ว');
        } elseif ($adjustType === 'deduct') {
            $deducted = deductUserCredit($targetUserId, $amount, "แอดมินหักเครดิต: {$reason}", 'admin_adjust');
            if ($deducted) {
                setFlash('success', 'หักเครดิตสมาชิกเรียบร้อยแล้ว');
            } else {
                setFlash('danger', 'ไม่สามารถหักเครดิตได้ (ยอดเงินคงเหลือของสมาชิกไม่พอ)');
            }
        }
    }
    header('Location: users.php');
    exit;
}

// เปลี่ยนสถานะผู้ใช้ (Toggle Role / Ban Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_status') {
    $targetUserId = (int)$_POST['user_id'];
    $newRole = in_array($_POST['role'] ?? '', ['user', 'admin']) ? $_POST['role'] : 'user';
    $newStatus = in_array($_POST['status'] ?? '', ['active', 'banned']) ? $_POST['status'] : 'active';

    if ($targetUserId > 0) {
        $stmt = $pdo->prepare("UPDATE `users` SET `role` = ?, `status` = ? WHERE `id` = ?");
        $stmt->execute([$newRole, $newStatus, $targetUserId]);
        setFlash('success', 'อัปเดตสถานะสมาชิกเรียบร้อยแล้ว');
    }
    header('Location: users.php');
    exit;
}

$search = clean($_GET['q'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` LIKE ? OR `email` LIKE ? OR `fullname` LIKE ? OR `phone` LIKE ? ORDER BY `created_at` DESC");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term, $term]);
} else {
    $stmt = $pdo->query("SELECT * FROM `users` ORDER BY `created_at` DESC");
}
$users = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i> จัดการสมาชิก (User Management)</h1>
        <p class="text-muted mb-0">ดูรายชื่อสมาชิก เพิ่ม/ลดเครดิต และจัดการสิทธิ์ผู้ดูแลระบบ</p>
    </div>
</div>

<!-- Search Bar -->
<div class="card-modern p-3 mb-4">
    <form method="GET" action="users.php" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="ค้นหาตาม username, email, ชื่อ หรือเบอร์โทร..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> ค้นหา</button>
        </div>
        <?php if (!empty($search)): ?>
            <div class="col-md-2">
                <a href="users.php" class="btn btn-outline-secondary w-100">ล้างการค้นหา</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Users Table -->
<div class="card-modern p-4">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อผู้ใช้ / อีเมล</th>
                    <th>ชื่อ-นามสกุล / เบอร์โทร</th>
                    <th>เครดิตคงเหลือ</th>
                    <th>สิทธิ์ (Role)</th>
                    <th>สถานะ</th>
                    <th>วันที่สมัคร</th>
                    <th class="text-end">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-muted">#<?= $u['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['username']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($u['fullname'] ?: '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($u['phone'] ?: '-') ?></small>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-6"><?= formatMoney($u['credit']) ?></span>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= statusBadge($u['status']) ?>
                            </td>
                            <td class="small text-muted"><?= thaiDate($u['created_at'], false) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#creditModal" 
                                        data-user-id="<?= $u['id'] ?>" 
                                        data-username="<?= htmlspecialchars($u['username']) ?>" 
                                        data-credit="<?= $u['credit'] ?>">
                                    <i class="bi bi-wallet2 me-1"></i> ปรับเงิน
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal"
                                        data-user-id="<?= $u['id'] ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-role="<?= $u['role'] ?>"
                                        data-status="<?= $u['status'] ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลสมาชิกที่ค้นหา</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal ปรับยอดเงิน -->
<div class="modal fade" id="creditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="adjust_credit">
                <input type="hidden" name="user_id" id="modal_user_id">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 text-success me-2"></i> ปรับยอดเงินเครดิต</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-3">สมาชิก: <strong id="modal_username" class="text-primary"></strong> (คงเหลือปัจจุบัน: <span id="modal_current_credit" class="fw-bold text-success"></span> บาท)</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทการปรับยอด</label>
                        <select name="adjust_type" class="form-select" required>
                            <option value="add">➕ เพิ่มเงินเข้า Wallet (+)</option>
                            <option value="deduct">➖ หักเงินออกจาก Wallet (-)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">จำนวนเงิน (บาท)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">เหตุผลในการปรับยอด</label>
                        <input type="text" name="reason" class="form-control" placeholder="เช่น โอนเงินผ่านบัญชีตรง, คืนเงินพิเศษ ฯลฯ" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gradient px-4 fw-bold">บันทึกการปรับยอด</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal แก้ไขสิทธิ์และสถานะ -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="update_user_status">
                <input type="hidden" name="user_id" id="edit_modal_user_id">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-primary me-2"></i> แก้ไขสิทธิ์และสถานะสมาชิก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-3">สมาชิก: <strong id="edit_modal_username" class="text-primary"></strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role)</label>
                        <select name="role" id="edit_modal_role" class="form-select">
                            <option value="user">User (สมาชิกทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">สถานะบัญชี (Status)</label>
                        <select name="status" id="edit_modal_status" class="form-select">
                            <option value="active">Active (ใช้งานได้ปกติ)</option>
                            <option value="banned">Banned (ระงับการใช้งาน)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Credit Modal Data Setter
    const creditModal = document.getElementById('creditModal');
    creditModal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        document.getElementById('modal_user_id').value = btn.getAttribute('data-user-id');
        document.getElementById('modal_username').textContent = btn.getAttribute('data-username');
        document.getElementById('modal_current_credit').textContent = parseFloat(btn.getAttribute('data-credit')).toLocaleString('th-TH', {minimumFractionDigits: 2});
    });

    // Edit User Modal Data Setter
    const editModal = document.getElementById('editUserModal');
    editModal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        document.getElementById('edit_modal_user_id').value = btn.getAttribute('data-user-id');
        document.getElementById('edit_modal_username').textContent = btn.getAttribute('data-username');
        document.getElementById('edit_modal_role').value = btn.getAttribute('data-role');
        document.getElementById('edit_modal_status').value = btn.getAttribute('data-status');
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
