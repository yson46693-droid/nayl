<?php
/**
 * ============================================
 * Forgot Password API
 * ============================================
 * طلب إعادة تعيين كلمة المرور
 * يرسل رابط استعادة إلى البريد الإلكتروني
 * 
 * Endpoint: POST /api/auth/forgot-password.php
 * Body: { "email": "user@example.com" }
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/email.php';

$allowedOrigin = getAllowedOrigin();
if (!$allowedOrigin) {
    $allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
}
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, null, 'بيانات غير صحيحة', 400);
}

$email = sanitizeInput($data['email'] ?? '');
if (empty($email) || !validateEmail($email)) {
    sendJsonResponse(false, null, 'أدخل بريداً إلكترونياً صحيحاً', 400);
}

// Rate limit: 3 طلبات في الساعة لكل بريد (منع إساءة الاستخدام)
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بالخادم', 500);
}

try {
    // التحقق من وجود المستخدم
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $successMessage = 'تم إرسال رابط استعادة كلمة المرور إلى بريدك. تحقق من صندوق الوارد والرسائل غير المرغوبة.';

    if (!$user) {
        sendJsonResponse(false, null, 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني', 404);
        exit;
    }

    // إبطال أي رموز استعادة سابقة للمستخدم
    $invalidateStmt = $pdo->prepare("UPDATE verification_tokens SET used_at = NOW() WHERE user_id = :uid AND token_type = 'password_reset' AND used_at IS NULL");
    $invalidateStmt->execute(['uid' => $user['id']]);

    // إنشاء رمز جديد (64 حرف عشوائي آمن)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $insertStmt = $pdo->prepare("
        INSERT INTO verification_tokens (user_id, token, token_type, expires_at) 
        VALUES (:uid, :token, 'password_reset', :expires)
    ");
    $insertStmt->execute([
        'uid' => $user['id'],
        'token' => $token,
        'expires' => $expiresAt
    ]);

    // بناء رابط الاستعادة
    $baseUrl = rtrim(function_exists('env') ? env('APP_URL', '') : '', '/');
    if (empty($baseUrl)) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
    }
    $resetLink = $baseUrl . '/reset-password.html?token=' . urlencode($token);

    $sent = sendPasswordResetEmail($user['email'], $resetLink, $user['full_name'] ?? '');

    if (!$sent) {
        error_log("Failed to send password reset email to: " . $user['email']);
        // لا نخبر المستخدم بفشل الإرسال لأسباب أمنية - نعيد نفس النجاح
    }

    sendJsonResponse(true, ['message' => $successMessage], null, 200);

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    sendJsonResponse(true, ['message' => $successMessage], null, 200);
}
