<?php
/**
 * Link to Command Center (humle93/command-center, the self-hosted SEO/marketing engine).
 *
 * - Pairing: one click creates a WordPress Application Password for the current
 *   administrator and shows it as a pairing code; pasted into Command Center it gives
 *   CC its WordPress login (no .env editing, no SSH).
 * - Heartbeat: CC calls POST /wp-json/sgap/v1/heartbeat every few minutes (and hits
 *   wp-cron.php, which keeps WP-Cron running on a cached, quiet site). The plugin
 *   shows when CC was last seen and which version it runs.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_CC {
    const SEEN = 'sgap_cc_seen';
    const APP_NAME = 'Command Center';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 22);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_filter('rest_pre_dispatch', [__CLASS__, 'note_call'], 10, 3);
        add_action('admin_post_sgap_cc_pair', [__CLASS__, 'pair']);
        add_action('admin_post_sgap_cc_unpair', [__CLASS__, 'unpair']);
        add_action('wp_abilities_api_init', [__CLASS__, 'ability']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Command Center', 'Command Center', 'manage_options', 'sgap-cc', [__CLASS__, 'page'], 3);
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/heartbeat', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'heartbeat'],
            'permission_callback' => [SGAP_REST::class, 'can_manage']
        ]);
    }

    /** Remembers when Command Center last called the plugin's API (it sends X-Command-Center: <version>). */
    public static function note_call($result, $server, $request) {
        $version = (string) $request->get_header('x_command_center');
        if ($version !== '' && strpos($request->get_route(), '/' . SGAP_REST::NS) === 0) {
            $seen = self::seen();
            if (empty($seen['at']) || time() - strtotime($seen['at']) > 60 || ($seen['version'] ?? '') !== $version) {
                $seen['at'] = current_time('mysql');
                $seen['version'] = sanitize_text_field($version);
                $seen['route'] = $request->get_route();
                update_option(self::SEEN, $seen, false);
            }
        }
        return $result;
    }

    public static function seen(): array {
        $s = get_option(self::SEEN);
        return is_array($s) ? $s : [];
    }

    /** POST /heartbeat: Command Center reports in; the answer tells CC how the site is doing. */
    public static function heartbeat(WP_REST_Request $request) {
        global $wpdb;
        $body = (array) $request->get_json_params();
        $seen = self::seen();
        $seen['at'] = current_time('mysql');
        $seen['heartbeat_at'] = $seen['at'];
        $seen['version'] = sanitize_text_field((string) ($body['version'] ?? $request->get_header('x_command_center') ?? ''));
        $seen['health'] = sanitize_text_field((string) ($body['health'] ?? ''));
        $seen['alarms'] = absint($body['open_alarms'] ?? 0);
        update_option(self::SEEN, $seen, false);
        $queue = [];
        foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$wpdb->prefix}sgap_queue GROUP BY status") as $row) {
            $queue[$row->status] = (int) $row->n;
        }
        return rest_ensure_response([
            'ok' => true,
            'plugin_version' => SGAP_VERSION,
            'site' => home_url('/'),
            'autopilot' => SGAP_Settings::enabled('autopilot_enabled'),
            'full_auto' => SGAP_Settings::enabled('full_auto_enabled'),
            'queue' => $queue,
            'pending_drafts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = 'pending'"),
            'cron_lag_minutes' => SGAP_Auto::cron_lag(),
            'paused_because' => SGAP_Queue::blocked_reason() !== '' ? SGAP_Queue::blocked_message() : null
        ]);
    }

    /* ---- pairing ---- */

    /** Application Passwords this plugin created for Command Center. */
    public static function app_passwords(int $user_id): array {
        if (!class_exists('WP_Application_Passwords')) {
            return [];
        }
        return array_values(array_filter(WP_Application_Passwords::get_user_application_passwords($user_id), fn($p) => strpos((string) $p['name'], self::APP_NAME) === 0));
    }

    /** Pairing code: base64 JSON with the site URL, user and a fresh Application Password. */
    public static function code(string $url, string $user, string $password): string {
        return 'SGCC1.' . rtrim(strtr(base64_encode((string) wp_json_encode(['url' => $url, 'user' => $user, 'password' => $password])), '+/', '-_'), '=');
    }

    /** @return array{code: string, uuid: string}|WP_Error */
    public static function create_pairing(int $user_id) {
        if (!class_exists('WP_Application_Passwords') || !wp_is_application_passwords_available_for_user(get_userdata($user_id))) {
            return new WP_Error('sgap_no_app_passwords', 'Application Passwords are switched off on this site (they need HTTPS, or a security plugin blocks them).');
        }
        $created = WP_Application_Passwords::create_new_application_password($user_id, ['name' => self::APP_NAME . ' ' . wp_date('Y-m-d H:i')]);
        if (is_wp_error($created)) {
            return $created;
        }
        [$password, $item] = $created;
        $user = get_userdata($user_id);
        return ['code' => self::code(home_url(), $user->user_login, WP_Application_Passwords::chunk_password($password)), 'uuid' => $item['uuid']];
    }

    public static function pair(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('sgap_cc_pair');
        $r = self::create_pairing(get_current_user_id());
        if (is_wp_error($r)) {
            set_transient('sgap_run_result_' . get_current_user_id(), 'Pairing failed: ' . $r->get_error_message(), 300);
        } else {
            // Shown once on the next page view; never stored anywhere else.
            set_transient('sgap_cc_code_' . get_current_user_id(), $r['code'], 600);
        }
        wp_safe_redirect(admin_url('admin.php?page=sgap-cc'));
        exit;
    }

    public static function unpair(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('sgap_cc_unpair');
        $removed = 0;
        foreach (self::app_passwords(get_current_user_id()) as $p) {
            $removed += WP_Application_Passwords::delete_application_password(get_current_user_id(), $p['uuid']) === true ? 1 : 0;
        }
        delete_option(self::SEEN);
        set_transient('sgap_run_result_' . get_current_user_id(), "Command Center disconnected: {$removed} pairing password(s) revoked.", 300);
        wp_safe_redirect(admin_url('admin.php?page=sgap-cc'));
        exit;
    }

    /* ---- status ---- */

    /** ['state' => connected|stale|never, 'label' => ...] */
    public static function status(): array {
        $seen = self::seen();
        if (empty($seen['at'])) {
            return ['state' => 'never', 'label' => 'Not connected', 'seen' => $seen];
        }
        $mins = (int) floor((time() - strtotime($seen['at'])) / 60);
        $ago = $mins < 1 ? 'just now' : ($mins < 120 ? "{$mins} min ago" : round($mins / 60) . ' h ago');
        return ['state' => $mins <= 45 ? 'connected' : 'stale', 'label' => ($mins <= 45 ? 'Connected' : 'Not heard from') . " · last seen {$ago}" . (!empty($seen['version']) ? ' · CC ' . $seen['version'] : ''), 'seen' => $seen, 'minutes' => $mins];
    }

    public static function card(): void {
        $s = self::status();
        SGAP_UI::card_open('Command Center', 'networking');
        echo '<p>' . SGAP_UI::pill($s['label'], ['connected' => 'ok', 'stale' => 'warn', 'never' => ''][$s['state']]) . '</p>';
        if ($s['state'] === 'never') {
            echo '<p class="description">Link your Command Center to see the autopilot there, send its posts here as drafts, and keep WP-Cron running.</p>';
        }
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-cc')) . '"><span class="dashicons dashicons-admin-links"></span>' . ($s['state'] === 'never' ? 'Link Command Center' : 'Manage link') . '</a></p>';
        SGAP_UI::card_close();
    }

    public static function page(): void {
        SGAP_Admin::open('Command Center', 'Link your self-hosted Command Center with this site in two steps.');
        $user_id = get_current_user_id();
        $code = get_transient('sgap_cc_code_' . $user_id);
        if ($code) {
            delete_transient('sgap_cc_code_' . $user_id);
        }
        $s = self::status();
        $passwords = self::app_passwords($user_id);
        echo '<div class="sgap-pills">' . SGAP_UI::pill($s['label'], ['connected' => 'ok', 'stale' => 'warn', 'never' => ''][$s['state']])
            . ($passwords ? SGAP_UI::pill(count($passwords) . ' pairing password(s)', 'brass') : '') . '</div>';
        echo '<div class="sgap-grid"><div>';
        SGAP_UI::card_open('1. Create a pairing code', 'admin-network');
        if ($code) {
            echo '<p><strong>Copy this code now – it is shown only once.</strong></p>'
                . '<div class="sgap-feed"><code id="sgap-cc-code" style="white-space:normal;word-break:break-all">' . esc_html($code) . '</code></div>'
                . '<p><button type="button" class="button button-primary sgap-copy" data-url="' . esc_attr($code) . '"><span class="dashicons dashicons-admin-page"></span>Copy code</button></p>'
                . '<script>document.querySelectorAll(".sgap-copy").forEach(function(b){var h=b.innerHTML;b.addEventListener("click",function(){var u=b.dataset.url,ok=function(){b.textContent="Copied ✓";setTimeout(function(){b.innerHTML=h;},2000);},'
                . 'old=function(){var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();try{document.execCommand("copy");}catch(e){}t.remove();ok();};'
                . 'if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(u).then(ok,old);}else{old();}});});</script>';
        } else {
            echo '<p class="description">Creates a WordPress Application Password for your user (' . esc_html(wp_get_current_user()->user_login) . ') that only Command Center uses. You can revoke it here at any time.</p>'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sgap_cc_pair">'
                . wp_nonce_field('sgap_cc_pair', '_wpnonce', true, false)
                . '<button class="button button-primary"><span class="dashicons dashicons-admin-network"></span>Create pairing code</button></form>';
        }
        SGAP_UI::card_close();
        SGAP_UI::card_open('2. Paste it into Command Center', 'admin-links');
        echo '<ol class="sgap-howto"><li>Open your Command Center.</li><li>On the dashboard, find <em>⚙️ Steamgears Autopilot</em> → <em>Link WordPress</em>.</li>'
            . '<li>Paste the code and press <strong>Link</strong>. Command Center tests the login and reports in here within a minute.</li></ol>'
            . '<p class="description">Works with Command Center 7.11 or newer (press "Opdater" in CC first). Older versions: put WP_URL, WP_USER and WP_APP_PASSWORD in its .env.</p>';
        SGAP_UI::card_close();
        echo '</div><div>';
        SGAP_UI::card_open('What the link does', 'info-outline');
        echo '<ul class="sgap-howto" style="list-style:disc"><li>Command Center shows queue, drafts, clicks and orders from here, and alarms on failures (incl. Telegram).</li>'
            . '<li>Posts written in Command Center can be sent here as drafts (→ Autopilot).</li>'
            . '<li>Every 5 minutes Command Center wakes WP-Cron, so posts go out on time even when the site is quiet.</li></ul>';
        if (!empty($s['seen'])) {
            $seen = $s['seen'];
            echo '<p class="description">Last call: ' . esc_html(($seen['at'] ?? '–') . (isset($seen['route']) ? ' · ' . $seen['route'] : '')) . (isset($seen['heartbeat_at']) ? '<br>Last heartbeat: ' . esc_html($seen['heartbeat_at']) : '')
                . (!empty($seen['health']) ? '<br>CC health: ' . esc_html($seen['health']) . (isset($seen['alarms']) ? ' · ' . (int) $seen['alarms'] . ' open alarms' : '') : '') . '</p>';
        }
        SGAP_UI::card_close();
        if ($passwords) {
            SGAP_UI::card_open('Disconnect', 'dismiss');
            echo '<p class="description">Revokes the pairing password(s); Command Center loses access immediately.</p>'
                . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="sgap_cc_unpair">'
                . wp_nonce_field('sgap_cc_unpair', '_wpnonce', true, false)
                . '<button class="button sgap-danger">Disconnect Command Center</button></form>';
            SGAP_UI::card_close();
        }
        echo '</div></div>';
        SGAP_Admin::close();
    }

    public static function ability(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        wp_register_ability(SGAP_Abilities::CATEGORY . '/command-center-status', [
            'label' => 'Command Center link status',
            'description' => 'Whether the self-hosted Command Center is linked to this site: when it last called the plugin, its version and health, and how many pairing passwords exist.',
            'category' => SGAP_Abilities::CATEGORY,
            'output_schema' => ['type' => 'object'],
            'execute_callback' => fn() => self::status() + ['pairing_passwords' => count(self::app_passwords(get_current_user_id()))],
            'permission_callback' => [SGAP_Abilities::class, 'can_manage'],
            'meta' => SGAP_Abilities::meta(true, true, false)
        ]);
    }
}
