<?php
/**
 * Delete Admin Notification API
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// Verify Admin headers
session_start();
$admin = requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? $input['id'] : null;
$type = isset($input['type']) ? $input['type'] : 'single'; // single or all

try {
    $pdo = getDatabaseConnection();
    
    if ($type === 'all') {
        $stmt = $pdo->prepare("DELETE FROM admin_notifications");
        $stmt->execute();
    } else if ($id) {
        $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        throw new Exception('Invalid parameters');
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
