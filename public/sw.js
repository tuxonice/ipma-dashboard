const CACHE_NAME = 'ipma-shell-v1';

const SHELL_ASSETS = [
    '/offline.html',
    '/icons/icon-192.svg',
    '/icons/icon-512.svg',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Cache-first for CDN assets and icons.
    if (
        url.hostname === 'cdn.jsdelivr.net' ||
        (url.hostname === self.location.hostname && url.pathname.startsWith('/icons/'))
    ) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
        return;
    }

    // Network-first for same-origin navigation (live weather data).
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match('/offline.html')
            )
        );
        return;
    }
});
