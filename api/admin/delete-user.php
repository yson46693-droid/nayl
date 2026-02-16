<?php
/**
 * ============================================
 * Delete User API (Admin)
 * ============================================
 * API لحذف مستخدم (Soft Delete)
 * 
 * Endpoint: POST /api/admin/delete-user.php
 */

// بدء الجلسة
session_start();

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// إعدادات CORS
$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من صلاحيات الأدمن
$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول', 401);
}

// قراءة البيانات المرسلة
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    sendJsonResponse(false, null, 'معرف المستخدم مطلوب', 400);
}

$userId = (int)$data['user_id'];

try {
    $pdo = getDatabaseConnection();
    
    // Soft Delete
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() > 0) {
        sendJsonResponse(true, null, 'تم حذف المستخدم بنجاح');
    } else {
        sendJsonResponse(false, null, 'المستخدم غير موجود أو تم حذفه مسبقاً');
    }
    
} catch (PDOException $e) {
    error_log("Delete User Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء حذف المستخدم', 500);
}
