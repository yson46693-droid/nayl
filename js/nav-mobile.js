/**
 * قائمة التنقل للجوال - فتح/إغلاق الشريط الجانبي على الهواتف والشاشات الصغيرة فقط
 */
(function () {
    const btn = document.querySelector('.nav-menu-btn');
    const overlay = document.getElementById('navDrawerOverlay');
    const drawer = document.getElementById('navDrawer');

    if (!btn || !overlay || !drawer) return;

    function openDrawer() {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function toggleDrawer() {
        if (drawer.classList.contains('is-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleDrawer();
    });

    overlay.addEventListener('click', closeDrawer);

    drawer.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            closeDrawer();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    /**
     * تعبئة بطاقة الملف الشخصي في أسفل الشريط الجانبي (اسم المستخدم، المعرف)
     */
    function fillDrawerProfileCard() {
        const nameEl = document.getElementById('navDrawerUserName');
        const idEl = document.getElementById('navDrawerUserId');
        if (!nameEl && !idEl) return;

        const user = typeof getCurrentUser === 'function' ? getCurrentUser() : null;
        if (user) {
            if (nameEl) {
                nameEl.textContent = user.full_name || user.name || (user.email ? user.email.split('@')[0] : '') || 'مستخدم';
            }
            if (idEl) {
                const userId = user.id || user.user_id || '';
                idEl.textContent = 'ID: ' + (userId || '--');
            }
        } else {
            if (nameEl) nameEl.textContent = 'زائر';
            if (idEl) idEl.textContent = 'ID: --';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fillDrawerProfileCard);
    } else {
        fillDrawerProfileCard();
    }
})();
