/**
 * synapta-portal.js — Patient Dashboard
 *
 * STRUCTURE:
 *  Part 1 — IIFE   : private helpers, tab switching, modals, AJAX, event wiring
 *  Part 2 — Globals: functions called from inline HTML onload/onclick attributes
 */

/* ═══════════════════════════════════════════════════════════
   PART 1 — IIFE  (private scope)
═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  // ── Helpers ──────────────────────────────────────────────────────────────
  function getCsrf() {
    var el = document.getElementById('syn-csrf-token');
    return el ? el.value : '';
  }

  function getHandlerUrl() {
    var el = document.getElementById('syn-msg-handler-url');
    return el ? el.value : '';
  }

  // ── Main tab switching ────────────────────────────────────────────────────
  function activateTab(name) {
    document.querySelectorAll('.syn-tpanel').forEach(function (el) {
      el.classList.remove('syn-on');
    });
    var panel = document.getElementById('syn-tab-' + name);
    if (panel) panel.classList.add('syn-on');

    document.querySelectorAll('.syn-stab').forEach(function (el) {
      var active = el.getAttribute('data-tab') === name;
      el.classList.toggle('syn-on', active);
      el.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    document.querySelectorAll('.syn-ni[data-tab]').forEach(function (el) {
      el.classList.toggle('syn-on', el.getAttribute('data-tab') === name);
    });

    // Hide right-rail for intake tab, restore for all others
    var app = document.querySelector('.syn-app');
    if (app) app.classList.toggle('syn-intake-active', name === 'intake');
  }

  // ── Messaging sub-tabs ────────────────────────────────────────────────────
  // Supports: inbox, reminder, recalls, portal (and sent if present)
  function activateMsgTab(name) {
    document.querySelectorAll('.syn-msg-tab[data-msgtab]').forEach(function (btn) {
      btn.classList.toggle('syn-on', btn.getAttribute('data-msgtab') === name);
    });
    var panelIds = ['inbox', 'sent', 'reminder', 'recalls', 'portal'];
    panelIds.forEach(function (key) {
      var el = document.getElementById('syn-msgtab-' + key);
      if (el) el.style.display = (key === name) ? '' : 'none';
    });
  }

  // ── Compose modal ─────────────────────────────────────────────────────────
  window.synOpenCompose = function (prefillSubject, prefillTo) {
    var overlay = document.getElementById('syn-compose-overlay');
    var form    = document.getElementById('syn-compose-form');
    var success = document.getElementById('syn-compose-success');
    var errBox  = document.getElementById('syn-compose-error');

    if (form)    form.style.display    = '';
    if (success) success.style.display = 'none';
    if (errBox)  errBox.style.display  = 'none';

    if (prefillSubject) {
      var subj = document.getElementById('syn-msg-subject');
      if (subj) subj.value = prefillSubject;
    }

    if (prefillTo) {
      var select = document.getElementById('syn-msg-to');
      if (select) select.value = prefillTo;
    }

    if (overlay) overlay.style.display = 'flex';
    setTimeout(function () {
      var f = document.getElementById('syn-msg-to');
      if (f) f.focus();
    }, 120);
  };

  window.synCloseCompose = function (e) {
    var overlay = document.getElementById('syn-compose-overlay');
    if (!overlay) return;
    if (e && e.target !== overlay) return;
    overlay.style.display = 'none';
    var form = document.getElementById('syn-compose-form');
    if (form) form.reset();
    updateCharCount();
  };

  // ── Send message via AJAX ─────────────────────────────────────────────────
  window.synSendMessage = function (e) {
    e.preventDefault();
    var btn     = document.getElementById('syn-send-btn');
    var label   = document.getElementById('syn-send-label');
    var spin    = document.getElementById('syn-send-spin');
    var errBox  = document.getElementById('syn-compose-error');
    var form    = document.getElementById('syn-compose-form');
    var success = document.getElementById('syn-compose-success');

    if (label) label.style.display  = 'none';
    if (spin)  spin.style.display   = '';
    if (btn)   btn.disabled          = true;
    if (errBox) errBox.style.display = 'none';

    var data = new FormData(form);
    data.append('action',     'send');
    data.append('csrf_token', getCsrf());

    fetch(getHandlerUrl(), { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          if (form)    form.style.display    = 'none';
          if (success) success.style.display = '';
          setTimeout(function () {
            var ov = document.getElementById('syn-compose-overlay');
            if (ov) ov.style.display = 'none';
            if (form) { form.style.display = ''; form.reset(); }
            updateCharCount();
            activateTab('messages');
            activateMsgTab('inbox');
          }, 2500);
        } else {
          if (errBox) {
            errBox.textContent   = '⚠ ' + (res.error || 'Could not send. Please try again.');
            errBox.style.display = '';
          }
        }
      })
      .catch(function () {
        if (errBox) {
          errBox.textContent   = '⚠ Network error. Please check your connection and try again.';
          errBox.style.display = '';
        }
      })
      .finally(function () {
        if (label) label.style.display = '';
        if (spin)  spin.style.display  = 'none';
        if (btn)   btn.disabled         = false;
      });
  };

  // ── View message modal ────────────────────────────────────────────────────
  window.synOpenMessage = function (id, sender, subject, body, date, isUnread) {
    var overlay = document.getElementById('syn-view-overlay');
    var subjEl  = document.getElementById('syn-view-subject');
    var fromEl  = document.getElementById('syn-view-from');
    var dateEl  = document.getElementById('syn-view-date');
    var bodyEl  = document.getElementById('syn-view-body');

    if (subjEl) subjEl.textContent = subject || '(no subject)';
    if (fromEl) fromEl.textContent = 'From: ' + (sender || 'Care Team');
    if (dateEl) dateEl.textContent = date || '';
    if (bodyEl) bodyEl.innerHTML   = (body || '').replace(/\n/g, '<br>');

    if (overlay) overlay.style.display = 'flex';

    // Mark as read via AJAX if unread
    if (id && isUnread) {
      var data = new FormData();
      data.append('action',     'mark_portal_read');
      data.append('id',         id);
      data.append('csrf_token', getCsrf());
      fetch(getHandlerUrl(), { method: 'POST', body: data })
        .then(function (response) { return response.json(); })
        .then(function (res) {
          if (!res.success) return;
          // Remove unread styling from matching rows
          document.querySelectorAll('[data-om-id="' + id + '"]').forEach(function (row) {
            row.classList.remove('syn-unread');
            var dot = row.querySelector('.syn-unread-dot');
            if (dot) dot.remove();
            var fs = row.querySelector('.syn-msg-from');
            var ss = row.querySelector('.syn-msg-subj');
            if (fs) fs.classList.remove('syn-fw700');
            if (ss) ss.classList.remove('syn-fw600');
          });
          updatePortalUnreadBadges();
        })
        .catch(function (err) { console.error(err); });
    }

    window._synViewSubject = subject;
    window._synViewSender  = sender;
  };

  window.synCloseView = function (e) {
    var overlay = document.getElementById('syn-view-overlay');
    if (!overlay) return;
    if (!e || e.target === overlay) overlay.style.display = 'none';
  };

  window.synReplyTo = function () {
    var overlay = document.getElementById('syn-view-overlay');
    if (overlay) overlay.style.display = 'none';
    var re     = window._synViewSubject
               ? ('Re: ' + window._synViewSubject.replace(/^Re:\s*/i, ''))
               : '';
    var sender = window._synViewSender || '';
    window.synOpenCompose(re, sender);
  };

  // ── Badge counters ────────────────────────────────────────────────────────
  function updateUnreadBadges() {
    var n = document.querySelectorAll('.syn-unread-dot').length;
    ['.syn-stab[data-tab="messages"] .syn-badge',
     '.syn-ni[data-tab="messages"] .syn-nb',
     '.syn-msg-tab[data-msgtab="inbox"] .syn-badge'
    ].forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (b) {
        if (n > 0) { b.textContent = Math.min(n, 9); }
        else       { b.remove(); }
      });
    });
  }

  function updatePortalUnreadBadges() {
    var count = document.querySelectorAll('#syn-portaltab-inbox .syn-unread').length;

    document.querySelectorAll('.syn-portal-tab[data-portaltab="inbox"] .syn-badge')
      .forEach(function (el) {
        if (count > 0) { el.textContent = count; }
        else           { el.remove(); }
      });

    var panelBadge = document.querySelector('#syn-portaltab-inbox .syn-card-ttl .syn-badge');
    if (panelBadge) {
      if (count > 0) { panelBadge.textContent = count + ' unread'; }
      else           { panelBadge.remove(); }
    }
  }

  // ── Portal mail sub-tabs ──────────────────────────────────────────────────
  function activatePortalTab(name) {
    document.querySelectorAll('.syn-portal-tab').forEach(function (btn) {
      btn.classList.toggle('syn-on', btn.getAttribute('data-portaltab') === name);
    });
    var panelIds = ['all', 'inbox', 'sent'];
    panelIds.forEach(function (key) {
      var el = document.getElementById('syn-portaltab-' + key);
      if (el) el.style.display = (key === name) ? '' : 'none';
    });
  }

  // ── Character counter ─────────────────────────────────────────────────────
  function updateCharCount() {
    var body  = document.getElementById('syn-msg-body');
    var count = document.getElementById('syn-char-n');
    if (body && count) count.textContent = body.value.length;
  }

  // ── Escape key closes all modals ──────────────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['syn-compose-overlay','syn-view-overlay',
     'syn-intake-overlay','syn-appt-overlay'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.style.display !== 'none') el.style.display = 'none';
    });
    var form = document.getElementById('syn-compose-form');
    if (form) form.reset();
    updateCharCount();
  });

  // ── postMessage — iframe height sync ─────────────────────────────────────
  window.addEventListener('message', function (e) {
    if (!e.data || e.data.type !== 'syn-height') return;
    var h = parseInt(e.data.h, 10);
    if (!h || h < 200) return;
    ['syn-intake-frame','syn-intake-modal-frame'].forEach(function (id) {
      var f = document.getElementById(id);
      if (f && h > f.clientHeight) f.style.minHeight = h + 'px';
    });
  });

  // ── DOM ready ─────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {

    // Main stab strip
    var stabs = Array.from(document.querySelectorAll('.syn-stab'));
    stabs.forEach(function (btn, idx) {
      btn.setAttribute('role', 'tab');
      btn.setAttribute('tabindex', btn.classList.contains('syn-on') ? '0' : '-1');
      btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-tab');
        if (tab) activateTab(tab);
      });
      btn.addEventListener('keydown', function (e) {
        var next;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
          next = stabs[(idx + 1) % stabs.length];
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
          next = stabs[(idx - 1 + stabs.length) % stabs.length];
        }
        if (next) {
          next.focus();
          stabs.forEach(function (s) { s.setAttribute('tabindex', '-1'); });
          next.setAttribute('tabindex', '0');
          var tab = next.getAttribute('data-tab');
          if (tab) activateTab(tab);
        }
      });
    });

    // Sidebar icons
    document.querySelectorAll('.syn-ni[data-tab]').forEach(function (icon) {
      icon.addEventListener('click', function () {
        var tab = icon.getAttribute('data-tab');
        if (tab) activateTab(tab);
      });
    });

    // data-tab-link anchors
    document.querySelectorAll('[data-tab-link]').forEach(function (el) {
      el.addEventListener('click', function (ev) {
        ev.preventDefault();
        var tab = el.getAttribute('data-tab-link');
        if (tab) activateTab(tab);
      });
    });

    // Messaging sub-tab clicks
    document.querySelectorAll('.syn-msg-tab[data-msgtab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t = btn.getAttribute('data-msgtab');
        if (t) activateMsgTab(t);
      });
    });

    // Records sub-tab clicks
    document.querySelectorAll('.syn-msg-tab[data-rectab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = btn.getAttribute('data-rectab');
        document.querySelectorAll('.syn-msg-tab[data-rectab]').forEach(function (b) {
          b.classList.toggle('syn-on', b.getAttribute('data-rectab') === name);
        });
        document.querySelectorAll('.syn-rec-panel').forEach(function (p) {
          p.style.display = 'none';
        });
        var panel = document.getElementById('syn-rectab-' + name);
        if (panel) panel.style.display = '';
      });
    });

    // Billing sub-tab clicks
    document.querySelectorAll('.syn-msg-tab[data-billtab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var name = btn.getAttribute('data-billtab');
        document.querySelectorAll('.syn-msg-tab[data-billtab]').forEach(function (b) {
          b.classList.toggle('syn-on', b.getAttribute('data-billtab') === name);
        });
        var summary = document.getElementById('syn-billtab-summary');
        var ledger  = document.getElementById('syn-billtab-ledger');
        if (summary) summary.style.display = name === 'summary' ? '' : 'none';
        if (ledger)  ledger.style.display  = name === 'ledger'  ? '' : 'none';
        if (name === 'ledger') {
          var frame = document.getElementById('syn-ledger-frame');
          if (frame && (!frame.src || frame.src === 'about:blank'
                        || frame.src === window.location.href)) {
            frame.src = frame.getAttribute('data-src') || '';
          }
        }
      });
    });

    // Portal mail sub-tab clicks
    document.querySelectorAll('.syn-portal-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activatePortalTab(btn.getAttribute('data-portaltab'));
      });
    });

    // Profile dropdown (null-checked)
    var menu = document.getElementById('profileMenu');
    if (menu) {
      var trigger = menu.querySelector('.prov-trigger');
      if (trigger) {
        trigger.addEventListener('click', function (e) {
          e.stopPropagation();
          menu.classList.toggle('active');
        });
      }
      document.addEventListener('click', function () {
        menu.classList.remove('active');
      });
    }

    // Character counter
    var bodyArea = document.getElementById('syn-msg-body');
    if (bodyArea) bodyArea.addEventListener('input', updateCharCount);

    // Intake lazy-load on first tab click
    document.querySelectorAll(
      '.syn-stab[data-tab="intake"], .syn-ni[data-tab="intake"]'
    ).forEach(function (el) {
      el.addEventListener('click', function () {
        window.synMaybeLoadIntakeFrame();
      });
    });

    // Activate home tab on load
    activateTab('dashboard');
  });

})();

