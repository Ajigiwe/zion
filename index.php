<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/product_card.php';

set_title('Express Yourself | Zion Groups of Companies | Lingerie & Instruments, Ghana');
set_meta(
    'Zion Groups of Companies - luxury lingerie, professional musical instruments, pro audio and church worship equipment in Tarkwa, Ghana. '
    . 'Discreet nationwide delivery, showroom try-ons and secure Paystack checkout.'
);
set_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => SITE_NAME,
    'url'      => APP_URL,
    'email'    => setting('contact_email'),
    'telephone' => setting('contact_phone'),
    'address'  => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => setting('address_line'),
        'addressLocality' => setting('address_city'),
        'addressCountry'  => setting('address_country'),
    ],
]);

$featuredByDept = [];
foreach (['lingerie', 'instruments'] as $dept) {
    $featuredByDept[$dept] = db_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND p.is_featured = 1 AND p.department = ?
         ORDER BY COALESCE(c.sort_order, 9999), p.rating DESC, p.id ASC LIMIT 20',
        [$dept]
    );
}
$hasFeatured = ($featuredByDept['lingerie'] !== [] || $featuredByDept['instruments'] !== []);

$homeNew = home_new_count();
$newProducts = $homeNew > 0 ? db_all(
    'SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT ' . $homeNew
) : [];

$categories = db_all(
    'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order'
);
$bySlug = [];
foreach ($categories as $c) {
    $bySlug[$c['slug']] = $c;
}
// Homepage tiles, in the order used by the original design.
$tileSlugs = ['church-worship', 'music-instruments', 'professional-audio', 'lingerie', 'keyboards', 'microphones'];
$homeCats  = array_values(array_filter(array_map(fn($s) => $bySlug[$s] ?? null, $tileSlugs)));
$heroLingerie    = img_url((string) ($bySlug['lingerie']['image_url'] ?? ''));
$heroInstruments = img_url((string) ($bySlug['guitars']['image_url'] ?? ''));

$heroSlides = hero_slides();
$heroDelay  = hero_autoplay_seconds();
$heroCount  = count($heroSlides);
$heroMulti  = $heroCount > 1;

