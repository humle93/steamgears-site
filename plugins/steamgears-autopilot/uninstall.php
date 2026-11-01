<?php
/**
 * Runs when the plugin is deleted from Plugins → Delete (not on deactivation).
 * Removes every table, option, transient and cron event the plugin created.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

foreach ([
    'sgap_queue', 'sgap_events', 'sgap_cro', 'sgap_learning',
    'sgap_ai_drafts', 'sgap_reposts', 'sgap_suppression', 'sgap_etsy_orders'
] as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'sgap\\_%'
        OR option_name LIKE '\\_transient\\_sgap\\_%'
        OR option_name LIKE '\\_transient\\_timeout\\_sgap\\_%'"
);

delete_post_meta_by_key('_sgap_promoted_at');

foreach ([
    'sgap_daily_cleanup', 'sgap_publish_tick', 'sgap_generate', 'sgap_process_queue', 'sgap_etsy_sync', 'sgap_etsy_inventory',
    'sgap_weekly_report', 'sgap_repost_check', 'sgap_suppression_check'
] as $hook) {
    wp_clear_scheduled_hook($hook);
}
