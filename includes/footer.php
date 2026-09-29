<?php /** Site footer: brand + contact, labelled link groups, social, legal line. */ ?>
<footer class="w-full bg-inverse-surface text-surface border-t border-white/10">
  <div class="max-w-[1360px] mx-auto px-margin py-space-md">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-x-space-lg gap-y-space-sm items-start">
      <!-- brand & contact -->
      <div class="sm:col-span-2 lg:col-span-4 flex flex-col gap-1">
        <div class="flex items-center gap-2.5 flex-wrap">
          <img alt="Zion Group of Companies" class="h-12 w-auto shrink-0" src="<?= e(url('assets/logo-mark.svg')) ?>"/>
          <span class="font-headline-sm text-headline-sm tracking-widest text-surface font-bold">ZION GROUPS</span>
          <span class="font-label-tag text-label-tag uppercase tracking-[0.25em] text-secondary-fixed">of Companies</span>
        </div>
        <p class="font-title-editorial text-title-editorial text-secondary-fixed italic"><?= e(setting('site_tagline')) ?></p>
        <p class="font-body-sm text-body-sm text-surface-dim max-w-sm line-clamp-2"><?= e(setting('footer_about')) ?></p>

        <div class="flex flex-wrap gap-x-4 gap-y-1 font-body-sm text-body-sm text-surface-dim">
          <a class="flex items-center gap-1.5 hover:text-surface transition-colors" href="<?= e(contact_phone_href()) ?>">
            <span class="material-symbols-outlined text-base text-secondary-fixed">call</span><?= e(setting('contact_phone')) ?>
          </a>
          <a class="flex items-center gap-1.5 hover:text-surface transition-colors" href="<?= e('mailto:' . setting('contact_email')) ?>">
            <span class="material-symbols-outlined text-base text-secondary-fixed">mail</span><?= e(setting('contact_email')) ?>
          </a>
          <a class="flex items-center gap-1.5 hover:text-surface transition-colors" href="<?= e(url('page.php?slug=contact')) ?>">
            <span class="material-symbols-outlined text-base text-secondary-fixed">location_on</span><?= e(setting('address_line')) ?>
          </a>
        </div>
      </div>

      <!-- link groups: labelled, 2-up on phones, 4-up on wider screens -->
      <nav class="sm:col-span-2 lg:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-x-space-lg gap-y-space-md font-body-sm text-body-sm text-surface-dim" aria-label="Footer">
        <div class="flex flex-col gap-1.5">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary-fixed">Quick Links</span>
          <a class="hover:text-surface transition-colors" href="<?= e(url('shop.php')) ?>">Shop</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('shop.php?dept=lingerie')) ?>">Lingerie</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('shop.php?dept=instruments')) ?>">Instruments</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('shop.php?view=collections')) ?>">Collections</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('shop.php?sale=1')) ?>">Deals</a>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary-fixed">Help</span>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=shipping-and-returns')) ?>">Shipping &amp; Returns</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=faq')) ?>">FAQ</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=contact')) ?>">Contact Us</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=size-guide')) ?>">Size Guide</a>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary-fixed">Company</span>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=about-us')) ?>">About Us</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=our-story')) ?>">Our Story</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=privacy-policy')) ?>">Privacy Policy</a>
          <a class="hover:text-surface transition-colors" href="<?= e(url('page.php?slug=terms')) ?>">Terms</a>
        </div>

        <!-- social + concierge -->
        <div class="flex flex-col gap-2">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary-fixed">Follow Us</span>
          <div class="flex flex-wrap items-center gap-2">
            <?php
            $socials = [
                'instagram' => ['Instagram', 'photo_camera'],
                'tiktok'    => ['TikTok', 'music_note'],
                'facebook'  => ['Facebook', 'thumb_up'],
                'youtube'   => ['YouTube', 'smart_display'],
            ];
            foreach ($socials as $key => [$label, $icon]):
                $href = trim(setting('social_' . $key));
                if ($href === '') {
                    continue;
                }
                ?>
              <a class="w-8 h-8 rounded-full border border-white/15 flex items-center justify-center text-surface-dim hover:text-surface hover:border-secondary-fixed transition-colors"
                 href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>">
                <span class="material-symbols-outlined text-base"><?= e($icon) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
          <a class="inline-flex w-fit items-center gap-1.5 rounded-full border border-white/15 px-3 py-1 font-body-sm text-body-sm text-surface-dim hover:text-surface hover:border-secondary-fixed transition-colors"
             href="<?= e(whatsapp_url('Hello Zion Groups, I would like to ask about an order.')) ?>" rel="noopener noreferrer" target="_blank">
            <span class="material-symbols-outlined text-base">chat</span>WhatsApp Concierge
          </a>
        </div>
      </nav>
    </div>

    <!-- legal line -->
    <div class="mt-3 pt-3 border-t border-white/10 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 font-body-sm text-body-sm text-surface-dim">
      <p>&copy; <?= date('Y') ?> <?= e(setting('site_name')) ?>. All rights reserved.</p>
      <p class="text-surface-dim/80"><?= e(setting('address_city')) ?></p>
    </div>
  </div>
