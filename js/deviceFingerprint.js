/**
 * ============================================
 * Device Fingerprint - بصمة الجهاز
 * ============================================
 * يجمع البيانات الثابتة للجهاز (شاشة، متصفح، canvas)،
 * يجزئها بـ SHA-256، يخزن الناتج في كوكي وقاعدة البيانات مرة واحدة فقط.
 * لا يُعاد إنشاء البصمة مع تكرار الزيارات إن وُجدت في الكوكي.
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'dfp';
    var COOKIE_MAX_AGE_DAYS = 365;
    var HASH_HEX_LENGTH = 64;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function setCookie(name, value, maxAgeDays) {
        var maxAge = maxAgeDays * 24 * 60 * 60;
        var secure = window.location && window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
    }

    function isValidFingerprintHash(str) {
        return typeof str === 'string' && /^[a-f0-9]{64}$/i.test(str.trim());
    }

    /**
     * تحويل ArrayBuffer إلى سلسلة hex
     */
    function bufferToHex(buffer) {
        var arr = new Uint8Array(buffer);
        var hex = '';
        for (var i = 0; i < arr.length; i++) {
            var h = arr[i].toString(16);
            hex += h.length === 1 ? '0' + h : h;
        }
        return hex;
    }

    /**
     * هاش SHA-256 لسلسلة نصية (تشفير قوي أحادي الاتجاه)
     */
    function sha256(str) {
        return new Promise(function (resolve, reject) {
            if (typeof crypto === 'undefined' || !crypto.subtle) {
                reject(new Error('crypto.subtle not available'));
                return;
            }
            var encoder = new TextEncoder();
            var data = encoder.encode(str);
            crypto.subtle.digest('SHA-256', data).then(function (buffer) {
                resolve(bufferToHex(buffer));
            }).catch(reject);
        });
    }

    /**
     * جمع البيانات الثابتة للشاشة والمتصفح (بدون timezone أو IP)
     */
    function collectStaticData() {
        var s = window.screen || {};
        var n = window.navigator || {};
        var screenData = {
            width: s.width,
            height: s.height,
            colorDepth: s.colorDepth,
            pixelDepth: s.pixelDepth,
            availWidth: s.availWidth,
            availHeight: s.availHeight
        };
        var pixelRatio = typeof window.devicePixelRatio === 'number' ? window.devicePixelRatio : 1;
        screenData.devicePixelRatio = pixelRatio;

        var lang = n.language || '';
        var langs = n.languages && Array.isArray(n.languages) ? n.languages.join(',') : lang;
        var navData = {
            hardwareConcurrency: n.hardwareConcurrency,
            deviceMemory: n.deviceMemory,
            platform: n.platform,
            maxTouchPoints: n.maxTouchPoints,
            language: lang,
            languages: langs,
            cookieEnabled: n.cookieEnabled,
            vendor: n.vendor,
            pdfViewerEnabled: n.pdfViewerEnabled,
            webdriver: n.webdriver,
            userAgent: n.userAgent
        };

        return { screen: screenData, navigator: navData };
    }

    /**
     * بصمة Canvas (نفس الجهاز يعطي نفس الناتج تقريباً)
     */
    function getCanvasFingerprint() {
        try {
            var canvas = document.createElement('canvas');
            canvas.width = 220;
            canvas.height = 30;
            var ctx = canvas.getContext('2d');
            if (!ctx) return '';
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#4a90e2';
            ctx.fillRect(0, 0, 220, 30);
            ctx.fillStyle = '#1a2332';
            ctx.fillText('AmrNayl', 2, 15);
            return canvas.toDataURL ? canvas.toDataURL('image/png') : '';
        } catch (e) {
            return '';
        }
    }

    /**
     * بناء السلسلة النهائية وتجزئتها
     */
    function buildAndHash() {
        var staticData = collectStaticData();
        var canvasData = getCanvasFingerprint();
        var payload = JSON.stringify(staticData) + canvasData;
        return sha256(payload);
    }

    /**
     * إرسال البصمة إلى السيرفر لتسجيلها مرة واحدة
     */
    function registerFingerprintOnServer(hash) {
        var path = window.location.pathname || '';
        var apiPath = 'api/visit/register-fingerprint.php';
        if (path.indexOf('/admin/') !== -1) {
            apiPath = '../api/visit/register-fingerprint.php';
        }
        fetch(apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fingerprint_hash: hash })
        }).catch(function () {});
    }

    /**
     * تهيئة البصمة: إن وُجدت في الكوكي لا نعيد الإنشاء، وإلا نحسبها ونخزنها مرة واحدة
     */
    function init() {
        var existing = getCookie(COOKIE_NAME);
        if (existing && isValidFingerprintHash(existing)) {
            return;
        }
        buildAndHash().then(function (hash) {
            if (!hash || hash.length !== HASH_HEX_LENGTH) return;
            setCookie(COOKIE_NAME, hash, COOKIE_MAX_AGE_DAYS);
            registerFingerprintOnServer(hash);
        }).catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.getDeviceFingerprint = function () {
        var v = getCookie(COOKIE_NAME);
        return isValidFingerprintHash(v) ? v : null;
    };
})();
