<?php
/**
 * ============================================
 * Validate Discount Code API
 * ============================================
 * التحقق من صحة كود الخصم لكورس محدد (غير مستخدم، مرتبط بنفس الكورس).
 * Endpoint: POST /api/courses/validate-discount.php
 * Body: { "code": "SAVE50", "course_id": 1 }
 * Response: { success, data: { discount_amount, final_price } }
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
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = isset($input['code']) ? trim((string) $input['code']) : '';
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;

if ($code === '' || $courseId <= 0) {
    sendJsonResponse(false, null, 'كود الخصم ومعرف الكورس مطلوبان', 400);
}

$currentUser = requireAuth();
$currentUserId = $currentUser ? (int) $currentUser['id'] : null;

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $courseStmt = $pdo->prepare("SELECT id, title, COALESCE(price, 500) AS price FROM courses WHERE id = :id AND status = 'published' LIMIT 1");
    $courseStmt->execute(['id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        sendJsonResponse(false, null, 'الكورس غير موجود أو غير متاح', 404);
    }

    $coursePrice = (float) $course['price'];

    $discountStmt = $pdo->prepare("
        SELECT id, discount_amount, assigned_to_user_id
        FROM discount_codes
        WHERE code = :code AND course_id = :course_id AND used_by IS NULL
        LIMIT 1
    ");
    $discountStmt->execute(['code' => $code, 'course_id' => $courseId]);
    $discount = $discountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$discount) {
        sendJsonResponse(false, null, 'كود الخصم غير صحيح أو مستخدم أو غير مرتبط بهذا الكورس', 400);
    }

    $assignedToUserId = isset($discount['assigned_to_user_id']) ? (int) $discount['assigned_to_user_id'] : null;
    if ($assignedToUserId !== null && $assignedToUserId > 0) {
        if ($currentUserId === null) {
            sendJsonResponse(false, null, 'يجب تسجيل الدخول لاستخدام هذا الكود', 401);
        }
        if ($currentUserId !== $assignedToUserId) {
            sendJsonResponse(false, null, 'هذا الكود مخصص لمستخدم آخر', 403);
        }
    }

    $discountAmount = (float) $discount['discount_amount'];
    $finalPrice = max(0, $coursePrice - $discountAmount);

    sendJsonResponse(true, [
        'discount_amount' => $discountAmount,
        'course_price' => $coursePrice,
        'final_price' => $finalPrice
    ]);
} catch (PDOException $e) {
    error_log('Validate discount error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء التحقق من كود الخصم', 500);
}
