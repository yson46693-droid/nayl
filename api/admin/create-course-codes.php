<?php
/**
 * ============================================
 * Create Course Codes API (Admin)
 * ============================================
 * API لإنشاء أكواد تفعيل مرتبطة بكورس محدد
 * Endpoint: POST /api/admin/create-course-codes.php
 * Body: { "course_id": 1, "count": 5, "expiry_days": 365 }
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
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$count = isset($input['count']) ? (int) $input['count'] : 1;
$expiryDays = isset($input['expiry_days']) ? (int) $input['expiry_days'] : 365;

if ($courseId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف الكورس مطلوب'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($count < 1 || $count > 100) {
    echo json_encode(['success' => false, 'error' => 'عدد الأكواد يجب أن يكون بين 1 و 100'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($expiryDays < 0) {
    $expiryDays = 365;
}

$adminId = (int) ($admin['id'] ?? $admin['admin_id'] ?? 0);

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $courseStmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'الكورس غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $expiresAt = null;
    if ($expiryDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    }

    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $insertStmt = $pdo->prepare("
        INSERT INTO course_codes (course_id, code, used_by, used_at, expires_at, is_active, created_by)
        VALUES (:course_id, :code, NULL, NULL, :expires_at, 1, :created_by)
    ");

    $created = 0;
    $maxAttemptsPerCode = 30;

    for ($i = 0; $i < $count; $i++) {
        $inserted = false;
        for ($attempt = 0; $attempt < $maxAttemptsPerCode; $attempt++) {
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $checkStmt = $pdo->prepare("SELECT 1 FROM course_codes WHERE code = ? LIMIT 1");
            $checkStmt->execute([$code]);
            if ($checkStmt->fetch()) {
                continue;
            }
            $insertStmt->execute([
                'course_id' => $courseId,
                'code' => $code,
                'expires_at' => $expiresAt,
                'created_by' => $adminId ?: null
            ]);
            $created++;
            $inserted = true;
            break;
        }
        if (!$inserted) {
            error_log("Create course codes: could not generate unique code after {$maxAttemptsPerCode} attempts");
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'created' => $created,
            'course_id' => $courseId,
            'course_title' => $course['title']
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Create course codes error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في إنشاء الأكواد'], JSON_UNESCAPED_UNICODE);
}
