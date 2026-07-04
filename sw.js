const CACHE_VERSION = "campusmarket-v21";

const OFFLINE_URL = "public/offline.html";



// Precache only offline essentials. Versioned CSS/JS are fetched network-first.

const CORE_ASSETS = [

  "manifest.webmanifest",

  "public/images/logo.png",

  OFFLINE_URL,

];



self.addEventListener("install", (event) => {

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

  event.waitUntil(

    caches.keys().then((keys) =>

      Promise.all(

        keys

          .filter((key) => key !== CACHE_VERSION)

          .map((key) => caches.delete(key))

      )

    ).then(() => self.clients.claim()).then(() =>

      self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {

        clientList.forEach((client) => {

          if (client && typeof client.postMessage === "function") {

            client.postMessage({ type: "SW_UPDATED", version: CACHE_VERSION });

          }

        });

      })

    )

  );

});



self.addEventListener("fetch", (event) => {

  if (event.request.method !== "GET") {

    return;

  }



  const requestUrl = new URL(event.request.url);



  if (!/^https?:$/i.test(requestUrl.protocol)) {

    return;

  }



  const isHtmlRequest = event.request.mode === "navigate";

  if (isHtmlRequest) {

    event.respondWith(

      fetch(event.request)

        .catch(() => caches.match(OFFLINE_URL))

    );

    return;

  }



  const isCssOrJs = /\.(css|js)(\?|$)/i.test(requestUrl.pathname + requestUrl.search);

  if (isCssOrJs) {

    event.respondWith(

      fetch(event.request)

        .then((networkResponse) => {

          if (networkResponse && networkResponse.status === 200 && (networkResponse.type === "basic" || networkResponse.type === "cors")) {

            const responseClone = networkResponse.clone();

            caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, responseClone));

          }

          return networkResponse;

        })

        .catch(() => caches.match(event.request))

    );

    return;

  }



  const isStaticAsset = /\.(png|jpg|jpeg|gif|svg|webp|webmanifest|woff2?|eot|ttf|otf)$/i.test(requestUrl.pathname);

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

