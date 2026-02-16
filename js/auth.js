/**
 * ============================================
 * Authentication Helper
 * ============================================
 * ملف للتحقق من تسجيل الدخول وحماية الصفحات
 */

// الصفحات التي تتطلب تسجيل الدخول
const PROTECTED_PAGES = [
    'profile.html',
    'my-courses.html',
    'recharge.html',
    'purchase-course.html',
    'course-detail.html'
];

// الصفحات العامة (لا تتطلب تسجيل دخول)
const PUBLIC_PAGES = [
    'index.html',
    'signup.html',
    'forgot-password.html',
    'home.html',
    'courses.html'
];

/**
 * التحقق من تسجيل الدخول
 * @returns {Promise<Object|null>} بيانات المستخدم أو null
 */
async function checkAuth() {
    try {
        // الحصول على session token من localStorage أو sessionStorage
        const sessionToken = localStorage.getItem('session_token') || sessionStorage.getItem('session_token');
        
        if (!sessionToken) {
            return null;
        }
        
        // التحقق من صحة الجلسة عبر API
        const response = await fetch('/api/auth/verify.php', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${sessionToken}`,
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });
        
        if (!response.ok) {
            // إذا فشل التحقق، حذف token
            clearAuth();
            return null;
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            return result.data;
        }
        
        return null;
        
    } catch (error) {
        console.error('Auth check error:', error);
        return null;
    }
}

/**
 * التحقق من تسجيل الدخول وحماية الصفحة
 * يجب استدعاء هذه الدالة في بداية كل صفحة محمية
 */
async function requireAuth() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    // إذا كانت الصفحة عامة، لا حاجة للتحقق
    if (PUBLIC_PAGES.includes(currentPage)) {
        return true;
    }
    
    // إذا كانت الصفحة محمية، تحقق من تسجيل الدخول
    if (PROTECTED_PAGES.includes(currentPage)) {
        const user = await checkAuth();
        
        if (!user) {
            // إظهار رسالة للمستخدم
            if (confirm('يجب تسجيل الدخول للوصول إلى هذه الصفحة. هل تريد الانتقال إلى صفحة تسجيل الدخول؟')) {
                window.location.href = 'index.html';
            } else {
                window.location.href = 'index.html';
            }
            return false;
        }
        
        // حفظ بيانات المستخدم في localStorage
        localStorage.setItem('user', JSON.stringify(user));
        localStorage.setItem('isLoggedIn', 'true');
        
        return true;
    }
    
    return true;
}

/**
 * تسجيل الخروج
 */
async function logout() {
    const sessionToken = localStorage.getItem('session_token') || sessionStorage.getItem('session_token');
    
    // إرسال طلب تسجيل الخروج إلى API
    if (sessionToken) {
        try {
            await fetch('/api/auth/logout.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${sessionToken}`,
                    'Content-Type': 'application/json'
                },
                credentials: 'include'
            });
        } catch (error) {
            console.error('Logout error:', error);
        }
    }
    
    // حذف جميع بيانات المصادقة
    clearAuth();
    
    // حذف جميع cookies المتعلقة بالجلسة
    document.cookie.split(";").forEach(function(c) { 
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
    });
    
    // إعادة التوجيه إلى صفحة home
    window.location.href = 'home.html';
}

/**
 * مسح جميع بيانات المصادقة
 */
function clearAuth() {
    localStorage.removeItem('session_token');
    localStorage.removeItem('user');
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userEmail');
    sessionStorage.removeItem('session_token');
    sessionStorage.removeItem('user');
    sessionStorage.removeItem('isLoggedIn');
    sessionStorage.removeItem('userEmail');
    sessionStorage.removeItem('isGuest');
}

/**
 * الحصول على بيانات المستخدم الحالي
 * @returns {Object|null}
 */
function getCurrentUser() {
    try {
        const userStr = localStorage.getItem('user') || sessionStorage.getItem('user');
        if (userStr) {
            return JSON.parse(userStr);
        }
    } catch (error) {
        console.error('Error parsing user data:', error);
    }
    return null;
}

/**
 * التحقق من تسجيل الدخول عند تحميل الصفحة
 */
document.addEventListener('DOMContentLoaded', async function() {
    // التحقق من تسجيل الدخول
    await requireAuth();
    
    // إضافة event listeners لأزرار تسجيل الخروج
    document.querySelectorAll('.logout-item, a[href*="logout"], button[data-action="logout"]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    });
    
    // تحديث واجهة المستخدم بناءً على حالة تسجيل الدخول
    updateUIForAuth();
});

/**
 * تحديث واجهة المستخدم بناءً على حالة تسجيل الدخول
 */
function updateUIForAuth() {
    const user = getCurrentUser();
    const isLoggedIn = !!user;
    
    // إخفاء/إظهار عناصر القائمة بناءً على حالة تسجيل الدخول
    const protectedLinks = document.querySelectorAll('[data-requires-auth="true"]');
    protectedLinks.forEach(link => {
        if (isLoggedIn) {
            link.style.display = '';
        } else {
            link.style.display = 'none';
        }
    });
    
    // تحديث معلومات المستخدم في القائمة
    if (isLoggedIn && user) {
        const userEmailElements = document.querySelectorAll('[data-user-email]');
        userEmailElements.forEach(element => {
            element.textContent = user.email || '';
        });
        
        const userNameElements = document.querySelectorAll('[data-user-name]');
        userNameElements.forEach(element => {
            element.textContent = user.name || user.email || '';
        });
    }
}

/**
 * حفظ بيانات تسجيل الدخول بعد تسجيل الدخول الناجح
 * @param {Object} loginData - بيانات تسجيل الدخول من API
 */
function saveAuthData(loginData) {
    if (loginData.session && loginData.session.token) {
        // حفظ session token
        if (loginData.remember) {
            localStorage.setItem('session_token', loginData.session.token);
        } else {
            sessionStorage.setItem('session_token', loginData.session.token);
        }
        
        // حفظ بيانات المستخدم
        if (loginData.user) {
            const userData = JSON.stringify(loginData.user);
            if (loginData.remember) {
                localStorage.setItem('user', userData);
                localStorage.setItem('isLoggedIn', 'true');
            } else {
                sessionStorage.setItem('user', userData);
                sessionStorage.setItem('isLoggedIn', 'true');
            }
            
            if (loginData.user.email) {
                if (loginData.remember) {
                    localStorage.setItem('userEmail', loginData.user.email);
                } else {
                    sessionStorage.setItem('userEmail', loginData.user.email);
                }
            }
        }
    }
}

// تصدير الدوال للاستخدام في ملفات أخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        checkAuth,
        requireAuth,
        logout,
        clearAuth,
        getCurrentUser,
        saveAuthData
    };
}
