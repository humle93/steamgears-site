<?php
/**
 * wordpress/tests/test-hardening.php — prøver S5-hardningen uden WordPress og uden net.
 *
 * `sgap-hardening.php` retter fire ting, der alle er usynlige når de fejler:
 * versionsmetaen, brugernes REST-ruter, forfatterarkivet, headerne, XML-RPC og
 * REST-indeksets cache. En header der ikke bliver sendt, giver ingen fejl — den
 * giver bare en side der kan indrammes. Derfor prøves hver regel for sig.
 *
 *   ./.tools/php wordpress/tests/test-hardening.php
 *   (eller: php wordpress/tests/test-hardening.php)
 *
 * Prøver:
 *   1. `wp_generator` er fjernet fra `wp_head`, og `the_generator` giver tom streng.
 *   2. `/wp/v2/users`-ruterne fjernes for anonyme og bevares for `list_users`.
 *   3. Forfatterarkivet får `noindex` + `follow`, og andre visninger røres ikke.
 *   4. `?author=N` omdirigeres kun for besøgende og kun i query-formen.
 *   5. Header-linjerne dækker klik, referrer, permissions og `nosniff`.
 *   6. En eksisterende CSP og `X-Frame-Options` overskrives ikke.
 *   7. XML-RPC-genkendelsen rammer `xmlrpc.php` og intet andet.
 *   8. `xmlrpc_enabled` er false, og metodelisten er tom.
 *   9. REST-indekset får 60 s, andre ruter røres ikke, og 0 giver `no-store`.
 *  10. Hver egenskab kan slås fra med sit filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['th'] = array(
	'actions'   => array(),
	'filters'   => array(),
	'removed'   => array(),
	'headers'   => array(),
	'status'    => 0,
	'auth'      => false,
	'cap'       => false,
	'admin'     => false,
	'is_author' => false,
	'off'       => array(),
);

/* ---------------------------------------------------------------- stubs */

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['th']['actions'][ $hook ][] = $callback;
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['th']['filters'][ $hook ][] = $callback;
	return true;
}

function remove_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['th']['removed'][] = $hook . ':' . ( is_string( $callback ) ? $callback : 'closure' );
	return true;
}

