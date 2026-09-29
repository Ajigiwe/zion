<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$pages = [
    'shipping-and-returns' => [
        'title'  => 'Shipping & Returns',
        'eyebrow' => 'Delivery & Discretion',
        'blocks' => [
            ['h' => 'Greater Accra & Kumasi', 'p' => 'Orders confirmed before 1:00 PM GMT are dispatched the same day through our discreet courier network. Delivery is complimentary on orders above ' . FREE_SHIPPING_THRESHOLD . ' cedis, otherwise ' . SHIPPING_METRO . ' cedis flat within Greater Accra.'],
            ['h' => 'Regional delivery', 'p' => 'Kumasi, Takoradi, Cape Coast, Koforidua, Ho and Tamale receive doorstep or pick-up station delivery within 48 hours for ' . SHIPPING_REGIONAL . ' cedis.'],
            ['h' => 'Discretion policy', 'p' => 'Every intimate order travels in a plain, unmarked luxury charcoal carton. The waybill declares "Household Goods". Couriers are never told the contents of your parcel, anywhere in Ghana.'],
            ['h' => 'Returns & exchanges', 'p' => 'Unworn intimates with hygiene seals intact may be exchanged within 7 days for a different size. Instrument purchases carry the manufacturer warranty; showroom units may be returned within 7 days in original packaging.'],
        ],
    ],
    'faq' => [
        'title'  => 'Frequently Asked Questions',
        'eyebrow' => 'Help Centre',
        'blocks' => [
            ['h' => 'How discreet is the packaging?', 'p' => 'Completely. Unbranded outer cartons, neutral waybills and blind-courier handling as standard on every intimate order.'],
            ['h' => 'Which payment methods do you accept?', 'p' => 'MTN Mobile Money, Telecel Cash, AT Money, Visa and Mastercard, plus cash on delivery in Greater Accra, Kumasi and Takoradi.'],
            ['h' => 'Are the instruments under warranty?', 'p' => 'Yes. All instruments are imported through authorised channels and ship with full regional manufacturer warranty and a Zion authenticity card.'],
            ['h' => 'Can I collect from a showroom?', 'p' => 'Yes - choose "Self Pick" at checkout and your order is prepared at the Airport Showroom, typically ready within two hours.'],
        ],
    ],
    'contact' => [
        'title'  => 'Contact Us',
        'eyebrow' => 'Accra Showroom',
        'blocks' => [],
    ],
    'size-guide' => [
        'title'  => 'Size Guide',
        'eyebrow' => 'Fit & Measurements',
        'blocks' => [
            ['h' => 'How to measure', 'p' => 'Use a soft tape over bare skin, kept level and comfortably snug. For band size, measure directly under the bust; for cups, measure the fullest point.'],
            ['h' => 'Standard size run', 'p' => 'XS, S, M, L, XL and XXL across the Zion atelier lines. Numeric bra sizes run 32B through 38D in the balconette and plunge families.'],
            ['h' => 'Exchange guarantee', 'p' => 'If the fit is not right, contact us within 7 days for a hassle-free size exchange on unworn pieces.'],
        ],
    ],
    'about-us' => [
        'title'  => 'About Us',
        'eyebrow' => 'Zion Luxury Retail Ltd.',
        'blocks' => [
            ['h' => 'Two houses, one standard', 'p' => 'Zion Groups curates sensual high-fashion intimates alongside master-crafted musical instruments - two sensory worlds held to a single standard of quality, discretion and service.'],
            ['h' => 'Built for Ghana', 'p' => 'Local pricing in cedis, mobile-money-first checkout, nationwide courier coverage and a physical showroom where you can see, hear and feel everything before you buy.'],
        ],
    ],
    'our-story' => [
        'title'  => 'Our Story',
        'eyebrow' => 'Est. Accra',
        'blocks' => [
            ['h' => 'From a fitting room and a listening room', 'p' => 'What began as a private atelier in Accra grew into a house that treats lingerie and instruments with the same reverence: cut, tone, feel and the confidence they give the person holding them.'],
            ['h' => 'Authorized, always', 'p' => 'We work directly with manufacturers and authorised distributors so that every instrument carries a genuine warranty and every intimate carries a promise of privacy.'],
        ],
    ],
    'privacy-policy' => [
        'title'  => 'Privacy Policy',
        'eyebrow' => 'Your Data',
        'blocks' => [
            ['h' => 'What we collect', 'p' => 'Your name, contact details, delivery addresses and order history - only what is needed to sell, deliver and support your purchase.'],
            ['h' => 'Payment data', 'p' => 'We never store your MoMo PIN or card security code. Card and mobile-money transactions are settled over encrypted channels with our payment partners.'],
            ['h' => 'Sharing', 'p' => 'We share your delivery details with couriers strictly to complete your order, and never for marketing. Parcel contents are never disclosed to couriers.'],
        ],
    ],
    'terms' => [
        'title'  => 'Terms of Service',
        'eyebrow' => 'Conditions',
        'blocks' => [
            ['h' => 'Orders', 'p' => 'An order is accepted once payment is authorised or, for cash on delivery, once the dispatch confirmation is issued. Stock is reserved for 45 minutes during checkout.'],
            ['h' => 'Pricing', 'p' => 'All prices are quoted in Ghana cedis and include applicable VAT unless stated otherwise.'],
            ['h' => 'Warranty', 'p' => 'Musical instruments carry the regional manufacturer warranty. Intimate apparel is exchangeable within 7 days provided hygiene seals remain intact.'],
        ],
    ],
];

