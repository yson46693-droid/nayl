<?php
/**
 * ============================================
 * Get All Complaints API (Admin)
 * ============================================
 * API لجلب جميع الشكاوي للمشرفين
 * 
 * Endpoint: GET /api/admin/get-all-complaints.php
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

// التحقق من صلاحيات الأدمن
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
        $whereClause .= " AND c.status = :status";
        $params['status'] = $status;
    }
    
    // الحصول على العدد الكلي للسجلات
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // جلب البيانات مع بيانات المستخدم
    $query = "
        SELECT 
            c.id,
            c.subject,
            c.message,
            c.status,
            c.admin_reply,
            c.created_at,
            c.updated_at,
            u.full_name as user_name,
            u.phone as user_phone
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        $whereClause
        ORDER BY c.created_at DESC
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
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, [
        'complaints' => $complaints,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Get All Complaints Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب الشكاوي', 500);
}
