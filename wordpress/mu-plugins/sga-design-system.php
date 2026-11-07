<?php
/**
 * Plugin Name: Steamgears — designsystem for hele sitet
 * Description: Læser css/sg-design-system.css og printer den efter template-delene, så ét lag bestemmer typografi, farver, knapper, formularer og butikkens komponenter. Kilde: wordpress/mu-plugins/sga-design-system.php i repoet.
 * Version: 1.0.0
 * Author: Steamgears
 *
 * Baggrund (design.md §11, 7/11 2026): temaet bærer sine regler i to base64-kodede
 * template-parts — header (1.383 linjer, 2 <style>-blokke) og footer — plus ~300 inline
 * regler hist og her. Der findes ingen samlet fil at rette i, så hver ændring hidtil er
 * blevet et nyt !important et nyt sted. Dette mu-plugin vender det om: én fil er kilden
 * til hvordan sitet ser ud, og den printes sidst på siden, så den vinder over temaet uden
 * at skulle slås med det.
 *
 * Rækkefølgen er hele pointen. Header-template-parten printer temaets <style> i <head>, og
 * blok-temaet printer sine globale styles efter den. Alt der står i footeren kommer efter
 * begge, så en regel her slår igennem uden !important — og undtagelserne i filen er kun de
 * steder hvor tredjepart (Woo, browseren) har samme specificitet.
 *
 * Filen findes i denne rækkefølge:
 *   1. konstanten SGA_DESIGN_CSS (absolut sti, fx sat i wp-config.php)
 *   2. uploads/sga-design/sg-design-system.css (så filen kan rettes uden at røre repoet)
 *   3. samme mappe som dette mu-plugin
 *   4. repoets css/-mappe to niveauer op (kun når mu-plugin'et køres fra en klon)
 *
 * Fejler alle fire, printes en HTML-kommentar i stedet for CSS — siden er så uændret,
 * ikke ustylet. Det er med vilje: et manglende filsystem må ikke koste forsiden.
 *
 * Rollback: slet denne fil, eller slå den fra uden at røre filen:
 *   add_filter( 'sga_design_enabled', '__return_false' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Er designsystemet slået til?
 *
 * @return bool
 */
function sga_design_enabled(): bool {
	return (bool) apply_filters( 'sga_design_enabled', true );
}

/**
 * De stier der ledes i, i prioriteret rækkefølge.
 *
 * @return string[]
 */
function sga_design_candidates(): array {
	$paths = array();

	if ( defined( 'SGA_DESIGN_CSS' ) && is_string( SGA_DESIGN_CSS ) && '' !== SGA_DESIGN_CSS ) {
		$paths[] = SGA_DESIGN_CSS;
	}

	if ( function_exists( 'wp_upload_dir' ) ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) ) {
			$paths[] = trailingslashit( $uploads['basedir'] ) . 'sga-design/sg-design-system.css';
		}
	}

	$paths[] = __DIR__ . '/sg-design-system.css';
	$paths[] = dirname( __DIR__, 2 ) . '/css/sg-design-system.css';

	return array_values( array_unique( $paths ) );
}

/**
 * CSS'indholdet, eller '' hvis filen ikke kan læses.
 *
 * @return string
 */
function sga_design_css(): string {
	static $css = null;
	if ( null !== $css ) {
		return $css;
	}

	$css = '';
	foreach ( sga_design_candidates() as $path ) {
		if ( ! is_readable( $path ) ) {
			continue;
		}
		$raw = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			continue;
		}
		// En blok der selv lukker <style> kan ikke printes inline uden at bryde siden.
		if ( false !== stripos( $raw, '</style' ) || false !== strpos( $raw, '<?' ) ) {
			continue;
		}
		$css = $raw;
		break;
	}

	return $css;
}

/**
 * Tilføjer body-klassen der afgrænser laget, så reglerne kan slås fra pr. skabelon.
 *
 * @param string[] $classes Eksisterende klasser.
 * @return string[]
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( sga_design_enabled() && '' !== sga_design_css() ) {
			$classes[] = 'sg-ui';
		}
		return $classes;
	}
);

/**
 * Printer CSS'en sidst i footeren — efter temaets <head>-styles og blokkens globale styles.
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( ! sga_design_enabled() ) {
			return;
		}
		if ( is_admin() || is_feed() ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$css = sga_design_css();
		if ( '' === $css ) {
			echo "\n<!-- sga-design-system: ingen CSS-fil fundet; temaets egne styles gaelder -->\n";
			return;
		}

		// Filen er vores egen, men en tidlig </style> er allerede frasorteret ovenfor.
		echo "\n<style id=\"sga-design-system\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	999
);
