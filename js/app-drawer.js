/**
 * قائمة التطبيق الجانبية - للموبايل فقط
 * فتح/إغلاق القائمة، تعبئة بطاقة المستخدم، تسجيل الخروج
 */
(function () {
    'use strict';

    /** نقل الستار والقائمة إلى نهاية body لضمان تغطية كامل الشاشة حتى بعد التمرير (تجنب أي containing block من عنصر أب) */
    function ensureDrawerInBody() {
        var overlay = document.querySelector('.app-drawer-overlay');
        var drawer = document.querySelector('.app-drawer');
        if (overlay && overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }
        if (drawer && drawer.parentNode !== document.body) {
            document.body.appendChild(drawer);
        }
    }

    function getDrawerElements() {
        return {
            toggle: document.querySelector('.app-drawer-toggle'),
            overlay: document.querySelector('.app-drawer-overlay'),
            drawer: document.querySelector('.app-drawer')
        };
    }

    function openDrawer() {
        var el = getDrawerElements();
        if (el.drawer) el.drawer.classList.add('open');
        if (el.overlay) el.overlay.classList.add('active');
        document.body.classList.add('app-drawer-open');
    }

    function closeDrawer() {
        var el = getDrawerElements();
        if (el.drawer) el.drawer.classList.remove('open');
        if (el.overlay) el.overlay.classList.remove('active');
        document.body.classList.remove('app-drawer-open');
    }

    function isDrawerOpen() {
        var drawer = document.querySelector('.app-drawer');
        return drawer && drawer.classList.contains('open');
    }

    function updateActiveTab() {
        var currentPath = window.location.pathname || '';
        var drawer = document.querySelector('.app-drawer');
        if (!drawer) return;
        var links = drawer.querySelectorAll('.app-drawer-link[href]');
        var onProfile = currentPath.indexOf('profile') !== -1;
        var onRecharge = currentPath.indexOf('recharge') !== -1;
        var onMyCourses = currentPath.indexOf('my-courses') !== -1;
        var onCourses = currentPath.indexOf('courses') !== -1 && currentPath.indexOf('my-courses') === -1;
        links.forEach(function (link) {
            var href = (link.getAttribute('href') || '').split('?')[0];
            var match = (onProfile && href.indexOf('profile') !== -1) ||
                (onRecharge && href.indexOf('recharge') !== -1) ||
                (onMyCourses && href.indexOf('my-courses') !== -1) ||
                (onCourses && href.indexOf('courses') !== -1 && href.indexOf('my-courses') === -1);
            if (match) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    function fillUserCard() {
        var nameEl = document.querySelector('.app-drawer-user-name');
        var idEl = document.querySelector('.app-drawer-user-id-value');
        if (typeof getCurrentUser !== 'function') return;
        var user = getCurrentUser();
        if (!user) return;
        if (nameEl) {
            nameEl.textContent = user.full_name || user.name || (user.email ? user.email.split('@')[0] : 'مستخدم');
        }
        if (idEl) {
            var id = user.id || user.user_id || '';
            idEl.textContent = id || '—';
        }
    }

    function handleLogoutClick(e) {
        e.preventDefault();
        closeDrawer();
        if (typeof logout === 'function') {
            logout().then(function () {
                window.location.href = 'index.html';
            }).catch(function () {
                window.location.href = 'index.html';
            });
        } else {
            window.location.href = 'index.html';
        }
    }

    function init() {
        ensureDrawerInBody();
        var el = getDrawerElements();
        if (!el.toggle || !el.drawer) return;

        el.toggle.addEventListener('click', function () {
            if (isDrawerOpen()) {
                closeDrawer();
            } else {
                openDrawer();
                fillUserCard();
                updateActiveTab();
            }
        });

        if (el.overlay) {
            el.overlay.addEventListener('click', closeDrawer);
        }

        var logoutBtn = document.querySelector('.app-drawer-logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogoutClick);
        }

        var drawerLinks = el.drawer.querySelectorAll('.app-drawer-link[href]');
        drawerLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                closeDrawer();
            });
        });

        fillUserCard();
        updateActiveTab();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
