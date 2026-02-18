<?php
/**
 * ============================================
 * Login API Endpoint
 * ============================================
 * API لتسجيل الدخول
 * 
 * Endpoint: POST /api/auth/login.php
 * فحص وصول: GET /api/auth/login.php?ping=1
 */

// فحص وصول (للتأكد من أن الرابط يعمل على الاستضافة)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Login API is reachable', 'ping' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

// بدء الجلسة للـ CSRF Token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        'https://127.0.0.1'
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

    // السماح بنفس نطاق الخادم (مهم على الاستضافة مثل Hostinger عند عدم وجود APP_URL)
    $serverProtocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $serverProtocol = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $serverProtocol = 'https';
    }
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if ($serverHost !== '') {
        $allowedOrigins[] = $serverProtocol . '://' . $serverHost;
    }
    
    // الحصول على Origin من الطلب
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    if ($requestOrigin) {
        $parsedOrigin = parse_url($requestOrigin);
        if ($parsedOrigin && isset($parsedOrigin['host'])) {
            $originDomain = ($parsedOrigin['scheme'] ?? 'http') . '://' . $parsedOrigin['host'];
            $originNorm = rtrim($originDomain, '/');
            $originNormNoPort = preg_replace('#^(https?)://([^:/]+):(80|443)$#', '$1://$2', $originNorm);
            foreach ($allowedOrigins as $allowed) {
                $allowedNorm = rtrim($allowed, '/');
                if ($originNorm === $allowedNorm) {
                    return $requestOrigin;
                }
                $allowedNormNoPort = preg_replace('#^(https?)://([^:/]+):(80|443)$#', '$1://$2', $allowedNorm);
                if ($originNormNoPort === $allowedNormNoPort) {
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
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
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
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/security.php';
    require_once __DIR__ . '/../config/rateLimiter.php';
} catch (Exception $e) {
    error_log("Error loading config files: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل ملفات الإعدادات'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// الحصول على البيانات من Request Body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    sendJsonResponse(false, null, 'بيانات غير صحيحة', 400);
}

// تنظيف وفحص البيانات
$email = sanitizeInput($data['identifier'] ?? ''); // البريد الإلكتروني فقط
$password = $data['password'] ?? '';
$remember = isset($data['remember']) ? (bool)$data['remember'] : false;

// التحقق من صحة البيانات
if (empty($email)) {
    sendJsonResponse(false, null, 'يجب إدخال البريد الإلكتروني', 400);
}

if (empty($password)) {
    sendJsonResponse(false, null, 'يجب إدخال كلمة المرور', 400);
}

// التحقق من صحة البريد الإلكتروني
if (!validateEmail($email)) {
    sendJsonResponse(false, null, 'البريد الإلكتروني غير صحيح', 400);
}

// معرف الجهاز - مطلوب للحظر حسب الجهاز وليس IP
$deviceUuid = isset($data['device_uuid']) ? trim($data['device_uuid']) : (isset($data['deviceUuid']) ? trim($data['deviceUuid']) : '');
if (empty($deviceUuid)) {
    sendJsonResponse(false, null, 'معرف الجهاز مطلوب. يرجى إعادة تحميل الصفحة والمحاولة مجدداً.', 400);
}

// Rate Limit: التحقق قبل السماح بالمحاولة (حسب الجهاز - UUID من localStorage/cookies)
$clientIP = getClientIP();
$rateCheck = checkLoginRateLimit($deviceUuid);
if (!$rateCheck['allowed']) {
    sendJsonResponse(false, null, $rateCheck['message'] ?? 'تم تجاوز الحد المسموح', 429);
}

// الاتصال بقاعدة البيانات
$pdo = getDatabaseConnection();
if (!$pdo) {
    $errorMsg = 'خطأ في الاتصال بقاعدة البيانات';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorMsg .= '. تحقق من إعدادات قاعدة البيانات في ملف .env';
    }
    sendJsonResponse(false, null, $errorMsg, 500);
}

try {
    // البحث عن المستخدم بالبريد الإلكتروني فقط
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            full_name,
            email, 
            phone, 
            country_code, 
            full_phone, 
            country, 
            city, 
            password_hash, 
            is_active, 
            is_verified, 
            whatsapp_verified,
            created_at,
            last_login_at
        FROM users 
        WHERE email = :email 
        AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب account_type إن وُجد العمود (متوافق مع قواعد البيانات قبل وبعد الـ migration)
    $user['account_type'] = 'free';
    try {
        $atStmt = $pdo->prepare("SELECT COALESCE(account_type, 'free') AS account_type FROM users WHERE id = ? LIMIT 1");
        $atStmt->execute([$user['id']]);
        $row = $atStmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['account_type'])) {
            $user['account_type'] = $row['account_type'];
        }
    } catch (PDOException $e) {
        // العمود غير موجود بعد - نبقى على 'free'
    }
    // إذا كان الحساب مجاني، نتحقق من وجود اشتراك فعال في أي كورس → نعتبره VIP
    if (($user['account_type'] ?? 'free') === 'free' && !empty($user['id'])) {
        try {
            $subStmt = $pdo->prepare("SELECT 1 FROM user_course_subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1");
            $subStmt->execute([$user['id']]);
            if ($subStmt->fetch()) {
                $user['account_type'] = 'vip';
            }
        } catch (PDOException $e) {
            // تجاهل
        }
    }
    
    // التحقق من وجود المستخدم
    if (!$user || empty($user)) {
        recordLoginAttempt($deviceUuid, $email, $clientIP);
        sendJsonResponse(false, null, 'البريد الإلكتروني غير مسجل', 404);
    }
    
    // التحقق من وجود password_hash
    if (empty($user['password_hash'])) {
        error_log("User found but password_hash is empty for email: " . $email);
        sendJsonResponse(false, null, 'خطأ في بيانات المستخدم', 500);
    }
    
    // التحقق من حالة الحساب
    if (!isset($user['is_active']) || !$user['is_active']) {
        sendJsonResponse(false, null, 'تم تعطيل حسابك. يرجى التواصل مع الدعم', 403);
    }
    
    // التحقق من كلمة المرور
    if (!verifyPassword($password, $user['password_hash'])) {
        recordLoginAttempt($deviceUuid, $email, $clientIP);
        sendJsonResponse(false, null, 'كلمة المرور غير صحيحة', 401);
    }

    // تسجيل الدخول نجح - مسح محاولات الفشل لهذا الجهاز
    clearLoginAttempts($deviceUuid);
    
    // تحديث آخر تاريخ دخول
    try {
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET last_login_at = NOW() 
            WHERE id = :user_id
        ");
        $updateStmt->execute(['user_id' => $user['id']]);
    } catch (PDOException $e) {
        error_log("Error updating last_login_at: " . $e->getMessage());
        // لا نوقف العملية، فقط نسجل الخطأ
    }
    
    // إنشاء session token
    try {
        $sessionToken = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        error_log("Error generating session token: " . $e->getMessage());
        sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الجلسة', 500);
    }
    
    $expiresAt = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+1 day'));
    
    // حفظ الجلسة في قاعدة البيانات
    $sessionStmt = $pdo->prepare("
        INSERT INTO user_sessions (
            user_id, 
            session_token, 
            ip_address, 
            user_agent, 
            expires_at
        ) VALUES (
            :user_id,
            :session_token,
            :ip_address,
            :user_agent,
            :expires_at
        )
    ");
    
    try {
        $sessionStmt->execute([
            'user_id' => $user['id'],
            'session_token' => $sessionToken,
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => $expiresAt
        ]);
        
        // التحقق من نجاح إدراج الجلسة
        if ($sessionStmt->rowCount() === 0) {
            error_log("Failed to insert session for user_id: " . $user['id']);
            sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الجلسة', 500);
        }
        
        // التحقق من أن الجلسة تم حفظها بشكل صحيح
        $verifyStmt = $pdo->prepare("SELECT id FROM user_sessions WHERE session_token = :token LIMIT 1");
        $verifyStmt->execute(['token' => $sessionToken]);
        $savedSession = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$savedSession) {
            error_log("Session was not saved correctly. Token: " . substr($sessionToken, 0, 16) . "...");
            sendJsonResponse(false, null, 'حدث خطأ أثناء حفظ الجلسة', 500);
        }
        
        // حفظ session token كـ cookie أيضاً لضمان الوصول
        // يجب أن يكون قبل إرسال أي output
        $cookieExpires = $remember ? time() + (30 * 24 * 60 * 60) : time() + (24 * 60 * 60);
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        
        // استخدام syntax متوافق مع جميع إصدارات PHP
        if (PHP_VERSION_ID >= 70300) {
            // PHP 7.3+ - استخدام array syntax
            @setcookie('session_token', $sessionToken, [
                'expires' => $cookieExpires,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            // PHP < 7.3 - استخدام المعاملات المنفصلة
            @setcookie('session_token', $sessionToken, $cookieExpires, '/', '', $isSecure, true);
        }
        
    } catch (PDOException $e) {
        error_log("Session Insert Error: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        sendJsonResponse(false, null, 'حدث خطأ أثناء إنشاء الجلسة', 500);
    }
    
    // إرجاع بيانات المستخدم (بدون كلمة المرور)
    $responseData = [
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'full_phone' => $user['full_phone'],
            'country' => $user['country'],
            'city' => $user['city'],
            'is_verified' => (bool)$user['is_verified'],
            'whatsapp_verified' => (bool)$user['whatsapp_verified'],
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
    
} catch (PDOException $e) {
    error_log("Login PDO Error: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    error_log("SQL State: " . $e->getCode());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تسجيل الدخول', 500);
} catch (Exception $e) {
    error_log("Login General Error: " . $e->getMessage());
    error_log("Error File: " . $e->getFile() . " Line: " . $e->getLine());
    sendJsonResponse(false, null, 'حدث خطأ غير متوقع أثناء تسجيل الدخول', 500);
}
