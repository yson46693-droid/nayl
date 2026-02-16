<?php
/**
 * API للحصول على الحسابات المرتبطة بالبصمة على الجهاز
 */

require_once 'config.php';
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من دعم WebAuthn
if (!function_exists('getDBConnection')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في تحميل النظام'], JSON_UNESCAPED_UNICODE);
    exit;
}

// إنشاء جدول webauthn_credentials إذا لم يكن موجوداً
if (!dbTableExists('webauthn_credentials')) {
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `webauthn_credentials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` varchar(50) NOT NULL,
        `credential_id` text NOT NULL,
        `public_key` text NOT NULL,
        `device_name` varchar(255) DEFAULT NULL,
        `counter` int(11) DEFAULT 0,
        `created_at` datetime NOT NULL,
        `last_used` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_credential_id` (`credential_id`(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn = getDBConnection();
    if ($conn) {
        $conn->multi_query($createTableSQL);
        // استهلاك جميع النتائج
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }
}

// إنشاء جدول users إذا لم يكن موجوداً (للتأكد من وجوده)
if (!dbTableExists('users')) {
    $createUsersTableSQL = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` varchar(50) NOT NULL,
        `username` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `role` enum('admin','manager','employee','technician') NOT NULL,
        `webauthn_enabled` tinyint(1) DEFAULT 0,
        `created_at` datetime NOT NULL,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn = getDBConnection();
    if ($conn) {
        $conn->multi_query($createUsersTableSQL);
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }
}

// إضافة عمود webauthn_enabled إذا لم يكن موجوداً
$conn = getDBConnection();
if ($conn && dbTableExists('users')) {
    $checkColumn = $conn->query("SHOW COLUMNS FROM `users` LIKE 'webauthn_enabled'");
    if ($checkColumn && $checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `webauthn_enabled` tinyint(1) DEFAULT 0");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // الحصول على جميع الحسابات التي لديها بصمات مسجلة
        $query = "
            SELECT DISTINCT 
                u.id,
                u.username,
                u.name,
                u.role,
                COUNT(wc.id) as credentials_count
            FROM users u
            INNER JOIN webauthn_credentials wc ON u.id = wc.user_id
            WHERE u.webauthn_enabled = 1 OR wc.id IS NOT NULL
            GROUP BY u.id, u.username, u.name, u.role
            ORDER BY u.name
        ";
        
        $accounts = dbSelect($query);
        
        if ($accounts === false) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'خطأ في قراءة قاعدة البيانات'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // إضافة معلومات البصمات لكل حساب
        foreach ($accounts as &$account) {
            $credentials = dbSelect(
                "SELECT id, device_name, created_at, last_used 
                 FROM webauthn_credentials 
                 WHERE user_id = ? 
                 ORDER BY last_used DESC, created_at DESC",
                [$account['id']]
            );
            
            $account['credentials'] = $credentials ? $credentials : [];
        }
        
        echo json_encode([
            'success' => true,
            'accounts' => $accounts
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("WebAuthn Accounts API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'حدث خطأ: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'طريقة غير مدعومة'
    ], JSON_UNESCAPED_UNICODE);
}
?>
