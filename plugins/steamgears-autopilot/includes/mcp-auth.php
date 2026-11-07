<?php
/**
 * MCP access — the site keeps its own MCP credential alive.
 *
 * The Novamira surface (`/wp-json/mcp/novamira-oauth`) hands out an access token
 * that lives 3600 seconds, and a refresh token that is **rotated** on every
 * renewal: the old one is dead afterwards. Keeping that pair alive therefore has
 * to happen somewhere that runs on its own, and it has to store the new refresh
 * token the moment it is issued.
 *
 * Until 7.44 that was done from a worktree or a GitHub workflow, which meant
 * repository secrets or a hand-started loop that stops when the sandbox is
 * archived. This class moves the renewal onto the site's own WP-Cron:
 *
 *   - `sgap_mcp_refresh` runs every 30 minutes, renews with the saved refresh
 *     token, verifies the new access token with `initialize` + `tools/list`, and
 *     saves the rotated refresh token.
 *   - Nothing is stored in the repo. The client ID is an ordinary setting; the
 *     refresh token is a secret (autoload off), and the access token and its
 *     expiry are plain non-autoloaded options.
 *   - A renewal that fails is recorded with the API's own reason, shown by the
 *     `mcp-status` ability and raised as a problem in the daily self-check — so
 *     an expired access is not discovered by noticing that nothing works.
 *   - A token that does not verify is never reported as renewed.
 *
 * A refresh token set in wp-config.php cannot be rotated (the constant would
 * keep winning), so that case is refused with an explanation instead of writing
 * a value nothing reads.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_MCP_Auth {
    const CRON = 'sgap_mcp_refresh';
    const SCHEDULE = 'sgap_mcp_half_hour';
    const STATE = 'sgap_mcp_refresh_state';
    const ACCESS = 'sgap_mcp_access_token';
    const EXPIRES = 'sgap_mcp_access_expires';

    /** Seconds of slack: a token inside this window counts as expired. */
    const MARGIN = 120;
    /** Never renew twice inside this window, however often the cron fires. */
    const REFRESH_WINDOW = 240;

    const TOKEN_PATH = '/wp-json/novamira/v1/oauth/token';
    const RESOURCE_PATH = '/wp-json/mcp/novamira-oauth';
    const AUTHORIZE_PAGE = 'admin.php?page=novamira-oauth-authorize';
    const PROTOCOL = '2025-06-18';

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'run']);
    }

    /* ------------------------------------------------------------------ *
     * Credentials
     * ------------------------------------------------------------------ */

    /** The public client ID from the one-time approval. Not a secret. */
    public static function client_id(): string {
        return trim((string) SGAP_Settings::get('mcp_client_id', ''));
    }

    /** The refresh token. Rotated by the server, so it is written back after every use. */
    public static function refresh_token(): string {
        return trim((string) SGAP_Settings::get('mcp_refresh_token', ''));
    }

    public static function configured(): bool {
        return self::client_id() !== '' && self::refresh_token() !== '';
    }

    /** Name of the wp-config.php constant holding the refresh token, or '' when it is stored normally. */
    public static function constant_name(): string {
        return class_exists('SGAP_Settings') ? SGAP_Settings::constant_name('mcp_refresh_token') : '';
    }

    public static function token_endpoint(): string {
        return (string) apply_filters('sgap_mcp_token_endpoint', home_url(self::TOKEN_PATH));
    }

    public static function resource(): string {
        return (string) apply_filters('sgap_mcp_resource', home_url(self::RESOURCE_PATH));
    }

    /* ------------------------------------------------------------------ *
     * Token
     * ------------------------------------------------------------------ */

    /**
     * A usable access token, renewed on demand when the cached one is about to
     * expire. Returns '' when there is no working credential — callers then
     * report that, instead of sending an empty Bearer header.
     */
    public static function token(): string {
        $token = (string) get_option(self::ACCESS, '');
        $expires = (int) get_option(self::EXPIRES, 0);
        if ($token !== '' && $expires > time() + self::MARGIN) {
            return $token;
        }
        $result = self::refresh();
        return is_wp_error($result) ? '' : (string) ($result['access_token'] ?? '');
    }

    /**
     * Renews the access token, verifies it and stores the rotated refresh token.
     * Returns the new token data, or a WP_Error whose message names the reason.
     */
    public static function refresh() {
        if (self::constant_name() !== '') {
            self::record(['error' => 'The refresh token is set in wp-config.php and cannot be rotated there.']);
            return new WP_Error(
                'sgap_mcp_constant',
                'The refresh token is a wp-config.php constant (' . self::constant_name() . '), so a rotated token cannot be stored. Remove the constant and save the token under Settings → MCP access.'
            );
        }
        $client = self::client_id();
        $refresh = self::refresh_token();
        if ($client === '' || $refresh === '') {
            self::record(['error' => 'No MCP client ID or refresh token is saved.']);
            return new WP_Error(
                'sgap_mcp_no_credentials',
                'Set the MCP client ID and an approved refresh token under Settings → MCP access. Approve the client once with tools/mcp_oauth.py --start/--finish.'
            );
        }

        $response = wp_remote_post(self::token_endpoint(), [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                // RFC 6749 §4.1.3: the token endpoint is form-encoded. JSON makes it
                // answer unsupported_grant_type before it ever reads the grant.
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'steamgears-autopilot/' . (defined('SGAP_VERSION') ? SGAP_VERSION : '1')
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
                'client_id' => $client
            ]
        ]);
        if (is_wp_error($response)) {
            $error = 'The token endpoint could not be reached: ' . $response->get_error_message() . '.';
            self::record(['error' => $error]);
            return new WP_Error('sgap_mcp_transport', $error);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body) || empty($body['access_token'])) {
            $detail = self::detail($body);
            $error = 'The token endpoint refused the renewal (HTTP ' . $code . ')' . ($detail !== '' ? ': ' . $detail : '') . '.';
            self::record(['error' => $error]);
            return new WP_Error('sgap_mcp_refused', $error, ['status' => $code]);
        }

        $expires_in = (int) ($body['expires_in'] ?? HOUR_IN_SECONDS);
        $access = (string) $body['access_token'];
        $rotated = isset($body['refresh_token']) && $body['refresh_token'] !== '' && $body['refresh_token'] !== $refresh;

        // Store first: a crash between the call and the write would leave the old,
        // now-dead refresh token behind and force a new approval.
        update_option(self::ACCESS, $access, false);
        update_option(self::EXPIRES, time() + max(60, $expires_in) - self::MARGIN, false);
        if ($rotated) {
            SGAP_Settings::set('mcp_refresh_token', (string) $body['refresh_token']);
        }

        $verify = self::verify($access);
        if (is_wp_error($verify)) {
            $error = 'A new token was issued but does not work: ' . $verify->get_error_message();
            self::record(['error' => $error, 'rotated' => $rotated, 'expires_in' => $expires_in]);
            return new WP_Error('sgap_mcp_unverified', $error);
        }

        $state = self::record([
            'ok' => true,
            'tools' => (int) $verify['tools'],
            'error' => '',
            'rotated' => $rotated,
            'expires_in' => $expires_in
        ]);
        return [
            'access_token' => $access,
            'tools' => (int) $verify['tools'],
            'rotated' => $rotated,
            'expires_in' => $expires_in,
            'session' => !empty($verify['session']),
            'state' => $state
        ];
    }

    /** The `mcp-refresh` tool: the same renewal, with the status attached. */
    public static function refresh_and_report() {
        $result = self::refresh();
        if (is_wp_error($result)) {
            return ['renewed' => false, 'error' => $result->get_error_message(), 'status' => self::status()];
        }
        return [
            'renewed' => true,
            'tools' => $result['tools'],
            'rotated' => $result['rotated'],
            'expires_in' => $result['expires_in'],
            'status' => self::status()
        ];
    }

    /**
     * `initialize` + `notifications/initialized` + `tools/list`.
     *
     * The session header from `initialize` is mandatory on the Novamira surface:
     * without it `tools/list` answers 200 with no tools, which would look like a
     * working token with an empty site.
     */
    public static function verify(string $token) {
        if ($token === '') {
            return new WP_Error('sgap_mcp_no_token', 'There is no access token to verify.');
        }
        $resource = self::resource();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'Mcp-Protocol-Version' => self::PROTOCOL
        ];
        $init = wp_remote_post($resource, [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => self::PROTOCOL,
                    'capabilities' => (object) [],
                    'clientInfo' => [
                        'name' => 'steamgears-autopilot',
                        'version' => defined('SGAP_VERSION') ? SGAP_VERSION : '1'
                    ]
                ]
            ])
        ]);
        if (is_wp_error($init)) {
            return new WP_Error('sgap_mcp_transport', 'initialize failed: ' . $init->get_error_message() . '.');
        }
        $code = (int) wp_remote_retrieve_response_code($init);
        $body = self::decode((string) wp_remote_retrieve_body($init));
        if ($code !== 200 || !is_array($body) || !isset($body['result'])) {
            $detail = self::detail(is_array($body) ? $body : []);
            return new WP_Error('sgap_mcp_initialize', 'initialize answered HTTP ' . $code . ($detail !== '' ? ': ' . $detail : '') . '.');
        }

        $session = (string) wp_remote_retrieve_header($init, 'mcp-session-id');
        if ($session !== '') {
            $headers['Mcp-Session-Id'] = $session;
        }
        wp_remote_post($resource, [
            'timeout' => 15,
            'headers' => $headers,
            'body' => wp_json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])
        ]);

        $list = wp_remote_post($resource, [
            'timeout' => 30,
            'headers' => $headers,
            'body' => wp_json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => (object) []])
        ]);
        if (is_wp_error($list)) {
            return new WP_Error('sgap_mcp_transport', 'tools/list failed: ' . $list->get_error_message() . '.');
        }
        $code = (int) wp_remote_retrieve_response_code($list);
        $body = self::decode((string) wp_remote_retrieve_body($list));
        $tools = is_array($body) ? ($body['result']['tools'] ?? null) : null;
        if ($code !== 200 || !is_array($tools) || !$tools) {
            $detail = self::detail(is_array($body) ? $body : []);
            return new WP_Error(
                'sgap_mcp_tools',
                'tools/list answered HTTP ' . $code . ' with no tools' . ($detail !== '' ? ': ' . $detail : '') . '. A missing Mcp-Session-Id gives exactly this answer; if it repeats, approve the client again.'
            );
        }
        return ['tools' => count($tools), 'session' => $session !== ''];
    }

    /* ------------------------------------------------------------------ *
     * Cron and status
     * ------------------------------------------------------------------ */

    /** Cron callback. Silent while nothing is configured: there is nothing to keep alive yet. */
    public static function run(): void {
        if (!self::configured() || self::constant_name() !== '') {
            return;
        }
        $state = self::state();
        if (!empty($state['ok']) && (time() - (int) $state['ts']) < self::REFRESH_WINDOW) {
            return;
        }
        self::refresh();
    }

    /** Last renewal attempt, with defaults so callers never guard for a missing option. */
    public static function state(): array {
        $state = get_option(self::STATE, []);
        $state = is_array($state) ? $state : [];
        return array_merge([
            'at' => '',
            'ts' => 0,
            'ok' => false,
            'tools' => 0,
            'error' => '',
            'rotated' => false,
            'expires_in' => 0
        ], $state);
    }

    private static function record(array $data): array {
        // Start from the previous attempt for the counters, but never carry an old
        // success into a new failure: `ok` is only true when this attempt says so.
        $state = array_merge(self::state(), ['ok' => false, 'tools' => 0, 'error' => '', 'rotated' => false, 'expires_in' => 0], $data, ['at' => current_time('mysql'), 'ts' => time()]);
        update_option(self::STATE, $state, false);
        return $state;
    }

    /** Everything an operator or an MCP client needs, in one array. */
    public static function status(): array {
        $state = self::state();
        $access = (string) get_option(self::ACCESS, '');
        $expires = (int) get_option(self::EXPIRES, 0);
        $valid = $access !== '' && $expires > time();
        $next = wp_next_scheduled(self::CRON);
        $missing = [];
        if (self::client_id() === '') {
            $missing[] = 'the client ID';
        }
        if (self::refresh_token() === '') {
            $missing[] = 'an approved refresh token';
        }
        if (self::constant_name() !== '') {
            $problem = 'The refresh token is set in wp-config.php (' . self::constant_name() . ') and cannot be rotated there — save it under Settings → MCP access instead.';
        } elseif ($missing) {
            $problem = 'Missing ' . implode(' and ', $missing) . ': approve the MCP client once (tools/mcp_oauth.py --start, then --finish with the code) and paste both values under Settings → MCP access.';
        } elseif (!$state['ok'] && $state['error'] !== '') {
            $problem = 'The last renewal failed: ' . $state['error'];
        } else {
            $problem = '';
        }
        return [
            'configured' => self::configured(),
            'client_id_saved' => self::client_id() !== '',
            'refresh_token_saved' => self::refresh_token() !== '',
            'token_from_constant' => self::constant_name() !== '' ? self::constant_name() : null,
            'access_token_valid' => $valid,
            'expires_at' => $expires ? wp_date('Y-m-d H:i:s', $expires) : null,
            'expires_in' => $valid ? $expires - time() : 0,
            'last_attempt' => $state['at'],
            'last_ok' => (bool) $state['ok'],
            'last_rotated' => (bool) $state['rotated'],
            'tools' => (int) $state['tools'],
            'error' => (string) $state['error'],
            'next_run' => $next ? wp_date('Y-m-d H:i:s', (int) $next) : null,
            'token_endpoint' => self::token_endpoint(),
            'resource' => self::resource(),
            'authorize_page' => admin_url(self::AUTHORIZE_PAGE),
            'problem' => $problem
        ];
    }

    /**
     * A line for the daily self-check, or '' when there is nothing wrong.
     * Silence is on purpose: an unconfigured site has no MCP credential to lose,
     * and warning about one would train the owner to ignore the list.
     */
    public static function problem(): string {
        if (!self::configured() || self::constant_name() !== '') {
            return '';
        }
        $state = self::state();
        if (!empty($state['ok']) || $state['error'] === '') {
            return '';
        }
        return 'MCP access: ' . $state['error'] . ' Renew it under Settings → MCP access, or call the mcp-refresh tool.';
    }

    /* ------------------------------------------------------------------ *
     * Transport helpers (pure; unit-tested in plugins/tests)
     * ------------------------------------------------------------------ */

    /** JSON body, also when the surface answers as server-sent events. */
    public static function decode(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (!preg_match('/^data:\s*(\{.*\})\s*$/', $line, $m)) {
                continue;
            }
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /** The API's own reason from an OAuth or JSON-RPC error body, trimmed to 200 characters. */
    public static function detail(array $body): string {
        $value = $body['error_description'] ?? null;
        if (!is_string($value) || $value === '') {
            $value = $body['error'] ?? null;
        }
        if (is_array($value)) {
            $value = $value['message'] ?? null;
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        return substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 200);
    }
}
