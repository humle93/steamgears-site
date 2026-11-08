<?php
/**
 * Plugin Name: Steamgears hardening (S5)
 * Description: Lukker S5.3–S5.6 fra køreplanen: versions- og brugernavnesignaler, sikkerhedsheaders, XML-RPC og REST-indeksets cache. Lægges i wp-content/mu-plugins/.
 * Version: 1.0.0
 * Author: Steamgears
 *
 * Baggrunden er målt 8/11 2026 (docs/library/site-mod-koereplan-2026-11-08.md):
 * `/wp-json/wp/v2/users` svarede 200 med to brugernavne, `?author=1` pegede på en
 * indekseret forfatterside, `generator` meldte WordPress-versionen, XML-RPC svarede
 * med `system.multicall`, klik-beskyttelsen fandtes kun på /my-account/, og
 * `/wp-json/` blev sendt med `max-age=31536000`.
 *
 * Pluginnet er et mu-plugin, fordi det skal virke uden aktivering og overleve et
 * temaskift. Det rører ingen indstillinger og skriver intet til databasen: hver
 * egenskab er en hook, der kan slås fra med en konstant eller et filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SGAP_HARDENING_VERSION' ) ) {
	define( 'SGAP_HARDENING_VERSION', '1.0.0' );
}

/**
 * Er en enkelt egenskab slået til?
 *
 * Standarden er til, fordi fraværet af en header er den fejl der ikke giver en
 * fejlmeddelelse. En konstant som SGAP_HARDEN_HEADERS = false slår den fra, og
 * filteret `sgap_hardening_feature` gør det samme pr. kald.
 *
 * @param string $feature identity|headers|xmlrpc|rest
 * @return bool
 */
function sgap_hardening_enabled( string $feature ): bool {
	$enabled = true;
	$const   = 'SGAP_HARDEN_' . strtoupper( $feature );

	if ( defined( $const ) ) {
		$enabled = (bool) constant( $const );
	}

	return (bool) apply_filters( 'sgap_hardening_feature', $enabled, $feature );
}

/* -------------------------------------------------------------------------
 * S5.3 — identitets- og versionslækager
 * ---------------------------------------------------------------------- */

/**
 * Fjern WordPress-versionen fra <head> og fra feeds.
 *
 * `wp_generator` er den ene linje, der gør hele CVE-arbejdet gratis for en
 * angriber. Rank Math kan fjerne den selv, men indstillingen er ikke sat, og et
 * temaskift må ikke kunne bringe den tilbage.
 */
function sgap_hardening_drop_generator(): void {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}

/**
 * Skjul brugerruterne i REST for alle uden `list_users`.
 *
 * `/wp/v2/users` er offentlig som standard og svarede med to brugernavne. Den
 * bruges ikke af butikken eller af forsiden, men af enhver der vil gætte kodeord.
 * Ruterne fjernes helt for anonyme i stedet for at give 401, så svaret ikke
 * bekræfter at endepunktet findes.
 *
 * @param array $endpoints Ruter fra `rest_endpoints`.
 * @param bool  $privileged Om kaldet er fra en bruger der må se listen.
 * @return array
 */
