(function () {
  var AUTO_MS = 4500;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function initCarousel(root) {
    var track = root.querySelector(".listing-carousel__track");
    var prevBtn = root.querySelector(".listing-carousel__nav--prev");
    var nextBtn = root.querySelector(".listing-carousel__nav--next");
    if (!track || !prevBtn || !nextBtn) {
      return;
    }

    var items = track.children;
    if (items.length < 2) {
      return;
    }

    prevBtn.hidden = false;
    nextBtn.hidden = false;

    var index = 0;
    var timer = null;
    var paused = false;

    function scrollToIndex(i, behavior) {
      var item = items[i];
      if (!item) {
        return;
      }
      var left = item.offsetLeft - track.offsetLeft;
      track.scrollTo({
        left: left,
        behavior: behavior || "smooth",
      });
    }

    function syncIndexFromScroll() {
      var scrollLeft = track.scrollLeft;
      var closest = 0;
      var closestDist = Infinity;
      for (var i = 0; i < items.length; i++) {
        var dist = Math.abs(items[i].offsetLeft - track.offsetLeft - scrollLeft);
        if (dist < closestDist) {
          closestDist = dist;
          closest = i;
        }
      }
      index = closest;
    }

    function next() {
      index = (index + 1) % items.length;
      scrollToIndex(index);
    }

    function prev() {
      index = (index - 1 + items.length) % items.length;
      scrollToIndex(index);
    }

    function stopAuto() {
      if (timer) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    function startAuto() {
      if (reducedMotion || paused || items.length < 2) {
        return;
      }
      stopAuto();
      timer = window.setInterval(next, AUTO_MS);
    }

    function pauseAuto() {
      paused = true;
      stopAuto();
    }

    function resumeAuto() {
      paused = false;
      startAuto();
    }

    nextBtn.addEventListener("click", function () {
      pauseAuto();
      next();
      window.setTimeout(resumeAuto, AUTO_MS * 2);
    });

    prevBtn.addEventListener("click", function () {
      pauseAuto();
      prev();
      window.setTimeout(resumeAuto, AUTO_MS * 2);
    });

    track.addEventListener(
      "scroll",
      function () {
        window.clearTimeout(track._carouselScrollTimer);
        track._carouselScrollTimer = window.setTimeout(syncIndexFromScroll, 80);
      },
      { passive: true }
    );

    root.addEventListener("mouseenter", pauseAuto);
    root.addEventListener("mouseleave", resumeAuto);
    root.addEventListener("focusin", pauseAuto);
    root.addEventListener("focusout", function (event) {
      if (!root.contains(event.relatedTarget)) {
        resumeAuto();
      }
    });
    track.addEventListener("touchstart", pauseAuto, { passive: true });
    track.addEventListener("touchend", function () {
      window.setTimeout(resumeAuto, AUTO_MS * 2);
    }, { passive: true });

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "hidden") {
        pauseAuto();
      } else {
        resumeAuto();
      }
    });

    scrollToIndex(0, "auto");
    startAuto();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-listing-carousel]").forEach(initCarousel);
  });
})();
