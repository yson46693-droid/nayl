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
})();
