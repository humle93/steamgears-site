<?php
/**
 * wordpress/tests/test-review-request.php — prøver anmeldelses-anmodningen uden WordPress.
 *
 * `sgap-review-request.php` er en cron-opgave med en mail i den anden ende: den skal vælge
 * de rigtige ordrer, vente de rigtige 14 dage, sende præcis én gang og ikke love kunden
 * noget butikken ikke gør. Alt det kan prøves med stubs i stedet for en hel WordPress — og
 * det skal prøves, fordi en mail der sendes to gange, eller til en annulleret ordre, ikke
 * giver en fejl nogen steder: den giver bare en kunde der husker butikken for det forkerte.
 *
 *   ./.tools/php wordpress/tests/test-review-request.php
 *
 * Prøver:
 *   1. Standardfristen er 14 dage, og filteret kan flytte den.
 *   2. En gennemført ordre giver præcis én planlagt opgave, og meta'en forhindrer en ny.
 *   3. Kørslen sender én mail til ordrens egen adresse med ét link pr. produkt til #reviews.
 *   4. Anden kørsel sender intet — heller ikke hvis ordren blev gennemført igen.
 *   5. En ordre der er refunderet/annulleret inden afsendelse får ingen mail, og det noteres.
 *   6. Er anmeldelser slået fra i butikken, planlægges og sendes intet.
 *   7. Produkter med lukkede anmeldelser spørges der ikke om; er der ingen tilbage, sendes intet.
 *   8. Action Scheduler bruges når den findes, ellers wp_schedule_single_event.
 *   9. HTML og tekst bærer de samme produktnavne — copy kan ikke drive fra hinanden.
 *  10. Mailen indeholder ingen incitamenter (rabat, gave, refundering) og ingen rå HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['rr'] = array(
	'hooks'       => array( 'filter' => array(), 'action' => array() ),
	'options'     => array( 'woocommerce_enable_reviews' => 'yes' ),
	'closed'      => array(),
	'permalinks'  => array(),
	'events'      => array(),
	'as_actions'  => array(),
	'mails'       => array(),
	'mail_result' => true,
	'orders'      => array(),
);

function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['rr']['hooks']['filter'][ $tag ][] = $callback;
	return true;
}

function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['rr']['hooks']['action'][ $tag ][] = $callback;
	return true;
}

function remove_action( $tag, $callback, $priority = 10 ) {
	$list = $GLOBALS['rr']['hooks']['action'][ $tag ] ?? array();
	$GLOBALS['rr']['hooks']['action'][ $tag ] = array_values( array_filter( $list, static fn( $cb ) => $cb !== $callback ) );
	return true;
}

function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['rr']['hooks']['filter'][ $tag ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function do_action( $tag, ...$args ) {
	foreach ( $GLOBALS['rr']['hooks']['action'][ $tag ] ?? array() as $callback ) {
		$callback( ...$args );
	}
}

function __return_false() {
	return false;
}

function __return_true() {
	return true;
}

function get_option( $name, $default = false ) {
	return $GLOBALS['rr']['options'][ $name ] ?? $default;
}

function wp_strip_all_tags( $text ): string {
	return trim( strip_tags( (string) $text ) );
}

function esc_html( $text ): string {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ): string {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function sanitize_email( $email ): string {
	return trim( (string) $email );
}

function get_permalink( $product_id ) {
	return $GLOBALS['rr']['permalinks'][ (int) $product_id ] ?? '';
}

function comments_open( $product_id ): bool {
	return ! in_array( (int) $product_id, $GLOBALS['rr']['closed'], true );
}

function wp_schedule_single_event( $when, $hook, $args = array() ) {
	$GLOBALS['rr']['events'][] = array( 'when' => $when, 'hook' => $hook, 'args' => $args );
	return true;
}

function wp_mail( $to, $subject, $message, $headers = array() ) {
	$GLOBALS['rr']['mails'][] = array( 'to' => $to, 'subject' => $subject, 'message' => $message, 'headers' => $headers );
	return (bool) $GLOBALS['rr']['mail_result'];
}

function wc_get_order( $order_id ) {
	return $GLOBALS['rr']['orders'][ (int) $order_id ] ?? false;
}

/** Minimal WC_Order + WC_Order_Item_Product: kun det plugin'et rører. */
class Fake_Item {
	public function __construct( private int $product_id, private string $name ) {}
	public function get_product_id(): int {
		return $this->product_id;
	}
	public function get_name(): string {
		return $this->name;
	}
}

