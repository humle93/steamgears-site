<?php
/**
 * Steamgears Autopilot core. Loaded from steamgears-autopilot.php only.
 */

if (!defined('ABSPATH')) exit;

/* ---------------- INSTALL / UPGRADE ---------------- */
class SGAP_Install {
    /** Cron hooks from earlier versions. This build has no handlers for them. */
    const LEGACY_CRON = [
        'sgap_process_queue',
        'sgap_etsy_sync',
        'sgap_etsy_inventory',
        'sgap_weekly_report',
        'sgap_repost_check',
        'sgap_suppression_check'
    ];

    const CLEANUP_CRON = 'sgap_daily_cleanup';
    const PUBLISH_CRON = 'sgap_publish_tick';
    const GENERATE_CRON = 'sgap_generate';
    const QUARTER_HOUR = 'sgap_quarter_hour';

    const TABLES = [
        'sgap_queue', 'sgap_events', 'sgap_cro', 'sgap_learning',
        'sgap_ai_drafts', 'sgap_reposts', 'sgap_suppression', 'sgap_etsy_orders'
    ];

    const DEFAULTS = [
        'sgap_autopilot_enabled' => 'no',
        'sgap_full_auto_enabled' => 'no',
        'sgap_daily_cap' => 8,
        'sgap_batch_size' => 5,
        'sgap_quiet_start' => 23,
        'sgap_quiet_end' => 7,
        'sgap_labor_rate' => 250,
        'sgap_monthly_budget' => 1000,
        'sgap_target_roi' => 200,
        'sgap_winner_min_clicks' => 25,
        'sgap_loser_min_clicks' => 100,
        'sgap_repost_cooldown_days' => 30,
        'sgap_repost_max' => 3,
        'sgap_posts_per_day' => 2,
        'sgap_channels' => 'facebook,instagram,pinterest',
        'sgap_ai_provider' => 'anthropic'
    ];

    public static function activate(): void {
        self::install();
    }

