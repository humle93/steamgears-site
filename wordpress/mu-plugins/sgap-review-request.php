<?php
/**
 * Plugin Name: Steamgears — review request after delivery
 * Description: Asks the customer for a review exactly once, 14 days after the order is completed, with a direct link to each product's review form.
 * Version: 1.0.0
 * Author: Steamgears.eu
 *
 * S4.10. The shop has **zero reviews** and no way to collect them: nothing in the theme or
 * the plugin ever asks. Reviews are the cheapest trust an unknown store can get, and the
 * native WooCommerce reviews are enough — no third-party platform, no account, no script.
 *
 * What this mu-plugin does, and nothing else:
 *
 *  - waits `sgap_review_delay_days` (default **14**) after the order is marked completed,
 *    because a review written the day the parcel arrives is about the parcel, not the item;
 *  - sends **once per order** (`_sgap_review_requested`), never twice, never to a cancelled
 *    or refunded order — the status is read again at send time, not only when scheduled;
 *  - links each purchased product to its own review form (`<permalink>#reviews`), and skips
 *    products where reviews are closed, because asking for a review that cannot be left is
 *    worse than not asking;
 *  - says how verification works: a review written with the email the order was placed with
 *    is marked as a verified purchase by WooCommerce. Nothing is promised beyond that.
 *
 * Two things are deliberately **not** in the mail: an incentive of any kind (a discount, a
 * gift, a refund for a review is unfair commercial practice under the Omnibus rules the
 * roadmap cites), and any request to change or remove a negative review.
 *
 * The copy lives in one place — `sgap_review_sections()` — and is rendered twice, so the
 * HTML and the plain-text mail cannot drift apart. Kill switch:
 * `add_filter('sgap_review_request_enabled', '__return_false')`.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Days between "completed" and the ask. Filterable so a test or the owner can move it. */
function sgap_review_delay_days(): int {
    $days = (int) apply_filters('sgap_review_delay_days', 14);
    return $days > 0 ? $days : 14;
}

/** The hook the scheduled ask fires on. One name, used by scheduling and by the sender. */
function sgap_review_hook(): string {
    return 'sgap_review_send';
}

/** Master switch. Also false when the shop has reviews turned off, because then there is no form. */
function sgap_review_enabled(): bool {
    $enabled = (bool) apply_filters('sgap_review_request_enabled', true);
    if (!$enabled) {
        return false;
    }
    if (function_exists('get_option') && get_option('woocommerce_enable_reviews') !== 'yes') {
        return false;
    }
    return true;
}

/**
 * The products in an order that can actually be reviewed: still existing, reviews open.
 * Returns a list of ['name' => string, 'url' => string], one per product (a product bought
 * twice is asked about once).
 */
function sgap_review_products($order): array {
    $products = [];
    if (!is_object($order) || !method_exists($order, 'get_items')) {
        return $products;
    }
    $seen = [];
    foreach ($order->get_items() as $item) {
        $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
        if ($product_id <= 0 || isset($seen[$product_id])) {
            continue;
        }
        $seen[$product_id] = true;
        $url = function_exists('get_permalink') ? (string) get_permalink($product_id) : '';
        if ($url === '') {
            continue;
        }
        $name = method_exists($item, 'get_name') ? trim(wp_strip_all_tags((string) $item->get_name())) : '';
        if ($name === '') {
            continue;
        }
        if (function_exists('comments_open') && !comments_open($product_id)) {
            continue;
        }
        $products[] = [
            'name' => $name,
            'url' => $url . '#reviews',
        ];
    }
    return $products;
}

/**
 * The copy, as one structure both renderers read. `intro` and `closing` are ordered lists of
 * paragraphs; `ask` is the line that introduces the product list; `products` is filled from
 * the order, never hard-coded.
 */
function sgap_review_sections($order = null): array {
    $number = '';
    if (is_object($order) && method_exists($order, 'get_order_number')) {
        $number = trim((string) $order->get_order_number());
    }
    $sections = [
        'subject' => 'How did your Steamgears order turn out?',
        'intro' => [
            'Your order ' . ($number !== '' ? $number : 'your recent order') . ' was delivered a couple of weeks ago. '
                . 'A short review helps the next person decide, and it is the only feedback we get on the fit.',
        ],
        'ask' => 'You can review each item here:',
        'products' => sgap_review_products($order),
        'closing' => [
            'Reviews written with the email address you ordered with are marked as a verified purchase, so other '
                . 'customers can see that the review comes from a real order.',
            'We do not offer discounts, gifts or refunds in exchange for a review, and an honest negative review stays '
                . 'where it is. Tell us what was wrong with the fit, the print or the delivery, and we will answer it.',
            'If something is wrong with the item rather than the fit, reply to this email and we will sort it out.',
        ],
    ];
    return (array) apply_filters('sgap_review_sections', $sections, $order);
}

