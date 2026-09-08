(function () {
  if (!("serviceWorker" in navigator)) {
    return;
  }

  var registrationRef = null;
  var controlledAtLoad = !!navigator.serviceWorker.controller;
  var isReloading = false;
  var installIdKey = "campusmarket_pwa_installation_id";
  var heartbeatKey = "campusmarket_pwa_last_heartbeat";

  function getInstallationId() {
    try {
      var existing = window.localStorage.getItem(installIdKey);
      if (existing) {
        return existing;
      }
      var id = window.crypto && typeof window.crypto.randomUUID === "function"
        ? window.crypto.randomUUID()
        : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            var value = character === "x" ? random : (random & 0x3 | 0x8);
            return value.toString(16);
          });
      window.localStorage.setItem(installIdKey, id);
      return id;
    } catch (_) {
      return null;
    }
  }

  function isStandaloneMode() {
    try {
      if (window.matchMedia) {
        if (
          window.matchMedia("(display-mode: standalone)").matches ||
          window.matchMedia("(display-mode: fullscreen)").matches ||
          window.matchMedia("(display-mode: minimal-ui)").matches
        ) {
          return true;
        }
      }
      if (window.navigator && window.navigator.standalone === true) {
        return true;
      }
      if (typeof document.referrer === "string" && document.referrer.indexOf("android-app://") === 0) {
        return true;
      }
    } catch (_) {}
    return false;
  }

  function recordInstallSignal(eventName) {
    var installationId = getInstallationId();
    if (!installationId) {
      return;
    }

    var standalone = isStandaloneMode();
    if (eventName === "heartbeat" && !standalone) {
      return;
    }

    if (eventName === "heartbeat") {
      var lastHeartbeat = Number(window.localStorage.getItem(heartbeatKey) || 0);
      if (Date.now() - lastHeartbeat < 24 * 60 * 60 * 1000) {
        return;
      }
    }

    var body = JSON.stringify({
      installation_id: installationId,
      event: eventName,
      standalone: standalone,
      display_mode: standalone ? "standalone" : "browser",
      platform: window.navigator.userAgentData && window.navigator.userAgentData.platform
        ? window.navigator.userAgentData.platform
        : window.navigator.platform || "unknown"
    });
    var endpoint = (window.__baseUrl || "/") + "pages/api_pwa_install.php";
    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: body,
      credentials: "same-origin",
      keepalive: true
    }).then(function (res) {
      if (res && res.ok && eventName === "heartbeat") {
        try {
          window.localStorage.setItem(heartbeatKey, String(Date.now()));
        } catch (_) {}
      }
    }).catch(function () {
      // Telemetry must never interfere with normal page use.
    });
  }

  function activateWaitingWorker(worker) {
    if (!worker) {
      return;
    }
    worker.postMessage({ type: "SKIP_WAITING" });
  }

  function applyPendingUpdate(registration) {
    if (!registration || !navigator.serviceWorker.controller) {
      return;
    }
    if (registration.waiting) {
      activateWaitingWorker(registration.waiting);
    }
  }

  function checkForUpdates() {
    if (!registrationRef) {
      return;
    }
    registrationRef
      .update()
      .then(function () {
        applyPendingUpdate(registrationRef);
      })
      .catch(function () {
        // Ignore transient network errors during background update checks.
      });
  }

  function watchForUpdates(registration) {
    registration.addEventListener("updatefound", function () {
      var newWorker = registration.installing;
      if (!newWorker) {
        return;
      }

      newWorker.addEventListener("statechange", function () {
        if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
          activateWaitingWorker(newWorker);
        }
      });
    });

    applyPendingUpdate(registration);
  }

  navigator.serviceWorker.addEventListener("controllerchange", function () {
    if (!controlledAtLoad || isReloading) {
      return;
    }
    isReloading = true;
    window.location.reload();
  });

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
      checkForUpdates();
    }
  });

  window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
      checkForUpdates();
    }
  });

  window.addEventListener("load", function () {
    recordInstallSignal("heartbeat");
    window.addEventListener("appinstalled", function () {
      recordInstallSignal("install");
    }, { once: true });
    navigator.serviceWorker
      .register(window.PWA_SW_URL || "/sw.js")
      .then(function (registration) {
        registrationRef = registration;
        watchForUpdates(registration);
        checkForUpdates();
        window.setInterval(checkForUpdates, 5 * 60 * 1000);
      })
      .catch(function (error) {
        console.error("Service worker registration failed:", error);
      });
  });
})();
