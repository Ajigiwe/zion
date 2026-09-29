<?php
/** Category management. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_admin();

function category_slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');

    if ($act === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $dept = (string) ($_POST['department'] ?? '');
        $slug = category_slugify((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $name);
        if ($name === '' || !in_array($dept, ['lingerie', 'instruments'], true)) {
            flash_set('error', 'A category name and department are required.');
        } elseif ($slug === '') {
            flash_set('error', 'Please provide a valid slug.');
        } elseif (db_one('SELECT id FROM categories WHERE slug = ?', [$slug]) !== null) {
            flash_set('error', 'That slug is already used by another category.');
        } else {
            db_exec('INSERT INTO categories (slug, name, department, is_active) VALUES (?,?,?,1)',
                [$slug, $name, $dept]);
            flash_set('success', 'Category "' . $name . '" created.');
        }
    } elseif ($act === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        db_exec('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash_set('success', 'Category visibility updated.');
    } elseif ($act === 'image') {
        $id  = (int) ($_POST['id'] ?? 0);
        $img = trim((string) ($_POST['image_url'] ?? ''));
        if ($img !== '' && preg_match('#^https?://#i', $img) && filter_var($img, FILTER_VALIDATE_URL) === false) {
            flash_set('error', 'That image reference is not valid.');
        } elseif (db_one('SELECT id FROM categories WHERE id = ?', [$id]) === null) {
            flash_set('error', 'That category no longer exists.');
        } else {
            db_exec('UPDATE categories SET image_url = ? WHERE id = ?', [$img, $id]);
            flash_set('success', 'Category image updated.');
        }
    } elseif ($act === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $count = (int) db_val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id], 0);
        if ($count > 0) {
            flash_set('error', 'Move or delete the ' . plural($count, 'product', 'products') . ' in that category first.');
        } else {
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            flash_set('success', 'Category removed.');
        }
    }
    header('Location: ' . url('admin/categories.php'));
    exit;
}

$rows = db_all(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS live
     FROM categories c
     ORDER BY c.department, c.sort_order, c.name'
);

admin_head('Categories', 'categories');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
  <div>
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Taxonomy</span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Categories</h1>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e(plural(count($rows), 'category', 'categories')) ?> across both departments.</p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
  <section class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-outline-variant">
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Category</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Image</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Department</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Products</th>
            <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Status</th>
            <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
              <td class="py-2.5 pr-3">
                <a class="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary" href="<?= e(url('shop.php?cat=' . urlencode($r['slug']))) ?>" target="_blank" rel="noopener"><?= e($r['name']) ?></a>
                <div class="font-body-sm text-[11px] text-outline">/<?= e($r['slug']) ?></div>
              </td>
              <td class="py-2.5 pr-3 min-w-[14rem]">
                <form data-ajax method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="act" value="image"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <?= upload_field('image_url', (string) $r['image_url'], [
                      'autosubmit'  => true,
                      'preview'     => 'w-10 h-12',
                      'label'       => 'Tile image',
                      'hint'        => 'Uploading saves immediately.',
                      'input_class' => 'h-9 text-xs',
                  ]) ?>
                </form>
              </td>
              <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant capitalize"><?= e($r['department']) ?></td>
              <td class="py-2.5 pr-3 font-body-sm text-body-sm text-on-surface-variant"><?= (int) $r['live'] ?> live / <?= (int) $r['n'] ?> total</td>
              <td class="py-2.5 pr-3">
                <form data-ajax method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="act" value="toggle"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <button class="font-label-tag text-label-tag uppercase px-2 py-0.5 rounded <?= (int) $r['is_active'] ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' ?>"
                          type="submit"><?= (int) $r['is_active'] ? 'Active' : 'Hidden' ?></button>
                </form>
              </td>
              <td class="py-2.5 text-right">
                <form data-ajax method="post" onsubmit="return confirm('Remove the category <?= e($r['name']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="act" value="delete"/>
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"/>
                  <button class="font-label-nav text-label-nav uppercase text-error hover:underline" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3">Add a category</h2>
    <form data-ajax method="post" class="flex flex-col gap-space-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="add"/>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Name</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="name" required placeholder="Corsets &amp; Bustiers"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Slug</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="slug" placeholder="generated from the name"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Department</span>
        <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none" name="department">
          <option value="lingerie">Lingerie</option>
          <option value="instruments">Instruments</option>
        </select>
      </label>
      <button class="py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Create Category</button>
    </form>
  </section>
</div>
<?php admin_foot(); ?>
