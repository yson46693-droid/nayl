/**
 * قائمة الكورسات - جلب وعرض الكورسات من API
 */
(function () {
    const grid = document.getElementById('courses-grid');
    const loadingEl = document.getElementById('courses-loading');
    const emptyEl = document.getElementById('courses-empty');

    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '—';
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        if (hours > 0 && mins > 0) return hours + ' ساعة و ' + mins + ' د';
        if (hours > 0) return hours + ' ساعة';
        return mins + ' دقيقة';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderCourseCard(course, index) {
        const coverUrl = course.cover_image_url || 'pics/1.jpg';
        const shortDesc = (course.description || '').substring(0, 80);
        const badge = index === 0 ? '<div class="course-badge">الأكثر مبيعاً</div>' : (index === 1 ? '<div class="course-badge new">جديد</div>' : '');
        const duration = formatDuration(course.total_duration_seconds);
        const lessons = course.videos_count ? course.videos_count + ' درس' : '—';

        const card = document.createElement('div');
        card.className = 'course-card';
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        card.innerHTML =
            badge +
            '<div class="course-image">' +
            '<img src="' + escapeHtml(coverUrl) + '" alt="' + escapeHtml(course.title) + '" loading="lazy">' +
            '<div class="course-overlay">' +
            '<h3>' + escapeHtml(course.title) + '</h3>' +
            '<p>' + escapeHtml(shortDesc) + (shortDesc.length >= 80 ? '...' : '') + '</p>' +
            '</div></div>' +
            '<div class="course-info">' +
            '<div class="course-meta">' +
            '<span class="course-duration">' +
            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 14C11.3137 14 14 11.3137 14 8C14 4.68629 11.3137 2 8 2C4.68629 2 2 4.68629 2 8C2 11.3137 4.68629 14 8 14Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 4V8L10.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> ' + escapeHtml(duration) +
            '</span>' +
            '<span class="course-lessons">' +
            '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 7L9.5 8.5L6.5 10V7Z" fill="currentColor"/></svg> ' + escapeHtml(lessons) +
            '</span></div></div>';

        card.addEventListener('click', function () {
            if (course.has_subscription) {
                window.location.href = 'course-detail.html?id=' + course.id;
            } else {
                window.location.href = 'purchase-course.html?id=' + course.id;
            }
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (course.has_subscription) {
                    window.location.href = 'course-detail.html?id=' + course.id;
                } else {
                    window.location.href = 'purchase-course.html?id=' + course.id;
                }
            }
        });

        return card;
    }

    async function loadCourses() {
        if (!grid || !loadingEl || !emptyEl) return;
        try {
            const response = await fetch('api/courses/list.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            loadingEl.style.display = 'none';
            var result;
            try {
                result = await response.json();
            } catch (parseErr) {
                console.error('Courses list: invalid JSON. Status:', response.status, 'Text:', await response.text());
                emptyEl.style.display = 'block';
                emptyEl.textContent = 'حدث خطأ أثناء تحميل الكورسات. تحقق من الاتصال أو أن الخادم يعمل بشكل صحيح.';
                return;
            }
            if (!response.ok || !result.success || !result.data || !result.data.courses) {
                emptyEl.style.display = 'block';
                emptyEl.textContent = (result && result.message) ? result.message : 'فشل تحميل الكورسات.';
                return;
            }
            const courses = result.data.courses;
            if (courses.length === 0) {
                emptyEl.style.display = 'block';
                return;
            }
            emptyEl.style.display = 'none';
            courses.forEach(function (course, index) {
                grid.appendChild(renderCourseCard(course, index));
            });
        } catch (err) {
            console.error('Error loading courses:', err);
            if (loadingEl) loadingEl.style.display = 'none';
            if (emptyEl) {
                emptyEl.style.display = 'block';
                emptyEl.textContent = 'حدث خطأ أثناء تحميل الكورسات. تحقق من الاتصال بالإنترنت أو أن الخادم يعمل.';
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadCourses);
    } else {
        loadCourses();
    }
})();
