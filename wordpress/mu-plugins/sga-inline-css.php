<?php
/**
 * Plugin Name: Steamgears payload — inline CSS til fil
 * Description: Flytter store inline <style>-blokke (≥ 4 KB) ud i cachede filer under uploads, saa HTML-budgettet falder uden at CSS-indholdet aendres. Kilde: wordpress/mu-plugins/sga-inline-css.php i repoet.
 * Version: 1.0.0
 * Author: Steamgears
 *
 * Baggrund (S2.7, maalt 7/11 2026): hver skabelon baerer 148-165 KB inline CSS, hvoraf
 * 37 KB er temaets eget CSS i header-template-parten, 11 KB er "Clockwise"-snippet'en og
 * 45 KB er WordPress' global styles. Blokkene er de samme paa hver side, saa de bliver
 * hentet forfra hver gang. Filene her navngives efter et sha1 af indholdet, saa en aendring
 * i CSS'en giver en ny fil og en ny URL — ingen manuel cache-rydning.
 *
 * Rollback: slet denne fil. Saa printes CSS'en inline igen som foer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGA_INLINE_CSS_MIN_BYTES = 4096;

/**
 * Mappe og URL til de cachede CSS-filer.
 *
 * @return array{dir:string,url:string}
 */
function sga_inline_css_paths(): array {
	static $paths = null;
	if ( null !== $paths ) {
		return $paths;
	}
	$uploads = wp_upload_dir();
	$paths   = array(
		'dir' => trailingslashit( $uploads['basedir'] ) . 'sga-css',
		'url' => trailingslashit( $uploads['baseurl'] ) . 'sga-css',
	);
	return $paths;
}

/**
 * Skriver CSS-filen hvis den ikke findes. Returnerer URL eller '' ved fejl.
 *
 * @param string $css CSS-indhold.
 * @return string
 */
function sga_inline_css_write( string $css ): string {
	$paths = sga_inline_css_paths();
	$name  = 'inline-' . substr( sha1( $css ), 0, 12 ) . '.css';
	$file  = $paths['dir'] . '/' . $name;

	if ( ! file_exists( $file ) ) {
		if ( ! is_dir( $paths['dir'] ) && ! wp_mkdir_p( $paths['dir'] ) ) {
			return '';
		}
		if ( false === @file_put_contents( $file, $css, LOCK_EX ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return '';
		}
	}

	return file_exists( $file ) ? $paths['url'] . '/' . $name : '';
}

/**
 * Bytter store inline <style>-blokke ud med et <link> til den cachede fil.
 *
 * Blokke under graensen og blokke der ikke kan skrives, bliver staaende urørte, saa
 * et fejlende filsystem aldrig kan goere en side ustylet.
 *
 * @param string $html Hele sidens HTML.
 * @return string
 */
function sga_inline_css_externalize( string $html ): string {
	if ( false === stripos( $html, '<style' ) ) {
		return $html;
	}

	$result = preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		static function ( array $m ): string {
			$css = $m[2];
			if ( strlen( $css ) < SGA_INLINE_CSS_MIN_BYTES ) {
				return $m[0];
			}
			// En blok der selv indeholder en style-lukning er ikke sikker at flytte.
			if ( false !== stripos( $css, '</style' ) || false !== strpos( $css, '<?' ) ) {
				return $m[0];
			}
			$url = sga_inline_css_write( $css );
			if ( '' === $url ) {
				return $m[0];
			}

			$id = '';
			if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $m[1], $im ) ) {
				$id = ' id="' . esc_attr( $im[1] ) . '"';
			}
			$media = 'all';
			if ( preg_match( '/\bmedia\s*=\s*["\']([^"\']+)["\']/i', $m[1], $mm ) ) {
				$media = esc_attr( $mm[1] );
			}

			return '<link rel="stylesheet"' . $id . ' href="' . esc_url( $url ) . '" media="' . $media . '" />';
		},
		$html
	);

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
		ob_start( 'sga_inline_css_externalize' );
	},
	1
);
