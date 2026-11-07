<?php
/**
 * Pinterest analytics ingestion — P1.5b.
 *
 * Why it exists: P1.5a reads Bluesky and Mastodon, which publish like/repost/reply
 * counts as a public read. Pinterest instead reports impressions, saves and outbound
 * clicks through the official v5 API, and only for the account that owns the pins.
 * Those three numbers are the only ones in this plugin that answer "was the pin seen",
 * as opposed to "did anybody click our tracking link" (sgap_cro) or "did it sell"
 * (WooCommerce). They are stored as their own event types so the two never mix.
 *
 * Facts that shape the design (official v5 documentation):
 *   - `GET /v5/pins/{pin_id}/analytics` takes start_date/end_date, cannot look more
 *     than 90 days back, and returns `summary_metrics` plus `daily_metrics`. We store
 *     the summary for the window; the daily rows are not kept.
 *   - `GET /v5/pins` lists the pins the token owns, 25 per page by default and at most
 *     250, with an opaque `bookmark` cursor. Each pin carries `link`, which is how a
 *     pin is tied back to the queue row that produced it.
 *   - The access token lives 30 days and the continuous refresh token 60 days, and
 *     **every refresh rotates the refresh token** — the old one is dead afterwards.
 *     So the new pair is written the moment it arrives, never only the access token.
 *
 * Matching is by link, not by title: the publisher hands Publer the tracked
 * `/go/<queue id>/` address, so a pin whose `link` is one of ours has an exact owner.
 * A pin that links somewhere else is counted as unmapped, and one whose `/go/` id has
 * no published Pinterest row in the window is counted as orphaned. Both are reported
 * instead of being guessed at, because attributing a stranger's pin to our post would
 * silently inflate every number downstream.
 *
 * Replacing rather than appending, like P1.5a: one refresh deletes the three event
 * types it owns for that queue row, then writes the current window totals. Summing
 * `value` therefore stays correct however often the daily job (or a manual run) fires.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Pinterest {
    /** Daily hook; scheduled through SGAP_Scheduler on init. */
    const CRON = 'sgap_pinterest_refresh';

    const API = 'https://api.pinterest.com/v5';

    /** Pinterest's documented maximum for a metrics window. */
    const WINDOW = 90;

    /** Event types this class owns; a refresh replaces them for a queue row. */
    const TYPES = ['impressions', 'saves', 'outbound_clicks'];

    /** What the analytics endpoint is asked for, and how the answer is read. */
    const METRICS = ['IMPRESSION', 'SAVE', 'OUTBOUND_CLICK'];
    const METRIC_MAP = ['IMPRESSION' => 'impressions', 'SAVE' => 'saves', 'OUTBOUND_CLICK' => 'outbound_clicks'];

    const ACCESS = 'sgap_pinterest_access_token';
    const EXPIRES = 'sgap_pinterest_access_expires';
    const REFRESH_EXPIRES = 'sgap_pinterest_refresh_expires';
    const PINS = 'sgap_pinterest_pins';
    const BOOKMARK = 'sgap_pinterest_bookmark';
    const STATE = 'sgap_pinterest_state';

    /** Renew this long before the access token expires, so a failed renewal has room. */
    const MARGIN = 3 * DAY_IN_SECONDS;
    /** Never renew twice inside this window, however often the cron or a button fires. */
    const RENEW_WINDOW = 300;

    const PAGE = 100;
    const MAX_PAGES = 10;
    /** Analytics calls per run: the endpoint is rate limited per app, so a backlog drains over days. */
    const MAX_CALLS = 25;
    /** How many pin mappings are kept; older pins drop out after they leave the 90-day window. */
    const KEEP_PINS = 800;

    /**
     * Test seam: function(string $method, string $url, array $args) returning a
     * WordPress HTTP response. Null in production. The smoke suite cannot redefine
     * wp_remote_get/wp_remote_post, so both calls go through here.
     */
    public static $transport = null;

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run']);
        // Action Scheduler when WooCommerce supplies it, WP-Cron otherwise.
        SGAP_Scheduler::recurring(self::CRON, DAY_IN_SECONDS);
    }

    /* ------------------------------------------------------------------ *
     * Credentials
     * ------------------------------------------------------------------ */

    /** The app's public client id. Not a secret. */
    public static function client_id(): string {
        return trim((string) SGAP_Settings::get('pinterest_client_id', ''));
    }

    public static function client_secret(): string {
        return trim((string) SGAP_Settings::get('pinterest_client_secret', ''));
    }

    /** Rotated on every refresh, so it is written back after each use. */
    public static function refresh_token(): string {
        return trim((string) SGAP_Settings::get('pinterest_refresh_token', ''));
    }

    /** A token pasted by hand (a Trial token, for instance) with no expiry date. */
    public static function saved_token(): string {
        return trim((string) SGAP_Settings::get('pinterest_access_token', ''));
    }

    /** Everything needed to renew without the owner: app credentials plus a refresh token. */
    public static function refreshable(): bool {
        return self::client_id() !== '' && self::client_secret() !== '' && self::refresh_token() !== '';
    }

    public static function configured(): bool {
        return self::saved_token() !== '' || self::refreshable();
    }

    /**
     * The wp-config.php constants that would swallow a rotated token. Both the refresh
     * token and the access token are checked: a constant always wins over the option, so
     * renewal would write a value nothing reads and the site would keep using the old one.
     */
    public static function constant_locked(): string {
        foreach (['pinterest_refresh_token', 'pinterest_access_token'] as $key) {
            $constant = SGAP_Settings::constant_name($key);
            if ($constant !== '') {
                return $constant;
            }
        }
        return '';
    }

    /** True when the access token itself comes from wp-config.php and therefore cannot be replaced. */
    public static function access_constant(): string {
        return SGAP_Settings::constant_name('pinterest_access_token');
    }

    /* ------------------------------------------------------------------ *
     * Token
     * ------------------------------------------------------------------ */

    /**
     * A usable access token, renewed when the stored one is inside MARGIN of its
     * expiry. Returns '' when there is nothing usable — callers report that instead
     * of sending an empty Bearer header and calling the 401 an API problem.
     */
    public static function token(): string {
        if (self::access_constant() !== '') {
            return self::saved_token(); // Constant wins over anything stored; it cannot be rotated.
        }
        $cached = (string) get_option(self::ACCESS, '');
        $expires = (int) get_option(self::EXPIRES, 0);
        // An unknown expiry (a token pasted by hand) is used until Pinterest refuses it;
        // treating "no expiry recorded" as expired would throw away a working token.
        if ($cached !== '' && ($expires === 0 || $expires > time() + self::MARGIN)) {
            return $cached;
        }
        if (self::refreshable() && self::constant_locked() === '') {
            $renewed = self::refresh();
            return is_wp_error($renewed) ? '' : (string) ($renewed['access_token'] ?? '');
        }
        // No way to renew: a hand-pasted token is used until Pinterest refuses it.
        return $cached !== '' ? $cached : self::saved_token();
    }

    /**
     * Renews the pair against /v5/oauth/token (HTTP Basic, form-encoded body) and
     * stores the rotated refresh token before anything else can fail.
     *
     * @return array{access_token:string,expires_in:int,rotated:bool}|WP_Error
     */
    public static function refresh() {
        if (self::constant_locked() !== '') {
            $message = 'The Pinterest refresh token is set in wp-config.php (' . self::constant_locked() . '), so a rotated token cannot be stored. Remove the constant and save it under Settings → Keys.';
            self::record(['ok' => false, 'error' => $message]);
            return new WP_Error('sgap_pinterest_constant', $message);
        }
        if (!self::refreshable()) {
            $message = 'Pinterest has no app credentials and refresh token saved, so the access token can only be replaced by hand: paste a fresh one under Settings → Keys (Developer app, scopes boards:read and pins:read).';
            self::record(['ok' => false, 'error' => $message]);
            return new WP_Error('sgap_pinterest_no_credentials', $message);
        }
        $state = self::state();
        if ((int) $state['renew_ts'] > 0 && (time() - (int) $state['renew_ts']) < self::RENEW_WINDOW) {
            return new WP_Error('sgap_pinterest_throttled', 'A Pinterest token renewal was attempted less than five minutes ago; not asking again yet.');
        }

        $secret = self::client_secret();
        $refresh = self::refresh_token();
        $response = self::send('POST', self::API . '/oauth/token', [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                // Pinterest documents Basic auth with client_id:client_secret on this endpoint.
                'Authorization' => 'Basic ' . base64_encode(self::client_id() . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
                // Required for apps created before 2025-09-25; ignored by newer ones.
                'continuous_refresh' => 'true'
            ]
        ]);
        if (is_wp_error($response)) {
            $message = 'Pinterest could not be reached to renew the token: ' . $response->get_error_message() . '.';
            self::record(['ok' => false, 'renew_ts' => time(), 'error' => $message]);
            return new WP_Error('sgap_pinterest_transport', $message);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body) || empty($body['access_token'])) {
            $detail = self::detail(is_array($body) ? $body : []);
            $message = 'Pinterest refused the token renewal (HTTP ' . $code . ')' . ($detail !== '' ? ': ' . $detail : '') . '.';
            self::record(['ok' => false, 'renew_ts' => time(), 'error' => $message]);
            return new WP_Error('sgap_pinterest_refused', $message, ['status' => $code]);
        }

        $access = (string) $body['access_token'];
        $expires_in = (int) ($body['expires_in'] ?? 30 * DAY_IN_SECONDS);
        $rotated = isset($body['refresh_token']) && $body['refresh_token'] !== '' && $body['refresh_token'] !== $refresh;

        // Store first: a crash after the request but before the write would leave the
        // old, already-spent refresh token behind and force a new authorisation.
        update_option(self::ACCESS, $access, false);
        update_option(self::EXPIRES, time() + max(MINUTE_IN_SECONDS, $expires_in), false);
        if ($rotated) {
            SGAP_Settings::set('pinterest_refresh_token', (string) $body['refresh_token']);
        }
        $refresh_expires = (int) ($body['refresh_token_expires_at'] ?? 0);
        if ($refresh_expires <= 0 && (int) ($body['refresh_token_expires_in'] ?? 0) > 0) {
            $refresh_expires = time() + (int) $body['refresh_token_expires_in'];
        }
        if ($refresh_expires > 0) {
            update_option(self::REFRESH_EXPIRES, $refresh_expires, false);
        }

        self::record([
            'ok' => true,
            'renew_ts' => time(),
            'error' => '',
            'rotated' => $rotated,
            'expires_in' => $expires_in
        ]);
        return ['access_token' => $access, 'expires_in' => $expires_in, 'rotated' => $rotated];
    }

    /** The API's own reason from an OAuth error body, trimmed to 200 characters. */
    public static function detail(array $body): string {
        $value = $body['message'] ?? ($body['error_description'] ?? ($body['error'] ?? ''));
        if (is_array($value)) {
            $value = $value['message'] ?? '';
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        return substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 200);
    }

    /* ------------------------------------------------------------------ *
     * Pins and their queue rows
     * ------------------------------------------------------------------ */

    /** pin_id => ['link' => string, 'created_at' => string, 'synced_at' => int]. */
    public static function mapping(): array {
        $map = get_option(self::PINS, []);
        return is_array($map) ? $map : [];
    }

    /**
     * The queue row a pin's link points at, or 0 when the link is not one of ours.
     * Only this site's host counts: another site's /go/ path is not our receipt.
     */
    public static function queue_id(string $link): int {
        $link = trim($link);
        if ($link === '' || preg_match('#^https?://#i', $link) !== 1) {
            return 0;
        }
        $host = strtolower((string) wp_parse_url($link, PHP_URL_HOST));
        $own = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($host === '' || $own === '' || preg_replace('/^www\./', '', $host) !== preg_replace('/^www\./', '', $own)) {
            return 0;
        }
        $path = (string) wp_parse_url($link, PHP_URL_PATH);
        return preg_match('#/go/(\d+)/?$#', $path, $m) === 1 ? (int) $m[1] : 0;
    }

    /**
     * One page of own pins, newest first, merged into the mapping. Pagination resumes
     * from the saved bookmark so a large account is walked over several runs instead
     * of one long request chain, and the bookmark is cleared when the last page is in.
     *
     * @return array{seen:int,pages:int,complete:bool,pins:int}|WP_Error
     */
    public static function scan(int $max_pages = self::MAX_PAGES) {
        $map = self::mapping();
        $bookmark = (string) get_option(self::BOOKMARK, '');
        $seen = 0;
        $pages = 0;
        while ($pages < max(1, $max_pages)) {
            $query = ['page_size' => self::PAGE];
            if ($bookmark !== '') {
                $query['bookmark'] = $bookmark;
            }
            $body = self::request('/pins', $query);
            if (is_wp_error($body)) {
                return $body;
            }
            $pages++;
            foreach ((array) ($body['items'] ?? []) as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $map[$id] = [
                    'link' => (string) ($item['link'] ?? ''),
                    'created_at' => (string) ($item['created_at'] ?? ''),
                    'synced_at' => (int) ($map[$id]['synced_at'] ?? 0)
                ];
                $seen++;
            }
            $bookmark = (string) ($body['bookmark'] ?? '');
            if ($bookmark === '') {
                break;
            }
        }
        update_option(self::BOOKMARK, $bookmark, false);
        update_option(self::PINS, self::trim($map), false);
        return ['seen' => $seen, 'pages' => $pages, 'complete' => $bookmark === '', 'pins' => count($map)];
    }

    /** Keeps the newest KEEP_PINS entries, so an old account cannot grow the option forever. */
    public static function trim(array $map): array {
        if (count($map) <= self::KEEP_PINS) {
            return $map;
        }
        uasort($map, static fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_slice($map, 0, self::KEEP_PINS, true);
    }

    /** Published Pinterest queue rows inside the window, indexed by id. */
    public static function targets(int $days = self::WINDOW): array {
        global $wpdb;
        // published_at is written with current_time('mysql'), so the window is site time too.
        $since = wp_date('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, angle, external_id, published_at FROM {$wpdb->prefix}sgap_queue
             WHERE channel = 'pinterest' AND status = 'published' AND published_at >= %s
             ORDER BY published_at DESC",
            $since
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /** The first date Pinterest will report on, never more than WINDOW days back (site time). */
    public static function floor_date(): string {
        return wp_date('Y-m-d', time() - (self::WINDOW - 1) * DAY_IN_SECONDS);
    }

    /** A row's window start: its publication day, raised to Pinterest's 90-day floor. */
    public static function start_date(string $published_at): string {
        $day = substr(trim($published_at), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return self::floor_date();
        }
        return $day > self::floor_date() ? $day : self::floor_date();
    }

    /* ------------------------------------------------------------------ *
     * Analytics
     * ------------------------------------------------------------------ */

    /**
     * Impressions, saves and outbound clicks for one pin inside one window.
     *
     * @return array{impressions:int,saves:int,outbound_clicks:int}|WP_Error
     */
    public static function analytics(string $pin_id, string $start, string $end) {
        $pin_id = trim($pin_id);
        if ($pin_id === '') {
            return new WP_Error('sgap_pinterest_no_pin', 'No Pinterest pin id was given.');
        }
        $body = self::request('/pins/' . rawurlencode($pin_id) . '/analytics', [
            'start_date' => $start,
            'end_date' => $end,
            'app_types' => 'ALL',
            'metric_types' => implode(',', self::METRICS)
        ]);
        if (is_wp_error($body)) {
            return $body;
        }
        $summary = self::summary_metrics($body);
        if ($summary === null) {
            return new WP_Error('sgap_pinterest_shape', 'Pinterest answered without summary_metrics for pin ' . $pin_id . '.');
        }
        $counts = [];
        foreach (self::METRIC_MAP as $metric => $type) {
            $counts[$type] = max(0, (int) round((float) ($summary[$metric] ?? 0)));
        }
        return $counts;
    }

    /**
     * The summary block of an analytics answer. Pinterest keys the response by the
     * requested metric group (`all` for the default), so the first bucket carrying a
     * summary is used instead of hardcoding one key.
     */
    public static function summary_metrics(array $body): ?array {
        if (isset($body['summary_metrics']) && is_array($body['summary_metrics'])) {
            return $body['summary_metrics'];
        }
        foreach ($body as $bucket) {
            if (is_array($bucket) && isset($bucket['summary_metrics']) && is_array($bucket['summary_metrics'])) {
                return $bucket['summary_metrics'];
            }
        }
        return null;
    }

    /** Replaces the three owned event types for one queue row. */
    public static function store(int $queue_id, string $angle, array $counts): void {
        global $wpdb;
        if ($queue_id <= 0) {
            return;
        }
        $types = self::TYPES;
        $marks = implode(',', array_fill(0, count($types), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sgap_events WHERE queue_id = %d AND channel = 'pinterest' AND event_type IN ({$marks})",
            array_merge([$queue_id], $types)
        ));
        foreach ($types as $type) {
            if (!array_key_exists($type, $counts)) {
                continue;
            }
            SGAP_ROI::record_event($queue_id, 'pinterest', $angle, $type, (float) $counts[$type]);
        }
    }

    /**
     * One run: list the pins, tie them to published queue rows, ask for each window
     * total, store it. Nothing is invented for a pin we cannot tie to a row.
     *
     * @return array<string,mixed>
     */
    public static function sync(int $limit = self::MAX_CALLS): array {
        $report = [
            'configured' => self::configured(),
            'pins' => 0,
            'matched' => 0,
            'updated' => 0,
            'unmapped' => 0,
            'orphaned' => 0,
            'errors' => [],
            'window' => self::WINDOW,
            'at' => current_time('mysql')
        ];
        if (!self::configured()) {
            $report['error'] = 'Pinterest analytics is not connected: save a Pinterest access token, or the app credentials plus a refresh token, under Settings → Keys.';
            self::record(['ok' => false, 'error' => $report['error'], 'report' => $report]);
            return $report;
        }

        // A refused listing (missing boards:read, rate limit, outage) must not throw away
        // the pins already mapped: their analytics may still be readable, and the error is
        // reported alongside whatever the run managed to collect.
        $scan = self::scan();
        if (is_wp_error($scan)) {
            $report['errors'][] = 'Pin listing: ' . $scan->get_error_message();
            $report['pins'] = count(self::mapping());
        } else {
            $report['pins'] = (int) $scan['pins'];
        }

        $targets = self::targets();
        $map = self::mapping();
        $candidates = [];
        foreach ($map as $pin_id => $meta) {
            $queue = self::queue_id((string) ($meta['link'] ?? ''));
            if ($queue === 0) {
                $report['unmapped']++;
                continue;
            }
            if (!isset($targets[$queue])) {
                $report['orphaned']++;
                continue;
            }
            $candidates[] = ['pin' => (string) $pin_id, 'queue' => $queue, 'synced' => (int) ($meta['synced_at'] ?? 0)];
        }
        $report['matched'] = count($candidates);
        // Oldest snapshot first, so a backlog larger than one run's budget drains fairly.
        usort($candidates, static fn($a, $b) => $a['synced'] <=> $b['synced']);
        $end = wp_date('Y-m-d');

        foreach (array_slice($candidates, 0, max(1, $limit)) as $candidate) {
            $row = $targets[$candidate['queue']];
            $start = self::start_date((string) ($row['published_at'] ?? ''));
            if ($start > $end) {
                continue; // Published "in the future" by a clock skew: try again tomorrow.
            }
            $counts = self::analytics($candidate['pin'], $start, $end);
            if (is_wp_error($counts)) {
                $report['errors'][] = 'Pin ' . $candidate['pin'] . ': ' . $counts->get_error_message();
                continue;
            }
            self::store((int) $row['id'], (string) ($row['angle'] ?? 'angle_a'), $counts);
            $map[$candidate['pin']]['synced_at'] = time();
            $report['updated']++;
        }

        update_option(self::PINS, self::trim($map), false);
        $ok = $report['errors'] === [];
        self::record(['ok' => $ok, 'error' => $ok ? '' : (string) $report['errors'][0], 'report' => $report]);
        return $report;
    }

    /** Cron callback: silent while nothing is configured, so an unconnected site stays quiet. */
    public static function run(): void {
        if (!self::configured()) {
            return;
        }
        self::sync();
    }

    /* ------------------------------------------------------------------ *
     * Reading what was collected
     * ------------------------------------------------------------------ */

    /** Totals per channel for the window, plus how many snapshots they rest on. */
    public static function summary(int $days = self::WINDOW): array {
        global $wpdb;
        // Matches the clock SGAP_ROI::record_event() writes created_at on.
        $since = wp_date('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $marks = implode(',', array_fill(0, count(self::TYPES), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, event_type, SUM(value) AS total, COUNT(*) AS rows_count
             FROM {$wpdb->prefix}sgap_events
             WHERE created_at >= %s AND event_type IN ({$marks})
             GROUP BY channel, event_type",
            array_merge([$since], self::TYPES)
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $channel = (string) $row['channel'];
            $out[$channel] ??= ['impressions' => 0.0, 'saves' => 0.0, 'outbound_clicks' => 0.0, 'rows' => 0];
            $out[$channel][(string) $row['event_type']] = (float) $row['total'];
            $out[$channel]['rows'] += (int) $row['rows_count'];
        }
        return $out;
    }

    /** Last run, with defaults so callers never guard for a missing option. */
    public static function state(): array {
        $state = get_option(self::STATE, []);
        $state = is_array($state) ? $state : [];
        return array_merge([
            'at' => '',
            'renew_ts' => 0,
            'ok' => false,
            'error' => '',
            'rotated' => false,
            'expires_in' => 0,
            'report' => []
        ], $state);
    }

    private static function record(array $data): array {
        $state = array_merge(self::state(), ['ok' => false, 'error' => ''], $data, ['at' => current_time('mysql')]);
        update_option(self::STATE, $state, false);
        return $state;
    }

    /** Everything an operator or an MCP client needs to judge the connection. */
    public static function status(): array {
        $state = self::state();
        $cached = (string) get_option(self::ACCESS, '');
        $expires = (int) get_option(self::EXPIRES, 0);
        $refresh_expires = (int) get_option(self::REFRESH_EXPIRES, 0);
        $mapping = self::mapping();
        $missing = [];
        if (!self::configured()) {
            $missing[] = 'a Pinterest access token, or app credentials plus a refresh token';
        } elseif (!self::refreshable()) {
            $missing[] = 'the app credentials and refresh token, so the token can renew itself';
        }
        if (self::constant_locked() !== '') {
            $problem = 'The refresh token is set in wp-config.php (' . self::constant_locked() . ') and cannot be rotated there — save it under Settings → Keys instead.';
        } elseif ($missing) {
            $problem = 'Missing ' . implode(' and ', $missing) . ', so Pinterest analytics only runs on a token that is still valid.';
        } elseif ($refresh_expires > 0 && $refresh_expires < time()) {
            $problem = 'The Pinterest refresh token expired on ' . wp_date('Y-m-d', $refresh_expires) . '; the account has to authorise the app again.';
        } elseif (!$state['ok'] && $state['error'] !== '') {
            $problem = 'The last Pinterest run failed: ' . $state['error'];
        } else {
            $problem = '';
        }
        return [
            'configured' => self::configured(),
            'refreshable' => self::refreshable(),
            'client_id_saved' => self::client_id() !== '',
            'access_token_saved' => $cached !== '' || self::saved_token() !== '',
            'refresh_token_saved' => self::refresh_token() !== '',
            'token_from_constant' => self::constant_locked() !== '' ? self::constant_locked() : null,
            'token_valid' => ($cached !== '' || self::saved_token() !== '') && ($expires === 0 || $expires > time()),
            'expires_at' => $expires > 0 ? wp_date('Y-m-d H:i:s', $expires) : null,
            'refresh_expires_at' => $refresh_expires > 0 ? wp_date('Y-m-d H:i:s', $refresh_expires) : null,
            'pins_mapped' => count($mapping),
            'last_run' => $state['at'],
            'last_ok' => (bool) $state['ok'],
            'last_rotated' => (bool) $state['rotated'],
            'error' => (string) $state['error'],
            'report' => (array) $state['report'],
            'next_run' => ($next = SGAP_Scheduler::next(self::CRON)) ? wp_date('Y-m-d H:i:s', (int) $next) : null,
            'problem' => $problem
        ];
    }

    /**
     * A line for the daily self-check, or '' when there is nothing to say. A site that
     * has not connected Pinterest is not broken, so it is not warned about.
     */
    public static function problem(): string {
        if (!self::configured()) {
            return '';
        }
        $status = self::status();
        return $status['problem'] === '' ? '' : 'Pinterest analytics: ' . $status['problem'];
    }

    /* ------------------------------------------------------------------ *
     * Admin and REST
     * ------------------------------------------------------------------ */

    /** Read-only dashboard card. */
    public static function dashboard_card(): void {
        SGAP_UI::card_open('Pinterest · last 90 days', 'chart-line');
        $status = self::status();
        if (!self::configured()) {
            echo '<p class="description">Pinterest analytics is not connected. Save a Pinterest access token, or the app credentials plus a refresh token, under '
                . '<a href="' . esc_url(admin_url('admin.php?page=sgap-settings#keys')) . '">Settings → Keys</a>. Until then the plugin only knows our own clicks, not impressions.</p>';
            SGAP_UI::card_close();
            return;
        }

        $summary = self::summary();
        $pinterest = $summary['pinterest'] ?? null;
        if ($pinterest === null) {
            echo '<p class="description">No Pinterest snapshots yet. Impressions, saves and outbound clicks appear after the daily run has matched a pin to a published post.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
                . '<th>Channel</th><th>Impressions</th><th>Saves</th><th>Outbound clicks</th><th>Posts</th>'
                . '</tr></thead><tbody><tr><td>' . SGAP_UI::channel('pinterest') . '</td>'
                . '<td>' . esc_html((string) (int) ($pinterest['impressions'] ?? 0)) . '</td>'
                . '<td>' . esc_html((string) (int) ($pinterest['saves'] ?? 0)) . '</td>'
                . '<td>' . esc_html((string) (int) ($pinterest['outbound_clicks'] ?? 0)) . '</td>'
                . '<td>' . esc_html((string) (int) ($pinterest['rows'] ?? 0)) . '</td></tr></tbody></table></div>';
        }

        $report = $status['report'];
        $parts = [];
        if ($report) {
            $parts[] = sprintf('%d pin(s) matched to a published post', (int) ($report['matched'] ?? 0));
            if (!empty($report['unmapped'])) {
                $parts[] = (int) $report['unmapped'] . ' pin(s) link somewhere else and are left out';
            }
            if (!empty($report['orphaned'])) {
                $parts[] = (int) $report['orphaned'] . ' pin(s) point at a post that is no longer published in the window';
            }
        }
        if ($status['last_run'] !== '') {
            $parts[] = 'Last run ' . $status['last_run'];
        }
        if ($parts) {
            echo '<p class="description">' . esc_html(implode(' · ', $parts)) . '</p>';
        }
        if ($status['problem'] !== '') {
            echo '<p class="description" style="color:#b32d2e">' . esc_html($status['problem']) . '</p>';
        }
        SGAP_UI::card_close();
    }

    /** REST: run once and return what changed, with the status attached. */
    public static function rest_refresh(): WP_REST_Response {
        $report = self::sync();
        return new WP_REST_Response($report + ['summary' => self::summary(), 'status' => self::status()], 200);
    }

    /* ------------------------------------------------------------------ *
     * Transport
     * ------------------------------------------------------------------ */

    /** One authorised GET. A 401 renews once and retries, because the token may have been revoked early. */
    private static function request(string $path, array $query = []) {
        $token = self::token();
        if ($token === '') {
            return new WP_Error('sgap_pinterest_no_token', self::token_problem());
        }
        $response = self::call($path, $query, $token);
        if (is_wp_error($response)) {
            return $response;
        }
        if ((int) wp_remote_retrieve_response_code($response) === 401 && self::refreshable() && self::constant_locked() === '') {
            $renewed = self::refresh();
            if (is_wp_error($renewed)) {
                return $renewed;
            }
            $response = self::call($path, $query, (string) $renewed['access_token']);
            if (is_wp_error($response)) {
                return $response;
            }
        }
        return self::decode($response, $path);
    }

    /** One HTTP GET to the v5 API, through the test seam when it is set. */
    private static function call(string $path, array $query, string $token) {
        $url = self::API . $path;
        if ($query) {
            $url = add_query_arg(array_map('strval', $query), $url);
        }
        return self::send('GET', $url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
            ]
        ]);
    }

    /** The single place both GET and POST leave through, so the tests can see every call. */
    private static function send(string $method, string $url, array $args) {
        if (is_callable(self::$transport)) {
            return call_user_func(self::$transport, $method, $url, $args);
        }
        return $method === 'POST' ? wp_remote_post($url, $args) : wp_remote_get($url, $args);
    }

    /** HTTP status and JSON body, with the API's own message on anything but 200. */
    private static function decode($response, string $path) {
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        if ($code === 200 && is_array($body)) {
            return $body;
        }
        $detail = is_array($body) ? self::detail($body) : '';
        if ($code === 401) {
            return new WP_Error('sgap_pinterest_401', 'Pinterest refused the access token (HTTP 401)' . ($detail !== '' ? ': ' . $detail : '') . '. Paste a fresh token, or save the app credentials and refresh token so it can renew itself.', ['status' => 401]);
        }
        if ($code === 403) {
            return new WP_Error('sgap_pinterest_403', 'Pinterest refused the request (HTTP 403)' . ($detail !== '' ? ': ' . $detail : '') . '. The app needs the boards:read and pins:read scopes, and pin analytics only work for the account that owns the pins.', ['status' => 403]);
        }
        if ($code === 429) {
            return new WP_Error('sgap_pinterest_429', 'Pinterest is rate limiting the app (HTTP 429)' . ($detail !== '' ? ': ' . $detail : '') . '. The next run picks up where this one stopped.', ['status' => 429]);
        }
        return new WP_Error('sgap_pinterest_http', 'Pinterest answered HTTP ' . $code . ' for ' . $path . ($detail !== '' ? ': ' . $detail : '') . '.', ['status' => $code]);
    }

    /** Why there is no token, in words the owner can act on. */
    public static function token_problem(): string {
        if (self::constant_locked() !== '') {
            return 'The Pinterest refresh token is set in wp-config.php (' . self::constant_locked() . ') and cannot be rotated there; save it under Settings → Keys.';
        }
        if (self::refreshable()) {
            return 'The Pinterest access token could not be renewed; see the status on the dashboard for the reason.';
        }
        return 'No usable Pinterest token: paste an access token under Settings → Keys, or save the app credentials and a refresh token so it renews itself.';
    }
}
