<?php
/**
 * ============================================
 * Redeem Course Code API
 * ============================================
 * تفعيل كود الكورس غير المستخدم: ربط الكود بالمستخدم وإنشاء اشتراك
 * Endpoint: POST /api/courses/redeem-code.php
 * Body: { "course_id": 1, "code": "XXXXXXXXXX" }
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

$user = requireAuth();
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول لتفعيل الكود', 401);
}

$userId = (int) $user['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$code = isset($input['code']) ? trim((string) $input['code']) : '';

if ($courseId <= 0) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب', 400);
}

if ($code === '') {
    sendJsonResponse(false, null, 'كود التفعيل مطلوب', 400);
}

// تنظيف الكود (أحرف وأرقام كبيرة فقط)
$code = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
if (strlen($code) < 6) {
    sendJsonResponse(false, null, 'كود التفعيل غير صحيح', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    // التحقق من وجود اشتراك مسبق
    $subStmt = $pdo->prepare("SELECT 1 FROM user_course_subscriptions WHERE user_id = :user_id AND course_id = :course_id AND status = 'active' LIMIT 1");
    $subStmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if ($subStmt->fetch()) {
        sendJsonResponse(false, null, 'لديك اشتراك مسبق في هذا الكورس', 400);
    }

    // التحقق من وجود الكورس
    $courseStmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = :id AND status = 'published' LIMIT 1");
    $courseStmt->execute(['id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        sendJsonResponse(false, null, 'الكورس غير موجود أو غير متاح', 404);
    }

    // البحث عن كود غير مستخدم ومرتبط بالكورس (أكواد الإدارة فقط - used_by IS NULL)
    // ملاحظة: أكواد الشراء تُنشأ مباشرة مع used_by = المشتري فلا تظهر هنا أبداً ولا يمكن استخدامها في حساب آخر
    $codeStmt = $pdo->prepare("
        SELECT id, used_by FROM course_codes
        WHERE course_id = :course_id
          AND code = :code
          AND is_active = 1
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $codeStmt->execute(['course_id' => $courseId, 'code' => $code]);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeRow) {
        sendJsonResponse(false, null, 'الكود غير صحيح أو منتهي الصلاحية', 400);
    }

    // إذا كان الكود مرتبطاً بمستخدم آخر (كود شراء) فلا يمكن استخدامه في أي حساب آخر
    if (!empty($codeRow['used_by']) && (int) $codeRow['used_by'] !== $userId) {
        sendJsonResponse(false, null, 'هذا الكود مرتبط بحساب المشتري فقط ولا يمكن استخدامه في حساب آخر', 400);
    }

    $codeId = (int) $codeRow['id'];

    $pdo->beginTransaction();

    try {
        // ربط الكود بالمستخدم
        $updateStmt = $pdo->prepare("UPDATE course_codes SET used_by = :user_id, used_at = NOW() WHERE id = :id");
        $updateStmt->execute(['user_id' => $userId, 'id' => $codeId]);

        // إنشاء اشتراك
        $insertStmt = $pdo->prepare("
            INSERT INTO user_course_subscriptions (user_id, course_id, code_id, status, progress_percentage)
            VALUES (:user_id, :course_id, :code_id, 'active', 0)
        ");
        $insertStmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'code_id' => $codeId
        ]);

        // ترقية الحساب من مجاني إلى VIP بعد تفعيل أي كود
        try {
            $upgradeStmt = $pdo->prepare("UPDATE users SET account_type = 'vip' WHERE id = :user_id");
            $upgradeStmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            // العمود account_type غير موجود بعد - نكمل دون ترقية
        }

        $pdo->commit();

        sendJsonResponse(true, [
            'course_id' => $courseId,
            'course_title' => $course['title'],
            'redirect_url' => 'course-detail.html?id=' . $courseId
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (PDOException $e) {
    error_log('Redeem code error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تفعيل الكود', 500);
}
