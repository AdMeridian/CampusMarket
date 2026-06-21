const CACHE_VERSION = "campusmarket-v7";
const OFFLINE_URL = "public/offline.html";

const CORE_ASSETS = [
  "manifest.webmanifest",
  "public/css/style.css",
  "public/js/theme.js",
  "public/js/pwa.js",
  "public/js/push-notifications.js",
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

  // Ignore unsupported schemes from browser extensions and other non-HTTP requests.
  if (!/^https?:$/i.test(requestUrl.protocol)) {
    return;
  }
  
  // HTML / Navigation requests: always fetch from network, fallback to offline.html
  const isHtmlRequest = event.request.mode === "navigate";
  if (isHtmlRequest) {
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Only cache static assets (styles, scripts, images, manifests, fonts)
  const isStaticAsset = /\.(css|js|png|jpg|jpeg|gif|svg|webp|webmanifest|woff2?|eot|ttf|otf)$/i.test(requestUrl.pathname);
  if (!isStaticAsset) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      const fetchPromise = fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && (networkResponse.type === "basic" || networkResponse.type === "cors")) {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, responseClone));
        }
        return networkResponse;
      }).catch((err) => {
        console.error("SW Fetch error:", err);
      });

      return cached || fetchPromise;
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
      url: payload.url || "/"
    }
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
