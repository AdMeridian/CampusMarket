/**
 * CampusMarket Listing Share Modal & Direct Inbox Sharing
 * Enables sharing listings directly with other students via CampusMarket messages,
 * copying direct links, WhatsApp, and device native share sheets.
 */
(function (global) {
  function qs(sel, parent) {
    return (parent || document).querySelector(sel);
  }

  function qsa(sel, parent) {
    return Array.from((parent || document).querySelectorAll(sel));
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
    }, 3000);
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

    if (typeof global.posthog !== 'undefined' && global.posthog.capture) {
      global.posthog.capture('listing_shared', {
        listing_id: Number(productId),
        channel: channel,
      });
    }
  }

  function copyText(text, successMsg) {
    var label = successMsg || 'Link copied to clipboard!';
    if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
      return global.navigator.clipboard.writeText(text).then(function () {
        showToast(label);
      });
    }
    var input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    showToast(label);
    return Promise.resolve();
  }

  var selectedUser = null;
  var currentData = null;
  var searchDebounceTimer = null;

  function createModal() {
    var existing = document.getElementById('cm-share-modal');
    if (existing) return existing;

    var modal = document.createElement('div');
    modal.id = 'cm-share-modal';
    modal.className = 'cm-share-modal-overlay';
    modal.innerHTML = 
      '<div class="cm-share-modal" role="dialog" aria-modal="true" aria-labelledby="cm-share-modal-title">' +
        '<div class="cm-share-modal__header">' +
          '<h3 id="cm-share-modal-title" class="cm-share-modal__title">Share Listing</h3>' +
          '<button type="button" class="cm-share-modal__close" aria-label="Close">&times;</button>' +
        '</div>' +
        '<div class="cm-share-modal__product-preview">' +
          '<img src="" alt="" class="cm-share-modal__product-img">' +
          '<div class="cm-share-modal__product-meta">' +
            '<strong class="cm-share-modal__product-title"></strong>' +
            '<span class="cm-share-modal__product-price"></span>' +
          '</div>' +
        '</div>' +
        '<div class="cm-share-modal__body">' +
          '<div class="cm-share-modal__section">' +
            '<div class="cm-share-modal__section-header">' +
              '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary);">' +
                '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>' +
              '</svg>' +
              '<span>Send in CampusMarket Inbox</span>' +
            '</div>' +
            '<div id="cm-share-inbox-content"></div>' +
          '</div>' +
          '<div class="cm-share-modal__section">' +
            '<div class="cm-share-quick-actions">' +
              '<button type="button" class="cm-share-quick-btn" id="cm-share-copy-btn">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                  '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>' +
                  '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>' +
                '</svg>' +
                '<span id="cm-share-copy-label">Copy Link</span>' +
              '</button>' +
              '<button type="button" class="cm-share-quick-btn" id="cm-share-wa-btn">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                  '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>' +
                '</svg>' +
                '<span>WhatsApp</span>' +
              '</button>' +
              '<button type="button" class="cm-share-quick-btn" id="cm-share-more-btn" style="display: none;">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                  '<circle cx="18" cy="5" r="3"></circle>' +
                  '<circle cx="6" cy="12" r="3"></circle>' +
                  '<circle cx="18" cy="19" r="3"></circle>' +
                  '<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>' +
                  '<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>' +
                '</svg>' +
                '<span>More...</span>' +
              '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(modal);

    modal.querySelector('.cm-share-modal__close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });

    return modal;
  }

  function closeModal() {
    var modal = document.getElementById('cm-share-modal');
    if (modal) {
      modal.classList.remove('is-open');
    }
    selectedUser = null;
  }

  function renderInboxSection(modal) {
    var container = modal.querySelector('#cm-share-inbox-content');
    if (!container) return;

    var base = (global.__baseUrl || '/').replace(/\/?$/, '/');

    if (!global.__isLoggedIn) {
      container.innerHTML =
        '<div class="cm-share-guest-notice">' +
          '<p>Log in to share this item directly with other students on campus.</p>' +
          '<a href="' + base + 'pages/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search) + '" class="btn btn-primary btn-sm" style="display:inline-block;padding:0.5rem 1.25rem;border-radius:var(--radius-md);">' +
            'Log In to Share' +
          '</a>' +
        '</div>';
      return;
    }

    container.innerHTML =
      '<div class="cm-share-search-wrap">' +
        '<input type="text" id="cm-share-search-input" class="cm-share-search-input" placeholder="Search student by username..." autocomplete="off">' +
      '</div>' +
      '<div id="cm-share-user-list" class="cm-share-user-list">' +
        '<div style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Loading contacts...</div>' +
      '</div>' +
      '<div id="cm-share-action-wrap" style="display:none;margin-top:0.75rem;">' +
        '<textarea id="cm-share-note-input" class="cm-share-note-input" rows="2" placeholder="Add an optional note... (e.g. Check out this listing!)"></textarea>' +
        '<button type="button" id="cm-share-submit-btn" class="btn btn-primary w-full" style="padding:0.65rem 1rem;margin-top:0.5rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:0.5rem;">' +
          'Send to <span id="cm-share-selected-username"></span>' +
        '</button>' +
      '</div>' +
      '<div id="cm-share-success-box" style="display:none;" class="cm-share-success-box"></div>';

    var searchInput = container.querySelector('#cm-share-search-input');
    var userList = container.querySelector('#cm-share-user-list');
    var actionWrap = container.querySelector('#cm-share-action-wrap');
    var selectedName = container.querySelector('#cm-share-selected-username');
    var submitBtn = container.querySelector('#cm-share-submit-btn');
    var noteInput = container.querySelector('#cm-share-note-input');
    var successBox = container.querySelector('#cm-share-success-box');

    function fetchUsers(query) {
      userList.innerHTML = '<div style="padding:0.75rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Searching...</div>';
      fetch(base + 'pages/api_messages.php?action=search_users&q=' + encodeURIComponent(query || ''))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.success || !data.users || data.users.length === 0) {
            userList.innerHTML = '<div style="padding:0.75rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">No students found</div>';
            return;
          }

          userList.innerHTML = '';
          data.users.forEach(function (user) {
            var item = document.createElement('div');
            item.className = 'cm-share-user-item';
            if (selectedUser && selectedUser.id === user.id) {
              item.classList.add('is-selected');
            }

            item.innerHTML =
              '<img src="' + user.avatar_url + '" class="cm-share-user-avatar" alt="" onerror="this.src=\'' + base + 'public/images/logo.png\'">' +
              '<div class="cm-share-user-info">' +
                '<strong>@' + user.username + '</strong>' +
                '<span>' + (user.is_recent ? 'Recent contact' : 'Student') + '</span>' +
              '</div>' +
              '<span class="badge" style="background:var(--bg-main);color:var(--text-muted);font-size:0.75rem;">' +
                (selectedUser && selectedUser.id === user.id ? 'Selected' : 'Select') +
              '</span>';

            item.addEventListener('click', function () {
              qsa('.cm-share-user-item', userList).forEach(function (el) {
                el.classList.remove('is-selected');
                var b = el.querySelector('.badge');
                if (b) b.textContent = 'Select';
              });

              selectedUser = user;
              item.classList.add('is-selected');
              var b = item.querySelector('.badge');
              if (b) b.textContent = 'Selected ✓';

              selectedName.textContent = '@' + user.username;
              actionWrap.style.display = 'block';
              noteInput.focus();
            });

            userList.appendChild(item);
          });
        })
        .catch(function () {
          userList.innerHTML = '<div style="padding:0.75rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Could not load contacts</div>';
        });
    }

    // Initial load: recent contacts
    fetchUsers('');

    // Search input listener
    searchInput.addEventListener('input', function () {
      clearTimeout(searchDebounceTimer);
      var val = searchInput.value.trim();
      searchDebounceTimer = setTimeout(function () {
        fetchUsers(val);
      }, 250);
    });

    // Send button listener
    submitBtn.addEventListener('click', function () {
      if (!selectedUser || !currentData) return;

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';

      var form = new FormData();
      form.append('action', 'share_to_inbox');
      form.append('product_id', String(currentData.productId));
      form.append('recipient_id', String(selectedUser.id));
      form.append('note', noteInput.value.trim());
      if (global.__csrfToken) {
        form.append('csrf_token', global.__csrfToken);
      }

      fetch(base + 'pages/api_messages.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
      })
        .then(function (res) { return res.json(); })
        .then(function (resData) {
          if (!resData.success) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send to ' + selectedName.textContent;
            alert(resData.error || 'Failed to send listing.');
            return;
          }

          // Success UI
          actionWrap.style.display = 'none';
          searchInput.style.display = 'none';
          userList.style.display = 'none';

          successBox.innerHTML =
            '<div style="display:flex;align-items:center;gap:0.75rem;">' +
              '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
                '<polyline points="20 6 9 17 4 12"></polyline>' +
              '</svg>' +
              '<div>' +
                '<strong style="display:block;color:var(--text-main);font-size:0.95rem;">Listing shared to @' + resData.recipient_username + '!</strong>' +
                '<span style="font-size:0.8rem;color:var(--text-muted);">The product preview card has been sent to their inbox.</span>' +
              '</div>' +
            '</div>' +
            '<div style="margin-top:0.85rem;display:flex;gap:0.5rem;">' +
              '<a href="' + resData.chat_url + '" class="btn btn-primary btn-sm" style="flex:1;text-align:center;padding:0.5rem 1rem;font-weight:700;">' +
                'Open Conversation →' +
              '</a>' +
              '<button type="button" class="btn btn-secondary btn-sm" id="cm-share-done-btn" style="padding:0.5rem 1rem;">Done</button>' +
            '</div>';

          successBox.style.display = 'block';
          showToast('Listing sent to @' + resData.recipient_username);

          var doneBtn = successBox.querySelector('#cm-share-done-btn');
          if (doneBtn) {
            doneBtn.addEventListener('click', closeModal);
          }
        })
        .catch(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send to ' + selectedName.textContent;
          alert('Network error. Please try again.');
        });
    });
  }

  function openModal(data) {
    currentData = data;
    selectedUser = null;
    var modal = createModal();

    // Fill product info
    var imgEl = modal.querySelector('.cm-share-modal__product-img');
    var titleEl = modal.querySelector('.cm-share-modal__product-title');
    var priceEl = modal.querySelector('.cm-share-modal__product-price');
    var copyBtn = modal.querySelector('#cm-share-copy-btn');
    var copyLabel = modal.querySelector('#cm-share-copy-label');
    var waBtn = modal.querySelector('#cm-share-wa-btn');
    var moreBtn = modal.querySelector('#cm-share-more-btn');

    if (imgEl) imgEl.src = data.image || '';
    if (titleEl) titleEl.textContent = data.title || '';
    if (priceEl) priceEl.textContent = data.price || '';

    // Render Inbox Section
    renderInboxSection(modal);

    // Setup Copy Link
    copyBtn.onclick = function () {
      copyText(data.url, data.copiedLabel).then(function () {
        copyLabel.textContent = 'Copied! ✓';
        recordShare(data.productId, 'copy');
        setTimeout(function () {
          copyLabel.textContent = 'Copy Link';
        }, 2200);
      });
    };

    // Setup WhatsApp
    waBtn.onclick = function () {
      var waUrl = 'https://wa.me/?text=' + encodeURIComponent(data.text + ' ' + data.url);
      window.open(waUrl, '_blank', 'noopener,noreferrer');
      recordShare(data.productId, 'whatsapp');
    };

    // Setup Native Share fallback button
    if (global.navigator && typeof global.navigator.share === 'function') {
      moreBtn.style.display = 'inline-flex';
      moreBtn.onclick = function () {
        global.navigator.share({
          title: data.title,
          text: data.text,
          url: data.url,
        }).then(function () {
          recordShare(data.productId, 'native');
        }).catch(function () {});
      };
    } else {
      moreBtn.style.display = 'none';
    }

    modal.classList.add('is-open');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = qs('#product-share-btn');
    if (!btn) {
      return;
    }

    function extractData() {
      return {
        productId: btn.getAttribute('data-product-id'),
        title: btn.getAttribute('data-title') || 'CampusMarket',
        text: btn.getAttribute('data-text') || '',
        url: btn.getAttribute('data-url') || global.location.href,
        image: btn.getAttribute('data-image') || '',
        price: btn.getAttribute('data-price') || '',
        copiedLabel: btn.getAttribute('data-copied-label') || 'Link copied to clipboard!',
      };
    }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(extractData());
    });

    // Also connect any secondary share buttons on the page
    qsa('.product-share-btn').forEach(function (otherBtn) {
      if (otherBtn !== btn) {
        otherBtn.addEventListener('click', function (e) {
          e.preventDefault();
          openModal(extractData());
        });
      }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeModal();
    }
  });

  global.CampusMarketProductShare = {
    openModal: openModal,
    closeModal: closeModal,
  };
})(window);
