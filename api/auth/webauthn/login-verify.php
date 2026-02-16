<?php
/**
 * WebAuthn: التحقق من البصمة وإنشاء الجلسة (تسجيل الدخول)
 * POST - body: credential من navigator.credentials.get + email
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

$challengeStored = $_SESSION['webauthn_login_challenge'] ?? '';
$challengeReceived = $clientData['challenge'] ?? '';
if (!$challengeStored || $challengeReceived !== $challengeStored) {
    sendJsonResponse(false, null, 'التحدي غير متطابق أو منتهي. أعد المحاولة من صفحة تسجيل الدخول.', 400);
}

$origin = $clientData['origin'] ?? '';
$expectedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
if (strpos($origin, $expectedOrigin) !== 0 && $origin !== $expectedOrigin) {
    sendJsonResponse(false, null, 'المصدر غير مسموح', 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

// البحث عن البصمة: أولاً بالمطابقة التامة، ثم بالمستخدم من الجلسة (لكل مستخدم بصمة واحدة)
$stmt = $pdo->prepare("
    SELECT w.id, w.user_id, w.credential_id, w.public_key_x, w.public_key_y, w.sign_count
    FROM webauthn_credentials w
    INNER JOIN users u ON w.user_id = u.id AND u.deleted_at IS NULL AND u.is_active = 1
    WHERE w.credential_id = ? LIMIT 1
");
$stmt->execute([$credentialId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row && !empty($_SESSION['webauthn_login_user_id'])) {
    $userIdFromSession = (int) $_SESSION['webauthn_login_user_id'];
    $stmt = $pdo->prepare("
        SELECT w.id, w.user_id, w.credential_id, w.public_key_x, w.public_key_y, w.sign_count
        FROM webauthn_credentials w
        INNER JOIN users u ON w.user_id = u.id AND u.deleted_at IS NULL AND u.is_active = 1
        WHERE w.user_id = ? LIMIT 1
    ");
    $stmt->execute([$userIdFromSession]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$row) {
    sendJsonResponse(false, null, 'البصمة غير مسجلة أو الحساب غير نشط', 404);
}

$authDataBin = is_string($authenticatorData) ? base64urlDecode($authenticatorData) : '';
$signatureBin = is_string($signature) ? base64urlDecode($signature) : '';
if (strlen($authDataBin) < 37 || strlen($signatureBin) < 8) {
    sendJsonResponse(false, null, 'بيانات التوقيع غير صالحة', 400);
}

// التحقق من التوقيع: signedData = authData || SHA256(clientDataJSON)
$clientDataHash = hash('sha256', $clientDataBin, true);
$signedData = $authDataBin . $clientDataHash;

$publicKeyX = $row['public_key_x'] ? base64urlDecode($row['public_key_x']) : null;
$publicKeyY = $row['public_key_y'] ? base64urlDecode($row['public_key_y']) : null;
// بعض الأجهزة تخزن المفتاح العام بـ 33 بايت (صفر في البداية) — تطبيع إلى 32 بايت
$publicKeyX = normalizeEcCoord($publicKeyX);
$publicKeyY = normalizeEcCoord($publicKeyY);

if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_verify')) {
    sendJsonResponse(false, null, 'يجب تفعيل امتداد OpenSSL في PHP. افتح في المتصفح: ' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/' : '') . 'check-openssl.php لمعرفة مسار php.ini وخطوات التفعيل، ثم أعد تشغيل الخادم.', 503);
}
if (!$publicKeyX || !$publicKeyY || strlen($publicKeyX) !== 32 || strlen($publicKeyY) !== 32) {
    sendJsonResponse(false, null, 'بيانات البصمة غير مكتملة. احذف البصمة من الملف الشخصي وسجّلها من جديد.', 401);
}

// تجهيز صيغ التوقيع: raw (r||s) 64/65/66 بايت، أو DER من بعض الأجهزة
$sigDerRs = rawToDerSignature($signatureBin);
$sigDerSr = (strlen($signatureBin) >= 64) ? rawToDerSignature(substr($signatureBin, 32, 32) . substr($signatureBin, 0, 32)) : null;
$sigNorm = (strlen($signatureBin) >= 64) ? normalizeRawSignature($signatureBin) : null;
$sigDerNormRs = $sigNorm ? rawToDerSignature($sigNorm) : null;
$sigDerNormSr = $sigNorm ? rawToDerSignature(substr($sigNorm, 32, 32) . substr($sigNorm, 0, 32)) : null;
// تقسيم 65 بايت: r=32 و s=33 أو r=33 و s=32 (أجهزة مختلفة)
$sig65_32_33 = (strlen($signatureBin) === 65) ? rawToDerFromParts(substr($signatureBin, 0, 32), substr($signatureBin, 32, 33)) : null;
$sig65_33_32 = (strlen($signatureBin) === 65) ? rawToDerFromParts(substr($signatureBin, 0, 33), substr($signatureBin, 33, 32)) : null;
// توقيع مرسل كـ DER مباشرة (أحياناً من بعض الـ authenticators)
$sigAsDer = (strlen($signatureBin) >= 8 && ord($signatureBin[0]) === 0x30) ? $signatureBin : null;

$sigDersCollected = array_filter([$sigDerRs, $sigDerSr, $sigDerNormRs, $sigDerNormSr, $sig65_32_33, $sig65_33_32, $sigAsDer]);
if (empty($sigDersCollected)) {
    sendJsonResponse(false, null, 'تنسيق التوقيع غير صالح. جرّب تسجيل البصمة من جديد من الملف الشخصي.', 401);
}

$verifyOk = false;
$keyPairs = [[$publicKeyX, $publicKeyY], [$publicKeyY, $publicKeyX]];
$sigDers = $sigDersCollected;
foreach ($keyPairs as $pair) {
    $px = $pair[0];
    $py = $pair[1];
    $point = "\x04" . $px . $py;
    $der = buildEcPubKeyDer($point);
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) continue;
    foreach ($sigDers as $sigDer) {
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
    sendJsonResponse(false, null, 'فشل التحقق من البصمة. جرّب حذف البصمة من الملف الشخصي (الأمان) ثم تسجيلها من جديد على نفس الجهاز.', 401);
}

$signCount = (ord($authDataBin[33]) << 24) | (ord($authDataBin[34]) << 16) | (ord($authDataBin[35]) << 8) | ord($authDataBin[36]);
$storedSignCount = (int) $row['sign_count'];
// بعض الأجهزة لا تزيد sign_count — نرفض فقط عند احتمال إعادة اللعب (stored > 0 و received < stored)
if ($storedSignCount > 0 && $signCount < $storedSignCount) {
    sendJsonResponse(false, null, 'تم استخدام البصمة من جهاز آخر. أعد المحاولة.', 401);
}

try {
    $pdo->prepare("UPDATE webauthn_credentials SET sign_count = ? WHERE id = ?")->execute([$signCount, $row['id']]);
} catch (PDOException $e) {
    // لا نوقف العملية
}

// إذا تم العثور على البصمة عبر user_id (وليس credential_id) نحدّث credential_id ليطابق ما يرسله المتصفح في المرات القادمة
if ($row['credential_id'] !== $credentialId) {
    try {
        $pdo->prepare("UPDATE webauthn_credentials SET credential_id = ? WHERE id = ?")->execute([$credentialId, $row['id']]);
    } catch (PDOException $e) {
        // لا نوقف العملية
    }
}

$userId = (int) $row['user_id'];
$userStmt = $pdo->prepare("
    SELECT id, full_name, email, phone, full_phone, country, city, is_verified, whatsapp_verified, created_at
    FROM users WHERE id = ? AND deleted_at IS NULL AND is_active = 1 LIMIT 1
");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    sendJsonResponse(false, null, 'المستخدم غير موجود أو غير نشط', 404);
}

$user['account_type'] = 'free';
try {
    $atStmt = $pdo->prepare("SELECT COALESCE(account_type, 'free') AS account_type FROM users WHERE id = ? LIMIT 1");
    $atStmt->execute([$userId]);
    $at = $atStmt->fetch(PDO::FETCH_ASSOC);
    if ($at && isset($at['account_type'])) {
        $user['account_type'] = $at['account_type'];
    }
} catch (PDOException $e) {
}
try {
    $subStmt = $pdo->prepare("SELECT 1 FROM user_course_subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1");
    $subStmt->execute([$userId]);
    if ($subStmt->fetch()) {
        $user['account_type'] = 'vip';
    }
} catch (PDOException $e) {
}

$remember = !empty($data['remember']);
try {
    $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $updateStmt->execute([$userId]);
} catch (PDOException $e) {
}

$sessionToken = bin2hex(random_bytes(32));
$expiresAt = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+1 day'));

try {
    $sessionStmt = $pdo->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $sessionStmt->execute([
        $userId,
        $sessionToken,
        getClientIP(),
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expiresAt
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الجلسة', 500);
}

$cookieExpires = $remember ? time() + (30 * 24 * 60 * 60) : time() + (24 * 60 * 60);
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
if (PHP_VERSION_ID >= 70300) {
    @setcookie('session_token', $sessionToken, [
        'expires' => $cookieExpires,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
} else {
    @setcookie('session_token', $sessionToken, $cookieExpires, '/', '', $isSecure, true);
}

unset($_SESSION['webauthn_login_challenge'], $_SESSION['webauthn_login_email'], $_SESSION['webauthn_login_user_id']);

$responseData = [
    'user' => [
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'full_phone' => $user['full_phone'],
        'country' => $user['country'],
        'city' => $user['city'],
        'is_verified' => (bool) $user['is_verified'],
        'whatsapp_verified' => (bool) $user['whatsapp_verified'],
        'account_type' => $user['account_type'] ?? 'free',
        'created_at' => $user['created_at'],
        'last_login_at' => date('Y-m-d H:i:s')
    ],
    'session' => [
        'token' => $sessionToken,
        'expires_at' => $expiresAt
    ],
    'message' => 'تم تسجيل الدخول بنجاح'
];

sendJsonResponse(true, $responseData, null, 200);

/**
 * تطبيع إحداثي EC (32 أو 33 بايت) إلى 32 بايت — إزالة الصفر الأمامي إن وُجد
 */
