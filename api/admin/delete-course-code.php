<?php
/**
 * ============================================
 * Delete Course Code API (Admin)
 * ============================================
 * API لحذف كود تفعيل
 * Endpoint: POST /api/admin/delete-course-code.php
 * Body: { "code_id": 1 }
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
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول كمسؤول'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$codeId = isset($input['code_id']) ? (int) $input['code_id'] : 0;

if ($codeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف الكود مطلوب'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM course_codes WHERE id = ?");
    $stmt->execute([$codeId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'الكود غير موجود أو تم حذفه'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => ['message' => 'تم حذف الكود بنجاح']
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Delete course code error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في حذف الكود'], JSON_UNESCAPED_UNICODE);
}
