<?php
/**
 * Plugin Name: Steamgears — OpenGraph-billedstørrelse og deduplikering
 * Description: Sikrer at Rank Math vælger en billedvariant med bredde >= 600 px på stående covers og fjerner Pinterest for WooCommerces dublerede og:image-tag på artikler.
 * Version: 1.0.4
 * Author: Steamgears.eu
 * License: GPL-2.0-or-later
 *
 * S2.5b (7–8/11 2026):
 *  1. Rank Math løber over ['full', 'large', 'medium_large'] og afviser billeder over 2000 px i
 *     højde eller bredde (`has_usable_dimensions`). Et stående cover som 1280 × 2276 px (indlæg 615)
 *     ryger derfor ned på 'large' (max 1024 px høj), hvilket giver 576 × 1024 px på 615 — under
 *     600 px-grænsen for store link-kort på Facebook/LinkedIn. Ved at indsætte '1536x1536' foran
 *     'full' får stående covers 864 × 1536 px og brede covers som 1920 × 1440 px (indlæg 806)
 *     1536 × 1152 px.
 *  2. Pinterest for WooCommerce udskriver sine egne OpenGraph-tags på både 'product' og 'post'.
 *     Rank Math udskriver allerede fuld OpenGraph + Article/Product-JSON-LD, så på indlæg gav
 *     Pinterest-plugin'et et ekstra og:image med en anden størrelse (576 × 1024). 8/11 blev det
 *     målt: `pinterest_for_woocommerce_richpins_args` med `posts.enabled = false` fjernede det
 *     ikke, fordi plugin'et stadig genererer tags når produkter er slået til. Den dokumenterede
 *     opt-out er `pinterest_for_woocommerce_disable_rich_pins`, og den bruges nu — kun på 'post',
 *     så produktsider beholder deres Rich Pins.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SGAP_OG_IMAGES_VERSION')) {
    define('SGAP_OG_IMAGES_VERSION', '1.0.4');
}

/**
 * Sætter '1536x1536' først i Rank Maths liste af billedstørrelser til OpenGraph.
 *
 * @param array<int, string> $sizes
 * @return array<int, string>
 */
function sgap_og_image_sizes(array $sizes): array
{
    // 'medium_large' er 768 px bred og findes for alle billeder WordPress selv har skaleret.
    // Den vælges før 'large' (576 px bred), som er under 600 px-grænsen for store link-kort,
    // og før 'full', hvis højde kan være over Rank Maths 2000 px-loft på stående covers.
    // Rank Math har selv 'medium_large' i listen, men sidst. Den skal først, fordi 'large'
    // (576 px bred) er under 600 px-grænsen, og 'full' kan være over 2000 px høj.
    $sizes = array_values(array_diff($sizes, array('medium_large')));
    array_unshift($sizes, 'medium_large');
    return $sizes;
}

/**
 * Slår Pinterest for WooCommerces Rich Pins helt fra på almindelige indlæg ('post'), så Rank
 * Math alene styrer og:image dér. Produktsider røres ikke, hvor Rich Pins har en funktion.
 *
 * @param bool $disabled
 * @return bool
 */
function sgap_og_disable_pinterest_rich_pins(bool $disabled): bool
{
    return is_singular('post') ? true : $disabled;
}

if (function_exists('add_filter')) {
    add_filter('rank_math/opengraph/image_sizes', 'sgap_og_image_sizes', 10, 1);
    add_filter('pinterest_for_woocommerce_disable_rich_pins', 'sgap_og_disable_pinterest_rich_pins', 20, 1);
}
