<?php
/**
 * API تسجيل WebAuthn - نظام جديد مبسط
 */

define('ACCESS_ALLOWED', true);

// ✅ إصلاح: بدء الجلسة قبل تحميل الملفات
if (session_status() === PHP_SESSION_NONE) {
    // إعدادات الجلسة لضمان عملها بشكل صحيح
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    
    // اكتشاف HTTPS
    $isSecure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
        $isSecure = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $isSecure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isSecure = true;
    } elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
        $isSecure = true;
    }
    
    ini_set('session.cookie_samesite', $isSecure ? 'None' : 'Lax');
    ini_set('session.cookie_secure', $isSecure ? '1' : '0');
    
    @session_start();
    
    // تسجيل معلومات الجلسة للمساعدة في التشخيص
    error_log("WebAuthn Register (webauthn/) - Session started. Session ID: " . session_id() . ", Status: " . session_status());
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/database.php';
require_once __DIR__ . '/webauthn.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'يجب استخدام طريقة POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ إصلاح: التحقق من تسجيل الدخول بشكل صحيح
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // تسجيل الخطأ للمساعدة في التشخيص
    error_log("WebAuthn Register - Session check failed. Session ID: " . session_id() . ", Session status: " . session_status() . ", Has user_id: " . (isset($_SESSION['user_id']) ? 'yes' : 'no'));
    
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً',
        'error' => 'session_not_found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // قراءة البيانات من JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'challenge';

    if ($action === 'challenge') {
        // إنشاء challenge للتسجيل
        // ✅ إصلاح: استخدام dbSelectOne بدلاً من getUserById
        $user = dbSelectOne(
            "SELECT id, username, name FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            error_log("WebAuthn Register - User not found: " . $userId);
            throw new Exception('المستخدم غير موجود');
        }

        try {
            // التأكد من بدء الجلسة قبل استدعاء createRegistrationChallenge
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            
            // التحقق من أن WebAuthn class موجودة
            if (!class_exists('WebAuthn')) {
                throw new Exception('فئة WebAuthn غير موجودة. تحقق من تحميل webauthn.php');
            }
            
            error_log("WebAuthn Register - Creating challenge for user: " . $userId . ", username: " . $user['username']);
            
            $challenge = WebAuthn::createRegistrationChallenge($userId, $user['username']);
            
            if (!$challenge || !is_array($challenge)) {
                error_log("WebAuthn Register - Failed to create challenge for user: " . $userId . ". Challenge type: " . gettype($challenge) . ", Value: " . var_export($challenge, true));
                throw new Exception('فشل في إنشاء challenge');
            }
            
            error_log("WebAuthn Register - Challenge created successfully for user: " . $userId);

            echo json_encode([
                'success' => true,
                'data' => $challenge
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("WebAuthn Register - Challenge creation error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("WebAuthn Register - Stack trace: " . $e->getTraceAsString());
            throw $e;
        } catch (Error $e) {
            error_log("WebAuthn Register - Challenge creation fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            throw new Exception('خطأ قاتل في إنشاء challenge: ' . $e->getMessage());
        }

    } elseif ($action === 'verify') {
        // التأكد من بدء الجلسة
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        // التحقق من البصمة وحفظها
        $response = $input['response'] ?? null;
        
        if (!$response) {
            throw new Exception('بيانات الاعتماد غير مكتملة');
        }

        // تحويل response من JSON string إلى array إذا لزم الأمر
        if (is_string($response)) {
            $response = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("WebAuthn Register - JSON decode error: " . json_last_error_msg() . " for response: " . substr($response, 0, 200));
                throw new Exception('استجابة غير صحيحة: ' . json_last_error_msg());
            }
        }
        
        if (!is_array($response)) {
            error_log("WebAuthn Register - Response is not an array. Type: " . gettype($response));
            throw new Exception('استجابة غير صحيحة: يجب أن تكون array');
        }

        try {
            // التحقق من البصمة
            $result = WebAuthn::verifyRegistration(json_encode($response), $userId);
            
            if ($result === true) {
                // ✅ إصلاح: استخدام dbExecute بدلاً من $db->execute
                $updateResult = dbExecute("UPDATE users SET webauthn_enabled = 1, updated_at = NOW() WHERE id = ?", [$userId]);
                if ($updateResult === false) {
                    error_log("WebAuthn Register - Failed to update user webauthn_enabled status");
                    // لا نرمي exception لأن البصمة تم حفظها بنجاح
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تسجيل البصمة بنجاح'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                error_log("WebAuthn Register - verifyRegistration returned false for user: " . $userId);
                throw new Exception('فشل التحقق من البصمة. تحقق من سجلات الخادم.');
            }
        } catch (Exception $e) {
            error_log("WebAuthn Register - Verify error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            throw $e;
        }

    } else {
        throw new Exception('إجراء غير صحيح');
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("WebAuthn register error: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    
    // إرجاع رسالة خطأ واضحة للمستخدم
    $userMessage = $e->getMessage();
    if (strpos($e->getMessage(), 'Direct access not allowed') !== false) {
        $userMessage = 'خطأ في تحميل نظام WebAuthn. تحقق من إعدادات الخادم.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'error' => $userMessage,
        'debug' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $errorDetails : null
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("WebAuthn register fatal error: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ قاتل في النظام',
        'error' => 'خطأ قاتل: ' . $e->getMessage(),
        'debug' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $errorDetails : null
    ], JSON_UNESCAPED_UNICODE);
}
