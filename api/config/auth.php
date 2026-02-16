<?php
/**
 * ============================================
 * Authentication Middleware
 * ============================================
 * ملف للتحقق من تسجيل الدخول وحماية APIs
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// استيراد الملفات المطلوبة
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

/**
 * التحقق من session token والتحقق من صحة المستخدم
 * @param string|null $sessionToken - Session token من header أو cookie
 * @return array|null - بيانات المستخدم أو null إذا لم يكن مسجل دخول
 */
function verifySession($sessionToken = null) {
    // الحصول على token من header أو cookie
    if (!$sessionToken) {
        // محاولة الحصول من Authorization header
        // دالة محسّنة للحصول على headers تعمل في جميع البيئات
        $headers = [];
        
        // محاولة استخدام getallheaders() إذا كان متاحاً
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback: الحصول من $_SERVER
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        // البحث عن Authorization header (case-insensitive)
        $sessionToken = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $sessionToken = $value;
                break;
            }
        }
        
        // إذا لم يوجد في headers، جرب من $_SERVER مباشرة
        if (!$sessionToken) {
            // محاولة من HTTP_AUTHORIZATION
            $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            
            // إذا لم يوجد، جرب REDIRECT_HTTP_AUTHORIZATION (لـ Apache mod_rewrite)
            if (!$sessionToken) {
                $sessionToken = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            }
            
            // إذا لم يوجد، جرب من apache_request_headers() إذا كان متاحاً
            if (!$sessionToken && function_exists('apache_request_headers')) {
                $apacheHeaders = apache_request_headers();
                foreach ($apacheHeaders as $key => $value) {
                    if (strtolower($key) === 'authorization') {
                        $sessionToken = $value;
                        break;
                    }
                }
            }
        }
        
        if ($sessionToken) {
            // إزالة "Bearer " إذا كان موجوداً
            $sessionToken = preg_replace('/^Bearer\s+/i', '', trim($sessionToken));
        }
        
        // إذا لم يوجد في header، جرب من cookie
        if (!$sessionToken) {
            $sessionToken = $_COOKIE['session_token'] ?? null;
        }
        
        // إذا لم يوجد في cookie، جرب من $_POST أو $_GET (للتوافق)
        if (!$sessionToken) {
            $sessionToken = $_POST['session_token'] ?? $_GET['session_token'] ?? null;
        }
    }
    
    if (!$sessionToken || empty(trim($sessionToken))) {
        return null;
    }
    
    // تنظيف token
    $sessionToken = sanitizeInput(trim($sessionToken));
    
    // الاتصال بقاعدة البيانات
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in verifySession");
        return null;
    }
    
    try {
        // تسجيل محاولة التحقق (للتتبع)
        error_log("Verifying session token: " . substr($sessionToken, 0, 16) . "...");
        // البحث عن الجلسة في قاعدة البيانات
        // استخدام PDO::FETCH_ASSOC للحصول على array بدلاً من object
        $stmt = $pdo->prepare("
            SELECT 
                us.id,
                us.user_id,
                us.session_token,
                us.ip_address,
                us.user_agent,
                us.expires_at,
                us.created_at,
                u.id as user_id,
                u.full_name,
                u.email,
                u.phone,
                u.full_phone,
                u.country,
                u.city,
                u.is_active,
                u.is_verified,
                u.whatsapp_verified,
                u.created_at as user_created_at
            FROM user_sessions us
            INNER JOIN users u ON us.user_id = u.id
            WHERE us.session_token = :token
            AND us.expires_at > NOW()
            AND u.deleted_at IS NULL
            AND u.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute(['token' => $sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // جلب account_type إن وُجد العمود (متوافق مع قواعد البيانات قبل وبعد الـ migration)
        if ($session && isset($session['user_id'])) {
            $session['account_type'] = 'free';
            try {
                $atStmt = $pdo->prepare("SELECT COALESCE(account_type, 'free') AS account_type FROM users WHERE id = ? LIMIT 1");
                $atStmt->execute([$session['user_id']]);
                $row = $atStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['account_type'])) {
                    $session['account_type'] = $row['account_type'];
                }
            } catch (PDOException $e) {
                // العمود غير موجود بعد - نبقى على 'free'
            }
            // إذا كان الحساب مجاني، نتحقق من وجود اشتراك فعال في أي كورس → نعتبره VIP
            if (($session['account_type'] ?? 'free') === 'free') {
                try {
                    $subStmt = $pdo->prepare("SELECT 1 FROM user_course_subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1");
                    $subStmt->execute([$session['user_id']]);
                    if ($subStmt->fetch()) {
                        $session['account_type'] = 'vip';
                    }
                } catch (PDOException $e) {
                    // تجاهل
                }
            }
        }
        
        if (!$session || empty($session)) {
            // تسجيل تفاصيل أكثر للمساعدة في التصحيح
            error_log("Session verification failed - Token: " . substr($sessionToken, 0, 16) . "...");
            
            // التحقق من وجود الجلسة منتهية الصلاحية أو غير نشطة
            $expiredStmt = $pdo->prepare("
                SELECT 
                    us.id, 
                    us.expires_at,
                    us.user_id,
                    u.is_active,
                    u.deleted_at
                FROM user_sessions us
                LEFT JOIN users u ON us.user_id = u.id
                WHERE us.session_token = :token 
                LIMIT 1
            ");
            $expiredStmt->execute(['token' => $sessionToken]);
            $expiredSession = $expiredStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($expiredSession) {
                $now = date('Y-m-d H:i:s');
                if ($expiredSession['expires_at'] < $now) {
                    error_log("Session expired. Expires at: " . $expiredSession['expires_at'] . ", Now: " . $now);
                } elseif (!$expiredSession['is_active']) {
                    error_log("User account is inactive for session token");
                } elseif ($expiredSession['deleted_at']) {
                    error_log("User account is deleted for session token");
                } else {
                    error_log("Session exists but failed verification for unknown reason");
                }
            } else {
                error_log("Session token not found in database");
            }
            
            return null;
        }
        
        // تسجيل نجاح التحقق
        error_log("Session verified successfully for user_id: " . $session['user_id']);
        
        // التحقق من أن البيانات موجودة
        if (!isset($session['user_id']) || !isset($session['email'])) {
            error_log("Session data incomplete: " . json_encode(array_keys($session)));
            return null;
        }
        
        // التحقق من IP address (اختياري - يمكن تعطيله للتنقل)
        // $clientIP = getClientIP();
        // if ($session['ip_address'] !== $clientIP) {
        //     // يمكن السماح أو رفض حسب الحاجة
        // }
        
        // إرجاع بيانات المستخدم
        $userData = [
            'id' => (int)$session['user_id'], // معرف المستخدم (للتوافق مع APIs الأخرى)
            'user_id' => (int)$session['user_id'], // للتوافق مع الكود القديم
            'full_name' => $session['full_name'] ?? null,
            'email' => $session['email'],
            'phone' => $session['phone'] ?? null,
            'full_phone' => $session['full_phone'] ?? null,
            'country' => $session['country'] ?? null,
            'city' => $session['city'] ?? null,
            'is_verified' => (bool)($session['is_verified'] ?? false),
            'whatsapp_verified' => (bool)($session['whatsapp_verified'] ?? false),
            'account_type' => $session['account_type'] ?? 'free',
            'session_token' => $sessionToken,
            'session_id' => (int)$session['id']
        ];
        
        return $userData;
        
    } catch (PDOException $e) {
        error_log("Session Verification Error: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("Token used: " . substr($sessionToken, 0, 16) . "...");
        return null;
    }
}

/**
 * التحقق من تسجيل الدخول (للمستخدمين) - Middleware function
 * يجب استدعاء هذه الدالة في بداية أي API endpoint يتطلب تسجيل الدخول
 * @param bool $returnUser - إذا كان true، ترجع بيانات المستخدم بدلاً من إرسال response
 * @return array|null - بيانات المستخدم أو null
 */
function requireAuth($returnUser = false) {
    $user = verifySession();
    
    if (!$user) {
        if ($returnUser) {
            return null;
        }
        
        // إعدادات CORS
        $allowedOrigin = getAllowedOrigin();
        if ($allowedOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Access-Control-Allow-Credentials: true');
        }
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة',
            'code' => 'UNAUTHORIZED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return $user;
}

/**
 * التحقق من session token الخاص بلوحة التحكم (المشرفين)
 * @param string|null $sessionToken
 * @return array|null - بيانات الأدمن أو null إذا لم تكن الجلسة صالحة
 */
function verifyAdminSession($sessionToken = null) {
    // الحصول على التوكن من الهيدر أو الكوكي أو الطلب
    if (!$sessionToken) {
        // محاولة من Authorization header
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $sessionToken = $value;
                break;
            }
        }
        
        // إذا لم يوجد في الهيدر، جرب متغيرات الخادم الشائعة
        if (!$sessionToken) {
            $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }
        
        // إزالة Bearer إن وُجد
        if ($sessionToken) {
            $sessionToken = preg_replace('/^Bearer\s+/i', '', trim($sessionToken));
        }
        
        // إذا لم يوجد، جرب من الكوكي
        if (!$sessionToken) {
            $sessionToken = $_COOKIE['admin_session_token'] ?? null;
        }
        
        // أخيراً من POST/GET (للتماشي مع أي استدعاءات يدوية)
        if (!$sessionToken) {
            $sessionToken = $_POST['admin_session_token'] ?? $_GET['admin_session_token'] ?? null;
        }
    }
    
    if (!$sessionToken || empty(trim($sessionToken))) {
        return null;
    }
    
    $sessionToken = sanitizeInput(trim($sessionToken));
    
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log('Database connection failed in verifyAdminSession');
        return null;
    }
    
    try {
        // مهلة الخمول: 30 دقيقة - بعدها تُحذف الجلسة
        $idleTimeoutMinutes = 30;
        // تجديد التوكن كل 15 دقيقة لتقليل خطر سرقة الجلسة
        $rotationIntervalMinutes = 15;

        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.admin_id,
                s.session_token,
                s.ip_address,
                s.user_agent,
                s.expires_at,
                s.created_at,
                s.last_activity_at,
                s.last_rotated_at,
                a.username,
                a.full_name,
                a.email,
                a.role,
                a.is_active
            FROM admin_sessions s
            INNER JOIN admins a ON s.admin_id = a.id
            WHERE s.session_token = :token
              AND s.expires_at > NOW()
              AND a.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute(['token' => $sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || empty($session)) {
            // محاولة معرفة إن كانت الجلسة موجودة ولكن منتهية أو الأدمن غير نشط
            $expiredStmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.expires_at,
                    s.admin_id,
                    a.is_active
                FROM admin_sessions s
                LEFT JOIN admins a ON s.admin_id = a.id
                WHERE s.session_token = :token
                LIMIT 1
            ");
            $expiredStmt->execute(['token' => $sessionToken]);
            $expired = $expiredStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($expired) {
                $now = date('Y-m-d H:i:s');
                if (!empty($expired['expires_at']) && $expired['expires_at'] < $now) {
                    error_log('Admin session expired for admin_id: ' . $expired['admin_id']);
                } elseif (isset($expired['is_active']) && !$expired['is_active']) {
                    error_log('Admin account inactive for admin_id: ' . $expired['admin_id']);
                }
            }
            
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $sessionId = (int)$session['id'];
        $lastActivity = $session['last_activity_at'] ?? $session['created_at'];
        $lastRotated = $session['last_rotated_at'] ?? $session['created_at'];

        // حذف الجلسة إذا انقضت مدة الخمول (30 دقيقة بدون نشاط)
        $idleLimit = date('Y-m-d H:i:s', strtotime("-{$idleTimeoutMinutes} minutes"));
        if ($lastActivity < $idleLimit) {
            $delStmt = $pdo->prepare("DELETE FROM admin_sessions WHERE id = :id");
            $delStmt->execute(['id' => $sessionId]);
            error_log('Admin session removed due to idle timeout (admin_id: ' . $session['admin_id'] . ')');
            return null;
        }

        // تحديث وقت آخر نشاط
        $updActivity = $pdo->prepare("UPDATE admin_sessions SET last_activity_at = NOW() WHERE id = :id");
        $updActivity->execute(['id' => $sessionId]);

        $effectiveToken = $sessionToken;
        $newToken = null;

        // تجديد التوكن كل 15 دقيقة (تقليل خطر سرقة الجلسة)
        $rotationLimit = date('Y-m-d H:i:s', strtotime("-{$rotationIntervalMinutes} minutes"));
        if ($lastRotated < $rotationLimit) {
            $newToken = bin2hex(random_bytes(32));
            $rotStmt = $pdo->prepare("
                UPDATE admin_sessions 
                SET session_token = :new_token, last_activity_at = NOW(), last_rotated_at = NOW() 
                WHERE id = :id
            ");
            $rotStmt->execute(['new_token' => $newToken, 'id' => $sessionId]);
            $effectiveToken = $newToken;
        }
        
        $adminData = [
            'id' => (int)$session['admin_id'],
            'admin_id' => (int)$session['admin_id'],
            'username' => $session['username'],
            'full_name' => $session['full_name'],
            'email' => $session['email'],
            'role' => $session['role'],
            'session_token' => $effectiveToken,
            'session_id' => $sessionId
        ];
        if ($newToken !== null) {
            $adminData['new_token'] = $newToken;
        }
        return $adminData;
        
    } catch (PDOException $e) {
        // إن كانت الأعمدة الجديدة غير موجودة (قبل الترحيل) نجرب استعلاماً بدونها
        if (strpos($e->getMessage(), 'last_activity_at') !== false || strpos($e->getMessage(), 'last_rotated_at') !== false) {
            try {
                $legacy = $pdo->prepare("
                    SELECT s.id, s.admin_id, s.session_token, s.expires_at, s.created_at,
                           a.username, a.full_name, a.email, a.role, a.is_active
                    FROM admin_sessions s
                    INNER JOIN admins a ON s.admin_id = a.id
                    WHERE s.session_token = :token AND s.expires_at > NOW() AND a.is_active = 1
                    LIMIT 1
                ");
                $legacy->execute(['token' => $sessionToken]);
                $session = $legacy->fetch(PDO::FETCH_ASSOC);
                if ($session && !empty($session)) {
                    return [
                        'id' => (int)$session['admin_id'],
                        'admin_id' => (int)$session['admin_id'],
                        'username' => $session['username'],
                        'full_name' => $session['full_name'],
                        'email' => $session['email'],
                        'role' => $session['role'],
                        'session_token' => $sessionToken,
                        'session_id' => (int)$session['id']
                    ];
                }
            } catch (PDOException $e2) {
                error_log('Admin Session Verification (legacy) Error: ' . $e2->getMessage());
            }
        }
        error_log('Admin Session Verification Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * التحقق من صلاحيات الأدمن - Middleware
 * @param bool $returnAdmin - إذا كان true ترجع بيانات الأدمن بدلاً من إرسال response
 * @return array|null
 */
function requireAdminAuth($returnAdmin = false) {
    $admin = verifyAdminSession();
    
    if (!$admin) {
        if ($returnAdmin) {
            return null;
        }
        
        $allowedOrigin = getAllowedOrigin();
        if ($allowedOrigin) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Access-Control-Allow-Credentials: true');
        }
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'يجب تسجيل الدخول كمسؤول للوصول إلى هذه الصفحة',
            'code' => 'ADMIN_UNAUTHORIZED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return $admin;
}

/**
 * الحصول على Origin المسموح (نفس الدالة في login.php)
 * @return string|null
 */
function getAllowedOrigin() {
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1'
    ];
    
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
    
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
    
    if ($requestOrigin) {
        $parsedOrigin = parse_url($requestOrigin);
        if ($parsedOrigin && isset($parsedOrigin['host'])) {
            $originDomain = ($parsedOrigin['scheme'] ?? 'http') . '://' . $parsedOrigin['host'];
            
            foreach ($allowedOrigins as $allowed) {
                if ($originDomain === $allowed) {
                    return $requestOrigin;
                }
            }
        }
    }
    
    $serverProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $serverOrigin = $serverProtocol . '://' . $serverHost;
    
    if (!$requestOrigin) {
        return $serverOrigin;
    }
    
    return null;
}
