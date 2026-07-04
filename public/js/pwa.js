(function () {
  if (!("serviceWorker" in navigator)) {
    return;
  }

  var i18n = window.PWA_I18N || {};
  var updateTitle = i18n.updateTitle || "Update available";
  var updateBody = i18n.updateBody || "A new version of CampusMarket is ready.";
  var refreshLabel = i18n.refresh || "Refresh now";

  var registrationRef = null;
  var updateBannerEl = null;
  var reloadPending = false;

  function shouldOfferRefresh() {
    var active = document.activeElement;
    if (!active) {
      return true;
    }
    var tag = (active.tagName || "").toLowerCase();
    if (tag === "input" || tag === "textarea" || tag === "select") {
      return false;
    }
    if (active.isContentEditable) {
      return false;
    }
    return true;
  }

  function reloadForUpdate() {
    reloadPending = true;
    var waiting = registrationRef && registrationRef.waiting;
    if (waiting) {
      waiting.postMessage({ type: "SKIP_WAITING" });
      return;
    }
    window.location.reload();
  }

  function syncBannerStack() {
    if (!updateBannerEl) {
      return;
    }
    var hasOtherToast = !!document.querySelector(".flash-toast-container:not(#cm-push-prompt):not([hidden])");
    updateBannerEl.classList.toggle("pwa-update-banner--stacked", hasOtherToast);
  }

  function showUpdateBanner() {
    if (updateBannerEl || !shouldOfferRefresh()) {
      return;
    }

    updateBannerEl = document.createElement("div");
    updateBannerEl.className = "pwa-update-banner";
    updateBannerEl.setAttribute("role", "status");
    updateBannerEl.innerHTML =
      '<div class="pwa-update-banner__content">' +
        '<p class="pwa-update-banner__title">' + updateTitle + "</p>" +
        '<p class="pwa-update-banner__body">' + updateBody + "</p>" +
      "</div>" +
      '<button type="button" class="pwa-update-banner__btn">' + refreshLabel + "</button>";

    updateBannerEl.querySelector(".pwa-update-banner__btn").addEventListener("click", reloadForUpdate);
    document.body.appendChild(updateBannerEl);
    syncBannerStack();
  }

  function handleWaitingWorker() {
    showUpdateBanner();
  }

  function watchForUpdates(registration) {
    if (!registration) {
      return;
    }

    registration.addEventListener("updatefound", function () {
      var newWorker = registration.installing;
      if (!newWorker) {
        return;
      }

      newWorker.addEventListener("statechange", function () {
        if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
          handleWaitingWorker();
        }
      });
    });

    if (registration.waiting && navigator.serviceWorker.controller) {
      handleWaitingWorker();
    }
  }

  function checkForUpdates() {
    if (!registrationRef) {
      return;
    }
    registrationRef.update().catch(function () {
      // Ignore transient network errors during background update checks.
    });
  }

  navigator.serviceWorker.addEventListener("message", function (event) {
    if (!event.data || event.data.type !== "SW_UPDATED") {
      return;
    }
    showUpdateBanner();
  });

  navigator.serviceWorker.addEventListener("controllerchange", function () {
    if (!reloadPending) {
      return;
    }
    window.location.reload();
  });

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
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
        window.setInterval(checkForUpdates, 60 * 60 * 1000);
      })
      .catch(function (error) {
        console.error("Service worker registration failed:", error);
      });
  });
})();
