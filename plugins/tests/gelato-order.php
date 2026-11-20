<?php
/** Isolated Gelato order-flow tests: fake WooCommerce and HTTP; never contacts a provider. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['opts'] = ['sggf_settings' => ['api_key' => 'fake', 'mode' => 'live', 'shipment' => 'normal'], 'sggf_print_dir' => 'files'];
$GLOBALS['http_calls'] = [];
$GLOBALS['scheduled'] = [];
$GLOBALS['next_response'] = null;
$GLOBALS['on_lock'] = null;
function get_option($k, $default = false) { return $GLOBALS['opts'][$k] ?? $default; }
function update_option($k, $v, $autoload = null) { $GLOBALS['opts'][$k] = $v; return true; }
function add_option($k, $v, $deprecated = '', $autoload = null) {
    if (array_key_exists($k, $GLOBALS['opts'])) return false;
    $GLOBALS['opts'][$k] = $v;
    if ($GLOBALS['on_lock']) { $cb = $GLOBALS['on_lock']; $GLOBALS['on_lock'] = null; $cb(); }
    return true;
}
function delete_option($k) { unset($GLOBALS['opts'][$k]); }
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir() . '/sgap-gelato-test', 'baseurl' => 'https://example.test/uploads']; }
function trailingslashit($v) { return rtrim($v, '/') . '/'; }
function wp_json_encode($v) { return json_encode($v); }
function wp_remote_post($url, $args) { $GLOBALS['http_calls'][] = [$url, $args]; if ($GLOBALS['next_response'] instanceof Throwable) throw $GLOBALS['next_response']; return $GLOBALS['next_response']; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_schedule_single_event($when, $hook, $args) { $GLOBALS['scheduled'][] = [$when, $hook, $args]; }
function current_time($type) { return '2026-11-20 00:00:00'; }
function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? null; }
class WP_Error { public function get_error_message() { return 'connection lost'; } }
class TestProduct { public function get_sku() { return 'SG-CC-01-5070'; } }
class WC_Order_Item_Product {
    public function get_product() { return new TestProduct(); }
    public function get_quantity() { return 1; }
}
class WC_Order {
    public int $id;
    public bool $paid = true;
    public array $meta = [];
    public array $notes = [];
    public function __construct($id) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function is_paid() { return $this->paid; }
    public function get_meta($key) { return $this->meta[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function delete_meta_data($key) { unset($this->meta[$key]); }
    public function read_meta_data($force = false) {}
    public function save() {}
    public function add_order_note($note) { $this->notes[] = $note; }
    public function get_items() { return [1 => new WC_Order_Item_Product()]; }
    public function has_shipping_address() { return false; }
    public function get_billing_email() { return 'nobody@example.test'; }
    public function get_billing_phone() { return ''; }
    public function get_customer_id() { return 0; }
    public function get_order_number() { return (string) $this->id; }
    public function get_currency() { return 'DKK'; }
}
require dirname(__DIR__) . '/steamgears-autopilot/includes/gelato.php';
$dir = wp_upload_dir()['basedir'] . '/files';
if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Cannot create test folder');
$file = $dir . '/' . SGAP_Gelato::file_name('SG-CC-01', '5070');
file_put_contents($file, 'test');
function fresh_order($id) { $o = new WC_Order($id); $GLOBALS['orders'][$id] = $o; $GLOBALS['http_calls'] = []; $GLOBALS['scheduled'] = []; return $o; }
function test_assert($label, $condition) { if (!$condition) { fwrite(STDERR, "FAIL $label\n"); exit(1); } echo "PASS $label\n"; }
try {
    $o = fresh_order(1); $o->paid = false; SGAP_Gelato::retry(1);
    test_assert('cancelled or unpaid order is not sent by a retry', count($GLOBALS['http_calls']) === 0);
    $o = fresh_order(2); $GLOBALS['on_lock'] = function () use ($o) { $o->meta[SGAP_Gelato::META_ID] = 'already-created'; };
    SGAP_Gelato::on_paid(2);
    test_assert('completed concurrent send is rechecked after the lock', count($GLOBALS['http_calls']) === 0);
    $o = fresh_order(11); $GLOBALS['on_lock'] = function () use ($o) { $o->paid = false; }; SGAP_Gelato::on_paid(11);
    test_assert('order cancelled before the lock is not sent', count($GLOBALS['http_calls']) === 0);
    $o = fresh_order(3); $GLOBALS['next_response'] = new WP_Error(); SGAP_Gelato::on_paid(3); SGAP_Gelato::retry(3); SGAP_Gelato::manual_send($o);
    test_assert('timeout blocks automatic and manual resends', count($GLOBALS['http_calls']) === 1 && $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && !$GLOBALS['scheduled']);
    test_assert('uncertain outcome tells operator to check Gelato', count(array_filter($o->notes, fn($n) => stripos($n, 'check Gelato') !== false)) >= 1);
    SGAP_Gelato::confirm_absent($o);
    test_assert('explicit confirmation clears uncertainty without sending', !$o->get_meta(SGAP_Gelato::META_UNCERTAIN) && count($GLOBALS['http_calls']) === 1);
    $o = fresh_order(4); $GLOBALS['next_response'] = ['status' => 429, 'body' => '{"message":"rate limit"}']; SGAP_Gelato::on_paid(4);
    test_assert('rate limit schedules a retry without uncertainty', count($GLOBALS['scheduled']) === 1 && !$o->get_meta(SGAP_Gelato::META_UNCERTAIN));
    $o->paid = false; SGAP_Gelato::retry(4);
    test_assert('cancelled order is not sent when the scheduled retry runs', count($GLOBALS['http_calls']) === 1);
    $o = fresh_order(5); $GLOBALS['next_response'] = ['status' => 201, 'body' => '{"id":"gelato-123"}']; SGAP_Gelato::on_paid(5); SGAP_Gelato::on_paid(5);
    test_assert('success records order id and prevents another send', $o->get_meta(SGAP_Gelato::META_ID) === 'gelato-123' && !$o->get_meta(SGAP_Gelato::META_UNCERTAIN) && count($GLOBALS['http_calls']) === 1);
    $o = fresh_order(6); $GLOBALS['next_response'] = ['status' => 503, 'body' => '{}']; SGAP_Gelato::on_paid(6);
    test_assert('server error is treated as uncertain', $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && !$GLOBALS['scheduled']);
    $o = fresh_order(7); $GLOBALS['next_response'] = ['status' => 200, 'body' => '{}']; SGAP_Gelato::on_paid(7);
    test_assert('success without an id is treated as uncertain', $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && !$GLOBALS['scheduled']);
    $o = fresh_order(9); $GLOBALS['next_response'] = new RuntimeException('simulated process error');
    try { SGAP_Gelato::on_paid(9); } catch (RuntimeException $e) {}
    test_assert('interrupted call leaves the outcome uncertain and releases its lock', $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && !isset($GLOBALS['opts']['sggf_lock_9']));
    $o = fresh_order(10); $o->meta[SGAP_Gelato::META_UNCERTAIN] = 'yes'; $o->meta[SGAP_Gelato::META_ID] = 'already-created'; SGAP_Gelato::confirm_absent($o);
    test_assert('a known Gelato order cannot be confirmed absent', $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && !$GLOBALS['http_calls']);
    $o = fresh_order(8); $o->meta[SGAP_Gelato::META_UNCERTAIN] = 'yes'; $GLOBALS['opts']['sggf_lock_8'] = time(); SGAP_Gelato::confirm_absent($o);
    test_assert('confirmation cannot unlock an in-flight send', $o->get_meta(SGAP_Gelato::META_UNCERTAIN) === 'yes' && isset($GLOBALS['opts']['sggf_lock_8']));
    $GLOBALS['opts']['sggf_lock_8'] = time() - 600; SGAP_Gelato::confirm_absent($o);
    test_assert('confirmed absence clears an old lock without sending', !$o->get_meta(SGAP_Gelato::META_UNCERTAIN) && !isset($GLOBALS['opts']['sggf_lock_8']) && !$GLOBALS['http_calls']);
} finally {
    unlink($file);
}
