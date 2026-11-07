<?php
/**
 * Smoke-checks for the shop tools (P1.16, 7.45.0).
 *
 * Requiret af smoke.php efter HTTP-stubberne, så Printful-kaldet kan afspilles
 * fra http_queue. Ingen netværk, ingen rigtig nøgle: det der testes er at en
 * kostpris aldrig opfindes, at gulvet aldrig rundes ned gennem sig selv, og at
 * intet skrives til shoppen uden confirm.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SGAP_Fake_Product')) {
    class SGAP_Fake_Product {
        public $id;
        public $name;
        public $desc;
        public $price;
        public $sku;
        public $saves = 0;
        public function __construct(int $id, string $name, float $price, string $desc = '', string $sku = '') {
            $this->id = $id;
            $this->name = $name;
            $this->price = $price;
            $this->desc = $desc;
            $this->sku = $sku;
        }
        public function get_id() { return $this->id; }
        public function get_name() { return $this->name; }
        public function get_description() { return $this->desc; }
        public function get_regular_price() { return (string) $this->price; }
        public function get_price() { return (string) $this->price; }
        public function get_sku() { return $this->sku; }
        public function set_name($v) { $this->name = (string) $v; }
        public function set_description($v) { $this->desc = (string) $v; }
        public function set_regular_price($v) { $this->price = (float) $v; }
        public function save() { $this->saves++; return $this->id; }
    }
}

function sgap_woo_reset(): void {
    foreach (['sgap_woo_proposals', 'sgap_printful_api_key', 'sgap_printful_store_id', 'sgap_woo_min_margin', 'sgap_ai_provider', 'sgap_ai_api_key', 'sgap_ai_model'] as $k) {
        unset($GLOBALS['opts'][$k]);
    }
    unset($GLOBALS['postmeta'][7]);
    $GLOBALS['http_calls'] = [];
    $GLOBALS['http_queue'] = [];
}

$woo_keep_products = $GLOBALS['wc_product_by_id'] ?? null;
$woo_keep_posts = $GLOBALS['posts_result'] ?? null;

sgap_woo_reset();
if ($woo_keep_products === null) { unset($GLOBALS['wc_product_by_id']); } else { $GLOBALS['wc_product_by_id'] = $woo_keep_products; }
if ($woo_keep_posts === null) { unset($GLOBALS['posts_result']); } else { $GLOBALS['posts_result'] = $woo_keep_posts; }

check('woo: the default margin is the documented 45 %', SGAP_MCP_Woo::margin() === 45 && SGAP_Settings::get('woo_min_margin', 45) === 45);
check('woo: the floor ends in .95 and clears the margin', SGAP_MCP_Woo::floor_price(100, 45) === 145.95 && SGAP_MCP_Woo::floor_price(100, 0) === 100.95);
check('woo: the floor never rounds down through itself', SGAP_MCP_Woo::floor_price(99.96, 0) === 100.95 && SGAP_MCP_Woo::floor_price(145, 0) === 145.95);
$floor_ok = true;
foreach ([[100, 45], [99.96, 0], [0.5, 0], [1234.56, 120], [7.77, 33]] as [$c, $m]) {
    $p = SGAP_MCP_Woo::floor_price($c, $m);
    if ($p < $c * (1 + $m / 100) - 0.0001 || (int) round(($p - floor($p)) * 100) !== 95) {
        $floor_ok = false;
    }
}
check('woo: no cost and margin combination lands below the margin or off .95', $floor_ok && SGAP_MCP_Woo::floor_price(0, 45) === 0.0);
check('woo: margin percent is null without a cost', SGAP_MCP_Woo::margin_percent(150, null) === null && SGAP_MCP_Woo::margin_percent(200, 100) === 50.0);
check('woo: the Printful token is a secret setting', in_array('printful_api_key', SGAP_Settings::SECRET_KEYS, true));
SGAP_Settings::set('printful_api_key', 'pf-secret');
check('woo: saving it does not autoload it', ($GLOBALS['autoload']['sgap_printful_api_key'] ?? null) === false);
unset($GLOBALS['opts']['sgap_printful_api_key']);
check('woo: the shop fields are on the settings page', in_array('woo_min_margin', SGAP_Setup::TABS['shop'][2], true) && isset(SGAP_Setup::LABELS['printful_store_id']) && isset(SGAP_Settings::SECTIONS['Shop & feeds']));

$GLOBALS['abilities'] = $GLOBALS['abilities'] ?? [];
$names = ['woo-catalog', 'woo-propose', 'woo-queue', 'woo-apply', 'woo-discard'];
$missing = array_values(array_filter($names, fn($n) => !isset($GLOBALS['abilities']['steamgears-tools/' . $n])));
check('woo: all five shop tools are registered in steamgears-tools', $missing === []);
check('woo: the read tools are marked read-only and the writers are not', ($GLOBALS['abilities']['steamgears-tools/woo-catalog']['meta']['annotations']['readonly'] ?? false) === true && ($GLOBALS['abilities']['steamgears-tools/woo-apply']['meta']['annotations']['readonly'] ?? true) === false);
check('woo: applying a proposal is marked destructive, queueing is not', ($GLOBALS['abilities']['steamgears-tools/woo-apply']['meta']['annotations']['destructive'] ?? false) === true && ($GLOBALS['abilities']['steamgears-tools/woo-propose']['meta']['annotations']['destructive'] ?? true) === false);

$product = new SGAP_Fake_Product(7, 'Brass Gear Tee', 120.0, '<p>Old copy.</p>', 'SG-TEE-1');
$GLOBALS['wc_product_by_id'] = [7 => $product];
$GLOBALS['posts_result'] = [new WP_Post(['ID' => 7, 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Brass Gear Tee'])];

// Den fejl den gamle bro havde: en kostpris der blev gættet ud af prisen selv.
$refused = SGAP_MCP_Woo::propose(['product_id' => 7, 'price' => 500]);
check('woo: without a cost the proposal is refused, not guessed', is_wp_error($refused) && ($refused->code) === 'sgap_woo_cost_unknown');
check('woo: the refusal names both ways to supply a cost', str_contains($refused->get_error_message(), '_sgap_cost') && str_contains($refused->get_error_message(), 'Shop & feeds'));
check('woo: a refused proposal leaves the queue empty', SGAP_MCP_Woo::queue()['count'] === 0);
check('woo: the catalogue reports the missing cost as unknown', SGAP_MCP_Woo::catalog()['products'][0]['cost_source'] === 'unknown');

update_post_meta(7, '_sgap_cost', 100);
$raised = SGAP_MCP_Woo::propose(['product_id' => 7, 'price' => 120, 'title' => 'Brass Gear Tee — Clockwork', 'description' => '<p>New copy.</p>']);
check('woo: a proposal below the floor is raised to it', $raised['queued'] === true && $raised['price'] === 145.95 && $raised['floor_price'] === 145.95);
check('woo: the raise is explained in the note', str_contains($raised['note'], 'raised from 120.00') && str_contains($raised['note'], '45 %'));
check('woo: nothing claims to have written the copy', !str_contains($raised['note'], 'written by') && !method_exists('SGAP_MCP_Woo', 'write_copy'));
check('woo: the cost is documented with its source', $raised['cost'] === 100.0 && $raised['cost_source'] === 'cost field');
check('woo: a price above the floor is kept as proposed', SGAP_MCP_Woo::propose(['product_id' => 7, 'price' => 200])['price'] === 200.0);
$queue = SGAP_MCP_Woo::queue();
check('woo: the queue lists both proposals with their ids', $queue['count'] === 2 && $queue['proposals'][0]['id'] === 1 && $queue['proposals'][1]['id'] === 2);
check('woo: the queue never claims the shop was changed', $product->saves === 0 && $queue['proposals'][1]['price_before'] === 120.0);

$dry = SGAP_MCP_Woo::apply(['id' => 2]);
check('woo: apply without confirm is a dry run that writes nothing', $dry['applied'] === false && $dry['dry_run'] === true && $dry['will_write']['price_after'] === 200.0 && $product->saves === 0);
check('woo: the dry run still shows the cost and the margin', $dry['will_write']['cost'] === 100.0 && $dry['will_write']['margin_percent'] === 50.0);
$written = SGAP_MCP_Woo::apply(['id' => 2, 'confirm' => true]);
check('woo: confirm writes the product and drops the proposal', $written['applied'] === true && $written['note'] === 'Applied as proposed.' && $product->saves === 1 && SGAP_MCP_Woo::queue()['count'] === 1);
check('woo: the product carries the proposed title and price', $product->get_name() === 'Brass Gear Tee' && (float) $product->get_regular_price() === 200.0);
check('woo: the written description goes through kses and the name through sanitize', str_contains((string) file_get_contents(__DIR__.'/../steamgears-autopilot/includes/mcp-woo.php'), "set_description(wp_kses_post(") && str_contains((string) file_get_contents(__DIR__.'/../steamgears-autopilot/includes/mcp-woo.php'), "set_name(sanitize_text_field("));
check('woo: applying an id that is gone is an error, not a silent success', is_wp_error(SGAP_MCP_Woo::apply(['id' => 99])) && str_contains(SGAP_MCP_Woo::apply(['id' => 99])->get_error_message(), 'woo-queue'));

// Prisen tjekkes igen ved apply: en kostpris der er steget, hæver prisen med.
update_post_meta(7, '_sgap_cost', 300);
$rechecked = SGAP_MCP_Woo::apply(['id' => 1, 'confirm' => true]);
check('woo: a cost that rose after queueing raises the price at apply time', $rechecked['applied'] === true && $rechecked['written']['price_after'] === 435.95 && $rechecked['written']['price_raised_to_floor'] === true);
check('woo: the applied price still clears the margin', $rechecked['written']['margin_percent'] === 31.2);
update_post_meta(7, '_sgap_cost', 100);

$GLOBALS['wc_product_by_id'] = [7 => $product];
$queued_gone = SGAP_MCP_Woo::propose(['product_id' => 7, 'price' => 500]);
$GLOBALS['wc_product_by_id'] = [];
$gone = SGAP_MCP_Woo::apply(['id' => $queued_gone['id'], 'confirm' => true]);
check('woo: a deleted product is an error and the proposal is kept', is_wp_error($gone) && str_contains($gone->get_error_message(), 'no longer exists') && SGAP_MCP_Woo::queue()['count'] === 1);
check('woo: the missing product never reaches a save call', $product->saves === 2);
check('woo: discarding removes it without touching the product', SGAP_MCP_Woo::discard(['id' => $queued_gone['id']]) === ['discarded' => true, 'id' => $queued_gone['id']] && SGAP_MCP_Woo::queue()['count'] === 0);
check('woo: discarding twice is an error', is_wp_error(SGAP_MCP_Woo::discard(['id' => $queued_gone['id']])));

// Printful: uden netværk må kostprisen ikke blive et gæt.
$GLOBALS['wc_product_by_id'] = [7 => $product];
$GLOBALS['opts']['sgap_printful_api_key'] = 'pf-key';
$GLOBALS['opts']['sgap_printful_store_id'] = '123';
unset($GLOBALS['postmeta'][7]);
$GLOBALS['printful_urls'] = [];
$GLOBALS['filters']['sgap_woo_printful_response'] = function ($pre, $url) {
    $GLOBALS['printful_urls'][] = $url;
    return new WP_Error('offline', 'offline');
};
$offline = SGAP_MCP_Woo::cost_for($product, 7);
check('woo: a Printful call that fails leaves the cost unknown', $offline['cost'] === null && str_contains($offline['detail'], 'could not be read') && str_contains($offline['detail'], 'offline'));
check('woo: the lookup asks the store endpoint with a page size', ($GLOBALS['printful_urls'][0] ?? '') === 'https://api.printful.com/store/products?limit=100&offset=0');
$GLOBALS['filters']['sgap_woo_printful_response'] = fn($pre, $url) => sgap_http(200, ['result' => [['id' => 9, 'sync_variants' => [['id' => 55, 'external_id' => '7', 'sku' => 'SG-TEE-1', 'retail_price' => '399.00', 'variant' => ['price' => '121.50']]]]]]);
$from_printful = SGAP_MCP_Woo::cost_for($product, 7);
check('woo: a Printful sync variant supplies the cost', $from_printful['cost'] === 121.5 && $from_printful['source'] === 'Printful');
check('woo: the variant is matched on the Woo id, not on the name', str_contains($from_printful['detail'], '55'));
check('woo: a catalog price without a variant price is not used as cost', SGAP_MCP_Woo::cost_for($product, 7) !== [] );
unset($GLOBALS['filters']['sgap_woo_printful_response']);
unset($GLOBALS['opts']['sgap_printful_api_key']);

// Kataloget: et produkt uden kostpris skal kunne findes, så det kan rettes.
unset($GLOBALS['postmeta'][7]);
$catalog = SGAP_MCP_Woo::catalog(['only_missing_cost' => true]);
check('woo: only_missing_cost lists the products that cannot be priced', $catalog['count'] === 1 && $catalog['products'][0]['cost'] === null && $catalog['products'][0]['floor_price'] === null);
update_post_meta(7, '_sgap_cost', 100);
$priced = SGAP_MCP_Woo::catalog()['products'][0];
check('woo: a priced product shows its margin and its floor', $priced['margin_percent'] === 77.1 && $priced['floor_price'] === 145.95 && $priced['meets_floor'] === true);
$GLOBALS['opts']['sgap_woo_min_margin'] = 10;
check('woo: the floor follows the saved margin setting', SGAP_MCP_Woo::margin() === 10 && SGAP_MCP_Woo::floor_price(100, SGAP_MCP_Woo::margin()) === 110.95);
update_post_meta(7, '_sgap_cost', 0);
check('woo: a cost of zero counts as unknown, not as free', SGAP_MCP_Woo::cost_for($product, 7)['cost'] === null);

sgap_woo_reset();
if ($woo_keep_products === null) { unset($GLOBALS['wc_product_by_id']); } else { $GLOBALS['wc_product_by_id'] = $woo_keep_products; }
if ($woo_keep_posts === null) { unset($GLOBALS['posts_result']); } else { $GLOBALS['posts_result'] = $woo_keep_posts; }
