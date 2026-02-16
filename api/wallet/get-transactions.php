<?php
/**
 * ============================================
 * Get Wallet Transactions API
 * ============================================
 * API للحصول على معاملات محفظة المستخدم
 * 
 * Endpoint: GET /api/wallet/get-transactions.php
 * Query Parameters:
 *   - page: رقم الصفحة (افتراضي: 1)
 *   - limit: عدد النتائج في الصفحة (افتراضي: 10)
 *   - type: نوع المعاملة (all, credit, debit) (افتراضي: all)
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

// الحصول على معاملات الطلب
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'all';

// التحقق من نوع المعاملة
if (!in_array($type, ['all', 'credit', 'debit'])) {
    $type = 'all';
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
    // بناء استعلام WHERE
    $whereClause = "WHERE user_id = :user_id";
    $params = ['user_id' => $user['user_id']];
    
    if ($type !== 'all') {
        $whereClause .= " AND type = :type";
        $params['type'] = $type;
    }
    
    // الحصول على العدد الإجمالي
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM wallet_transactions 
        $whereClause
    ");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetch()['total'];
    $totalPages = ceil($totalCount / $limit);
    
    // الحصول على المعاملات
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare("
        SELECT 
            id,
            type,
            amount,
            title,
            description,
            reference_id,
            reference_type,
            balance_before,
            balance_after,
            created_at
        FROM wallet_transactions 
        $whereClause
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    
    $stmt->execute();
    $transactions = $stmt->fetchAll();
    
    // تنسيق البيانات
    $formattedTransactions = array_map(function($transaction) {
        return [
            'id' => (int)$transaction['id'],
            'type' => $transaction['type'],
            'amount' => number_format((float)$transaction['amount'], 2, '.', ''),
            'title' => $transaction['title'],
            'description' => $transaction['description'],
            'reference_id' => $transaction['reference_id'],
            'reference_type' => $transaction['reference_type'],
            'balance_before' => number_format((float)$transaction['balance_before'], 2, '.', ''),
            'balance_after' => number_format((float)$transaction['balance_after'], 2, '.', ''),
            'date' => date('d M Y - h:i A', strtotime($transaction['created_at']))
        ];
    }, $transactions);
    
    // إرجاع النتيجة
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $formattedTransactions,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalCount,
                'items_per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Wallet Transactions Error: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ أثناء جلب معاملات المحفظة',
        'code' => 'SERVER_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
