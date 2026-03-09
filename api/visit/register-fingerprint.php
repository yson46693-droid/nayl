<?php
/**
 * Register Device Fingerprint API
 * Endpoint: POST /api/visit/register-fingerprint.php
 * يسجّل بصمة الجهاز (هاش SHA-256) في قاعدة البيانات مرة واحدة فقط.
 * عند تكرار الطلب بنفس الهاش يُحدَّث last_seen_at فقط.
 */

session_start();

require_once __DIR__ . '/../config/cors.php';

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['fingerprint_hash'])) {
    sendJsonResponse(false, null, 'fingerprint_hash required', 400);
    exit;
}

$hash = trim($data['fingerprint_hash']);

// التحقق: هاش SHA-256 = 64 حرف hex فقط
if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
    sendJsonResponse(false, null, 'Invalid fingerprint_hash format', 400);
    exit;
}

$hash = substr($hash, 0, 64);

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'Database connection failed', 500);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO device_fingerprints (fingerprint_hash, first_seen_at, last_seen_at)
        VALUES (:hash, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ");
    $stmt->execute(['hash' => $hash]);
    sendJsonResponse(true, ['message' => 'Fingerprint registered']);
} catch (PDOException $e) {
    error_log("Register fingerprint error: " . $e->getMessage());
    sendJsonResponse(false, null, 'Registration failed', 500);
}
