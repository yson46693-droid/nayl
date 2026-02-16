<?php
/**
 * ============================================
 * Verify Course Access (Activation Code)
 * ============================================
 * التحقق من كود التفعيل للكورس: الكود يجب أن يكون مرتبطاً بالمستخدم الحالي لهذا الكورس.
 * جهاز واحد فقط: عند أول تفعيل ناجح تُربط بصمة الجهاز بالكود؛ أي جهاز آخر يُرفض.
 * Endpoint: POST /api/courses/verify-access.php
 * Body: { "course_id": 1, "code": "XXXXXXXXXX", "fingerprint_hash": "64 hex chars" }
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
    sendJsonResponse(false, null, 'يجب تسجيل الدخول لإدخال كود التفعيل', 401);
}

$userId = (int) $user['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$code = isset($input['code']) ? trim((string) $input['code']) : '';
$fingerprintHash = isset($input['fingerprint_hash']) ? trim((string) $input['fingerprint_hash']) : '';

if ($courseId <= 0) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب', 400);
}

if ($code === '') {
    sendJsonResponse(false, null, 'كود التفعيل مطلوب', 400);
}

// بصمة الجهاز مطلوبة لربط الكورس بجهاز واحد فقط
if ($fingerprintHash === '' || !preg_match('/^[a-f0-9]{64}$/i', $fingerprintHash)) {
    sendJsonResponse(false, null, 'لم يتم التعرف على الجهاز. حدّث الصفحة وحاول مرة أخرى.', 400);
}
$fingerprintHash = strtolower(substr($fingerprintHash, 0, 64));

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

    // التحقق: الكود موجود لهذا الكورس ومرتبط بالمستخدم الحالي فقط + جلب bound_device_hash
    $stmt = $pdo->prepare("
        SELECT cc.id, cc.bound_device_hash
        FROM course_codes cc
        WHERE cc.course_id = :course_id
          AND cc.used_by = :user_id
          AND cc.is_active = 1
          AND UPPER(REPLACE(REPLACE(REPLACE(REPLACE(cc.code, '-', ''), ' ', ''), '_', ''), '.', '')) = :code
        LIMIT 1
    ");
    $stmt->execute([
        'course_id' => $courseId,
        'code' => $code,
        'user_id' => $userId
    ]);
    $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeRow) {
        sendJsonResponse(false, null, 'كود المشاهدة غير صحيح أو غير مرتبط بحسابك لهذا الكورس', 400);
    }

    $codeId = (int) $codeRow['id'];
    $boundHash = $codeRow['bound_device_hash'] ? strtolower(trim($codeRow['bound_device_hash'])) : null;

    // جهاز واحد فقط: إن كان الكود مُربوطاً بجهاز سابق، يجب أن تكون البصمة الحالية نفسها
    if ($boundHash !== null) {
        if ($fingerprintHash !== $boundHash) {
            sendJsonResponse(false, null, 'هذا الكورس مفعّل على جهاز آخر. للمشاهدة استخدم نفس الجهاز الذي فعّلت عليه الكود، أو تواصل مع الدعم لطلب نقل التفعيل.', 403);
        }
    } else {
        // أول تفعيل: ربط هذا الجهاز بالكود (إن وُجد العمود - متوافق مع قواعد قديمة)
        try {
            $updateStmt = $pdo->prepare("UPDATE course_codes SET bound_device_hash = :fp WHERE id = :id");
            $updateStmt->execute(['fp' => $fingerprintHash, 'id' => $codeId]);
        } catch (PDOException $e) {
            // العمود bound_device_hash غير موجود بعد - متابعة بدون ربط الجهاز
        }
    }

    // التحقق من وجود اشتراك فعال
    $subStmt = $pdo->prepare("
        SELECT 1 FROM user_course_subscriptions
        WHERE user_id = :user_id AND course_id = :course_id AND status = 'active'
        LIMIT 1
    ");
    $subStmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if (!$subStmt->fetch()) {
        sendJsonResponse(false, null, 'لا يوجد اشتراك فعال لهذا الكورس', 400);
    }

    // حفظ التحقق في الجلسة + بصمة الجهاز لهذا الكورس (للمقارنة في proxy/get-detail إن لزم)
    if (!isset($_SESSION['course_view_verified'])) {
        $_SESSION['course_view_verified'] = [];
    }
    $_SESSION['course_view_verified'][$courseId] = true;
    if (!isset($_SESSION['course_device'])) {
        $_SESSION['course_device'] = [];
    }
    $_SESSION['course_device'][$courseId] = $fingerprintHash;

    sendJsonResponse(true, ['verified' => true, 'course_id' => $courseId]);
} catch (PDOException $e) {
    error_log('Verify access error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء التحقق', 500);
}
