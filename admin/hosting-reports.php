<?php
/**
 * Admin: ระบบแจ้งปัญหา (Hosting Reports)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

requireAdmin();
$pdo = getDB();
$api = new NamiResellerAPI();

$action = $_GET['action'] ?? 'list';
$pageTitle = 'ระบบแจ้งปัญหา (Hosting Reports) - Admin';

if ($action === 'view') {
    $reportId = (int)($_GET['id'] ?? 0);
    $res = $api->getReport($reportId);
    if (!$res || empty($res['ok']) || empty($res['data'])) {
        setFlash('danger', 'ไม่พบข้อมูลรายงาน หรือเกิดข้อผิดพลาดจาก API: ' . ($res['error'] ?? ''));
        header('Location: hosting-reports.php');
        exit;
    }
    $report = $res['data'];
    $pageTitle = 'รายละเอียดแจ้งปัญหา: ' . htmlspecialchars($report['report_no']);
} else {
    $statusFilter = $_GET['status'] ?? '';
    $res = $api->getReports($statusFilter);
    $reports = [];
    if ($res && !empty($res['ok'])) {
        $reports = $res['data'] ?? [];
    } else {
        $apiError = $res['error'] ?? 'Unknown API Error';
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">
        <i class="bi bi-chat-square-text text-primary me-2"></i><?= htmlspecialchars($pageTitle) ?>
    </h3>
    <?php if ($action === 'view'): ?>
        <a href="hosting-reports.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> กลับหน้าแรก</a>
    <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>
    <div class="card-modern p-4 mb-4">
        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">กรองสถานะ:</label>
                <select name="status" class="form-select">
                    <option value="">-- ทั้งหมด --</option>
                    <option value="reported" <?= ($statusFilter === 'reported') ? 'selected' : '' ?>>Reported (แจ้งใหม่)</option>
                    <option value="acknowledged" <?= ($statusFilter === 'acknowledged') ? 'selected' : '' ?>>Acknowledged (รับเรื่องแล้ว)</option>
                    <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>In Progress (กำลังดำเนินการ)</option>
                    <option value="resolved" <?= ($statusFilter === 'resolved') ? 'selected' : '' ?>>Resolved (แก้ไขแล้ว)</option>
                    <option value="closed" <?= ($statusFilter === 'closed') ? 'selected' : '' ?>>Closed (ปิดงาน)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> กรอง</button>
            </div>
        </form>

        <?php if (!empty($apiError)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>ดึงข้อมูลจาก API ไม่สำเร็จ: <?= htmlspecialchars($apiError) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ (No.)</th>
                        <th>หมวดหมู่</th>
                        <th>สถานะ</th>
                        <th>Service ID</th>
                        <th>วันที่แจ้ง</th>
                        <th class="text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลการแจ้งปัญหา</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($r['report_no']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['category']) ?></span></td>
                                <td>
                                    <?php
                                        $s = $r['status'];
                                        $c = 'secondary';
                                        $label = strtoupper($s);
                                        if ($s === 'reported') { $c = 'danger'; $label = 'รอตรวจสอบ (Reported)'; }
                                        elseif ($s === 'acknowledged') { $c = 'warning text-dark'; $label = 'รับเรื่องแล้ว (Acknowledged)'; }
                                        elseif ($s === 'in_progress') { $c = 'info text-dark'; $label = 'กำลังดำเนินการ (In Progress)'; }
                                        elseif ($s === 'resolved') { $c = 'success'; $label = 'แก้ไขเรียบร้อย (Resolved)'; }
                                        elseif ($s === 'closed') { $c = 'dark'; $label = 'ปิดงาน (Closed)'; }
                                    ?>
                                    <span class="badge bg-<?= $c ?>"><?= $label ?></span>
                                </td>
                                <td><?= (int)$r['service_id'] ?></td>
                                <td><?= thaiDate($r['created_at']) ?></td>
                                <td class="text-end">
                                    <a href="hosting-reports.php?action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'view'): ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-modern p-4">
                <h5 class="fw-bold mb-4 border-bottom pb-3">รายละเอียดการแจ้งปัญหา (<?= htmlspecialchars($report['report_no']) ?>)</h5>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">User ID:</div>
                    <div class="col-sm-9 fw-semibold"><?= (int)($report['user_id'] ?? 0) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">Service ID:</div>
                    <div class="col-sm-9 fw-semibold"><?= (int)($report['service_id'] ?? 0) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">หมวดหมู่:</div>
                    <div class="col-sm-9"><span class="badge bg-secondary"><?= htmlspecialchars($report['category']) ?></span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">สถานะ:</div>
                    <div class="col-sm-9">
                        <span class="badge bg-primary fs-6"><?= strtoupper($report['status']) ?></span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">รายละเอียดปัญหา:</div>
                    <div class="col-sm-9">
                        <div class="p-3 bg-light rounded-3 border">
                            <?= nl2br(htmlspecialchars($report['description'] ?? 'ไม่มีรายละเอียด')) ?>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">วันที่แจ้ง (Created):</div>
                    <div class="col-sm-9"><?= thaiDate($report['created_at']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-3 text-muted">อัปเดตล่าสุด:</div>
                    <div class="col-sm-9"><?= thaiDate($report['updated_at']) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
