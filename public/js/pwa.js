(function () {
  var registrationRef = null;
  var controlledAtLoad = !!(navigator.serviceWorker && navigator.serviceWorker.controller);
  var isReloading = false;
  var installIdKey = "campusmarket_pwa_installation_id";
  var heartbeatKey = "campusmarket_pwa_last_heartbeat";
  var pillDismissedKey = "campusmarket_pwa_pill_dismissed_at";
  var installedKey = "campusmarket_pwa_is_installed";
  var PILL_COOLDOWN_MS = 14 * 24 * 60 * 60 * 1000; // 14 days

  var deferredInstallPrompt = null;

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

  function isAppInstalled() {
    if (isStandaloneMode()) {
      try {
        window.localStorage.setItem(installedKey, "1");
      } catch (_) {}
      return true;
    }
    try {
      return window.localStorage.getItem(installedKey) === "1";
    } catch (_) {
      return false;
    }
  }

  function markAppInstalled() {
    try {
      window.localStorage.setItem(installedKey, "1");
    } catch (_) {}
    dismissPill();
    hideMenuInstallButtons();
  }

  function isIosSafari() {
    var ua = window.navigator.userAgent || "";
    var isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var isWebKit = /WebKit/i.test(ua);
    var isOtherBrowser = /CriOS|FxiOS|OPiOS|mercury/i.test(ua);
    return isIos && isWebKit && !isOtherBrowser;
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

  // ─── PWA Install Prompt & UI Flow ──────────────────────

  function showMenuInstallButtons() {
    if (isAppInstalled()) {
      hideMenuInstallButtons();
      return;
    }
    document.querySelectorAll(".cm-pwa-install-btn").forEach(function (btn) {
      btn.style.display = "";
    });
  }

  function hideMenuInstallButtons() {
    document.querySelectorAll(".cm-pwa-install-btn").forEach(function (btn) {
      btn.style.display = "none";
    });
  }

  function isPillCooldownActive() {
    try {
      var last = window.localStorage.getItem(pillDismissedKey);
      if (!last) return false;
      return Date.now() - parseInt(last, 10) < PILL_COOLDOWN_MS;
    } catch (_) {
      return false;
    }
  }

  function dismissPill() {
    try {
      window.localStorage.setItem(pillDismissedKey, Date.now().toString());
    } catch (_) {}
    var pill = document.getElementById("cm-pwa-floating-pill");
    if (pill) {
      pill.classList.remove("is-visible");
      setTimeout(function () {
        pill.remove();
      }, 300);
    }
  }

  function showFloatingPill() {
    if (isAppInstalled() || isPillCooldownActive()) {
      return;
    }

    var path = window.location.pathname.toLowerCase();
    if (
      path.includes("/admin/") ||
      path.includes("messages.php") ||
      path.includes("login.php") ||
      path.includes("register.php") ||
      path.includes("reset_password.php")
    ) {
      return;
    }

    if (document.getElementById("cm-pwa-floating-pill")) {
      return;
    }

    var i18n = window.__pwaI18n || {};
    var title = i18n.installTitle || "Install CampusMarket";
    var desc = i18n.installDesc || "Faster browsing and instant alerts on your device.";
    var btnText = i18n.installBtn || "Install";

    var pill = document.createElement("div");
    pill.id = "cm-pwa-floating-pill";
    pill.className = "cm-pwa-floating-pill";
    pill.setAttribute("role", "alert");

    pill.innerHTML = `
      <div class="cm-pwa-pill-media">
        <div class="cm-pwa-pill-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        </div>
        <div class="cm-pwa-pill-text">
          <span class="cm-pwa-pill-title">${escapeHtml(title)}</span>
          <span class="cm-pwa-pill-desc">${escapeHtml(desc)}</span>
        </div>
      </div>
      <div class="cm-pwa-pill-actions">
        <button type="button" class="btn btn-primary btn-sm cm-pwa-pill-install-btn" id="cm-pwa-pill-action">${escapeHtml(btnText)}</button>
        <button type="button" class="cm-pwa-pill-close-btn" id="cm-pwa-pill-close" aria-label="Dismiss">&times;</button>
      </div>
    `;

    document.body.appendChild(pill);

    pill.querySelector("#cm-pwa-pill-action").addEventListener("click", function () {
      triggerPwaInstall();
      dismissPill();
    });

    pill.querySelector("#cm-pwa-pill-close").addEventListener("click", dismissPill);

    setTimeout(function () {
      pill.classList.add("is-visible");
    }, 100);
  }

  function showIosInstructions() {
    var existing = document.getElementById("cm-pwa-ios-modal");
    if (existing) {
      existing.classList.add("is-open");
      return;
    }

    var i18n = window.__pwaI18n || {};
    var title = i18n.iosTitle || "Install on iPhone / iPad";
    var step1 = i18n.iosStep1 || "Tap the Share button in Safari toolbar";
    var step2 = i18n.iosStep2 || "Scroll down and tap 'Add to Home Screen'";
    var gotIt = i18n.iosGotIt || "Got it";

    var backdrop = document.createElement("div");
    backdrop.id = "cm-pwa-ios-modal";
    backdrop.className = "cm-pwa-ios-backdrop";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");

    backdrop.innerHTML = `
      <div class="cm-pwa-ios-card">
        <div class="cm-pwa-ios-header">
          <h3>${escapeHtml(title)}</h3>
          <button type="button" class="cm-pwa-pill-close-btn" id="cm-pwa-ios-close">&times;</button>
        </div>
        <div class="cm-pwa-ios-steps">
          <div class="cm-pwa-ios-step">
            <div class="cm-pwa-ios-step-num">1</div>
            <div class="cm-pwa-ios-step-text">${escapeHtml(step1)} (<strong>⎋ Share</strong>)</div>
          </div>
          <div class="cm-pwa-ios-step">
            <div class="cm-pwa-ios-step-num">2</div>
            <div class="cm-pwa-ios-step-text">${escapeHtml(step2)} (<strong>⊞ Add to Home Screen</strong>)</div>
          </div>
        </div>
        <button type="button" class="btn btn-primary w-full" id="cm-pwa-ios-confirm" style="border-radius: var(--radius-lg); padding: 0.75rem; font-weight: 700;">${escapeHtml(gotIt)}</button>
      </div>
    `;

    document.body.appendChild(backdrop);

    function closeIosModal() {
      backdrop.classList.remove("is-open");
    }

    backdrop.querySelector("#cm-pwa-ios-close").addEventListener("click", closeIosModal);
    backdrop.querySelector("#cm-pwa-ios-confirm").addEventListener("click", closeIosModal);
    backdrop.addEventListener("click", function (e) {
      if (e.target === backdrop) closeIosModal();
    });

    requestAnimationFrame(function () {
      backdrop.classList.add("is-open");
    });
  }

  function showDesktopInstructions() {
    var existing = document.getElementById("cm-pwa-desktop-modal");
    if (existing) {
      existing.classList.add("is-open");
      return;
    }

    var i18n = window.__pwaI18n || {};
    var title = i18n.desktopTitle || "Install CampusMarket on Desktop";
    var step1 = i18n.desktopStep1 || "Look at the right side of your browser's address bar";
    var step2 = i18n.desktopStep2 || "Click the Install icon (⊕) or browser menu (⋮) → 'Install CampusMarket'";
    var gotIt = i18n.iosGotIt || "Got it";

    var backdrop = document.createElement("div");
    backdrop.id = "cm-pwa-desktop-modal";
    backdrop.className = "cm-pwa-ios-backdrop";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");

    backdrop.innerHTML = `
      <div class="cm-pwa-ios-card" style="align-self: center; margin-bottom: 0;">
        <div class="cm-pwa-ios-header">
          <h3>${escapeHtml(title)}</h3>
          <button type="button" class="cm-pwa-pill-close-btn" id="cm-pwa-desktop-close">&times;</button>
        </div>
        <div class="cm-pwa-ios-steps">
          <div class="cm-pwa-ios-step">
            <div class="cm-pwa-ios-step-num">1</div>
            <div class="cm-pwa-ios-step-text">${escapeHtml(step1)}</div>
          </div>
          <div class="cm-pwa-ios-step">
            <div class="cm-pwa-ios-step-num">2</div>
            <div class="cm-pwa-ios-step-text">${escapeHtml(step2)}</div>
          </div>
        </div>
        <button type="button" class="btn btn-primary w-full" id="cm-pwa-desktop-confirm" style="border-radius: var(--radius-lg); padding: 0.75rem; font-weight: 700;">${escapeHtml(gotIt)}</button>
      </div>
    `;

    document.body.appendChild(backdrop);

    function closeDesktopModal() {
      backdrop.classList.remove("is-open");
    }

    backdrop.querySelector("#cm-pwa-desktop-close").addEventListener("click", closeDesktopModal);
    backdrop.querySelector("#cm-pwa-desktop-confirm").addEventListener("click", closeDesktopModal);
    backdrop.addEventListener("click", function (e) {
      if (e.target === backdrop) closeDesktopModal();
    });

    requestAnimationFrame(function () {
      backdrop.classList.add("is-open");
    });
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str || "";
    return div.innerHTML;
  }

  window.triggerPwaInstall = function () {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then(function (choice) {
        if (choice.outcome === "accepted") {
          markAppInstalled();
        }
        deferredInstallPrompt = null;
      });
    } else if (isIosSafari()) {
      showIosInstructions();
    } else {
      showDesktopInstructions();
    }
  };

  // Capture install prompt event
  window.addEventListener("beforeinstallprompt", function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;
    try {
      window.localStorage.removeItem(installedKey);
    } catch (_) {}
    if (!isAppInstalled()) {
      showMenuInstallButtons();
      setTimeout(showFloatingPill, 6000);
    }
  });

  if (isIosSafari() && !isAppInstalled()) {
    showMenuInstallButtons();
    setTimeout(showFloatingPill, 6000);
  }

  // ─── Service Worker Lifecycle & Listeners ──────────────

  if ("serviceWorker" in navigator) {
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
        markAppInstalled();
      }, { once: true });

      if (isAppInstalled()) {
        hideMenuInstallButtons();
      }

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
  }
})();
