/**
 * Caça ao Tesouro — Common JS
 * Flash dismiss, mobile menu, client-side validation, digit input
 */
(function () {
  'use strict';

  /* -------------------------------------------------------
     FLASH AUTO-DISMISS
     ------------------------------------------------------- */
  function initFlash() {
    var flash = document.querySelector('.flash');
    if (!flash) return;

    setTimeout(function () {
      flash.classList.add('flash-hide');
      setTimeout(function () {
        flash.remove();
      }, 300);
    }, 4000);
  }

  /* -------------------------------------------------------
     SIDEBAR MOBILE DRAWER
     ------------------------------------------------------- */
  function initMobileMenu() {
    var hamburger = document.querySelector('.hamburger');
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    if (!hamburger || !sidebar || !overlay) return;

    function openMenu() {
      sidebar.classList.add('open');
      overlay.classList.add('visible');
      document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
      sidebar.classList.remove('open');
      overlay.classList.remove('visible');
      document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', function () {
      if (sidebar.classList.contains('open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    overlay.addEventListener('click', closeMenu);

    // Close on Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeMenu();
      }
    });
  }

  /* -------------------------------------------------------
     DIGIT INPUT — individual digit boxes
     Supports multiple fields: each [data-digit-input] container
     syncs to its own hidden input (via data-hidden-name attribute
     or fallback to name="numeric_answer").
     ------------------------------------------------------- */
  function initDigitInput() {
    var containers = document.querySelectorAll('[data-digit-input]');
    if (!containers.length) return;

    containers.forEach(function (container) {
      var boxes = container.querySelectorAll('.digit-box');
      var hiddenName = container.getAttribute('data-hidden-name') || 'numeric_answer';
      var hidden = container.querySelector('input[name="' + hiddenName + '"]');
      if (!boxes.length || !hidden) return;

      // Pre-fill boxes from hidden value (edit mode)
      var existingVal = hidden.value || '';
      if (existingVal) {
        for (var p = 0; p < existingVal.length && p < boxes.length; p++) {
          boxes[p].value = existingVal[p];
        }
      }

      boxes.forEach(function (box, idx) {
        // Allow only digits
        box.addEventListener('input', function () {
          var val = box.value.replace(/[^0-9]/g, '');
          box.value = val;

          if (val && idx < boxes.length - 1) {
            boxes[idx + 1].focus();
          }
        });

        // Backspace: move to previous box when current is empty
        box.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !box.value && idx > 0) {
            e.preventDefault();
            boxes[idx - 1].focus();
            boxes[idx - 1].value = '';
          }
        });

        // Allow paste: fill sequential boxes from clipboard digits
        box.addEventListener('paste', function (e) {
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
          if (!text) return;

          for (var j = 0; j < text.length && (idx + j) < boxes.length; j++) {
            boxes[idx + j].value = text[j];
          }

          // Focus last filled or next empty
          var nextIdx = Math.min(idx + text.length, boxes.length - 1);
          boxes[nextIdx].focus();
        });

        // On focus: select the content
        box.addEventListener('focus', function () {
          box.select();
        });
      });

      // Sync hidden field before form submit
      var form = container.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          var digits = '';
          boxes.forEach(function (b) {
            if (b.value) {
              digits += b.value;
            }
          });
          hidden.value = digits;
        });
      }
    });
  }

  /* -------------------------------------------------------
     CLIENT-SIDE VALIDATION
     ------------------------------------------------------- */
  function initValidation() {
    var forms = document.querySelectorAll('form[data-validate]');
    if (!forms.length) return;

    forms.forEach(function (form) {
      // Remove native validation — server is authority
      form.setAttribute('novalidate', '');

      form.addEventListener('submit', function (e) {
        var valid = true;

        // Clear previous errors
        form.querySelectorAll('.form-group').forEach(function (g) {
          g.classList.remove('has-error');
          var inline = g.querySelector('.error-inline');
          if (inline) inline.textContent = '';
        });

        // Username validation (obrigatório, >=3 chars, apenas letras/números/underscore)
        var usernameInputs = form.querySelectorAll('input[name="username"]');
        usernameInputs.forEach(function (input) {
          var val = input.value.trim();
          if (!val) return; // required handles empty
          if (val.length < 3) {
            setError(input, 'O usuário deve ter no mínimo 3 caracteres');
            valid = false;
          } else if (!/^[a-zA-Z0-9_]+$/.test(val)) {
            setError(input, 'Apenas letras, números e underscores são permitidos');
            valid = false;
          }
        });

        // Password minlength
        var pwInputs = form.querySelectorAll('input[name="password"]');
        pwInputs.forEach(function (input) {
          if (!input.value) return;
          if (input.value.length < 6) {
            setError(input, 'A senha deve ter no mínimo 6 caracteres');
            valid = false;
          }
        });

        // Password confirm match
        var pwConfirm = form.querySelector('input[name="password_confirm"]');
        var pw = form.querySelector('input[name="password"]');
        if (pwConfirm && pw && pwConfirm.value && pw.value) {
          if (pwConfirm.value !== pw.value) {
            setError(pwConfirm, 'As senhas não coincidem');
            valid = false;
          }
        }

        // Required fields (skip digit boxes — handled separately)
        var requireds = form.querySelectorAll('[required]');
        requireds.forEach(function (input) {
          if (!input.classList.contains('digit-box') && !input.value.trim() && input.type !== 'hidden') {
            setError(input, 'Campo obrigatório');
            valid = false;
          }
        });

        // Digit input validation: 1-8 digits required
        var digitContainers = form.querySelectorAll('[data-digit-input]');
        digitContainers.forEach(function (container) {
          var boxes = container.querySelectorAll('.digit-box');
          var hidden = container.querySelector('input[name="numeric_answer"]');
          if (!hidden) return;

          // Collect digits
          var digits = '';
          boxes.forEach(function (b) {
            if (b.value) digits += b.value;
          });

          // Must have 1-8 digits
          if (digits.length < 1 || digits.length > 8) {
            valid = false;
            var group = container.closest('.form-group');
            if (group) {
              group.classList.add('has-error');
              var inline = group.querySelector('.error-inline');
              if (inline) inline.textContent = 'A resposta deve ter de 1 a 8 dígitos.';
            }
            // Focus first empty or first box
            var firstEmpty = Array.from(boxes).find(function (b) { return !b.value; });
            if (firstEmpty) firstEmpty.focus();
            else if (boxes.length) boxes[0].focus();
          } else {
            // Set hidden value for submission
            hidden.value = digits;
          }
        });

        if (!valid) {
          e.preventDefault();
          // Focus first error
          var firstErr = form.querySelector('.has-error .form-input, .has-error input');
          if (firstErr) firstErr.focus();
        }
      });
    });

    function setError(input, msg) {
      var group = input.closest('.form-group');
      if (!group) return;
      group.classList.add('has-error');
      var inline = group.querySelector('.error-inline');
      if (inline) inline.textContent = msg;
    }
  }

  /* -------------------------------------------------------
     CONFIRM DELETE (data-confirm)
     ------------------------------------------------------- */
  function initConfirmDelete() {
    var forms = document.querySelectorAll('form[data-confirm]');
    if (!forms.length) return;

    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var message = form.getAttribute('data-confirm');
        if (message && !confirm(message)) {
          e.preventDefault();
        }
      });
    });
  }

  /* -------------------------------------------------------
     TABS — settings page tab switching
     ------------------------------------------------------- */
  function initTabs() {
    var tabBtns = document.querySelectorAll('.tab-btn');
    var tabPanels = document.querySelectorAll('.tab-panel');
    var activeTabInput = document.getElementById('activeTab');
    if (!tabBtns.length || !tabPanels.length) return;

    function activateTab(tabId) {
      // Deactivate all
      tabBtns.forEach(function (btn) { btn.classList.remove('active'); btn.setAttribute('aria-selected', 'false'); });
      tabPanels.forEach(function (panel) { panel.classList.remove('active'); });

      // Activate target
      var targetBtn = document.querySelector('.tab-btn[data-tab="' + tabId + '"]');
      var targetPanel = document.getElementById('tab-' + tabId);
      if (targetBtn && targetPanel) {
        targetBtn.classList.add('active');
        targetBtn.setAttribute('aria-selected', 'true');
        targetPanel.classList.add('active');
        if (activeTabInput) activeTabInput.value = tabId;
      }
    }

    // Click handlers
    tabBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var tabId = btn.getAttribute('data-tab');
        activateTab(tabId);
        // Update URL hash without scrolling
        if (history.replaceState) {
          history.replaceState(null, null, '#' + tabId);
        }
      });
    });

    // Restore active tab: hash first, then hidden field, default 'geral'
    var hash = (window.location.hash || '').replace('#', '');
    var savedTab = activeTabInput ? activeTabInput.value : '';
    var initialTab = 'geral';
    if (hash && document.getElementById('tab-' + hash)) {
      initialTab = hash;
    } else if (savedTab && document.getElementById('tab-' + savedTab)) {
      initialTab = savedTab;
    }
    activateTab(initialTab);
  }

  /* -------------------------------------------------------
     CLIPBOARD COPY — data-copy buttons
     ------------------------------------------------------- */
  function initClipboardCopy() {
    var copyBtns = document.querySelectorAll('[data-copy]');
    if (!copyBtns.length) return;

    copyBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var text = btn.getAttribute('data-copy');
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            showCopiedFeedback(btn);
          }).catch(function () {
            fallbackCopy(text, btn);
          });
        } else {
          fallbackCopy(text, btn);
        }
      });
    });

    function fallbackCopy(text, btn) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        showCopiedFeedback(btn);
      } catch (err) {
        // silently fail
      }
      document.body.removeChild(ta);
    }

    function showCopiedFeedback(btn) {
      var original = btn.innerHTML;
      btn.classList.add('copied');
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copiado!';
      setTimeout(function () {
        btn.classList.remove('copied');
        btn.innerHTML = original;
      }, 2000);
    }
  }

  /* -------------------------------------------------------
     PASSWORD TOGGLE — show/hide password fields
     ------------------------------------------------------- */
  function initPasswordToggle() {
    var toggles = document.querySelectorAll('[data-toggle-password]');
    if (!toggles.length) return;

    toggles.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-toggle-password');
        var input = document.getElementById(targetId);
        if (!input) return;

        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        // Swap SVG icons
        var iconOpen = btn.querySelector('.icon-eye-open');
        var iconClosed = btn.querySelector('.icon-eye-closed');
        if (iconOpen && iconClosed) {
          iconOpen.style.display = isPassword ? 'none' : '';
          iconClosed.style.display = isPassword ? '' : 'none';
        }

        // Update aria-label
        btn.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
      });
    });
  }

  /* -------------------------------------------------------
     DRAG REORDER — HTML5 drag & drop for treasure list
     ------------------------------------------------------- */
  function initDragReorder() {
    var list = document.getElementById('treasure-list');
    var btn = document.getElementById('btn-save-order');
    var csrfEl = document.getElementById('csrf-token-data');
    if (!list || !btn) return;

    var csrf = csrfEl ? csrfEl.getAttribute('data-csrf') : '';
    var isRandomOrder = list.getAttribute('data-random-order') === 'true';
    var draggedEl = null;

    function readOrder() {
      return Array.from(list.querySelectorAll('.treasure-card[data-treasure-id]'))
        .map(function (el) {
          return parseInt(el.getAttribute('data-treasure-id'), 10);
        });
    }

    function updateOrderNumbers() {
      var cards = list.querySelectorAll('.treasure-card[data-treasure-id]');
      cards.forEach(function (card, i) {
        var badge = card.querySelector('.order-badge');
        if (badge) badge.textContent = String(i + 1);
      });
    }

    // Disable drag if random order
    if (isRandomOrder) {
      btn.disabled = true;
      list.querySelectorAll('.drag-handle').forEach(function (h) {
        h.style.cursor = 'not-allowed';
        h.style.opacity = '0.3';
        h.title = 'Ordem aleatória — reordenação desabilitada';
      });
      return;
    }

    // HTML5 Drag & Drop
    list.addEventListener('dragstart', function (e) {
      var card = e.target.closest('.treasure-card[data-treasure-id]');
      if (!card) return;

      draggedEl = card;
      card.classList.add('dragging');

      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.getAttribute('data-treasure-id'));

      // Use setTimeout to avoid showing the drag ghost of the original
      setTimeout(function () {
        card.style.opacity = '0.4';
      }, 0);
    });

    list.addEventListener('dragend', function (e) {
      var card = e.target.closest('.treasure-card[data-treasure-id]');
      if (card) {
        card.classList.remove('dragging');
        card.style.opacity = '';
      }
      // Remove all drag-over indicators
      list.querySelectorAll('.drag-over').forEach(function (el) {
        el.classList.remove('drag-over');
      });
      draggedEl = null;
    });

    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';

      var target = e.target.closest('.treasure-card[data-treasure-id]');
      if (!target || target === draggedEl) return;

      // Remove previous indicators
      list.querySelectorAll('.drag-over').forEach(function (el) {
        el.classList.remove('drag-over');
      });

      target.classList.add('drag-over');
    });

    list.addEventListener('dragleave', function (e) {
      var target = e.target.closest('.treasure-card[data-treasure-id]');
      if (target) {
        target.classList.remove('drag-over');
      }
    });

    list.addEventListener('drop', function (e) {
      e.preventDefault();

      var target = e.target.closest('.treasure-card[data-treasure-id]');
      if (!target || !draggedEl || target === draggedEl) return;

      target.classList.remove('drag-over');

      var cards = Array.from(list.querySelectorAll('.treasure-card[data-treasure-id]'));
      var dragIdx = cards.indexOf(draggedEl);
      var dropIdx = cards.indexOf(target);

      if (dragIdx < dropIdx) {
        list.insertBefore(draggedEl, target.nextSibling);
      } else {
        list.insertBefore(draggedEl, target);
      }

      updateOrderNumbers();
      btn.disabled = false;
    });

    // Also support click-to-move for accessibility (legacy)
    list.querySelectorAll('.drag-handle').forEach(function (handle) {
      handle.addEventListener('click', function () {
        var card = handle.closest('.treasure-card[data-treasure-id]');
        if (!card) return;

        var cards = Array.from(list.querySelectorAll('.treasure-card[data-treasure-id]'));
        var idx = cards.indexOf(card);

        if (idx < cards.length - 1) {
          list.insertBefore(cards[idx + 1], card);
          updateOrderNumbers();
          btn.disabled = false;
        }
      });
    });

    // Save order via fetch
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var order = readOrder();

      fetch('/tesouros/reorder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ ids: order })
      })
        .then(function (res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json();
        })
        .then(function (data) {
          if (data.success) {
            // Show inline success feedback
            var origText = btn.innerHTML;
            btn.innerHTML = '✓ Ordem salva!';
            btn.style.background = 'rgba(39, 174, 96, 0.2)';
            btn.style.borderColor = 'rgba(39, 174, 96, 0.4)';
            btn.style.color = '#5fd99f';
            setTimeout(function () {
              btn.innerHTML = origText;
              btn.style.background = '';
              btn.style.borderColor = '';
              btn.style.color = '';
              btn.disabled = false;
            }, 2000);
          } else {
            alert(data.error || 'Falha ao salvar a ordem.');
            btn.disabled = false;
          }
        })
        .catch(function () {
          alert('Falha ao salvar a ordem.');
          btn.disabled = false;
        });
    });

    // Initialize order on page load
    readOrder();
  }

  /* -------------------------------------------------------
     INIT
     ------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    initFlash();
    initMobileMenu();
    initDigitInput();
    initValidation();
    initConfirmDelete();
    initTabs();
    initClipboardCopy();
    initPasswordToggle();
    initDragReorder();
  });
})();
