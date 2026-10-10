/* ADHD.lk service worker
 *
 * Caching rules:
 *  - Session-dependent pages are NEVER cached. The Cache API is keyed by URL
 *    only, so caching /dashboard would let one visitor's rendered tasks, email
 *    or XP be served to the next person on the same device (or to themselves
 *    after logging out).
 *  - Static assets and genuinely public pages are cached network-first, so the
 *    site keeps working offline.
 *  - Any failed navigation falls back to /offline.html.
 */
const CACHE_NAME = 'adhdlk-cache-v9';

const PRECACHE = [
  '/offline.html',
  '/assets/icon-192.png',
  '/assets/icon-512.png'
];

// Pages rendered from $_SESSION — must never enter a shared cache.
const PRIVATE_PATHS = [
  '/dashboard', '/settings', '/habits', '/journal', '/routines',
  '/admin', '/admin_articles', '/login', '/register', '/logout',
  '/forgot', '/reset', '/delete_account'
];

function isPrivate(url) {
  return PRIVATE_PATHS.some(p => url.pathname === p || url.pathname.startsWith(p + '/'));
}

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => Promise.all(
        PRECACHE.map(url => cache.add(url).catch(() => {}))
      ))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(names => Promise.all(
        names.filter(n => n !== CACHE_NAME).map(n => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  let url;
  try { url = new URL(request.url); } catch (e) { return; }

  // Only handle our own origin.
  if (url.origin !== self.location.origin) return;

  // Never touch the API or private pages — always go straight to the network,
  // and fall back to the offline page rather than to someone else's cached HTML.
  if (url.pathname.startsWith('/api/') || isPrivate(url)) {
    event.respondWith(
      fetch(request).catch(() =>
        request.mode === 'navigate'
          ? caches.match('/offline.html')
          : Response.error()
      )
    );
    return;
  }

  // Public pages + static assets: network-first, fall back to cache.
  event.respondWith(
    fetch(request)
      .then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, copy)).catch(() => {});
        }
        return response;
      })
      .catch(() =>
        caches.match(request).then(hit => {
          if (hit) return hit;
          if (request.mode === 'navigate') return caches.match('/offline.html');
          return Response.error();
        })
      )
  );
});
