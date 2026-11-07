<?php
/**
 * P1.5d — fejlsøgning i pluginnet: én side der svarer på «hvad fejlede, hvornår, og hvad
 * kan tallene ikke sige?».
 *
 * Two separate jobs, deliberately kept apart:
 *
 *  - `checks()` reads the state the modules already keep (last run, token expiry, next
 *    cron, failed queue rows) and turns it into rows an owner can act on. It writes
 *    nothing but its own snapshot, so opening the page is never a side effect.
 *  - `log()` is the only thing here that records anything, and only when a module hands
 *    it an error. The modules keep one error string each and overwrite it on the next
 *    successful run — which is exactly how a nightly failure disappears before anyone
 *    reads it. The ring buffer keeps the last MAX errors with their time and source.
 *
 * The caveats are part of the answer, not decoration: a dashboard that shows a number
 * without saying what it cannot see invites the reader to treat a missing metric as a
 * zero.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Diagnostics {

    /** Ring buffer of module errors. Not autoloaded: it is only read on the page. */
    const LOG = 'sgap_error_log';

    /** Snapshot of the last check run, for the dashboard card. */
    const STATE = 'sgap_diagnostics_state';

    /** How many errors are kept before the oldest is dropped. */
    const MAX = 40;

    /** Longer messages are cut; the full text is in the module's own state. */
    const MESSAGE_MAX = 300;

    /** How far back the dashboard looks when it says "N errors". */
    const WINDOW_HOURS = 24;

    /**
     * What each module's numbers cannot tell you. Shown under the module's own card and
     * collected on the diagnostics page.
     *
     * Written as the limitation, not as a disclaimer: what is left out, and why.
     */
    const CAVEATS = [
        'instagram' => 'Views, likes, comments, shares and saves are what Instagram had reported when we asked. A metric Instagram has not published yet (up to 48 hours) is left out, not stored as 0, and the post counts as waiting. Each media object reports its own lifetime totals, so the table is not a sum over a date range.',
        'pinterest' => 'Impressions, saves and outbound clicks are what Pinterest had reported when we asked, and a pin that is not reported yet is left out rather than counted as 0. Saves and likes are event types we also use for other channels, so every query here is scoped to the pinterest channel.',
        'engagement' => 'Likes, reposts and replies are what the platforms answered when the daily run asked. A platform that is not connected, or refuses the call, contributes nothing here — its row is missing, not zero — and the reason is in the error log.',
        'roi' => 'Revenue is last-click attribution inside the window: an order with no tracked click belongs to no channel, and an order after the window is counted for nobody. Our own /go/ clicks are counted by us, not by the platform.',
        'automation' => 'A post that never left has no numbers at all. A failed post is retried once; if it fails again it stays failed until someone looks at the reason on the Queue page.',
        'mcp' => 'The MCP credential is renewed by this plugin. A refused renewal is reported, but a client that stops calling looks the same as a site nobody asks about.'
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sgap_diagnostics_run', [__CLASS__, 'handle_run']);
        add_action('admin_post_sgap_diagnostics_clear', [__CLASS__, 'handle_clear']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('sgap_daily_health', [__CLASS__, 'sweep'], 20);
    }

    /* ------------------------------------------------------------------ *
     * The error log
     * ------------------------------------------------------------------ */

    /**
     * Records one module error. Accepts a WP_Error, an array with code/message, or a
     * plain string, because that is what the calling modules have at hand.
     *
     * @param string $source Short module name, e.g. 'instagram'.
     * @param mixed  $error  WP_Error|array|string.
     * @param array  $context Small scalar values worth keeping (channel, status code).
     */
    public static function log(string $source, $error, array $context = []): void {
        if (is_wp_error($error)) {
            $code = (string) $error->get_error_code();
            $message = (string) $error->get_error_message();
        } elseif (is_array($error)) {
            $code = (string) ($error['code'] ?? 'error');
            $message = (string) ($error['message'] ?? '');
        } else {
            $code = '';
            $message = (string) $error;
        }
        $message = trim($message);
        if ($message === '') {
            return;
        }
        // The last identical error from the same source is refreshed instead of repeated:
        // a nightly failure that keeps failing is one problem, not thirty.
        $entries = self::entries();
        if ($entries) {
            $last = $entries[0];
            if ($last['source'] === $source && $last['code'] === $code && $last['message'] === self::shorten($message)) {
                array_shift($entries);
                $last['at'] = current_time('mysql');
                $last['ts'] = time();
                $last['count'] = (int) $last['count'] + 1;
                array_unshift($entries, $last);
                update_option(self::LOG, $entries, false);
                return;
            }
        }
        $clean = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $clean[sanitize_key((string) $key)] = self::shorten((string) $value, 80);
            }
        }
        array_unshift($entries, [
            'at' => current_time('mysql'),
            'ts' => time(),
            'source' => sanitize_key($source),
            'code' => self::shorten($code, 60),
            'message' => self::shorten($message),
            'count' => 1,
            'context' => $clean
        ]);
        update_option(self::LOG, array_slice($entries, 0, self::MAX), false);
    }

    /** Newest first, always an array. */
    public static function entries(): array {
        $entries = get_option(self::LOG, []);
        return is_array($entries) ? array_values(array_filter($entries, 'is_array')) : [];
    }

    /** How many errors were recorded in the last N hours (site clock is not trusted: `ts` is). */
    public static function count_since(int $hours = self::WINDOW_HOURS): int {
        $since = time() - max(1, $hours) * HOUR_IN_SECONDS;
        $count = 0;
        foreach (self::entries() as $entry) {
            if ((int) ($entry['ts'] ?? 0) >= $since) {
                $count += max(1, (int) ($entry['count'] ?? 1));
            }
        }
        return $count;
    }

    /** Empties the log. Used by the button on the page, after a problem is understood. */
    public static function clear(): void {
        delete_option(self::LOG);
        delete_option(self::STATE);
    }

    public static function caveat(string $key): string {
        return (string) (self::CAVEATS[$key] ?? '');
    }

    private static function shorten(string $value, int $max = self::MESSAGE_MAX): string {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if (function_exists('mb_substr')) {
            return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
        }
        return strlen($value) > $max ? substr($value, 0, $max - 1) . '…' : $value;
    }

    /* ------------------------------------------------------------------ *
     * The checks
     * ------------------------------------------------------------------ */

    /**
     * One row per thing that can be wrong, with what it is and what to do about it.
     * States: ok, warn, bad, off (not configured — not a fault).
     *
     * @return array<int,array{id:string,label:string,state:string,detail:string,fix:string}>
     */
    public static function checks(): array {
        $checks = [];
        $checks[] = self::row(
            'php',
            'PHP version',
            version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'bad',
            PHP_VERSION . ' · WordPress and this plugin need 8.0 or newer.',
            'Ask the host to switch the site to PHP 8.2 or newer.'
        );

        $missing = [];
        foreach (['sgap_queue', 'sgap_events', 'sgap_ai_drafts', 'sgap_publish_log'] as $table) {
            if (!self::table_exists($table)) {
                $missing[] = $table;
            }
        }
        $checks[] = self::row(
            'tables',
            'Database tables',
            $missing ? 'bad' : 'ok',
            $missing ? 'Missing: ' . implode(', ', $missing) . '.' : 'Queue, events, AI drafts and the publish log exist.',
            'Deactivate and reactivate the plugin so dbDelta can create the missing tables.'
        );

        $checks[] = self::cron_row();

        global $wpdb;
        $q = $wpdb->prefix . 'sgap_queue';
        if (!self::table_exists('sgap_queue')) {
            // Same row on every page load: a check that disappears is a check nobody notices.
            $checks[] = self::row('queue', 'Failed posts', 'bad',
                'The queue table is missing, so no post can be tracked or retried.',
                'Deactivate and reactivate the plugin so dbDelta can create the table.');
        } else {
            $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$q} WHERE status = 'failed'");
            $reason = $failed > 0 ? (string) $wpdb->get_var("SELECT error FROM {$q} WHERE status = 'failed' AND error IS NOT NULL ORDER BY id DESC LIMIT 1") : '';
            $checks[] = self::row(
                'queue',
                'Failed posts',
                $failed === 0 ? 'ok' : ($failed >= 5 ? 'bad' : 'warn'),
                $failed === 0 ? 'No post is stuck in failed.' : $failed . ' post(s) failed' . ($reason !== '' ? '. Last reason: ' . self::shorten($reason, 160) : '.'),
                'Open the Queue page, fix the reason (photo, link, channel) and requeue the posts.'
            );
        }

        $ai = (string) SGAP_Settings::get('ai_api_key');
        $keys = get_option(SGAP_KeyCheck::RESULT);
        $ai_tested = is_array($keys) ? ($keys['ai']['ok'] ?? null) : null;
        $checks[] = self::row(
            'ai',
            'AI key',
            $ai === '' ? 'bad' : ($ai_tested === false ? 'bad' : ($ai_tested === null ? 'warn' : 'ok')),
            $ai === ''
                ? 'No key saved: nothing new is written.'
                : ($ai_tested === false ? 'The last test failed: ' . (string) ($keys['ai']['message'] ?? '') : ($ai_tested === null ? 'Saved but not tested since the last save.' : 'Saved and tested.')),
            'Save a working key under Settings → Keys; the weekly self-check tests it every Monday.'
        );

        foreach (['pinterest', 'instagram'] as $channel) {
            $checks[] = self::channel_row($channel);
        }

        $mcp = class_exists('SGAP_MCP_Auth') ? SGAP_MCP_Auth::problem() : '';
        $checks[] = self::row(
            'mcp',
            'MCP credential',
            $mcp === '' ? 'ok' : 'warn',
            $mcp === '' ? 'The editor connection is being renewed by the plugin.' : $mcp,
            'Check Tools → MCP or the log under Settings → Advanced; a refused renewal needs the connection authorised again.'
        );

        $recent = self::count_since();
        $checks[] = self::row(
            'errors',
            'Module errors (last 24 h)',
            $recent === 0 ? 'ok' : ($recent >= 3 ? 'bad' : 'warn'),
            $recent === 0 ? 'No module has reported an error in the last 24 hours.' : $recent . ' error(s) recorded below; the oldest is dropped after ' . self::MAX . '.',
            'Read the log below. The same error repeated is one problem — the count says how many runs it survived.'
        );

        return $checks;
    }

    /** The scheduler is the one failure that makes every other number look fine. */
    private static function cron_row(): array {
        $hooks = [
            'publish tick' => SGAP_Install::PUBLISH_CRON,
            'self-check' => SGAP_Auto::HEALTH_CRON,
            'Pinterest' => SGAP_Pinterest::CRON,
            'Instagram' => SGAP_Instagram::CRON
        ];
        $late = [];
        $unscheduled = [];
        foreach ($hooks as $label => $hook) {
            $next = SGAP_Scheduler::next($hook);
            if ($next === false || (int) $next === 0) {
                $unscheduled[] = $label;
            } elseif ((int) $next < time() - HOUR_IN_SECONDS) {
                $late[] = $label . ' (' . self::minutes_late((int) $next) . ' min late)';
            }
        }
        if ($unscheduled) {
            return self::row('cron', 'Scheduled jobs', 'bad',
                'Not scheduled: ' . implode(', ', $unscheduled) . '.',
                'Deactivate and reactivate the plugin; if the site uses DISABLE_WP_CRON, a host cron must call wp-cron.php.');
        }
        if ($late) {
            return self::row('cron', 'Scheduled jobs', 'warn',
                'Overdue: ' . implode(', ', $late) . '.',
                SGAP_Auto::cron_advice(60));
        }
        return self::row('cron', 'Scheduled jobs', 'ok', 'Publishing tick, self-check and both insight runs have a next run in the future.', '');
    }

    private static function minutes_late(int $due): int {
        return max(1, (int) floor((time() - $due) / MINUTE_IN_SECONDS));
    }

    /** One insight channel: configured, token alive, last run, next run. */
    private static function channel_row(string $channel): array {
        $class = $channel === 'pinterest' ? 'SGAP_Pinterest' : 'SGAP_Instagram';
        $label = ucfirst($channel) . ' insights';
        if (!class_exists($class)) {
            return self::row($channel, $label, 'bad', 'The module is not loaded.', 'Reactivate the plugin; the file is missing from the release.');
        }
        $status = (array) $class::status();
        $configured = !empty($status['configured']);
        if (!$configured) {
            return self::row($channel, $label, 'off',
                'Not connected, so nothing is measured. This is not a fault.',
                'Save the credential under Settings → Keys when you want the channel counted.');
        }
        $problem = (string) ($status['problem'] ?? '');
        $last = (string) ($status['last_run'] ?? '');
        $next = (string) ($status['next_run'] ?? '');
        $detail = 'Last run ' . ($last !== '' ? $last : 'never') . ($next !== '' ? ' · next ' . $next : '');
        if ($problem !== '') {
            return self::row($channel, $label, 'bad', $problem . ' (' . $detail . ')', self::channel_fix($channel));
        }
        if (empty($status['token_valid'])) {
            return self::row($channel, $label, 'warn', 'The token is missing or expired. ' . $detail, self::channel_fix($channel));
        }
        if ($last === '') {
            return self::row($channel, $label, 'warn', 'Configured but has not run yet. ' . $detail, 'Run it once from the dashboard card, or wait for the daily run.');
        }
        return self::row($channel, $label, 'ok', 'Connected. ' . $detail, '');
    }

    private static function channel_fix(string $channel): string {
        return $channel === 'instagram'
            ? 'Paste a long-lived Instagram User token and the professional account id under Settings → Keys; a token set in wp-config.php cannot be refreshed.'
            : 'Paste a Pinterest access token, or the app credentials plus a refresh token, under Settings → Keys.';
    }

    private static function row(string $id, string $label, string $state, string $detail, string $fix): array {
        return ['id' => $id, 'label' => $label, 'state' => $state, 'detail' => $detail, 'fix' => $fix];
    }

    private static function table_exists(string $table): bool {
        return SGAP_Admin::table_exists($GLOBALS['wpdb']->prefix . $table);
    }

    /* ------------------------------------------------------------------ *
     * Aggregates
     * ------------------------------------------------------------------ */

    /** Runs the checks, stores the snapshot and returns it with the counts. */
    public static function status(): array {
        $checks = self::checks();
        $totals = ['ok' => 0, 'warn' => 0, 'bad' => 0, 'off' => 0];
        foreach ($checks as $check) {
            $totals[$check['state']] = ($totals[$check['state']] ?? 0) + 1;
        }
        $status = [
            'at' => current_time('mysql'),
            'checks' => $checks,
            'totals' => $totals,
            'errors' => self::count_since(),
            'log' => self::entries(),
            'caveats' => self::CAVEATS,
            'problem' => self::summarise($totals, self::count_since())
        ];
        update_option(self::STATE, ['at' => $status['at'], 'totals' => $totals, 'errors' => $status['errors'], 'problem' => $status['problem']], false);
        return $status;
    }

    /** True when the card has nothing to add: no failed check and no error in the window. */
    public static function quiet(array $status): bool {
        return (string) ($status['problem'] ?? '') === '' && (int) ($status['errors'] ?? 0) === 0;
    }

    private static function summarise(array $totals, int $errors): string {
        $parts = [];
        if ($totals['bad'] > 0) {
            $parts[] = $totals['bad'] . ' check(s) failed';
        }
        if ($totals['warn'] > 0) {
            $parts[] = $totals['warn'] . ' need attention';
        }
        if ($errors > 0) {
            $parts[] = $errors . ' module error(s) in the last 24 h';
        }
        return $parts ? implode(' · ', $parts) : '';
    }

    /**
     * One line for the daily self-check. Only errors the rest of the self-check does not
     * already know about: a channel problem is reported by the channel itself, so this
     * only mentions the log — which is where a failure that later succeeded is kept.
     */
    public static function problem(): string {
        $errors = self::count_since();
        if ($errors === 0) {
            return '';
        }
        return $errors . ' module error(s) in the last 24 h — see Steamgears → Fejlsøgning for the message and the run it came from.';
    }

    /** Daily: refresh the snapshot so the dashboard card does not run checks on every load. */
    public static function sweep(): array {
        return self::status();
    }

    /* ------------------------------------------------------------------ *
     * Admin
     * ------------------------------------------------------------------ */

    public static function menu(): void {
        add_submenu_page(
            'steamgears-autopilot',
            'Fejlsøgning',
            'Fejlsøgning',
            'manage_options',
            'sgap-diagnostics',
            [__CLASS__, 'page']
        );
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to see this page.');
        }
        $status = self::status();
        SGAP_Admin::open('Fejlsøgning', 'What each module last did, what failed, and what the numbers cannot tell you.');
        echo '<div class="sgap-pills">'
            . SGAP_UI::pill($status['totals']['ok'] . ' ok', 'ok')
            . SGAP_UI::pill($status['totals']['warn'] . ' need attention', $status['totals']['warn'] > 0 ? 'warn' : '')
            . SGAP_UI::pill($status['totals']['bad'] . ' failed', $status['totals']['bad'] > 0 ? 'bad' : '')
            . SGAP_UI::pill($status['totals']['off'] . ' not connected', 'brass')
            . SGAP_UI::pill('Checked ' . $status['at'])
            . '</div>';

        // Two buttons, both POST to admin-post.php so a refresh in the browser never re-runs them.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0">';
        wp_nonce_field('sgap_diagnostics');
        echo '<input type="hidden" name="action" value="sgap_diagnostics_run">'
            . '<button class="button button-primary" type="submit">Run the checks now</button></form>';
        if (self::entries()) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 12px">';
            wp_nonce_field('sgap_diagnostics');
            echo '<input type="hidden" name="action" value="sgap_diagnostics_clear">'
                . '<button class="button" type="submit" onclick="return confirm(\'Clear the error log? The module state itself is not touched.\')">Clear the error log</button></form>';
        }

        echo '<h2>Checks</h2><div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
            . '<th>Check</th><th>State</th><th>What it found</th><th>What to do</th></tr></thead><tbody>';
        foreach ($status['checks'] as $check) {
            echo '<tr><td><strong>' . esc_html($check['label']) . '</strong></td>'
                . '<td>' . self::state_pill($check['state']) . '</td>'
                . '<td>' . esc_html($check['detail']) . '</td>'
                . '<td>' . esc_html($check['fix']) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<h2>Module errors</h2>';
        if (!$status['log']) {
            echo '<p class="description">Nothing is logged. The log only gets a row when a module hands over an error, and it survives the next successful run.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
                . '<th>When</th><th>Module</th><th>Code</th><th>Message</th><th>Times</th></tr></thead><tbody>';
            foreach ($status['log'] as $entry) {
                echo '<tr><td>' . esc_html((string) $entry['at']) . '</td>'
                    . '<td>' . esc_html((string) $entry['source']) . '</td>'
                    . '<td>' . esc_html((string) $entry['code']) . '</td>'
                    . '<td>' . esc_html((string) $entry['message']) . '</td>'
                    . '<td>' . esc_html((string) (int) ($entry['count'] ?? 1)) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '<h2>Forbehold</h2><p class="description">What the numbers on the dashboard do not include. They are part of the answer, not fine print.</p><ul class="sgap-caveats">';
        foreach ($status['caveats'] as $key => $text) {
            echo '<li><strong>' . esc_html($key) . '</strong> — ' . esc_html($text) . '</li>';
        }
        echo '</ul>';
        SGAP_Admin::close();
    }

    private static function state_pill(string $state): string {
        $map = ['ok' => ['ok', 'ok'], 'warn' => ['warn', 'needs attention'], 'bad' => ['bad', 'failed'], 'off' => ['brass', 'not connected']];
        [$kind, $label] = $map[$state] ?? ['', $state];
        return SGAP_UI::pill($label, $kind);
    }

    public static function handle_run(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to run the checks.');
        }
        check_admin_referer('sgap_diagnostics');
        $status = self::status();
        SGAP_Admin::done(($status['problem'] !== '' ? $status['problem'] : 'All checks passed.') . ' (checked ' . $status['at'] . ')', 'sgap-diagnostics');
    }

    public static function handle_clear(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to clear the log.');
        }
        check_admin_referer('sgap_diagnostics');
        self::clear();
        SGAP_Admin::done('The error log is empty.', 'sgap-diagnostics');
    }

    /**
     * Dashboard card: only when there is something to say. A green card that repeats
     * "all good" every day trains the eye to skip the place where a failure would appear.
     */
    public static function dashboard_card(): void {
        $status = self::status();
        $errors = (int) $status['errors'];
        if (self::quiet($status)) {
            return;
        }
        SGAP_UI::card_open('Fejlsøgning', 'editor-help');
        echo '<p>' . ($status['totals']['bad'] > 0
            ? SGAP_UI::pill($status['totals']['bad'] . ' failed', 'bad')
            : SGAP_UI::pill('No failed checks', 'ok'))
            . ($errors > 0 ? ' ' . SGAP_UI::pill($errors . ' error(s) in 24 h', 'warn') : '') . '</p>';
        $first = null;
        foreach ($status['checks'] as $check) {
            if ($check['state'] === 'bad' || $check['state'] === 'warn') {
                $first = $check;
                break;
            }
        }
        if ($first) {
            echo '<p class="description">' . esc_html($first['label'] . ': ' . $first['detail']) . '</p>';
        }
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-diagnostics')) . '">Open Fejlsøgning</a></p>';
        SGAP_UI::card_close();
    }

    /* ------------------------------------------------------------------ *
     * REST
     * ------------------------------------------------------------------ */

    public static function routes(): void {
        register_rest_route(SGAP_Admin::NS, '/diagnostics', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_report'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    /** Read-only report, so an MCP client or a shell can ask the same question as the page. */
    public static function rest_report(): WP_REST_Response {
        return new WP_REST_Response(self::status(), 200);
    }
}
