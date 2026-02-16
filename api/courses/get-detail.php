<?php
/**
 * ============================================
 * Get Course Detail (with videos) - User
 * ============================================
 * جلب تفاصيل كورس مع قائمة الفيديوهات للمشاهدة (يُسمح فقط للمشتركين)
 * Endpoint: GET /api/courses/get-detail.php?id=1
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
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Fingerprint');
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$user = requireAuth();
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول لعرض الكورس', 401);
}

$userId = (int) $user['id'];
$courseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($courseId <= 0) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    // التحقق من إدخال كود المشاهدة في هذه الجلسة (مطلوب حتى للمشترين)
    if (empty($_SESSION['course_view_verified'][$courseId])) {
        sendJsonResponse(false, null, 'أدخل كود المشاهدة أولاً للتحقق من صلاحيتك', 403);
    }

    // التحقق من اشتراك المستخدم في الكورس
    $subStmt = $pdo->prepare("
        SELECT ucs.code_id
        FROM user_course_subscriptions ucs
        WHERE ucs.user_id = :user_id AND ucs.course_id = :course_id AND ucs.status = 'active'
        LIMIT 1
    ");
    $subStmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
    $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
    if (!$subRow) {
        sendJsonResponse(false, null, 'ليس لديك اشتراك في هذا الكورس. أدخل كود التفعيل أولاً.', 403);
    }

    // جهاز واحد فقط: جلب bound_device_hash إن وُجد العمود (متوافق مع قواعد لم تُطبَّق عليها الـ migration)
    $boundDeviceHash = null;
    $codeId = isset($subRow['code_id']) ? (int) $subRow['code_id'] : 0;
    if ($codeId > 0) {
        try {
            $ccStmt = $pdo->prepare("SELECT bound_device_hash FROM course_codes WHERE id = :id LIMIT 1");
            $ccStmt->execute(['id' => $codeId]);
            $ccRow = $ccStmt->fetch(PDO::FETCH_ASSOC);
            if ($ccRow && isset($ccRow['bound_device_hash']) && $ccRow['bound_device_hash'] !== '' && $ccRow['bound_device_hash'] !== null) {
                $boundDeviceHash = strtolower(trim($ccRow['bound_device_hash']));
            }
        } catch (PDOException $e) {
            // العمود bound_device_hash غير موجود بعد - تخطي التحقق من الجهاز
        }
    }

    if ($boundDeviceHash !== null) {
        $clientFp = isset($_SERVER['HTTP_X_DEVICE_FINGERPRINT']) ? trim((string) $_SERVER['HTTP_X_DEVICE_FINGERPRINT']) : '';
        $clientFpNormalized = preg_match('/^[a-f0-9]{64}$/i', $clientFp) ? strtolower(substr($clientFp, 0, 64)) : '';
        if ($clientFpNormalized === '' || $clientFpNormalized !== $boundDeviceHash) {
            sendJsonResponse(false, null, 'المشاهدة متاحة فقط على الجهاز الذي فعّلت عليه الكورس. استخدم نفس الجهاز أو تواصل مع الدعم.', 403);
        }
    }

    // جلب تفاصيل الكورس
    $courseStmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.cover_image_url,
               COUNT(cv.id) AS videos_count,
               COALESCE(SUM(cv.duration), 0) AS total_duration_seconds
        FROM courses c
        LEFT JOIN course_videos cv ON cv.course_id = c.id AND cv.status = 'ready'
        WHERE c.id = :id AND c.status = 'published'
        GROUP BY c.id, c.title, c.description, c.cover_image_url
    ");
    $courseStmt->execute(['id' => $courseId]);
    $courseRow = $courseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$courseRow) {
        sendJsonResponse(false, null, 'الكورس غير موجود أو غير متاح', 404);
    }

    // جلب قائمة الفيديوهات (بدون video_url للأمان - التشغيل عبر proxy)
    $videosStmt = $pdo->prepare("
        SELECT id, title, description, video_order, duration, thumbnail_url
        FROM course_videos
        WHERE course_id = :course_id AND status = 'ready'
        ORDER BY video_order ASC, id ASC
    ");
    $videosStmt->execute(['course_id' => $courseId]);
    $videoRows = $videosStmt->fetchAll(PDO::FETCH_ASSOC);

    $videos = [];
    foreach ($videoRows as $v) {
        $videos[] = [
            'id' => (int) $v['id'],
            'title' => $v['title'],
            'description' => $v['description'] ?: '',
            'order' => (int) $v['video_order'],
            'duration' => (int) ($v['duration'] ?? 0),
            'thumbnail_url' => !empty($v['thumbnail_url']) ? $v['thumbnail_url'] : null
        ];
    }

    $course = [
        'id' => (int) $courseRow['id'],
        'title' => $courseRow['title'],
        'description' => $courseRow['description'] ?: '',
        'cover_image_url' => $courseRow['cover_image_url'] ?: null,
        'videos_count' => (int) $courseRow['videos_count'],
        'total_duration_seconds' => (int) $courseRow['total_duration_seconds'],
        'videos' => $videos
    ];

    sendJsonResponse(true, ['course' => $course]);
} catch (PDOException $e) {
    error_log('Get course detail error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ في جلب تفاصيل الكورس', 500);
}
