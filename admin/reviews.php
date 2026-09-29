<?php
/** Product review moderation: approve, hide and delete customer reviews. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $revId = (int) ($_POST['id'] ?? 0);
    $row = $revId > 0 ? db_one('SELECT id, product_id FROM reviews WHERE id = ?', [$revId]) : null;

    if ($row === null) {
        flash_set('error', 'That review no longer exists.');
    } elseif ($act === 'approve') {
        db_exec('UPDATE reviews SET is_approved = 1 WHERE id = ?', [$revId]);
        refresh_product_rating((int) $row['product_id']);
        flash_set('success', 'Review approved and published.');
    } elseif ($act === 'hide') {
        db_exec('UPDATE reviews SET is_approved = 0 WHERE id = ?', [$revId]);
        refresh_product_rating((int) $row['product_id']);
        flash_set('info', 'Review hidden from the storefront.');
    } elseif ($act === 'delete') {
        $pid = (int) $row['product_id'];
        db_exec('DELETE FROM reviews WHERE id = ?', [$revId]);
        refresh_product_rating($pid);
        flash_set('success', 'Review deleted.');
    }

    header('Location: ' . url('admin/reviews.php' . (isset($_POST['filter']) && $_POST['filter'] !== '' ? '?filter=' . urlencode((string) $_POST['filter']) : '')));
    exit;
}

$filter = (string) ($_GET['filter'] ?? 'pending');
if (!in_array($filter, ['pending', 'approved', 'all'], true)) {
    $filter = 'pending';
}

$pending = (int) db_val('SELECT COUNT(*) FROM reviews WHERE is_approved = 0', [], 0);
$total   = (int) db_val('SELECT COUNT(*) FROM reviews', [], 0);

$where = match ($filter) {
    'approved' => 'r.is_approved = 1',
    'all'      => '1 = 1',
    default    => 'r.is_approved = 0',
};
$rows = db_all(
    "SELECT r.*, p.name AS product_name, p.slug AS product_slug, u.email AS user_email
       FROM reviews r
       JOIN products p ON p.id = r.product_id
       LEFT JOIN users u ON u.id = r.user_id
      WHERE $where
      ORDER BY r.created_at DESC, r.id DESC
      LIMIT 200"
);

admin_head('Reviews', 'reviews');
?>
<div class="max-w-5xl">
  <nav class="font-label-nav text-label-nav text-on-surface-variant mb-3 flex items-center gap-2">
    <a class="hover:text-primary" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
    <span class="text-outline-variant">/</span>
    <span class="text-primary font-semibold">Reviews</span>
  </nav>

  <div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
    <div>
      <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Moderation</span>
      <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Customer reviews</h1>
      <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
        <?= e(plural($pending, 'review waiting', 'reviews waiting')) ?> for approval &middot; <?= e(plural($total, 'review', 'reviews')) ?> in total.
      </p>
    </div>
    <div class="flex gap-1 bg-surface-container-low p-1 rounded-lg">
      <?php foreach (['pending' => 'Pending', 'approved' => 'Published', 'all' => 'All'] as $key => $label): ?>
        <a class="px-4 py-2 rounded font-label-nav text-label-nav uppercase transition-colors <?= $filter === $key ? 'bg-surface-container-lowest text-primary font-semibold shadow-sm' : 'text-on-surface-variant hover:text-on-surface' ?>"
           href="<?= e(url('admin/reviews.php?filter=' . $key)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-surface-container-lowest rounded-xl shadow-xs overflow-hidden">
    <?php if ($rows === []): ?>
      <div class="p-12 text-center">
        <span class="material-symbols-outlined text-4xl text-outline">rate_review</span>
        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mt-3">
          <?= $filter === 'pending' ? 'Nothing waiting' : 'No reviews here' ?>
        </h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
          <?= $filter === 'pending'
              ? 'Every submitted review has been moderated. New submissions land here first.'
              : 'Reviews matching this filter will appear here.' ?>
        </p>
      </div>
    <?php else: ?>
      <ul class="divide-y divide-outline-variant/60">
        <?php foreach ($rows as $r): ?>
          <li class="px-4 py-4 flex flex-col gap-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-body-sm text-body-sm font-bold text-on-surface"><?= e($r['reviewer_name']) ?></span>
                  <span class="flex gap-0.5">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <span class="material-symbols-outlined text-secondary" style="font-size:16px"><?= $s <= (int) $r['rating'] ? 'star' : 'star_outline' ?></span>
                    <?php endfor; ?>
                  </span>
                  <?php if ((int) $r['is_approved'] === 1): ?>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-secondary-fixed text-on-secondary-fixed">Published</span>
                  <?php else: ?>
                    <span class="font-label-tag text-label-tag uppercase tracking-wider px-2 py-0.5 rounded bg-error-container text-on-error-container">Pending</span>
                  <?php endif; ?>
                </div>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                  on <a class="text-primary underline" target="_blank" rel="noopener"
                        href="<?= e(url('product.php?slug=' . urlencode($r['product_slug'])) . '#reviews') ?>"><?= e($r['product_name']) ?></a>
                  &middot; <?= e(date('d M Y, H:i', strtotime((string) $r['created_at']))) ?>
                  <?php if (($r['user_email'] ?? null) !== null): ?>
                    &middot; <?= e($r['user_email']) ?>
                  <?php endif; ?>
                </p>
              </div>

              <div class="flex gap-2 shrink-0">
                <?php if ((int) $r['is_approved'] === 0): ?>
                  <form data-ajax method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act" value="approve"/>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
                    <button class="px-3.5 py-2 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav uppercase hover:bg-primary"
                            type="submit">Approve</button>
                  </form>
                <?php else: ?>
                  <form data-ajax method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="act" value="hide"/>
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
                    <button class="px-3.5 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container"
                            type="submit">Hide</button>
                  </form>
                <?php endif; ?>
                <form data-ajax method="post" onsubmit="return confirm('Delete this review permanently?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="act" value="delete"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
                  <button class="px-3.5 py-2 border border-error-container text-error rounded-lg font-label-nav text-label-nav uppercase hover:bg-error-container/40"
                          type="submit">Delete</button>
                </form>
              </div>
            </div>

            <?php if ($r['title'] !== null && $r['title'] !== ''): ?>
              <p class="font-body-sm text-body-sm font-semibold text-on-surface"><?= e($r['title']) ?></p>
            <?php endif; ?>
            <p class="font-body-sm text-body-sm text-on-surface-variant whitespace-pre-line"><?= e($r['body']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php admin_foot(); ?>
