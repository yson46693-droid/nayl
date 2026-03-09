<?php
/**
 * ============================================
 * Email Verification Handler (Public URL)
 * ============================================
 * رابط التأكيد: /verify-email?token=TOKEN_VALUE
 * يتحقق من الرمز ثم يوجه إلى /email-verified أو /verification-error
 * لا يُرجع JSON — توجيه HTTP فقط أو صفحة HTML بسيطة.
 */

// السماح بـ GET فقط
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: /verification-error');
    exit;
}

// تحميل الإعدادات والأمان
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/security.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/**
 * التحقق من صحة شكل الرمز (طول وحروف آمنة لتجنب هجمات)
 * الرمز المتوقع: 32+ حرف (مثلاً hex أو alphanumeric)
 */
function isTokenFormatValid($token) {
    if (!is_string($token) || strlen($token) < 32 || strlen($token) > 128) {
        return false;
    }
    return (bool) preg_match('/^[a-zA-Z0-9\-_]+$/', $token);
}

// قراءة الرمز وتنظيفه
$rawToken = isset($_GET['token']) ? $_GET['token'] : '';
$token = is_string($rawToken) ? trim($rawToken) : '';

if (!isTokenFormatValid($token)) {
    header('Location: /verification-error');
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    header('Location: /verification-error');
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
        header('Location: /verification-error');
        exit;
    }

    $userId = (int) $row['user_id'];

    $pdo->beginTransaction();
    $inTransaction = true;

    $updateToken = $pdo->prepare("
        UPDATE verification_tokens
        SET used_at = NOW()
        WHERE token = :token AND token_type = 'email_verification'
    ");
    $updateToken->execute(['token' => $token]);

    $updateUser = $pdo->prepare("
        UPDATE users
        SET is_verified = 1
        WHERE id = :id AND deleted_at IS NULL
    ");
    $updateUser->execute(['id' => $userId]);

    $pdo->commit();
    $inTransaction = false;

    header('Location: /email-verified');
    exit;

} catch (PDOException $e) {
    if ($inTransaction && $pdo) {
        $pdo->rollBack();
    }
    error_log("Verify email error: " . $e->getMessage());
    header('Location: /verification-error');
    exit;
}