/* ═══════════════════════════════════════════════════════════
   PART 2 — GLOBAL functions
   Must be outside IIFE — called from onload/onclick in HTML
═══════════════════════════════════════════════════════════ */

// ── Generic iframe spinner hider ─────────────────────────────────────────────
function synIframeLoaded(loaderId) {
  var loader = document.getElementById(loaderId);
  if (!loader) return;
  loader.classList.add('syn-hidden');
  setTimeout(function () {
    if (loader.parentNode) loader.parentNode.removeChild(loader);
  }, 350);
}

// ── Report iframe toggle (Records tab) ───────────────────────────────────────
function synOpenReportFrame(wrapId, src, trigger) {
  var wrap = document.getElementById(wrapId);
  if (!wrap) return;
  var isOpen = wrap.style.display !== 'none';
  wrap.style.display = isOpen ? 'none' : '';
  if (trigger) trigger.classList.toggle('syn-open', !isOpen);
  if (!isOpen) {
    var frame = wrap.querySelector('iframe');
    if (frame && (!frame.src || frame.src === 'about:blank'
                  || frame.src === window.location.href)) {
      frame.src = src;
    }
  }
}

// ── Intake form ───────────────────────────────────────────────────────────────
function synIntakeLoaded() {
  synIframeLoaded('syn-intake-loader');
}

