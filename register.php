<?php
/**
 * User Registration (register.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$username = '';
$email = '';
$fullname = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'เซสชั่นหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    }
    if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'ชื่อผู้ใช้ (Username) ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข หรือ _ ความยาว 3-30 ตัวอักษร';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาระบุอีเมลที่ถูกต้อง';
    }

    if (strlen($password) < 6) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    }

    if ($password !== $password_confirm) {
        $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
    }

    if (empty($errors)) {
        $pdo = getDB();

        // ตรวจสอบ Username ซ้ำ
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `username` = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่ออื่น';
        }

        // ตรวจสอบ Email ซ้ำ
        $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'อีเมลนี้มีอยู่ในระบบแล้ว กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ';
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO `users` (`username`, `email`, `password`, `fullname`, `phone`, `credit`, `role`, `status`) VALUES (?, ?, ?, ?, ?, 0.00, 'user', 'active')");
            $insertStmt->execute([$username, $email, $hashedPassword, $fullname, $phone]);

            $newUserId = $pdo->lastInsertId();

            // Auto Login
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_credit'] = 0.00;

            setFlash('success', 'สมัครสมาชิกสำเร็จ! ยินดีต้อนรับเข้าสู่ระบบ');
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'สมัครสมาชิกใหม่ - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card-modern p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="stat-icon primary mx-auto mb-3">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h3 class="fw-bold">สมัครสมาชิก</h3>
                    <p class="text-muted small">สร้างบัญชีเพื่อสั่งซื้อและจัดการโฮสติ้ง / VPS ได้ทันที</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" autocomplete="off">
                    <?= csrfField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="เช่น somchai99" value="<?= htmlspecialchars($username) ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">อีเมล (Email) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" placeholder="สมชาย ใจดี" value="<?= htmlspecialchars($fullname) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" class="form-control" placeholder="08xxxxxxxx" value="<?= htmlspecialchars($phone) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รหัสผ่าน <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="reg_password" class="form-control" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password_confirm" id="reg_password_confirm" class="form-control" placeholder="พิมพ์รหัสผ่านซ้ำอีกครั้ง" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="reg_password_confirm">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary-gradient py-2 fw-bold">
                            <i class="bi bi-check-lg me-1"></i> ยืนยันการสมัครสมาชิก
                        </button>
                    </div>

                    <div class="text-center small text-muted">
                        มีบัญชีอยู่แล้ว? <a href="login.php" class="text-primary fw-bold text-decoration-none">เข้าสู่ระบบที่นี่</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
