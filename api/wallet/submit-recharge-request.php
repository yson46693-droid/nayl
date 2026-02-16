<?php
/**
 * ============================================
 * Submit Recharge Request API
 * ============================================
 * API لإرسال طلب شحن رصيد جديد مع رفع صورة التحويل
 */

// بدء الجلسة
session_start();

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// التحقق من تسجيل الدخول
$user = requireAuth();

// إعدادات CORS
header('Access-Control-Allow-Origin: ' . getAllowedOrigin());
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method Not Allowed', 405);
}

// استلام البيانات (Form Data)
// ملاحظة: مع رفع الملفات نستخدم $_POST و $_FILES وليس JSON body
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$paymentMethod = isset($_POST['payment_method']) ? sanitizeInput($_POST['payment_method']) : '';
$accountNumber = isset($_POST['account_number']) ? sanitizeInput($_POST['account_number']) : '';
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';

// التحقق من البيانات
$errors = [];

if ($amount <= 0) {
    $errors[] = 'يرجى إدخال مبلغ صحيح';
}

if (empty($paymentMethod)) {
    $errors[] = 'يرجى اختيار طريقة الدفع';
}

if (empty($accountNumber)) {
    $errors[] = 'يرجى إدخال رقم الحساب أو الهاتف المحول منه';
}

if (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'يرجى إرفاق صورة إيصال التحويل';
}

if (!empty($errors)) {
    sendJsonResponse(false, null, $errors[0], 400);
}

// دالة لتنظيف الصور القديمة (أكثر من شهر)
function cleanupOldReceipts($dir) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    $now = time();
    $monthInSeconds = 30 * 24 * 60 * 60; // 30 days
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_file($filePath)) {
            $fileAge = $now - filemtime($filePath);
            if ($fileAge > $monthInSeconds) {
                @unlink($filePath); // حذف الملف
            }
        }
    }
}

try {
    $pdo = getDatabaseConnection();
    
    // 1. إدخال سجل أولي للحصول على ID
    $stmt = $pdo->prepare("
        INSERT INTO recharge_requests 
        (user_id, amount, payment_method, account_number, transaction_message, status, created_at)
        VALUES (:user_id, :amount, :payment_method, :account_number, :message, 'pending', NOW())
    ");
    
    $stmt->execute([
        ':user_id' => $user['id'],
        ':amount' => $amount,
        ':payment_method' => $paymentMethod,
        ':account_number' => $accountNumber,
        ':message' => $message
    ]);
    
    $requestId = $pdo->lastInsertId();
    
    // 2. معالجة الصورة
    $uploadDir = __DIR__ . '/../../uploads/recharge_receipts';
    
    // إنشاء المجلد إذا لم يكن موجوداً
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // تنظيف الصور القديمة
    cleanupOldReceipts($uploadDir);
    
    $fileInfo = pathinfo($_FILES['receipt_image']['name']);
    $extension = strtolower($fileInfo['extension']);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $allowedExtensions)) {
        // حذف السجل الذي تم إنشاؤه لأن الصورة غير صالحة
        $pdo->exec("DELETE FROM recharge_requests WHERE id = $requestId");
        sendJsonResponse(false, null, 'نوع الملف غير مدعوم. يرجى رفع صورة (JPG, PNG, WEBP)', 400);
    }
    
    // تنسيق الاسم الجديد: ID_Date_UserID_Phone.ext
    // التاريخ بتنسيق Ymd (بدون فواصل ليسهل قراءته في اسم الملف)
    $dateStr = date('Ymd');
    // تنظيف رقم الهاتف لإزالة أي رموز، إذا كان يحتوي على حروف (instapay username) نستخدم regex مختلف
    // ولكن للأمان في اسم الملف، نسمح فقط بـ a-z0-9_-
    $cleanAccountNumber = preg_replace('/[^a-zA-Z0-9_-]/', '', $accountNumber);
    
    $newFileName = "{$requestId}_{$dateStr}_{$user['id']}_{$cleanAccountNumber}.{$extension}";
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
    
    if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
        
        // المسار النسبي للحفظ في قاعدة البيانات
        // نفترض أن uploads موجود في root الموقع
        $dbPath = "uploads/recharge_receipts/" . $newFileName;
        
        // 3. تحديث مسار الصورة في قاعدة البيانات
        $updateStmt = $pdo->prepare("UPDATE recharge_requests SET transaction_image = :image WHERE id = :id");
        $updateStmt->execute([
            ':image' => $dbPath,
            ':id' => $requestId
        ]);

        // 4. إرسال إشعار للمسؤولين
        $notifStmt = $pdo->prepare("
            INSERT INTO admin_notifications (type, message, reference_id)
            VALUES ('recharge_request', :message, :ref_id)
        ");
        $notifResult = $notifStmt->execute([
            ':message' => "طلب شحن جديد بقيمة {$amount} من {$user['full_name']}",
            ':ref_id' => $requestId
        ]);
        
        sendJsonResponse(true, [
            'message' => 'تم إرسال طلب الشحن بنجاح',
            'request_id' => $requestId
        ]);
        
    } else {
        // حذف السجل إذا فشل رفع الملف
        $pdo->exec("DELETE FROM recharge_requests WHERE id = $requestId");
        error_log("Failed to move uploaded file to: " . $targetPath);
        sendJsonResponse(false, null, 'حدث خطأ أثناء رفع الصورة', 500);
    }
    
} catch (PDOException $e) {
    error_log("Submit Recharge Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء حفظ الطلب', 500);
} catch (Exception $e) {
    error_log("Submit Recharge General Error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ غير متوقع', 500);
}
