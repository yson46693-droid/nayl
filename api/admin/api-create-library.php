<?php
/**
 * إنشاء مكتبة فيديو جديدة في Bunny CDN (للتجربة أو التشغيل اليدوي)
 * يستخدم BUNNY_ACCOUNT_API_KEY من ملف .env - لا تضمن أي مفاتيح هنا.
 */

require_once __DIR__ . '/../config/bunny-cdn.php';

// ترتيب الإنشاء (من GET/POST للتجربة، أو افتراضي 1)
$orderNumber = isset($_GET['order']) ? (int) $_GET['order'] : (isset($_POST['order']) ? (int) $_POST['order'] : 1);
$orderNumber = max(1, $orderNumber);

$date = date('Y-m-d');
$libraryName = 'Library_' . str_pad($orderNumber, 3, '0', STR_PAD_LEFT) . '_' . $date;

$result = createBunnyLibrary($libraryName);

if (isset($result['error'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $result['error'],
        'library_name' => $libraryName
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'library_name' => $libraryName,
    'library_id' => $result['Id'],
    'api_key' => $result['ApiKey'] ?? ''
], JSON_UNESCAPED_UNICODE);