    public static function deactivate(): void {
        self::clear_legacy_cron();
        foreach ([self::CLEANUP_CRON, self::PUBLISH_CRON, self::GENERATE_CRON] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        delete_option('rewrite_rules'); // drop the /go/ rule; WP rebuilds on next request
    }

    /**
     * Runs on every request (cron included, so an update uploaded over an older
     * version gets its new columns before the publisher runs); costs one
     * autoloaded option read when there is nothing to do.
     */
    public static function maybe_upgrade(): void {
        if (get_option('sgap_version') !== SGAP_VERSION) {
            self::install();
        }
    }

    /** Adds the 15-minute interval the publisher runs on. */
    public static function cron_schedules(array $schedules): array {
        $schedules[self::QUARTER_HOUR] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes (Steamgears)'];
        return $schedules;
    }

    public static function clear_legacy_cron(): void {
        foreach (self::LEGACY_CRON as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        channel VARCHAR(40) NOT NULL,
        angle VARCHAR(20) NOT NULL DEFAULT 'angle_a',
        content_type VARCHAR(40) NOT NULL DEFAULT 'product',
        format VARCHAR(40) NOT NULL DEFAULT 'pin',
        destination_url TEXT NOT NULL,
        title TEXT NOT NULL,
        body LONGTEXT NULL,
        image_url TEXT NULL,
        utm_url TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        scheduled_at DATETIME NULL,
        published_at DATETIME NULL,
        error TEXT NULL,
        fingerprint VARCHAR(64) NOT NULL DEFAULT '',
        external_id VARCHAR(64) NOT NULL DEFAULT '',
        sent_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY channel_status (channel,status),
        KEY post_channel (post_id,channel),
        KEY scheduled (scheduled_at),
        KEY fingerprint (fingerprint)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        channel VARCHAR(40) NOT NULL,
        angle VARCHAR(20) NOT NULL DEFAULT 'angle_a',
        event_type VARCHAR(30) NOT NULL,
        value DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY channel_event (channel,event_type),
        KEY queue_id (queue_id),
        KEY created_at (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_cro (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        angle VARCHAR(20) NOT NULL DEFAULT 'angle_a',
        click_id VARCHAR(64) NOT NULL DEFAULT '',
        order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
        clicked_at DATETIME NULL,
        converted_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY angle (angle),
        KEY queue_id (queue_id),
        KEY click_id (click_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_learning (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        dimension VARCHAR(40) NOT NULL,
        dimension_value VARCHAR(120) NOT NULL,
        clicks INT UNSIGNED NOT NULL DEFAULT 0,
        orders INT UNSIGNED NOT NULL DEFAULT 0,
        revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
        cost DECIMAL(14,2) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY dimension_value (dimension,dimension_value),
        KEY updated_at (updated_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_ai_drafts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        channel VARCHAR(40) NOT NULL,
        angle VARCHAR(20) NOT NULL DEFAULT 'angle_a',
        content_type VARCHAR(40) NOT NULL DEFAULT 'product',
        format VARCHAR(40) NOT NULL DEFAULT 'pin',
        title TEXT NOT NULL,
        body LONGTEXT NULL,
        alt_text TEXT NULL,
        hashtags TEXT NULL,
        etsy_title TEXT NULL,
        etsy_tags TEXT NULL,
        email_subject TEXT NULL,
        cta TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        ai_model VARCHAR(40) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY post_id (post_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_reposts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        original_queue_id BIGINT UNSIGNED NOT NULL,
        repost_queue_id BIGINT UNSIGNED NOT NULL,
        repost_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
        new_angle VARCHAR(20) NOT NULL,
        new_hook TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY original (original_queue_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_suppression (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        channel VARCHAR(40) NOT NULL,
        angle VARCHAR(20) NOT NULL,
        content_type VARCHAR(40) NOT NULL,
        format VARCHAR(40) NOT NULL,
        level VARCHAR(20) NOT NULL DEFAULT 'reduced',
        reason TEXT NULL,
        suppressed_at DATETIME NOT NULL,
        reactivate_at DATETIME NULL,
        protected TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY combo (channel,angle,content_type,format)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_etsy_orders (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        etsy_order_id BIGINT UNSIGNED NOT NULL,
        product_sku VARCHAR(80) NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 1,
        revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
        fees DECIMAL(12,2) NOT NULL DEFAULT 0,
        shipping DECIMAL(12,2) NOT NULL DEFAULT 0,
        profit DECIMAL(12,2) NOT NULL DEFAULT 0,
        country VARCHAR(10) NULL,
        imported_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY etsy_order (etsy_order_id)
        ) $charset;");

        foreach (self::DEFAULTS as $key => $value) {
            add_option($key, $value);
        }

        // Secrets are only needed by the REST API and admin, never on a
        // normal page view: keep them out of the autoloaded options blob.
        $secret_options = [];
        foreach (SGAP_Settings::SECRET_KEYS as $key) {
            add_option('sgap_' . $key, '', '', false);
            $secret_options['sgap_' . $key] = false;
        }
        if (function_exists('wp_set_option_autoload_values')) {
            wp_set_option_autoload_values($secret_options);
        }

        // Old versions scheduled these; nothing handles them now, so they
        // would only keep waking WP-Cron for no reason.
        self::clear_legacy_cron();
        foreach ([
            self::CLEANUP_CRON => ['daily', HOUR_IN_SECONDS],
            self::GENERATE_CRON => ['daily', 10 * MINUTE_IN_SECONDS],
            self::PUBLISH_CRON => [self::QUARTER_HOUR, 5 * MINUTE_IN_SECONDS]
        ] as $hook => [$recurrence, $delay]) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + $delay, $recurrence, $hook);
            }
        }

        SGAP_Tracking::add_rewrite_rule();
        flush_rewrite_rules(false);

        update_option('sgap_version', SGAP_VERSION);
    }
}
register_activation_hook(SGAP_FILE, ['SGAP_Install', 'activate']);
register_deactivation_hook(SGAP_FILE, ['SGAP_Install', 'deactivate']);

/* ---------------- SETTINGS ---------------- */
class SGAP_Settings {
    const BOOL_KEYS = ['autopilot_enabled', 'full_auto_enabled'];
    const HOUR_KEYS = ['quiet_start', 'quiet_end'];
    const INT_KEYS = [
        'daily_cap','batch_size','labor_rate','monthly_budget','target_roi',
        'winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max','posts_per_day'
    ];
    /** Never echoed back to the browser; can be overridden by SGAP_<KEY> constants in wp-config.php. */
    const SECRET_KEYS = [
        'publer_api_key','ai_api_key',
        'pinterest_token','instagram_token','telegram_bot_token','tiktok_token','youtube_token',
        'etsy_oauth_token','etsy_refresh_token','merchant_feed_secret'
    ];
    const TEXT_KEYS = [
        'publer_workspace_id','publer_pinterest_board','channels','ai_model','ai_base_url',
        'telegram_chat_id','email_test_recipient','youtube_channel_id','reddit_username',
        'reddit_subreddit','etsy_shop_id','etsy_shipping_profile_id','etsy_return_policy_id','ai_mode'
    ];
    /** Settings with a fixed list of values, shown as a select. */
    const CHOICE_KEYS = [
        'ai_provider' => ['anthropic' => 'Anthropic (Claude)', 'openai_compatible' => 'OpenAI-compatible (AIMLAPI, OpenAI, ...)']
    ];

    /** Shown in the settings page. Keys not listed here stay registered for older data but are unused. */
    const SECTIONS = [
        'General' => ['autopilot_enabled','full_auto_enabled','daily_cap','batch_size','quiet_start','quiet_end'],
        'Publer' => ['publer_api_key','publer_workspace_id','publer_pinterest_board','channels'],
        'AI content' => ['ai_provider','ai_api_key','ai_model','ai_base_url','posts_per_day'],
        'Economics' => ['labor_rate','monthly_budget','target_roi','winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max'],
        'Etsy' => ['etsy_shop_id','etsy_oauth_token','etsy_refresh_token','etsy_shipping_profile_id','etsy_return_policy_id'],
        'Google Feed' => ['merchant_feed_secret']
    ];

    const HELP = [
        'autopilot_enabled' => 'Master switch: generate posts daily and publish the queue via Publer.',
        'full_auto_enabled' => 'On: AI posts go straight to the queue. Off: they wait under AI Drafts for your approval.',
        'publer_api_key' => 'Publer → Settings → Access & Login → API Keys (Business plan). Scopes: posts, media, accounts.',
        'publer_workspace_id' => 'Shown by "Test Publer" on the dashboard once the API key is saved.',
        'publer_pinterest_board' => 'Pinterest board ID the pins go to (required for Pinterest).',
        'channels' => 'Comma-separated. Supported: ' . 'facebook, instagram, pinterest, linkedin, twitter, threads, bluesky, mastodon, telegram, google',
        'ai_model' => 'Empty = claude-opus-5-5 for Anthropic. Required for OpenAI-compatible.',
        'ai_base_url' => 'OpenAI-compatible only. Empty = https://api.aimlapi.com/v1',
        'posts_per_day' => 'How many site posts/products the AI writes social posts for each day.'
    ];

    public static function get(string $key, $default = '') {
        if (in_array($key, self::SECRET_KEYS, true) && self::constant_name($key)) {
            return constant(self::constant_name($key));
        }
        return get_option('sgap_' . $key, $default);
    }
    public static function set(string $key, $value): void {
        update_option('sgap_' . $key, $value);
    }
    public static function enabled(string $key): bool {
        return self::get($key, 'no') === 'yes';
    }
    /** Name of the wp-config.php constant overriding a secret, or '' when not defined. */
    public static function constant_name(string $key): string {
        $name = 'SGAP_' . strtoupper($key);
        return defined($name) ? $name : '';
    }
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register']);
    }
    public static function register(): void {
        foreach (self::BOOL_KEYS as $key) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'string',
                'sanitize_callback' => fn($v) => $v === 'yes' ? 'yes' : 'no'
            ]);
        }
        foreach (self::HOUR_KEYS as $key) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'integer',
                'sanitize_callback' => fn($v) => min(23, absint($v))
            ]);
        }
        foreach (self::INT_KEYS as $key) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]);
        }
        foreach (self::TEXT_KEYS as $key) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]);
        }
        foreach (self::CHOICE_KEYS as $key => $choices) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'string',
                'sanitize_callback' => fn($v) => array_key_exists((string) $v, $choices) ? (string) $v : (string) array_key_first($choices)
            ]);
        }
        foreach (self::SECRET_KEYS as $key) {
            register_setting('sgap_settings', 'sgap_' . $key, [
                'type' => 'string',
                // The form never shows the stored secret, so an empty field
                // means "keep what is saved" unless "clear" was ticked.
                'sanitize_callback' => function ($value) use ($key) {
                    if (!empty($_POST['sgap_clear'][$key])) {
                        return '';
                    }
                    $value = sanitize_text_field((string) $value);
                    return $value === '' ? (string) get_option('sgap_' . $key, '') : $value;
                }
            ]);
        }
    }
}