function sgap_hardening_strip_user_routes( array $endpoints, bool $privileged ): array {
	if ( $privileged ) {
		return $endpoints;
	}

	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( (string) $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
}

/**
 * Sæt `noindex` på forfatterarkiver.
 *
 * Arkivet overlever som side, men skal ikke konkurrere med de rigtige sider om
 * de samme søgeord. Funktionen er ren, så begge robot-filtre kan bruge den.
 *
 * @param array $robots Robot-direktiver.
 * @param bool  $is_author Om den aktuelle visning er et forfatterarkiv.
 * @return array
 */
function sgap_hardening_author_robots( array $robots, bool $is_author ): array {
	if ( ! $is_author ) {
		return $robots;
	}

	unset( $robots['index'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
	$robots['noindex'] = 'noindex';
	$robots['follow']  = 'follow';

	return $robots;
}

/**
 * Standarden for `wp_robots`, så det også virker uden Rank Math.
 *
 * @param array $robots
 * @return array
 */
function sgap_hardening_wp_robots( array $robots ): array {
	return sgap_hardening_author_robots( $robots, function_exists( 'is_author' ) && is_author() );
}

/**
 * Rank Maths eget robot-filter, når det er aktivt.
 *
 * @param array $robots
 * @return array
 */
function sgap_hardening_rank_math_robots( $robots ): array {
	$robots = is_array( $robots ) ? $robots : array();

	return sgap_hardening_author_robots( $robots, function_exists( 'is_author' ) && is_author() );
}

/**
 * Luk den numeriske bruger-enumerering: `?author=1` → forsiden.
 *
 * Det er kun query-formen der lukkes. Den rigtige forfatterside bliver liggende,
 * fordi den er internlenket og kan bruges til at vise hvem der skriver.
 */
function sgap_hardening_block_author_scan(): void {
	$has_param = isset( $_GET['author'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! sgap_hardening_should_block_author_scan( $has_param, is_user_logged_in() ) ) {
		return;
	}

	$target = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * Skal anmodningen omdirigeres?
 *
 * Ren funktion, så reglen kan prøves uden at afslutte processen: kun den
 * numeriske query-form rammes, og kun for besøgende der ikke er logget ind.
 *
 * @param bool $has_author_param Om `?author=` står i forespørgslen.
 * @param bool $logged_in       Om brugeren er logget ind.
 * @return bool
 */
function sgap_hardening_should_block_author_scan( bool $has_author_param, bool $logged_in ): bool {
	return $has_author_param && ! $logged_in;
}

/* -------------------------------------------------------------------------
 * S5.4 — sikkerhedsheaders på alle sider
 * ---------------------------------------------------------------------- */

/**
 * De headers der skal stå på hver offentlig side.
 *
 * Konto-siden havde allerede `SAMEORIGIN` og `frame-ancestors 'self'` (sat af
 * WooCommerce), mens forside, butik, kurv og kasse ikke havde nogen af dem.
 * Funktionen er ren og returnerer de linjer der mangler, så den kan prøves uden
 * en webserver — og så en eksisterende CSP ikke bliver overskrevet.
 *
 * @param string[] $existing Header-linjer der allerede er sendt.
 * @return string[]
 */
function sgap_hardening_header_lines( array $existing = array() ): array {
	$have = array();
	foreach ( $existing as $line ) {
		$name = strtolower( trim( strtok( (string) $line, ':' ) ) );
		if ( '' !== $name ) {
			$have[ $name ] = true;
		}
	}

	$wanted = array(
		'x-frame-options'      => 'X-Frame-Options: SAMEORIGIN',
		'x-content-type-options' => 'X-Content-Type-Options: nosniff',
		'referrer-policy'      => 'Referrer-Policy: strict-origin-when-cross-origin',
		'permissions-policy'   => 'Permissions-Policy: geolocation=(), microphone=(), camera=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()',
	);

	$lines = array();
	foreach ( $wanted as $name => $line ) {
		if ( ! isset( $have[ $name ] ) ) {
			$lines[] = $line;
		}
	}

	// `frame-ancestors` er den moderne afløser for X-Frame-Options. Har siden
	// allerede en CSP, rører vi den ikke: en halv CSP er værre end ingen.
	if ( ! isset( $have['content-security-policy'] ) ) {
		$lines[] = "Content-Security-Policy: frame-ancestors 'self'";
	}

	return $lines;
}

/**
 * Tynde wrappers om PHP's header-funktioner.
 *
 * De findes, fordi `header()` og `headers_list()` er indbyggede og ikke kan
 * overskrives i en test. Testen definerer sine egne, før pluginnet indlæses.
 */
if ( ! function_exists( 'sgap_hardening_emit_header' ) ) {
	function sgap_hardening_emit_header( string $line ): void {
		header( $line, true );
	}
}

if ( ! function_exists( 'sgap_hardening_current_headers' ) ) {
	function sgap_hardening_current_headers(): array {
		return headers_list();
	}
}

if ( ! function_exists( 'sgap_hardening_headers_sent' ) ) {
	function sgap_hardening_headers_sent(): bool {
		return headers_sent();
	}
}

/**
 * Send headerne på front-end. Admin og REST lades urørt.
 */
function sgap_hardening_send_headers(): void {
	if ( is_admin() || sgap_hardening_headers_sent() ) {
		return;
	}

	foreach ( sgap_hardening_header_lines( sgap_hardening_current_headers() ) as $line ) {
		sgap_hardening_emit_header( $line );
	}
}

/* -------------------------------------------------------------------------
 * S5.5 — XML-RPC
 * ---------------------------------------------------------------------- */

/**
 * Sluk XML-RPC og svar 403 på selve filen.
 *
 * `/wp-login.php` bliver blokeret af WAF'en (455), men `/xmlrpc.php` gjorde ikke:
 * den svarede med `system.multicall`, som er den metode der gør det billigt at
 * afprøve mange kodeord i ét kald. Intet i butikken bruger XML-RPC.
 */
function sgap_hardening_disable_xmlrpc(): void {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );
}

/**
 * Standarden for XML-RPC-kaldet: 403 i stedet for en metodeliste.
 *
 * @param string $script Basenavn fra `$_SERVER['SCRIPT_NAME']`.
 * @return bool
 */
function sgap_hardening_is_xmlrpc_request( string $script ): bool {
	return 'xmlrpc.php' === strtolower( basename( $script ) );
}

/**
 * Afvis XML-RPC-anmodningen, før WordPress begynder at svare på den.
 */
function sgap_hardening_block_xmlrpc_request(): void {
	$script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	if ( ! sgap_hardening_is_xmlrpc_request( $script ) ) {
		return;
	}

	if ( function_exists( 'status_header' ) ) {
		status_header( 403 );
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'XML-RPC er slaaet fra.';
	exit;
}

/* -------------------------------------------------------------------------
 * S5.6 — REST-indeksets cache
 * ---------------------------------------------------------------------- */

/**
 * Hvor længe REST-indekset må caches, i sekunder.
 *
 * Målt 8/11: `cache-control: max-age=31536000` på et indeks over 1 193 ruter.
 * Et år på en rute-fortegnelse betyder at en fjernet eller tilføjet endpoint er
 * usynlig for en klient længe efter opdateringen. Et minut er nok til at tage
 * det værste tryk og kort nok til at en ændring bliver set.
 */
function sgap_hardening_rest_cache_ttl(): int {
	return (int) apply_filters( 'sgap_hardening_rest_cache_ttl', 60 );
}

/**
 * Sæt en kort levetid på REST-indekset — og kun på indekset.
 *
 * @param string $route Den rute der blev kaldt.
 * @return string|null Cache-Control-værdi, eller null hvis ruten ikke er indekset.
 */
function sgap_hardening_rest_cache_header( string $route ): ?string {
	if ( '/' !== $route ) {
		return null;
	}

	$ttl = sgap_hardening_rest_cache_ttl();
	if ( $ttl < 1 ) {
		return 'no-store, max-age=0';
	}

	return 'public, max-age=' . $ttl . ', s-maxage=' . $ttl;
}

/**
 * Sæt headeren på svaret fra REST-serveren.
 *
 * @param mixed            $response
 * @param mixed            $server
 * @param WP_REST_Request  $request
 * @return mixed
 */
function sgap_hardening_rest_post_dispatch( $response, $server, $request ) {
	if ( ! is_object( $response ) || ! method_exists( $response, 'header' ) ) {
		return $response;
	}
	if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
		return $response;
	}

	$cache = sgap_hardening_rest_cache_header( (string) $request->get_route() );
	if ( null === $cache ) {
		return $response;
	}

	$response->header( 'Cache-Control', $cache );
	$response->header( 'Expires', gmdate( 'D, d M Y H:i:s', time() + sgap_hardening_rest_cache_ttl() ) . ' GMT' );

	return $response;
}

/* -------------------------------------------------------------------------
 * Registrering
 * ---------------------------------------------------------------------- */

if ( sgap_hardening_enabled( 'identity' ) ) {
	sgap_hardening_drop_generator();

	add_filter(
		'rest_endpoints',
		static function ( $endpoints ) {
			$privileged = is_user_logged_in() && current_user_can( 'list_users' );
			return is_array( $endpoints ) ? sgap_hardening_strip_user_routes( $endpoints, $privileged ) : $endpoints;
		},
		999
	);

	add_filter( 'wp_robots', 'sgap_hardening_wp_robots', 20 );
	add_filter( 'rank_math/frontend/robots', 'sgap_hardening_rank_math_robots', 20 );
	add_action( 'template_redirect', 'sgap_hardening_block_author_scan', 1 );
}

if ( sgap_hardening_enabled( 'headers' ) ) {
	add_action( 'send_headers', 'sgap_hardening_send_headers', 20 );
}

if ( sgap_hardening_enabled( 'xmlrpc' ) ) {
	sgap_hardening_disable_xmlrpc();
	add_action( 'init', 'sgap_hardening_block_xmlrpc_request', 0 );
}

if ( sgap_hardening_enabled( 'rest' ) ) {
	add_filter( 'rest_post_dispatch', 'sgap_hardening_rest_post_dispatch', 20, 3 );
}
