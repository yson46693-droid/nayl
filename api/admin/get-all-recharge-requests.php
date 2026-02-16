<?php
/**
 * ============================================
 * Get All Recharge Requests API (Admin)
 * ============================================
 * API لجلب جميع طلبات تعبئة الرصيد للمشرفين
 * 
 * Endpoint: GET /api/admin/get-all-recharge-requests.php
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
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من صلاحيات الأدمن بناءً على admin_sessions
$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    // استلام معاملات الفلتر والصفحات
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
    
    // تصحيح القيم
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;
    
    $offset = ($page - 1) * $limit;
    
    // بناء الاستعلام الأساسي
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($status && $status !== 'all') {
        $whereClause .= " AND r.status = :status";
        $params['status'] = $status;
    }
    
    // الحصول على العدد الكلي للسجلات
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM recharge_requests r $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // جلب البيانات مع بيانات المستخدم
    $query = "
        SELECT 
            r.id,
            r.amount,
            r.payment_method,
            r.account_number,
            r.transaction_image,
            r.transaction_message,
            r.status,
            r.admin_notes,
            r.created_at,
            u.full_name as user_name,
            u.phone as user_phone
        FROM recharge_requests r
        JOIN users u ON r.user_id = u.id
        $whereClause
        ORDER BY r.created_at DESC
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
    
    // تنسيق البيانات
    $formattedRequests = [];
    foreach ($requests as $request) {
        $formattedRequests[] = [
            'id' => $request['id'],
            'amount' => (float)$request['amount'],
            'payment_method' => $request['payment_method'],
            'account_number' => $request['account_number'],
            'transaction_image' => $request['transaction_image'],
            'message' => $request['transaction_message'],
            'status' => $request['status'],
            'user_name' => $request['user_name'],
            'user_phone' => $request['user_phone'],
            'created_at' => $request['created_at']
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
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Get All Recharge Requests Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب الطلبات', 500);
}
