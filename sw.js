// Service Worker for caching and offline functionality

const CACHE_NAME = 'website-cache-v1.1';
const urlsToCache = [
  '/',
  '/index.php',
  '/about.php',
  '/contact.php',
  '/search.php',
  '/assets/css/modern-theme.css',
  '/assets/css/mobile-menu-fix.css',
  '/assets/css/ad-performance.css',
  '/assets/css/performance-optimizations.css',
  '/assets/css/style.css',
  '/assets/js/responsive-ads.js',
  '/assets/css/admin.css',
  '/assets/js/admin.js'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        // Filter out external URLs for pre-caching
        const localUrls = urlsToCache.filter(url => 
          !url.startsWith('http://') && !url.startsWith('https://')
        );
        return cache.addAll(localUrls);
      })
  );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Skip caching for external resources
  if (event.request.url.startsWith('http') && 
      !event.request.url.includes(self.location.hostname)) {
    return;
  }
  
  // For HTML requests, try network first, then cache
  if (event.request.headers.get('accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone the response to put in cache
          const responseToCache = response.clone();
          
          // Cache successful responses
          if (response.ok) {
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
          }
          
          return response;
        })
        .catch(() => {
          // Fallback to cache if network fails
          return caches.match(event.request)
            .then(response => {
              // If no cache, return fallback page
              return response || caches.match('/');
            });
        })
    );
    return;
  }
  
  // For other assets (CSS, JS, images), try cache first, then network
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        return response || fetch(event.request).then(response => {
          // Check if we received a valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response to put in cache
          const responseToCache = response.clone();

          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });

          return response;
        });
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];

  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});