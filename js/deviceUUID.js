/**
 * ============================================
 * Device UUID - معرف فريد للجهاز
 * ============================================
 * يُنشأ مرة واحدة ويُخزَّن في localStorage ولا يُعاد إنشاؤه إن وُجد
 * يعمل تلقائياً عند تحميل الصفحة
 */
(function () {
    'use strict';

    var DEVICE_UUID_KEY = 'device_uuid_v1';

    function generateSecureUUID() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            var bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            var hex = Array.from(bytes).map(function (b) {
                return b.toString(16).padStart(2, '0');
            }).join('');
            return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20, 32);
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function getDeviceUUID() {
        try {
            var existing = localStorage.getItem(DEVICE_UUID_KEY);
            if (existing) {
                return existing;
            }
            var uuid = generateSecureUUID();
            localStorage.setItem(DEVICE_UUID_KEY, uuid);
            try {
                localStorage.removeItem('device_id');
            } catch (e) {}
            return uuid;
        } catch (e) {
            return generateSecureUUID();
        }
    }

    window.getDeviceUUID = getDeviceUUID;
    getDeviceUUID();
})();
