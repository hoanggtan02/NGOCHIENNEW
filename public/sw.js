const CACHE_NAME = "eclo-cache-v1";
const OFFLINE_URL = "/offline.html";

// =================== INSTALL ===================
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll([
        OFFLINE_URL,
        "/assets-src/512x512.png",
        "/assets-src/badge.png"
      ]);
    })
  );
  self.skipWaiting();
});

// =================== ACTIVATE ===================
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) return caches.delete(key);
        })
      )
    )
  );
  self.clients.claim();
});

// =================== FETCH ===================
self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1. API → Network First (không cache, chỉ fallback cache nếu có)
  if (url.pathname.startsWith("/api/")) {
    event.respondWith(
      fetch(req).catch(() => caches.match(req))
    );
    return;
  }

  // 2. CSS, JS, Images → Cache First
  if (req.destination === "style" || req.destination === "script" || req.destination === "image") {
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        if (cachedRes) return cachedRes;

        return fetch(req).then((res) => {
          if (res && res.ok) {
            const resToCache = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, resToCache));
          }
          return res;
        }).catch(() => cachedRes);
      })
    );
    return;
  }

  // 3. Fonts / Icons → Stale-While-Revalidate
  if (req.destination === "font" || req.destination === "icon") {
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        const fetchPromise = fetch(req).then((res) => {
          if (res && res.ok) {
            const resToCache = res.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, resToCache));
          }
          return res;
        }).catch(() => cachedRes);

        return cachedRes || fetchPromise;
      })
    );
    return;
  }

  // 4. HTML Pages (navigate) → luôn tải mới, offline thì fallback
  if (req.mode === "navigate") {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // 5. Default → network first, fallback cache nếu có
  event.respondWith(
    fetch(req).catch(() => caches.match(req))
  );
});

// =================== Push Notification ===================
self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    console.error("Push event data is not JSON:", e);
  }

  const title = data.title || "Thông báo";
  const options = {
    body: data.body || "",
    icon: data.icon || "/assets-src/512x512.png",
    badge: data.badge || "/assets-src/badge.png",
    data: {
      url: data.url || "/",
    },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = event.notification?.data?.url || "/";

  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(url) && "focus" in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
