/**
 * صفحة تفاصيل الكورس - طلب كود التفعيل ثم عرض المحتوى
 */
(function () {
    'use strict';
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('id') ? parseInt(urlParams.get('id'), 10) : null;

    const STORAGE_KEY_PREFIX = 'course_unlocked_';
    const sessionToken = localStorage.getItem('sessionToken') || sessionStorage.getItem('sessionToken');

    const codeModal = document.getElementById('code-verify-modal');
    const codeInput = document.getElementById('code-verify-input');
    const codeSubmitBtn = document.getElementById('code-verify-submit');
    const codeErrorEl = document.getElementById('code-verify-error');
    const courseContentEl = document.getElementById('course-content-wrapper');
    const courseTitleEl = document.querySelector('.course-detail-header .course-title');
    const courseSubtitleEl = document.querySelector('.course-detail-header .course-subtitle');
    const videoSectionEl = document.querySelector('.video-section');
    const lessonsListEl = document.querySelector('.lessons-list');
    const backButton = document.querySelector('.course-detail-section .back-button');

    function getUnlockKey() {
        return courseId ? STORAGE_KEY_PREFIX + courseId : null;
    }

    function isUnlocked() {
        const key = getUnlockKey();
        return key ? sessionStorage.getItem(key) === '1' : false;
    }

    function setUnlocked() {
        const key = getUnlockKey();
        if (key) sessionStorage.setItem(key, '1');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '—';
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        if (hours > 0 && mins > 0) return hours + ' ساعة و ' + mins + ' د';
        if (hours > 0) return hours + ' ساعة';
        return mins + ' دقيقة';
    }

    function showCodeModal() {
        if (codeModal) codeModal.style.display = 'flex';
        if (courseContentEl) courseContentEl.style.display = 'none';
        if (codeErrorEl) codeErrorEl.textContent = '';
        if (codeInput) {
            codeInput.value = '';
            codeInput.focus();
        }
        document.body.style.overflow = 'hidden';
    }

    function hideCodeModal() {
        if (codeModal) codeModal.style.display = 'none';
        if (courseContentEl) courseContentEl.style.display = 'block';
        document.body.style.overflow = '';
    }

    function showError(msg) {
        if (codeErrorEl) {
            codeErrorEl.textContent = msg || '';
            codeErrorEl.style.display = msg ? 'block' : 'none';
        }
    }

    /** الحصول على بصمة الجهاز (مطلوبة لربط الكورس بجهاز واحد فقط) */
    function getFingerprint() {
        return (typeof window.getDeviceFingerprint === 'function' && window.getDeviceFingerprint()) || null;
    }

    async function verifyCode() {
        const code = codeInput ? codeInput.value.trim() : '';
        if (!code) {
            showError('أدخل كود المشاهدة');
            return;
        }
        if (!courseId || courseId <= 0) {
            showError('معرف الكورس غير صحيح');
            return;
        }

        const fingerprintHash = getFingerprint();
        if (!fingerprintHash) {
            showError('لم يتم التعرف على الجهاز. حدّث الصفحة وحاول مرة أخرى، أو تأكد من تفعيل JavaScript.');
            return;
        }

        if (codeSubmitBtn) {
            codeSubmitBtn.disabled = true;
            codeSubmitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري التحقق...';
        }
        showError('');

        try {
            const response = await fetch('api/courses/verify-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (sessionToken || '')
                },
                credentials: 'include',
                body: JSON.stringify({
                    course_id: courseId,
                    code: code,
                    fingerprint_hash: fingerprintHash
                })
            });
            const result = await response.json();

            if (response.ok && result.success) {
                setUnlocked();
                hideCodeModal();
                loadCourseContent();
            } else {
                showError(result.error || result.message || 'كود المشاهدة غير صحيح أو غير مرتبط بحسابك لهذا الكورس');
            }
        } catch (err) {
            console.error('Verify code error:', err);
            showError('حدث خطأ في الاتصال. حاول مرة أخرى.');
        }

        if (codeSubmitBtn) {
            codeSubmitBtn.disabled = false;
            codeSubmitBtn.innerHTML = '<i class="bi bi-check-lg"></i> تأكيد والمشاهدة';
        }
    }

    async function loadCourseContent() {
        if (!courseId || courseId <= 0) return;

        if (courseContentEl) courseContentEl.style.display = 'block';
        const loadingEl = document.getElementById('course-detail-loading');
        if (loadingEl) loadingEl.style.display = 'block';

        var fp = getFingerprint();
        var headers = {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + (sessionToken || '')
        };
        if (fp) {
            headers['X-Device-Fingerprint'] = fp;
        }

        try {
            const response = await fetch('/api/courses/get-detail.php?id=' + courseId, {
                method: 'GET',
                headers: headers,
                credentials: 'include'
            });
            const result = await response.json();

            if (loadingEl) loadingEl.style.display = 'none';

            if (!response.ok || !result.success || !result.data || !result.data.course) {
                if (response.status === 403) {
                    const key = getUnlockKey();
                    if (key) sessionStorage.removeItem(key);
                    showCodeModal();
                    if (result.message && codeErrorEl) {
                        codeErrorEl.textContent = result.message;
                        codeErrorEl.style.display = 'block';
                    }
                } else {
                    if (courseTitleEl) courseTitleEl.textContent = 'حدث خطأ في تحميل الكورس';
                }
                return;
            }

            const course = result.data.course;
            window.__courseDetailData = course;
            if (courseTitleEl) courseTitleEl.textContent = course.title;
            if (courseSubtitleEl) courseSubtitleEl.textContent = course.description || '';

            const videos = course.videos || [];
            const courseName = escapeHtml(course.title || '');
            if (lessonsListEl) {
                lessonsListEl.innerHTML = videos.map(function (v, i) {
                    const duration = formatDuration(v.duration);
                    const title = escapeHtml(v.title);
                    const dayNum = i + 1;
                    return (
                        '<div class="lesson-item schedule-card" data-video-id="' + v.id + '" role="button" tabindex="0">' +
                        '<span class="schedule-date">' + dayNum + '</span>' +
                        '<div class="schedule-info">' +
                        '<span class="schedule-course">' + courseName + '</span>' +
                        '<span class="schedule-activity">' + title + '</span>' +
                        '</div>' +
                        '<span class="schedule-time">' + escapeHtml(duration) + '</span>' +
                        '</div>'
                    );
                }).join('');

                lessonsListEl.querySelectorAll('.lesson-item').forEach(function (item) {
                    const videoId = item.getAttribute('data-video-id');
                    item.addEventListener('click', function () {
                        playVideo(parseInt(videoId, 10));
                    });
                    item.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            playVideo(parseInt(videoId, 10));
                        }
                    });
                });
            }

            if (videoSectionEl && videos.length > 0) {
                renderVideoPlayer(videos[0].id, videos[0].title);
                if (lessonsListEl && lessonsListEl.querySelector('.lesson-item')) {
                    lessonsListEl.querySelector('.lesson-item').classList.add('active');
                }
            }
        } catch (err) {
            console.error('Load course error:', err);
            if (loadingEl) loadingEl.style.display = 'none';
            if (courseTitleEl) courseTitleEl.textContent = 'حدث خطأ في تحميل الكورس';
        }
    }

    function renderVideoPlayer(videoId, title) {
        if (!videoSectionEl) return;
        var fp = getFingerprint();
        var videoUrl = 'api/courses/proxy-video.php?video_id=' + videoId;
        if (fp) {
            videoUrl += '&fp=' + encodeURIComponent(fp);
        }
        videoSectionEl.innerHTML =
            '<div class="video-player-wrapper">' +
            '<video id="course-video-player" class="course-video-player" controls controlsList="nodownload" playsinline crossorigin="use-credentials">' +
            '<source src="' + escapeHtml(videoUrl) + '" type="video/mp4">' +
            'متصفحك لا يدعم تشغيل الفيديو.' +
            '</video>' +
            '</div>';
    }

    function playVideo(videoId) {
        const course = window.__courseDetailData;
        if (!course || !course.videos) return;
        const video = course.videos.find(function (v) { return v.id === videoId; });
        const title = video ? video.title : '';
        renderVideoPlayer(videoId, title);
        if (lessonsListEl) {
            lessonsListEl.querySelectorAll('.lesson-item').forEach(function (el) {
                el.classList.toggle('active', parseInt(el.getAttribute('data-video-id'), 10) === videoId);
            });
        }
        const videoEl = document.getElementById('course-video-player');
        if (videoEl) {
            videoEl.play().catch(function (err) {
                // تجاهل AbortError - يحدث عند تغيير الدرس بسرعة (إزالة الفيديو من DOM)
                if (err.name !== 'AbortError') {
                    console.error('Video play error:', err);
                }
            });
        }
    }

    function init() {
        if (!courseId || courseId <= 0) {
            if (courseTitleEl) courseTitleEl.textContent = 'معرف الكورس غير صحيح';
            if (backButton) backButton.href = 'courses.html';
            return;
        }

        const codeForm = document.getElementById('code-verify-form');
        if (codeForm) {
            codeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                verifyCode();
                return false;
            });
        }
        if (codeInput) {
            codeInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyCode();
                }
            });
        }

        document.body.addEventListener('click', function (e) {
            var btn = e.target.closest('#code-verify-submit');
            if (btn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                verifyCode();
            }
        }, true);

        document.getElementById('code-verify-link-codes')?.addEventListener('click', function () {
            window.location.href = 'profile.html?tab=course-codes';
        });

        // مطلوب دائماً: إظهار مودال إدخال كود المشاهدة أولاً (حتى للمشترين)
        // لا ندخل للمشاهدة إلا بعد إدخال الكود هنا → verify-access يربط الجهاز بـ bound_device_hash
        if (courseContentEl) courseContentEl.style.display = 'none';
        if (codeModal) codeModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
