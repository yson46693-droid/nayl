<?php
/**
 * ============================================
 * Create Discount Code API (Admin)
 * ============================================
 * إنشاء كود خصم جديد: مرتبط بكورس محدد، مبلغ خصم ثابت، استخدام مرة واحدة لمستخدم واحد.
 * Endpoint: POST /api/admin/create-discount-code.php
 * Body: { "code": "SAVE50", "discount_amount": 50, "course_id": 1, "assigned_to_user_id": 123 }
 * assigned_to_user_id اختياري: عند تعبئته يصبح الكود قابلاً للاستخدام من هذا المستخدم فقط.
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$code = isset($input['code']) ? trim((string) $input['code']) : '';
$discountAmount = isset($input['discount_amount']) ? (float) $input['discount_amount'] : 0;
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$assignedToUserId = isset($input['assigned_to_user_id']) ? (int) $input['assigned_to_user_id'] : null;
if ($assignedToUserId <= 0) {
    $assignedToUserId = null;
}

if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'كود الخصم مطلوب'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strlen($code) > 50) {
    echo json_encode(['success' => false, 'error' => 'كود الخصم طويل جداً'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($discountAmount <= 0) {
    echo json_encode(['success' => false, 'error' => 'مبلغ الخصم يجب أن يكون أكبر من صفر'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($courseId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف الكورس مطلوب'], JSON_UNESCAPED_UNICODE);
    exit;
}

$adminId = (int) ($admin['id'] ?? $admin['admin_id'] ?? 0);

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $courseStmt = $pdo->prepare("SELECT id, title, COALESCE(price, 500) AS price FROM courses WHERE id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'الكورس غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $coursePrice = (float) $course['price'];
    if ($discountAmount > $coursePrice) {
        echo json_encode(['success' => false, 'error' => 'مبلغ الخصم لا يمكن أن يتجاوز سعر الكورس (' . number_format($coursePrice, 2) . ' ج.م)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($assignedToUserId !== null) {
        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $userCheck->execute([$assignedToUserId]);
        if (!$userCheck->fetch()) {
            echo json_encode(['success' => false, 'error' => 'المستخدم المحدد غير موجود'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // التحقق من عدم تكرار الكود
    $checkStmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE code = ? LIMIT 1");
    $checkStmt->execute([$code]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'كود الخصم مستخدم مسبقاً، اختر كوداً آخر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO discount_codes (code, discount_amount, course_id, assigned_to_user_id, created_by)
        VALUES (:code, :discount_amount, :course_id, :assigned_to_user_id, :created_by)
    ");
    $insertStmt->execute([
        'code' => $code,
        'discount_amount' => $discountAmount,
        'course_id' => $courseId,
        'assigned_to_user_id' => $assignedToUserId,
        'created_by' => $adminId ?: null
    ]);
    $newId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $newId,
            'code' => $code,
            'discount_amount' => $discountAmount,
            'course_id' => $courseId,
            'course_title' => $course['title'],
            'assigned_to_user_id' => $assignedToUserId
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Create discount code error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء إنشاء كود الخصم'], JSON_UNESCAPED_UNICODE);
}
