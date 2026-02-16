/**
 * ============================================
 * Rate Limiter - تحديد معدل الطلبات
 * ============================================
 * حماية من الطلبات المفرطة (Rate Limiting)
 * 
 * ملاحظة: هذا Rate Limiting في Frontend فقط
 * الحماية الحقيقية يجب أن تكون في Backend
 */

/**
 * إعدادات Rate Limiting
 */
const RATE_LIMIT_CONFIG = {
    // عدد الطلبات المسموحة
    maxRequests: 5,
    
    // الفترة الزمنية بالثواني (15 دقيقة)
    windowSeconds: 15 * 60,
    
    // مدة القفل بالثواني (30 دقيقة) بعد تجاوز الحد
    lockoutSeconds: 30 * 60,
    
    // مفتاح التخزين في localStorage
    storageKey: 'rate_limit_signup'
};

/**
 * مفتاح التخزين لـ Device UUID في localStorage - يُستخدم مرة واحدة فقط لكل جهاز
 */
const DEVICE_UUID_STORAGE_KEY = 'device_uuid_v1';

/**
 * إنشاء UUID v4 قوي وآمن باستخدام Crypto API
 * يستخدم أرقام عشوائية مشفرة (cryptographically secure) - 122 بت عشوائية
 * احتمال التكرار: ~1 من 5.3×10^36 (شبه مستحيل)
 * @returns {string} - UUID بصيغة xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 */
function generateSecureUUID() {
    // الطريقة 1: crypto.randomUUID() - متاحة في المتصفحات الحديثة (Chrome 92+, Firefox 95+, Safari 15.4+)
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    
    // الطريقة 2: crypto.getRandomValues() - بديل آمن للمتصفحات الأقدم
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        
        // تعيين إصدار UUID v4 (البت 6 و 7 من byte 6 = 0100)
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        // تعيين variant (البت 6 و 7 من byte 8 = 10)
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        
        const hex = Array.from(bytes)
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
        
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
    }
    
    // الطريقة 3: Fallback نادر جداً - إن لم تتوفر Crypto API
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/**
 * الحصول على معرف الجهاز الفريد - يُنشأ مرة واحدة فقط ولا يُعاد إنشاؤه إن وُجد
 * UUID v4 مشفر وآمن (cryptographically secure random)
 * يُخزَّن في localStorage عند عدم وجوده مسبقاً - يحفظ مرة واحدة فقط
 * @returns {string} - معرف الجهاز الفريد
 */
function getDeviceId() {
    // التحقق من وجود UUID مسبقاً في localStorage - إن وُجد يُرجع كما هو (لا يُعاد إنشاؤه)
    const existingUUID = localStorage.getItem(DEVICE_UUID_STORAGE_KEY);
    
    if (existingUUID) {
        return existingUUID;
    }
    
    // إنشاء UUID جديد فقط عند عدم وجوده - ويُحفظ مرة واحدة
    const deviceId = generateSecureUUID();
    localStorage.setItem(DEVICE_UUID_STORAGE_KEY, deviceId);
    
    // حذف المفاتيح القديمة إن وُجدت (ترحيل من النسخة السابقة)
    try {
        localStorage.removeItem('device_id');
    } catch (e) {}
    
    return deviceId;
}

/**
 * الحصول على عنوان IP (تقريبي من localStorage)
 * @returns {string} - عنوان IP
 */
function getClientIdentifier() {
    const deviceId = getDeviceId();
    
    // يمكن إضافة معلومات إضافية مثل User Agent
    const userAgent = navigator.userAgent;
    
    // إنشاء معرف فريد للعميل
    return `${deviceId}_${userAgent.substring(0, 50)}`;
}

/**
 * الحصول على بيانات Rate Limit الحالية
 * @param {string} identifier - معرف العميل
 * @returns {object} - بيانات Rate Limit
 */
function getRateLimitData(identifier) {
    const storageKey = `${RATE_LIMIT_CONFIG.storageKey}_${identifier}`;
    const data = localStorage.getItem(storageKey);
    
    if (!data) {
        return {
            requests: [],
            lockedUntil: null
        };
    }
    
    try {
        return JSON.parse(data);
    } catch (error) {
        return {
            requests: [],
            lockedUntil: null
        };
    }
}

/**
 * حفظ بيانات Rate Limit
 * @param {string} identifier - معرف العميل
 * @param {object} data - بيانات Rate Limit
 */
function saveRateLimitData(identifier, data) {
    const storageKey = `${RATE_LIMIT_CONFIG.storageKey}_${identifier}`;
    localStorage.setItem(storageKey, JSON.stringify(data));
}

