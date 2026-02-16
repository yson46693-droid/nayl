<?php
/**
 * Get Admin Notifications API
 */

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// Verify Admin headers
// Verify Admin headers
$admin = requireAdminAuth();

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    // Get unread notifications primarily, but maybe limit to last 20
    $stmt = $pdo->prepare("
        SELECT * FROM admin_notifications 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count unread
    $countStmt = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0");
    $unreadCount = $countStmt->fetchColumn();
    
    echo json_encode(['success' => true, 'data' => $notifications, 'unread_count' => $unreadCount]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
