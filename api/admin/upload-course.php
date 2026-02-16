<?php
/**
 * ============================================
 * Upload Course with Videos to Bunny CDN
 * ============================================
 * رفع كورس مع فيديوهاته إلى Bunny CDN
 */

// تعطيل عرض الأخطاء على الشاشة
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// معالج الأخطاء المخصص
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// معالج الاستثناءات المخصص
set_exception_handler(function($exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ غير متوقع: ' . $exception->getMessage(),
        'code' => 'INTERNAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    exit;
});

header('Content-Type: application/json; charset=utf-8');

// استيراد الملفات المطلوبة أولاً
try {
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/bunny-cdn.php';
    require_once __DIR__ . '/../config/security.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل ملفات الإعدادات: ' . $e->getMessage(),
        'code' => 'CONFIG_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Config loading error: " . $e->getMessage());
    exit;
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في تحميل ملفات الإعدادات',
        'code' => 'CONFIG_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Config loading fatal error: " . $e->getMessage());
    exit;
}

// CORS Headers (بعد تحميل auth.php الذي يحتوي على getAllowedOrigin)
$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من تسجيل الدخول كأدمن
$admin = requireAdminAuth(true);
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'يجب تسجيل الدخول كمسؤول للوصول إلى هذه الصفحة',
        'code' => 'ADMIN_UNAUTHORIZED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'الطريقة غير مسموحة',
        'code' => 'METHOD_NOT_ALLOWED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من حجم البيانات المرسلة
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = ini_get('post_max_size');
    $postMaxSizeBytes = parseSize($postMaxSize);
    
    if ($contentLength > 0 && $contentLength > $postMaxSizeBytes) {
        throw new Exception('حجم البيانات المرسلة كبير جداً. الحد الأقصى: ' . $postMaxSize);
    }
    
    // الحصول على البيانات من الطلب
    $rawInput = @file_get_contents('php://input');
    
    if ($rawInput === false) {
        throw new Exception('فشل قراءة البيانات المرسلة');
    }
    
    if (empty($rawInput)) {
        // محاولة من $_POST إذا لم يكن JSON
        $input = $_POST;
        if (empty($input)) {
            throw new Exception('لم يتم إرسال أي بيانات');
        }
    } else {
        $input = @json_decode($rawInput, true);
        
        // التحقق من وجود خطأ في JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'خطأ في تنسيق JSON: ';
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $errorMsg .= 'العمق الأقصى تم تجاوزه';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $errorMsg .= 'خطأ في الحالة';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $errorMsg .= 'خطأ في أحرف التحكم';
                    break;
                case JSON_ERROR_SYNTAX:
                    $errorMsg .= 'خطأ في الصيغة - قد تكون البيانات غير صحيحة أو كبيرة جداً';
                    break;
                case JSON_ERROR_UTF8:
                    $errorMsg .= 'خطأ في ترميز UTF-8';
                    break;
                default:
                    $errorMsg .= 'خطأ غير معروف (كود: ' . json_last_error() . ')';
            }
            throw new Exception($errorMsg);
        }
        
        if (!$input || !is_array($input)) {
            throw new Exception('بيانات غير صحيحة أو فارغة');
        }
    }
    
    // التحقق من البيانات المطلوبة
    $courseTitle = sanitizeInput($input['courseTitle'] ?? '');
    $courseDescription = sanitizeInput($input['courseDescription'] ?? '');
    $coursePriceRaw = $input['coursePrice'] ?? 500;
    $coursePrice = is_numeric($coursePriceRaw) ? (float) $coursePriceRaw : 500.00;
    if ($coursePrice < 0) {
        $coursePrice = 0.00;
    }
    $courseCoverFileBase64 = $input['courseCoverFile'] ?? '';
    $videos = $input['videos'] ?? [];
    
    if (empty($courseTitle)) {
        throw new Exception('عنوان الكورس مطلوب');
    }
    
    if (empty($videos) || !is_array($videos) || count($videos) === 0) {
        throw new Exception('يجب إضافة فيديو واحد على الأقل');
    }
    
    // الاتصال بقاعدة البيانات
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('فشل الاتصال بقاعدة البيانات');
    }
    
    // متغير لتتبع حالة المعاملة
    $transactionStarted = false;
    
    // بدء المعاملة
    try {
        $pdo->beginTransaction();
        $transactionStarted = true;
    } catch (PDOException $e) {
        throw new Exception('فشل بدء المعاملة: ' . $e->getMessage());
    }
    
    try {
        // 1. إنشاء الكورس في قاعدة البيانات (مع السعر)
        $stmt = $pdo->prepare("
            INSERT INTO courses (title, description, price, status, created_by)
            VALUES (:title, :description, :price, 'published', :created_by)
        ");
        
        $stmt->execute([
            'title' => $courseTitle,
            'description' => $courseDescription,
            'price' => $coursePrice,
            'created_by' => $admin['admin_id']
        ]);
        
        $courseId = $pdo->lastInsertId();
        
        // 2. إنشاء مكتبة Bunny CDN جديدة لهذا الكورس (اسمها: رقم الكورس + ترتيب الكورس)
        $libraryName = $courseId . ' ترتيب الكورس';
        $bunnyLibrary = createBunnyLibrary($libraryName);
        if (isset($bunnyLibrary['error']) || empty($bunnyLibrary['Id']) || empty($bunnyLibrary['ApiKey'])) {
            $libError = $bunnyLibrary['error'] ?? 'لم يتم إرجاع معرف المكتبة أو المفتاح';
            error_log("Failed to create Bunny library for course {$courseId}: " . $libError);
            throw new Exception('فشل إنشاء مكتبة الفيديوهات في Bunny CDN: ' . $libError);
        }
        $courseLibraryId = (int) $bunnyLibrary['Id'];
        $courseLibraryApiKey = $bunnyLibrary['ApiKey'];
        
        $updateCourseStmt = $pdo->prepare("
            UPDATE courses
            SET bunny_library_id = :lib_id, bunny_library_api_key = :lib_key
            WHERE id = :course_id
        ");
        $updateCourseStmt->execute([
            'lib_id' => $courseLibraryId,
            'lib_key' => $courseLibraryApiKey,
            'course_id' => $courseId
        ]);

        // حفظ صورة واجهة الكورس (البطاقة) إن وُجدت
        if (!empty($courseCoverFileBase64)) {
            $coverContent = base64_decode($courseCoverFileBase64);
            if ($coverContent !== false) {
                $coversDir = __DIR__ . '/../../uploads/covers/';
                if (!is_dir($coversDir)) {
                    mkdir($coversDir, 0755, true);
                }
                $coverExt = 'jpg';
                $coverFileName = 'course_' . $courseId . '_' . uniqid() . '.' . $coverExt;
                $coverPath = $coversDir . $coverFileName;
                if (file_put_contents($coverPath, $coverContent) !== false) {
                    $coverUrl = '/uploads/covers/' . $coverFileName;
                    $updateCoverStmt = $pdo->prepare("UPDATE courses SET cover_image_url = :url WHERE id = :course_id");
                    $updateCoverStmt->execute(['url' => $coverUrl, 'course_id' => $courseId]);
                }
            }
        }
        
        // 3. معالجة كل فيديو (يُرفع إلى مكتبة الكورس فقط)
        $uploadedVideos = [];
        $errors = [];
        
        foreach ($videos as $index => $videoData) {
            try {
                $videoTitle = sanitizeInput($videoData['title'] ?? '');
                $videoDescription = sanitizeInput($videoData['description'] ?? '');
                $videoOrder = (int)($videoData['order'] ?? ($index + 1));
                $videoFileBase64 = $videoData['videoFile'] ?? '';
                $thumbnailFileBase64 = $videoData['thumbnailFile'] ?? '';
                
                if (empty($videoTitle)) {
                    throw new Exception("عنوان الفيديو #{$videoOrder} مطلوب");
                }
                
                if (empty($videoFileBase64)) {
                    throw new Exception("ملف الفيديو #{$videoOrder} مطلوب");
                }
                
                // فك تشفير Base64 للفيديو
                $videoContent = base64_decode($videoFileBase64);
                if ($videoContent === false) {
                    throw new Exception("فشل فك تشفير ملف الفيديو #{$videoOrder}");
                }
                
                // حفظ الفيديو مؤقتاً
                $tempVideoPath = sys_get_temp_dir() . '/bunny_upload_' . uniqid() . '.mp4';
                if (file_put_contents($tempVideoPath, $videoContent) === false) {
                    throw new Exception("فشل حفظ ملف الفيديو #{$videoOrder} مؤقتاً");
                }
                
                // إنشاء فيديو في Bunny CDN (داخل مكتبة هذا الكورس)
                $bunnyVideo = createBunnyVideo($videoTitle, $courseLibraryId, $courseLibraryApiKey);
                if (!$bunnyVideo || !isset($bunnyVideo['guid'])) {
                    unlink($tempVideoPath);
                    $errorDetails = '';
                    if (isset($bunnyVideo['error'])) {
                        $errorDetails = ' - ' . $bunnyVideo['error'];
                    } elseif (isset($bunnyVideo['http_code'])) {
                        $errorDetails = ' - HTTP ' . $bunnyVideo['http_code'];
                        if (isset($bunnyVideo['response'])) {
                            $errorDetails .= ': ' . substr($bunnyVideo['response'], 0, 200);
                        }
                    }
                    error_log("Failed to create Bunny video - Response: " . print_r($bunnyVideo, true));
                    throw new Exception("فشل إنشاء فيديو في Bunny CDN للفيديو #{$videoOrder}{$errorDetails}");
                }
                
                $bunnyVideoId = $bunnyVideo['guid'];
                
                // رفع الفيديو إلى Bunny CDN (مكتبة الكورس)
                $uploadSuccess = uploadBunnyVideo($bunnyVideoId, $tempVideoPath, $courseLibraryId, $courseLibraryApiKey);
                
                // حذف الملف المؤقت
                unlink($tempVideoPath);
                
                if (!$uploadSuccess) {
                    throw new Exception("فشل رفع الفيديو #{$videoOrder} إلى Bunny CDN");
                }
                
                // 5. معالجة صورة الواجهة (Thumbnail)
                $thumbnailUrl = null;
                if (!empty($thumbnailFileBase64)) {
                    $thumbnailContent = base64_decode($thumbnailFileBase64);
                    if ($thumbnailContent !== false) {
                        // حفظ الصورة في مجلد uploads (يمكن تحسينه لرفعها إلى CDN أيضاً)
                        $uploadsDir = __DIR__ . '/../../uploads/thumbnails/';
                        if (!is_dir($uploadsDir)) {
                            mkdir($uploadsDir, 0755, true);
                        }
                        
                        $thumbnailExtension = 'jpg'; // يمكن تحديده من البيانات
                        $thumbnailFileName = 'thumb_' . $courseId . '_' . $videoOrder . '_' . uniqid() . '.' . $thumbnailExtension;
                        $thumbnailPath = $uploadsDir . $thumbnailFileName;
                        
                        if (file_put_contents($thumbnailPath, $thumbnailContent) !== false) {
                            // يمكن رفع الصورة إلى CDN هنا أيضاً
                            $thumbnailUrl = '/uploads/thumbnails/' . $thumbnailFileName;
                        }
                    }
                }
                
                // الحصول على معلومات الفيديو من Bunny CDN (مكتبة الكورس)
                $videoInfo = getBunnyVideoInfo($bunnyVideoId, $courseLibraryId, $courseLibraryApiKey);
                $videoUrl = null;
                $duration = null;
                $fileSize = null;
                
                if ($videoInfo) {
                    $videoUrl = getBunnyVideoUrl($bunnyVideoId, $courseLibraryId);
                    $duration = $videoInfo['length'] ?? null;
                    $fileSize = $videoInfo['storageSize'] ?? null;
                } else {
                    $videoUrl = getBunnyVideoUrl($bunnyVideoId, $courseLibraryId);
                }
                
                // 7. حفظ معلومات الفيديو في قاعدة البيانات
                $stmt = $pdo->prepare("
                    INSERT INTO course_videos (
                        course_id, title, description, video_order,
                        thumbnail_url, video_url, bunny_video_id,
                        duration, file_size, status
                    )
                    VALUES (
                        :course_id, :title, :description, :video_order,
                        :thumbnail_url, :video_url, :bunny_video_id,
                        :duration, :file_size, 'processing'
                    )
                ");
                
                $stmt->execute([
                    'course_id' => $courseId,
                    'title' => $videoTitle,
                    'description' => $videoDescription,
                    'video_order' => $videoOrder,
                    'thumbnail_url' => $thumbnailUrl,
                    'video_url' => $videoUrl,
                    'bunny_video_id' => $bunnyVideoId,
                    'duration' => $duration,
                    'file_size' => $fileSize
                ]);
                
                $videoId = $pdo->lastInsertId();
                
                // تحديث حالة الفيديو إلى ready بعد التأكد من الرفع
                $updateStmt = $pdo->prepare("
                    UPDATE course_videos
                    SET status = 'ready'
                    WHERE id = :video_id
                ");
                $updateStmt->execute(['video_id' => $videoId]);
                
                $uploadedVideos[] = [
                    'id' => $videoId,
                    'title' => $videoTitle,
                    'order' => $videoOrder,
                    'bunny_video_id' => $bunnyVideoId
                ];
                
            } catch (Exception $e) {
                $errors[] = "فيديو #{$videoOrder}: " . $e->getMessage();
                error_log("Video upload error: " . $e->getMessage());
            }
        }
        
        // إذا فشل رفع جميع الفيديوهات، إلغاء المعاملة
        if (count($uploadedVideos) === 0) {
            if ($transactionStarted && $pdo && $pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (PDOException $rollbackError) {
                    error_log("Rollback error: " . $rollbackError->getMessage());
                }
            }
            throw new Exception('فشل رفع جميع الفيديوهات: ' . implode(', ', $errors));
        }
        
        // إذا نجح رفع بعض الفيديوهات فقط، إكمال المعاملة مع تحذير
        if (count($errors) > 0) {
            // يمكن إضافة إشعار أو تسجيل للأخطاء
            error_log("Some videos failed to upload: " . implode(', ', $errors));
        }
        
        // تأكيد المعاملة
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->commit();
        }
        
        // إرجاع النتيجة
        echo json_encode([
            'success' => true,
            'message' => 'تم رفع الكورس بنجاح',
            'data' => [
                'courseId' => $courseId,
                'courseTitle' => $courseTitle,
                'videosCount' => count($uploadedVideos),
                'videos' => $uploadedVideos,
                'warnings' => count($errors) > 0 ? $errors : null
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // إلغاء المعاملة فقط إذا كانت نشطة
        if ($transactionStarted && $pdo && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackError) {
                error_log("Rollback error: " . $rollbackError->getMessage());
            }
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 'UPLOAD_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Course upload error: " . $e->getMessage());
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ غير متوقع: ' . $e->getMessage(),
        'code' => 'FATAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
    error_log("Course upload fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

/**
 * تحويل حجم من نص (مثل "128M") إلى بايت
 */
function parseSize($size) {
    $size = trim($size);
    $last = strtolower($size[strlen($size)-1]);
    $size = (int)$size;
    switch($last) {
        case 'g':
            $size *= 1024;
        case 'm':
            $size *= 1024;
        case 'k':
            $size *= 1024;
    }
    return $size;
}
