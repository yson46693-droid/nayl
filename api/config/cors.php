<?php
/**
 * ============================================
 * CORS - مصدر واحد للنطاقات المسموحة
 * ============================================
 * جميع الـ allowedOrigins من .env (APP_URL + ALLOWED_ORIGINS_EXTRA) + localhost
 * استخدم getAllowedOrigin() أو getAdminAllowedOrigin() في أي endpoint.
 */

require_once __DIR__ . '/env.php';
if (!function_exists('loadEnv')) {
    return;
}
$envFile = file_exists(__DIR__ . '/../.env') ? __DIR__ . '/../.env' : null;
if ($envFile) {
    loadEnv($envFile);
}

/**
 * بناء قائمة النطاقات المسموحة من .env
 * @return array
 */
function getAllowedOriginsList() {
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1'
    ];
    if (function_exists('env')) {
        $extra = env('ALLOWED_ORIGINS_EXTRA', '');
        if ($extra !== '') {
            foreach (array_map('trim', explode(',', $extra)) as $origin) {
                if ($origin !== '') {
                    $allowedOrigins[] = rtrim($origin, '/');
                }
            }
        }
        $appUrl = env('APP_URL', '');
        if ($appUrl) {
            $allowedOrigins[] = rtrim($appUrl, '/');
            $parsed = parse_url($appUrl);
            if ($parsed && isset($parsed['host'])) {
                $allowedOrigins[] = ($parsed['scheme'] ?? 'http') . '://' . $parsed['host'];
            }
        }
    }
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
    return $allowedOrigins;
}

/**
 * الحصول على Origin المسموح للمستخدمين ولوحة الأدمن (مصدر واحد)
 * @return string|null
 */
function getAllowedOrigin() {
    $allowedOrigins = getAllowedOriginsList();
    $serverProtocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $serverProtocol = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $serverProtocol = 'https';
    }
    $serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $serverOrigin = $serverProtocol . '://' . ($serverHost !== '' ? $serverHost : 'localhost');
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

    if (!$requestOrigin) {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($referer) {
            $parsedRef = parse_url($referer);
            if ($parsedRef && isset($parsedRef['host'])) {
                $refOrigin = ($parsedRef['scheme'] ?? 'http') . '://' . $parsedRef['host'];
                $refNorm = rtrim($refOrigin, '/');
                $refNormNoPort = preg_replace('#^(https?)://([^:/]+):(80|443)$#', '$1://$2', $refNorm);
                foreach ($allowedOrigins as $allowed) {
                    $allowedNorm = rtrim($allowed, '/');
                    $allowedNormNoPort = preg_replace('#^(https?)://([^:/]+):(80|443)$#', '$1://$2', $allowedNorm);
                    if ($refNorm === $allowedNorm || $refNormNoPort === $allowedNormNoPort) {
                        return $refOrigin;
                    }
                }
            }
        }
        return $serverOrigin;
    }
    return null;
}

/**
 * نفس getAllowedOrigin() للوحة الأدمن (قائمة النطاقات واحدة من .env)
 * @return string|null
 */
function getAdminAllowedOrigin() {
    return getAllowedOrigin();
}
