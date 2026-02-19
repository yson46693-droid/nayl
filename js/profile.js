// Profile page functionality
document.addEventListener('DOMContentLoaded', async function () {
    // 1. التحميل المبدئي لبيانات المستخدم
    loadUserData();

    // 2. تحديث رصيد المحفظة
    await initWallet();

    // 3. تحميل طلبات تعبئة الرصيد
    initRechargeRequests();

    // 4. التحقق من حالة التوثيق (بعد تحديث بيانات المستخدم من الخادم)
    await checkVerificationStatus();

    // 4. تفعيل تبديل التبويبات (Tabs)
    initTabs();

    // 5. ربط زر تسجيل الخروج
    initLogout();

    // 6. التعامل مع فورم الأمان (تغيير كلمة المرور)
    initSecurityForm();



    // 8. التعامل مع حذف الحساب
    initDeleteAccount();

    // 9. تحميل الأجهزة المتصلة عند فتح تبويب الأمان
    initDevicesLoading();

    // 9.1 تهيئة تسجيل البصمة (WebAuthn) في تبويب الأمان
    initWebAuthnProfile();

    // 10. تهيئة تحميل أكواد الكورسات عند فتح التبويب
    initCourseCodesLoading();

    // 10.1 تهيئة تحميل أكواد الخصم المستخدمة عند فتح التبويب
    initDiscountCodesLoading();

    // 11. تهيئة رفع صورة الملف الشخصي - تم تعطيله
    // initProfilePictureUpload();
});

/**
 * بناء رابط API يعمل من الجذر أو من مجلد فرعي (مثل yoursite.com/nayl/)
 * يمنع ظهور "خطأ في الاتصال" في صفحة أكوادي وباقي تبويبات الملف الشخصي
 */
function getProfileApiUrl(relativePath) {
    const path = (relativePath || '').replace(/^\//, '');
    if (typeof window.API_BASE !== 'undefined' && window.API_BASE) {
        const base = window.API_BASE.endsWith('/') ? window.API_BASE : window.API_BASE + '/';
        return base + path;
    }
    const pathname = window.location.pathname || '';
    const dir = pathname.substring(0, pathname.lastIndexOf('/') + 1);
    const fullRelative = dir + path;
    if (window.location.origin) {
        return window.location.origin + (fullRelative.startsWith('/') ? fullRelative : '/' + fullRelative);
    }
    return fullRelative;
}

/**
 * تحميل بيانات المستخدم من التخزين المحلي وتعبئتها في النموذج
 */
function loadUserData() {
    const user = getCurrentUser(); // دالة من auth-check.js

    if (!user) return;

    // تعبئة الاسم في الهيدر (بطاقة البروفايل)
    const displayName = document.getElementById('display-name');
    if (displayName) {
        // الأولوية للاسم الكامل من قاعدة البيانات
        displayName.textContent = user.full_name || user.name || user.email.split('@')[0] || 'مستخدم';
    }

    // تعبئة معرف المستخدم في بطاقة البروفايل
    const displayUserId = document.getElementById('user-id-value');
    if (displayUserId) {
        const userId = user.id || user.user_id || '';
        displayUserId.textContent = userId || '-';
    }

    // تعبئة البيانات في فورم المعلومات الشخصية
    const fields = {
        'user-id': user.id || user.user_id || '',
        'full-name': user.full_name || user.name || '',
        'email': user.email || '',
        'phone': user.full_phone || user.phone || '',
        'city': user.city || '',
        'country': user.country || ''
    };

    for (const [id, value] of Object.entries(fields)) {
        const input = document.getElementById(id);
        if (input) {
            input.value = value;
        }
    }

    // تعبئة اسم حامل البطاقة في المحفظة
    const walletHolderName = document.getElementById('wallet-holder-name');
    if (walletHolderName) {
        walletHolderName.textContent = user.full_name || user.name || '---';
    }

    // تعبئة معرف المحفظة
    const walletId = document.getElementById('user-wallet-id');
    if (walletId) {
        const userId = user.id || user.user_id || '';
        walletId.textContent = userId.toString().padStart(4, '0').slice(-4);
    }

    // تعبئة صورة الملف الشخصي - تم تعطيلها بناءً على طلب المستخدم (استخدام أيقونة ثابتة)
    /*
    const userAvatarContainer = document.querySelector('.user-avatar');
    if (userAvatarContainer && user.profile_image) {
        userAvatarContainer.innerHTML = `<img src="${user.profile_image}" alt="Profile Picture" onerror="this.src='pics/logo.png'">`;
        // تخزينها في localStorage كاحتياط
        // localStorage.setItem('userProfileImage', user.profile_image);
    } else if (userAvatarContainer) {
        // محاولة التحميل من localStorage إذا لم توجد في بيانات المستخدم (للتوافق القديم)
        const savedImage = localStorage.getItem('userProfileImage');
        if (savedImage) {
            userAvatarContainer.innerHTML = `<img src="${savedImage}" alt="Profile Picture">`;
        }
    }
    */
}

/**
 * تهيئة رصيد المحفظة
 */
async function initWallet() {
    const balanceElement = document.getElementById('wallet-balance');

    if (!balanceElement) return;

    // إظهار حالة التحميل
    balanceElement.textContent = '...';

    // جلب الرصيد من API
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        balanceElement.textContent = '0.00';
        return;
    }

    try {
        const response = await fetch(getProfileApiUrl('api/wallet/get-balance.php'), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });

        const result = await response.json();

        if (response.ok && result.success) {
            balanceElement.textContent = result.data.balance;
        } else {
            balanceElement.textContent = '0.00';
        }
    } catch (error) {
        console.error('Error fetching wallet balance:', error);
        balanceElement.textContent = '0.00';
    }

    // إزالة أي بيانات افتراضية من localStorage
    localStorage.removeItem('walletBalance');
}

/**
 * التحقق من حالة التوثيق وتحديث الواجهة
 * يحدّث بيانات المستخدم من الخادم أولاً لضمان ظهور "حساب موثق" لمن لديه اشتراك/كود
 */
