/**
 * Product listing share — native phone share sheet first,
 * copy-link fallback when Web Share is unavailable.
 */
(function (global) {
  function qs(sel) {
    return document.querySelector(sel);
  }

  function showToast(message) {
    var existing = document.getElementById('cm-share-toast');
    if (existing) {
      existing.remove();
    }
    var toast = document.createElement('div');
    toast.id = 'cm-share-toast';
    toast.className = 'cm-share-toast';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add('is-visible');
    });
    setTimeout(function () {
      toast.classList.remove('is-visible');
      setTimeout(function () {
        toast.remove();
      }, 300);
    }, 3200);
  }

  function recordShare(productId, channel) {
    var form = new FormData();
    form.append('product_id', String(productId));
    form.append('channel', channel);
    if (global.__csrfToken) {
      form.append('csrf_token', global.__csrfToken);
    }

    var base = (global.__baseUrl || '/').replace(/\/?$/, '/');
    fetch(base + 'pages/api_product_share.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
    }).catch(function () {});

    if (typeof global.posthog !== 'undefined') {
      global.posthog.capture('listing_shared', {
        listing_id: Number(productId),
        channel: channel,
      });
    }
  }

  function copyText(text, copiedLabel) {
    if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
      return global.navigator.clipboard.writeText(text).then(function () {
        showToast(copiedLabel);
      });
    }
    var input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    showToast(copiedLabel);
    return Promise.resolve();
  }

  function canSharePayload(payload) {
    if (!global.navigator || typeof global.navigator.share !== 'function') {
      return false;
    }
    if (typeof global.navigator.canShare === 'function') {
      try {
        return global.navigator.canShare(payload);
      } catch (err) {
        return false;
      }
    }
    return true;
  }

  function shareNative(data, channel) {
    var payload = {
      title: data.title,
      text: data.text,
      url: data.url,
    };
    if (!canSharePayload(payload)) {
      return Promise.resolve(false);
    }
    return global.navigator
      .share(payload)
      .then(function () {
        recordShare(data.productId, channel || 'native');
        return true;
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return true;
        }
        return false;
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = qs('#product-share-btn');
    if (!btn) {
      return;
    }

    var data = {
      productId: btn.getAttribute('data-product-id'),
      title: btn.getAttribute('data-title') || 'CampusMarket',
      text: btn.getAttribute('data-text') || '',
      url: btn.getAttribute('data-url') || global.location.href,
    };
    var copiedLabel = btn.getAttribute('data-copied-label') || 'Link copied';

    btn.addEventListener('click', function () {
      shareNative(data, 'native').then(function (didHandle) {
        if (!didHandle) {
          copyText(data.url, copiedLabel).then(function () {
            recordShare(data.productId, 'copy');
          });
        }
      });
    });
  });
})(window);
