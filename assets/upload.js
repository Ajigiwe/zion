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
})();
