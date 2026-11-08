<?php
/**
 * Plugin Name: Steamgears · Season & Mobile Layer
 * Description: Sætter årstidens farver på <html> ud fra måneden og lægger css/sg-season.css efter temaets CSS. Retter mobilheader, menuen og de tre rystekilder målt 8/11 2026.
 * Version: 1.0.0
 * Author: Steamgears
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Årstid ud fra måneden, i dansk tid.
 *
 * dec–feb = winter, mar–maj = spring, jun–aug = summer, sep–nov = autumn.
 * Kan overstyres med filteret `sga_season`, fx til en kampagne.
 */
function sga_season_current() {
	$month = (int) current_time( 'n' );

	if ( in_array( $month, array( 12, 1, 2 ), true ) ) {
		$season = 'winter';
	} elseif ( in_array( $month, array( 3, 4, 5 ), true ) ) {
		$season = 'spring';
	} elseif ( in_array( $month, array( 6, 7, 8 ), true ) ) {
		$season = 'summer';
	} else {
		$season = 'autumn';
	}

	/** @param string $season Et af winter, spring, summer, autumn. */
	return (string) apply_filters( 'sga_season', $season );
}

/**
 * `data-season` på <html>, så CSS'en kan vælge palette uden JavaScript.
 */
function sga_season_language_attributes( $output ) {
	$season = sga_season_current();
	if ( ! in_array( $season, array( 'winter', 'spring', 'summer', 'autumn' ), true ) ) {
		$season = 'autumn';
	}

	return trim( $output ) . ' data-season="' . esc_attr( $season ) . '"';
}
add_filter( 'language_attributes', 'sga_season_language_attributes' );

/**
 * CSS'en lægges sidst i footeren, efter temaets, plugin'ernes og den gamle
 * header-CSS — og uden om W3TC's minify, så den ikke bliver blandet sammen
 * med de filer den skal rette.
 */
function sga_season_print_style() {
	$file = __DIR__ . '/sga-season.css';

	if ( ! is_readable( $file ) ) {
		return;
	}

	$css = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokal fil, ikke fjernkald.
	if ( false === $css || '' === trim( $css ) ) {
		return;
	}

	// data-no-minify: W3TC må ikke trække laget ind i en samlet fil, hvor det
	// ender før den CSS det skal rette. attribution="false" holder w3tc ude.
	printf(
		"<style id=\"sga-season\" data-no-minify=\"1\" data-cfasync=\"false\">\n%s\n</style>\n",
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS skal stå rå.
	);
}
add_action( 'wp_footer', 'sga_season_print_style', 999 );

/**
 * Cache-nøglen skal følge filen, ellers kan en side ligge med den gamle CSS.
 * W3TC og WP Rocket tømmes manuelt efter upload; her markeres blot en version.
 */
function sga_season_asset_version() {
	$file = __DIR__ . '/sga-season.css';

	return is_readable( $file ) ? (string) filemtime( $file ) : '1';
}

/** Kort status til fejlsøgning: /wp-json/sga/v1/season */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'sga/v1',
			'/season',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return array(
						'season'  => sga_season_current(),
						'css'     => is_readable( __DIR__ . '/sga-season.css' ),
						'bytes'   => is_readable( __DIR__ . '/sga-season.css' ) ? filesize( __DIR__ . '/sga-season.css' ) : 0,
						'version' => sga_season_asset_version(),
					);
				},
			)
		);
	}
);