/**
 * تنظيف الطلبات القديمة
 * @param {Array} requests - قائمة الطلبات
 * @returns {Array} - قائمة الطلبات المحدثة
 */
function cleanOldRequests(requests) {
    const now = Date.now();
    const windowMs = RATE_LIMIT_CONFIG.windowSeconds * 1000;
    
    return requests.filter(timestamp => {
        return (now - timestamp) < windowMs;
    });
}

/**
 * التحقق من Rate Limit
 * @param {string} identifier - معرف العميل (اختياري)
 * @returns {object} - { allowed: boolean, remaining: number, resetAt: number, lockedUntil: number|null }
 */
export function checkRateLimit(identifier = null) {
    const clientId = identifier || getClientIdentifier();
    const data = getRateLimitData(clientId);
    
    const now = Date.now();
    
    // التحقق من القفل
    if (data.lockedUntil && data.lockedUntil > now) {
        const remainingSeconds = Math.ceil((data.lockedUntil - now) / 1000);
        return {
            allowed: false,
            remaining: 0,
            resetAt: data.lockedUntil,
            lockedUntil: data.lockedUntil,
            message: `تم تجاوز الحد المسموح. يرجى المحاولة بعد ${Math.ceil(remainingSeconds / 60)} دقيقة.`
        };
    }
    
    // تنظيف الطلبات القديمة
    const cleanRequests = cleanOldRequests(data.requests || []);
    
    // التحقق من عدد الطلبات
    if (cleanRequests.length >= RATE_LIMIT_CONFIG.maxRequests) {
        // قفل الحساب
        const lockedUntil = now + (RATE_LIMIT_CONFIG.lockoutSeconds * 1000);
        
        saveRateLimitData(clientId, {
            requests: cleanRequests,
            lockedUntil: lockedUntil
        });
        
        return {
            allowed: false,
            remaining: 0,
            resetAt: lockedUntil,
            lockedUntil: lockedUntil,
            message: `تم تجاوز الحد المسموح (${RATE_LIMIT_CONFIG.maxRequests} طلبات). تم قفل الطلبات لمدة ${RATE_LIMIT_CONFIG.lockoutSeconds / 60} دقيقة.`
        };
    }
    
    // حساب أقدم طلب لتحديد وقت إعادة التعيين
    const oldestRequest = cleanRequests.length > 0 
        ? Math.min(...cleanRequests) 
        : now;
    const resetAt = oldestRequest + (RATE_LIMIT_CONFIG.windowSeconds * 1000);
    
    return {
        allowed: true,
        remaining: RATE_LIMIT_CONFIG.maxRequests - cleanRequests.length,
        resetAt: resetAt,
        lockedUntil: null,
        message: null
    };
}

/**
 * تسجيل طلب جديد
 * @param {string} identifier - معرف العميل (اختياري)
 * @returns {object} - { allowed: boolean, remaining: number, resetAt: number }
 */
export function recordRequest(identifier = null) {
    const clientId = identifier || getClientIdentifier();
    const checkResult = checkRateLimit(clientId);
    
    if (!checkResult.allowed) {
        return checkResult;
    }
    
    // الحصول على البيانات الحالية
    const data = getRateLimitData(clientId);
    const cleanRequests = cleanOldRequests(data.requests || []);
    
    // إضافة طلب جديد
    cleanRequests.push(Date.now());
    
    // حفظ البيانات
    saveRateLimitData(clientId, {
        requests: cleanRequests,
        lockedUntil: data.lockedUntil
    });
    
    // حساب الطلبات المتبقية
    const remaining = RATE_LIMIT_CONFIG.maxRequests - cleanRequests.length;
    const oldestRequest = cleanRequests.length > 0 
        ? Math.min(...cleanRequests) 
        : Date.now();
    const resetAt = oldestRequest + (RATE_LIMIT_CONFIG.windowSeconds * 1000);
    
    return {
        allowed: true,
        remaining: remaining,
        resetAt: resetAt,
        lockedUntil: null,
        message: null
    };
}

/**
 * إعادة تعيين Rate Limit (للاستخدام في حالات خاصة)
 * @param {string} identifier - معرف العميل (اختياري)
 */
export function resetRateLimit(identifier = null) {
    const clientId = identifier || getClientIdentifier();
    const storageKey = `${RATE_LIMIT_CONFIG.storageKey}_${clientId}`;
    localStorage.removeItem(storageKey);
}

/**
 * الحصول على معلومات Rate Limit الحالية
 * @param {string} identifier - معرف العميل (اختياري)
 * @returns {object} - معلومات Rate Limit
 */
export function getRateLimitInfo(identifier = null) {
    const clientId = identifier || getClientIdentifier();
    return checkRateLimit(clientId);
}
