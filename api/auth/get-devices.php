<?php
/**
 * ============================================
 * Get Connected Devices API Endpoint
 * ============================================
 * API لجلب الأجهزة المتصلة للمستخدم الحالي
 * 
 * Endpoint: GET /api/auth/get-devices.php
 */

// بدء الجلسة
session_start();

// تحميل env.php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

// إعدادات CORS
require_once __DIR__ . '/../config/auth.php';

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Origin not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Security Headers
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// السماح فقط بـ GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استيراد الملفات المطلوبة
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/auth.php';

// التحقق من تسجيل الدخول
$user = requireAuth(true);
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول للوصول إلى هذه الصفحة', 401);
}

// الحصول على session token الحالي
$headers = getallheaders();
$currentSessionToken = $headers['Authorization'] ?? $headers['authorization'] ?? null;
if ($currentSessionToken) {
    $currentSessionToken = str_replace('Bearer ', '', $currentSessionToken);
}
if (!$currentSessionToken) {
    $currentSessionToken = $_COOKIE['session_token'] ?? null;
}
$currentSessionToken = sanitizeInput($currentSessionToken ?? '');

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
}

try {
    // جلب جميع الجلسات النشطة للمستخدم مع معلومات آخر تسجيل دخول
    $stmt = $pdo->prepare("
        SELECT 
            us.id,
            us.session_token,
            us.ip_address,
            us.user_agent,
            us.expires_at,
            us.created_at,
            u.last_login_at
        FROM user_sessions us
        INNER JOIN users u ON us.user_id = u.id
        WHERE us.user_id = :user_id
        AND us.expires_at > NOW()
        ORDER BY us.created_at DESC
    ");
    
    $stmt->execute(['user_id' => $user['id']]);
    $sessions = $stmt->fetchAll();
    
    // تحليل كل جلسة وإضافة معلومات الجهاز
    $devices = [];
    foreach ($sessions as $session) {
        $deviceInfo = parseUserAgent($session['user_agent'] ?? '');
        $isCurrentDevice = ($session['session_token'] === $currentSessionToken);
        
        // استخدام created_at كوقت آخر تسجيل دخول لهذا الجهاز المحدد
        // لأن created_at يمثل وقت إنشاء الجلسة (آخر تسجيل دخول من هذا الجهاز)
        $lastLogin = $session['created_at'];
        
        $devices[] = [
            'session_id' => (int)$session['id'],
            'session_token' => substr($session['session_token'], 0, 16) . '...', // إخفاء جزء من التوكن
            'device_name' => $deviceInfo['device_name'],
            'device_type' => $deviceInfo['device_type'], // desktop, mobile, tablet
            'browser_name' => $deviceInfo['browser_name'],
            'browser_version' => $deviceInfo['browser_version'],
            'os_name' => $deviceInfo['os_name'],
            'os_version' => $deviceInfo['os_version'],
            'ip_address' => $session['ip_address'],
            'is_current_device' => $isCurrentDevice,
            'last_login' => $lastLogin, // تغيير الاسم من last_active إلى last_login
            'expires_at' => $session['expires_at']
        ];
    }
    
    sendJsonResponse(true, ['devices' => $devices], null, 200);
    
} catch (PDOException $e) {
    error_log("Get Devices Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء جلب الأجهزة', 500);
}

/**
 * تحليل User Agent للحصول على معلومات الجهاز والمتصفح
 * @param string $userAgent
 * @return array
 */
function parseUserAgent($userAgent) {
    if (empty($userAgent)) {
        return [
            'device_name' => 'جهاز غير معروف',
            'device_type' => 'unknown',
            'browser_name' => 'متصفح غير معروف',
            'browser_version' => '',
            'os_name' => 'نظام تشغيل غير معروف',
            'os_version' => ''
        ];
    }
    
    $deviceType = 'desktop';
    $deviceName = 'جهاز كمبيوتر';
    $browserName = 'متصفح غير معروف';
    $browserVersion = '';
    $osName = 'نظام تشغيل غير معروف';
    $osVersion = '';
    
    // تحديد نوع الجهاز - يجب التحقق من iPad أولاً قبل Mobile
    if (preg_match('/iPad/i', $userAgent)) {
        $deviceType = 'tablet';
        $deviceName = 'iPad';
    } elseif (preg_match('/Android/i', $userAgent)) {
        // التحقق من أن Android ليس tablet
        if (preg_match('/Mobile/i', $userAgent)) {
            $deviceType = 'mobile';
            $deviceName = 'هاتف Android';
        } else {
            $deviceType = 'tablet';
            $deviceName = 'جهاز لوحي Android';
        }
    } elseif (preg_match('/iPhone|iPod/i', $userAgent)) {
        $deviceType = 'mobile';
        if (preg_match('/iPhone/i', $userAgent)) {
            $deviceName = 'iPhone';
        } else {
            $deviceName = 'iPod';
        }
    } elseif (preg_match('/Mobile|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
        $deviceType = 'mobile';
        $deviceName = 'هاتف محمول';
    }
    
    // تحديد نظام التشغيل - يجب التحقق من iPadOS أولاً
    if (preg_match('/OS ([0-9_]+).*like Mac/i', $userAgent, $matches) && preg_match('/iPad/i', $userAgent)) {
        $osName = 'iPadOS';
        $osVersion = str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/iPhone OS ([0-9_]+)|OS ([0-9_]+).*like Mac/i', $userAgent, $matches)) {
        $osName = 'iOS';
        $osVersion = str_replace('_', '.', $matches[1] ?? $matches[2] ?? '');
    } elseif (preg_match('/Android ([0-9.]+)/i', $userAgent, $matches)) {
        $osName = 'Android';
        $osVersion = $matches[1];
    } elseif (preg_match('/Windows NT ([0-9.]+)/i', $userAgent, $matches)) {
        $osName = 'Windows';
        $osVersion = $matches[1];
        if ($osVersion === '10.0' || $osVersion === '11.0') {
            $osVersion = str_replace('.0', '', $osVersion);
        } elseif ($osVersion === '6.3') {
            $osVersion = '8.1';
        } elseif ($osVersion === '6.2') {
            $osVersion = '8';
        } elseif ($osVersion === '6.1') {
            $osVersion = '7';
        }
    } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $userAgent, $matches)) {
        $osName = 'macOS';
        $osVersion = str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $osName = 'Linux';
        // محاولة تحديد التوزيعة
        if (preg_match('/Ubuntu/i', $userAgent)) {
            $osName = 'Ubuntu';
        } elseif (preg_match('/Fedora/i', $userAgent)) {
            $osName = 'Fedora';
        }
    }
    
    // تحديد المتصفح - يجب التحقق من Edge و Opera قبل Chrome
    if (preg_match('/Edg\/([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Edge';
        $browserVersion = $matches[1];
    } elseif (preg_match('/OPR\/([0-9.]+)|Opera\/([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Opera';
        $browserVersion = $matches[1] ?? $matches[2] ?? '';
    } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Firefox';
        $browserVersion = $matches[1];
    } elseif (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches) && !preg_match('/Edg|OPR|Opera/i', $userAgent)) {
        $browserName = 'Chrome';
        $browserVersion = $matches[1];
    } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches) && !preg_match('/Chrome|CriOS|FxiOS/i', $userAgent)) {
        $browserName = 'Safari';
        $browserVersion = $matches[1];
    } elseif (preg_match('/MSIE ([0-9.]+)|Trident.*rv:([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Internet Explorer';
        $browserVersion = $matches[1] ?? $matches[2] ?? '';
    } elseif (preg_match('/CriOS\/([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Chrome';
        $browserVersion = $matches[1];
        $deviceType = 'mobile';
    } elseif (preg_match('/FxiOS\/([0-9.]+)/i', $userAgent, $matches)) {
        $browserName = 'Firefox';
        $browserVersion = $matches[1];
        $deviceType = 'mobile';
    }
    
    // تحسين اسم الجهاز بناءً على المعلومات
    if ($deviceType === 'desktop') {
        if ($osName === 'Windows') {
            $deviceName = 'كمبيوتر Windows' . ($osVersion ? ' ' . $osVersion : '');
        } elseif ($osName === 'macOS') {
            $deviceName = 'Mac' . ($osVersion ? ' (' . $osVersion . ')' : '');
        } elseif ($osName === 'Linux' || $osName === 'Ubuntu' || $osName === 'Fedora') {
            $deviceName = 'كمبيوتر ' . $osName;
        }
    } elseif ($deviceType === 'mobile') {
        if ($osName === 'iOS' && preg_match('/iPhone/i', $userAgent)) {
            $deviceName = 'iPhone' . ($osVersion ? ' (iOS ' . $osVersion . ')' : '');
        } elseif ($osName === 'Android') {
            $deviceName = 'هاتف Android' . ($osVersion ? ' ' . $osVersion : '');
        }
    } elseif ($deviceType === 'tablet') {
        if (preg_match('/iPad/i', $userAgent)) {
            $deviceName = 'iPad' . ($osVersion ? ' (iPadOS ' . $osVersion . ')' : '');
        } elseif ($osName === 'Android') {
            $deviceName = 'جهاز لوحي Android' . ($osVersion ? ' ' . $osVersion : '');
        }
    }
    
    return [
        'device_name' => $deviceName,
        'device_type' => $deviceType,
        'browser_name' => $browserName,
        'browser_version' => $browserVersion,
        'os_name' => $osName,
        'os_version' => $osVersion
    ];
}
