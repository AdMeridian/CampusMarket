/**
 * CampusMarket Web Push — subscribe users for system notifications (PWA / mobile).
 */
(function (global) {
  const STORAGE_DISMISS = 'cm_push_prompt_dismissed_until';
  const PROMPT_DELAY_MS = 1800;

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

  function labels() {
    return global.__pushI18n || {};
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
    if (!el) {
      return;
    }
    el.innerHTML = '';
    el.hidden = true;
    el.setAttribute('aria-hidden', 'true');
    el.classList.remove('cm-push-toast-container--stacked');
  }

  function hasWelcomeToast() {
    return !!document.querySelector('.flash-toast-container:not(#cm-push-prompt):not([hidden])');
  }

  function showPrompt() {
    if (!isPushSupported()) {
      return;
    }
    if (Notification.permission === 'granted') {
      return;
    }
    if (isPromptDismissed()) {
      return;
    }
    if (Notification.permission === 'denied') {
      return;
    }

    const toast = document.getElementById('cm-push-prompt');
    if (!toast) {
      return;
    }
    if (!toast.hidden && toast.innerHTML.trim() !== '') {
      return;
    }

    const i18n = labels();
    const isStandalone = isStandalonePwa();
    const title = isStandalone ? (i18n.titlePwa || 'Turn on notifications') : (i18n.titleBrowser || 'Get notified on this device');
    const body = isStandalone
      ? (i18n.bodyPwa || 'Alerts for messages, orders, and marketplace activity — even when the app is closed.')
      : (i18n.bodyBrowser || 'Enable notifications to get message and order alerts on this device.');

    toast.classList.toggle('cm-push-toast-container--stacked', hasWelcomeToast());
    toast.setAttribute('role', 'region');
    toast.setAttribute('aria-label', title);
    toast.removeAttribute('aria-hidden');

    toast.innerHTML =
      '<div class="flash flash-push">' +
        '<div class="cm-push-toast__message">' +
          '<svg style="width:20px;height:20px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>' +
          '</svg>' +
          '<div class="cm-push-toast__copy">' +
            '<strong>' + title + '</strong>' +
            '<span>' + body + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="cm-push-toast__actions">' +
          '<button type="button" class="btn btn-primary btn-sm cm-push-toast__enable">' + (i18n.enable || 'Enable') + '</button>' +
          '<button type="button" class="btn btn-secondary btn-sm cm-push-toast__dismiss">' + (i18n.notNow || 'Not now') + '</button>' +
        '</div>' +
      '</div>';

    toast.hidden = false;

    const enableBtn = toast.querySelector('.cm-push-toast__enable');
    const dismissBtn = toast.querySelector('.cm-push-toast__dismiss');

    enableBtn.addEventListener('click', async function () {
      enableBtn.disabled = true;
      enableBtn.textContent = i18n.enabling || 'Enabling…';
      const result = await subscribe();
      enableBtn.disabled = false;
      enableBtn.textContent = i18n.enable || 'Enable';
      if (result.ok) {
        hidePrompt();
      }
    });

    dismissBtn.addEventListener('click', function () {
      dismissPrompt(14);
    });
  }

  async function maybeShowPromptAfterLogin() {
    if (!global.__isLoggedIn) {
      return;
    }
    if (await hasActiveSubscription()) {
      hidePrompt();
      return;
    }
    showPrompt();
  }

  global.CampusMarketPush = {
    isPushSupported,
    isStandalonePwa,
    subscribe,
    hasActiveSubscription,
    maybeShowPrompt: maybeShowPromptAfterLogin,
    dismissPrompt,
    hidePrompt,
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (!global.__isLoggedIn || !isPushSupported()) {
      return;
    }

    if (!global.__promptPush) {
      return;
    }

    hasActiveSubscription().then(function (subscribed) {
      if (subscribed) {
        return;
      }
      setTimeout(maybeShowPromptAfterLogin, PROMPT_DELAY_MS);
    });
  });
})(window);
