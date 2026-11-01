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
        'sgap_suppression_check',
        'sgap_ai_generate',        // hourly hook of an older AI writer
        'sgap_maintenance_daily'   // replaced by sgap_daily_cleanup
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
        'sgap_ai_provider' => 'anthropic',
        'sgap_publish_mode' => 'rss',
        'sgap_auto_images' => 'yes',
        'sgap_auto_seo' => 'yes',
        'sgap_auto_publish_articles' => 'no',
        'sgap_weekly_report' => 'yes',
        'sgap_articles_per_week' => 0,
        'sgap_rescue_hosts' => 'steamgears.juno.page'
    ];

    public static function activate(): void {
        self::install();
    }

    public static function deactivate(): void {
        self::clear_legacy_cron();
        foreach ([self::CLEANUP_CRON, self::PUBLISH_CRON, self::GENERATE_CRON] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        if (class_exists('SGAP_Auto')) {
            SGAP_Auto::clear();
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
            // A page cache may still hold a feed stored by an older version: drop it so Publer sees fresh posts.
            if (class_exists('SGAP_RSS')) {
                foreach (SGAP_Publer::CHANNELS as $channel) {
                    SGAP_RSS::flush_cache($channel);
                }
            }
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
        KEY status_scheduled (status,scheduled_at),
        KEY status_sent (status,sent_at),
        KEY published_at (published_at),
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
        SGAP_RSS::add_rewrite_rule();
        flush_rewrite_rules(false);

        update_option('sgap_version', SGAP_VERSION);
    }
}
register_activation_hook(SGAP_FILE, ['SGAP_Install', 'activate']);
register_deactivation_hook(SGAP_FILE, ['SGAP_Install', 'deactivate']);

/* ---------------- SETTINGS ---------------- */
class SGAP_Settings {
    const BOOL_KEYS = ['autopilot_enabled', 'full_auto_enabled', 'auto_images', 'auto_seo', 'auto_publish_articles', 'weekly_report'];
    const HOUR_KEYS = ['quiet_start', 'quiet_end'];
    const INT_KEYS = [
        'daily_cap','batch_size','labor_rate','monthly_budget','target_roi',
        'winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max','posts_per_day','articles_per_week'
    ];
    /** Never echoed back to the browser; can be overridden by SGAP_<KEY> constants in wp-config.php. */
    const SECRET_KEYS = [
        'publer_api_key','ai_api_key','image_api_key','serpapi_key',
        'pinterest_token','instagram_token','telegram_bot_token','tiktok_token','youtube_token',
        'etsy_oauth_token','etsy_refresh_token','merchant_feed_secret'
    ];
    const TEXT_KEYS = [
        'publer_workspace_id','publer_pinterest_board','channels','ai_model','ai_base_url','image_model','content_language','rescue_hosts',
        'telegram_chat_id','email_test_recipient','youtube_channel_id','reddit_username',
        'reddit_subreddit','etsy_shop_id','etsy_shipping_profile_id','etsy_return_policy_id','ai_mode'
    ];
    /** Settings with a fixed list of values, shown as a select. */
    const CHOICE_KEYS = [
        'publish_mode' => ['rss' => 'RSS feeds (Publer Professional)', 'api' => 'Publer API (Publer Business)'],
        'ai_provider' => ['anthropic' => 'Anthropic (Claude)', 'aimlapi' => 'AIMLAPI (Claude and 300+ other models)', 'openai_compatible' => 'Other OpenAI-compatible API (OpenAI, OpenRouter, ...)']
    ];

    /** Shown in the settings page. Keys not listed here stay registered for older data but are unused. */
    const SECTIONS = [
        'General' => ['autopilot_enabled','full_auto_enabled','daily_cap','batch_size','quiet_start','quiet_end'],
        'Publer' => ['publish_mode','publer_api_key','publer_workspace_id','publer_pinterest_board','channels'],
        'AI content' => ['ai_provider','ai_api_key','ai_model','ai_base_url','posts_per_day','content_language','image_model','image_api_key','serpapi_key'],
        'Economics' => ['labor_rate','monthly_budget','target_roi','winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max'],
        'Etsy' => ['etsy_shop_id','etsy_oauth_token','etsy_refresh_token','etsy_shipping_profile_id','etsy_return_policy_id'],
        'Google Feed' => ['merchant_feed_secret']
    ];

    const HELP = [
        'autopilot_enabled' => 'Master switch: generate posts daily and publish the queue via Publer.',
        'full_auto_enabled' => 'On: AI posts go straight to the queue. Off: they wait under AI Drafts for your approval.',
        'publish_mode' => 'RSS: posts are released into one feed per channel; in Publer add each feed (Explore → RSS Feeds) and set it to Auto-post. API: posts are sent straight to Publer (needs a Business plan API key).',
        'publer_api_key' => 'API mode only. Publer → Settings → Access & Login → API Keys (Business plan). Scopes: posts, media, accounts.',
        'publer_workspace_id' => 'Shown by "Test Publer" on the dashboard once the API key is saved.',
        'publer_pinterest_board' => 'API mode: Pinterest board ID the pins go to. RSS mode: set a default board in Publer instead.',
        'ai_provider' => 'AIMLAPI: key from aimlapi.com → Dashboard → API Keys; pay per use, many models with one key.',
        'ai_model' => 'Empty = claude-opus-5-5 for Anthropic, anthropic/claude-opus-5.5 for AIMLAPI. Required for another OpenAI-compatible API.',
        'ai_base_url' => 'Other OpenAI-compatible API only, e.g. https://api.openai.com/v1.',
        'posts_per_day' => 'How many site posts/products the AI writes social posts for each day.',
        'content_language' => 'Language of articles and guides from the Content Studio. Empty = English.',
        'image_model' => 'AIMLAPI image model. Empty = bytedance/seedream-5-0-pro (about $0.045 per image). Cheaper: flux/schnell.',
        'image_api_key' => 'Only needed when the AI provider is not AIMLAPI: an AIMLAPI key for images.',
        'serpapi_key' => 'serpapi.com → Manage API key. Gives the article writer live Google data: People also ask, related searches, top results.',
        'ai_api_key' => 'AIMLAPI: aimlapi.com → Dashboard → API Keys. Anthropic: console.anthropic.com → API keys.',
        'channels' => 'Instagram and Pinterest posts need a photo; the Content Studio can create one.',
        'daily_cap' => 'Never more than this many posts released per day, across all channels.',
        'batch_size' => 'How many due posts are released per 15-minute run.',
        'quiet_start' => 'No posts are released from this hour …',
        'quiet_end' => '… until this hour (site time).',
        'repost_cooldown_days' => 'A page is not promoted again within this many days.',
        'auto_images' => 'Instagram and Pinterest posts without a photo get the page\'s featured image or an AI photo (AIMLAPI, about $0.045 each).',
        'auto_seo' => 'New posts and products without a Rank Math description get a focus keyword, meta title and description from the AI when published.',
        'articles_per_week' => 'The AI picks topics the site does not cover yet and writes this many SEO articles a week. 0 = off.',
        'auto_publish_articles' => 'Off: weekly articles wait as drafts for you. On: they are published straight away.',
        'weekly_report' => 'A short e-mail every week: posts, clicks, orders per channel and anything that needs you.',
        'rescue_hosts' => 'Images on these servers are copied into your media library and every link is rewritten (comma-separated).'
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
                    if (isset($_POST['sgap_clear']) && is_array($_POST['sgap_clear']) && !empty($_POST['sgap_clear'][$key])) {
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
            '/bot|crawl|spider|slurp|facebookexternalhit|facebookplatform|linkpreview|publer|embedly|preview|whatsapp|curl|wget|python-requests|headless/i',
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
        $angle = self::key($item['angle'] ?? '', 'angle_a', 20);
        $content_type = self::key($item['content_type'] ?? '', 'product', 40);
        $format = self::key($item['format'] ?? '', 'pin', 40);
        if (SGAP_Settings::enabled('full_auto_enabled') && self::suppressed($channel, $angle, $content_type, $format)) {
            return new WP_Error('sgap_suppressed', 'Combination is currently suppressed.', ['status' => 422]);
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
            'angle' => $angle,
            'content_type' => $content_type,
            'format' => $format,
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

    /** True when Full Auto has blocked this channel/angle/type/format combination. */
    public static function suppressed(string $channel, string $angle, string $content_type, string $format): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT level FROM {$wpdb->prefix}sgap_suppression
             WHERE channel = %s AND angle = %s AND content_type = %s AND format = %s
               AND level = 'blocked' AND protected = 0
               AND (reactivate_at IS NULL OR reactivate_at > %s)",
            $channel, $angle, $content_type, $format, current_time('mysql')
        ));
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

    /** blocked_reason() in words, for the admin and the Claude tools. */
    public static function blocked_message(string $reason = ''): string {
        $reason = $reason !== '' ? $reason : self::blocked_reason();
        if ($reason === 'quiet_hours') {
            return sprintf('quiet hours %02d:00–%02d:00; queued posts are released from %02d:00 (site time is now %s). Set Quiet start and Quiet end to 0 in Settings to send at night',
                (int) SGAP_Settings::get('quiet_start', 23), (int) SGAP_Settings::get('quiet_end', 7), (int) SGAP_Settings::get('quiet_end', 7), wp_date('H:i'));
        }
        return [
            'autopilot_disabled' => 'the autopilot is off: Settings → General → Autopilot enabled',
            'daily_cap_reached' => 'today\'s limit of ' . (int) SGAP_Settings::get('daily_cap', 8) . ' posts is reached; more go out tomorrow'
        ][$reason] ?? $reason;
    }

    /** How many posts may go out now: the batch size, capped by what is left of today's limit. */
    public static function capacity(): int {
        global $wpdb;
        $today = wp_date('Y-m-d 00:00:00');
        // Two index-backed counts (published_at, status+sent_at) instead of one OR that scans the table.
        $in_flight_or_published = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT (SELECT COUNT(*) FROM ' . self::table() . ' WHERE published_at >= %s)
                  + (SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'sending' AND sent_at >= %s AND (published_at IS NULL OR published_at < %s))",
            $today, $today, $today
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
        register_rest_route(self::NS, '/suppression', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_suppression'], 'permission_callback' => $auth],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'upsert_suppression'], 'permission_callback' => $auth]
        ]);
        register_rest_route(self::NS, '/reposts', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_reposts'], 'permission_callback' => $auth],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_repost'], 'permission_callback' => $auth]
        ]);
        register_rest_route(self::NS, '/etsy/orders', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'import_etsy_order'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/feed', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'merchant_feed'], 'permission_callback' => '__return_true'
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
    public static function list_suppression(WP_REST_Request $r) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgap_suppression ORDER BY suppressed_at DESC LIMIT 100", ARRAY_A);
    }

    public static function upsert_suppression(WP_REST_Request $r) {
        global $wpdb;
        $channel = self::key($r, 'channel', '', 40);
        $angle = self::key($r, 'angle', 'angle_a', 20);
        $content_type = self::key($r, 'content_type', 'product', 40);
        $format = self::key($r, 'format', 'pin', 40);
        $level = in_array($r->get_param('level'), ['reduced', 'blocked', 'active'], true) ? $r->get_param('level') : 'reduced';
        $reason = self::text($r->get_param('reason'));
        $reactivate = self::datetime($r->get_param('reactivate_at'));
        if ($channel === '') {
            return new WP_Error('sgap_invalid', 'channel is required.', ['status' => 400]);
        }
        // No reactivation date must be stored as NULL: '' is rejected by MySQL in strict mode.
        $reactivate_sql = $reactivate === null ? 'NULL' : $wpdb->prepare('%s', $reactivate);
        $saved = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}sgap_suppression (channel, angle, content_type, format, level, reason, suppressed_at, reactivate_at)
             VALUES (%s, %s, %s, %s, %s, %s, %s, {$reactivate_sql})
             ON DUPLICATE KEY UPDATE level = IF(protected = 1, level, VALUES(level)), reason = VALUES(reason), reactivate_at = VALUES(reactivate_at)",
            $channel, $angle, $content_type, $format, $level, $reason, current_time('mysql')
        ));
        if ($saved === false) {
            return new WP_Error('sgap_db', 'Could not save: ' . $wpdb->last_error, ['status' => 500]);
        }
        return ['status' => 'ok', 'channel' => $channel, 'angle' => $angle, 'level' => $level];
    }

    public static function list_reposts(WP_REST_Request $r) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sgap_reposts ORDER BY id DESC LIMIT 100", ARRAY_A);
    }

    public static function create_repost(WP_REST_Request $r) {
        global $wpdb;
        $orig_id = absint($r->get_param('original_queue_id'));
        $repost_id = absint($r->get_param('repost_queue_id'));
        if (!$orig_id || !$repost_id) {
            return new WP_Error('sgap_invalid', 'original_queue_id and repost_queue_id required.', ['status' => 400]);
        }
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sgap_reposts WHERE original_queue_id = %d",
            $orig_id
        ));
        if ($count >= (int) SGAP_Settings::get('repost_max', 3)) {
            return new WP_Error('sgap_repost_cap', 'Max reposts reached for this item.', ['status' => 422]);
        }
        $wpdb->insert($wpdb->prefix . 'sgap_reposts', [
            'original_queue_id' => $orig_id,
            'repost_queue_id' => $repost_id,
            'repost_number' => $count + 1,
            'new_angle' => self::key($r, 'new_angle', 'angle_b', 20),
            'new_hook' => self::text($r->get_param('new_hook')),
            'created_at' => current_time('mysql')
        ]);
        return new WP_REST_Response(['id' => (int) $wpdb->insert_id, 'repost_number' => $count + 1], 201);
    }

    public static function import_etsy_order(WP_REST_Request $r) {
        global $wpdb;
        $order_id = absint($r->get_param('etsy_order_id'));
        if (!$order_id) {
            return new WP_Error('sgap_invalid', 'etsy_order_id is required.', ['status' => 400]);
        }
        $revenue = (float) $r->get_param('revenue');
        $fees = (float) $r->get_param('fees');
        $shipping = (float) $r->get_param('shipping');
        $profit = $r->get_param('profit') !== null ? (float) $r->get_param('profit') : ($revenue - $fees - $shipping);
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}sgap_etsy_orders (etsy_order_id, product_sku, quantity, revenue, fees, shipping, profit, country, imported_at)
             VALUES (%d, %s, %d, %f, %f, %f, %f, %s, %s)
             ON DUPLICATE KEY UPDATE revenue = VALUES(revenue), fees = VALUES(fees), shipping = VALUES(shipping), profit = VALUES(profit)",
            $order_id,
            substr(sanitize_text_field((string) $r->get_param('product_sku')), 0, 80),
            max(1, absint($r->get_param('quantity') ?: 1)),
            $revenue, $fees, $shipping, $profit,
            substr(sanitize_text_field((string) $r->get_param('country')), 0, 10),
            current_time('mysql')
        ));
        return ['etsy_order_id' => $order_id, 'profit' => $profit];
    }

    public static function merchant_feed(WP_REST_Request $r) {
        $secret = (string) SGAP_Settings::get('merchant_feed_secret', '');
        $provided = (string) $r->get_param('key');
        if ($secret === '' || !hash_equals($secret, $provided)) {
            return new WP_Error('sgap_forbidden', 'Invalid feed secret.', ['status' => 403]);
        }
        if (!function_exists('wc_get_products')) {
            return new WP_Error('sgap_no_wc', 'WooCommerce is not active.', ['status' => 501]);
        }
        header('Content-Type: application/xml; charset=utf-8');
        $products = wc_get_products(['status' => 'publish', 'limit' => 500]);
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
        echo '<title>' . esc_xml(get_bloginfo('name')) . '</title>' . "\n";
        echo '<link>' . esc_url(home_url('/')) . '</link>' . "\n";
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
        foreach ($products as $p) {
            $img = wp_get_attachment_url($p->get_image_id());
            $price = $p->get_price();
            if (!$price || !$img) continue;
            echo "<item>\n";
            echo '  <g:id>' . esc_xml((string) ($p->get_sku() ?: $p->get_id())) . "</g:id>\n";
            echo '  <g:title>' . esc_xml($p->get_name()) . "</g:title>\n";
            echo '  <g:description>' . esc_xml(wp_strip_all_tags($p->get_short_description() ?: $p->get_description())) . "</g:description>\n";
            echo '  <g:link>' . esc_url($p->get_permalink()) . "</g:link>\n";
            echo '  <g:image_link>' . esc_url($img) . "</g:image_link>\n";
            echo '  <g:availability>' . ($p->is_in_stock() ? 'in_stock' : 'out_of_stock') . "</g:availability>\n";
            echo '  <g:price>' . esc_xml(number_format((float) $price, 2, '.', '') . ' ' . $currency) . "</g:price>\n";
            echo '  <g:condition>new</g:condition>' . "\n";
            echo "</item>\n";
        }
        echo '</channel></rss>';
        exit;
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
        add_action('admin_post_sgap_queue_action', [__CLASS__, 'queue_action']);
        add_action('admin_post_sgap_suppression_action', [__CLASS__, 'suppression_action']);
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

    public static function open(string $title, string $subtitle = ''): void {
        SGAP_UI::css();
        echo '<div class="wrap sgap"><div class="sgap-head"><span class="sgap-logo" aria-hidden="true">⚙</span><div><h1>' . esc_html($title) . '</h1>'
            . ($subtitle !== '' ? '<p>' . esc_html($subtitle) . '</p>' : '') . '</div></div><hr class="wp-header-end" style="display:none">';
        // Result of the last button press, shown at the top where it is seen without scrolling.
        $result = get_transient('sgap_run_result_' . get_current_user_id());
        if (is_string($result) && $result !== '') {
            delete_transient('sgap_run_result_' . get_current_user_id());
            $type = stripos($result, 'error') !== false || stripos($result, 'failed:') !== false ? 'warning' : 'success';
            echo '<div class="notice notice-' . $type . ' is-dismissible"><pre style="white-space:pre-wrap;font-size:13px">' . esc_html($result) . '</pre></div>';
        }
        // Buttons that call an AI or the network take a while: show that the click registered.
        echo '<script>document.addEventListener("submit",function(e){var f=e.target;if((f.getAttribute("action")||"").indexOf("admin-post.php")<0)return;'
            . 'var b=e.submitter||f.querySelector("button,input[type=submit]");if(!b)return;setTimeout(function(){b.disabled=true;'
            . 'var t="Working… this can take a minute";if(b.tagName==="INPUT"){b.value=t;}else{b.textContent=t;}},0);});</script>';
    }

    /** Stores a message for open() on the next page and goes there. */
    private static function done(string $message, string $page = 'steamgears-autopilot'): void {
        delete_transient('sgap_dashboard_stats');
        set_transient('sgap_run_result_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }

    public static function close(): void {
        echo '</div>';
    }

    public static function dashboard(): void {
        global $wpdb;
        self::open('Steamgears Autopilot', 'Social media on autopilot: AI writes, Publer posts, every click is counted.');
        $stats = get_transient('sgap_dashboard_stats');
        if (!is_array($stats) || !isset($stats['tiles'])) {
            $q = $wpdb->prefix . 'sgap_queue';
            $d = $wpdb->prefix . 'sgap_ai_drafts';
            $e = $wpdb->prefix . 'sgap_events';
            $since = wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
            $has = ['q' => self::table_exists($q), 'd' => self::table_exists($d), 'e' => self::table_exists($e)];
            $totals = $has['e'] ? $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks, SUM(event_type = 'order') AS orders,
                        SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue FROM {$e} WHERE created_at >= %s", $since)) : null;
            $stats = [
                'missing' => in_array(false, $has, true),
                'tiles' => [
                    'queued' => $has['q'] ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$q} WHERE status = 'queued'") : 0,
                    'pending' => $has['d'] ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$d} WHERE status = 'pending'") : 0,
                    'published' => (int) ($totals->published ?? 0),
                    'clicks' => (int) ($totals->clicks ?? 0),
                    'orders' => (int) ($totals->orders ?? 0),
                    'revenue' => (float) ($totals->revenue ?? 0)
                ],
                'channels' => $has['e'] ? $wpdb->get_results($wpdb->prepare(
                    "SELECT channel, SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks,
                        SUM(event_type = 'order') AS orders, SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue
                     FROM {$e} WHERE created_at >= %s GROUP BY channel ORDER BY revenue DESC, clicks DESC", $since)) : []
            ];
            set_transient('sgap_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS);
        }
        if ($stats['missing']) {
            echo '<div class="notice notice-error"><p>Some Steamgears tables are missing: deactivate and reactivate the plugin.</p></div>';
        }

        // Status pills
        $keys = get_option(SGAP_KeyCheck::RESULT);
        $ai_ok = is_array($keys) ? ($keys['ai']['ok'] ?? null) : null;
        $blocked = SGAP_Queue::blocked_reason();
        echo '<div class="sgap-pills">'
            . SGAP_UI::pill('Autopilot ' . (SGAP_Settings::enabled('autopilot_enabled') ? 'on' : 'off'), SGAP_Settings::enabled('autopilot_enabled') ? 'ok' : 'bad')
            . SGAP_UI::pill('Full auto ' . (SGAP_Settings::enabled('full_auto_enabled') ? 'on' : 'off – you approve'), 'brass')
            . SGAP_UI::pill(SGAP_RSS::enabled() ? 'Publer via RSS' : 'Publer API', 'brass')
            . SGAP_UI::pill('AI: ' . SGAP_AI::provider_name() . ($ai_ok === null ? ' (untested)' : ($ai_ok ? ' ✓' : ' ✗')), $ai_ok === null ? 'warn' : ($ai_ok ? 'ok' : 'bad'))
            . ($blocked === 'quiet_hours' ? SGAP_UI::pill('Quiet hours until ' . sprintf('%02d:00', (int) SGAP_Settings::get('quiet_end', 7)), 'warn') : '')
            . ($blocked === 'daily_cap_reached' ? SGAP_UI::pill('Daily limit reached', 'warn') : '')
            . SGAP_UI::pill('v' . SGAP_VERSION)
            . '</div>';

        SGAP_Setup::checklist_card();
        SGAP_Rescue::card();

        // Tiles
        $t = $stats['tiles'];
        echo '<div class="sgap-stats">'
            . SGAP_UI::stat('Drafts to review', $t['pending'], admin_url('admin.php?page=sgap-ai'))
            . SGAP_UI::stat('Waiting in queue', $t['queued'], admin_url('admin.php?page=sgap-queue'))
            . SGAP_UI::stat('Published · 30 days', $t['published'])
            . SGAP_UI::stat('Clicks · 30 days', $t['clicks'])
            . SGAP_UI::stat('Orders · 30 days', $t['orders'] . ($t['revenue'] > 0 ? ' · ' . number_format_i18n($t['revenue'], 0) : ''))
            . '</div>';

        echo '<div class="sgap-grid"><div>';
        self::run_panel();
        SGAP_UI::card_open('Last 30 days per channel', 'chart-bar');
        self::render_rows($stats['channels'], ['channel', 'published', 'clicks', 'orders', 'revenue']);
        SGAP_UI::card_close();
        echo '</div><div>';
        SGAP_UI::card_open('AI Chat', 'format-chat');
        echo '<p class="description" style="margin-top:0">Ask how things are going or tell the AI what to do: write posts, approve, release the queue, change settings.</p>'
            . '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sgap-chat')) . '"><span class="dashicons dashicons-format-chat"></span>Open AI Chat</a></p>';
        SGAP_UI::card_close();
        SGAP_Auto::health_card();
        SGAP_CC::card();
        self::feeds_panel();
        self::keys_panel();
        SGAP_Updater::panel();
        echo '</div></div>';
        self::close();
    }

    private static function run_panel(): void {
        SGAP_UI::card_open('Run now', 'controls-play');
        echo '<div class="sgap-actions">';
        $tasks = [
            'generate' => ['Generate posts now', 'edit', true],
            'publish' => ['Publish queue now', 'megaphone', false],
            'test_keys' => ['Test API keys', 'admin-network', false],
            'test_publer' => ['List Publer accounts', 'groups', false]
        ];
        foreach ($tasks as $task => [$label, $icon, $primary]) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="sgap_run"><input type="hidden" name="task" value="' . esc_attr($task) . '">'
                . wp_nonce_field('sgap_run_' . $task, '_wpnonce', true, false)
                . '<button class="button' . ($primary ? ' button-primary' : '') . '"><span class="dashicons dashicons-' . $icon . '"></span>' . esc_html($label) . '</button></form>';
        }
        echo '</div>';
        // Progress bar for "Generate posts now": one request per page, so each step is visible.
        echo '<div id="sgap-progress" class="sgap-progress" style="display:none">'
            . '<progress id="sgap-progress-bar" value="0" max="1"></progress>'
            . '<p id="sgap-progress-text"></p></div>';
        $gen = [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sgap_generate'),
            'back' => admin_url('admin.php?page=steamgears-autopilot')
        ];
        echo '<script>(function(){var C=' . wp_json_encode($gen) . ';'
            . 'var f=document.querySelector(\'form input[name="task"][value="generate"]\');if(!f||!window.fetch)return;f=f.form;'
            . 'var box=document.getElementById("sgap-progress"),bar=document.getElementById("sgap-progress-bar"),txt=document.getElementById("sgap-progress-text");'
            . 'function call(action,data){var b=new URLSearchParams(data||{});b.append("action",action);b.append("nonce",C.nonce);'
            . 'return fetch(C.ajax,{method:"POST",credentials:"same-origin",body:b}).then(function(r){return r.json();}).then(function(j){if(!j||!j.success)throw new Error((j&&j.data)||"request failed");return j.data;});}'
            . 'f.addEventListener("submit",function(e){e.preventDefault();e.stopImmediatePropagation();var btn=f.querySelector("button");btn.disabled=true;'
            . 'document.querySelectorAll(".wrap > .notice").forEach(function(n){n.remove();});box.style.display="block";bar.removeAttribute("value");txt.textContent="Choosing pages…";'
            . 'var tot={sources:0,drafts:0,queued:0},errs=[];'
            . 'call("sgap_gen_plan").then(function(plan){var pages=plan.pages,i=0;bar.max=Math.max(pages.length,1);bar.value=0;'
            . 'if(!pages.length){txt.textContent="No page needs a post right now.";}'
            . 'function next(){if(i>=pages.length){bar.value=bar.max;txt.textContent="Done: "+tot.drafts+" posts written. Saving…";'
            . 'var d={sources:tot.sources,drafts:tot.drafts,queued:tot.queued};errs.forEach(function(x,k){d["errors["+k+"]"]=x;});'
            . 'return call("sgap_gen_done",d).then(function(){location.href=C.back;});}'
            . 'var p=pages[i];txt.textContent="Writing "+plan.channels.length+" posts for \u201c"+p.title+"\u201d ("+(i+1)+" of "+pages.length+")…";'
            . 'return call("sgap_gen_one",{post_id:p.id}).then(function(r){tot.sources+=r.sources;tot.drafts+=r.drafts;tot.queued+=r.queued;errs=errs.concat(r.errors);},'
            . 'function(err){tot.sources++;errs.push(p.title+": "+err.message);}).then(function(){i++;bar.value=i;return next();});}'
            . 'return next();}).catch(function(err){txt.textContent="Stopped: "+err.message;btn.disabled=false;});},true);})();</script>';
        $last = get_option('sgap_last_generate');
        if (is_array($last)) {
            echo '<p class="description">Last generation: ' . esc_html($last['at'] . ' – ' . $last['drafts'] . ' drafts, ' . $last['queued'] . ' queued' . ($last['errors'] ? ', ' . count($last['errors']) . ' errors' : '')) . '</p>';
        }
        SGAP_UI::card_close();
    }

    private static function feeds_panel(): void {
        if (!SGAP_RSS::enabled()) {
            return;
        }
        SGAP_UI::card_open('Publer RSS feeds', 'rss');
        foreach (SGAP_Publer::channels() as $channel) {
            $url = SGAP_RSS::url($channel);
            echo '<div class="sgap-feed">' . SGAP_UI::channel($channel) . '<code title="' . esc_attr($url) . '">' . esc_html($url) . '</code>'
                . '<button type="button" class="button button-small sgap-copy" data-url="' . esc_attr($url) . '"><span class="dashicons dashicons-admin-page"></span>Copy</button>'
                . '<a class="button button-small" href="' . esc_url($url) . '" target="_blank" rel="noopener">Open</a></div>';
        }
        echo '<p class="description">In Publer: <em>Explore → RSS Feeds → add feed</em> → paste → <em>Settings → Auto-post</em>, '
            . 'tick "use link title/description as caption", <em>photo</em> for Instagram/Pinterest, <em>link</em> for the rest, and pick the account.</p>'
            . '<p><a class="button button-primary" href="https://app.publer.com/" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span>Open Publer</a></p>';
        echo '<script>document.querySelectorAll(".sgap-copy").forEach(function(b){b.addEventListener("click",function(){var u=b.dataset.url,h=b.innerHTML,done=function(){b.textContent="Copied ✓";setTimeout(function(){b.innerHTML=h;},2000);};'
            . 'var old=function(){var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();try{document.execCommand("copy");}catch(e){}t.remove();done();};'
            . 'if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(u).then(done,old);}else{old();}});});</script>';
        SGAP_UI::card_close();
    }

    private static function keys_panel(): void {
        $keys = get_option(SGAP_KeyCheck::RESULT);
        SGAP_UI::card_open('API keys', 'admin-network');
        if (!is_array($keys)) {
            echo '<p class="description">Not tested yet. Save a key in Settings or press "Test API keys".</p>';
        } else {
            foreach (['ai' => 'AI', 'publer' => 'Publer'] as $part => $label) {
                $check = $keys[$part] ?? ['ok' => null, 'message' => ''];
                $kind = $check['ok'] === null ? '' : ($check['ok'] ? 'ok' : 'bad');
                $mark = $check['ok'] === null ? '–' : ($check['ok'] ? '✓' : '✗');
                echo '<div class="sgap-check">' . SGAP_UI::pill("{$label} {$mark}", $kind) . '<span>' . esc_html($check['message']) . '</span></div>';
            }
            echo '<p class="description">Tested ' . esc_html($keys['checked_at']) . ' · <a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings</a></p>';
        }
        SGAP_UI::card_close();
    }

    public static function run_action(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        $task = isset($_POST['task']) ? sanitize_key($_POST['task']) : '';
        check_admin_referer('sgap_run_' . $task);
        if ($task === 'generate') {
            $out = SGAP_Generator::summary(SGAP_Generator::run());
        } elseif ($task === 'publish') {
            if (!SGAP_RSS::enabled() && !SGAP_Publer::configured()) {
                $out = 'Save the Publer API key and workspace id first (or switch Publish mode to RSS feeds).';
            } else {
                $r = SGAP_Publer::tick();
                $out = $r['blocked'] !== ''
                    ? 'Nothing sent: ' . SGAP_Queue::blocked_message($r['blocked']) . '.'
                    : count($r['sent']) . ' post(s) ' . (SGAP_RSS::enabled() ? 'released to the RSS feeds' : 'sent to Publer') . ', ' . count($r['failed']) . ' failed. See the Queue page.';
            }
        } elseif ($task === 'test_keys') {
            $r = SGAP_KeyCheck::run();
            $out = 'Publer: ' . $r['publer']['message'] . "\nAI: " . $r['ai']['message'];
        } elseif ($task === 'test_publer') {
            $out = self::publer_report();
        } else {
            $out = 'Unknown action.';
        }
        self::done($out);
    }

    private static function publer_report(): string {
        if (SGAP_RSS::enabled()) {
            return 'RSS mode: Publer reads the feeds listed on the dashboard; no API key needed. Their accounts are chosen in Publer per feed.';
        }
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
        SGAP_Setup::settings_page();
    }

    private static function get_setting_help_link(string $key): ?array {
        $links = [
            'pinterest_token' => ['url' => 'https://developers.pinterest.com/', 'text' => 'Pinterest Developers'],
            'instagram_token' => ['url' => 'https://developers.facebook.com/', 'text' => 'Meta App Dashboard'],
            'telegram_bot_token' => ['url' => 'https://t.me/BotFather', 'text' => 'Telegram BotFather'],
            'tiktok_token' => ['url' => 'https://developers.tiktok.com/', 'text' => 'TikTok Developers'],
            'youtube_token' => ['url' => 'https://console.cloud.google.com/', 'text' => 'Google Cloud Console'],
            'youtube_channel_id' => ['url' => 'https://www.youtube.com/account_advanced', 'text' => 'Find Channel ID'],
            'reddit_username' => ['url' => 'https://www.reddit.com/prefs/apps', 'text' => 'Reddit App Preferences'],
            'etsy_shop_id' => ['url' => 'https://www.etsy.com/your/shops/me', 'text' => 'Etsy Shop Manager'],
            'etsy_oauth_token' => ['url' => 'https://www.etsy.com/developers/your-apps', 'text' => 'Etsy Developer Portal'],
            'publer_api_key' => ['url' => 'https://publer.com/help/en/article/how-to-access-the-publer-api-1w08edo/', 'text' => 'Publer API keys'],
        ];
        return $links[$key] ?? null;
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
        self::open('Queue', 'Posts waiting to go out, and what happened to the ones that did.');
        $table = $wpdb->prefix . 'sgap_queue';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT id, channel, title, status, attempts, scheduled_at, published_at, error FROM {$table} ORDER BY id DESC LIMIT 50")
            : [];
        if (!$rows) {
            SGAP_UI::empty_state('clock', 'The queue is empty. Approve a draft under AI Drafts to fill it.');
            self::close();
            return;
        }
        echo '<div class="sgap-card"><div class="sgap-scroll"><table class="widefat striped sgap-qtable"><thead><tr><th>#</th><th>Channel</th><th>Title</th><th>Status</th><th>Scheduled</th><th>Published</th><th>Tracking link</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td data-label="#">' . esc_html($r->id) . '</td><td data-label="Channel">' . SGAP_UI::channel($r->channel) . '</td><td class="sgap-qtitle" data-label="Title">' . esc_html($r->title)
                . ($r->error ? '<br><small style="color:#b32d2e">' . esc_html($r->error) . '</small>' : '') . '</td><td data-label="Status">'
                . SGAP_UI::status_pill((string) $r->status) . ($r->attempts > 1 ? ' <small>' . esc_html($r->attempts . ' tries') . '</small>' : '') . '</td><td data-label="Scheduled">'
                . esc_html($r->scheduled_at ? mysql2date('j M H:i', $r->scheduled_at) : '–') . '</td><td data-label="Published">'
                . esc_html($r->published_at ? mysql2date('j M H:i', $r->published_at) : '–') . '</td><td data-label="Link"><code>' . esc_html(wp_parse_url(SGAP_Tracking::url((int) $r->id), PHP_URL_PATH)) . '</code></td><td data-label="">'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-actions" style="flex-wrap:nowrap">'
                . '<input type="hidden" name="action" value="sgap_queue_action">'
                . '<input type="hidden" name="queue_id" value="' . esc_attr($r->id) . '">'
                . wp_nonce_field('sgap_queue_' . $r->id, '_wpnonce', true, false)
                . '<button class="button button-small" name="cmd" value="retry">Re-queue</button>'
                . '<button class="button button-small sgap-danger" name="cmd" value="delete">Delete</button></form></td></tr>';
        }
        echo '</tbody></table></div></div>';
        self::close();
    }

    public static function queue_action(): void {
        global $wpdb;
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        $id = isset($_POST['queue_id']) ? absint($_POST['queue_id']) : 0;
        check_admin_referer('sgap_queue_' . $id);
        $cmd = isset($_POST['cmd']) ? sanitize_key($_POST['cmd']) : '';
        $table = $wpdb->prefix . 'sgap_queue';
        $message = 'Nothing changed.';
        if ($id && $cmd === 'retry') {
            $wpdb->update($table, ['status' => 'queued', 'attempts' => 0, 'error' => null, 'external_id' => ''], ['id' => $id]);
            $blocked = SGAP_Queue::blocked_reason();
            $message = "Post #{$id} is queued again." . ($blocked !== '' ? ' It waits: ' . SGAP_Queue::blocked_message($blocked) . '.' : '');
        } elseif ($id && $cmd === 'delete') {
            $wpdb->delete($table, ['id' => $id]);
            $message = "Post #{$id} deleted from the queue.";
        }
        self::done($message, 'sgap-queue');
    }

    public static function ai(): void {
        global $wpdb;
        self::open('AI Drafts', 'Posts the AI wrote for your pages. Approve & Queue sends them to the publishing queue.');
        $table = $wpdb->prefix . 'sgap_ai_drafts';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT d.id, d.post_id, d.channel, d.title, d.body, d.hashtags, d.created_at, p.post_title AS source
                FROM {$table} d LEFT JOIN {$wpdb->posts} p ON p.ID = d.post_id WHERE d.status = 'pending' ORDER BY d.id DESC LIMIT 60")
            : [];
        if (!$rows) {
            SGAP_UI::empty_state('yes-alt', 'No drafts waiting. Press "Generate posts now" on the dashboard to have the AI write some.');
            self::close();
            return;
        }
        echo '<p class="sgap-sub">' . esc_html(count($rows) . ' draft' . (count($rows) === 1 ? '' : 's') . ' waiting for you.') . '</p><div class="sgap-drafts">';
        foreach ($rows as $row) {
            echo '<div class="sgap-draft"><div class="sgap-meta">' . SGAP_UI::channel($row->channel) . '<span>#' . esc_html($row->id) . '</span></div>'
                . '<h3>' . esc_html($row->title) . '</h3>'
                . '<div class="sgap-text">' . esc_html($row->body) . '</div>'
                . ($row->hashtags !== '' ? '<div class="sgap-tags">' . esc_html($row->hashtags) . '</div>' : '')
                . '<div class="sgap-meta"><span>' . esc_html($row->source ? 'For: ' . $row->source : '') . '</span><span>' . esc_html(mysql2date('j M H:i', $row->created_at)) . '</span></div>'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-actions">'
                . '<input type="hidden" name="action" value="sgap_approve_ai">'
                . '<input type="hidden" name="draft_id" value="' . esc_attr($row->id) . '">'
                . wp_nonce_field('sgap_approve_ai_' . $row->id, '_wpnonce', true, false)
                . '<button class="button button-primary" name="decision" value="queue"><span class="dashicons dashicons-yes"></span>Approve &amp; Queue</button>'
                . '<button class="button" name="decision" value="approved">Approve Only</button>'
                . '<button class="button sgap-danger" name="decision" value="rejected">Reject</button></form></div>';
        }
        echo '</div>';
        self::close();
    }

    public static function approve_ai(): void {
        global $wpdb;
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        $id = isset($_POST['draft_id']) ? absint($_POST['draft_id']) : 0;
        check_admin_referer('sgap_approve_ai_' . $id);
        $decision = isset($_POST['decision']) ? sanitize_key($_POST['decision']) : 'queue';
        $message = 'Unknown draft.';
        if ($id && $decision === 'queue') {
            // Approve & Queue: straight into the publishing queue.
            $queued = SGAP_Queue::from_draft($id);
            if (is_wp_error($queued)) {
                $message = "Draft #{$id} not queued, error: " . $queued->get_error_message();
            } else {
                $blocked = SGAP_Queue::blocked_reason();
                $message = "Draft #{$id} approved and queued."
                    . ($blocked !== '' ? ' It waits: ' . SGAP_Queue::blocked_message($blocked) . '.'
                        : (SGAP_RSS::enabled() ? ' Press "Publish queue now" on the dashboard to release it to the RSS feed right away, or wait for the next 15-minute run.'
                            : ' Press "Publish queue now" on the dashboard to send it right away, or wait for the next 15-minute run.'));
            }
        } elseif ($id) {
            $status = $decision === 'rejected' ? 'rejected' : 'approved';
            $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => $status], ['id' => $id]);
            $message = "Draft #{$id} {$status}.";
        }
        self::done($message, 'sgap-ai');
    }


    public static function campaigns(): void {
        global $wpdb;
        self::open('Campaigns & Angle Performance');
        $win_clicks = (int) SGAP_Settings::get('winner_min_clicks', 25);
        $lose_clicks = (int) SGAP_Settings::get('loser_min_clicks', 100);

        echo '<h2>Learning Matrix (Channels &amp; Angles)</h2>';
        $learning = $wpdb->get_results("SELECT dimension, dimension_value, clicks, orders, revenue, updated_at FROM {$wpdb->prefix}sgap_learning ORDER BY dimension ASC, revenue DESC, clicks DESC");
        self::render_rows($learning, ['dimension', 'dimension_value', 'clicks', 'orders', 'revenue', 'updated_at']);

        echo '<h2>Top Winning Posts (&ge; ' . esc_html((string) $win_clicks) . ' clicks or &ge; 1 order)</h2>';
        $winners = $wpdb->get_results($wpdb->prepare(
            "SELECT q.id, q.channel, q.angle, q.title,
                    COUNT(c.id) AS clicks,
                    SUM(c.order_id > 0) AS orders,
                    SUM(c.revenue) AS revenue
             FROM {$wpdb->prefix}sgap_queue q
             JOIN {$wpdb->prefix}sgap_cro c ON c.queue_id = q.id
             GROUP BY q.id
             HAVING clicks >= %d OR orders >= 1
             ORDER BY revenue DESC, clicks DESC LIMIT 25",
            $win_clicks
        ));
        self::render_rows($winners, ['id', 'channel', 'angle', 'title', 'clicks', 'orders', 'revenue']);

        echo '<h2>Underperforming Posts (&ge; ' . esc_html((string) $lose_clicks) . ' clicks, 0 orders)</h2>';
        $losers = $wpdb->get_results($wpdb->prepare(
            "SELECT q.id, q.channel, q.angle, q.title, COUNT(c.id) AS clicks
             FROM {$wpdb->prefix}sgap_queue q
             JOIN {$wpdb->prefix}sgap_cro c ON c.queue_id = q.id
             GROUP BY q.id
             HAVING clicks >= %d AND SUM(c.order_id > 0) = 0
             ORDER BY clicks DESC LIMIT 25",
            $lose_clicks
        ));
        self::render_rows($losers, ['id', 'channel', 'angle', 'title', 'clicks']);
        self::close();
    }

    public static function etsy(): void {
        global $wpdb;
        self::open('Etsy Orders & Profit');
        $table = $wpdb->prefix . 'sgap_etsy_orders';
        $totals = $wpdb->get_row("SELECT COUNT(*) AS orders, SUM(revenue) AS revenue, SUM(fees) AS fees, SUM(shipping) AS shipping, SUM(profit) AS profit FROM {$table}");
        if ($totals && (int) $totals->orders > 0) {
            echo '<p><strong>Orders:</strong> ' . esc_html((string) $totals->orders)
                . ' &middot; <strong>Revenue:</strong> ' . esc_html(number_format((float) $totals->revenue, 2))
                . ' &middot; <strong>Fees:</strong> ' . esc_html(number_format((float) $totals->fees, 2))
                . ' &middot; <strong>Shipping:</strong> ' . esc_html(number_format((float) $totals->shipping, 2))
                . ' &middot; <strong>Net Profit:</strong> ' . esc_html(number_format((float) $totals->profit, 2)) . '</p>';
        }
        $rows = $wpdb->get_results("SELECT etsy_order_id, product_sku, quantity, revenue, fees, shipping, profit, country, imported_at FROM {$table} ORDER BY id DESC LIMIT 50");
        self::render_rows($rows, ['etsy_order_id', 'product_sku', 'quantity', 'revenue', 'fees', 'shipping', 'profit', 'country', 'imported_at']);
        self::close();
    }

    public static function reports(): void {
        global $wpdb;
        self::open('ROI & Attribution Reports');
        $budget = (float) SGAP_Settings::get('monthly_budget', 1000);
        $target_roi = (float) SGAP_Settings::get('target_roi', 200);
        $since = wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);

        $rev_30d = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(value) FROM {$wpdb->prefix}sgap_events WHERE event_type = 'order' AND created_at >= %s",
            $since
        ));
        $roi = SGAP_ROI::roi($rev_30d, $budget);
        $roas = SGAP_ROI::roas($rev_30d, $budget);

        echo '<p><strong>30-Day Attributed Revenue:</strong> ' . esc_html(number_format($rev_30d, 2))
            . ' &middot; <strong>Monthly Budget:</strong> ' . esc_html(number_format($budget, 2))
            . ' &middot; <strong>ROI:</strong> ' . esc_html((string) $roi) . '% (Target: ' . esc_html((string) $target_roi) . '%)'
            . ' &middot; <strong>ROAS:</strong> ' . esc_html((string) $roas) . 'x</p>';

        echo '<h2>Recent Attributed Conversions</h2>';
        $conversions = $wpdb->get_results(
            "SELECT c.order_id, q.channel, c.angle, q.title, c.revenue, c.clicked_at, c.converted_at
             FROM {$wpdb->prefix}sgap_cro c
             LEFT JOIN {$wpdb->prefix}sgap_queue q ON q.id = c.queue_id
             WHERE c.order_id > 0
             ORDER BY c.converted_at DESC LIMIT 50"
        );
        self::render_rows($conversions, ['order_id', 'channel', 'angle', 'title', 'revenue', 'clicked_at', 'converted_at']);
        self::close();
    }

    public static function fullauto(): void {
        global $wpdb;
        self::open('Full Auto: Suppression & Reposts');
        echo '<p>Full Auto Enabled: <strong>' . esc_html(SGAP_Settings::get('full_auto_enabled', 'no')) . '</strong>'
            . ' &middot; Cooldown: <strong>' . esc_html((string) SGAP_Settings::get('repost_cooldown_days', 30)) . ' days</strong>'
            . ' &middot; Max Reposts: <strong>' . esc_html((string) SGAP_Settings::get('repost_max', 3)) . '</strong></p>';

        echo '<h2>Suppressed Combinations</h2>';
        $sup = $wpdb->get_results("SELECT id, channel, angle, content_type, format, level, reason, suppressed_at, reactivate_at, protected FROM {$wpdb->prefix}sgap_suppression ORDER BY id DESC LIMIT 50");
        if (!$sup) {
            echo '<p>No suppressed combinations.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Channel</th><th>Angle</th><th>Type</th><th>Format</th><th>Level</th><th>Reason</th><th>Protected</th><th>Actions</th></tr></thead><tbody>';
            foreach ($sup as $s) {
                echo '<tr><td>' . esc_html($s->channel) . '</td><td>' . esc_html($s->angle) . '</td><td>'
                    . esc_html($s->content_type) . '</td><td>' . esc_html($s->format) . '</td><td>'
                    . esc_html($s->level) . '</td><td>' . esc_html((string) $s->reason) . '</td><td>'
                    . ($s->protected ? 'Yes' : 'No') . '</td><td>'
                    . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                    . '<input type="hidden" name="action" value="sgap_suppression_action">'
                    . '<input type="hidden" name="sup_id" value="' . esc_attr($s->id) . '">'
                    . wp_nonce_field('sgap_sup_' . $s->id, '_wpnonce', true, false)
                    . '<button class="button button-small" name="cmd" value="protect">Toggle Protect</button> '
                    . '<button class="button button-small" name="cmd" value="delete">Remove</button></form></td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h2>Recent Winner Reposts</h2>';
        $reposts = $wpdb->get_results("SELECT original_queue_id, repost_queue_id, repost_number, new_angle, new_hook, created_at FROM {$wpdb->prefix}sgap_reposts ORDER BY id DESC LIMIT 50");
        self::render_rows($reposts, ['original_queue_id', 'repost_queue_id', 'repost_number', 'new_angle', 'new_hook', 'created_at']);
        self::close();
    }

    public static function suppression_action(): void {
        global $wpdb;
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        $id = isset($_POST['sup_id']) ? absint($_POST['sup_id']) : 0;
        check_admin_referer('sgap_sup_' . $id);
        $cmd = isset($_POST['cmd']) ? sanitize_key($_POST['cmd']) : '';
        $table = $wpdb->prefix . 'sgap_suppression';
        $message = 'Nothing changed.';
        if ($id && $cmd === 'protect') {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET protected = 1 - protected, level = IF(protected = 1, 'active', level) WHERE id = %d", $id));
            $message = "Suppression #{$id}: protection toggled.";
        } elseif ($id && $cmd === 'delete') {
            $wpdb->delete($table, ['id' => $id]);
            $message = "Suppression #{$id} removed.";
        }
        self::done($message, 'sgap-fullauto');
    }

    public static function merchant(): void {
        self::open('Google Merchant & Pinterest Catalog Feed');
        $secret = (string) SGAP_Settings::get('merchant_feed_secret', '');
        if ($secret === '') {
            echo '<div class="notice notice-warning"><p>Please set a <strong>Merchant feed secret</strong> under <a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings &rarr; Google Feed</a> to enable your XML product feed.</p></div>';
        } else {
            $feed_url = add_query_arg('key', rawurlencode($secret), rest_url(SGAP_REST::NS . '/feed'));
            echo '<p>Use this XML feed URL in <strong>Pinterest Catalogs</strong> and <strong>Google Merchant Center</strong>:</p>';
            echo '<p><input type="text" readonly class="large-text code" value="' . esc_attr($feed_url) . '" onclick="this.select();"></p>';
        }
        self::close();
    }

    private static function render_rows(array $rows, array $cols): void {
        if (!$rows) {
            echo '<p class="description">Nothing here yet.</p>';
            return;
        }
        echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>';
        foreach ($cols as $c) {
            echo '<th>' . esc_html($c) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($cols as $c) {
                $val = is_object($row) ? ($row->$c ?? '') : ($row[$c] ?? '');
                echo '<td>' . esc_html((string) $val) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
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
