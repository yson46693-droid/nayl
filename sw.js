const CACHE_NAME = 'amrnayl-v2';
const STATIC_ASSETS = [
    './',
    './index.html',
    './home.html',
    './courses.html',
    './signup.html',
    './profile.html',
    './css/style.css',
    './js/main.js',
    './js/pwa.js',
    './pics/logo.png',
    './manifest.json'
];

// تثبيت Service Worker وتخزين الملفات
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                // تفعيل فوري بدون انتظار
                return self.skipWaiting();
            })
            .catch(err => {
                console.log('Service Worker: Cache failed', err);
            })
    );
});

// تفعيل Service Worker وحذف الكاش القديم
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('Service Worker: Clearing old cache', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => {
            // التحكم في جميع العملاء فوراً
            return self.clients.claim();
        })
    );
});

// استراتيجية: Network First, fallback to Cache
self.addEventListener('fetch', (event) => {
    // تجاهل طلبات غير HTTP/HTTPS
    if (!event.request.url.startsWith('http')) {
        return;
    }

    // تجاهل طلبات API أو الطلبات الخارجية
    if (event.request.url.includes('/api/') ||
        event.request.url.includes('googleapis.com') ||
        event.request.url.includes('cdn.jsdelivr.net')) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        // محاولة جلب من الشبكة أولاً
        fetch(event.request)
            .then((response) => {
                // إذا نجح الطلب، نخزن نسخة في الكاش
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // إذا فشل الطلب، نجلب من الكاش
                return caches.match(event.request)
                    .then((response) => {
                        if (response) {
                            return response;
                        }
                        // إذا لم يوجد في الكاش، نعرض صفحة offline
                        if (event.request.mode === 'navigate') {
                            return caches.match('./home.html');
                        }
                    });
            })
    );
});

// استقبال رسائل من الصفحة
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
