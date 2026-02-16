<?php
/**
 * ============================================
 * Remove Device API Endpoint
 * ============================================
 * API لإزالة جهاز محدد (تسجيل الخروج من جلسة محددة)
 * 
 * Endpoint: POST /api/auth/remove-device.php
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

// التحقق من تسجيل الدخول
$user = requireAuth(true);
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول للوصول إلى هذه الصفحة', 401);
}

// الحصول على البيانات من Request Body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, null, 'بيانات غير صحيحة', 400);
}

$sessionId = isset($data['session_id']) ? (int)$data['session_id'] : 0;

if (!$sessionId || $sessionId <= 0) {
    sendJsonResponse(false, null, 'يجب تحديد معرف الجلسة بشكل صحيح', 400);
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    // التحقق من أن الجلسة تخص المستخدم الحالي
    $stmt = $pdo->prepare("
        SELECT id, session_token
        FROM user_sessions
        WHERE id = :session_id
        AND user_id = :user_id
        LIMIT 1
    ");
    
    $stmt->execute([
        'session_id' => $sessionId,
        'user_id' => $user['id']
    ]);
    
    $session = $stmt->fetch();
    
    if (!$session) {
        sendJsonResponse(false, null, 'الجلسة غير موجودة أو لا تخصك', 404);
    }
    
    // الحصول على session token الحالي
    $headers = getallheaders();
    $currentSessionToken = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    if ($currentSessionToken) {
        $currentSessionToken = str_replace('Bearer ', '', $currentSessionToken);
    }
    if (!$currentSessionToken) {
        $currentSessionToken = $_COOKIE['session_token'] ?? null;
    }
    $currentSessionToken = sanitizeInput($currentSessionToken ?? '');
    
    // منع حذف الجلسة الحالية (يجب استخدام logout.php لذلك)
    if ($session['session_token'] === $currentSessionToken) {
        sendJsonResponse(false, null, 'لا يمكنك إزالة الجهاز الحالي. استخدم تسجيل الخروج بدلاً من ذلك.', 400);
    }
    
    // حذف الجلسة
    $deleteStmt = $pdo->prepare("
        DELETE FROM user_sessions
        WHERE id = :session_id
        AND user_id = :user_id
    ");
    
    $deleteStmt->execute([
        'session_id' => $sessionId,
        'user_id' => $user['id']
    ]);
    
    if ($deleteStmt->rowCount() > 0) {
        sendJsonResponse(true, ['message' => 'تم إزالة الجهاز بنجاح'], null, 200);
    } else {
        sendJsonResponse(false, null, 'حدث خطأ أثناء إزالة الجهاز', 500);
    }
    
} catch (PDOException $e) {
    error_log("Remove Device Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء إزالة الجهاز', 500);
}
