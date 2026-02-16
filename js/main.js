// Main application functionality - Performance Optimized
document.addEventListener('DOMContentLoaded', function () {
    // Check login status
    checkLoginStatus();

    // Add smooth scroll behavior (فقط للروابط الداخلية مثل #features، وليس للروابط التي تُحدَّث لاحقاً مثل course-detail.html?id=17)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            // معالجة التمرير فقط إذا كان href مرجعاً داخلياً صالحاً (مثل #section-id)
            const isValidFragment = href && href.length > 1 && /^#[a-zA-Z0-9_-]+$/.test(href);
            if (isValidFragment) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Video player functionality
    const videoOverlay = document.querySelector('.video-overlay');
    if (videoOverlay) {
        videoOverlay.addEventListener('click', function () {
            alert('Video player would start here. In production, this would integrate with a video streaming service.');
        });
    }

    // Lesson item click handlers
    const lessonItems = document.querySelectorAll('.lesson-item');
    lessonItems.forEach(item => {
        item.addEventListener('click', function () {
            // Remove active class from all items
            lessonItems.forEach(i => i.classList.remove('active'));

            // Add active class to clicked item
            this.classList.add('active');

            // In production, this would load the corresponding video
            console.log('Loading lesson:', this.querySelector('h3').textContent);
        });
    });
});

// Check if user is logged in
function checkLoginStatus() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') || sessionStorage.getItem('isLoggedIn');
    const isGuest = sessionStorage.getItem('isGuest');

    // Update UI based on login status
    if (isLoggedIn) {
        const userEmail = localStorage.getItem('userEmail') || sessionStorage.getItem('userEmail');
        console.log('User logged in:', isGuest ? 'Guest' : userEmail);
    }
}

// Fallback محلي فقط عند عدم تحميل auth-check.js (لا نسميها logout حتى لا نستبدل window.logout)
function doLocalLogoutFallback() {
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('sessionToken');
    localStorage.removeItem('sessionExpiresAt');
    localStorage.removeItem('userData');
    sessionStorage.removeItem('isLoggedIn');
    sessionStorage.removeItem('userEmail');
    sessionStorage.removeItem('sessionToken');
    sessionStorage.removeItem('sessionExpiresAt');
    sessionStorage.removeItem('userData');
    sessionStorage.removeItem('isGuest');
    document.cookie.split(';').forEach(function (c) {
        document.cookie = c.replace(/^\s+/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
    });
    window.location.href = 'home.html';
}

// ربط زر تسجيل الخروج: استدعاء window.logout من auth-check.js فقط (لا نستبدلها)
function attachLogoutListeners() {
    const selectors = '.logout-item, a[href*="logout"], button[data-action="logout"], a[href*="Log Out"]';
    document.querySelectorAll(selectors).forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            if (typeof window.logout === 'function') {
                window.logout();
            } else {
                doLocalLogoutFallback();
            }
        });
    });
}

// تنفيذ الربط عند تحميل الصفحة (لضمان وجود العناصر)
document.addEventListener('DOMContentLoaded', attachLogoutListeners);
// تنفيذ فوري أيضاً للصفحات التي قد تكون DOM جاهزة مسبقاً
if (document.readyState === 'loading') {
    // سيعالجه DOMContentLoaded
} else {
    attachLogoutListeners();
}

// Smooth scroll to top functionality
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
