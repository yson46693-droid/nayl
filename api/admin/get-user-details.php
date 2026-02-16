<?php
/**
 * ============================================
 * Get User Details API (Admin)
 * ============================================
 * API لجلب تفاصيل مستخدم واحد مع قائمة الكورسات المشترك بها
 *
 * Endpoint: GET /api/admin/get-user-details.php?user_id=123
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId < 1) {
    sendJsonResponse(false, null, 'معرف المستخدم غير صالح', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.phone,
            u.email,
            u.country_code,
            u.country,
            u.city,
            u.is_active,
            u.created_at,
            COALESCE(w.balance, 0.00) as wallet_balance
        FROM users u
        LEFT JOIN wallet w ON u.id = w.user_id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendJsonResponse(false, null, 'المستخدم غير موجود', 404);
    }

    $coursesStmt = $pdo->prepare("
        SELECT title, created_at
        FROM wallet_transactions
        WHERE user_id = ? AND reference_type = 'course'
        ORDER BY created_at DESC
    ");
    $coursesStmt->execute([$userId]);
    $coursesRows = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

    $courses = array_map(function ($r) {
        return [
            'title' => $r['title'],
            'enrolled_at' => date('d/m/Y', strtotime($r['created_at']))
        ];
    }, $coursesRows);

    $formatted = [
        'id' => (int)$user['id'],
        'name' => $user['full_name'],
        'phone' => $user['phone'],
        'email' => $user['email'],
        'country_code' => $user['country_code'],
        'country' => $user['country'],
        'city' => $user['city'],
        'status' => $user['is_active'] ? 'نشط' : 'معلق',
        'wallet' => (float)$user['wallet_balance'],
        'joined' => date('d/m/Y', strtotime($user['created_at'])),
        'courses' => $courses
    ];

    sendJsonResponse(true, ['user' => $formatted]);

} catch (PDOException $e) {
    error_log('Admin Get User Details Error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب بيانات المستخدم', 500);
}
