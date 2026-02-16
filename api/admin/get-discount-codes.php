<?php
/**
 * ============================================
 * Get Discount Codes API (Admin)
 * ============================================
 * جلب قائمة أكواد الخصم للمشرفين (نشطة ومستخدمة).
 * Endpoint: GET /api/admin/get-discount-codes.php
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->query("
        SELECT
            d.id,
            d.code,
            d.discount_amount,
            d.course_id,
            d.assigned_to_user_id,
            d.used_by,
            d.used_at,
            d.created_at,
            c.title AS course_title,
            u.full_name AS used_by_name,
            u.email AS used_by_email,
            au.full_name AS assigned_to_name,
            au.email AS assigned_to_email
        FROM discount_codes d
        LEFT JOIN courses c ON c.id = d.course_id
        LEFT JOIN users u ON u.id = d.used_by
        LEFT JOIN users au ON au.id = d.assigned_to_user_id
        ORDER BY d.created_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'discount_amount' => (float) $row['discount_amount'],
            'course_id' => (int) $row['course_id'],
            'course_title' => $row['course_title'] ?: '—',
            'assigned_to_user_id' => isset($row['assigned_to_user_id']) && $row['assigned_to_user_id'] ? (int) $row['assigned_to_user_id'] : null,
            'assigned_to_name' => $row['assigned_to_name'] ?: null,
            'assigned_to_email' => $row['assigned_to_email'] ?: null,
            'used_by' => $row['used_by'] ? (int) $row['used_by'] : null,
            'used_by_name' => $row['used_by_name'] ?: null,
            'used_by_email' => $row['used_by_email'] ?: null,
            'used_at' => $row['used_at'] ?: null,
            'created_at' => $row['created_at'],
            'status' => $row['used_by'] ? 'used' : 'active'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => ['discount_codes' => $list]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get discount codes error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في جلب أكواد الخصم'], JSON_UNESCAPED_UNICODE);
}
