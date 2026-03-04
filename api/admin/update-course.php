<?php
/**
 * ============================================
 * Update Course API (Admin)
 * ============================================
 * API لتعديل بيانات كورس (العنوان، الوصف، الحالة، السعر)
 *
 * Endpoint: POST /api/admin/update-course.php
 * Body: { course_id, title?, description?, status?, price? }
 */

session_start();

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../.env');

$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($data)) {
    sendJsonResponse(false, null, 'بيانات الطلب غير صالحة أو فارغة. تأكد من إرسال JSON يتضمن course_id.', 400);
}

$courseId = isset($data['course_id']) ? (int) $data['course_id'] : 0;
if ($courseId < 1) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب ويجب أن يكون رقماً صحيحاً أكبر من 0', 400);
}

$title = isset($data['title']) ? sanitizeInput(trim($data['title'])) : null;
$description = isset($data['description']) ? sanitizeInput($data['description']) : null;
$statusAr = isset($data['status']) ? trim($data['status']) : null;
$price = isset($data['price']) ? (float) $data['price'] : null;
$coverImageBase64 = isset($data['cover_image_base64']) && is_string($data['cover_image_base64']) ? trim($data['cover_image_base64']) : null;

if ($price !== null && ($price < 0 || $price > 999999.99)) {
    sendJsonResponse(false, null, 'السعر يجب أن يكون بين 0 و 999999.99', 400);
}

// تحويل الحالة من العربية إلى القيمة في قاعدة البيانات
$statusDb = null;
if ($statusAr !== null) {
    if ($statusAr === 'منشور') {
        $statusDb = 'published';
    } elseif ($statusAr === 'مسودة') {
        $statusDb = 'draft';
    } elseif ($statusAr === 'مؤرشف') {
        $statusDb = 'archived';
    } else {
        sendJsonResponse(false, null, 'حالة النشر غير صالحة', 400);
    }
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    $check = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
    $check->execute([$courseId]);
    if (!$check->fetch()) {
        sendJsonResponse(false, null, 'الكورس غير موجود', 404);
    }

    $updates = [];
    $params = [];

    if ($title !== null) {
        $updates[] = "title = :title";
        $params['title'] = $title;
    }
    if ($description !== null) {
        $updates[] = "description = :description";
        $params['description'] = $description;
    }
    if ($statusDb !== null) {
        $updates[] = "status = :status";
        $params['status'] = $statusDb;
    }
    if ($price !== null) {
        $updates[] = "price = :price";
        $params['price'] = $price;
    }

    // صورة واجهة الكورس (اختيارية)
    if ($coverImageBase64 !== null && $coverImageBase64 !== '') {
        // إزالة أي prefix مثل data:image/jpeg;base64,
        $base64Data = preg_replace('#^data:image/[^;]+;base64,#i', '', $coverImageBase64);
        $base64Data = preg_replace('#\s+#', '', $base64Data);
        $coverContent = base64_decode($base64Data, true);
        if ($coverContent !== false && strlen($coverContent) > 0) {
            $coversDir = __DIR__ . '/../../uploads/covers/';
            $uploadsDir = __DIR__ . '/../../uploads/';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0755, true);
            }
            if (!is_dir($coversDir)) {
                if (!@mkdir($coversDir, 0755, true)) {
                    error_log('Update course: failed to create covers dir: ' . $coversDir);
                }
            }
            if (is_dir($coversDir) && is_writable($coversDir)) {
                $coverExt = 'jpg';
                $coverFileName = 'course_' . $courseId . '_' . uniqid() . '.' . $coverExt;
                $coverPath = $coversDir . $coverFileName;
                $written = @file_put_contents($coverPath, $coverContent);
                if ($written !== false && $written > 0) {
                    $coverUrl = '/uploads/covers/' . $coverFileName;
                    $updates[] = "cover_image_url = :cover_image_url";
                    $params['cover_image_url'] = $coverUrl;
                } else {
                    error_log('Update course: file_put_contents failed for cover (path=' . $coverPath . ', len=' . strlen($coverContent) . ')');
                }
            } else {
                error_log('Update course: covers dir not writable or missing: ' . $coversDir . ', is_dir=' . (is_dir($coversDir) ? '1' : '0'));
            }
        } else {
            error_log('Update course: cover base64_decode failed or empty (input_len=' . strlen($coverImageBase64) . ')');
        }
    }

    if (empty($updates)) {
        sendJsonResponse(true, ['message' => 'لم يتم تغيير أي حقل'], null, 200);
    }

    $params['id'] = $courseId;
    $sql = "UPDATE courses SET " . implode(", ", $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $responseData = ['message' => 'تم تحديث الكورس بنجاح'];
    if (isset($params['cover_image_url'])) {
        $responseData['cover_image_url'] = $params['cover_image_url'];
        $responseData['cover_saved'] = true;
    } elseif ($coverImageBase64 !== null && $coverImageBase64 !== '') {
        $responseData['cover_saved'] = false;
        $responseData['cover_error'] = 'لم يتم حفظ صورة الواجهة (تحقق من مجلد uploads/covers وصلاحيات الكتابة)';
    }
    sendJsonResponse(true, $responseData, null, 200);
} catch (PDOException $e) {
    error_log('Update course error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تحديث الكورس', 500);
}
