<?php
/**
 * تحديث حساب أدمن (مدير أعلى أو أدمن فقط)
 * Endpoint: POST /api/admin/update-admin.php
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

$currentRole = $admin['role'] ?? '';
$currentAdminId = (int) ($admin['admin_id'] ?? $admin['id'] ?? 0);
if ($currentRole !== 'super_admin' && $currentRole !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'غير مصرح لك بتعديل حسابات الأدمن'], JSON_UNESCAPED_UNICODE);
    http_response_code(403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$targetId = isset($input['admin_id']) ? (int) $input['admin_id'] : 0;
if ($targetId < 1) {
    echo json_encode(['success' => false, 'error' => 'معرف الأدمن غير صالح'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

$fullName = isset($input['full_name']) ? trim(sanitizeInput($input['full_name'])) : null;
$email = isset($input['email']) ? trim(sanitizeInput($input['email'])) : null;
$newRole = isset($input['role']) ? trim(sanitizeInput($input['role'])) : null;
$isActive = isset($input['is_active']) ? (bool) $input['is_active'] : null;
$password = isset($input['password']) ? $input['password'] : '';

if ($fullName !== null && $fullName === '') {
    echo json_encode(['success' => false, 'error' => 'الاسم الكامل مطلوب'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني غير صحيح'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

$allowedRoles = ['admin', 'super_admin', 'moderator'];
if ($newRole !== null && !in_array($newRole, $allowedRoles)) {
    echo json_encode(['success' => false, 'error' => 'الصلاحية غير صالحة'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

if ($password !== '' && strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'], JSON_UNESCAPED_UNICODE);
    http_response_code(400);
    exit;
}

// لا يجوز للأدمن تغيير صلاحيات نفسه أو إيقاف نفسه
if ($targetId === $currentAdminId) {
    if ($newRole !== null && $newRole !== $currentRole) {
        echo json_encode(['success' => false, 'error' => 'لا يمكنك تغيير صلاحياتك بنفسك'], JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
    if ($isActive === false) {
        echo json_encode(['success' => false, 'error' => 'لا يمكنك إيقاف حسابك بنفسك'], JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
}

// فقط مدير أعلى يمكنه تعيين صلاحية مدير أعلى أو تعديل أدمن آخر بصلاحية أعلى
if ($currentRole !== 'super_admin' && $newRole === 'super_admin') {
    echo json_encode(['success' => false, 'error' => 'فقط مدير أعلى يمكنه تعيين صلاحية مدير أعلى'], JSON_UNESCAPED_UNICODE);
    http_response_code(403);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, is_active FROM admins WHERE id = ?');
    $stmt->execute([$targetId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'الحساب غير موجود'], JSON_UNESCAPED_UNICODE);
        http_response_code(404);
        exit;
    }

    $updates = [];
    $params = [];

    if ($fullName !== null) {
        $updates[] = 'full_name = ?';
        $params[] = $fullName;
    }
    if ($email !== null) {
        $updates[] = 'email = ?';
        $params[] = $email === '' ? null : $email;
    }
    if ($newRole !== null) {
        $updates[] = 'role = ?';
        $params[] = $newRole;
    }
    if ($isActive !== null) {
        $updates[] = 'is_active = ?';
        $params[] = $isActive ? 1 : 0;
    }
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$hash) {
            echo json_encode(['success' => false, 'error' => 'فشل تشفير كلمة المرور'], JSON_UNESCAPED_UNICODE);
            http_response_code(500);
            exit;
        }
        $updates[] = 'password_hash = ?';
        $params[] = $hash;
    }

    if (count($updates) === 0) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updates[] = 'updated_at = NOW()';
    $params[] = $targetId;
    $sql = 'UPDATE admins SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'email') !== false) {
            echo json_encode(['success' => false, 'error' => 'البريد الإلكتروني مستخدم من حساب آخر'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => 'البيانات مكررة'], JSON_UNESCAPED_UNICODE);
        }
        http_response_code(400);
        exit;
    }
    error_log('update-admin: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'فشل التحديث'], JSON_UNESCAPED_UNICODE);
    http_response_code(500);
}
