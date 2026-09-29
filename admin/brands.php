<?php
/** Brand management: create, rename, remove. */

declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_slugs.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');

    if ($act === 'add' || $act === 'rename') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = admin_slugify((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $name);

        if ($name === '') {
            flash_set('error', 'A brand name is required.');
        } elseif ($slug === '') {
            flash_set('error', 'That brand name cannot be turned into a slug.');
        } else {
            $clash = db_one('SELECT id FROM brands WHERE slug = ?' . ($id > 0 ? ' AND id <> ?' : ''), $id > 0 ? [$slug, $id] : [$slug]);
            $nameClash = db_one('SELECT id FROM brands WHERE name = ?' . ($id > 0 ? ' AND id <> ?' : ''), $id > 0 ? [$name, $id] : [$name]);
            if ($nameClash !== null) {
                flash_set('error', 'A brand called "' . $name . '" already exists.');
            } elseif ($clash !== null) {
                flash_set('error', 'The slug "' . $slug . '" is already used by another brand.');
            } elseif ($act === 'add') {
                db_exec('INSERT INTO brands (slug, name) VALUES (?,?)', [$slug, $name]);
                flash_set('success', 'Brand "' . $name . '" created.');
            } else {
                db_exec('UPDATE brands SET name = ?, slug = ? WHERE id = ?', [$name, $slug, $id]);
                db_exec('UPDATE products SET brand_label = ? WHERE brand_id = ?', [$name, $id]);
                flash_set('success', 'Brand renamed to "' . $name . '".');
            }
        }
    } elseif ($act === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $count = (int) db_val('SELECT COUNT(*) FROM products WHERE brand_id = ?', [$id], 0);
        if ($count > 0) {
            flash_set('error', 'This brand is used by ' . plural($count, 'product', 'products') . '. Reassign them first.');
        } else {
            db_exec('DELETE FROM brands WHERE id = ?', [$id]);
            flash_set('success', 'Brand removed.');
        }
    }

    header('Location: ' . url('admin/brands.php'));
    exit;
}

$rows = db_all(
    'SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS n,
            (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.is_active = 1) AS live
     FROM brands b
     ORDER BY b.name'
);

admin_head('Brands', 'brands');
?>
<div class="flex flex-wrap items-end justify-between gap-3 mb-space-md">
  <div>
    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Taxonomy</span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Brands</h1>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e(plural(count($rows), 'brand', 'brands')) ?> available across the catalogue.</p>
  </div>
  <a class="px-5 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container-lowest"
     href="<?= e(url('admin/product_form.php')) ?>">+ New product</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
  <section class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <div class="overflow-x-auto">
      <form data-ajax method="post">
        <?= csrf_field() ?>
        <table class="w-full text-left align-top">
          <thead>
            <tr class="border-b border-outline-variant">
              <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Brand</th>
              <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Slug</th>
              <th class="py-2 pr-3 font-label-nav text-label-nav uppercase text-on-surface-variant">Products</th>
              <th class="py-2 font-label-nav text-label-nav uppercase text-on-surface-variant text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td class="py-8 text-center font-body-sm text-body-sm text-on-surface-variant" colspan="4">No brands yet - create one on the right.</td></tr>
            <?php else: foreach ($rows as $b): ?>
              <tr class="border-b border-outline-variant/50 hover:bg-surface-container">
                <td class="py-3 pr-3">
                  <input class="w-full max-w-64 h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         name="name" value="<?= e($b['name']) ?>" required aria-label="Brand name"/>
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>"/>
                </td>
                <td class="py-3 pr-3">
                  <input class="w-full max-w-48 h-10 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                         name="slug" value="<?= e($b['slug']) ?>" aria-label="Brand slug"/>
                </td>
                <td class="py-3 pr-3 font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">
                  <?= e(plural((int) $b['n'], 'product', 'products')) ?> &middot; <?= (int) $b['live'] ?> live
                </td>
                <td class="py-3 text-right">
                  <div class="flex items-center justify-end gap-4">
                    <button class="px-4 h-10 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav uppercase hover:bg-primary"
                            type="submit" name="act" value="rename">Save</button>
                    <button class="px-2 h-10 font-label-nav text-label-nav uppercase text-error hover:underline bg-transparent border-0 cursor-pointer"
                            type="submit" name="act" value="delete" formnovalidate
                            onclick="return confirm('Remove the brand <?= e($b['name']) ?>?');">Delete</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </form>
    </div>
  </section>

  <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-1">Add a brand</h2>
    <p class="font-body-sm text-body-sm text-on-surface-variant mb-3">New brands can also be created straight from the product form.</p>
    <form data-ajax method="post" class="flex flex-col gap-space-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="act" value="add"/>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Brand name</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="name" required placeholder="Zion Couture"/>
      </label>
      <label class="flex flex-col gap-1">
        <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Slug</span>
        <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
               name="slug" placeholder="generated from the name"/>
      </label>
      <button class="py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Create Brand</button>
    </form>
  </section>
</div>
<?php admin_foot(); ?>