function synMaybeLoadIntakeFrame() {
  var frame = document.getElementById('syn-intake-frame');
  if (!frame) return;
  if (!frame.src || frame.src === 'about:blank'
      || frame.src === window.location.href) {
    var real = frame.getAttribute('data-src');
    if (real) frame.src = real;
  }
}

function synReloadIntake() {
  var frame = document.getElementById('syn-intake-frame');
  var wrap  = document.querySelector('.syn-intake-wrap');
  if (!frame) return;
  if (wrap && !document.getElementById('syn-intake-loader')) {
    var loader = document.createElement('div');
    loader.id        = 'syn-intake-loader';
    loader.className = 'syn-intake-loader';
    loader.innerHTML = '<div class="syn-intake-spinner"></div>'
                     + '<div class="syn-intake-loader-txt">Reloading…</div>';
    wrap.insertBefore(loader, frame);
  }
  frame.src = frame.getAttribute('data-src') || frame.src;
}

function synOpenIntake() {
  var overlay    = document.getElementById('syn-intake-overlay');
  var modalFrame = document.getElementById('syn-intake-modal-frame');
  var tabFrame   = document.getElementById('syn-intake-frame');
  if (!overlay) return;
  var src = tabFrame ? (tabFrame.getAttribute('data-src') || tabFrame.src) : '';
  if (modalFrame && src && modalFrame.src !== src) {
    modalFrame.src = src;
  }
  overlay.style.display = 'flex';
}
// Alias for fullscreen button
window.synOpenIntakeFullscreen = synOpenIntake;

