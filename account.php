<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_login();

$u = current_user();
$addresses = db_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC', [(int) $u['id']]);

$orderStats = db_one(
    'SELECT COUNT(*) AS total,
            SUM(status = "delivered") AS delivered,
            SUM(status IN ("pending","confirmed","packing","shipped")) AS active
     FROM orders WHERE user_id = ?',
    [(int) $u['id']],
    ['total' => 0, 'delivered' => 0, 'active' => 0]
);
$lifetime = (float) db_val('SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = ? AND payment_status = "paid"', [(int) $u['id']], 0);
$wishCount = (int) db_val('SELECT COUNT(*) FROM wishlist WHERE user_id = ?', [(int) $u['id']], 0);
$addressCount = count($addresses);

$regions = [
    'Greater Accra Region', 'Ashanti Region (Kumasi Metro)', 'Western Region (Takoradi)',
    'Central Region (Cape Coast)', 'Eastern Region (Koforidua)', 'Volta Region (Ho)',
    'Northern Region (Tamale)',
];

set_title('My Account | Zion Groups');
set_meta(null, null, true);  // account/order pages are noindex
render_head();

$tabs = [
    'overview'   => ['dashboard', 'Dashboard Overview'],
    'orders'     => ['package_2', 'Order History'],
    'wishlist'   => ['favorite', 'My Wishlist'],
    'addresses'  => ['pin_drop', 'Delivery Addresses'],
    'details'    => ['manage_accounts', 'Personal Details'],
    'security'   => ['lock_reset', 'Security & Privacy'],
];
$tab = (string) ($_GET['tab'] ?? 'overview');
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]" data-ajax-out>
  <div class="max-w-[1360px] mx-auto px-margin py-space-lg">

    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold">My Account</span>
      <span class="text-outline-variant">/</span>
      <span class="text-on-surface"><?= e($tabs[$tab][1]) ?></span>
    </nav>

    <div class="flex flex-col lg:flex-row gap-space-lg items-start">
      <!-- sidebar -->
      <aside class="lg:w-72 shrink-0 flex flex-col gap-space-md lg:sticky lg:top-24 w-full">
        <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
          <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-full bg-primary-container text-on-primary font-headline-sm text-headline-sm font-bold flex items-center justify-center">
              <?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?>
            </span>
            <div class="min-w-0">
              <p class="font-headline-sm text-headline-sm text-on-surface font-bold truncate"><?= e($u['name']) ?></p>
              <p class="font-body-sm text-body-sm text-on-surface-variant truncate"><?= e($u['email']) ?></p>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-1 rounded bg-secondary-fixed text-on-secondary-fixed">Zion Club</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">Member since <?= e(date('M Y', strtotime((string) $u['created_at']))) ?></span>
          </div>
          <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1.5">
            <span class="material-symbols-outlined text-secondary text-base">smartphone</span>
            <?= e($u['phone'] ?: 'No phone on file') ?>
          </p>
        </div>

        <nav class="bg-surface-container-lowest rounded-xl shadow-xs p-2 flex flex-col">
          <?php foreach ($tabs as $key => [$icon, $label]): ?>
            <?php $isOrders = $key === 'orders'; ?>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-body-sm text-body-sm transition-colors <?= $tab === $key ? 'bg-primary-container text-on-primary font-bold' : 'text-on-surface-variant hover:bg-surface-container' ?>"
               href="<?= e(url('account.php?tab=' . $key)) ?>">
              <span class="material-symbols-outlined text-lg"><?= e($icon) ?></span>
              <span class="flex-1"><?= e($label) ?></span>
              <?php if ($isOrders && (int) $orderStats['total'] > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full bg-surface-container-high text-on-surface text-[10px] font-bold"><?= (int) $orderStats['total'] ?></span>
              <?php elseif ($key === 'wishlist' && $wishCount > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full bg-surface-container-high text-on-surface text-[10px] font-bold"><?= $wishCount ?></span>
              <?php elseif ($key === 'addresses' && $addressCount > 0): ?>
                <span class="px-1.5 py-0.5 rounded-full bg-surface-container-high text-on-surface text-[10px] font-bold"><?= $addressCount ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
          <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-body-sm text-body-sm text-error hover:bg-error-container/40 transition-colors"
             href="<?= e(url('logout.php')) ?>">
            <span class="material-symbols-outlined text-lg">logout</span> Sign Out of Session
          </a>
        </nav>

        <div class="bg-inverse-surface text-surface rounded-xl p-4">
          <span class="font-label-tag text-label-tag uppercase tracking-wider text-secondary-fixed">Black Tier Concierge</span>
          <p class="font-body-sm text-body-sm text-surface-dim mt-1">Direct priority line, dedicated acoustic curation and bespoke fitting support across Greater Accra.</p>
          <a class="mt-3 inline-flex items-center gap-1.5 font-label-nav text-label-nav font-bold uppercase tracking-wider text-secondary-fixed hover:underline"
             href="<?= e(url('page.php?slug=contact')) ?>">
            <span class="material-symbols-outlined text-base">chat</span> WhatsApp Concierge
          </a>
        </div>
      </aside>

      <!-- content -->
      <div class="flex-1 min-w-0 flex flex-col gap-space-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Accra Hub Active</span>
            <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold"><?= e($tabs[$tab][1]) ?></h1>
          </div>
          <span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1.5">
            <span class="material-symbols-outlined text-secondary text-base">verified</span> Last synced just now
          </span>
        </div>

        <?php if ($tab === 'overview'): ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-sm">
            <?php
            $cards = [
                ['inventory_2', 'Total Orders', (string) (int) $orderStats['total'], (int) $orderStats['active'] . ' active', 'text-primary'],
                ['local_shipping', 'In Transit', (string) (int) $orderStats['active'], 'Across Ghana', 'text-secondary'],
                ['favorite', 'Wishlist', (string) $wishCount, 'Saved pieces', 'text-primary-container'],
                ['payments', 'Lifetime Spend', money($lifetime, true), 'Paid orders', 'text-secondary-fixed-variant'],
            ];
            foreach ($cards as [$icon, $label, $value, $sub, $cls]): ?>
              <div class="bg-surface-container-lowest rounded-xl shadow-xs p-4">
                <div class="flex items-center justify-between">
                  <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant"><?= e($label) ?></span>
                  <span class="material-symbols-outlined text-lg <?= e($cls) ?>"><?= e($icon) ?></span>
                </div>
                <p class="font-headline-md text-headline-md text-on-surface font-bold mt-1"><?= e($value) ?></p>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><?= e($sub) ?></p>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
            <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
              <div class="flex items-center justify-between mb-3">
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Recent orders</h2>
                <a class="font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:underline" href="<?= e(url('orders.php')) ?>">View all</a>
              </div>
              <?php $recent = db_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3', [(int) $u['id']]); ?>
              <?php if ($recent === []): ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant">No orders yet. Your history will appear here.</p>
              <?php else: foreach ($recent as $o): ?>
                <a class="flex items-center justify-between gap-3 py-2.5 border-b border-outline-variant/60 last:border-0 hover:bg-surface-container rounded px-2 -mx-2 transition-colors"
                   href="<?= e(url('order.php?no=' . urlencode($o['order_no']))) ?>">
                  <div class="min-w-0">
                    <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate"><?= e($o['order_no']) ?></p>
                    <p class="font-body-sm text-[11px] text-on-surface-variant"><?= e(date('d M Y', strtotime((string) $o['created_at']))) ?> &middot; <?= e(payment_channel_label((string) $o['payment_channel'])) ?></p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="font-label-price text-label-price text-on-surface"><?= e(price($o['total'])) ?></p>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider px-1.5 py-0.5 rounded
                      <?= $o['status'] === 'delivered' ? 'bg-secondary-fixed text-on-secondary-fixed' : ($o['status'] === 'cancelled' ? 'bg-error-container text-on-error-container' : 'bg-primary-fixed text-primary') ?>">
                      <?= e($o['status']) ?>
                    </span>
                  </div>
                </a>
              <?php endforeach; endif; ?>
            </div>

            <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Default destination</h2>
              <?php $d = $addresses[0] ?? null; ?>
              <?php if ($d === null): ?>
                <p class="font-body-sm text-body-sm text-on-surface-variant">No delivery address saved yet.</p>
              <?php else: ?>
                <p class="font-body-sm text-body-sm text-on-surface font-semibold"><?= e($d['recipient']) ?></p>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><?= e($d['street']) ?><br/><?= e($d['city']) ?>, <?= e($d['region']) ?></p>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e($d['phone']) ?></p>
              <?php endif; ?>
              <div class="mt-3 flex flex-wrap gap-2">
                <a class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container"
                   href="<?= e(url('account.php?tab=addresses')) ?>">Edit address</a>
                <span class="px-4 py-2 rounded-lg bg-surface-container-high font-label-nav text-label-nav uppercase text-on-surface-variant flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-secondary text-sm">lock</span> Discreet packaging: ON
                </span>
              </div>
            </div>
          </div>

        <?php elseif ($tab === 'orders'): ?>
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md text-center">
            <p class="font-body-md text-body-md text-on-surface-variant">You have <?= plural((int) $orderStats['total'], 'order', 'orders') ?> on file.</p>
            <a class="inline-block mt-3 px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" href="<?= e(url('orders.php')) ?>">Open order history</a>
          </div>

        <?php elseif ($tab === 'wishlist'): ?>
          <?php $items = db_all('SELECT p.* FROM wishlist w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.created_at DESC', [(int) $u['id']]); ?>
          <?php if ($items === []): ?>
            <div class="bg-surface-container-lowest rounded-xl shadow-xs p-10 text-center">
              <span class="material-symbols-outlined text-4xl text-outline">favorite</span>
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mt-2">Your wishlist is empty</h2>
              <a class="inline-block mt-3 px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider" href="<?= e(url('shop.php')) ?>">Start browsing</a>
            </div>
          <?php else: ?>
            <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
              <ul class="flex flex-col divide-y divide-outline-variant/60">
                <?php foreach ($items as $it): ?>
                  <li class="py-3 flex items-center gap-3">
                    <img class="w-12 h-14 object-cover rounded bg-surface-container" src="<?= e(img_url($it['image_url'])) ?>" alt="<?= e($it['name']) ?>"/>
                    <div class="flex-1 min-w-0">
                      <a class="font-body-sm text-body-sm text-on-surface font-semibold hover:text-primary truncate block" href="<?= e(url('product.php?slug=' . urlencode($it['slug']))) ?>"><?= e($it['name']) ?></a>
                      <p class="font-body-sm text-[11px] text-on-surface-variant"><?= e($it['brand_label']) ?></p>
                    </div>
                    <span class="font-label-price text-label-price text-on-surface"><?= e(price($it['price'])) ?></span>
                    <a class="px-3 py-1.5 bg-primary-container text-on-primary rounded font-label-nav text-label-nav uppercase" href="<?= e(url('product.php?slug=' . urlencode($it['slug']))) ?>">View</a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

        <?php elseif ($tab === 'addresses'): ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
            <?php foreach ($addresses as $a): ?>
              <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= e($a['recipient']) ?></p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e($a['street']) ?><br/><?= e($a['city']) ?>, <?= e($a['region']) ?><br/><?= e($a['phone']) ?></p>
                  </div>
                  <?php if ((int) $a['is_default'] === 1): ?>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-1 rounded bg-secondary-fixed text-on-secondary-fixed shrink-0">Default</span>
                  <?php endif; ?>
                </div>
                <div class="mt-3 flex gap-2">
                  <form data-ajax method="post" action="<?= e(url('actions.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_address"/>
                    <input type="hidden" name="address_id" value="<?= (int) $a['id'] ?>"/>
                    <button class="px-3 py-1.5 border border-outline-variant rounded font-label-nav text-label-nav uppercase text-error hover:bg-error-container/40" type="submit">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md md:col-span-2">
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Add a delivery address</h2>
              <form data-ajax method="post" action="<?= e(url('actions.php')) ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_address"/>
                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Recipient</span>
                  <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="recipient" required value="<?= e($u['name']) ?>"/>
                </label>
                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone</span>
                  <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="phone" required value="<?= e($u['phone'] ?: '+233') ?>"/>
                </label>
                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Region</span>
                  <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="region" required>
                    <?php foreach ($regions as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
                  </select>
                </label>
                <label class="flex flex-col gap-1">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">City / Neighborhood</span>
                  <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="city" required placeholder="East Legon"/>
                </label>
                <label class="flex flex-col gap-1 sm:col-span-2">
                  <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Street Address &amp; Landmark</span>
                  <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="street" required placeholder="No. 14 Boundary Road, East Legon"/>
                </label>
                <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant sm:col-span-2">
                  <input type="checkbox" name="is_default" value="1" class="accent-primary"/> Set as default destination
                </label>
                <button class="sm:col-span-2 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Save Address</button>
              </form>
            </div>
          </div>

        <?php elseif ($tab === 'details'): ?>
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md max-w-xl">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Personal details</h2>
            <form data-ajax method="post" action="<?= e(url('actions.php')) ?>" class="flex flex-col gap-space-sm">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="account_details"/>
              <label class="flex flex-col gap-1">
                <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full name</span>
                <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="name" required value="<?= e($u['name']) ?>"/>
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Email</span>
                <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm bg-surface-container text-on-surface-variant" value="<?= e($u['email']) ?>" disabled/>
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone &amp; MoMo</span>
                <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="phone" value="<?= e($u['phone'] ?? '') ?>"/>
              </label>
              <button class="py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Save Changes</button>
            </form>
          </div>

        <?php elseif ($tab === 'security'): ?>
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md max-w-xl">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Security &amp; privacy</h2>
            <ul class="flex flex-col gap-3 font-body-sm text-body-sm text-on-surface-variant">
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-secondary text-base">lock</span> Your password is stored as a salted bcrypt hash and never in plain text.</li>
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-secondary text-base">enhanced_encryption</span> Payment details are never written to our database.</li>
              <li class="flex items-start gap-2"><span class="material-symbols-outlined text-secondary text-base">verified_user</span> Discretion is applied to every intimate shipment by default.</li>
            </ul>
            <a class="mt-4 inline-block px-5 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-error hover:bg-error-container/40" href="<?= e(url('logout.php')) ?>">Sign Out of Session</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php render_foot(); ?>
