<?php
/**
 * WebAuthn للمشرفين: التحقق من البصمة وإنشاء جلسة الأدمن
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
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
$authenticatorData = $response['authenticatorData'] ?? '';
$signature = $response['signature'] ?? '';

if (!$credentialId || !$clientDataJSON || !$authenticatorData || !$signature) {
    sendJsonResponse(false, null, 'بيانات البصمة ناقصة', 400);
}

$clientDataBin = is_string($clientDataJSON) ? base64urlDecode($clientDataJSON) : '';
if (strlen($clientDataBin) < 32) {
    sendJsonResponse(false, null, 'بيانات العميل غير صالحة', 400);
}
$clientData = json_decode($clientDataBin, true);
if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.get') {
    sendJsonResponse(false, null, 'نوع الطلب غير صحيح', 400);
}

$challengeStored = $_SESSION['webauthn_admin_login_challenge'] ?? '';
$challengeReceived = $clientData['challenge'] ?? '';
if (!$challengeStored || $challengeReceived !== $challengeStored) {
    sendJsonResponse(false, null, 'التحدي غير متطابق أو منتهي. أعد المحاولة من صفحة تسجيل الدخول.', 400);
}

$origin = $clientData['origin'] ?? '';
$expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . (getRpId());
if (strpos($origin, $expectedOrigin) !== 0 && $origin !== $expectedOrigin) {
    sendJsonResponse(false, null, 'المصدر غير مسموح', 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

$stmt = $pdo->prepare("
    SELECT w.id, w.admin_id, w.credential_id, w.public_key_x, w.public_key_y, w.sign_count
    FROM webauthn_admin_credentials w
    INNER JOIN admins a ON w.admin_id = a.id AND a.is_active = 1
    WHERE w.credential_id = ? LIMIT 1
");
$stmt->execute([$credentialId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    sendJsonResponse(false, null, 'البصمة غير مسجلة أو الحساب غير نشط. سجّل الدخول بكلمة المرور ثم فعّل البصمة من لوحة التحكم.', 404);
}

$authDataBin = is_string($authenticatorData) ? base64urlDecode($authenticatorData) : '';
$signatureBin = is_string($signature) ? base64urlDecode($signature) : '';
if (strlen($authDataBin) < 37 || strlen($signatureBin) < 8) {
    sendJsonResponse(false, null, 'بيانات التوقيع غير صالحة', 400);
}

$clientDataHash = hash('sha256', $clientDataBin, true);
$signedData = $authDataBin . $clientDataHash;

$publicKeyX = $row['public_key_x'] ? base64urlDecode($row['public_key_x']) : null;
$publicKeyY = $row['public_key_y'] ? base64urlDecode($row['public_key_y']) : null;
$publicKeyX = normalizeEcCoord($publicKeyX);
$publicKeyY = normalizeEcCoord($publicKeyY);

if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_verify')) {
    sendJsonResponse(false, null, 'يجب تفعيل امتداد OpenSSL في PHP.', 503);
}
if (!$publicKeyX || !$publicKeyY || strlen($publicKeyX) !== 32 || strlen($publicKeyY) !== 32) {
    sendJsonResponse(false, null, 'بيانات البصمة غير مكتملة. سجّل البصمة من جديد من لوحة التحكم.', 401);
}

$sigDerRs = rawToDerSignature($signatureBin);
$sigDerSr = (strlen($signatureBin) >= 64) ? rawToDerSignature(substr($signatureBin, 32, 32) . substr($signatureBin, 0, 32)) : null;
$sigNorm = (strlen($signatureBin) >= 64) ? normalizeRawSignature($signatureBin) : null;
$sigDerNormRs = $sigNorm ? rawToDerSignature($sigNorm) : null;
$sigDerNormSr = $sigNorm ? rawToDerSignature(substr($sigNorm, 32, 32) . substr($sigNorm, 0, 32)) : null;
$sig65_32_33 = (strlen($signatureBin) === 65) ? rawToDerFromParts(substr($signatureBin, 0, 32), substr($signatureBin, 32, 33)) : null;
$sig65_33_32 = (strlen($signatureBin) === 65) ? rawToDerFromParts(substr($signatureBin, 0, 33), substr($signatureBin, 33, 32)) : null;
$sigAsDer = (strlen($signatureBin) >= 8 && ord($signatureBin[0]) === 0x30) ? $signatureBin : null;

$sigDersCollected = array_filter([$sigDerRs, $sigDerSr, $sigDerNormRs, $sigDerNormSr, $sig65_32_33, $sig65_33_32, $sigAsDer]);
if (empty($sigDersCollected)) {
    sendJsonResponse(false, null, 'تنسيق التوقيع غير صالح. سجّل البصمة من جديد.', 401);
}

$verifyOk = false;
$keyPairs = [[$publicKeyX, $publicKeyY], [$publicKeyY, $publicKeyX]];
foreach ($keyPairs as $pair) {
    $px = $pair[0];
    $py = $pair[1];
    $point = "\x04" . $px . $py;
    $der = buildEcPubKeyDer($point);
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) continue;
    foreach ($sigDersCollected as $sigDer) {
        if ($sigDer && openssl_verify($signedData, $sigDer, $key, OPENSSL_ALGO_SHA256) === 1) {
            $verifyOk = true;
            break 2;
        }
    }
    if (is_resource($key)) {
        openssl_pkey_free($key);
    }
}
if (!$verifyOk) {
    sendJsonResponse(false, null, 'فشل التحقق من البصمة. سجّل البصمة من جديد من لوحة التحكم.', 401);
}

$signCount = (ord($authDataBin[33]) << 24) | (ord($authDataBin[34]) << 16) | (ord($authDataBin[35]) << 8) | ord($authDataBin[36]);
$storedSignCount = (int) $row['sign_count'];
if ($storedSignCount > 0 && $signCount < $storedSignCount) {
    sendJsonResponse(false, null, 'تم استخدام البصمة من جهاز آخر. أعد المحاولة.', 401);
}

try {
    $pdo->prepare("UPDATE webauthn_admin_credentials SET sign_count = ? WHERE id = ?")->execute([$signCount, $row['id']]);
} catch (PDOException $e) {}

if ($row['credential_id'] !== $credentialId) {
    try {
        $pdo->prepare("UPDATE webauthn_admin_credentials SET credential_id = ? WHERE id = ?")->execute([$credentialId, $row['id']]);
    } catch (PDOException $e) {}
}

$adminId = (int) $row['admin_id'];
$adminStmt = $pdo->prepare("SELECT id, username, full_name, email, role FROM admins WHERE id = ? AND is_active = 1 LIMIT 1");
$adminStmt->execute([$adminId]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    sendJsonResponse(false, null, 'الحساب غير موجود أو غير نشط', 404);
}

try {
    $pdo->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = ?")->execute([$adminId]);
} catch (PDOException $e) {}

$remember = !empty($data['remember']);
$token = bin2hex(random_bytes(32));
$expiresAt = $remember ? date('Y-m-d H:i:s', strtotime('+7 days')) : date('Y-m-d H:i:s', strtotime('+12 hours'));
$clientIP = getClientIP();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $sessionStmt = $pdo->prepare("
        INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at, last_activity_at, last_rotated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $sessionStmt->execute([$adminId, $token, $clientIP, $userAgent, $expiresAt]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $sessionStmt = $pdo->prepare("
            INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $sessionStmt->execute([$adminId, $token, $clientIP, $userAgent, $expiresAt]);
    } else {
        sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الجلسة', 500);
    }
}

$cookieExpires = strtotime($expiresAt);
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
if (PHP_VERSION_ID >= 70300) {
    setcookie('admin_session_token', $token, [
        'expires' => $cookieExpires,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
} else {
    setcookie('admin_session_token', $token, $cookieExpires, '/', '', $isSecure, false);
}

unset($_SESSION['webauthn_admin_login_challenge'], $_SESSION['webauthn_admin_login_id']);

$responseData = [
    'admin' => [
        'id' => (int) $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role']
    ],
    'user' => [
        'id' => (int) $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role']
    ],
    'session' => [
        'token' => $token,
        'expires_at' => $expiresAt
    ],
    'token' => $token,
    'message' => 'تم تسجيل الدخول بنجاح'
];

sendJsonResponse(true, $responseData, null, 200);

function normalizeEcCoord($bin) {
    if (!$bin || strlen($bin) < 32) return null;
    if (strlen($bin) === 33 && $bin[0] === "\x00") return substr($bin, 1, 32);
    if (strlen($bin) === 32) return $bin;
    return null;
}

function normalizeRawSignature($raw) {
    $len = strlen($raw);
    if ($len < 64) return null;
    $rLen = min(33, (int) (($len + 1) / 2));
    $sLen = min(33, $len - $rLen);
    $r = substr($raw, 0, $rLen);
    $s = substr($raw, $rLen, $sLen);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    if (strlen($r) > 32) $r = substr($r, -32);
    if (strlen($s) > 32) $s = substr($s, -32);
    if (strlen($r) === 0) $r = "\x00";
    if (strlen($s) === 0) $s = "\x00";
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

function buildEcPubKeyDer($point) {
    $oidEc = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
    $oidP256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $seqAlg = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $bitString = "\x03\x42\x00" . $point;
    $outer = "\x30" . chr(strlen($seqAlg) + strlen($bitString)) . $seqAlg . $bitString;
    return $outer;
}

function rawToDerSignature($raw) {
    $len = strlen($raw);
    if ($len < 64) return null;
    $r = substr($raw, 0, 32);
    $s = substr($raw, 32, 32);
    return rawToDerFromParts($r, $s);
}

function rawToDerFromParts($rBin, $sBin) {
    if (!$rBin || !$sBin) return null;
    $r = ltrim($rBin, "\x00");
    $s = ltrim($sBin, "\x00");
    if (strlen($r) === 0) $r = "\x00";
    if (strlen($s) === 0) $s = "\x00";
    if (strlen($r) > 32) $r = substr($r, -32);
    if (strlen($s) > 32) $s = substr($s, -32);
    if (ord($r[0]) >= 0x80) $r = "\x00" . $r;
    if (ord($s[0]) >= 0x80) $s = "\x00" . $s;
    $rDer = "\x02" . chr(strlen($r)) . $r;
    $sDer = "\x02" . chr(strlen($s)) . $s;
    return "\x30" . chr(strlen($rDer) + strlen($sDer)) . $rDer . $sDer;
}
