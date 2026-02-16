<?php
/**
 * ============================================
 * Get Course Codes API (Admin)
 * ============================================
 * API لجلب قائمة أكواد التفعيل مع الكورس المرتبط
 * Endpoint: GET /api/admin/get-course-codes.php
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

    $stmt = $pdo->query("
        SELECT
            cc.id,
            cc.course_id,
            cc.code,
            cc.used_by,
            cc.used_at,
            cc.expires_at,
            cc.is_active,
            cc.created_at,
            c.title AS course_title
        FROM course_codes cc
        INNER JOIN courses c ON c.id = cc.course_id
        ORDER BY cc.created_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $codes = [];
    foreach ($rows as $row) {
        $usedByName = '—';
        if (!empty($row['used_by'])) {
            $userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $userStmt->execute([$row['used_by']]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            $usedByName = $userRow ? $userRow['full_name'] : '—';
        }
        $createdAt = $row['created_at'];
        $createdAtFormatted = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '—';
        $status = ($row['used_by'] !== null) ? 'مفعل' : 'غير مفعل';
        $codes[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'course_id' => (int) $row['course_id'],
            'course_title' => $row['course_title'],
            'created_at' => $createdAtFormatted,
            'status' => $status,
            'used_by' => $usedByName,
            'used_at' => $row['used_at'] ? date('d/m/Y H:i', strtotime($row['used_at'])) : null,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => ['codes' => $codes]
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get course codes error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في جلب الأكواد'], JSON_UNESCAPED_UNICODE);
}
