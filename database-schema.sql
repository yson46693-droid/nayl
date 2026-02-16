-- ============================================
-- قاعدة بيانات AmrNayl Academy
-- ملف Schema الوحيد - جميع التعديلات هنا
-- ============================================
-- 
-- ملاحظات مهمة:
-- 1. هذا هو الملف الوحيد لتعديل schema قاعدة البيانات
-- 2. عند إضافة أي جدول أو تعديل، يتم التعديل هنا فقط
-- 3. استخدم migrations عند التطبيق على قاعدة البيانات الفعلية
-- 4. جميع كلمات المرور يجب hashing قبل الحفظ (bcrypt/argon2)
-- ============================================

-- إنشاء قاعدة البيانات (اختياري - يمكن استخدام قاعدة موجودة)
-- CREATE DATABASE IF NOT EXISTS amrnayl_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE amrnayl_academy;

-- ============================================
-- جدول المستخدمين
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED PRIMARY KEY,
    
    -- المعلومات الشخصية
    full_name VARCHAR(255) NULL COMMENT 'الاسم الكامل',
    profile_image VARCHAR(255) NULL COMMENT 'صورة الملف الشخصي',
    
    -- الاتصال
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'البريد الإلكتروني',
    phone VARCHAR(20) NOT NULL COMMENT 'رقم الهاتف',
    country_code VARCHAR(5) NOT NULL DEFAULT '+20' COMMENT 'كود الدولة',
    full_phone VARCHAR(25) NOT NULL UNIQUE COMMENT 'الرقم بالكامل',
    
    -- الموقع
    country VARCHAR(100) NOT NULL COMMENT 'الدولة',
    city VARCHAR(100) NOT NULL COMMENT 'المدينة',
    
    -- الأمان
    password_hash VARCHAR(255) NOT NULL COMMENT 'كلمة المرور',
    
    -- الحالة
    is_active BOOLEAN DEFAULT TRUE COMMENT 'نشط',
    is_verified BOOLEAN DEFAULT FALSE COMMENT 'تم التحقق',
    whatsapp_verified BOOLEAN DEFAULT FALSE COMMENT 'واتساب مفعل',
    account_type ENUM('free', 'vip') NOT NULL DEFAULT 'free' COMMENT 'نوع الحساب: مجاني أو VIP (يُحدّث تلقائياً عند شراء أي كورس)',
    
    -- الشروط
    terms_accepted BOOLEAN DEFAULT FALSE COMMENT 'موافق على الشروط',
    terms_accepted_at DATETIME NULL COMMENT 'تاريخ الموافقة',
    
    -- التواريخ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL COMMENT 'آخر دخول',
    deleted_at DATETIME NULL,
    
    INDEX idx_email (email),
    INDEX idx_full_phone (full_phone),
    INDEX idx_full_name (full_name),
    INDEX idx_country (country),
    INDEX idx_city (city),
    INDEX idx_created_at (created_at),
    INDEX idx_is_active (is_active),
    INDEX idx_deleted_at (deleted_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='المستخدمين';

-- جدول الجلسات
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE COMMENT 'التوكن',
    ip_address VARCHAR(45) NULL COMMENT 'IP',
    user_agent TEXT NULL COMMENT 'المتصفح',
    expires_at DATETIME NOT NULL COMMENT 'الانتهاء',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='الجلسات';

-- محاولات الدخول - الحظر حسب الجهاز (device_uuid) وليس IP
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_uuid VARCHAR(36) NOT NULL COMMENT 'معرف الجهاز (UUID من localStorage/cookies)',
    email VARCHAR(255) NULL COMMENT 'البريد',
    full_phone VARCHAR(25) NULL COMMENT 'الهاتف',
    ip_address VARCHAR(45) NULL COMMENT 'IP - للتسجيل فقط وليس للحظر',
    attempt_count INT UNSIGNED DEFAULT 1 COMMENT 'العدد',
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'آخر محاولة',
    locked_until DATETIME NULL COMMENT 'قفل حتى',
    
    INDEX idx_device_uuid (device_uuid),
    INDEX idx_email (email),
    INDEX idx_full_phone (full_phone),
    INDEX idx_locked_until (locked_until)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='محاولات الدخول - حظر حسب الجهاز';

-- رموز التحقق
CREATE TABLE IF NOT EXISTS verification_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE COMMENT 'الرمز',
    token_type ENUM('email_verification', 'password_reset', 'phone_verification') NOT NULL COMMENT 'النوع',
    expires_at DATETIME NOT NULL COMMENT 'الانتهاء',
    used_at DATETIME NULL COMMENT 'تاريخ الاستخدام',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_token_type (token_type),
    INDEX idx_expires_at (expires_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='رموز التحقق';

-- جدول بيانات WebAuthn (البصمة / مفتاح المرور) لتسجيل الدخول
CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    credential_id VARCHAR(512) NOT NULL COMMENT 'معرف البصمة (base64url)',
    public_key_x VARCHAR(256) NULL COMMENT 'مفتاح عام X (P-256)',
    public_key_y VARCHAR(256) NULL COMMENT 'مفتاح عام Y (P-256)',
    sign_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عداد التوقيع',
    device_name VARCHAR(255) NULL COMMENT 'اسم الجهاز للمستخدم',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_credential_id (credential_id(255)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='بيانات WebAuthn لتسجيل الدخول بالبصمة';

-- ============================================
-- جداول المحفظة
-- ============================================

-- جدول المحفظة (رصيد كل مستخدم)
CREATE TABLE IF NOT EXISTS wallet (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE COMMENT 'معرف المستخدم',
    balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_balance (balance)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='محافظ المستخدمين';

-- جدول معاملات المحفظة
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    type ENUM('credit', 'debit') NOT NULL COMMENT 'النوع: credit (إضافة) أو debit (خصم)',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'المبلغ',
    title VARCHAR(255) NOT NULL COMMENT 'عنوان المعاملة',
    description TEXT NULL COMMENT 'وصف إضافي',
    reference_id VARCHAR(255) NULL COMMENT 'معرف مرجعي (مثل معرف الكورس عند الشراء)',
    reference_type VARCHAR(50) NULL COMMENT 'نوع المرجع (course, recharge, refund, etc.)',
    balance_before DECIMAL(10, 2) NOT NULL COMMENT 'الرصيد قبل المعاملة',
    balance_after DECIMAL(10, 2) NOT NULL COMMENT 'الرصيد بعد المعاملة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_reference (reference_type, reference_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='معاملات المحفظة';

-- جدول طلبات تعبئة الرصيد
CREATE TABLE IF NOT EXISTS recharge_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'المبلغ المطلوب',
    payment_method VARCHAR(50) NOT NULL COMMENT 'طريقة الدفع (instapay, vodafone_cash, etc.)',
    account_number VARCHAR(255) NULL COMMENT 'رقم الحساب / اسم المستخدم',
    transaction_image VARCHAR(500) NULL COMMENT 'رابط صورة إيصال التحويل',
    transaction_message TEXT NULL COMMENT 'رسالة التحويل / رقم العملية',
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending' COMMENT 'الحالة',
    admin_notes TEXT NULL COMMENT 'ملاحظات الإدارة',
    processed_by INT UNSIGNED NULL COMMENT 'معرف المشرف الذي قام بالمعالجة',
    processed_at DATETIME NULL COMMENT 'تاريخ المعالجة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_payment_method (payment_method)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='طلبات تعبئة الرصيد';

-- Trigger: إنشاء محفظة تلقائياً عند إنشاء مستخدم جديد
DELIMITER //
CREATE TRIGGER IF NOT EXISTS create_wallet_on_user_insert 
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO wallet (user_id, balance) 
    VALUES (NEW.id, 0.00)
    ON DUPLICATE KEY UPDATE balance = balance;
END //
DELIMITER ;

-- Views
CREATE OR REPLACE VIEW active_users AS
SELECT 
    id, full_name, email, full_phone, country, city, is_verified, whatsapp_verified, created_at, last_login_at
FROM users
WHERE is_active = TRUE AND deleted_at IS NULL;

-- Procedures

-- تنظيف المستخدمين المحذوفين
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_deleted_users()
BEGIN
    DELETE FROM users 
    WHERE deleted_at IS NOT NULL 
      AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END //
DELIMITER ;

-- تنظيف الجلسات
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_sessions()
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- تنظيف الرموز
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_tokens()
BEGIN
    DELETE FROM verification_tokens 
    WHERE expires_at < NOW() 
       OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 7 DAY));
END //
DELIMITER ;

-- Triggers

-- تحديث الهاتف الكامل
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_full_phone 
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.phone != OLD.phone OR NEW.country_code != OLD.country_code THEN
        SET NEW.full_phone = CONCAT(NEW.country_code, NEW.phone);
    END IF;
END //
DELIMITER ;

-- تعيين الهاتف الكامل عند الإضافة
DELIMITER //
CREATE TRIGGER IF NOT EXISTS set_full_phone_on_insert 
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.full_phone IS NULL OR NEW.full_phone = '' THEN
        SET NEW.full_phone = CONCAT(NEW.country_code, NEW.phone);
    END IF;
END //
DELIMITER ;

-- ============================================
-- جدول الشكاوي
-- ============================================

CREATE TABLE IF NOT EXISTS complaints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    subject VARCHAR(255) NOT NULL COMMENT 'عنوان الشكوى',
    message TEXT NOT NULL COMMENT 'نص الشكوى',
    status ENUM('open', 'replied', 'closed') DEFAULT 'open' COMMENT 'حالة الشكوى',
    admin_reply TEXT NULL COMMENT 'رد الإدارة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='الشكاوي والمقترحات';

-- ============================================
-- جداول لوحة التحكم (Admins)
-- ============================================

-- جدول المشرفين
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'اسم المستخدم',
    full_name VARCHAR(100) NULL COMMENT 'الاسم الكامل',
    email VARCHAR(255) NULL UNIQUE COMMENT 'البريد الإلكتروني',
    password_hash VARCHAR(255) NOT NULL COMMENT 'كلمة المرور',
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin' COMMENT 'الصلاحية',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'نشط',
    last_login_at DATETIME NULL COMMENT 'آخر دخول',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='المشرفين';

-- جدول جلسات المشرفين (جلسة تتجدد وتُحذف بعد الخمول لأمان أفضل)
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE COMMENT 'التوكن',
    ip_address VARCHAR(45) NULL COMMENT 'IP',
    user_agent TEXT NULL COMMENT 'المتصفح',
    expires_at DATETIME NOT NULL COMMENT 'الانتهاء المطلق',
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'آخر نشاط - انتهاء الجلسة بعد الخمول',
    last_rotated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'آخر تجديد للتوكن',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_activity_at (last_activity_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='جلسات المشرفين';

-- للقواعد الموجودة مسبقاً: إضافة أعمدة الجلسة الآمنة (شغّل مرة واحدة)
-- ALTER TABLE admin_sessions ADD COLUMN last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'آخر نشاط' AFTER expires_at;
-- ALTER TABLE admin_sessions ADD COLUMN last_rotated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'آخر تجديد للتوكن' AFTER last_activity_at;
-- ALTER TABLE admin_sessions ADD INDEX idx_last_activity_at (last_activity_at);

-- INSERT INTO admins (username, password_hash) VALUES ('admin', '$2y$10$YourHashedPasswordHere');

-- ============================================
-- جدول إشعارات المسؤولين
-- ============================================
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL COMMENT 'نوع الإشعار (recharge_request, new_user, etc)',
    message TEXT NOT NULL COMMENT 'نص الإشعار',
    reference_id INT UNSIGNED NULL COMMENT 'معرف السجل المرتبط (user_id, request_id)',
    is_read BOOLEAN DEFAULT FALSE COMMENT 'هل تمت القراءة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='إشعارات لوحة التحكم';

-- ============================================
-- جدول زيارات الموقع
-- ============================================
CREATE TABLE IF NOT EXISTS site_visits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    v_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'معرف الزائر (Device UUID)',
    visit_count INT UNSIGNED DEFAULT 1 COMMENT 'عدد مرات الزيارة',
    last_visit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'آخر زيارة',
    user_agent TEXT NULL COMMENT 'معلومات المتصفح',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_v_id (v_id),
    INDEX idx_last_visit_at (last_visit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='زيارات الموقع وأجهزة الزوار';

-- بصمة الجهاز (Device Fingerprint) - تُنشأ مرة واحدة ولا تُعاد
-- الهاش من بيانات ثابتة (شاشة، متصفح، canvas) بتشفير SHA-256
CREATE TABLE IF NOT EXISTS device_fingerprints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fingerprint_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'هاش SHA-256 للبيانات الثابتة للجهاز',
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'أول ظهور',
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'آخر ظهور',
    
    INDEX idx_fingerprint_hash (fingerprint_hash),
    INDEX idx_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بصمات الأجهزة - تُسجّل مرة واحدة';

-- ============================================
-- جداول الكورسات والفيديوهات
-- ============================================

-- جدول الكورسات
CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT 'عنوان الكورس',
    description TEXT NULL COMMENT 'وصف الكورس',
    cover_image_url VARCHAR(500) NULL COMMENT 'صورة واجهة الكورس (البطاقة)',
    price DECIMAL(10, 2) NOT NULL DEFAULT 500.00 COMMENT 'سعر الكورس بالجنية',
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft' COMMENT 'الحالة',
    created_by INT UNSIGNED NULL COMMENT 'معرف المشرف الذي أنشأ الكورس',
    bunny_library_id INT UNSIGNED NULL COMMENT 'معرف مكتبة Bunny CDN الخاصة بهذا الكورس',
    bunny_library_api_key VARCHAR(255) NULL COMMENT 'مفتاح API لمكتبة Bunny CDN الخاصة بهذا الكورس',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_title (title),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_bunny_library_id (bunny_library_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الكورسات';

-- جدول فيديوهات الكورسات
CREATE TABLE IF NOT EXISTS course_videos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL COMMENT 'معرف الكورس',
    title VARCHAR(255) NOT NULL COMMENT 'عنوان الفيديو',
    description TEXT NULL COMMENT 'وصف الفيديو',
    video_order INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ترتيب الفيديو في الكورس',
    thumbnail_url VARCHAR(500) NULL COMMENT 'رابط صورة الواجهة',
    video_url VARCHAR(500) NULL COMMENT 'رابط الفيديو من Bunny CDN',
    bunny_video_id VARCHAR(100) NULL COMMENT 'معرف الفيديو في Bunny CDN',
    duration INT UNSIGNED NULL COMMENT 'مدة الفيديو بالثواني',
    file_size BIGINT UNSIGNED NULL COMMENT 'حجم الملف بالبايت',
    status ENUM('uploading', 'processing', 'ready', 'failed') DEFAULT 'uploading' COMMENT 'حالة الفيديو',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course_id (course_id),
    INDEX idx_video_order (video_order),
    INDEX idx_status (status),
    INDEX idx_bunny_video_id (bunny_video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فيديوهات الكورسات';

-- جدول أكواد تفعيل الكورسات (كل كود فريد - لا يتطابق مع أي كود آخر)
-- ملاحظة: أكواد الشراء تُنشأ عند الشراء مع used_by = المشتري فوراً، فلا يمكن استخدامها أبداً في حساب آخر
CREATE TABLE IF NOT EXISTS course_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL COMMENT 'معرف الكورس',
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'كود التفعيل - فريد في الجدول',
    used_by INT UNSIGNED NULL COMMENT 'معرف المستخدم صاحب الكود (يُعيَّن عند الشراء فوراً أو عند التفعيل لأكواد الإدارة)',
    used_at DATETIME NULL COMMENT 'تاريخ الاستخدام',
    expires_at DATETIME NULL COMMENT 'تاريخ انتهاء الصلاحية',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'نشط',
    bound_device_hash VARCHAR(64) NULL COMMENT 'بصمة الجهاز الوحيد المسموح لمشاهدة الكورس بهذا الكود - جهاز واحد فقط',
    created_by INT UNSIGNED NULL COMMENT 'معرف المشرف الذي أنشأ الكود',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_course_id (course_id),
    INDEX idx_used_by (used_by),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_bound_device (bound_device_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أكواد تفعيل الكورسات';

-- جدول أكواد الخصم (كود واحد = استخدام واحد لمستخدم واحد فقط، مرتبط بكورس محدد)
CREATE TABLE IF NOT EXISTS discount_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'كود الخصم - فريد',
    discount_amount DECIMAL(10, 2) NOT NULL COMMENT 'مبلغ الخصم بالجنية',
    course_id INT UNSIGNED NOT NULL COMMENT 'الكورس الذي يُطبَّق عليه الخصم',
    assigned_to_user_id INT UNSIGNED NULL COMMENT 'معرف المستخدم المخصص له الكود (NULL = أي مستخدم يمكنه استخدامه)',
    used_by INT UNSIGNED NULL COMMENT 'معرف المستخدم الذي استخدم الكود (NULL = لم يُستخدم)',
    used_at DATETIME NULL COMMENT 'تاريخ الاستخدام',
    created_by INT UNSIGNED NULL COMMENT 'معرف المشرف الذي أنشأ الكود',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_course_id (course_id),
    INDEX idx_assigned_to_user_id (assigned_to_user_id),
    INDEX idx_used_by (used_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='أكواد الخصم - استخدام واحد لكل كود، ويمكن تخصيص كود لمستخدم محدد';

-- جدول اشتراكات المستخدمين في الكورسات
CREATE TABLE IF NOT EXISTS user_course_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT 'معرف المستخدم',
    course_id INT UNSIGNED NOT NULL COMMENT 'معرف الكورس',
    code_id INT UNSIGNED NULL COMMENT 'معرف الكود المستخدم (إن وجد)',
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active' COMMENT 'حالة الاشتراك',
    progress_percentage DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'نسبة الإنجاز',
    last_watched_video_id INT UNSIGNED NULL COMMENT 'آخر فيديو تم مشاهدته',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (code_id) REFERENCES course_codes(id) ON DELETE SET NULL,
    FOREIGN KEY (last_watched_video_id) REFERENCES course_videos(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_course (user_id, course_id),
    INDEX idx_user_id (user_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اشتراكات المستخدمين في الكورسات';

-- ============================================
-- Migrations (لتطبيقها على قواعد بيانات موجودة)
-- ============================================
-- لتحديث login_attempts للحظر حسب الجهاز بدلاً من IP (شغّل بالترتيب):
-- 1. ALTER TABLE login_attempts ADD COLUMN device_uuid VARCHAR(36) NULL COMMENT 'معرف الجهاز' AFTER full_phone;
-- 2. ALTER TABLE login_attempts MODIFY COLUMN ip_address VARCHAR(45) NULL COMMENT 'IP - للتسجيل فقط';
-- 3. UPDATE login_attempts SET device_uuid = CONCAT('legacy-', COALESCE(ip_address,'0')) WHERE device_uuid IS NULL;
-- 4. ALTER TABLE login_attempts MODIFY COLUMN device_uuid VARCHAR(36) NOT NULL COMMENT 'معرف الجهاز (UUID)';
-- 5. ALTER TABLE login_attempts ADD INDEX idx_device_uuid (device_uuid);
--
-- تحديث عمود v_id ليقبل UUID (36 حرف) - للجدول site_visits الموجود:
-- ALTER TABLE site_visits MODIFY COLUMN v_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'معرف الزائر (Device UUID)';
--
-- إضافة أعمدة مكتبة Bunny للكورسات (شغّل الأسطر التالية مرة واحدة إذا ظهر خطأ: Unknown column 'bunny_library_id'):
-- ALTER TABLE courses ADD COLUMN bunny_library_id INT UNSIGNED NULL COMMENT 'معرف مكتبة Bunny CDN الخاصة بهذا الكورس' AFTER created_by;
-- ALTER TABLE courses ADD COLUMN bunny_library_api_key VARCHAR(255) NULL COMMENT 'مفتاح API لمكتبة Bunny CDN الخاصة بهذا الكورس' AFTER bunny_library_id;
-- CREATE INDEX idx_bunny_library_id ON courses (bunny_library_id);
-- إضافة صورة واجهة الكورس (شغّل مرة واحدة إذا ظهر خطأ: Unknown column 'cover_image_url'):
-- ALTER TABLE courses ADD COLUMN cover_image_url VARCHAR(500) NULL COMMENT 'صورة واجهة الكورس (البطاقة)' AFTER description;
--
-- إضافة سعر الكورس (شغّل مرة واحدة إذا ظهر خطأ: Unknown column 'price'):
-- ALTER TABLE courses ADD COLUMN price DECIMAL(10, 2) NOT NULL DEFAULT 500.00 COMMENT 'سعر الكورس بالجنية' AFTER cover_image_url;
--
-- ملاحظة: جدول verification_tokens يدعم token_type='password_reset' لاستعادة كلمة المرور عبر البريد
--
-- إضافة نوع الحساب (مجاني / VIP) - شغّل مرة واحدة إذا ظهر خطأ: Unknown column 'account_type':
-- ALTER TABLE users ADD COLUMN account_type ENUM('free', 'vip') NOT NULL DEFAULT 'free' COMMENT 'نوع الحساب: مجاني أو VIP' AFTER whatsapp_verified;
--
-- ربط الكورس بجهاز واحد فقط (بصمة الجهاز) - شغّل مرة واحدة إذا ظهر خطأ: Unknown column 'bound_device_hash':
-- ALTER TABLE course_codes ADD COLUMN bound_device_hash VARCHAR(64) NULL COMMENT 'بصمة الجهاز الوحيد المسموح لمشاهدة الكورس بهذا الكود' AFTER is_active;
-- CREATE INDEX idx_bound_device ON course_codes (bound_device_hash);
--
-- إضافة جدول أكواد الخصم (شغّل مرة واحدة إذا لم يكن الجدول موجوداً):
-- CREATE TABLE IF NOT EXISTS discount_codes ( ... ) كما في الأعلى;
--
-- إضافة عمود تخصيص كود الخصم لمستخدم (شغّل مرة واحدة إذا ظهر خطأ: Unknown column 'assigned_to_user_id'):
-- ALTER TABLE discount_codes ADD COLUMN assigned_to_user_id INT UNSIGNED NULL COMMENT 'معرف المستخدم المخصص له الكود (NULL = أي مستخدم)' AFTER course_id;
-- ALTER TABLE discount_codes ADD FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL;
-- CREATE INDEX idx_assigned_to_user_id ON discount_codes (assigned_to_user_id);

