<?php
/**
 * P1.5 — one screen that says, per channel, what the plugin can and cannot do right now.
 *
 * The analytics ingestion itself is built (P1.5a/b/c). What was missing is the step before
 * it can be verified: the owner could not see *why* a channel stayed silent. This class
 * separates the three cases that look identical from the outside — no token at all, a token
 * that is stored but not refreshable, and a channel that is ready to be measured — so the
 * next action is a sentence rather than a guess.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Channel_Health {
    /** Channels whose analytics end up in sgap_events, and the class that owns each one. */
    const CHANNELS = [
        'pinterest' => ['label' => 'Pinterest', 'class' => 'SGAP_Pinterest'],
        'instagram' => ['label' => 'Instagram', 'class' => 'SGAP_Instagram'],
        'bluesky'   => ['label' => 'Bluesky',   'class' => 'SGAP_Bluesky'],
        'mastodon'  => ['label' => 'Mastodon',  'class' => 'SGAP_Mastodon'],
        'telegram'  => ['label' => 'Telegram',  'class' => 'SGAP_Telegram'],
        'facebook'  => ['label' => 'Facebook',  'class' => 'SGAP_Facebook'],
    ];

    const NEXT_STEP = [
        'ready'         => 'Ready — run the refresh and the events land in sgap_events.',
        'needs_refresh' => 'No access token, but a refresh token is stored: the next refresh will fetch a new one.',
        'missing_token' => 'Nothing stored — paste the token on the Keys screen, then run the refresh.',
        'refused'       => 'The stored token was refused by the provider (HTTP 401): paste a fresh one, or save credentials that can renew it.',
        'needs_scope'   => 'The token works, but the provider does not allow the call: reconnect the app with the scope the channel needs.',
        'no_class'      => 'The channel class is not loaded, so the state cannot be read.',
    ];

    /**
     * The event types in sgap_events that are a *platform's* number, per channel.
     *
     * Everything else in the table is ours: `publish`, `click` and `order` are written by this
     * plugin, so counting them would answer "did we post" when the question is "did the platform
     * report anything". Telegram is deliberately empty: the Bot API has no per-post metrics, so a
     * zero there is not a missing measurement.
     */
    const PLATFORM_TYPES = [
        'pinterest' => ['impressions', 'saves', 'outbound_clicks'],
        'instagram' => ['views', 'likes', 'comments', 'shares', 'saves'],
        'bluesky'   => ['likes', 'reposts', 'replies'],
        'mastodon'  => ['likes', 'reposts', 'replies'],
        'telegram'  => [],
        'facebook'  => ['impressions', 'reach', 'reactions', 'post_clicks'],
    ];

    /** What each analytics state means, in the same voice as NEXT_STEP. */
    const ANALYTICS_NOTE = [
        'measured' => 'Platform numbers are in sgap_events for this channel inside the window.',
        'empty'    => 'No platform number for this channel inside the window: either nothing was posted, or the refresh has not landed a row yet.',
        'no_metric'=> 'This channel has no analytics ingestion: the provider exposes no per-post numbers we can read.',
    ];

    /** The window is bounded so a typo cannot turn into a full-table scan. */
    const MIN_DAYS = 1;
    const MAX_DAYS = 365;

    /** 90 days is Pinterest's own ceiling, so it is the window the report uses by default. */
    const WINDOW_DAYS = 90;

    /** Every state the report can print, and the summary counter each one increments. */
    const STATES = [
        'ready'         => 'ready',
        'needs_refresh' => 'recoverable',
        'missing_token' => 'missing',
        'refused'       => 'refused',
        'needs_scope'   => 'needs_scope',
        'no_class'      => 'broken',
    ];

    /**
     * Six states, and no guessing: a refused token beats a stored one, a stored token wins
     * over a refresh token, and a refresh token alone still counts as recoverable.
     *
     * A token being *stored* was read as `ready` until 7.64.4, which is exactly backwards: on
     * 8/11 the screen called Pinterest ready while every call answered 401. A channel that can
     * report its own auth failure now says so here.
     */
    public static function classify(bool $configured, bool $refreshable, string $refused = '', string $scope = ''): string {
        if ($refused !== '') {
            return 'refused';
        }
        if ($scope !== '') {
            return 'needs_scope';
        }
        if ($configured) {
            return 'ready';
        }
        return $refreshable ? 'needs_refresh' : 'missing_token';
    }

    public static function channel(string $id): array {
        $def = self::CHANNELS[$id] ?? null;
        if ($def === null) {
            return [];
        }
        $class = $def['class'];
        if (!class_exists($class) || !method_exists($class, 'configured')) {
            return [
                'id' => $id, 'label' => $def['label'], 'configured' => false,
                'refreshable' => false, 'state' => 'no_class',
                'next' => self::NEXT_STEP['no_class'],
            ];
        }
        $configured = (bool) call_user_func([$class, 'configured']);
        $refreshable = method_exists($class, 'refreshable') ? (bool) call_user_func([$class, 'refreshable']) : false;
        $refused = [];
        if (method_exists($class, 'refused')) {
            $refused = (array) call_user_func([$class, 'refused']);
        }
        $scope_problem = [];
        if (method_exists($class, 'scope_problem')) {
            $scope_problem = (array) call_user_func([$class, 'scope_problem']);
        }
        $refused_at = (string) ($refused['at'] ?? '');
        $scope_at = (string) ($scope_problem['at'] ?? '');
        $state = self::classify($configured, $refreshable, $refused_at, $scope_at);
        $row = [
            'id' => $id, 'label' => $def['label'],
            'configured' => $configured, 'refreshable' => $refreshable,
            'state' => $state, 'next' => self::NEXT_STEP[$state] ?? '',
        ];
        if ($refused_at !== '') {
            $row['refused_at'] = $refused_at;
        }
        if ($scope_at !== '') {
            $row['scope_at'] = $scope_at;
            $row['missing_scope'] = (string) ($scope_problem['scope'] ?? '');
            $row['next'] = $row['missing_scope'] !== ''
                ? 'The token works; Pinterest only refuses the call. Missing scope: ' . $row['missing_scope'] . '.'
                : $row['next'];
        }
        return $row;
    }

    /** @return array<string,array> one entry per channel, in the order they are reported. */
    public static function report(): array {
        $out = [];
        foreach (array_keys(self::CHANNELS) as $id) {
            $out[$id] = self::channel($id);
        }
        return $out;
    }

    /**
     * Counts for the admin line: how many channels can be measured today.
     *
     * One counter per state, so the line cannot tell the owner to paste a token for a channel whose
     * class is missing: `broken` means the plugin lost a channel class, `missing` means the owner has
     * not pasted a key. Folding the two together made a code regression look like an empty field.
     */
    public static function summary(): array {
        $report = self::report();
        $counts = array_fill_keys(array_values(self::STATES), 0);
        foreach ($report as $row) {
            $state = (string) ($row['state'] ?? 'no_class');
            $key = self::STATES[$state] ?? 'broken';
            $counts[$key]++;
        }
        return [
            'ready' => $counts['ready'], 'recoverable' => $counts['recoverable'],
            'refused' => $counts['refused'], 'needs_scope' => $counts['needs_scope'],
            'missing' => $counts['missing'], 'broken' => $counts['broken'],
            'total' => count($report),
        ];
    }

    /**
     * Which platform numbers have landed in sgap_events, per channel, inside a window.
     *
     * This is the only place the question "did the ingestion actually work?" is answered: the
     * channel report above says whether the next refresh *can* run, and this one says whether the
     * last ones *did* land anything. A channel that is `ready` and `empty` is the interesting row —
     * the token works and nothing came back.
     *
     * Only PLATFORM_TYPES count, and always scoped by channel: `likes` is written by Bluesky,
     * Mastodon, Instagram and Facebook, so a query without the channel would add four platforms
     * into one number. Telegram has no types at all and is reported as `no_metric`, never as 0.
     *
     * @return array<string,array> one entry per channel, in the order they are reported.
     */
    public static function analytics(int $days = self::WINDOW_DAYS): array {
        global $wpdb;
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
        $since = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, event_type, SUM(value) AS total, COUNT(*) AS rows, MAX(created_at) AS last_at
             FROM {$wpdb->prefix}sgap_events WHERE created_at >= %s GROUP BY channel, event_type",
            $since
        ), ARRAY_A);
        $seen = [];
        foreach ((array) $rows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            $type = (string) ($row['event_type'] ?? '');
            if (!isset(self::PLATFORM_TYPES[$channel]) || !in_array($type, self::PLATFORM_TYPES[$channel], true)) {
                continue;
            }
            $seen[$channel][$type] = [
                'total' => (float) ($row['total'] ?? 0),
                'rows' => (int) ($row['rows'] ?? 0),
                'last_at' => (string) ($row['last_at'] ?? ''),
            ];
        }
        $out = [];
        foreach (array_keys(self::CHANNELS) as $id) {
            $types = self::PLATFORM_TYPES[$id] ?? [];
            $counts = [];
            $total = 0.0;
            $rows_count = 0;
            $last = '';
            foreach ($types as $type) {
                $count = $seen[$id][$type] ?? ['total' => 0.0, 'rows' => 0, 'last_at' => ''];
                $counts[$type] = $count['total'];
                $total += $count['total'];
                $rows_count += $count['rows'];
                if ($count['last_at'] > $last) {
                    $last = $count['last_at'];
                }
            }
            $state = $types === [] ? 'no_metric' : ($rows_count > 0 ? 'measured' : 'empty');
            $out[$id] = [
                'id' => $id,
                'label' => self::CHANNELS[$id]['label'],
                'state' => $state,
                'types' => $types,
                'counts' => $counts,
                'total' => $total,
                'rows' => $rows_count,
                'last_at' => $last !== '' ? $last : null,
                'note' => self::ANALYTICS_NOTE[$state] ?? '',
            ];
        }
        return $out;
    }

    /** @return array<string,int> how many channels have landed a number, landed none, or have no metric. */
    public static function analytics_summary(array $analytics): array {
        $out = ['measured' => 0, 'empty' => 0, 'no_metric' => 0, 'total' => count($analytics), 'rows' => 0];
        foreach ($analytics as $row) {
            $state = (string) ($row['state'] ?? 'empty');
            if (isset($out[$state])) {
                $out[$state]++;
            }
            $out['rows'] += (int) ($row['rows'] ?? 0);
        }
        return $out;
    }

    public static function register(): void {
        $auth = [SGAP_Abilities::class, 'can_manage'];
        register_rest_route('sgap/v1', '/channel-health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_report'],
            'permission_callback' => $auth,
        ]);
    }

    public static function rest_report() {
        $analytics = self::analytics();
        return new WP_REST_Response([
            'channels' => self::report(),
            'summary' => self::summary(),
            'window_days' => self::WINDOW_DAYS,
            'analytics' => $analytics,
            'analytics_summary' => self::analytics_summary($analytics),
        ], 200);
    }
}

add_action('rest_api_init', ['SGAP_Channel_Health', 'register']);
