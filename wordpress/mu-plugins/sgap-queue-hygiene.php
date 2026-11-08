<?php
/**
 * Plugin Name: Steamgears queue hygiene
 * Description: Holder Action Scheduler-køen ren: fjerner handlinger fra plugins der ikke længere findes, rydder gamle fejl og slår dublerede WooCommerce-webhooks fra, så de ikke gentager de samme leveringer i det uendelige.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Køhygiejne målt 8/11 2026: 1 084 fejlede `facebook_for_woocommerce_process_logs_batch`
 * (plugin'et er ikke installeret), 326 ventende `woocommerce_deliver_webhook_async` fordelt på
 * 40 aktive webhooks hvor de samme emner var oprettet 2–5 gange mod samme URL.
 *
 * Reglerne her er derfor:
 *  1. Alle handlinger hvis plugin ikke findes på disken slettes, uanset status — de kan aldrig
 *     køre igen, og deres historik hører til et plugin der ikke er der.
 *  2. Fejlede handlinger ældre end 14 dage slettes, undtagen plugin'ets egne `sgap_*`.
 *  3. Af flere aktive webhooks med samme emne og samme URL beholdes den ældste; resten slås fra
 *     (status `disabled`, ikke slettet), så det kan rulles tilbage med ét klik.
 *  4. Er der over 50 ventende leveringer, tømmes køen i op til tre batches samme dag.
 *
 * Alt skrives til optionen `sgap_queue_hygiene`, så effekten kan måles uden at læse koden.
 */
final class SGAP_Queue_Hygiene
{
    public const OPTION = 'sgap_queue_hygiene';
    public const HOOK = 'sgap_queue_hygiene';
    public const STALE_FAILED_DAYS = 14;
    public const DRAIN_THRESHOLD = 50;
    public const DRAIN_TARGET = 20;
    public const DRAIN_BATCHES = 6;

