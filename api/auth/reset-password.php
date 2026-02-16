<?php
/**
 * ============================================
 * Reset Password API
 * ============================================
 * تعيين كلمة مرور جديدة باستخدام الرمز من البريد
 * 
 * Endpoint: POST /api/auth/reset-password.php
 * Body: { "token": "...", "password": "...", "confirmPassword": "..." }
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// CORS
$allowedOrigins = [
    'http://localhost', 'https://localhost',
    'http://127.0.0.1', 'https://127.0.0.1'
];
if (function_exists('env')) {
    $appUrl = env('APP_URL', '');
    if ($appUrl) {
        $allowedOrigins[] = rtrim($appUrl, '/');
        $parsed = parse_url($appUrl);
        if ($parsed && isset($parsed['host'])) {
            $allowedOrigins[] = ($parsed['scheme'] ?? 'http') . '://' . $parsed['host'];
        }
    }
}
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
$allowedOrigin = null;
if ($requestOrigin) {
    $parsedOrigin = parse_url($requestOrigin);
    if ($parsedOrigin && isset($parsedOrigin['host'])) {
        $originDomain = ($parsedOrigin['scheme'] ?? 'http') . '://' . $parsedOrigin['host'];
        foreach ($allowedOrigins as $allowed) {
            if ($originDomain === $allowed) {
                $allowedOrigin = $requestOrigin;
                break;
            }
        }
    }
}
if (!$allowedOrigin) {
    $serverProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $allowedOrigin = $serverProtocol . '://' . $serverHost;
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

$token = isset($data['token']) ? trim($data['token']) : '';
$password = $data['password'] ?? '';
$confirmPassword = $data['confirmPassword'] ?? '';

if (empty($token) || strlen($token) !== 64) {
    sendJsonResponse(false, null, 'رابط استعادة غير صالح. يرجى طلب رابط جديد.', 400);
}

if (empty($password) || strlen($password) < 6) {
    sendJsonResponse(false, null, 'كلمة المرور يجب أن تكون 6 أحرف على الأقل', 400);
}

if ($password !== $confirmPassword) {
    sendJsonResponse(false, null, 'كلمة المرور وتأكيدها غير متطابقتين', 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بالخادم', 500);
}

try {
    // التحقق من الرمز
    $stmt = $pdo->prepare("
        SELECT vt.id, vt.user_id 
        FROM verification_tokens vt
        INNER JOIN users u ON u.id = vt.user_id AND u.deleted_at IS NULL
        WHERE vt.token = :token 
          AND vt.token_type = 'password_reset' 
          AND vt.used_at IS NULL 
          AND vt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendJsonResponse(false, null, 'رابط الاستعادة منتهي الصلاحية أو تم استخدامه. يرجى طلب رابط جديد من صفحة نسيت كلمة المرور.', 400);
    }

    $userId = (int) $row['user_id'];
    $passwordHash = hashPassword($password);
    if (!$passwordHash) {
        sendJsonResponse(false, null, 'حدث خطأ أثناء تعيين كلمة المرور', 500);
    }

    // تحديث كلمة المرور
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
    $updateStmt->execute(['hash' => $passwordHash, 'id' => $userId]);

    // تعليم الرمز كمستخدم
    $markStmt = $pdo->prepare("UPDATE verification_tokens SET used_at = NOW() WHERE id = :id");
    $markStmt->execute(['id' => $row['id']]);

    sendJsonResponse(true, ['message' => 'تم تعيين كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.'], null, 200);

} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ غير متوقع', 500);
}
