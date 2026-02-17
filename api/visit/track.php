<?php
/**
 * Track Visitor API
 * Endpoint: POST /api/visit/track.php
 */

session_start();

require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

// === CORS START ===
function getAllowedOrigin() {
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1'
    ];
    
    if (function_exists('env')) {
        $appUrl = env('APP_URL', '');
        if ($appUrl) {
            $allowedOrigins[] = rtrim($appUrl, '/');
            $parsed = parse_url($appUrl);
            if ($parsed && isset($parsed['host'])) {
                $allowedOrigins[] = ($parsed['scheme'] ?? 'http') . '://' . $parsed['host'];
            }
        }
    }
    
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    if ($requestOrigin) {
        $parsedOrigin = parse_url($requestOrigin);
        if ($parsedOrigin && isset($parsedOrigin['host'])) {
            $originDomain = ($parsedOrigin['scheme'] ?? 'http') . '://' . $parsedOrigin['host'];
            foreach ($allowedOrigins as $allowed) {
                if ($originDomain === $allowed) {
                    return $requestOrigin;
                }
            }
        }
    }
    
    $serverProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $serverOrigin = $serverProtocol . '://' . $serverHost;
    
    if (!$requestOrigin) {
        return $serverOrigin;
    }
    
    return null;
}

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // For tracking, we might be lenient, but let's stick to security pattern
    // If strict processing is needed:
    // http_response_code(403); exit;
    // But often tracking pixels allow *
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// === CORS END ===

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// Get JSON Input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendJsonResponse(false, null, 'Invalid JSON', 400);
}

$v_id = $data['v_id'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Validate v_id - يقبل UUID (36 حرف) أو الصيغة القديمة (20 حرف)
if (empty($v_id)) {
    sendJsonResponse(false, null, 'v_id is required', 400);
}

// UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx أو الصيغة القديمة 20 حرف
if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $v_id) && !preg_match('/^[a-zA-Z0-9]{20}$/', $v_id)) {
    sendJsonResponse(false, null, 'Invalid v_id format', 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'Database connection failed', 500);
}

try {
    // Insert or Update to track visit
    $stmt = $pdo->prepare("
        INSERT INTO site_visits (v_id, user_agent, visit_count, last_visit_at)
        VALUES (:v_id, :user_agent, 1, NOW())
        ON DUPLICATE KEY UPDATE 
            visit_count = visit_count + 1,
            last_visit_at = NOW(),
            user_agent = VALUES(user_agent)
    ");
    
    $stmt->execute([
        'v_id' => $v_id,
        'user_agent' => $user_agent
    ]);
    
    sendJsonResponse(true, ['message' => 'Visit tracked']);

} catch (PDOException $e) {
    error_log("Tracking Error: " . $e->getMessage());
    $message = 'Tracking failed';
    if (function_exists('env') && (env('APP_DEBUG', false) === true || env('APP_DEBUG') === 'true')) {
        $message = $e->getMessage();
    }
    sendJsonResponse(false, null, $message, 500);
}