</footer>
<script>
(function () {
  // only fade the server-rendered flash messages that exist right now
  var flashes = Array.prototype.slice.call(document.querySelectorAll('.fixed.top-24.right-4 > div'));
  setTimeout(function () {
    flashes.forEach(function (el) {
      if (!el.isConnected || el.hasAttribute('data-auto')) { return; }
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { if (el.isConnected) { el.remove(); } }, 450);
    });
  }, 4000);
})();
</script>
<script>
(function () {
  var ACTIONS = <?= json_encode(url('actions.php')) ?>;
  var WISHLIST_STATE = <?= json_encode(url('wishlist_state.php')) ?>;

  function toast(message, type) {
    var box = document.querySelector('.fixed.top-24.right-4');
    if (!box) {
      box = document.createElement('div');
      box.className = 'fixed top-24 right-4 z-[60] flex flex-col gap-2 w-[min(92vw,380px)]';
      document.body.appendChild(box);
    }
    var el = document.createElement('div');
    el.setAttribute('data-auto', '1');
    el.className = 'rounded-lg shadow-lg px-4 py-3 font-body-sm text-body-sm flex items-start gap-2 ' +
      (type === 'error' ? 'bg-error-container text-on-error-container' : 'bg-primary-container text-on-primary');
    var icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-base mt-0.5';
    icon.textContent = type === 'error' ? 'error' : 'check_circle';
    var text = document.createElement('span');
    text.textContent = message;
    el.appendChild(icon);
    el.appendChild(text);
    box.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 450);
    }, 3500);
  }

  function paintWish(form, added) {
    var icon = form.querySelector('[data-wish-icon]');
    if (icon) { icon.textContent = added ? 'favorite' : 'favorite_border'; }
    var label = form.querySelector('[data-wish-label]');
    if (label) { label.textContent = added ? 'Saved to Wishlist' : 'Save to Wishlist'; }
    var btn = form.querySelector('[data-wish-btn]');
    if (btn) {
      btn.classList.toggle('text-primary', added);
      btn.classList.toggle('text-on-surface-variant', !added);
      btn.setAttribute('aria-pressed', added ? 'true' : 'false');
      if (btn.hasAttribute('aria-label')) {
        btn.setAttribute('aria-label', added ? 'Remove from Wishlist' : 'Add to Wishlist');
      }
    }
  }

  /** Paint every card of a product: one item can appear more than once on a page. */
  function paintProduct(productId, added) {
    var forms = document.querySelectorAll('form[data-wishlist]');
    for (var i = 0; i < forms.length; i++) {
      var field = forms[i].querySelector('input[name="product_id"]');
      if (field && Number(field.value) === Number(productId)) { paintWish(forms[i], added); }
    }
  }

  // Re-read the wishlist from the server when the DOM may be stale: a page
  // restored from the back/forward cache, or a tab coming back into view after
  // the shopper saved an item somewhere else.
  var wishSyncedAt = 0;
  function syncWishlist(force) {
    var forms = document.querySelectorAll('form[data-wishlist]');
    if (!forms.length) { return; }
    var now = Date.now();
    if (!force && now - wishSyncedAt < 4000) { return; }
    wishSyncedAt = now;
    fetch(WISHLIST_STATE, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    }).then(function (res) {
      return res.ok ? res.json() : null;
    }).then(function (data) {
      if (!data || !Array.isArray(data.ids)) { return; }
      var live = document.querySelectorAll('form[data-wishlist]');
      for (var i = 0; i < live.length; i++) {
        var field = live[i].querySelector('input[name="product_id"]');
        if (!field) { continue; }
        paintWish(live[i], data.ids.indexOf(Number(field.value)) !== -1);
      }
    }).catch(function () { /* offline / endpoint blocked: keep what the server rendered */ });
  }

  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { syncWishlist(true); }
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { syncWishlist(false); }
  });

  // ---- shared helpers --------------------------------------------------
  function postForm(form) {
    // NB: form.action is shadowed by <input name="action"> (LegacyOverrideBuiltIns)
    var url = form.getAttribute('action') || location.href;
    return fetch(url, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (res) {
      var type = res.headers.get('content-type') || '';
      if (!res.ok || type.indexOf('application/json') === -1) { throw new Error('non-json response'); }
      return res.json();
    });
  }

  /** No network / unexpected response: do the original full-page POST. */
  function normalSubmit(form, err) {
    if (err) { console.error('[zion] ajax fallback:', (err && err.stack) ? err.stack : err); }
    HTMLFormElement.prototype.submit.call(form);
  }

  function setBadge(count) {
    document.querySelectorAll('[data-cart-badge]').forEach(function (b) {
      b.textContent = count;
      b.classList.toggle('hidden', !count);
    });
  }

  function setText(sel, value) {
    var el = document.querySelector(sel);
    if (el) { el.textContent = value; }
  }

  function removeCard(el) {
    el.style.transition = 'opacity .25s';
    el.style.opacity = '0';
    setTimeout(function () { el.remove(); }, 260);
  }

  /** Apply a cart_payload() response to the bag page in place. */
  function patchCart(data) {
    if (!data) { return; }
    setBadge(data.count);

    var s = data.summary || {};
    setText('[data-sum="subtotal"]', s.subtotal);
    setText('[data-sum="shipping"]', s.shipping);
    setText('[data-sum="total"]', s.total);

    var voucherRow = document.querySelector('[data-voucher-row]');
    if (voucherRow) {
      voucherRow.classList.toggle('hidden', !s.has_discount);
      setText('[data-sum="voucher-code"]', s.voucher || '');
      setText('[data-sum="discount"]', s.discount);
    }

    var hint = document.querySelector('[data-freehint]');
    if (hint) {
      hint.classList.toggle('hidden', !!s.free_ship);
      setText('[data-sum="gap"]', s.gap);
    }

    var lines = data.lines || {};
    document.querySelectorAll('[data-line-total]').forEach(function (el) {
      var line = lines[el.getAttribute('data-line-total')];
      if (line) { el.textContent = line.line_total; }
    });
    document.querySelectorAll('[data-line]').forEach(function (el) {
      if (!lines[el.getAttribute('data-line')]) { removeCard(el); }
    });

    setText('[data-count]', '(' + data.count + ' item' + (data.count === 1 ? '' : 's') + ')');

    if (data.promo_html) {
      var block = document.querySelector('[data-promo-block]');
      if (block) {
        var wrap = document.createElement('div');
        wrap.innerHTML = data.promo_html;
        if (wrap.firstElementChild) { block.replaceWith(wrap.firstElementChild); }
      }
    }
  }

  // ---- live cart sync (steppers, typed quantities, removes) ------------
  var syncTimer = null;
  var syncing = false;
  var pendingForm = null;

  function syncCart(form) {
    if (!form) { return; }
    if (syncing) { pendingForm = form; return; }
    syncing = true;
    postForm(form).then(function (data) {
      syncing = false;
      if (!data || data.ok !== true) {
        toast((data && data.message) || 'Could not update your bag.', 'error');
      } else {
        patchCart(data);
        if (data.count === 0) { window.location.reload(); return; }
        toast(data.message, 'success');
      }
      if (pendingForm) { var next = pendingForm; pendingForm = null; syncCart(next); }
    }).catch(function (err) {
      syncing = false;
      pendingForm = null;
      normalSubmit(form, err);
    });
  }

  function queueSync(form) {
    clearTimeout(syncTimer);
    syncTimer = setTimeout(function () { syncCart(form); }, 250);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-step]') : null;
    if (!btn) { return; }
    var input = document.querySelector('[data-qty-input="' + btn.dataset.target + '"]');
    if (!input || !input.form) { return; }
    e.preventDefault();
    var max = Number(input.max || 99);
    var next = Math.max(0, Math.min(max, Number(input.value) + Number(btn.dataset.step)));
    if (next === Number(input.value)) { return; }
    input.value = next;
    queueSync(input.form);
  });

  document.addEventListener('change', function (e) {
    var input = e.target;
    if (input && input.matches && input.matches('[data-qty-input]') && input.form) {
      queueSync(input.form);
    }
  });

  function busy(btn, on) {
    if (!btn) { return; }
    btn.disabled = on;
    btn.style.opacity = on ? '.75' : '';
  }

  // ---- submit dispatch -------------------------------------------------
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.matches) { return; }

    // add to cart (product page + quick add on cards)
    if (form.matches('form[data-add]')) {
      e.preventDefault();
      var addBtn = form.querySelector('[type="submit"]');
      var original = addBtn ? addBtn.innerHTML : '';
      busy(addBtn, true);
      postForm(form).then(function (data) {
        busy(addBtn, false);
        if (!data || data.ok !== true) {
          toast((data && data.message) || 'Could not add that item.', 'error');
          return;
        }
        setBadge(data.count);
        toast(data.message, 'success');
        if (addBtn) {
          addBtn.innerHTML = '<span class="material-symbols-outlined text-base">check</span> Added';
          setTimeout(function () { addBtn.innerHTML = original; }, 1500);
        }
      }).catch(function (err) { busy(addBtn, false); normalSubmit(form, err); });
      return;
    }

    // voucher apply / clear
    if (form.matches('form[data-promo]')) {
      e.preventDefault();
      var promoBtn = form.querySelector('[type="submit"]');
      busy(promoBtn, true);
      postForm(form).then(function (data) {
        busy(promoBtn, false);
        if (!data || data.ok !== true) {
          toast((data && data.message) || 'That voucher is not valid or has expired.', 'error');
          return;
        }
        patchCart(data);
        toast(data.message, 'success');
      }).catch(function (err) { busy(promoBtn, false); normalSubmit(form, err); });
      return;
    }

    // remove a bag line
    if (form.matches('form[data-cart-remove]')) {
      e.preventDefault();
      var idInput = form.querySelector('input[name="cart_id"]');
      var card = form.closest('[data-line]')
        || (idInput ? document.querySelector('[data-line="' + idInput.value + '"]') : null);
      postForm(form).then(function (data) {
        if (!data || data.ok !== true) {
          toast((data && data.message) || 'Could not remove that item.', 'error');
          return;
        }
        patchCart(data);
        if (card) { removeCard(card); }
        toast(data.message, 'success');
        if (data.count === 0) { setTimeout(function () { window.location.reload(); }, 350); }
      }).catch(function (err) { normalSubmit(form, err); });
      return;
    }

    // quantity form submitted directly (Enter key in a qty field)
    if (form.matches('form[data-cart-update]')) {
      e.preventDefault();
      clearTimeout(syncTimer);
      syncCart(form);
      return;
    }

    // product review
    if (form.matches('form[data-review-form]')) {
      e.preventDefault();
      var revBtn = form.querySelector('[type="submit"]');
      busy(revBtn, true);
      postForm(form).then(function (data) {
        busy(revBtn, false);
        if (!data || data.ok !== true) {
          toast((data && data.message) || 'Could not post your review.', 'error');
          return;
        }
        toast(data.message, 'success');
        var note = document.createElement('div');
        note.className = 'flex items-start gap-2.5 bg-secondary-fixed text-on-secondary-fixed rounded-lg p-4 font-body-sm text-body-sm';
        var icon = document.createElement('span');
        icon.className = 'material-symbols-outlined text-base mt-0.5';
        icon.textContent = 'fact_check';
        var text = document.createElement('span');
        text.textContent = data.message;
        note.appendChild(icon);
        note.appendChild(text);
        form.replaceWith(note);
      }).catch(function (err) { busy(revBtn, false); normalSubmit(form, err); });
      return;
    }

    // newsletter
    if (form.matches('form[data-newsletter]')) {
      e.preventDefault();
      var mailBtn = form.querySelector('[type="submit"]');
      busy(mailBtn, true);
      postForm(form).then(function (data) {
        busy(mailBtn, false);
        if (!data || data.ok !== true) {
          toast((data && data.message) || 'Please enter a valid email address.', 'error');
          return;
        }
        toast(data.message, 'success');
        var field = form.querySelector('input[name="email"]');
        if (field) { field.value = ''; }
      }).catch(function (err) { busy(mailBtn, false); normalSubmit(form, err); });
      return;
    }

    // wishlist (existing behaviour)
    if (!form.matches('form[data-wishlist]')) { return; }
    e.preventDefault();

    var btn = form.querySelector('[data-wish-btn]');
    if (btn) { btn.disabled = true; }

    postForm(form).then(function (data) {
      if (btn) { btn.disabled = false; }
      if (!data || data.ok !== true) {
        if (data && data.needs_login && data.login) {
          window.location.href = data.login;
          return;
        }
        toast((data && data.message) || 'Something went wrong. Please try again.', 'error');
        return;
      }
      var pidField = form.querySelector('input[name="product_id"]');
      paintProduct(pidField ? Number(pidField.value) : 0, !!data.added);
      toast(data.message, 'success');
      // on the wishlist screen, drop the card when an item is unsaved
      if (!data.added && /(^|\/)wishlist\.php$/.test(location.pathname)) {
        var card = form.closest('article');
        if (card) { removeCard(card); }
      }
      document.dispatchEvent(new CustomEvent('wishlist:changed', { detail: data }));
    }).catch(function (err) {
      if (btn) { btn.disabled = false; }
      // no network / non-JSON response: fall back to a normal POST
      normalSubmit(form, err);
    });
  });
})();
</script>
<script src="<?= e(url('assets/ajax.js')) ?>"></script>
</body>
</html>
