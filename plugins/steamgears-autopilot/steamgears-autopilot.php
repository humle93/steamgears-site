<?php
/**
* Plugin Name: Steamgears Autopilot
* Description: Premium multichannel growth autopilot for Steamgears.eu.
* Version: 7.1.1
* Requires at least: 6.4
* Requires PHP: 8.0
* Author: Steamgears.eu
* License: GPL-2.0-or-later
* Text Domain: steamgears-autopilot
*/

if (!defined('ABSPATH')) exit;

// Another copy (e.g. an old version in a different folder) is already loaded:
// bail out instead of fatally redeclaring the classes below.
if (defined('SGAP_VERSION') || class_exists('SGAP_Settings', false)) {
    if (is_admin()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Steamgears Autopilot:</strong> another copy of the plugin is already active. Deactivate the old copy.</p></div>';
        });
    }
    return;
}

define('SGAP_VERSION', '7.1.1');
define('SGAP_FILE', __FILE__);

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
        'sgap_repost_max' => 3
    ];

    public static function activate(): void {
        self::install();
    }

    public static function deactivate(): void {
        self::clear_cron();
    }

    /** Runs on admin requests only; one autoloaded option read when nothing to do. */
    public static function maybe_upgrade(): void {
        if (get_option('sgap_version') !== SGAP_VERSION) {
            self::install();
        }
    }

    public static function clear_cron(): void {
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

        // Old versions scheduled these; nothing handles them now, so they
        // would only keep waking WP-Cron for no reason.
        self::clear_cron();

        update_option('sgap_version', SGAP_VERSION);
    }
}
register_activation_hook(__FILE__, ['SGAP_Install', 'activate']);
register_deactivation_hook(__FILE__, ['SGAP_Install', 'deactivate']);

/* ---------------- SETTINGS ---------------- */
class SGAP_Settings {
    const INT_KEYS = [
        'daily_cap','batch_size','quiet_start','quiet_end','labor_rate','monthly_budget',
        'target_roi','winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max'
    ];
    const TEXT_KEYS = [
        'autopilot_enabled','full_auto_enabled','pinterest_token','instagram_token',
        'telegram_bot_token','telegram_chat_id','email_test_recipient','tiktok_token',
        'youtube_token','youtube_channel_id','reddit_username','reddit_subreddit',
        'etsy_shop_id','etsy_oauth_token','etsy_refresh_token','etsy_shipping_profile_id',
        'etsy_return_policy_id','ai_provider','ai_api_key','ai_model','ai_mode',
        'merchant_feed_secret'
    ];

    public static function get(string $key, $default = '') {
        return get_option('sgap_' . $key, $default);
    }
    public static function set(string $key, $value): void {
        update_option('sgap_' . $key, $value);
    }
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register']);
    }
    public static function register(): void {
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
        add_action('admin_init', ['SGAP_Install', 'maybe_upgrade']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sgap_approve_ai', [__CLASS__, 'approve_ai']);
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
        if (!is_array($stats)) {
            $stats = [];
            foreach (['sgap_queue' => 'Queue', 'sgap_ai_drafts' => 'AI drafts', 'sgap_events' => 'Events'] as $t => $label) {
                $table = $wpdb->prefix . $t;
                $stats[$label] = self::table_exists($table)
                    ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}")
                    : null;
            }
            set_transient('sgap_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS);
        }
        echo '<p>Version ' . esc_html(SGAP_VERSION) . ' &middot; Autopilot: <strong>'
            . esc_html(SGAP_Settings::get('autopilot_enabled', 'no')) . '</strong></p>';
        echo '<table class="widefat striped" style="max-width:420px"><tbody>';
        foreach ($stats as $label => $count) {
            echo '<tr><td>' . esc_html($label) . '</td><td>'
                . ($count === null ? '<em>table missing &ndash; deactivate and reactivate the plugin</em>' : esc_html((string) $count))
                . '</td></tr>';
        }
        echo '</tbody></table>';
        self::close();
    }

    public static function settings(): void {
        self::open('Settings');
        echo '<form method="post" action="options.php">';
        settings_fields('sgap_settings');
        echo '<table class="form-table"><tbody>';
        foreach (array_merge(SGAP_Settings::INT_KEYS, SGAP_Settings::TEXT_KEYS) as $key) {
            $secret = (bool) preg_match('/token|key|secret/', $key);
            printf(
                '<tr><th scope="row"><label for="sgap_%1$s">%1$s</label></th><td><input type="%2$s" id="sgap_%1$s" name="sgap_%1$s" value="%3$s" class="regular-text" autocomplete="off"></td></tr>',
                esc_attr($key),
                $secret ? 'password' : 'text',
                esc_attr((string) SGAP_Settings::get($key))
            );
        }
        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        self::close();
    }

    public static function queue(): void {
        global $wpdb;
        self::open('Queue');
        $table = $wpdb->prefix . 'sgap_queue';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT id, channel, title, status, scheduled_at, published_at FROM {$table} ORDER BY id DESC LIMIT 50")
            : [];
        self::render_rows($rows, ['id', 'channel', 'title', 'status', 'scheduled_at', 'published_at']);
        self::close();
    }

    public static function ai(): void {
        global $wpdb;
        self::open('AI Drafts');
        if (isset($_GET['approved'])) {
            echo '<div class="notice notice-success"><p>Draft approved.</p></div>';
        }
        $table = $wpdb->prefix . 'sgap_ai_drafts';
        $rows = self::table_exists($table)
            ? $wpdb->get_results("SELECT id, channel, title, status, created_at FROM {$table} WHERE status = 'pending' ORDER BY id DESC LIMIT 50")
            : [];
        if (!$rows) {
            echo '<p>No pending drafts.</p>';
            self::close();
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Channel</th><th>Title</th><th>Created</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($row->channel) . '</td><td>'
                . esc_html($row->title) . '</td><td>' . esc_html($row->created_at) . '</td><td>'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="sgap_approve_ai">'
                . '<input type="hidden" name="draft_id" value="' . esc_attr($row->id) . '">'
                . wp_nonce_field('sgap_approve_ai_' . $row->id, '_wpnonce', true, false)
                . '<button class="button button-primary">Approve</button></form></td></tr>';
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
        if ($id) {
            $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => 'approved'], ['id' => $id]);
            delete_transient('sgap_dashboard_stats');
        }
        wp_safe_redirect(add_query_arg(['page' => 'sgap-ai', 'approved' => 1], admin_url('admin.php')));
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

// Settings must register on every request (options.php saves go through admin),
// but the admin UI is only loaded inside wp-admin, so the frontend pays nothing.
SGAP_Settings::init();
if (is_admin()) {
    SGAP_Admin::init();
}
