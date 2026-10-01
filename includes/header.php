<?php
/** Site header. Expects url(), cart_count(), current_user(). */

$u            = current_user();
$cartQty      = cart_count();
$currentFile  = basename($_SERVER['SCRIPT_NAME'] ?? '');
$query        = $_GET ?? [];

$active = static function (string $key) use ($currentFile, $query): string {
    $is = match ($key) {
        'home'        => $currentFile === 'index.php',
        'shop'        => $currentFile === 'shop.php' && !isset($query['dept']),
        'lingerie'    => $currentFile === 'shop.php' && ($query['dept'] ?? '') === 'lingerie',
        'instruments' => $currentFile === 'shop.php' && ($query['dept'] ?? '') === 'instruments',
        'church'      => ($currentFile === 'category.php' && ($query['slug'] ?? '') === 'church-worship')
                         || ($currentFile === 'shop.php' && ($query['cat'] ?? '') === 'church-worship'),
        'deals'       => $currentFile === 'shop.php' && ($query['sale'] ?? '') === '1',
        'cart'        => $currentFile === 'cart.php',
        'account'     => in_array($currentFile, ['account.php', 'orders.php', 'order.php', 'wishlist.php'], true),
        default       => false,
    };
    return $is ? 'text-primary font-bold' : '';
};

$navLink = static function (string $key, string $href, string $label) use ($active): string {
    $cls = trim('font-label-nav text-label-nav text-on-surface-variant hover:text-primary transition-colors uppercase tracking-wider ' . $active($key));
    return '<a class="' . e($cls) . '" href="' . e($href) . '">' . e($label) . '</a>';
};
?>
<header class="fixed top-0 left-0 w-full z-50 bg-surface-container-lowest/95 backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
  <div class="h-16 sm:h-20 max-w-[1360px] mx-auto px-margin flex items-center justify-between gap-3 sm:gap-space-lg">
    <div class="flex items-center gap-space-md shrink-0">
      <a class="flex items-center gap-3" href="<?= e(url('index.php')) ?>">
        <img alt="Zion Group of Companies" class="h-10 sm:h-12 w-auto object-contain" src="<?= e(url('assets/logo-mark.svg')) ?>"/>
        <span class="font-headline-sm text-headline-sm tracking-[0.14em] text-on-surface font-bold hidden sm:inline-block">ZION GROUPS</span>
        <span class="font-label-tag text-label-tag uppercase tracking-[0.2em] text-secondary hidden md:inline-block">of Companies</span>
      </a>
    </div>

    <nav class="hidden xl:flex items-center gap-space-lg">
      <?= $navLink('shop', url('shop.php'), 'Shop') ?>
      <?= $navLink('lingerie', url('shop.php?dept=lingerie'), 'Lingerie') ?>
      <?= $navLink('instruments', url('shop.php?dept=instruments'), 'Music & Audio') ?>
      <?= $navLink('church', url('category.php?slug=church-worship'), 'Church') ?>
      <?= $navLink('shop', url('shop.php?view=collections'), 'Collections') ?>
      <?= $navLink('deals', url('shop.php?sale=1'), 'Deals') ?>
    </nav>

    <div class="flex items-center gap-space-md flex-1 max-w-md justify-end">
      <form class="relative w-full max-w-xs hidden lg:block" action="<?= e(url('search.php')) ?>" method="get" role="search">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none">search</span>
        <input class="w-full pl-9 pr-3 py-2 bg-surface-container text-on-surface placeholder:text-outline font-body-sm text-body-sm rounded-lg outline-none focus:bg-surface-container-lowest transition-colors"
               placeholder="Search lingerie, instruments, audio..." type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>"/>
      </form>

      <div class="flex items-center gap-space-sm shrink-0">
        <button id="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="nav-drawer"
                class="xl:hidden p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors flex items-center justify-center">
          <span class="material-symbols-outlined text-xl">menu</span>
        </button>

        <button aria-label="Search" onclick="document.getElementById('mobile-search').classList.toggle('hidden')"
                class="lg:hidden p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors flex items-center justify-center">
          <span class="material-symbols-outlined text-xl">search</span>
        </button>

        <a aria-label="Wishlist" class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors hidden sm:flex items-center justify-center <?= e($active('account') && $currentFile === 'wishlist.php' ? 'text-primary' : '') ?>"
           href="<?= e(url('wishlist.php')) ?>">
          <span class="material-symbols-outlined text-xl">favorite</span>
        </a>

        <a aria-label="Account" class="p-1 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors hidden sm:flex items-center gap-2"
           href="<?= e(url($u ? 'account.php' : 'login.php')) ?>">
          <?php if ($u !== null): ?>
            <span class="w-8 h-8 rounded-full bg-primary-container text-on-primary font-label-nav text-label-nav font-bold flex items-center justify-center">
              <?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?>
            </span>
          <?php else: ?>
            <span class="material-symbols-outlined text-xl">account_circle</span>
          <?php endif; ?>
        </a>

        <a aria-label="Shopping Cart" class="relative p-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors flex items-center justify-center <?= e($active('cart')) ?>"
           href="<?= e(url('cart.php')) ?>">
          <span class="material-symbols-outlined text-xl">shopping_bag</span>
          <span data-cart-badge class="absolute top-1 right-1 bg-primary-container text-on-primary font-label-tag text-label-tag rounded-full min-w-4 h-4 px-1 flex items-center justify-center <?= $cartQty > 0 ? '' : 'hidden' ?>"><?= (int) $cartQty ?></span>
        </a>
      </div>
    </div>
  </div>

  <div id="mobile-search" class="hidden border-t border-outline-variant bg-surface-container-lowest px-margin py-3">
    <form action="<?= e(url('search.php')) ?>" method="get" role="search" class="max-w-[1360px] mx-auto flex gap-2">
      <input class="flex-1 px-4 py-2.5 bg-surface-container text-on-surface placeholder:text-outline font-body-sm text-body-sm rounded-lg outline-none"
             placeholder="Search lingerie, instruments, audio..." type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>"/>
      <button class="px-5 py-2.5 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-wider rounded-lg" type="submit">Search</button>
    </form>
  </div>
