<?php
/**
 * ============================================
 * Get Single Course API
 * ============================================
 * جلب تفاصيل كورس واحد (لصفحة الشراء)
 * Endpoint: GET /api/courses/get.php?id=1
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

loadEnv(__DIR__ . '/../.env');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$courseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الكورس مطلوب']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.title,
            c.description,
            c.cover_image_url,
            COALESCE(c.price, 500) AS price,
            c.status,
            COUNT(cv.id) AS videos_count,
            COALESCE(SUM(cv.duration), 0) AS total_duration_seconds
        FROM courses c
        LEFT JOIN course_videos cv ON cv.course_id = c.id AND cv.status = 'ready'
        WHERE c.id = :id
        GROUP BY c.id, c.title, c.description, c.cover_image_url, c.price, c.status
    ");
    $stmt->execute(['id' => $courseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الكورس غير موجود']);
        exit;
    }

    if ($row['status'] !== 'published') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الكورس غير متاح']);
        exit;
    }

    $price = isset($row['price']) ? (float) $row['price'] : 500.00;
    if ($price < 0) {
        $price = 500.00;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?: '',
            'cover_image_url' => $row['cover_image_url'] ?: null,
            'price' => $price,
            'videos_count' => (int) $row['videos_count'],
            'total_duration_seconds' => (int) $row['total_duration_seconds']
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Course get API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في جلب الكورس'], JSON_UNESCAPED_UNICODE);
}
