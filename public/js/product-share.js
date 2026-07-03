/**
 * Product listing share — Stories/Status via Web Share API (with image),
 * plus direct-message and copy-link fallbacks.
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

  function openWindow(url) {
    global.open(url, '_blank', 'noopener,noreferrer,width=600,height=520');
  }

  function copyText(text, labels) {
    if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
      return global.navigator.clipboard.writeText(text).then(function () {
        showToast(labels.copied);
      });
    }
    var input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    showToast(labels.copied);
    return Promise.resolve();
  }

  function safeFileName(title) {
    var base = String(title || 'campusmarket-listing')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
    return (base || 'campusmarket-listing') + '.jpg';
  }

  function fetchImageAsFile(imageUrl, title) {
    if (!imageUrl) {
      return Promise.resolve(null);
    }
    return fetch(imageUrl, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('image_fetch_failed');
        }
        return res.blob();
      })
      .then(function (blob) {
        if (!blob || !blob.type || blob.type.indexOf('image/') !== 0) {
          return null;
        }
        return new File([blob], safeFileName(title), { type: blob.type });
      })
      .catch(function () {
        return null;
      });
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

  function buildShareText(data) {
    return data.text + '\n' + data.url;
  }

  function shareToStories(data, channel, labels) {
    var shareText = buildShareText(data);

    return fetchImageAsFile(data.imageUrl, data.title).then(function (file) {
      var payload = {
        title: data.title,
        text: shareText,
        url: data.url,
      };
      if (file) {
        payload.files = [file];
      }

      if (canSharePayload(payload)) {
        return global.navigator
          .share(payload)
          .then(function () {
            recordShare(data.productId, channel);
            closeMenu();
          })
          .catch(function (err) {
            if (err && err.name === 'AbortError') {
              return;
            }
            return copyText(data.url, labels).then(function () {
              showToast(labels.storyDesktopHint);
              recordShare(data.productId, channel);
              closeMenu();
            });
          });
      }

      return copyText(data.url, labels).then(function () {
        showToast(labels.storyDesktopHint);
        recordShare(data.productId, channel);
        closeMenu();
      });
    });
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
    var encodedText = encodeURIComponent(buildShareText(data));

    var storyItems = [
      {
        key: 'instagram_story',
        label: labels.instagramStory,
        action: function () {
          shareToStories(data, 'instagram_story', labels);
        },
      },
      {
        key: 'whatsapp_status',
        label: labels.whatsappStatus,
        action: function () {
          shareToStories(data, 'whatsapp_status', labels);
        },
      },
      {
        key: 'facebook_story',
        label: labels.facebookStory,
        action: function () {
          shareToStories(data, 'facebook_story', labels);
        },
      },
    ];

    var messageItems = [
      {
        key: 'whatsapp',
        label: labels.whatsappMessage,
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

    var otherItems = [
      {
        key: 'copy',
        label: labels.copy,
        action: function () {
          copyText(data.url, labels).then(function () {
            recordShare(data.productId, 'copy');
            closeMenu();
          });
        },
      },
    ];

    if (canSharePayload({ title: data.title, text: buildShareText(data), url: data.url })) {
      otherItems.unshift({
        key: 'native',
        label: labels.moreOptions,
        action: function () {
          shareToStories(data, 'native', labels);
        },
      });
    }

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
      '<div class="cm-share-menu__body"></div>' +
      '</div>';

    var body = menu.querySelector('.cm-share-menu__body');
    appendSection(body, labels.sectionStories, storyItems, labels.storySectionHint);
    appendSection(body, labels.sectionMessages, messageItems);
    appendSection(body, labels.sectionOther, otherItems);

    menu.querySelector('.cm-share-menu__close').addEventListener('click', closeMenu);
    menu.classList.add('is-open');
    menu._anchor = btn;
    positionMenu(menu, btn);
  }

  function appendSection(container, title, items, hint) {
    if (!items.length) {
      return;
    }
    var section = document.createElement('div');
    section.className = 'cm-share-menu__section';
    section.innerHTML =
      '<div class="cm-share-menu__section-title">' + title + '</div>' +
      (hint ? '<p class="cm-share-menu__section-hint">' + hint + '</p>' : '') +
      '<div class="cm-share-menu__list"></div>';
    var list = section.querySelector('.cm-share-menu__list');
    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'cm-share-menu__item';
      button.setAttribute('role', 'menuitem');
      button.textContent = item.label;
      button.addEventListener('click', item.action);
      list.appendChild(button);
    });
    container.appendChild(section);
  }

  function positionMenu(menu, btn) {
    var rect = btn.getBoundingClientRect();
    var panel = menu.querySelector('.cm-share-menu__panel');
    var top = rect.bottom + 8;
    var left = Math.min(rect.left, global.innerWidth - 300);
    if (top + 420 > global.innerHeight) {
      top = Math.max(12, rect.top - 420);
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
      imageUrl: btn.getAttribute('data-image-url') || '',
    };

    btn.addEventListener('click', function () {
      buildMenu(btn, data, labels);
    });
  });
})(window);
