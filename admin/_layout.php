<?php
/** Admin chrome: collapsible sidebar layout shared by every /admin screen. */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** Colour-coded chip for order / payment states. */
function status_pill(string $status): string
{
    $cls = match ($status) {
        'delivered', 'paid'  => 'bg-secondary-fixed text-on-secondary-fixed',
        'cancelled', 'failed' => 'bg-error-container text-on-error-container',
        'shipped', 'packing' => 'bg-primary-container text-on-primary',
        default              => 'bg-primary-fixed text-primary',
    };
    return '<span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded ' . $cls . '">'
        . e($status) . '</span>';
}

function admin_nav_items(): array
{
    return [
        'index'         => ['dashboard', 'Dashboard'],
        'products'      => ['category', 'Products'],
        'bulk_products' => ['dataset', 'Bulk Products'],
        'categories'    => ['list_alt', 'Categories'],
        'brands'        => ['sell', 'Brands'],
        'orders'        => ['receipt_long', 'Orders'],
        'reviews'       => ['rate_review', 'Reviews'],
        'customers'     => ['group', 'Customers'],
        'messages'      => ['mail', 'Messages'],
        'backup'        => ['database', 'Backup & Wipe'],
        'settings'      => ['settings', 'Settings'],
    ];
}

/** Unread contact messages, for the sidebar badge. */
function admin_unread_messages(): int
{
    try {
        return (int) db_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0', [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Reviews still waiting for moderation, for the sidebar badge. */
function admin_pending_reviews(): int
{
    try {
        return (int) db_val('SELECT COUNT(*) FROM reviews WHERE is_approved = 0', [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Local image upload widget: file picker + thumbnail + the stored path field.
 *
 * The path input keeps its normal name, so the surrounding form saves the
 * image exactly as before - uploads just fill it with a storage/uploads path.
 *
 * $opts: label, hint, wrap (grid classes), preview (thumb classes),
 *        input_class, autosubmit (submit the form once uploaded).
 */
function upload_field(string $name, string $value, array $opts = []): string
{
    $label   = $opts['label'] ?? 'Image';
    $hint    = $opts['hint'] ?? 'JPG, PNG, WebP or GIF up to 8 MB - stored in storage/uploads/.';
    $wrap    = $opts['wrap'] ?? '';
    $thumb   = $opts['preview'] ?? 'w-20 h-24';
    $inCls   = $opts['input_class'] ?? 'h-11';
    $auto    = !empty($opts['autosubmit']);
    $value   = trim($value);
    $has     = $value !== '';

    $out = '<div class="flex items-start gap-3' . ($wrap !== '' ? ' ' . e($wrap) : '') . '"'
        . ' data-upload-scope' . ($auto ? ' data-upload-autosubmit' : '') . '>';
    $out .= '<img data-upload-preview alt=""'
        . ' class="' . e($thumb) . ' object-cover rounded-lg border border-outline-variant bg-surface-container shrink-0'
        . ($has ? '' : ' hidden') . '"'
        . ($has ? ' src="' . e(img_url($value)) . '"' : '') . '/>';
    $out .= '<div class="flex-1 min-w-0 flex flex-col gap-1.5">';
    $out .= '<span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">' . e($label) . '</span>';
    $out .= '<input type="file" data-upload accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/bmp"'
        . ' class="block w-full font-body-sm text-body-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0'
        . ' file:bg-surface-container file:text-on-surface-variant hover:file:bg-surface-container-high file:cursor-pointer"/>';
    $out .= '<input type="text" data-upload-url name="' . e($name) . '" value="' . e($value) . '"'
        . ' placeholder="library path or image URL"'
        . ' class="' . e($inCls) . ' w-full px-3 border border-outline-variant rounded font-body-sm text-body-sm'
        . ' focus:border-primary outline-none"/>';
    $out .= '<div class="flex items-center gap-3 min-h-4">'
        . '<button type="button" data-upload-clear class="font-label-nav text-[11px] uppercase text-error hover:underline'
        . ($has ? '' : ' hidden') . '">Remove image</button>'
        . '<span data-upload-status class="text-[11px] text-on-surface-variant"></span>'
        . '</div>';
    $out .= '<span class="text-[11px] leading-4 text-outline">' . e($hint) . '</span>';
    $out .= '</div></div>';

    return $out;
}

function admin_head(string $title, string $active = ''): void
{
    set_title($title . ' | Zion Groups Admin');
    set_meta(null, null, true);  // back office is never indexed
    require __DIR__ . '/../includes/head.php';
    $items = admin_nav_items();
    ?>
<style>
  #admin-nav { width: 16rem; transition: width .2s ease; }
  /* keep the nav scrollable but hide its scrollbar */
  #admin-nav nav { scrollbar-width: none; -ms-overflow-style: none; }
  #admin-nav nav::-webkit-scrollbar { width: 0; height: 0; display: none; }
  #admin-nav .nav-label, #admin-nav .nav-tag { display: block; }
  #admin-nav .nav-link { position: relative; }
  body.nav-collapsed #admin-nav { width: 4.75rem; }
  body.nav-collapsed #admin-nav .nav-label,
  body.nav-collapsed #admin-nav .nav-tag { display: none; }
  body.nav-collapsed #admin-nav .nav-link { justify-content: center; gap: 0; padding-left: 0; padding-right: 0; }
  body.nav-collapsed #admin-nav .nav-brand { padding-left: .5rem; padding-right: .5rem; justify-content: center; }
  body.nav-collapsed #admin-nav .nav-brand > a { flex: 0 0 auto; justify-content: center; }
  body.nav-collapsed #admin-nav .nav-brand a .material-symbols-outlined { font-size: 24px; }
  body.nav-collapsed #admin-nav .nav-link:hover::after {
    content: attr(data-label);
    position: absolute; left: calc(100% + .6rem); top: 50%; transform: translateY(-50%);
    background: #1d1216; color: #fff; border: 1px solid rgba(255,255,255,.14);
    font-size: .68rem; letter-spacing: .08em; text-transform: uppercase;
    padding: .35rem .6rem; border-radius: .4rem; white-space: nowrap;
    z-index: 80; pointer-events: none;
  }
  #navBackdrop { display: none; }
  @media (max-width: 1023px) {
    #admin-nav {
      position: fixed; top: 0; bottom: 0; left: 0; z-index: 70;
      width: 16rem; transform: translateX(-100%);
      transition: transform .2s ease; box-shadow: 0 0 40px rgba(0,0,0,.45);
    }
    body.nav-open #admin-nav { transform: none; }
    body.nav-collapsed #admin-nav { width: 16rem; }
    body.nav-collapsed #admin-nav .nav-label,
    body.nav-collapsed #admin-nav .nav-tag { display: block; }
    body.nav-collapsed #admin-nav .nav-link { justify-content: flex-start; gap: .75rem; padding-left: .75rem; padding-right: .75rem; }
    body.nav-collapsed #admin-nav .nav-brand { padding-left: 1.25rem; padding-right: 1.25rem; justify-content: space-between; }
    body.nav-collapsed #admin-nav .nav-brand > a { flex: 1 1 auto; justify-content: flex-start; }
    body.nav-open #navBackdrop { display: block; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 65; }
  }
</style>
<script>
  try {
    if (localStorage.getItem('zion-admin-nav') === 'collapsed' && window.matchMedia('(min-width: 1024px)').matches) {
      document.body.classList.add('nav-collapsed');
    }
  } catch (e) {}
</script>
<div class="min-h-screen flex bg-surface-container">
  <aside id="admin-nav" class="shrink-0 bg-inverse-surface text-surface flex flex-col sticky top-0 h-screen">
    <div class="nav-brand px-5 py-4 border-b border-white/10 flex items-center gap-2">
      <a class="flex items-center gap-2 flex-1 min-w-0" href="<?= e(url('index.php')) ?>">
        <img alt="" class="w-8 h-8 shrink-0" src="<?= e(url('assets/logo-mark.svg')) ?>"/>
        <span class="nav-label min-w-0">
          <span class="font-headline-sm text-headline-sm tracking-widest font-bold block truncate">ZION GROUPS</span>
          <span class="nav-tag font-label-tag text-label-tag uppercase tracking-[0.18em] text-surface-dim">Retail Operations</span>
        </span>
      </a>
      <button id="navCollapse" type="button" aria-label="Collapse navigation"
              class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-surface-dim hover:bg-white/10 hover:text-surface transition-colors">
        <span class="material-symbols-outlined text-lg" data-nav-icon>left_panel_close</span>
      </button>
    </div>

    <nav class="flex-1 p-3 flex flex-col gap-1 overflow-y-auto">
      <?php foreach ($items as $key => [$icon, $label]): ?>
        <?php
        $isActive  = $active === $key;
        $badge     = match ($key) {
            'messages' => admin_unread_messages(),
            'reviews'  => admin_pending_reviews(),
            default    => 0,
        };
        $navTitle  = $badge > 0
            ? $label . ' (' . $badge . ($key === 'reviews' ? ' pending' : ' unread') . ')'
            : $label;
        ?>
        <a class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-body-sm text-body-sm transition-colors
                  <?= $isActive ? 'bg-primary-container text-on-primary font-bold' : 'text-surface-dim hover:bg-white/10 hover:text-surface' ?>"
           href="<?= e(url('admin/' . $key . '.php')) ?>" data-label="<?= e($navTitle) ?>" title="<?= e($navTitle) ?>">
          <span class="material-symbols-outlined text-lg shrink-0"><?= e($icon) ?></span>
          <span class="nav-label flex-1"><?= e($label) ?></span>
          <?php if ($badge > 0): ?>
            <span class="nav-label min-w-4 h-4 px-1 rounded-full bg-error-container text-on-error-container font-label-tag text-label-tag flex items-center justify-center"><?= $badge > 99 ? '99+' : $badge ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="nav-foot p-3 border-t border-white/10 flex flex-col gap-1">
      <a class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-body-sm text-body-sm text-surface-dim hover:bg-white/10"
         href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener" data-label="View storefront">
        <span class="material-symbols-outlined text-lg shrink-0">open_in_new</span>
        <span class="nav-label">View storefront</span>
      </a>
      <a class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg font-body-sm text-body-sm text-surface-dim hover:bg-white/10"
         href="<?= e(url('admin/logout.php')) ?>" data-label="Sign out">
        <span class="material-symbols-outlined text-lg shrink-0">logout</span>
        <span class="nav-label">Sign out</span>
      </a>
    </div>
  </aside>

  <div id="navBackdrop"></div>

  <main class="flex-1 min-w-0 p-space-md" data-ajax-out>
    <button id="navOpen" type="button"
            class="lg:hidden mb-4 px-4 py-2 inline-flex items-center gap-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface hover:bg-surface-container-lowest transition-colors">
      <span class="material-symbols-outlined text-lg">menu</span> Menu
    </button>
    <?php foreach (flash_all() as $f): ?>
      <?php $cls = match ($f['type']) {
          'error'   => 'bg-error-container text-on-error-container',
          'info'    => 'bg-primary-fixed text-primary',
          default   => 'bg-secondary-fixed text-on-secondary-fixed',
      }; ?>
      <div class="mb-4 px-4 py-3 rounded-lg font-body-sm text-body-sm flex items-center gap-2 <?= $cls ?>">
        <span class="material-symbols-outlined text-base">info</span><?= e($f['message']) ?>
      </div>
    <?php endforeach; ?>
    <?php
}

function admin_foot(): void
{
    ?>
    </main>
  </div>
  <script>
    (function () {
      var KEY = 'zion-admin-nav';
      var mq = window.matchMedia('(min-width: 1024px)');

      function icon() {
        var btn = document.getElementById('navCollapse');
        var i = btn && btn.querySelector('[data-nav-icon]');
        if (i) { i.textContent = document.body.classList.contains('nav-collapsed') ? 'left_panel_open' : 'left_panel_close'; }
      }

      function sync() {
        var body = document.body;
        var stored = null;
        try { stored = localStorage.getItem(KEY); } catch (e) {}
        if (mq.matches) {
          body.classList.toggle('nav-collapsed', stored === 'collapsed');
        } else {
          body.classList.remove('nav-collapsed');
          body.classList.remove('nav-open');
        }
        icon();
      }

      // Delegated: #navOpen lives inside [data-ajax-out] and is replaced on
      // every form[data-ajax] save, which would drop direct listeners.
      document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) { return; }
        var body = document.body;
        if (e.target.closest('#navCollapse')) {
          if (mq.matches) {
            var collapsed = !body.classList.contains('nav-collapsed');
            body.classList.toggle('nav-collapsed', collapsed);
            try { localStorage.setItem(KEY, collapsed ? 'collapsed' : 'expanded'); } catch (err) {}
            icon();
          } else {
            body.classList.remove('nav-open');
          }
          return;
        }
        if (e.target.closest('#navOpen')) {
          body.classList.add('nav-open');
          return;
        }
        if (e.target.closest('#navBackdrop')) {
          body.classList.remove('nav-open');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.body.classList.remove('nav-open'); }
      });
      document.addEventListener('ajax:done', sync);
      if (mq.addEventListener) { mq.addEventListener('change', sync); }
      else if (mq.addListener) { mq.addListener(sync); }
      sync();
      var aside = document.getElementById('admin-nav');
      if (aside) { aside.removeAttribute('hidden'); }
    })();
  </script>
  <script>window.ZION_UPLOAD = <?= json_encode(url('admin/upload.php'), JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="<?= e(url('assets/upload.js')) ?>"></script>
  <script src="<?= e(url('assets/ajax.js')) ?>"></script>
</body>
</html>
    <?php
}