$slug = (string) ($_GET['slug'] ?? 'about-us');
if (!isset($pages[$slug])) {
    http_response_code(404);
    $slug = 'about-us';
}
$page = $pages[$slug];

set_title($page['title'] . ' | Zion Groups');
$lead = (string) ($page['blocks'][0]['p'] ?? '');
set_meta(
    $lead !== ''
        ? $page['title'] . ' - ' . mb_strimwidth(trim($lead), 0, 140, '…')
        : (string) setting('site_description')
);
render_head();

if ($slug === 'contact'):
    $user       = current_user();
    $mapLat     = (float) setting('map_lat');
    $mapLng     = (float) setting('map_lng');
    $gmapsLink  = 'https://www.google.com/maps/search/?api=1&query=' . $mapLat . ',' . $mapLng;
    $channels   = [
        ['call', 'Call the concierge', setting('contact_phone'), 'Orders, warranties and delivery tracking', contact_phone_href(), 'Call now'],
        ['chat', 'WhatsApp', '+' . preg_replace('/\D/', '', setting('contact_whatsapp')), 'Fitting advice and stock checks, 7 days a week', whatsapp_url('Hello Zion Groups, I would like some advice.'), 'Open WhatsApp'],
        ['mail', 'Email', setting('contact_email'), 'Wholesale, partnerships and detailed enquiries', 'mailto:' . setting('contact_email'), 'Write to us'],
        ['location_on', 'Showroom', setting('address_line'), 'Private fitting suites and a treated listening room', map_directions_url(), 'Get directions'],
    ];
    $teams = [
        ['style', 'Styling & Fitting Concierge', 'Bra fittings, size exchanges, personal shopping and gift selection - in showroom or over WhatsApp.', whatsapp_url('Hello, I would like to book a fitting.')],
        ['music_note', 'Instrument Showroom', 'Demos on the acoustic suite floor, trade-ins, studio setup advice and authorised warranty advice.', 'mailto:' . setting('contact_email')],
        ['support_agent', 'Orders & After-Sales', 'Order status, discreet delivery updates, returns, exchanges and service bookings.', contact_phone_href()],
    ];
