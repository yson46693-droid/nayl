<?php
/**
 * WebAuthn للمشرفين: التحقق من البصمة وحفظها (بعد تسجيل الدخول بكلمة المرور)
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

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($data['credential'])) {
    sendJsonResponse(false, null, 'بيانات غير صحيحة', 400);
}

$cred = $data['credential'];
$credentialId = isset($cred['id']) ? trim($cred['id']) : '';
$response = $cred['response'] ?? [];
$clientDataJSON = $response['clientDataJSON'] ?? '';
$attestationObject = $response['attestationObject'] ?? '';

if (!$credentialId || !$clientDataJSON || !$attestationObject) {
    sendJsonResponse(false, null, 'بيانات البصمة ناقصة', 400);
}

$clientDataBin = is_string($clientDataJSON) ? base64urlDecode($clientDataJSON) : '';
if (strlen($clientDataBin) < 32) {
    sendJsonResponse(false, null, 'بيانات العميل غير صالحة', 400);
}
$clientData = json_decode($clientDataBin, true);
if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.create') {
    sendJsonResponse(false, null, 'نوع الطلب غير صحيح', 400);
}

$challengeStored = $_SESSION['webauthn_admin_register_challenge'] ?? '';
$challengeReceived = $clientData['challenge'] ?? '';
if (!$challengeStored || $challengeReceived !== $challengeStored) {
    sendJsonResponse(false, null, 'التحدي غير متطابق أو منتهي', 400);
}

$origin = $clientData['origin'] ?? '';
$expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . (getRpId());
if (strpos($origin, $expectedOrigin) !== 0 && $origin !== $expectedOrigin) {
    sendJsonResponse(false, null, 'المصدر غير مسموح', 400);
}

$adminId = (int) ($_SESSION['webauthn_admin_register_id'] ?? $admin['id']);

$attBin = is_string($attestationObject) ? base64urlDecode($attestationObject) : '';
if (strlen($attBin) < 55) {
    sendJsonResponse(false, null, 'بيانات البصمة غير صالحة', 400);
}

$authDataKey = "\x68authData";
$pos = strpos($attBin, $authDataKey);
if ($pos === false) {
    sendJsonResponse(false, null, 'لم يتم العثور على authData', 400);
}

$valueStart = $pos + strlen($authDataKey);
if ($valueStart + 2 > strlen($attBin)) {
    sendJsonResponse(false, null, 'authData قصير', 400);
}

$tag = ord($attBin[$valueStart]);
if ($tag === 0x58) {
    $len = ord($attBin[$valueStart + 1]);
    $authData = substr($attBin, $valueStart + 2, $len);
} elseif ($tag === 0x59) {
    $len = (ord($attBin[$valueStart + 1]) << 8) | ord($attBin[$valueStart + 2]);
    $authData = substr($attBin, $valueStart + 3, $len);
} else {
    sendJsonResponse(false, null, 'صيغة authData غير مدعومة', 400);
}

if (strlen($authData) < 55) {
    sendJsonResponse(false, null, 'authData غير مكتمل', 400);
}

$credIdLen = (ord($authData[53]) << 8) | ord($authData[54]);
$credIdStart = 55;
if ($credIdStart + $credIdLen > strlen($authData)) {
    sendJsonResponse(false, null, 'معرف البصمة غير صالح', 400);
}

$credentialIdStored = $credentialId;
$coseStart = $credIdStart + $credIdLen;
$coseKey = substr($authData, $coseStart);

$x = null;
$y = null;
$searchX32 = "\x21\x58\x20";
$searchY32 = "\x22\x58\x20";
$searchX33 = "\x21\x58\x21";
$searchY33 = "\x22\x58\x21";
$searchX32_59 = "\x21\x59\x00\x20";
$searchY32_59 = "\x22\x59\x00\x20";
$searchX33_59 = "\x21\x59\x00\x21";
$searchY33_59 = "\x22\x59\x00\x21";

$ix = strpos($coseKey, $searchX32);
if ($ix !== false && strlen($coseKey) >= $ix + 3 + 32) {
    $x = substr($coseKey, $ix + 3, 32);
}
if ($x === null && ($ix = strpos($coseKey, $searchX33)) !== false && strlen($coseKey) >= $ix + 3 + 33) {
    $xRaw = substr($coseKey, $ix + 3, 33);
    $x = ($xRaw[0] === "\x00") ? substr($xRaw, 1, 32) : substr($xRaw, -32);
}
if ($x === null && ($ix = strpos($coseKey, $searchX32_59)) !== false && strlen($coseKey) >= $ix + 5 + 32) {
    $x = substr($coseKey, $ix + 5, 32);
}
if ($x === null && ($ix = strpos($coseKey, $searchX33_59)) !== false && strlen($coseKey) >= $ix + 5 + 33) {
    $xRaw = substr($coseKey, $ix + 5, 33);
    $x = ($xRaw[0] === "\x00") ? substr($xRaw, 1, 32) : substr($xRaw, -32);
}

$iy = strpos($coseKey, $searchY32);
if ($iy !== false && strlen($coseKey) >= $iy + 3 + 32) {
    $y = substr($coseKey, $iy + 3, 32);
}
if ($y === null && ($iy = strpos($coseKey, $searchY33)) !== false && strlen($coseKey) >= $iy + 3 + 33) {
    $yRaw = substr($coseKey, $iy + 3, 33);
    $y = ($yRaw[0] === "\x00") ? substr($yRaw, 1, 32) : substr($yRaw, -32);
}
if ($y === null && ($iy = strpos($coseKey, $searchY32_59)) !== false && strlen($coseKey) >= $iy + 5 + 32) {
    $y = substr($coseKey, $iy + 5, 32);
}
if ($y === null && ($iy = strpos($coseKey, $searchY33_59)) !== false && strlen($coseKey) >= $iy + 5 + 33) {
    $yRaw = substr($coseKey, $iy + 5, 33);
    $y = ($yRaw[0] === "\x00") ? substr($yRaw, 1, 32) : substr($yRaw, -32);
}

$publicKeyX = ($x !== null && strlen($x) === 32) ? base64urlEncode($x) : null;
$publicKeyY = ($y !== null && strlen($y) === 32) ? base64urlEncode($y) : null;
$signCount = (ord($authData[33]) << 24) | (ord($authData[34]) << 16) | (ord($authData[35]) << 8) | ord($authData[36]);

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM webauthn_admin_credentials WHERE admin_id = ?");
$countStmt->execute([$adminId]);
if ((int) $countStmt->fetchColumn() >= 1) {
    sendJsonResponse(false, null, 'لديك بالفعل بصمة مسجلة. احذفها أولاً إن أردت تسجيل بصمة جديدة.', 409);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO webauthn_admin_credentials (admin_id, credential_id, public_key_x, public_key_y, sign_count, device_name)
        VALUES (:admin_id, :credential_id, :public_key_x, :public_key_y, :sign_count, :device_name)
    ");
    $stmt->execute([
        'admin_id' => $adminId,
        'credential_id' => $credentialIdStored,
        'public_key_x' => $publicKeyX,
        'public_key_y' => $publicKeyY,
        'sign_count' => $signCount,
        'device_name' => isset($data['deviceName']) ? sanitizeInput($data['deviceName']) : null
    ]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        sendJsonResponse(false, null, 'هذه البصمة مسجلة مسبقاً', 409);
    }
    sendJsonResponse(false, null, 'فشل حفظ البصمة', 500);
}

unset($_SESSION['webauthn_admin_register_challenge'], $_SESSION['webauthn_admin_register_id']);
sendJsonResponse(true, ['message' => 'تم تسجيل البصمة بنجاح'], null, 200);
