<?php
// test-db.php في مجلد api/
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/api/config/database.php';
    
    // التحقق من تحميل الإعدادات
    echo "<h3>إعدادات قاعدة البيانات:</h3>";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'غير محدد') . "<br>";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'غير محدد') . "<br>";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'غير محدد') . "<br>";
    echo "DB_PORT: " . (defined('DB_PORT') ? DB_PORT : '3306') . "<br>";
    echo "<hr>";
    
    // محاولة الاتصال بدون قاعدة البيانات أولاً للتحقق من أن MySQL يعمل
    echo "<h4>الخطوة 1: التحقق من اتصال MySQL...</h4>";
    try {
        $dsn = "mysql:host=" . DB_HOST;
        if (defined('DB_PORT') && DB_PORT && DB_PORT !== '3306') {
            $dsn .= ";port=" . DB_PORT;
        }
        $testPdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "<p style='color: green;'>✅ الاتصال بـ MySQL نجح!</p>";
        
        // التحقق من وجود قاعدة البيانات
        echo "<h4>الخطوة 2: التحقق من وجود قاعدة البيانات '" . DB_NAME . "'...</h4>";
        $stmt = $testPdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        $dbExists = $stmt->fetch();
        
        if (!$dbExists) {
            echo "<p style='color: orange;'>⚠️ قاعدة البيانات '" . DB_NAME . "' غير موجودة</p>";
            echo "<p>محاولة إنشاء قاعدة البيانات...</p>";
            
            try {
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<p style='color: green;'>✅ تم إنشاء قاعدة البيانات '" . DB_NAME . "' بنجاح!</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ فشل إنشاء قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p>الخطأ: " . htmlspecialchars($e->getCode()) . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ قاعدة البيانات '" . DB_NAME . "' موجودة</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ فشل الاتصال بـ MySQL</p>";
        echo "<p><strong>رسالة الخطأ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>رمز الخطأ:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
        echo "<hr>";
        echo "<h4>الحلول المقترحة:</h4>";
        echo "<ul>";
        echo "<li>تأكد من أن خدمة MySQL/MariaDB تعمل</li>";
        echo "<li>تحقق من إعدادات DB_HOST و DB_PORT في ملف .env</li>";
        echo "<li>تحقق من اسم المستخدم وكلمة المرور</li>";
        echo "<li>إذا كنت تستخدم XAMPP/WAMP، تأكد من تشغيل MySQL من لوحة التحكم</li>";
        echo "</ul>";
        exit;
    }
    
    // الآن محاولة الاتصال بقاعدة البيانات
    echo "<h4>الخطوة 3: الاتصال بقاعدة البيانات '" . DB_NAME . "'...</h4>";
    $pdo = getDatabaseConnection();
    
    if ($pdo) {
        echo "<h2 style='color: green;'>✅ الاتصال بقاعدة البيانات نجح!</h2>";
        
        // اختبار استعلام بسيط
        try {
            $stmt = $pdo->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            echo "<p>إصدار MySQL: " . htmlspecialchars($result['version']) . "</p>";
            
            // عرض عدد الجداول
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>عدد الجداول: " . count($tables) . "</p>";
            if (count($tables) > 0) {
                echo "<p>الجداول: " . implode(', ', $tables) . "</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ الاتصال نجح لكن الاستعلام فشل: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<h2 style='color: red;'>❌ فشل الاتصال بقاعدة البيانات</h2>";
        
        // محاولة الحصول على رسالة الخطأ من خلال محاولة اتصال مباشرة
        try {
            $dsn = "mysql:host=" . DB_HOST;
            if (defined('DB_PORT') && DB_PORT && DB_PORT !== '3306') {
                $dsn .= ";port=" . DB_PORT;
            }
            $dsn .= ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $testPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (PDOException $e) {
            echo "<p><strong>رسالة الخطأ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>رمز الخطأ:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
        }
        
        echo "<p>تحقق من:</p>";
        echo "<ul>";
        echo "<li>أن MySQL/MariaDB يعمل</li>";
        echo "<li>أن إعدادات قاعدة البيانات في ملف .env صحيحة</li>";
        echo "<li>أن قاعدة البيانات '" . (defined('DB_NAME') ? DB_NAME : 'غير محدد') . "' موجودة</li>";
        echo "<li>أن المستخدم '" . (defined('DB_USER') ? DB_USER : 'غير محدد') . "' لديه صلاحيات</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ خطأ في تحميل الملفات:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>الملف: " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p>السطر: " . $e->getLine() . "</p>";
}