?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]" data-ajax-out>
  <div class="max-w-[1360px] mx-auto px-margin py-space-xl">

    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold">Contact</span>
    </nav>

    <!-- Hero -->
    <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md md:p-space-lg mb-space-md">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-md items-center">
        <div class="lg:col-span-7">
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-2">Contact &amp; Showroom</span>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-2">Let&rsquo;s talk.</h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl"><?= e(setting('contact_response')) ?></p>
          <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4 font-body-sm text-body-sm text-on-surface">
            <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-secondary text-base">schedule</span><?= e(setting('contact_hours')) ?></span>
          </div>
        </div>
        <div class="lg:col-span-5 grid grid-cols-3 gap-space-sm">
          <?php
          $stats = [
              ['2 hrs', 'Average reply'],
              ['7 days', 'A week'],
              ['2', 'Showroom floors'],
          ];
          foreach ($stats as [$big, $small]): ?>
            <div class="bg-surface-container rounded-xl p-3 text-center">
              <span class="font-headline-sm text-headline-sm text-primary font-bold block"><?= e($big) ?></span>
              <span class="font-label-tag text-label-tag uppercase tracking-wider text-on-surface-variant"><?= e($small) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
      <!-- Form -->
      <section class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-xs p-space-md md:p-space-lg">
        <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Send a message</span>
        <h2 class="font-headline-md text-headline-md text-on-surface font-bold mb-space-sm">How can we help?</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">Every message lands with a real person in Accra - not a bot.</p>

        <form data-ajax method="post" action="<?= e(url('actions.php')) ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact_send"/>
          <input type="hidden" name="return" value="page.php?slug=contact"/>
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true"/>

          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Full name <span class="text-error">*</span></span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="name" required value="<?= e($user['name'] ?? '') ?>" placeholder="Ama Mensah"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Email <span class="text-error">*</span></span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   type="email" name="email" required value="<?= e($user['email'] ?? '') ?>" placeholder="you@example.com"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Phone (optional)</span>
            <input class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                   name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+233 20 000 0000"/>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Topic <span class="text-error">*</span></span>
            <select class="h-11 px-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none bg-surface-container-lowest"
                    name="subject" required>
              <?php foreach ([
                  'General enquiry',
                  'Order support & delivery',
                  'Fitting & styling advice',
                  'Instrument demo or purchase',
                  'Warranty & servicing',
                  'Wholesale & partnerships',
              ] as $opt): ?>
                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="flex flex-col gap-1 sm:col-span-2">
            <span class="font-label-nav text-label-nav text-on-surface-variant uppercase tracking-wider">Message <span class="text-error">*</span></span>
            <textarea class="min-h-36 p-3 border border-outline-variant rounded font-body-sm text-body-sm focus:border-primary outline-none"
                      name="message" required placeholder="Tell us what you need - include an order number if you have one."></textarea>
          </label>

          <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-outline-variant/60">
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">
              By sending this message you agree to our
              <a class="text-primary underline" href="<?= e(url('page.php?slug=privacy-policy')) ?>">privacy policy</a>.
              We never share your details.
            </p>
            <button class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors"
                    type="submit">Send message</button>
          </div>
        </form>
      </section>

      <!-- Channels + hours -->
      <aside class="flex flex-col gap-space-md">
        <div class="grid grid-cols-1 gap-space-sm">
          <?php foreach ($channels as [$icon, $label, $value, $hint, $href, $cta]): ?>
            <a class="group bg-surface-container-lowest rounded-xl shadow-xs p-4 flex gap-3 hover:shadow-md transition-shadow"
               href="<?= e($href) ?>" <?= str_starts_with($href, 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
              <span class="w-10 h-10 shrink-0 rounded-lg bg-primary-fixed text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-xl"><?= e($icon) ?></span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant block"><?= e($label) ?></span>
                <span class="font-body-sm text-body-sm text-on-surface font-semibold block truncate"><?= e($value) ?></span>
                <span class="font-body-sm text-body-sm text-on-surface-variant block mt-0.5"><?= e($hint) ?></span>
              </span>
              <span class="material-symbols-outlined text-lg text-outline self-center group-hover:text-primary transition-colors">arrow_outward</span>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="bg-inverse-surface text-surface rounded-xl p-space-md">
          <span class="material-symbols-outlined text-secondary-fixed mb-1">schedule</span>
          <h3 class="font-headline-sm text-headline-sm font-bold mb-2">Opening hours</h3>
          <div class="flex flex-col gap-1 font-body-sm text-body-sm text-surface-dim">
            <?php
            $hours = array_filter(array_map('trim', explode('|', setting('contact_hours'))));
            foreach ($hours as $block):
                if (str_contains($block, ':')) {
                    [$days, $time] = array_pad(explode(':', $block, 2), 2, '');
                    echo '<div class="flex justify-between gap-3"><span>' . e(trim($days)) . '</span><span class="text-surface font-semibold">' . e(trim($time)) . '</span></div>';
                } else {
                    echo '<div class="text-surface">' . e($block) . '</div>';
                }
            endforeach; ?>
          </div>
          <p class="font-body-sm text-body-sm text-secondary-fixed mt-3"><?= e(setting('contact_response')) ?></p>
        </div>
      </aside>
    </div>

    <!-- Map -->
    <section class="mt-space-md bg-surface-container-lowest rounded-xl shadow-xs overflow-hidden">
      <div class="flex flex-wrap items-end justify-between gap-3 p-space-md md:p-space-lg pb-0">
        <div>
          <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1">Find us</span>
          <h2 class="font-headline-md text-headline-md text-on-surface font-bold"><?= e(setting('address_line')) ?></h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-1"><?= e(setting('address_city')) ?></p>
        </div>
        <div class="flex flex-wrap gap-3">
          <a class="px-5 py-2.5 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface hover:bg-surface-container transition-colors"
             href="<?= e($gmapsLink) ?>" target="_blank" rel="noopener">Open in Maps</a>
          <a class="px-5 py-2.5 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors"
             href="<?= e(map_directions_url()) ?>" target="_blank" rel="noopener">Get directions</a>
        </div>
      </div>
      <div class="relative mt-space-md h-[360px] md:h-[440px] bg-surface-container">
        <iframe class="w-full h-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                title="Zion Groups showroom map" src="<?= e(map_embed_url()) ?>"></iframe>
        <div class="absolute left-4 bottom-4 bg-surface-container-lowest/95 backdrop-blur shadow-md rounded-lg px-4 py-3 max-w-xs">
          <span class="font-label-nav text-label-nav uppercase tracking-wider text-on-surface-variant block">Zion Groups of Companies</span>
          <span class="font-body-sm text-body-sm text-on-surface block mt-0.5"><?= e(address_block()) ?></span>
          <a class="font-body-sm text-body-sm text-primary underline inline-block mt-1" href="<?= e(contact_phone_href()) ?>"><?= e(setting('contact_phone')) ?></a>
        </div>
      </div>
    </section>

    <!-- Teams -->
    <section class="mt-space-md">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
        <?php foreach ($teams as [$icon, $title, $copy, $href]): ?>
          <div class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md flex flex-col">
            <span class="w-10 h-10 rounded-lg bg-secondary-fixed text-on-secondary-fixed flex items-center justify-center mb-3">
              <span class="material-symbols-outlined text-xl"><?= e($icon) ?></span>
            </span>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-1"><?= e($title) ?></h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant flex-1"><?= e($copy) ?></p>
            <a class="mt-3 inline-flex items-center gap-1.5 font-label-nav text-label-nav uppercase tracking-wider text-primary font-bold hover:text-primary-container transition-colors"
               href="<?= e($href) ?>" <?= str_starts_with($href, 'http') ? 'target="_blank" rel="noopener"' : '' ?>>
              Contact this team <span class="material-symbols-outlined text-base">arrow_forward</span>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Help shortcuts -->
    <div class="mt-space-md flex flex-wrap gap-3">
      <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary transition-colors" href="<?= e(url('page.php?slug=faq')) ?>">Read the FAQ</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container-lowest transition-colors" href="<?= e(url('page.php?slug=shipping-and-returns')) ?>">Shipping &amp; returns</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container-lowest transition-colors" href="<?= e(url('page.php?slug=size-guide')) ?>">Size guide</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container-lowest transition-colors" href="<?= e(url('shop.php')) ?>">Browse the collection</a>
    </div>
  </div>
</main>
<?php
    render_foot();
    return;
endif;

?>
<main class="w-full pt-24 bg-surface min-h-[calc(100vh-280px)]" data-ajax-out>
  <div class="max-w-[860px] mx-auto px-margin py-space-xl">
    <nav class="font-label-nav text-label-nav text-on-surface-variant mb-4 flex items-center gap-2" aria-label="Breadcrumb">
      <a class="hover:text-primary" href="<?= e(url('index.php')) ?>">Home</a>
      <span class="text-outline-variant">/</span>
      <span class="text-primary font-semibold"><?= e($page['title']) ?></span>
    </nav>

    <span class="font-label-tag text-label-tag text-secondary uppercase tracking-[0.18em] block mb-1"><?= e($page['eyebrow']) ?></span>
    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-space-md"><?= e($page['title']) ?></h1>

    <div class="flex flex-col gap-space-md">
      <?php foreach ($page['blocks'] as $b): ?>
        <section class="bg-surface-container-lowest rounded-xl shadow-xs p-space-md">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-1"><?= e($b['h']) ?></h2>
          <p class="font-body-md text-body-md text-on-surface-variant"><?= e($b['p']) ?></p>
        </section>
      <?php endforeach; ?>
    </div>

    <div class="mt-space-lg flex flex-wrap gap-3">
      <a class="px-6 py-3 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" href="<?= e(url('shop.php')) ?>">Browse the collection</a>
      <a class="px-6 py-3 border border-outline-variant text-on-surface rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-surface-container" href="<?= e(url('page.php?slug=contact')) ?>">Talk to us</a>
    </div>
  </div>
</main>
<?php render_foot(); ?>
