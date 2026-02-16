<?php
/**
 * ============================================
 * Security Functions
 * ============================================
 * وظائف الأمان المشتركة
 */

/**
 * تنظيف المدخلات من HTML/XSS
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    if (!is_string($input)) {
        return '';
    }
    
    // إزالة HTML tags
    $cleaned = strip_tags($input);
    
    // تنظيف إضافي
    $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
    
    return trim($cleaned);
}

/**
 * Hash كلمة المرور باستخدام bcrypt
 * @param string $password
 * @return string|false
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * التحقق من كلمة المرور
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * التحقق من صحة البريد الإلكتروني
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من صحة رقم الهاتف
 * @param string $phone
 * @param int $minLength
 * @return bool
 */
function validatePhone($phone, $minLength = 7) {
    return preg_match('/^[0-9]+$/', $phone) && strlen($phone) >= $minLength;
}

/**
 * التحقق من رمز الدولة
 * @param string $countryCode
 * @return bool
 */
function validateCountryCode($countryCode) {
    return preg_match('/^\+[0-9]{1,4}$/', $countryCode);
}

/**
 * الحصول على عنوان IP الحقيقي للعميل
 * @return string
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
               'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * إرسال استجابة JSON
 * @param bool $success
 * @param mixed $data
 * @param string|null $error
 * @param int $statusCode
 */
function sendJsonResponse($success, $data = null, $error = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * التحقق من CSRF Token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}