render_head();
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]">
<div class="flex flex-col w-full">

  <!-- 1. EDITORIAL HERO (editable slider) -->
  <section class="relative w-full overflow-hidden bg-inverse-surface text-surface"
           data-hero-slider data-hero-autoplay="<?= (int) ($heroMulti ? $heroDelay * 1000 : 0) ?>"
           aria-roledescription="carousel" aria-label="Zion Groups highlights">
    <div class="relative w-full min-h-[580px] lg:min-h-[660px] flex items-center">
      <!-- backgrounds -->
      <div class="absolute inset-0 opacity-90 pointer-events-none">
        <?php foreach ($heroSlides as $i => $slide): ?>
          <?php
            $bg = $slide['image_url'];
            if ($bg === '') {
                $bg = (stripos($slide['cta_href'], 'instruments') !== false || stripos($slide['cta_href'], 'guitar') !== false)
                    ? $heroInstruments
                    : $heroLingerie;
            }
            $bg = $bg !== '' ? img_url($bg) : '';
            ?>
          <div class="absolute inset-0 bg-cover bg-center transition-[opacity,visibility] duration-700<?= $i === 0 ? '' : ' opacity-0 invisible' ?>"
               data-hero-bg="<?= (int) $i ?>" style="background-image: url('<?= e($bg) ?>')"></div>
        <?php endforeach; ?>
        <div class="absolute inset-0 bg-inverse-surface/40"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-inverse-surface/85 via-inverse-surface/40 to-inverse-surface/85"></div>
      </div>
      <div class="absolute inset-0 bg-gradient-to-b from-inverse-surface/60 via-transparent to-inverse-surface pointer-events-none"></div>

      <div class="relative z-10 max-w-[1360px] mx-auto px-margin w-full py-space-xl text-center flex flex-col items-center justify-center">
        <div class="grid grid-cols-1 w-full" data-hero-track>
          <?php foreach ($heroSlides as $i => $slide): ?>
            <?php
            $hidden = $i === 0 ? '' : ' opacity-0 invisible pointer-events-none';
            $hTag   = $i === 0 ? 'h1' : 'h2';
            $links  = [];
            foreach ([[$slide['cta_label'], $slide['cta_href']], [$slide['cta2_label'], $slide['cta2_href']]] as [$lab, $href]) {
                if ($lab === '' || $href === '') {
                    continue;
                }
                $links[] = [preg_match('#^https?://#i', $href) === 1 ? $href : url(ltrim($href, '/')), $lab];
            }
            ?>
            <div class="col-start-1 row-start-1 flex flex-col items-center transition-[opacity,visibility] duration-700<?= $hidden ?>"
                 data-hero-slide="<?= (int) $i ?>" role="group" aria-roledescription="slide"
                 aria-label="<?= (int) ($i + 1) ?> of <?= (int) $heroCount ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
              <<?= $hTag ?> class="font-display-hero text-display-hero md:text-[56px] md:leading-[64px] text-surface font-bold tracking-tight max-w-4xl mx-auto uppercase"><?= e($slide['title']) ?></<?= $hTag ?>>
              <?php if ($slide['subtitle'] !== ''): ?>
                <p class="font-title-editorial text-title-editorial text-secondary-fixed italic mt-3 max-w-xl mx-auto"><?= e($slide['subtitle']) ?></p>
              <?php endif; ?>
              <?php if ($slide['body'] !== ''): ?>
                <p class="font-body-md text-body-md text-surface-dim max-w-md mx-auto mt-2"><?= e($slide['body']) ?></p>
              <?php endif; ?>
              <?php if ($links !== []): ?>
                <div class="flex flex-wrap items-center justify-center gap-space-md mt-space-lg">
                  <?php foreach ($links as $n => [$href, $label]): ?>
                    <a class="inline-flex items-center justify-center px-8 py-3.5 <?= $n === 0 ? 'bg-primary-container text-on-primary shadow-md hover:bg-primary' : 'bg-surface/10 backdrop-blur-md text-surface shadow-sm hover:bg-secondary-fixed hover:text-inverse-surface' ?> font-label-nav text-label-nav font-bold tracking-widest uppercase rounded-lg transition-all duration-300 transform hover:-translate-y-0.5" href="<?= e($href) ?>"><?= e($label) ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($heroMulti): ?>
          <!-- dots -->
          <div class="flex items-center justify-center gap-2.5 mt-space-lg" data-hero-dots>
            <?php foreach ($heroSlides as $i => $slide): ?>
              <button type="button" data-hero-dot="<?= (int) $i ?>"
                      aria-label="Show slide <?= (int) ($i + 1) ?> of <?= (int) $heroCount ?>"
                      aria-current="<?= $i === 0 ? 'true' : 'false' ?>"
                      class="h-2 <?= $i === 0 ? 'w-7 bg-secondary-fixed' : 'w-2 bg-surface/40 hover:bg-surface/70' ?> rounded-full transition-all duration-300"></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($heroMulti): ?>
        <!-- arrows -->
        <button type="button" data-hero-prev aria-label="Previous slide"
                class="hidden sm:flex absolute left-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full border border-white/20 bg-surface/10 backdrop-blur-md text-surface items-center justify-center hover:bg-surface/25 transition-colors">
          <span class="material-symbols-outlined">chevron_left</span>
        </button>
        <button type="button" data-hero-next aria-label="Next slide"
                class="hidden sm:flex absolute right-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full border border-white/20 bg-surface/10 backdrop-blur-md text-surface items-center justify-center hover:bg-surface/25 transition-colors">
          <span class="material-symbols-outlined">chevron_right</span>
        </button>
      <?php endif; ?>
    </div>
  </section>

  <!-- 2. SHOP BY CATEGORY -->
  <section class="w-full max-w-[1360px] mx-auto px-margin py-space-xl">
    <div class="flex items-end justify-between mb-space-lg">
      <div>
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Curation</span>
        <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">Shop by Category</h2>
      </div>
      <a class="hidden sm:inline-flex items-center gap-1.5 font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:text-primary-container transition-colors" href="<?= e(url('shop.php')) ?>">
        All Categories <span class="material-symbols-outlined text-base">arrow_forward</span>
      </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-space-md">
      <?php foreach ($homeCats as $cat): ?>
        <a class="group flex flex-col bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300"
           href="<?= e(url('category.php?slug=' . urlencode($cat['slug']))) ?>">
          <div class="relative w-full aspect-square bg-surface-container overflow-hidden">
            <img class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500 ease-out"
                 src="<?= e(img_url($cat['image_url'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy"/>
          </div>
          <div class="p-space-sm flex flex-col flex-1 justify-between bg-surface-container-lowest">
            <span class="font-headline-sm text-headline-sm text-on-surface font-bold line-clamp-1"><?= e($cat['name']) ?></span>
            <span class="inline-flex items-center gap-1 font-label-nav text-label-nav text-primary font-medium mt-1 group-hover:translate-x-0.5 transition-transform">
              Shop Now <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 3. FEATURED PRODUCTS (up to 20 per department) -->
  <?php if ($hasFeatured): ?>
  <section class="w-full bg-surface-container-low py-space-xl">
    <div class="max-w-[1360px] mx-auto px-margin">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-space-lg gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2 h-2 rounded-full bg-primary"></span>
            <span class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-[0.18em]">Handpicked Luxury</span>
          </div>
          <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">Featured Products</h2>
        </div>
        <a class="inline-flex items-center gap-1.5 font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:text-primary-container transition-colors" href="<?= e(url('shop.php')) ?>">
          View All Products <span class="material-symbols-outlined text-base">arrow_forward</span>
        </a>
      </div>

      <?php foreach (['lingerie' => 'Featured Lingerie', 'instruments' => 'Featured Instruments & Audio'] as $dept => $deptTitle): ?>
        <?php if ($featuredByDept[$dept] === []) { continue; } ?>
        <div class="flex items-center justify-between mt-space-lg mb-space-md first:mt-0">
          <h3 class="font-headline-md text-headline-md text-on-surface font-bold"><?= e($deptTitle) ?></h3>
          <a class="inline-flex items-center gap-1 font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:text-primary-container transition-colors" href="<?= e(url('shop.php?dept=' . $dept)) ?>">
            Shop <?= $dept === 'lingerie' ? 'Lingerie' : 'Music & Audio' ?> <span class="material-symbols-outlined text-base">arrow_forward</span>
          </a>
        </div>
        <div class="product-grid grid <?= e(product_grid_classes()) ?> gap-3 sm:gap-space-lg">
          <?php foreach ($featuredByDept[$dept] as $p): ?>
            <?php product_card($p); ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($homeNew > 0 && $newProducts !== []): ?>
  <!-- 3b. NEW ARRIVALS -->
  <section class="w-full bg-surface py-space-xl">
    <div class="max-w-[1360px] mx-auto px-margin">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-space-lg gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="w-2 h-2 rounded-full bg-secondary"></span>
            <span class="font-label-tag text-label-tag text-on-surface-variant uppercase tracking-[0.18em]">Just Landed</span>
          </div>
          <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold">New Arrivals</h2>
        </div>
        <a class="inline-flex items-center gap-1.5 font-label-nav text-label-nav text-primary font-bold uppercase tracking-wider hover:text-primary-container transition-colors" href="<?= e(url('shop.php?sort=newest')) ?>">
          Shop Newest <span class="material-symbols-outlined text-base">arrow_forward</span>
        </a>
      </div>

      <div class="product-grid grid <?= e(product_grid_classes()) ?> gap-3 sm:gap-space-lg">
        <?php foreach ($newProducts as $p): ?>
          <?php product_card($p); ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 4. EDITORIAL SPLIT BANNERS -->
  <section class="w-full max-w-[1360px] mx-auto px-margin py-space-xl">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg">
      <div class="relative rounded-2xl overflow-hidden min-h-[380px] sm:min-h-[440px] flex items-end p-8 sm:p-12 shadow-lg group">
        <div class="absolute inset-0 bg-cover bg-center group-hover:scale-105 transition-transform duration-700 ease-out"
             style="background-image: url('<?= e($heroLingerie) ?>')"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-inverse-surface via-inverse-surface/60 to-transparent"></div>
        <div class="relative z-10 max-w-md">
          <span class="font-label-tag text-label-tag uppercase tracking-[0.2em] text-secondary-fixed block mb-2">Exquisite Intimates</span>
          <h2 class="font-headline-lg text-headline-lg text-surface font-bold">Lingerie Collection</h2>
          <p class="font-title-editorial text-title-editorial text-surface-dim italic mt-1 mb-6">Feel confident. Always.</p>
          <a class="inline-flex items-center gap-2 px-6 py-3 bg-surface text-on-surface font-label-nav text-label-nav font-bold tracking-widest uppercase rounded-lg shadow-md hover:bg-secondary-fixed hover:text-on-secondary-fixed transition-colors" href="<?= e(url('shop.php?dept=lingerie')) ?>">
            Explore Now <span class="material-symbols-outlined text-base">arrow_forward</span>
          </a>
        </div>
      </div>

      <div class="relative rounded-2xl overflow-hidden min-h-[380px] sm:min-h-[440px] flex items-end p-8 sm:p-12 shadow-lg group">
        <div class="absolute inset-0 bg-cover bg-center group-hover:scale-105 transition-transform duration-700 ease-out"
             style="background-image: url('<?= e($heroInstruments) ?>')"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-inverse-surface via-inverse-surface/60 to-transparent"></div>
        <div class="relative z-10 max-w-md">
          <span class="font-label-tag text-label-tag uppercase tracking-[0.2em] text-secondary-fixed block mb-2">Master Sound &amp; Gear</span>
          <h2 class="font-headline-lg text-headline-lg text-surface font-bold">Premium Instruments</h2>
          <p class="font-title-editorial text-title-editorial text-surface-dim italic mt-1 mb-6">For every sound, every stage.</p>
          <a class="inline-flex items-center gap-2 px-6 py-3 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold tracking-widest uppercase rounded-lg shadow-md hover:bg-primary transition-colors" href="<?= e(url('shop.php?dept=instruments')) ?>">
            Shop Now <span class="material-symbols-outlined text-base">arrow_forward</span>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- 6. BRANDS -->
  <section class="w-full py-space-xl bg-surface">
    <div class="max-w-[1360px] mx-auto px-margin text-center">
      <span class="font-label-tag text-label-tag text-outline uppercase tracking-[0.25em] block mb-space-md">Recognized &amp; Authorized Global Partners</span>
      <div class="flex flex-wrap items-center justify-center gap-8 md:gap-14 opacity-75">
        <?php foreach (['YAMAHA', 'ROLAND', 'Fender', 'SHURE', 'LA PERLA', 'AGENT PROVOCATEUR', 'AUDIO-TECHNICA'] as $b): ?>
          <span class="font-headline-sm text-headline-sm tracking-widest uppercase font-bold text-on-surface-variant"><?= e($b) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- 7. NEWSLETTER -->
  <section class="w-full max-w-[1360px] mx-auto px-margin pb-space-xl">
    <div class="relative bg-inverse-surface text-surface rounded-2xl p-8 sm:p-14 overflow-hidden shadow-xl">
      <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-primary-container/20 blur-3xl pointer-events-none"></div>
      <div class="absolute -left-20 -bottom-20 w-80 h-80 rounded-full bg-secondary/15 blur-3xl pointer-events-none"></div>
      <div class="relative z-10 max-w-2xl mx-auto text-center flex flex-col items-center">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface/10 text-secondary-fixed font-label-tag text-label-tag uppercase tracking-[0.15em] mb-3">VIP Access &amp; Weekly Curations</span>
        <h2 class="font-headline-lg text-headline-lg sm:text-[36px] text-surface font-bold">Stay in the loop.</h2>
        <p class="font-body-md text-body-md text-surface-dim mt-2 max-w-lg">
          Get private notifications for limited intimacy drops, exclusive musician masterclasses, and secret discount codes delivered directly to your inbox.
        </p>
        <form class="w-full max-w-md mt-space-md flex flex-col sm:flex-row gap-2" method="post" action="<?= e(url('actions.php')) ?>" data-newsletter>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="newsletter"/>
          <input class="flex-1 px-4 py-3 bg-surface-container-lowest text-on-surface placeholder:text-outline font-body-sm text-body-sm rounded-lg outline-none focus:ring-2 focus:ring-secondary-fixed transition-all"
                 placeholder="Enter your email address" required type="email" name="email"/>
          <button class="px-7 py-3 bg-primary-container text-on-primary font-label-nav text-label-nav font-bold uppercase tracking-wider rounded-lg shadow-md hover:bg-primary transition-colors shrink-0" type="submit">Subscribe</button>
        </form>
        <p class="font-body-sm text-body-sm text-surface-dim/70 mt-3 text-center">
          Strict confidentiality guaranteed. We never share your data. Unsubscribe at any time.
        </p>
      </div>
    </div>
  </section>
</div>
</main>
<script src="<?= e(url('assets/hero-slider.js')) ?>"></script>
<?php render_foot(); ?>
