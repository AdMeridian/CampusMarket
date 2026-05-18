const CACHE_VERSION = "campusmarket-v2";
const OFFLINE_URL = "public/offline.html";

const CORE_ASSETS = [
  "./",
  "index.php",
  "manifest.webmanifest",
  "public/css/style.css",
  "public/js/theme.js",
  "public/js/pwa.js",
  "public/images/logo.png",
  OFFLINE_URL
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(CORE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_VERSION)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  const requestUrl = new URL(event.request.url);
  if (!requestUrl.pathname.startsWith("/")) {
    return;
  }

  const isHtmlRequest = event.request.mode === "navigate";

  if (isHtmlRequest) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const responseClone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, responseClone));
          return response;
        })
        .catch(() => caches.match(event.request).then((cached) => cached || caches.match(OFFLINE_URL)))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(event.request).then((response) => {
        if (response && response.status === 200 && response.type === "basic") {
          const responseClone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, responseClone));
        }
        return response;
      });
    })
  );
});