/* ---------------- UTM ---------------- */
class SGAP_UTM {
    public static function build(array $args): string {
        $args = wp_parse_args($args, [
            'channel' => '',
            'angle' => 'angle_a',
            'content_type' => 'product',
            'format' => 'pin',
            'destination_url' => home_url('/')
        ]);
        return add_query_arg(array_map('rawurlencode', [
            'utm_source' => $args['channel'],
            'utm_medium' => 'social',
            'utm_campaign' => 'steamgears_premium_evergreen',
            'utm_content' => $args['angle'] . '_' . $args['content_type'] . '_' . $args['format']
        ]), $args['destination_url']);
    }
}

/* ---------------- LEARNING ---------------- */
class SGAP_Learning {
    /** Single atomic upsert (was SELECT + UPDATE/INSERT, which raced on the unique key). */
    public static function bump(string $dimension, string $value, string $event, float $amount = 0): void {
        global $wpdb;
        $clicks = $event === 'click' ? 1 : 0;
        $orders = $event === 'order' ? 1 : 0;
        $revenue = $event === 'order' ? $amount : 0.0;
        if (!$clicks && !$orders) {
            return; // nothing to count
        }
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}sgap_learning (dimension, dimension_value, clicks, orders, revenue, cost, updated_at)
             VALUES (%s, %s, %d, %d, %f, 0, %s)
             ON DUPLICATE KEY UPDATE clicks = clicks + VALUES(clicks), orders = orders + VALUES(orders),
             revenue = revenue + VALUES(revenue), updated_at = VALUES(updated_at)",
            substr($dimension, 0, 40), substr($value, 0, 120), $clicks, $orders, $revenue, current_time('mysql')
        ));
    }
}

/* ---------------- ROI ---------------- */
class SGAP_ROI {
    public static function record_event(int $queue_id, string $channel, string $angle, string $type, float $value = 0): void {
        global $wpdb;
        $channel = sanitize_key($channel);
        $angle = sanitize_key($angle);
        $type = sanitize_key($type);
        $wpdb->insert($wpdb->prefix . 'sgap_events', [
            'queue_id' => $queue_id,
            'channel' => $channel,
            'angle' => $angle,
            'event_type' => $type,
            'value' => $value,
            'created_at' => current_time('mysql')
        ]);
        SGAP_Learning::bump('channel', $channel, $type, $value);
        SGAP_Learning::bump('angle', $angle, $type, $value);
    }
    public static function roi(float $revenue, float $cost): float {
        return $cost > 0 ? round((($revenue - $cost) / $cost) * 100, 2) : 0.0;
    }
    public static function roas(float $revenue, float $ad_spend): float {
        return $ad_spend > 0 ? round($revenue / $ad_spend, 2) : 0.0;
    }
}

/* ---------------- CLICK TRACKING ---------------- */
class SGAP_Tracking {
    const COOKIE = 'sgap_click';
    const QUERY_VAR = 'sgap_go';

