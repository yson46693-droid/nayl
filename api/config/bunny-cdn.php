<?php
/**
 * ============================================
 * Bunny CDN Configuration
 * ============================================
 * إعدادات Bunny CDN لرفع الفيديوهات
 */

// تحميل متغيرات البيئة
require_once __DIR__ . '/env.php';

// تحميل ملف .env
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    error_log("Bunny CDN Config: .env file not found at: {$envFile}");
} else {
    $loadResult = loadEnv($envFile);
    if (!$loadResult) {
        error_log("Bunny CDN Config: Failed to load .env file");
    }
}

// إعدادات Bunny CDN من ملف .env
$libraryId = env('BUNNY_LIBRARY_ID', '');
$apiKey = env('BUNNY_API_KEY', '');
$accountApiKey = env('BUNNY_ACCOUNT_API_KEY', '');
$cdnUrl = env('BUNNY_CDN_URL', 'https://video.bunnycdn.com');

// تسجيل للتحقق (بدون عرض القيم الحساسة)
error_log("Bunny CDN Config Loaded - Library ID: " . (!empty($libraryId) ? 'SET' : 'EMPTY') . ", API Key: " . (!empty($apiKey) ? 'SET' : 'EMPTY') . ", Account API Key: " . (!empty($accountApiKey) ? 'SET' : 'EMPTY'));

define('BUNNY_LIBRARY_ID', $libraryId);
define('BUNNY_API_KEY', $apiKey);
define('BUNNY_ACCOUNT_API_KEY', $accountApiKey);
define('BUNNY_CDN_URL', $cdnUrl);

// التحقق من SSL: استخدم false فقط في بيئة تطوير خلف بروكسي/شهادة ذاتية (مثل خطأ cURL 60)
$sslVerifyEnv = env('BUNNY_CDN_SSL_VERIFY', 'true');
define('BUNNY_CDN_SSL_VERIFY', filter_var($sslVerifyEnv, FILTER_VALIDATE_BOOLEAN));

/**
 * إنشاء مكتبة فيديو جديدة في Bunny CDN (عبر Core API - api.bunny.net)
 * @param string $name - اسم المكتبة (مثل: "5 ترتيب الكورس")
 * @return array - ['Id' => int, 'ApiKey' => string] أو ['error' => string]
 */
function createBunnyLibrary($name) {
    $accountApiKey = trim((string) BUNNY_ACCOUNT_API_KEY);
    if ($accountApiKey === '') {
        $errorMsg = "Bunny Account API Key مفقود. أضف BUNNY_ACCOUNT_API_KEY في .env لإنشاء مكتبات جديدة.";
        error_log($errorMsg);
        return ['error' => $errorMsg];
    }
    $url = 'https://api.bunny.net/videolibrary';
    $postData = json_encode(['Name' => $name]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'AccessKey: ' . $accountApiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => BUNNY_CDN_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => BUNNY_CDN_SSL_VERIFY ? 2 : 0
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    if ($error) {
        $errorMsg = "Bunny Create Library cURL Error (Code: {$curlErrno}): {$error}";
        error_log($errorMsg);
        return ['error' => $errorMsg];
    }
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorMsg = "Bunny Create Library HTTP Error: {$httpCode}" . ($response ? " - " . substr($response, 0, 500) : '');
        if ($httpCode === 401) {
            $errorMsg .= ' استخدم مفتاح الحساب (Account API Key) من: Bunny Dashboard → Account → API (وليس مفتاح المكتبة من Stream).';
        }
        error_log($errorMsg);
        return ['error' => $errorMsg, 'http_code' => $httpCode, 'response' => $response];
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['Id'])) {
        $errorMsg = "Bunny Create Library: استجابة غير صالحة - " . ($response ? substr($response, 0, 300) : '');
        error_log($errorMsg);
        return ['error' => $errorMsg];
    }
    error_log("Bunny CDN Library Created - Id: " . $data['Id'] . ", Name: " . $name);
    return [
        'Id' => (int) $data['Id'],
        'ApiKey' => $data['ApiKey'] ?? ''
    ];
}

