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

    $stmt = $pdo->query("
        SELECT id, v_id, visit_count, last_visit_at, user_agent
        FROM site_visits
        ORDER BY last_visit_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $devices = [];
    foreach ($rows as $row) {
        $ua = $row['user_agent'] ?? '';
        $deviceType = getDeviceTypeFromUserAgent($ua);

        $devices[] = [
            'id' => (int) $row['id'],
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
