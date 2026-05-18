(function () {
  if (!("serviceWorker" in navigator)) {
    return;
  }

  window.addEventListener("load", function () {
    navigator.serviceWorker
      .register(window.PWA_SW_URL || "/sw.js")
      .catch(function (error) {
        console.error("Service worker registration failed:", error);
      });
  });
})();
