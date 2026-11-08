<?php
/**
 * Plugin Name: Steamgears — withdrawal information in the order confirmation
 * Description: Puts the 14-day right of withdrawal, the way to use it and the refund terms into the customer's own order email, so the confirmation is a durable medium and not a link to one.
 * Version: 1.0.0
 * Author: Steamgears.eu
 *
 * S4.12. An order confirmation that only links to a page does not carry the withdrawal
 * information itself, and the confirmation is the document the customer keeps. Everything
 * below is quoted from the site's own pages — /right-of-withdrawal/ and /refund_returns/,
 * text captured in docs/library/trust-sider-2026-11-06.md — so the email cannot promise
 * something the shop does not do:
 *
 *  - 14 days from **receiving the goods**, no reason needed;
 *  - tell us first (order number + a clear statement), because items ship from different
 *    warehouses and a parcel sent to a guessed address can take weeks to come back;
 *  - the customer pays return shipping unless the item is faulty or wrong;
 *  - refund within 14 days of the withdrawal notice, same payment method, including the
 *    standard shipping of the original order;
 *  - the hygiene exception for garments worn against the skin once the seal is removed.
 *
 * Deliberately absent: a return address (the shop sends the right one per warehouse), a
 * shipping rate, and any promise about how fast the money arrives beyond the 14 days the
 * site already states. Copy is written once and rendered twice, so the HTML and the
 * plain-text mail cannot drift apart.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** The address the site tells customers to write to. One constant, so a change is one edit. */
if (!defined('SGAP_WITHDRAWAL_EMAIL')) {
    define('SGAP_WITHDRAWAL_EMAIL', 'cpaleadsis@gmail.com');
}

/** The page that carries the step-by-step version and the standard EU form. */
if (!defined('SGAP_WITHDRAWAL_PAGE')) {
    define('SGAP_WITHDRAWAL_PAGE', 'https://steamgears.eu/right-of-withdrawal/');
}

/**
 * Which customer emails carry the block. The confirmation is "processing"; a shop that
 * marks an order complete or on hold must not lose the information on the way, and the
 * admin's own new-order mail does not need it.
 */
function sgap_withdrawal_email_ids(): array {
    return (array) apply_filters('sgap_withdrawal_email_ids', [
        'customer_processing_order',
        'customer_completed_order',
        'customer_on_hold_order',
    ]);
}

/** The paragraphs, in order. Returned as a list so both renderers share the source. */
function sgap_withdrawal_paragraphs($order = null): array {
    $number = '';
    if (is_object($order) && method_exists($order, 'get_order_number')) {
        $number = trim((string) $order->get_order_number());
    }
    $paragraphs = [
        'Right of withdrawal',
        'If you order as a consumer in the EU, you can cancel this order within 14 days of receiving the goods — no reason needed.'
            . ($number !== '' ? ' Your order number is ' . $number . '.' : ''),
        'Tell us before you send anything: write to ' . SGAP_WITHDRAWAL_EMAIL
            . ' with your order number and a clear statement that you want to cancel or return. We reply with the return instructions and the correct return address, because items ship from different warehouses. Please do not send an item back before you have heard from us.',
        'The step-by-step version, including the standard EU withdrawal form, is on ' . SGAP_WITHDRAWAL_PAGE . '.',
        'You pay the return shipping unless the item is faulty or we sent the wrong thing — in that case we cover it.',
        'We refund within 14 days of receiving your withdrawal notice, using the same payment method you paid with. The refund covers the purchase price and the standard shipping cost of the original order. We may hold the refund until the goods are back with us, or until you have sent proof that you returned them.',
        'Items must be unused and in their original packaging. Clothing worn in direct contact with the skin, including corsets with intimate contact areas, cannot be returned once the hygiene seal is removed, unless the item is faulty.',
    ];
    return (array) apply_filters('sgap_withdrawal_paragraphs', $paragraphs, $order);
}

/** HTML version: one heading, then paragraphs. Escaped here, so callers never have to. */
function sgap_withdrawal_html($order = null): string {
    $paragraphs = sgap_withdrawal_paragraphs($order);
    $title = array_shift($paragraphs);
    $html = '<div class="sgap-withdrawal" style="margin-top:24px;padding-top:16px;border-top:1px solid #dcdcde">'
        . '<h2 style="font-size:16px;margin:0 0 8px">' . esc_html((string) $title) . '</h2>';
    foreach ($paragraphs as $paragraph) {
        $html .= '<p style="margin:0 0 10px">' . esc_html((string) $paragraph) . '</p>';
    }
    $html .= '</div>';
    return (string) apply_filters('sgap_withdrawal_email_html', $html, $order);
}

/** Plain-text version of the same paragraphs, so a text-only mail is not a bare link either. */
function sgap_withdrawal_text($order = null): string {
    $lines = [];
    foreach (sgap_withdrawal_paragraphs($order) as $paragraph) {
        $lines[] = (string) $paragraph;
    }
    $text = implode("\n\n", $lines);
    return (string) apply_filters('sgap_withdrawal_email_text', $text, $order);
}

/**
 * WooCommerce hook: runs after the order table in the customer's mail. `$email` is the
 * WC_Email object, so the block is decided by the email's id, not by guessing the context.
 */
function sgap_withdrawal_email_block($order, $sent_to_admin = false, $plain_text = false, $email = null) {
    if ($sent_to_admin) {
        return;
    }
    $id = is_object($email) && method_exists($email, 'get_id') ? (string) $email->get_id() : '';
    if ($id === '' || !in_array($id, sgap_withdrawal_email_ids(), true)) {
        return;
    }
    $plain = $plain_text;
    if (is_object($email) && method_exists($email, 'get_content_type')) {
        $plain = $plain || $email->get_content_type() === 'text/plain';
    }
    echo $plain ? sgap_withdrawal_text($order) . "\n\n" : sgap_withdrawal_html($order); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builders.
}

add_action('woocommerce_email_after_order_table', 'sgap_withdrawal_email_block', 20, 4);