async function checkVerificationStatus() {
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    if (sessionToken) {
        try {
            const verifyUrl = getProfileApiUrl('api/auth/verify.php');
            const response = await fetch(verifyUrl, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${sessionToken}` },
                credentials: 'include'
            });
            if (response.ok) {
                const text = await response.text();
                let result = null;
                try { result = text ? JSON.parse(text) : {}; } catch (e) { return; }
                if (result.success && result.data && result.data.email) {
                    const storage = localStorage.getItem('sessionToken') ? localStorage : sessionStorage;
                    storage.setItem('userData', JSON.stringify(result.data));
                }
            }
        } catch (e) {
            // تجاهل - نعتمد على البيانات المحلية
        }
    }

    const user = getCurrentUser();
    const verificationBadge = document.querySelector('.user-verification');
    const verificationStatus = document.getElementById('verification-status');

    if (!verificationBadge || !verificationStatus) return;

    const hasPurchasedCourses = localStorage.getItem('hasPurchasedCourses') === 'true';
    const isVerified = user && (user.is_verified || user.is_active);
    const isVip = user && user.account_type === 'vip';

    if (isVerified || hasPurchasedCourses || isVip) {
        verificationStatus.textContent = 'حساب VIP';
        verificationBadge.classList.remove('free');
        // أيقونة التوثيق الخضراء (افتراضية بالفعل في HTML)
    } else {
        verificationStatus.textContent = 'حساب مجاني';
        verificationBadge.classList.add('free');

        // تحديث الأيقونة لتكون رمادية أو غير موثقة
        const svg = verificationBadge.querySelector('svg');
        if (svg) {
            svg.innerHTML = `
                <circle cx="12" cy="12" r="10" fill="#8b95a5"/>
                <path d="M8 12L11 15L16 9" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            `;
        }
    }
}

/**
 * منطق تبديل التبويبات
 */
function initTabs() {
    const navItems = document.querySelectorAll('.profile-nav-item');
    const tabs = document.querySelectorAll('.profile-tab');

    // Restore active tab from localStorage
    const savedTab = localStorage.getItem('profile_active_tab');
    if (savedTab) {
        const targetNav = document.querySelector(`.profile-nav-item[data-tab="${savedTab}"]`);
        const targetTab = document.getElementById(`${savedTab}-tab`);

        if (targetNav && targetTab) {
            // Remove active class from all
            navItems.forEach(nav => nav.classList.remove('active'));
            tabs.forEach(tab => tab.classList.remove('active'));

            // Add active class to saved tab
            targetNav.classList.add('active');
            targetTab.classList.add('active');

            // Trigger specific loads
            if (savedTab === 'wallet') {
                setTimeout(() => {
                    if (typeof initRechargePagination === 'function') initRechargePagination();
                }, 100);
            } else if (savedTab === 'security') {
                setTimeout(() => {
                    if (typeof loadDevices === 'function') loadDevices();
                }, 100);
            } else if (savedTab === 'complaints') {
                setTimeout(() => {
                    if (typeof loadComplaints === 'function') loadComplaints();
                }, 100);
            } else if (savedTab === 'course-codes') {
                setTimeout(() => {
                    if (typeof loadCourseCodes === 'function') loadCourseCodes();
                }, 100);
            }
        }
    }

    navItems.forEach(item => {
        item.addEventListener('click', function () {
            const tabId = this.getAttribute('data-tab');

            // Save active tab to localStorage
            localStorage.setItem('profile_active_tab', tabId);

            // إزالة الكلاس النشط من الجميع
            navItems.forEach(nav => nav.classList.remove('active'));
            tabs.forEach(tab => tab.classList.remove('active'));

            // إضافة الكلاس النشط للعنصر والتبويب المختار
            this.classList.add('active');
            const targetTab = document.getElementById(`${tabId}-tab`);
            if (targetTab) {
                targetTab.classList.add('active');
            }
            // تحميل البيانات عند النقر على التبويب
            if (tabId === 'course-codes' && typeof loadCourseCodes === 'function') {
                setTimeout(loadCourseCodes, 100);
            }
            if (tabId === 'discount-codes' && typeof loadMyDiscountCodes === 'function') {
                setTimeout(loadMyDiscountCodes, 100);
            }
        });
    });

    // Check URL parameters for tab
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    if (tabParam) {
        const targetNav = document.querySelector(`.profile-nav-item[data-tab="${tabParam}"]`);
        if (targetNav) {
            // Trigger click to activate logic
            targetNav.click();
        }
    }
}

/**
 * منطق تسجيل الخروج
 */
function initLogout() {
    const logoutBtn = document.querySelector('.logout-item');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            e.stopPropagation();

            const confirmed = confirm('هل أنت متأكد من تسجيل الخروج؟');
            console.log('Logout confirmed:', confirmed);

            if (confirmed) {
                console.log('Starting logout process...');

                // التأكد من وجود دالة logout
                if (typeof window.logout === 'function') {
                    console.log('Using window.logout');
                    try {
                        await window.logout();
                    } catch (error) {
                        console.error('Error in logout:', error);
                        // Fallback: مسح البيانات المحلية والتوجيه
                        localStorage.clear();
                        sessionStorage.clear();
                        document.cookie.split(";").forEach(function (c) {
                            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                        });
                        window.location.href = 'home.html';
                    }
                } else if (typeof logout === 'function') {
                    console.log('Using logout directly');
                    try {
                        await logout();
                    } catch (error) {
                        console.error('Error in logout:', error);
                        // Fallback: مسح البيانات المحلية والتوجيه
                        localStorage.clear();
                        sessionStorage.clear();
                        document.cookie.split(";").forEach(function (c) {
                            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                        });
                        window.location.href = 'home.html';
                    }
                } else {
                    console.error('Logout function not found');
                    // Fallback: مسح البيانات المحلية والتوجيه
                    localStorage.clear();
                    sessionStorage.clear();
                    document.cookie.split(";").forEach(function (c) {
                        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                    });
                    window.location.href = 'home.html';
                }
            }
        });
    } else {
        console.warn('Logout button not found');
    }
}

/**
 * فورم الأمان - تغيير كلمة المرور
 */
function initSecurityForm() {
    const securityForm = document.querySelector('.security-form');
    if (securityForm) {
        securityForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const currentPass = document.getElementById('current-password').value;
            const newPass = document.getElementById('new-password').value;
            const confirmPass = document.getElementById('confirm-password').value;

            if (!currentPass || !newPass || !confirmPass) {
                alert('الرجاء تعبئة جميع الحقول');
                return;
            }

            if (newPass !== confirmPass) {
                alert('كلمة المرور الجديدة غير متطابقة');
                return;
            }

            if (newPass.length < 6) {
                alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
                return;
            }

            // هنا يتم إرسال الطلب للـ API في المرحلة القادمة
            alert('تم إرسال طلب تحديث كلمة المرور بنجاح (سيكون متاحاً قريباً)');
            this.reset();
        });
    }
}



/**
 * تنظيف النص لعرض آمن (حماية XSS)
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
    if (text == null || text === '') return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * تحميل البصمة المسجلة وعرضها في الجدول (بصمة واحدة فقط لكل مستخدم)
 */
function loadWebAuthnCredential() {
    const tableWrap = document.getElementById('webauthn-table-wrap');
    const tbody = document.getElementById('webauthn-credential-tbody');
    const noCredentialMsg = document.getElementById('webauthn-no-credential-msg');
    const registerArea = document.getElementById('webauthn-register-area');
    const registerBtn = document.getElementById('webauthn-register-btn');
    const deleteBtn = document.getElementById('webauthn-delete-btn');

    if (!tableWrap || !tbody) return;

    fetch('api/auth/webauthn/get-credentials.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({})
    })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (!result.success) {
                if (noCredentialMsg) noCredentialMsg.style.display = 'block';
                if (registerBtn) registerBtn.style.display = 'inline-flex';
                if (deleteBtn) deleteBtn.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="4" class="webauthn-empty-cell">لا توجد بيانات</td></tr>';
                return;
            }
            var hasCredential = result.data && result.data.hasCredential && result.data.credential;
            var cred = result.data && result.data.credential;

            if (hasCredential && cred) {
                var createdAt = cred.created_at || '—';
                if (createdAt !== '—' && createdAt.indexOf(' ') === -1) createdAt = createdAt.replace('T', ' ').substring(0, 16);
                tbody.innerHTML =
                    '<tr>' +
                    '<td>' + (cred.device_name ? escapeHtml(cred.device_name) : '—') + '</td>' +
                    '<td dir="ltr" class="webauthn-cred-id">' + (cred.credential_id_short ? escapeHtml(cred.credential_id_short) : '—') + '</td>' +
                    '<td>' + escapeHtml(createdAt) + '</td>' +
                    '<td><button type="button" class="btn-webauthn-delete-row" data-action="delete" title="حذف البصمة"><i class="bi bi-trash"></i></button></td>' +
                    '</tr>';
                if (noCredentialMsg) noCredentialMsg.style.display = 'none';
                if (registerBtn) registerBtn.style.display = 'none';
                if (deleteBtn) deleteBtn.style.display = 'inline-flex';
                var deleteRowBtn = tbody.querySelector('.btn-webauthn-delete-row');
                if (deleteRowBtn) {
                    deleteRowBtn.addEventListener('click', function () {
                        if (!confirm('هل تريد حذف البصمة المسجلة؟ بعد الحذف يمكنك تسجيل بصمة جديدة.')) return;
                        fetch('api/auth/webauthn/delete-credentials.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include'
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                if (res.success) {
                                    if (typeof alert === 'function') alert(res.data && res.data.message ? res.data.message : 'تم حذف البصمة.');
                                    loadWebAuthnCredential();
                                } else {
                                    if (typeof alert === 'function') alert(res.error || 'فشل حذف البصمة');
                                }
                            })
                            .catch(function () {
                                if (typeof alert === 'function') alert('حدث خطأ في الاتصال');
                            });
                    });
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="webauthn-empty-cell">لا توجد بصمة مسجلة. سجّل بصمة واحدة من الزر أدناه.</td></tr>';
                if (noCredentialMsg) noCredentialMsg.style.display = 'block';
                if (registerBtn) registerBtn.style.display = 'inline-flex';
                if (deleteBtn) deleteBtn.style.display = 'none';
            }
        })
        .catch(function () {
            tbody.innerHTML = '<tr><td colspan="4" class="webauthn-empty-cell">فشل تحميل البيانات</td></tr>';
            if (noCredentialMsg) noCredentialMsg.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'inline-flex';
            if (deleteBtn) deleteBtn.style.display = 'none';
        });
}

/**
 * تهيئة تسجيل البصمة (WebAuthn) في تبويب الأمان
 */
function initWebAuthnProfile() {
    const registerArea = document.getElementById('webauthn-register-area');
    const unsupportedArea = document.getElementById('webauthn-unsupported');
    const registerBtn = document.getElementById('webauthn-register-btn');
    const tableWrap = document.getElementById('webauthn-table-wrap');

    if (!registerArea || !unsupportedArea) return;

    if (typeof window.WebAuthn !== 'undefined' && window.WebAuthn.isSupported()) {
        registerArea.style.display = 'block';
        unsupportedArea.style.display = 'none';
        if (tableWrap) tableWrap.style.display = 'block';
        loadWebAuthnCredential();

        if (registerBtn) {
            registerBtn.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split" style="margin-left: 8px;"></i>جاري التسجيل...';

                window.WebAuthn.register(
                    function () {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        if (typeof alert === 'function') {
                            alert('تم تسجيل البصمة بنجاح. يمكنك الآن تسجيل الدخول بالبصمة من صفحة تسجيل الدخول.');
                        }
                        loadWebAuthnCredential();
                    },
                    function (err) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        if (typeof alert === 'function') {
                            alert(err || 'حدث خطأ أثناء تسجيل البصمة');
                        }
                    }
                );
            });
        }

        const deleteBtn = document.getElementById('webauthn-delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (!confirm('هل تريد حذف البصمة المسجلة؟ بعد الحذف يمكنك تسجيل بصمة جديدة.')) return;
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split" style="margin-left: 8px;"></i>جاري الحذف...';
                fetch('api/auth/webauthn/delete-credentials.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include'
                })
                    .then(function (res) { return res.json(); })
                    .then(function (result) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        if (result.success) {
                            if (typeof alert === 'function') alert(result.data && result.data.message ? result.data.message : 'تم حذف البصمة.');
                            loadWebAuthnCredential();
                        } else {
                            if (typeof alert === 'function') alert(result.error || 'فشل حذف البصمة');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        if (typeof alert === 'function') alert('حدث خطأ في الاتصال');
                    });
            });
        }
    } else {
        registerArea.style.display = 'none';
        unsupportedArea.style.display = 'block';
        if (tableWrap) tableWrap.style.display = 'none';
    }
}

/**
 * تهيئة تحميل الأجهزة عند فتح تبويب الأمان
 */
function initDevicesLoading() {
    const securityTabBtn = document.querySelector('[data-tab="security"]');
    const devicesContainer = document.getElementById('devices-container');

    if (!securityTabBtn || !devicesContainer) return;

    // استماع لفتح تبويب الأمان
    securityTabBtn.addEventListener('click', function () {
        // تحميل الأجهزة والبصمة المسجلة بعد تأخير بسيط
        setTimeout(function () {
            loadDevices();
            if (typeof loadWebAuthnCredential === 'function') loadWebAuthnCredential();
        }, 100);
    });

    // إذا كان التبويب مفتوحاً بالفعل عند تحميل الصفحة
    const securityTab = document.getElementById('security-tab');
    if (securityTab && securityTab.classList.contains('active')) {
        loadDevices();
        if (typeof loadWebAuthnCredential === 'function') loadWebAuthnCredential();
    }
}

/**
 * تهيئة تحميل أكواد الكورسات عند فتح التبويب
 */
function initCourseCodesLoading() {
    const courseCodesTabBtn = document.querySelector('[data-tab="course-codes"]');
    const container = document.getElementById('course-codes-container');

    if (!courseCodesTabBtn || !container) return;

    courseCodesTabBtn.addEventListener('click', function () {
        setTimeout(loadCourseCodes, 100);
    });

    const courseCodesTab = document.getElementById('course-codes-tab');
    if (courseCodesTab && courseCodesTab.classList.contains('active')) {
        loadCourseCodes();
    }
}

/**
 * عرض واجهة خطأ في تبويب الأكواد مع زر إعادة المحاولة
 */
function showCourseCodesError(title, message, isConnectionError) {
    const container = document.getElementById('course-codes-container');
    const loadingEl = document.getElementById('course-codes-loading');
    if (!container) return;
    if (loadingEl) loadingEl.classList.add('hidden');
    const safeTitle = (title || 'خطأ').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const safeMessage = (message || 'حدث خطأ. حاول مرة أخرى.').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    container.innerHTML = `
        <div class="course-codes-empty">
            <div class="course-codes-empty-icon"><i class="bi bi-wifi-off"></i></div>
            <h4>${safeTitle}</h4>
            <p>${safeMessage}</p>
            ${isConnectionError ? '<button type="button" class="btn-retry-codes" id="btn-retry-course-codes"><i class="bi bi-arrow-clockwise"></i> إعادة المحاولة</button>' : ''}
        </div>
    `;
    const retryBtn = document.getElementById('btn-retry-course-codes');
    if (retryBtn) retryBtn.addEventListener('click', function () { loadCourseCodes(); });
}

/**
 * تحميل أكواد الكورسات من API
 */
async function loadCourseCodes() {
    const container = document.getElementById('course-codes-container');
    const loadingEl = document.getElementById('course-codes-loading');

    if (!container) return;

    if (loadingEl) {
        loadingEl.classList.remove('hidden');
        loadingEl.style.display = '';
    }
    container.innerHTML = '';
    if (loadingEl) container.appendChild(loadingEl);

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        if (loadingEl) loadingEl.classList.add('hidden');
        container.innerHTML = `
            <div class="course-codes-empty">
                <div class="course-codes-empty-icon"><i class="bi bi-lock-fill"></i></div>
                <h4>يجب تسجيل الدخول</h4>
                <p>يجب تسجيل الدخول لعرض أكواد الكورسات</p>
            </div>
        `;
        return;
    }

    const apiUrl = getProfileApiUrl('api/courses/get-my-course-codes.php');

    try {
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });

        const text = await response.text();
        let result = null;
        try {
            result = text ? JSON.parse(text) : {};
        } catch (parseErr) {
            console.error('Course codes API returned non-JSON:', parseErr);
            if (loadingEl) loadingEl.classList.add('hidden');
            showCourseCodesError(
                'رد غير متوقع من الخادم',
                response.ok ? 'تعذر قراءة البيانات. تأكد أن الموقع يعمل بشكل صحيح.' : 'الخادم أعاد خطأ (رمز ' + response.status + '). حاول لاحقاً أو تواصل مع الدعم.',
                true
            );
            return;
        }

        if (loadingEl) loadingEl.classList.add('hidden');

        if (response.ok && result.success && result.data && result.data.codes) {
            renderCourseCodes(result.data.codes);
        } else if (response.ok && result.success && (!result.data || !result.data.codes || result.data.codes.length === 0)) {
            container.innerHTML = `
                <div class="course-codes-empty">
                    <div class="course-codes-empty-icon"><i class="bi bi-upc-scan"></i></div>
                    <h4>لا توجد أكواد حالياً</h4>
                    <p>ستظهر هنا الأكواد الخاصة بمشاهدة الكورسات بعد شرائك لأي كورس</p>
                </div>
            `;
        } else {
            const errMsg = (result && result.error) ? result.error : 'تعذر تحميل الأكواد. حاول مرة أخرى.';
            const isAuth = result && result.code === 'UNAUTHORIZED';
            showCourseCodesError(isAuth ? 'يجب تسجيل الدخول' : 'حدث خطأ', errMsg, !response.ok);
        }
    } catch (error) {
        console.error('Error loading course codes:', error);
        showCourseCodesError('خطأ في الاتصال', 'حدث خطأ في الاتصال. تحقق من الإنترنت أو حاول مرة أخرى.', true);
    }
}

/**
 * عرض أكواد الكورسات في الشبكة
 */
function renderCourseCodes(codes) {
    const container = document.getElementById('course-codes-container');
    const loadingEl = document.getElementById('course-codes-loading');

    if (!container) return;

    if (loadingEl) loadingEl.remove();

    const grid = document.createElement('div');
    grid.className = 'course-codes-grid';

    codes.forEach(item => {
        const card = document.createElement('div');
        card.className = 'course-code-card';
        card.setAttribute('data-code', item.code);
        card.title = 'اضغط لنسخ الكود';

        const coverHtml = item.cover_image_url
            ? `<img src="${item.cover_image_url}" alt="" class="course-code-card-cover">`
            : `<div class="course-code-card-cover-placeholder"><i class="bi bi-play-circle-fill"></i></div>`;

        const sanitizedTitle = (item.course_title || 'كورس').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const sanitizedCode = (item.code || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        card.innerHTML = `
            <div class="course-code-card-header">
                ${coverHtml}
                <h3 class="course-code-card-title">${sanitizedTitle}</h3>
            </div>
            <div class="course-code-card-body">
                <span class="course-code-label">كود المشاهدة</span>
                <div class="course-code-value">${sanitizedCode}</div>
            </div>
            <div class="course-code-card-footer">
                <i class="bi bi-clipboard"></i>
                <span>اضغط لنسخ الكود</span>
            </div>
        `;

        card.addEventListener('click', function () {
            copyCourseCode(item.code, card);
        });

        grid.appendChild(card);
    });

    container.innerHTML = '';
    container.appendChild(grid);
}

/**
 * نسخ كود الكورس مع التغذية الراجعة
 */
function copyCourseCode(code, cardElement) {
    const textToCopy = (code || '').toString();

    const doCopy = () => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(textToCopy);
        }
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            return Promise.resolve();
        } catch (e) {
            return Promise.reject(e);
        } finally {
            document.body.removeChild(textArea);
        }
    };

    doCopy().then(() => {
        if (cardElement) {
            cardElement.classList.add('copied');
            const footer = cardElement.querySelector('.course-code-card-footer');
            if (footer) {
                const oldContent = footer.innerHTML;
                footer.innerHTML = '<i class="bi bi-check-circle-fill"></i><span>تم النسخ!</span>';
                footer.style.color = '#4CAF50';
                setTimeout(() => {
                    footer.innerHTML = oldContent;
                    footer.style.color = '';
                    cardElement.classList.remove('copied');
                }, 2000);
            }
        }
        showCopyFeedback(code);
    }).catch(() => {
        alert('لم يتم النسخ. الكود: ' + textToCopy);
    });
}

/**
 * عرض رسالة تأكيد النسخ
 */
function showCopyFeedback(code) {
    const toast = document.createElement('div');
    toast.className = 'copy-toast';
    toast.innerHTML = `
        <i class="bi bi-check-circle-fill"></i>
        <span>تم نسخ كود الكورس بنجاح</span>
    `;
    Object.assign(toast.style, {
        position: 'fixed',
        bottom: '20px',
        left: '50%',
        transform: 'translateX(-50%) translateY(100px)',
        background: '#1a2332',
        color: 'white',
        padding: '12px 24px',
        borderRadius: '50px',
        boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        zIndex: '10000',
        transition: 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        fontWeight: '500',
        opacity: '0'
    });
    document.body.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(-50%) translateY(0)';
        toast.style.opacity = '1';
    });
    setTimeout(() => {
        toast.style.transform = 'translateX(-50%) translateY(100px)';
        toast.style.opacity = '0';
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 2500);
}

/**
 * تهيئة تحميل أكواد الخصم المستخدمة عند فتح التبويب
 */
function initDiscountCodesLoading() {
    const discountCodesTabBtn = document.querySelector('[data-tab="discount-codes"]');
    if (!discountCodesTabBtn) return;
    discountCodesTabBtn.addEventListener('click', function () {
        setTimeout(loadMyDiscountCodes, 100);
    });
    const discountCodesTab = document.getElementById('discount-codes-tab');
    if (discountCodesTab && discountCodesTab.classList.contains('active')) {
        loadMyDiscountCodes();
    }
}

/**
 * تحميل أكواد الخصم التي استخدمها المستخدم من API
 */
async function loadMyDiscountCodes() {
    const listEl = document.getElementById('discount-codes-list');
    const loadingEl = document.getElementById('discount-codes-loading');
    const emptyEl = document.getElementById('discount-codes-empty');
    if (!listEl) return;
    if (loadingEl) loadingEl.style.display = 'flex';
    if (emptyEl) emptyEl.style.display = 'none';
    listEl.innerHTML = '';

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    if (!sessionToken) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (emptyEl) { emptyEl.textContent = 'يجب تسجيل الدخول لعرض أكواد الخصم المستخدمة.'; emptyEl.style.display = 'block'; }
        return;
    }

    try {
        const response = await fetch(getProfileApiUrl('api/discount-codes/get-my-used.php'), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + sessionToken },
            credentials: 'include'
        });
        const result = await response.json();
        if (loadingEl) loadingEl.style.display = 'none';

        if (response.ok && result.success && result.data && Array.isArray(result.data.discount_codes)) {
            const codes = result.data.discount_codes;
            if (codes.length === 0) {
                if (emptyEl) { emptyEl.textContent = 'لم تستخدم أي كود خصم بعد.'; emptyEl.style.display = 'block'; }
                return;
            }
            codes.forEach(function (d) {
                const title = (d.course_title || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const code = (d.code || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const usedAt = d.used_at ? new Date(d.used_at).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
                const row = document.createElement('div');
                row.className = 'discount-code-used-card';
                row.innerHTML = `
                    <div class="discount-code-used-header">
                        <span class="discount-code-used-code">${code}</span>
                        <span class="discount-code-used-amount">-${parseFloat(d.discount_amount).toFixed(2)} ج.م</span>
                    </div>
                    <div class="discount-code-used-body">
                        <span class="discount-code-used-course">${title}</span>
                        <span class="discount-code-used-date">${usedAt}</span>
                    </div>
                `;
                listEl.appendChild(row);
            });
        } else {
            if (emptyEl) { emptyEl.textContent = result.error || 'تعذر تحميل البيانات.'; emptyEl.style.display = 'block'; }
        }
    } catch (err) {
        console.error('Load my discount codes error:', err);
        if (loadingEl) loadingEl.style.display = 'none';
        if (emptyEl) { emptyEl.textContent = 'حدث خطأ في الاتصال. حاول مرة أخرى.'; emptyEl.style.display = 'block'; }
    }
}

/**
 * تحميل الأجهزة المتصلة من API
 */
async function loadDevices() {
    const devicesContainer = document.getElementById('devices-container');
    if (!devicesContainer) return;

    // إظهار حالة التحميل
    devicesContainer.innerHTML = `
        <div class="empty-state">
            <p>جاري تحميل قائمة الأجهزة...</p>
        </div>
    `;

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        devicesContainer.innerHTML = `
            <div class="empty-state">
                <p>يجب تسجيل الدخول لعرض الأجهزة المتصلة</p>
            </div>
        `;
        return;
    }

    try {
        const response = await fetch(getProfileApiUrl('api/auth/get-devices.php'), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });

        const result = await response.json();

        if (response.ok && result.success && result.data && result.data.devices) {
            renderDevices(result.data.devices);
        } else {
            devicesContainer.innerHTML = `
                <div class="empty-state">
                    <p>${result.error || 'حدث خطأ أثناء تحميل الأجهزة'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading devices:', error);
        devicesContainer.innerHTML = `
            <div class="empty-state">
                <p>حدث خطأ في الاتصال. حاول مرة أخرى.</p>
            </div>
        `;
    }
}

/**
 * عرض الأجهزة في القائمة
 */
function renderDevices(devices) {
    const devicesContainer = document.getElementById('devices-container');
    if (!devicesContainer) return;

    if (!devices || devices.length === 0) {
        devicesContainer.innerHTML = `
            <div class="empty-state">
                <p>لا توجد أجهزة متصلة حالياً</p>
            </div>
        `;
        return;
    }

    let html = '';

    devices.forEach(device => {
        const deviceIcon = getDeviceIcon(device.device_type);
        const browserIcon = getBrowserIcon(device.browser_name);
        const isCurrent = device.is_current_device;
        // استخدام last_login بدلاً من last_active
        const lastLogin = formatLastLogin(device.last_login || device.last_active);

        html += `
            <div class="device-item ${isCurrent ? 'current-device' : ''}">
                <div class="device-icon">
                    ${deviceIcon}
                </div>
                <div class="device-info">
                    <div class="device-header">
                        <h4 class="device-name">
                            ${device.device_name}
                            ${isCurrent ? '<span class="current-badge">هذا الجهاز</span>' : ''}
                        </h4>
                    </div>
                    <div class="device-details">
                        <div class="device-detail-item">
                            <span class="detail-icon">${browserIcon}</span>
                            <span class="detail-text">${device.browser_name}${device.browser_version ? ' ' + device.browser_version.split('.')[0] : ''}</span>
                        </div>
                        <div class="device-detail-item">
                            <span class="detail-icon">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <rect x="2" y="3" width="12" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M2 6H14" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </span>
                            <span class="detail-text">${device.os_name}${device.os_version ? ' ' + device.os_version.split('.')[0] : ''}</span>
                        </div>
                        <div class="device-detail-item">
                            <span class="detail-icon">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="detail-text">آخر تسجيل دخول: ${lastLogin}</span>
                        </div>
                       
                    </div>
                </div>
                ${!isCurrent ? `
                    <button class="device-remove-btn" data-session-id="${device.session_id}" title="إزالة الجهاز">
                        <i class="bi bi-x-lg"></i>
                    </button>
                ` : ''}
            </div>
        `;
    });

    devicesContainer.innerHTML = html;

    // ربط أزرار إزالة الأجهزة
    const removeButtons = devicesContainer.querySelectorAll('.device-remove-btn');
    removeButtons.forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            e.stopPropagation();

            const sessionId = this.getAttribute('data-session-id');
            console.log('Removing device with session_id:', sessionId);

            if (!sessionId) {
                alert('خطأ: معرف الجلسة غير موجود');
                return;
            }

            if (confirm('هل أنت متأكد من إزالة هذا الجهاز؟ سيتم تسجيل الخروج من هذا الجهاز.')) {
                // إظهار حالة التحميل
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                this.disabled = true;

                try {
                    await removeDevice(sessionId);
                } finally {
                    // استعادة الزر حتى لو فشلت العملية
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            }
        });
    });
}

