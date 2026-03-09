<?php
/**
 * WebAuthn للمشرفين: خيارات تسجيل البصمة (يُستدعى بعد تسجيل الدخول بكلمة المرور)
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
}

$admin = verifyAdminSession();
if (!$admin || empty($admin['id'])) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول كأدمن أولاً', 401);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    $pdo->query("SELECT 1 FROM webauthn_admin_credentials LIMIT 1");
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'جدول البصمة غير موجود. شغّل ترحيل قاعدة البيانات.', 503);
}

$adminId = (int) $admin['id'];
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM webauthn_admin_credentials WHERE admin_id = ?");
$countStmt->execute([$adminId]);
if ((int) $countStmt->fetchColumn() >= 1) {
    sendJsonResponse(false, null, 'لديك بالفعل بصمة مسجلة. احذفها أولاً إن أردت تسجيل بصمة جديدة.', 409);
}

$excludeCredentials = [];
try {
    $stmt = $pdo->prepare("SELECT credential_id FROM webauthn_admin_credentials WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $excludeCredentials[] = [
            'type' => 'public-key',
            'id' => $row['credential_id'],
            'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble']
        ];
    }
} catch (PDOException $e) {}

$challenge = random_bytes(32);
$_SESSION['webauthn_admin_register_challenge'] = base64urlEncode($challenge);
$_SESSION['webauthn_admin_register_id'] = $adminId;

$rpId = getRpId();
$userIdBytes = substr(hash('sha256', 'admin_' . $adminId, true), 0, 32);
$username = $admin['username'] ?? ('admin' . $adminId);

$options = [
    'challenge' => base64urlEncode($challenge),
    'rp' => [
        'name' => (function_exists('env') ? (env('APP_NAME', '') ?: 'AmrNayl Academy') : 'AmrNayl Academy') . ' - لوحة التحكم',
        'id' => $rpId
    ],
    'user' => [
        'id' => base64urlEncode($userIdBytes),
        'name' => $username,
        'displayName' => $admin['full_name'] ?: $username
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