    public static function init(): void {
        add_action('init', [__CLASS__, 'add_rewrite_rule']);
        add_filter('query_vars', fn(array $vars) => array_merge($vars, [self::QUERY_VAR]));
        add_action('template_redirect', [__CLASS__, 'redirect'], 1);

        // Order attribution; these hooks only ever fire when WooCommerce is active.
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'tag_order']);
        add_action('woocommerce_store_api_checkout_update_order_meta', [__CLASS__, 'tag_order']);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'attribute_order'], 10, 4);
    }

    public static function add_rewrite_rule(): void {
        add_rewrite_rule('^go/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function url(int $queue_id): string {
        return home_url('/go/' . $queue_id . '/');
    }

    public static function is_bot(string $ua): bool {
        return $ua === '' || (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|whatsapp|curl|wget|python-requests|headless/i',
            $ua
        );
    }

    /** Only redirect to our own site or hosts we explicitly sell on. */
    public static function safe_target(string $url): string {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $allowed = apply_filters('sgap_redirect_hosts', [
            strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)),
            'etsy.com',
            'www.etsy.com'
        ]);
        return $host !== '' && in_array($host, $allowed, true) ? $url : home_url('/');
    }

    public static function redirect(): void {
        $id = absint(get_query_var(self::QUERY_VAR));
        if (!$id) {
            return;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, channel, angle, destination_url, utm_url FROM {$wpdb->prefix}sgap_queue WHERE id = %d",
            $id
        ));
        nocache_headers();
        if (!$row) {
            wp_redirect(home_url('/'), 302, 'Steamgears');
            exit;
        }

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        if (!self::is_bot($ua)) {
            $click_id = wp_generate_password(20, false);
            $wpdb->insert($wpdb->prefix . 'sgap_cro', [
                'queue_id' => (int) $row->id,
                'angle' => $row->angle,
                'click_id' => $click_id,
                'clicked_at' => current_time('mysql')
            ]);
            SGAP_ROI::record_event((int) $row->id, $row->channel, $row->angle, 'click');
            if (!headers_sent()) {
                setcookie(self::COOKIE, $click_id, [
                    'expires' => time() + 30 * DAY_IN_SECONDS,
                    'path' => COOKIEPATH ?: '/',
                    'domain' => COOKIE_DOMAIN ?: '',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }

        wp_redirect(self::safe_target($row->utm_url ?: $row->destination_url), 302, 'Steamgears');
        exit;
    }

    private static function cookie_click_id(): string {
        $value = isset($_COOKIE[self::COOKIE]) ? (string) $_COOKIE[self::COOKIE] : '';
        return preg_match('/^[A-Za-z0-9]{20}$/', $value) ? $value : '';
    }

    /** @param WC_Order $order */
    public static function tag_order($order): void {
        $click_id = self::cookie_click_id();
        if ($click_id && is_object($order)) {
            $order->update_meta_data('_sgap_click_id', $click_id);
        }
    }

    /** Credit the click once, when the order is actually paid. */
    public static function attribute_order($order_id, $from, $to, $order = null): void {
        if (!in_array($to, ['processing', 'completed'], true)) {
            return;
        }
        $order = $order ?: (function_exists('wc_get_order') ? wc_get_order($order_id) : null);
        if (!$order || $order->get_meta('_sgap_attributed')) {
            return;
        }
        $click_id = (string) $order->get_meta('_sgap_click_id');
        if ($click_id === '') {
            return;
        }
        global $wpdb;
        $click = $wpdb->get_row($wpdb->prepare(
            "SELECT c.id, c.queue_id, c.angle, q.channel FROM {$wpdb->prefix}sgap_cro c
             LEFT JOIN {$wpdb->prefix}sgap_queue q ON q.id = c.queue_id
             WHERE c.click_id = %s ORDER BY c.id DESC LIMIT 1",
            $click_id
        ));
        if (!$click) {
            return;
        }
        $revenue = (float) $order->get_total();
        $wpdb->update($wpdb->prefix . 'sgap_cro', [
            'order_id' => (int) $order->get_id(),
            'revenue' => $revenue,
            'converted_at' => current_time('mysql')
        ], ['id' => (int) $click->id]);
        SGAP_ROI::record_event((int) $click->queue_id, (string) $click->channel, (string) $click->angle, 'order', $revenue);
        $order->update_meta_data('_sgap_attributed', 1);
        $order->save();
    }
}

/* ---------------- QUEUE ---------------- */
class SGAP_Queue {
    const MAX_ATTEMPTS = 3;

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sgap_queue';
    }

    private static function key($value, string $default, int $max): string {
        $value = sanitize_key((string) ($value ?? ''));
        return $value === '' ? $default : substr($value, 0, $max);
    }

    public static function in_quiet_hours(int $hour, int $start, int $end): bool {
        if ($start === $end) {
            return false;
        }
        return $start > $end ? ($hour >= $start || $hour < $end) : ($hour >= $start && $hour < $end);
    }

    /**
     * Adds a post to the queue. Returns ['id', 'tracking_url', 'utm_url'], or a
     * WP_Error (400 invalid, 409 when the same channel + URL + title is queued).
     */
    public static function add(array $item) {
        global $wpdb;
        $channel = self::key($item['channel'] ?? '', '', 40);
        $title = sanitize_text_field((string) ($item['title'] ?? ''));
        $destination = esc_url_raw((string) ($item['destination_url'] ?? ''), ['http', 'https']);
        if ($channel === '' || $title === '' || $destination === '') {
            return new WP_Error('sgap_invalid', 'channel, title and destination_url are required.', ['status' => 400]);
        }
        $fingerprint = hash('sha256', $channel . '|' . $destination . '|' . $title);
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . " WHERE fingerprint = %s AND status <> 'failed' LIMIT 1",
            $fingerprint
        ));
        if ($existing) {
            return new WP_Error('sgap_duplicate', 'Already queued.', ['status' => 409, 'id' => (int) $existing]);
        }
        $row = [
            'post_id' => absint($item['post_id'] ?? 0),
            'channel' => $channel,
            'angle' => self::key($item['angle'] ?? '', 'angle_a', 20),
            'content_type' => self::key($item['content_type'] ?? '', 'product', 40),
            'format' => self::key($item['format'] ?? '', 'pin', 40),
            'destination_url' => $destination,
            'title' => $title,
            'body' => sanitize_textarea_field((string) ($item['body'] ?? '')),
            'image_url' => esc_url_raw((string) ($item['image_url'] ?? '')),
            'status' => ($item['status'] ?? '') === 'draft' ? 'draft' : 'queued',
            'scheduled_at' => ($item['scheduled_at'] ?? null) ?: current_time('mysql'),
            'fingerprint' => $fingerprint
        ];
        $row['utm_url'] = SGAP_UTM::build($row);
        if (!$wpdb->insert(self::table(), $row)) {
            return new WP_Error('sgap_db', 'Could not save.', ['status' => 500]);
        }
        $id = (int) $wpdb->insert_id;
        delete_transient('sgap_dashboard_stats');
        return ['id' => $id, 'tracking_url' => SGAP_Tracking::url($id), 'utm_url' => $row['utm_url']];
    }

    /** Why nothing may be sent right now, or '' when sending is allowed. */
    public static function blocked_reason(): string {
        if (!SGAP_Settings::enabled('autopilot_enabled')) {
            return 'autopilot_disabled';
        }
        if (self::in_quiet_hours((int) wp_date('G'), (int) SGAP_Settings::get('quiet_start', 23), (int) SGAP_Settings::get('quiet_end', 7))) {
            return 'quiet_hours';
        }
        return self::capacity() <= 0 ? 'daily_cap_reached' : '';
    }

    /** How many posts may go out now: the batch size, capped by what is left of today's limit. */
    public static function capacity(): int {
        global $wpdb;
        $in_flight_or_published = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . " WHERE published_at >= %s OR (status = 'sending' AND sent_at >= %s)",
            wp_date('Y-m-d 00:00:00'), wp_date('Y-m-d 00:00:00')
        ));
        return max(0, min((int) SGAP_Settings::get('batch_size', 5), (int) SGAP_Settings::get('daily_cap', 8) - $in_flight_or_published));
    }

    /** Takes the next due items and marks them 'sending' so nobody else picks them up. */
    public static function claim(int $limit, string $channel = ''): array {
        global $wpdb;
        if ($limit <= 0) {
            return [];
        }
        $where = $channel !== '' ? $wpdb->prepare(' AND channel = %s', $channel) : '';
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT id, post_id, channel, angle, content_type, format, destination_url, title, body, image_url, utm_url, attempts, scheduled_at
             FROM ' . self::table() . " WHERE status = 'queued' AND (scheduled_at IS NULL OR scheduled_at <= %s){$where}
             ORDER BY scheduled_at ASC, id ASC LIMIT %d",
            current_time('mysql'), $limit
        ), ARRAY_A);
        if ($items) {
            $ids = implode(',', array_map('intval', array_column($items, 'id')));
            $wpdb->query('UPDATE ' . self::table() . " SET status = 'sending', attempts = attempts + 1 WHERE id IN ({$ids})");
        }
        return $items;
    }

    /**
     * Records the outcome of a send. A failure goes back in the queue until it has
     * been tried MAX_ATTEMPTS times. Returns the new status, or null for an unknown id.
     */
    public static function mark(int $id, string $outcome, string $error = ''): ?string {
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare('SELECT channel, angle, attempts FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$item) {
            return null;
        }
        if ($outcome === 'published') {
            $data = ['status' => 'published', 'published_at' => current_time('mysql'), 'error' => null];
        } else {
            $data = [
                'status' => (int) $item->attempts < self::MAX_ATTEMPTS ? 'queued' : 'failed',
                'error' => sanitize_textarea_field($error),
                'external_id' => ''
            ];
        }
        $wpdb->update(self::table(), $data, ['id' => $id]);
        if ($data['status'] === 'published') {
            SGAP_ROI::record_event($id, $item->channel, $item->angle, 'publish');
        }
        delete_transient('sgap_dashboard_stats');
        return $data['status'];
    }

    /** Turns an approved AI draft into a queue item for its source post, and marks the draft used. */
    public static function from_draft(int $draft_id) {
        global $wpdb;
        $draft = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sgap_ai_drafts WHERE id = %d", $draft_id));
        if (!$draft) {
            return new WP_Error('sgap_not_found', 'Unknown draft.', ['status' => 404]);
        }
        $post_id = (int) $draft->post_id;
        $body = trim((string) $draft->body . ($draft->hashtags ? "\n\n" . $draft->hashtags : ''));
        $result = self::add([
            'post_id' => $post_id,
            'channel' => $draft->channel,
            'angle' => $draft->angle,
            'content_type' => $draft->content_type,
            'format' => $draft->format,
            'destination_url' => $post_id ? get_permalink($post_id) : home_url('/'),
            'title' => $draft->title,
            'body' => $body,
            'image_url' => $post_id ? (string) get_the_post_thumbnail_url($post_id, 'large') : ''
        ]);
        if (!is_wp_error($result)) {
            $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => 'used'], ['id' => $draft_id]);
        }
        return $result;
    }
}

