/**
 * Product listing share — native Web Share API with social/copy fallbacks.
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
    }, 2400);
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

  function openWindow(url) {
    global.open(url, '_blank', 'noopener,noreferrer,width=600,height=520');
  }

  function buildMenu(btn, data, labels) {
    var menu = document.getElementById('cm-share-menu');
    if (!menu) {
      menu = document.createElement('div');
      menu.id = 'cm-share-menu';
      menu.className = 'cm-share-menu';
      menu.setAttribute('role', 'menu');
      document.body.appendChild(menu);
    }

    var encodedUrl = encodeURIComponent(data.url);
    var encodedText = encodeURIComponent(data.text + ' ' + data.url);

    var items = [
      {
        key: 'copy',
        label: labels.copy,
        action: function () {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(data.url).then(function () {
              showToast(labels.copied);
            });
          } else {
            var input = document.createElement('input');
            input.value = data.url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
            showToast(labels.copied);
          }
          recordShare(data.productId, 'copy');
          closeMenu();
        },
      },
      {
        key: 'whatsapp',
        label: labels.whatsapp,
        action: function () {
          openWindow('https://wa.me/?text=' + encodedText);
          recordShare(data.productId, 'whatsapp');
          closeMenu();
        },
      },
      {
        key: 'telegram',
        label: labels.telegram,
        action: function () {
          openWindow(
            'https://t.me/share/url?url=' + encodedUrl + '&text=' + encodeURIComponent(data.text)
          );
          recordShare(data.productId, 'telegram');
          closeMenu();
        },
      },
      {
        key: 'twitter',
        label: labels.twitter,
        action: function () {
          openWindow(
            'https://twitter.com/intent/tweet?text=' +
              encodeURIComponent(data.text) +
              '&url=' +
              encodedUrl
          );
          recordShare(data.productId, 'twitter');
          closeMenu();
        },
      },
      {
        key: 'facebook',
        label: labels.facebook,
        action: function () {
          openWindow('https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl);
          recordShare(data.productId, 'facebook');
          closeMenu();
        },
      },
    ];

    menu.innerHTML =
      '<div class="cm-share-menu__panel" role="presentation">' +
      '<div class="cm-share-menu__head">' +
      '<strong>' +
      labels.title +
      '</strong>' +
      '<button type="button" class="cm-share-menu__close" aria-label="' +
      labels.close +
      '">&times;</button>' +
      '</div>' +
      '<div class="cm-share-menu__list"></div>' +
      '</div>';

    var list = menu.querySelector('.cm-share-menu__list');
    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'cm-share-menu__item';
      button.setAttribute('role', 'menuitem');
      button.textContent = item.label;
      button.addEventListener('click', item.action);
      list.appendChild(button);
    });

    menu.querySelector('.cm-share-menu__close').addEventListener('click', closeMenu);
    menu.classList.add('is-open');
    menu._anchor = btn;
    positionMenu(menu, btn);
  }

  function positionMenu(menu, btn) {
    var rect = btn.getBoundingClientRect();
    var panel = menu.querySelector('.cm-share-menu__panel');
    var top = rect.bottom + 8;
    var left = Math.min(rect.left, global.innerWidth - 280);
    if (top + 260 > global.innerHeight) {
      top = Math.max(12, rect.top - 260);
    }
    panel.style.top = top + 'px';
    panel.style.left = Math.max(12, left) + 'px';
  }

  function closeMenu() {
    var menu = document.getElementById('cm-share-menu');
    if (menu) {
      menu.classList.remove('is-open');
    }
  }

  document.addEventListener('click', function (e) {
    var menu = document.getElementById('cm-share-menu');
    if (!menu || !menu.classList.contains('is-open')) {
      return;
    }
    if (menu.contains(e.target) || (menu._anchor && menu._anchor.contains(e.target))) {
      return;
    }
    closeMenu();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMenu();
    }
  });

  global.addEventListener('resize', function () {
    var menu = document.getElementById('cm-share-menu');
    if (menu && menu.classList.contains('is-open') && menu._anchor) {
      positionMenu(menu, menu._anchor);
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var btn = qs('#product-share-btn');
    if (!btn) {
      return;
    }

    var labels = global.__productShareI18n || {};
    var data = {
      productId: btn.getAttribute('data-product-id'),
      title: btn.getAttribute('data-title') || 'CampusMarket',
      text: btn.getAttribute('data-text') || '',
      url: btn.getAttribute('data-url') || global.location.href,
    };

    btn.addEventListener('click', async function () {
      if (global.navigator && typeof global.navigator.share === 'function') {
        try {
          await global.navigator.share({
            title: data.title,
            text: data.text,
            url: data.url,
          });
          recordShare(data.productId, 'native');
          return;
        } catch (err) {
          if (err && err.name === 'AbortError') {
            return;
          }
        }
      }
      openMenu(btn, data, labels);
    });
  });

  function openMenu(btn, data, labels) {
    buildMenu(btn, data, labels);
  }
})(window);
