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
    const EVERGREEN_CRON = 'sgap_evergreen';
    const QUARTER_HOUR = 'sgap_quarter_hour';
    const MCP_CRON = 'sgap_mcp_refresh';
    const MCP_HALF_HOUR = 'sgap_mcp_half_hour';

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
        'sgap_locale_source' => 'en',
        'sgap_locales' => '',
        'sgap_bluesky_handle' => '',
        'sgap_bluesky_app_password' => '',
        'sgap_mastodon_instance' => '',
        'sgap_mastodon_token' => '',
        'sgap_mastodon_limit' => 500,
        'sgap_instagram_user_id' => '',
        // graph.instagram.com for Instagram Login, graph.facebook.com for Facebook Login.
        'sgap_instagram_graph_host' => 'graph.instagram.com',
        'sgap_telegram_bot_token' => '',
        'sgap_telegram_chat_id' => '',
        'sgap_utm_campaign' => 'steamgears_premium_evergreen',
        'sgap_ai_provider' => 'anthropic',
        'sgap_publish_mode' => 'rss',
        'sgap_auto_images' => 'yes',
        'sgap_auto_seo' => 'yes',
        'sgap_auto_publish_articles' => 'no',
        'sgap_weekly_report' => 'yes',
        'sgap_alert_mode' => 'immediate',
        'sgap_alert_email' => '',
        'sgap_alert_dry_days' => 2,
        'sgap_channel_caps' => '',
        'sgap_audit_keep_days' => 90,
        'sgap_dupe_days' => 30,
        'sgap_ai_profile' => 'assistant',
        'sgap_articles_per_week' => 0,
        'sgap_rescue_hosts' => 'steamgears.juno.page',
        'sgap_evergreen_enabled' => 'no',
        'sgap_evergreen_after_days' => 60,
        'sgap_evergreen_max_shares' => 3,
        'sgap_evergreen_batch' => 1,
        'sgap_mcp_client_id' => '',
        'sgap_printful_api_key' => '',
        'sgap_printful_store_id' => '',
        'sgap_woo_min_margin' => 45
    ];

    public static function activate(): void {
        self::install();
    }

    public static function deactivate(): void {
        self::clear_legacy_cron();
        foreach ([self::CLEANUP_CRON, self::PUBLISH_CRON, self::GENERATE_CRON, self::EVERGREEN_CRON, self::MCP_CRON] as $hook) {
            SGAP_Scheduler::unschedule($hook);
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
        // The MCP access token lives 3600 s and the refresh token is rotated on use:
        // half an hour between renewals leaves room for one failed attempt.
        $schedules[self::MCP_HALF_HOUR] = ['interval' => 30 * MINUTE_IN_SECONDS, 'display' => 'Every 30 minutes (Steamgears MCP)'];
        return $schedules;
    }

    public static function clear_legacy_cron(): void {
        foreach (self::LEGACY_CRON as $hook) {
            SGAP_Scheduler::unschedule($hook);
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
        remind TINYINT(1) NOT NULL DEFAULT 0,
        locale VARCHAR(10) NOT NULL DEFAULT '',
        source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
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
        KEY fingerprint (fingerprint),
        KEY source_locale (source_id,locale)
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

        dbDelta("CREATE TABLE {$wpdb->prefix}sgap_publish_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        channel VARCHAR(40) NOT NULL,
        outcome VARCHAR(20) NOT NULL,
        http_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        actor VARCHAR(20) NOT NULL,
        message VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY queue_id (queue_id),
        KEY created_at (created_at),
        KEY outcome_created (outcome,created_at),
        KEY channel_created (channel,created_at)
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
        // [interval in seconds, WP-Cron recurrence name, first-run delay]
        foreach ([
            self::CLEANUP_CRON => [DAY_IN_SECONDS, 'daily', HOUR_IN_SECONDS],
            self::GENERATE_CRON => [DAY_IN_SECONDS, 'daily', 10 * MINUTE_IN_SECONDS],
            self::EVERGREEN_CRON => [DAY_IN_SECONDS, 'daily', 20 * MINUTE_IN_SECONDS],
            self::PUBLISH_CRON => [15 * MINUTE_IN_SECONDS, self::QUARTER_HOUR, 5 * MINUTE_IN_SECONDS],
            self::MCP_CRON => [30 * MINUTE_IN_SECONDS, self::MCP_HALF_HOUR, 12 * MINUTE_IN_SECONDS]
        ] as $hook => [$interval, $recurrence, $delay]) {
            SGAP_Scheduler::recurring($hook, $interval, [], $delay, $recurrence);
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
    const BOOL_KEYS = ['autopilot_enabled', 'full_auto_enabled', 'auto_images', 'auto_seo', 'auto_publish_articles', 'weekly_report', 'evergreen_enabled'];
    const HOUR_KEYS = ['quiet_start', 'quiet_end'];
    const INT_KEYS = [
        'daily_cap','batch_size','labor_rate','monthly_budget','target_roi',
        'winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max','posts_per_day','articles_per_week','alert_dry_days','audit_keep_days','dupe_days','mastodon_limit',
        'evergreen_after_days','evergreen_max_shares','evergreen_batch',
        'woo_min_margin'
    ];
    /** Never echoed back to the browser; can be overridden by SGAP_<KEY> constants in wp-config.php. */
    const SECRET_KEYS = [
        'publer_api_key','ai_api_key','image_api_key','serpapi_key',
        'telegram_bot_token',
        'merchant_feed_secret','bluesky_app_password','mastodon_token',
        'mcp_refresh_token','printful_api_key',
        'pinterest_access_token','pinterest_refresh_token','pinterest_client_secret',
        'instagram_access_token',
        'facebook_access_token'
    ];
    /** Multi-line text settings, shown as a textarea. */
    const TEXTAREA_KEYS = ['blackout_ranges', 'banned_words'];
    const TEXT_KEYS = [
        'publer_workspace_id','publer_pinterest_board','channels','ai_model','ai_base_url','image_model','content_language','rescue_hosts',
        'telegram_chat_id',
        'alert_email','channel_caps',
        'blackout_ranges','banned_words','utm_campaign','bluesky_handle','mastodon_instance',
        'pinterest_client_id','instagram_user_id','facebook_page_id',
        'locale_source','locales','command_center_url','mcp_client_id',
        'printful_store_id'
    ];
    /** Settings with a fixed list of values, shown as a select. */
    const CHOICE_KEYS = [
        'publish_mode' => ['rss' => 'RSS feeds (Publer Professional)', 'api' => 'Publer API (Publer Business)'],
        'ai_provider' => ['anthropic' => 'Anthropic (Claude)', 'aimlapi' => 'AIMLAPI (Claude and 300+ other models)', 'openai_compatible' => 'Other OpenAI-compatible API (OpenAI, OpenRouter, ...)'],
        'quality_gate' => ['block' => 'Block — keep the post out of the queue until it is fixed', 'warn' => 'Warn — queue it and report what is wrong', 'off' => 'Off — do not check'],
        'instagram_graph_host' => ['graph.instagram.com' => 'Instagram Login (graph.instagram.com) — scopes instagram_business_basic and instagram_business_manage_insights', 'graph.facebook.com' => 'Facebook Login (graph.facebook.com) — scopes instagram_basic and instagram_manage_insights; the token cannot renew itself'],
        'facebook_graph_version' => ['v21.0' => 'v21.0 (current)', 'v20.0' => 'v20.0', 'v19.0' => 'v19.0'],
        'facebook_direct' => ['no' => 'No — keep publishing through Publer, read the page only', 'yes' => 'Yes — publish to the page directly with the Graph API'],
        'alert_mode' => ['immediate' => 'Immediate — one mail per failed post, plus one when the queue runs dry', 'daily' => 'Daily digest — one mail a day from the self-check', 'off' => 'Off — the dashboard still shows the problems'],
        'ai_profile' => ['assistant' => 'Autopilot assistant — drafts, queue and results', 'site_admin' => 'Site administration — full access, everything on the site', 'wp_seo_expert' => 'WordPress SEO expert — content and SEO, no ops']
    ];

    /** Shown in the settings page. Keys not listed here stay registered for older data but are unused. */
    const SECTIONS = [
        'General' => ['autopilot_enabled','full_auto_enabled','daily_cap','batch_size','quiet_start','quiet_end','blackout_ranges','quality_gate','banned_words','dupe_days','utm_campaign','command_center_url'],
        'Publer' => ['publish_mode','publer_api_key','publer_workspace_id','publer_pinterest_board','pinterest_client_id','instagram_user_id','instagram_graph_host','facebook_page_id','facebook_access_token','facebook_graph_version','facebook_direct','channels','bluesky_handle','bluesky_app_password','mastodon_instance','mastodon_token','mastodon_limit','telegram_bot_token','telegram_chat_id'],
        'AI content' => ['ai_profile','ai_provider','ai_api_key','ai_model','ai_base_url','posts_per_day','content_language','locale_source','locales','image_model','image_api_key','serpapi_key'],
        'Economics' => ['labor_rate','monthly_budget','target_roi','winner_min_clicks','loser_min_clicks','repost_cooldown_days','repost_max'],
        'Evergreen' => ['evergreen_enabled','evergreen_after_days','evergreen_max_shares','evergreen_batch'],
        'Alerts and log' => ['alert_mode','alert_email','alert_dry_days','channel_caps','audit_keep_days'],
        'Shop & feeds' => ['merchant_feed_secret','printful_api_key','printful_store_id','woo_min_margin'],
        'MCP access' => ['mcp_client_id','mcp_refresh_token']
    ];

    const HELP = [
        'evergreen_enabled' => 'Keep promoting pages that keep earning: once a page is marked, it is recycled with a freshly written caption after the waiting period, until it has used its share budget or reached its expiry date.',
        'evergreen_after_days' => 'How long a page waits between two shares. The same clock the normal generator uses, so a recycled page is not also promoted as new the same week.',
        'evergreen_max_shares' => 'Default budget per page: how many recycled posts it may produce. A page can override it in the editor, and 0 there means this number.',
        'evergreen_batch' => 'How many evergreen pages may be recycled per day, each on its own channel in rotation. 1 keeps the queue balanced; the button on the Evergreen page can recycle more at once.',
        'command_center_url' => 'Address of your Steamgears Command Center dashboard, e.g. https://cc.example.com. Adds a Log ind button to the dashboard. Empty = no button.',
        'ai_profile' => 'Who the AI Chat is and what it may touch. Autopilot assistant: drafts, queue and results, with the site-changing tools withheld. Site administration: every tool the site has, and AI Engine full REST access is switched on when the profile is chosen. WordPress SEO expert: content, SEO fields, products and media, with no plugin or settings work. Switching away from site administration does not turn full REST access off — that is the switch below it.',
        'dupe_days' => 'How far back an identical post blocks a new one: same channel, destination and title. 30 days by default, 0 = the whole history. A post that is still queued always blocks, however old it is.',
        'locale_source' => 'The language the source posts are written in. Every other language in the list gets its own translated caption.',
        'locales' => 'Comma-separated languages, e.g. da,sv,de. A queued post also queues one translated caption per language, linking to that language\'s version of the page (WPML or Polylang). Languages without a translated page are reported, not posted. Needs an AI API key.',
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
        'channels' => 'Instagram and Pinterest posts need a photo; the Content Studio can create one. Comma-separated. Add bluesky to post there directly, without Publer — it needs the handle and an app password below, and is skipped (through Publer) until both are filled in.',
        'bluesky_handle' => 'Your Bluesky handle, e.g. steamgears.bsky.social. The @ is optional. Used together with an app password to post directly; an email address works too but the handle is what readers see.',
        'bluesky_app_password' => 'Bluesky → Settings → Privacy and security → App passwords (not the account password). Stays on this server: it is never shown again after saving, and an empty field keeps the stored value.',
        'mastodon_instance' => 'Address of the server the account lives on, e.g. mastodon.social. There is no central Mastodon, so the token only works on the instance it was created on.',
        'mastodon_token' => 'Mastodon → Preferences → Development → New application, then the access token of that application (scopes: write:statuses). Never shown again after saving; an empty field keeps the stored value.',
        'instagram_user_id' => 'The Instagram professional account id (starts with 1784…), not the @handle: Meta app dashboard → Instagram → API setup with Instagram login, or `GET /me?fields=id,username` with the token. Insights only exist for business and creator accounts you manage.',
        'instagram_access_token' => 'A long-lived Instagram User token (60 days). With Instagram Login it is refreshed in place on every run once it is at least 24 hours old, so it never expires as long as the cron runs; with Facebook Login it has to be replaced by hand. Never shown again after saving; an empty field keeps the stored value.',
        'instagram_graph_host' => 'Which Graph host the token belongs to. The two login types issue tokens to different hosts, so this is a setting instead of a guess: pointing an Instagram Login token at graph.facebook.com answers 400.',
        'facebook_page_id' => 'The numeric id of the Facebook Page, not the @name. Found under the page\'s About → Page transparency, or with GET /me/accounts using a user token that manages the page.',
        'facebook_access_token' => 'A Page access token (Facebook Login for Business → the page). It is what writes as the page; a user token cannot. Scopes: pages_show_list, pages_read_engagement, pages_manage_posts, and pages_manage_engagement for answers and hiding. Never shown again after saving; an empty field keeps the stored value.',
        'facebook_graph_version' => 'The Graph API version the calls use. Meta retires old versions after about two years, so this is a setting and not a constant in the code.',
        'facebook_direct' => 'Off by default on purpose: the page can be connected just to be read, and a key must not move publishing out of Publer on its own. On: queue posts on the facebook channel go straight to the page.',
        'pinterest_client_id' => 'The App ID of your Pinterest developer app (developers.pinterest.com → My apps). Not a secret — it is sent with the secret to renew the access token. Analytics need the boards:read and pins:read scopes, and only work for pins the token account owns.',
        'mastodon_limit' => 'Characters the instance accepts in one post. 500 is what every server allows; raise it only when the instance documents a higher limit. The tracking link is always kept whole.',
        'telegram_bot_token' => 'BotFather → /newbot (or /token for an existing bot). The token is what authorises the message; it is never shown again after saving.',
        'telegram_chat_id' => 'Where the message lands: @channelname for a public channel, or the numeric id (a group is negative). The bot must be an administrator of the channel, or the API answers ok=false.',
        'daily_cap' => 'Never more than this many posts released per day, across all channels.',
        'batch_size' => 'How many due posts are released per 15-minute run.',
        'quiet_start' => 'No posts are released from this hour …',
        'quiet_end' => '… until this hour (site time).',
        'blackout_ranges' => 'Days where nothing is released, one range per line: 2026-12-24..2026-12-26 Christmas. Queued posts that land in a range are moved to the next free day at the same time of day — not dropped, and not saved up for one big day afterwards.',
        'quality_gate' => 'Checked before a post joins the queue: banned words, dead links, a photo without alt text, and a caption that already went out on the same channel. Block: the post is refused with the reason, and you can queue it anyway from the tool that refused it. Warn: it joins the queue and the problems are reported.',
        'banned_words' => 'Words that keep a post out of the queue, one per line or comma-separated. Whole words only, so “sale” does not catch “wholesale”; hashtags count.',
        'repost_cooldown_days' => 'A page is not promoted again within this many days.',
        'auto_images' => 'Instagram and Pinterest posts without a photo get the page\'s featured image or an AI photo (AIMLAPI, about $0.045 each).',
        'auto_seo' => 'New posts and products without a Rank Math description get a focus keyword, meta title and description from the AI when published.',
        'articles_per_week' => 'The AI picks topics the site does not cover yet and writes this many SEO articles a week. 0 = off.',
        'auto_publish_articles' => 'Off: weekly articles wait as drafts for you. On: they are published straight away.',
        'weekly_report' => 'A short e-mail every week: posts, clicks, orders per channel and anything that needs you.',
        'alert_mode' => 'Immediate: a mail the moment a post gives up after three attempts, and one when the queue has less than the warning level left. Daily: the same information collected into one mail after the daily self-check. Off: nothing.',
        'alert_email' => 'Where alerts go. Empty = the site admin address.',
        'alert_dry_days' => 'Warn when the queue has fewer days of posts left than this, measured as queued posts divided by the daily cap. 0 = never warn.',
        'channel_caps' => 'Optional cap per channel, one per line or comma-separated: pinterest=2, facebook=3. Each channel then gets its own runway, and a channel with a cap warns when its own queue runs below the warning level — a healthy total can hide a channel that is about to stop. A channel without a cap is measured against Max posts per day.',
        'audit_keep_days' => 'How long the publish log is kept: one row per attempt, with channel, HTTP status, response and who started it. 0 = keep everything.',
        'rescue_hosts' => 'Images on these servers are copied into your media library and every link is rewritten (comma-separated).',
        'utm_campaign' => 'The campaign name every link from this site carries as utm_campaign: set it once per campaign (e.g. clockwork_punk_2026_11) and GA4 can tell campaigns apart. A single post can override it with its own campaign. Letters, digits, -            and _ only; empty = steamgears_premium_evergreen. Posts queued before the change keep the value they were queued with.',
        'mcp_client_id' => 'The public client ID from the one-time MCP approval (tools/mcp_oauth.py --start prints it). It is not a secret.',
        'mcp_refresh_token' => 'The refresh token from that approval (tools/mcp_oauth.py --finish saves it in ~/.config/steamgears-mcp/tokens.json). The plugin renews the access token every 30 minutes and writes the rotated refresh token back here, so it must live in the database — a wp-config.php constant cannot be updated by the server. Never shown again after saving; an empty field keeps the stored value.',
        'printful_api_key' => 'Printful API token (Dashboard → Settings → API). Optional: it lets the shop tools read the real cost price of a sync product instead of asking you to fill in the cost field on every product. Not the same key as AIMLAPI.',
        'printful_store_id' => 'The Printful store id the token belongs to (Dashboard → Stores). Sent as X-PF-Store-Id; empty means the token\'s default store.',
        'woo_min_margin' => 'The margin a price must keep above the cost price, in percent. The shop tools never propose or apply a price below cost × (1 + this), rounded up to .95. 45 % on a cost of 100 refuses anything under 145.95.'
    ];

    public static function get(string $key, $default = '') {
        if (in_array($key, self::SECRET_KEYS, true) && self::constant_name($key)) {
            return constant(self::constant_name($key));
        }
        return get_option('sgap_' . $key, $default);
    }
    public static function set(string $key, $value): void {
        // Secrets stay out of the autoloaded options blob, also when a write
        // happens outside the install routine (key test, wizard, abilities).
        $secret = in_array($key, self::SECRET_KEYS, true);
        $before = $secret ? (string) get_option('sgap_' . $key, '') : '';
        update_option('sgap_' . $key, $value, $secret ? false : null);
        // This path bypasses options.php, so the change log is told directly; the settings
        // hook sees the same write and is deduped inside SGAP_Keys.
        if ($secret && class_exists('SGAP_Keys')) {
            SGAP_Keys::record($key, $before, (string) $value);
        }
    }
    public static function enabled(string $key): bool {
        return self::get($key, 'no') === 'yes';
    }
    /** Empty means "use the default", never an empty utm_campaign in the link. */
    public static function sanitize_campaign($value): string {
        return SGAP_UTM::campaign((string) $value, true);
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
                'sanitize_callback' => match (true) {
                    // A campaign name ends up in a URL: strip everything a query
                    // string would have to encode, and never save an empty one.
                    $key === 'locales' => [__CLASS__, 'sanitize_locales'],
                    $key === 'locale_source' => [__CLASS__, 'sanitize_locale_code'],
                    $key === 'utm_campaign' => [__CLASS__, 'sanitize_campaign'],
                    $key === 'command_center_url' => 'esc_url_raw',
                    // A model the provider does not offer is what broke the chat once: refuse it
                    // at the door, and let the owner pick one from the dropdown instead.
                    $key === 'ai_model' => [__CLASS__, 'sanitize_model'],
                    // sanitize_text_field would flatten the newlines that separate the
                    // blackout ranges, leaving only the first range saved.
                    in_array($key, self::TEXTAREA_KEYS, true) => 'sanitize_textarea_field',
                    default => 'sanitize_text_field'
                }
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
        // ai_model holds the concrete id in use, so a provider switch must not leave the old
        // provider's id there: it is not offered by the new one, and the first call would fail
        // before the fallback repaired it. Re-resolve it to the new provider's default instead.
        add_action('update_option_sgap_ai_provider', [__CLASS__, 'keep_provider_model']);
        add_action('add_option_sgap_ai_provider', [__CLASS__, 'keep_provider_model']);
        // The catalogue belongs to the endpoint: keeping a list fetched from the old address
        // would reject a model the new provider offers for up to 12 hours. The key is left
        // alone on purpose — an invalid key must not turn an empty list into "accept anything".
        add_action('update_option_sgap_ai_base_url', [__CLASS__, 'clear_model_catalog']);
        add_action('add_option_sgap_ai_base_url', [__CLASS__, 'clear_model_catalog']);
    }

    /** Drops the cached model lists so the next read asks the provider that is configured now. */
    public static function clear_model_catalog(): void {
        foreach (['anthropic', 'aimlapi', 'openai_compatible'] as $provider) {
            delete_site_transient('sgap_ai_catalog_' . $provider);
        }
    }

    /**
     * Replaces a stored model the current provider does not offer with that provider's default.
     * Runs when ai_provider changes, and does nothing while the catalogue cannot be read, so an
     * unreachable provider never wipes a model that might well be fine.
     */
    public static function keep_provider_model(): void {
        $stored = trim((string) get_option('sgap_ai_model', ''));
        if ($stored === '') {
            return;
        }
        $models = SGAP_AI::catalog();
        if ($models === [] || in_array($stored, $models, true)) {
            return;
        }
        update_option('sgap_ai_model', SGAP_AI::default_model());
    }
    /** 'DA, sv ,da' becomes 'da,sv': no junk, no duplicates, never the source language. */
    public static function sanitize_locales($value): string {
        return implode(',', SGAP_Locales::codes((string) $value));
    }

    /** A language code, or the source language when the value is not one. */
    public static function sanitize_locale_code($value): string {
        return SGAP_Locales::code((string) $value, SGAP_Locales::DEFAULT_SOURCE);
    }

    /**
     * Only a model the provider actually lists may be stored; empty means "provider default".
     * When the list cannot be read at all, the typed name is kept, so a provider that is briefly
     * unreachable never blocks saving the rest of the form.
     */
    public static function sanitize_model($value): string {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === '') {
            // "Provider default" is stored as the model it resolves to, so the field says what is
            // actually used instead of depending on a later change of provider or of that default.
            return SGAP_AI::default_model();
        }
        $models = SGAP_AI::catalog();
        if ($models === [] || in_array($value, $models, true)) {
            return $value;
        }
        $previous = trim((string) get_option('sgap_ai_model', ''));
        if (function_exists('add_settings_error')) {
            add_settings_error('sgap_ai_model', 'sgap_model_unknown', sprintf(
                '%s does not offer "%s", so the model was left unchanged. Pick one from the list.',
                SGAP_AI::provider_name(),
                $value
            ));
        }
        return $previous;
    }
}

/* ---------------- UTM ---------------- */
class SGAP_UTM {
    /** What every link carried before the campaign was a setting: kept so old links stay comparable. */
    const DEFAULT_CAMPAIGN = 'steamgears_premium_evergreen';

    public static function build(array $args): string {
        $args = wp_parse_args($args, [
            'channel' => '',
            'angle' => 'angle_a',
            'content_type' => 'product',
            'format' => 'pin',
            'campaign' => '',
            'destination_url' => home_url('/')
        ]);
        return add_query_arg(array_map('rawurlencode', [
            'utm_source' => $args['channel'],
            'utm_medium' => 'social',
            'utm_campaign' => self::campaign((string) $args['campaign']),
            'utm_content' => $args['angle'] . '_' . $args['content_type'] . '_' . $args['format']
        ]), $args['destination_url']);
    }

    /**
     * The campaign name for one link: the post's own campaign, else the site-wide
     * setting, else the historical default. Never empty, always URL-safe.
     */
    public static function campaign(string $campaign = '', bool $default_only = false): string {
        $campaign = sanitize_key($campaign);
        if ($campaign === '' && !$default_only) {
            $campaign = sanitize_key((string) SGAP_Settings::get('utm_campaign', self::DEFAULT_CAMPAIGN));
        }
        return $campaign === '' ? self::DEFAULT_CAMPAIGN : substr($campaign, 0, 60);
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
    /**
     * Hvor mange arbejdstimer en sum svarer til ved ejerens timepris.
     * `labor_rate` blev registreret i 7.15.0 og laest af intet; den bruges nu her,
     * saa ROI-kortet kan sige hvad omsaetningen er vaerd i ejerens timer.
     * Null naar prisen er 0, saa linjen kan skjules i stedet for at vise uendeligt.
     */
    public static function hours(float $amount, float $rate): ?float {
        return $rate > 0 ? round($amount / $rate, 1) : null;
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

    /**
     * A /go/<id>/ that is not a queue item is a product short link (guides, print, campaigns).
     * It resolves to the product permalink; anything unresolvable still lands on the front page.
     */
    public static function product_target(int $id): string {
        if ($id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($id);
            if ($product && method_exists($product, 'get_permalink')) {
                $url = (string) $product->get_permalink();
                if ($url !== '') {
                    return $url;
                }
            }
        }
        return home_url('/');
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
            // No queue row with this id: treat it as a product short link instead of the front page.
            wp_redirect(self::product_target($id), 302, 'Steamgears');
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
    /** Minutes to wait before the next attempt, indexed by attempt number - 1. */
    const BACKOFF_MINUTES = [5, 15, 45];

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
        // Per-post campaign: a twelve-post campaign can be told apart from the
        // evergreen stream in GA4 without changing the setting for everything else.
        $campaign = self::key($item['campaign'] ?? '', '', 60);
        // P2.5: a variant knows its language and the queued post it was made from,
        // so a translated caption never looks like an independent post.
        $locale = self::key($item['locale'] ?? '', '', 10);
        $source_id = absint($item['source_id'] ?? 0);
        if (SGAP_Settings::enabled('full_auto_enabled') && self::suppressed($channel, $angle, $content_type, $format)) {
            return new WP_Error('sgap_suppressed', 'Combination is currently suppressed.', ['status' => 422]);
        }
        // P0.6: the fingerprint is channel + destination + title, so a second writer
        // (Command Center, a script, an MCP client) cannot queue the same post twice.
        $fingerprint = SGAP_Duplicate::fingerprint($channel, $destination, $title);
        $existing = SGAP_Duplicate::find($fingerprint);
        if ($existing !== null) {
            return new WP_Error('sgap_duplicate', SGAP_Duplicate::message($existing), [
                'status' => 409,
                'id' => (int) $existing['id'],
                'fingerprint' => $fingerprint
            ]);
        }
        // A blackout moves the post instead of refusing it: the owner asked for "not
        // on these days", not "not at all".
        $scheduled = (string) (($item['scheduled_at'] ?? '') ?: current_time('mysql'));
        if (SGAP_Blackout::hits($scheduled)) {
            $scheduled = SGAP_Blackout::next_free($scheduled);
        }
        $row = [
            'post_id' => absint($item['post_id'] ?? 0),
            'channel' => $channel,
            'angle' => $angle,
            'content_type' => $content_type,
            'format' => $format,
            // P1.2: hand this one to the owner instead of trying to post a format the API rejects.
            'remind' => !empty($item['remind']) ? 1 : 0,
            'locale' => $locale,
            'source_id' => $source_id,
            'destination_url' => $destination,
            'title' => $title,
            'body' => sanitize_textarea_field((string) ($item['body'] ?? '')),
            'image_url' => esc_url_raw((string) ($item['image_url'] ?? '')),
            'status' => ($item['status'] ?? '') === 'draft' ? 'draft' : 'queued',
            'scheduled_at' => $scheduled,
            'fingerprint' => $fingerprint
        ];
        $row['utm_url'] = SGAP_UTM::build(['campaign' => $campaign] + $row);
        // The quality gate sits here and not in the callers, so a new way into the
        // queue cannot forget it. `force` queues the post anyway and reports the
        // problems as warnings: a deliberate repost is the owner's call, not ours.
        $gate = SGAP_Quality::check($row, !empty($item['force']));
        if (!$gate['ok']) {
            return new WP_Error('sgap_quality', SGAP_Quality::message(SGAP_Quality::blocking($gate['problems'])), [
                'status' => 422,
                'problems' => $gate['problems']
            ]);
        }
        if (!$wpdb->insert(self::table(), $row)) {
            return new WP_Error('sgap_db', 'Could not save.', ['status' => 500]);
        }
        $id = (int) $wpdb->insert_id;
        delete_transient('sgap_dashboard_stats');
        // Say now what Publer would reject later: a missing photo, a PNG on Instagram,
        // no Pinterest board. Warnings only — the photo can still arrive later (featured
        // image or an AI photo), so nothing is refused at this point.
        $warnings = array_column(SGAP_Preflight::problems($channel, $row), 'message');
        // A forced post carries the gate's findings with it, so "queued anyway" is never silent.
        foreach ($gate['problems'] as $problem) {
            $warnings[] = (string) ($problem['message'] ?? '');
        }
        // P2.5: the source post also queues one translated caption per locale. A variant
        // never expands again (source_id is set), and a language that cannot be translated
        // is reported here instead of vanishing: the source post stays queued.
        $variants = [];
        $locale_errors = [];
        if ($source_id === 0 && SGAP_Locales::enabled()) {
            $expanded = SGAP_Locales::expand($row, $id);
            foreach ($expanded['items'] as $variant) {
                $created = self::add($variant);
                if (is_wp_error($created)) {
                    $locale_errors[] = ['locale' => $variant['locale'], 'error' => $created->get_error_message()];
                    continue;
                }
                $variants[] = ['locale' => $variant['locale'], 'id' => (int) $created['id']];
            }
            $locale_errors = array_merge($locale_errors, $expanded['errors']);
        }
        return ['id' => $id, 'tracking_url' => SGAP_Tracking::url($id), 'utm_url' => $row['utm_url'], 'warnings' => array_values(array_filter($warnings)), 'scheduled_at' => $scheduled, 'remind' => (int) $row['remind'], 'locales' => $variants, 'locale_errors' => $locale_errors];
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
        if (SGAP_Blackout::active() !== null) {
            return 'blackout';
        }
        if (self::in_quiet_hours((int) wp_date('G'), (int) SGAP_Settings::get('quiet_start', 23), (int) SGAP_Settings::get('quiet_end', 7))) {
            return 'quiet_hours';
        }
        return self::capacity() <= 0 ? 'daily_cap_reached' : '';
    }

    /** blocked_reason() in words, for the admin and the Claude tools. */
    public static function blocked_message(string $reason = ''): string {
        $reason = $reason !== '' ? $reason : self::blocked_reason();
        if ($reason === 'blackout') {
            $range = SGAP_Blackout::active();
            return sprintf('blackout %s to %s%s: nothing is released, and queued posts inside the range move to %s',
                (string) ($range['from'] ?? ''), (string) ($range['to'] ?? ''),
                $range && $range['label'] !== '' ? ' (' . $range['label'] . ')' : '',
                substr(SGAP_Blackout::next_free(current_time('mysql')), 0, 16));
        }
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

    /**
     * Channels with their own cap (channel_caps) that have used it up today, counting posts
     * published or still sending. Only the publisher checks this; the queue is untouched.
     */
    public static function full_channels(): array {
        global $wpdb;
        if (!class_exists('SGAP_Alerts')) {
            return [];
        }
        $caps = SGAP_Alerts::caps();
        if (!$caps) {
            return [];
        }
        $today = wp_date('Y-m-d 00:00:00');
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT channel, COUNT(*) AS n FROM ' . self::table() . " WHERE published_at >= %s OR (status = 'sending' AND sent_at >= %s AND (published_at IS NULL OR published_at < %s)) GROUP BY channel",
            $today, $today, $today
        ), ARRAY_A);
        $used = [];
        foreach ($rows as $row) {
            $used[sanitize_key((string) ($row['channel'] ?? ''))] = (int) ($row['n'] ?? 0);
        }
        $full = [];
        foreach ($caps as $channel => $cap) {
            if (($used[$channel] ?? 0) >= $cap) {
                $full[] = $channel;
            }
        }
        return $full;
    }

    /** Takes the next due items and marks them 'sending' so nobody else picks them up. */
    public static function claim(int $limit, string $channel = ''): array {
        global $wpdb;
        if ($limit <= 0) {
            return [];
        }
        $where = $channel !== '' ? $wpdb->prepare(' AND channel = %s', $channel) : '';
        if ($channel === '') {
            // channel_caps is a real ceiling: a channel that has reached its own daily cap is
            // skipped here, so the rest of the queue still goes out and the capped one waits.
            $full = self::full_channels();
            if ($full) {
                $where = ' AND channel NOT IN (' . implode(',', array_fill(0, count($full), '%s')) . ')';
                $where = $wpdb->prepare($where, $full);
            }
        }
        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT id, post_id, channel, angle, content_type, format, remind, destination_url, title, body, image_url, utm_url, attempts, scheduled_at
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
    /** Delay before the next attempt of a failed item: 5, then 15, then 45 minutes. */
    public static function retry_delay_minutes(int $attempts): int {
        $steps = self::BACKOFF_MINUTES;
        return $steps[max(0, min(count($steps) - 1, $attempts - 1))];
    }

    public static function mark(int $id, string $outcome, string $error = ''): ?string {
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare('SELECT channel, angle, attempts FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$item) {
            return null;
        }
        if ($outcome === 'published') {
            $data = ['status' => 'published', 'published_at' => current_time('mysql'), 'error' => null];
        } elseif ($outcome === 'reminded') {
            // P1.2: the owner has been mailed and will post it by hand. Terminal, never a failure.
            $data = ['status' => 'reminded', 'error' => null, 'external_id' => ''];
        } else {
            $retry = (int) $item->attempts < self::MAX_ATTEMPTS;
            $data = [
                'status' => $retry ? 'queued' : 'failed',
                'error' => sanitize_textarea_field($error),
                'external_id' => ''
            ];
            if ($retry) {
                // Back off instead of spending every attempt inside the same 15-minute window:
                // a token that expired at 09:00 is not fixed by 09:15.
                $data['scheduled_at'] = wp_date('Y-m-d H:i:s', time() + self::retry_delay_minutes((int) $item->attempts) * MINUTE_IN_SECONDS);
            }
        }
        $wpdb->update(self::table(), $data, ['id' => $id]);
        if ($data['status'] === 'published') {
            SGAP_ROI::record_event($id, $item->channel, $item->angle, 'publish');
        } elseif ($data['status'] === 'failed') {
            // Last attempt spent: tell someone now rather than leaving it on the Queue page.
            do_action('sgap_queue_failed', $id, (string) $item->channel, (string) $error);
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
        register_rest_route(self::NS, '/snapshot', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'snapshot'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/engagement/refresh', [
            'methods' => 'POST', 'callback' => ['SGAP_Engagement', 'rest_refresh'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/pinterest/refresh', [
            'methods' => 'POST', 'callback' => ['SGAP_Pinterest', 'rest_refresh'], 'permission_callback' => $auth
        ]);
        register_rest_route(self::NS, '/instagram/refresh', [
            'methods' => 'POST', 'callback' => ['SGAP_Instagram', 'rest_refresh'], 'permission_callback' => $auth
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
            'scheduled_at' => self::datetime($r->get_param('scheduled_at')),
            'campaign' => $r->get_param('campaign'),
            'force' => (bool) $r->get_param('force'),
            'remind' => (bool) $r->get_param('remind')
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

    /**
     * Everything the plugin knows, in one read-only call, for an outside dashboard
     * (Command Center): queue, drafts, results, conversions, learning, suppression,
     * reposts, Etsy orders, settings (secrets reduced to set/not set) and health.
     * It only reads, so it is safe to poll; admin application password required.
     */
    public static function snapshot(WP_REST_Request $r) {
        global $wpdb;
        $days = min(365, max(1, absint($r->get_param('days') ?: 30)));
        $limit = min(500, max(1, absint($r->get_param('limit') ?: 100)));
        $since = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $prefix = $wpdb->prefix;

        // An install that lost its tables would otherwise return empty lists and look healthy.
        $tables = [
            'queue' => 'sgap_queue', 'drafts' => 'sgap_ai_drafts', 'events' => 'sgap_events',
            'cro' => 'sgap_cro', 'learning' => 'sgap_learning', 'suppression' => 'sgap_suppression',
            'reposts' => 'sgap_reposts', 'etsy_orders' => 'sgap_etsy_orders'
        ];
        $missing = [];
        foreach ($tables as $name => $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix . $table))) !== $prefix . $table) {
                $missing[] = $name;
            }
        }

        $queue = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, channel, angle, content_type, format, destination_url, title, body,
                    image_url, status, attempts, scheduled_at, sent_at, published_at, error, fingerprint
             FROM {$prefix}sgap_queue ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        foreach ($queue as &$item) {
            $item['tracking_url'] = SGAP_Tracking::url((int) $item['id']);
        }
        unset($item);

        $drafts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, channel, angle, content_type, format, title, body, hashtags, alt_text, cta,
                    status, ai_model, created_at
             FROM {$prefix}sgap_ai_drafts ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        foreach ($drafts as &$draft) {
            $post_id = (int) $draft['post_id'];
            $draft['promotes'] = $post_id ? ['title' => get_the_title($post_id), 'url' => get_permalink($post_id)] : null;
            $draft['has_image'] = $post_id ? has_post_thumbnail($post_id) : false;
            unset($draft['post_id']);
        }
        unset($draft);

        $queue_counts = [];
        foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$prefix}sgap_queue GROUP BY status", ARRAY_A) as $row) {
            $queue_counts[$row['status']] = (int) $row['n'];
        }
        $draft_counts = [];
        foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$prefix}sgap_ai_drafts GROUP BY status", ARRAY_A) as $row) {
            $draft_counts[$row['status']] = (int) $row['n'];
        }

        $by_channel = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks,
                    SUM(event_type = 'order') AS orders, SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue
             FROM {$prefix}sgap_events WHERE created_at >= %s GROUP BY channel ORDER BY revenue DESC, clicks DESC",
            $since
        ), ARRAY_A);

        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS day, channel, event_type, COUNT(*) AS count, SUM(value) AS value
             FROM {$prefix}sgap_events WHERE created_at >= %s
             GROUP BY day, channel, event_type ORDER BY day ASC LIMIT 2000",
            $since
        ), ARRAY_A);

        $conversions = $wpdb->get_results($wpdb->prepare(
            "SELECT c.order_id, c.revenue, c.clicked_at, c.converted_at, q.channel, q.angle, q.title
             FROM {$prefix}sgap_cro c LEFT JOIN {$prefix}sgap_queue q ON q.id = c.queue_id
             WHERE c.order_id > 0 ORDER BY c.converted_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);

        $revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(value) FROM {$prefix}sgap_events WHERE event_type = 'order' AND created_at >= %s",
            $since
        ));
        $budget = (float) SGAP_Settings::get('monthly_budget', 1000);
        $health = get_option(SGAP_Auto::HEALTH);

        return [
            'generated_at' => current_time('mysql'),
            'site' => [
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'timezone' => wp_timezone_string(),
                'local_time' => wp_date('Y-m-d H:i')
            ],
            'version' => SGAP_VERSION,
            'missing_tables' => $missing,
            'window' => ['days' => $days, 'since' => $since],
            'status' => [
                'autopilot' => SGAP_Settings::enabled('autopilot_enabled'),
                'full_auto' => SGAP_Settings::enabled('full_auto_enabled'),
                'publish_mode' => SGAP_RSS::enabled() ? 'rss' : 'api',
                'channels' => SGAP_Publer::channels(),
                'bluesky_direct' => SGAP_Bluesky::enabled(),
                'feeds' => SGAP_RSS::enabled() ? array_map([SGAP_RSS::class, 'url'], SGAP_Publer::channels()) : null,
                'publer_configured' => SGAP_RSS::enabled() || SGAP_Publer::configured(),
                'ai_configured' => SGAP_Settings::get('ai_api_key') !== '',
                'ai_provider' => (string) SGAP_Settings::get('ai_provider', 'anthropic'),
                'ai_model' => SGAP_AI::model(),
                'sending_paused_because' => SGAP_Queue::blocked_reason() !== '' ? SGAP_Queue::blocked_message() : null,
                'can_send_now' => SGAP_Queue::capacity(),
                'limits' => [
                    'daily_cap' => (int) SGAP_Settings::get('daily_cap', 8),
                    'batch_size' => (int) SGAP_Settings::get('batch_size', 5),
                    'quiet_hours' => SGAP_Settings::get('quiet_start', 23) . ':00-' . SGAP_Settings::get('quiet_end', 7) . ':00'
                ],
                'key_test' => get_option(SGAP_KeyCheck::RESULT) ?: null,
                'last_self_check' => is_array($health) ? $health : null,
                'cron_lag_minutes' => SGAP_Auto::cron_lag(),
                'command_center' => SGAP_CC::status(),
                'last_generation' => get_option('sgap_last_generate') ?: null,
                'last_promote_new' => get_option('sgap_last_promote_new') ?: null,
                'last_articles' => get_option('sgap_last_articles') ?: null
            ],
            'counts' => ['queue' => $queue_counts, 'drafts' => $draft_counts],
            'results' => [
                'by_channel' => $by_channel,
                'daily' => $daily,
                'revenue' => $revenue,
                'budget' => $budget,
                'roi' => SGAP_ROI::roi($revenue, $budget),
                'roas' => SGAP_ROI::roas($revenue, $budget)
            ],
            'queue' => $queue,
            'drafts' => $drafts,
            'conversions' => $conversions,
            'learning' => $wpdb->get_results("SELECT dimension, dimension_value, clicks, orders, revenue, cost, updated_at FROM {$prefix}sgap_learning ORDER BY revenue DESC, clicks DESC LIMIT 200", ARRAY_A),
            'suppression' => $wpdb->get_results("SELECT * FROM {$prefix}sgap_suppression ORDER BY suppressed_at DESC LIMIT 200", ARRAY_A),
            'reposts' => $wpdb->get_results("SELECT * FROM {$prefix}sgap_reposts ORDER BY id DESC LIMIT 200", ARRAY_A),
            'etsy_orders' => $wpdb->get_results("SELECT * FROM {$prefix}sgap_etsy_orders ORDER BY id DESC LIMIT 200", ARRAY_A),
            'settings' => SGAP_AdminTools::get_settings()
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
        $products = wc_get_products(['status' => 'publish', 'limit' => 500]);
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
        $xml .= '<title>' . esc_xml(get_bloginfo('name')) . '</title>' . "\n";
        $xml .= '<link>' . esc_url(home_url('/')) . '</link>' . "\n";
        foreach ((array) $products as $p) {
            $img = wp_get_attachment_url($p->get_image_id());
            $price = $p->get_price();
            if (!$price || !$img) continue;
            $xml .= "<item>\n";
            $xml .= '  <g:id>' . esc_xml((string) ($p->get_sku() ?: $p->get_id())) . "</g:id>\n";
            $xml .= '  <g:title>' . esc_xml($p->get_name()) . "</g:title>\n";
            $xml .= '  <g:description>' . esc_xml(wp_strip_all_tags($p->get_short_description() ?: $p->get_description())) . "</g:description>\n";
            $xml .= '  <g:link>' . esc_url($p->get_permalink()) . "</g:link>\n";
            $xml .= '  <g:image_link>' . esc_url($img) . "</g:image_link>\n";
            $xml .= '  <g:availability>' . ($p->is_in_stock() ? 'in_stock' : 'out_of_stock') . "</g:availability>\n";
            $xml .= '  <g:price>' . esc_xml(number_format((float) $price, 2, '.', '') . ' ' . $currency) . "</g:price>\n";
            $xml .= '  <g:condition>new</g:condition>' . "\n";
            $xml .= "</item>\n";
        }
        $xml .= '</channel></rss>';
        // Returned instead of echoed: the old version printed the body and called exit, so
        // the feed could not be tested at all, and a header() after that output was already
        // too late to matter. WordPress sends the string and the content type from here.
        return new WP_HTTP_Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
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

        // The publish log keeps its own, shorter window (Settings → Alerts and log).
        if (class_exists('SGAP_Audit')) {
            SGAP_Audit::prune();
        }

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
        ['Evergreen', 'sgap-evergreen', 'evergreen'],
        ['Full Auto', 'sgap-fullauto', 'fullauto'],
        ['Shop & feeds', 'sgap-merchant', 'merchant']
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sgap_approve_ai', [__CLASS__, 'approve_ai']);
        add_action('admin_post_sgap_run', [__CLASS__, 'run_action']);
        add_action('admin_post_sgap_queue_action', [__CLASS__, 'queue_action']);
        add_action('admin_post_sgap_evergreen', [__CLASS__, 'evergreen_action']);
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

    public static function table_exists(string $table): bool {
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
    }

    /** Stores a message for open() on the next page and goes there. */
    public static function done(string $message, string $page = 'steamgears-autopilot'): void {
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

        // One line before the cards: what a person has to do, if anything.
        self::attention_strip($stats['tiles'], $blocked, $ai_ok);

        SGAP_Setup::checklist_card();

        // Tiles
        $t = $stats['tiles'];
        echo '<div class="sgap-stats">'
            . SGAP_UI::stat('Drafts to review', $t['pending'], admin_url('admin.php?page=sgap-ai'))
            . SGAP_UI::stat('Waiting in queue', $t['queued'], admin_url('admin.php?page=sgap-queue'))
            . SGAP_UI::stat('Published · 30 days', $t['published'])
            . SGAP_UI::stat('Clicks · 30 days', $t['clicks'])
            . SGAP_UI::stat('Orders · 30 days', $t['orders'] . ($t['revenue'] > 0 ? ' · ' . number_format_i18n($t['revenue'], 0) : ''))
            . '</div>';

        // 7.52.0 — three questions, in the order they are asked: is it running, where does it post,
        // and what keeps it alive. Every card sits under the question it answers, so the page reads
        // top-down instead of being two columns of unequal length.
        SGAP_UI::section('Now', 'Run a step by hand, ask the assistant, and check the job arrived on time.');
        echo '<div class="sgap-grid"><div>';
        self::run_panel();
        echo '</div><div>';
        self::chat_card();
        SGAP_Auto::health_card();
        SGAP_Diagnostics::dashboard_card();
        SGAP_Auto::scheduler_card();
        echo '</div></div>';

        SGAP_UI::section('Channels', 'Where the posts go, and what the platforms and the visitors did with them.');
        echo '<div class="sgap-grid"><div>';
        SGAP_UI::card_open('Last 30 days per channel', 'chart-bar');
        self::render_rows($stats['channels'], ['channel', 'published', 'clicks', 'orders', 'revenue']);
        SGAP_UI::card_close();
        SGAP_Engagement::dashboard_card();
        SGAP_Pinterest::dashboard_card();
        SGAP_Instagram::dashboard_card();
        SGAP_Facebook::dashboard_card();
        echo '</div><div>';
        self::feeds_panel();
        self::keys_panel();
        echo '</div></div>';

        SGAP_UI::section('System', 'Connections, the next release, and the jobs that tidy up after themselves.');
        echo '<div class="sgap-grid"><div>';
        SGAP_Updater::panel();
        echo '</div><div>';
        self::cc_card();
        SGAP_Rescue::card();
        echo '</div></div>';
        self::close();
    }

    /**
     * The one line at the top of the dashboard: the things a person has to do, and nothing else.
     * A quiet, all-clear strip is cheaper to read than a page of green pills — but it still says
     * what it checked, so "all clear" is a claim rather than an empty space.
     */
    private static function attention_strip(array $tiles, string $blocked, $ai_ok): void {
        $health = get_option(SGAP_Auto::HEALTH);
        $problems = is_array($health) ? (array) ($health['problems'] ?? []) : [];
        $items = [];
        if ($ai_ok === false) {
            $items[] = ['AI key failed its last test', 'bad'];
        }
        if ($problems !== []) {
            $items[] = [count($problems) . (count($problems) === 1 ? ' problem' : ' problems') . ' in the self-check', 'warn'];
        }
        $words = [
            'autopilot_disabled' => 'Autopilot is off — nothing new is written',
            'quiet_hours' => 'Quiet hours — queued posts go out at ' . sprintf('%02d:00', (int) SGAP_Settings::get('quiet_end', 7)),
            'daily_cap_reached' => 'Daily limit reached — the rest waits for tomorrow',
            'blackout' => 'Blackout — queued posts moved to the next free day'
        ];
        if (isset($words[$blocked])) {
            $items[] = [$words[$blocked], $blocked === 'autopilot_disabled' ? 'bad' : 'brass'];
        }
        if ((int) ($tiles['pending'] ?? 0) > 0) {
            $items[] = [$tiles['pending'] . ' draft' . ((int) $tiles['pending'] === 1 ? '' : 's') . ' waiting for you', 'brass'];
        }

        echo '<div class="sgap-attn' . ($items === [] ? ' is-ok' : '') . '"><strong>' . ($items === [] ? 'Status' : 'Needs you') . '</strong>';
        foreach ($items as [$text, $kind]) {
            echo SGAP_UI::pill($text, $kind);
        }
        if ($items === []) {
            echo SGAP_UI::pill('All clear — the autopilot runs on its own', 'ok');
        }
        echo '</div>';
    }

    private static function chat_card(): void {
        SGAP_UI::card_open('AI Chat', 'format-chat');
        echo '<p class="description" style="margin-top:0">Ask how things are going or tell the AI what to do: write posts, approve, release the queue, change settings.</p>'
            . '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sgap-chat')) . '"><span class="dashicons dashicons-format-chat"></span>Open AI Chat</a></p>';
        SGAP_UI::card_close();
    }

    /** Command Center is a connection, so it belongs under System rather than next to the queue. */
    private static function cc_card(): void {
        $line = SGAP_CC::line();
        $button = SGAP_CC::dashboard_button();
        if ($line === '' && $button === '') {
            return; // never connected and no address set: no card, no noise
        }
        SGAP_UI::card_open('Command Center', 'admin-site-alt3');
        echo '<p class="description" style="margin-top:0">The self-hosted dashboard reads <code>/snapshot</code> and sends drafts here. Nothing on this site calls it.</p>';
        if ($line !== '') {
            echo '<p>' . $line . '</p>';
        }
        echo $button;
        SGAP_UI::card_close();
    }

    private static function run_panel(): void {
        SGAP_UI::card_open('Run now', 'controls-play');
        echo '<div class="sgap-actions">';
        $tasks = [
            'generate' => ['Write posts now', 'edit', true],
            'publish' => ['Send the queue now', 'megaphone', false],
            'test_keys' => ['Test connections', 'admin-network', false],
            'test_publer' => ['Show Publer channels', 'groups', false]
        ];
        foreach ($tasks as $task => [$label, $icon, $primary]) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="sgap_run"><input type="hidden" name="task" value="' . esc_attr($task) . '">'
                . wp_nonce_field('sgap_run_' . $task, '_wpnonce', true, false)
                . '<button class="button' . ($primary ? ' button-primary' : '') . '"><span class="dashicons dashicons-' . $icon . '"></span>' . esc_html($label) . '</button></form>';
        }
        echo '</div>'
            . '<p class="description">Write posts: the AI drafts a post per channel for pages that need one. Send the queue: releases what is approved, now. '
            . 'Test connections: checks AI, Publer and Printful. Keys live under <a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings &amp; keys</a>.</p>';
        // Progress bar for "Generate posts now": one request per page, so each step is visible.
        echo '<div id="sgap-progress" class="sgap-progress" style="display:none">'
            . '<progress id="sgap-progress-bar" value="0" max="1"></progress>'
            . '<p id="sgap-progress-text"></p></div>';
        $gen = [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sgap_generate'),
            'back' => admin_url('admin.php?page=steamgears-autopilot')
        ];
        $dashboard = $gen + ['rescue' => ['ajax' => $gen['ajax'], 'nonce' => wp_create_nonce('sgap_rescue')]];
        SGAP_Assets::page_script('dashboard', 'SGAP_DASHBOARD', $dashboard);
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
        // Copy-knapperne styres af assets/pages/dashboard.js, som siden selv enqueuer.
        SGAP_UI::card_close();
    }

    private static function keys_panel(): void {
        $keys = get_option(SGAP_KeyCheck::RESULT);
        SGAP_UI::card_open('API keys', 'admin-network');
        if (!is_array($keys)) {
            echo '<p class="description">Not tested yet. Save a key in Settings or press "Test connections".</p>';
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
            $out = 'Publer: ' . $r['publer']['message'] . "\nAI: " . $r['ai']['message'] . "\nPrintful: " . $r['printful']['message'];
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

        // Why nothing is going out right now, in the owner's words, before any table.
        $blocked = SGAP_Queue::blocked_reason();
        if ($blocked !== '') {
            echo '<div class="sgap-attn"><strong>Held</strong>'
                . SGAP_UI::pill(ucfirst(SGAP_Queue::blocked_message($blocked)), $blocked === 'autopilot_disabled' ? 'bad' : 'warn')
                . '</div>';
        }

        $table = $wpdb->prefix . 'sgap_queue';
        $rows = self::table_exists($table)
            // P2.5: the language and the row this one was translated from travel with it,
            // so the owner can see which entries are translations instead of guessing.
            ? $wpdb->get_results("SELECT q.id, q.channel, q.title, q.status, q.remind, q.attempts, q.scheduled_at, q.published_at, q.error,
                    q.locale, q.source_id, s.title AS source_title
                FROM {$table} q LEFT JOIN {$table} s ON s.id = q.source_id ORDER BY q.id DESC LIMIT 50")
            : [];
        if (!$rows) {
            SGAP_UI::empty_state('clock', 'The queue is empty. Approve a draft under AI Drafts to fill it.');
            self::close();
            return;
        }

        // Two questions, not one table with a status column: what is about to go out, and what
        // already did. A row in `sending` is still on its way, so it belongs in the first group.
        $waiting = [];
        $finished = [];
        foreach ($rows as $r) {
            if (in_array((string) $r->status, ['queued', 'sending', 'reminded', 'pending', 'approved'], true)) {
                $waiting[] = $r;
            } else {
                $finished[] = $r;
            }
        }

        SGAP_UI::section('Waiting', count($waiting) === 1 ? 'One post still has to go out.' : count($waiting) . ' posts still have to go out.');
        if ($waiting) {
            self::queue_table($waiting);
        } else {
            echo '<p class="description">Nothing is waiting. The next post queued shows up here.</p>';
        }

        SGAP_UI::section('Done', 'Published and failed rows among the last 50 queue entries — the same list the publish log describes in detail.');
        if ($finished) {
            self::queue_table($finished);
        } else {
            echo '<p class="description">Nothing has gone out yet.</p>';
        }
        self::close();
    }

    /** One queue table. Every action is a small form, so the buttons work without JavaScript. */
    private static function queue_table(array $rows): void {
        echo '<div class="sgap-card sgap-qgroup"><div class="sgap-scroll"><table class="widefat striped sgap-qtable"><thead><tr><th>#</th><th>Channel</th><th>Title</th><th>Language</th><th>Source</th><th>Status</th><th>Scheduled</th><th>Published</th><th>Tracking link</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr id="sgap-q-' . absint($r->id) . '"><td data-label="#">' . esc_html($r->id) . '</td><td data-label="Channel">' . SGAP_UI::channel($r->channel) . '</td><td class="sgap-qtitle" data-label="Title">' . esc_html($r->title)
                . ($r->error ? '<br><small style="color:#b32d2e">' . esc_html($r->error) . '</small>' : '') . '</td><td data-label="Language">' . self::language_cell($r) . '</td><td data-label="Source">' . self::source_cell($r) . '</td><td data-label="Status">'
                . SGAP_UI::status_pill((string) $r->status) . (!empty($r->remind) ? ' <small title="The autopilot leaves this one to you">reminder</small>' : '') . ($r->attempts > 1 ? ' <small>' . esc_html($r->attempts . ' tries') . '</small>' : '') . '</td><td data-label="Scheduled">'
                . esc_html($r->scheduled_at ? mysql2date('j M H:i', $r->scheduled_at) : '–') . '</td><td data-label="Published">'
                . esc_html($r->published_at ? mysql2date('j M H:i', $r->published_at) : '–') . '</td><td data-label="Link"><code>' . esc_html(wp_parse_url(SGAP_Tracking::url((int) $r->id), PHP_URL_PATH)) . '</code></td><td data-label="">'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-actions" style="flex-wrap:nowrap">'
                . '<input type="hidden" name="action" value="sgap_queue_action">'
                . '<input type="hidden" name="queue_id" value="' . esc_attr($r->id) . '">'
                . wp_nonce_field('sgap_queue_' . $r->id, '_wpnonce', true, false)
                . '<button class="button button-small" name="cmd" value="retry">Re-queue</button>'
                . '<button class="button button-small" name="cmd" value="remind" title="Mail yourself the text, image and link instead of posting it">Remind me</button>'
                . '<button class="button button-small sgap-danger" name="cmd" value="delete">Delete</button></form></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    /**
     * P2.5: the language a queue row is written in, and whether it is a translation.
     * An empty locale is every row that existed before the feature and every source
     * post, so it is shown as the site's source language rather than as "unknown".
     */
    private static function language_cell(object $r): string {
        $locale = SGAP_Locales::code((string) ($r->locale ?? ''));
        return $locale !== ''
            ? '<code>' . esc_html($locale) . '</code> <small>translation</small>'
            : '<code>' . esc_html(SGAP_Locales::source()) . '</code> <small>original</small>';
    }

    /**
     * P2.5: the queue row this one was translated from, linked so one click jumps
     * to it. A source that has since been deleted still gets its id, never a guess.
     */
    private static function source_cell(object $r): string {
        $source_id = absint($r->source_id ?? 0);
        if ($source_id === 0) {
            return '—';
        }
        $title = trim((string) ($r->source_title ?? ''));
        return '<a href="#sgap-q-' . $source_id . '">#' . $source_id . '</a>'
            . ($title !== '' ? ' <small>' . esc_html($title) . '</small>' : '');
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
            // Re-queue means "let the autopilot try again", so a reminder flag is cleared too.
            $wpdb->update($table, ['status' => 'queued', 'attempts' => 0, 'error' => null, 'external_id' => '', 'remind' => 0], ['id' => $id]);
            $blocked = SGAP_Queue::blocked_reason();
            $message = "Post #{$id} is queued again." . ($blocked !== '' ? ' It waits: ' . SGAP_Queue::blocked_message($blocked) . '.' : '');
        } elseif ($id && $cmd === 'remind') {
            // P1.2: hand it to the owner. The next tick mails it instead of posting it.
            $wpdb->update($table, ['status' => 'queued', 'remind' => 1, 'error' => null, 'external_id' => ''], ['id' => $id]);
            $message = "Post #{$id} is marked “remind me”: on the next run you get a mail with the text, image and link instead of the autopilot posting it.";
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
            SGAP_UI::empty_state('yes-alt', 'No drafts waiting. Press "Write posts now" on the dashboard to have the AI write some.');
            self::close();
            return;
        }
        // Grouped by the page the AI wrote for: a decision is about one page's posts, and once a
        // group is empty that page is done. A draft without a source page gets its own group, so
        // nothing is silently dropped from the list.
        $pages = [];
        $channels = [];
        foreach ($rows as $row) {
            $key = $row->source !== null && $row->source !== '' ? (string) $row->source : 'No source page';
            $pages[$key][] = $row;
            $channels[sanitize_key((string) $row->channel)] = true;
        }
        SGAP_UI::section('Waiting for your decision', 'The AI wrote these for your pages. Approve & Queue sends one to the publishing queue, Approve Only keeps it, and Reject drops it.');
        echo '<div class="sgap-metrics">'
            . SGAP_UI::metric('Drafts', count($rows), count($rows) === 1 ? '1 post to read' : count($rows) . ' posts to read')
            . SGAP_UI::metric('Pages', count($pages))
            . SGAP_UI::metric('Channels', count($channels))
            . '</div>';
        foreach ($pages as $source => $group) {
            echo '<div class="sgap-group-head"><h3>' . esc_html((string) $source) . '</h3>' . SGAP_UI::pill(count($group) . (count($group) === 1 ? ' post' : ' posts'), 'brass') . '</div>'
                . '<div class="sgap-drafts">';
            foreach ($group as $row) {
                echo '<div class="sgap-draft"><div class="sgap-meta">' . SGAP_UI::channel($row->channel) . '<span>#' . esc_html($row->id) . '</span></div>'
                    . '<h3>' . esc_html($row->title) . '</h3>'
                    . '<div class="sgap-text">' . esc_html($row->body) . '</div>'
                    . ($row->hashtags !== '' ? '<div class="sgap-tags">' . esc_html($row->hashtags) . '</div>' : '')
                    . '<div class="sgap-meta"><span>' . esc_html(mysql2date('j M H:i', $row->created_at)) . '</span></div>'
                    . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-actions">'
                    . '<input type="hidden" name="action" value="sgap_approve_ai">'
                    . '<input type="hidden" name="draft_id" value="' . esc_attr($row->id) . '">'
                    . wp_nonce_field('sgap_approve_ai_' . $row->id, '_wpnonce', true, false)
                    . '<button class="button button-primary" name="decision" value="queue"><span class="dashicons dashicons-yes"></span>Approve &amp; Queue</button>'
                    . '<button class="button" name="decision" value="approved">Approve Only</button>'
                    . '<button class="button sgap-danger" name="decision" value="rejected">Reject</button></form></div>';
            }
            echo '</div>';
        }
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
        self::open('Etsy Orders & Profit', 'Imported by hand — the plugin has no Etsy API client.');
        echo '<div class="notice notice-info inline"><p><strong>Manual import.</strong> Orders arrive through '
            . '<code>POST /wp-json/sgap/v1/etsy/orders</code> with <code>etsy_order_id</code> and the amounts. '
            . 'The plugin never talks to Etsy and holds no Etsy credentials; the five Etsy fields were removed from '
            . 'Settings in 7.33.4 so the screen no longer promises an integration that does not exist.</p></div>';
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
        $labor_rate = (float) SGAP_Settings::get('labor_rate', 250);
        $labour_hours = SGAP_ROI::hours($rev_30d, $labor_rate);
        $budget_hours = SGAP_ROI::hours($budget, $labor_rate);

        echo '<p><strong>30-Day Attributed Revenue:</strong> ' . esc_html(number_format($rev_30d, 2))
            . ' &middot; <strong>Monthly Budget:</strong> ' . esc_html(number_format($budget, 2))
            . ' &middot; <strong>ROI:</strong> ' . esc_html((string) $roi) . '% (Target: ' . esc_html((string) $target_roi) . '%)'
            . ' &middot; <strong>ROAS:</strong> ' . esc_html((string) $roas) . 'x'
            . (null !== $labour_hours
                ? ' &middot; <strong>Labour equivalent:</strong> ' . esc_html((string) $labour_hours) . ' h at ' . esc_html(number_format($labor_rate, 2)) . '/h'
                    . ' (budget &asymp; ' . esc_html((string) $budget_hours) . ' h)'
                : ' &middot; <strong>Labour equivalent:</strong> off (set your hourly rate to see it)')
            . '</p>';

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

    public static function evergreen(): void {
        self::open('Evergreen recycling', 'Pages that keep earning come back with a new caption — until the budget or the expiry date says stop.');
        $summary = SGAP_Evergreen::summary();
        echo '<div class="sgap-pills">'
            . SGAP_UI::pill('Recycling ' . ($summary['enabled'] ? 'on' : 'off'), $summary['enabled'] ? 'ok' : 'bad')
            . SGAP_UI::pill($summary['marked'] . ' marked')
            . SGAP_UI::pill($summary['ready'] . ' ready now', $summary['ready'] > 0 ? 'ok' : 'warn')
            . SGAP_UI::pill($summary['shares_used'] . ' shares used')
            . SGAP_UI::pill('Wait ' . $summary['after_days'] . ' days')
            . '</div>';
        echo '<div class="sgap-card"><h2><span class="dashicons dashicons-update"></span>How it works</h2>'
            . '<p>Mark a post or product as evergreen — here, or in the editor sidebar. After <strong>' . esc_html((string) $summary['after_days']) . ' days</strong> it is recycled with a variation written for the next channel in rotation, until it has had <strong>' . esc_html((string) $summary['default_max']) . ' shares</strong> (unless the page has its own budget) or reaches its expiry date. A share is counted when the post reaches the queue, and the quality gate, preflight, blackout and duplicate guard all still apply.</p>'
            . ($summary['enabled'] ? '' : '<p><strong>Recycling is off.</strong> Turn it on under <a href="' . esc_url(admin_url('admin.php?page=sgap-settings#evergreen')) . '">Settings → Evergreen</a>.</p>')
            . '<div class="sgap-actions">'
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="sgap_evergreen"><input type="hidden" name="cmd" value="run">'
            . wp_nonce_field('sgap_evergreen', '_wpnonce', true, false)
            . '<button class="button button-primary"' . ($summary['ready'] > 0 ? '' : ' disabled') . '>Recycle ' . esc_html((string) min($summary['ready'], $summary['batch'])) . ' now</button></form>'
            . '</div></div>';

        echo '<div class="sgap-card"><h2><span class="dashicons dashicons-plus-alt2"></span>Mark a page</h2>'
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-inline">'
            . '<input type="hidden" name="action" value="sgap_evergreen"><input type="hidden" name="cmd" value="mark">'
            . wp_nonce_field('sgap_evergreen', '_wpnonce', true, false)
            . '<label>Post or product ID <input type="number" min="1" name="post_id" required class="small-text"></label>'
            . '<label>Max shares <input type="number" min="0" name="max" value="0" class="small-text"></label>'
            . '<label>Until <input type="date" name="until"></label>'
            . '<button class="button">Mark evergreen</button></form>'
            . '<p class="description">The ID is the number in the editor URL. Max shares 0 uses the setting; an empty date never expires.</p></div>';

        $ids = SGAP_Evergreen::marked();
        if (!$ids) {
            SGAP_UI::empty_state('update-alt', 'Nothing is marked yet. Mark a page here or in its editor sidebar.');
            self::close();
            return;
        }
        echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr><th>Page</th><th>Type</th><th>Shares</th><th>Until</th><th>Last share</th><th>Next</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($ids as $id) {
            $status = SGAP_Evergreen::status($id);
            echo '<tr><td><a href="' . esc_url((string) get_edit_post_link($id)) . '">' . esc_html(get_the_title($id)) . '</a></td>'
                . '<td>' . esc_html((string) get_post_type($id)) . '</td>'
                . '<td>' . esc_html($status['shares'] . ' / ' . $status['max']) . '</td>'
                . '<td>' . esc_html($status['until'] !== '' ? $status['until'] : '—') . '</td>'
                . '<td>' . esc_html($status['last_share'] ?? '—') . '</td>'
                . '<td>' . esc_html($status['next_share'] ?? '—') . '</td>'
                . '<td>' . ($status['eligible'] ? SGAP_UI::pill('ready', 'ok') : SGAP_UI::pill($status['reason'], 'warn')) . '</td>'
                . '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="sgap_evergreen"><input type="hidden" name="cmd" value="unmark">'
                . '<input type="hidden" name="post_id" value="' . esc_attr((string) $id) . '">'
                . wp_nonce_field('sgap_evergreen', '_wpnonce', true, false)
                . '<button class="button button-small sgap-danger">Stop</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
        self::close();
    }

    public static function evergreen_action(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        check_admin_referer('sgap_evergreen');
        $cmd = isset($_POST['cmd']) ? sanitize_key($_POST['cmd']) : '';
        $id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($cmd === 'run') {
            $report = SGAP_Evergreen::run();
            $lines = [sprintf('Recycled %d page(s).', $report['recycled'])];
            foreach ($report['items'] as $item) {
                $lines[] = sprintf('#%d %s → %s (%d of %d shares)', $item['post_id'], $item['title'], $item['channel'], $item['share'], $item['max']);
            }
            if (!empty($report['note'])) {
                $lines[] = $report['note'];
            }
            foreach ($report['errors'] as $error) {
                $lines[] = 'Error: ' . $error;
            }
            self::done(implode("\n", $lines), 'sgap-evergreen');
        }
        if ($cmd === 'mark') {
            $result = SGAP_Evergreen::mark($id, [
                'max' => isset($_POST['max']) ? absint($_POST['max']) : 0,
                'until' => isset($_POST['until']) ? sanitize_text_field(wp_unslash((string) $_POST['until'])) : ''
            ]);
            self::done(is_wp_error($result) ? $result->get_error_message() : 'Page #' . $id . ' is now evergreen.', 'sgap-evergreen');
        }
        if ($cmd === 'unmark') {
            SGAP_Evergreen::unmark($id);
            self::done('Page #' . $id . ' is no longer recycled.', 'sgap-evergreen');
        }
        self::done('Nothing changed.', 'sgap-evergreen');
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
            echo '<div class="notice notice-warning"><p>Please set a <strong>Merchant feed secret</strong> under <a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings &rarr; Shop &amp; feeds</a> to enable your XML product feed.</p></div>';
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
