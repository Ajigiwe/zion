<?php
/**
 * Voucher / promo card on the shopping bag page.
 * Shared by cart.php (first render) and actions.php (AJAX re-render after
 * applying or clearing a code, so the block can be swapped in place).
 */

declare(strict_types=1);

function promo_block_html(?array $promo, float $discount): string
{
    $has = $promo !== null;
    $code = $has ? (string) $promo['code'] : '';
    $label = $has ? ($discount > 0 ? price($discount) : 'applied') : '';
    $csrf = e(csrf_token());

    $body = $has
        ? '<form method="post" action="' . e(url('actions.php')) . '" class="flex items-center gap-2" data-promo>'
            . '<input type="hidden" name="csrf" value="' . $csrf . '"/>'
            . '<input type="hidden" name="action" value="clear_promo"/>'
            . '<span class="px-3 py-2 rounded-lg bg-primary-fixed text-primary font-label-nav text-label-nav font-bold uppercase">'
            . e($code) . ' &minus; ' . e($label) . '</span>'
            . '<button class="px-4 py-2 border border-outline-variant rounded-lg font-label-nav text-label-nav uppercase text-on-surface-variant hover:bg-surface-container" type="submit">Remove</button>'
            . '</form>'
        : '<form method="post" action="' . e(url('actions.php')) . '" class="flex items-center gap-2 w-full sm:w-auto" data-promo>'
            . '<input type="hidden" name="csrf" value="' . $csrf . '"/>'
            . '<input type="hidden" name="action" value="apply_promo"/>'
            . '<input class="flex-1 sm:w-48 px-3 py-2 border border-outline-variant rounded-lg font-body-sm text-body-sm uppercase tracking-wider outline-none focus:border-primary"'
            . ' placeholder="Enter code" name="promo" required/>'
            . '<button class="px-5 py-2 bg-primary-container text-on-primary rounded-lg font-label-nav text-label-nav font-bold uppercase tracking-wider hover:bg-primary" type="submit">Apply</button>'
            . '</form>';

    return '<div class="bg-surface-container-lowest rounded-xl shadow-xs p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between" data-promo-block>'
        . '<div>'
        . '<p class="font-label-nav text-label-nav text-on-surface font-bold uppercase tracking-wider">Privilege Voucher / Promo</p>'
        . '<p class="font-body-sm text-body-sm text-on-surface-variant">Apply a VIP code or Zion Club gift certificate</p>'
        . '</div>'
        . $body
        . '</div>';
}
