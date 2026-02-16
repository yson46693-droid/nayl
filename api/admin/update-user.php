<?php
/**
 * ============================================
 * Update User API (Admin)
 * ============================================
 * API لتعديل بيانات مستخدم (الاسم، الهاتف، البريد، الدولة، المدينة، الحالة، رصيد المحفظة)
 *
 * Endpoint: POST /api/admin/update-user.php
 * Body: { user_id, full_name?, phone?, email?, country?, city?, is_active?, wallet_balance? }
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['user_id'])) {
    sendJsonResponse(false, null, 'معرف المستخدم مطلوب', 400);
}

$userId = (int)$data['user_id'];
if ($userId < 1) {
    sendJsonResponse(false, null, 'معرف المستخدم غير صالح', 400);
}

$fullName = isset($data['full_name']) ? sanitizeInput($data['full_name']) : null;
$phone = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : null;
$email = isset($data['email']) ? trim($data['email']) : null;
$country = isset($data['country']) ? sanitizeInput($data['country']) : null;
$city = isset($data['city']) ? sanitizeInput($data['city']) : null;
$isActive = isset($data['is_active']) ? (bool)$data['is_active'] : null;
$walletBalance = isset($data['wallet_balance']) ? (float)$data['wallet_balance'] : null;

if ($email !== null && !validateEmail($email)) {
    sendJsonResponse(false, null, 'البريد الإلكتروني غير صالح', 400);
}
if ($phone !== null && !validatePhone($phone)) {
    sendJsonResponse(false, null, 'رقم الهاتف غير صالح', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $check = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND deleted_at IS NULL");
    $check->execute([$userId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        sendJsonResponse(false, null, 'المستخدم غير موجود', 404);
    }

    if ($email !== null && $email !== $existing['email']) {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) {
            sendJsonResponse(false, null, 'البريد الإلكتروني مستخدم من حساب آخر', 400);
        }
    }

    $updates = [];
    $params = [];
    if ($fullName !== null && $fullName !== '') { $updates[] = 'full_name = ?'; $params[] = $fullName; }
    if ($phone !== null && $phone !== '') { $updates[] = 'phone = ?'; $params[] = $phone; }
    if ($email !== null && $email !== '') { $updates[] = 'email = ?'; $params[] = $email; }
    if ($country !== null && $country !== '') { $updates[] = 'country = ?'; $params[] = $country; }
    if ($city !== null && $city !== '') { $updates[] = 'city = ?'; $params[] = $city; }
    if ($isActive !== null) { $updates[] = 'is_active = ?'; $params[] = $isActive ? 1 : 0; }

    if (!empty($updates)) {
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    if ($walletBalance !== null && $walletBalance >= 0) {
        $walletStmt = $pdo->prepare("UPDATE wallet SET balance = ? WHERE user_id = ?");
        $walletStmt->execute([$walletBalance, $userId]);
    }

    sendJsonResponse(true, null, 'تم تحديث بيانات المستخدم بنجاح');

} catch (PDOException $e) {
    error_log('Admin Update User Error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تحديث البيانات', 500);
}
