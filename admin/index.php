<?php
/** Back-office dashboard: date-ranged revenue, order queue, stock alerts. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

/* --- manual low-stock alert ------------------------------------------- */
if (($_POST['act'] ?? '') === 'low_stock_alert' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $low = db_all(
        'SELECT name, sku, stock FROM products
          WHERE is_active = 1 AND stock <= ?
          ORDER BY stock ASC, name LIMIT 20',
        [LOW_STOCK_THRESHOLD]
    );
    if ($low === []) {
        flash_set('info', 'Nothing sits at or below the low-stock threshold.');
    } elseif (send_low_stock_alert($low, 'Sent manually from the dashboard')) {
        flash_set('success', 'Low-stock alert sent to ' . ADMIN_EMAIL . ' (see storage/logs/mail.log).');
    } else {
        flash_set('error', 'The mailer could not send the alert - check storage/logs/mail.log.');
    }
    header('Location: ' . url('admin/index.php?range=' . urlencode($range ?? '30')));
    exit;
}

/* --- date range -------------------------------------------------------- */
$ranges = [
    '7'   => 'Last 7 days',
    '30'  => 'Last 30 days',
    '90'  => 'Last 90 days',
    'ytd' => 'Year to date',
    'all' => 'All time',
];
$range = (string) ($_GET['range'] ?? '30');
if (!isset($ranges[$range])) {
    $range = '30';
}
$rangeLabel = $ranges[$range];
$exportRange = in_array($range, ['7', '30', '90'], true) ? $range : ($range === 'ytd' ? 'ytd' : '0');

$rangeSql = '';
if ($range === 'ytd') {
    $rangeSql = 'AND created_at >= DATE_FORMAT(CURDATE(), "%Y-01-01")';
} elseif ($range !== 'all') {
    $rangeSql = 'AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ((int) $range - 1) . ' DAY)';
}

