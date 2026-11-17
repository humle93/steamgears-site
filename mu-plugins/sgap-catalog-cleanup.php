<?php
/**
 * Plugin Name: SGAP Catalog Cleanup
 * Description: Sender tomme produktkategorier til /shop/, så en posters-only-butik ikke viser tomme kategorisider. En forældrekategori med varer i en underkategori er ikke tom. 302, så redirecten forsvinder af sig selv, når kategorien får varer igen.
 * Version: 1.1.0
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

		// En forældrekategori kan have 0 direkte varer og alligevel vise varer,
		// fordi WooCommerce arver produkter fra underkategorierne. Tæl hele
		// undertræet, før kategorien behandles som tom.
		$children = get_term_children( $term->term_id, $term->taxonomy );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child_id ) {
				$child = get_term( $child_id, $term->taxonomy );
				if ( $child instanceof WP_Term && (int) $child->count > 0 ) {
					return;
				}
			}
		}

		wp_safe_redirect( home_url( '/shop/' ), 302 );
		exit;
	},
	5
);
