(function () {
  if (!("serviceWorker" in navigator)) {
    return;
  }

  var registrationRef = null;
  var controlledAtLoad = !!navigator.serviceWorker.controller;
  var isReloading = false;

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
