<?php
/**
 * ============================================
 * Create User API (Admin)
 * ============================================
 * API لإنشاء مستخدم جديد من لوحة الأدمن
 *
 * Endpoint: POST /api/admin/create-user.php
 * Body: { full_name, email, phone, password, country_code?, country?, city?, is_active? }
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    sendJsonResponse(false, null, 'بيانات غير صالحة', 400);
}

$fullName = isset($data['full_name']) ? trim(sanitizeInput($data['full_name'])) : '';
$email = isset($data['email']) ? trim(sanitizeInput($data['email'])) : '';
$phone = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$countryCode = isset($data['country_code']) ? trim(sanitizeInput($data['country_code'])) : '+20';
$country = isset($data['country']) ? trim(sanitizeInput($data['country'])) : 'مصر';
$city = isset($data['city']) ? trim(sanitizeInput($data['city'])) : 'غير محدد';
$isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

if ($fullName === '') {
    sendJsonResponse(false, null, 'الاسم الكامل مطلوب', 400);
}
if ($email === '') {
    sendJsonResponse(false, null, 'البريد الإلكتروني مطلوب', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, null, 'البريد الإلكتروني غير صالح', 400);
}
if (strlen($phone) < 7) {
    sendJsonResponse(false, null, 'رقم الهاتف غير صالح (7 أرقام على الأقل)', 400);
}
if (!validateCountryCode($countryCode)) {
    $countryCode = '+20';
}
if (strlen($password) < 6) {
    sendJsonResponse(false, null, 'كلمة المرور يجب أن تكون 6 أحرف على الأقل', 400);
}

$fullPhone = $countryCode . $phone;

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, null, 'البريد الإلكتروني مستخدم بالفعل', 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE full_phone = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$fullPhone]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, null, 'رقم الهاتف مستخدم بالفعل', 400);
    }

    $passwordHash = hashPassword($password);
    if (!$passwordHash) {
        sendJsonResponse(false, null, 'فشل تشفير كلمة المرور', 500);
    }

    $userId = 0;
    $isUnique = false;
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
        try {
            $userId = random_int(100000, 999999);
        } catch (Exception $e) {
            $userId = mt_rand(100000, 999999);
        }
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmtCheck->execute([$userId]);
        if (!$stmtCheck->fetch()) {
            $isUnique = true;
            break;
        }
    }
    if (!$isUnique) {
        sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الحساب، يرجى المحاولة مرة أخرى', 500);
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (
            id, full_name, email, phone, country_code, full_phone, country, city,
            password_hash, is_active, whatsapp_verified, terms_accepted, terms_accepted_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, 0, 1, NOW()
        )
    ");
    $stmt->execute([
        $userId,
        $fullName ?: null,
        $email,
        $phone,
        $countryCode,
        $fullPhone,
        $country,
        $city,
        $passwordHash,
        $isActive ? 1 : 0
    ]);

    sendJsonResponse(true, [
        'id' => $userId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'full_phone' => $fullPhone
    ], null, 201);

} catch (PDOException $e) {
    error_log('create-user: ' . $e->getMessage());
    if ($e->getCode() == 23000) {
        sendJsonResponse(false, null, 'البريد الإلكتروني أو رقم الهاتف مستخدم بالفعل', 400);
    }
    sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء المستخدم', 500);
}