function synCloseIntakeFullscreen(e) {
  var overlay = document.getElementById('syn-intake-overlay');
  if (!overlay) return;
  if (!e || e.target === overlay) overlay.style.display = 'none';
}

// ── Appointment modal ─────────────────────────────────────────────────────────

function synOpenApptModal() {

    const modal = document.getElementById('syn-appt-modal');
    const frame = document.getElementById('syn-appt-frame');

    frame.src =
        '../interface/modules/custom_modules/oe-module-patient-dashboard/public/appt_wrapper.php?patid='+patientId;

    modal.style.display = 'flex';
}

function synCloseApptModal() {

    const modal = document.getElementById('syn-appt-modal');
    const frame = document.getElementById('syn-appt-frame');

    frame.src = 'about:blank';
    modal.style.display = 'none';
}
function synOpenApptw() {
    var overlay = document.getElementById('syn-appt-overlay');
    var frame   = document.getElementById('syn-appt-frame');
    if (!overlay) {
      console.warn('synOpenAppt: #syn-appt-overlay not found in DOM');
      return;
    }
 
    // Force critical positioning styles inline so they
    // cannot be overridden by Bootstrap or OpenEMR CSS
    overlay.style.cssText = [
      'display:flex !important',
      'position:fixed',
      'inset:0',
      'top:0',
      'left:0',
      'right:0',
      'bottom:0',
      'width:100vw',
      'height:100vh',
      'background:rgba(0,0,0,0.6)',
      'z-index:99999',
      'align-items:center',
      'justify-content:center',
      'padding:20px',
      'box-sizing:border-box'
    ].join(';');
 
    // Lazy-load iframe on first open
    if (frame && (!frame.src || frame.src === 'about:blank'
                  || frame.src === window.location.href)) {
      var src = frame.getAttribute('data-src');
      if (src) frame.src = src;
    }
  }

 function synCloseAppt(e) {
    var overlay = document.getElementById('syn-appt-overlay');
    if (!overlay) return;
    if (!e || e.target === overlay) {
      overlay.style.cssText = 'display:none';
    }
  }

function synApptFrameLoaded() {
  synIframeLoaded('syn-appt-loader');
  var frame = document.getElementById('syn-appt-frame');
  if (!frame) return;
  try {
    var url = frame.contentWindow.location.href;
    if (url && (url.indexOf('home.php')  !== -1 ||
                url.indexOf('index.php') !== -1 ||
                url.indexOf('success')   !== -1)) {
      setTimeout(function () {
        synCloseAppt();
        frame.src = 'about:blank';
      }, 800);
    }
  } catch (ex) { /* cross-origin — ignore */ }
}

let currentTrend = 0;

const slides = document.querySelectorAll('.trend-slide');

function showTrend(index) {

    slides.forEach(slide => {
        slide.classList.remove('active');
    });

    slides[index].classList.add('active');
}

function changeTrend(direction) {

    currentTrend += direction;

    if (currentTrend < 0) {
        currentTrend = slides.length - 1;
    }

    if (currentTrend >= slides.length) {
        currentTrend = 0;
    }

    showTrend(currentTrend);
}

showTrend(currentTrend);