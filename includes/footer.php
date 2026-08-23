<?php
/**
 * Footer Template
 */
$siteName = getSetting('site_name', SITE_NAME);
$contactLine = getSetting('contact_line', '@hostpro');
$contactEmail = getSetting('contact_email', 'support@example.com');
?>
<footer class="py-5 mt-5">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white fw-bold d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-hdd-network-fill text-info"></i> <?= htmlspecialchars($siteName) ?>
                </h5>
                <p class="small text-light text-opacity-75">
                    ระบบให้บริการเว็บโฮสติ้งและคลาวด์เซิร์ฟเวอร์ความเร็วสูง ควบคุมด้วย DirectAdmin จัดการคำสั่งซื้อและต่ออายุอัตโนมัติผ่าน API ตลอด 24 ชั่วโมง
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-dark border border-secondary text-info"><i class="bi bi-shield-check me-1"></i> DirectAdmin API</span>
                    <span class="badge bg-dark border border-secondary text-success"><i class="bi bi-lightning-charge-fill me-1"></i> Auto Setup</span>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h6 class="text-white fw-bold mb-3">บริการของเรา</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="packages.php">Shared Hosting</a></li>
                    <li><a href="packages.php?category=reseller-hosting">Reseller Hosting</a></li>
                    <li><a href="vps.php">Cloud VPS Server</a></li>
                    <li><a href="packages.php">แพ็กเกจทั้งหมด</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">เมนูลูกค้า</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php">แผงควบคุมสมาชิก</a></li>
                        <li><a href="services.php">บริการของฉัน</a></li>
                        <li><a href="topup.php">เติมเงินเข้าระบบ</a></li>
                        <li><a href="transactions.php">ประวัติการชำระเงิน</a></li>
                    <?php else: ?>
                        <li><a href="login.php">เข้าสู่ระบบ</a></li>
                        <li><a href="register.php">สมัครสมาชิกใหม่</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">ช่องทางติดต่อ</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li class="text-light text-opacity-75">
                        <i class="bi bi-envelope-fill text-info me-2"></i> <?= htmlspecialchars($contactEmail) ?>
                    </li>
                    <li class="text-light text-opacity-75">
                        <i class="bi bi-chat-dots-fill text-success me-2"></i> LINE: <?= htmlspecialchars($contactLine) ?>
                    </li>
                    <li class="text-light text-opacity-75">
                        <i class="bi bi-clock-fill text-warning me-2"></i> ให้บริการและซัพพอร์ต 24/7
                    </li>
                </ul>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="row align-items-center small">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0 text-light text-opacity-75">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved. Powered by Nami Reseller API.
            </div>
            <div class="col-md-6 text-center text-md-end text-light text-opacity-75">
                <span>Fast & Reliable Cloud Hosting Solution</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/main.js"></script>
</body>
</html>