class Fake_Order {
	public array $meta = array();
	public int $saves = 0;
	public function __construct(
		private int $id,
		private string $status,
		private string $number,
		private string $email,
		private array $items
	) {}
	public function get_id(): int {
		return $this->id;
	}
	public function get_status(): string {
		return $this->status;
	}
	public function set_status( string $status ): void {
		$this->status = $status;
	}
	public function get_order_number(): string {
		return $this->number;
	}
	public function get_billing_email(): string {
		return $this->email;
	}
	public function get_items(): array {
		return $this->items;
	}
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
	public function update_meta_data( $key, $value ): void {
		$this->meta[ $key ] = $value;
	}
	public function save(): void {
		$this->saves++;
	}
}

require_once dirname( __DIR__ ) . '/mu-plugins/sgap-review-request.php';

$pass = 0;
$fail = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  PASS  {$label}\n";
		return;
	}
	$fail++;
	echo "  FAIL  {$label}" . ( $detail !== '' ? " — {$detail}" : '' ) . "\n";
}

function reset_state(): void {
	$GLOBALS['rr']['hooks']['filter'] = array();
	$GLOBALS['rr']['hooks']['action'] = array();
	$GLOBALS['rr']['events']          = array();
	$GLOBALS['rr']['mails']           = array();
	$GLOBALS['rr']['orders']          = array();
	$GLOBALS['rr']['closed']          = array();
	$GLOBALS['rr']['options']['woocommerce_enable_reviews'] = 'yes';
	$GLOBALS['rr']['mail_result']     = true;
}

function product_map( array $ids ): void {
	foreach ( $ids as $id ) {
		$GLOBALS['rr']['permalinks'][ (int) $id ] = 'https://steamgears.eu/product/p' . (int) $id . '/';
	}
}

function fake_order( int $id, string $status = 'completed', array $items = array( array( 1, 'Brass Luna Moth — Tee' ), array( 2, 'Clockwork Rose — Art Print' ) ) ): Fake_Order {
	$list = array();
	foreach ( $items as $item ) {
		$list[] = new Fake_Item( (int) $item[0], (string) $item[1] );
	}
	$order = new Fake_Order( $id, $status, (string) $id, 'buyer' . $id . '@example.com', $list );
	$GLOBALS['rr']['orders'][ $id ] = $order;
	return $order;
}

echo "review-request\n";

// 1. Fristen.
reset_state();
product_map( array( 1, 2 ) );
check( 'standardfristen er 14 dage', sgap_review_delay_days() === 14, 'fik ' . sgap_review_delay_days() );
add_filter( 'sgap_review_delay_days', static fn() => 7 );
check( 'filteret kan flytte fristen til 7', sgap_review_delay_days() === 7, 'fik ' . sgap_review_delay_days() );

// 2. Planlægning: én opgave, og meta forhindrer en ny.
reset_state();
product_map( array( 1, 2 ) );
$order = fake_order( 100 );
$before = time();
sgap_review_schedule( 100 );
$event = $GLOBALS['rr']['events'][0] ?? null;
check( 'en gennemført ordre planlægger præcis én opgave', count( $GLOBALS['rr']['events'] ) === 1, 'fik ' . count( $GLOBALS['rr']['events'] ) );
check( 'opgaven ligger 14 dage ude', is_array( $event ) && abs( ( $event['when'] - $before ) - 14 * DAY_IN_SECONDS ) <= 2, 'when=' . ( $event['when'] ?? '?' ) );
check( 'opgaven bruger pluginets eget hook', is_array( $event ) && $event['hook'] === 'sgap_review_send', 'hook=' . ( $event['hook'] ?? '?' ) );
check( 'ordren husker hvornår den blev planlagt', (int) $order->get_meta( '_sgap_review_scheduled' ) > 0 );
sgap_review_schedule( 100 );
check( 'anden gennemførelse planlægger intet nyt', count( $GLOBALS['rr']['events'] ) === 1, 'fik ' . count( $GLOBALS['rr']['events'] ) );

// 3. Afsendelse.
reset_state();
product_map( array( 1, 2 ) );
$order = fake_order( 101 );
sgap_review_send( 101 );
$mail = $GLOBALS['rr']['mails'][0] ?? null;
check( 'mailen går til ordrens adresse', is_array( $mail ) && $mail['to'] === 'buyer101@example.com', 'to=' . ( $mail['to'] ?? '?' ) );
check( 'emnet spørger til ordren', is_array( $mail ) && str_contains( $mail['subject'], 'Steamgears' ), 'subject=' . ( $mail['subject'] ?? '?' ) );
check( 'begge produkter har et link til #reviews', is_array( $mail )
	&& str_contains( $mail['message'], 'https://steamgears.eu/product/p1/#reviews' )
	&& str_contains( $mail['message'], 'https://steamgears.eu/product/p2/#reviews' ) );
