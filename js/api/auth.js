/**
 * ============================================
 * Auth API - APIs المصادقة والتسجيل
 * ============================================
 * يحتوي على جميع APIs المتعلقة بالمصادقة والتسجيل
 * 
 * ملاحظة: هذا الملف مخصص فقط لـ APIs المصادقة
 * لا تضيف APIs أخرى هنا
 */

import { 
    sanitizeInput, 
    validateEmail, 
    validatePhone, 
    validatePassword, 
    validatePasswordMatch,
    validateCountryCode,
    createFullPhone,
    getCSRFToken,
    handleApiError,
    showError,
    showSuccess
} from './utils.js';

// تم إزالة Rate Limiting - استخدام نظام التوكن بدلاً منه

/**
 * إعدادات API
 * استخدام مسار نسبي ليعمل عند تشغيل المشروع من مجلد فرعي (مثل localhost/nayl/)
 */
const API_CONFIG = {
    baseUrl: 'api/auth', // مسار نسبي لـ PHP APIs
    timeout: 30000, // 30 ثانية
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
};

/**
 * إضافة CSRF Token إلى Headers
 * @param {object} headers - Headers الحالية
 * @returns {object} - Headers مع CSRF Token
 */
function addCSRFToken(headers) {
    const csrfToken = getCSRFToken();
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    return headers;
}

/**
 * إرسال طلب API مع معالجة الأخطاء
 * @param {string} url - رابط API
 * @param {object} options - خيارات الطلب
 * @returns {Promise<object>} - استجابة API
 */
