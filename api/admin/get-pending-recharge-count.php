<?php
/**
 * ============================================
 * Get Pending Recharge Requests Count API (Admin)
 * ============================================
 * Endpoint: GET /api/admin/get-pending-recharge-count.php
 */

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// CORS Headers
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

// Verify Admin Auth
$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'Unauthorized', 401);
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'Database Connection Error', 500);
    }
    
    // Count pending requests
    // Using TRIM and LOWER to be safe against data inconsistencies
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recharge_requests WHERE LOWER(TRIM(status)) = 'pending'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    // Debug: Get all status counts
    $debugStmt = $pdo->prepare("SELECT status, COUNT(*) as c FROM recharge_requests GROUP BY status");
    $debugStmt->execute();
    $debugCounts = $debugStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    sendJsonResponse(true, [
        'count' => (int)$count,
        'debug_breakdown' => $debugCounts
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Get Pending Recharge Count Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Database Error', 500);
}
?>
