// My Courses Page JavaScript

/**
 * بناء رابط API ليعمل من الجذر أو من مجلد فرعي (مثل yoursite.com/nayl/)
 * يمنع 404 عند فتح صفحة كورساتي من مسار فرعي
 */
function getMyCoursesApiUrl(relativePath) {
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

document.addEventListener('DOMContentLoaded', async function () {
    const grid = document.querySelector('.my-courses-grid');
    const filtersWrapper = document.querySelector('.courses-filters');
    const quickStats = document.querySelector('.quick-stats');

    if (!grid) return;

    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');
    if (!sessionToken) {
        showEmptyState(grid, filtersWrapper, true);
        return;
    }

    try {
        const apiUrl = getMyCoursesApiUrl('api/courses/get-my-course-codes.php');
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + sessionToken
            },
            credentials: 'include'
        });

        const contentType = response.headers.get('Content-Type') || '';
        const isJson = contentType.indexOf('application/json') !== -1;
        if (!response.ok || !isJson) {
            if (!response.ok && !isJson) {
                console.warn('My courses API returned non-JSON (e.g. 404 page). URL:', apiUrl, 'Status:', response.status);
            }
            showEmptyState(grid, filtersWrapper, false);
            return;
        }

        const result = await response.json();
        const codes = (result.success && result.data && result.data.codes) ? result.data.codes : [];

        if (codes.length === 0) {
            showEmptyState(grid, filtersWrapper);
            return;
        }

        renderCourses(grid, codes);
        updateStats(quickStats, codes.length);
        initFilters();
        initCardAnimations();
    } catch (err) {
        console.error('Error loading my courses:', err);
        showEmptyState(grid, filtersWrapper, false);
    }
});

/**
 * عرض حالة عدم وجود كورسات مع زر كبير للتوجه لصفحة الكورسات
 */
function showEmptyState(grid, filtersWrapper, isUnauth) {
    if (filtersWrapper) filtersWrapper.style.display = 'none';

    const message = isUnauth
        ? 'يجب تسجيل الدخول لعرض كورساتك'
        : 'لا يوجد لديك كورسات حتى الآن';
    const subMessage = isUnauth
        ? 'سجّل الدخول ثم اشترِ كورسات من صفحة الكورسات'
        : 'اشترِ كورسات من صفحة الكورسات وستظهر هنا';

    grid.innerHTML = `
        <div class="my-courses-empty">
            <div class="my-courses-empty-icon">
                <i class="bi bi-collection-play"></i>
            </div>
            <h2 class="my-courses-empty-title">${escapeHtml(message)}</h2>
            <p class="my-courses-empty-text">${escapeHtml(subMessage)}</p>
            ${!isUnauth ? `
            <a href="courses.html" class="my-courses-empty-btn">
                <i class="bi bi-grid-fill"></i>
                <span>تصفح وشراء الكورسات</span>
            </a>
            ` : `
            <a href="index.html" class="my-courses-empty-btn">تسجيل الدخول</a>
            `}
        </div>
    `;
}

/**
 * تنظيف النص من HTML للحماية من XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * رسم بطاقات الكورسات في الشبكة
 */
function renderCourses(grid, codes) {
    grid.innerHTML = codes.map(function (item) {
        const title = escapeHtml(item.course_title || 'كورس');
        const imgUrl = item.cover_image_url ? escapeHtml(item.cover_image_url) : 'pics/logo.png';
        const detailUrl = 'course-detail.html?id=' + encodeURIComponent(item.course_id);
        return `
            <a href="${detailUrl}" class="my-course-card" data-status="in-progress">
                <div class="course-thumbnail">
                    <img src="${imgUrl}" alt="${title}" loading="lazy">
                    <span class="course-status-badge in-progress">جاري</span>
                </div>
                <div class="my-course-content">
                    <h3 class="my-course-title">${title}</h3>
                </div>
            </a>
        `;
    }).join('');
}

/**
 * تحديث الإحصائيات السريعة بعدد الكورسات
 */
function updateStats(quickStats, totalCount) {
    if (!quickStats) return;
    const statValues = quickStats.querySelectorAll('.stat-value');
    if (statValues.length >= 2) {
        statValues[1].textContent = totalCount;
    }
}

/**
 * تفعيل فلاتر الكورسات (الكل / جارية / مكتملة)
 */
function initFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const courseCards = document.querySelectorAll('.my-course-card');

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            filterButtons.forEach(function (btn) { btn.classList.remove('active'); });
            this.classList.add('active');
            const filter = this.getAttribute('data-filter');

            courseCards.forEach(function (card) {
                const status = card.getAttribute('data-status');
                if (filter === 'all' || status === filter) {
                    card.style.display = 'block';
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(function () {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        });
    });
}

/**
 * حركة ظهور البطاقات عند التحميل
 */
function initCardAnimations() {
    const courseCards = document.querySelectorAll('.my-course-card');
    courseCards.forEach(function (card, index) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(function () {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}
