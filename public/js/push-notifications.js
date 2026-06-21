/**
 * CampusMarket Web Push — subscribe users for system notifications (PWA / mobile).
 */
(function (global) {
  const STORAGE_DISMISS = 'cm_push_prompt_dismissed_until';

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function isIos() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !global.MSStream;
  }

  function isStandalonePwa() {
    return (
      global.navigator.standalone === true ||
      global.matchMedia('(display-mode: standalone)').matches
    );
  }

  function getVapidPublicKey() {
    return (global.__env && global.__env.WEB_PUSH_PUBLIC_KEY) || '';
  }

  function isPushSupported() {
    if (!('serviceWorker' in navigator) || !('PushManager' in global)) {
      return false;
    }
    if (isIos() && !isStandalonePwa()) {
      return false;
    }
    return !!getVapidPublicKey();
  }

  async function saveSubscription(subscription) {
    const res = await fetch((global.__baseUrl || '/') + 'pages/api_push_subscriptions.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': global.__csrfToken || '',
      },
      body: JSON.stringify({
        action: 'subscribe',
        subscription: subscription.toJSON ? subscription.toJSON() : subscription,
      }),
    });
    return res.ok;
  }

  async function hasActiveSubscription() {
    if (!isPushSupported()) {
      return false;
    }
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      return !!sub;
    } catch (_) {
      return false;
    }
  }

  function iosInstallMessage() {
    return (
      'To get notifications on iPhone/iPad:\n\n' +
      '1. Open this site in Safari\n' +
      '2. Tap Share → Add to Home Screen\n' +
      '3. Open CampusMarket from your home screen\n' +
      '4. Enable notifications when prompted'
    );
  }

  async function subscribe(options) {
    const silent = !!(options && options.silent);

    if (isIos() && !isStandalonePwa()) {
      if (!silent) {
        alert(iosInstallMessage());
      }
      return { ok: false, error: 'ios_requires_install' };
    }

    if (!isPushSupported()) {
      if (!silent) {
        alert('Push notifications are not supported on this device or browser.');
      }
      return { ok: false, error: 'unsupported' };
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      if (!silent) {
        alert('Notifications were blocked. You can enable them in your browser or device settings.');
      }
      return { ok: false, error: 'denied' };
    }

    const reg = await navigator.serviceWorker.ready;
    let subscription = await reg.pushManager.getSubscription();
    if (!subscription) {
      subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(getVapidPublicKey()),
      });
    }

    const saved = await saveSubscription(subscription);
    if (!saved) {
      return { ok: false, error: 'save_failed' };
    }

    hidePrompt();
    return { ok: true };
  }

  function dismissPrompt(days) {
    const ttl = typeof days === 'number' ? days : 14;
    global.localStorage.setItem(STORAGE_DISMISS, String(Date.now() + ttl * 86400000));
    hidePrompt();
  }

  function isPromptDismissed() {
    const until = Number(global.localStorage.getItem(STORAGE_DISMISS) || 0);
    return until > Date.now();
  }

  function hidePrompt() {
    const el = document.getElementById('cm-push-prompt');
    if (el) {
      el.remove();
    }
  }

  function showPrompt(force) {
    if (!isPushSupported()) {
      return;
    }
    if (Notification.permission === 'granted') {
      return;
    }
    if (!force && isPromptDismissed()) {
      return;
    }
    if (Notification.permission === 'denied' && !force) {
      return;
    }

    if (document.getElementById('cm-push-prompt')) {
      return;
    }

    const banner = document.createElement('div');
    banner.id = 'cm-push-prompt';
    banner.className = 'cm-push-prompt';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Enable notifications');

    const isStandalone = isStandalonePwa();
    const title = isStandalone ? 'Turn on notifications' : 'Get notified on your phone';
    const body = isStandalone
      ? 'Receive alerts for new messages, orders, and marketplace activity — even when the app is closed.'
      : 'Install CampusMarket or enable notifications to get message and order alerts on your device.';

    banner.innerHTML =
      '<div class="cm-push-prompt__content">' +
        '<div class="cm-push-prompt__icon" aria-hidden="true">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>' +
        '</div>' +
        '<div class="cm-push-prompt__text">' +
          '<strong>' + title + '</strong>' +
          '<p>' + body + '</p>' +
        '</div>' +
        '<div class="cm-push-prompt__actions">' +
          '<button type="button" class="btn btn-primary btn-sm cm-push-prompt__enable">Enable</button>' +
          '<button type="button" class="btn btn-secondary btn-sm cm-push-prompt__dismiss">Not now</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(banner);

    banner.querySelector('.cm-push-prompt__enable').addEventListener('click', async function () {
      const btn = this;
      btn.disabled = true;
      btn.textContent = 'Enabling…';
      const result = await subscribe();
      btn.disabled = false;
      btn.textContent = 'Enable';
      if (result.ok) {
        hidePrompt();
      }
    });

    banner.querySelector('.cm-push-prompt__dismiss').addEventListener('click', function () {
      dismissPrompt(14);
    });
  }

  async function maybeShowPrompt(force) {
    if (!global.__isLoggedIn) {
      return;
    }
    if (await hasActiveSubscription()) {
      hidePrompt();
      return;
    }
    showPrompt(!!force);
  }

  global.CampusMarketPush = {
    isPushSupported,
    isStandalonePwa,
    subscribe,
    hasActiveSubscription,
    maybeShowPrompt,
    dismissPrompt,
    hidePrompt,
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (!global.__isLoggedIn || !isPushSupported()) {
      return;
    }

    hasActiveSubscription().then(function (subscribed) {
      if (subscribed) {
        return;
      }
      if (global.__promptPush) {
        maybeShowPrompt(true);
        return;
      }
      if (isStandalonePwa()) {
        setTimeout(function () {
          maybeShowPrompt(false);
        }, 2000);
      }
    });
  });
})(window);
