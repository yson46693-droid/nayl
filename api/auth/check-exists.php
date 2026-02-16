<?php
/**
 * ============================================
 * Check Existence API Endpoint
 * ============================================
 * API للتحقق من وجود بريد إلكتروني أو رقم هاتف مسبقاً
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// الحصول على البيانات
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$type = sanitizeInput($data['type'] ?? '');
$value = sanitizeInput($data['value'] ?? '');

if (empty($type) || empty($value)) {
    sendJsonResponse(false, null, 'بيانات ناقصة', 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في قاعدة البيانات', 500);
}

try {
    if ($type === 'email') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :val AND deleted_at IS NULL LIMIT 1");
    } else if ($type === 'phone') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE full_phone = :val AND deleted_at IS NULL LIMIT 1");
    } else {
        sendJsonResponse(false, null, 'نوع غير معروف', 400);
    }

    $stmt->execute(['val' => $value]);
    $exists = $stmt->fetch() ? true : false;

    sendJsonResponse(true, ['exists' => $exists]);

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'حدث خطأ في البحث', 500);
}
