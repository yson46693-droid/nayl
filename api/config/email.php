<?php
/**
 * ============================================
 * Email Helper
 * ============================================
 * إرسال رسائل البريد الإلكتروني
 * يستخدم SMTP إذا وُجدت إعدادات SMTP في .env، وإلا PHP mail()
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
    $appName = function_exists('env') ? (env('APP_NAME', '') ?: env('MAIL_FROM_NAME', 'AmrNayl Academy')) : 'AmrNayl Academy';
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
        .container-app { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
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
    <div class='container-app'>
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
 * يستخدم SMTP إذا وُجدت SMTP_HOST و SMTP_USERNAME و SMTP_PASSWORD، وإلا mail()
 * @param string $to - البريد الإلكتروني للمستلم
 * @param string $subject - موضوع الرسالة
 * @param string $bodyHtml - محتوى HTML للرسالة
 * @return bool
 */
function sendEmail($to, $subject, $bodyHtml) {
    if (isSmtpConfigured()) {
        return sendEmailViaSmtp($to, $subject, $bodyHtml);
    }
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
 * التحقق من وجود إعدادات SMTP
 * @return bool
 */
function isSmtpConfigured() {
    if (!function_exists('env')) {
        return false;
    }
    $host = env('SMTP_HOST', '');
    $user = env('SMTP_USERNAME', '');
    $pass = env('SMTP_PASSWORD', '');
    return $host !== '' && $user !== '' && $pass !== '';
}

/**
 * إرسال بريد عبر SMTP (اتصال TLS على المنفذ 587 أو SSL على 465)
 * @param string $to - البريد الإلكتروني للمستلم
 * @param string $subject - موضوع الرسالة
 * @param string $bodyHtml - محتوى HTML للرسالة
 * @return bool
 */
function sendEmailViaSmtp($to, $subject, $bodyHtml) {
    $host = trim(env('SMTP_HOST', ''));
    $port = (int) env('SMTP_PORT', 587);
    $encryption = strtolower(trim(env('SMTP_ENCRYPTION', 'tls')));
    $username = trim(env('SMTP_USERNAME', ''));
    $password = env('SMTP_PASSWORD', '');
    $fromEmail = trim(env('MAIL_FROM_EMAIL', $username));
    $fromName = trim(env('MAIL_FROM_NAME', 'AmrNayl Academy'));
    if ($fromEmail === '') {
        $fromEmail = $username;
    }

    $useSsl = ($port === 465 || $encryption === 'ssl');
    $scheme = $useSsl ? 'ssl' : 'tcp';
    $addr = $scheme . '://' . $host . ':' . $port;
    $timeout = 15;
    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client(
        $addr,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
    );
    if (!is_resource($socket)) {
        error_log("SMTP connection failed: {$addr} ({$errno}) {$errstr}");
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $read = function () use ($socket) {
        $line = @fgets($socket, 512);
        return $line === false ? '' : trim($line);
    };
    $expect = function ($code) use ($socket, $read) {
        $line = $read();
        $got = substr($line, 0, 3);
        while (strlen($line) >= 4 && $line[3] === '-') {
            $line = $read();
            $got = substr($line, 0, 3);
        }
        return (int) $got === (int) $code;
    };
    $send = function ($cmd) use ($socket) {
        return @fwrite($socket, $cmd . "\r\n") !== false;
    };

    if (!$expect(220)) {
        fclose($socket);
        return false;
    }

    $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if (!$expect(250)) {
        fclose($socket);
        return false;
    }

    if (!$useSsl && ($port === 587 || $encryption === 'tls')) {
        if (!$send('STARTTLS') || !$expect(220)) {
            fclose($socket);
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect(250)) {
            fclose($socket);
            return false;
        }
    }

    $send('AUTH LOGIN');
    if (!$expect(334)) {
        fclose($socket);
        return false;
    }
    $send(base64_encode($username));
    if (!$expect(334)) {
        fclose($socket);
        return false;
    }
    $send(base64_encode($password));
    if (!$expect(235)) {
        fclose($socket);
        return false;
    }

    $send('MAIL FROM:<' . $fromEmail . '>');
    if (!$expect(250)) {
        fclose($socket);
        return false;
    }
    $send('RCPT TO:<' . $to . '>');
    if (!$expect(250)) {
        fclose($socket);
        return false;
    }
    $send('DATA');
    if (!$expect(354)) {
        fclose($socket);
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHeader = $fromName . ' <' . $fromEmail . '>';
    $bodyEscaped = preg_replace('/^\./m', '..', $bodyHtml);
    $data = "From: {$fromHeader}\r\nTo: {$to}\r\nSubject: {$encodedSubject}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $bodyEscaped . "\r\n.";
    if (!@fwrite($socket, $data . "\r\n")) {
        fclose($socket);
        return false;
    }
    if (!$expect(250)) {
        fclose($socket);
        return false;
    }
    $send('QUIT');
    fclose($socket);
    return true;
}

/**
 * الحصول على عنوان المرسل
 * @return string
 */
function getMailFrom() {
    $fromEmail = function_exists('env') ? env('MAIL_FROM_EMAIL', '') : '';
    $fromName = function_exists('env') ? env('MAIL_FROM_NAME', '') : '';
    if ($fromEmail === '') {
        $fromEmail = 'noreply@localhost';
    }
    if ($fromName === '') {
        $fromName = 'AmrNayl Academy';
    }
    return $fromName . ' <' . $fromEmail . '>';
}
