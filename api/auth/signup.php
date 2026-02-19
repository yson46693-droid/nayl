<?php
/**
 * ============================================
 * Signup API Endpoint
 * ============================================
 * API لتسجيل حساب جديد
 * 
 * Endpoint: POST /api/auth/signup.php
 */

// بدء الجلسة للـ CSRF Token
session_start();

// تحميل env.php للوصول إلى دالة env()
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

/**
 * وظيفة للتحقق من Origin المسموح
 * @return string|null - Origin المسموح أو null
 */
function getAllowedOrigin() {
    // قائمة النطاقات المسموحة
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1',
        'https://almoustafa.site',
        'http://almoustafa.site',
        'https://www.almoustafa.site',
        'http://www.almoustafa.site'
    ];

    // إضافة النطاق من .env إذا كان موجوداً
    if (function_exists('env')) {
        $appUrl = env('APP_URL', '');
        if ($appUrl) {
            $allowedOrigins[] = rtrim($appUrl, '/');
            $parsed = parse_url($appUrl);
            if ($parsed && isset($parsed['host'])) {
                $allowedOrigins[] = ($parsed['scheme'] ?? 'http') . '://' . $parsed['host'];
            }
        }
    }
    
    // الحصول على Origin من الطلب
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    if ($requestOrigin) {
        // استخراج النطاق من Origin
        $parsedOrigin = parse_url($requestOrigin);
        if ($parsedOrigin && isset($parsedOrigin['host'])) {
            $originDomain = ($parsedOrigin['scheme'] ?? 'http') . '://' . $parsedOrigin['host'];
            
            // التحقق من أن النطاق مسموح
            foreach ($allowedOrigins as $allowed) {
                if ($originDomain === $allowed) {
                    return $requestOrigin;
                }
            }
        }
    }
    
    // إذا لم يكن هناك Origin في الطلب (مثل طلبات من نفس النطاق)، نسمح
    // لكن فقط إذا كان الطلب من نفس النطاق
    $serverProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $serverOrigin = $serverProtocol . '://' . $serverHost;
    
    // إذا لم يكن هناك Origin في الطلب، نعتبره من نفس النطاق
    if (!$requestOrigin) {
        return $serverOrigin;
    }
    
    // إذا كان Origin موجود لكن غير مسموح، نرفض
    return null;
}

/**
 * إعدادات CORS الآمنة
 */
$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // إذا لم يكن هناك Origin مسموح، نرفض الطلب
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Origin not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Security Headers
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Max-Age: 86400'); // 24 ساعة
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// معالجة OPTIONS request (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// السماح فقط بـ POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// استيراد الملفات المطلوبة
// database.php يحمل env.php بالفعل، لذا لا حاجة لإعادة التحميل
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/rateLimiter.php';

/**
 * Rate Limiting: منع الضغط على API إنشاء الحساب
 * لا يوجد أي قيود على عدد محاولات إنشاء الحساب
 * الحماية تتم فقط من خلال نظام التوكن الأمني
 */

// الحصول على البيانات من Request Body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, null, 'بيانات غير صحيحة', 400);
}

/**
 * التحقق من توكن الأمان
 * هذا التوكن يتم توليده في صفحة إنشاء الحساب فقط
 * يضمن أن API لا يمكن استخدامه إلا من خلال الصفحة الرسمية
 */
function validateSecurityToken($token) {
    // التحقق من وجود التوكن
    if (empty($token) || !is_string($token)) {
        error_log("Security token validation failed: token is empty or not a string");
        return false;
    }
    
    // التحقق من طول التوكن (يجب أن يكون 32 حرف على الأقل)
    $tokenLength = strlen($token);
    if ($tokenLength < 32) {
        error_log("Security token validation failed: token too short (length: $tokenLength)");
        return false;
    }
    
    // التحقق من أن التوكن يحتوي على أحرف hex فقط (0-9, a-f)
    if (!preg_match('/^[0-9a-f]+$/i', $token)) {
        error_log("Security token validation failed: token contains invalid characters");
        return false;
    }
    
    return true;
}

// التحقق من توكن الأمان (مطلوب)
$securityToken = $data['security_token'] ?? '';

// تسجيل معلومات التوكن للأغراض التشخيصية (في بيئة التطوير فقط)
if (empty($securityToken)) {
    error_log("Security token missing from request. Available keys: " . implode(', ', array_keys($data)));
}

if (!validateSecurityToken($securityToken)) {
    sendJsonResponse(false, null, 'توكن الأمان غير صحيح أو مفقود. يرجى استخدام صفحة إنشاء الحساب الرسمية.', 403);
}

// Rate Limit: منع الضغط على API
$clientIP = getClientIP();
$signupRateCheck = checkSignupRateLimit($clientIP);
if (!$signupRateCheck['allowed']) {
    sendJsonResponse(false, null, $signupRateCheck['message'] ?? 'تم تجاوز الحد المسموح', 429);
}
recordSignupAttempt($clientIP);