/**
 * إزالة جهاز (تسجيل الخروج من جلسة محددة)
 */
async function removeDevice(sessionId) {
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        alert('يجب تسجيل الدخول لإزالة الجهاز');
        return;
    }

    if (!sessionId) {
        console.error('Session ID is missing');
        alert('خطأ: معرف الجلسة غير موجود');
        return;
    }

    try {
        const response = await fetch(getProfileApiUrl('api/auth/remove-device.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            body: JSON.stringify({ session_id: parseInt(sessionId) }),
            credentials: 'include'
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // إعادة تحميل قائمة الأجهزة
            await loadDevices();
        } else {
            alert(result.error || 'حدث خطأ أثناء إزالة الجهاز');
        }
    } catch (error) {
        console.error('Error removing device:', error);
        alert('حدث خطأ في الاتصال. حاول مرة أخرى.');
    }
}

/**
 * الحصول على أيقونة الجهاز
 */
function getDeviceIcon(deviceType) {
    const icons = {
        'desktop': `
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="4" y="6" width="24" height="16" rx="1" fill="currentColor" opacity="0.2"/>
                <rect x="4" y="6" width="24" height="16" rx="1" stroke="currentColor" stroke-width="2"/>
                <path d="M10 24H22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M14 28H18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        `,
        'mobile': `
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="10" y="4" width="12" height="24" rx="2" fill="currentColor" opacity="0.2"/>
                <rect x="10" y="4" width="12" height="24" rx="2" stroke="currentColor" stroke-width="2"/>
                <path d="M16 8H16.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        `,
        'tablet': `
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <rect x="8" y="4" width="16" height="24" rx="1" fill="currentColor" opacity="0.2"/>
                <rect x="8" y="4" width="16" height="24" rx="1" stroke="currentColor" stroke-width="2"/>
            </svg>
        `
    };

    return icons[deviceType] || icons['desktop'];
}

