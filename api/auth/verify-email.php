<?php
/**
 * ============================================
 * Legacy Email Verification Redirect
 * ============================================
 * الرابط الجديد: /verify-email?token=xxx (لتجنب تحذيرات المتصفح)
 * هذا الملف يوجه الروابط القديمة (/api/auth/verify-email.php?token=xxx)
 * إلى المعالج الجديد دون تغيير الرمز.
 *
 * GET /api/auth/verify-email.php?token=xxx → 302 → /verify-email?token=xxx
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: /verification-error');
    exit;
}

$token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
$baseUrl = rtrim(function_exists('env') ? env('APP_URL', '') : '', '/');
if (empty($baseUrl)) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
}
$target = $baseUrl . '/verify-email' . ($token !== '' ? '?token=' . rawurlencode($token) : '');
header('Location: ' . $target, true, 302);
exit;
