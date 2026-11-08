<?php
/**
 * Bluesky — the one channel the plugin can post to without Publer (P2.1).
 *
 * Why it exists: Bluesky needs no app review and no partner tier — an app password
 * and two API calls are enough. Publer stays the publisher for every other channel;
 * this class is only reached from the same publish loop, with the same return value
 * (a job id or a WP_Error) and the same failure path (backoff, alert, publish log).
 *
 * The two calls are com.atproto.server.createSession (handle + app password) and
 * com.atproto.repo.createRecord (the post itself), both through WordPress' HTTP API
 * so they can be stubbed in tests — no SDK, no Composer package.
 *
 * Text rules: Bluesky counts 300 graphemes, not bytes. A vacated grapheme (an emoji
 * with a skin-tone modifier, an accented letter) must not be cut in half, so the
 * trimming works on \X matches. The tracking link is reserved before the body is
 * shortened, and the URL is also declared as a richtext facet — a bare URL in the
 * text is not clickable on Bluesky.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Bluesky {
    /** Public AppView/PDS endpoint; a self-hosted PDS is not supported yet. */
    const PDS = 'https://bsky.social/xrpc/';

    const SESSION = 'sgap_bluesky_session';
    const SESSION_TTL = 90 * MINUTE_IN_SECONDS;
    const MAX = 300;

    public static function handle(): string {
        return ltrim(trim((string) SGAP_Settings::get('bluesky_handle', '')), '@');
    }

    public static function password(): string {
        return trim((string) SGAP_Settings::get('bluesky_app_password', ''));
    }

    /** Both credentials present. Saying nothing about the channel list. */
    public static function configured(): bool {
        return self::handle() !== '' && self::password() !== '';
    }

    /**
     * Direct posting is on when the credentials exist *and* bluesky is one of the
     * enabled channels — the same list that decides what the plugin queues at all.
     * Removing it from the list, or clearing the password, turns the channel off.
     */
    public static function enabled(): bool {
        return self::configured() && in_array('bluesky', SGAP_Publer::channels(), true);
    }

    public static function handles(string $channel): bool {
        return $channel === 'bluesky' && self::enabled();
    }

    /** Extended grapheme clusters: the unit Bluesky counts and a cut may not split. */
    public static function graphemes(string $text): array {
        preg_match_all('/\X/u', $text, $matches);
        return $matches[0] ?? [];
    }

    public static function length(string $text): int {
        return count(self::graphemes($text));
    }

    /** Cuts at a grapheme boundary and marks the cut, so a shortened post reads as one. */
    public static function shorten(string $text, int $max): string {
        $graphemes = self::graphemes($text);
        if (count($graphemes) <= $max) {
            return $text;
        }
        $keep = max(0, $max - 1);
        return rtrim(implode('', array_slice($graphemes, 0, $keep))) . '…';
    }

    /**
     * Body plus tracking link, inside 300 graphemes. The link is reserved first and
     * never shortened: a cut URL is worse than a short caption. Falls back to the
     * title when a post has no body, and returns '' when not even the link fits.
     */
    public static function compose(array $row, string $url): string {
        $body = trim((string) ($row['body'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($row['title'] ?? ''));
        }
        if ($body === '') {
            return '';
        }
        $room = self::MAX - self::length($url) - 2; // the two newlines before the link
        if ($room < 1) {
            return '';
        }
        return self::shorten($body, $room) . "\n\n" . $url;
    }

    /**
     * Richtext facets — byte offsets, as the AT Protocol specifies. The link is what
     * makes the post worth anything on Bluesky; hashtags are highlighted by the app.
     */
    public static function facets(string $text, string $url): array {
        $facets = [];
        if ($url !== '') {
            $pos = strpos($text, $url);
            if ($pos !== false) {
                $facets[] = [
                    'index' => ['byteStart' => $pos, 'byteEnd' => $pos + strlen($url)],
                    'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]]
                ];
            }
        }
        preg_match_all('/(?<![\p{L}\p{N}_])#([\p{L}\p{N}_]+)/u', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $hit) {
            $facets[] = [
                'index' => ['byteStart' => $hit[1], 'byteEnd' => $hit[1] + strlen($hit[0])],
                'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => substr($hit[0], 1)]]
            ];
        }
        return $facets;
    }

    /**
     * One XRPC call through wp_remote_post. Returns the decoded body or a WP_Error
     * that names what Bluesky said — "invalid_grant" is actionable, "HTTP 400" is not.
     */
    private static function rpc(string $method, ?array $body, string $token = '') {
        $args = [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']
        ];
        if ($token !== '') {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_post(self::PDS . $method, $args);
        if (is_wp_error($response)) {
            return new WP_Error('sgap_bluesky_network', 'Bluesky could not be reached: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $reason = is_array($data)
                ? trim((string) ($data['error'] ?? '') . ' ' . (string) ($data['message'] ?? ''))
                : '';
            return new WP_Error(
                'sgap_bluesky_http',
                'Bluesky: ' . ($reason !== '' ? $reason : 'HTTP ' . $code),
                ['status' => $code, 'method' => $method]
            );
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Login, cached for 90 minutes. A refused login is passed on as the WP_Error it is;
     * an accepted one without a token is not trusted to work later.
     */
    public static function session(bool $fresh = false) {
        if (!$fresh) {
            $cached = get_transient(self::SESSION);
            if (is_array($cached) && !empty($cached['accessJwt']) && !empty($cached['did'])) {
                return $cached;
            }
        }
        if (!self::configured()) {
            return new WP_Error('sgap_bluesky_setup', 'Bluesky is not configured: set the handle and an app password under Settings → Publishing.');
        }
        $session = self::rpc('com.atproto.server.createSession', [
            'identifier' => self::handle(),
            'password' => self::password()
        ]);
        if (is_wp_error($session)) {
            return $session;
        }
        if (empty($session['accessJwt']) || empty($session['did'])) {
            return new WP_Error('sgap_bluesky_setup', 'Bluesky accepted the login but returned no access token; check that the password is an app password, not the account password.');
        }
        set_transient(self::SESSION, $session, self::SESSION_TTL);
        return $session;
    }

    public static function forget(): void {
        delete_transient(self::SESSION);
    }

    /**
     * Publishes one queue row. Same contract as SGAP_Publer::publish(): the AT URI of
     * the post (the queue's external id) on success, a WP_Error with the reason on
     * failure — never a silent no-op, and never a PHP warning.
     */
    public static function publish(array $row) {
        if (!self::configured()) {
            return new WP_Error('sgap_bluesky_setup', 'Bluesky is not configured: set the handle and an app password under Settings → Publishing, and add bluesky to the channels list.');
        }
        $url = (string) ($row['tracking_url'] ?? '');
        if ($url === '' && !empty($row['id'])) {
            $url = SGAP_Tracking::url((int) $row['id']);
        }
        $text = self::compose($row, $url);
        if ($text === '') {
            return new WP_Error('sgap_bluesky_text', 'Bluesky: the post has no text, or the tracking link alone leaves no room inside 300 characters.');
        }
        $record = ['$type' => 'app.bsky.feed.post', 'text' => $text, 'createdAt' => gmdate('c')];
        $facets = self::facets($text, $url);
        if ($facets) {
            $record['facets'] = $facets;
        }

        $session = self::session();
        if (is_wp_error($session)) {
            return $session;
        }
        $payload = ['repo' => (string) $session['did'], 'collection' => 'app.bsky.feed.post', 'record' => $record];
        $created = self::rpc('com.atproto.repo.createRecord', $payload, (string) $session['accessJwt']);

        // An access token lives about two hours, so a cached session can be refused
        // before the transient runs out. One fresh login, then the same verdict stands.
        if (is_wp_error($created) && (int) (($created->get_error_data() ?: [])['status'] ?? 0) === 401) {
            self::forget();
            $session = self::session(true);
            if (is_wp_error($session)) {
                return $session;
            }
            $payload['repo'] = (string) $session['did'];
            $created = self::rpc('com.atproto.repo.createRecord', $payload, (string) $session['accessJwt']);
        }
        if (is_wp_error($created)) {
            return $created;
        }
        $uri = (string) ($created['uri'] ?? '');
        return $uri !== '' ? $uri : new WP_Error('sgap_bluesky_http', 'Bluesky accepted the post but returned no AT URI.');
    }
}
