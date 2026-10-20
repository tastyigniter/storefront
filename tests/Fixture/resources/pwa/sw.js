const CACHE = 'glass-offline-v2'
const OFFLINE = '/offline.html'

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.add(OFFLINE))
            .then(() => self.skipWaiting()),
    )
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    )
})

self.addEventListener('fetch', (event) => {
    const request = event.request
    if (request.method !== 'GET' || request.mode !== 'navigate') {
        return
    }

    // Checkout and payment returns stay on the network so a saved page is never served.
    const path = new URL(request.url).pathname
    if (path.startsWith('/checkout')) {
        return
    }

    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)))
})
