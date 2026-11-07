<?php
/**
 * Facebook Page control — P2.6.
 *
 * Why it exists: Facebook is the channel where the shop reaches people who never search
 * for it, and until now everything about it lived inside Publer: we could hand a post over
 * but not read the page, not see what a post did, and not answer anybody who commented.
 * This module puts the page itself in the plugin:
 *
 *   - **Publish** a queue row straight to the page (`/{page-id}/feed` with a link, or
 *     `/{page-id}/photos` when the row has an image), instead of through Publer.
 *   - **Measure** each published post (`post_impressions`, `post_impressions_unique`,
 *     `post_reactions_by_type_total`, `post_clicks`) and the page itself (`fan_count`,
 *     `followers_count`), stored as Facebook's own event types.
 *   - **Moderate**: read the comments on our posts, answer them, and hide/unhide one.
 *     Hiding is reversible and is what the platform offers; nothing here deletes.
 *
 * Facts that shape the design (Graph API v21.0, read 7/11 2026):
 *   - A **Page access token** is what writes to the page; the user token that mints it is
 *     not enough. The token is stored as a secret and never echoed back.
 *   - Insight names carry a `post_` prefix (`post_impressions`), and
 *     `post_reactions_by_type_total` answers with an **object** of reaction → count, not a
 *     number, so it has to be summed before it can be stored.
 *   - **A metric that is not reported yet is left out of the answer instead of being 0.**
 *     Fresh posts report nothing for a while; writing 0 would be a claim Meta has not made,
 *     and every average downstream would inherit it.
 *   - **`shares` and `likes` are event types other channels also use**, so every delete and
 *     every read here is scoped to `channel = 'facebook'` — exactly the bug that was fixed
 *     in the Pinterest and Instagram modules.
 *   - The Graph host is fixed (`graph.facebook.com`); unlike Instagram there is only one,
 *     so it is not a setting.
 *   - A post id belongs to the page: the mapping from our queue row to Meta's post id is
 *     what makes the numbers attachable at all, so it is written at publish time and kept
 *     for `KEEP_POSTS` entries.
 *
 * Publishing through the page is **opt-in** (`facebook_direct`): a site that connected the
 * page only to read it must not have its publishing moved out of Publer by a key.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Facebook {
    /** Daily insights hook; scheduled through SGAP_Scheduler on init. */
    const CRON = 'sgap_facebook_refresh';

    const HOST = 'graph.facebook.com';

    /** Event types this class owns; a run replaces them for a queue row on this channel. */
    const TYPES = ['impressions', 'reach', 'reactions', 'post_clicks'];

    /** What the per-post insights endpoint is asked for, and how the answer is read. */
    const METRICS = ['post_impressions', 'post_impressions_unique', 'post_reactions_by_type_total', 'post_clicks'];
    const METRIC_MAP = [
        'post_impressions' => 'impressions',
        'post_impressions_unique' => 'reach',
        'post_reactions_by_type_total' => 'reactions',
        'post_clicks' => 'post_clicks',
    ];

    /** The two metrics every post reports; used when the full set is refused. */
    const NARROW = ['post_impressions', 'post_impressions_unique'];

    /** How far back a post is still worth measuring. */
    const WINDOW = 90;

    /** Mapping queue row → Meta post id, and the page snapshot. */
    const POSTS = 'sgap_facebook_posts';
    const PAGEINFO = 'sgap_facebook_page';
    const STATE = 'sgap_facebook_state';
    const EXPIRES = 'sgap_facebook_access_expires';

    const KEEP_POSTS = 400;
    /** Graph calls per run; the app is rate limited per hour, so a backlog drains over days. */
    const MAX_CALLS = 20;
    const COMMENT_LIMIT = 25;

    /**
     * Test seam: function(string $method, string $url, array $args) returning a WordPress
     * HTTP response. Null in production, so no request leaves a test machine.
     */
    public static $transport = null;

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run']);
        add_action('admin_post_sgap_facebook_reply', [__CLASS__, 'handle_reply']);
        add_action('admin_post_sgap_facebook_hide', [__CLASS__, 'handle_hide']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        SGAP_Scheduler::recurring(self::CRON, DAY_IN_SECONDS);
    }

    /* ------------------------------------------------------------------ *
     * Credentials
     * ------------------------------------------------------------------ */

    public static function page_id(): string {
        return trim((string) SGAP_Settings::get('facebook_page_id', ''));
    }

    /** The Page access token; a user token cannot post as the page. */
    public static function saved_token(): string {
        return trim((string) SGAP_Settings::get('facebook_access_token', ''));
    }

    /** A token pasted into wp-config.php cannot be rotated by the plugin, so it is refused. */
    public static function constant_locked(): string {
        return defined('SGAP_FACEBOOK_ACCESS_TOKEN') && SGAP_FACEBOOK_ACCESS_TOKEN ? 'SGAP_FACEBOOK_ACCESS_TOKEN' : '';
    }

    public static function token(): string {
        return self::constant_locked() !== '' ? '' : self::saved_token();
    }

    public static function version(): string {
        $version = trim((string) SGAP_Settings::get('facebook_graph_version', 'v21.0'));
        return preg_match('/^v\d{1,2}\.\d$/', $version) ? $version : 'v21.0';
    }

    public static function configured(): bool {
        return self::page_id() !== '' && self::saved_token() !== '';
    }

    /** True when this module, not Publer, should post a row on this channel. */
    public static function handles(string $channel): bool {
        return $channel === 'facebook' && self::configured() && SGAP_Settings::get('facebook_direct', 'no') === 'yes';
    }

    /* ------------------------------------------------------------------ *
     * Publishing
     * ------------------------------------------------------------------ */

    /**
     * Hands one queue row to the page. Returns the post id, or a WP_Error whose message the
     * queue shows to the owner.
     *
     * @return string|WP_Error
     */
    public static function publish(array $row) {
        if (!self::configured()) {
            return new WP_Error('sgap_facebook_no_token', self::token_problem());
        }
        $preflight = SGAP_Preflight::check('facebook', $row);
        if (!$preflight['ok']) {
            return new WP_Error('sgap_preflight', SGAP_Preflight::message($preflight['problems']), ['status' => 422, 'problems' => $preflight['problems']]);
        }
        $row = $preflight['row'];
        $message = trim((string) ($row['body'] ?? ''));
        $queue = (int) ($row['id'] ?? 0);
        $url = $queue > 0 ? SGAP_Tracking::url($queue) : '';
        // A photo post gets the link in the caption; a text post gets a real link preview,
        // which is what makes the post clickable on Facebook instead of a bare URL.
        if (!empty($row['image_url'])) {
            $body = $message;
            if ($url !== '') {
                $body .= "\n\n" . $url;
            }
            $result = self::request('POST', '/' . rawurlencode(self::page_id()) . '/photos', [
                'url' => (string) $row['image_url'],
                'caption' => $body
            ]);
        } else {
            $args = ['message' => $message];
            if ($url !== '') {
                $args['link'] = $url;
            }
            $result = self::request('POST', '/' . rawurlencode(self::page_id()) . '/feed', $args);
        }
        if (is_wp_error($result)) {
            return $result;
        }
        $post = (string) ($result['post_id'] ?? $result['id'] ?? '');
        if ($post === '') {
            return new WP_Error('sgap_facebook', 'Facebook returned no post id.');
        }
        if ($queue > 0) {
            self::remember($queue, $post);
        }
        return $post;
    }

    /** queue id → Meta post id, with the last time its numbers were read. */
    public static function mapping(): array {
        $map = get_option(self::POSTS, []);
        return is_array($map) ? $map : [];
    }

    /** Writes one mapping and trims the oldest so the option cannot grow without end. */
    public static function remember(int $queue, string $post): void {
        $map = self::mapping();
        $map[(string) $post] = ['queue' => $queue, 'synced_at' => 0, 'at' => current_time('mysql')];
        update_option(self::POSTS, self::trim($map), false);
    }

    /** Newest KEEP_POSTS entries by their publish time. */
    private static function trim(array $map): array {
        if (count($map) <= self::KEEP_POSTS) {
            return $map;
        }
        uasort($map, static fn($a, $b) => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));
        return array_slice($map, -self::KEEP_POSTS, null, true);
    }

    /* ------------------------------------------------------------------ *
     * Insights
     * ------------------------------------------------------------------ */

    /** The page itself: name, fans, followers. Cached in an option, read on the card. */
    public static function page(): array {
        $info = get_option(self::PAGEINFO, []);
        return is_array($info) ? $info : [];
    }

    /** Refresh the page snapshot. A refusal is reported, not stored as an empty page. */
    public static function page_info(): array {
        $result = self::request('GET', '/' . rawurlencode(self::page_id()), [
            'fields' => 'name,link,fan_count,followers_count'
        ]);
        if (is_wp_error($result)) {
            // Nothing is stored: an empty page snapshot would look like a page with no fans.
            if (class_exists('SGAP_Diagnostics')) {
                SGAP_Diagnostics::log('facebook', $result);
            }
            return [];
        }
        $info = [
            'name' => (string) ($result['name'] ?? ''),
            'link' => (string) ($result['link'] ?? ''),
            'fans' => isset($result['fan_count']) ? (int) $result['fan_count'] : null,
            'followers' => isset($result['followers_count']) ? (int) $result['followers_count'] : null,
            'at' => current_time('mysql')
        ];
        update_option(self::PAGEINFO, $info, false);
        return $info;
    }

    /**
     * One post's numbers. Returns only the metrics Meta actually reported: a metric that is
     * missing is left out, never written as 0.
     *
     * @return array<string,int>|WP_Error
     */
    public static function analytics(string $post_id) {
        $body = self::request('GET', '/' . rawurlencode($post_id) . '/insights', ['metric' => implode(',', self::METRICS)]);
        if (is_wp_error($body)) {
            // A media type that has no `post_clicks` refuses the whole call; ask for the two
            // every post reports instead of dropping the post.
            $body = self::request('GET', '/' . rawurlencode($post_id) . '/insights', ['metric' => implode(',', self::NARROW)]);
            if (is_wp_error($body)) {
                return $body;
            }
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
            if (is_array($value)) {
                // post_reactions_by_type_total answers {LIKE: 3, LOVE: 1, …}.
                $sum = 0;
                foreach ($value as $one) {
                    $sum += (int) $one;
                }
                $value = $sum;
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
        // Scoped to the channel on purpose: `likes` and `shares` are also Bluesky, Mastodon
        // and Instagram snapshot types, so deleting by queue id alone would wipe theirs.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sgap_events WHERE queue_id = %d AND channel = 'facebook' AND event_type IN ({$marks})",
            array_merge([$queue_id], $types)
        ));
        foreach ($types as $type) {
            if (!array_key_exists($type, $counts)) {
                continue;
            }
            SGAP_ROI::record_event($queue_id, 'facebook', $angle, $type, (float) $counts[$type]);
        }
    }

    /**
     * One run: refresh the page snapshot, then read the numbers for the mapped posts that
     * have waited longest.
     *
     * @return array<string,mixed>
     */
    public static function sync(int $limit = self::MAX_CALLS): array {
        $report = [
            'configured' => self::configured(),
            'posts' => 0,
            'matched' => 0,
            'updated' => 0,
            'waiting' => 0,
            'orphaned' => 0,
            'errors' => [],
            'window' => self::WINDOW,
            'at' => current_time('mysql')
        ];
        if (!self::configured()) {
            $report['error'] = 'Facebook is not connected: save the page id and a Page access token under Settings → Keys.';
            self::record(['ok' => false, 'error' => $report['error'], 'report' => $report]);
            return $report;
        }

        $page = self::page_info();
        if ($page === []) {
            $report['errors'][] = 'The page snapshot could not be read.';
        }

        $map = self::mapping();
        $report['posts'] = count($map);
        $candidates = [];
        foreach ($map as $post_id => $meta) {
            $queue = (int) ($meta['queue'] ?? 0);
            if ($queue <= 0) {
                continue;
            }
            $row = self::queue_row($queue);
            if ($row === null) {
                // The queue row was deleted or never published on this channel: the post id
                // has no owner any more, and guessing one would attach numbers to nothing.
                $report['orphaned']++;
                continue;
            }
            $candidates[] = ['post' => (string) $post_id, 'queue' => $queue, 'row' => $row, 'synced' => (int) ($meta['synced_at'] ?? 0)];
        }
        $report['matched'] = count($candidates);
        usort($candidates, static fn($a, $b) => $a['synced'] <=> $b['synced']);

        foreach (array_slice($candidates, 0, max(1, $limit)) as $candidate) {
            $counts = self::analytics($candidate['post']);
            if (is_wp_error($counts)) {
                $report['errors'][] = 'Post ' . $candidate['post'] . ': ' . $counts->get_error_message();
                continue;
            }
            if ($counts === []) {
                // Meta has not reported anything for this post yet. It is not a post with
                // zero impressions, so nothing is stored and it is asked again tomorrow.
                $report['waiting']++;
                continue;
            }
            self::store((int) $candidate['row']['id'], (string) ($candidate['row']['angle'] ?? 'angle_a'), $counts);
            $map[$candidate['post']]['synced_at'] = time();
            $report['updated']++;
        }

        update_option(self::POSTS, self::trim($map), false);
        $ok = $report['errors'] === [];
        self::record(['ok' => $ok, 'error' => $ok ? '' : (string) $report['errors'][0], 'report' => $report]);
        return $report;
    }

    /** The published row behind a mapped post, or null when there is none any more. */
    private static function queue_row(int $queue): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, channel, angle, body, status FROM {$wpdb->prefix}sgap_queue WHERE id = %d AND channel = 'facebook'",
            $queue
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** Cron callback: silent while nothing is configured, so an unconnected site stays quiet. */
    public static function run(): void {
        if (!self::configured()) {
            return;
        }
        self::sync();
    }

    /* ------------------------------------------------------------------ *
     * Moderation: read, answer, hide
     * ------------------------------------------------------------------ */

    /**
     * The comments on one of our posts, newest first. `can_hide` comes from Meta, so the
     * button is only offered where the API says it would work.
     *
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public static function comments(string $post_id) {
        $body = self::request('GET', '/' . rawurlencode($post_id) . '/comments', [
            'fields' => 'id,message,from{name},created_time,is_hidden,can_hide,like_count',
            'limit' => self::COMMENT_LIMIT,
            'order' => 'reverse_chronological'
        ]);
        if (is_wp_error($body)) {
            return $body;
        }
        $out = [];
        foreach ((array) ($body['data'] ?? []) as $comment) {
            $out[] = [
                'id' => (string) ($comment['id'] ?? ''),
                'message' => (string) ($comment['message'] ?? ''),
                'from' => (string) ($comment['from']['name'] ?? ''),
                'created' => (string) ($comment['created_time'] ?? ''),
                'hidden' => !empty($comment['is_hidden']),
                'can_hide' => !array_key_exists('can_hide', $comment) ? true : (bool) $comment['can_hide'],
                'likes' => (int) ($comment['like_count'] ?? 0)
            ];
        }
        return $out;
    }

    /** Answers a comment as the page. @return string|WP_Error The new comment id. */
    public static function reply(string $comment_id, string $message) {
        $message = trim($message);
        if ($comment_id === '' || $message === '') {
            return new WP_Error('sgap_facebook_comment', 'A comment id and a reply are both needed.');
        }
        $body = self::request('POST', '/' . rawurlencode($comment_id) . '/comments', ['message' => $message]);
        if (is_wp_error($body)) {
            return $body;
        }
        return (string) ($body['id'] ?? '');
    }

    /**
     * Hides or unhides a comment. Hiding is reversible and is the only moderation this
     * module does: deleting somebody else's words is not something a cron job should own.
     *
     * @return bool|WP_Error
     */
    public static function hide(string $comment_id, bool $hide = true) {
        if ($comment_id === '') {
            return new WP_Error('sgap_facebook_comment', 'A comment id is needed.');
        }
        $body = self::request('POST', '/' . rawurlencode($comment_id), ['is_hidden' => $hide ? 'true' : 'false']);
        if (is_wp_error($body)) {
            return $body;
        }
        return (bool) ($body['success'] ?? false);
    }

    /* ------------------------------------------------------------------ *
     * Reading what was collected
     * ------------------------------------------------------------------ */

    /** Totals for the channel in the window, plus how many snapshots they rest on. */
    public static function summary(int $days = self::WINDOW): array {
        global $wpdb;
        $since = wp_date('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $marks = implode(',', array_fill(0, count(self::TYPES), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, event_type, SUM(value) AS total, COUNT(*) AS rows_count
             FROM {$wpdb->prefix}sgap_events
             WHERE created_at >= %s AND channel = 'facebook' AND event_type IN ({$marks})
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

    public static function state(): array {
        $state = get_option(self::STATE, []);
        $state = is_array($state) ? $state : [];
        return array_merge(['at' => '', 'ok' => false, 'error' => '', 'report' => []], $state);
    }

    private static function record(array $data): array {
        $state = array_merge(self::state(), ['ok' => false, 'error' => ''], $data, ['at' => current_time('mysql')]);
        update_option(self::STATE, $state, false);
        if (empty($data['ok']) && (string) ($data['error'] ?? '') !== '' && class_exists('SGAP_Diagnostics')) {
            SGAP_Diagnostics::log('facebook', (string) $data['error'], ['page' => self::page_id()]);
        }
        return $state;
    }

    /** Everything an operator or an MCP client needs to judge the connection. */
    public static function status(): array {
        $state = self::state();
        $expires = (int) get_option(self::EXPIRES, 0);
        $missing = [];
        if (self::page_id() === '') {
            $missing[] = 'the page id';
        }
        if (self::saved_token() === '') {
            $missing[] = 'a Page access token';
        }
        if (self::constant_locked() !== '') {
            $problem = 'The Facebook token is set in wp-config.php (' . self::constant_locked() . ') and cannot be rotated there — save it under Settings → Keys instead.';
        } elseif ($missing) {
            $problem = 'Missing ' . implode(' and ', $missing) . ', so the page cannot be read or posted to.';
        } elseif ($expires > 0 && $expires < time()) {
            $problem = 'The Facebook token expired on ' . wp_date('Y-m-d', $expires) . '; mint a new Page access token.';
        } elseif (!$state['ok'] && $state['error'] !== '') {
            $problem = 'The last Facebook run failed: ' . $state['error'];
        } else {
            $problem = '';
        }
        return [
            'configured' => self::configured(),
            'page_id' => self::page_id(),
            'direct' => SGAP_Settings::get('facebook_direct', 'no') === 'yes',
            'version' => self::version(),
            'token_saved' => self::saved_token() !== '',
            'token_from_constant' => self::constant_locked() !== '' ? self::constant_locked() : null,
            'posts_mapped' => count(self::mapping()),
            'page' => self::page(),
            'last_run' => $state['at'],
            'last_ok' => (bool) $state['ok'],
            'error' => (string) $state['error'],
            'report' => (array) $state['report'],
            'next_run' => ($next = SGAP_Scheduler::next(self::CRON)) ? wp_date('Y-m-d H:i:s', (int) $next) : null,
            'problem' => $problem
        ];
    }

    /** A line for the daily self-check, or '' when nothing is connected. */
    public static function problem(): string {
        if (!self::configured()) {
            return '';
        }
        $status = self::status();
        return $status['problem'] === '' ? '' : 'Facebook page: ' . $status['problem'];
    }

    /* ------------------------------------------------------------------ *
     * Admin: the page screen
     * ------------------------------------------------------------------ */

    public static function menu(): void {
        add_submenu_page(
            'steamgears-autopilot',
            'Facebook',
            'Facebook',
            'manage_options',
            'sgap-facebook',
            [__CLASS__, 'page_screen']
        );
    }

    /** Read-only dashboard card. */
    public static function dashboard_card(): void {
        SGAP_UI::card_open('Facebook · posts from the last 90 days', 'facebook-alt');
        $status = self::status();
        if (!self::configured()) {
            echo '<p class="description">The page is not connected. Save the page id and a Page access token under '
                . '<a href="' . esc_url(admin_url('admin.php?page=sgap-settings#keys')) . '">Settings → Keys</a>. Until then Facebook posts go through Publer and nothing is measured.</p>';
            SGAP_UI::card_close();
            return;
        }
        $page = $status['page'];
        if ($page) {
            echo '<p>' . SGAP_UI::pill((string) ($page['name'] !== '' ? $page['name'] : 'Page ' . $status['page_id']), 'ok')
                . (isset($page['fans']) ? ' ' . SGAP_UI::pill(number_format_i18n((int) $page['fans']) . ' fans') : '')
                . (isset($page['followers']) ? ' ' . SGAP_UI::pill(number_format_i18n((int) $page['followers']) . ' followers') : '')
                . ($status['direct'] ? ' ' . SGAP_UI::pill('Publishing directly', 'brass') : '') . '</p>';
        }
        $summary = self::summary();
        $facebook = $summary['facebook'] ?? null;
        if ($facebook === null) {
            echo '<p class="description">No Facebook snapshots yet. Impressions, reach, reactions and clicks appear after a run has read a post we published; Meta can take a while to report them.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
                . '<th>Channel</th><th>Impressions</th><th>Reach</th><th>Reactions</th><th>Post clicks</th><th>Posts</th>'
                . '</tr></thead><tbody><tr><td>' . SGAP_UI::channel('facebook') . '</td>';
            foreach (self::TYPES as $type) {
                echo '<td>' . esc_html((string) (int) ($facebook[$type] ?? 0)) . '</td>';
            }
            echo '<td>' . esc_html((string) (int) ($facebook['rows'] ?? 0)) . '</td></tr></tbody></table></div>';
        }
        $report = $status['report'];
        $parts = [];
        if ($report) {
            $parts[] = sprintf('%d post(s) mapped to a queue row', (int) ($report['matched'] ?? 0));
            if (!empty($report['waiting'])) {
                $parts[] = (int) $report['waiting'] . ' post(s) waiting for Meta to report';
            }
            if (!empty($report['orphaned'])) {
                $parts[] = (int) $report['orphaned'] . ' post(s) belong to a queue row that is gone';
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
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-facebook')) . '">Open the page screen</a></p>';
        SGAP_UI::caveat('facebook');
        SGAP_UI::card_close();
    }

    /** The screen where the page is actually run: posts, comments, reply and hide. */
    public static function page_screen(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to see this page.');
        }
        SGAP_Admin::open('Facebook', 'What the page published, what it did, and who is talking to you.');
        $status = self::status();
        $page_name = (string) ($status['page']['name'] ?? '');
        echo '<div class="sgap-pills">'
            . SGAP_UI::pill($status['configured'] ? 'Connected' : 'Not connected', $status['configured'] ? 'ok' : 'warn')
            . ($page_name !== '' ? SGAP_UI::pill($page_name, 'brass') : '')
            . SGAP_UI::pill($status['direct'] ? 'Publishing directly' : 'Publishing via Publer')
            . SGAP_UI::pill('Graph ' . $status['version'])
            . '</div>';
        $result = get_transient('sgap_run_result_' . get_current_user_id());
        if (is_string($result) && $result !== '') {
            delete_transient('sgap_run_result_' . get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result) . '</p></div>';
        }
        if (!$status['configured']) {
            echo '<p class="description">Save the page id and a Page access token under '
                . '<a href="' . esc_url(admin_url('admin.php?page=sgap-settings#keys')) . '">Settings → Keys</a>, then come back. '
                . esc_html(self::token_problem()) . '</p>';
            SGAP_Admin::close();
            return;
        }

        if ($status['problem'] !== '') {
            echo '<div class="notice notice-warning"><p>' . esc_html($status['problem']) . '</p></div>';
        }

        // Newest posts first; the comments under each one are read live, because a comment
        // from ten minutes ago is the whole reason to open this screen.
        $map = self::mapping();
        uasort($map, static fn($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        $map = array_slice($map, 0, 5, true);
        if (!$map) {
            echo '<p class="description">No post has been published from here yet. The screen fills up when a queue post goes out through the page' . ($status['direct'] ? '.' : ' — publishing is set to Publer, so nothing is mapped.') . '</p>';
        }
        foreach ($map as $post_id => $meta) {
            $row = self::queue_row((int) ($meta['queue'] ?? 0));
            SGAP_UI::card_open(($row['body'] ?? '') !== '' ? wp_trim_words((string) $row['body'], 12) : 'Post ' . $post_id, 'facebook-alt');
            echo '<p class="description">' . esc_html('Queue #' . (int) ($meta['queue'] ?? 0) . ' · published ' . (string) ($meta['at'] ?? '') . ' · post ' . $post_id) . '</p>';
            $comments = self::comments((string) $post_id);
            if (is_wp_error($comments)) {
                echo '<p class="description" style="color:#b32d2e">' . esc_html($comments->get_error_message()) . '</p>';
                SGAP_UI::card_close();
                continue;
            }
            if (!$comments) {
                echo '<p class="description">No comments yet.</p>';
            } else {
                echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr><th>From</th><th>Comment</th><th>When</th><th>Answer</th></tr></thead><tbody>';
                foreach ($comments as $comment) {
                    echo '<tr><td>' . esc_html($comment['from']) . '</td>'
                        . '<td>' . esc_html($comment['message']) . ($comment['hidden'] ? ' ' . SGAP_UI::pill('hidden', 'warn') : '') . '</td>'
                        . '<td>' . esc_html($comment['created']) . '</td><td>'
                        . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px">';
                    wp_nonce_field('sgap_facebook_moderate');
                    echo '<input type="hidden" name="action" value="sgap_facebook_reply">'
                        . '<input type="hidden" name="comment_id" value="' . esc_attr($comment['id']) . '">'
                        . '<input type="text" name="message" class="regular-text" placeholder="Reply as the page" required>'
                        . '<button class="button" type="submit">Reply</button></form>';
                    if ($comment['can_hide']) {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:4px">';
                        wp_nonce_field('sgap_facebook_moderate');
                        echo '<input type="hidden" name="action" value="sgap_facebook_hide">'
                            . '<input type="hidden" name="comment_id" value="' . esc_attr($comment['id']) . '">'
                            . '<input type="hidden" name="hide" value="' . ($comment['hidden'] ? '0' : '1') . '">'
                            . '<button class="button button-small" type="submit">' . ($comment['hidden'] ? 'Unhide' : 'Hide') . '</button></form>';
                    }
                    echo '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            SGAP_UI::card_close();
        }
        SGAP_UI::caveat('facebook');
        SGAP_Admin::close();
    }

    private static function back(string $message): void {
        set_transient('sgap_run_result_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=sgap-facebook'));
        exit;
    }

    public static function handle_reply(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to answer comments.');
        }
        check_admin_referer('sgap_facebook_moderate');
        $comment = sanitize_text_field((string) ($_POST['comment_id'] ?? ''));
        $message = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
        $result = self::reply($comment, $message);
        self::back(is_wp_error($result) ? 'The reply failed: ' . $result->get_error_message() : 'Answered comment ' . $comment . '.');
    }

    public static function handle_hide(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to moderate comments.');
        }
        check_admin_referer('sgap_facebook_moderate');
        $comment = sanitize_text_field((string) ($_POST['comment_id'] ?? ''));
        $hide = (string) ($_POST['hide'] ?? '1') === '1';
        $result = self::hide($comment, $hide);
        self::back(is_wp_error($result)
            ? 'The change failed: ' . $result->get_error_message()
            : ($hide ? 'Comment hidden.' : 'Comment visible again.'));
    }

    /* ------------------------------------------------------------------ *
     * REST
     * ------------------------------------------------------------------ */

    public static function routes(): void {
        $auth = static fn() => current_user_can('manage_options');
        register_rest_route(SGAP_Admin::NS, '/facebook/refresh', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'rest_refresh'], 'permission_callback' => $auth
        ]);
        register_rest_route(SGAP_Admin::NS, '/facebook/reply', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'rest_reply'], 'permission_callback' => $auth
        ]);
        register_rest_route(SGAP_Admin::NS, '/facebook/hide', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'rest_hide'], 'permission_callback' => $auth
        ]);
    }

    /** REST: run once and return what changed, with the status attached. */
    public static function rest_refresh(): WP_REST_Response {
        $report = self::sync();
        return new WP_REST_Response($report + ['summary' => self::summary(), 'status' => self::status()], 200);
    }

    public static function rest_reply(WP_REST_Request $request): WP_REST_Response {
        $result = self::reply(
            sanitize_text_field((string) $request->get_param('comment_id')),
            sanitize_textarea_field((string) $request->get_param('message'))
        );
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 422);
        }
        return new WP_REST_Response(['comment_id' => $result], 200);
    }

    public static function rest_hide(WP_REST_Request $request): WP_REST_Response {
        $hide = $request->get_param('hide');
        $result = self::hide(
            sanitize_text_field((string) $request->get_param('comment_id')),
            $hide === null ? true : (bool) $hide
        );
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 422);
        }
        return new WP_REST_Response(['hidden' => (bool) $result], 200);
    }

    /* ------------------------------------------------------------------ *
     * Transport
     * ------------------------------------------------------------------ */

    /** Why there is no usable token, in words the owner can act on. */
    public static function token_problem(): string {
        if (self::constant_locked() !== '') {
            return 'The Facebook token is set in wp-config.php (' . self::constant_locked() . ') and cannot be rotated there; save it under Settings → Keys.';
        }
        if (self::page_id() === '') {
            return 'No page id: paste the numeric id of the Facebook Page under Settings → Keys.';
        }
        if (self::saved_token() === '') {
            return 'No Page access token: mint one in the Meta app dashboard (Facebook Login for Business → Page) and paste it under Settings → Keys. A user token cannot post as the page.';
        }
        return 'Facebook refused the request; see the card on the dashboard for the reason.';
    }

    /** One authorised call, with the error mapping every caller expects. */
    private static function request(string $method, string $path, array $args = []) {
        $token = self::token();
        if ($token === '') {
            return new WP_Error('sgap_facebook_no_token', self::token_problem());
        }
        $url = 'https://' . self::HOST . '/' . self::version() . $path;
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
        ];
        if ($method === 'GET') {
            if ($args) {
                $url = add_query_arg(array_map('strval', $args), $url);
            }
            $response = self::send('GET', $url, ['timeout' => 20, 'headers' => $headers]);
        } else {
            $response = self::send('POST', $url, ['timeout' => 20, 'headers' => $headers, 'body' => $args]);
        }
        if (is_wp_error($response)) {
            return $response;
        }
        return self::decode($response, $path);
    }

    private static function send(string $method, string $url, array $args) {
        if (is_callable(self::$transport)) {
            return call_user_func(self::$transport, $method, $url, $args);
        }
        return $method === 'GET' ? wp_remote_get($url, $args) : wp_remote_post($url, $args);
    }

    /** HTTP status and JSON body, with Meta's own message on anything but 200. */
    private static function decode($response, string $path) {
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);
        if ($code === 200 && is_array($body)) {
            return $body;
        }
        $body = is_array($body) ? $body : [];
        $error = (array) ($body['error'] ?? []);
        $detail = trim((string) ($error['message'] ?? ''));
        $graph = (int) ($error['code'] ?? 0);
        $suffix = $detail !== '' ? ': ' . $detail : '';
        if ($code === 401 || $graph === 190) {
            return new WP_Error('sgap_facebook_401', 'Facebook refused the access token (HTTP ' . $code . ')' . $suffix . '. A Page access token is what writes to the page; mint a new one under Settings → Keys.', ['status' => $code]);
        }
        if ($graph === 4 || $graph === 17 || $graph === 32 || $graph === 613 || $code === 429) {
            return new WP_Error('sgap_facebook_429', 'Facebook is rate limiting the app (HTTP ' . $code . ')' . $suffix . '. The next run picks up where this one stopped.', ['status' => $code]);
        }
        if ($code === 403 || $graph === 10 || $graph === 200 || $graph === 272) {
            return new WP_Error('sgap_facebook_403', 'Facebook refused the request (HTTP ' . $code . ')' . $suffix . '. The token needs pages_show_list, pages_read_engagement and pages_manage_posts, and pages_manage_engagement for answers and hiding, with the page granted to the app.', ['status' => $code]);
        }
        if ($code === 400 || $graph === 100) {
            return new WP_Error('sgap_facebook_400', 'Facebook answered HTTP ' . $code . ' for ' . $path . $suffix . '.', ['status' => $code]);
        }
        return new WP_Error('sgap_facebook_http', 'Facebook answered HTTP ' . $code . ' for ' . $path . $suffix . '.', ['status' => $code]);
    }
}
