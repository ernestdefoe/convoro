/* Convoro service worker — installable PWA + Web Push.
 * Conservative caching: only same-origin built assets + icons (stale-while-revalidate).
 * Navigations and API/XHR always hit the network, so Inertia/CSRF are never stale. */
const CACHE = 'convoro-static-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const cacheable = url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');
  if (!cacheable) return; // let the network handle everything else

  event.respondWith(
    caches.open(CACHE).then(async (cache) => {
      const cached = await cache.match(request);
      const network = fetch(request)
        .then((res) => {
          if (res && res.status === 200) cache.put(request, res.clone());
          return res;
        })
        .catch(() => cached);
      return cached || network;
    })
  );
});

/* ---- Web Push ---- */
self.addEventListener('push', (event) => {
  let p = {};
  try { p = event.data ? event.data.json() : {}; } catch (e) { p = {}; }
  const title = p.title || 'Convoro';
  const url = (p.data && p.data.url) || p.url || '/';
  event.waitUntil(
    self.registration.showNotification(title, {
      body: p.body || '',
      icon: p.icon || '/icons/icon-192.png',
      badge: p.badge || '/icons/favicon-32.png',
      tag: p.tag || undefined,
      data: { url },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if ('focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      return self.clients.openWindow(target);
    })
  );
});