/* --- KPIs for the selected window -------------------------------------- */
$revenue    = (float) db_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid' $rangeSql", [], 0);
$revOrders  = (int) db_val("SELECT COUNT(*) FROM orders WHERE 1 = 1 $rangeSql", [], 0);
$avgBasket  = (float) db_val("SELECT COALESCE(AVG(total),0) FROM orders WHERE payment_status = 'paid' $rangeSql", [], 0);
$newCustSql = match ($range) {
    'all'   => '',
    'ytd'   => "AND created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')",
    default => 'AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ((int) $range - 1) . ' DAY)',
};
$newCust = (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'customer' $newCustSql", [], 0);

$lifetimeRev = (float) db_val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'", [], 0);
$totalOrders = (int) db_val('SELECT COUNT(*) FROM orders', [], 0);
$openOrders  = (int) db_val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','packing','shipped')", [], 0);
$customers   = (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'customer'", [], 0);
$lowStock    = (int) db_val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= ?', [LOW_STOCK_THRESHOLD]);
$products    = (int) db_val('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0);
$avgBasketAll = (float) db_val("SELECT COALESCE(AVG(total),0) FROM orders WHERE payment_status = 'paid'", [], 0);

/* --- revenue series, bucketed day / week / month ------------------------ */
$series = db_all(
    "SELECT DATE(created_at) AS d,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS rev,
            COUNT(*) AS n
       FROM orders
      WHERE 1 = 1 $rangeSql
      GROUP BY DATE(created_at)
      ORDER BY d"
);

$today = new DateTimeImmutable('today');
if ($range === 'ytd') {
    $start = new DateTimeImmutable(date('Y') . '-01-01');
} elseif ($range === 'all') {
    $min = db_val('SELECT DATE(MIN(created_at)) FROM orders', [], null);
    $start = $min ? new DateTimeImmutable((string) $min) : $today;
} else {
    $start = $today->modify('-' . ((int) $range - 1) . ' days');
}
$span = $start->diff($today)->days + 1;
$gran = $span <= 31 ? 'day' : ($span <= 200 ? 'week' : 'month');

$map = [];
foreach ($series as $r) {
    $dt = new DateTimeImmutable((string) $r['d']);
    $key = match ($gran) {
        'day'   => $dt->format('Y-m-d'),
        'week'  => $dt->format('o-\WW'),
        default => $dt->format('Y-m'),
    };
    if (!isset($map[$key])) {
        $map[$key] = ['rev' => 0.0, 'n' => 0];
    }
    $map[$key]['rev'] += (float) $r['rev'];
    $map[$key]['n']   += (int) $r['n'];
}

$keys = [];
$labels = [];
if ($gran === 'day') {
    for ($d = new DateTimeImmutable($start->format('Y-m-d')); $d <= $today; $d = $d->modify('+1 day')) {
        $keys[] = $d->format('Y-m-d');
        $labels[] = $d->format('d M');
    }
} elseif ($gran === 'week') {
    $d = $start->modify('-' . (int) $start->format('w') . ' days');
    while ($d <= $today) {
        $keys[] = $d->format('o-\WW');
        $labels[] = $d->format('d M');
        $d = $d->modify('+7 days');
    }
} else {
    $d = new DateTimeImmutable($start->format('Y-m-01'));
    while ($d <= $today) {
        $keys[] = $d->format('Y-m');
        $labels[] = $d->format('M Y');
        $d = $d->modify('first day of +1 month');
    }
}

$bars = [];
$chartTotal = 0.0;
$chartOrders = 0;
$chartMax = 0.0;
foreach ($keys as $i => $k) {
    $rev = (float) ($map[$k]['rev'] ?? 0.0);
    $cnt = (int) ($map[$k]['n'] ?? 0);
    $bars[] = ['label' => $labels[$i], 'rev' => $rev, 'n' => $cnt];
    $chartTotal += $rev;
    $chartOrders += $cnt;
    $chartMax = max($chartMax, $rev);
}

$short = static function (float $v): string {
    if ($v >= 1000000) {
        return '₵' . number_format($v / 1000000, 1) . 'm';
    }
    if ($v >= 1000) {
        return '₵' . number_format($v / 1000, 1) . 'k';
    }
    return '₵' . number_format($v, 0);
};

$byStatus = db_all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status ORDER BY n DESC');
$recent   = db_all('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 10');
$stock    = db_all(
    'SELECT id, name, sku, stock, price, slug FROM products
      WHERE is_active = 1 AND stock <= ? ORDER BY stock ASC, name LIMIT 8',
    [LOW_STOCK_THRESHOLD]
);

admin_head('Dashboard', 'index');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
  <div>
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Retail Operations</span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Dashboard</h1>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Live numbers pulled from <?= e(number_format($totalOrders)) ?> orders and <?= e(number_format($products)) ?> active SKUs.</p>
  </div>
  <a class="px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary"
     href="<?= e(url('admin/product_form.php')) ?>">+ New product</a>
</div>

<div class="flex flex-wrap items-center gap-1.5 mb-space-md">
  <?php foreach ($ranges as $k => $label): ?>
    <a class="px-3.5 py-1.5 rounded-full font-label-nav text-label-nav uppercase tracking-wider transition-colors
       <?= $range === $k ? 'bg-primary-container text-on-primary font-bold' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' ?>"
       href="<?= e(url('admin/index.php?range=' . $k)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="ml-auto inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border border-outline-variant font-label-nav text-label-nav uppercase tracking-wider text-on-surface hover:bg-surface-container"
     href="<?= e(url('admin/orders.php?export=csv&range=' . $exportRange)) ?>">
    <span class="material-symbols-outlined text-base">download</span> Export CSV
  </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-sm mb-space-md">
  <?php
  $cards = [
      ['payments', 'Revenue (paid)', money($revenue, true), $rangeLabel, 'text-primary'],
      ['receipt_long', 'Orders', number_format($revOrders), $rangeLabel, 'text-primary-container'],
      ['shopping_basket', 'Avg basket', money($avgBasket, true), 'paid orders, ' . strtolower($rangeLabel), 'text-secondary'],
      ['group', 'New customers', number_format($newCust), $rangeLabel, 'text-secondary-fixed-variant'],
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

<section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md mb-space-md">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <div>
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Revenue</h2>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
        <?= e($rangeLabel) ?> &middot; <?= e(money($chartTotal, true)) ?> from <?= e(plural($chartOrders, 'order', 'orders')) ?>
        <?= $gran !== 'day' ? ' &middot; bucketed weekly' : '' ?>
      </p>
    </div>
    <p class="font-body-sm text-body-sm text-on-surface-variant">
      Lifetime <?= e(money($lifetimeRev, true)) ?> &middot; <?= e(money($avgBasketAll, true)) ?> avg basket
    </p>
  </div>

  <?php if ($bars === [] || $chartTotal <= 0): ?>
    <div class="py-8 text-center">
      <span class="material-symbols-outlined text-4xl text-outline">insights</span>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">No paid orders in this window yet.</p>
    </div>
  <?php else:
    $W = 720; $H = 190; $padL = 46; $padR = 8; $padT = 16; $padB = 28;
    $innerW = $W - $padL - $padR;
    $innerH = $H - $padT - $padB;
    $n = count($bars);
    $gap = $n > 60 ? 1 : 2;
    $slot = $innerW / $n;
    $bw = max(2, $slot - $gap);
    $yMax = $chartMax > 0 ? $chartMax : 1.0;
    $labelStep = max(1, (int) ceil($n / 5));
    ?>
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="w-full" style="height:190px" role="img" aria-label="Revenue chart for <?= e($rangeLabel) ?>">
      <defs>
        <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--color-primary-container, #14532d)"/>
          <stop offset="100%" stop-color="var(--color-primary-container, #14532d)" stop-opacity="0.45"/>
        </linearGradient>
      </defs>
      <?php foreach ([1.0, 0.5, 0.0] as $f): ?>
        <?php $y = $padT + $innerH - ($f * $innerH); ?>
        <line x1="<?= $padL ?>" y1="<?= round($y, 1) ?>" x2="<?= $W - $padR ?>" y2="<?= round($y, 1) ?>"
              stroke="var(--color-outline-variant, #d6d0cf)" stroke-width="1" <?= $f === 0.0 ? '' : 'stroke-dasharray="3 4"' ?>/>
        <text x="<?= $padL - 6 ?>" y="<?= round($y + 3.5, 1) ?>" text-anchor="end"
              font-size="9" fill="var(--color-outline, #827e7e)"><?= e($short($yMax * $f)) ?></text>
      <?php endforeach; ?>
      <?php foreach ($bars as $i => $b): ?>
        <?php
        $h = ($b['rev'] / $yMax) * $innerH;
        $x = $padL + $i * $slot + max(0, ($slot - $bw) / 2);
        $y = $padT + $innerH - $h;
        ?>
        <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round(max($b['rev'] > 0 ? 2 : 0, $h), 1) ?>"
              rx="<?= min(2, $bw / 2) ?>" fill="url(#revGrad)">
          <title><?= e($b['label']) ?>: <?= e(money($b['rev'], true)) ?> (<?= e(plural($b['n'], 'order', 'orders')) ?>)</title>
        </rect>
        <?php if ($i % $labelStep === 0 || $i === $n - 1): ?>
          <text x="<?= round($padL + $i * $slot + $slot / 2, 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"
                font-size="9" fill="var(--color-outline, #827e7e)"><?= e($b['label']) ?></text>
        <?php endif; ?>
      <?php endforeach; ?>
    </svg>
  <?php endif; ?>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md mb-space-md">
  <section class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Recent orders</h2>
      <div class="flex items-center gap-4">
        <span class="font-body-sm text-body-sm text-on-surface-variant"><?= e(plural($openOrders, 'open order', 'open orders')) ?></span>
        <a class="font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:underline" href="<?= e(url('admin/orders.php')) ?>">All orders</a>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-outline-variant">
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Order</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Customer</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Placed</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Status</th>
            <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent === []): ?>
            <tr><td class="py-4 font-body-sm text-body-sm text-on-surface-variant" colspan="5">No orders yet.</td></tr>
          <?php else: foreach ($recent as $o): ?>
            <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
              <td class="py-2.5 pr-3">
                <a class="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary" href="<?= e(url('admin/order_detail.php?no=' . urlencode($o['order_no']))) ?>"><?= e($o['order_no']) ?></a>
              </td>
              <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= e($o['customer_name']) ?><br/><span class="text-[11px] text-outline"><?= e($o['region']) ?></span></td>
              <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= e(date('d M, H:i', strtotime((string) $o['created_at']))) ?></td>
              <td class="py-2.5 pr-3"><?= status_pill($o['status']) ?></td>
              <td class="py-2.5 font-label-price text-label-price text-on-surface text-right"><?= e(price($o['total'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="flex flex-col gap-space-md">
    <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Order pipeline</h2>
      <div class="flex flex-col gap-2">
        <?php $max = 1; foreach ($byStatus as $row) { $max = max($max, (int) $row['n']); } ?>
        <?php if ($byStatus === []): ?>
          <p class="font-body-sm text-body-sm text-on-surface-variant">No orders recorded yet.</p>
        <?php else: foreach ($byStatus as $row): ?>
          <div class="flex items-center gap-3">
            <span class="w-24 shrink-0 font-body-sm text-body-sm text-on-surface-variant capitalize"><?= e($row['status']) ?></span>
            <span class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden">
              <span class="block h-full bg-primary-container" style="width:<?= (int) round(((int) $row['n'] / $max) * 100) ?>%"></span>
            </span>
            <span class="w-6 text-right font-body-sm text-body-sm text-on-surface font-semibold"><?= (int) $row['n'] ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <p class="mt-3 font-body-sm text-body-sm text-on-surface-variant">Average basket <?= e(money($avgBasketAll, true)) ?> &middot; <?= e(number_format($customers)) ?> customers</p>
    </section>

    <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Low stock</h2>
        <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-1 rounded <?= $lowStock > 0 ? 'bg-error-container text-on-error-container' : 'bg-secondary-fixed text-on-secondary-fixed' ?>">
          <?= e(plural($lowStock, 'alert', 'alerts')) ?>
        </span>
      </div>
      <?php if ($stock === []): ?>
        <p class="font-body-sm text-body-sm text-on-surface-variant">Every active SKU has healthy cover (threshold: <?= (int) LOW_STOCK_THRESHOLD ?>).</p>
      <?php else: ?>
        <ul class="flex flex-col gap-2">
          <?php foreach ($stock as $s): ?>
            <li class="flex items-center justify-between gap-3">
              <a class="font-body-sm text-body-sm text-on-surface hover:text-primary truncate" href="<?= e(url('admin/product_form.php?id=' . (int) $s['id'])) ?>"><?= e($s['name']) ?></a>
              <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded <?= (int) $s['stock'] === 0 ? 'bg-error-container text-on-error-container' : 'bg-primary-fixed text-primary' ?>">
                <?= (int) $s['stock'] ?> left
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <form data-ajax method="post" class="mt-3">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="act" value="low_stock_alert"/>
          <button class="w-full py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase tracking-wider text-on-surface hover:bg-surface-container transition-colors"
                  type="submit">
            Email alert to <?= e(ADMIN_EMAIL) ?>
          </button>
        </form>
      <?php endif; ?>
    </section>

    <?php
    $unread  = admin_unread_messages();
    $lastMsg = db_all('SELECT name, subject, created_at FROM contact_messages ORDER BY created_at DESC, id DESC LIMIT 1')[0] ?? null;
    ?>
    <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Customer messages</h2>
        <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-1 rounded <?= $unread > 0 ? 'bg-error-container text-on-error-container' : 'bg-secondary-fixed text-on-secondary-fixed' ?>">
          <?= e(plural($unread, 'unread', 'unread')) ?>
        </span>
      </div>
      <?php if ($lastMsg === null): ?>
        <p class="font-body-sm text-body-sm text-on-surface-variant">No enquiries yet — the contact form inbox is empty.</p>
      <?php else: ?>
        <p class="font-body-sm text-body-sm text-on-surface-variant">
          Latest from <strong class="text-on-surface"><?= e($lastMsg['name']) ?></strong>:
          &ldquo;<?= e($lastMsg['subject']) ?>&rdquo; &middot; <?= e(date('d M, H:i', strtotime((string) $lastMsg['created_at']))) ?>
        </p>
      <?php endif; ?>
      <a class="mt-3 inline-flex items-center gap-1 font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:underline"
         href="<?= e(url('admin/messages.php')) ?>">Open inbox <span class="material-symbols-outlined text-base">arrow_forward</span></a>
    </section>
  </div>
</div>
<?php admin_foot(); ?>
