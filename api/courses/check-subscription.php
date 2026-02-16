<?php
/**
 * ============================================
 * Check Course Subscription API
 * ============================================
 * التحقق من اشتراك المستخدم في كورس معين
 * Endpoint: GET /api/courses/check-subscription.php?id=1
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
    sendJsonResponse(true, ['has_subscription' => false]);
}

$userId = (int) $user['id'];
$courseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($courseId <= 0) {
    sendJsonResponse(true, ['has_subscription' => false]);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $stmt = $pdo->prepare("
        SELECT 1 FROM user_course_subscriptions
        WHERE user_id = :user_id AND course_id = :course_id AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
    $hasSubscription = (bool) $stmt->fetch();

    sendJsonResponse(true, ['has_subscription' => $hasSubscription]);
} catch (PDOException $e) {
    error_log('Check subscription error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء التحقق', 500);
}
