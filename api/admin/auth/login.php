<?php
/**
 * ============================================
 * Admin Login API Endpoint
 * ============================================
 * API لتسجيل دخول المشرفين
 * 
 * Endpoint: POST /api/admin/auth/login.php
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل الإعدادات
require_once __DIR__ . '/../../config/env.php';
loadEnv(__DIR__ . '/../../.env');

// دالة التحقق من Origin (مشتركة)
function getAdminAllowedOrigin() {
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

// CORS Headers
$allowedOrigin = getAdminAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Origin not allowed']);
    exit;
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/security.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error']);
    exit;
}

// قراءة البيانات
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$username = sanitizeInput($data['username'] ?? '');
$password = $data['password'] ?? '';
$remember = isset($data['remember']) ? (bool)$data['remember'] : false;

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال اسم المستخدم وكلمة المرور']);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}

try {
    // البحث في جدول admins
    $stmt = $pdo->prepare("
        SELECT * FROM admins 
        WHERE username = :username 
        AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || !verifyPassword($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'بيانات الدخول غير صحيحة']);
        exit;
    }
    
    // تحديث آخر دخول
    $updateStmt = $pdo->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = :id");
    $updateStmt->execute(['id' => $admin['id']]);
    
    // إنشاء جلسة جديدة
    $token = bin2hex(random_bytes(32));
    $expiresAt = $remember ? date('Y-m-d H:i:s', strtotime('+7 days')) : date('Y-m-d H:i:s', strtotime('+12 hours'));
    $clientIP = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // محاولة INSERT مع الأعمدة الكاملة (last_activity_at, last_rotated_at)
    try {
        $sessionStmt = $pdo->prepare("
            INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at, last_activity_at, last_rotated_at)
            VALUES (:id, :token, :ip, :ua, :expires, NOW(), NOW())
        ");
        $sessionStmt->execute([
            'id' => $admin['id'],
            'token' => $token,
            'ip' => $clientIP,
            'ua' => $userAgent,
            'expires' => $expiresAt
        ]);
    } catch (PDOException $e) {
        // في حال عدم وجود الأعمدة، استخدم INSERT مبسط
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $sessionStmt = $pdo->prepare("
                INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at)
                VALUES (:id, :token, :ip, :ua, :expires)
            ");
            $sessionStmt->execute([
                'id' => $admin['id'],
                'token' => $token,
                'ip' => $clientIP,
                'ua' => $userAgent,
                'expires' => $expiresAt
            ]);
        } else {
            throw $e;
        }
    }
    
    // حفظ الـ Cookie باسم مختلف (admin_session_token)
    $cookieExpires = strtotime($expiresAt);
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    
    // استخدام setcookie المتوافق
    if (PHP_VERSION_ID >= 70300) {
        setcookie('admin_session_token', $token, [
            'expires' => $cookieExpires,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie('admin_session_token', $token, $cookieExpires, '/', '', $isSecure, false);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'token' => $token,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name'],
            'role' => $admin['role']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    error_log("Admin Login Trace: " . $e->getTraceAsString());
    http_response_code(500);
    $errorMsg = 'حدث خطأ غير متوقع';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $errorMsg .= ': ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'error' => $errorMsg]);
}
