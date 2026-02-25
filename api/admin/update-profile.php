<?php
/**
 * تحديث ملف الأدمن الشخصي (الاسم، البريد)
 * Endpoint: POST /api/admin/update-profile.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

loadEnv(__DIR__ . '/../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    http_response_code(405);
    exit;
}

$admin = verifyAdminSession();
if (!$admin) {
    echo json_encode(['success' => false, 'error' => 'الجلسة غير صالحة'], JSON_UNESCAPED_UNICODE);
    http_response_code(401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$fullName = isset($input['full_name']) ? trim(sanitizeInput($input['full_name'])) : '';
$email = isset($input['email']) ? trim(sanitizeInput($input['email'])) : null;

if ($fullName === '') {
    echo json_encode(['success' => false, 'error' => 'الاسم الكامل مطلوب'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

if ($email !== null && $email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني غير صحيح'], JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
    exit;
}

$adminId = (int) ($admin['admin_id'] ?? $admin['id']);

try {
    if ($email !== null && $email !== '') {
        $stmt = $pdo->prepare('UPDATE admins SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$fullName, $email, $adminId]);
    } else {
        $stmt = $pdo->prepare('UPDATE admins SET full_name = ?, email = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$fullName, $adminId]);
    }
    if ($stmt->rowCount() >= 0) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        return;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني مستخدم من حساب آخر'], JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
    error_log('update-profile: ' . $e->getMessage());
}

echo json_encode(['success' => false, 'error' => 'فشل التحديث'], JSON_UNESCAPED_UNICODE);
http_response_code(500);
