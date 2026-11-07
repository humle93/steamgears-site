<?php
/**
 * Scheduling adapter — Action Scheduler when the site already has it, WP-Cron when it does not.
 *
 * Why an adapter instead of a dependency: Action Scheduler is GPL-3.0 and designed to be
 * bundled, but WooCommerce already ships it on every site that runs the shop. A second copy
 * inside our zip would be dead weight that has to be re-synced with every Woo release, and
 * the library's own version handling would load the newest copy anyway. So this class asks
 * the site what it has:
 *
 *   function_exists('as_schedule_recurring_action')  → Action Scheduler, group `sgap`
 *   otherwise                                        → wp_schedule_event() / WP-Cron
 *
 * The hook names and the callbacks do not change, because Action Scheduler fires the same
 * `do_action($hook)`. Only the storage and the retry behaviour differ: AS keeps its own
 * queue and can be driven from WP-CLI (`wp action-scheduler run`), while WP-Cron depends on
 * traffic. P0.4's Site Health test stays the source of truth for lateness either way.
 *
 * One caveat that matters for alerting: Action Scheduler 4.0.0 purges failed actions by
 * default, so a dead-letter list cannot be built on its `failed` status alone. Our publish
 * log remains the record of what happened, and this adapter never reads `failed`.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Scheduler {
    /** Group every job of ours, so `wp action-scheduler run --group=sgap` is a clean handle. */
    const GROUP = 'sgap';

    /**
     * The three functions that must exist before we hand scheduling to AS — plus an explicit
     * way out. `define('SGAP_SCHEDULER_WP_CRON', true)` in wp-config.php, or the
     * `sgap_scheduler_action_scheduler` filter, forces WP-Cron while AS is still loaded.
     * Without that, a site whose AS queue has stalled would have no way to publish if the
     * admin is unreachable.
     */
    public static function available(): bool {
        if (defined('SGAP_SCHEDULER_WP_CRON') && SGAP_SCHEDULER_WP_CRON) {
            return false;
        }
        $present = function_exists('as_schedule_recurring_action')
            && function_exists('as_schedule_single_action')
            && function_exists('as_next_scheduled_action');
        return $present && (bool) apply_filters('sgap_scheduler_action_scheduler', true);
    }

    /** `action-scheduler` or `wp-cron` — what the admin page and Site Health report. */
    public static function backend(): string {
        return self::available() ? 'action-scheduler' : 'wp-cron';
    }

    /**
     * Make sure a recurring job exists. Idempotent: an existing occurrence is left alone,
     * so calling this on every request is safe and cannot stack duplicates.
     */
    public static function recurring(string $hook, int $interval, array $args = [], int $first_delay = 0, string $schedule = ''): bool {
        $interval = max(60, $interval);
        if (self::next($hook, $args) !== false) {
            return true;
        }
        // The first run keeps the delay the caller asked for — activation starts the
        // publisher five minutes in, not a whole interval in — and repeats on the interval.
        $first = time() + ($first_delay > 0 ? $first_delay : $interval);
        if (self::available()) {
            $id = as_schedule_recurring_action($first, $interval, $hook, $args, self::GROUP);
            return !is_wp_error($id) && (int) $id > 0;
        }
        return (bool) wp_schedule_event($first, $schedule !== '' ? $schedule : self::wp_schedule_name($interval), $hook, $args);
    }

    /** A one-off job at a fixed time; past timestamps run on the next queue pass. */
    public static function single(string $hook, int $timestamp, array $args = []): bool {
        if (self::available()) {
            $id = as_schedule_single_action($timestamp, $hook, $args, self::GROUP);
            return !is_wp_error($id) && (int) $id > 0;
        }
        return (bool) wp_schedule_single_event(max(time(), $timestamp), $hook, $args);
    }

    /**
     * Next run as a Unix timestamp, or false.
     *
     * @return int|false
     */
    public static function next(string $hook, array $args = []) {
        if (self::available()) {
            $next = as_next_scheduled_action($hook, $args, self::GROUP);
            return $next ? (int) $next : false;
        }
        // wp_next_scheduled() returns false, but a stubbed or filtered 0 must not read as
        // "already scheduled" — that would silently skip scheduling on a real site too.
        $next = wp_next_scheduled($hook, $args);
        return $next ? (int) $next : false;
    }

    /**
     * Remove every pending occurrence of a hook. Both backends are cleared on purpose: a job
     * scheduled before AS became available would otherwise survive the switch and keep firing
     * next to its AS twin.
     */
    public static function unschedule(string $hook, array $args = []): void {
        if (self::available()) {
            as_unschedule_all_actions($hook, $args, self::GROUP);
        }
        wp_clear_scheduled_hook($hook, $args);
    }

    /**
     * A WP-Cron schedule name for an interval. `daily`/`hourly` is the fallback because a
     * custom interval would need its own `cron_schedules` entry just to be scheduled here.
     */
    private static function wp_schedule_name(int $interval): string {
        if (function_exists('wp_get_schedules')) {
            foreach ((array) wp_get_schedules() as $name => $spec) {
                if ((int) ($spec['interval'] ?? 0) === $interval) {
                    return (string) $name;
                }
            }
        }
        return $interval >= DAY_IN_SECONDS ? 'daily' : 'hourly';
    }

    /** What the admin page and Site Health show; no credentials, no side effects. */
    public static function health(): array {
        return [
            'backend' => self::backend(),
            'action_scheduler' => self::available(),
            'as_version' => defined('ACTION_SCHEDULER_VERSION') ? (string) ACTION_SCHEDULER_VERSION : '',
            'woocommerce' => class_exists('WooCommerce'),
            'note' => self::available()
                ? 'Jobs kører på Action Scheduler, som WooCommerce leverer; WP-Cron er kun triggeren.'
                : 'Action Scheduler er ikke indlæst; jobs kører på WP-Cron og arver dets pålidelighed.',
        ];
    }
}
