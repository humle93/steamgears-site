<?php
/**
 * Plugin Name: Steamgears cron kicker
 * Description: Kører forfaldne cron-opgaver på et almindeligt sidevisning, når webhotellet har slået WP-Cron fra (DISABLE_WP_CRON) og der ikke er sat et server-cron. Svaret sendes til brugeren først, så kørslen ikke koster ventetid.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Målt 8/11 2026: `DISABLE_WP_CRON` er sand, `wp-cron.php` kører ikke de forfaldne opgaver
 * (0 bytes, ingen effekt), og der er intet server-cron. Resultatet var 158 forfaldne
 * Action Scheduler-handlinger, en helbredsmåling der sidst skrev 7/11 og en ugentlig
 * artikel der aldrig blev kørt.
 *
 * Kicker'en gør det samme som `_wp_cron()`, men uden at kræve konstanten fjernet:
 * den kører de forfaldne opgaver ved `shutdown`, højst hvert andet minut, højst otte
 * opgaver og 30 sekunder ad gangen. `fastcgi_finish_request()` sender svaret først,
 * så kunden ikke venter på jobbet. Alt skrives til `sgap_cron_kicker`.
 */
final class SGAP_Cron_Kicker
{
    public const OPTION = 'sgap_cron_kicker';
    public const LOCK = 'sgap_cron_kicker_lock';
    public const LOCK_TTL = 120;
    public const MAX_HOOKS = 8;
    public const MAX_SECONDS = 30;

    /** Sider hvor en kørende cron-opgave ville kunne mærkes af en kunde. */
    private const SKIP_PATHS = ['/cart', '/checkout', '/my-account', '/wc-ajax', '/wp-login', '/wp-cron', '/wp-admin'];

    public static function boot(): void
    {
        add_action('shutdown', [self::class, 'maybe_run'], 0);
        add_action('rest_api_init', [self::class, 'register_tick']);
    }

    /**
     * Offentligt tick, som wp-cron.php: kører kun det der allerede er forfaldent, og højst
     * én gang i minuttet. Bruges af .github/workflows/cron-tick.yml, fordi webhotellet har
     * slået WP-Cron fra og siden caches foran PHP.
     */
    public static function register_tick(): void
    {
        register_rest_route('sgap/v1', '/tick', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'tick'],
        ]);
    }

    public static function tick(): WP_REST_Response
    {
        if (get_transient('sgap_tick_lock')) {
            return new WP_REST_Response(['ok' => true, 'skipped' => 'locked'], 200);
        }

        set_transient('sgap_tick_lock', time(), 60);

        // Skriv intentionen før opgaverne kører: en enkelt cron-opgave kan afslutte requesten
        // (W3TC's oprydning gør), og så ville loggen ellers være tom selv om arbejdet skete.
        update_option(self::OPTION, [
            'at' => gmdate('c'),
            'state' => 'running',
            'due_before' => self::count_due((array) wp_get_ready_cron_jobs()),
        ], false);

        $result = self::run_due(12, 40);
        $result['drained'] = class_exists('SGAP_Queue_Hygiene') ? SGAP_Queue_Hygiene::drain_now(3) : 0;
        $result['pending'] = class_exists('SGAP_Queue_Hygiene') ? SGAP_Queue_Hygiene::pending_count() : -1;

        update_option(self::OPTION, $result, false);

        return new WP_REST_Response(array_merge(['ok' => true], $result), 200);
    }

    private static function blocked(): bool
    {
        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (is_admin()) {
            return true;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return true;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        foreach (self::SKIP_PATHS as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    public static function maybe_run(): array
    {
        $debug = isset($_GET['sgap_kick_debug']);

        if ($debug) {
            update_option('sgap_cron_kicker_debug', [
                'at' => gmdate('c'),
                'blocked' => self::blocked() ? 'yes' : 'no',
                'lock' => get_transient(self::LOCK) ? 'yes' : 'no',
                'uri' => (string) ($_SERVER['REQUEST_URI'] ?? '?'),
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? '?'),
                'is_admin' => is_admin() ? 'yes' : 'no',
                'ready' => function_exists('wp_get_ready_cron_jobs') ? count((array) wp_get_ready_cron_jobs()) : -1,
                'cron_array' => count((array) _get_cron_array()),
            ], false);
        }

        if (self::blocked()) {
            return ['skipped' => 'request'];
        }

        if (get_transient(self::LOCK)) {
            return ['skipped' => 'locked'];
        }

        if (!function_exists('wp_get_ready_cron_jobs')) {
            return ['skipped' => 'no-api'];
        }

        $report = self::run_due(self::MAX_HOOKS, self::MAX_SECONDS);

        if (($report['ran'] ?? []) === [] && ($report['due_before'] ?? 0) === 0) {
            return ['skipped' => 'nothing-due'];
        }

        if (class_exists('SGAP_Queue_Hygiene')) {
            SGAP_Queue_Hygiene::drain_now(3);
        }

        update_option(self::OPTION, $report, false);

        return $report;
    }

    /**
     * Kører de forfaldne opgaver og flytter dem videre, som _wp_cron() ville have gjort.
     *
     * @return array<string,mixed>
     */
    private static function run_due(int $maxHooks, int $maxSeconds): array
    {
        $jobs = wp_get_ready_cron_jobs();
        if (empty($jobs)) {
            return ['at' => gmdate('c'), 'seconds' => 0, 'ran' => [], 'due_before' => 0];
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        ignore_user_abort(true);
        @set_time_limit($maxSeconds + 10);

        $started = microtime(true);
        $ran = [];

        foreach ($jobs as $timestamp => $hooks) {
            if ((float) $timestamp > microtime(true)) {
                break;
            }

            foreach ((array) $hooks as $hook => $items) {
                foreach ((array) $items as $item) {
                    $args = isset($item['args']) && is_array($item['args']) ? $item['args'] : [];
                    $schedule = $item['schedule'] ?? false;

                    if ($schedule) {
                        try {
                            wp_reschedule_event((int) $timestamp, (string) $schedule, (string) $hook, $args, true);
                        } catch (Throwable $e) {
                            // En opgave der ikke kan flyttes må ikke stoppe resten.
                        }
                    } else {
                        wp_unschedule_event((int) $timestamp, (string) $hook, $args);
                    }

                    try {
                        do_action_ref_array((string) $hook, $args);
                        $ran[] = (string) $hook;
                    } catch (Throwable $e) {
                        $ran[] = (string) $hook . ' (fejl)';
                    }

                    if (count($ran) >= $maxHooks || (microtime(true) - $started) > $maxSeconds) {
                        break 3;
                    }
                }
            }
        }

        return [
            'at' => gmdate('c'),
            'seconds' => round(microtime(true) - $started, 2),
            'ran' => $ran,
            'due_before' => self::count_due($jobs),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     */
    private static function count_due(array $jobs): int
    {
        $now = time();
        $count = 0;

        foreach ($jobs as $timestamp => $hooks) {
            if ((int) $timestamp > $now) {
                continue;
            }
            foreach ((array) $hooks as $items) {
                $count += count((array) $items);
            }
        }

        return $count;
    }
}

SGAP_Cron_Kicker::boot();
