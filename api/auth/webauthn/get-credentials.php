<?php
/**
 * WebAuthn: جلب البصمة المسجلة للمستخدم (بصمة واحدة فقط لكل مستخدم)
 * GET أو POST - يتطلب تسجيل الدخول
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, null, 'Method not allowed.', 405);
}

$user = verifySession();
if (!$user || empty($user['id'])) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول أولاً', 401);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

$userId = (int) $user['id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, credential_id, device_name, created_at
        FROM webauthn_credentials
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'خطأ في جلب بيانات البصمة', 500);
}

if (!$row) {
    sendJsonResponse(true, ['credential' => null, 'hasCredential' => false], null, 200);
    return;
}

// عرض معرف البصمة مختصراً للأمان (أول 16 حرف + ...)
$credIdShort = strlen($row['credential_id']) > 20
    ? substr($row['credential_id'], 0, 16) . '...'
    : $row['credential_id'];

$credential = [
    'id' => (int) $row['id'],
    'credential_id_short' => $credIdShort,
    'device_name' => !empty(trim((string) $row['device_name'])) ? trim($row['device_name']) : 'جهاز البصمة',
    'created_at' => $row['created_at']
];

sendJsonResponse(true, ['credential' => $credential, 'hasCredential' => true], null, 200);
