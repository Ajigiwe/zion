<?php
/**
 * Shop filter form: departments, categories, price.
 *
 * Rendered twice - inside the shop sidebar (lg and up) and inside the
 * mobile drawer - so every id is prefixed with $idp to stay unique.
 */

declare(strict_types=1);

function shop_filters_form(string $idp = 'f'): void
{
    $dept    = in_array($_GET['dept'] ?? '', ['lingerie', 'instruments'], true) ? (string) $_GET['dept'] : '';
    $catSlug = trim((string) ($_GET['cat'] ?? ''));
    $onSale  = ($_GET['sale'] ?? '') === '1';
    $sort    = (string) ($_GET['sort'] ?? 'featured');
    $q       = trim((string) ($_GET['q'] ?? ''));
    $min     = isset($_GET['min']) && is_numeric($_GET['min']) ? (float) $_GET['min'] : null;
    $max     = isset($_GET['max']) && is_numeric($_GET['max']) ? (float) $_GET['max'] : null;

    $cats = db_all(
        'SELECT * FROM categories WHERE is_active = 1 AND (? IS NULL OR department = ?) ORDER BY sort_order',
        [$dept !== '' ? $dept : null, $dept !== '' ? $dept : null]
    );

    $parents = [];
    $kids    = [];
    foreach ($cats as $c) {
        if ($c['parent_id'] === null) {
            $parents[(int) $c['id']] = $c;
        } else {
            $kids[(int) $c['parent_id']][] = $c;
        }
    }
    ?>
    <form method="get" action="<?= e(url('shop.php')) ?>" class="flex flex-col gap-space-md">
      <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"/><?php endif; ?>
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"/><?php endif; ?>

      <div class="bg-surface-container-lowest p-4 rounded-xl shadow-xs">
        <label for="<?= e($idp) ?>-dept" class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3 block">Departments</label>
        <div class="relative">
          <select id="<?= e($idp) ?>-dept" name="dept" onchange="this.form.cat.value=''; this.form.submit()"
                  class="w-full appearance-none bg-surface-container border border-outline-variant rounded-lg pl-3 pr-9 py-2.5 font-body-sm text-body-sm text-on-surface cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary">
            <option value="">All Products</option>
            <?php foreach (['lingerie' => 'Lingerie', 'instruments' => 'Music, Audio & Church'] as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $dept === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-lg text-on-surface-variant pointer-events-none">expand_more</span>
        </div>
      </div>

      <div class="bg-surface-container-lowest p-4 rounded-xl shadow-xs">
        <label for="<?= e($idp) ?>-cat" class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3 block">Categories</label>
        <div class="relative">
          <select id="<?= e($idp) ?>-cat" name="cat" onchange="this.form.submit()"
                  class="w-full appearance-none bg-surface-container border border-outline-variant rounded-lg pl-3 pr-9 py-2.5 font-body-sm text-body-sm text-on-surface cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary">
            <option value="">All categories</option>
            <?php foreach ($parents as $pid => $parent):
              if (!isset($kids[$pid])) { continue; } ?>
              <optgroup label="<?= e($parent['name']) ?>">
                <?php foreach ($kids[$pid] as $c): ?>
                  <option value="<?= e($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-lg text-on-surface-variant pointer-events-none">expand_more</span>
        </div>
      </div>

      <div class="bg-surface-container-lowest p-4 rounded-xl shadow-xs">
        <span class="font-headline-sm text-headline-sm text-on-surface font-bold mb-3 block">Price (<?= e(CURRENCY) ?>)</span>
        <div class="flex items-center gap-2">
          <input type="number" name="min" min="0" placeholder="Min" value="<?= e($min !== null ? (string) (int) $min : '') ?>"
                 class="w-full px-2.5 py-2 bg-surface-container border border-outline-variant rounded text-body-sm text-on-surface outline-none focus:border-primary"/>
          <span class="text-outline">&ndash;</span>
          <input type="number" name="max" min="0" placeholder="Max" value="<?= e($max !== null ? (string) (int) $max : '') ?>"
                 class="w-full px-2.5 py-2 bg-surface-container border border-outline-variant rounded text-body-sm text-on-surface outline-none focus:border-primary"/>
        </div>
        <label class="flex items-center gap-2 mt-3 font-body-sm text-body-sm text-on-surface-variant cursor-pointer">
          <input type="checkbox" name="sale" value="1" <?= $onSale ? 'checked' : '' ?> class="accent-primary"/> On sale only
        </label>
        <div class="flex gap-2 mt-3">
          <button class="flex-1 py-2 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-wider rounded-lg hover:bg-primary transition-colors" type="submit">Apply</button>
          <a class="px-3 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container" href="<?= e(url('shop.php')) ?>">Reset</a>
        </div>
      </div>
    </form>
    <?php
}
