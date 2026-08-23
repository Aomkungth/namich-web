<?php
/**
 * Admin Panel Header
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../api_helper.php';

requireAdmin();
$adminUser = getLoggedInUser();
$siteName = getSetting('site_name', SITE_NAME);

// เช็กยอดคงเหลือใน Reseller API
$api = new NamiResellerAPI();
$apiBalRes = $api->getBalance();
$apiCredit = ($apiBalRes && !empty($apiBalRes['ok']) && isset($apiBalRes['data']['credit'])) ? (float)$apiBalRes['data']['credit'] : null;
$apiError = ($apiBalRes && empty($apiBalRes['ok'])) ? ($apiBalRes['error'] ?? 'API Error') : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ระบบจัดการหลังบ้าน (Admin Panel)') ?> - <?= htmlspecialchars($siteName) ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    
    <style>
        .admin-sidebar {
            min-height: calc(100vh - 65px);
            background: #090d16;
            color: #f8fafc;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        .admin-nav-link {
            color: #e2e8f0;
            padding: 12px 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 6px;
            transition: all 0.2s ease;
        }
        .admin-nav-link i {
            font-size: 1.15rem;
        }
        .admin-nav-link:hover {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.12);
        }
        .admin-nav-link.active {
            color: #ffffff !important;
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
    </style>
</head>
<body class="bg-light">

<!-- Admin Top Navbar -->
<nav class="navbar navbar-dark bg-dark sticky-top border-bottom border-secondary px-3 py-2">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-shield-lock-fill text-warning"></i>
            <span><?= htmlspecialchars($siteName) ?> <span class="badge bg-danger ms-1" style="font-size:0.7rem;">ADMIN</span></span>
        </a>

        <div class="d-flex align-items-center gap-3">
            <!-- API Balance Live Indicator -->
            <?php if ($apiCredit !== null): ?>
                <div class="badge bg-dark border border-secondary text-info d-flex align-items-center gap-2 px-3 py-2" title="ยอดเงินเครดิตคงเหลือในบัญชี Nami Reseller API">
                    <i class="bi bi-cloud-check-fill text-success"></i>
                    <span>Reseller API Balance: <strong><?= formatMoney($apiCredit) ?></strong></span>
                </div>
            <?php else: ?>
                <div class="badge bg-danger text-white d-flex align-items-center gap-1 px-3 py-2" title="<?= htmlspecialchars($apiError) ?>">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>API: ไม่ได้เชื่อมต่อ / Key ไม่ถูกต้อง</span>
                </div>
            <?php endif; ?>

            <a href="../index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i> ไปหน้าบ้าน
            </a>
            <a href="../logout.php" class="btn btn-danger btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 p-3 admin-sidebar d-none d-md-block">
            <div class="mb-3 px-2 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">เมนูจัดการระบบ</div>
            <nav class="nav flex-column">
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-speedometer2"></i> แผงควบคุม (Overview)
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                    <i class="bi bi-gear-fill"></i> ตั้งค่าระบบ & API Key
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'pricing.php') ? 'active' : '' ?>" href="pricing.php">
                    <i class="bi bi-tags-fill"></i> ตั้งราคาบวกกำไร (Markup)
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-people-fill"></i> จัดการสมาชิก & เครดิต
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'topups.php') ? 'active' : '' ?>" href="topups.php">
                    <i class="bi bi-qr-code-scan"></i> ตรวจสอบสลิปเติมเงิน
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'services.php') ? 'active' : '' ?>" href="services.php">
                    <i class="bi bi-hdd-stack-fill"></i> บริการลูกค้าทั้งหมด
                </a>
                <a class="admin-nav-link <?= (basename($_SERVER['PHP_SELF']) == 'hosting-reports.php') ? 'active' : '' ?>" href="hosting-reports.php">
                    <i class="bi bi-chat-square-text-fill"></i> ระบบแจ้งปัญหา (Reports)
                </a>
            </nav>
        </div>

        <!-- Main Content Container -->
        <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
            <?= renderFlash() ?>
