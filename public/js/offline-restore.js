/**
 * CampusMarket — Offline Resilience, Scroll Restoration & Form Draft Auto-Save
 */
(function () {
  'use strict';

  var SCROLL_PREFIX = 'cm_scroll_';
  var DRAFT_PREFIX = 'cm_draft_';
  var CURRENT_KEY = SCROLL_PREFIX + window.location.pathname + window.location.search;

  /* ==========================================================================
     1. SCROLL POSITION MEMORY & RESTORATION
     ========================================================================== */
  var scrollDebounceTimer = null;

  function saveScrollPosition() {
    if (scrollDebounceTimer) clearTimeout(scrollDebounceTimer);
    scrollDebounceTimer = setTimeout(function () {
      try {
        if (window.scrollY > 0) {
          sessionStorage.setItem(CURRENT_KEY, String(Math.round(window.scrollY)));
        } else {
          sessionStorage.removeItem(CURRENT_KEY);
        }
      } catch (e) {
        // Storage disabled or quota exceeded
      }
    }, 120);
  }

  function restoreScrollPosition() {
    try {
      var savedPos = sessionStorage.getItem(CURRENT_KEY);
      if (savedPos !== null && !isNaN(savedPos)) {
        var targetY = parseInt(savedPos, 10);
        if (targetY > 0) {
          // Delay briefly to allow initial DOM rendering/images to settle
          requestAnimationFrame(function () {
            window.scrollTo({ top: targetY, behavior: 'instant' });
          });
        }
      }
    } catch (e) {
      // Storage error fallback
    }
  }

  window.addEventListener('scroll', saveScrollPosition, { passive: true });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restoreScrollPosition);
  } else {
    restoreScrollPosition();
  }

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      restoreScrollPosition();
    }
  });

  /* ==========================================================================
     2. FORM DRAFT AUTO-SAVING & RESTORATION
     ========================================================================== */
  function getFormDraftKey(form, index) {
    var formId = form.getAttribute('id') || form.getAttribute('name') || ('form_' + index);
    return DRAFT_PREFIX + window.location.pathname + '_' + formId;
  }

  function saveFormDraft(form, key) {
    try {
      var formData = {};
      var elements = form.querySelectorAll('input:not([type="password"]):not([type="hidden"]):not([type="file"]):not([type="submit"]), textarea, select');
      var hasData = false;

      elements.forEach(function (el) {
        if (!el.name && !el.id) return;
        var fieldId = el.name || el.id;
        
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.checked) {
            formData[fieldId] = el.value;
            hasData = true;
          }
        } else if (el.value && el.value.trim().length > 0) {
          formData[fieldId] = el.value;
          hasData = true;
        }
      });

      if (hasData) {
        localStorage.setItem(key, JSON.stringify({
          timestamp: Date.now(),
          data: formData
        }));
      } else {
        localStorage.removeItem(key);
      }
    } catch (e) {
      // Ignore quota errors
    }
  }

  function restoreFormDraft(form, key) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return;

      var parsed = JSON.parse(raw);
      // Expire drafts older than 7 days
      if (!parsed || !parsed.data || (Date.now() - (parsed.timestamp || 0) > 7 * 86400000)) {
        localStorage.removeItem(key);
        return;
      }

      var data = parsed.data;
      var elements = form.querySelectorAll('input:not([type="password"]):not([type="hidden"]):not([type="file"]), textarea, select');
      
      elements.forEach(function (el) {
        var fieldId = el.name || el.id;
        if (!fieldId || !(fieldId in data)) return;

        var savedVal = data[fieldId];
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.value === savedVal) el.checked = true;
        } else if (!el.value || el.value.trim() === '') {
          el.value = savedVal;
        }
      });
    } catch (e) {
      // Parsing error
    }
  }

  function clearFormDraft(key) {
    try {
      localStorage.removeItem(key);
    } catch (e) {}
  }

  function initFormDrafts() {
    var forms = document.querySelectorAll('form');
    forms.forEach(function (form, index) {
      // Skip login, search, or payment forms if explicitly excluded
      if (form.classList.contains('no-draft') || form.getAttribute('data-no-draft') === 'true') return;

      var draftKey = getFormDraftKey(form, index);

      // Restore existing draft
      restoreFormDraft(form, draftKey);

      // Debounced auto-save on input
      var inputTimer = null;
      form.addEventListener('input', function () {
        if (inputTimer) clearTimeout(inputTimer);
        inputTimer = setTimeout(function () {
          saveFormDraft(form, draftKey);
        }, 300);
      });

      form.addEventListener('change', function () {
        saveFormDraft(form, draftKey);
      });

      // Clear draft on successful form submit
      form.addEventListener('submit', function () {
        clearFormDraft(draftKey);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormDrafts);
  } else {
    initFormDrafts();
  }

  /* ==========================================================================
     3. OFFLINE / ONLINE STATUS TOAST BANNER
     ========================================================================== */
  function createToastElement() {
    var toast = document.getElementById('cm-network-toast');
    if (toast) return toast;

    toast = document.createElement('div');
    toast.id = 'cm-network-toast';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    toast.style.cssText = [
      'position: fixed',
      'bottom: 20px',
      'left: 50%',
      'transform: translateX(-50%) translateY(100px)',
      'z-index: 99999',
      'padding: 10px 18px',
      'border-radius: 999px',
      'font-size: 0.875rem',
      'font-weight: 600',
      'box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16)',
      'transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease',
      'opacity: 0',
      'display: flex',
      'align-items: center',
      'gap: 8px',
      'pointer-events: none',
      'font-family: inherit'
    ].join(';');

    document.body.appendChild(toast);
    return toast;
  }

  function showToast(message, isOnline) {
    var toast = createToastElement();
    
    if (isOnline) {
      toast.style.backgroundColor = '#059669';
      toast.style.color = '#ffffff';
      toast.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>' + message + '</span>';
    } else {
      toast.style.backgroundColor = '#1e293b';
      toast.style.color = '#f8fafc';
      toast.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 12.55a10.94 10.94 0 0 1 5.17-2.39M10.71 5.05A16 16 0 0 1 22.58 9M1.42 9a15.91 15.91 0 0 1 4.7-2.88M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"></path></svg> <span>' + message + '</span>';
    }

    // Slide up
    requestAnimationFrame(function () {
      toast.style.transform = 'translateX(-50%) translateY(0)';
      toast.style.opacity = '1';
    });

    if (isOnline) {
      setTimeout(function () {
        toast.style.transform = 'translateX(-50%) translateY(100px)';
        toast.style.opacity = '0';
      }, 3500);
    }
  }

  window.addEventListener('offline', function () {
    showToast('You are offline. Showing saved pages & drafts.', false);
  });

  window.addEventListener('online', function () {
    showToast('Back online! Connection restored.', true);
  });
})();
