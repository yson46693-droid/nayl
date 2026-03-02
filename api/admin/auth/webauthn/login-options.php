<?php
/**
 * WebAuthn للمشرفين: خيارات تسجيل الدخول بالبصمة (discoverable - بدون اسم مستخدم)
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
}

$challenge = random_bytes(32);
$_SESSION['webauthn_admin_login_challenge'] = base64urlEncode($challenge);
$_SESSION['webauthn_admin_login_id'] = null;

// وضع discoverable: لا نمرر allowCredentials — المتصفح يعرض البصمات المرتبطة بالموقع
$rpId = getRpId();
$options = [
    'challenge' => base64urlEncode($challenge),
    'rpId' => $rpId,
    'timeout' => 60000,
    'allowCredentials' => [],
    'userVerification' => 'preferred'
];

sendJsonResponse(true, $options, null, 200);