/* ---------------- REST API (for external tools) ---------------- */
class SGAP_REST {
    const NS = 'sgap/v1';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    public static function routes(): void {
        $auth = [__CLASS__, 'can_manage'];
        register_rest_route(self::NS, '/queue', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'create_queue'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/queue/due', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'due'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/queue/(?P<id>\d+)/status', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'queue_status'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/drafts', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_draft'], 'permission_callback' => $auth],
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_drafts'], 'permission_callback' => $auth]
        ]);
        register_rest_route(self::NS, '/drafts/(?P<id>\d+)/status', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'draft_status'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/stats', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'stats'], 'permission_callback' => $auth
        ]);
    }

    private static function key(WP_REST_Request $r, string $name, string $default, int $max): string {
        $value = sanitize_key((string) ($r->get_param($name) ?? ''));
        return $value === '' ? $default : substr($value, 0, $max);
    }

    private static function text($value): string {
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }
        return sanitize_textarea_field((string) ($value ?? ''));
    }

    private static function datetime($value): ?string {
        if (!$value) {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts ? wp_date('Y-m-d H:i:s', $ts) : null;
    }

    public static function create_queue(WP_REST_Request $r) {
        $result = SGAP_Queue::add([
            'post_id' => $r->get_param('post_id'),
            'channel' => $r->get_param('channel'),
            'angle' => $r->get_param('angle'),
            'content_type' => $r->get_param('content_type'),
            'format' => $r->get_param('format'),
            'destination_url' => $r->get_param('destination_url'),
            'title' => $r->get_param('title'),
            'body' => self::text($r->get_param('body')),
            'image_url' => $r->get_param('image_url'),
            'status' => $r->get_param('status'),
            'scheduled_at' => self::datetime($r->get_param('scheduled_at'))
        ]);
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response($result, 201);
    }

    /** Hands out the next items to publish, honouring on/off, quiet hours, daily cap and batch size. */
    public static function due(WP_REST_Request $r) {
        $reason = SGAP_Queue::blocked_reason();
        if ($reason !== '') {
            return ['items' => [], 'reason' => $reason];
        }
        $limit = SGAP_Queue::capacity();
        $items = SGAP_Queue::claim($limit, sanitize_key((string) $r->get_param('channel')));
        foreach ($items as &$item) {
            $item['tracking_url'] = SGAP_Tracking::url((int) $item['id']);
        }
        unset($item);
        return ['items' => $items, 'remaining_today' => max(0, $limit - count($items))];
    }

    public static function queue_status(WP_REST_Request $r) {
        $status = (string) $r->get_param('status');
        if (!in_array($status, ['published', 'failed'], true)) {
            return new WP_Error('sgap_invalid', 'status must be published or failed.', ['status' => 400]);
        }
        $id = absint($r['id']);
        $new = SGAP_Queue::mark($id, $status, (string) $r->get_param('error'));
        if ($new === null) {
            return new WP_Error('sgap_not_found', 'Unknown queue id.', ['status' => 404]);
        }
        return ['id' => $id, 'status' => $new];
    }

    public static function create_draft(WP_REST_Request $r) {
        global $wpdb;
        $channel = self::key($r, 'channel', '', 40);
        $title = sanitize_text_field((string) $r->get_param('title'));
        if ($channel === '' || $title === '') {
            return new WP_Error('sgap_invalid', 'channel and title are required.', ['status' => 400]);
        }
        $row = [
            'post_id' => absint($r->get_param('post_id')),
            'channel' => $channel,
            'angle' => self::key($r, 'angle', 'angle_a', 20),
            'content_type' => self::key($r, 'content_type', 'product', 40),
            'format' => self::key($r, 'format', 'pin', 40),
            'title' => $title,
            'status' => 'pending',
            'ai_model' => substr(sanitize_text_field((string) $r->get_param('ai_model')), 0, 40),
            'created_at' => current_time('mysql')
        ];
        foreach (['body', 'alt_text', 'hashtags', 'etsy_title', 'etsy_tags', 'email_subject', 'cta'] as $field) {
            $row[$field] = self::text($r->get_param($field));
        }
        if (!$wpdb->insert($wpdb->prefix . 'sgap_ai_drafts', $row)) {
            return new WP_Error('sgap_db', 'Could not save.', ['status' => 500]);
        }
        delete_transient('sgap_dashboard_stats');
        return new WP_REST_Response(['id' => (int) $wpdb->insert_id], 201);
    }

    public static function list_drafts(WP_REST_Request $r) {
        global $wpdb;
        $status = sanitize_key((string) ($r->get_param('status') ?: 'approved'));
        $limit = min(100, max(1, absint($r->get_param('limit') ?: 50)));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = %s ORDER BY id ASC LIMIT %d",
            $status, $limit
        ), ARRAY_A);
    }

    public static function draft_status(WP_REST_Request $r) {
        global $wpdb;
        $status = (string) $r->get_param('status');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'used'], true)) {
            return new WP_Error('sgap_invalid', 'status must be pending, approved, rejected or used.', ['status' => 400]);
        }
        $updated = $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => $status], ['id' => absint($r['id'])]);
        if ($updated === false) {
            return new WP_Error('sgap_db', 'Could not save.', ['status' => 500]);
        }
        delete_transient('sgap_dashboard_stats');
        return ['id' => absint($r['id']), 'status' => $status];
    }

    public static function stats(WP_REST_Request $r) {
        global $wpdb;
        $days = min(365, max(1, absint($r->get_param('days') ?: 30)));
        $since = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        return [
            'days' => $days,
            'events' => $wpdb->get_results($wpdb->prepare(
                "SELECT channel, event_type, COUNT(*) AS count, SUM(value) AS value
                 FROM {$wpdb->prefix}sgap_events WHERE created_at >= %s GROUP BY channel, event_type",
                $since
            ), ARRAY_A),
            'learning' => $wpdb->get_results(
                "SELECT dimension, dimension_value, clicks, orders, revenue, cost FROM {$wpdb->prefix}sgap_learning
                 ORDER BY revenue DESC, clicks DESC LIMIT 100",
                ARRAY_A
            ),
            'queue' => $wpdb->get_results(
                "SELECT status, COUNT(*) AS count FROM {$wpdb->prefix}sgap_queue GROUP BY status",
                ARRAY_A
            )
        ];
    }
}

