<?php
/**
 * Full-bleed brand art panel for the split auth pages (login / register).
 * Expects: $artSide  'left' | 'right' (desktop half).
 *
 * Slides come from Settings > Sign-in slider and fall back to catalogue
 * images when none are saved. Each slide may carry its own caption (shown
 * under the tagline); blank captions use the standard blurb. On phones the
 * panel sits behind the form card.
 */

$artSide = ($artSide ?? 'left') === 'right' ? 'right' : 'left';
$artPos  = $artSide === 'right' ? 'lg:left-auto lg:right-0' : 'lg:left-0 lg:right-auto';
$tagline = (string) setting('site_tagline');

$artSlides  = auth_slider_slides();
$artDefault = 'Hand-finished luxury intimates and master-grade instruments, curated in Tarkwa for the discerning few.';
$artCaptions = $artSlides !== [] ? $artSlides : [['image_url' => '', 'caption' => '']];
$artCount   = count($artSlides);
$artMulti   = $artCount > 1;
?>
<div class="absolute inset-0 lg:w-1/2 <?= e($artPos) ?>" aria-hidden="true">
  <div class="absolute inset-0" data-auth-slider data-auth-autoplay="<?= (int) (auth_slider_autoplay_seconds() * 1000) ?>">
    <?php foreach ($artSlides as $i => $s): ?>
      <img class="absolute inset-0 w-full h-full object-cover transition-[opacity,visibility] duration-700<?= $i === 0 ? '' : ' opacity-0 invisible' ?>"
           src="<?= e(img_url($s['image_url'])) ?>" alt="" data-auth-slide="<?= (int) $i ?>"
           <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>/>
    <?php endforeach; ?>

    <div class="absolute inset-0 bg-inverse-surface/75 lg:bg-inverse-surface/60"></div>

    <?php if ($artMulti): ?>
      <!-- dots (desktop only: on phones the card covers the panel) -->
      <div class="hidden lg:flex absolute bottom-12 xl:bottom-16 right-12 xl:right-16 items-center gap-2">
        <?php foreach ($artSlides as $i => $s): ?>
          <button type="button" data-auth-dot="<?= (int) $i ?>" tabindex="-1"
                  aria-label="Show slide <?= (int) ($i + 1) ?> of <?= (int) $artCount ?>"
                  aria-current="<?= $i === 0 ? 'true' : 'false' ?>"
                  class="h-2 <?= $i === 0 ? 'w-7 bg-secondary-fixed' : 'w-2 bg-surface/40 hover:bg-surface/70' ?> rounded-full transition-all duration-300"></button>
        <?php endforeach; ?>
      </div>

      <!-- arrows -->
      <button type="button" data-auth-prev tabindex="-1" aria-label="Previous slide"
              class="hidden sm:flex absolute left-6 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full border border-white/20 bg-surface/10 backdrop-blur-md text-surface items-center justify-center hover:bg-surface/25 transition-colors">
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
      <button type="button" data-auth-next tabindex="-1" aria-label="Next slide"
              class="hidden sm:flex absolute right-6 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full border border-white/20 bg-surface/10 backdrop-blur-md text-surface items-center justify-center hover:bg-surface/25 transition-colors">
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
    <?php endif; ?>

    <div class="relative hidden lg:flex h-full flex-col justify-end gap-3 p-12 xl:p-16">
      <img class="h-14 w-auto" src="<?= e(url('assets/logo-mark.svg')) ?>" alt=""/>
      <span class="block h-px w-16 bg-secondary-fixed/70 my-1"></span>
      <p class="font-headline-lg text-headline-lg italic text-surface"><?= e($tagline) ?></p>
      <div class="grid">
        <?php foreach ($artCaptions as $i => $s): ?>
          <p class="col-start-1 row-start-1 font-body-md text-body-md text-surface-dim max-w-sm leading-relaxed transition-[opacity,visibility] duration-700<?= $i === 0 ? '' : ' opacity-0 invisible' ?>"
             data-auth-caption="<?= (int) $i ?>"><?= e($s['caption'] !== '' ? $s['caption'] : $artDefault) ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
