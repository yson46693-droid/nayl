<?php
/**
 * ============================================
 * Create Complaint API
 * ============================================
 * API لإنشاء شكوى جديدة
 * 
 * Endpoint: POST /api/complaints/create.php
 */

// بدء الجلسة
session_start();

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

// تحميل متغيرات البيئة
loadEnv(__DIR__ . '/../../.env');

// التحقق من تسجيل الدخول
$user = requireAuth();

// إعدادات CORS
header('Access-Control-Allow-Origin: ' . getAllowedOrigin());
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method Not Allowed', 405);
}

// استلام البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse(false, null, 'Invalid JSON Data', 400);
}

$subject = isset($input['subject']) ? sanitizeInput($input['subject']) : '';
$message = isset($input['message']) ? sanitizeInput($input['message']) : '';

// التحقق من صحة البيانات
$errors = [];

if (empty($subject)) {
    $errors[] = 'عنوان الشكوى مطلوب';
}

if (empty($message)) {
    $errors[] = 'نص الشكوى مطلوب';
}

if (!empty($errors)) {
    sendJsonResponse(false, null, $errors[0], 400);
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }
    
    // إدخال الشكوى
    $stmt = $pdo->prepare("
        INSERT INTO complaints (user_id, subject, message, created_at)
        VALUES (:user_id, :subject, :message, NOW())
    ");
    
    $stmt->execute([
        ':user_id' => $user['id'],
        ':subject' => $subject,
        ':message' => $message
    ]);
    
    $complaintId = $pdo->lastInsertId();
    
    sendJsonResponse(true, [
        'id' => $complaintId,
        'message' => 'تم إرسال الشكوى بنجاح، سيتم الرد عليك قريباً'
    ]);
    
} catch (PDOException $e) {
    error_log("Create Complaint Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء إرسال الشكوى', 500);
}
