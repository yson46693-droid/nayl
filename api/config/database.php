<?php
/**
 * ============================================
 * Database Configuration
 * ============================================
 * إعدادات الاتصال بقاعدة البيانات
 */

// تحميل متغيرات البيئة
require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

// إعدادات قاعدة البيانات من ملف .env مع قيم افتراضية
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'nayl'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_PORT', env('DB_PORT', '3306'));

/**
 * إنشاء اتصال بقاعدة البيانات
 * @return PDO|null
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // بناء DSN مع Port إذا كان محدداً
            $dsn = "mysql:host=" . DB_HOST;
            if (defined('DB_PORT') && DB_PORT && DB_PORT !== '3306') {
                $dsn .= ";port=" . DB_PORT;
            }
            $dsn .= ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5 // timeout 5 ثواني
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
        } catch (PDOException $e) {
            // تسجيل تفاصيل الخطأ بشكل أفضل
            $errorDetails = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'host' => DB_HOST,
                'database' => DB_NAME,
                'user' => DB_USER,
                'port' => defined('DB_PORT') ? DB_PORT : '3306'
            ];
            error_log("Database Connection Error: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
            return null;
        }
    }
    
    return $pdo;
}
