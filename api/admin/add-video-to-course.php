<?php
/**
 * ============================================
 * Add Video to Existing Course (Admin)
 * ============================================
 * إضافة فيديو واحد لكورس موجود (رفع إلى Bunny CDN + حفظ في قاعدة البيانات)
 *
 * Endpoint: POST /api/admin/add-video-to-course.php
 * Body: { course_id, title, description, video_order, videoFile (base64), thumbnailFile (base64) }
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/bunny-cdn.php';

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
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($data)) {
    sendJsonResponse(false, null, 'بيانات الطلب غير صالحة. أرسل JSON يتضمن course_id, title, description, video_order, videoFile, thumbnailFile.', 400);
}

$courseId = isset($data['course_id']) ? (int) $data['course_id'] : 0;
if ($courseId < 1) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب ويجب أن يكون رقماً صحيحاً أكبر من 0', 400);
}

$title = isset($data['title']) ? sanitizeInput(trim($data['title'])) : '';
$description = isset($data['description']) ? sanitizeInput($data['description']) : '';
$videoOrder = isset($data['video_order']) ? (int) $data['video_order'] : 1;
if ($videoOrder < 1) {
    $videoOrder = 1;
}

$videoFileBase64 = isset($data['videoFile']) && is_string($data['videoFile']) ? trim($data['videoFile']) : '';
$thumbnailFileBase64 = isset($data['thumbnailFile']) && is_string($data['thumbnailFile']) ? trim($data['thumbnailFile']) : '';

if ($title === '') {
    sendJsonResponse(false, null, 'عنوان الفيديو مطلوب', 400);
}
if ($videoFileBase64 === '') {
    sendJsonResponse(false, null, 'ملف الفيديو مطلوب', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $check = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
    $check->execute([$courseId]);
    if (!$check->fetch()) {
        sendJsonResponse(false, null, 'الكورس غير موجود', 404);
    }

    $courseLibraryId = (int) env('BUNNY_LIBRARY_ID', '');
    if ($courseLibraryId <= 0) {
        sendJsonResponse(false, null, 'معرف مكتبة الكورسات مطلوب. أضف BUNNY_LIBRARY_ID في ملف .env', 500);
    }
    $courseLibraryApiKey = defined('BUNNY_API_KEY') ? (string) BUNNY_API_KEY : '';
    if ($courseLibraryApiKey === '') {
        sendJsonResponse(false, null, 'مفتاح Bunny API مفقود. أضف BUNNY_API_KEY في ملف .env', 500);
    }

    $videoContent = base64_decode($videoFileBase64, true);
    if ($videoContent === false || strlen($videoContent) === 0) {
        sendJsonResponse(false, null, 'فشل فك تشفير ملف الفيديو', 400);
    }

    $tempVideoPath = sys_get_temp_dir() . '/bunny_upload_' . uniqid() . '.mp4';
    if (file_put_contents($tempVideoPath, $videoContent) === false) {
        sendJsonResponse(false, null, 'فشل حفظ ملف الفيديو مؤقتاً', 500);
    }

    $bunnyVideo = createBunnyVideo($title, $courseLibraryId, $courseLibraryApiKey);
    if (!$bunnyVideo || !isset($bunnyVideo['guid'])) {
        @unlink($tempVideoPath);
        $err = isset($bunnyVideo['error']) ? $bunnyVideo['error'] : 'فشل إنشاء فيديو في Bunny CDN';
        sendJsonResponse(false, null, $err, 500);
    }

    $bunnyVideoId = $bunnyVideo['guid'];

    $uploadSuccess = uploadBunnyVideo($bunnyVideoId, $tempVideoPath, $courseLibraryId, $courseLibraryApiKey);
    @unlink($tempVideoPath);

    if (!$uploadSuccess) {
        sendJsonResponse(false, null, 'فشل رفع الفيديو إلى Bunny CDN', 500);
    }

    $thumbnailUrl = null;
    if ($thumbnailFileBase64 !== '') {
        $thumbnailContent = base64_decode($thumbnailFileBase64, true);
        if ($thumbnailContent !== false && strlen($thumbnailContent) > 0) {
            $uploadsDir = __DIR__ . '/../../uploads/thumbnails/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $thumbnailFileName = 'thumb_' . $courseId . '_' . $videoOrder . '_' . uniqid() . '.jpg';
            $thumbnailPath = $uploadsDir . $thumbnailFileName;
            if (file_put_contents($thumbnailPath, $thumbnailContent) !== false) {
                $thumbnailUrl = '/uploads/thumbnails/' . $thumbnailFileName;
            }
        }
    }

    $videoInfo = getBunnyVideoInfo($bunnyVideoId, $courseLibraryId, $courseLibraryApiKey);
    $videoUrl = getBunnyVideoUrl($bunnyVideoId, $courseLibraryId);
    $duration = null;
    $fileSize = null;
    if ($videoInfo) {
        $duration = isset($videoInfo['length']) ? (int) $videoInfo['length'] : null;
        $fileSize = isset($videoInfo['storageSize']) ? (int) $videoInfo['storageSize'] : null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO course_videos (
            course_id, title, description, video_order,
            thumbnail_url, video_url, bunny_video_id,
            duration, file_size, status
        )
        VALUES (
            :course_id, :title, :description, :video_order,
            :thumbnail_url, :video_url, :bunny_video_id,
            :duration, :file_size, 'processing'
        )
    ");
    $stmt->execute([
        'course_id' => $courseId,
        'title' => $title,
        'description' => $description,
        'video_order' => $videoOrder,
        'thumbnail_url' => $thumbnailUrl,
        'video_url' => $videoUrl,
        'bunny_video_id' => $bunnyVideoId,
        'duration' => $duration,
        'file_size' => $fileSize
    ]);

    $videoId = (int) $pdo->lastInsertId();

    $updateStmt = $pdo->prepare("UPDATE course_videos SET status = 'ready' WHERE id = ?");
    $updateStmt->execute([$videoId]);

    sendJsonResponse(true, [
        'message' => 'تم إضافة الفيديو بنجاح',
        'video_id' => $videoId,
        'title' => $title,
        'order' => $videoOrder,
        'thumbnail_url' => $thumbnailUrl,
        'video_url' => $videoUrl
    ], null, 200);

} catch (PDOException $e) {
    error_log('Add video to course DB error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء حفظ الفيديو', 500);
} catch (Exception $e) {
    error_log('Add video to course error: ' . $e->getMessage());
    sendJsonResponse(false, null, $e->getMessage(), 500);
}
