<?php
/**
 * Instagram insights ingestion — P1.5c.
 *
 * Why it exists: Bluesky and Mastodon publish like/repost/reply counts as a public read
 * (P1.5a), and Pinterest reports impressions/saves/outbound clicks for the pins the token
 * owns (P1.5b). Instagram is the third channel where the platform knows something we
 * cannot see from our own /go/ clicks: how many accounts saw the post, and what they did
 * with it. Those numbers are stored as Instagram's own event types so they never mix with
 * our clicks (sgap_cro), orders (WooCommerce) or the other platforms' snapshots.
 *
 * Facts that shape the design (official Instagram Platform documentation, read 7/11 2026):
 *   - `GET /<IG_MEDIA_ID>/insights?metric=views,likes,comments,shares,saved` returns
 *     lifetime totals as `data[].values[0].value`. There is no date range: a media object
 *     reports its own accumulated numbers, so the window below only decides *which posts*
 *     are asked about, never what the answer covers.
 *   - `impressions` is deprecated for media created after 2/7 2024 and replaced by
 *     `views`; asking for `impressions` on a new post is how this module would silently
 *     report nothing. `saved` is spelled with a d, `likes`/`comments`/`shares` without.
 *   - **A metric that is unavailable is left out of the answer instead of being 0**, and
 *     data can lag up to 48 hours. So a post with no rows yet is reported as *waiting*,
 *     never stored as a post with zero views — writing 0 would be a claim Meta has not
 *     made, and every average downstream would inherit it.
 *   - `GET /<IG_USER_ID>/media?fields=id,caption,permalink,timestamp,media_type,media_product_type`
 *     lists the account's media newest first, cursor-paged. A media object has no link
 *     field: the tracked `/go/<id>/` address only exists inside the caption, which is why
 *     the match is made on the caption and not on `permalink` (an Instagram permalink is
 *     `/p/<shortcode>/` and never contains our host).
 *   - The long-lived Instagram User token lives 60 days and is refreshed **in place** with
 *     `GET https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token`
 *     (the token must be at least 24 hours old). There is no separate refresh token, so the
 *     refreshed token is written back over the saved one — which is why a token held in a
 *     `wp-config.php` constant is refused rather than half-used.
 *   - Two login types exist and they talk to different hosts: Instagram Login
 *     (`graph.instagram.com`, scopes `instagram_business_basic` and
 *     `instagram_business_manage_insights`) and Facebook Login (`graph.facebook.com`,
 *     `instagram_basic` + `instagram_manage_insights`). The host is a setting instead of a
 *     guess from the token's shape: guessing wrong turns a working token into a 401.
 *
 * Matching is by caption, not by title, for the same reason as P1.5b: the publisher puts
 * our tracked `/go/<queue id>/` address in the caption, so a post whose caption contains
 * one of ours has an exact owner. A post without it is counted as unmapped; a `/go/` id
 * with no published Instagram row inside the window is counted as orphaned. Both are
 * reported on the dashboard instead of being guessed at.
 *
 * Replacing rather than appending, like P1.5a/b: one run deletes the five event types it
 * owns **for this channel** on that queue row, then writes the totals it just read. The
 * channel is part of the delete on purpose — `likes` is also a Bluesky/Mastodon snapshot
 * type, and deleting by queue id alone would wipe another channel's numbers.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Instagram {
    /** Daily hook; scheduled through SGAP_Scheduler on init. */
    const CRON = 'sgap_instagram_refresh';

    /** How far back a post still counts as worth measuring. Instagram keeps data for 2 years. */
    const WINDOW = 90;

    /** Event types this class owns; a run replaces them for a queue row on this channel. */
    const TYPES = ['views', 'likes', 'comments', 'shares', 'saves'];

    /** What the media insights endpoint is asked for, and how the answer is read. */
    const METRICS = ['views', 'likes', 'comments', 'shares', 'saved'];
    const METRIC_MAP = ['views' => 'views', 'likes' => 'likes', 'comments' => 'comments', 'shares' => 'shares', 'saved' => 'saves'];

    /**
     * The three metrics every supported media type reports. If the full set is refused
     * (a media type that has no `saved`, for instance), the call is retried with these
     * instead of failing the post: three real numbers beat five that never arrive.
     */
    const NARROW = ['views', 'likes', 'comments'];

    const HOST_IG = 'graph.instagram.com';
    const HOST_FB = 'graph.facebook.com';

    const EXPIRES = 'sgap_instagram_access_expires';
    const MEDIA = 'sgap_instagram_media';
    const CURSOR = 'sgap_instagram_after';
    const STATE = 'sgap_instagram_state';

    /** Refresh this long before the 60-day token runs out, so a failed refresh has room. */
    const MARGIN = 7 * DAY_IN_SECONDS;
    /** Instagram refuses a refresh inside the first 24 hours; never ask more than once per window. */
    const RENEW_WINDOW = 300;

    const PAGE = 50;
    const MAX_PAGES = 6;
    /** Insights calls per run: the account is rate limited per hour, so a backlog drains over days. */
    const MAX_CALLS = 20;
    /** How many media mappings are kept; older posts drop out after they leave the window. */
    const KEEP_MEDIA = 400;

    /**
     * Test seam: function(string $method, string $url, array $args) returning a WordPress
     * HTTP response. Null in production. The smoke suite cannot redefine wp_remote_get,
     * so every call goes through here.
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

    /** The professional account's id (178414…), not the @handle. */
    public static function user_id(): string {
        return trim((string) SGAP_Settings::get('instagram_user_id', ''));
    }

    /** Which Graph host the token belongs to; the two login types are not interchangeable. */
    public static function host(): string {
        $host = trim((string) SGAP_Settings::get('instagram_graph_host', self::HOST_IG));
        return $host === self::HOST_FB ? self::HOST_FB : self::HOST_IG;
    }

    /** The long-lived Instagram User token, refreshed in place once it is inside MARGIN. */
    public static function saved_token(): string {
        return trim((string) SGAP_Settings::get('instagram_access_token', ''));
    }

    public static function configured(): bool {
        return self::user_id() !== '' && self::saved_token() !== '';
    }

    /**
     * A token can only be refreshed in place against graph.instagram.com; a Facebook
     * Login token needs the app secret and a different exchange, which is out of scope.
     * A token in wp-config.php cannot be replaced at all.
     */
    public static function refreshable(): bool {
        return self::host() === self::HOST_IG && self::saved_token() !== '' && self::constant_locked() === '';
    }

    public static function constant_locked(): string {
        return SGAP_Settings::constant_name('instagram_access_token');
    }

    /* ------------------------------------------------------------------ *
     * Token
     * ------------------------------------------------------------------ */

    /**
     * A usable token. Returns '' when there is none, so callers report that instead of
     * sending an empty Bearer header and calling the 401 an API problem.
     */
    public static function token(): string {
        $token = self::saved_token();
        if ($token === '' || self::constant_locked() !== '') {
            return $token;
        }
        $expires = (int) get_option(self::EXPIRES, 0);
        // An unknown expiry is used until Instagram refuses it: treating "no expiry
        // recorded" as expired would throw away a token that works.
        if ($expires === 0 || $expires > time() + self::MARGIN) {
            return $token;
        }
        if (self::refreshable()) {
            $renewed = self::refresh();
            return is_wp_error($renewed) ? '' : (string) ($renewed['access_token'] ?? '');
        }
        return $token; // No way to renew: use it until the API says no.
    }

    /**
     * Refreshes the long-lived token in place (no separate refresh token exists) and
     * stores the new value with its expiry before anything else can fail.
     *
     * @return array{access_token:string,expires_in:int}|WP_Error
     */
    public static function refresh() {
        if (self::constant_locked() !== '') {
            $message = 'The Instagram access token is set in wp-config.php (' . self::constant_locked() . '), so a refreshed token cannot be stored. Remove the constant and save the token under Settings → Keys.';
            self::record(['ok' => false, 'error' => $message]);
            return new WP_Error('sgap_instagram_constant', $message);
        }
        if (self::host() !== self::HOST_IG) {
            $message = 'The Instagram token is for Facebook Login (' . self::HOST_FB . '), which needs the app secret to renew. Paste a fresh 60-day token under Settings → Keys when it expires, or switch to an Instagram Login token.';
            self::record(['ok' => false, 'error' => $message]);
            return new WP_Error('sgap_instagram_host', $message);
        }
        if (self::saved_token() === '') {
            $message = 'Instagram has no access token saved, so there is nothing to renew: paste a long-lived Instagram User token under Settings → Keys.';
            self::record(['ok' => false, 'error' => $message]);
            return new WP_Error('sgap_instagram_no_token', $message);
        }
        $state = self::state();
        if ((int) $state['renew_ts'] > 0 && (time() - (int) $state['renew_ts']) < self::RENEW_WINDOW) {
            return new WP_Error('sgap_instagram_throttled', 'An Instagram token refresh was attempted less than five minutes ago; not asking again yet.');
        }

        $response = self::send('GET', add_query_arg([
            'grant_type' => 'ig_refresh_token',
            'access_token' => self::saved_token()
        ], 'https://' . self::HOST_IG . '/refresh_access_token'), [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
            ]
        ]);
        if (is_wp_error($response)) {
            $message = 'Instagram could not be reached to refresh the token: ' . $response->get_error_message() . '.';
            self::record(['ok' => false, 'renew_ts' => time(), 'error' => $message]);
            return new WP_Error('sgap_instagram_transport', $message);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body) || empty($body['access_token'])) {
            $detail = self::detail(is_array($body) ? $body : []);
            $message = 'Instagram refused the token refresh (HTTP ' . $code . ')' . ($detail !== '' ? ': ' . $detail : '') . '. A token must be at least 24 hours old to be refreshed; once it has expired the account has to authorise the app again.';
            self::record(['ok' => false, 'renew_ts' => time(), 'error' => $message]);
            return new WP_Error('sgap_instagram_refused', $message, ['status' => $code]);
        }

        $token = (string) $body['access_token'];
        $expires_in = (int) ($body['expires_in'] ?? 60 * DAY_IN_SECONDS);
        // Store first: a crash after the response but before the write would leave an
        // about-to-expire token behind and force a new authorisation.
        SGAP_Settings::set('instagram_access_token', $token);
        update_option(self::EXPIRES, time() + max(DAY_IN_SECONDS, $expires_in), false);
        self::record(['ok' => true, 'renew_ts' => time(), 'error' => '', 'expires_in' => $expires_in]);
        return ['access_token' => $token, 'expires_in' => $expires_in];
    }

    /** The API's own reason from a Graph error body (`error.message`), trimmed to 200 characters. */
    public static function detail(array $body): string {
        $value = $body['error'] ?? ($body['message'] ?? ($body['error_description'] ?? ''));
        if (is_array($value)) {
            $value = $value['message'] ?? '';
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        return substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 200);
    }

    /** Graph error code, when there is one: 190 is "the token is no longer valid". */
    public static function error_code(array $body): int {
        $error = $body['error'] ?? [];
        if (is_array($error) && isset($error['code'])) {
            return (int) $error['code'];
        }
        return 0;
    }

    /* ------------------------------------------------------------------ *
     * Media and their queue rows
     * ------------------------------------------------------------------ */

    /** media_id => ['queue' => int, 'permalink' => string, 'created_at' => string, 'synced_at' => int]. */
    public static function mapping(): array {
        $map = get_option(self::MEDIA, []);
        return is_array($map) ? $map : [];
    }

    /**
     * The queue row a caption points at, or 0 when it contains none of our tracked links.
     * Only this site's host counts: another site's /go/ path is not our receipt. The
     * scheme is optional because a caption often writes `steamgears.eu/go/42/` without it.
     */
    public static function queue_id(string $text): int {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($host === '') {
            return 0;
        }
        $host = preg_quote(preg_replace('/^www\./', '', $host), '#');
        return preg_match('#(?:https?://)?(?:www\.)?' . $host . '/go/(\d+)/?#i', $text, $m) === 1 ? (int) $m[1] : 0;
    }

    /**
     * One batch of media, newest first, merged into the mapping. Pagination resumes from
     * the saved cursor so a large account is walked over several runs instead of one long
     * request chain, and the cursor is cleared when the last page is in.
     *
     * @return array{seen:int,pages:int,complete:bool,media:int}|WP_Error
     */
    public static function scan(int $max_pages = self::MAX_PAGES) {
        $map = self::mapping();
        $cursor = (string) get_option(self::CURSOR, '');
        $seen = 0;
        $pages = 0;
        while ($pages < max(1, $max_pages)) {
            $query = [
                'fields' => 'id,caption,permalink,timestamp,media_type,media_product_type',
                'limit' => self::PAGE
            ];
            if ($cursor !== '') {
                $query['after'] = $cursor;
            }
            $body = self::request('/' . rawurlencode(self::user_id()) . '/media', $query);
            if (is_wp_error($body)) {
                return $body;
            }
            $pages++;
            foreach ((array) ($body['data'] ?? []) as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $map[$id] = [
                    'queue' => self::queue_id((string) ($item['caption'] ?? '')),
                    'permalink' => (string) ($item['permalink'] ?? ''),
                    'created_at' => (string) ($item['timestamp'] ?? ''),
                    'synced_at' => (int) ($map[$id]['synced_at'] ?? 0)
                ];
                $seen++;
            }
            $cursor = (string) ($body['paging']['cursors']['after'] ?? '');
            // No cursor means the last page; a cursor equal to the one we asked for means
            // the API is repeating itself, and retrying forever would burn the rate limit.
            if ($cursor === '' || ($body['paging']['next'] ?? '') === '') {
                $cursor = '';
                break;
            }
        }
        update_option(self::CURSOR, $cursor, false);
        update_option(self::MEDIA, self::trim($map), false);
        return ['seen' => $seen, 'pages' => $pages, 'complete' => $cursor === '', 'media' => count($map)];
    }

    /** Keeps the newest KEEP_MEDIA entries, so an old account cannot grow the option forever. */
    public static function trim(array $map): array {
        if (count($map) <= self::KEEP_MEDIA) {
            return $map;
        }
        uasort($map, static fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_slice($map, 0, self::KEEP_MEDIA, true);
    }

    /** Published Instagram queue rows inside the window, indexed by id. */
    public static function targets(int $days = self::WINDOW): array {
        global $wpdb;
        // published_at is written with current_time('mysql'), so the window is site time too.
        $since = wp_date('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, angle, external_id, published_at FROM {$wpdb->prefix}sgap_queue
             WHERE channel = 'instagram' AND status = 'published' AND published_at >= %s
             ORDER BY published_at DESC",
            $since
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /* ------------------------------------------------------------------ *
     * Insights
     * ------------------------------------------------------------------ */

    /**
     * Lifetime views, likes, comments, shares and saves for one media object.
     *
     * The answer is *partial by design*: Instagram leaves a metric out when it has no
     * data for it yet (up to 48 hours after posting), so the returned array only holds
     * the metrics that actually came back. An empty array means "no data yet", which the
     * caller reports as waiting instead of storing zeros.
     *
     * @return array<string,int>|WP_Error
     */
    public static function analytics(string $media_id, array $metrics = self::METRICS) {
        $media_id = trim($media_id);
        if ($media_id === '') {
            return new WP_Error('sgap_instagram_no_media', 'No Instagram media id was given.');
        }
        $body = self::request('/' . rawurlencode($media_id) . '/insights', ['metric' => implode(',', $metrics)]);
        if (is_wp_error($body)) {
            // A media type that does not carry one of the metrics refuses the whole call
            // with HTTP 400. Retry once with the three every supported type reports, so
            // one unavailable metric cannot cost the post its other numbers.
            $status = (int) ((array) $body->get_error_data())['status'] ?? 0;
            if ($metrics !== self::NARROW && $status === 400 && $body->get_error_code() === 'sgap_instagram_400') {
                $narrow = self::analytics($media_id, self::NARROW);
                if (!is_wp_error($narrow)) {
                    return $narrow;
                }
            }
            return $body;
        }
        $counts = [];
        foreach ((array) ($body['data'] ?? []) as $metric) {
            $name = (string) ($metric['name'] ?? '');
            if (!isset(self::METRIC_MAP[$name])) {
                continue;
            }
            $values = (array) ($metric['values'] ?? []);
            $value = $values[0]['value'] ?? null;
            if ($value === null) {
                continue;
            }
            $counts[self::METRIC_MAP[$name]] = max(0, (int) round((float) $value));
        }
        return $counts;
    }

    /** Replaces this channel's owned event types for one queue row. */
    public static function store(int $queue_id, string $angle, array $counts): void {
        global $wpdb;
        if ($queue_id <= 0) {
            return;
        }
        $types = self::TYPES;
        $marks = implode(',', array_fill(0, count($types), '%s'));
        // The channel is part of the delete: `likes` is also a Bluesky/Mastodon snapshot
        // type, so deleting by queue id alone would wipe another channel's numbers.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sgap_events WHERE queue_id = %d AND channel = 'instagram' AND event_type IN ({$marks})",
            array_merge([$queue_id], $types)
        ));
        foreach ($types as $type) {
            if (!array_key_exists($type, $counts)) {
                continue;
            }
            SGAP_ROI::record_event($queue_id, 'instagram', $angle, $type, (float) $counts[$type]);
        }
    }

    /**
     * One run: list the media, tie them to published queue rows, read the totals, store
     * them. Nothing is invented for a post we cannot tie to a row.
     *
     * @return array<string,mixed>
     */
    public static function sync(int $limit = self::MAX_CALLS): array {
        $report = [
            'configured' => self::configured(),
            'media' => 0,
            'matched' => 0,
            'updated' => 0,
            'waiting' => 0,
            'unmapped' => 0,
            'orphaned' => 0,
            'errors' => [],
            'window' => self::WINDOW,
            'at' => current_time('mysql')
        ];
        if (!self::configured()) {
            $report['error'] = 'Instagram insights is not connected: save the professional account id and a long-lived Instagram User token under Settings → Keys.';
            self::record(['ok' => false, 'error' => $report['error'], 'report' => $report]);
            return $report;
        }

        // A refused listing must not throw away the media already mapped: their totals may
        // still be readable, and the error is reported alongside whatever the run collected.
        $scan = self::scan();
        if (is_wp_error($scan)) {
            $report['errors'][] = 'Media listing: ' . $scan->get_error_message();
            $report['media'] = count(self::mapping());
        } else {
            $report['media'] = (int) $scan['media'];
        }

        $targets = self::targets();
        $map = self::mapping();
        $candidates = [];
        foreach ($map as $media_id => $meta) {
            $queue = (int) ($meta['queue'] ?? 0);
            if ($queue === 0) {
                $report['unmapped']++;
                continue;
            }
            if (!isset($targets[$queue])) {
                $report['orphaned']++;
                continue;
            }
            $candidates[] = ['media' => (string) $media_id, 'queue' => $queue, 'synced' => (int) ($meta['synced_at'] ?? 0)];
        }
        $report['matched'] = count($candidates);
        // Oldest snapshot first, so a backlog larger than one run's budget drains fairly.
        usort($candidates, static fn($a, $b) => $a['synced'] <=> $b['synced']);

        foreach (array_slice($candidates, 0, max(1, $limit)) as $candidate) {
            $row = $targets[$candidate['queue']];
            $counts = self::analytics($candidate['media']);
            if (is_wp_error($counts)) {
                $report['errors'][] = 'Media ' . $candidate['media'] . ': ' . $counts->get_error_message();
                continue;
            }
            if ($counts === []) {
                // Meta has not published numbers for this post yet (up to 48 hours). It is
                // not a post with zero views, so nothing is stored and it is retried later.
                $report['waiting']++;
                continue;
            }
            self::store((int) $row['id'], (string) ($row['angle'] ?? 'angle_a'), $counts);
            $map[$candidate['media']]['synced_at'] = time();
            $report['updated']++;
        }

        update_option(self::MEDIA, self::trim($map), false);
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

    /**
     * Totals per channel for the window, plus how many snapshots they rest on. The number
     * is "the posts from the last WINDOW days, counted the way Instagram counts them", not
     * a date-range sum: a media object reports its own lifetime totals.
     */
    public static function summary(int $days = self::WINDOW): array {
        global $wpdb;
        // Matches the clock SGAP_ROI::record_event() writes created_at on.
        $since = wp_date('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $marks = implode(',', array_fill(0, count(self::TYPES), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, event_type, SUM(value) AS total, COUNT(*) AS rows_count
             FROM {$wpdb->prefix}sgap_events
             WHERE created_at >= %s AND channel = 'instagram' AND event_type IN ({$marks})
             GROUP BY channel, event_type",
            array_merge([$since], self::TYPES)
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $channel = (string) $row['channel'];
            $out[$channel] ??= array_merge(array_fill_keys(self::TYPES, 0.0), ['rows' => 0]);
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
        $expires = (int) get_option(self::EXPIRES, 0);
        $missing = [];
        if (self::user_id() === '') {
            $missing[] = 'the professional account id';
        }
        if (self::saved_token() === '') {
            $missing[] = 'a long-lived Instagram User token';
        }
        if (self::constant_locked() !== '') {
            $problem = 'The Instagram token is set in wp-config.php (' . self::constant_locked() . ') and cannot be refreshed there — save it under Settings → Keys instead.';
        } elseif ($missing) {
            $problem = 'Missing ' . implode(' and ', $missing) . ', so Instagram insights cannot run.';
        } elseif ($expires > 0 && $expires < time()) {
            $problem = 'The Instagram token expired on ' . wp_date('Y-m-d', $expires) . '; the account has to authorise the app again.';
        } elseif (!$state['ok'] && $state['error'] !== '') {
            $problem = 'The last Instagram run failed: ' . $state['error'];
        } else {
            $problem = '';
        }
        return [
            'configured' => self::configured(),
            'host' => self::host(),
            'refreshable' => self::refreshable(),
            'user_id_saved' => self::user_id() !== '',
            'token_saved' => self::saved_token() !== '',
            'token_from_constant' => self::constant_locked() !== '' ? self::constant_locked() : null,
            'token_valid' => self::saved_token() !== '' && ($expires === 0 || $expires > time()),
            'expires_at' => $expires > 0 ? wp_date('Y-m-d H:i:s', $expires) : null,
            'media_mapped' => count(self::mapping()),
            'last_run' => $state['at'],
            'last_ok' => (bool) $state['ok'],
            'error' => (string) $state['error'],
            'report' => (array) $state['report'],
            'next_run' => ($next = SGAP_Scheduler::next(self::CRON)) ? wp_date('Y-m-d H:i:s', (int) $next) : null,
            'problem' => $problem
        ];
    }

    /**
     * A line for the daily self-check, or '' when there is nothing to say. An Instagram
     * account that was never connected is not broken, so it is not warned about.
     */
    public static function problem(): string {
        if (!self::configured()) {
            return '';
        }
        $status = self::status();
        return $status['problem'] === '' ? '' : 'Instagram insights: ' . $status['problem'];
    }

    /* ------------------------------------------------------------------ *
     * Admin and REST
     * ------------------------------------------------------------------ */

    /** Read-only dashboard card. */
    public static function dashboard_card(): void {
        SGAP_UI::card_open('Instagram · posts from the last 90 days', 'instagram');
        $status = self::status();
        if (!self::configured()) {
            echo '<p class="description">Instagram insights is not connected. Save the professional account id and a long-lived Instagram User token under '
                . '<a href="' . esc_url(admin_url('admin.php?page=sgap-settings#keys')) . '">Settings → Keys</a>. Until then the plugin only knows our own clicks, not views.</p>';
            SGAP_UI::card_close();
            return;
        }

        $summary = self::summary();
        $instagram = $summary['instagram'] ?? null;
        if ($instagram === null) {
            echo '<p class="description">No Instagram snapshots yet. Views, likes, comments, shares and saves appear after a run has matched a post to a published one; Instagram can take up to 48 hours to report them.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
                . '<th>Channel</th><th>Views</th><th>Likes</th><th>Comments</th><th>Shares</th><th>Saves</th><th>Posts</th>'
                . '</tr></thead><tbody><tr><td>' . SGAP_UI::channel('instagram') . '</td>';
            foreach (self::TYPES as $type) {
                echo '<td>' . esc_html((string) (int) ($instagram[$type] ?? 0)) . '</td>';
            }
            echo '<td>' . esc_html((string) (int) ($instagram['rows'] ?? 0)) . '</td></tr></tbody></table></div>';
        }

        $report = $status['report'];
        $parts = [];
        if ($report) {
            $parts[] = sprintf('%d post(s) matched to a published queue row', (int) ($report['matched'] ?? 0));
            if (!empty($report['waiting'])) {
                $parts[] = (int) $report['waiting'] . ' post(s) waiting for Instagram to report (up to 48 h)';
            }
            if (!empty($report['unmapped'])) {
                $parts[] = (int) $report['unmapped'] . ' post(s) carry no tracked link and are left out';
            }
            if (!empty($report['orphaned'])) {
                $parts[] = (int) $report['orphaned'] . ' post(s) point at a queue row that is not published in the window';
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

    /**
     * One authorised GET. Instagram reports an invalid token as HTTP 400 with code 190
     * rather than 401, so both are treated the same way: refresh once, then retry.
     */
    private static function request(string $path, array $query = []) {
        $token = self::token();
        if ($token === '') {
            return new WP_Error('sgap_instagram_no_token', self::token_problem());
        }
        [$body, $response] = self::call($path, $query, $token);
        // The token check comes before the error is returned: Instagram's "the token is no
        // longer valid" is a WP_Error by the time it gets here, and returning it first would
        // make every expired token a dead end instead of something the refresh can fix.
        $stale = is_wp_error($body)
            ? $body->get_error_code() === 'sgap_instagram_401'
            : ((int) wp_remote_retrieve_response_code($response) === 401 || self::error_code((array) $body) === 190);
        if ($stale && self::refreshable()) {
            $renewed = self::refresh();
            if (is_wp_error($renewed)) {
                return $renewed;
            }
            [$body, $response] = self::call($path, $query, (string) $renewed['access_token']);
        }
        return $body;
    }

    /**
     * One HTTP GET. Returns the decoded body (or a WP_Error) together with the raw
     * response, because the caller has to look at both the status and the error code.
     *
     * @return array{0:array|WP_Error,1:mixed}
     */
    private static function call(string $path, array $query, string $token): array {
        $url = 'https://' . self::host() . $path;
        if ($query) {
            $url = add_query_arg(array_map('strval', $query), $url);
        }
        $response = self::send('GET', $url, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
            ]
        ]);
        if (is_wp_error($response)) {
            return [$response, $response];
        }
        return [self::decode($response, $path), $response];
    }

    /** The single place every call leaves through, so the tests can see all of them. */
    private static function send(string $method, string $url, array $args) {
        if (is_callable(self::$transport)) {
            return call_user_func(self::$transport, $method, $url, $args);
        }
        return wp_remote_get($url, $args);
    }

    /** HTTP status and JSON body, with the API's own message on anything but 200. */
    private static function decode($response, string $path) {
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        if ($code === 200 && is_array($body)) {
            return $body;
        }
        $body = is_array($body) ? $body : [];
        $detail = self::detail($body);
        $suffix = $detail !== '' ? ': ' . $detail : '';
        $graph = self::error_code($body);
        if ($code === 401 || $graph === 190) {
            return new WP_Error('sgap_instagram_401', 'Instagram refused the access token (HTTP ' . $code . ')' . $suffix . '. A long-lived token lives 60 days; an expired one has to be replaced under Settings → Keys.', ['status' => $code]);
        }
        if ($graph === 4 || $graph === 17 || $graph === 32 || $code === 429) {
            return new WP_Error('sgap_instagram_429', 'Instagram is rate limiting the app (HTTP ' . $code . ')' . $suffix . '. The next run picks up where this one stopped.', ['status' => $code]);
        }
        if ($code === 403 || $graph === 10 || $graph === 200) {
            return new WP_Error('sgap_instagram_403', 'Instagram refused the request (HTTP ' . $code . ')' . $suffix . '. The token needs instagram_business_basic and instagram_business_manage_insights (Instagram Login), and the account must be a professional one you manage.', ['status' => $code]);
        }
        if ($code === 400) {
            return new WP_Error('sgap_instagram_400', 'Instagram answered HTTP 400 for ' . $path . $suffix . '.', ['status' => 400]);
        }
        return new WP_Error('sgap_instagram_http', 'Instagram answered HTTP ' . $code . ' for ' . $path . $suffix . '.', ['status' => $code]);
    }

    /** Why there is no token, in words the owner can act on. */
    public static function token_problem(): string {
        if (self::constant_locked() !== '') {
            return 'The Instagram token is set in wp-config.php (' . self::constant_locked() . ') and cannot be refreshed there; save it under Settings → Keys.';
        }
        if (self::saved_token() === '') {
            return 'No Instagram token: paste a long-lived Instagram User token under Settings → Keys, together with the professional account id.';
        }
        return 'The Instagram token could not be refreshed; see the status on the dashboard for the reason.';
    }
}
