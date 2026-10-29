<?php
/**
 * Steamgears WooCommerce & Custom Product Group Schema Hooks
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure 100% English frontend and clean currency handling for Steamgears.eu
add_filter( 'woocommerce_currency', function( $currency ) {
    return 'EUR';
});

// Custom Product Group Schema for Printify variant variants
function steamgears_custom_product_group_schema() {
    if ( is_product() ) {
        global $product;
        // Output microdata / schema hooks for mechanical / steampunk inventory
    }
}
add_action( 'wp_head', 'steamgears_custom_product_group_schema' );
