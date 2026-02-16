<?php
/**
 * WebAuthn: الحصول على خيارات تسجيل الدخول بالبصمة
 * POST - body: { "email": "user@example.com" } أو {} للاعتماد على البصمات المسجلة على الجهاز (بدون إيميل)
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = [];
}

$email = isset($data['email']) ? trim($data['email']) : '';
$discoverable = empty($email);

$challenge = random_bytes(32);
$_SESSION['webauthn_login_challenge'] = base64urlEncode($challenge);
$_SESSION['webauthn_login_email'] = '';
$_SESSION['webauthn_login_user_id'] = null;

$allowCredentials = [];

if (!$discoverable && validateEmail($email)) {
    // وضع تقليدي: إيميل معطى — جلب البصمات لهذا المستخدم فقط
    $email = sanitizeInput($email);
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendJsonResponse(false, null, 'لا يوجد حساب بهذا البريد أو الحساب غير نشط', 404);
    }
    $userId = (int) $user['id'];
    $_SESSION['webauthn_login_email'] = $email;
    $_SESSION['webauthn_login_user_id'] = $userId;
    try {
        $stmt = $pdo->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allowCredentials[] = [
                'type' => 'public-key',
                'id' => $row['credential_id'],
                'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble']
            ];
        }
    } catch (PDOException $e) {
        sendJsonResponse(false, null, 'خطأ في جلب بيانات البصمة', 500);
    }
    if (empty($allowCredentials)) {
        sendJsonResponse(false, null, 'لم يتم تسجيل بصمة لهذا الحساب. سجّل البصمة من الملف الشخصي أولاً.', 404);
    }
}

// discoverable: لا نمرر أي allowCredentials — المتصفح يعرض البصمات المرتبطة بالموقع على هذا الجهاز
$rpId = getRpId();
$options = [
    'challenge' => base64urlEncode($challenge),
    'rpId' => $rpId,
    'timeout' => 60000,
    'allowCredentials' => $allowCredentials,
    'userVerification' => 'preferred'
];

sendJsonResponse(true, $options, null, 200);
