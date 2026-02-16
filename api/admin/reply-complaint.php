<?php
/**
 * ============================================
 * Reply Complaint API (Admin)
 * ============================================
 * API للرد على الشكاوي
 * 
 * Endpoint: POST /api/admin/reply-complaint.php
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
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method Not Allowed', 405);
}

// استلام البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse(false, null, 'Invalid JSON Data', 400);
}

$complaintId = isset($input['complaint_id']) ? (int)$input['complaint_id'] : 0;
$reply = isset($input['reply']) ? sanitizeInput($input['reply']) : '';

if ($complaintId <= 0) {
    sendJsonResponse(false, null, 'معرف الشكوى مطلوب', 400);
}

if (empty($reply)) {
    sendJsonResponse(false, null, 'نص الرد مطلوب', 400);
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }
    
    // تحديث الشكوى
    $stmt = $pdo->prepare("
        UPDATE complaints 
        SET admin_reply = :reply, 
            status = 'replied',
            updated_at = NOW()
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':reply' => $reply,
        ':id' => $complaintId
    ]);
    
    if ($stmt->rowCount() > 0) {
        sendJsonResponse(true, ['message' => 'تم الرد على الشكوى بنجاح']);
    } else {
        sendJsonResponse(false, null, 'لم يتم العثور على الشكوى أو لم يتم إجراء تغييرات', 404);
    }
    
} catch (PDOException $e) {
    error_log("Admin Reply Complaint Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء الرد على الشكوى', 500);
}
