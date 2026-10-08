const CACHE_NAME = 'str-syc-v2';
const ASSETS_TO_CACHE = [
  '/str-syc/manifest/index.php',
  '/str-syc/manifest/login.php',
  '/str-syc/manifest/manifest.json',
  '/str-syc/assets/js/app.js',
  '/str-syc/assets/img/logo_strandsync.png',
  '/str-syc/assets/img/Strand_Sync_bg.png'
];

// Install Event - Resilient asset caching
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      console.log('[SW] Pre-caching offline assets...');
      
      const cachePromises = ASSETS_TO_CACHE.map(async (asset) => {
        try {
          const response = await fetch(asset);
          if (!response.ok) {
            throw new Error(`Status ${response.status}`);
          }
          await cache.put(asset, response);
          console.log(`[SW] Cached: ${asset}`);
        } catch (error) {
          console.warn(`[SW] Failed to cache asset (${asset}):`, error);
        }
      });

      await Promise.allSettled(cachePromises);
    })
  );
  self.skipWaiting();
});

// Activate Event - Clean up old cache versions
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            console.log(`[SW] Deleting old cache: ${key}`);
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event - Hybrid Strategy (Network-First for PHP/HTML, Cache-First for Assets)
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // 1. Navigation / PHP Page Requests: Network-First Strategy
  if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(async (networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        })
        .catch(async () => {
          // If offline or network fails, try returning cached page or fallback
          const cachedPage = await caches.match(event.request, { ignoreSearch: true });
          if (cachedPage) return cachedPage;

          const fallbackPage = await caches.match('/str-syc/manifest/login.php', { ignoreSearch: true });
          if (fallbackPage) return fallbackPage;

          return new Response('Offline: Network unavailable.', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({ 'Content-Type': 'text/plain' })
          });
        })
    );
    return;
  }

  // 2. Static Assets (Images, CSS, JS): Cache-First Strategy
  event.respondWith(
    caches.match(event.request, { ignoreSearch: true }).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request)
        .then(async (networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        })
        .catch(() => {
          return new Response('Asset unavailable offline.', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({ 'Content-Type': 'text/plain' })
          });
        });
    })
  );
});