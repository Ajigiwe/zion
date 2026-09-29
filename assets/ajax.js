/**
 * Zion in-place form layer.
 *
 * Any <form data-ajax> is sent with fetch() and the [data-ajax-out] region of
 * the response document replaces the live one, so admin row actions, account
 * forms and the contact form update without a full reload.
 *
 * Behaviour rules:
 *   - inline onsubmit="return confirm(...)" still vetoes the request;
 *   - a redirect to another path is followed with a real navigation;
 *   - a same-path redirect (flash PRG) swaps the region and syncs the URL;
 *   - network failure falls back to a native submit; a usable-but-unparseable
 *     response falls back to a plain navigation (never re-posts);
 *   - without JavaScript the form posts normally.
 */
(function () {
  'use strict';

  var OUT_SEL = '[data-ajax-out]';
  var FLASH_SEL = '.fixed.top-24.right-4';

  function pathOf(url) {
    try { return new URL(url, location.href).pathname; }
    catch (e) { return String(url || '').split('?')[0].split('#')[0]; }
  }

  function formData(form, submitter) {
    try {
      return new FormData(form, submitter || undefined);
    } catch (e) {
      var fd = new FormData(form);
      if (submitter && submitter.name) { fd.append(submitter.name, submitter.value); }
      return fd;
    }
  }

  function replayScripts(root) {
    var old = root.querySelectorAll('script');
    for (var i = 0; i < old.length; i++) {
      var src = old[i];
      var next = document.createElement('script');
      for (var a = 0; a < src.attributes.length; a++) {
        next.setAttribute(src.attributes[a].name, src.attributes[a].value);
      }
      next.textContent = src.textContent;
      if (src.parentNode) { src.parentNode.replaceChild(next, src); }
    }
  }

  /** Server flashes in the toast strip are not in the page-load snapshot: fade them here. */
  function fadeStrips(container) {
    var kids = [];
    for (var i = 0; i < container.children.length; i++) { kids.push(container.children[i]); }
    kids.forEach(function (el) {
      if (el.hasAttribute('data-auto')) { return; }
      el.setAttribute('data-auto', '1');
      setTimeout(function () {
        if (!el.parentNode) { return; }
        el.style.transition = 'opacity 450ms ease';
        el.style.opacity = '0';
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 460);
      }, 4000);
    });
  }

  function swap(html, finalUrl) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var next = doc.querySelector(OUT_SEL);
    var cur = document.querySelector(OUT_SEL);
    if (!next || !cur || !cur.parentNode) { throw new Error('no ' + OUT_SEL + ' in response'); }

    var nextFlash = doc.querySelector(FLASH_SEL);
    if (nextFlash) {
      var curFlash = document.querySelector(FLASH_SEL);
      if (curFlash) { curFlash.parentNode.replaceChild(nextFlash, curFlash); }
      else { cur.parentNode.insertBefore(nextFlash, cur); }
      var live = document.querySelector(FLASH_SEL);
      if (live) { fadeStrips(live); }
    }

    if (finalUrl && finalUrl !== location.href) { history.replaceState(null, '', finalUrl); }
    if (doc.title) { document.title = doc.title; }

    cur.parentNode.replaceChild(next, cur);
    replayScripts(next);

    document.dispatchEvent(new CustomEvent('ajax:done', { detail: { url: finalUrl || location.href } }));
  }

  function busy(form, on) {
    var btn = null;
    var act = form.ownerDocument.activeElement;
    if (act && act !== document.body && (form.contains(act) || act.form === form)) { btn = act; }
    if (!btn) { btn = form.querySelector('[type="submit"]:not([disabled]), button:not([type]):not([disabled])'); }
    if (btn && 'disabled' in btn) { btn.disabled = !!on; btn.style.opacity = on ? '.7' : ''; }
    if (btn && !on && btn.style.opacity === '.7') { btn.style.opacity = ''; }
    form.setAttribute('data-busy', on ? '1' : '');
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches || !form.matches('form[data-ajax]')) { return; }
    if (e.defaultPrevented) { return; }
    if (form.getAttribute('data-busy') === '1') { e.preventDefault(); return; }
    e.preventDefault();

    var method = (form.getAttribute('method') || 'get').toUpperCase();
    var action = form.getAttribute('action') || location.href;
    var submitter = e.submitter || null;
    var init = { credentials: 'same-origin', redirect: 'follow' };
    var url = action;

    if (method === 'POST') {
      init.method = 'POST';
      init.body = formData(form, submitter);
    } else {
      init.method = 'GET';
      url = action + (action.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams(formData(form, submitter)).toString();
    }

    var stage = 'fetch';
    var finalUrl = url;

    busy(form, true);
    fetch(url, init).then(function (res) {
      return res.text().then(function (html) { return { res: res, html: html }; });
    }).then(function (r) {
      stage = 'swap';
      finalUrl = r.res.url || url;
      // answered by the server: show it as a page if we cannot render it in place
      if (!r.res.ok || pathOf(finalUrl) !== pathOf(location.href)) { location.replace(finalUrl); return; }
      busy(form, false);
      swap(r.html, finalUrl);
    }).catch(function (err) {
      console.error('[zion] ajax ' + stage + ' failed:', err && (err.stack || err.message || err));
      if (stage === 'fetch') {
        busy(form, false);
        HTMLFormElement.prototype.submit.call(form);
      } else {
        location.replace(finalUrl);
      }
    });
  }, false);
})();
