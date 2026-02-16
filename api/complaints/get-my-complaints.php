<?php
/**
 * ============================================
 * Get My Complaints API
 * ============================================
 * API لجلب شكاوي المستخدم المسجل
 * 
 * Endpoint: GET /api/complaints/get-my-complaints.php
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }
    
    // جلب الشكاوي
    $stmt = $pdo->prepare("
        SELECT id, subject, message, status, admin_reply, created_at, updated_at
        FROM complaints
        WHERE user_id = :user_id
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([':user_id' => $user['id']]);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, [
        'complaints' => $complaints
    ]);
    
} catch (PDOException $e) {
    error_log("Get My Complaints Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب الشكاوي', 500);
}
