/**
 * ============================================
 * Authentication Check - حماية الصفحات
 * ============================================
 * ملف للتحقق من تسجيل الدخول وحماية الصفحات
 */

/**
 * التحقق من تسجيل الدخول
 * @returns {Promise<boolean>} - true إذا كان المستخدم مسجل دخول
 */
async function checkAuth() {
    // التحقق من وجود session token في localStorage أو sessionStorage
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true' || sessionStorage.getItem('isLoggedIn') === 'true';
    
    if (!sessionToken || !isLoggedIn) {
        return false;
    }
    
    // التحقق من انتهاء الجلسة
    const expiresAt = localStorage.getItem('sessionExpiresAt') || sessionStorage.getItem('sessionExpiresAt');
    if (expiresAt) {
        const expiryDate = new Date(expiresAt);
        const now = new Date();
        if (now > expiryDate) {
            // الجلسة منتهية
            clearAuthData();
            return false;
        }
    }
    
    // التحقق من صحة الجلسة مع الخادم
    try {
        // مسار API نسبي ليعمل من الجذر أو من مجلد فرعي (مثل yoursite.com/nayl/)
        const verifyUrl = (function () {
            if (typeof window.API_BASE !== 'undefined' && window.API_BASE) return window.API_BASE + '/api/auth/verify.php';
            const path = window.location.pathname || '';
            const dir = path.substring(0, path.lastIndexOf('/') + 1);
            return dir + 'api/auth/verify.php';
        })();
        const response = await fetch(verifyUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });
        
        // التحقق من حالة الاستجابة
        if (!response.ok) {
            console.error('Verify response not OK:', response.status, response.statusText);
            if (response.status === 401 || response.status === 404) {
                clearAuthData();
                return false;
            }
        }
        
        const responseText = await response.text();
        let result = null;
        try {
            result = responseText ? JSON.parse(responseText) : {};
        } catch (e) {
            // استجابة غير JSON (مثل صفحة 404 HTML)
            console.error('Verify response not JSON:', response.status, responseText.substring(0, 100));
            if (!response.ok) {
                clearAuthData();
                return false;
            }
        }
        
        if (result && response.ok && result.success) {
            // تحديث بيانات المستخدم إذا لزم الأمر
            // verify.php يعيد البيانات مباشرة في result.data (وليس result.data.user)
            if (result.data) {
                const storage = localStorage.getItem('sessionToken') ? localStorage : sessionStorage;
                // إذا كانت البيانات في result.data.user (من login/signup) أو result.data مباشرة (من verify)
                const userData = result.data.user || result.data;
                if (userData && userData.email) {
                    storage.setItem('userData', JSON.stringify(userData));
                    return true;
                }
            }
            return true;
        } else {
            // الجلسة غير صالحة أو استجابة غير متوقعة
            if (result) {
                console.error('Session verification failed:', result.error || 'Unknown error');
            }
            clearAuthData();
            return false;
        }
    } catch (error) {
        console.error('Auth check error:', error);
        // في حالة خطأ في الاتصال، نتحقق من البيانات المحلية فقط
        // لكن نتحقق من أن البيانات موجودة وصحيحة
        if (isLoggedIn && sessionToken) {
            const userData = getCurrentUser();
            if (userData && userData.email) {
                return true;
            }
        }
        return false;
    }
}

/**
 * مسح جميع بيانات المصادقة والجلسة من جهاز المستخدم
 * (يُستدعى بعد تسجيل الخروج لضمان عدم بقاء أي بيانات جلسة)
 */
function clearAuthData() {
    // مفاتيح الجلسة الأساسية (auth-check / login.js)
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('sessionToken');
    localStorage.removeItem('sessionExpiresAt');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('userData');
    localStorage.removeItem('userIdentifier');
    localStorage.removeItem('identifierType');

    sessionStorage.removeItem('isLoggedIn');
    sessionStorage.removeItem('sessionToken');
    sessionStorage.removeItem('sessionExpiresAt');
    sessionStorage.removeItem('userEmail');
    sessionStorage.removeItem('userData');
    sessionStorage.removeItem('userIdentifier');
    sessionStorage.removeItem('identifierType');
    sessionStorage.removeItem('isGuest');

    // توافق مع auth.js (مفاتيح بديلة)
    localStorage.removeItem('session_token');
    localStorage.removeItem('user');
    sessionStorage.removeItem('session_token');
    sessionStorage.removeItem('user');

    // صفحة التوجيه بعد الدخول (لا تبقى بعد الخروج)
    sessionStorage.removeItem('redirectAfterLogin');
}

