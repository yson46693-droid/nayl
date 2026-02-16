<?php
/**
 * ============================================
 * Get Course Details (with videos) - Admin
 * ============================================
 * API لجلب تفاصيل كورس واحد مع قائمة الفيديوهات (لنافذة تعديل الكورس)
 * Endpoint: GET /api/admin/get-course-details.php?course_id=1
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($courseId <= 0) {
    http_response_code(400);
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

    $stmt = $pdo->prepare("
        SELECT id, title, description, status, created_at
        FROM courses
        WHERE id = ?
    ");
    $stmt->execute([$courseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'الكورس غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $statusAr = 'منشور';
    if ($row['status'] === 'draft') {
        $statusAr = 'مسودة';
    } elseif ($row['status'] === 'archived') {
        $statusAr = 'مؤرشف';
    }
    $createdAt = $row['created_at'];
    $uploadDate = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '—';

    $stmtVideos = $pdo->prepare("
        SELECT id, title, description, video_order, duration, status, thumbnail_url, video_url
        FROM course_videos
        WHERE course_id = ?
        ORDER BY video_order ASC, id ASC
    ");
    $stmtVideos->execute([$courseId]);
    $videoRows = $stmtVideos->fetchAll(PDO::FETCH_ASSOC);

    $videos = [];
    foreach ($videoRows as $v) {
        $videos[] = [
            'id' => (int) $v['id'],
            'title' => $v['title'],
            'description' => $v['description'] ?: '',
            'order' => (int) $v['video_order'],
            'duration' => (int) ($v['duration'] ?? 0),
            'status' => $v['status'],
            'thumbnail_url' => !empty($v['thumbnail_url']) ? $v['thumbnail_url'] : null,
            'video_url' => !empty($v['video_url']) ? $v['video_url'] : null
        ];
    }

    $course = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'description' => $row['description'] ?: '',
        'status' => $statusAr,
        'statusRaw' => $row['status'],
        'uploadDate' => $uploadDate,
        'created_at' => $createdAt,
        'videosCount' => count($videos),
        'videos' => $videos
    ];

    echo json_encode([
        'success' => true,
        'data' => ['course' => $course]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get course details error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في جلب تفاصيل الكورس'], JSON_UNESCAPED_UNICODE);
}
