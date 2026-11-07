<?php
/**
 * Platform engagement ingestion — P1.5a (Bluesky + Mastodon).
 *
 * Why it exists: every other number in sgap_events is our own (we posted, someone
 * clicked, an order landed). This class is the first that reads the platforms back:
 * Bluesky's likeCount/repostCount/replyCount and Mastodon's favourites_count/
 * reblogs_count/replies_count are public reads, so there is no app review, no token
 * and no partner tier between us and the answer.
 *
 * What it deliberately does not do: reach and impressions. Neither protocol has the
 * concept, so `summary()` reports reach as null instead of inventing a follower-based
 * estimate. Pinterest and Instagram (P1.5b/P1.5c) do have those numbers and will get
 * their own modules.
 *
 * Storage: one snapshot per queue row per run, not an append. A refresh deletes the
 * three event types it owns for that queue row before writing them again, so summing
 * `value` stays correct however many times the cron runs. `queue_id` ties the numbers
 * back to the post that produced them, which is what the ROI table needs.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Engagement {
    /** Daily hook; scheduled on init when it is missing. */
    const CRON = 'sgap_engagement_refresh';

    /** How far back published rows are followed; platforms keep counts forever, we do not. */
    const WINDOW = 30;

    /** Rows per run — the public AppView accepts 25 URIs per call, and Mastodon is one call each. */
    const LIMIT = 50;

    const BSKY_HOST = 'https://public.api.bsky.app/xrpc/app.bsky.feed.getPosts';

    /**
     * Test seam: a callable that takes a URL and returns a WordPress HTTP response.
     * Null in production, where get() goes through wp_remote_get(). The smoke suite
     * cannot stub wp_remote_get (it is already defined once), so the seam lives here.
     */
    public static $transport = null;

    /** The three event types this class owns; a refresh replaces them instead of adding. */
    const TYPES = ['likes', 'reposts', 'replies'];

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'refresh']);
        // Action Scheduler when WooCommerce supplies it, WP-Cron otherwise.
        SGAP_Scheduler::recurring(self::CRON, DAY_IN_SECONDS);
    }

    /**
     * Published queue rows in the window whose external id we can query. Rows handed to
     * Publer are skipped by the channel filter: their external id is a job id, not a post.
     */
    public static function targets(int $days = self::WINDOW, int $limit = self::LIMIT): array {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $sql = $wpdb->prepare(
            "SELECT id, channel, angle, external_id FROM {$wpdb->prefix}sgap_queue
             WHERE status = 'published' AND external_id <> '' AND published_at >= %s
               AND channel IN ('bluesky','mastodon')
             ORDER BY published_at DESC LIMIT %d",
            $since,
            max(1, $limit)
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Bluesky counts for a list of AT URIs. The AppView takes repeated `uris` parameters
     * and answers only for the posts that still exist, so callers must tolerate gaps.
     *
     * @return array<string,array{likes:int,reposts:int,replies:int}>|WP_Error
     */
    public static function bluesky(array $uris) {
        $uris = array_values(array_unique(array_filter(array_map('strval', $uris), static fn($u) => str_starts_with($u, 'at://'))));
        $out = [];
        foreach (array_chunk($uris, 25) as $chunk) {
            $query = [];
            foreach ($chunk as $uri) {
                $query[] = 'uris=' . rawurlencode($uri);
            }
            $res = self::get(self::BSKY_HOST . '?' . implode('&', $query));
            if (is_wp_error($res)) {
                return $res;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code !== 200) {
                return new WP_Error('sgap_engagement_http', sprintf('Bluesky svarede HTTP %d.', $code));
            }
            $body = json_decode((string) wp_remote_retrieve_body($res), true);
            if (!is_array($body)) {
                return new WP_Error('sgap_engagement_json', 'Bluesky svarede ikke med JSON.');
            }
            foreach ((array) ($body['posts'] ?? []) as $post) {
                $uri = (string) ($post['uri'] ?? '');
                if ($uri === '') {
                    continue;
                }
                $out[$uri] = self::counts($post, 'likeCount', 'repostCount', 'replyCount');
            }
        }
        return $out;
    }

    /**
     * Mastodon counts for status ids on the configured instance. One call per status:
     * the API has no multi-status endpoint, and the list is short by design.
     *
     * @return array<string,array{likes:int,reposts:int,replies:int}>|WP_Error
     */
    public static function mastodon(array $ids) {
        $base = SGAP_Mastodon::instance();
        if ($base === '') {
            return new WP_Error('sgap_engagement_no_instance', 'Mastodon-instansen er ikke sat.');
        }
        $clean = [];
        foreach ($ids as $id) {
            $id = self::mastodon_id((string) $id);
            if ($id !== '') {
                $clean[$id] = true;
            }
        }
        $ids = array_keys($clean);
        $out = [];
        foreach ($ids as $id) {
            $res = self::get($base . '/api/v1/statuses/' . rawurlencode($id));
            if (is_wp_error($res)) {
                return $res;
            }
            $code = (int) wp_remote_retrieve_response_code($res);
            if ($code === 404) {
                continue; // Deleted on the instance; not an error for the run.
            }
            if ($code !== 200) {
                return new WP_Error('sgap_engagement_http', sprintf('Mastodon svarede HTTP %d.', $code));
            }
            $body = json_decode((string) wp_remote_retrieve_body($res), true);
            if (!is_array($body)) {
                return new WP_Error('sgap_engagement_json', 'Mastodon svarede ikke med JSON.');
            }
            $out[$id] = self::counts($body, 'favourites_count', 'reblogs_count', 'replies_count');
        }
        return $out;
    }

    /**
     * The publisher stores either `mastodon:<id>` or the post URL as the receipt, so the
     * numeric status id is taken from whichever form arrived. Anything else is not ours.
     */
    public static function mastodon_id(string $raw): string {
        $raw = trim($raw);
        if (str_starts_with($raw, 'mastodon:')) {
            $raw = substr($raw, 9);
        }
        // An AT URI ends in a record key that can be numeric; it is not a status on our instance.
        if (str_contains($raw, '://') && preg_match('#^https?://#i', $raw) !== 1) {
            return '';
        }
        if (preg_match('#(?:^|/)(\d+)/?$#', $raw, $m) === 1) {
            return $m[1];
        }
        return '';
    }

    /** One GET, through the test seam when it is set. */
    private static function get(string $url) {
        if (is_callable(self::$transport)) {
            return call_user_func(self::$transport, $url);
        }
        return wp_remote_get($url, ['timeout' => 15, 'headers' => ['Accept' => 'application/json']]);
    }

    /** Normalises one platform object into the three numbers we store. */
    private static function counts(array $row, string $likes, string $reposts, string $replies): array {
        return [
            'likes' => max(0, (int) ($row[$likes] ?? 0)),
            'reposts' => max(0, (int) ($row[$reposts] ?? 0)),
            'replies' => max(0, (int) ($row[$replies] ?? 0)),
        ];
    }

    /** Replaces the three owned event types for one queue row. */
    public static function store(int $queue_id, string $channel, string $angle, array $counts): void {
        global $wpdb;
        if ($queue_id <= 0) {
            return;
        }
        $types = self::TYPES;
        $marks = implode(',', array_fill(0, count($types), '%s'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sgap_events WHERE queue_id = %d AND event_type IN ({$marks})",
            array_merge([$queue_id], $types)
        ));
        foreach ($types as $type) {
            if (!array_key_exists($type, $counts)) {
                continue;
            }
            SGAP_ROI::record_event($queue_id, $channel, $angle, $type, (float) $counts[$type]);
        }
    }

    /**
     * One run: resolve targets, ask each platform, store what came back.
     *
     * @return array{targets:int,updated:int,skipped:int,errors:string[]}
     */
    public static function refresh(): array {
        $targets = self::targets();
        $result = ['targets' => count($targets), 'updated' => 0, 'skipped' => 0, 'errors' => []];
        if ($targets === []) {
            return $result;
        }

        $bluesky = [];
        $mastodon = [];
        foreach ($targets as $row) {
            $id = (string) $row['external_id'];
            if ($row['channel'] === 'bluesky') {
                if (str_starts_with($id, 'at://')) {
                    $bluesky[$id][] = $row;
                } else {
                    $result['skipped']++;
                }
            } elseif ($row['channel'] === 'mastodon') {
                $status = self::mastodon_id($id);
                if ($status !== '') {
                    $mastodon[$status][] = $row;
                } else {
                    $result['skipped']++;
                }
            }
        }

        foreach ([['bluesky', $bluesky], ['mastodon', $mastodon]] as [$kind, $groups]) {
            if ($groups === []) {
                continue;
            }
            $counts = $kind === 'bluesky' ? self::bluesky(array_keys($groups)) : self::mastodon(array_keys($groups));
            if (is_wp_error($counts)) {
                $result['errors'][] = $counts->get_error_message();
                continue;
            }
            foreach ($groups as $id => $rows) {
                if (!isset($counts[$id])) {
                    $result['skipped'] += count($rows);
                    continue;
                }
                foreach ($rows as $row) {
                    self::store((int) $row['id'], (string) $row['channel'], (string) $row['angle'], $counts[$id]);
                    $result['updated']++;
                }
            }
        }
        return $result;
    }

    /**
     * Totals per channel for the window. `reach` is null on purpose: neither network
     * exposes it, and a follower-count guess would poison every comparison downstream.
     */
    public static function summary(int $days = self::WINDOW): array {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, event_type, SUM(value) AS total, COUNT(*) AS rows_count
             FROM {$wpdb->prefix}sgap_events
             WHERE created_at >= %s AND event_type IN ('likes','reposts','replies')
             GROUP BY channel, event_type",
            $since
        ), ARRAY_A);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $channel = (string) $row['channel'];
            $out[$channel] ??= ['likes' => 0.0, 'reposts' => 0.0, 'replies' => 0.0, 'reach' => null, 'rows' => 0];
            $out[$channel][(string) $row['event_type']] = (float) $row['total'];
            $out[$channel]['rows'] += (int) $row['rows_count'];
        }
        return $out;
    }

    /** Read-only dashboard summary of the snapshots collected in the last 30 days. */
    public static function dashboard_card(): void {
        $summary = self::summary();
        SGAP_UI::card_open('Engagement · last 30 days', 'chart-bar');
        if ($summary === []) {
            echo '<p class="description">No engagement snapshots yet. Bluesky and Mastodon counts appear after the daily refresh.</p>';
            SGAP_UI::card_close();
            return;
        }

        // The three numbers people actually look for, before the per-channel detail.
        $totals = ['likes' => 0, 'reposts' => 0, 'replies' => 0];
        foreach ($summary as $counts) {
            foreach ($totals as $type => $sum) {
                $totals[$type] += (int) ($counts[$type] ?? 0);
            }
        }
        echo '<div class="sgap-metrics">'
            . SGAP_UI::metric('Likes', $totals['likes'], count($summary) . (count($summary) === 1 ? ' channel' : ' channels'))
            . SGAP_UI::metric('Reposts', $totals['reposts'])
            . SGAP_UI::metric('Replies', $totals['replies'])
            . '</div>';

        echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr>'
            . '<th>Channel</th><th>Likes</th><th>Reposts</th><th>Replies</th>'
            . '</tr></thead><tbody>';
        foreach ($summary as $channel => $counts) {
            echo '<tr><td>' . SGAP_UI::channel((string) $channel) . '</td>'
                . '<td>' . esc_html((string) (int) ($counts['likes'] ?? 0)) . '</td>'
                . '<td>' . esc_html((string) (int) ($counts['reposts'] ?? 0)) . '</td>'
                . '<td>' . esc_html((string) (int) ($counts['replies'] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        SGAP_UI::caveat('engagement');
        SGAP_UI::card_close();
    }

    /** REST: run once and return what changed. */
    public static function rest_refresh(): WP_REST_Response {
        return new WP_REST_Response(self::refresh() + ['summary' => self::summary()], 200);
    }
}