</header>

<!-- Mobile drawer: navigation + shop filters -->
<div id="nav-drawer" class="fixed inset-0 z-[70] invisible opacity-0 transition-opacity duration-300" role="dialog" aria-modal="true" aria-label="Menu">
  <div class="absolute inset-0 bg-inverse-surface/60 backdrop-blur-[2px]" data-drawer-close></div>
  <aside id="nav-drawer-panel"
         class="absolute inset-y-0 left-0 w-[min(88vw,360px)] bg-surface-container-lowest shadow-2xl flex flex-col -translate-x-full transition-transform duration-300 ease-out">
    <div class="h-20 px-5 flex items-center justify-between border-b border-outline-variant shrink-0">
      <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Menu</span>
      <button type="button" aria-label="Close menu" data-drawer-close
              class="p-2 -mr-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors flex items-center justify-center">
        <span class="material-symbols-outlined text-xl">close</span>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-5 flex flex-col gap-6">
      <nav class="flex flex-col gap-1">
        <?php foreach ([
            'home'        => [url('index.php'), 'Home'],
            'shop'        => [url('shop.php'), 'Shop All'],
            'lingerie'    => [url('shop.php?dept=lingerie'), 'Lingerie'],
            'instruments' => [url('shop.php?dept=instruments'), 'Music & Audio'],
            'church'      => [url('category.php?slug=church-worship'), 'Church & Worship'],
            'deals'       => [url('shop.php?sale=1'), 'Deals'],
        ] as $key => [$href, $label]): ?>
          <a class="py-2.5 font-label-nav text-label-nav uppercase tracking-wider border-b border-outline-variant/50 transition-colors <?= e($active($key) ?: 'text-on-surface-variant hover:text-primary') ?>"
             href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="py-2.5 font-label-nav text-label-nav uppercase tracking-wider border-b border-outline-variant/50 transition-colors <?= $currentFile === 'page.php' && ($_GET['slug'] ?? '') === 'contact' ? 'text-primary font-bold' : 'text-on-surface-variant hover:text-primary' ?>"
           href="<?= e(url('page.php?slug=contact')) ?>">Contact</a>
      </nav>

      <div>
        <div class="flex items-center gap-2 mb-3">
          <span class="material-symbols-outlined text-lg text-primary">tune</span>
          <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Shop Filters</span>
        </div>
        <?php shop_filters_form('d'); ?>
      </div>

      <div class="flex flex-col gap-1">
        <a class="py-2.5 flex items-center gap-2.5 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant hover:text-primary transition-colors"
           href="<?= e(url('wishlist.php')) ?>">
          <span class="material-symbols-outlined text-lg">favorite</span> Wishlist
        </a>
        <a class="py-2.5 flex items-center gap-2.5 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant hover:text-primary transition-colors"
           href="<?= e(url($u ? 'account.php' : 'login.php')) ?>">
          <span class="material-symbols-outlined text-lg">account_circle</span> <?= $u !== null ? 'My Account' : 'Sign In' ?>
        </a>
        <a class="py-2.5 flex items-center gap-2.5 font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant hover:text-primary transition-colors"
           href="<?= e(url('cart.php')) ?>">
          <span class="material-symbols-outlined text-lg">shopping_bag</span> Cart
          <span data-cart-badge class="ml-auto min-w-5 h-5 px-1.5 rounded-full bg-primary-container text-on-primary font-label-tag text-label-tag flex items-center justify-center <?= $cartQty > 0 ? '' : 'hidden' ?>"><?= (int) $cartQty ?></span>
        </a>
      </div>
    </div>
  </aside>
</div>

<script>
  (function () {
    var drawer = document.getElementById('nav-drawer');
    var panel  = document.getElementById('nav-drawer-panel');
    var btn    = document.getElementById('nav-toggle');
    if (!drawer || !panel || !btn) { return; }

    function setOpen(open) {
      drawer.classList.toggle('invisible', !open);
      drawer.classList.toggle('opacity-0', !open);
      panel.classList.toggle('-translate-x-full', !open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    }

    btn.addEventListener('click', function () {
      setOpen(drawer.classList.contains('invisible'));
    });
    drawer.addEventListener('click', function (e) {
      if (e.target && e.target.closest && e.target.closest('[data-drawer-close]')) { setOpen(false); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { setOpen(false); }
    });
  })();
</script>

<?php $flash = flash_all(); if ($flash !== []): ?>
<div class="fixed top-24 right-4 z-[60] flex flex-col gap-2 w-[min(92vw,380px)]">
  <?php foreach ($flash as $f): ?>
    <div class="rounded-lg shadow-lg px-4 py-3 font-body-sm text-body-sm flex items-start gap-2
      <?= $f['type'] === 'error' ? 'bg-error-container text-on-error-container'
          : ($f['type'] === 'info' ? 'bg-surface-container-high text-on-surface'
          : 'bg-primary-container text-on-primary') ?>">
      <span class="material-symbols-outlined text-base mt-0.5"><?= $f['type'] === 'error' ? 'error' : 'check_circle' ?></span>
      <span><?= e($f['message']) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
