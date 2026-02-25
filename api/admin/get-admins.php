<?php
/**
 * جلب قائمة حسابات الأدمن (للوحة التحكم)
 * Endpoint: GET /api/admin/get-admins.php
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
    exit;
}

try {
    $stmt = $pdo->query('
        SELECT id, username, full_name, email, role, is_active, last_login_at, created_at
        FROM admins
        ORDER BY created_at DESC
    ');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'is_active' => (bool) $row['is_active'],
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $list], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('get-admins: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'فشل جلب البيانات'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
}
