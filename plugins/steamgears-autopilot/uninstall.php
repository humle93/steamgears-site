<?php
/**
 * Runs when the plugin is deleted from Plugins → Delete (not on deactivation).
 * Removes every table, option, transient and cron event the plugin created.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

foreach ([
    'sgap_queue', 'sgap_events', 'sgap_cro', 'sgap_learning',
    'sgap_ai_drafts', 'sgap_reposts', 'sgap_suppression', 'sgap_etsy_orders',
    'sgap_publish_log', 'sgap_reddit_posts'
] as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'sgap\\_%'
        OR option_name LIKE '\\_transient\\_sgap\\_%'
        OR option_name LIKE '\\_transient\\_timeout\\_sgap\\_%'"
);

// Every post meta key the plugin owns starts with _sgap_ (evergreen marks, AI
// images, studio data, niche markers and Reddit links). One LIKE removes them all,
// including keys added by a later version.
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
    $wpdb->esc_like('_sgap_') . '%'
));

foreach ([
    'sgap_daily_cleanup', 'sgap_publish_tick', 'sgap_generate', 'sgap_process_queue', 'sgap_etsy_sync', 'sgap_etsy_inventory',
    'sgap_weekly_report', 'sgap_repost_check', 'sgap_suppression_check', 'sgap_mcp_refresh',
    'sgap_engagement_refresh', 'sgap_pinterest_refresh', 'sgap_instagram_refresh', 'sgap_facebook_refresh',
    'sgap_reddit_tick', 'sgap_coach_learn', 'sgap_rescue', 'sgap_evergreen', 'sgap_cirla_daily'
] as $hook) {
    wp_clear_scheduled_hook($hook);
}

// The plugin may have scheduled its jobs in Action Scheduler instead: a deleted plugin
// must not leave actions behind that fire hooks nobody listens to any more.
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('', [], 'sgap');
}
