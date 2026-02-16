<?php
/**
 * ============================================
 * Email Helper
 * ============================================
 * إرسال رسائل البريد الإلكتروني
 * يستخدم PHP mail() أو SMTP حسب الإعداد
 */

require_once __DIR__ . '/env.php';

/**
 * إرسال بريد استعادة كلمة المرور
 * @param string $to - البريد الإلكتروني للمستلم
 * @param string $resetLink - رابط استعادة كلمة المرور
 * @param string $userName - اسم المستخدم (اختياري)
 * @return bool - نجاح الإرسال
 */
function sendPasswordResetEmail($to, $resetLink, $userName = '') {
    $appName = 'AmrNayl Academy';
    $subject = 'استعادة كلمة المرور - ' . $appName;
    
    $greeting = $userName ? "مرحباً {$userName}," : 'مرحباً،';
    
    $body = "
<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .logo { font-size: 1.5rem; font-weight: bold; color: #4a90e2; margin-bottom: 20px; }
        h1 { color: #1a2332; font-size: 1.25rem; margin: 0 0 15px; }
        p { color: #2c3e50; line-height: 1.7; margin: 0 0 15px; font-size: 0.95rem; }
        .btn { display: inline-block; background: #4a90e2; color: #fff !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 15px 0; }
        .btn:hover { background: #3a7bc8; }
        .link-note { font-size: 0.85rem; color: #8b95a5; word-break: break-all; }
        .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.85rem; color: #8b95a5; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='logo'>{$appName}</div>
        <h1>استعادة كلمة المرور</h1>
        <p>{$greeting}</p>
        <p>طلبتم إعادة تعيين كلمة المرور لحسابكم. انقر على الزر أدناه لتعيين كلمة مرور جديدة:</p>
        <p><a href='{$resetLink}' class='btn'>تعيين كلمة مرور جديدة</a></p>
        <p class='link-note'>إن لم يعمل الزر، انسخ الرابط التالي إلى المتصفح:<br>{$resetLink}</p>
        <p>رابط الاستعادة صالح لمدة ساعة واحدة فقط.</p>
        <div class='footer'>إن لم تكن أنت من طلب هذا التغيير، تجاهل هذه الرسالة.</div>
    </div>
</body>
</html>
";

    return sendEmail($to, $subject, $body);
}

/**
 * إرسال بريد إلكتروني عام
 * @param string $to - البريد الإلكتروني للمستلم
 * @param string $subject - موضوع الرسالة
 * @param string $bodyHtml - محتوى HTML للرسالة
 * @return bool
 */
function sendEmail($to, $subject, $bodyHtml) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . getMailFrom(),
        'Reply-To: ' . getMailFrom(),
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    return @mail($to, $encodedSubject, $bodyHtml, implode("\r\n", $headers));
}

/**
 * الحصول على عنوان المرسل
 * @return string
 */
function getMailFrom() {
    $fromEmail = function_exists('env') ? env('MAIL_FROM_EMAIL', 'noreply@amrnayl.com') : 'noreply@amrnayl.com';
    $fromName = function_exists('env') ? env('MAIL_FROM_NAME', 'AmrNayl Academy') : 'AmrNayl Academy';
    return $fromName . ' <' . $fromEmail . '>';
}
