/**
 * قائمة التطبيق الجانبية - للموبايل فقط
 * فتح/إغلاق القائمة، تعبئة بطاقة المستخدم، تسجيل الخروج
 */
(function () {
    'use strict';

    /**
     * نقل الستار والقائمة إلى html (وليس body) لأن body يستخدم animation + will-change: transform
     * فيصبح containing block للـ fixed فيرتبط الشريط به بدل الـ viewport. بجعلهما أبناءً لـ html يُحل المشكلة.
     */
    function ensureDrawerInViewport() {
        var overlay = document.querySelector('.app-drawer-overlay');
        var drawer = document.querySelector('.app-drawer');
        var root = document.documentElement;
        if (overlay && overlay.parentNode !== root) {
            root.appendChild(overlay);
        }
        if (drawer && drawer.parentNode !== root) {
            root.appendChild(drawer);
        }
    }

    function getDrawerElements() {
        return {
            toggle: document.querySelector('.app-drawer-toggle'),
            overlay: document.querySelector('.app-drawer-overlay'),
            drawer: document.querySelector('.app-drawer')
        };
    }

    /**
     * حساب ارتفاع كامل يغطي كل المنطقة المرئية (حل نهائي لمشكلة القص على الموبايل).
     * نأخذ أكبر قيمة من كل المصادر ثم نطبّقها بـ !important حتى لا يُلغى من أي CSS آخر.
     */
    function getFullViewportHeight() {
        var vv = window.visualViewport;
        var inner = window.innerHeight || 0;
        var client = document.documentElement.clientHeight || 0;
        var vvH = (vv && vv.height) || 0;
        var screenH = (typeof screen !== 'undefined' && screen.availHeight) || 0;
        var h = Math.max(vvH, inner, client, screenH, 568);
        return Math.min(h, 2000);
    }

    function getViewportTop() {
        var vv = window.visualViewport;
        return (vv && vv.offsetTop) || 0;
    }

    function setDrawerFullHeight() {
        var overlay = document.querySelector('.app-drawer-overlay');
        var drawer = document.querySelector('.app-drawer');
        var h = getFullViewportHeight();
        var topOffset = getViewportTop();
        var px = 'px';
        if (overlay) {
            overlay.style.setProperty('height', h + px, 'important');
            overlay.style.setProperty('top', topOffset + px, 'important');
            overlay.style.setProperty('left', '0', 'important');
            overlay.style.setProperty('right', '0', 'important');
            overlay.style.setProperty('bottom', 'auto', 'important');
        }
        if (drawer) {
            drawer.style.setProperty('height', h + px, 'important');
            drawer.style.setProperty('top', topOffset + px, 'important');
            drawer.style.setProperty('bottom', 'auto', 'important');
        }
    }

    function openDrawer() {
        var el = getDrawerElements();
        setDrawerFullHeight();
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
        ensureDrawerInViewport();
        var el = getDrawerElements();
        if (!el.toggle || !el.drawer) return;

        setDrawerFullHeight();
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', setDrawerFullHeight);
            window.visualViewport.addEventListener('scroll', setDrawerFullHeight);
        }
        window.addEventListener('resize', setDrawerFullHeight);

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

        var drawerLinks = el.drawer.querySelectorAll('.app-drawer-link[href], a.app-drawer-user-card');
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
