<?php
/**
 * فحص امتداد OpenSSL ومسار php.ini (لتسجيل الدخول بالبصمة)
 * افتح في المتصفح: http://localhost:8000/check-openssl.php
 */
header('Content-Type: text/html; charset=utf-8');

$loaded = extension_loaded('openssl');
$ini = php_ini_loaded_file();
$scanned = php_ini_scanned_files();

echo '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>فحص OpenSSL</title>';
echo '<style>body{font-family:Cairo,sans-serif;max-width:600px;margin:2rem auto;padding:1rem;background:#f5f7fa;}';
echo '.ok{color:#4CAF50;}.no{color:#dc3545;}code{background:#e0e6ed;padding:2px 6px;border-radius:4px;}';
echo 'ol{line-height:1.8;}pre{background:#1a2332;color:#fff;padding:1rem;border-radius:8px;overflow:auto;}</style></head><body>';

echo '<h1>فحص امتداد OpenSSL</h1>';

if ($loaded) {
    echo '<p class="ok"><strong>✓ امتداد OpenSSL مفعّل.</strong> تسجيل الدخول بالبصمة يعمل.</p>';
} else {
    echo '<p class="no"><strong>✗ امتداد OpenSSL غير مفعّل.</strong> اتبع الخطوات التالية:</p>';
    echo '<ol>';
    echo '<li>افتح ملف <strong>php.ini</strong>.';
    if ($ini) {
        echo ' المسار: <code>' . htmlspecialchars($ini) . '</code>';
    } else {
        echo ' ابحث عن الملف (غالباً داخل مجلد PHP أو في C:\php).';
    }
    echo '</li>';
    echo '<li>ابحث عن السطر: <code>;extension=openssl</code></li>';
    echo '<li>احذف الفاصلة المنقوطة <code>;</code> من بداية السطر ليصبح: <code>extension=openssl</code></li>';
    echo '<li>احفظ الملف (Ctrl+S) ثم <strong>أغلق نافذة الطرفية بالكامل</strong> وافتحها من جديد وشغّل <code>start-server.bat</code>.</li>';
    echo '</ol>';
    if ($ini && is_readable($ini)) {
        $content = file_get_contents($ini);
        $lines = preg_split('/\r?\n/', $content);
        $opensslLines = [];
        foreach ($lines as $i => $line) {
            if (stripos($line, 'openssl') !== false) {
                $opensslLines[] = ['num' => $i + 1, 'text' => $line];
            }
        }
        if (!empty($opensslLines)) {
            echo '<p><strong>السطور الحالية في php.ini التي تحتوي على openssl:</strong></p><pre>';
            foreach ($opensslLines as $l) {
                $highlight = (strpos(ltrim($l['text']), ';') === 0) ? ' ← معطّل (احذف ; من البداية)' : ' ← مفعّل';
                echo htmlspecialchars($l['text']) . $highlight . "\n";
            }
            echo '</pre><p>إذا كان السطر يبدأ بـ <code>;</code> فهو معطّل. احذف <code>;</code> واحفظ ثم أعد تشغيل الخادم.</p>';
        }
    }
}

echo '<p><small>ملف php.ini الحالي: <code>' . ($ini ?: 'غير معروف') . '</code></small></p>';
echo '</body></html>';
