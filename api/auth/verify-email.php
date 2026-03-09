<?php
/**
 * ============================================
 * Verify Email API
 * ============================================
 * تأكيد البريد الإلكتروني عند التسجيل
 * المستخدم يفتح الرابط المرسل إلى بريده
 *
 * GET /api/auth/verify-email.php?token=xxx
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (strlen($token) < 32) {
    showVerificationPage(false, 'رابط التأكيد غير صحيح أو منتهي الصلاحية.');
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    showVerificationPage(false, 'خطأ في الاتصال بالخادم. يرجى المحاولة لاحقاً.');
    exit;
}

$inTransaction = false;
try {
    $stmt = $pdo->prepare("
        SELECT vt.user_id, vt.expires_at
        FROM verification_tokens vt
        WHERE vt.token = :token
          AND vt.token_type = 'email_verification'
          AND vt.used_at IS NULL
          AND vt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        showVerificationPage(false, 'رابط التأكيد غير صالح أو منتهي الصلاحية. يمكنك طلب إرسال رابط جديد من صفحة تسجيل الدخول.');
        exit;
    }

    $userId = (int) $row['user_id'];

    $inTransaction = false;
    $pdo->beginTransaction();
    $inTransaction = true;

    $updateToken = $pdo->prepare("UPDATE verification_tokens SET used_at = NOW() WHERE token = :token AND token_type = 'email_verification'");
    $updateToken->execute(['token' => $token]);

    $updateUser = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = :id AND deleted_at IS NULL");
    $updateUser->execute(['id' => $userId]);

    $pdo->commit();
    $inTransaction = false;

    $baseUrl = rtrim(function_exists('env') ? env('APP_URL', '') : '', '/');
    if (empty($baseUrl)) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    }
    $loginUrl = $baseUrl . '/index.html?email_verified=1';

    showVerificationPage(true, 'تم تأكيد بريدك الإلكتروني بنجاح. يمكنك تسجيل الدخول الآن.', $loginUrl);

} catch (PDOException $e) {
    if (!empty($inTransaction)) {
        $pdo->rollBack();
    }
    error_log("Verify email error: " . $e->getMessage());
    showVerificationPage(false, 'حدث خطأ أثناء التأكيد. يرجى المحاولة لاحقاً.');
}

/**
 * عرض صفحة تأكيد البريد (HTML بسيط مع رسالة وزر للتوجيه)
 */
function showVerificationPage($success, $message, $loginUrl = null) {
    $appName = function_exists('env') ? (env('APP_NAME', '') ?: env('MAIL_FROM_NAME', 'AmrNayl Academy')) : 'AmrNayl Academy';
    $loginUrl = $loginUrl ?: (rtrim(function_exists('env') ? env('APP_URL', '') : '', '/') ?: '#') . '/index.html';
    $btnText = $success ? 'تسجيل الدخول' : 'العودة للرئيسية';
    $color = $success ? '#4CAF50' : '#ff9f43';
    ?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد - <?php echo htmlspecialchars($appName); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .box { max-width: 420px; width: 100%; background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; }
        .logo { font-size: 1.25rem; font-weight: bold; color: #4a90e2; margin-bottom: 1.5rem; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { color: #1a2332; font-size: 1.2rem; margin: 0 0 1rem; }
        p { color: #2c3e50; line-height: 1.7; margin: 0 0 1.5rem; font-size: 0.95rem; }
        .btn { display: inline-block; background: #4a90e2; color: #fff !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #3a7bc8; }
        .msg { color: <?php echo $color; ?>; }
    </style>
</head>
<body>
    <div class="box">
        <div class="logo"><?php echo htmlspecialchars($appName); ?></div>
        <div class="icon"><?php echo $success ? '✓' : '!'; ?></div>
        <h1><?php echo $success ? 'تم التأكيد' : 'رابط غير صالح'; ?></h1>
        <p class="msg"><?php echo htmlspecialchars($message); ?></p>
        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn"><?php echo $btnText; ?></a>
    </div>
</body>
</html>
    <?php
}
