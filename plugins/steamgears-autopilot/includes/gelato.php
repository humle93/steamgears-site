<?php
/**
 * Gelato print orders: paid WooCommerce orders for the Steamgears posters (variant SKU
 * SG-DA|SG-CC|SG-AC-NN-3040|5070) are sent straight to Gelato's order API with the right
 * print file, so no product has to be connected in Gelato's own app.
 *
 * Off by default. "Draft" creates draft orders in Gelato that are approved there; "Live"
 * sends them to print. One Gelato order per WooCommerce order: an option lock plus order
 * meta stop a second send, 5xx/429/timeouts are retried twice, 4xx becomes an order note.
 *
 * Moved in from the stand-alone plugin sg-gelato-forward 1.0.0 (19/11 2026). The option and
 * meta keys are unchanged (sggf_*), so the saved key, mode and print-file folder carry over.
 * While that plugin is still active, this module sends nothing and says so.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Gelato {
    const OPTION = 'sggf_settings';
    const LOG = 'sggf_log';
    const DIR_OPTION = 'sggf_print_dir';
    const API = 'https://order.gelatoapis.com/v4/orders';
    const META_ID = '_sggf_gelato_order_id';
    const META_ATTEMPTS = '_sggf_attempts';
    const RETRY_HOOK = 'sggf_retry';
    const MAX_ATTEMPTS = 3;
    const PAGE = 'sgap-gelato';

    /** Gelato products: 200 gsm matte poster paper, the paper the price model is built on. */
    const PRODUCTS = [
        '3040' => 'flat_product_pf_300x400-mm_pt_200-gsm-uncoated_cl_4-0_ct_none_prt_none_sft_none_set_none_ver',
        '5070' => 'flat_product_pf_500x700-mm_pt_200-gsm-uncoated_cl_4-0_ct_none_prt_none_sft_none_set_none_ver',
    ];

    /** Product SKU => print file name without size and .pdf. The files live in uploads/<sggf_print_dir>/. */
    const FILES = [
        'SG-DA-01' => 'steamgears-diesel-age-diesel-003-female-aviator-airship',
        'SG-DA-02' => 'steamgears-diesel-age-diesel-004-art-deco-locomotive',
        'SG-DA-03' => 'steamgears-diesel-age-diesel-005-spy-radio-operator',
        'SG-DA-04' => 'steamgears-diesel-age-diesel-006-female-race-driver',
        'SG-DA-05' => 'steamgears-diesel-age-diesel-007-female-v12-mechanic',
        'SG-DA-06' => 'steamgears-diesel-age-diesel-008-submarine-naval-base',
        'SG-DA-07' => 'steamgears-diesel-age-diesel-009-tesla-arc-engineer',
        'SG-DA-08' => 'steamgears-diesel-age-mix-002-dieselpunk-shipyard-scout',
        'SG-CC-01' => 'steamgears-clockwork-court-clock-001-baroque-clockwork-stag',
        'SG-CC-02' => 'steamgears-clockwork-court-clock-002-female-clocktower-inventor',
        'SG-CC-03' => 'steamgears-clockwork-court-clock-004-cathedral-of-time',
        'SG-CC-04' => 'steamgears-clockwork-court-clock-005-venetian-automaton-harpsichord',
        'SG-CC-05' => 'steamgears-clockwork-court-clock-006-female-guard-captain-crossbow',
        'SG-CC-06' => 'steamgears-clockwork-court-clock-007-female-fencer-brass-arm',
        'SG-CC-07' => 'steamgears-clockwork-court-clock-008-royal-watchmaker-phoenix',
        'SG-CC-08' => 'steamgears-clockwork-court-clock-009-female-royal-horologist',
        'SG-CC-09' => 'steamgears-clockwork-court-mix-001-botanist-clockwork-squirrel',
        'SG-AC-10' => 'steamgears-airship-chronicles-clock-010-wind-up-clockwork-galleon',
        'SG-AC-12' => 'steamgears-airship-chronicles-clock-003-renaissance-ornithopter',
    ];

    public static function init(): void {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'on_paid'], 20);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_paid'], 20);
        add_action(self::RETRY_HOOK, [__CLASS__, 'retry'], 10, 1);
        add_filter('woocommerce_order_actions', [__CLASS__, 'order_action']);
        add_action('woocommerce_order_action_sggf_send', [__CLASS__, 'manual_send']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sgap_gelato_save', [__CLASS__, 'save']);
    }

    /** True while the old stand-alone plugin is active: then it owns the orders, not this module. */
    public static function standalone_active(): bool {
        return class_exists('SG_Gelato_Forward');
    }

    /** @return array{api_key:string,mode:string,shipment:string} */
    public static function settings(): array {
        $s = (array) get_option(self::OPTION, []);
        return [
            'api_key' => (string) ($s['api_key'] ?? ''),
            'mode' => in_array($mode = (string) ($s['mode'] ?? 'off'), ['off', 'draft', 'live'], true) ? $mode : 'off',
            'shipment' => in_array($ship = (string) ($s['shipment'] ?? 'normal'), ['normal', 'express'], true) ? $ship : 'normal',
        ];
    }

    /** Which poster and size a variant SKU is, e.g. SG-CC-01-5070 => [SG-CC-01, 5070]. Pure. */
    public static function parse_sku(string $sku): ?array {
        if (!preg_match('/^(SG-(?:DA|CC|AC)-\d{2})-(3040|5070)$/', $sku, $m) || !isset(self::FILES[$m[1]])) {
            return null;
        }
        return ['sku' => $sku, 'base' => $m[1], 'size' => $m[2]];
    }

    /** The print file name for a poster and size. Pure. */
    public static function file_name(string $base, string $size): string {
        return self::FILES[$base] . '-' . ($size === '3040' ? '30x40' : '50x70') . '.pdf';
    }

    public static function file_url(string $base, string $size): string {
        return trailingslashit(wp_upload_dir()['baseurl']) . (string) get_option(self::DIR_OPTION, '') . '/' . self::file_name($base, $size);
    }

    public static function file_path(string $base, string $size): string {
        return trailingslashit(wp_upload_dir()['basedir']) . (string) get_option(self::DIR_OPTION, '') . '/' . self::file_name($base, $size);
    }

    /** @return array{sku:string,base:string,size:string}|null */
    public static function match_item($item): ?array {
        if (!$item instanceof WC_Order_Item_Product) {
            return null;
        }
        $product = $item->get_product();
        return $product ? self::parse_sku((string) $product->get_sku()) : null;
    }

    /**
     * Gelato's order JSON for the posters the order holds. No items = nothing to send.
     *
     * @return array<string,mixed>
     */
    public static function build_payload(WC_Order $order, string $orderType): array {
        $items = [];
        foreach ($order->get_items() as $itemId => $item) {
            $m = self::match_item($item);
            if (!$m) {
                continue;
            }
            $items[] = [
                'itemReferenceId' => 'wc-item-' . $itemId,
                'productUid' => self::PRODUCTS[$m['size']],
                'files' => [['type' => 'default', 'url' => self::file_url($m['base'], $m['size'])]],
                'quantity' => max(1, (int) $item->get_quantity()),
            ];
        }

        $ship = $order->has_shipping_address();
        $get = static function (string $field) use ($order, $ship): string {
            $shipping = 'get_shipping_' . $field;
            $billing = 'get_billing_' . $field;
            $value = $ship && method_exists($order, $shipping) ? (string) $order->$shipping() : '';
            return $value !== '' ? $value : (method_exists($order, $billing) ? (string) $order->$billing() : '');
        };
        $address = array_filter([
            'firstName' => $get('first_name'),
            'lastName' => $get('last_name'),
            'companyName' => $get('company'),
            'addressLine1' => $get('address_1'),
            'addressLine2' => $get('address_2'),
            'city' => $get('city'),
            'postCode' => $get('postcode'),
            'state' => $get('state'),
            'country' => $get('country'),
            'email' => (string) $order->get_billing_email(),
            'phone' => $get('phone') !== '' ? $get('phone') : (string) $order->get_billing_phone(),
        ], static fn ($v) => $v !== '');

        $customer = $order->get_customer_id()
            ? 'wc-customer-' . $order->get_customer_id()
            : 'wc-guest-' . substr(md5(strtolower((string) $order->get_billing_email())), 0, 12);

        return [
            'orderType' => $orderType,
            'orderReferenceId' => 'wc-' . $order->get_order_number(),
            'customerReferenceId' => $customer,
            'currency' => $order->get_currency() ?: 'DKK',
            'items' => $items,
            'shipmentMethodUid' => self::settings()['shipment'],
            'shippingAddress' => $address,
        ];
    }

    public static function on_paid($orderId): void {
        self::forward((int) $orderId, false);
    }

    public static function retry($orderId): void {
        self::forward((int) $orderId, false);
    }

    public static function manual_send($order): void {
        if ($order instanceof WC_Order) {
            self::forward($order->get_id(), true);
        }
    }

    public static function order_action($actions) {
        $actions = (array) $actions;
        $actions['sggf_send'] = 'Send posters to Gelato';
        return $actions;
    }

    public static function forward(int $orderId, bool $manual): void {
        if (self::standalone_active() || !function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }
        $settings = self::settings();
        if (($settings['mode'] === 'off' && !$manual) || $order->get_meta(self::META_ID)) {
            return;
        }
        $orderType = $settings['mode'] === 'live' ? 'order' : 'draft';
        $payload = self::build_payload($order, $orderType);
        if ($payload['items'] === []) {
            return;
        }
        if ($settings['api_key'] === '') {
            $order->add_order_note('Gelato: the posters were not sent because the Gelato API key is missing (Steamgears → Gelato print orders).');
            return;
        }
        $missing = [];
        foreach ($order->get_items() as $item) {
            $m = self::match_item($item);
            if ($m && !file_exists(self::file_path($m['base'], $m['size']))) {
                $missing[] = $m['sku'];
            }
        }
        if ($missing !== []) {
            $order->add_order_note('Gelato: the posters were not sent because the print file is missing for ' . implode(', ', $missing) . '.');
            self::log($orderId, 'error', 'print file missing: ' . implode(', ', $missing));
            return;
        }

        $lock = 'sggf_lock_' . $orderId;
        if (!add_option($lock, time(), '', false)) {
            return;
        }
        try {
            $response = wp_remote_post(self::API, [
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json', 'X-API-KEY' => $settings['api_key']],
                'body' => wp_json_encode($payload),
            ]);
            $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
            $body = is_wp_error($response) ? $response->get_error_message() : (string) wp_remote_retrieve_body($response);
            $json = json_decode($body, true);

            if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['id'])) {
                $order->update_meta_data(self::META_ID, (string) $json['id']);
                $order->update_meta_data('_sggf_gelato_type', $orderType);
                $order->save();
                $order->add_order_note(sprintf(
                    'Gelato: %d poster line(s) sent as %s. Gelato order %s.%s',
                    count($payload['items']),
                    $orderType === 'order' ? 'a live order' : 'a draft (approve it in Gelato)',
                    (string) $json['id'],
                    $manual ? ' (sent by hand)' : ''
                ));
                self::log($orderId, 'ok', $orderType . ' ' . (string) $json['id']);
                return;
            }

            $attempts = (int) $order->get_meta(self::META_ATTEMPTS) + 1;
            $order->update_meta_data(self::META_ATTEMPTS, $attempts);
            $order->save();
            $short = mb_substr(is_array($json) && isset($json['message']) ? (string) $json['message'] : $body, 0, 300);
            if (self::temporary($code) && $attempts < self::MAX_ATTEMPTS) {
                wp_schedule_single_event(time() + 15 * MINUTE_IN_SECONDS * $attempts, self::RETRY_HOOK, [$orderId]);
                $order->add_order_note(sprintf('Gelato answered %d (attempt %d of %d), trying again in %d min.: %s', $code, $attempts, self::MAX_ATTEMPTS, 15 * $attempts, $short));
            } else {
                $order->add_order_note(sprintf('Gelato refused the order (HTTP %d). Fix it and use the order action "Send posters to Gelato": %s', $code, $short));
            }
            self::log($orderId, 'error', $code . ' ' . $short);
        } finally {
            delete_option($lock);
        }
    }

    /** Worth another try: no answer, rate limit or a server error. Pure. */
    public static function temporary(int $code): bool {
        return $code === 0 || $code === 429 || $code >= 500;
    }

    private static function log(int $orderId, string $state, string $text): void {
        $log = (array) get_option(self::LOG, []);
        $log[] = ['at' => current_time('mysql'), 'order' => $orderId, 'state' => $state, 'text' => mb_substr($text, 0, 300)];
        update_option(self::LOG, array_slice($log, -30), false);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Gelato print orders', 'Gelato print orders', 'manage_woocommerce', self::PAGE, [__CLASS__, 'page']);
    }

    public static function save(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('sgap_gelato_save');
        $old = self::settings();
        $key = trim((string) wp_unslash($_POST['api_key'] ?? ''));
        update_option(self::OPTION, [
            'api_key' => $key !== '' ? $key : $old['api_key'],
            'mode' => sanitize_key((string) ($_POST['mode'] ?? 'off')),
            'shipment' => sanitize_key((string) ($_POST['shipment'] ?? 'normal')),
        ], false);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&saved=1'));
        exit;
    }

    public static function page(): void {
        $s = self::settings();
        $hint = $s['api_key'] !== '' ? '…' . substr($s['api_key'], -4) : 'not set';
        SGAP_Admin::open('Gelato print orders', 'Paid orders for the 19 Steamgears posters go straight to Gelato with their print file. Other products are left alone.');
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success"><p>Saved.</p></div>';
        }
        if (self::standalone_active()) {
            echo '<div class="notice notice-warning"><p>The stand-alone plugin <strong>Steamgears Gelato-videresendelse</strong> is still active, so it sends the orders and this page does nothing. Deactivate it under Plugins; the key and mode carry over.</p></div>';
        }
        SGAP_UI::card_open('Settings', 'admin-generic');
        echo '<p><strong>Do not connect the same 19 products in Gelato\'s own app</strong>, or they are printed twice.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sgap_gelato_save');
        echo '<input type="hidden" name="action" value="sgap_gelato_save">';
        echo '<table class="form-table"><tr><th>Gelato API key</th><td><input type="password" name="api_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr($hint) . '"><p class="description">Leave empty to keep the saved key (' . esc_html($hint) . ').</p></td></tr>';
        echo '<tr><th>Mode</th><td>';
        foreach (['off' => 'Off: nothing is sent', 'draft' => 'Draft: a draft order is created in Gelato and approved there', 'live' => 'Live: the order goes to print right away'] as $v => $label) {
            echo '<label style="display:block"><input type="radio" name="mode" value="' . esc_attr($v) . '"' . checked($s['mode'], $v, false) . '> ' . esc_html($label) . '</label>';
        }
        echo '</td></tr><tr><th>Shipping</th><td><select name="shipment"><option value="normal"' . selected($s['shipment'], 'normal', false) . '>Normal</option><option value="express"' . selected($s['shipment'], 'express', false) . '>Express</option></select></td></tr></table>';
        submit_button('Save');
        echo '</form>';
        SGAP_UI::card_close();

        SGAP_UI::card_open('Print files', 'media-document');
        echo '<table class="widefat striped"><thead><tr><th>SKU</th><th>30×40</th><th>50×70</th></tr></thead><tbody>';
        foreach (array_keys(self::FILES) as $base) {
            echo '<tr><td>' . esc_html($base) . '</td>';
            foreach (['3040', '5070'] as $size) {
                echo '<td>' . (file_exists(self::file_path($base, $size)) ? '✓' : '<strong>missing</strong>') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        SGAP_UI::card_close();

        SGAP_UI::card_open('Latest sends', 'backup');
        $log = array_reverse((array) get_option(self::LOG, []));
        if ($log === []) {
            echo '<p class="sgap-hint">Nothing sent yet.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>When</th><th>Order</th><th>State</th><th>Detail</th></tr></thead><tbody>';
            foreach ($log as $row) {
                echo '<tr><td>' . esc_html((string) ($row['at'] ?? '')) . '</td><td>#' . (int) ($row['order'] ?? 0) . '</td><td>' . esc_html((string) ($row['state'] ?? '')) . '</td><td>' . esc_html((string) ($row['text'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        SGAP_UI::card_close();
        SGAP_Admin::close();
    }
}
