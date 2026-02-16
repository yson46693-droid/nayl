<?php
/**
 * ============================================
 * Get All Users API (Admin)
 * ============================================
 * API لجلب جميع المستخدمين للمشرفين
 * 
 * Endpoint: GET /api/admin/get-all-users.php
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
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    
    // تصحيح القيم
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;
    if ($limit > 100) $limit = 100;
    
    $offset = ($page - 1) * $limit;
    
    // بناء الاستعلام الأساسي
    $whereClause = "WHERE u.deleted_at IS NULL";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (u.full_name LIKE :search OR u.phone LIKE :search OR u.email LIKE :search)";
        $params['search'] = "%$search%";
    }
    
    // الحصول على العدد الكلي للسجلات
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // جلب البيانات مع الرصيد
    // نفترض أن عدد الكورسات هو عدد المعاملات من نوع 'purchase_course' أو 'course'
    $query = "
        SELECT 
            u.id,
            u.full_name,
            u.phone,
            u.email,
            u.created_at,
            u.is_active,
            COALESCE(w.balance, 0.00) as wallet_balance,
            (SELECT COUNT(*) FROM wallet_transactions wt 
             WHERE wt.user_id = u.id AND wt.reference_type = 'course') as courses_count
        FROM users u
        LEFT JOIN wallet w ON u.id = w.user_id
        $whereClause
        ORDER BY u.created_at DESC
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
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => $user['id'],
            'name' => $user['full_name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'joined' => date('d/m/Y', strtotime($user['created_at'])),
            'status' => $user['is_active'] ? 'نشط' : 'معلق',
            'wallet' => (float)$user['wallet_balance'],
            // بما أننا لا نملك جدول الكورسات، سنعرض العدد فقط أو نص generic
            // في النسخة الحالية سنرسل مصفوفة فارغة أو أسماء وهمية إذا لزم الأمر، لكن الأفضل إرسال العدد
            // Frontend expecting array of strings for courses. 
            // We can send ["$count courses"]
            'courses' => $user['courses_count'] > 0 ? [$user['courses_count'] . ' كورس'] : []
        ];
    }
    
    sendJsonResponse(true, [
        'users' => $formattedUsers,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Admin Get All Users Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب المستخدمين', 500);
}
