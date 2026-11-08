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
     * Four states, and no guessing: a refused token beats a stored one, a stored token wins
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

    /** Counts for the admin line: how many channels can be measured today. */
    public static function summary(): array {
        $report = self::report();
        $ready = 0;
        $recoverable = 0;
        $missing = 0;
        $refused = 0;
        $scope = 0;
        foreach ($report as $row) {
            if ($row['state'] === 'ready') {
                $ready++;
            } elseif ($row['state'] === 'needs_refresh') {
                $recoverable++;
            } elseif ($row['state'] === 'refused') {
                $refused++;
            } elseif ($row['state'] === 'needs_scope') {
                $scope++;
            } else {
                $missing++;
            }
        }
        return [
            'ready' => $ready, 'recoverable' => $recoverable, 'refused' => $refused,
            'needs_scope' => $scope, 'missing' => $missing, 'total' => count($report),
        ];
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
        return new WP_REST_Response([
            'channels' => self::report(),
            'summary' => self::summary(),
        ], 200);
    }
}

add_action('rest_api_init', ['SGAP_Channel_Health', 'register']);
