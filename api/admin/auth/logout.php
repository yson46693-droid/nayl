<?php
/**
 * ============================================
 * Admin Logout API Endpoint
 * ============================================
 * API لتسجيل خروج المشرفين
 * 
 * Endpoint:POST /api/admin/auth/logout.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/cors.php';

$allowedOrigin = getAdminAllowedOrigin();
if (!$allowedOrigin) {
    $allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
}
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/security.php';
    
    // الحصول على التوكن
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $token = $data['token'] ?? $_COOKIE['admin_session_token'] ?? null;
    
    // إذا لم يوجد، ابحث في الهيدر
    if (!$token) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    if ($token) {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE session_token = :token");
        $stmt->execute(['token' => $token]);
    }
    
    // حذف الكوكي
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    if (PHP_VERSION_ID >= 70300) {
        setcookie('admin_session_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('admin_session_token', '', time() - 3600, '/', '', $isSecure, false);
    }
    
    echo json_encode(['success' => true, 'message' => 'تم تسجيل الخروج بنجاح']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ']);
}
