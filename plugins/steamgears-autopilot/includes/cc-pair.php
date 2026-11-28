<?php
/**
 * SGAP_CC_Pair — connect Command Center to a new site with a link code (7.95.0, P24).
 *
 * The first step of the "new sites via the plugin" plan (Command Center roadmap, 28 November):
 * an owner installs one-click WordPress at a web host, uploads this plugin and presses
 * "Forbind Command Center (linkkode)" under Steamgears → System. The plugin makes a one-time code
 * (15 minutes, 60 random bits, kept only as a sha256 hash) and shows one string to copy:
 * "sgap1.<base64url({"u": home_url, "c": code})>". Command Center reads the site address out of the
 * string and calls POST {u}/wp-json/sgap/v1/pair {code, cc_url, cc_version}. Command Center thereby
 * only ever calls the site that made the code, and no application password is typed or pasted.
 *
 * The plugin checks the code, burns it, makes an application password named "Command Center" for
 * the administrator who pressed the button and returns it once, together with a short description
 * of the site. Five wrong codes burn the code. Only over https (http for localhost while developing).
 * The bridge stays Command Center → site: this site never calls Command Center.
 *
 * Ported from the unmerged branch p18-flere-sites (7.92.0), without its login-from-CC part and
 * without SGAP_Site, which main does not have.
 *
 * @package Steamgears_Autopilot
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGAP_CC_Pair {
    const CODE = 'sgap_cc_pair_code';
    const MAKE_ACTION = 'sgap_cc_pair_make';
    const TTL = 15 * MINUTE_IN_SECONDS;
    const MAX_TRIES = 5;
    const PREFIX = 'sgap1.';
    const APP_NAME = 'Command Center';
    const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_post_' . self::MAKE_ACTION, [__CLASS__, 'make_from_admin']);
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/pair', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_pair'],
            // The one-time code is the credential: Command Center has no account here yet.
            'permission_callback' => '__return_true'
        ]);
    }

    private static function b64url(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** "ABCD-EFGH-JKLM" from 12 random characters of a 32-letter alphabet (60 bits). */
    public static function new_code(): string {
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return implode('-', str_split($out, 4));
    }

    public static function normalize_code(string $code): string {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    }

    /** The string the owner copies into Command Center. */
    public static function link_string(string $code): string {
        return self::PREFIX . self::b64url((string) wp_json_encode(['u' => home_url('/'), 'c' => $code]));
    }

    /** Pairing needs https, except on localhost. */
    public static function transport_ok(): bool {
        $home = home_url('/');
        $host = strtolower((string) wp_parse_url($home, PHP_URL_HOST));
        return (function_exists('is_ssl') && is_ssl()) || wp_parse_url($home, PHP_URL_SCHEME) === 'https'
            || in_array($host, ['localhost', '127.0.0.1'], true);
    }

    /** The current request came in over https (or to localhost while developing). */
    public static function request_secure(): bool {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }
        $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    /** What Command Center needs to name the new project. Small on purpose: no keys, no settings. */
    public static function site_info(): array {
        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        return [
            'domain' => (string) preg_replace('/^www\./', '', strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST))),
            'name' => (string) get_bloginfo('name'),
            'locale' => $locale,
            'woocommerce' => class_exists('WooCommerce')
        ];
    }

    /** Makes a new code for an administrator; an older code stops working. */
    public static function make(WP_User $user, ?int $now = null) {
        if (!user_can($user, 'manage_options')) {
            return new WP_Error('sgap_pair', 'Only administrators can connect Command Center.');
        }
        if (!class_exists('WP_Application_Passwords') || !wp_is_application_passwords_available_for_user($user)) {
            return new WP_Error('sgap_pair', 'Application passwords are switched off on this site, so Command Center cannot get a login.');
        }
        if (!self::transport_ok()) {
            return new WP_Error('sgap_pair', 'The site must run on https before Command Center can be connected.');
        }
        $now = $now ?? time();
        $code = self::new_code();
        update_option(self::CODE, [
            'hash' => hash('sha256', self::normalize_code($code)),
            'user' => (int) $user->ID,
            'exp' => $now + self::TTL,
            'tries' => 0
        ], false);
        return ['code' => $code, 'link' => self::link_string($code), 'expires' => $now + self::TTL];
    }

    /** admin-post: the "Forbind Command Center" button. Shows the code once, on a plain page. */
    public static function make_from_admin(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Only administrators can connect Command Center.', 403);
        }
        check_admin_referer(self::MAKE_ACTION);
        $made = self::make(wp_get_current_user());
        if (is_wp_error($made)) {
            wp_die(esc_html($made->get_error_message()), 'Command Center', ['response' => 409, 'back_link' => true]);
        }
        $link = esc_attr($made['link']);
        $html = '<h1>Forbind Command Center</h1>'
            . '<p>Kopiér linkkoden og sæt den ind i Command Center. Koden virker én gang og i 15 minutter.</p>'
            . '<p><input type="text" readonly value="' . $link . '" style="width:100%;font-size:16px;padding:10px" onclick="this.select()" id="sgap-link"></p>'
            . '<p><button type="button" class="button button-primary button-hero" onclick="navigator.clipboard.writeText(document.getElementById(\'sgap-link\').value);this.textContent=\'Kopieret\'">Kopiér linkkoden</button></p>'
            . '<p class="description">Kode til at taste i hånden: <code>' . esc_html($made['code']) . '</code> · site: <code>' . esc_html(home_url('/')) . '</code></p>';
        wp_die($html, 'Forbind Command Center', ['response' => 200, 'back_link' => true]);
    }

    /** POST /pair {code, cc_url, cc_version}. */
    public static function rest_pair(WP_REST_Request $request) {
        // The answer carries an application password: refuse a plain-http exchange before the code is touched.
        if (!self::request_secure()) {
            return new WP_Error('sgap_pair', 'Pairing only works over https.', ['status' => 403]);
        }
        return self::pair(
            (string) $request->get_param('code'),
            (string) $request->get_param('cc_url'),
            (string) $request->get_param('cc_version')
        );
    }

    public static function pair(string $code, string $cc_url = '', string $cc_version = '', ?int $now = null) {
        $now = $now ?? time();
        $stored = get_option(self::CODE);
        if (!is_array($stored) || empty($stored['hash'])) {
            return new WP_Error('sgap_pair', 'No link code is waiting. Make a new one on the plugin dashboard.', ['status' => 404]);
        }
        if ($now > (int) ($stored['exp'] ?? 0)) {
            delete_option(self::CODE);
            return new WP_Error('sgap_pair', 'The link code has expired. Make a new one.', ['status' => 410]);
        }
        if (!hash_equals((string) $stored['hash'], hash('sha256', self::normalize_code($code)))) {
            $stored['tries'] = (int) ($stored['tries'] ?? 0) + 1;
            if ($stored['tries'] >= self::MAX_TRIES) {
                delete_option(self::CODE);
                return new WP_Error('sgap_pair', 'Too many wrong codes: the link code is gone. Make a new one.', ['status' => 429]);
            }
            update_option(self::CODE, $stored, false);
            return new WP_Error('sgap_pair', 'Wrong link code.', ['status' => 403]);
        }
        // Burn the code before anything else. delete_option() is one DELETE and returns false when another
        // request removed the row first, so only one of two concurrent calls with the same code goes on.
        if (!delete_option(self::CODE)) {
            return new WP_Error('sgap_pair', 'The link code has already been used. Make a new one.', ['status' => 409]);
        }
        $user = get_userdata((int) ($stored['user'] ?? 0));
        if (!$user || !user_can($user, 'manage_options')) {
            return new WP_Error('sgap_pair', 'The administrator who made the code no longer exists.', ['status' => 409]);
        }
        $created = WP_Application_Passwords::create_new_application_password((int) $user->ID, ['name' => self::APP_NAME]);
        if (is_wp_error($created)) {
            return new WP_Error('sgap_pair', 'WordPress refused to make an application password: ' . $created->get_error_message(), ['status' => 500]);
        }
        $url = class_exists('SGAP_CC') ? SGAP_CC::clean_url($cc_url) : '';
        if ($url !== '') {
            SGAP_Settings::set('command_center_url', $url);
        }
        if (function_exists('do_action')) {
            do_action('sgap_cc_paired', (int) $user->ID, substr(sanitize_text_field($cc_version), 0, 20));
        }
        return [
            'ok' => true,
            'site' => home_url('/'),
            'rest' => rest_url(),
            'user' => (string) $user->user_login,
            'app_password' => (string) $created[0],
            'plugin_version' => defined('SGAP_VERSION') ? SGAP_VERSION : '',
            'info' => self::site_info()
        ];
    }

    /** Whether a code is waiting, without the code. */
    public static function pending(?int $now = null): ?array {
        $stored = get_option(self::CODE);
        if (!is_array($stored) || ($now ?? time()) > (int) ($stored['exp'] ?? 0)) {
            return null;
        }
        return ['expires' => (int) $stored['exp'], 'tries' => (int) ($stored['tries'] ?? 0)];
    }

    /** The button on the plugin dashboard (System → Command Center). */
    public static function dashboard_button(): string {
        if (!current_user_can('manage_options')) {
            return '';
        }
        $wait = self::pending();
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><p>'
            . '<input type="hidden" name="action" value="' . esc_attr(self::MAKE_ACTION) . '">'
            . wp_nonce_field(self::MAKE_ACTION, '_wpnonce', true, false)
            . '<button class="button button-secondary"><span class="dashicons dashicons-admin-links"></span>Forbind Command Center (linkkode)</button>'
            . ($wait ? ' <span class="description">En kode venter til ' . esc_html(wp_date('H:i', $wait['expires'])) . '.</span>' : '')
            . '</p></form>';
    }
}

SGAP_CC_Pair::init();