/**
 * حماية الصفحة - يجب استدعاء هذه الدالة في بداية الصفحات المحمية
 * @param {string} redirectTo - الصفحة التي سيتم التوجيه إليها إذا لم يكن مسجل دخول (افتراضي: index.html)
 */
async function protectPage(redirectTo = 'index.html') {
    const isAuthenticated = await checkAuth();
    
    if (!isAuthenticated) {
        // حفظ الصفحة الحالية للرجوع إليها بعد تسجيل الدخول
        const currentPage = window.location.pathname;
        if (currentPage && currentPage !== '/index.html' && currentPage !== '/') {
            sessionStorage.setItem('redirectAfterLogin', currentPage);
        }
        
        // إظهار رسالة للمستخدم
        if (typeof showAuthError === 'function') {
            showAuthError('يجب تسجيل الدخول للوصول إلى هذه الصفحة');
        }
        
        // التوجيه إلى صفحة تسجيل الدخول
        window.location.href = redirectTo;
        return false;
    }
    
    return true;
}

/**
 * إظهار رسالة خطأ المصادقة
 * @param {string} message - رسالة الخطأ
 */
function showAuthError(message) {
    // إنشاء عنصر رسالة الخطأ
    const errorDiv = document.createElement('div');
    errorDiv.className = 'auth-error-message';
    errorDiv.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #fee;
        border: 1px solid #fcc;
        color: #c33;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
        max-width: 90%;
        text-align: center;
    `;
    errorDiv.innerHTML = `
        <i class="bi bi-exclamation-circle-fill" style="font-size: 1.5rem;"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(errorDiv);
    
    // إزالة الرسالة بعد 5 ثوان
    setTimeout(() => {
        errorDiv.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => errorDiv.remove(), 300);
    }, 5000);
}

/**
 * الحصول على بيانات المستخدم الحالي
 * @returns {Object|null} - بيانات المستخدم أو null
 */
function getCurrentUser() {
    const userDataStr = localStorage.getItem('userData') || sessionStorage.getItem('userData');
    if (userDataStr) {
        try {
            return JSON.parse(userDataStr);
        } catch (e) {
            console.error('Error parsing user data:', e);
            return null;
        }
    }
    return null;
}

/**
 * التحقق من تسجيل الدخول (للصفحات التي لا تحتاج حماية صارمة)
 * @returns {boolean} - true إذا كان مسجل دخول
 */
function isLoggedIn() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true' || sessionStorage.getItem('isLoggedIn') === 'true';
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    return isLoggedIn && !!sessionToken;
}

/**
 * تسجيل الخروج: حذف الجلسة من قاعدة البيانات ثم مسح كل بيانات الجلسة من جهاز المستخدم
 */
async function logout() {
    try {
        const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

        // 1) إرسال طلب تسجيل الخروج إلى الخادم لـحذف الجلسة من قاعدة البيانات
        if (sessionToken) {
            try {
                const response = await fetch('/api/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${sessionToken}`
                    },
                    credentials: 'include'
                });
                if (!response.ok) {
                    console.error('Logout request failed:', response.status);
                }
            } catch (err) {
                console.error('Logout fetch error:', err);
            }
        }

        // 2) مسح جميع بيانات الجلسة من جهاز المستخدم (localStorage + sessionStorage)
        clearAuthData();

        // 3) حذف جميع cookies المتعلقة بالجلسة
        document.cookie.split(';').forEach(function (c) {
            document.cookie = c.replace(/^\s+/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
        });

        // 4) التوجيه إلى الصفحة الرئيسية
        window.location.href = 'home.html';
    } catch (error) {
        console.error('Logout function error:', error);
        clearAuthData();
        document.cookie.split(';').forEach(function (c) {
            document.cookie = c.replace(/^\s+/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
        });
        window.location.href = 'home.html';
    }
}

// جعل الدالة متاحة في النطاق العام
window.logout = logout;
console.log('window.logout function registered');

// إضافة أنماط CSS للرسائل
if (!document.getElementById('auth-check-styles')) {
    const style = document.createElement('style');
    style.id = 'auth-check-styles';
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        @keyframes slideUp {
            from {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            to {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
        }
    `;
    document.head.appendChild(style);
}

// تصدير الدوال للاستخدام في ملفات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        checkAuth,
        protectPage,
        clearAuthData,
        getCurrentUser,
        isLoggedIn,
        logout,
        showAuthError
    };
}
