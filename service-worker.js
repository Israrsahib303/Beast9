const CACHE_NAME = 'subhub-v2'; // Version change kiya taaki naya cache bane
const urlsToCache = [
  'user/index.php',
  'user/smm_order.php',
  'assets/css/style.css',
  'assets/css/smm_style.css',
  'manifest.php', // <--- Yahan .json ki jagah .php kar diya
  'assets/img/logo.png' // Default logo fallback
];

// Install SW
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch Resources
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        return response || fetch(event.request);
      })
  );
});

// Activate & Cleanup Old Caches
self.addEventListener('activate', (event) => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});