/**
 * صفحة تفاصيل الكورس - طلب كود التفعيل ثم عرض المحتوى
 */
(function () {
    'use strict';
    const urlParams = new URLSearchParams(window.location.search);
    const courseId = urlParams.get('id') ? parseInt(urlParams.get('id'), 10) : null;

    const STORAGE_KEY_PREFIX = 'course_unlocked_';
    const VERIFIED_AT_KEY_PREFIX = 'course_verified_at_';
    /** صلاحية التحقق بالساعات — بعدها يُطلب الكود مرة أخرى */
    const VERIFY_VALID_HOURS = 2;
    const VERIFY_VALID_MS = VERIFY_VALID_HOURS * 60 * 60 * 1000;
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

    function getVerifiedAtKey() {
        return courseId ? VERIFIED_AT_KEY_PREFIX + courseId : null;
    }

    function isUnlocked() {
        const key = getUnlockKey();
        return key ? sessionStorage.getItem(key) === '1' : false;
    }

    /** هل التحقق لا يزال ضمن المدة المسموحة (مثلاً ساعتين)؟ */
    function isVerificationStillValid() {
        if (!isUnlocked()) return false;
        const key = getVerifiedAtKey();
        if (!key) return false;
        const at = sessionStorage.getItem(key);
        if (!at) return false;
        const ts = parseInt(at, 10);
        if (isNaN(ts)) return false;
        return (Date.now() - ts) < VERIFY_VALID_MS;
    }

    function setUnlocked() {
        const key = getUnlockKey();
        const atKey = getVerifiedAtKey();
        if (key) sessionStorage.setItem(key, '1');
        if (atKey) sessionStorage.setItem(atKey, String(Date.now()));
    }

    function clearUnlockAndTimestamp() {
        const key = getUnlockKey();
        const atKey = getVerifiedAtKey();
        if (key) sessionStorage.removeItem(key);
        if (atKey) sessionStorage.removeItem(atKey);
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
                    clearUnlockAndTimestamp();
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
                    const desc = (v.description || '').trim();
                    const descHtml = desc ? ('<span class="schedule-description">' + escapeHtml(desc) + '</span>') : '';
                    const dayNum = i + 1;
                    const thumbHtml = (v.thumbnail_url)
                        ? ('<img class="lesson-thumb" src="' + escapeHtml(v.thumbnail_url) + '" alt="">')
                        : '<div class="lesson-thumb lesson-thumb-placeholder"><i class="bi bi-play-circle-fill"></i></div>';
                    return (
                        '<div class="lesson-item schedule-card" data-video-id="' + v.id + '" role="button" tabindex="0">' +
                        '<div class="lesson-thumb-wrap">' + thumbHtml + '</div>' +
                        '<span class="schedule-date">' + dayNum + '</span>' +
                        '<div class="schedule-info">' +
                        '<span class="schedule-course">' + courseName + '</span>' +
                        '<span class="schedule-activity">' + title + '</span>' +
                        descHtml +
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

    var currentHlsInstance = null;
    var watermarkIntervalId = null;

    function getUserIdForWatermark() {
        try {
            var userDataStr = localStorage.getItem('userData') || sessionStorage.getItem('userData');
            if (userDataStr) {
                var user = JSON.parse(userDataStr);
                return user.id || user.user_id || '';
            }
        } catch (e) { /* ignore */ }
        return '';
    }

    function moveWatermark() {
        var watermark = document.getElementById('course-video-watermark');
        var container = document.getElementById('course-video-container');
        if (!watermark || !container) return;
        var maxX = container.offsetWidth - watermark.offsetWidth;
        var maxY = container.offsetHeight - watermark.offsetHeight;
        if (maxX < 0) maxX = 0;
        if (maxY < 0) maxY = 0;
        var randomX = Math.floor(Math.random() * (maxX + 1));
        var randomY = Math.floor(Math.random() * (maxY + 1));
        watermark.style.left = randomX + 'px';
        watermark.style.top = randomY + 'px';
    }

    function renderVideoPlayer(videoId, title) {
        if (!videoSectionEl) return;
        if (watermarkIntervalId) {
            clearInterval(watermarkIntervalId);
            watermarkIntervalId = null;
        }
        if (currentHlsInstance) {
            try { currentHlsInstance.destroy(); } catch (e) { /* ignore */ }
            currentHlsInstance = null;
        }

        var course = window.__courseDetailData;
        var video = course && course.videos ? course.videos.find(function (v) { return v.id === videoId; }) : null;
        var videoUrl = (video && video.video_url) ? video.video_url : '';
        var hlsUrl = (video && video.hls_url) ? video.hls_url : '';
        var thumbUrl = (video && video.thumbnail_url) ? video.thumbnail_url : '';
        var userId = getUserIdForWatermark();
        var videoTitle = (video && video.title) ? escapeHtml(video.title) : (title ? escapeHtml(title) : '');
        var videoDesc = (video && video.description) ? escapeHtml(video.description.trim()) : '';
        var descriptionBlock = videoDesc
            ? ('<div class="course-video-description"><p>' + videoDesc + '</p></div>')
            : '';
        var titleBlock = videoTitle
            ? ('<h3 class="course-video-title-display">' + videoTitle + '</h3>')
            : '';

        if (hlsUrl) {
            var posterAttr = thumbUrl ? (' poster="' + escapeHtml(thumbUrl) + '"') : '';
            videoSectionEl.innerHTML =
                '<div class="video-player-wrapper">' +
                titleBlock +
                '<div id="course-video-container" class="course-video-container cvp-container">' +
                '<video id="course-video-player" class="course-video-player" playsinline' + posterAttr + '></video>' +
                '<div id="course-video-watermark" class="course-video-watermark" aria-hidden="true">' + escapeHtml(String(userId)) + '</div>' +
                '<div class="cvp-click-overlay" id="cvp-click-overlay"></div>' +
                '<div class="cvp-controls" id="cvp-controls">' +
                '<div class="cvp-progress-wrap"><input type="range" class="cvp-progress" id="cvp-progress" value="0" min="0" max="100" step="0.1"></div>' +
                '<div class="cvp-bottom">' +
                '<div class="cvp-left">' +
                '<button type="button" class="cvp-btn" id="cvp-play" title="تشغيل/إيقاف"><i class="bi bi-play-fill" id="cvp-play-icon"></i></button>' +
                '<span class="cvp-time" id="cvp-time">0:00 / 0:00</span>' +
                '<button type="button" class="cvp-btn" id="cvp-mute" title="صوت"><i class="bi bi-volume-up-fill" id="cvp-mute-icon"></i></button>' +
                '<input type="range" class="cvp-volume" id="cvp-volume" value="100" min="0" max="100">' +
                '</div>' +
                '<div class="cvp-right">' +
                '<button type="button" class="cvp-btn" id="cvp-fullscreen" title="ملء الشاشة"><i class="bi bi-fullscreen" id="cvp-fs-icon"></i></button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                descriptionBlock;

            var videoEl = document.getElementById('course-video-player');
            var src = hlsUrl;

            if (videoEl && src) {
                if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                    currentHlsInstance = new Hls({ enableWorker: true });
                    currentHlsInstance.loadSource(src);
                    currentHlsInstance.attachMedia(videoEl);
                } else if (videoEl.canPlayType && videoEl.canPlayType('application/vnd.apple.mpegurl')) {
                    videoEl.src = src;
                }
            }

            moveWatermark();
            watermarkIntervalId = setInterval(moveWatermark, 3000);

            // ── Custom Player Controls ──
            var container   = document.getElementById('course-video-container');
            var playBtn     = document.getElementById('cvp-play');
            var playIcon    = document.getElementById('cvp-play-icon');
            var muteBtn     = document.getElementById('cvp-mute');
            var muteIcon    = document.getElementById('cvp-mute-icon');
            var volumeRange = document.getElementById('cvp-volume');
            var progressRange = document.getElementById('cvp-progress');
            var timeEl      = document.getElementById('cvp-time');
            var fsBtn       = document.getElementById('cvp-fullscreen');
            var fsIcon      = document.getElementById('cvp-fs-icon');
            var clickOverlay = document.getElementById('cvp-click-overlay');
            var hideTimeout;

            function fmtTime(s) {
                if (!s || isNaN(s)) return '0:00';
                var h = Math.floor(s / 3600);
                var m = Math.floor((s % 3600) / 60);
                var sec = Math.floor(s % 60);
                if (h > 0) return h + ':' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
                return m + ':' + (sec < 10 ? '0' : '') + sec;
            }

            function togglePlay() {
                if (videoEl.paused) { videoEl.play(); } else { videoEl.pause(); }
            }

            if (playBtn) playBtn.addEventListener('click', togglePlay);
            if (clickOverlay) clickOverlay.addEventListener('click', togglePlay);

            videoEl.addEventListener('play', function () {
                if (playIcon) playIcon.className = 'bi bi-pause-fill';
                showControls();
            });
            videoEl.addEventListener('pause', function () {
                if (playIcon) playIcon.className = 'bi bi-play-fill';
                if (container) container.classList.add('cvp-show-controls');
            });
            videoEl.addEventListener('ended', function () {
                if (playIcon) playIcon.className = 'bi bi-arrow-counterclockwise';
                if (container) container.classList.add('cvp-show-controls');
            });
            videoEl.addEventListener('waiting', function () {
                if (container) container.classList.add('cvp-buffering');
            });
            videoEl.addEventListener('canplay', function () {
                if (container) container.classList.remove('cvp-buffering');
            });

            videoEl.addEventListener('timeupdate', function () {
                if (videoEl.duration && progressRange) {
                    progressRange.value = (videoEl.currentTime / videoEl.duration) * 100;
                    var pct = (progressRange.value / 100) * 100;
                    progressRange.style.setProperty('--cvp-progress-pct', pct + '%');
                }
                if (timeEl) timeEl.textContent = fmtTime(videoEl.currentTime) + ' / ' + fmtTime(videoEl.duration);
            });

            videoEl.addEventListener('loadedmetadata', function () {
                if (timeEl) timeEl.textContent = fmtTime(0) + ' / ' + fmtTime(videoEl.duration);
            });

            if (progressRange) {
                progressRange.addEventListener('input', function () {
                    if (videoEl.duration) {
                        videoEl.currentTime = (progressRange.value / 100) * videoEl.duration;
                        progressRange.style.setProperty('--cvp-progress-pct', progressRange.value + '%');
                    }
                });
            }

            function updateVolumeUI(val) {
                // val: 0–100
                var pct = val + '%';
                var color = val === 0 ? 'rgba(255,255,255,0.3)' : val > 66 ? '#e74c3c' : val > 33 ? '#f39c12' : '#2ecc71';
                if (volumeRange) {
                    volumeRange.value = val;
                    volumeRange.style.setProperty('--cvp-vol-pct', pct);
                    volumeRange.style.setProperty('--cvp-vol-color', color);
                }
                if (muteIcon) {
                    muteIcon.className = val === 0 ? 'bi bi-volume-mute-fill' : val < 50 ? 'bi bi-volume-down-fill' : 'bi bi-volume-up-fill';
                    muteIcon.style.color = val === 0 ? '' : color;
                }
            }

            // تهيئة الـ UI بالقيمة الافتراضية
            updateVolumeUI(100);

            if (muteBtn) {
                muteBtn.addEventListener('click', function () {
                    videoEl.muted = !videoEl.muted;
                    updateVolumeUI(videoEl.muted ? 0 : Math.round(videoEl.volume * 100));
                });
            }
            if (volumeRange) {
                volumeRange.addEventListener('input', function () {
                    var val = parseInt(volumeRange.value, 10);
                    videoEl.volume = val / 100;
                    videoEl.muted = (val === 0);
                    updateVolumeUI(val);
                });
            }

            // Fullscreen
            // iOS Safari: requestFullscreen مش شغال على div — لازم webkitEnterFullscreen على الـ video
            var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

            if (fsBtn && container) {
                fsBtn.addEventListener('click', function () {
                    if (isIOS) {
                        // iOS: fullscreen على الـ video element مباشرةً
                        if (videoEl.webkitEnterFullscreen) {
                            videoEl.webkitEnterFullscreen();
                        }
                    } else {
                        var fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                        if (!fsEl) {
                            var req = container.requestFullscreen || container.webkitRequestFullscreen;
                            if (req) req.call(container);
                        } else {
                            var exit = document.exitFullscreen || document.webkitExitFullscreen;
                            if (exit) exit.call(document);
                        }
                    }
                });
            }

            function onFsChange() {
                var fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                if (fsIcon) fsIcon.className = fsEl ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
                moveWatermark();
            }
            document.addEventListener('fullscreenchange', onFsChange);
            document.addEventListener('webkitfullscreenchange', onFsChange);

            // iOS fullscreen events
            if (isIOS && videoEl) {
                videoEl.addEventListener('webkitbeginfullscreen', function () {
                    if (fsIcon) fsIcon.className = 'bi bi-fullscreen-exit';
                });
                videoEl.addEventListener('webkitendfullscreen', function () {
                    if (fsIcon) fsIcon.className = 'bi bi-fullscreen';
                });
            }

            function showControls() {
                if (!container) return;
                container.classList.add('cvp-show-controls');
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(function () {
                    if (!videoEl.paused) container.classList.remove('cvp-show-controls');
                }, 3000);
            }

            if (container) {
                container.addEventListener('mousemove', showControls);
                container.addEventListener('touchstart', showControls, { passive: true });
                container.classList.add('cvp-show-controls');
            }
        } else {
            // لا iframe — نعرض صورة الواجهة مع رسالة أن التشغيل عبر HLS فقط
            var noHlsHtml = '<div class="video-player-wrapper">' +
                titleBlock +
                '<div id="course-video-container" class="course-video-container course-video-no-hls">' +
                (thumbUrl
                    ? '<div class="video-thumb-overlay video-thumb-overlay-static">' +
                      '<img class="video-thumb-overlay-img" src="' + escapeHtml(thumbUrl) + '" alt="">' +
                      '<span class="course-video-no-hls-msg"><i class="bi bi-broadcast"></i> الفيديو غير متاح للتشغيل (يتطلب HLS)</span>' +
                      '</div>'
                    : '<div class="course-video-no-hls-msg only"><i class="bi bi-broadcast"></i> الفيديو غير متاح للتشغيل (يتطلب HLS)</div>') +
                '</div>' +
                '</div>' +
                descriptionBlock;
            videoSectionEl.innerHTML = noHlsHtml;
        }
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
        // iframe يحمّل الفيديو تلقائياً عند تعيين src في renderVideoPlayer
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

        // إذا كان المستخدم قد تحقق خلال المدة المسموحة (مثلاً ساعتين) لا نطلب الكود عند الريفرش
        if (isVerificationStillValid()) {
            if (codeModal) codeModal.style.display = 'none';
            if (courseContentEl) courseContentEl.style.display = 'block';
            document.body.style.overflow = '';
            loadCourseContent();
            return;
        }

        // غير ذلك: إظهار مودال إدخال كود المشاهدة
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
