<?php
/**
 * ============================================
 * Get All Courses API (Admin)
 * ============================================
 * API لجلب قائمة الكورسات للمشرفين (للعرض في لوحة التحكم)
 * Endpoint: GET /api/admin/get-courses.php
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

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // استعلام بدون عمود price ليعمل حتى لو لم يُضف العمود بعد (migration)
    $stmt = $pdo->query("
        SELECT
            c.id,
            c.title,
            c.description,
            c.status,
            c.created_at,
            COUNT(cv.id) AS videos_count
        FROM courses c
        LEFT JOIN course_videos cv ON cv.course_id = c.id
        GROUP BY c.id, c.title, c.description, c.status, c.created_at
        ORDER BY c.created_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $courses = [];
    foreach ($rows as $row) {
        $statusAr = 'منشور';
        if ($row['status'] === 'draft') {
            $statusAr = 'مسودة';
        } elseif ($row['status'] === 'archived') {
            $statusAr = 'مؤرشف';
        }
        $createdAt = $row['created_at'];
        $uploadDate = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '—';
        $courses[] = [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?: '',
            'status' => $statusAr,
            'statusRaw' => $row['status'],
            'uploadDate' => $uploadDate,
            'created_at' => $createdAt,
            'videosCount' => (int) $row['videos_count'],
            'price' => 500.00
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => ['courses' => $courses]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get courses error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في جلب الكورسات'], JSON_UNESCAPED_UNICODE);
}
