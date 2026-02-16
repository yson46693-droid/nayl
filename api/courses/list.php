<?php
/**
 * ============================================
 * List Published Courses API
 * ============================================
 * جلب قائمة الكورسات المنشورة (لصفحة الكورسات)
 * إذا كان المستخدم مسجلاً: يُرجَع has_subscription لكل كورس
 * Endpoint: GET /api/courses/list.php
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
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = verifySession();
$userId = $user ? (int) $user['id'] : null;

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
        exit;
    }

    if ($userId) {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.title,
                c.description,
                c.cover_image_url,
                c.created_at,
                COUNT(DISTINCT cv.id) AS videos_count,
                COALESCE(SUM(cv.duration), 0) AS total_duration_seconds,
                (SELECT 1 FROM user_course_subscriptions ucs WHERE ucs.course_id = c.id AND ucs.user_id = :user_id AND ucs.status = 'active' LIMIT 1) AS has_subscription
            FROM courses c
            LEFT JOIN course_videos cv ON cv.course_id = c.id AND cv.status = 'ready'
            WHERE c.status = 'published'
            GROUP BY c.id, c.title, c.description, c.cover_image_url, c.created_at
            ORDER BY c.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
    } else {
        $stmt = $pdo->query("
            SELECT
                c.id,
                c.title,
                c.description,
                c.cover_image_url,
                COUNT(cv.id) AS videos_count,
                COALESCE(SUM(cv.duration), 0) AS total_duration_seconds,
                0 AS has_subscription
            FROM courses c
            LEFT JOIN course_videos cv ON cv.course_id = c.id AND cv.status = 'ready'
            WHERE c.status = 'published'
            GROUP BY c.id, c.title, c.description, c.cover_image_url, c.created_at
            ORDER BY c.created_at DESC
        ");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $courses = [];
    foreach ($rows as $row) {
        $courses[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?: '',
            'cover_image_url' => $row['cover_image_url'] ?: null,
            'videos_count' => (int) $row['videos_count'],
            'total_duration_seconds' => (int) $row['total_duration_seconds'],
            'has_subscription' => !empty($row['has_subscription'])
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => ['courses' => $courses]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Courses list API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في جلب الكورسات'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Courses list API unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحميل الكورسات'], JSON_UNESCAPED_UNICODE);
}
