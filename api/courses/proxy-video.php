<?php
/**
 * ============================================
 * Video Proxy (User) - تشغيل الفيديو للمشتركين
 * ============================================
 * يجلب الفيديو من CDN ويُعيده للمتصفح بعد التحقق من اشتراك المستخدم في الكورس.
 * Endpoint: GET /api/courses/proxy-video.php?video_id=123
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = getAllowedOrigin();
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-Fingerprint');
    http_response_code(200);
    exit;
}

$user = requireAuth();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'يجب تسجيل الدخول لمشاهدة الفيديو';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$videoId = isset($_GET['video_id']) ? (int) $_GET['video_id'] : 0;
$clientFp = isset($_GET['fp']) ? trim((string) $_GET['fp']) : '';
if ($videoId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'معرف الفيديو مطلوب';
    exit;
}

$userId = (int) $user['id'];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        exit;
    }

    // جلب الفيديو والكورس
    $stmt = $pdo->prepare('SELECT id, course_id, video_url FROM course_videos WHERE id = ? AND status = ?');
    $stmt->execute([$videoId, 'ready']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['video_url'])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'الفيديو غير موجود';
        exit;
    }

    $courseId = (int) $row['course_id'];

    // التحقق من إدخال كود المشاهدة في هذه الجلسة (مطلوب حتى للمشترين)
    if (empty($_SESSION['course_view_verified'][$courseId])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'أدخل كود المشاهدة أولاً للتحقق من صلاحيتك';
        exit;
    }

    // التحقق من اشتراك المستخدم في الكورس
    $subStmt = $pdo->prepare("
        SELECT ucs.code_id
        FROM user_course_subscriptions ucs
        WHERE ucs.user_id = ? AND ucs.course_id = ? AND ucs.status = 'active'
        LIMIT 1
    ");
    $subStmt->execute([$userId, $courseId]);
    $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
    if (!$subRow) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ليس لديك صلاحية لمشاهدة هذا الفيديو';
        exit;
    }

    // جهاز واحد فقط: جلب bound_device_hash إن وُجد العمود (متوافق مع قواعد لم تُطبَّق عليها الـ migration)
    $boundDeviceHash = null;
    $codeId = isset($subRow['code_id']) ? (int) $subRow['code_id'] : 0;
    if ($codeId > 0) {
        try {
            $ccStmt = $pdo->prepare("SELECT bound_device_hash FROM course_codes WHERE id = ? LIMIT 1");
            $ccStmt->execute([$codeId]);
            $ccRow = $ccStmt->fetch(PDO::FETCH_ASSOC);
            if ($ccRow && isset($ccRow['bound_device_hash']) && $ccRow['bound_device_hash'] !== '' && $ccRow['bound_device_hash'] !== null) {
                $boundDeviceHash = strtolower(trim($ccRow['bound_device_hash']));
            }
        } catch (PDOException $e) {
            // العمود bound_device_hash غير موجود بعد - تخطي التحقق من الجهاز
        }
    }

    if ($boundDeviceHash !== null) {
        $clientFpNormalized = preg_match('/^[a-f0-9]{64}$/i', $clientFp) ? strtolower(substr(trim($clientFp), 0, 64)) : '';
        if ($clientFpNormalized === '' || $clientFpNormalized !== $boundDeviceHash) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'المشاهدة متاحة فقط على الجهاز الذي فعّلت عليه الكورس. استخدم نفس الجهاز أو تواصل مع الدعم.';
            exit;
        }
    }

    $videoUrl = $row['video_url'];

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
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
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
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!$success || ($httpCode !== 200 && $httpCode !== 206)) {
        $finalCode = $httpCode ?: 502;
        if ($curlError) {
            error_log('Proxy video curl error: ' . $curlError . ' (video_id=' . $videoId . ')');
        }
        if ($finalCode === 502) {
            error_log('Proxy video 502: CDN unreachable or invalid response (video_id=' . $videoId . ', http=' . $httpCode . ')');
        }
        if (!headers_sent()) {
            http_response_code($finalCode);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'تعذر جلب الفيديو من الخادم. حاول مرة أخرى لاحقاً.';
        }
    }
} catch (Exception $e) {
    error_log('User proxy video error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'خطأ في الخادم';
}
