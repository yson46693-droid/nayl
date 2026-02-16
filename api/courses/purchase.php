<?php
/**
 * ============================================
 * Purchase Course API
 * ============================================
 * شراء كورس بالرصيد: خصم الرصيد، إنشاء كود وصول مرتبط بالحساب فقط، وإنشاء اشتراك.
 * Endpoint: POST /api/courses/purchase.php
 * Body: { "course_id": 1 }
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
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, null, 'Method not allowed', 405);
}

$user = requireAuth();
if (!$user) {
    sendJsonResponse(false, null, 'يجب تسجيل الدخول لشراء الكورس', 401);
}

$userId = (int) $user['id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$courseId = isset($input['course_id']) ? (int) $input['course_id'] : 0;
$discountCodeInput = isset($input['discount_code']) ? trim((string) $input['discount_code']) : '';

if ($courseId <= 0) {
    sendJsonResponse(false, null, 'معرف الكورس مطلوب', 400);
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        sendJsonResponse(false, null, 'خطأ في الاتصال بقاعدة البيانات', 500);
    }

    // التحقق من وجود الكورس وأنه منشور وجلب السعر من قاعدة البيانات
    $courseStmt = $pdo->prepare("SELECT id, title, COALESCE(price, 500) AS price FROM courses WHERE id = :id AND status = 'published' LIMIT 1");
    $courseStmt->execute(['id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        sendJsonResponse(false, null, 'الكورس غير موجود أو غير متاح للشراء', 404);
    }

    $coursePrice = (float) $course['price'];
    if ($coursePrice < 0) {
        $coursePrice = 500.00;
    }

    $amountToCharge = $coursePrice;
    $discountAmount = 0.0;
    $discountCodeId = null;

    if ($discountCodeInput !== '') {
        $discountStmt = $pdo->prepare("
            SELECT id, discount_amount
            FROM discount_codes
            WHERE code = :code AND course_id = :course_id AND used_by IS NULL
            AND (assigned_to_user_id IS NULL OR assigned_to_user_id = :user_id)
            LIMIT 1
        ");
        $discountStmt->execute(['code' => $discountCodeInput, 'course_id' => $courseId, 'user_id' => $userId]);
        $discountRow = $discountStmt->fetch(PDO::FETCH_ASSOC);
        if (!$discountRow) {
            sendJsonResponse(false, null, 'كود الخصم غير صحيح أو مستخدم أو غير مرتبط بهذا الكورس أو مخصص لمستخدم آخر', 400);
        }
        $discountAmount = (float) $discountRow['discount_amount'];
        $discountCodeId = (int) $discountRow['id'];
        $amountToCharge = max(0, $coursePrice - $discountAmount);
    }

    // التحقق من عدم وجود اشتراك مسبق
    $subStmt = $pdo->prepare("SELECT 1 FROM user_course_subscriptions WHERE user_id = :user_id AND course_id = :course_id LIMIT 1");
    $subStmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
    if ($subStmt->fetch()) {
        sendJsonResponse(false, null, 'لديك اشتراك مسبق في هذا الكورس', 400);
    }

    // جلب رصيد المحفظة
    $walletStmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = :user_id LIMIT 1");
    $walletStmt->execute(['user_id' => $userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) {
        sendJsonResponse(false, null, 'المحفظة غير موجودة', 500);
    }
    $balanceBefore = (float) $wallet['balance'];
    if ($balanceBefore < $amountToCharge) {
        sendJsonResponse(false, null, 'رصيدك غير كافٍ لشراء هذا الكورس' . ($amountToCharge < $coursePrice ? ' (بعد الخصم: ' . number_format($amountToCharge, 2) . ' ج.م)' : ''), 400);
    }

    // توليد كود فريد (حروف وأرقام كبيرة، 10 أحرف) مع التأكد من عدم التطابق مع أي كود آخر
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxAttempts = 50;
    $code = '';
    $codeId = null;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $code = strtoupper(trim($code));
        if (strlen($code) !== 10) continue;

        $checkStmt = $pdo->prepare("SELECT 1 FROM course_codes WHERE code = :code LIMIT 1");
        $checkStmt->execute(['code' => $code]);
        if ($checkStmt->fetch()) continue;

        $pdo->beginTransaction();
        try {
            $codeStmt = $pdo->prepare("
                INSERT INTO course_codes (course_id, code, used_by, used_at, is_active, created_by)
                VALUES (:course_id, :code, :used_by, NOW(), 1, NULL)
            ");
            $codeStmt->execute([
                'course_id' => $courseId,
                'code' => $code,
                'used_by' => $userId
            ]);
            $codeId = (int) $pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                continue;
            }
            throw $e;
        }
    }

    if (!$codeId || strlen($code) !== 10) {
        sendJsonResponse(false, null, 'فشل توليد كود فريد، حاول مرة أخرى', 500);
    }

    try {

        // ربط الاشتراك بالكود المُسجّل (يظهر في صفحة أكوادي)
        $subInsertStmt = $pdo->prepare("
            INSERT INTO user_course_subscriptions (user_id, course_id, code_id, status)
            VALUES (:user_id, :course_id, :code_id, 'active')
        ");
        $subInsertStmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'code_id' => $codeId
        ]);

        // خصم المبلغ النهائي (بعد الخصم إن وُجد) من رصيد المحفظة
        $updateWalletStmt = $pdo->prepare("
            UPDATE wallet SET balance = balance - :amount WHERE user_id = :user_id AND balance >= :amount2
        ");
        $updateWalletStmt->execute([
            'amount' => $amountToCharge,
            'user_id' => $userId,
            'amount2' => $amountToCharge
        ]);
        if ($updateWalletStmt->rowCount() === 0) {
            throw new Exception('فشل خصم الرصيد');
        }

        $balanceAfter = $balanceBefore - $amountToCharge;

        $txTitle = 'شراء كورس: ' . $course['title'];
        if ($discountAmount > 0) {
            $txTitle .= ' (كود خصم: -' . number_format($discountAmount, 2) . ' ج.م)';
        }

        // تسجيل المعاملة في المحفظة
        $txStmt = $pdo->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, title, reference_id, reference_type, balance_before, balance_after)
            VALUES (:user_id, 'debit', :amount, :title, :ref_id, 'course', :bal_before, :bal_after)
        ");
        $txStmt->execute([
            'user_id' => $userId,
            'amount' => $amountToCharge,
            'title' => $txTitle,
            'ref_id' => (string) $courseId,
            'bal_before' => $balanceBefore,
            'bal_after' => $balanceAfter
        ]);

        // تعليم كود الخصم كمُستخدم (مرة واحدة لمستخدم واحد)
        if ($discountCodeId !== null) {
            $markUsedStmt = $pdo->prepare("
                UPDATE discount_codes SET used_by = :user_id, used_at = NOW() WHERE id = :id AND used_by IS NULL
            ");
            $markUsedStmt->execute(['user_id' => $userId, 'id' => $discountCodeId]);
            if ($markUsedStmt->rowCount() === 0) {
                throw new Exception('كود الخصم تم استخدامه من قبل مستخدم آخر');
            }
        }

        // ترقية الحساب من مجاني إلى VIP بعد شراء أي كورس (إن وُجد العمود)
        try {
            $upgradeStmt = $pdo->prepare("UPDATE users SET account_type = 'vip' WHERE id = :user_id");
            $upgradeStmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            // العمود account_type غير موجود بعد - نكمل دون ترقية
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Purchase course transaction error: " . $e->getMessage());
        sendJsonResponse(false, null, $e->getMessage() ?: 'فشلت عملية الشراء', 500);
    }

    sendJsonResponse(true, [
        'code' => $code,
        'course_id' => (int) $courseId,
        'course_title' => $course['title'],
        'amount' => $amountToCharge,
        'discount_applied' => $discountAmount > 0,
        'discount_amount' => $discountAmount,
        'account_type' => 'vip'
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Purchase course error: " . $e->getMessage());
    sendJsonResponse(false, null, 'حدث خطأ أثناء تنفيذ الشراء', 500);
}