function apply_filters( $hook, $value, ...$args ) {
	if ( in_array( $hook, $GLOBALS['th']['off'], true ) ) {
		return false;
	}
	if ( empty( $GLOBALS['th']['filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['th']['filters'][ $hook ] as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function __return_false() {
	return false;
}

function __return_empty_array() {
	return array();
}

function __return_empty_string() {
	return '';
}

function is_admin() {
	return (bool) $GLOBALS['th']['admin'];
}

function is_user_logged_in() {
	return (bool) $GLOBALS['th']['auth'];
}

function current_user_can( $cap ) {
	return (bool) $GLOBALS['th']['cap'];
}

function is_author() {
	return (bool) $GLOBALS['th']['is_author'];
}

/* Wrapperne defineres før pluginnet, så headeren bliver fanget i stedet for sendt. */
function sgap_hardening_emit_header( string $line ): void {
	$GLOBALS['th']['headers'][] = $line;
}

function sgap_hardening_current_headers(): array {
	return $GLOBALS['th']['headers'];
}

function sgap_hardening_headers_sent(): bool {
	return false;
}

function status_header( $code ) {
	$GLOBALS['th']['status'] = (int) $code;
}

function wp_safe_redirect( $url, $status = 302 ) {
	$GLOBALS['th']['redirect'] = array( $url, $status );
	return true;
}

function home_url( $path = '' ) {
	return 'https://steamgears.eu' . $path;
}

class WP_REST_Request {
	private $route;
	public function __construct( $route ) {
		$this->route = $route;
	}
	public function get_route() {
		return $this->route;
	}
}

class WP_REST_Response {
	public $headers = array();
	public function header( $key, $value ) {
		$this->headers[ $key ] = $value;
	}
}

/* ------------------------------------------------------------- harness */

$GLOBALS['th_pass'] = 0;
$GLOBALS['th_fail'] = 0;

function th_is( $label, $expected, $actual ) {
	if ( $expected === $actual ) {
		$GLOBALS['th_pass']++;
		return;
	}
	$GLOBALS['th_fail']++;
	printf(
		"FAIL  %s\n      forventet: %s\n      fik:       %s\n",
		$label,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

function th_ok( $label, $condition ) {
	th_is( $label, true, (bool) $condition );
}

require_once __DIR__ . '/../mu-plugins/sgap-hardening.php';

/* 1. generator */
th_ok( 'wp_generator er fjernet fra wp_head', in_array( 'wp_head:wp_generator', $GLOBALS['th']['removed'], true ) );
th_is( 'the_generator giver tom streng', '', apply_filters( 'the_generator', 'WordPress 7.1.4' ) );

/* 2. REST-brugere */
$routes = array(
	'/wp/v2/users'               => array(),
	'/wp/v2/users/(?P<id>[\d]+)' => array(),
	'/wp/v2/posts'               => array(),
	'/wc/store/products'         => array(),
);
$anon = sgap_hardening_strip_user_routes( $routes, false );
th_is( 'anonym mister begge brugerruter', 2, count( $anon ) );
th_ok( 'anonym beholder indlæg', isset( $anon['/wp/v2/posts'] ) );
th_ok( 'anonym beholder butikken', isset( $anon['/wc/store/products'] ) );
$privileged = sgap_hardening_strip_user_routes( $routes, true );
th_is( 'list_users beholder alle ruter', 4, count( $privileged ) );

/* 3. forfatter-robots */
$indexed = array( 'index' => 'index', 'follow' => 'follow', 'max-snippet' => 'max-snippet:-1' );
$author  = sgap_hardening_author_robots( $indexed, true );
th_is( 'forfatterarkivet får noindex', 'noindex', $author['noindex'] ?? null );
th_ok( 'index er fjernet', ! isset( $author['index'] ) );
th_ok( 'max-snippet er fjernet', ! isset( $author['max-snippet'] ) );
th_is( 'follow bevares', 'follow', $author['follow'] );
th_is( 'andre visninger er urørte', $indexed, sgap_hardening_author_robots( $indexed, false ) );
$GLOBALS['th']['is_author'] = true;
th_is( 'wp_robots-hooken bruger is_author()', 'noindex', sgap_hardening_wp_robots( $indexed )['noindex'] ?? null );
$GLOBALS['th']['is_author'] = false;
th_is( 'wp_robots-hooken rører ikke forsiden', $indexed, sgap_hardening_wp_robots( $indexed ) );

/* 4. ?author= */
th_ok( 'anonym med ?author= blokeres', sgap_hardening_should_block_author_scan( true, false ) );
th_ok( 'indlogget med ?author= blokeres ikke', ! sgap_hardening_should_block_author_scan( true, true ) );
th_ok( 'uden ?author= sker intet', ! sgap_hardening_should_block_author_scan( false, false ) );
th_ok( 'template_redirect-hooken er registreret', in_array( 'sgap_hardening_block_author_scan', $GLOBALS['th']['actions']['template_redirect'] ?? array(), true ) );

/* 5. headers */
$lines = sgap_hardening_header_lines( array() );
th_is( 'fem header-linjer uden eksisterende', 5, count( $lines ) );
$flat = strtolower( implode( "\n", $lines ) );
th_ok( 'x-frame-options SAMEORIGIN', false !== strpos( $flat, 'x-frame-options: sameorigin' ) );
th_ok( 'frame-ancestors self', false !== strpos( $flat, "frame-ancestors 'self'" ) );
th_ok( 'referrer-policy strict', false !== strpos( $flat, 'referrer-policy: strict-origin-when-cross-origin' ) );
th_ok( 'permissions-policy', false !== strpos( $flat, 'permissions-policy: geolocation=()' ) );
th_ok( 'nosniff', false !== strpos( $flat, 'x-content-type-options: nosniff' ) );
th_ok( 'ingen payment-spærring (Stripe)', false === strpos( $flat, 'payment=()' ) );

/* 6. eksisterende headers */
$existing = array( 'X-Frame-Options: SAMEORIGIN', "Content-Security-Policy: frame-ancestors 'self'" );
th_is( 'kun de tre manglende sendes', 3, count( sgap_hardening_header_lines( $existing ) ) );
$merged = strtolower( implode( "\n", sgap_hardening_header_lines( $existing ) ) );
th_ok( 'eksisterende CSP duplikeres ikke', 0 === substr_count( $merged, 'content-security-policy' ) );
th_ok( 'eksisterende X-Frame-Options duplikeres ikke', 0 === substr_count( $merged, 'x-frame-options' ) );
th_ok( 'send_headers-hooken er registreret', in_array( 'sgap_hardening_send_headers', $GLOBALS['th']['actions']['send_headers'] ?? array(), true ) );

/* 7. XML-RPC-genkendelse */
th_ok( 'xmlrpc.php genkendes', sgap_hardening_is_xmlrpc_request( '/xmlrpc.php' ) );
th_ok( 'sti med undermappe genkendes', sgap_hardening_is_xmlrpc_request( '/wp/xmlrpc.php' ) );
th_ok( 'wp-login.php rammes ikke', ! sgap_hardening_is_xmlrpc_request( '/wp-login.php' ) );
th_ok( 'tom sti rammes ikke', ! sgap_hardening_is_xmlrpc_request( '' ) );

/* 8. XML-RPC-filtre */
th_is( 'xmlrpc_enabled er false', false, apply_filters( 'xmlrpc_enabled', true ) );
th_is( 'metodelisten er tom', array(), apply_filters( 'xmlrpc_methods', array( 'system.multicall' => 'x' ) ) );
th_ok( 'init-hooken er registreret', in_array( 'sgap_hardening_block_xmlrpc_request', $GLOBALS['th']['actions']['init'] ?? array(), true ) );

/* 9. REST-cache */
th_is( 'indekset får 60 s', 'public, max-age=60, s-maxage=60', sgap_hardening_rest_cache_header( '/' ) );
th_is( 'en anden rute røres ikke', null, sgap_hardening_rest_cache_header( '/wp/v2/posts' ) );
$response = new WP_REST_Response();
$out      = sgap_hardening_rest_post_dispatch( $response, null, new WP_REST_Request( '/' ) );
th_is( 'indeks-svaret får Cache-Control', 'public, max-age=60, s-maxage=60', $out->headers['Cache-Control'] ?? null );
th_ok( 'indeks-svaret får Expires', isset( $out->headers['Expires'] ) );

$other = new WP_REST_Response();
sgap_hardening_rest_post_dispatch( $other, null, new WP_REST_Request( '/wc/store/products' ) );
th_is( 'butikkens svar røres ikke', array(), $other->headers );
$bad = sgap_hardening_rest_post_dispatch( 'ikke-et-svar', null, new WP_REST_Request( '/' ) );
th_is( 'et ikke-svar crasher ikke', 'ikke-et-svar', $bad );

/* TTL-filteret sættes til sidst, så det ikke påvirker rækkerne ovenfor. */
add_filter( 'sgap_hardening_rest_cache_ttl', static function () { return 0; } );
th_is( 'et TTL på 0 giver no-store', 'no-store, max-age=0', sgap_hardening_rest_cache_header( '/' ) );

/* 10. kontakter */
th_ok( 'standarden er til, når intet er slået fra', sgap_hardening_enabled( 'identity' ) );
$GLOBALS['th']['off'] = array( 'sgap_hardening_feature' );
th_is( 'filteret slår en egenskab fra', false, sgap_hardening_enabled( 'headers' ) );
$GLOBALS['th']['off'] = array();

printf( "\n%d PASS · %d FAIL\n", $GLOBALS['th_pass'], $GLOBALS['th_fail'] );
exit( $GLOBALS['th_fail'] > 0 ? 1 : 0 );
