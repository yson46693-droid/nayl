// Login functionality
document.addEventListener('DOMContentLoaded', async function () {
    // إذا كانت هناك جلسة نشطة بالفعل، تحويل المستخدم إلى صفحة الكورسات
    if (typeof checkAuth === 'function') {
        try {
            const isAuthenticated = await checkAuth();
            if (isAuthenticated) {
                window.location.href = 'courses.html';
                return;
            }
        } catch (e) {
            console.error('Auth check on login page:', e);
        }
    }

    const loginForm = document.getElementById('loginForm');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const emailInput = document.getElementById('email');

    // Toggle password visibility
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            const eyeIcon = this.querySelector('.eye-icon');
            const eyeOffIcon = this.querySelector('.eye-off-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'inline-block';
                this.setAttribute('aria-label', 'إخفاء كلمة المرور');
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'inline-block';
                eyeOffIcon.style.display = 'none';
                this.setAttribute('aria-label', 'إظهار كلمة المرور');
            }
        });
    }

    // Handle login form submission
    if (loginForm) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const remember = document.getElementById('remember').checked;
            const submitButton = loginForm.querySelector('button[type="submit"]');

            // التحقق من البريد الإلكتروني
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                showError('الرجاء إدخال بريد إلكتروني صحيح');
                return;
            }

            // التحقق من كلمة المرور
            if (!password) {
                showError('الرجاء إدخال كلمة المرور');
                return;
            }

            // تعطيل الزر أثناء الإرسال
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split" style="margin-left: 8px;"></i>جاري تسجيل الدخول...';

            try {
                // الحصول على معرف الجهاز (UUID من localStorage) - مطلوب للحظر حسب الجهاز
                // التأكد من وجود معرف الجهاز (يُنشأ في deviceUUID.js عند تحميل الصفحة)
                if (typeof window.getDeviceUUID === 'function') {
                    window.getDeviceUUID();
                }
                const deviceUuid = (typeof window.getDeviceUUID === 'function' ? window.getDeviceUUID() : null) || localStorage.getItem('device_uuid_v1') || '';
                if (!deviceUuid) {
                    showError('معرف الجهاز غير متوفر. تأكد من تفعيل التخزين المحلي (Cookies/Storage) للموقع وأعد تحميل الصفحة ثم جرّب مرة أخرى.');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }

                // مسار API: استخدام نفس المنشأ (origin) لتجنب مشاكل Safari مع إعادة التوجيه والـ CORS
                const apiUrl = (function () {
                    if (typeof window.API_BASE !== 'undefined' && window.API_BASE) return window.API_BASE + '/api/auth/login.php';
                    const path = window.location.pathname || '';
                    const dir = path.substring(0, path.lastIndexOf('/') + 1);
                    const relative = dir + 'api/auth/login.php';
                    // Safari: استخدام عنوان كامل من نفس المنشأ لتفادي مشاكل الطلبات النسبية
                    if (window.location.origin) {
                        return window.location.origin + (relative.startsWith('/') ? relative : '/' + relative);
                    }
                    return relative;
                })();
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    credentials: 'include',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        identifier: email,
                        identifier_type: 'email',
                        password: password,
                        remember: remember,
                        device_uuid: deviceUuid
                    })
                });

                const responseText = await response.text();
                let result = null;
                try {
                    result = responseText ? JSON.parse(responseText) : {};
                } catch (e) {
                    console.error('Login API response not JSON. URL:', apiUrl, 'Status:', response.status, 'Body:', responseText.substring(0, 200));
                    const isHtml = (responseText && (responseText.trim().startsWith('<') || responseText.includes('<!DOCTYPE') || responseText.includes('<html')));
                    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent) || /iPhone|iPad|Macintosh.*Safari/i.test(navigator.userAgent);
                    let errMsg;
                    if (response.ok) {
                        errMsg = 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى';
                        if (isHtml && isSafari) {
                            errMsg = 'الخادم أعاد صفحة ويب بدلاً من البيانات (غالباً في Safari). جرّب: فتح الموقع بنفس الرابط في شريط العنوان، أو تعطيل "منع التتبع عبر المواقع" لهذا الموقع من إعدادات Safari، أو استخدام متصفح آخر.';
                        }
                    } else {
                        errMsg = 'خطأ من الخادم (رمز ' + response.status + '). تحقق من إعدادات الاستضافة وملف .env. للتشخيص: افتح في المتصفح نفس الموقع ثم أضف /api/auth/login.php?ping=1';
                        if (isHtml && isSafari) {
                            errMsg = 'الخادم أعاد صفحة خطأ (رمز ' + response.status + '). في Safari جرّب: تعطيل منع التتبع لهذا الموقع، أو استخدم Chrome/Firefox.';
                        }
                    }
                    showError(errMsg);
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }

                if (!response.ok || !result.success) {
                    const errorMessage = result.error || result.message || 'حدث خطأ أثناء تسجيل الدخول';
                    showError(errorMessage);
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }

                // التحقق من شكل الاستجابة الناجحة
                if (!result.data || !result.data.user || !result.data.session) {
                    console.error('Login API success but invalid response shape:', result);
                    showError('استجابة غير متوقعة من الخادم. يرجى المحاولة مرة أخرى.');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                    return;
                }

                // حفظ بيانات المستخدم والجلسة
                const userData = result.data.user;
                const sessionData = result.data.session;

                if (remember) {
                    localStorage.setItem('userIdentifier', email);
                    localStorage.setItem('identifierType', 'email');
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('sessionToken', sessionData.token);
                    localStorage.setItem('sessionExpiresAt', sessionData.expires_at);
                    localStorage.setItem('userEmail', email);
                    // حفظ بيانات المستخدم الكاملة
                    localStorage.setItem('userData', JSON.stringify(userData));
                } else {
                    sessionStorage.setItem('userIdentifier', email);
                    sessionStorage.setItem('identifierType', 'email');
                    sessionStorage.setItem('isLoggedIn', 'true');
                    sessionStorage.setItem('sessionToken', sessionData.token);
                    sessionStorage.setItem('sessionExpiresAt', sessionData.expires_at);
                    sessionStorage.setItem('userEmail', email);
                    // حفظ بيانات المستخدم الكاملة
                    sessionStorage.setItem('userData', JSON.stringify(userData));
                }

                // إظهار رسالة نجاح
                showSuccess('تم تسجيل الدخول بنجاح');

                // إعادة التوجيه إلى صفحة الكورسات بعد ثانية واحدة
                setTimeout(() => {
                    window.location.href = 'courses.html';
                }, 1000);

            } catch (error) {
                console.error('Login error:', error);
                const msg = (error && error.message) ? error.message : '';
                const isNetwork = typeof msg === 'string' && (msg.indexOf('Failed to fetch') !== -1 || msg.indexOf('NetworkError') !== -1 || msg.indexOf('Load failed') !== -1 || msg.indexOf('fetch') !== -1);
                const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent) || /iPhone|iPad|Macintosh.*Safari/i.test(navigator.userAgent);
                let errMsg = isNetwork ? 'تعذر الاتصال بالخادم. تحقق من الاتصال بالإنترنت أو أن الموقع يعمل على الاستضافة.' : 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.';
                if (isSafari && (isNetwork || msg.indexOf('insecure') !== -1 || msg.indexOf('security') !== -1)) {
                    errMsg = 'تعذر الاتصال في Safari. جرّب: 1) تعطيل "منع التتبع عبر المواقع" لهذا الموقع (الإعدادات > Safari)، 2) التأكد من فتح الموقع عبر https:// ونفس الرابط، 3) أو استخدم Chrome أو Firefox.';
                }
                showError(errMsg);
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });
    }

    // وظيفة لإظهار رسائل الخطأ
    function showError(message) {
        // إزالة أي رسائل سابقة
        const existingError = document.querySelector('.login-error-message');
        if (existingError) {
            existingError.remove();
        }

        // إنشاء رسالة خطأ جديدة
        const errorDiv = document.createElement('div');
        errorDiv.className = 'login-error-message';
        errorDiv.style.cssText = `
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        `;
        errorDiv.innerHTML = `
            <i class="bi bi-exclamation-circle-fill" style="font-size: 1.2rem;"></i>
            <span>${message}</span>
        `;

        // إدراج الرسالة قبل النموذج
        const formContent = loginForm.parentElement;
        formContent.insertBefore(errorDiv, loginForm);

        // إزالة الرسالة بعد 5 ثوان
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => errorDiv.remove(), 300);
            }
        }, 5000);
    }

    // وظيفة لإظهار رسائل النجاح
    function showSuccess(message) {
        // إزالة أي رسائل سابقة
        const existingMessage = document.querySelector('.login-success-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        // إنشاء رسالة نجاح جديدة
        const successDiv = document.createElement('div');
        successDiv.className = 'login-success-message';
        successDiv.style.cssText = `
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        `;
        successDiv.innerHTML = `
            <i class="bi bi-check-circle-fill" style="font-size: 1.2rem;"></i>
            <span>${message}</span>
        `;

        // إدراج الرسالة قبل النموذج
        const formContent = loginForm.parentElement;
        formContent.insertBefore(successDiv, loginForm);
    }

    // Terms Modal Functionality
    const termsModal = document.getElementById('termsModal');
    const termsBtn = document.getElementById('termsBtn');
    const closeModal = document.querySelector('.close-modal');
    const closeTermsBtn = document.getElementById('closeTermsBtn');

    if (termsModal && termsBtn) {
        // Open modal
        termsBtn.addEventListener('click', function () {
            termsModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });

        // Close modal (X icon)
        if (closeModal) {
            closeModal.addEventListener('click', function () {
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }

        // Close modal (Close button)
        if (closeTermsBtn) {
            closeTermsBtn.addEventListener('click', function () {
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            });
        }

        // Close when clicking outside
        window.addEventListener('click', function (e) {
            if (e.target === termsModal) {
                termsModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }

    // تسجيل الدخول بالبصمة (WebAuthn) — يفحص البصمات المسجلة على هذا الجهاز للموقع دون طلب الإيميل
    const fingerprintLoginBtn = document.getElementById('fingerprintLoginBtn');
    if (fingerprintLoginBtn && typeof window.WebAuthn !== 'undefined') {
        if (window.WebAuthn.isSupported()) {
            fingerprintLoginBtn.style.display = 'flex';
        }

        fingerprintLoginBtn.addEventListener('click', function () {
            const remember = document.getElementById('remember') ? document.getElementById('remember').checked : false;

            fingerprintLoginBtn.disabled = true;
            fingerprintLoginBtn.innerHTML = '<i class="bi bi-hourglass-split" style="margin-left: 8px;"></i>جاري التحقق...';

            window.WebAuthn.login(
                '',
                remember,
                function (userData, sessionData) {
                    var email = (userData && userData.email) ? userData.email : '';
                    if (remember) {
                        localStorage.setItem('userIdentifier', email);
                        localStorage.setItem('identifierType', 'email');
                        localStorage.setItem('isLoggedIn', 'true');
                        localStorage.setItem('sessionToken', sessionData.token);
                        localStorage.setItem('sessionExpiresAt', sessionData.expires_at);
                        localStorage.setItem('userEmail', email);
                        localStorage.setItem('userData', JSON.stringify(userData));
                    } else {
                        sessionStorage.setItem('userIdentifier', email);
                        sessionStorage.setItem('identifierType', 'email');
                        sessionStorage.setItem('isLoggedIn', 'true');
                        sessionStorage.setItem('sessionToken', sessionData.token);
                        sessionStorage.setItem('sessionExpiresAt', sessionData.expires_at);
                        sessionStorage.setItem('userEmail', email);
                        sessionStorage.setItem('userData', JSON.stringify(userData));
                    }
                    showSuccess('تم تسجيل الدخول بنجاح');
                    setTimeout(function () {
                        window.location.href = 'courses.html';
                    }, 1000);
                },
                function (err) {
                    fingerprintLoginBtn.disabled = false;
                    fingerprintLoginBtn.innerHTML = '<i class="bi bi-fingerprint" style="font-size: 1.1rem;"></i> تسجيل الدخول بالبصمة';
                    showError(err || 'فشل تسجيل الدخول بالبصمة');
                }
            );
        });
    }
});

// إضافة تأثيرات CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
`;
document.head.appendChild(style);
