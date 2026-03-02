<?php
/**
 * WebAuthn للمشرفين - CORS وتحميل الإعدادات
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../../config/env.php';
    loadEnv(__DIR__ . '/../../.env');
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/security.php';
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل الإعدادات'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getAdminAllowedOrigin() {
    $allowedOrigins = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1',
        'https://almoustafa.site',
        'http://almoustafa.site',
        'https://www.almoustafa.site',
        'http://www.almoustafa.site',
        'https://an.almoustafa.site',
        'http://an.almoustafa.site'
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
    $serverOrigin = $serverProtocol . '://' . ($serverHost !== '' ? $serverHost : 'localhost');
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
    if (!$requestOrigin) {
        return $serverOrigin;
    }
    return null;
}

$allowedOrigin = getAdminAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Origin not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function base64urlEncode($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function base64urlDecode($str) {
    $bin = base64_decode(strtr($str, '-_', '+/') . str_repeat('=', (4 - strlen($str) % 4) % 4), true);
    return $bin !== false ? $bin : '';
}

function getRpId() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    return $host;
}
