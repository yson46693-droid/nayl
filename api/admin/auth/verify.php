<?php
/**
 * ============================================
 * Verify Admin Session API Endpoint
 * ============================================
 * API للتحقق من صحة جلسة المشرف (admin_session_token)
 * 
 * Endpoint: GET /api/admin/auth/verify.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/auth.php';

loadEnv(__DIR__ . '/../../.env');

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
        'error'   => 'Origin not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// معالجة طلبات الـ OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// السماح فقط بـ GET أو POST (للتماشي مع أي استدعاءات مستقبلية)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use GET or POST.', 405);
}

// التحقق من جلسة الأدمن
$admin = verifyAdminSession();

if (!$admin) {
    sendJsonResponse(false, null, 'الجلسة غير صالحة أو منتهية الصلاحية', 401);
}

// إرجاع بيانات الأدمن (بدون حقول حساسة) + new_token عند تجديد الجلسة
$data = [
    'id'        => $admin['id'],
    'admin_id'  => $admin['admin_id'],
    'username'  => $admin['username'],
    'full_name' => $admin['full_name'],
    'email'     => $admin['email'],
    'role'      => $admin['role'],
];
if (!empty($admin['new_token'])) {
    $data['new_token'] = $admin['new_token'];
}
sendJsonResponse(true, $data, null, 200);

