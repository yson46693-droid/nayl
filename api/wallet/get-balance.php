<?php
/**
 * ============================================
 * Get Wallet Balance API
 * ============================================
 * API للحصول على رصيد محفظة المستخدم
 * 
 * Endpoint: GET /api/wallet/get-balance.php
 */

// بدء الجلسة
session_start();

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// إعدادات CORS
$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من تسجيل الدخول
$user = requireAuth(true);

if (!$user) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة',
        'code' => 'UNAUTHORIZED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الاتصال بقاعدة البيانات',
        'code' => 'DATABASE_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // الحصول على رصيد المحفظة
    $stmt = $pdo->prepare("
        SELECT balance 
        FROM wallet 
        WHERE user_id = :user_id
        LIMIT 1
    ");
    
    $stmt->execute(['user_id' => $user['user_id']]);
    $wallet = $stmt->fetch();
    
    // إذا لم توجد محفظة، إنشاؤها برصيد 0
    if (!$wallet) {
        $stmt = $pdo->prepare("
            INSERT INTO wallet (user_id, balance) 
            VALUES (:user_id, 0.00)
        ");
        $stmt->execute(['user_id' => $user['user_id']]);
        $balance = 0.00;
    } else {
        $balance = (float)$wallet['balance'];
    }
    
    // إرجاع النتيجة
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'balance' => number_format($balance, 2, '.', ''),
            'currency' => 'ج.م'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Wallet Balance Error: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ أثناء جلب رصيد المحفظة',
        'code' => 'SERVER_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