/* ---------------- MAINTENANCE ---------------- */
class SGAP_Maintenance {
    public static function init(): void {
        add_action(SGAP_Install::CLEANUP_CRON, [__CLASS__, 'daily']);
    }

    public static function daily(): void {
        global $wpdb;
        // Items claimed by /queue/due but never reported back go back in line.
        // Items handed to Publer (external_id set) are followed up by SGAP_Publer instead.
        $wpdb->query("UPDATE {$wpdb->prefix}sgap_queue SET status = 'queued' WHERE status = 'sending' AND external_id = ''");

        $days = (int) apply_filters('sgap_event_retention_days', 180);
        if ($days < 1) {
            return;
        }
        $cutoff = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        // Totals live on in sgap_learning; raw rows are deleted in small
        // batches so a large backlog never locks the tables for long.
        foreach ([
            "DELETE FROM {$wpdb->prefix}sgap_events WHERE created_at < %s LIMIT 5000",
            "DELETE FROM {$wpdb->prefix}sgap_cro WHERE order_id = 0 AND clicked_at < %s LIMIT 5000"
        ] as $sql) {
            for ($i = 0; $i < 20; $i++) {
                if ((int) $wpdb->query($wpdb->prepare($sql, $cutoff)) < 5000) {
                    break;
                }
            }
        }
    }
}

/* ---------------- ADMIN ---------------- */
class SGAP_Admin {
    const PAGES = [
        ['Settings', 'sgap-settings', 'settings'],
        ['Queue', 'sgap-queue', 'queue'],
        ['AI Drafts', 'sgap-ai', 'ai'],
        ['Campaigns', 'sgap-campaigns', 'campaigns'],
        ['Etsy', 'sgap-etsy', 'etsy'],
        ['Reports', 'sgap-reports', 'reports'],
        ['Full Auto', 'sgap-fullauto', 'fullauto'],
        ['Google Feed', 'sgap-merchant', 'merchant']
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sgap_approve_ai', [__CLASS__, 'approve_ai']);
        add_action('admin_post_sgap_run', [__CLASS__, 'run_action']);
    }