/** Plain-text body: the same copy, with each product written out as name + URL. */
function sgap_review_text($order = null): string {
    $sections = sgap_review_sections($order);
    $lines = [];
    foreach ((array) ($sections['intro'] ?? []) as $paragraph) {
        $lines[] = (string) $paragraph;
    }
    $lines[] = (string) ($sections['ask'] ?? '');
    foreach ((array) ($sections['products'] ?? []) as $product) {
        $lines[] = '- ' . (string) $product['name'] . ': ' . (string) $product['url'];
    }
    foreach ((array) ($sections['closing'] ?? []) as $paragraph) {
        $lines[] = (string) $paragraph;
    }
    $text = implode("\n\n", array_values(array_filter($lines, static fn($line) => $line !== '')));
    return (string) apply_filters('sgap_review_email_text', $text, $order);
}

/** HTML body: the same copy, with each product name linked to its review form. */
function sgap_review_html($order = null): string {
    $sections = sgap_review_sections($order);
    $html = '<div class="sgap-review" style="font-family:Georgia,serif;color:#1b1b1b;line-height:1.5">'
        . '<h2 style="font-size:18px;margin:0 0 12px">' . esc_html((string) ($sections['subject'] ?? '')) . '</h2>';
    foreach ((array) ($sections['intro'] ?? []) as $paragraph) {
        $html .= '<p style="margin:0 0 12px">' . esc_html((string) $paragraph) . '</p>';
    }
    $html .= '<p style="margin:0 0 6px">' . esc_html((string) ($sections['ask'] ?? '')) . '</p>';
    foreach ((array) ($sections['products'] ?? []) as $product) {
        $html .= '<p style="margin:0 0 6px"><a href="' . esc_url((string) $product['url']) . '">'
            . esc_html((string) $product['name']) . '</a></p>';
    }
    foreach ((array) ($sections['closing'] ?? []) as $paragraph) {
        $html .= '<p style="margin:0 0 12px">' . esc_html((string) $paragraph) . '</p>';
    }
    $html .= '</div>';
    return (string) apply_filters('sgap_review_email_html', $html, $order);
}

/**
 * WooCommerce: the order became complete. Schedule the ask once. The order meta is the
 * guard, so a second completion event (status flipped back and forth) cannot queue twice.
 */
function sgap_review_schedule($order_id): void {
    if (!sgap_review_enabled()) {
        return;
    }
    $order_id = (int) $order_id;
    if ($order_id <= 0 || !function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!is_object($order) || !method_exists($order, 'get_meta')) {
        return;
    }
    if ($order->get_meta('_sgap_review_requested') || $order->get_meta('_sgap_review_scheduled')) {
        return;
    }
    if (count(sgap_review_products($order)) === 0) {
        return;
    }
    $when = time() + (sgap_review_delay_days() * DAY_IN_SECONDS);
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action($when, sgap_review_hook(), [$order_id], 'steamgears');
    } else {
        wp_schedule_single_event($when, sgap_review_hook(), [$order_id]);
    }
    $order->update_meta_data('_sgap_review_scheduled', $when);
    $order->save();
    do_action('sgap_review_scheduled', $order_id, $when);
}

/** phpmailer_init: attach the plain-text version, so a text-only client is not left blank. */
function sgap_review_alt_body($phpmailer): void {
    $alt = isset($GLOBALS['sgap_review_alt_body']) ? (string) $GLOBALS['sgap_review_alt_body'] : '';
    if ($alt !== '' && is_object($phpmailer)) {
        $phpmailer->AltBody = $alt;
    }
}

/**
 * The scheduled send. Everything is re-read here — status, meta, products — because the
 * customer may have cancelled or returned the order in the two weeks that passed.
 */
function sgap_review_send($order_id): bool {
    $order_id = (int) $order_id;
    if (!sgap_review_enabled() || $order_id <= 0 || !function_exists('wc_get_order')) {
        return false;
    }
    $order = wc_get_order($order_id);
    if (!is_object($order) || !method_exists($order, 'get_meta')) {
        return false;
    }
    if ($order->get_meta('_sgap_review_requested')) {
        return false;
    }
    $status = method_exists($order, 'get_status') ? (string) $order->get_status() : '';
    if ($status !== 'completed') {
        $order->update_meta_data('_sgap_review_skipped', $status !== '' ? $status : 'unknown');
        $order->save();
        do_action('sgap_review_skipped', $order_id, $status);
        return false;
    }
    if (count(sgap_review_products($order)) === 0) {
        return false;
    }
    $to = method_exists($order, 'get_billing_email') ? sanitize_email((string) $order->get_billing_email()) : '';
    if ($to === '') {
        return false;
    }
    $sections = sgap_review_sections($order);
    $subject = (string) apply_filters('sgap_review_email_subject', (string) ($sections['subject'] ?? ''), $order);
    $html = sgap_review_html($order);
    $text = sgap_review_text($order);

    $GLOBALS['sgap_review_alt_body'] = $text;
    add_action('phpmailer_init', 'sgap_review_alt_body');
    $sent = (bool) wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    remove_action('phpmailer_init', 'sgap_review_alt_body');
    $GLOBALS['sgap_review_alt_body'] = '';

    if ($sent) {
        $order->update_meta_data('_sgap_review_requested', time());
        $order->save();
    }
    do_action('sgap_review_sent', $order_id, $sent);
    return $sent;
}

if (function_exists('add_action')) {
    add_action('woocommerce_order_status_completed', 'sgap_review_schedule', 20, 1);
    add_action(sgap_review_hook(), 'sgap_review_send', 10, 1);
}
