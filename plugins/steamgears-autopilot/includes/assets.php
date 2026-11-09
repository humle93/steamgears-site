<?php
/**
 * Asset loader: one stylesheet and one script, enqueued only where they are used.
 *
 * Until 7.65.2 the plugin printed its whole stylesheet — 40 452 characters — into the
 * <head> of every admin page it rendered, plus menu CSS/JS on every admin page and a
 * small submit script per page. That HTML was downloaded again on every page view and
 * could never be cached by the browser.
 *
 * Now the files are ordinary assets: `assets/admin.css`, `admin.js` for the plugin's own
 * screens, `menu.css`/`menu.js` for the sidebar menu that WordPress shows on every admin
 * page, and one file per page in `assets/pages/` for the behaviour that used to be written
 * into the page as an inline <script> with its configuration as JSON. They carry the file's own modification time as their version, so a change is
 * never served stale after an update, and they are only loaded for users who can manage
 * the plugin.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Assets {
    const STYLE = 'sgap-admin';
    const SCRIPT = 'sgap-admin';
    const MENU_STYLE = 'sgap-menu';
    const MENU_SCRIPT = 'sgap-menu';
    const TEXTDOMAIN = 'steamgears-autopilot';

    /** Absolute path with a trailing slash, so the file checks work in a test. */
    public static function dir(): string {
        $base = defined('SGAP_PATH') ? SGAP_PATH : dirname((string) constant('SGAP_FILE')) . '/';
        return rtrim($base, '/') . '/';
    }

    /** Public URL of a file in assets/. */
    public static function url(string $file): string {
        return plugins_url('assets/' . ltrim($file, '/'), defined('SGAP_FILE') ? SGAP_FILE : __FILE__);
    }

    /**
     * Version string for a file: its modification time.
     *
     * SGAP_VERSION alone would leave the browser with an old stylesheet after an update
     * that only changed CSS — WordPress only busts the cache when the version changes.
     */
    public static function version(string $file): string {
        $path = self::dir() . 'assets/' . ltrim($file, '/');
        if (is_readable($path)) {
            $mtime = filemtime($path);
            if ($mtime) {
                return (string) $mtime;
            }
        }
        return defined('SGAP_VERSION') ? (string) constant('SGAP_VERSION') : '0';
    }

    /**
     * Is this admin screen one of the plugin's own pages?
     *
     * Pure, so the rule can be tested without WordPress. The hook suffix is built by
     * WordPress as `{parent}_page_{slug}`; the plugin's parent is `steamgears-autopilot`,
     * and its submenu slugs all start with `sgap-`.
     */
    public static function is_plugin_screen(string $hook): bool {
        if ($hook === '') {
            return false;
        }
        return str_contains($hook, 'steamgears-autopilot') || str_contains($hook, '_page_sgap-');
    }

    public static function init(): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    /**
     * Load the assets for the current admin request.
     *
     * The menu assets go everywhere, because the menu is visible everywhere; the page
     * assets only on the plugin's own screens. Both are limited to users who can act
     * on the plugin, so no other role pays for them.
     */
    public static function enqueue(string $hook = ''): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::enqueue_menu();
        if (self::is_plugin_screen($hook)) {
            self::enqueue_admin();
        }
    }

    /** Stylesheet and behaviour for the plugin's own pages. */
    public static function enqueue_admin(): void {
        if (!current_user_can('manage_options') || !function_exists('wp_enqueue_style')) {
            return;
        }
        wp_enqueue_style(self::STYLE, self::url('admin.css'), [], self::version('admin.css'), 'all');
        wp_enqueue_script(self::SCRIPT, self::url('admin.js'), [], self::version('admin.js'), true);
    }

    /**
     * One page's behaviour, with its configuration as a JS global.
     *
     * The pages used to build their own `<script>` with `wp_json_encode($cfg)` inside it,
     * which cannot be cached, cannot be linted and grows the HTML on every request. The
     * file now lives in assets/pages/, and the configuration that has to be computed per
     * request (nonces, the chat history, the tool catalogue) is handed over by
     * wp_localize_script, which prints it as one object before the script runs.
     */
    public static function page_script(string $name, string $object, array $config = []): void {
        if (!function_exists('wp_enqueue_script')) {
            return;
        }
        $file = 'pages/' . $name . '.js';
        $handle = 'sgap-page-' . $name;
        wp_enqueue_script($handle, self::url($file), [], self::version($file), true);
        if ($config !== [] && function_exists('wp_localize_script')) {
            wp_localize_script($handle, $object, $config);
        }
    }

    /** Styling and the "More" toggle for the sidebar menu. */
    public static function enqueue_menu(): void {
        if (!current_user_can('manage_options') || !function_exists('wp_enqueue_style')) {
            return;
        }
        wp_enqueue_style(self::MENU_STYLE, self::url('menu.css'), [], self::version('menu.css'), 'all');
        wp_enqueue_script(self::MENU_SCRIPT, self::url('menu.js'), [], self::version('menu.js'), true);
    }
}

SGAP_Assets::init();
