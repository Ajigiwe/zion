<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$pages = [
    'shipping-and-returns' => [
        'title'  => 'Shipping & Delivery',
        'eyebrow' => 'Last Updated 01 October 2026',
        'blocks' => [
            ['h' => 'Order confirmation', 'p' => 'Every order is confirmed by phone or WhatsApp before it is dispatched. We verify your items, delivery address, total and preferred delivery window, so nothing ships without your go-ahead.'],
            ['h' => 'Processing', 'p' => 'Orders are processed promptly during business hours. In-stock items are usually prepared the same day or the next working day. Made-to-order and imported items may need extra time, which we confirm upfront.'],
            ['h' => 'Delivery options', 'p' => 'Collect free from our Tarkwa locations (Market Circle, and the Tarkwa main station opposite Ben Betty / Hisense), choose local delivery within Tarkwa, or have your order couriered anywhere in Ghana - Accra, Kumasi, Takoradi, Cape Coast and beyond. Delivery fees are shown at checkout or communicated to you before you confirm.'],
            ['h' => 'Delivery timelines', 'p' => 'Tarkwa local: same-day or next-day. Major cities (Accra, Kumasi, Takoradi): typically 24 - 48 hours. Other locations: 2 - 5 working days. Remote areas and special items (large instruments, bulk church orders) may take a little longer - we always give you a realistic estimate.'],
            ['h' => 'Payment before dispatch', 'p' => 'Orders are dispatched after payment is received, or after a cash-on-delivery order has been confirmed. We accept MTN Mobile Money, Telecel Cash, AT Money, Visa / Mastercard and bank transfer. Cash on delivery is available in selected areas.'],
            ['h' => 'Tracking your order', 'p' => 'You receive dispatch confirmation with your tracking details, and you can follow order status anytime from your account. Our team also sends WhatsApp updates at key stages, from packing to out-for-delivery.'],
            ['h' => 'Delivery issues', 'p' => 'Parcel delayed, damaged or not arrived? Contact us within 48 hours of the expected delivery with your order number. We will trace the shipment with the courier and resolve it - redelivery, replacement or refund, whichever suits your situation.'],
            ['h' => 'Returns & exchanges', 'p' => 'Changed your mind? Unworn, unopened items in original packaging can be returned within 7 days. Lingerie and intimate apparel must be unworn with hygiene seals and tags intact for hygiene reasons. Faulty or incorrectly supplied items are replaced or refunded in full, including return shipping. Instruments carry the manufacturer warranty - see our terms for details.'],
        ],
    ],
    'faq' => [
        'title'  => 'Frequently Asked Questions',
        'eyebrow' => 'Help Centre',
        'blocks' => [
            ['h' => 'How do I place an order?', 'p' => 'Browse the shop, add items to your cart and check out online - or simply send us a WhatsApp message on 054 171 7773 and we will place the order for you and share payment details.'],
            ['h' => 'Do you deliver nationwide?', 'p' => 'Yes. We deliver across Ghana - Tarkwa, Accra, Kumasi, Takoradi, Cape Coast, Tamale and everywhere in between - by courier, and offer free collection from our Tarkwa locations.'],
            ['h' => 'How long does delivery take?', 'p' => 'Tarkwa local orders are typically same-day or next-day. Accra, Kumasi and Takoradi usually take 24 - 48 hours. Other locations take 2 - 5 working days.'],
            ['h' => 'Which payment methods do you accept?', 'p' => 'MTN Mobile Money, Telecel Cash, AT Money, Visa and Mastercard, bank transfer, and cash on delivery in selected areas. Payment instructions are sent after your order is confirmed.'],
            ['h' => 'Can I pay on delivery?', 'p' => 'Cash on delivery is available for selected locations and order types. Ask us on WhatsApp before ordering if you want to confirm availability for your area.'],
            ['h' => 'Can I chat with you on WhatsApp?', 'p' => 'Absolutely - WhatsApp is the fastest way to reach us on 054 171 7773. Fitting advice, stock checks, order status and warranty questions, seven days a week.'],
            ['h' => 'What is your return policy?', 'p' => 'Unworn, unopened items in original packaging can be returned within 7 days of delivery. If something arrives faulty or wrong, we fix it - replacement or full refund including return shipping.'],
            ['h' => 'Can I exchange lingerie for another size?', 'p' => 'Yes - unworn pieces with hygiene seals and tags intact can be exchanged within 7 days. For hygiene reasons we cannot accept worn or washed intimate apparel.'],
            ['h' => 'How do I know my size?', 'p' => 'See our size guide for measurements and fit notes, or send your measurements over WhatsApp and our fitting team will recommend the right size.'],
            ['h' => 'Do you supply churches and worship teams?', 'p' => 'Yes - see our Church & Worship Equipment category for keyboards, guitars, drums, microphones, speakers, mixers and church accessories. We also handle complete sanctuary sound setups and can quote for your church directly.'],
            ['h' => 'Do you supply studios and sound engineers?', 'p' => 'Yes - microphones, interfaces, monitors, mixers, headphones and everything for the studio. Tell us your room and budget and we will put a package together.'],
            ['h' => 'Where are you located?', 'p' => 'Our locations in Tarkwa: Market Circle, and the Tarkwa main station opposite Ben Betty / Hisense. Visit to see, try and hear anything before you buy - or order online for nationwide delivery.'],
            ['h' => 'How do I contact you?', 'p' => 'Call 027 543 9830, WhatsApp 054 171 7773, or use the contact form on this site. Our team replies within two hours during business hours, seven days a week.'],
        ],
    ],
    'contact' => [
        'title'  => 'Contact Us',
        'eyebrow' => 'Tarkwa Showrooms',
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
        'eyebrow' => 'Sound. Style. Quality.',
        'blocks' => [
            ['h' => 'Who we are', 'p' => 'Zion Groups of Companies is a Ghanaian retail house built on two pillars: sensual high-fashion lingerie and world-class musical instruments, professional audio and church worship equipment. One standard of quality, authenticity and service runs through everything we sell.'],
            ['h' => 'Our vision', 'p' => 'To be Ghana’s most trusted destination for intimate apparel and sound equipment - where a first-time shopper, a performing artiste and a church all feel equally at home and equally confident buying from us.'],
            ['h' => 'Our mission', 'p' => 'To provide high-quality, genuine products at fair cedis prices, backed by expert advice, honest service and reliable nationwide delivery - so every customer leaves with the right product, not just a product.'],
            ['h' => 'What we offer', 'p' => 'Four categories, one store: Lingerie (bras, panties, sets, nightwear, bodysuits, shapewear), Music & Musical Instruments (keyboards, guitars, drums, wind and traditional instruments), Professional Audio & Sound (microphones, speakers, mixers, amplifiers, studio equipment), and Church & Worship Equipment (worship instruments, sanctuary audio and accessories).'],
            ['h' => 'Our commitment', 'p' => 'Every instrument is sourced through authorised channels and carries genuine manufacturer warranty. Every intimate order ships in discreet, unmarked packaging. Every customer gets responsive support - reply within two hours during business hours, seven days a week.'],
            ['h' => 'Who we serve', 'p' => 'Individuals and couples shopping for lingerie, artistes and bands, studio owners and sound engineers, worship teams and churches, schools and event companies - anyone who cares about quality and wants a supplier they can trust long-term.'],
            ['h' => 'Why choose Zion', 'p' => 'Genuine, warranty-backed products. Physical locations in Tarkwa you can walk into. Expert team for fittings, demos and system design. Fair local pricing in Ghana cedis. Nationwide delivery with order tracking. And a track record of customers who come back - and recommend us to their friends.'],
        ],
    ],
    'our-story' => [
        'title'  => 'Our Story',
        'eyebrow' => 'From Tarkwa, Ghana',
        'blocks' => [
            ['h' => 'From Tarkwa to nationwide', 'p' => 'What began in Tarkwa as a passion for good sound and beautiful things grew into a house that treats lingerie and instruments with the same reverence: cut, tone, feel and the confidence they give the person holding them. Today we serve customers across Ghana from our locations at Market Circle and the Tarkwa main station.'],
            ['h' => 'Authorized, always', 'p' => 'We work directly with manufacturers and authorised distributors so that every instrument carries a genuine warranty and every intimate carries a promise of privacy.'],
        ],
    ],
    'privacy-policy' => [
        'title'  => 'Privacy Policy',
        'eyebrow' => 'Last Updated 01 October 2026',
        'blocks' => [
            ['h' => 'What we collect', 'p' => 'Your name, phone number, email address, delivery addresses and order history - only what we need to sell, deliver and support your purchase. When you contact us we keep your messages so we can assist you properly.'],
            ['h' => 'How we use your information', 'p' => 'To process and deliver your orders, send order and delivery updates, handle returns and warranty claims, and improve our service. We do not sell your personal data to anyone, ever.'],
            ['h' => 'Payment data', 'p' => 'We never store your MoMo PIN or card security code. Mobile-money and card transactions are settled over encrypted channels with our payment partners.'],
            ['h' => 'Sharing', 'p' => 'We share your delivery details with couriers strictly to complete your order, and never for marketing. Parcel contents are never disclosed to couriers.'],
            ['h' => 'Security', 'p' => 'Access to customer data is limited to team members who need it to do their jobs. We use secure connections across the site and review our practices regularly.'],
            ['h' => 'Cookies', 'p' => 'The site uses cookies to keep you signed in, remember your cart and understand how the shop is used. You can disable cookies in your browser, though some features may stop working.'],
            ['h' => 'Your rights', 'p' => 'You may request a copy of the data we hold about you, ask us to correct anything inaccurate, or ask us to delete your account. Email concierge@ziongroups.com.gh or use the contact form and we will act on it promptly.'],
            ['h' => 'Contact us', 'p' => 'Questions about this policy? Reach us on 027 543 9830, WhatsApp 054 171 7773, or through the contact page. We answer within two hours during business hours.'],
        ],
    ],
    'terms' => [
        'title'  => 'Terms of Service',
        'eyebrow' => 'Last Updated 01 October 2026',
        'blocks' => [
            ['h' => '1. Orders', 'p' => 'An order is accepted once payment is confirmed or, for cash on delivery, once the dispatch confirmation is issued. We may decline or cancel an order if an item is out of stock or a pricing error is discovered, with a full refund for anything already paid.'],
            ['h' => '2. Pricing', 'p' => 'All prices are quoted in Ghana cedis (GH₵) and include applicable VAT unless stated otherwise. Prices may change without notice; the price at checkout is the price you pay.'],
            ['h' => '3. Payment', 'p' => 'We accept MTN Mobile Money, Telecel Cash, AT Money, Visa / Mastercard, bank transfer and cash on delivery in selected areas. Orders are dispatched after payment is received or a COD order is confirmed.'],
            ['h' => '4. Delivery', 'p' => 'Delivery timelines and any applicable charges are communicated before you confirm your order. Risk in the goods passes to you on delivery. See the shipping & delivery page for full details.'],
            ['h' => '5. Returns & exchanges', 'p' => 'Unworn, unopened items in original packaging may be returned within 7 days of delivery. Lingerie and intimate apparel must be unworn with hygiene seals and tags intact. Faulty or incorrectly supplied items are replaced or refunded in full.'],
            ['h' => '6. Warranty', 'p' => 'Musical instruments and electronic equipment carry the regional manufacturer warranty against defects under normal use. Warranty does not cover misuse, unauthorised modification or consumable parts. Keep your invoice - it is your proof of purchase.'],
            ['h' => '7. Product information', 'p' => 'We describe and photograph every product as accurately as possible. Colours can vary slightly by screen. Dimensional and specification data is provided by manufacturers and may be updated without notice.'],
            ['h' => '8. Privacy', 'p' => 'Your personal data is handled as described in our privacy policy, which forms part of these terms.'],
            ['h' => '9. Governing law', 'p' => 'These terms are governed by the laws of Ghana. Any dispute is subject to the jurisdiction of the courts of Ghana.'],
            ['h' => '10. Contact', 'p' => 'Questions about these terms? Call 027 543 9830, WhatsApp 054 171 7773, or use the contact page. Zion Groups of Companies, Tarkwa, Ghana.'],
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
              ['2', 'Tarkwa locations'],
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
        <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-md">Every message lands with a real person in Tarkwa - not a bot.</p>

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
