<?php
/**
 * Header Template
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$currentUser = getLoggedInUser();
$siteName = getSetting('site_name', SITE_NAME);
$siteSlogan = getSetting('site_slogan', SITE_SLOGAN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $siteName . ' - ' . $siteSlogan) ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-hdd-network-fill text-info"></i>
            <span><?= htmlspecialchars($siteName) ?></span>
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house me-1"></i> หน้าแรก
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'packages.php') ? 'active' : '' ?>" href="packages.php">
                        <i class="bi bi-server me-1"></i> เว็บโฮสติ้ง (Hosting)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'vps.php') ? 'active' : '' ?>" href="vps.php">
                        <i class="bi bi-cpu me-1"></i> คลาวด์ VPS
                    </a>
                </li>
                <?php if ($currentUser): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'services.php' || basename($_SERVER['PHP_SELF']) == 'service_detail.php') ? 'active' : '' ?>" href="services.php">
                        <i class="bi bi-layers me-1"></i> บริการของฉัน
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <?php if ($currentUser): ?>
                    <!-- Wallet Badge -->
                    <a href="topup.php" class="wallet-badge text-decoration-none shadow-sm" title="คลิกเพื่อเติมเงิน">
                        <i class="bi bi-wallet2"></i>
                        <span><?= formatMoney($currentUser['credit']) ?></span>
                        <span class="badge bg-light text-dark rounded-pill" style="font-size: 0.75rem;">+ เติมเงิน</span>
                    </a>

                    <!-- User Menu Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-dark dropdown-toggle d-flex align-items-center gap-2 border-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-info"></i>
                            <span><?= htmlspecialchars($currentUser['username']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="dropdown-header">
                                <strong><?= htmlspecialchars($currentUser['fullname'] ?: $currentUser['username']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($currentUser['email']) ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-2"></i> แผงควบคุม (Dashboard)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="services.php">
                                    <i class="bi bi-hdd-stack me-2"></i> บริการทั้งหมดของฉัน
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="topup.php">
                                    <i class="bi bi-qr-code me-2"></i> เติมเงินเข้ากระเป๋า
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="transactions.php">
                                    <i class="bi bi-receipt me-2"></i> ประวัติรายการ
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-gear me-2"></i> ข้อมูลส่วนตัว / รหัสผ่าน
                                </a>
                            </li>
                            <?php if (isAdmin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-primary fw-bold" href="admin/index.php">
                                    <i class="bi bi-shield-lock me-2"></i> ระบบจัดการหลังบ้าน (Admin)
                                </a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                        <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                    </a>
                    <a href="register.php" class="btn btn-primary-gradient btn-sm px-3 rounded-pill">
                        <i class="bi bi-person-plus me-1"></i> สมัครสมาชิก
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Main Container for Flash Messages -->
<div class="container mt-3">
    <?= renderFlash() ?>
</div>
