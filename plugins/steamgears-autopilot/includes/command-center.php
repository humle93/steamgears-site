<?php
/**
 * Command Center link (one-way): Command Center calls this site's REST API
 * (GET /snapshot, POST /drafts …) with an admin application password and sends
 * an X-Command-Center: <version> header. This file only notices those calls, so
 * the plugin can show "Command Center connected" and the self-check can warn
 * when a dashboard that used to call stops doing so. Nothing here calls out.
 *
 * One-click login (7.76.0, P10): Command Center puts its public address and a shared
 * secret here with POST /sgap/v1/cc-link on every sync. The "Log ind i Command Center"
 * button then signs a ticket for the WordPress user who clicked (HMAC-SHA256, valid 60 s,
 * a random jti that Command Center accepts once) and sends the browser to {cc}/cc-sso.
 * Command Center only logs in a user whose e-mail it already knows. The secret never
 * leaves this file: not in the snapshot, not in an ability, not in the HTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGAP_CC {
    const SEEN = 'sgap_cc_seen';
    /** Command Center syncs every 30 minutes; three missed rounds count as gone. */
    const STALE = 2 * HOUR_IN_SECONDS;
    /** Where Command Center's address and the shared secret live (not autoloaded). */
    const LINK = 'sgap_cc_link';
    const LOGIN_ACTION = 'sgap_cc_login';
    /** A ticket is used right away by the browser; a minute is plenty and keeps a leaked URL short-lived. */
    const TICKET_TTL = 60;
    const MIN_SECRET = 32;

    public static function init(): void {
        add_filter('rest_request_before_callbacks', [__CLASS__, 'note_call'], 10, 3);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_post_' . self::LOGIN_ACTION, [__CLASS__, 'login']);
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/cc-link', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_link'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    /**
     * Command Center's own address: https, or http only for a loopback host (a dashboard under
     * development). A path is kept (a dashboard behind /cc), query and fragment are not.
     */
    public static function clean_url(string $url): string {
        $url = trim($url);
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if (($parts['scheme'] === 'http') && !in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
            return '';
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        return $parts['scheme'] . '://' . $host . $port . rtrim((string) ($parts['path'] ?? ''), '/');
    }

    /** POST /cc-link {url, secret, version}: an empty secret removes the link. */
    public static function rest_link(WP_REST_Request $request) {
        $secret = (string) $request->get_param('secret');
        if ($secret === '') {
            delete_option(self::LINK);
            return ['ok' => true, 'linked' => false];
        }
        $url = self::clean_url((string) $request->get_param('url'));
        if ($url === '') {
            return new WP_Error('sgap_cc_link', 'url must be https:// (http only for localhost).', ['status' => 400]);
        }
        if (strlen($secret) < self::MIN_SECRET || strlen($secret) > 256 || preg_match('/[^A-Za-z0-9_\-]/', $secret)) {
            return new WP_Error('sgap_cc_link', 'secret must be 32-256 URL-safe characters.', ['status' => 400]);
        }
        $old = get_option(self::LINK);
        $new = [
            'url' => $url,
            'secret' => $secret,
            'version' => sanitize_text_field(substr((string) $request->get_param('version'), 0, 20)),
            'at' => is_array($old) && ($old['secret'] ?? '') === $secret && ($old['url'] ?? '') === $url ? (int) ($old['at'] ?? time()) : time()
        ];
        update_option(self::LINK, $new, false);
        return ['ok' => true, 'linked' => true, 'url' => $url];
    }

    /** The stored link, or null when Command Center has not sent one. */
    public static function link(): ?array {
        $link = get_option(self::LINK);
        return is_array($link) && !empty($link['url']) && strlen((string) ($link['secret'] ?? '')) >= self::MIN_SECRET ? $link : null;
    }

    private static function b64url(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * A signed login ticket for a WordPress user: base64url(json) . '.' . base64url(hmac).
     * Command Center checks the signature with the shared secret, the expiry and the jti.
     */
    public static function ticket(WP_User $user, string $secret, ?int $now = null): string {
        $now = $now ?? time();
        $payload = self::b64url((string) wp_json_encode([
            'v' => 1,
            'email' => strtolower((string) $user->user_email),
            'login' => (string) $user->user_login,
            'site' => home_url('/'),
            'iat' => $now,
            'exp' => $now + self::TICKET_TTL,
            'jti' => bin2hex(random_bytes(16))
        ]));
        return $payload . '.' . self::b64url(hash_hmac('sha256', $payload, $secret, true));
    }

    /** The address the button sends the browser to, or a WP_Error that says why it cannot. */
    public static function login_url(WP_User $user) {
        $link = self::link();
        if (!$link) {
            return new WP_Error('sgap_cc_login', 'Command Center has not connected the login button yet: it needs Command Center 7.27+ with CC_PUBLIC_URL set, and one sync.');
        }
        if ((string) $user->user_email === '') {
            return new WP_Error('sgap_cc_login', 'Your WordPress user has no e-mail address, so Command Center cannot tell who you are.');
        }
        return $link['url'] . '/cc-sso?t=' . rawurlencode(self::ticket($user, (string) $link['secret']));
    }

    /** Rights, nonce and the ticket URL for the user who clicked — everything but the redirect. */
    public static function login_target() {
        if (!current_user_can('manage_options')) {
            wp_die('Only administrators can log in to Command Center from here.', 403);
        }
        check_admin_referer(self::LOGIN_ACTION);
        return self::login_url(wp_get_current_user());
    }

    /** admin-post: an admin clicked "Log ind i Command Center". */
    public static function login(): void {
        $url = self::login_target();
        if (is_wp_error($url)) {
            wp_die(esc_html($url->get_error_message()), 'Command Center', ['response' => 409, 'back_link' => true]);
        }
        // An external host on purpose: wp_safe_redirect() would refuse it.
        wp_redirect($url, 303);
        exit;
    }

    /** Records a Command Center call on our own routes (at most once a minute). */
    public static function note_call($response, $handler, $request) {
        if (!$request instanceof WP_REST_Request || strpos((string) $request->get_route(), '/' . SGAP_REST::NS . '/') !== 0) {
            return $response;
        }
        $version = sanitize_text_field(substr((string) $request->get_header('x_command_center'), 0, 20));
        if ($version === '' || !current_user_can('manage_options')) {
            return $response;
        }
        $seen = get_option(self::SEEN);
        if (!is_array($seen) || time() - (int) ($seen['ts'] ?? 0) >= 60 || ($seen['version'] ?? '') !== $version) {
            update_option(self::SEEN, [
                'ts' => time(),
                'version' => $version,
                'route' => (string) $request->get_route(),
                'user' => wp_get_current_user()->user_login,
                'first' => is_array($seen) && !empty($seen['first']) ? (int) $seen['first'] : time()
            ], false);
        }
        return $response;
    }

    /** connected / stale / never, with the last call. */
    public static function status(): array {
        $seen = get_option(self::SEEN);
        if (!is_array($seen) || empty($seen['ts'])) {
            return ['state' => 'never', 'connected' => false, 'last_seen' => null, 'version' => null, 'sso' => self::link() !== null];
        }
        $age = time() - (int) $seen['ts'];
        return [
            // Only whether the login button works — never the address' secret.
            'sso' => self::link() !== null,
            'state' => $age <= self::STALE ? 'connected' : 'stale',
            'connected' => $age <= self::STALE,
            'last_seen' => wp_date('Y-m-d H:i', (int) $seen['ts']),
            'minutes_ago' => (int) floor($age / 60),
            'version' => (string) ($seen['version'] ?? ''),
            'last_route' => (string) ($seen['route'] ?? ''),
            'user' => (string) ($seen['user'] ?? '')
        ];
    }

    /** For the self-check: only a dashboard that has called before can go missing. */
    public static function problem(): string {
        $s = self::status();
        if ($s['state'] !== 'stale') {
            return '';
        }
        $hours = (int) floor($s['minutes_ago'] / 60);
        return "Command Center has not called for {$hours} h (last {$s['last_seen']}): check that it is running and that its application password is still valid.";
    }

    /**
     * "Log ind i Command Center": a one-click login once Command Center has sent its link, else a
     * plain link to the address in Settings (login there by hand). Empty when neither exists.
     */
    public static function dashboard_button(): string {
        if (self::link() && current_user_can('manage_options')) {
            return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" target="_blank"><p>'
                . '<input type="hidden" name="action" value="' . esc_attr(self::LOGIN_ACTION) . '">'
                . wp_nonce_field(self::LOGIN_ACTION, '_wpnonce', true, false)
                . '<button class="button button-primary"><span class="dashicons dashicons-external"></span>Log ind i Command Center</button>'
                . '</p></form>';
        }
        $url = esc_url(SGAP_Settings::get('command_center_url'));
        if ($url === '') {
            return '';
        }
        return '<p><a class="button button-secondary" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
            . '<span class="dashicons dashicons-external"></span>Open Command Center</a> '
            . '<span class="description">One-click login needs Command Center 7.27+ with CC_PUBLIC_URL.</span></p>';
    }

    /** One line for the dashboard. */
    public static function line(): string {
        $s = self::status();
        if ($s['state'] === 'never') {
            return '';
        }
        $text = $s['connected']
            ? sprintf('Command Center %s connected · last sync %d min ago', $s['version'], $s['minutes_ago'])
            : sprintf('Command Center silent since %s', $s['last_seen']);
        return SGAP_UI::pill($s['connected'] ? 'CC' : 'CC !', $s['connected'] ? 'ok' : 'warn') . ' <span class="description">' . esc_html($text) . '</span>';
    }
}

SGAP_CC::init();
