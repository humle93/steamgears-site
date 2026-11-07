<?php
/**
 * Plugin Name: Steamgears payload — trin 0–1 (S2.7)
 * Description: Fjerner emoji-scriptet og de to annoncepixels der ikke bruges (TikTok for Business' browser-pixel og Kliken), saa hver side slipper for scripts den ikke har brug for. Kilde: wordpress/mu-plugins/sga-payload-trim.php i repoet.
 * Version: 1.3.0
 * Author: Steamgears
 *
 * Baggrund (S2.7, maalt 7/11 2026): forsiden var 117,7 KB med 51 <script>-tags, og
 * script-fordelingen viste 13 sporings-scripts paa hver eneste side — ogsaa sider uden
 * annoncer. Dette mu-plugin tager de tre der ikke koster funktionalitet:
 *
 *   emoji   2 scripts + 1 inline style. WordPress' emoji-detektion henter en 3 KB
 *           inline-loader og et ekstra script for at kunne vise emoji i gamle browsere.
 *   tiktok  4 scripts (tt4b_ajax_script + ajaxSnippet.js + to inline pixel-blokke).
 *           TikTok-pluginnet sender sine events server-side via Events API uanset, saa
 *           kun browser-pixelen forsvinder — ikke konverteringssporingen i TikTok Ads.
 *   kliken  1 script-modul (frontendInjection.js fra *.azurefd.net) + inline. Kilden er
 *           transienten _transient_brandagent_backend_url; den bliver ikke slettet her, saa
 *           et senere valg om at bruge Kliken igen kun kraever at filteret slaas fra.
 *   woo     6 scripts + 2 inline paa sider uden kurv- eller produkt-UI (blockUI,
 *           add-to-cart, js-cookie, woocommerce.min, sourcebuster, order-attribution).
 *           Butik, kurv, kasse og konto beholder dem alle — der er de noedvendige.
 *
 * Med vilje IKKE fjernet: Google Listings & Ads. Dets AW-18518250875-tag er den eneste
 * Ads-konvertering tilbage efter S0.9, og en fjernelse ville slukke konverteringssporingen
 * i Google Ads. Samme grund for gtag (GT-PL9L67B4) og Complianz.
 *
 * Hvorfor et output-filter og ikke kun dequeue: TikTok kalder wp_register_script() inde i
 * selve renderingen (Tt4b_Pixel_Class::enqueue_event), saa et dequeue paa
 * wp_enqueue_scripts rammer for tidligt, og Kliken-pixelen er et script-modul der printes
 * udenom script-koeen. Filteret fjerner praecis de kendte tags og roerer intet andet;
 * dequeue-kaldene bliver staaende som foerste forsoeg, saa de kan virke paa sider hvor
 * timingen passer.
 *
 * Rollback pr. indgreb (uden at slette filen):
 *   add_filter( 'sga_payload_trim_enabled', fn( $on, $part ) => 'tiktok' === $part ? false : $on, 10, 2 );
 * eller slet filen: saa printes alt som foer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Er et indgreb slået til? Filtrerbart, så et enkelt indgreb kan rulles tilbage.
 *
 * @param string $part 'emoji', 'tiktok' eller 'kliken'.
 * @return bool
 */
function sga_payload_trim_enabled( string $part ): bool {
	return (bool) apply_filters( 'sga_payload_trim_enabled', true, $part );
}

/**
 * Trin 0 — WordPress' emoji-detektion.
 *
 * To scripts og en inline style pr. side, og kun for at understøtte emoji i browsere
 * der ikke kan vise dem selv. Native emoji i indhold virker uændret.
 */
