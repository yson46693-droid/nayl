<?php
/**
 * اختبار الاتصال بقاعدة البيانات
 * للتحقق من إعدادات api/.env فقط - احذف هذا الملف أو احمِه على السيرفر الحقيقي
 * أضف ?debug=1 لرؤية رسالة الخطأ الفعلية من MySQL (للتشخيص فقط)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$pdo = getDatabaseConnection();
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($pdo) {
    echo json_encode([
        'success' => true,
        'message' => 'اتصال ناجح بقاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE);
} else {
    $err = function_exists('getLastDatabaseError') ? getLastDatabaseError() : null;
    $isMaxConnections = $err && (strpos($err, '1226') !== false || strpos($err, 'max_connections_per_hour') !== false);
    $response = [
        'success' => false,
        'message' => $isMaxConnections
            ? 'تم استهلاك حد الاتصالات بالساعة (500). جرّب بعد دقائق، أو فعّل DB_PERSISTENT=true في api/.env، أو اطلب من الاستضافة رفع الحد.'
            : 'فشل الاتصال. تحقق من: 1) وجود ملف api/.env 2) قيم DB_HOST, DB_NAME, DB_USER, DB_PASS 3) تشغيل MySQL 4) إنشاء قاعدة البيانات (استورد database-schema.sql أو nayl.sql)'
    ];
    if ($debug && $err) {
        $response['debug_error'] = $err;
        $response['debug_used'] = [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : '(غير معرّف)',
            'DB_NAME' => defined('DB_NAME') ? DB_NAME : '(غير معرّف)',
            'DB_USER' => defined('DB_USER') ? DB_USER : '(غير معرّف)',
            'DB_PORT' => defined('DB_PORT') ? DB_PORT : '(غير معرّف)',
        ];
    }
    http_response_code(500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