/**
 * إنشاء فيديو جديد في Bunny CDN
 * @param string $title - عنوان الفيديو
 * @param int|null $libraryId - معرف المكتبة (إن وُجد، وإلا تُستخدم الإعدادات الافتراضية)
 * @param string|null $libraryApiKey - مفتاح API للمكتبة (مطلوب إذا تم تمرير libraryId)
 * @return array|null - بيانات الفيديو أو null في حالة الفشل
 */
function createBunnyVideo($title, $libraryId = null, $libraryApiKey = null) {
    if ($libraryId !== null && $libraryApiKey !== null) {
        $libId = $libraryId;
        $apiKey = $libraryApiKey;
    } else {
        $libId = BUNNY_LIBRARY_ID;
        $apiKey = BUNNY_API_KEY;
    }
    
    if (empty($libId) || empty($apiKey)) {
        $errorMsg = "Bunny CDN credentials are missing. Library ID: " . (empty($libId) ? 'empty' : 'set') . ", API Key: " . (empty($apiKey) ? 'empty' : 'set');
        error_log($errorMsg);
        return ['error' => $errorMsg];
    }
    
    // استخدام API URL مباشرة (ليس pull zone URL)
    $apiUrl = 'https://video.bunnycdn.com';
    $url = $apiUrl . "/library/{$libId}/videos";
    
    $postData = json_encode([
        "title" => $title
    ]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "AccessKey: {$apiKey}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => BUNNY_CDN_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => BUNNY_CDN_SSL_VERIFY ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    // تسجيل تفاصيل الطلب
    error_log("Bunny CDN Create Video Request:");
    error_log("  URL: {$url}");
    error_log("  Library ID: {$libId}");
    error_log("  Title: {$title}");
    error_log("  HTTP Code: {$httpCode}");
    
    if ($error) {
        $errorMsg = "Bunny CDN cURL Error (Code: {$curlErrno}): {$error}";
        error_log($errorMsg);
        return ['error' => $errorMsg];
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        $errorMsg = "Bunny CDN HTTP Error: {$httpCode}";
        if ($response) {
            $errorMsg .= " - Response: " . substr($response, 0, 500);
            error_log("Bunny CDN Full Response: " . $response);
        }
        error_log($errorMsg);
        error_log("Bunny CDN Request URL: {$url}");
        error_log("Bunny CDN Request Data: {$postData}");
        return ['error' => $errorMsg, 'http_code' => $httpCode, 'response' => $response];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Bunny CDN JSON Decode Error: " . json_last_error_msg() . " - Response: " . substr($response, 0, 500);
        error_log($errorMsg);
        return ['error' => $errorMsg, 'raw_response' => $response];
    }
    
    if (!$data || !isset($data['guid'])) {
        $errorMsg = "Bunny CDN: Invalid response format - Missing 'guid' field";
        error_log($errorMsg);
        error_log("Bunny CDN Response Data: " . print_r($data, true));
        error_log("Bunny CDN Raw Response: " . $response);
        return ['error' => $errorMsg, 'response_data' => $data];
    }
    
    error_log("Bunny CDN Video Created Successfully - GUID: " . $data['guid']);
    return $data;
}

/**
 * رفع فيديو إلى Bunny CDN
 * @param string $videoId - معرف الفيديو في Bunny CDN
 * @param string $videoPath - مسار ملف الفيديو
 * @param int|null $libraryId - معرف المكتبة (اختياري)
 * @param string|null $libraryApiKey - مفتاح API للمكتبة (اختياري)
 * @return bool - true في حالة النجاح، false في حالة الفشل
 */
function uploadBunnyVideo($videoId, $videoPath, $libraryId = null, $libraryApiKey = null) {
    if ($libraryId !== null && $libraryApiKey !== null) {
        $libId = $libraryId;
        $apiKey = $libraryApiKey;
    } else {
        $libId = BUNNY_LIBRARY_ID;
        $apiKey = BUNNY_API_KEY;
    }
    
    if (empty($libId) || empty($apiKey)) {
        error_log("Bunny CDN credentials are missing");
        return false;
    }
    
    if (!file_exists($videoPath)) {
        error_log("Video file not found: {$videoPath}");
        return false;
    }
    
    // استخدام API URL مباشرة (ليس pull zone URL)
    $apiUrl = 'https://video.bunnycdn.com';
    $url = $apiUrl . "/library/{$libId}/videos/{$videoId}";
    
    $videoContent = file_get_contents($videoPath);
    if ($videoContent === false) {
        error_log("Failed to read video file: {$videoPath}");
        return false;
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_HTTPHEADER => [
            "AccessKey: {$apiKey}",
            "Content-Type: video/mp4"
        ],
        CURLOPT_POSTFIELDS => $videoContent,
        CURLOPT_TIMEOUT => 300, // 5 دقائق للفيديوهات الكبيرة
        CURLOPT_INFILESIZE => filesize($videoPath),
        CURLOPT_SSL_VERIFYPEER => BUNNY_CDN_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => BUNNY_CDN_SSL_VERIFY ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Bunny CDN Upload Error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("Bunny CDN Upload HTTP Error: {$httpCode} - {$response}");
        return false;
    }
    
    return true;
}

/**
 * الحصول على معلومات فيديو من Bunny CDN
 * @param string $videoId - معرف الفيديو
 * @param int|null $libraryId - معرف المكتبة (اختياري)
 * @param string|null $libraryApiKey - مفتاح API للمكتبة (اختياري)
 * @return array|null - بيانات الفيديو أو null في حالة الفشل
 */
function getBunnyVideoInfo($videoId, $libraryId = null, $libraryApiKey = null) {
    if ($libraryId !== null && $libraryApiKey !== null) {
        $libId = $libraryId;
        $apiKey = $libraryApiKey;
    } else {
        $libId = BUNNY_LIBRARY_ID;
        $apiKey = BUNNY_API_KEY;
    }
    
    if (empty($libId) || empty($apiKey)) {
        return null;
    }
    
    // استخدام API URL مباشرة (ليس pull zone URL)
    $apiUrl = 'https://video.bunnycdn.com';
    $url = $apiUrl . "/library/{$libId}/videos/{$videoId}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "AccessKey: {$apiKey}"
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => BUNNY_CDN_SSL_VERIFY,
        CURLOPT_SSL_VERIFYHOST => BUNNY_CDN_SSL_VERIFY ? 2 : 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * بناء رابط الفيديو للعرض (Pull Zone URL)
 * @param string $videoId - معرف الفيديو في Bunny CDN
 * @param int|null $libraryId - معرف المكتبة (إن وُجد نستخدمه لبناء الرابط)
 * @return string|null - رابط الفيديو أو null
 */
function getBunnyVideoUrl($videoId, $libraryId = null) {
    // إذا تم تمرير مكتبة محددة، نستخدمها لبناء الرابط
    if ($libraryId !== null && $libraryId !== '') {
        return "https://vz-{$libraryId}.b-cdn.net/{$videoId}";
    }
    
    $pullZoneUrl = BUNNY_CDN_URL;
    
    // إذا كان Pull Zone URL موجوداً وليس API URL
    if (!empty($pullZoneUrl) && strpos($pullZoneUrl, 'video.bunnycdn.com') === false) {
        // إضافة https:// إذا لم يكن موجوداً
        if (strpos($pullZoneUrl, 'http') !== 0) {
            $pullZoneUrl = 'https://' . $pullZoneUrl;
        }
        return $pullZoneUrl . '/' . $videoId;
    }
    
    // إذا لم يكن Pull Zone URL محدداً، نستخدم Library ID لبناء رابط مباشر
    $libId = BUNNY_LIBRARY_ID;
    if (!empty($libId)) {
        return "https://vz-{$libId}.b-cdn.net/{$videoId}";
    }
    
    return null;
}
