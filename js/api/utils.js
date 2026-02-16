/**
 * ============================================
 * Utils - وظائف مساعدة مشتركة
 * ============================================
 * يحتوي على وظائف التحقق والتنظيف المشتركة
 */

/**
 * تنظيف المدخلات من HTML/XSS
 * @param {string} input - النص المراد تنظيفه
 * @returns {string} - النص المنظف
 */
export function sanitizeInput(input) {
    if (typeof input !== 'string') {
        return '';
    }
    
    // إنشاء عنصر div مؤقت
    const div = document.createElement('div');
    div.textContent = input;
    
    // إرجاع النص المنظف
    return div.textContent || div.innerText || '';
}

/**
 * التحقق من صحة البريد الإلكتروني
 * @param {string} email - البريد الإلكتروني
 * @returns {boolean} - true إذا كان صحيحاً
 */
export function validateEmail(email) {
    if (!email || typeof email !== 'string') {
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email.trim());
}

/**
 * التحقق من صحة رقم الهاتف
 * @param {string} phone - رقم الهاتف
 * @param {number} minLength - الحد الأدنى للأرقام (افتراضي: 7)
 * @returns {boolean} - true إذا كان صحيحاً
 */
export function validatePhone(phone, minLength = 7) {
    if (!phone || typeof phone !== 'string') {
        return false;
    }
    
    const phoneRegex = /^[0-9]+$/;
    return phoneRegex.test(phone.trim()) && phone.trim().length >= minLength;
}

/**
 * التحقق من قوة كلمة المرور
 * @param {string} password - كلمة المرور
 * @param {number} minLength - الحد الأدنى للأحرف (افتراضي: 6)
 * @returns {object} - { valid: boolean, message: string }
 */
export function validatePassword(password, minLength = 6) {
    if (!password || typeof password !== 'string') {
        return {
            valid: false,
            message: 'كلمة المرور مطلوبة'
        };
    }
    
    if (password.length < minLength) {
        return {
            valid: false,
            message: `يجب أن تكون كلمة المرور ${minLength} أحرف على الأقل`
        };
    }
    
    return {
        valid: true,
        message: 'كلمة المرور صحيحة'
    };
}

/**
 * التحقق من تطابق كلمتي المرور
 * @param {string} password - كلمة المرور
 * @param {string} confirmPassword - تأكيد كلمة المرور
 * @returns {boolean} - true إذا كانتا متطابقتين
 */
export function validatePasswordMatch(password, confirmPassword) {
    if (!password || !confirmPassword) {
        return false;
    }
    
    return password === confirmPassword;
}

/**
 * التحقق من صحة رمز الدولة
 * @param {string} countryCode - رمز الدولة (مثل +20, +966)
 * @returns {boolean} - true إذا كان صحيحاً
 */
export function validateCountryCode(countryCode) {
    if (!countryCode || typeof countryCode !== 'string') {
        return false;
    }
    
    const codeRegex = /^\+[0-9]{1,4}$/;
    return codeRegex.test(countryCode.trim());
}

/**
 * إنشاء رقم هاتف كامل من رمز الدولة والرقم
 * @param {string} countryCode - رمز الدولة
 * @param {string} phone - رقم الهاتف
 * @returns {string} - رقم الهاتف الكامل
 */
export function createFullPhone(countryCode, phone) {
    const cleanCode = countryCode.trim();
    const cleanPhone = phone.trim();
    
    return cleanCode + cleanPhone;
}

/**
 * الحصول على CSRF Token من meta tag
 * @returns {string|null} - CSRF Token أو null
 */
export function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : null;
}

/**
 * معالجة الأخطاء من API
 * @param {Error|Response} error - الخطأ
 * @returns {string} - رسالة الخطأ بالعربية
 */
export function handleApiError(error) {
    if (error instanceof Response) {
        // خطأ من API
        switch (error.status) {
            case 400:
                return 'بيانات غير صحيحة. يرجى التحقق من المدخلات.';
            case 401:
                return 'غير مصرح لك بهذه العملية.';
            case 403:
                return 'تم تجاوز الحد المسموح. يرجى المحاولة لاحقاً.';
            case 404:
                return 'المورد المطلوب غير موجود.';
            case 429:
                return 'تم تجاوز عدد المحاولات المسموحة. يرجى المحاولة لاحقاً.';
            case 500:
                return 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً.';
            default:
                return 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.';
        }
    }
    
    if (error instanceof Error) {
        // خطأ في الشبكة
        if (error.message.includes('fetch')) {
            return 'فشل الاتصال بالخادم. يرجى التحقق من الاتصال بالإنترنت.';
        }
        return error.message || 'حدث خطأ غير متوقع.';
    }
    
    return 'حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.';
}

/**
 * إظهار رسالة خطأ للمستخدم
 * @param {string} message - رسالة الخطأ
 */
export function showError(message) {
    // يمكن استبدالها بنظام إشعارات أفضل
    alert(message);
}

/**
 * إظهار رسالة نجاح للمستخدم
 * @param {string} message - رسالة النجاح
 */
export function showSuccess(message) {
    // يمكن استبدالها بنظام إشعارات أفضل
    alert(message);
}
