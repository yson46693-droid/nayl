<?php
/**
 * ============================================
 * Update Discount Code API (Admin)
 * ============================================
 * تحديث كود خصم (فقط إذا لم يُستخدم بعد)
 * Endpoint: POST /api/admin/update-discount-code.php
 * Body: { "discount_code_id": 1, "code": "SAVE50", "discount_amount": 50, "course_id": 1, "assigned_to_user_id": null }
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
$discountCodeId = isset($input['discount_code_id']) ? (int) $input['discount_code_id'] : 0;
$code = isset($input['code']) ? trim((string) $input['code']) : '';
$discountAmount = isset($input['discount_amount']) ? (float) $input['discount_amount'] : 0;
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$assignedToUserId = isset($input['assigned_to_user_id']) ? (int) $input['assigned_to_user_id'] : null;
if ($assignedToUserId <= 0) {
    $assignedToUserId = null;
}

if ($discountCodeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف كود الخصم مطلوب'], JSON_UNESCAPED_UNICODE);
    exit;
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

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $row = $pdo->prepare("SELECT id, used_by FROM discount_codes WHERE id = ? LIMIT 1");
    $row->execute([$discountCodeId]);
    $existing = $row->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'كود الخصم غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($existing['used_by'])) {
        echo json_encode(['success' => false, 'error' => 'لا يمكن تعديل كود خصم مُستخدم بالفعل'], JSON_UNESCAPED_UNICODE);
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
        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $userCheck->execute([$assignedToUserId]);
        if (!$userCheck->fetch()) {
            echo json_encode(['success' => false, 'error' => 'المستخدم المحدد غير موجود'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $checkStmt = $pdo->prepare("SELECT 1 FROM discount_codes WHERE code = ? AND id != ? LIMIT 1");
    $checkStmt->execute([$code, $discountCodeId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'كود الخصم مستخدم من قبل كود آخر، اختر كوداً مختلفاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE discount_codes
        SET code = ?, discount_amount = ?, course_id = ?, assigned_to_user_id = ?
        WHERE id = ? AND used_by IS NULL
    ");
    $stmt->execute([$code, $discountAmount, $courseId, $assignedToUserId, $discountCodeId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'لم يتم التحديث (ربما الكود مُستخدم)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $discountCodeId,
            'code' => $code,
            'discount_amount' => $discountAmount,
            'course_id' => $courseId,
            'course_title' => $course['title'],
            'assigned_to_user_id' => $assignedToUserId
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Update discount code error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء تحديث كود الخصم'], JSON_UNESCAPED_UNICODE);
}
