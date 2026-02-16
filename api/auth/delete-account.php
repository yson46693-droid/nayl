<?php
/**
 * ============================================
 * Delete Account API Endpoint
 * ============================================
 * API لحذف الحساب
 * 
 * Endpoint: POST /api/auth/delete-account.php
 */

// بدء الجلسة
session_start();

// تحميل env.php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

// استيراد الملفات المطلوبة
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// إعدادات CORS
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

// التحقق من تسجيل الدخول
$user = verifySession();

if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'يجب تسجيل الدخول للقيام بهذا الإجراء'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    $userId = $user['user_id'];

    // بدء Transaction لضمان سلامة البيانات
    $pdo->beginTransaction();

    // 1. تحديث جدول المستخدمين (Soft Delete)
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET deleted_at = NOW(), is_active = 0 
        WHERE id = :id
    ");
    $updateStmt->execute(['id' => $userId]);

    // 2. حذف جميع جلسات المستخدم
    $deleteSessionsStmt = $pdo->prepare("
        DELETE FROM user_sessions 
        WHERE user_id = :user_id
    ");
    $deleteSessionsStmt->execute(['user_id' => $userId]);

    // التأكيد على التغييرات
    $pdo->commit();

    // 3. تدمير الجلسة الحالية من السيرفر (PHP Session)
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // حذف الكوكيز الخاصة بالجلسة
    if (isset($_COOKIE['session_token'])) {
        setcookie('session_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        unset($_COOKIE['session_token']);
    }
    
    session_destroy();

    // 4. إرجاع استجابة نجاح
    sendJsonResponse(true, ['message' => 'تم حذف الحساب بنجاح'], null, 200);

} catch (PDOException $e) {
    // التراجع عن التغييرات في حالة الخطأ
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete Account Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء حذف الحساب', 500);
}
?>
