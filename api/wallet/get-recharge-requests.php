<?php
/**
 * ============================================
 * Get Recharge Requests API Endpoint
 * ============================================
 * API لجلب طلبات تعبئة الرصيد للمستخدم الحالي
 * 
 * Endpoint: GET /api/wallet/get-recharge-requests.php
 */

// بدء الجلسة
session_start();

// تحميل env.php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

// إعدادات CORS (مطابقة لـ get-my-course-codes لتفادي خطأ الاتصال)
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = getAllowedOrigin();
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

$origin = getAllowedOrigin();
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
// منع كاش المتصفح لضمان ظهور الطلبات
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// السماح فقط بـ GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استيراد الملفات المطلوبة
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

// التحقق من تسجيل الدخول
$user = requireAuth(true);
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول للوصول إلى هذه الصفحة', 401);
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    // استلام معاملات الفلتر والصفحات
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
    
    // تصحيح القيم
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 5;
    if ($limit > 50) $limit = 50;
    
    $offset = ($page - 1) * $limit;
    
    // بناء الاستعلام الأساسي
    $whereClause = "WHERE user_id = :user_id";
    $params = ['user_id' => $user['id']];
    
    if ($status && $status !== 'all') {
        $whereClause .= " AND status = :status";
        $params['status'] = $status;
    }
    
    // الحصول على العدد الكلي للسجلات (للـ pagination)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM recharge_requests $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // جلب البيانات
    $query = "
        SELECT 
            id,
            amount,
            payment_method,
            account_number,
            transaction_image,
            transaction_message,
            status,
            admin_notes,
            created_at,
            updated_at,
            processed_at
        FROM recharge_requests
        $whereClause
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    
    // ربط المعاملات
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $requests = $stmt->fetchAll();
    
    // تحويل البيانات إلى تنسيق مناسب
    $formattedRequests = [];
    foreach ($requests as $request) {
        $formattedRequests[] = [
            'id' => (int)$request['id'],
            'amount' => (float)$request['amount'],
            'payment_method' => $request['payment_method'],
            'account_number' => $request['account_number'] ?? '',
            'transaction_image' => $request['transaction_image'] ?? '',
            'transaction_message' => $request['transaction_message'] ?? '',
            'status' => $request['status'],
            'admin_notes' => $request['admin_notes'] ?? '',
            'created_at' => $request['created_at'],
            'updated_at' => $request['updated_at'],
            'processed_at' => $request['processed_at'] ?? null
        ];
    }
    
    sendJsonResponse(true, [
        'requests' => $formattedRequests,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ], null, 200);
    
} catch (PDOException $e) {
    error_log("Get Recharge Requests Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب طلبات التعبئة', 500);
}
