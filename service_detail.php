<?php
/**
 * Service Detail & Renewal (service_detail.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api_helper.php';

requireLogin();
$user = getLoggedInUser();
if (!$user || !is_array($user)) { session_destroy(); header('Location: login.php'); exit; }
$pdo = getDB();
$api = new NamiResellerAPI();

$serviceId = (int)($_GET['id'] ?? 0);

// ดึงข้อมูลบริการ (ถ้าไม่ใช่ admin จะดูได้เฉพาะของตัวเอง)
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$serviceId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = ? AND `user_id` = ? LIMIT 1");
    $stmt->execute([$serviceId, $user['id']]);
}
$service = $stmt->fetch();

if (!$service) {
    setFlash('danger', 'ไม่พบบริการที่ต้องการ หรือคุณไม่มีสิทธิ์เข้าถึง');
    header('Location: services.php');
    exit;
}

$isHosting = ($service['service_type'] === 'hosting');
$apiServiceId = $service['api_service_id'];
$liveData = null;

// ดึงข้อมูลสดจาก Reseller API
if (!$apiServiceId) {
    // ถ้าไม่มี api_service_id ในฐานข้อมูล ให้ค้นหาจากรายการทั้งหมดด้วยโดเมน
    if ($isHosting) {
        $allRes = $api->getServices();
        if ($allRes && !empty($allRes['ok']) && !empty($allRes['data'])) {
            foreach ($allRes['data'] as $sData) {
                if (strtolower(trim($sData['domain'])) === strtolower(trim($service['domain_or_hostname']))) {
                    $apiServiceId = $sData['id'];
                    $liveData = $sData;
                    // อัปเดตลง DB ให้มี api_service_id
                    $pdo->prepare("UPDATE `services` SET `api_service_id` = ? WHERE `id` = ?")->execute([$apiServiceId, $serviceId]);
                    break;
                }
            }
        } else {
            $apiErrorMsg = $allRes['error'] ?? 'Unknown API Error in getServices';
        }
    } else {
        $allRes = $api->getVPS();
        if ($allRes && !empty($allRes['ok']) && !empty($allRes['data'])) {
            foreach ($allRes['data'] as $vData) {
                if (strtolower(trim($vData['hostname'])) === strtolower(trim($service['domain_or_hostname']))) {
                    $apiServiceId = $vData['id'];
                    $liveData = $vData;
                    $pdo->prepare("UPDATE `services` SET `api_service_id` = ? WHERE `id` = ?")->execute([$apiServiceId, $serviceId]);
                    break;
                }
            }
        } else {
            $apiErrorMsg = $allRes['error'] ?? 'Unknown API Error in getVPS';
        }
    }
}

if ($apiServiceId && !$liveData) {
    if ($isHosting) {
        $apiRes = $api->getService($apiServiceId);
        if ($apiRes && !empty($apiRes['ok'])) {
            $liveData = $apiRes['data'] ?? [];
        } else {
            $apiErrorMsg = $apiRes['error'] ?? 'Unknown API Error';
        }
    } else {
        $apiRes = $api->getVPSService($apiServiceId);
        if ($apiRes && !empty($apiRes['ok'])) {
            $liveData = $apiRes['data'] ?? [];
        } else {
            $apiErrorMsg = $apiRes['error'] ?? 'Unknown API Error';
        }
    }

    // Auto-sync IP Address and Nameservers back to local DB if they are missing
    if ($liveData) {
        $dbUpdated = false;
        $updates = [];
        $params = [];
        
        if (empty($service['ip_address']) && !empty($liveData['ip_address'])) {
            $updates[] = "`ip_address` = ?";
            $params[] = $liveData['ip_address'];
            $service['ip_address'] = $liveData['ip_address'];
            $dbUpdated = true;
        }
        
        if ($isHosting) {
            if (empty($service['server_name']) && !empty($liveData['server_name'])) {
                $updates[] = "`server_name` = ?";
                $params[] = $liveData['server_name'];
                $service['server_name'] = $liveData['server_name'];
                $dbUpdated = true;
            }
            if (empty($service['nameservers']) && !empty($liveData['nameserver1']) && !empty($liveData['nameserver2'])) {
                $nsCombo = $liveData['nameserver1'] . ',' . $liveData['nameserver2'];
                $updates[] = "`nameservers` = ?";
                $params[] = $nsCombo;
                $service['nameservers'] = $nsCombo;
                $dbUpdated = true;
            }
        }

        if ($dbUpdated) {
            $params[] = $serviceId;
            $setSql = implode(", ", $updates);
            $pdo->prepare("UPDATE `services` SET {$setSql} WHERE `id` = ?")->execute($params);
        }
    }
}

// ดึงประวัติการแจ้งปัญหาของ Service นี้
$serviceReports = [];
if ($apiServiceId) {
    $reportsRes = $api->getReports();
    if ($reportsRes && !empty($reportsRes['ok']) && !empty($reportsRes['data'])) {
        foreach ($reportsRes['data'] as $r) {
            if ((int)$r['service_id'] === (int)$apiServiceId) {
                $serviceReports[] = $r;
            }
        }
    }
}

// จัดการการกดต่ออายุ (Renew Service)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew_service') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'เซสชั่นหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        header('Location: service_detail.php?id=' . $service['id']);
        exit;
    }

    $renewPrice = (float)$service['price'];

    if ($user['credit'] < $renewPrice) {
        setFlash('danger', 'ยอดเงินในกระเป๋าไม่เพียงพอสำหรับการต่ออายุ (ต้องการ ' . formatMoney($renewPrice) . ' แต่มี ' . formatMoney($user['credit']) . ')');
        header('Location: service_detail.php?id=' . $service['id']);
        exit;
    }

    // 1. ตัดเงินจาก Wallet
    $desc = "ต่ออายุบริการ {$service['package_name']} ({$service['billing_cycle']}) — {$service['domain_or_hostname']}";
    $deducted = deductUserCredit($user['id'], $renewPrice, $desc, $isHosting ? 'renew_hosting' : 'renew_vps', $service['id']);

    if (!$deducted) {
        setFlash('danger', 'เกิดข้อผิดพลาดในการตัดยอดเงิน กรุณาลองใหม่อีกครั้ง');
    } else {
        // 2. เรียก API Renew
        $renewRes = null;
        if ($apiServiceId) {
            if ($isHosting) {
                $renewRes = $api->renewHosting($apiServiceId);
            } else {
                $renewRes = $api->renewVPS($apiServiceId);
            }
        } else {
            // กรณีไม่มี api_service_id ให้อัปเดตสถานะสำเร็จ
            $renewRes = ['ok' => true];
        }

        if ($renewRes && !empty($renewRes['ok'])) {
            // คำนวณวันหมดอายุรอบถัดไป
            $currentDue = strtotime($service['next_due_date'] ?: date('Y-m-d'));
            if ($currentDue < time()) {
                $currentDue = time(); // ถ้าหมดอายุไปแล้ว ให้นับจากวันนี้
            }

            $interval = ($service['billing_cycle'] === 'yearly') ? '+1 year' : '+1 month';
            $newDueDate = date('Y-m-d', strtotime($interval, $currentDue));

            $updateStmt = $pdo->prepare("UPDATE `services` SET `next_due_date` = ?, `status` = 'active' WHERE `id` = ?");
            $updateStmt->execute([$newDueDate, $service['id']]);

            setFlash('success', 'ต่ออายุบริการสำเร็จเรียบร้อยแล้ว! วันหมดอายุใหม่คือ ' . thaiDate($newDueDate, false));
            header('Location: service_detail.php?id=' . $service['id']);
            exit;
        } else {
            // หาก API ล้มเหลว -> คืนเงิน
            $apiError = $renewRes['error'] ?? 'API Renew Error';
            addUserCredit($user['id'], $renewPrice, "คืนเงินการต่ออายุบริการล้มเหลว: {$apiError}", 'refund', $service['id']);
            setFlash('danger', 'เกิดข้อผิดพลาดจาก API: ' . $apiError . ' (ระบบได้คืนเงินเข้ากระเป๋าให้คุณแล้ว)');
            header('Location: service_detail.php?id=' . $service['id']);
            exit;
        }
    }
}
// จัดการส่งรีพอร์ต (Report Issue)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_report') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'เซสชั่นหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        header('Location: service_detail.php?id=' . $service['id']);
        exit;
    }

    $category = $_POST['category'] ?? 'other';
    $description = trim($_POST['description'] ?? '');

    if (empty($description)) {
        setFlash('danger', 'กรุณาระบุรายละเอียดปัญหา');
        header('Location: service_detail.php?id=' . $service['id']);
        exit;
    }

    if ($apiServiceId) {
        // ส่งไป Nami API ด้วย action report_hosting
        $reportRes = $api->reportHosting($apiServiceId, $category, $description);
        
        if ($reportRes && !empty($reportRes['ok'])) {
            setFlash('success', 'ส่งเรื่องแจ้งปัญหาเรียบร้อยแล้ว (Report No: ' . htmlspecialchars($reportRes['data']['report_no'] ?? 'N/A') . ')');
        } else {
            setFlash('danger', 'ไม่สามารถส่งเรื่องแจ้งปัญหาได้: ' . htmlspecialchars($reportRes['error'] ?? 'API Error'));
        }
    } else {
        setFlash('danger', 'ไม่พบ Service ID ในระบบ API ไม่สามารถแจ้งปัญหาได้');
    }
    header('Location: service_detail.php?id=' . $service['id']);
    exit;
}

$pageTitle = 'รายละเอียดบริการ ' . htmlspecialchars($service['domain_or_hostname']) . ' - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<style>
.blur-text {
    filter: blur(4px);
    transition: filter 0.3s ease;
    cursor: pointer;
}
.blur-text:hover {
    filter: blur(2px);
}
</style>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="services.php" class="text-decoration-none">บริการของฉัน</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($service['domain_or_hostname']) ?></li>
        </ol>
    </nav>

    <!-- Header Card -->
    <div class="card-modern p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon <?= $isHosting ? 'primary' : 'purple' ?>">
                    <i class="bi <?= $isHosting ? 'bi-server' : 'bi-cpu' ?>"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($service['domain_or_hostname']) ?></h3>
                        <?= statusBadge($service['status']) ?>
                    </div>
                    <span class="text-muted small">
                        <?= $isHosting ? 'DirectAdmin Web Hosting' : 'Cloud VPS Server' ?> &bull; 
                        แพ็กเกจ: <strong class="text-dark"><?= htmlspecialchars($service['package_name']) ?></strong>
                    </span>
                </div>
            </div>

            <!-- Buttons -->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger px-3 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#reportModal">
                    <i class="bi bi-exclamation-triangle me-1"></i> แจ้งปัญหา
                </button>
                <button type="button" class="btn btn-primary-gradient px-4 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#renewModal">
                    <i class="bi bi-arrow-clockwise me-1"></i> ต่ออายุบริการ (<?= formatMoney($service['price']) ?>)
                </button>
            </div>
        </div>
    </div>

    <?php 
    $extraData = json_decode($service['extra_info'] ?? '{}', true) ?: [];
    $servicePassword = $extraData['password'] ?? $liveData['password'] ?? ''; 
    ?>

    <div class="row g-4">
        <!-- Main Details Column -->
        <div class="col-lg-8">
            <?php if ($isHosting): ?>
                <!-- Hosting Overview Card -->
                <div class="card-modern p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i> ข้อมูลการเข้าใช้งาน DirectAdmin</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">ชื่อโดเมนหลัก (Domain)</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-6 text-primary"><?= htmlspecialchars($service['domain_or_hostname']) ?></span>
                                <a href="http://<?= htmlspecialchars($service['domain_or_hostname']) ?>" target="_blank" class="btn btn-sm btn-light border p-1" title="เปิดเว็บไซต์">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">ชื่อผู้ใช้ (DirectAdmin Username)</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-6"><?= htmlspecialchars($service['server_username'] ?: '-') ?></span>
                                <?php if ($service['server_username']): ?>
                                    <button class="btn btn-sm btn-light border p-1 btn-copy" data-copy="<?= htmlspecialchars($service['server_username']) ?>" title="คัดลอก">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($servicePassword): ?>
                        <div class="col-md-6">
                            <label class="text-muted small">รหัสผ่าน (Password)</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-6 blur-text" id="da_pass"><?= htmlspecialchars($servicePassword) ?></span>
                                <button class="btn btn-sm btn-light border p-1" onclick="document.getElementById('da_pass').classList.toggle('blur-text')" title="แสดงรหัสผ่าน">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-light border p-1 btn-copy" data-copy="<?= htmlspecialchars($servicePassword) ?>" title="คัดลอก">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="text-muted small">เซิร์ฟเวอร์ (Server Host)</label>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($liveData['server_name'] ?? $service['server_name'] ?? 'SRV-BKK-01') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">ไอพีเซิร์ฟเวอร์ (IP Address)</label>
                            <div class="d-flex align-items-center gap-2">
                                <?php $hostIp = $liveData['ip_address'] ?? $service['ip_address'] ?? '-'; ?>
                                <span class="fw-semibold text-dark font-monospace"><?= htmlspecialchars($hostIp) ?></span>
                                <?php if ($hostIp !== '-'): ?>
                                    <button class="btn btn-sm btn-light border p-1 btn-copy" data-copy="<?= htmlspecialchars($hostIp) ?>" title="คัดลอก">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">ลิงก์เข้า DirectAdmin</label>
                            <?php 
                            $daLink = 'http://' . $service['domain_or_hostname'] . ':2222';
                            if (!empty($liveData['server_hostname'])) {
                                $daLink = 'https://' . $liveData['server_hostname'] . ':2222';
                            }
                            ?>
                            <div>
                                <a href="<?= htmlspecialchars($daLink) ?>" target="_blank" class="btn btn-sm btn-primary-gradient rounded-pill px-3">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ DirectAdmin :2222
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nameservers Card -->
                <div class="card-modern p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-diagram-3-fill text-info me-2"></i> ข้อมูล Nameservers (DNS)</h5>
                    <p class="text-muted small mb-3">กรุณาชี้ค่า Nameserver ของโดเมนคุณมายังค่าด้านล่างนี้เพื่อให้เว็บไซต์ใช้งานได้</p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Nameserver 1:</small>
                                    <strong class="text-dark"><?= htmlspecialchars($liveData['nameserver1'] ?? 'ns1.nami-ch.com') ?></strong>
                                </div>
                                <button class="btn btn-sm btn-white border btn-copy" data-copy="<?= htmlspecialchars($liveData['nameserver1'] ?? 'ns1.nami-ch.com') ?>">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Nameserver 2:</small>
                                    <strong class="text-dark"><?= htmlspecialchars($liveData['nameserver2'] ?? 'ns2.nami-ch.com') ?></strong>
                                </div>
                                <button class="btn btn-sm btn-white border btn-copy" data-copy="<?= htmlspecialchars($liveData['nameserver2'] ?? 'ns2.nami-ch.com') ?>">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Disk & Bandwidth Live Stats -->
                <?php if ($liveData && (isset($liveData['disk_percent']) || isset($liveData['bw_percent']))): ?>
                <div class="card-modern p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-activity text-success me-2"></i> สถิติการใช้งานทรัพยากร (Live Resource Usage)</h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-semibold">พื้นที่ใช้งาน (Disk Usage)</span>
                            <span class="small text-muted"><?= (float)($liveData['disk_percent'] ?? 0) ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (float)($liveData['disk_percent'] ?? 0) ?>%;"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-semibold">ปริมาณรับส่งข้อมูล (Bandwidth Usage)</span>
                            <span class="small text-muted"><?= (float)($liveData['bw_percent'] ?? 0) ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= (float)($liveData['bw_percent'] ?? 0) ?>%;"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- VPS Details Card -->
                <div class="card-modern p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-cpu text-info me-2"></i> ข้อมูลการเชื่อมต่อ VPS</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">IP Address</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-5 text-primary"><?= htmlspecialchars($liveData['ip_address'] ?? $service['ip_address'] ?? 'กำลังจัดสรร IP...') ?></span>
                                <?php if (!empty($liveData['ip_address']) || !empty($service['ip_address'])): ?>
                                    <button class="btn btn-sm btn-light border p-1 btn-copy" data-copy="<?= htmlspecialchars($liveData['ip_address'] ?? $service['ip_address']) ?>">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">SSH Port</label>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($liveData['port'] ?? 22) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Default Username</label>
                            <div class="fw-bold fs-6 text-danger"><?= htmlspecialchars($liveData['username'] ?? 'root') ?></div>
                        </div>
                        <?php if ($servicePassword): ?>
                        <div class="col-md-6">
                            <label class="text-muted small">รหัสผ่าน (Root Password)</label>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold fs-6 blur-text" id="vps_pass"><?= htmlspecialchars($servicePassword) ?></span>
                                <button class="btn btn-sm btn-light border p-1" onclick="document.getElementById('vps_pass').classList.toggle('blur-text')" title="แสดงรหัสผ่าน">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-light border p-1 btn-copy" data-copy="<?= htmlspecialchars($servicePassword) ?>" title="คัดลอก">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="text-muted small">ระบบปฏิบัติการ (OS)</label>
                            <div class="fw-semibold"><?= htmlspecialchars($liveData['os_name'] ?? $service['os_name'] ?? 'Linux') ?></div>
                        </div>
                    </div>

                    <?php if (!empty($liveData['ip_address']) || !empty($service['ip_address'])): 
                        $vpsIp = $liveData['ip_address'] ?? $service['ip_address'];
                    ?>
                        <hr class="my-4">
                        <label class="text-muted small mb-1">คำสั่งเชื่อมต่อ SSH Terminal:</label>
                        <div class="p-3 bg-dark text-white rounded-3 d-flex justify-content-between align-items-center font-monospace">
                            <span>ssh root@<?= htmlspecialchars($vpsIp) ?></span>
                            <button class="btn btn-sm btn-outline-light btn-copy" data-copy="ssh root@<?= htmlspecialchars($vpsIp) ?>">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Summary Column -->
        <div class="col-lg-4">
            <div class="card-modern p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-check text-success me-2"></i> ข้อมูลรอบบิล</h5>
                <div class="p-3 bg-light rounded-3 d-flex flex-column gap-2 mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">รอบการชำระ:</span>
                        <span class="fw-semibold"><?= ($service['billing_cycle'] === 'yearly') ? 'รายปี (Yearly)' : 'รายเดือน (Monthly)' ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">ราคาต่อรอบ:</span>
                        <span class="fw-bold text-dark"><?= formatMoney($service['price']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">วันที่เริ่มใช้งาน:</span>
                        <span class="small"><?= thaiDate($service['start_date'], false) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">วันครบกำหนดรอบถัดไป:</span>
                        <span class="small fw-bold text-danger"><?= thaiDate($service['next_due_date'], false) ?></span>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="button" class="btn btn-primary-gradient py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#renewModal">
                        <i class="bi bi-arrow-clockwise me-1"></i> ต่ออายุบริการ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report History -->
    <?php 
    if (!empty($serviceReports)): 
        // การแบ่งหน้า (Pagination) สำหรับ Report History
        $reportsPerPage = 10;
        $totalReports = count($serviceReports);
        $totalPages = ceil($totalReports / $reportsPerPage);
        $currentReportPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentReportPage < 1) $currentReportPage = 1;
        if ($currentReportPage > $totalPages) $currentReportPage = $totalPages;
        
        $startIndex = ($currentReportPage - 1) * $reportsPerPage;
        $pagedReports = array_slice($serviceReports, $startIndex, $reportsPerPage);
    ?>
    <div class="card-modern p-4 mt-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-secondary me-2"></i> ประวัติการแจ้งปัญหา (Report History)</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>เลขที่ (No.)</th>
                        <th>หมวดหมู่</th>
                        <th>สถานะ</th>
                        <th>วันที่แจ้ง</th>
                        <th>อัปเดตล่าสุด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagedReports as $r): ?>
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
                            <td class="small"><?= thaiDate($r['created_at']) ?></td>
                            <td class="small"><?= thaiDate($r['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Report pagination" class="mt-4">
            <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?= ($currentReportPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="service_detail.php?id=<?= $service['id'] ?>&page=<?= $currentReportPage - 1 ?>">ก่อนหน้า</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i === $currentReportPage) ? 'active' : '' ?>">
                        <a class="page-link" href="service_detail.php?id=<?= $service['id'] ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($currentReportPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="service_detail.php?id=<?= $service['id'] ?>&page=<?= $currentReportPage + 1 ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Renew Confirmation Modal -->
<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="renewModalLabel">
                    <i class="bi bi-arrow-clockwise text-primary me-2"></i> ต่ออายุบริการ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-3">คุณกำลังจะต่ออายุบริการ <strong><?= htmlspecialchars($service['domain_or_hostname']) ?></strong></p>
                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ระยะเวลาต่ออายุ:</span>
                        <span class="fw-semibold"><?= ($service['billing_cycle'] === 'yearly') ? '+ 1 ปี' : '+ 1 เดือน' ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ยอดเงินที่ต้องชำระ:</span>
                        <span class="fw-bold text-primary fs-5"><?= formatMoney($service['price']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">ยอดเงินในกระเป๋าของคุณ:</span>
                        <span class="fw-bold <?= ($user['credit'] >= $service['price']) ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($user['credit']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($user['credit'] < $service['price']): ?>
                    <div class="alert alert-warning py-2 small d-flex justify-content-between align-items-center">
                        <span>ยอดเงินไม่พอสำหรับการต่ออายุ</span>
                        <a href="topup.php" class="btn btn-sm btn-primary">เติมเงิน</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 pb-4 justify-content-center">
                <form method="POST" action="service_detail.php?id=<?= $service['id'] ?>" class="w-100 d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="renew_service">
                    <button type="button" class="btn btn-light flex-grow-1 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary-gradient w-50 fw-bold" <?= ($user['credit'] < $service['price']) ? 'disabled' : '' ?>>
                        ยืนยันการต่ออายุ
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Issue Modal -->
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="reportModalLabel">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i> แจ้งปัญหาการใช้งาน
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="service_detail.php?id=<?= $service['id'] ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_report">
                        <div class="modal-body py-4">
                            <p class="mb-3 text-muted small">พบปัญหาการใช้งานของ <strong><?= htmlspecialchars($service['domain_or_hostname']) ?></strong>? แจ้งให้เราทราบได้เลยครับ</p>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small">หมวดหมู่ปัญหา</label>
                                <select name="category" class="form-select" required>
                                    <option value="down">Hosting เข้าไม่ได้ / Connection Error</option>
                                    <option value="website">เว็บไซต์มีปัญหา / Error 500</option>
                                    <option value="database">Database มีปัญหา / เชื่อมต่อไม่ได้</option>
                                    <option value="email_dns_ssl">Email / DNS / SSL มีปัญหา</option>
                                    <option value="server_resource">Server Load สูง / Disk เต็ม</option>
                                    <option value="other">อื่น ๆ</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small">รายละเอียด (ระบุให้ชัดเจน)</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="เช่น หน้าเว็บไซต์ขึ้น Error 500 ตอนพยายามล็อกอิน" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light border w-25" data-bs-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-danger w-50 fw-bold">
                                <i class="bi bi-send me-1"></i> ส่งรายงาน
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/includes/footer.php'; ?>
