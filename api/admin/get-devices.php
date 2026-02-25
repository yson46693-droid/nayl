<?php
/**
 * ============================================
 * Get Devices API (Admin)
 * ============================================
 * قائمة الأجهزة حسب معرف الجهاز (v_id) المُنشأ عند أول زيارة.
 * نوع الجهاز يُستنتج من User-Agent: iPhone, MacBook, Android, Windows.
 *
 * Endpoint: GET /api/admin/get-devices.php
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'Unauthorized', 401);
}

/**
 * استنتاج نوع الجهاز من User-Agent
 * أولوية: iPhone → MacBook → Android → Windows
 * عند الفشل يُعاد "جهاز آخر"
 */
function getDeviceTypeFromUserAgent($userAgent) {
    if (empty($userAgent) || !is_string($userAgent)) {
        return 'جهاز آخر';
    }
    $ua = $userAgent;

    // iPhone (يشمل iPod كأجهزة Apple محمولة)
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPod') !== false) {
        return 'iPhone';
    }

    // MacBook / Mac (Macintosh أو Mac OS بدون iPhone)
    if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
        return 'MacBook';
    }

    // Android
    if (stripos($ua, 'Android') !== false) {
        return 'Android';
    }

    // Windows
    if (stripos($ua, 'Windows') !== false || stripos($ua, 'Win32') !== false || stripos($ua, 'Win64') !== false) {
        return 'Windows';
    }

    return 'جهاز آخر';
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'Database Connection Error', 500);
    }

    // فلترة حسب الأيام (اختياري): ?days=90 = الأجهزة النشطة خلال آخر 90 يوم فقط
    $days = isset($_GET['days']) ? (int) $_GET['days'] : 0;
    $whereClause = '';
    $params = [];
    if ($days > 0 && $days <= 365) {
        $whereClause = ' WHERE t.last_visit_at >= (NOW() - INTERVAL :days DAY) ';
        $params['days'] = $days;
    }

    // صف واحد لكل جهاز (v_id): مجموع الزيارات، آخر نشاط، و user_agent من آخر زيارة
    $sql = "
        SELECT
            t.v_id,
            SUM(t.visit_count) AS visit_count,
            MAX(t.last_visit_at) AS last_visit_at,
            (SELECT u.user_agent FROM site_visits u WHERE u.v_id = t.v_id ORDER BY u.last_visit_at DESC LIMIT 1) AS user_agent
        FROM site_visits t
        $whereClause
        GROUP BY t.v_id
        ORDER BY last_visit_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $devices = [];
    foreach ($rows as $row) {
        $ua = $row['user_agent'] ?? '';
        $deviceType = getDeviceTypeFromUserAgent($ua);

        $devices[] = [
            'v_id' => $row['v_id'],
            'device_type' => $deviceType,
            'visit_count' => (int) ($row['visit_count'] ?? 0),
            'last_visit_at' => $row['last_visit_at'],
        ];
    }

    sendJsonResponse(true, ['devices' => $devices]);

} catch (PDOException $e) {
    error_log('Admin Get Devices Error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'Database Error', 500);
}