/**
 * الحصول على أيقونة المتصفح
 */
function getBrowserIcon(browserName) {
    const icons = {
        'Chrome': `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="6" fill="#4285F4"/>
                <circle cx="8" cy="8" r="3" fill="white"/>
            </svg>
        `,
        'Firefox': `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 2C10 2 11 4 11 4L9 8H13C13 8 14 10 12 12C12 12 10 14 8 14C8 14 6 12 6 10C6 10 4 8 6 6C6 6 7 4 8 2Z" fill="#FF7139"/>
            </svg>
        `,
        'Safari': `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" fill="#007AFF" stroke="white" stroke-width="0.5"/>
            </svg>
        `,
        'Edge': `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M8 2L12 6L8 10L4 6L8 2Z" fill="#0078D4"/>
            </svg>
        `,
        'Opera': `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="6" fill="#FF1B2D"/>
            </svg>
        `
    };

    return icons[browserName] || `
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/>
        </svg>
    `;
}

// Global variables for pagination
let rechargePage = 1;
const rechargeLimit = 5;
let rechargeTotalPages = 0;

/**
 * تهيئة تحميل طلبات تعبئة الرصيد
 */
function initRechargeRequests() {
    const walletTabBtn = document.querySelector('[data-tab="wallet"]');
    const container = document.getElementById('recharge-requests-container');

    if (!walletTabBtn || !container) return;

    // استماع لفتح تبويب المحفظة
    walletTabBtn.addEventListener('click', function () {
        setTimeout(() => {
            initRechargePagination();
        }, 100);
    });

    // إذا كان التبويب مفتوحاً بالفعل عند تحميل الصفحة
    const walletTab = document.getElementById('wallet-tab');
    if (walletTab && walletTab.classList.contains('active')) {
        initRechargePagination();
    }
}

