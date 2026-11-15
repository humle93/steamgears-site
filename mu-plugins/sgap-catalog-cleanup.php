<?php
/**
 * Plugin Name: SGAP Catalog Cleanup
 * Description: Sender tomme produktkategorier til /shop/, så en posters-only-butik ikke viser tomme kategorisider. 302, så redirecten forsvinder af sig selv, når kategorien får varer igen.
 * Version: 1.0.0
 * Author: Steamgears
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'template_redirect',
	function () {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		if ( (int) $term->count > 0 ) {
			return;
		}

		wp_safe_redirect( home_url( '/shop/' ), 302 );
		exit;
	},
	5
);
