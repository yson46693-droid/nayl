<?php
/**
 * ============================================
 * Video Proxy (Admin) - لتجاوز CORS مع Bunny CDN
 * ============================================
 * يجلب الفيديو من رابط مخزن في قاعدة البيانات ويُعيده للمتصفح
 * لتجنب طلب CORS من المتصفح مباشرة إلى CDN
 *
 * Endpoint: GET /api/admin/proxy-video.php?video_id=123
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

$admin = requireAdminAuth(true);
if (!$admin) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'غير مصرح';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$videoId = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;
if ($videoId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'معرف الفيديو مطلوب';
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        exit;
    }

    $stmt = $pdo->prepare('SELECT video_url FROM course_videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['video_url'])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'الفيديو غير موجود';
        exit;
    }

    $videoUrl = $row['video_url'];

    // السماح فقط بروابط Bunny CDN أو الدومين المسموح (أمان)
    $allowedHosts = ['b-cdn.net', 'bunnycdn.com', 'bunny.net', 'almoustafa.site'];
    $parsed = parse_url($videoUrl);
    $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
    $isAllowed = false;
    foreach ($allowedHosts as $h) {
        if (strpos($host, $h) !== false) {
            $isAllowed = true;
            break;
        }
    }
    if (!$isAllowed && $host !== '' && $host !== 'localhost') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'الرابط غير مسموح';
        exit;
    }

    // استخدام cURL لتمرير طلب Range إلى CDN (للتنقل داخل الفيديو)
    $ch = curl_init($videoUrl);
    $curlHeaders = ['Accept: */*'];
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $curlHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            return strlen($data);
        },
        CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) {
            $len = strlen($headerLine);
            $line = trim($headerLine);
            if ($line === '') {
                return $len;
            }
            if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $line, $m)) {
                http_response_code((int) $m[1]);
                return $len;
            }
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $name = strtolower(trim(substr($line, 0, $colon)));
                if (in_array($name, ['content-type', 'content-length', 'content-range', 'accept-ranges'], true)) {
                    header(trim($line));
                }
            }
            return $len;
        },
        CURLOPT_HTTPHEADER => $curlHeaders
    ]);

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$success || ($httpCode !== 200 && $httpCode !== 206)) {
        if (!headers_sent()) {
            http_response_code($httpCode ?: 502);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'تعذر جلب الفيديو';
        }
    }
} catch (Exception $e) {
    error_log('Proxy video error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'خطأ في الخادم';
}