    public static function menu(): void {
        add_menu_page(
            'Steamgears Autopilot',
            'Steamgears',
            'manage_options',
            'steamgears-autopilot',
            [__CLASS__, 'dashboard'],
            'dashicons-superhero',
            58
        );
        foreach (self::PAGES as [$title, $slug, $method]) {
            add_submenu_page(
                'steamgears-autopilot',
                $title,
                $title,
                'manage_options',
                $slug,
                [__CLASS__, $method]
            );
        }
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    private static function open(string $title): void {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
    }

    private static function close(): void {
        echo '</div>';
    }

    public static function dashboard(): void {
        global $wpdb;
        self::open('Steamgears Autopilot');
        $stats = get_transient('sgap_dashboard_stats');
        if (!is_array($stats) || !isset($stats['counts'])) {
            $stats = ['counts' => [], 'channels' => []];
            foreach (['sgap_queue' => 'Queue', 'sgap_ai_drafts' => 'AI drafts', 'sgap_events' => 'Events'] as $t => $label) {
                $table = $wpdb->prefix . $t;
                $stats['counts'][$label] = self::table_exists($table)
                    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}")
                    : null;
            }
            if ($stats['counts']['Events'] !== null) {
                $stats['channels'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT channel,
                        SUM(event_type = 'click') AS clicks,
                        SUM(event_type = 'order') AS orders,
                        SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue
                     FROM {$wpdb->prefix}sgap_events WHERE created_at >= %s
                     GROUP BY channel ORDER BY revenue DESC, clicks DESC",
                    wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
                ));
            }
            set_transient('sgap_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS);
        }
        echo '<p>Version ' . esc_html(SGAP_VERSION) . ' &middot; Autopilot: <strong>'
            . esc_html(SGAP_Settings::get('autopilot_enabled', 'no')) . '</strong>'
            . ' &middot; REST: <code>' . esc_html(rest_url(SGAP_REST::NS)) . '</code></p>';
        echo '<table class="widefat striped" style="max-width:420px"><tbody>';
        foreach ($stats['counts'] as $label => $count) {
            echo '<tr><td>' . esc_html($label) . '</td><td>'
                . ($count === null ? '<em>table missing &ndash; deactivate and reactivate the plugin</em>' : esc_html((string) $count))
                . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>Last 30 days per channel</h2>';
        self::render_rows($stats['channels'], ['channel', 'clicks', 'orders', 'revenue']);
        self::run_panel();
        self::close();
    }

    private static function run_panel(): void {
        $result = get_transient('sgap_run_result_' . get_current_user_id());
        if ($result) {
            delete_transient('sgap_run_result_' . get_current_user_id());
            echo '<div class="notice notice-info"><pre style="white-space:pre-wrap">' . esc_html($result) . '</pre></div>';
        }
        echo '<h2>Run now</h2><p>';
        foreach (['generate' => 'Generate posts now', 'publish' => 'Publish queue now', 'test_publer' => 'Test Publer'] as $task => $label) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px">'
                . '<input type="hidden" name="action" value="sgap_run"><input type="hidden" name="task" value="' . esc_attr($task) . '">'
                . wp_nonce_field('sgap_run_' . $task, '_wpnonce', true, false)
                . '<button class="button">' . esc_html($label) . '</button></form>';
        }
        echo '</p>';
        $last = get_option('sgap_last_generate');
        if (is_array($last)) {
            echo '<p class="description">Last generation: ' . esc_html($last['at'] . ' – ' . $last['drafts'] . ' drafts, ' . $last['queued'] . ' queued, ' . count($last['errors']) . ' errors') . '</p>';
        }
    }

    public static function run_action(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        $task = isset($_POST['task']) ? sanitize_key($_POST['task']) : '';
        check_admin_referer('sgap_run_' . $task);
        if ($task === 'generate') {
            $r = SGAP_Generator::run();
            $out = "Generated from {$r['sources']} pages: {$r['drafts']} drafts, {$r['queued']} queued."
                . ($r['errors'] ? "\nErrors:\n- " . implode("\n- ", $r['errors']) : '');
        } elseif ($task === 'publish') {
            if (!SGAP_Publer::configured()) {
                $out = 'Save the Publer API key and workspace id first.';
            } else {
                $reason = SGAP_Queue::blocked_reason();
                SGAP_Publer::tick();
                $out = $reason === '' ? 'Publisher ran. See the Queue page for results.' : "Followed up on sent posts; nothing new sent ({$reason}).";
            }
        } elseif ($task === 'test_publer') {
            $out = self::publer_report();
        } else {
            $out = 'Unknown action.';
        }
        set_transient('sgap_run_result_' . get_current_user_id(), $out, MINUTE_IN_SECONDS * 5);
        wp_safe_redirect(admin_url('admin.php?page=steamgears-autopilot'));
        exit;
    }

    private static function publer_report(): string {
        if (SGAP_Settings::get('publer_api_key') === '') {
            return 'Save the Publer API key in Settings first.';
        }
        $lines = [];
        $workspaces = SGAP_Publer::workspaces();
        if (is_wp_error($workspaces)) {
            return 'Publer: ' . $workspaces->get_error_message();
        }
        $lines[] = 'Workspaces (put the id in Settings → Publer workspace id):';
        foreach ($workspaces as $workspace) {
            if (is_array($workspace)) {
                $lines[] = '  ' . ($workspace['id'] ?? '?') . '  ' . ($workspace['name'] ?? '');
            }
        }
        if (SGAP_Settings::get('publer_workspace_id') !== '') {
            $accounts = SGAP_Publer::accounts(true);
            if (is_wp_error($accounts)) {
                $lines[] = 'Accounts: ' . $accounts->get_error_message();
            } else {
                $lines[] = 'Connected accounts:';
                foreach ($accounts as $account) {
                    $lines[] = "  {$account['provider']}: {$account['name']} ({$account['id']})";
                }
                $missing = array_diff(SGAP_Publer::channels(), array_column($accounts, 'provider'));
                if ($missing) {
                    $lines[] = 'Channels without a Publer account: ' . implode(', ', $missing);
                }
            }
        }
        return implode("\n", $lines);
    }

