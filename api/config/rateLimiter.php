<?php
/**
 * Rate Limiter - Backend
 * حماية من الضغط على APIs: تسجيل الدخول وإنشاء الحساب
 * 5 محاولات خاطئة = حظر 30 دقيقة
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/env.php';

if (!function_exists('env')) {
    loadEnv(__DIR__ . '/../.env');
}

define('RATE_LIMIT_MAX_ATTEMPTS', (int)env('RATE_LIMIT_MAX_ATTEMPTS', 5));
define('RATE_LIMIT_WINDOW_SECONDS', (int)env('RATE_LIMIT_WINDOW_SECONDS', 15 * 60)); // 15 دقيقة
define('RATE_LIMIT_LOCKOUT_SECONDS', (int)env('RATE_LIMIT_LOCKOUT_SECONDS', 30 * 60)); // 30 دقيقة

// حد الطلبات لإنشاء الحساب (منع السبام)
define('SIGNUP_RATE_LIMIT_MAX', (int)env('SIGNUP_RATE_LIMIT_MAX', 5));
define('SIGNUP_RATE_LIMIT_WINDOW', (int)env('SIGNUP_RATE_LIMIT_WINDOW', 60 * 60)); // ساعة

/**
 * التحقق من Rate Limit لتسجيل الدخول (5 محاولات = حظر)
 * الحظر حسب الجهاز (device_uuid) وليس IP
 * @param string $deviceUuid - معرف الجهاز من localStorage/cookies (device_uuid_v1 أو v_id)
 */
