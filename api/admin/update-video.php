<?php
/**
 * ============================================
 * Update Video API (Admin)
 * ============================================
 * API لتعديل بيانات فيديو (العنوان، الوصف، الترتيب، صورة الواجهة، استبدال ملف الفيديو)
 *
 * Endpoint: POST /api/admin/update-video.php
 * Body: { video_id, title?, description?, video_order?, thumbnail_base64?, video_base64? }
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
    sendJsonResponse(false, null, 'بيانات الطلب غير صالحة أو فارغة. تأكد من إرسال JSON يتضمن video_id.', 400);
}

$videoId = isset($data['video_id']) ? (int) $data['video_id'] : 0;
if ($videoId < 1) {
    sendJsonResponse(false, null, 'معرف الفيديو مطلوب ويجب أن يكون رقماً صحيحاً أكبر من 0', 400);
}

$title = isset($data['title']) ? sanitizeInput(trim($data['title'])) : null;
$description = isset($data['description']) ? sanitizeInput($data['description']) : null;
$videoOrder = isset($data['video_order']) ? (int) $data['video_order'] : null;
$thumbnailBase64 = isset($data['thumbnail_base64']) ? $data['thumbnail_base64'] : null;
$videoBase64 = isset($data['video_base64']) && is_string($data['video_base64']) ? trim($data['video_base64']) : '';

if ($videoOrder !== null && ($videoOrder < 1 || $videoOrder > 9999)) {
    sendJsonResponse(false, null, 'ترتيب الفيديو يجب أن يكون بين 1 و 9999', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $check = $pdo->prepare("SELECT id, course_id, title FROM course_videos WHERE id = ?");
    $check->execute([$videoId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendJsonResponse(false, null, 'الفيديو غير موجود', 404);
    }

    $courseId = (int) $row['course_id'];
    $currentTitle = isset($row['title']) ? trim($row['title']) : 'Video';
    $updates = [];
    $params = ['video_id' => $videoId];
    $newVideoUrl = null;

    if ($title !== null) {
        $updates[] = "title = :title";
        $params['title'] = $title;
    }
    if ($description !== null) {
        $updates[] = "description = :description";
        $params['description'] = $description;
    }
    if ($videoOrder !== null) {
        $updates[] = "video_order = :video_order";
        $params['video_order'] = $videoOrder;
    }

    $thumbnailUrl = null;
    if (!empty($thumbnailBase64) && is_string($thumbnailBase64)) {
        $base64Data = preg_replace('#^data:image/[^;]+;base64,#i', '', trim($thumbnailBase64));
        $base64Data = preg_replace('#\s+#', '', $base64Data);
        $thumbnailContent = base64_decode($base64Data, true);
        if ($thumbnailContent !== false && strlen($thumbnailContent) > 0 && strlen($thumbnailContent) <= 5 * 1024 * 1024) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($thumbnailContent);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($mime, $allowedMimes, true)) {
                $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : ($mime === 'image/webp' ? 'webp' : 'jpg'));
                $uploadsBase = __DIR__ . '/../../uploads/';
                $uploadsDir = $uploadsBase . 'thumbnails/';
                if (!is_dir($uploadsBase)) {
                    @mkdir($uploadsBase, 0755, true);
                }
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0755, true);
                }
                if (is_dir($uploadsDir) && is_writable($uploadsDir)) {
                    $thumbnailFileName = 'thumb_' . $courseId . '_' . $videoId . '_' . uniqid() . '.' . $ext;
                    $thumbnailPath = $uploadsDir . $thumbnailFileName;
                    if (@file_put_contents($thumbnailPath, $thumbnailContent) !== false) {
                        $thumbnailUrl = '/uploads/thumbnails/' . $thumbnailFileName;
                        $updates[] = "thumbnail_url = :thumbnail_url";
                        $params['thumbnail_url'] = $thumbnailUrl;
                    }
                }
            }
        }
    }

    if (!empty($videoBase64)) {
        $videoContent = base64_decode($videoBase64, true);
        if ($videoContent !== false && strlen($videoContent) > 0) {
            $courseLibraryId = (int) env('BUNNY_LIBRARY_ID', '');
            if ($courseLibraryId <= 0) {
                sendJsonResponse(false, null, 'معرف مكتبة الكورسات مطلوب. أضف BUNNY_LIBRARY_ID في ملف .env', 500);
            }
            $courseLibraryApiKey = defined('BUNNY_API_KEY') ? (string) BUNNY_API_KEY : '';
            if ($courseLibraryApiKey !== '') {
                $tempVideoPath = sys_get_temp_dir() . '/bunny_replace_' . uniqid() . '.mp4';
                if (file_put_contents($tempVideoPath, $videoContent) !== false) {
                    $bunnyVideo = createBunnyVideo($title !== null ? $title : $currentTitle, $courseLibraryId, $courseLibraryApiKey);
                    if ($bunnyVideo && !empty($bunnyVideo['guid'])) {
                        $bunnyVideoId = $bunnyVideo['guid'];
                        $uploadSuccess = uploadBunnyVideo($bunnyVideoId, $tempVideoPath, $courseLibraryId, $courseLibraryApiKey);
                        @unlink($tempVideoPath);
                        if ($uploadSuccess) {
                            $videoInfo = getBunnyVideoInfo($bunnyVideoId, $courseLibraryId, $courseLibraryApiKey);
                            $newVideoUrl = getBunnyVideoUrl($bunnyVideoId, $courseLibraryId);
                            $duration = null;
                            if ($videoInfo && isset($videoInfo['length'])) {
                                $duration = (int) $videoInfo['length'];
                            }
                            $updates[] = "video_url = :video_url";
                            $params['video_url'] = $newVideoUrl;
                            $updates[] = "bunny_video_id = :bunny_video_id";
                            $params['bunny_video_id'] = $bunnyVideoId;
                            if ($duration !== null) {
                                $updates[] = "duration = :duration";
                                $params['duration'] = $duration;
                            }
                        } else {
                            sendJsonResponse(false, null, 'فشل رفع الفيديو الجديد إلى Bunny CDN', 500);
                        }
                    } else {
                        @unlink($tempVideoPath);
                        $err = ($bunnyVideo && isset($bunnyVideo['error'])) ? $bunnyVideo['error'] : 'فشل إنشاء فيديو في Bunny CDN';
                        sendJsonResponse(false, null, $err, 500);
                    }
                } else {
                    sendJsonResponse(false, null, 'فشل حفظ ملف الفيديو مؤقتاً', 500);
                }
            } else {
                sendJsonResponse(false, null, 'مفتاح Bunny API مفقود. أضف BUNNY_API_KEY في ملف .env', 500);
            }
        } else {
            sendJsonResponse(false, null, 'فشل فك تشفير ملف الفيديو أو الملف فارغ', 400);
        }
    }

    if (empty($updates)) {
        sendJsonResponse(true, ['message' => 'لم يتم تغيير أي حقل'], null, 200);
    }

    $sql = "UPDATE course_videos SET " . implode(", ", $updates) . " WHERE id = :video_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $responseData = ['message' => 'تم تحديث الفيديو بنجاح', 'thumbnail_url' => $thumbnailUrl];
    if ($newVideoUrl !== null) {
        $responseData['video_url'] = $newVideoUrl;
    }
    sendJsonResponse(true, $responseData, null, 200);
} catch (PDOException $e) {
    error_log('Update video error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تحديث الفيديو', 500);
}
