<?php
/**
 * ============================================
 * Logout API Endpoint
 * ============================================
 * API لتسجيل الخروج
 * يحذف الجلسة من قاعدة البيانات (user_sessions) ويمسح جلسة PHP والكوكيز.
 * يتوقع العميل أن يمسح بدوره بيانات الجلسة من localStorage/sessionStorage.
 *
 * Endpoint: POST /api/auth/logout.php
 */

// بدء الجلسة
session_start();

// تحميل env.php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

// إعدادات CORS
require_once __DIR__ . '/../config/auth.php';

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Origin not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Security Headers
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// السماح فقط بـ POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استيراد الملفات المطلوبة
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

// الحصول على session token
$headers = getallheaders();
$sessionToken = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if ($sessionToken) {
    // إزالة "Bearer " إذا كان موجوداً
    $sessionToken = str_replace('Bearer ', '', $sessionToken);
}

// إذا لم يوجد في header، جرب من cookie
if (!$sessionToken) {
    $sessionToken = $_COOKIE['session_token'] ?? null;
}

// إذا لم يوجد، جرب من body (JSON) كاحتياط
if (!$sessionToken) {
    $input = file_get_contents('php://input');
    if ($input) {
        $body = json_decode($input, true);
        if (!empty($body['session_token'])) {
            $sessionToken = $body['session_token'];
        } elseif (!empty($body['token'])) {
            $sessionToken = $body['token'];
        }
    }
}

if ($sessionToken) {
    // تنظيف token
    $sessionToken = sanitizeInput($sessionToken);
    
    // الاتصال بقاعدة البيانات
    $pdo = getDatabaseConnection();
    if ($pdo) {
        try {
            // حذف الجلسة من قاعدة البيانات
            $stmt = $pdo->prepare("
                DELETE FROM user_sessions 
                WHERE session_token = :token
            ");
            $stmt->execute(['token' => $sessionToken]);
        } catch (PDOException $e) {
            error_log("Logout Error: " . $e->getMessage());
        }
    }
}

// مسح جميع بيانات الجلسة
$_SESSION = array();

// حذف cookie الجلسة إذا كان موجوداً
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// حذف session_token cookie إذا كان موجوداً
if (isset($_COOKIE['session_token'])) {
    setcookie('session_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    unset($_COOKIE['session_token']);
}

// تدمير الجلسة تماماً
session_destroy();

// إرجاع استجابة نجاح
sendJsonResponse(true, ['message' => 'تم تسجيل الخروج بنجاح'], null, 200);
