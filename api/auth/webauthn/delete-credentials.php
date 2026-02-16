<?php
/**
 * WebAuthn: حذف جميع البصمات المسجلة للمستخدم (لإعادة التسجيل)
 * POST - يتطلب تسجيل الدخول
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed. Use POST.', 405);
}

$user = verifySession();
if (!$user || empty($user['id'])) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول أولاً', 401);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    $stmt = $pdo->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
    $stmt->execute([(int) $user['id']]);
    $deleted = $stmt->rowCount();
} catch (PDOException $e) {
    sendJsonResponse(false, null, 'فشل حذف البصمة', 500);
}

sendJsonResponse(true, ['deleted' => $deleted, 'message' => 'تم حذف البصمة. يمكنك تسجيل بصمة جديدة الآن.'], null, 200);
