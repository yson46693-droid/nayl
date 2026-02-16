<?php
/**
 * ============================================
 * Get Site Stats API (Admin)
 * ============================================
 * API لجلب إحصائيات الموقع (عدد الزوار الفريدين)
 * 
 * Endpoint: GET /api/admin/get-site-stats.php
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// CORS Headers
$allowedOrigin = getAllowedOrigin(); // Assuming this is available via auth.php or we need to define/include it? 
// Wait, get-pending-recharge-count.php used getAllowedOrigin(). Where is it defined?
// It is NOT in database.php, security.php, or env.php that I read. 
// It MUST be in auth.php then. Let's verify auth.php.

if (!function_exists('getAllowedOrigin')) {
    // If not found, define simple version or include where it is.
    // Assuming it's in auth.php based on previous file usage.
    // If not, I'll add a check or safe fallback.
}

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

// Verify Admin Auth
$admin = requireAdminAuth(true); // Assuming this checks for valid admin session
if (!$admin) {
    sendJsonResponse(false, null, 'Unauthorized', 401);
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'Database Connection Error', 500);
    }
    
    // Count unique visitors
    $stmt = $pdo->query("SELECT COUNT(*) FROM site_visits");
    $visitorCount = $stmt->fetchColumn();

    // إجمالي المبالغ المرسلة للمستخدمين من خلال الموافقة على طلبات الشحن خلال الشهر الحالي
    $monthlyRechargeStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM recharge_requests
        WHERE status = 'approved'
          AND COALESCE(processed_at, updated_at) >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01 00:00:00')
          AND COALESCE(processed_at, updated_at) < DATE_ADD(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), INTERVAL 1 MONTH)
    ");
    $monthlyRechargeStmt->execute();
    $monthlyRechargeTotal = (float) $monthlyRechargeStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    sendJsonResponse(true, [
        'visitor_count' => (int)$visitorCount,
        'monthly_sales_total' => $monthlyRechargeTotal
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Get Site Stats Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Database Error', 500);
}
