<?php
/**
 * WebAuthn للمشرفين - CORS وتحميل الإعدادات
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../../config/cors.php';
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/security.php';
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل الإعدادات'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedOrigin = getAdminAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Origin not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function base64urlEncode($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function base64urlDecode($str) {
    $bin = base64_decode(strtr($str, '-_', '+/') . str_repeat('=', (4 - strlen($str) % 4) % 4), true);
    return $bin !== false ? $bin : '';
}

function getRpId() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    return $host;
}
