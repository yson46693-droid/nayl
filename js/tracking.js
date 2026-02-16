(function () {
    // Visitor Tracking - يستخدم Device UUID من deviceUUID.js
    // يتأكد من تحميل deviceUUID.js قبل هذا الملف
    var deviceUUID = typeof window.getDeviceUUID === 'function'
        ? window.getDeviceUUID()
        : (function () {
            var k = 'device_uuid_v1';
            try {
                var existing = localStorage.getItem(k);
                if (existing) return existing;
                var u = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (Math.random() * 16) | 0;
                    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
                });
                localStorage.setItem(k, u);
                return u;
            } catch (e) {
                return 'fallback-' + Date.now() + '-' + Math.random().toString(36).slice(2);
            }
        })();

    var v_id = deviceUUID;

    // Determine API path (handle if we are in admin subfolder or root)
    // Assuming the script is loaded in root pages mostly.
    // If loaded in admin/, track.php is ../api/visit/track.php
    // If loaded in root, api/visit/track.php

    // Simple check: current path
    const path = window.location.pathname;
    let apiPath = 'api/visit/track.php';
    if (path.includes('/admin/')) {
        apiPath = '../api/visit/track.php';
    } else if (path.includes('/api/')) {
        // Unlikely to visit api files directly with HTML
    }

    // Try to send
    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ v_id: v_id })
    }).catch(err => {
        // Fallback for pathing issues, try absolute if likely root
        if (apiPath !== '/api/visit/track.php') {
            fetch('/api/visit/track.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ v_id: v_id })
            }).catch(e => console.error('Tracking failed', e));
        }
    });

})();
