const CACHE_NAME = 'str-syc-v1';
const ASSETS_TO_CACHE = [
  '/str-syc/',
  '/str-syc/index.php',
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
      console.log('[SW] Pre-caching offline assets individually...');
      
      // Cache files individually to prevent one 404 from failing the entire Service Worker
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

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      return cachedResponse || fetch(event.request);
    })
  );
});