/**
 * تحميل طلبات تعبئة الرصيد من API
 */
async function loadRechargeRequests() {
    const container = document.getElementById('recharge-requests-container');
    if (!container) return;

    // إظهار حالة التحميل بنفس نمط المعاملات
    container.innerHTML = `
        <div class="transactions-loading" style="text-align: center; padding: 2rem; color: #8b95a5;">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" style="animation: spin 1s linear infinite; margin: 0 auto;">
                <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="25" opacity="0.3"/>
                <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="3" stroke-dasharray="50" stroke-dashoffset="25" stroke-linecap="round"/>
            </svg>
            <p style="margin-top: 1rem;">جاري تحميل الطلبات...</p>
        </div>
    `;

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    if (!sessionToken) {
        container.innerHTML = `
            <div class="transactions-empty">
                <i class="bi bi-lock-fill" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>يجب تسجيل الدخول لعرض طلبات التعبئة</p>
            </div>
        `;
        return;
    }

    try {
        const filterStatus = document.getElementById('recharge-filter')?.value || 'all';
        const url = `/api/wallet/get-recharge-requests.php?page=${rechargePage}&limit=${rechargeLimit}&status=${filterStatus}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${sessionToken}`
            },
            credentials: 'include'
        });

        const result = await response.json();

        if (response.ok && result.success && result.data && result.data.requests) {
            rechargeTotalPages = result.data.pagination.total_pages;
            renderRechargeRequests(result.data.requests);
            renderRechargePagination();
        } else {
            container.innerHTML = `
                <div class="transactions-empty">
                    <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                        <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="2" />
                        <path d="M40 25V45M40 55V55.1" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <h4>لا توجد طلبات تعبئة</h4>
                    <p>${result.error || 'لم تقم بأي طلبات تعبئة رصيد بعد'}</p>
                </div>
            `;
            // إخفاء التصفح عند الفراغ
            const pagContainer = document.getElementById('recharge-pagination');
            if (pagContainer) pagContainer.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading recharge requests:', error);
        container.innerHTML = `
            <div class="transactions-empty">
                <i class="bi bi-wifi-off" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>حدث خطأ في الاتصال. حاول مرة أخرى.</p>
            </div>
        `;
    }
}