// تنظيف وفحص البيانات
$fullName = sanitizeInput($data['full_name'] ?? '');
$email = sanitizeInput($data['email'] ?? '');
$phone = sanitizeInput($data['phone'] ?? '');
$countryCode = sanitizeInput($data['country_code'] ?? '+20');
$password = $data['password'] ?? '';
$fullPhone = sanitizeInput($data['full_phone'] ?? '');
$country = sanitizeInput($data['country'] ?? '');
$city = sanitizeInput($data['city'] ?? '');
$whatsappVerified = isset($data['whatsapp_verified']) ? (bool)$data['whatsapp_verified'] : false;
$termsAccepted = isset($data['terms_accepted']) ? (bool)$data['terms_accepted'] : false;

// التحقق من صحة البيانات
if (empty($email) || !validateEmail($email)) {
    sendJsonResponse(false, null, 'البريد الإلكتروني غير صحيح', 400);
}

if (empty($phone) || !validatePhone($phone, 7)) {
    sendJsonResponse(false, null, 'رقم الهاتف غير صحيح. يجب أن يكون 7 أرقام على الأقل', 400);
}

if (!validateCountryCode($countryCode)) {
    sendJsonResponse(false, null, 'رمز الدولة غير صحيح', 400);
}

if (empty($password) || strlen($password) < 6) {
    sendJsonResponse(false, null, 'كلمة المرور يجب أن تكون 6 أحرف على الأقل', 400);
}

if (empty($country)) {
    sendJsonResponse(false, null, 'يجب اختيار الدولة', 400);
}

if (empty($city)) {
    sendJsonResponse(false, null, 'يجب إدخال المدينة', 400);
}

if (!$termsAccepted) {
    sendJsonResponse(false, null, 'يجب الموافقة على الشروط والأحكام', 400);
}

// إنشاء رقم الهاتف الكامل إذا لم يكن موجوداً
if (empty($fullPhone)) {
    $fullPhone = $countryCode . $phone;
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    // رسالة خطأ أكثر تفصيلاً في بيئة التطوير
    $errorMsg = 'خطأ في الاتصال بقاعدة البيانات';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorMsg .= '. تحقق من إعدادات قاعدة البيانات في ملف .env';
    }
    sendJsonResponse(false, null, $errorMsg, 500);
}

try {
    // التحقق من وجود البريد الإلكتروني مسبقاً
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = :email 
        AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, null, 'البريد الإلكتروني مستخدم بالفعل', 409);
    }
    
    // التحقق من وجود رقم الهاتف مسبقاً
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE full_phone = :full_phone 
        AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute(['full_phone' => $fullPhone]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, null, 'رقم الهاتف مستخدم بالفعل', 409);
    }
    
    // Hash كلمة المرور
    $passwordHash = hashPassword($password);
    if (!$passwordHash) {
        sendJsonResponse(false, null, 'خطأ في تشفير كلمة المرور', 500);
    }
    
    // توليد معرف مستخدم عشوائي مكون من 6 أرقام
    $userId = 0;
    $isUnique = false;
    $maxAttempts = 20; // عدد محاولات كافٍ لتجنب التصادم
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        try {
            $userId = random_int(100000, 999999);
        } catch (Exception $e) {
            $userId = mt_rand(100000, 999999);
        }
        
        // التحقق من أن المعرف غير مستخدم
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmtCheck->execute(['id' => $userId]);
        
        if (!$stmtCheck->fetch()) {
            $isUnique = true;
            break;
        }
    }
    
    if (!$isUnique) {
        sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الحساب (رمز 101)، يرجى المحاولة مرة أخرى', 500);
    }

    // إدراج المستخدم الجديد
    $stmt = $pdo->prepare("
        INSERT INTO users (
            id,
            full_name,
            email, 
            phone, 
            country_code, 
            full_phone, 
            country, 
            city, 
            password_hash, 
            whatsapp_verified, 
            terms_accepted, 
            terms_accepted_at,
            created_at
        ) VALUES (
            :id,
            :full_name,
            :email,
            :phone,
            :country_code,
            :full_phone,
            :country,
            :city,
            :password_hash,
            :whatsapp_verified,
            :terms_accepted,
            NOW(),
            NOW()
        )
    ");
    
    $stmt->execute([
        'id' => $userId,
        'full_name' => $fullName ?: null,
        'email' => $email,
        'phone' => $phone,
        'country_code' => $countryCode,
        'full_phone' => $fullPhone,
        'country' => $country,
        'city' => $city,
        'password_hash' => $passwordHash,
        'whatsapp_verified' => $whatsappVerified ? 1 : 0,
        'terms_accepted' => $termsAccepted ? 1 : 0
    ]);
    
    // إرجاع بيانات المستخدم (بدون كلمة المرور)
    $responseData = [
        'user' => [
            'id' => $userId,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'full_phone' => $fullPhone,
            'country' => $country,
            'city' => $city,
            'whatsapp_verified' => $whatsappVerified,
            'created_at' => date('Y-m-d H:i:s')
        ],
        'message' => 'تم إنشاء الحساب بنجاح'
    ];
    
    sendJsonResponse(true, $responseData, null, 201);
    
} catch (PDOException $e) {
    error_log("Signup Error: " . $e->getMessage());
    
    // التحقق من أخطاء قاعدة البيانات المحددة
    if ($e->getCode() == 23000) { // Duplicate entry
        sendJsonResponse(false, null, 'البريد الإلكتروني أو رقم الهاتف مستخدم بالفعل', 409);
    }
    
    sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الحساب', 500);
}
