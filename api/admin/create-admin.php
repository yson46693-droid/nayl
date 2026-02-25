<?php
/**
 * إنشاء حساب أدمن جديد (مدير أعلى أو أدمن فقط)
 * Endpoint: POST /api/admin/create-admin.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

loadEnv(__DIR__ . '/../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    http_response_code(405);
    exit;
}

$admin = verifyAdminSession();
if (!$admin) {
    echo json_encode(['success' => false, 'error' => 'الجلسة غير صالحة'], JSON_UNESCAPED_UNICODE);
    http_response_code(401);
    exit;
}

$role = $admin['role'] ?? '';
if ($role !== 'super_admin' && $role !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بإنشاء حسابات أدمن'], JSON_UNESCAPED_UNICODE);
    http_response_code(403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$fullName = isset($input['full_name']) ? trim(sanitizeInput($input['full_name'])) : '';
$username = isset($input['username']) ? trim(sanitizeInput($input['username'])) : '';
$email = isset($input['email']) ? trim(sanitizeInput($input['email'])) : null;
$password = isset($input['password']) ? $input['password'] : '';
$newRole = isset($input['role']) ? trim(sanitizeInput($input['role'])) : 'admin';

if ($fullName === '' || $username === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'الاسم الكامل واسم المستخدم وكلمة المرور مطلوبة'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

$allowedRoles = ['admin', 'super_admin', 'moderator'];
if (!in_array($newRole, $allowedRoles)) {
    $newRole = 'admin';
}

if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني غير صحيح'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if (!$passwordHash) {
    echo json_encode(['success' => false, 'error' => 'فشل تشفير كلمة المرور'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
    exit;
}

try {
    $stmt = $pdo->prepare('
        INSERT INTO admins (username, full_name, email, password_hash, role)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $username,
        $fullName,
        $email ?: null,
        $passwordHash,
        $newRole
    ]);
    echo json_encode(['success' => true, 'data' => ['id' => (int) $pdo->lastInsertId()]], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'username') !== false) {
            echo json_encode(['success' => false, 'error' => 'اسم المستخدم مستخدم مسبقاً'], JSON_UNESCAPED_UNICODE);
        } elseif (strpos($e->getMessage(), 'email') !== false) {
            echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني مستخدم مسبقاً'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => 'البيانات مكررة'], JSON_UNESCAPED_UNICODE);
        }
        http_response_code(400);
        exit;
    }
    error_log('create-admin: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'فشل إنشاء الحساب'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
}
