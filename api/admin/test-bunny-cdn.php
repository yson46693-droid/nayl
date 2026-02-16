<?php
/**
 * ============================================
 * Test Bunny CDN Connection
 * ============================================
 * ملف اختبار للتحقق من اتصال Bunny CDN
 */

// استيراد الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bunny-cdn.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

// التحقق من تسجيل الدخول كأدمن
$admin = requireAdminAuth(true);
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'يجب تسجيل الدخول كمسؤول'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// اختبار الاتصال
$results = [];

// 1. التحقق من البيانات
$libraryId = BUNNY_LIBRARY_ID;
$apiKey = BUNNY_API_KEY;

$results['credentials'] = [
    'library_id' => !empty($libraryId) ? 'موجود (' . substr($libraryId, 0, 3) . '...)' : 'غير موجود',
    'api_key' => !empty($apiKey) ? 'موجود (' . substr($apiKey, 0, 10) . '...)' : 'غير موجود'
];

if (empty($libraryId) || empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'error' => 'بيانات Bunny CDN غير موجودة',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. اختبار إنشاء فيديو
$testTitle = 'Test Video - ' . date('Y-m-d H:i:s');
$bunnyVideo = createBunnyVideo($testTitle);

$results['create_video'] = [
    'title' => $testTitle,
    'result' => $bunnyVideo ? 'نجح' : 'فشل',
    'response' => $bunnyVideo
];

if (!$bunnyVideo || isset($bunnyVideo['error'])) {
    echo json_encode([
        'success' => false,
        'error' => 'فشل إنشاء فيديو تجريبي',
        'results' => $results,
        'details' => $bunnyVideo
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$videoId = $bunnyVideo['guid'] ?? null;

if (!$videoId) {
    echo json_encode([
        'success' => false,
        'error' => 'لم يتم الحصول على معرف الفيديو',
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. اختبار الحصول على معلومات الفيديو
$videoInfo = getBunnyVideoInfo($videoId);

$results['get_video_info'] = [
    'video_id' => $videoId,
    'result' => $videoInfo ? 'نجح' : 'فشل',
    'response' => $videoInfo
];

// 4. اختبار بناء رابط الفيديو
$videoUrl = getBunnyVideoUrl($videoId);

$results['get_video_url'] = [
    'video_id' => $videoId,
    'url' => $videoUrl
];

// النتيجة النهائية
echo json_encode([
    'success' => true,
    'message' => 'تم اختبار اتصال Bunny CDN بنجاح',
    'results' => $results,
    'video_id' => $videoId,
    'video_url' => $videoUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
