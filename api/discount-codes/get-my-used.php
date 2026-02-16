<?php
/**
 * ============================================
 * Get My Used Discount Codes API
 * ============================================
 * جلب قائمة أكواد الخصم التي استخدمها المستخدم الحالي.
 * Endpoint: GET /api/discount-codes/get-my-used.php
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = getAllowedOrigin();
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$user = requireAuth();
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول', 401);
}

$userId = (int) $user['id'];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.code,
            d.discount_amount,
            d.course_id,
            d.used_at,
            c.title AS course_title
        FROM discount_codes d
        LEFT JOIN courses c ON c.id = d.course_id
        WHERE d.used_by = :user_id
        ORDER BY d.used_at DESC
    ");
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'discount_amount' => (float) $row['discount_amount'],
            'course_id' => (int) $row['course_id'],
            'course_title' => $row['course_title'] ?: '—',
            'used_at' => $row['used_at']
        ];
    }

    sendJsonResponse(true, ['discount_codes' => $list]);
} catch (PDOException $e) {
    error_log('Get my used discount codes error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب البيانات', 500);
}
