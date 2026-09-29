<?php
/** Customer accounts: search, spend, account state. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $id  = (int) ($_POST['id'] ?? 0);
    if ($act === 'toggle_active') {
        db_exec('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash_set('success', 'Account state updated.');
    } elseif ($act === 'toggle_role') {
        db_exec('UPDATE users SET role = IF(role = "admin", "customer", "admin") WHERE id = ?', [$id]);
        flash_set('success', 'Account role updated.');
    }
    $q = (string) ($_POST['q'] ?? '');
    header('Location: ' . url('admin/customers.php' . ($q !== '' ? '?q=' . urlencode($q) : '')));
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = ['u.role <> "admin"'];
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$customers = db_all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'paid') AS spend,
            (SELECT MAX(o.created_at) FROM orders o WHERE o.user_id = u.id) AS last_order
     FROM users u
     WHERE $whereSql
     ORDER BY spend DESC, u.created_at DESC
     LIMIT 300",
    $params
);
$totalCustomers = (int) db_val('SELECT COUNT(*) FROM users WHERE role <> "admin"', [], 0);

admin_head('Customers', 'customers');
?>
<div class="mb-space-md">
  <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Clientele</span>
  <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Customers</h1>
  <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e(plural($totalCustomers, 'account', 'accounts')) ?> registered on the storefront.</p>
</div>

<form method="get" class="flex flex-wrap gap-2 mb-space-md">
  <input class="h-11 w-72 px-3 border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary outline-none"
         type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone"/>
  <button class="h-11 px-5 bg-inverse-surface text-surface rounded-lg font-label-nav text-label-nav uppercase" type="submit">Search</button>
</form>

<section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
  <div class="overflow-x-auto">
    <table class="w-full text-left">
      <thead>
        <tr class="border-b border-outline-variant">
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Customer</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Phone</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Orders</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Spend</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Last order</th>
          <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">State</th>
          <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($customers === []): ?>
          <tr><td class="py-8 text-center font-body-sm text-body-sm text-on-surface-variant" colspan="7">No accounts match that search.</td></tr>
        <?php else: foreach ($customers as $c): ?>
          <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
            <td class="py-2.5 pr-3">
              <span class="font-body-sm text-body-sm font-semibold text-on-surface"><?= e($c['name']) ?></span>
              <div class="font-body-sm text-[11px] text-outline"><?= e($c['email']) ?></div>
            </td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= e($c['phone'] ?: '—') ?></td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= (int) $c['orders'] ?></td>
            <td class="py-2.5 pr-3 font-label-price text-label-price text-on-surface"><?= e(money((float) $c['spend'], true)) ?></td>
            <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant">
              <?= $c['last_order'] !== null ? e(date('d M Y', strtotime((string) $c['last_order']))) : '—' ?>
            </td>
            <td class="py-2.5 pr-3">
              <span class="font-label-tag text-label-tag uppercase px-2 py-0.5 rounded <?= (int) $c['is_active'] ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container' ?>">
                <?= (int) $c['is_active'] ? 'Active' : 'Locked' ?>
              </span>
            </td>
            <td class="py-2.5 text-right whitespace-nowrap">
              <form data-ajax method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="toggle_active"/>
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>"/>
                <input type="hidden" name="q" value="<?= e($q) ?>"/>
                <button class="font-label-nav text-label-nav uppercase text-primary hover:underline" type="submit">
                  <?= (int) $c['is_active'] ? 'Lock' : 'Unlock' ?>
                </button>
              </form>
              <form data-ajax method="post" class="inline ml-3" onsubmit="return confirm('Change the role for <?= e($c['name']) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="toggle_role"/>
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>"/>
                <input type="hidden" name="q" value="<?= e($q) ?>"/>
                <button class="font-label-nav text-label-nav uppercase text-on-surface-variant hover:underline" type="submit">Make <?= $c['role'] === 'admin' ? 'customer' : 'admin' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_foot(); ?>