function normalizeEcCoord($bin) {
    if (!$bin || strlen($bin) < 32) return null;
    if (strlen($bin) === 33 && $bin[0] === "\x00") return substr($bin, 1, 32);
    if (strlen($bin) === 32) return $bin;
    return null;
}

/**
 * تطبيع التوقيع الخام (64 أو 65 أو 66 بايت) إلى 64 بايت — r و s كل منهما 32 بايت بعد إزالة الأصفار الأمامية
 */
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

/**
 * بناء DER لـ EC public key (P-256) من النقطة غير المضغوطة 04||x||y (65 bytes)
 */
function buildEcPubKeyDer($point) {
    $oidEc = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
    $oidP256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $seqAlg = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $bitString = "\x03\x42\x00" . $point;
    $outer = "\x30" . chr(strlen($seqAlg) + strlen($bitString)) . $seqAlg . $bitString;
    return $outer;
}

/**
 * تحويل توقيع ECDSA من raw (r||s حتى 33+33 بايت) إلى DER
 */
function rawToDerSignature($raw) {
    $len = strlen($raw);
    if ($len < 64) return null;
    $r = substr($raw, 0, 32);
    $s = substr($raw, 32, 32);
    return rawToDerFromParts($r, $s);
}

/**
 * بناء توقيع DER من مكوّني r و s (بايتات خام، قد تكون 32 أو 33)
 */
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
