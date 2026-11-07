<?php
/**
 * WooCommerce and Printful tools for the site's MCP server (P1.16, 7.45.0).
 *
 * The shop side used to live in a separate, unfinished plugin: a `/tools` route
 * that returned a tool list but spoke no MCP, a token renewal against an
 * endpoint that answers 404, a default model the provider no longer offers, and
 * a "margin floor" computed as 40 % of the very price it was meant to check —
 * a floor that could never fail.
 *
 * Here it is one module, registered in the same `steamgears-tools` category as
 * every other tool, so the site's MCP server exposes it without another plugin
 * and without a second credential. Two rules hold:
 *
 *   1. **A cost is never invented.** It is the product's own `_sgap_cost` meta,
 *      or the Printful sync variant's price. Without either, the call is refused
 *      and says how to fix it — a missing cost is a real answer, not a guess.
 *   2. **Nothing goes live by accident.** Every change is a proposal in a queue,
 *      with the floor recomputed at apply time, and `woo-apply` needs confirm.
 *
 * The copy itself is not written here: the MCP client is an assistant already, and
 * SGAP_Studio writes product texts for the owner. Asking a third model from inside a
 * tool would be the same work done twice, with a second bill.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_MCP_Woo {
    const CATEGORY = 'steamgears-tools';
    /** Proposals, newest last. Not autoloaded: read only by these tools. */
    const OPTION = 'sgap_woo_proposals';
    /** Product meta the owner can fill in with the real cost price. */
    const COST_META = '_sgap_cost';
    const MAX_QUEUE = 50;
    const API = 'https://api.printful.com';
    const COST_CACHE = 'sgap_printful_costs';
    const COST_TTL = 21600; // 6 hours
    const MAX_PAGES = 4;
    const PAGE_SIZE = 100;

    /**
     * The shop tools, in the same shape as the rest of the catalogue.
     *
     * Registration, the capability callback and the ability metadata live in
     * SGAP_MCP_Tools; this class only adds its rows and does the work.
     *
     * name => [label, description, properties, required, execute, readonly, idempotent, destructive]
     */
    public static function definitions(): array {
        return [
            'woo-catalog' => [
                'Shop catalogue with cost and margin',
                'Lists products with price, the cost the module can actually document (your own cost field or the Printful sync variant), where that cost came from, the margin it leaves, and the lowest price your margin setting allows. Use only_missing_cost to find the products that cannot be priced safely yet.',
                [
                    'search' => ['type' => 'string', 'description' => 'Match the product title.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                    'only_missing_cost' => ['type' => 'boolean', 'default' => false]
                ],
                [], [__CLASS__, 'catalog'], true, true
            ],
            'woo-propose' => [
                'Propose a product optimisation',
                'Builds a proposal for one product — title, description, price — and puts it in the queue instead of changing the shop. The price is raised to the floor your margin setting requires, and the plan says by how much. Without a known cost the call is refused: set the cost field or the Printful key first. Send only the fields you want changed; the rest is carried over from the product.',
                [
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image_alt' => ['type' => 'string'],
                    'price' => ['type' => 'number', 'minimum' => 0]
                ],
                ['product_id'], [__CLASS__, 'propose'], false, false
            ],
            'woo-queue' => [
                'List the change queue',
                'The proposals waiting for approval, with product, before and after, the cost behind the price and what the floor was when the proposal was made. Nothing here has touched the shop yet.',
                [], [], [__CLASS__, 'queue'], true, true
            ],
            'woo-apply' => [
                'Apply a queued proposal',
                'Writes one proposal to the product. The margin is recomputed from the current cost first, and the price is raised to the floor if the proposal is below it. Dry run unless confirm is true, so the first call shows exactly what would be written.',
                [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'confirm' => ['type' => 'boolean', 'default' => false]
                ],
                ['id'], [__CLASS__, 'apply'], false, false, true
            ],
            'woo-discard' => [
                'Discard a queued proposal',
                'Removes one proposal from the queue without touching the product. The record is gone afterwards; the shop was never changed.',
                ['id' => ['type' => 'integer', 'minimum' => 1]],
                ['id'], [__CLASS__, 'discard'], false, true, true
            ]
        ];
    }

    /* ------------------------------------------------------------------ *
     * Margin and price
     * ------------------------------------------------------------------ */

    /** The owner's margin requirement, as a percentage above cost. */
    public static function margin(): int {
        $margin = (int) SGAP_Settings::get('woo_min_margin', 45);
        return min(500, max(0, $margin));
    }

    /**
     * The lowest price that still meets the margin, ending in .95.
     *
     * `floor(min) + 0.95` can land a few øre *below* the minimum (100.00 ->
     * 99.95), which is why it is raised by one when that happens: the floor is a
     * promise, so it must never round down through itself.
     */
    public static function floor_price(float $cost, int $margin): float {
        if ($cost <= 0) {
            return 0.0;
        }
        $min = $cost * (1 + $margin / 100);
        $price = floor($min) + 0.95;
        if ($price < $min) {
            $price += 1;
        }
        return round($price, 2);
    }

    /** Margin in percent for a price and a cost, or null when the cost is unknown. */
    public static function margin_percent(float $price, ?float $cost): ?float {
        if ($cost === null || $cost <= 0 || $price <= 0) {
            return null;
        }
        return round((($price - $cost) / $price) * 100, 1);
    }

    /* ------------------------------------------------------------------ *
     * Cost: the owner's field, then Printful, never a guess
     * ------------------------------------------------------------------ */

    /**
     * Where the cost of one product comes from.
     *
     * @return array{cost:?float, source:string, detail:string}
     */
    public static function cost_for($product, int $post_id): array {
        $meta = get_post_meta($post_id, self::COST_META, true);
        if (is_numeric($meta) && (float) $meta > 0) {
            return ['cost' => (float) $meta, 'source' => 'cost field', 'detail' => 'Product meta ' . self::COST_META . '.'];
        }
        $printful = self::printful_cost($product, $post_id);
        if ($printful['cost'] !== null) {
            return $printful;
        }
        return ['cost' => null, 'source' => 'unknown', 'detail' => $printful['detail']];
    }

    /**
     * The Printful sync variant's own price for one product.
     *
     * Matched on `external_id` (Printful stores the WooCommerce id there) or on
     * SKU. A name match is deliberately not used: sync names and shop names are
     * maintained separately, so matching on them pairs the wrong products.
     *
     * @return array{cost:?float, source:string, detail:string}
     */
    public static function printful_cost($product, int $post_id): array {
        $key = (string) SGAP_Settings::get('printful_api_key', '');
        if ($key === '') {
            return ['cost' => null, 'source' => 'unknown', 'detail' => 'No Printful key saved (Settings → Shop & feeds).'];
        }
        $items = self::printful_sync_products();
        if (is_wp_error($items)) {
            return ['cost' => null, 'source' => 'unknown', 'detail' => 'Printful could not be read: ' . $items->get_error_message()];
        }
        $sku = $product && method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
        foreach ($items as $item) {
            $variants = is_array($item['sync_variants'] ?? null) ? $item['sync_variants'] : [];
            foreach ($variants as $variant) {
                $external = (string) ($variant['external_id'] ?? '');
                $variant_sku = (string) ($variant['sku'] ?? '');
                $matches = ($external !== '' && (int) $external === $post_id)
                    || ($sku !== '' && $variant_sku !== '' && $variant_sku === $sku);
                if (!$matches) {
                    continue;
                }
                $price = $variant['variant']['price'] ?? null; // Printful's own price = the cost
                if (is_numeric($price) && (float) $price > 0) {
                    return ['cost' => (float) $price, 'source' => 'Printful', 'detail' => 'Sync variant ' . ($variant['id'] ?? '?') . ' price.'];
                }
                return ['cost' => null, 'source' => 'unknown', 'detail' => 'The Printful variant carries no price; Printful answers with retail_price only.'];
            }
        }
        return ['cost' => null, 'source' => 'unknown', 'detail' => 'The product is not a Printful sync product, or it has no matching external_id/SKU.'];
    }

    /** Printful sync products, paginated, cached for six hours. @return array|WP_Error */
    private static function printful_sync_products() {
        $cached = get_transient(self::COST_CACHE);
        if (is_array($cached)) {
            return $cached;
        }
        $key = (string) SGAP_Settings::get('printful_api_key', '');
        $store = (string) SGAP_Settings::get('printful_store_id', '');
        $items = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = self::API . '/store/products?limit=' . self::PAGE_SIZE . '&offset=' . ($page * self::PAGE_SIZE);
            // Test seam: a site (or the smoke suite) can answer the lookup without
            // a Printful account. Anything but null is used as the HTTP response.
            $response = apply_filters('sgap_woo_printful_response', null, $url, $key, $store);
            if ($response === null) {
                $response = wp_remote_get($url, [
                    'headers' => ['Authorization' => 'Bearer ' . $key, 'X-PF-Store-Id' => $store],
                    'timeout' => 15
                ]);
            }
            if (is_wp_error($response)) {
                return $response;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('sgap_printful_http', 'Printful answered HTTP ' . $code . '.', ['status' => $code]);
            }
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            $page_items = is_array($body['result'] ?? null) ? $body['result'] : [];
            $items = array_merge($items, $page_items);
            if (count($page_items) < self::PAGE_SIZE) {
                break;
            }
        }
        set_transient(self::COST_CACHE, $items, self::COST_TTL);
        return $items;
    }

    /* ------------------------------------------------------------------ *
     * The queue
     * ------------------------------------------------------------------ */

    /** @return array<int, array> */
    public static function proposals(): array {
        $list = get_option(self::OPTION, []);
        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    private static function store(array $list): void {
        update_option(self::OPTION, array_slice(array_values($list), -self::MAX_QUEUE), false);
    }

    public static function add_proposal(array $row): array {
        $list = self::proposals();
        $row['id'] = (int) (($list ? max(array_column($list, 'id')) : 0) + 1);
        $list[] = $row;
        self::store($list);
        return $row;
    }

    public static function find(int $id): ?array {
        foreach (self::proposals() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }

    public static function drop(int $id): bool {
        $list = self::proposals();
        $kept = array_values(array_filter($list, static fn($row) => (int) ($row['id'] ?? 0) !== $id));
        if (count($kept) === count($list)) {
            return false;
        }
        self::store($kept);
        return true;
    }

    /* ------------------------------------------------------------------ *
     * Tools
     * ------------------------------------------------------------------ */

    public static function catalog($input = null): array {
        $input = (array) $input;
        $limit = min(100, max(1, (int) (($input['limit'] ?? 25) ?: 25)));
        $only_missing = !empty($input['only_missing_cost']);
        $search = trim((string) ($input['search'] ?? ''));
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'numberposts' => $only_missing ? 100 : $limit,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }
        $margin = self::margin();
        $rows = [];
        foreach (get_posts($args) as $post) {
            $product = function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
            $price = $product && method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
            $cost = self::cost_for($product ?: null, (int) $post->ID);
            $row = [
                'id' => (int) $post->ID,
                'name' => get_the_title($post),
                'sku' => $product && method_exists($product, 'get_sku') ? (string) $product->get_sku() : '',
                'status' => $post->post_status,
                'price' => $price,
                'cost' => $cost['cost'],
                'cost_source' => $cost['source'],
                'cost_detail' => $cost['detail'],
                'margin_percent' => self::margin_percent($price, $cost['cost']),
                'floor_price' => $cost['cost'] === null ? null : self::floor_price($cost['cost'], $margin)
            ];
            $row['meets_floor'] = $cost['cost'] !== null && ($row['floor_price'] === null || $price >= $row['floor_price']);
            if ($only_missing && $cost['cost'] !== null) {
                continue;
            }
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }
        return [
            'woocommerce' => function_exists('wc_get_product'),
            'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
            'min_margin_percent' => $margin,
            'count' => count($rows),
            'products' => $rows
        ];
    }

    public static function propose($input = null) {
        $input = (array) $input;
        $product_id = (int) ($input['product_id'] ?? 0);
        if ($product_id <= 0) {
            return new WP_Error('sgap_woo_no_product', 'product_id is required.');
        }
        if (!function_exists('wc_get_product')) {
            return new WP_Error('sgap_woo_no_woo', 'WooCommerce is not active on this site, so there is no product to change.');
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('sgap_woo_missing', 'No product with id ' . $product_id . '.');
        }
        $cost = self::cost_for($product, $product_id);
        if ($cost['cost'] === null) {
            return new WP_Error(
                'sgap_woo_cost_unknown',
                'No cost price is known for "' . $product->get_name() . '", so the margin cannot be checked: ' . $cost['detail']
                . ' Set the product custom field ' . self::COST_META . ' to the real cost, or save a Printful key and store id under Settings → Shop & feeds. '
                . 'Nothing was queued.',
                ['status' => 400]
            );
        }
        $margin = self::margin();
        $floor = self::floor_price((float) $cost['cost'], $margin);

        $title = trim((string) ($input['title'] ?? ''));
        $description = (string) ($input['description'] ?? '');
        $alt = trim((string) ($input['image_alt'] ?? ''));
        $price = isset($input['price']) ? (float) $input['price'] : 0.0;
        $note = '';

        $title = $title !== '' ? $title : (string) $product->get_name();
        $description = $description !== '' ? $description : (string) $product->get_description();
        if ($price <= 0) {
            $price = (float) $product->get_price();
        }
        if ($price < $floor) {
            $note = trim($note . ' Price raised from ' . self::money($price) . ' to the floor ' . self::money($floor) . ' (' . $margin . ' % above a cost of ' . self::money((float) $cost['cost']) . ').');
            $price = $floor;
        }

        $row = self::add_proposal([
            'created' => current_time('mysql'),
            'product_id' => $product_id,
            'product_name' => (string) $product->get_name(),
            'before' => ['name' => (string) $product->get_name(), 'price' => (float) $product->get_price()],
            'after' => [
                'name' => mb_substr($title, 0, 200),
                'description' => mb_substr($description, 0, 8000),
                'price' => round($price, 2),
                'image_alt' => mb_substr($alt, 0, 200)
            ],
            'cost' => (float) $cost['cost'],
            'cost_source' => $cost['source'],
            'margin_percent' => $margin,
            'floor_price' => $floor,
            'margin_at_price' => self::margin_percent(round($price, 2), (float) $cost['cost']),
            'note' => $note
        ]);

        return [
            'queued' => true,
            'id' => $row['id'],
            'product_id' => $product_id,
            'cost' => $row['cost'],
            'cost_source' => $row['cost_source'],
            'floor_price' => $floor,
            'price' => $row['after']['price'],
            'margin_percent' => $row['margin_at_price'],
            'note' => $note,
            'next' => 'Call woo-apply with id ' . $row['id'] . ' (confirm false shows what would be written). The shop has not been touched.'
        ];
    }

    public static function queue($input = null): array {
        $rows = [];
        foreach (self::proposals() as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created' => (string) ($row['created'] ?? ''),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'price_before' => (float) ($row['before']['price'] ?? 0),
                'price_after' => (float) ($row['after']['price'] ?? 0),
                'cost' => (float) ($row['cost'] ?? 0),
                'cost_source' => (string) ($row['cost_source'] ?? ''),
                'floor_price' => (float) ($row['floor_price'] ?? 0),
                'margin_percent' => $row['margin_at_price'] ?? null,
                'note' => (string) ($row['note'] ?? '')
            ];
        }
        return ['count' => count($rows), 'proposals' => $rows];
    }

    public static function apply($input = null) {
        $input = (array) $input;
        $id = (int) ($input['id'] ?? 0);
        $row = self::find($id);
        if ($row === null) {
            return new WP_Error('sgap_woo_no_proposal', 'No proposal with id ' . $id . '. Call woo-queue to see the ids.');
        }
        if (!function_exists('wc_get_product')) {
            return new WP_Error('sgap_woo_no_woo', 'WooCommerce is not active on this site.');
        }
        $product = wc_get_product((int) ($row['product_id'] ?? 0));
        if (!$product) {
            return new WP_Error('sgap_woo_missing', 'The product in proposal ' . $id . ' no longer exists. Nothing was changed and the proposal was kept.');
        }

        $cost = self::cost_for($product, (int) $row['product_id']);
        $cost_value = $cost['cost'] ?? (float) ($row['cost'] ?? 0);
        if ($cost_value <= 0) {
            return new WP_Error('sgap_woo_cost_unknown', 'The cost for proposal ' . $id . ' is gone: ' . $cost['detail'] . ' Nothing was changed.');
        }
        $margin = self::margin();
        $floor = self::floor_price($cost_value, $margin);
        $price = round((float) ($row['after']['price'] ?? 0), 2);
        $raised = false;
        if ($price < $floor) {
            $price = $floor;
            $raised = true;
        }

        $plan = [
            'id' => $id,
            'product_id' => (int) $row['product_id'],
            'name_before' => (string) ($row['before']['name'] ?? ''),
            'name_after' => (string) ($row['after']['name'] ?? ''),
            'price_before' => (float) ($row['before']['price'] ?? 0),
            'price_after' => $price,
            'cost' => $cost_value,
            'cost_source' => $cost['source'],
            'min_margin_percent' => $margin,
            'floor_price' => $floor,
            'margin_percent' => self::margin_percent($price, $cost_value),
            'price_raised_to_floor' => $raised,
            'image_alt' => (string) ($row['after']['image_alt'] ?? '')
        ];

        if (empty($input['confirm'])) {
            return ['applied' => false, 'dry_run' => true, 'will_write' => $plan, 'next' => 'Call again with confirm true to write this.'];
        }

        $product->set_name(sanitize_text_field((string) ($row['after']['name'] ?? '')));
        $product->set_description(wp_kses_post((string) ($row['after']['description'] ?? '')));
        if (method_exists($product, 'set_regular_price')) {
            $product->set_regular_price(number_format($price, 2, '.', ''));
        }
        $product->save();
        self::drop($id);

        return [
            'applied' => true,
            'dry_run' => false,
            'id' => $id,
            'product_id' => (int) $row['product_id'],
            'written' => $plan,
            'note' => $raised ? 'The proposal was below the current floor and was raised to it.' : 'Applied as proposed.'
        ];
    }

    public static function discard($input = null) {
        $id = (int) (((array) $input)['id'] ?? 0);
        if ($id <= 0) {
            return new WP_Error('sgap_woo_no_proposal', 'id is required.');
        }
        if (!self::drop($id)) {
            return new WP_Error('sgap_woo_no_proposal', 'No proposal with id ' . $id . '.');
        }
        return ['discarded' => true, 'id' => $id];
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /** Currency-aware number for the notes. */
    private static function money(float $amount): string {
        $currency = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
        $formatted = number_format($amount, 2, '.', '');
        return $currency !== '' ? $formatted . ' ' . $currency : $formatted;
    }
}
