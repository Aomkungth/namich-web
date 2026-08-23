<?php
/**
 * User Login (login.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$usernameOrEmail = '';
$returnUrl = safeRedirectUrl($_GET['return'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $returnUrl = safeRedirectUrl($_POST['return_url'] ?? 'dashboard.php');
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'เซสชั่นหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif (empty($usernameOrEmail) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้/อีเมล และรหัสผ่าน';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'banned') {
                $error = 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
            } else {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_credit'] = $user['credit'];

                setFlash('success', 'เข้าสู่ระบบสำเร็จ! ยินดีต้อนรับคุณ ' . ($user['fullname'] ?: $user['username']));
                
                // Redirect
                header('Location: ' . $returnUrl);
                exit;
            }
        } else {
            $error = 'ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card-modern p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="stat-icon primary mx-auto mb-3">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <h3 class="fw-bold">เข้าสู่ระบบ</h3>
                    <p class="text-muted small">เข้าสู่ระบบจัดการโฮสติ้งและกระเป๋าเงินของคุณ</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อผู้ใช้ หรือ อีเมล</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" name="username_or_email" class="form-control" placeholder="username หรือ email" value="<?= htmlspecialchars($usernameOrEmail) ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">รหัสผ่าน</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="login_password" class="form-control" placeholder="รหัสผ่านของคุณ" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="login_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary-gradient py-2 fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                        </button>
                    </div>

                    <div class="text-center small text-muted">
                        ยังไม่มีบัญชี? <a href="register.php" class="text-primary fw-bold text-decoration-none">สมัครสมาชิกใหม่</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