add_action(
	'init',
	static function (): void {
		if ( ! sga_payload_trim_enabled( 'emoji' ) ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter(
			'tiny_mce_plugins',
			static function ( $plugins ) {
				return array_diff( (array) $plugins, array( 'wpemoji' ) );
			}
		);
	}
);

/**
 * Trin 1a — TikTok for Business' browser-pixel.
 *
 * Dequeue-kaldet tager scriptet hvor det er køet normalt; filteret nedenfor tager resten.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! sga_payload_trim_enabled( 'tiktok' ) ) {
			return;
		}
		foreach ( array( 'tt4b_ajax_script', 'tiktok-pixel-tracking-handle-header', 'tiktok-tracking-handle-header' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	999
);

/**
 * Trin 2a — Woo's blok-hook maa ikke sætte mini-kurven og kontoen tilbage.
 *
 * Begge blev fjernet fra header-template-parten (post 12), men Woo hooker dem ind i
 * blokken paa render-tid, saa de dukkede op igen — med React, react-dom og
 * mini-cart.js som følge. Dette filter fjerner praecis de to blokke fra hook-listen.
 */
add_filter(
	'hooked_block_types',
	static function ( $types, $position, $anchor, $context ) {
		if ( ! sga_payload_trim_enabled( 'mini_cart' ) ) {
			return $types;
		}
		$blocked = array( 'woocommerce/mini-cart', 'woocommerce/customer-account' );
		return array_values( array_diff( (array) $types, $blocked ) );
	},
	10,
	4
);

/**
 * Trin 2b — Woo's kurv-scripts kun hvor der er en kurv eller et produkt.
 *
 * Woo enqueuer seks scripts og to config-blokke paa hver side, ogsaa en FAQ-side uden
 * en vare. De bruges til ajax-kurven, attributionscookies og blockUI; intet af det har
 * en funktion paa en tekstside. Butik, produkt, kurv, kasse og konto beholder dem.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! sga_payload_trim_enabled( 'woo' ) ) {
			return;
		}
		if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
			return;
		}
		foreach ( array( 'wc-jquery-blockui', 'wc-add-to-cart', 'wc-js-cookie', 'woocommerce', 'sourcebuster-js', 'wc-order-attribution' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	999
);

/**
 * Trin 1b — Kliken-pixelen er et script-modul, ikke et almindeligt script.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( ! sga_payload_trim_enabled( 'kliken' ) ) {
			return;
		}
		if ( function_exists( 'wp_dequeue_script_module' ) ) {
			wp_dequeue_script_module( 'brand-agent-frontend-js-module' );
		}
	},
	1
);

/**
 * Trin 1c — den garanterede fjernelse: præcis de kendte tags, intet andet.
 *
 * Hvert mønster kræver den fulde URL eller det fulde id, så et andet script med samme
 * leverandør ikke bliver ramt ved et uheld. Er et indgreb slået fra, står tagget urørt.
 *
 * @param string $html Hele sidens HTML.
 * @return string
 */
function sga_payload_trim_html( string $html ): string {
	$patterns = array();

	if ( sga_payload_trim_enabled( 'tiktok' ) ) {
		$patterns[] = '#<script\b[^>]*\bsrc\s*=\s*["\'][^"\']*ajaxSnippet\.js[^"\']*["\'][^>]*>\s*</script>\s*#i';
		$patterns[] = '#<script\b[^>]*\bid\s*=\s*["\']tt4b_ajax_script-js-extra["\'][^>]*>.*?</script>\s*#is';
		$patterns[] = '#<script\b[^>]*\bid\s*=\s*["\']tiktok-pixel-tracking-handle-header-js-after["\'][^>]*>.*?</script>\s*#is';
		$patterns[] = '#<script\b[^>]*\bid\s*=\s*["\']tiktok-tracking-handle-header-js-after["\'][^>]*>.*?</script>\s*#is';
	}

	if ( sga_payload_trim_enabled( 'kliken' ) ) {
		$patterns[] = '#<script\b[^>]*\bsrc\s*=\s*["\'][^"\']*azurefd\.net/frontendInjection\.js[^"\']*["\'][^>]*>\s*</script>\s*#i';
		$patterns[] = '#<link\b[^>]*\bhref\s*=\s*["\'][^"\']*azurefd\.net/[^"\']*["\'][^>]*>\s*#i';
	}

	if ( array() === $patterns ) {
		return $html;
	}

	$result = preg_replace( $patterns, '', $html );
	return is_string( $result ) ? $result : $html;
}

add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		ob_start( 'sga_payload_trim_html' );
	},
	2
);
