<?php
/**
 * ============================================
 * Update Recharge Request Status API (Admin)
 * ============================================
 * API لتحديث حالة طلبات تعبئة الرصيد من لوحة التحكم
 * - عند الموافقة يتم إضافة المبلغ إلى محفظة المستخدم مع تسجيل معاملة في جدول المحفظة
 *
 * Endpoint: POST /api/admin/update-recharge-request.php
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل الملفات المطلوبة
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

loadEnv(__DIR__ . '/../../.env');

// إعدادات CORS
$allowedOrigin = getAllowedOrigin();
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// معالجة OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// التحقق من صلاحيات الأدمن
$admin = requireAdminAuth(true);
if (!$admin) {
    sendJsonResponse(false, null, 'غير مصرح لك بالوصول. يرجى تسجيل الدخول كأدمن.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method Not Allowed', 405);
}

// استلام البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJsonResponse(false, null, 'Invalid JSON data', 400);
}

// يمكن أن يأتي المعرف باسم request_id أو id
$requestId = isset($input['request_id'])
    ? (int)$input['request_id']
    : (isset($input['id']) ? (int)$input['id'] : 0);

$status = isset($input['status']) ? sanitizeInput($input['status']) : '';
$adminNotes = isset($input['admin_notes']) ? sanitizeInput($input['admin_notes']) : null;

// يمكن مستقبلاً دعم تعديل المبلغ من لوحة التحكم، لذلك نقرأه اختيارياً
$amountOverride = null;
if (isset($input['amount']) && $input['amount'] !== null && $input['amount'] !== '') {
    $amountOverride = (float)$input['amount'];
}

if ($requestId <= 0) {
    sendJsonResponse(false, null, 'معرف طلب الشحن مطلوب', 400);
}

if (empty($status)) {
    sendJsonResponse(false, null, 'حالة الطلب مطلوبة', 400);
}

$validStatuses = ['approved', 'rejected', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    sendJsonResponse(false, null, 'حالة غير صحيحة للطلب', 400);
}

try {
    $pdo = getDatabaseConnection();

    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    // بدء معاملة لضمان اتساق البيانات
    $pdo->beginTransaction();

    // قفل سجل الطلب أثناء المعالجة
    $stmt = $pdo->prepare("
        SELECT *
        FROM recharge_requests
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'لم يتم العثور على طلب الشحن', 404);
    }

    // لا نسمح بمعالجة الطلب أكثر من مرة
    if ($request['status'] !== 'pending') {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'تم معالجة هذا الطلب مسبقاً ولا يمكن تغييره', 400);
    }

    $userId = (int)$request['user_id'];
    $originalAmount = (float)$request['amount'];

    // التأكد من أن المبلغ صالح
    if ($amountOverride !== null && $amountOverride <= 0) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'قيمة المبلغ غير صحيحة', 400);
    }

    // المبلغ الذي سيتم اعتماده وإضافته للمحفظة
    $finalAmount = $amountOverride !== null ? $amountOverride : $originalAmount;

    if ($status === 'approved') {
        // قفل المحفظة الخاصة بالمستخدم
        $walletStmt = $pdo->prepare("
            SELECT id, balance
            FROM wallet
            WHERE user_id = :user_id
            FOR UPDATE
        ");
        $walletStmt->execute([':user_id' => $userId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            // في حالة عدم وجود محفظة (حالة نادرة لأن هناك trigger ينشئها)، ننشئ واحدة برصيد 0
            $createWallet = $pdo->prepare("
                INSERT INTO wallet (user_id, balance)
                VALUES (:user_id, 0.00)
            ");
            $createWallet->execute([':user_id' => $userId]);

            $walletId = (int)$pdo->lastInsertId();
            $balanceBefore = 0.00;
        } else {
            $walletId = (int)$wallet['id'];
            $balanceBefore = (float)$wallet['balance'];
        }

        $balanceAfter = $balanceBefore + $finalAmount;

        // تحديث رصيد المحفظة
        $updateWallet = $pdo->prepare("
            UPDATE wallet
            SET balance = :balance
            WHERE id = :id
        ");
        $updateWallet->execute([
            ':balance' => $balanceAfter,
            ':id' => $walletId
        ]);

        // تسجيل معاملة في جدول معاملات المحفظة
        $transactionTitle = 'شحن رصيد المحفظة';
        $transactionDescription = 'تمت الموافقة على طلب شحن رقم ' . $requestId;
        if ($adminNotes) {
            $transactionDescription .= ' - ملاحظات الأدمن: ' . $adminNotes;
        }

        $insertTransaction = $pdo->prepare("
            INSERT INTO wallet_transactions (
                user_id,
                type,
                amount,
                title,
                description,
                reference_id,
                reference_type,
                balance_before,
                balance_after
            ) VALUES (
                :user_id,
                'credit',
                :amount,
                :title,
                :description,
                :reference_id,
                'recharge',
                :balance_before,
                :balance_after
            )
        ");

        $insertTransaction->execute([
            ':user_id' => $userId,
            ':amount' => $finalAmount,
            ':title' => $transactionTitle,
            ':description' => $transactionDescription,
            ':reference_id' => (string)$requestId,
            ':balance_before' => $balanceBefore,
            ':balance_after' => $balanceAfter
        ]);

        // تحديث سجل طلب الشحن
        $updateRequest = $pdo->prepare("
            UPDATE recharge_requests
            SET status = 'approved',
                amount = :amount,
                admin_notes = :admin_notes,
                processed_by = :processed_by,
                processed_at = NOW()
            WHERE id = :id
        ");

        $updateRequest->execute([
            ':amount' => $finalAmount,
            ':admin_notes' => $adminNotes,
            ':processed_by' => $admin['id'],
            ':id' => $requestId
        ]);

    } else {
        // في حالة الرفض أو الإلغاء لا يتم تعديل المحفظة، فقط تحديث حالة الطلب
        $updateRequest = $pdo->prepare("
            UPDATE recharge_requests
            SET status = :status,
                admin_notes = :admin_notes,
                processed_by = :processed_by,
                processed_at = NOW()
            WHERE id = :id
        ");

        $updateRequest->execute([
            ':status' => $status,
            ':admin_notes' => $adminNotes,
            ':processed_by' => $admin['id'],
            ':id' => $requestId
        ]);
    }

    $pdo->commit();

    $responseData = [
        'request_id' => $requestId,
        'status' => $status,
        'amount' => $finalAmount,
        'message' => $status === 'approved'
            ? 'تم قبول الطلب وتم إضافة الرصيد إلى المحفظة بنجاح'
            : 'تم تحديث حالة الطلب بنجاح'
    ];

    sendJsonResponse(true, $responseData, null, 200);

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin Update Recharge Request Error: ' . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تحديث طلب الشحن', 500);
}