function checkLoginRateLimit($deviceUuid) {
    if (empty($deviceUuid) || !is_string($deviceUuid)) {
        return ['allowed' => false, 'message' => 'معرف الجهاز مطلوب. يرجى إعادة تحميل الصفحة.'];
    }
    $deviceUuid = substr(trim($deviceUuid), 0, 36);

    $pdo = getDatabaseConnection();
    if (!$pdo) return ['allowed' => false, 'message' => 'خطأ في الاتصال'];

    try {
        $stmt = $pdo->prepare("
            SELECT attempt_count, last_attempt_at, locked_until 
            FROM login_attempts 
            WHERE device_uuid = :du AND (full_phone IS NULL OR full_phone != '__signup__')
            ORDER BY last_attempt_at DESC LIMIT 1
        ");
        $stmt->execute(['du' => $deviceUuid]);
        $row = $stmt->fetch();

        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW_SECONDS;

        if ($row) {
            if ($row['locked_until']) {
                $locked = strtotime($row['locked_until']);
                if ($locked > $now) {
                    return [
                        'allowed' => false,
                        'message' => 'تم تجاوز الحد المسموح (5 محاولات). يرجى المحاولة بعد ' . ceil(($locked - $now) / 60) . ' دقيقة.'
                    ];
                }
            }
            $lastAt = strtotime($row['last_attempt_at']);
            $count = ($lastAt >= $windowStart) ? (int)$row['attempt_count'] : 0;
        } else {
            $count = 0;
        }

        if ($count >= RATE_LIMIT_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', $now + RATE_LIMIT_LOCKOUT_SECONDS);
            $stmt = $pdo->prepare("
                UPDATE login_attempts SET locked_until = :lu, last_attempt_at = NOW() 
                WHERE device_uuid = :du AND (full_phone IS NULL OR full_phone != '__signup__')
            ");
            $stmt->execute(['lu' => $lockedUntil, 'du' => $deviceUuid]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare("INSERT INTO login_attempts (device_uuid, full_phone, attempt_count, locked_until) VALUES (:du, NULL, 1, :lu)")
                    ->execute(['du' => $deviceUuid, 'lu' => $lockedUntil]);
            }
            return [
                'allowed' => false,
                'message' => 'تم تجاوز الحد المسموح. تم حظر هذا الجهاز لمدة ' . (RATE_LIMIT_LOCKOUT_SECONDS / 60) . ' دقيقة.'
            ];
        }

        return ['allowed' => true, 'remaining' => RATE_LIMIT_MAX_ATTEMPTS - $count];
    } catch (PDOException $e) {
        error_log("Rate Limit: " . $e->getMessage());
        return ['allowed' => true];
    }
}

/**
 * تسجيل محاولة تسجيل دخول فاشلة - حسب الجهاز (device_uuid)
 * @param string $deviceUuid - معرف الجهاز من localStorage/cookies
 * @param string|null $email - البريد الإلكتروني
 * @param string|null $ip - عنوان IP للتسجيل فقط (اختياري)
 */
function recordLoginAttempt($deviceUuid, $email = null, $ip = null) {
    if (empty($deviceUuid) || !is_string($deviceUuid)) return;
    $deviceUuid = substr(trim($deviceUuid), 0, 36);

    $pdo = getDatabaseConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare("SELECT id, attempt_count, last_attempt_at FROM login_attempts WHERE device_uuid = :du AND (full_phone IS NULL OR full_phone != '__signup__') LIMIT 1");
        $stmt->execute(['du' => $deviceUuid]);
        $row = $stmt->fetch();

        if ($row) {
            $lastAt = strtotime($row['last_attempt_at']);
            $count = ($lastAt >= time() - RATE_LIMIT_WINDOW_SECONDS) ? (int)$row['attempt_count'] + 1 : 1;
            $locked = ($count >= RATE_LIMIT_MAX_ATTEMPTS) ? date('Y-m-d H:i:s', time() + RATE_LIMIT_LOCKOUT_SECONDS) : null;
            $pdo->prepare("UPDATE login_attempts SET attempt_count = :c, last_attempt_at = NOW(), locked_until = COALESCE(:lu, locked_until), email = COALESCE(:email, email), ip_address = COALESCE(:ip, ip_address) WHERE device_uuid = :du AND (full_phone IS NULL OR full_phone != '__signup__')")
                ->execute(['c' => $count, 'lu' => $locked, 'email' => $email, 'ip' => $ip, 'du' => $deviceUuid]);
        } else {
            $locked = (RATE_LIMIT_MAX_ATTEMPTS <= 1) ? date('Y-m-d H:i:s', time() + RATE_LIMIT_LOCKOUT_SECONDS) : null;
            $pdo->prepare("INSERT INTO login_attempts (device_uuid, email, full_phone, ip_address, attempt_count, locked_until) VALUES (:du, :email, NULL, :ip, 1, :lu)")
                ->execute(['du' => $deviceUuid, 'email' => $email, 'ip' => $ip, 'lu' => $locked]);
        }
    } catch (PDOException $e) {
        error_log("Record Attempt: " . $e->getMessage());
    }
}

/**
 * مسح محاولات تسجيل الدخول عند النجاح - حسب الجهاز
 * @param string $deviceUuid - معرف الجهاز من localStorage/cookies
 */
function clearLoginAttempts($deviceUuid) {
    if (empty($deviceUuid) || !is_string($deviceUuid)) return;
    $deviceUuid = substr(trim($deviceUuid), 0, 36);

    $pdo = getDatabaseConnection();
    if (!$pdo) return;
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE device_uuid = :du AND (full_phone IS NULL OR full_phone != '__signup__')")->execute(['du' => $deviceUuid]);
    } catch (PDOException $e) {}
}

/**
 * Rate Limit لإنشاء الحساب (منع الضغط)
 * يستخدم full_phone = '__signup__' للتمييز عن محاولات الدخول
 */
function checkSignupRateLimit($ip) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return ['allowed' => false, 'message' => 'خطأ في الاتصال'];

    try {
        $windowStart = date('Y-m-d H:i:s', time() - SIGNUP_RATE_LIMIT_WINDOW);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as c FROM login_attempts 
            WHERE ip_address = :ip AND full_phone = '__signup__' AND last_attempt_at >= :ws
        ");
        $stmt->execute(['ip' => $ip, 'ws' => $windowStart]);
        $count = (int)($stmt->fetch()['c'] ?? 0);

        if ($count >= SIGNUP_RATE_LIMIT_MAX) {
            return [
                'allowed' => false,
                'message' => 'تم تجاوز الحد المسموح لإنشاء الحساب. يرجى المحاولة لاحقاً.'
            ];
        }
        return ['allowed' => true];
    } catch (PDOException $e) {
        return ['allowed' => true];
    }
}

function recordSignupAttempt($ip) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return;
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, full_phone, attempt_count) VALUES (:ip, '__signup__', 1)")
            ->execute(['ip' => $ip]);
    } catch (PDOException $e) {}
}
