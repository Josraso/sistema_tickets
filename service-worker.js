/* ============================================================
   Service Worker — Sistema de Tickets
   Responsable de: cache estático (offline PWA) + notificaciones
   ============================================================ */

var CACHE = 'tickets-sw-v1';
var STATICS = ['/assets/style.css', '/assets/icon-192.png', '/assets/icon-512.png'];

/* --- Install: cachear assets estáticos --- */
self.addEventListener('install', function(e) {
    e.waitUntil(
        caches.open(CACHE).then(function(cache) {
            return cache.addAll(STATICS);
        })
    );
    self.skipWaiting();
});

/* --- Activate: borrar caches viejos --- */
self.addEventListener('activate', function(e) {
    e.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) { return k !== CACHE; })
                    .map(function(k) { return caches.delete(k); })
            );
        })
    );
});

/* --- Fetch: cache-first solo para assets estáticos --- */
self.addEventListener('fetch', function(e) {
    if (e.request.url.match(/\.(css|png|jpg|jpeg|gif|ico|woff2?)$/)) {
        e.respondWith(
            caches.match(e.request).then(function(cached) {
                if (cached) return cached;
                return fetch(e.request).then(function(resp) {
                    return caches.open(CACHE).then(function(cache) {
                        cache.put(e.request, resp.clone());
                        return resp;
                    });
                });
            })
        );
    }
    // Páginas y APIs: sin interceptar, network normal
});

/* --- Mensaje desde página principal → mostrar notificación --- */
self.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'SHOW_NOTIFICATION') {
        self.showNotification(e.data.title, {
            body:               e.data.body || '',
            icon:               '/assets/icon-192.png',
            badge:              '/assets/icon-192.png',
            data:               { url: e.data.url || '/' },
            requireInteraction: false,
            silent:             false
        });
    }
});

/* --- Push (futuro Web Push) --- */
self.addEventListener('push', function(e) {
    var data = e.data ? e.data.json() : {};
    e.waitUntil(
        self.showNotification(data.title || 'Tickets', {
            body:  data.body  || '',
            icon:  '/assets/icon-192.png',
            badge: '/assets/icon-192.png',
            data:  { url: data.url || '/' }
        })
    );
});

/* --- Click en notificación → abrir ticket --- */
self.addEventListener('notificationclick', function(e) {
    e.preventDefault();
    var url = (e.notification.data && e.notification.data.url) || '/';
    e.notification.close();
    e.waitUntil(
        clients.matchAll({ type: 'window' }).then(function(list) {
            for (var i = 0; i < list.length; i++) {
                if (list[i].url === url) return list[i].focus();
            }
            return clients.openWindow(url);
        })
    );
});