/**
 * عرض طلبات تعبئة الرصيد في الجدول
 */
function renderRechargeRequests(requests) {
    const container = document.getElementById('recharge-requests-container');
    if (!container) return;

    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="transactions-empty">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                    <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="2" />
                    <path d="M40 25V45M40 55V55.1" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <h4>لا توجد طلبات تعبئة</h4>
                <p>لم تقم بأي طلبات تعبئة رصيد بعد</p>
            </div>
        `;
        return;
    }

    container.innerHTML = requests.map(request => {
        const statusClass = getStatusClass(request.status);
        const statusText = getStatusText(request.status);
        const statusIcon = getStatusIcon(request.status);
        const paymentMethodText = getPaymentMethodText(request.payment_method);
        const date = formatDate(request.created_at);

        return `
            <div class="transaction-item-premium">
                <div class="transaction-icon-premium" style="background: rgba(74, 144, 226, 0.1); color: var(--primary-blue);">
                    <i class="bi bi-wallet2" style="font-size: 1.5rem;"></i>
                </div>
                
                <div class="transaction-id-column" 
                     onclick="copyTransactionId('${request.id}')" 
                     title="اضغط لنسخ رقم المعاملة"
                     style="padding: 0 15px; cursor: pointer; border-left: 1px solid #eee; margin-left: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 80px;">
                    <span style="font-size: 0.75rem; color: #888; margin-bottom: 2px;">ID</span>
                    <span style="font-family: monospace; font-weight: bold; color: var(--primary-dark); font-size: 1.1rem; transition: color 0.2s;">#${request.id}</span>
                </div>

                <div class="transaction-details-premium">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <h4 class="transaction-title-premium" style="margin: 0;">
                            شحن رصيد: ${paymentMethodText}
                            <span class="status-badge ${statusClass}" style="margin-right: 8px; font-size: 0.75rem;">
                                ${statusText}
                            </span>
                        </h4>
                        <div class="transaction-amount-premium credit">
                            ${parseFloat(request.amount).toFixed(2)} ج.م
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 6px;">
                        <p class="transaction-date-premium">
                            <i class="bi bi-calendar3" style="font-size: 0.8rem;"></i>
                            ${date}
                        </p>
                        <div class="request-actions">
                            ${request.transaction_image ? `
                                <button class="btn-view-receipt" onclick="viewReceipt('${request.transaction_image}')" title="عرض الإيصال">
                                    <i class="bi bi-image"></i>
                                </button>
                            ` : ''}
                            ${request.admin_notes ? `
                                <button class="btn-view-notes" onclick="viewNotes(${request.id}, '${request.admin_notes.replace(/'/g, "\\'")}')" title="عرض الملاحظات">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * نسخ رقم المعاملة
 */
window.copyTransactionId = function (id) {
    const textToCopy = id.toString();

    // محاولة النسخ باستخدام Clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            showCopyFeedback(id);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            fallbackCopyText(textToCopy, id);
        });
    } else {
        fallbackCopyText(textToCopy, id);
    }
};

function fallbackCopyText(text, id) {
    const textArea = document.createElement("textarea");
    textArea.value = text;

    // جعل العنصر غير مرئي تقريبًا
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.opacity = '0';

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyFeedback(id);
        } else {
            alert('فشل النسخ تلقائياً. رقم المعاملة هو: ' + text);
        }
    } catch (err) {
        console.error('Fallback copy failed', err);
        alert('فشل النسخ تلقائياً. رقم المعاملة هو: ' + text);
    }

    document.body.removeChild(textArea);
}

function showCopyFeedback(id) {
    // إنشاء عنصر التلميح
    const toast = document.createElement('div');
    toast.className = 'copy-toast';
    toast.innerHTML = `
        <i class="bi bi-check-circle-fill"></i>
        <span>تم نسخ رقم المعاملة: #${id}</span>
    `;

    // تنسيق التلميح
    Object.assign(toast.style, {
        position: 'fixed',
        bottom: '20px',
        left: '50%',
        transform: 'translateX(-50%) translateY(100px)',
        background: '#333',
        color: 'white',
        padding: '12px 24px',
        borderRadius: '50px',
        boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        zIndex: '10000',
        transition: 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
        fontWeight: '500',
        opacity: '0'
    });

    document.body.appendChild(toast);

    // تفعيل الأنيميشن
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(-50%) translateY(0)';
        toast.style.opacity = '1';
    });

    // إخفاء وحذف بعد فترة
    setTimeout(() => {
        toast.style.transform = 'translateX(-50%) translateY(100px)';
        toast.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 2500);
}

// Render pagination for recharge requests
function renderRechargePagination() {
    const paginationContainer = document.getElementById('recharge-pagination');
    const paginationNumbers = document.getElementById('recharge-pagination-numbers');
    const prevBtn = document.getElementById('recharge-prev-page');
    const nextBtn = document.getElementById('recharge-next-page');

    if (!paginationContainer || !paginationNumbers || !prevBtn || !nextBtn) return;

    if (rechargeTotalPages <= 1) {
        paginationContainer.style.display = 'none';
        return;
    }

    paginationContainer.style.display = 'flex';

    // Update buttons state
    prevBtn.disabled = rechargePage === 1;
    nextBtn.disabled = rechargePage === rechargeTotalPages;

    // Remove existing event listeners (by cloning)
    const newPrevBtn = prevBtn.cloneNode(true);
    const newNextBtn = nextBtn.cloneNode(true);
    prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
    nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

    newPrevBtn.addEventListener('click', () => {
        if (rechargePage > 1) {
            rechargePage--;
            loadRechargeRequests();
        }
    });

    newNextBtn.addEventListener('click', () => {
        if (rechargePage < rechargeTotalPages) {
            rechargePage++;
            loadRechargeRequests();
        }
    });

    // Render page numbers
    paginationNumbers.innerHTML = '';

    // Logic to show limited page numbers (similar to transactions)
    let startPage = Math.max(1, rechargePage - 1);
    let endPage = Math.min(rechargeTotalPages, startPage + 2);

    if (endPage - startPage < 2 && rechargeTotalPages > 2) {
        startPage = Math.max(1, endPage - 2);
    }

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-number ${i === rechargePage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => {
            rechargePage = i;
            loadRechargeRequests();
        });
        paginationNumbers.appendChild(pageBtn);
    }
}

// Filter recharge requests
async function filterRechargeRequests(status) {
    rechargePage = 1;
    loadRechargeRequests();
}

// Initialize pagination logic
function initRechargePagination() {
    const filterSelect = document.getElementById('recharge-filter');

    if (filterSelect) {
        // Remove existing listener to avoid duplicates if called multiple times
        const newFilter = filterSelect.cloneNode(true);
        filterSelect.parentNode.replaceChild(newFilter, filterSelect);

        newFilter.addEventListener('change', (e) => {
            filterRechargeRequests(e.target.value);
        });

        // Set initial value
        newFilter.value = 'all';
    }

    loadRechargeRequests();
}

/**
 * الحصول على كلاس الحالة
 */
function getStatusClass(status) {
    const statusClasses = {
        'pending': 'status-pending',
        'approved': 'status-approved',
        'rejected': 'status-rejected',
        'cancelled': 'status-cancelled'
    };
    return statusClasses[status] || 'status-pending';
}

/**
 * الحصول على نص الحالة
 */
function getStatusText(status) {
    const statusTexts = {
        'pending': 'قيد المراجعة',
        'approved': 'تم الموافقة',
        'rejected': 'مرفوض',
        'cancelled': 'ملغي'
    };
    return statusTexts[status] || 'غير معروف';
}

/**
 * الحصول على أيقونة الحالة
 */
function getStatusIcon(status) {
    const statusIcons = {
        'pending': '<i class="bi bi-clock"></i>',
        'approved': '<i class="bi bi-check-circle"></i>',
        'rejected': '<i class="bi bi-x-circle"></i>',
        'cancelled': '<i class="bi bi-x-octagon"></i>'
    };
    return statusIcons[status] || '<i class="bi bi-question-circle"></i>';
}

/**
 * الحصول على نص طريقة الدفع
 */
function getPaymentMethodText(method) {
    const methods = {
        'instapay': 'InstaPay',
        'vodafone_cash': 'Vodafone Cash',
        'orange_money': 'Orange Money',
        'etisalat_cash': 'Etisalat Cash',
        'we_pay': 'We Pay'
    };
    return methods[method] || method;
}

/**
 * تنسيق التاريخ
 */
function formatDate(dateString) {
    if (!dateString) return 'غير معروف';

    const date = new Date(dateString);
    return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * عرض الإيصال
 */
/**
 * عرض الإيصال
 */
function viewReceipt(imageUrl) {
    if (!imageUrl) {
        alert('لا توجد صورة إيصال');
        return;
    }

    // Force scroll to top instantly
    try {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    } catch (e) {
        console.error('Error scrolling to top:', e);
    }

    // Lock scroll on both body and html to contain user
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    // إنشاء نافذة منبثقة لعرض الصورة
    const modal = document.createElement('div');
    modal.className = 'receipt-modal';
    modal.innerHTML = `
        <div class="receipt-modal-overlay"></div>
        <div class="receipt-modal-content enhanced-modal">
            <div class="receipt-modal-header">
                <h3 class="receipt-title">
                    <i class="bi bi-receipt"></i>
                    إيصال التحويل
                </h3>
                <div class="receipt-actions">
                    <a href="${imageUrl}" download="receipt.jpg" class="receipt-btn download-btn" target="_blank">
                        <i class="bi bi-download"></i>
                        <span>تحميل</span>
                    </a>
                    <button class="receipt-btn close-btn receipt-modal-close">
                        <i class="bi bi-x-lg"></i>
                        <span>إغلاق</span>
                    </button>
                </div>
            </div>
            <div class="receipt-image-container">
                <img src="${imageUrl}" alt="إيصال التحويل" class="receipt-image">
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Ensure modal itself is scrolled to top (if content is long)
    modal.scrollTop = 0;

    // إغلاق النافذة
    const closeBtn = modal.querySelector('.receipt-modal-close');
    const overlay = modal.querySelector('.receipt-modal-overlay');

    const closeModal = () => {
        document.body.removeChild(modal);
        // Restore scroll
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    };

    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', closeModal);
}

/**
 * عرض الملاحظات
 */
function viewNotes(requestId, notes) {
    alert(`ملاحظات الإدارة على الطلب #${requestId}:\n\n${notes}`);
}

/**
 * تنسيق آخر تسجيل دخول بشكل دقيق
 */
function formatLastLogin(dateString) {
    if (!dateString) return 'غير معروف';

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    // إذا كان أقل من دقيقة
    if (diffMins < 1) {
        return 'الآن';
    }
    // إذا كان أقل من ساعة
    else if (diffMins < 60) {
        return `منذ ${diffMins} دقيقة`;
    }
    // إذا كان أقل من 24 ساعة
    else if (diffHours < 24) {
        return `منذ ${diffHours} ساعة`;
    }
    // إذا كان أقل من 7 أيام
    else if (diffDays < 7) {
        return `منذ ${diffDays} يوم`;
    }
    // إذا كان أقل من شهر
    else if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        return `منذ ${weeks} أسبوع`;
    }
    // إذا كان أقل من سنة
    else if (diffDays < 365) {
        const months = Math.floor(diffDays / 30);
        return `منذ ${months} شهر`;
    }
    // أكثر من سنة - عرض التاريخ الكامل
    else {
        return date.toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

/**
 * التعامل مع حذف الحساب
 */
function initDeleteAccount() {
    const deleteBtn = document.getElementById('delete-account-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function () {
            const confirmed = confirm('هل أنت متأكد تماماً بأنك تريد حذف حسابك؟ هذا الإجراء لا يمكن التراجع عنه.');

            if (confirmed) {
                const secondConfirm = confirm('تحذير أخير: سيتم حذف حسابك نهائياً ولن تتمكن من الوصول إلى كورساتك أو محفظتك. هل أنت موافق؟');

                if (secondConfirm) {
                    try {
                        // إظهار حالة التحميل
                        const oldText = this.textContent;
                        this.textContent = 'جاري الحذف...';
                        this.disabled = true;

                        const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

                        const response = await fetch('api/auth/delete-account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': `Bearer ${sessionToken}`
                            }
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            alert('تم حذف الحساب بنجاح.');
                            // تسجيل الخروج وتنظيف البيانات
                            localStorage.clear();
                            sessionStorage.clear();
                            document.cookie.split(";").forEach(function (c) {
                                document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                            });
                            window.location.href = 'index.html';
                        } else {
                            alert(result.error || 'حدث خطأ أثناء حذف الحساب');
                            this.textContent = oldText;
                            this.disabled = false;
                        }
                    } catch (error) {
                        console.error('Error deleting account:', error);
                        alert('حدث خطأ في الاتصال. حاول مرة أخرى.');
                        this.textContent = 'حذف حسابي';
                        this.disabled = false;
                    }
                }
            }
        });
    }
}