    public static function settings(): void {
        self::open('Settings');
        echo '<form method="post" action="options.php">';
        settings_fields('sgap_settings');
        foreach (SGAP_Settings::SECTIONS as $section => $keys) {
            echo '<h2>' . esc_html($section) . '</h2><table class="form-table"><tbody>';
            foreach ($keys as $key) {
                echo '<tr><th scope="row"><label for="sgap_' . esc_attr($key) . '">' . esc_html(ucfirst(str_replace('_', ' ', $key))) . '</label></th><td>';
                self::field($key);
                if (isset(SGAP_Settings::HELP[$key])) {
                    echo '<p class="description">' . esc_html(SGAP_Settings::HELP[$key]) . '</p>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        submit_button();
        echo '</form>';
        self::close();
    }

    private static function field(string $key): void {
        $id = 'sgap_' . $key;
        if (in_array($key, SGAP_Settings::BOOL_KEYS, true)) {
            $on = SGAP_Settings::enabled($key);
            printf(
                '<select id="%1$s" name="%1$s"><option value="no"%2$s>Off</option><option value="yes"%3$s>On</option></select>',
                esc_attr($id), selected(!$on, true, false), selected($on, true, false)
            );
            return;
        }
        if (isset(SGAP_Settings::CHOICE_KEYS[$key])) {
            $current = (string) SGAP_Settings::get($key);
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($id) . '">';
            foreach (SGAP_Settings::CHOICE_KEYS[$key] as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            return;
        }
        if (in_array($key, SGAP_Settings::SECRET_KEYS, true)) {
            if ($const = SGAP_Settings::constant_name($key)) {
                echo '<em>Set in wp-config.php (<code>' . esc_html($const) . '</code>)</em>';
                return;
            }
            $saved = get_option($id, '') !== '';
            printf(
                '<input type="password" id="%1$s" name="%1$s" value="" class="regular-text" autocomplete="new-password" placeholder="%2$s">',
                esc_attr($id), esc_attr($saved ? 'Saved ✓ – leave empty to keep' : '')
            );
            if ($saved) {
                printf(' <label><input type="checkbox" name="sgap_clear[%s]" value="1"> Clear</label>', esc_attr($key));
            }
            return;
        }
        $number = in_array($key, SGAP_Settings::INT_KEYS, true) || in_array($key, SGAP_Settings::HOUR_KEYS, true);
        printf(
            '<input type="%2$s" id="%1$s" name="%1$s" value="%3$s" class="%4$s"%5$s>',
            esc_attr($id),
            $number ? 'number' : 'text',
            esc_attr((string) SGAP_Settings::get($key)),
            $number ? 'small-text' : 'regular-text',
            in_array($key, SGAP_Settings::HOUR_KEYS, true) ? ' min="0" max="23"' : ($number ? ' min="0"' : '')
        );
    }

    public static function queue(): void {
        global $wpdb;
        self::open('Queue');
        $table = $wpdb->prefix . 'sgap_queue';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT id, channel, title, status, attempts, scheduled_at, published_at, error FROM {$table} ORDER BY id DESC LIMIT 50")
            : [];
        self::render_rows($rows, ['id', 'channel', 'title', 'status', 'attempts', 'scheduled_at', 'published_at', 'error']);
        self::close();
    }

    public static function ai(): void {
        global $wpdb;
        self::open('AI Drafts');
        if (isset($_GET['done'])) {
            echo '<div class="notice notice-info"><p>Draft ' . esc_html(sanitize_text_field(wp_unslash($_GET['done']))) . '.</p></div>';
        }
        $table = $wpdb->prefix . 'sgap_ai_drafts';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT id, channel, title, body, status, created_at FROM {$table} WHERE status = 'pending' ORDER BY id DESC LIMIT 50")
            : [];
        if (!$rows) {
            echo '<p>No pending drafts.</p>';
            self::close();
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Channel</th><th>Title</th><th>Text</th><th>Created</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($row->channel) . '</td><td>'
                . esc_html($row->title) . '</td><td>' . esc_html(wp_trim_words((string) $row->body, 30)) . '</td><td>'
                . esc_html($row->created_at) . '</td><td>'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="sgap_approve_ai">'
                . '<input type="hidden" name="draft_id" value="' . esc_attr($row->id) . '">'
                . wp_nonce_field('sgap_approve_ai_' . $row->id, '_wpnonce', true, false)
                . '<button class="button button-primary" name="decision" value="approved">Approve</button> '
                . '<button class="button" name="decision" value="rejected">Reject</button></form></td></tr>';
        }
        echo '</tbody></table>';
        self::close();
    }

    public static function approve_ai(): void {
        global $wpdb;
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        $id = isset($_POST['draft_id']) ? absint($_POST['draft_id']) : 0;
        check_admin_referer('sgap_approve_ai_' . $id);
        $decision = (isset($_POST['decision']) && $_POST['decision'] === 'rejected') ? 'rejected' : 'approved';
        $done = $decision;
        if ($id && $decision === 'approved') {
            // An approved draft goes straight into the publishing queue.
            $queued = SGAP_Queue::from_draft($id);
            $done = is_wp_error($queued) ? 'error: ' . $queued->get_error_message() : 'approved and queued';
        } elseif ($id) {
            $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => 'rejected'], ['id' => $id]);
        }
        delete_transient('sgap_dashboard_stats');
        wp_safe_redirect(add_query_arg(['page' => 'sgap-ai', 'done' => rawurlencode($done)], admin_url('admin.php')));
        exit;
    }

    public static function campaigns(): void { self::placeholder('Campaigns'); }
    public static function etsy(): void { self::placeholder('Etsy'); }
    public static function reports(): void { self::placeholder('Reports'); }
    public static function fullauto(): void { self::placeholder('Full Auto'); }
    public static function merchant(): void { self::placeholder('Google Feed'); }

    private static function placeholder(string $title): void {
        self::open($title);
        echo '<p>This module is not included in this build.</p>';
        self::close();
    }

    private static function render_rows(array $rows, array $cols): void {
        if (!$rows) {
            echo '<p>Nothing here yet.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>';
        foreach ($cols as $c) {
            echo '<th>' . esc_html($c) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($cols as $c) {
                echo '<td>' . esc_html((string) ($row->$c ?? '')) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

add_filter('cron_schedules', ['SGAP_Install', 'cron_schedules']);
add_action('init', ['SGAP_Install', 'maybe_upgrade'], 5);

// Settings must register on every request (options.php saves go through admin),
// but the admin UI is only loaded inside wp-admin, so the frontend pays nothing.
SGAP_Settings::init();
SGAP_Tracking::init();
SGAP_REST::init();
SGAP_Maintenance::init();
if (is_admin()) {
    SGAP_Admin::init();
}
