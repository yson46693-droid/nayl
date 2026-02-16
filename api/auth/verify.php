<?php
/**
 * ============================================
 * Verify Session API Endpoint
 * ============================================
 * API للتحقق من صحة session token
 * 
 * Endpoint: GET /api/auth/verify.php
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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// السماح بـ GET أو POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET or POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استيراد ملفات المصادقة
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

// التحقق من session token
$user = verifySession();

if (!$user) {
    sendJsonResponse(false, null, 'الجلسة غير صالحة أو منتهية الصلاحية', 401);
}

// إرجاع بيانات المستخدم
sendJsonResponse(true, $user, null, 200);
