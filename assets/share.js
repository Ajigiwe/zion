(function () {
  'use strict';

  var POP_ID = 'share-pop';

  var ICONS = {
    wa: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M16.75 13.96c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.79-.79.96-.15.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.24-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.47c-.16 0-.43.06-.65.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.17 1.73 2.64 4.2 3.7.58.26 1.04.41 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.22-.17-.47-.29zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.9-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.89-9.89 9.89zM20.52 3.45A11.82 11.82 0 0 0 12.05 0C5.5 0 .18 5.32.17 11.87c0 2.09.55 4.14 1.59 5.94L.08 24l6.34-1.66a11.88 11.88 0 0 0 5.62 1.43h.01c6.55 0 11.87-5.32 11.88-11.87 0-3.18-1.24-6.16-3.4-8.45z"/></svg>',
    fb: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.6 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>',
    x: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41z"/></svg>',
    tg: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M23.91 3.79L20.3 20.84c-.25 1.21-.98 1.5-2 .94l-5.5-4.07-2.66 2.57c-.3.3-.55.56-1.1.56-.72 0-.6-.27-.84-.95L6.3 13.7l-5.45-1.7c-1.18-.35-1.19-1.16.26-1.75l21.26-8.2c.97-.43 1.9.24 1.53 1.73z"/></svg>',
    link: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    sys: '<svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>'
  };

  function close() {
    var el = document.getElementById(POP_ID);
    if (el) { el.parentNode.removeChild(el); }
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text)['catch'](function () { legacyCopy(text); });
    } else {
      legacyCopy(text);
    }
  }

  function legacyCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* noop */ }
    document.body.removeChild(ta);
  }

  function row(icon, label, href, onClick) {
    var el = document.createElement(onClick ? 'button' : 'a');
    el.type = 'button';
    el.className = 'flex items-center gap-2.5 px-3 py-2 rounded-lg text-body-sm text-on-surface hover:bg-surface-container transition-colors text-left w-full';
    el.innerHTML = icon + '<span>' + label + '</span>';
    if (onClick) {
      el.addEventListener('click', onClick);
    } else {
      el.href = href;
      el.target = '_blank';
      el.rel = 'noopener';
    }
    return el;
  }

  function openPopover(btn) {
    var url = btn.getAttribute('data-share-url') || window.location.href;
    var title = btn.getAttribute('data-share-title') || document.title;
    var existing = document.getElementById(POP_ID);
    close();
    if (existing) { return; }

    var pop = document.createElement('div');
    pop.id = POP_ID;
    pop.setAttribute('role', 'menu');
    pop.className = 'fixed z-[90] bg-surface-container-lowest border border-outline-variant rounded-xl shadow-lg p-2 flex flex-col gap-0.5 w-56';

    pop.appendChild(row(ICONS.wa, 'WhatsApp', 'https://wa.me/?text=' + encodeURIComponent(title + '\n' + url)));
    pop.appendChild(row(ICONS.fb, 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url)));
    pop.appendChild(row(ICONS.x, 'X (Twitter)', 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title)));
    pop.appendChild(row(ICONS.tg, 'Telegram', 'https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title)));
    var copyRow = row(ICONS.link, 'Copy link', null, function () {
      copyText(url);
      var lbl = copyRow.querySelector('span');
      if (lbl) { lbl.textContent = 'Link copied!'; }
    });
    pop.appendChild(copyRow);

    if (navigator.share) {
      pop.appendChild(row(ICONS.sys, 'More options...', null, function () {
        close();
        navigator.share({ title: title, url: url })['catch'](function () { /* user cancelled */ });
      }));
    }

    document.body.appendChild(pop);
    var r = btn.getBoundingClientRect();
    var top = r.bottom + 8;
    if (top + pop.offsetHeight > window.innerHeight - 8) {
      top = Math.max(8, r.top - pop.offsetHeight - 8);
    }
    var left = Math.min(r.left, window.innerWidth - pop.offsetWidth - 8);
    pop.style.top = top + 'px';
    pop.style.left = Math.max(8, left) + 'px';
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    var btn = t && t.closest ? t.closest('[data-share]') : null;
    if (btn) {
      e.preventDefault();
      openPopover(btn);
      return;
    }
    if (t && t.closest && t.closest('#' + POP_ID)) { return; }
    close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { close(); }
  });

  window.addEventListener('scroll', close, { passive: true });
})();
