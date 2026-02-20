<?php
/**
 * ============================================
 * Get My Course Codes API
 * ============================================
 * API لجلب أكواد الكورسات الخاصة بالمستخدم
 * (الأكواد التي حصل عليها بعد شراء أو تفعيل الكورسات)
 *
 * Endpoint: GET /api/courses/get-my-course-codes.php
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

$user = requireAuth();

$origin = getAllowedOrigin();
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// منع تخزين الاستجابة في كاش المتصفح (حل مشكلة عدم ظهور الأكواد على Chrome)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $userId = (int) $user['id'];
    $allCodes = [];
    $codesFromUsed = [];
    $codesFromSubs = [];

    // جلب الأكواد من course_codes التي استخدمها المستخدم
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                cc.id,
                cc.code,
                cc.used_at,
                c.id as course_id,
                c.title as course_title,
                c.cover_image_url
            FROM course_codes cc
            INNER JOIN courses c ON cc.course_id = c.id
            WHERE cc.used_by = :user_id 
              AND cc.is_active = 1
              AND c.status = 'published'
            ORDER BY cc.used_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        $codesFromUsed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get My Course Codes (used): " . $e->getMessage());
    }

    // جلب الأكواد من الاشتراكات التي لها code_id
    try {
        $stmt2 = $pdo->prepare("
            SELECT DISTINCT 
                cc.id,
                cc.code,
                COALESCE(cc.used_at, ucs.started_at) as used_at,
                c.id as course_id,
                c.title as course_title,
                c.cover_image_url
            FROM user_course_subscriptions ucs
            INNER JOIN course_codes cc ON ucs.code_id = cc.id
            INNER JOIN courses c ON cc.course_id = c.id
            WHERE ucs.user_id = :user_id 
              AND ucs.status = 'active'
              AND cc.is_active = 1
              AND c.status = 'published'
            ORDER BY ucs.started_at DESC
        ");
        $stmt2->execute([':user_id' => $userId]);
        $codesFromSubs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get My Course Codes (subs): " . $e->getMessage());
    }

    // دمج النتائج وإزالة التكرار (ضمان عدم تكرار أي كود)
    $seenIds = [];
    $codeMap = [];
    foreach (array_merge($codesFromUsed, $codesFromSubs) as $row) {
        $cid = (int) $row['id'];
        if (!isset($codeMap[$cid])) {
            $codeMap[$cid] = [
                'id' => $cid,
                'code' => $row['code'],
                'course_id' => (int) $row['course_id'],
                'course_title' => $row['course_title'],
                'cover_image_url' => $row['cover_image_url'],
                'obtained_at' => $row['used_at']
            ];
        }
    }

    $allCodes = array_values($codeMap);

    // ترتيب من الأحدث للأقدم
    usort($allCodes, function ($a, $b) {
        $ta = strtotime($a['obtained_at'] ?? '0');
        $tb = strtotime($b['obtained_at'] ?? '0');
        return $tb - $ta;
    });

    sendJsonResponse(true, ['codes' => $allCodes]);

} catch (Exception $e) {
    error_log("Get My Course Codes Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب أكواد الكورسات', 500);
}
