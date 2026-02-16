// Minimal Service Worker for Admin PWA
const CACHE_NAME = 'admin-cache-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Network-only strategy for now to avoid caching issues, 
    // effectively satisfying PWA installation requirements.
    event.respondWith(
        fetch(event.request).catch(() => {
            // Optional: Return a fallback offline page if available
            return new Response("You are offline. Please check your internet connection.");
        })
    );
});
