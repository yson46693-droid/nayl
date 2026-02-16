<?php
/**
 * ============================================
 * Environment Variables Loader
 * ============================================
 * تحميل متغيرات البيئة من ملف .env
 */

/**
 * تحميل متغيرات البيئة من ملف .env
 * @param string $envFile - مسار ملف .env
 * @return bool
 */
function loadEnv($envFile = null) {
    if ($envFile === null) {
        $envFile = __DIR__ . '/../.env';
    }
    
    if (!file_exists($envFile)) {
        error_log("Environment file not found: " . $envFile);
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // تجاهل التعليقات
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // تجاهل الأسطر الفارغة
        if (empty(trim($line))) {
            continue;
        }
        
        // تقسيم السطر إلى key و value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // إزالة علامات الاقتباس إذا كانت موجودة
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // تعيين المتغير إذا لم يكن موجوداً بالفعل
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    return true;
}

/**
 * الحصول على متغير بيئة
 * @param string $key - اسم المتغير
 * @param mixed $default - القيمة الافتراضية
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // تحويل القيم النصية إلى boolean
    if (strtolower($value) === 'true') {
        return true;
    }
    
    if (strtolower($value) === 'false') {
        return false;
    }
    
    // تحويل القيم النصية إلى null
    if (strtolower($value) === 'null') {
        return null;
    }
    
    return $value;
}
