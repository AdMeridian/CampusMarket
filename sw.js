const CACHE_VERSION = "campusmarket-v24";
const DYNAMIC_CACHE = CACHE_VERSION + "-dynamic";

const OFFLINE_URL = "public/offline.html";

// Offline essentials
const CORE_ASSETS = [
  "manifest.webmanifest",
  "public/images/logo.png",
  OFFLINE_URL,
];

self.addEventListener("install", (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(CORE_ASSETS))
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("activate", (event) => {
  const allowedCaches = [CACHE_VERSION, DYNAMIC_CACHE];
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => !allowedCaches.includes(key))
            .map((key) => caches.delete(key))
        )
      )
      .then(() => caches.open(CACHE_VERSION))
      .then((cache) => cache.addAll(CORE_ASSETS))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  if (event.request.mode !== "navigate") {
    return;
  }

  const requestUrl = new URL(event.request.url);
  if (!/^https?:$/i.test(requestUrl.protocol)) {
    return;
  }

  // Network-First strategy for pages: try live network first, update dynamic cache; fallback to cache or offline.html on network failure.
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200 && response.type === "basic") {
          const responseToCache = response.clone();
          caches.open(DYNAMIC_CACHE).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          return caches.match(OFFLINE_URL);
        });
      })
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = (event.notification && event.notification.data && event.notification.data.url) || "/";
  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ("focus" in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});

self.addEventListener("push", (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (_) {
    payload = { body: event.data ? event.data.text() : "" };
  }

  const title = payload.title || "CampusMarket";
  const options = {
    body: payload.body || "You have a new update.",
    icon: payload.icon || "public/images/logo.png",
    badge: payload.badge || "public/images/logo.png",
    data: {
      url: payload.url || "/",
    },
  };

  event.waitUntil(
    self.registration.showNotification(title, options).then(() =>
      clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {
        clientList.forEach((client) => {
          if (client && typeof client.postMessage === "function") {
            client.postMessage({ type: "COUNTS_REFRESH" });
          }
        });
      })
    )
  );
});