/**
 * تهيئة رفع صورة الملف الشخصي
 */
function initProfilePictureUpload() {
    const uploadBtn = document.querySelector('.btn-set-profile-pic');
    const fileInput = document.getElementById('avatar-upload');
    const userAvatarContainer = document.querySelector('.user-avatar');

    // Load saved image
    const savedImage = localStorage.getItem('userProfileImage');
    if (savedImage && userAvatarContainer) {
        userAvatarContainer.innerHTML = `<img src="${savedImage}" alt="Profile Picture">`;
    }
    // If no image, check for saved icon or default
    else {
        const savedIcon = localStorage.getItem('userAvatarIcon') || 'bi-person-circle';
        // Note: The HTML defaults to icon, so we might not need to do anything if it's already there,
        // unless we need to restore the icon state
    }

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fileInput.click();
        });

        fileInput.addEventListener('change', function (e) {
            if (this.files && this.files[0]) {
                handleImageUpload(this.files[0]);
            }
        });
    }
}

/**
 * معالجة رفع الصورة وضغطها
 */
function handleImageUpload(file) {
    if (!file.type.match('image.*')) {
        alert('الرجاء اختيار ملف صورة صالح');
        return;
    }

    // إظهار مؤشر تحميل بسيط
    const uploadBtn = document.querySelector('.btn-set-profile-pic span');
    const originalText = uploadBtn ? uploadBtn.textContent : 'تعيين الصورة';
    if (uploadBtn) uploadBtn.textContent = 'جاري المعالجة...';

    const reader = new FileReader();

    reader.onload = function (e) {
        const img = new Image();
        img.src = e.target.result;

        img.onload = function () {
            // إنشاء قماش للرسم (Canvas)
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            // تحديد الأبعاد الجديدة (تصغير للحفاظ على المساحة)
            // الحجم الأقصى 300x300 يكفي جداً للعرض 150x150 بجودة عالية
            const maxWidth = 300;
            const maxHeight = 300;
            let width = img.width;
            let height = img.height;

            // حساب الأبعاد مع الحفاظ على النسبة
            if (width > height) {
                if (width > maxWidth) {
                    height *= maxWidth / width;
                    width = maxWidth;
                }
            } else {
                if (height > maxHeight) {
                    width *= maxHeight / height;
                    height = maxHeight;
                }
            }

            canvas.width = width;
            canvas.height = height;

            // رسم الصورة المضغوطة والأصغر حجماً
            ctx.drawImage(img, 0, 0, width, height);

            // تحويل القماش إلى Blob بدلاً من Data URL
            canvas.toBlob(async function (blob) {
                if (!blob) {
                    alert('حدث خطأ أثناء معالجة الصورة');
                    if (uploadBtn) uploadBtn.textContent = originalText;
                    return;
                }

                // إعداد FormData للإرسال
                const formData = new FormData();
                formData.append('avatar', blob, 'avatar.jpg');

                const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

                if (!sessionToken) {
                    alert('يجب تسجيل الدخول لرفع الصورة');
                    if (uploadBtn) uploadBtn.textContent = originalText;
                    return;
                }

                try {
                    const response = await fetch(getProfileApiUrl('api/auth/upload-avatar.php'), {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${sessionToken}`
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        const newImageUrl = result.data.image_url;

                        // تحديث الواجهة
                        const userAvatarContainer = document.querySelector('.user-avatar');
                        if (userAvatarContainer) {
                            userAvatarContainer.innerHTML = `<img src="${newImageUrl}?t=${new Date().getTime()}" alt="Profile Picture">`;
                        }

                        // تحديث بيانات المستخدم في localStorage
                        const user = getCurrentUser();
                        if (user) {
                            user.profile_image = newImageUrl;
                            localStorage.setItem('user_data', JSON.stringify(user));
                        }

                        // تنظيف القديم (اختياري)
                        localStorage.removeItem('userProfileImage');

                        // alert('تم تحديث الصورة الشخصية بنجاح');
                    } else {
                        throw new Error(result.error || 'فشل رفع الصورة');
                    }
                } catch (error) {
                    console.error('Upload failed:', error);
                    alert('حدث خطأ أثناء رفع الصورة: ' + error.message);
                } finally {
                    if (uploadBtn) uploadBtn.textContent = originalText;
                }
            }, 'image/jpeg', 0.8); // جودة 80%
        };
    };

    reader.readAsDataURL(file);
}

/**
 * نسخ معرف المستخدم
 */
function copyUserId() {
    const userIdElement = document.getElementById('user-id-value');
    if (!userIdElement) return;

    const userId = userIdElement.textContent;
    if (userId === '--' || !userId) return;

    navigator.clipboard.writeText(userId).then(() => {
        // Visual feedback
        const badge = document.querySelector('.user-id-badge');
        const originalIcon = badge.querySelector('.copy-icon').className;
        const icon = badge.querySelector('.copy-icon');

        // Change icon to check
        icon.className = 'bi bi-check-lg copy-icon';
        badge.style.borderColor = '#4CAF50';
        badge.style.color = '#4CAF50';

        setTimeout(() => {
            icon.className = originalIcon;
            badge.style.borderColor = '';
            badge.style.color = '';
        }, 2000);

        // Optional: Show toast
        // alert('تم نسخ الرقم التعريفي: ' + userId);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
    });
}
