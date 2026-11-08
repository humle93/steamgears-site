<?php
/**
 * wordpress/tests/test-design-system.php — prøver loaderen uden en WordPress-installation.
 *
 * `sga-design-system.php` er kun to ting: find CSS-filen, og print den i footeren efter
 * temaet. Det kan prøves med syv stubs i stedet for en hel WordPress — og det skal prøves,
 * fordi fejlen ellers først vises på det levende site: en loader der printer intet gør
 * ingen skade, men en loader der printer halvt eller glemmer `body_class` giver et tema
 * der ser næsten rigtigt ud.
 *
 *   ./.tools/php wordpress/tests/test-design-system.php
 *
 * Prøver:
 *   1. CSS'en findes i repoets css/-mappe, og footeren får et <style id="sga-design-system">
 *      med tokens og regler — ikke en tom blok.
 *   2. body får klassen `sg-ui`, som hele laget er afgrænset til.
 *   3. Kill-switchen (`sga_design_enabled`) fjerner både klassen og stilblokken.
 *   4. Mangler filen, printes en HTML-kommentar — og ingen CSS (siden er uændret, ikke ustylet).
 *   5. Ingen `</style` eller `<?` kan slippe ind i stilblokken.
 *
 * Scenariet «filen mangler» kører i en egen proces, fordi loaderen cacher sit fund statisk,
 * og fordi det kræver en kopi af loaderen et sted hvor der ikke ligger CSS ved siden af.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['sg_hooks'] = array(
	'filter' => array(),
	'action' => array(),
);
$GLOBALS['sg_kill']  = false;

function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['sg_hooks']['filter'][ $tag ][] = $callback;
	return true;
}

function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['sg_hooks']['action'][ $tag ][] = $callback;
	return true;
}

function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['sg_hooks']['filter'][ $tag ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function do_action( $tag ) {
	foreach ( $GLOBALS['sg_hooks']['action'][ $tag ] ?? array() as $callback ) {
		$callback();
	}
}

function is_admin(): bool {
	return false;
}

function is_feed(): bool {
	return false;
}

function trailingslashit( string $path ): string {
	return rtrim( $path, "/\\" ) . '/';
}

function wp_upload_dir(): array {
	return array(
		'basedir' => getenv( 'SG_TEST_UPLOADS' ) ?: sys_get_temp_dir() . '/sg-test-uploads-' . getmypid(),
		'error'   => false,
	);
}

// ---------------------------------------------------------------- scenariet «filen mangler»
if ( 'missing' === getenv( 'SG_TEST_MODE' ) ) {
	require getenv( 'SG_TEST_PLUGIN' );
	ob_start();
	do_action( 'wp_footer' );
	$html = ob_get_clean();
	$ok   = false !== strpos( $html, 'sga-design-system: ingen CSS-fil fundet' )
		&& false === strpos( $html, '<style' );
	echo $ok ? "PASS\n" : 'FAIL ' . substr( $html, 0, 120 ) . "\n";
	exit( $ok ? 0 : 1 );
}

$failures = 0;

function check( string $name, bool $ok, string $detail = '' ): void {
	global $failures;
	if ( $ok ) {
		echo "  PASS  {$name}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$name}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
}

echo "test-design-system: loader uden WordPress\n";

require __DIR__ . '/../mu-plugins/sga-design-system.php';

// 1. Stilblokken.
ob_start();
do_action( 'wp_footer' );
$html = ob_get_clean();

check(
	'footeren får en style-blok med id',
	false !== strpos( $html, '<style id="sga-design-system">' ) && false !== strpos( $html, '</style>' ),
	substr( $html, 0, 120 )
);
check( 'stilblokken indeholder tokens', false !== strpos( $html, '--sg-ink:' ) );
check( 'stilblokken indeholder regler', false !== strpos( $html, '.woocommerce ul.products' ) );
check( 'stilblokken er ikke tom eller afkortet', strlen( $html ) > 20000, 'fik ' . strlen( $html ) . ' tegn' );
check(
	'ingen </style> eller <? inde i CSS\'en',
	1 === substr_count( $html, '</style>' ) && false === strpos( $html, '<?' )
);

// 2. body-klassen.
$classes = apply_filters( 'body_class', array( 'home', 'wp-theme' ) );
check( 'body får sg-ui', in_array( 'sg-ui', $classes, true ), implode( ' ', $classes ) );
check( 'eksisterende klasser bevares', in_array( 'home', $classes, true ) );

// 3. Kill-switch.
add_filter(
	'sga_design_enabled',
	static function ( $on ) {
		return empty( $GLOBALS['sg_kill'] ) ? $on : false;
	}
);
$GLOBALS['sg_kill'] = true;

$classes = apply_filters( 'body_class', array( 'home' ) );
check( 'kill-switch fjerner sg-ui', ! in_array( 'sg-ui', $classes, true ) );

ob_start();
do_action( 'wp_footer' );
$html = ob_get_clean();
check( 'kill-switch fjerner stilblokken', '' === trim( $html ), trim( substr( $html, 0, 80 ) ) );
$GLOBALS['sg_kill'] = false;

// 4. Filen mangler: en kopi af loaderen, hvor der hverken er css/ eller uploads.
$tmp  = sys_get_temp_dir() . '/sg-test-missing-' . getmypid();
@mkdir( $tmp . '/mu-plugins', 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
copy( __DIR__ . '/../mu-plugins/sga-design-system.php', $tmp . '/mu-plugins/sga-design-system.php' );

$cmd  = 'SG_TEST_MODE=missing SG_TEST_PLUGIN=' . escapeshellarg( $tmp . '/mu-plugins/sga-design-system.php' )
	. ' SG_TEST_UPLOADS=' . escapeshellarg( $tmp . '/uploads' )
	. ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1';
$out  = array();
$code = 0;
exec( $cmd, $out, $code );
check( 'manglende fil giver en kommentar, ikke CSS', 0 === $code, implode( ' ', $out ) );

echo $failures ? "FEJL: {$failures} prøve(r) fejlede\n" : "OK: alle prøver bestået\n";
exit( $failures ? 1 : 0 );
