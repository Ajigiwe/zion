/**
 * Zion local image uploads (admin).
 *
 * Any input[type=file][data-upload] inside a [data-upload-scope] posts the
 * chosen file to window.ZION_UPLOAD, then:
 *   - writes the returned library path into [data-upload-url] (the field the
 *     form already saves), so nothing else in the stack changes;
 *   - swaps it into the [data-upload-preview] thumbnail;
 *   - optionally submits the surrounding form ([data-upload-autosubmit]).
 */
(function () {
  'use strict';

  function token() {
    var el = document.querySelector('input[name=csrf]');
    return el ? el.value : '';
  }

  function status(scope, message, isError) {
    var el = scope.querySelector('[data-upload-status]');
    if (!el) { return; }
    el.textContent = message || '';
    el.className = isError
      ? 'text-[11px] text-error'
      : 'text-[11px] text-on-surface-variant';
  }

  function previewOf(scope) { return scope.querySelector('[data-upload-preview]'); }
  function targetOf(scope) { return scope.querySelector('[data-upload-url]'); }
  function clearBtnOf(scope) { return scope.querySelector('[data-upload-clear]'); }

  function apply(scope, path, url) {
    var target = targetOf(scope);
    if (target) {
      target.value = path;
      try { target.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
    }
    var preview = previewOf(scope);
    if (preview) {
      preview.src = url;
      preview.classList.remove('hidden');
    }
    var clear = clearBtnOf(scope);
    if (clear) { clear.classList.remove('hidden'); }

    if (scope.hasAttribute('data-upload-autosubmit')) {
      var form = scope.closest('form');
      if (form && form.matches && form.matches('form[data-ajax]')) {
        status(scope, 'Saved.', false);
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
        else { HTMLFormElement.prototype.submit.call(form); }
        return;
      }
    }
    status(scope, 'Uploaded ' + path + ' - save to apply.', false);
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || !input.matches || !input.matches('input[type=file][data-upload]')) { return; }
    var file = input.files && input.files[0];
    if (!file) { return; }
    var scope = input.closest('[data-upload-scope]');
    if (!scope) { return; }
    if (!window.ZION_UPLOAD) {
      status(scope, 'Upload endpoint is not configured.', true);
      return;
    }

    status(scope, 'Uploading ' + file.name + '...', false);
    var fd = new FormData();
    fd.append('file', file);
    fd.append('csrf', token());

    fetch(window.ZION_UPLOAD, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (json) {
        return { status: res.status, json: json };
      });
    }).then(function (r) {
      var json = r.json;
      if (r.status !== 200 || !json || json.ok !== true) {
        status(scope, (json && json.error)
          || (json ? 'Upload was rejected.' : 'Session expired - refresh the page and try again.'), true);
        input.value = '';   // allow picking the same file again
        return;
      }
      input.value = '';
      apply(scope, json.path, json.url);
    }).catch(function (err) {
      status(scope, 'Upload failed: ' + (err && err.message ? err.message : err), true);
      input.value = '';
    });
  });

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest && e.target.closest('[data-upload-clear]');
    if (!btn) { return; }
    var scope = btn.closest('[data-upload-scope]');
    if (!scope) { return; }
    var target = targetOf(scope);
    if (target) {
      target.value = '';
      try { target.dispatchEvent(new Event('input', { bubbles: true })); } catch (err) {}
    }
    var input = scope.querySelector('input[type=file][data-upload]');
    if (input) { input.value = ''; }
    var preview = previewOf(scope);
    if (preview) { preview.removeAttribute('src'); preview.classList.add('hidden'); }
    btn.classList.add('hidden');
    status(scope, 'Image cleared - save to apply.', false);
  });

  /* ------------------------------------------------ gallery multi-upload */

  var GALLERY_MAX = 12;

  function gStatus(mgr, message, isError) {
    var el = mgr.querySelector('[data-gallery-status]');
    if (!el) { return; }
    el.textContent = message || '';
    el.className = isError
      ? 'text-[11px] text-error'
      : 'text-[11px] text-on-surface-variant';
  }

  function gListOf(mgr) { return mgr.querySelector('[data-gallery-list]'); }

  /** Only the first item carries the MAIN badge. */
  function gRefreshBadges(list) {
    var items = list.querySelectorAll('[data-gallery-item]');
    for (var i = 0; i < items.length; i++) {
      var badge = items[i].querySelector('[data-gallery-badge]');
      if (badge) { badge.classList.toggle('hidden', i !== 0); }
    }
  }

  function gAppendItem(list, path, thumbUrl) {
    var wrap = document.createElement('div');
    wrap.setAttribute('data-gallery-item', '');
    wrap.className = 'relative rounded-lg overflow-hidden border border-outline-variant bg-surface-container aspect-square';

    var img = document.createElement('img');
    img.className = 'w-full h-full object-cover';
    img.alt = '';
    img.loading = 'lazy';
    img.src = thumbUrl;
    wrap.appendChild(img);

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'gallery_urls[]';
    hidden.value = path;
    wrap.appendChild(hidden);

    var badge = document.createElement('span');
    badge.setAttribute('data-gallery-badge', '');
    badge.className = 'absolute top-1 left-1 px-1.5 py-0.5 rounded bg-primary-container text-on-primary font-label-tag text-label-tag uppercase font-bold hidden';
    badge.textContent = 'Main';
    wrap.appendChild(badge);

    var bar = document.createElement('div');
    bar.className = 'absolute bottom-1 inset-x-1 flex gap-1';

    var main = document.createElement('button');
    main.type = 'button';
    main.setAttribute('data-gallery-main', '');
    main.title = 'Make main image';
    main.className = 'flex-1 px-1 py-1 rounded bg-inverse-surface/85 text-surface font-label-tag text-label-tag uppercase hover:bg-primary-container hover:text-on-primary transition-colors';
    main.textContent = 'Main';
    bar.appendChild(main);

    var rm = document.createElement('button');
    rm.type = 'button';
    rm.setAttribute('data-gallery-remove', '');
    rm.title = 'Remove image';
    rm.setAttribute('aria-label', 'Remove image');
    rm.className = 'px-2 py-1 rounded bg-inverse-surface/85 text-surface hover:bg-error-container hover:text-on-error-container transition-colors';
    rm.textContent = '\u00D7';
    bar.appendChild(rm);

    wrap.appendChild(bar);
    list.appendChild(wrap);
    gRefreshBadges(list);
  }

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || !input.matches || !input.matches('input[type=file][data-gallery-upload]')) { return; }
    var mgr = input.closest('[data-gallery-manager]');
    var list = mgr && gListOf(mgr);
    var files = input.files ? Array.prototype.slice.call(input.files) : [];
    input.value = '';
    if (!mgr || !list || files.length === 0) { return; }
    if (!window.ZION_UPLOAD) {
      gStatus(mgr, 'Upload endpoint is not configured.', true);
      return;
    }
    var room = GALLERY_MAX - list.querySelectorAll('[data-gallery-item]').length;
    if (room <= 0) {
      gStatus(mgr, 'Gallery is full (' + GALLERY_MAX + ' images). Remove one first.', true);
      return;
    }
    files = files.slice(0, room);
    var total = files.length;
    var done = 0;
    var failed = 0;

    function uploadOne(i) {
      if (i >= total) {
        gStatus(mgr, failed === 0
          ? 'Uploaded ' + done + ' image(s) - save to apply.'
          : 'Uploaded ' + done + ', ' + failed + ' failed.', failed !== 0);
        return;
      }
      gStatus(mgr, 'Uploading ' + (i + 1) + '/' + total + '...', false);
      var fd = new FormData();
      fd.append('file', files[i]);
      fd.append('csrf', token());
      fetch(window.ZION_UPLOAD, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (res) {
        return res.json().catch(function () { return null; }).then(function (json) {
          return { status: res.status, json: json };
        });
      }).then(function (r) {
        var json = r.json;
        if (r.status === 200 && json && json.ok === true) {
          gAppendItem(list, json.path, json.url);
          done++;
        } else {
          failed++;
        }
        uploadOne(i + 1);
      }).catch(function () {
        failed++;
        uploadOne(i + 1);
      });
    }
    uploadOne(0);
  });

  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) { return; }
    var mgr = e.target.closest('[data-gallery-manager]');
    if (!mgr) { return; }
    var list = gListOf(mgr);
    if (!list) { return; }

    var rm = e.target.closest('[data-gallery-remove]');
    if (rm) {
      var item = rm.closest('[data-gallery-item]');
      if (item && item.parentNode === list) { list.removeChild(item); }
      gRefreshBadges(list);
      gStatus(mgr, 'Image removed - save to apply.', false);
      return;
    }

    var mk = e.target.closest('[data-gallery-main]');
    if (mk) {
      var cur = mk.closest('[data-gallery-item]');
      if (cur && cur.parentNode === list && cur !== list.firstElementChild) {
        list.insertBefore(cur, list.firstElementChild);
        // Keep the Main image field in step so products.image_url matches.
        var hidden = cur.querySelector('input[name="gallery_urls[]"]');
        var thumb = cur.querySelector('img');
        var form = mgr.closest('form');
        var mainField = form && form.querySelector('[data-upload-scope] [data-upload-url]');
        if (hidden && mainField) {
          mainField.value = hidden.value;
          try { mainField.dispatchEvent(new Event('input', { bubbles: true })); } catch (err) {}
          var scope = mainField.closest('[data-upload-scope]');
          var prev = scope && scope.querySelector('[data-upload-preview]');
          if (prev && thumb && thumb.src) {
            prev.src = thumb.src;
            prev.classList.remove('hidden');
          }
          var clear = scope && scope.querySelector('[data-upload-clear]');
          if (clear) { clear.classList.remove('hidden'); }
        }
      }
      gRefreshBadges(list);
      gStatus(mgr, 'Main image set - save to apply.', false);
    }
  });
})();