    public static function boot(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
        add_action('init', [self::class, 'schedule']);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(strtotime('tomorrow 04:10'), 'daily', self::HOOK);
        }
    }

    /**
     * Hook-prefix => mappenavn i wp-content/plugins. Mangler mappen, er handlingerne forældreløse.
     *
     * @return array<string,string>
     */
    private static function orphan_map(): array
    {
        return [
            'facebook_for_woocommerce_' => 'facebook-for-woocommerce',
            'aioseo_' => 'all-in-one-seo-pack',
            'wpforms_' => 'wpforms-lite',
            'mailpoet/' => 'mailpoet',
            'wcpay_' => 'woocommerce-payments',
        ];
    }

    private static function db(): wpdb
    {
        global $wpdb;
        return $wpdb;
    }

    private static function table(string $name): string
    {
        return self::db()->prefix . $name;
    }

    public static function pending_count(): int
    {
        return (int) self::db()->get_var(
            "SELECT COUNT(*) FROM " . self::table('actionscheduler_actions') . " WHERE hook = 'woocommerce_deliver_webhook_async' AND status = 'pending'"
        );
    }

    /**
     * Kører hele oprydningen og returnerer det der blev gjort, så det kan logges og måles.
     *
     * @return array<string,mixed>
     */
    public static function run(): array
    {
        $report = [
            'at' => gmdate('c'),
            'pending_before' => self::pending_count(),
            'orphans_deleted' => 0,
            'stale_deleted' => 0,
            'duplicates_disabled' => 0,
            'cron_events_removed' => 0,
            'drained' => 0,
            'pending_after' => 0,
            'orphan_hooks' => [],
            'webhook_kept' => [],
        ];

        if (class_exists('ActionScheduler_Store')) {
            $report['orphans_deleted'] = self::purge_orphans($report['orphan_hooks']);
            $report['stale_deleted'] = self::purge_stale_failures();
            $report['duplicates_disabled'] = self::dedupe_webhooks($report['webhook_kept']);
            $report['cron_events_removed'] = self::purge_orphan_cron_events();
            $report['drained'] = self::drain_backlog();
        }

        $report['pending_after'] = self::pending_count();
        update_option(self::OPTION, $report, false);

        return $report;
    }

    /**
     * @param array<int,string> $hooked
     */
    private static function purge_orphans(array &$hooked): int
    {
        $store = ActionScheduler_Store::instance();
        $deleted = 0;

        foreach (self::orphan_map() as $prefix => $dir) {
            if (is_dir(WP_PLUGIN_DIR . '/' . $dir)) {
                continue;
            }

            $ids = self::db()->get_col(
                self::db()->prepare(
                    "SELECT action_id FROM " . self::table('actionscheduler_actions') . "
                     WHERE hook LIKE %s LIMIT 5000",
                    $prefix . '%'
                )
            );

            foreach ($ids as $id) {
                $store->delete_action((int) $id);
                $deleted++;
            }

            if ($ids) {
                $hooked[] = $prefix . '(' . count($ids) . ')';
            }
        }

        return $deleted;
    }

    private static function purge_stale_failures(): int
    {
        $store = ActionScheduler_Store::instance();
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::STALE_FAILED_DAYS * DAY_IN_SECONDS));

        $ids = self::db()->get_col(
            self::db()->prepare(
                "SELECT action_id FROM " . self::table('actionscheduler_actions') . "
                 WHERE status = 'failed' AND hook NOT LIKE 'sgap%%' AND scheduled_date_gmt < %s LIMIT 5000",
                $cutoff
            )
        );

        foreach ($ids as $id) {
            $store->delete_action((int) $id);
        }

        return count($ids);
    }

    /**
     * Fjerner planlagte cron-begivenheder fra plugins der ikke findes (fx Facebook-pluginnets
     * heartbeat), så de ikke bliver ved med at vække en kø der ikke har nogen modtager.
     */
    private static function purge_orphan_cron_events(): int
    {
        $removed = 0;

        foreach (self::orphan_map() as $prefix => $dir) {
            if (is_dir(WP_PLUGIN_DIR . '/' . $dir)) {
                continue;
            }

            foreach ((array) _get_cron_array() as $timestamp => $hooks) {
                foreach ((array) $hooks as $hook => $items) {
                    if (strpos((string) $hook, $prefix) !== 0) {
                        continue;
                    }
                    foreach ((array) $items as $item) {
                        $args = isset($item['args']) && is_array($item['args']) ? $item['args'] : [];
                        wp_unschedule_event((int) $timestamp, (string) $hook, $args);
                        $removed++;
                    }
                }
            }

            wp_clear_scheduled_hook($prefix . 'hourly_heartbeat_cron');
        }

        return $removed;
    }

    /**
     * @param array<int,string> $kept
     */
    private static function dedupe_webhooks(array &$kept): int
    {
        $rows = self::db()->get_results(
            "SELECT webhook_id, topic, delivery_url FROM " . self::table('wc_webhooks') . " WHERE status = 'active' ORDER BY webhook_id ASC",
            ARRAY_A
        );

        $seen = [];
        $disabled = 0;

        foreach ((array) $rows as $row) {
            $key = $row['topic'] . '|' . $row['delivery_url'];

            if (!isset($seen[$key])) {
                $seen[$key] = (int) $row['webhook_id'];
                continue;
            }

            if (!function_exists('wc_get_webhook')) {
                break;
            }

            $webhook = wc_get_webhook((int) $row['webhook_id']);
            if (!$webhook) {
                continue;
            }

            $webhook->set_status('disabled');
            $webhook->save();
            $disabled++;
        }

        foreach ($seen as $key => $id) {
            $kept[] = $key . ' => #' . $id;
        }

        return $disabled;
    }

    /**
     * Tømmer køen med et antal batches. Offentlig, så cron-kicker'en kan kalde den
     * på almindelige sidevisninger uden at vente på Action Schedulers eget loopback.
     */
    public static function drain_now(int $batches = 3): int
    {
        if (!class_exists('ActionScheduler_QueueRunner')) {
            return 0;
        }

        $runner = ActionScheduler_QueueRunner::instance();
        $before = self::pending_count();

        for ($i = 0; $i < max(1, $batches); $i++) {
            if (self::pending_count() <= self::DRAIN_TARGET) {
                break;
            }
            $runner->run('queue-hygiene');
        }

        return max(0, $before - self::pending_count());
    }

    private static function drain_backlog(): int
    {
        if (!class_exists('ActionScheduler_QueueRunner')) {
            return 0;
        }

        if (self::pending_count() < self::DRAIN_THRESHOLD) {
            return 0;
        }

        if (get_transient('sgap_queue_hygiene_lock')) {
            return 0;
        }

        set_transient('sgap_queue_hygiene_lock', 1, 10 * MINUTE_IN_SECONDS);
        $runner = ActionScheduler_QueueRunner::instance();
        $before = self::pending_count();

        for ($i = 0; $i < self::DRAIN_BATCHES; $i++) {
            if (self::pending_count() <= self::DRAIN_TARGET) {
                break;
            }
            $runner->run('queue-hygiene');
        }

        delete_transient('sgap_queue_hygiene_lock');

        return max(0, $before - self::pending_count());
    }
}

SGAP_Queue_Hygiene::boot();