async function fetchApi(url, options = {}) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);
    
    try {
        const headers = {
            ...API_CONFIG.headers,
            ...options.headers
        };
        
        // إضافة CSRF Token
        addCSRFToken(headers);
        
        const response = await fetch(url, {
            ...options,
            headers: headers,
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        // التحقق من حالة الاستجابة
        if (!response.ok) {
            throw response;
        }
        
        // محاولة تحليل JSON
        const data = await response.json();
        return {
            success: true,
            data: data
        };
        
    } catch (error) {
        clearTimeout(timeoutId);
        
        if (error.name === 'AbortError') {
            return {
                success: false,
                error: 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى.',
                status: 408
            };
        }
        
        // معالجة أخطاء API (Response) - استخراج رسالة الخطأ من الجسم
        if (error instanceof Response) {
            try {
                const body = await error.json();
                if (body && typeof body.error === 'string') {
                    // إضافة معلومات إضافية لـ 429
                    if (error.status === 429) {
                        let errorMsg = body.error;
                        // إضافة معلومات Rate Limit من body إذا كانت موجودة
                        if (body.rateLimitInfo) {
                            if (body.rateLimitInfo.locked_until) {
                                const lockedUntil = new Date(body.rateLimitInfo.locked_until);
                                const now = new Date();
                                const minutesLeft = Math.ceil((lockedUntil - now) / (1000 * 60));
                                if (minutesLeft > 0) {
                                    errorMsg += `\n\nالوقت المتبقي: ${minutesLeft} دقيقة`;
                                }
                            }
                            if (body.rateLimitInfo.remaining !== undefined) {
                                errorMsg += `\nالطلبات المتبقية: ${body.rateLimitInfo.remaining}`;
                            }
                        }
                        return {
                            success: false,
                            error: errorMsg,
                            status: error.status,
                            rateLimitInfo: body.rateLimitInfo || null
                        };
                    }
                    return {
                        success: false,
                        error: body.error,
                        status: error.status
                    };
                }
            } catch (parseError) {
                /* تعذر تحليل JSON */
                console.warn('Failed to parse error response:', parseError);
            }
            return {
                success: false,
                error: handleApiError(error),
                status: error.status || 500
            };
        }
        
        // أخطاء أخرى (شبكة، إلخ)
        return {
            success: false,
            error: error.message || 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.',
            status: 500
        };
    }
}

/**
 * API تسجيل حساب جديد
 * @param {object} userData - بيانات المستخدم
 * @param {string} userData.fullname - الاسم الكامل
 * @param {string} userData.email - البريد الإلكتروني
 * @param {string} userData.phone - رقم الهاتف
 * @param {string} userData.countryCode - رمز الدولة
 * @param {string} userData.password - كلمة المرور
 * @param {string} userData.confirmPassword - تأكيد كلمة المرور
 * @param {string} userData.country - الدولة
 * @param {string} userData.city - المدينة
 * @param {boolean} userData.whatsappVerified - هل تم التحقق عبر واتساب
 * @param {boolean} userData.termsAccepted - موافقة على الشروط
 * @param {string} userData.securityToken - توكن الأمان (مطلوب)
 * @returns {Promise<object>} - نتيجة التسجيل
 */
export async function signup(userData) {
    try {
        // التحقق من وجود التوكن
        if (!userData.securityToken || typeof userData.securityToken !== 'string' || userData.securityToken.length < 32) {
            return {
                success: false,
                error: 'توكن الأمان غير صحيح. يرجى إعادة تحميل الصفحة.'
            };
        }
        
        // تنظيف وفحص البيانات
        const fullname = sanitizeInput(userData.fullname || '').trim();
        const email = sanitizeInput(userData.email || '').trim();
        const phone = sanitizeInput(userData.phone || '').trim();
        const countryCode = sanitizeInput(userData.countryCode || '+20').trim();
        const password = userData.password || '';
        const confirmPassword = userData.confirmPassword || '';
        const country = sanitizeInput(userData.country || '').trim();
        const city = sanitizeInput(userData.city || '').trim();
        const whatsappVerified = userData.whatsappVerified || false;
        const termsAccepted = userData.termsAccepted || false;
        const securityToken = userData.securityToken;
        
        // التحقق من صحة البيانات
        if (!validateEmail(email)) {
            return {
                success: false,
                error: 'البريد الإلكتروني غير صحيح'
            };
        }
        
        if (!validatePhone(phone, 7)) {
            return {
                success: false,
                error: 'رقم الهاتف غير صحيح. يجب أن يكون 7 أرقام على الأقل'
            };
        }
        
        if (!validateCountryCode(countryCode)) {
            return {
                success: false,
                error: 'رمز الدولة غير صحيح'
            };
        }
        
        const passwordValidation = validatePassword(password, 6);
        if (!passwordValidation.valid) {
            return {
                success: false,
                error: passwordValidation.message
            };
        }
        
        if (!validatePasswordMatch(password, confirmPassword)) {
            return {
                success: false,
                error: 'كلمة المرور وتأكيد كلمة المرور غير متطابقتين'
            };
        }
        
        if (!country || country.trim().length === 0) {
            return {
                success: false,
                error: 'يجب اختيار الدولة'
            };
        }
        
        if (!city || city.trim().length === 0) {
            return {
                success: false,
                error: 'يجب إدخال المدينة'
            };
        }
        
        if (!termsAccepted) {
            return {
                success: false,
                error: 'يجب الموافقة على الشروط والأحكام'
            };
        }
        
        // إنشاء رقم الهاتف الكامل
        const fullPhone = createFullPhone(countryCode, phone);
        
        // إعداد بيانات الطلب مع التوكن (لا نرسل كلمة المرور كنص عادي - Backend سيتولى Hashing)
        const requestData = {
            full_name: fullname,
            email: email,
            phone: phone,
            country_code: countryCode,
            full_phone: fullPhone,
            password: password, // Backend يجب أن يقوم بـ hashing
            country: country,
            city: city,
            whatsapp_verified: whatsappVerified,
            terms_accepted: termsAccepted,
            terms_accepted_at: new Date().toISOString(),
            security_token: securityToken // إرسال التوكن مع الطلب
        };
        
        // التحقق من أن التوكن موجود في requestData
        if (!requestData.security_token || requestData.security_token.length < 32) {
            return {
                success: false,
                error: 'خطأ في إعداد طلب التسجيل. يرجى إعادة تحميل الصفحة.'
            };
        }
        
        // إرسال الطلب إلى API
        const result = await fetchApi(`${API_CONFIG.baseUrl}/signup.php`, {
            method: 'POST',
            body: JSON.stringify(requestData)
        });
        
        if (result.success) {
            return {
                success: true,
                data: result.data
            };
        } else {
            return {
                success: false,
                error: result.error || 'حدث خطأ أثناء التسجيل',
                status: result.status
            };
        }
        
    } catch (error) {
        console.error('Signup API Error:', error);
        return {
            success: false,
            error: error.message || 'حدث خطأ غير متوقع أثناء التسجيل'
        };
    }
}

/**
 * طلب إعادة تعيين كلمة المرور (إرسال رابط للبريد)
 * @param {string} email - البريد الإلكتروني المسجل
 * @returns {Promise<{success: boolean, data?: object, error?: string}>}
 */
export async function forgotPasswordRequest(email) {
    try {
        const cleanEmail = sanitizeInput((email || '').trim());
        if (!validateEmail(cleanEmail)) {
            return { success: false, error: 'أدخل بريداً إلكترونياً صحيحاً' };
        }
        const result = await fetchApi(`${API_CONFIG.baseUrl}/forgot-password.php`, {
            method: 'POST',
            body: JSON.stringify({ email: cleanEmail })
        });
        if (result.success) {
            return {
                success: true,
                data: result.data?.data || result.data,
                message: result.data?.data?.message || result.data?.message
            };
        }
        return {
            success: false,
            error: result.error || 'حدث خطأ أثناء إرسال الرابط'
        };
    } catch (error) {
        console.error('Forgot password API Error:', error);
        return {
            success: false,
            error: error.message || 'حدث خطأ غير متوقع'
        };
    }
}

/**
 * تعيين كلمة مرور جديدة باستخدام رمز الاستعادة
 * @param {string} token - رمز الاستعادة من الرابط
 * @param {string} password - كلمة المرور الجديدة
 * @param {string} confirmPassword - تأكيد كلمة المرور
 * @returns {Promise<{success: boolean, data?: object, error?: string}>}
 */
export async function resetPassword(token, password, confirmPassword) {
    try {
        const t = (token || '').trim();
        if (!t || t.length !== 64) {
            return { success: false, error: 'رابط الاستعادة غير صالح' };
        }
        const passValidation = validatePassword(password, 6);
        if (!passValidation.valid) {
            return { success: false, error: passValidation.message };
        }
        if (!validatePasswordMatch(password, confirmPassword)) {
            return { success: false, error: 'كلمة المرور وتأكيدها غير متطابقتين' };
        }
        const result = await fetchApi(`${API_CONFIG.baseUrl}/reset-password.php`, {
            method: 'POST',
            body: JSON.stringify({
                token: t,
                password,
                confirmPassword
            })
        });
        if (result.success) {
            return {
                success: true,
                data: result.data?.data || result.data,
                message: result.data?.data?.message || result.data?.message
            };
        }
        return {
            success: false,
            error: result.error || 'حدث خطأ أثناء تعيين كلمة المرور'
        };
    } catch (error) {
        console.error('Reset password API Error:', error);
        return {
            success: false,
            error: error.message || 'حدث خطأ غير متوقع'
        };
    }
}

// تم إزالة getSignupRateLimitInfo - لم يعد هناك Rate Limiting
