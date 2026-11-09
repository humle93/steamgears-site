<?php
/**
 * Reddit — P5.
 *
 * Why it exists: Reddit is the one channel where a small shop can borrow an audience
 * without a media budget, and it is also the channel that punishes a plugin that does
 * too much. So this module draws the line at links:
 *
 *   - **It posts link posts**, one thread at a time, to a subreddit the owner named. It
 *     never comments, never votes, never messages. Automated self-promotion in somebody
 *     else's thread is spam under Reddit's rules and ends with a banned account.
 *   - **Reading needs a different grant than writing.** `client_credentials` reads public
 *     listings without a user; a script app with a refresh token writes as the account.
 *     The radar therefore works before publishing is connected, and the admin page says
 *     which of the two is available instead of failing at the first submit.
 *   - **The queue is its own table** (`sgap_reddit_posts`), not `sgap_queue`: Publer has
 *     no Reddit channel, and a link post carries a subreddit and a Reddit id that a
 *     social post does not. It has a daily cap, a minimum interval between two posts and
 *     a 5 → 15 → 45 minute backoff, because Reddit refuses bursts and remembers them.
 *   - **Every link carries UTM** (`utm_source=reddit`, `utm_medium=forum`,
 *     `utm_campaign=<board>`), so GA4 can say which board paid for the visit. A shared
 *     link that cannot be attributed is a guess with a URL.
 *   - **Failures are stored, not swallowed.** Reddit answers HTTP 200 with an `errors`
 *     array for a refused submit; that array is the error message. The last failure is
 *     kept for the admin page and cleared on the next success.
 *
 * Facts that shape the code (Reddit API, read 9/11 2026):
 *   - The token endpoint is `https://www.reddit.com/api/v1/access_token` with HTTP Basic
 *     auth (app id : secret) and the app's own User-Agent; the other endpoints live on
 *     `https://oauth.reddit.com` and need `Authorization: Bearer`.
 *   - A **script app** answers `grant_type=password` and returns a `refresh_token` that
 *     is rotated on every use, so the new one has to be written back.
 *   - `POST /api/submit` takes `sr`, `kind=link`, `title`, `url` and `api_type=json`.
 *     A refusal arrives inside `json.errors`, not as a non-2xx status.
 *   - Public listings are `GET /r/<sub>/hot?limit=25&raw_json=1`; `data.children[]` is
 *     the list, `data.score` and `data.num_comments` are the two numbers worth ranking.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Reddit {
    /** Every 15 minutes: one queued row per run, so a refusal never drains the queue. */
    const CRON = 'sgap_reddit_tick';
    const TABLE = 'sgap_reddit_posts';
    const RADAR_OPTION = 'sgap_reddit_radar';
    const SEEN_OPTION = 'sgap_reddit_seen';
    const TOKEN_TRANSIENT = 'sgap_reddit_token';
    const LAST_ERROR = 'sgap_reddit_last_error';
    const TOKEN_URL = 'https://www.reddit.com/api/v1/access_token';
    const API = 'https://oauth.reddit.com';
    const UA = 'web:steamgears-forum:1.0 (forum on steamgears.eu)';

    const MAX_PER_RUN = 1;
    const MAX_ATTEMPTS = 3;
    const KEEP_SEEN = 400;
    const RADAR_ITEMS = 12;
    const BACKOFF = [5, 15, 45];

    /**
     * Test seam: function(string $method, string $url, array $args) returning a WordPress
     * HTTP response. Null in production, so nothing leaves a test machine.
     */
    public static $transport = null;

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'tick']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_post_sgap_reddit_radar', [__CLASS__, 'handle_radar']);
        SGAP_Scheduler::recurring(self::CRON, 15 * MINUTE_IN_SECONDS);
    }

    /* ------------------------------------------------------------------ *
     * Credentials
     * ------------------------------------------------------------------ */

    public static function client_id(): string {
        return trim((string) SGAP_Settings::get('reddit_client_id', ''));
    }

    public static function client_secret(): string {
        return trim((string) SGAP_Settings::get('reddit_client_secret', ''));
    }

    public static function username(): string {
        return trim((string) SGAP_Settings::get('reddit_username', ''));
    }

    public static function password(): string {
        return trim((string) SGAP_Settings::get('reddit_password', ''));
    }

    public static function refresh_token(): string {
        return trim((string) SGAP_Settings::get('reddit_refresh_token', ''));
    }

    public static function configured(): bool {
        return self::client_id() !== '' && self::client_secret() !== '';
    }

    /** Writing needs a user: a refresh token, or a username and password to mint one. */
    public static function can_post(): bool {
        return self::configured() && (self::refresh_token() !== '' || (self::username() !== '' && self::password() !== ''));
    }

    public static function daily_cap(): int {
        return max(1, min(10, (int) SGAP_Settings::get('reddit_daily_cap', 2)));
    }

    public static function min_words(): int {
        return max(20, min(5000, (int) SGAP_Settings::get('reddit_min_words', 120)));
    }

    public static function min_interval(): int {
        return max(10, min(1440, (int) SGAP_Settings::get('reddit_min_interval', 90)));
    }

    /** A subreddit name Reddit will accept: 2–21 letters, digits or underscore. */
    public static function clean_sub(string $sub): string {
        $sub = trim($sub);
        $sub = preg_replace('#^/?r/#i', '', $sub);
        return preg_match('/^[A-Za-z0-9_]{2,21}$/', (string) $sub) === 1 ? (string) $sub : '';
    }

    /** @return string[] */
    public static function radar_subs(): array {
        $out = [];
        foreach (preg_split('/[\s,]+/', (string) SGAP_Settings::get('reddit_radar_subs', 'steampunk,cosplay')) as $sub) {
            $clean = self::clean_sub((string) $sub);
            if ($clean !== '' && !in_array($clean, $out, true)) {
                $out[] = $clean;
            }
        }
        return array_slice($out, 0, 5);
    }

    /** @return string[] */
    public static function keywords(): array {
        $out = [];
        // Comma-separated, because a phrase is one keyword: splitting on spaces would turn
        // "top hat" into "top" and "hat" and match every photo of a hat.
        foreach (explode(',', strtolower((string) SGAP_Settings::get('reddit_keywords', 'steampunk,cosplay,goggles,top hat,brass'))) as $word) {
            $word = trim($word);
            if ($word !== '' && !in_array($word, $out, true)) {
                $out[] = $word;
            }
        }
        return array_slice($out, 0, 12);
    }

    /* ------------------------------------------------------------------ *
     * HTTP and tokens
     * ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $args
     * @return array|WP_Error A WordPress HTTP response, or an error from the seam.
     */
    private static function http(string $method, string $url, array $args) {
        if (is_callable(self::$transport)) {
            return (self::$transport)($method, $url, $args);
        }
        return wp_remote_request($url, array_merge(['method' => $method, 'timeout' => 20], $args));
    }

    private static function cache_key(bool $write): string {
        return self::TOKEN_TRANSIENT . ($write ? '_write' : '_read');
    }

    /**
     * An access token for one of the two grants. The write grant prefers the stored
     * refresh token; when only a username and password exist, Reddit mints a refresh
     * token and it is saved immediately, because that is the only copy.
     *
     * @return string|WP_Error
     */
    public static function token(bool $write = false) {
        $cached = get_transient(self::cache_key($write));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        if (!self::configured()) {
            return new WP_Error('sgap_reddit_config', 'Save the Reddit app id and secret first.');
        }
        if ($write) {
            if (self::refresh_token() !== '') {
                $body = ['grant_type' => 'refresh_token', 'refresh_token' => self::refresh_token()];
            } elseif (self::username() !== '' && self::password() !== '') {
                $body = [
                    'grant_type' => 'password',
                    'username' => self::username(),
                    'password' => self::password()
                ];
            } else {
                return new WP_Error('sgap_reddit_user', 'Publishing needs a Reddit user: save the username and password, or a refresh token.');
            }
        } else {
            $body = ['grant_type' => 'client_credentials'];
        }
        $response = self::http('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(self::client_id() . ':' . self::client_secret()),
                'User-Agent' => self::UA,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => $body
        ]);
        if (is_wp_error($response)) {
            self::fail($response->get_error_message());
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($data) || empty($data['access_token'])) {
            $reason = is_array($data) ? (string) ($data['error'] ?? $data['message'] ?? '') : '';
            $message = 'Reddit refused the token (' . $code . ')' . ($reason !== '' ? ': ' . $reason : '');
            self::fail($message);
            return new WP_Error('sgap_reddit_token', $message);
        }
        if ($write && !empty($data['refresh_token'])) {
            // Rotated on every use; losing it means the owner has to paste a new one.
            SGAP_Settings::set('reddit_refresh_token', (string) $data['refresh_token']);
        }
        $ttl = max(60, (int) ($data['expires_in'] ?? 3600) - 60);
        set_transient(self::cache_key($write), (string) $data['access_token'], $ttl);
        return (string) $data['access_token'];
    }

    /**
     * One API call with a bearer token; a 401 drops the cached token and retries once,
     * because a token can expire between the cache read and the call.
     *
     * @return array|WP_Error
     */
    public static function api(string $method, string $path, array $args = [], bool $write = false) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = self::token($write);
            if (is_wp_error($token)) {
                return $token;
            }
            $args['headers'] = array_merge(
                ['Authorization' => 'Bearer ' . $token, 'User-Agent' => self::UA],
                (array) ($args['headers'] ?? [])
            );
            $response = self::http($method, self::API . $path, $args);
            if (is_wp_error($response)) {
                self::fail($response->get_error_message());
                return $response;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if ($code === 401 && $attempt === 0) {
                delete_transient(self::cache_key($write));
                continue;
            }
            if ($code < 200 || $code >= 300) {
                $reason = is_array($body) && isset($body['message']) ? ': ' . (string) $body['message'] : '';
                $message = 'Reddit answered ' . $code . $reason;
                self::fail($message);
                return new WP_Error('sgap_reddit_http', $message);
            }
            return is_array($body) ? $body : [];
        }
        return new WP_Error('sgap_reddit_auth', 'Reddit kept refusing the token.');
    }

    private static function fail(string $message): void {
        update_option(self::LAST_ERROR, mb_substr($message, 0, 300), false);
    }

    private static function clear_error(): void {
        delete_option(self::LAST_ERROR);
    }

    public static function last_error(): string {
        return (string) get_option(self::LAST_ERROR, '');
    }

    /** What the admin page and the REST route report, in the same shape as channel health. */
    public static function status(): array {
        if (!SGAP_Settings::enabled('reddit_enabled')) {
            return ['state' => 'off', 'label' => 'Reddit is switched off', 'detail' => 'Turn it on under Settings → Forum & Reddit.'];
        }
        if (!self::configured()) {
            return ['state' => 'missing_token', 'label' => 'No Reddit app', 'detail' => 'Save the app id and secret of a Reddit script app. Reading needs nothing else; publishing needs a user.'];
        }
        if (!self::can_post()) {
            return ['state' => 'read_only', 'label' => 'Reading only', 'detail' => 'The radar works with the app credentials. To publish links, save the Reddit username and password, or a refresh token.'];
        }
        $error = self::last_error();
        if ($error !== '') {
            return ['state' => 'refused', 'label' => 'Reddit refused the last call', 'detail' => $error];
        }
        return ['state' => 'ready', 'label' => 'Connected', 'detail' => 'A link post may be queued for r/' . (string) SGAP_Settings::get('reddit_default_sub', 'steampunk') . ', within ' . self::daily_cap() . ' a day and at least ' . self::min_interval() . ' minutes apart.'];
    }

    /* ------------------------------------------------------------------ *
     * Posting
     * ------------------------------------------------------------------ */

    /** The tracked URL that goes to Reddit: the thread, with the board as the campaign. */
    public static function share_url(int $topic_id, string $subreddit): string {
        return add_query_arg([
            'utm_source' => 'reddit',
            'utm_medium' => 'forum',
            'utm_campaign' => sanitize_key(SGAP_Forum::board_label(SGAP_Forum::board_of($topic_id)) !== '' ? SGAP_Forum::board_of($topic_id) : $subreddit)
        ], (string) get_permalink($topic_id));
    }

    /**
     * Submits one link post.
     *
     * @return array{id:string,url:string}|WP_Error
     */
    public static function submit(string $subreddit, string $title, string $url) {
        $sub = self::clean_sub($subreddit);
        if ($sub === '') {
            return new WP_Error('sgap_reddit_sub', 'A subreddit name is needed, e.g. steampunk.');
        }
        $title = trim(wp_strip_all_tags($title));
        if ($title === '' || mb_strlen($title) > 300) {
            return new WP_Error('sgap_reddit_title', 'A Reddit title must be 1–300 characters.');
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        if ($host === '' || $host !== $own) {
            return new WP_Error('sgap_reddit_host', 'Only links to this site are posted.');
        }
        if (!self::can_post()) {
            return new WP_Error('sgap_reddit_user', 'Publishing is not connected: save a Reddit user or a refresh token.');
        }
        $body = self::api('POST', '/api/submit', ['body' => [
            'sr' => $sub,
            'kind' => 'link',
            'title' => $title,
            'url' => $url,
            'resubmit' => 'false',
            'api_type' => 'json'
        ]], true);
        if (is_wp_error($body)) {
            return $body;
        }
        $errors = $body['json']['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            $first = $errors[0];
            $message = is_array($first) ? (string) ($first[1] ?? $first[0] ?? 'refused') : (string) $first;
            self::fail('Reddit refused the post: ' . $message);
            return new WP_Error('sgap_reddit_refused', 'Reddit refused the post: ' . $message);
        }
        $data = $body['json']['data'] ?? [];
        $id = (string) ($data['id'] ?? '');
        $permalink = (string) ($data['url'] ?? '');
        $link = $permalink === '' ? '' : (str_starts_with($permalink, 'http') ? $permalink : 'https://www.reddit.com' . $permalink);
        self::clear_error();
        return ['id' => $id, 'url' => $link];
    }

    /** Daily cap and minimum interval, read from the rows that actually went out. */
    public static function within_limits(): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $today = current_time('Y-m-d') . ' 00:00:00';
        $sent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND sent_at >= %s", $today));
        if ($sent >= self::daily_cap()) {
            return false;
        }
        $last = (string) $wpdb->get_var("SELECT sent_at FROM {$table} WHERE status = 'sent' AND sent_at IS NOT NULL ORDER BY sent_at DESC LIMIT 1");
        if ($last !== '') {
            $stamp = strtotime($last);
            if ($stamp !== false && (time() - $stamp) < self::min_interval() * MINUTE_IN_SECONDS) {
                return false;
            }
        }
        return true;
    }

    /**
     * Queues one thread for one subreddit. Refuses a duplicate, an unpublished thread,
     * a full day and a too-recent post — each with the reason, never silently.
     *
     * @return array{id:int,subreddit:string,url:string}|WP_Error
     */
    public static function queue(int $topic_id, string $subreddit = '') {
        global $wpdb;
        $topic = get_post($topic_id);
        if (!is_object($topic) || (string) ($topic->post_type ?? '') !== SGAP_Forum::TOPIC) {
            return new WP_Error('sgap_reddit_topic', 'That thread does not exist.');
        }
        if ((string) ($topic->post_status ?? '') !== 'publish') {
            return new WP_Error('sgap_reddit_unpublished', 'Publish the thread before sharing it.');
        }
        $sub = self::clean_sub($subreddit !== '' ? $subreddit : SGAP_Forum::subreddit_for($topic_id));
        if ($sub === '') {
            return new WP_Error('sgap_reddit_sub', 'Name a subreddit, or set a default under Settings → Forum & Reddit.');
        }
        if (!self::can_post()) {
            return new WP_Error('sgap_reddit_user', 'Publishing is not connected: save a Reddit user or a refresh token.');
        }
        $table = $wpdb->prefix . self::TABLE;
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE topic_id = %d AND status IN ('queued', 'sent') LIMIT 1",
            $topic_id
        ));
        if ($existing > 0) {
            return new WP_Error('sgap_reddit_dupe', 'This thread is already queued or posted to Reddit.');
        }
        if (!self::within_limits()) {
            return new WP_Error('sgap_reddit_limit', 'The daily cap or the minimum interval is reached: ' . self::daily_cap() . ' a day, at least ' . self::min_interval() . ' minutes apart.');
        }
        $url = self::share_url($topic_id, $sub);
        $wpdb->insert($table, [
            'topic_id' => $topic_id,
            'subreddit' => $sub,
            'title' => mb_substr((string) ($topic->post_title ?? ''), 0, 300),
            'url' => $url,
            'status' => 'queued',
            'attempts' => 0,
            'scheduled_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ]);
        $id = (int) $wpdb->insert_id;
        update_post_meta($topic_id, SGAP_Forum::META_STATE, 'queued');
        update_post_meta($topic_id, SGAP_Forum::META_SUB, $sub);
        return ['id' => $id, 'subreddit' => $sub, 'url' => $url];
    }

    /** Runs on `save_post_sgap_topic`: only a published, substantial thread is worth a link. */
    public static function maybe_queue(int $post_id, $post = null, bool $update = false): void {
        if (!SGAP_Settings::enabled('reddit_enabled') || !SGAP_Settings::enabled('reddit_auto_share')) {
            return;
        }
        if (is_object($post) && (string) ($post->post_type ?? '') !== SGAP_Forum::TOPIC) {
            return;
        }
        if (get_post_status($post_id) !== 'publish') {
            return;
        }
        $state = (string) get_post_meta($post_id, SGAP_Forum::META_STATE, true);
        if ($state !== '' && $state !== 'none') {
            return;
        }
        $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
        if ($words < self::min_words()) {
            return;
        }
        self::queue($post_id);
    }

    /** Cron callback: at most one row per run, with the backoff Reddit expects. */
    public static function tick(): void {
        if (!self::can_post()) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'queued' AND (scheduled_at IS NULL OR scheduled_at <= %s) ORDER BY id ASC LIMIT %d",
            current_time('mysql'),
            self::MAX_PER_RUN
        ), ARRAY_A);
        foreach ((array) $rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $topic_id = (int) ($row['topic_id'] ?? 0);
            $result = self::submit((string) ($row['subreddit'] ?? ''), (string) ($row['title'] ?? ''), (string) ($row['url'] ?? ''));
            if (is_wp_error($result)) {
                $attempts = (int) ($row['attempts'] ?? 0) + 1;
                $wait = self::BACKOFF[min(count(self::BACKOFF) - 1, $attempts - 1)] ?? 45;
                $wpdb->update($table, [
                    'attempts' => $attempts,
                    'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued',
                    'scheduled_at' => $attempts >= self::MAX_ATTEMPTS ? ($row['scheduled_at'] ?? null) : gmdate('Y-m-d H:i:s', time() + $wait * MINUTE_IN_SECONDS),
                    'error' => mb_substr($result->get_error_message(), 0, 500)
                ], ['id' => $id]);
                update_post_meta($topic_id, SGAP_Forum::META_STATE, $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued');
                continue;
            }
            $wpdb->update($table, [
                'status' => 'sent',
                'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                'reddit_id' => (string) ($result['id'] ?? ''),
                'reddit_url' => (string) ($result['url'] ?? ''),
                'sent_at' => current_time('mysql'),
                'error' => ''
            ], ['id' => $id]);
            update_post_meta($topic_id, SGAP_Forum::META_STATE, 'sent');
            update_post_meta($topic_id, SGAP_Forum::META_REDDIT, (string) ($result['url'] ?? ''));
        }
    }

    /* ------------------------------------------------------------------ *
     * Radar — read, never write
     * ------------------------------------------------------------------ */

    /**
     * Threads in the configured subreddits that mention one of our words, ranked by
     * score plus two per comment (a question with answers is worth more than a picture).
     *
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public static function radar(bool $fresh = false) {
        $cached = get_option(self::RADAR_OPTION);
        if (!$fresh && is_array($cached) && (time() - (int) ($cached['at'] ?? 0)) < HOUR_IN_SECONDS) {
            return (array) ($cached['items'] ?? []);
        }
        $token = self::token(false);
        if (is_wp_error($token)) {
            return $token;
        }
        $seen = array_map('strval', (array) get_option(self::SEEN_OPTION, []));
        $known = array_flip($seen);
        $keywords = self::keywords();
        $items = [];
        $errors = [];
        foreach (self::radar_subs() as $sub) {
            $body = self::api('GET', '/r/' . rawurlencode($sub) . '/hot?limit=25&raw_json=1');
            if (is_wp_error($body)) {
                $errors[] = 'r/' . $sub . ': ' . $body->get_error_message();
                continue;
            }
            foreach ((array) ($body['data']['children'] ?? []) as $child) {
                $data = is_array($child) ? ($child['data'] ?? []) : [];
                if (!is_array($data) || $data === [] || !empty($data['stickied'])) {
                    continue;
                }
                $name = (string) ($data['name'] ?? $data['id'] ?? '');
                $title = trim((string) ($data['title'] ?? ''));
                if ($name === '' || $title === '' || isset($known[$name])) {
                    continue;
                }
                $haystack = strtolower($title . ' ' . (string) ($data['selftext'] ?? ''));
                $hit = false;
                foreach ($keywords as $word) {
                    if ($word !== '' && str_contains($haystack, $word)) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
                $items[] = [
                    'id' => $name,
                    'title' => mb_substr($title, 0, 200),
                    'subreddit' => (string) ($data['subreddit'] ?? $sub),
                    'url' => 'https://www.reddit.com' . (string) ($data['permalink'] ?? '/'),
                    'score' => (int) ($data['score'] ?? 0) + 2 * (int) ($data['num_comments'] ?? 0),
                    'comments' => (int) ($data['num_comments'] ?? 0)
                ];
            }
        }
        usort($items, static fn($a, $b) => $b['score'] <=> $a['score']);
        $items = array_slice($items, 0, self::RADAR_ITEMS);
        $keep = array_merge($seen, array_map(static fn($item) => (string) $item['id'], $items));
        update_option(self::SEEN_OPTION, array_slice(array_values(array_unique($keep)), -self::KEEP_SEEN), false);
        update_option(self::RADAR_OPTION, ['at' => time(), 'items' => $items, 'errors' => $errors], false);
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public static function radar_cached(): array {
        $cached = get_option(self::RADAR_OPTION);
        return is_array($cached) ? array_values((array) ($cached['items'] ?? [])) : [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 10): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, topic_id, subreddit, title, status, attempts, error, sent_at FROM ' . $wpdb->prefix . self::TABLE . ' ORDER BY id DESC LIMIT %d',
            max(1, min(50, $limit))
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function handle_radar(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to refresh the radar.');
        }
        check_admin_referer('sgap_reddit_radar');
        self::radar(true);
        wp_safe_redirect(admin_url('admin.php?page=sgap-forum&forum_notice=radar'));
    }

    /* ------------------------------------------------------------------ *
     * REST
     * ------------------------------------------------------------------ */

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/reddit/status', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_status'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
        register_rest_route(SGAP_REST::NS, '/reddit/radar', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_radar'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
        register_rest_route(SGAP_REST::NS, '/reddit/share', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_share'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    public static function rest_status(): array {
        return ['status' => self::status(), 'queue' => self::recent(10)];
    }

    public static function rest_radar(): array {
        $items = self::radar(false);
        return is_wp_error($items) ? ['error' => $items->get_error_message(), 'items' => []] : ['items' => $items];
    }

    /** @return array|WP_Error */
    public static function rest_share($request) {
        $topic_id = (int) (is_object($request) && method_exists($request, 'get_param') ? $request->get_param('topic_id') : 0);
        $subreddit = sanitize_key((string) (is_object($request) && method_exists($request, 'get_param') ? $request->get_param('subreddit') : ''));
        $result = self::queue($topic_id, $subreddit);
        return is_wp_error($result) ? $result : ['queued' => $result];
    }
}
