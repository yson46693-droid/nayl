<?php
/**
 * ============================================
 * Delete Course API (Admin)
 * ============================================
 * API لحذف كورس من قاعدة البيانات
 * يحذف الكورس وسيتم حذف الفيديوهات والأكواد والاشتراكات المرتبطة تلقائياً (CASCADE)
 *
 * Endpoint: POST /api/admin/delete-course.php
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول كمسؤول', 401);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_id'])) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب', 400);
}

$courseId = (int) $data['course_id'];
if ($courseId <= 0) {
    sendJsonResponse(false, null, 'معرف الكورس غير صالح', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->execute([$courseId]);

    if ($stmt->rowCount() > 0) {
        sendJsonResponse(true, null, 'تم حذف الكورس بنجاح');
    }

    sendJsonResponse(false, null, 'الكورس غير موجود أو تم حذفه مسبقاً', 404);
} catch (PDOException $e) {
    error_log('Delete course error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء حذف الكورس', 500);
}