check( 'mailen nævner ordrenummeret', is_array( $mail ) && str_contains( $mail['message'], '101' ) );
check( 'verifikationen forklares', is_array( $mail ) && str_contains( $mail['message'], 'verified purchase' ) );
check( 'ordren er markeret som spurgt', (int) $order->get_meta( '_sgap_review_requested' ) > 0 );
check( 'tekst-versionen bærer de samme produktnavne', str_contains( sgap_review_text( $order ), 'Brass Luna Moth' )
	&& str_contains( sgap_review_text( $order ), 'Clockwork Rose' ) );

// 4. Ingen dubletter.
$mails_before = count( $GLOBALS['rr']['mails'] );
$again = sgap_review_send( 101 );
check( 'anden kørsel sender intet og svarer false', $again === false && count( $GLOBALS['rr']['mails'] ) === $mails_before );

// 5. Refunderet ordre inden afsendelse.
reset_state();
product_map( array( 1, 2 ) );
$order = fake_order( 102, 'completed' );
sgap_review_schedule( 102 );
$order->set_status( 'refunded' );
$sent = sgap_review_send( 102 );
check( 'refunderet ordre får ingen mail', $sent === false && count( $GLOBALS['rr']['mails'] ) === 0 );
check( 'springet noteres med status', $order->get_meta( '_sgap_review_skipped' ) === 'refunded', 'meta=' . $order->get_meta( '_sgap_review_skipped' ) );

// 6. Anmeldelser slået fra i butikken.
reset_state();
product_map( array( 1, 2 ) );
$GLOBALS['rr']['options']['woocommerce_enable_reviews'] = 'no';
fake_order( 103 );
sgap_review_schedule( 103 );
sgap_review_send( 103 );
check( 'er anmeldelser slået fra, sker der intet', count( $GLOBALS['rr']['events'] ) === 0 && count( $GLOBALS['rr']['mails'] ) === 0 );

// 7. Lukkede produktanmeldelser.
reset_state();
product_map( array( 1, 2 ) );
$GLOBALS['rr']['closed'] = array( 2 );
fake_order( 104 );
sgap_review_schedule( 104 );
sgap_review_send( 104 );
$mail = $GLOBALS['rr']['mails'][0] ?? null;
check( 'produkt med lukkede anmeldelser udelades', is_array( $mail )
	&& str_contains( $mail['message'], '/product/p1/' )
	&& ! str_contains( $mail['message'], '/product/p2/' ) );

reset_state();
product_map( array( 1, 2 ) );
$GLOBALS['rr']['closed'] = array( 1, 2 );
fake_order( 105 );
sgap_review_schedule( 105 );
sgap_review_send( 105 );
check( 'ingen anmeldbare produkter = ingen mail og ingen opgave', count( $GLOBALS['rr']['events'] ) === 0 && count( $GLOBALS['rr']['mails'] ) === 0 );

// 8. Action Scheduler når den findes.
reset_state();
product_map( array( 1, 2 ) );
fake_order( 106 );
if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $when, $hook, $args = array(), $group = '' ) {
		$GLOBALS['rr']['as_actions'][] = array( 'when' => $when, 'hook' => $hook, 'args' => $args, 'group' => $group );
		return 1;
	}
}
sgap_review_schedule( 106 );
check( 'Action Scheduler bruges frem for wp-cron når den findes', count( $GLOBALS['rr']['as_actions'] ) === 1 && count( $GLOBALS['rr']['events'] ) === 0 );

// 9 og 10. Copy: ens i begge formater, ingen incitamenter, ingen rå HTML.
reset_state();
product_map( array( 1, 2 ) );
$order = fake_order( 107, 'completed', array( array( 1, 'Goggles <script>alert(1)</script>' ), array( 2, 'Corset & Coat' ) ) );
$html = sgap_review_html( $order );
$text = sgap_review_text( $order );
check( 'HTML og tekst nævner det samme antal produkter',
	substr_count( $html, '#reviews' ) === 2 && substr_count( $text, '#reviews' ) === 2,
	'html=' . substr_count( $html, '#reviews' ) . ' tekst=' . substr_count( $text, '#reviews' ) );
check( 'produktnavne escapes i HTML', ! str_contains( $html, '<script>' ) && str_contains( $html, '&amp;' ) );
check(
	'copy afviser incitamenter i stedet for at tilbyde dem',
	str_contains( $text, 'We do not offer discounts, gifts or refunds in exchange for a review' )
		&& ! preg_match( '/leave a review (and|to) (get|receive)|get a (discount|gift)|rabat for en anmeldelse/i', $text )
);
check( 'ingen rå HTML i tekst-versionen', ! str_contains( $text, '<p' ) && ! str_contains( $text, '<a ' ) );
add_filter( 'sgap_review_request_enabled', '__return_false' );
check( 'kill-switchen slår alt fra', sgap_review_enabled() === false );

echo "\n{$pass} PASS / {$fail} FAIL\n";
exit( $fail === 0 ? 0 : 1 );
