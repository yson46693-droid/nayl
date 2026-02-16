<?php
/**
 * WebAuthn: الحصول على خيارات التسجيل (للمستخدم المسجل دخوله)
 * POST - يتطلب Authorization / session_token
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
}

$user = verifySession();
if (!$user || empty($user['id']) || empty($user['email'])) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول أولاً', 401);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

$userId = (int) $user['id'];
$email = $user['email'];
$displayName = $user['full_name'] ?? $email;

// تحقق من وجود الجدول
try {
    $pdo->query("SELECT 1 FROM webauthn_credentials LIMIT 1");
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'ميزة البصمة غير مفعّلة. يرجى تشغيل ترحيل قاعدة البيانات.', 503);
}

// لكل مستخدم بصمة واحدة فقط - منع إنشاء أكثر من بصمة
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = ?");
$countStmt->execute([$userId]);
if ((int) $countStmt->fetchColumn() >= 1) {
    sendJsonResponse(false, null, 'لديك بالفعل بصمة مسجلة. لكل مستخدم بصمة واحدة فقط. احذف البصمة الحالية من الجدول أدناه إن أردت تسجيل بصمة جديدة.', 409);
}

// استبعاد البصمات المسجلة مسبقاً لهذا المستخدم (id كـ base64url للعميل)
$excludeCredentials = [];
try {
    $stmt = $pdo->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $excludeCredentials[] = [
            'type' => 'public-key',
            'id' => $row['credential_id'],
            'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble']
        ];
    }
} catch (PDOException $e) {
    // تجاهل - نكمل بدون استبعاد
}

$challenge = random_bytes(32);
$_SESSION['webauthn_register_challenge'] = base64urlEncode($challenge);
$_SESSION['webauthn_register_user_id'] = $userId;

$rpId = getRpId();
$userIdBytes = substr(hash('sha256', (string) $userId, true), 0, 32);

$options = [
    'challenge' => base64urlEncode($challenge),
    'rp' => [
        'name' => 'AmrNayl Academy',
        'id' => $rpId
    ],
    'user' => [
        'id' => base64urlEncode($userIdBytes),
        'name' => $email,
        'displayName' => $displayName ?: $email
    ],
    'pubKeyCredParams' => [
        ['type' => 'public-key', 'alg' => -7],
        ['type' => 'public-key', 'alg' => -257]
    ],
    'timeout' => 60000,
    'authenticatorSelection' => [
        'authenticatorAttachment' => 'platform',
        'requireResidentKey' => false,
        'userVerification' => 'preferred',
        'residentKey' => 'preferred'
    ]
];

if (!empty($excludeCredentials)) {
    $options['excludeCredentials'] = $excludeCredentials;
}

sendJsonResponse(true, $options, null, 200);
