<?php
/**
 * Self-update from GitHub (humle93/steamgears-site, branch main).
 * Hooks into WordPress' own update system, so a newer version shows up under
 * Plugins and Dashboard → Updates (and auto-updates work), and adds an
 * "Update now" button on the Steamgears dashboard plus a Claude/MCP ability.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Updater {
    const RAW = 'https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/';
    const REPO = 'https://github.com/humle93/steamgears-site/tree/main/plugins/steamgears-autopilot';
    const SLUG = 'steamgears-autopilot';
    const CACHE = 'sgap_remote_version';
    const NOTES_CACHE = 'sgap_release_notes';
    const ADMIN_CACHE = 'sgap_remote_version_admin';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 10, 3);
        add_action('admin_post_sgap_self_update', [__CLASS__, 'admin_update']);
    }

    public static function basename(): string {
        return plugin_basename(SGAP_FILE);
    }

    /** "Version: x.y.z" from a plugin header. Pure function. */
    public static function parse_version(string $header): string {
        return preg_match('/^[\s\*#@]*Version:\s*([0-9][0-9A-Za-z.\-]*)\s*$/mi', $header, $m) ? $m[1] : '';
    }

    /**
     * Release notes for the update modal, from the roadmap's changelog table. Pure function:
     * the newest rows first, HTML-escaped, with the two bits of markdown the table uses.
     */
    public static function changelog_notes(string $markdown, int $limit = 3): string {
        $rows = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (!preg_match('/^\|\s*(\d{4}-\d{2}-\d{2})\s*\|\s*([0-9][0-9A-Za-z.\-]*)\s*\|\s*(.+?)\s*\|\s*$/', trim($line), $m)) {
                continue;
            }
            $rows[] = $m;
            if (count($rows) >= max(1, $limit)) {
                break;
            }
        }
        if (!$rows) {
            return '';
        }
        $out = '<ul class="sgap-release-notes">';
        foreach ($rows as $m) {
            $out .= '<li><strong>' . esc_html($m[2]) . '</strong> <span class="description">' . esc_html($m[1]) . '</span><br>'
                . self::note_html($m[3]) . '</li>';
        }
        return $out . '</ul>';
    }

    /** Escapes a changelog cell and turns **bold** and `code` into markup. */
    private static function note_html(string $text): string {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html($text));
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', (string) $html);
    }

    /** Release notes for the latest release on GitHub, cached for 6 hours. '' when unreadable. */
    public static function remote_changelog(bool $fresh = false): string {
        $cached = get_site_transient(self::NOTES_CACHE);
        if (!$fresh && is_string($cached) && $cached !== '') {
            return $cached === 'none' ? '' : $cached;
        }
        $response = wp_remote_get(self::RAW . 'steamgears-autopilot/ROADMAP.md', ['timeout' => 10]);
        $notes = '';
        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $notes = self::changelog_notes(substr((string) wp_remote_retrieve_body($response), 0, 400000));
        }
        set_site_transient(self::NOTES_CACHE, $notes !== '' ? $notes : 'none', $notes !== '' ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
        return $notes;
    }

    public static function package_url(string $version): string {
        return self::RAW . 'steamgears-autopilot-v' . rawurlencode($version) . '.zip';
    }

    /** Latest version on GitHub, cached for 6 hours. '' when GitHub cannot be read. */
    public static function remote_version(bool $fresh = false): string {
        $cached = get_site_transient(self::CACHE);
        if (!$fresh && is_string($cached) && $cached !== '') {
            return $cached === 'none' ? '' : $cached;
        }
        $response = wp_remote_get(self::RAW . 'steamgears-autopilot/steamgears-autopilot.php', ['timeout' => 10]);
        $version = '';
        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $version = self::parse_version(substr((string) wp_remote_retrieve_body($response), 0, 2000));
        }
        // Remember a failure for 30 minutes too, so an unreachable GitHub never slows every admin page.
        set_site_transient(self::CACHE, $version !== '' ? $version : 'none', $version !== '' ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
        return $version;
    }

    public static function update_available(bool $fresh = false): bool {
        $remote = self::remote_version($fresh);
        return $remote !== '' && version_compare($remote, SGAP_VERSION, '>');
    }

    /** Adds this plugin to WordPress' update list when GitHub has a newer version. */
    public static function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }
        $remote = self::remote_version();
        $item = (object) [
            'id' => self::REPO,
            'slug' => self::SLUG,
            'plugin' => self::basename(),
            'new_version' => $remote !== '' ? $remote : SGAP_VERSION,
            'url' => self::REPO,
            'package' => $remote !== '' ? self::package_url($remote) : '',
            'requires' => '6.4',
            'requires_php' => '8.0'
        ];
        if ($remote !== '' && version_compare($remote, SGAP_VERSION, '>')) {
            $transient->response[self::basename()] = $item;
        } else {
            unset($transient->response[self::basename()]);
            $transient->no_update[self::basename()] = $item; // lets the auto-update toggle show
        }
        return $transient;
    }

    /** "View details" popup on the Plugins page. */
    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }
        $remote = self::remote_version() ?: SGAP_VERSION;
        $notes = self::remote_changelog() ?: 'Release notes live in the <a href="' . esc_url(self::REPO . '/blob/main/ROADMAP.md') . '">roadmap</a>.';
        return (object) [
            'name' => 'Steamgears Autopilot',
            'slug' => self::SLUG,
            'version' => $remote,
            'author' => 'Steamgears.eu',
            'homepage' => self::REPO,
            'requires' => '6.4',
            'requires_php' => '8.0',
            'download_link' => self::package_url($remote),
            'sections' => [
                'description' => 'Social media autopilot for steamgears.eu: AI drafts, publishing via Publer, click tracking and results.',
                'changelog' => $notes
            ]
        ];
    }

    /**
     * Installs the latest version from GitHub over this one. The plugin stays active
     * (same folder name). Returns what happened; never throws.
     * @return array{updated: bool, from: string, to: string, message: string}
     */
    public static function run(): array {
        $report = ['updated' => false, 'from' => SGAP_VERSION, 'to' => SGAP_VERSION, 'message' => ''];
        if (!current_user_can('update_plugins')) {
            $report['message'] = 'You are not allowed to update plugins.';
            return $report;
        }
        $remote = self::remote_version(true);
        if ($remote === '') {
            $report['message'] = 'Could not read the latest version from GitHub.';
            return $report;
        }
        $report['to'] = $remote;
        if (!version_compare($remote, SGAP_VERSION, '>')) {
            $report['message'] = 'Already up to date (' . SGAP_VERSION . ').';
            return $report;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // Refresh WordPress' update list so Plugin_Upgrader finds the GitHub package.
        delete_site_transient('update_plugins');
        wp_update_plugins();
        $was_active = is_plugin_active(self::basename());
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->upgrade(self::basename());
        if ($was_active && !is_plugin_active(self::basename())) {
            activate_plugin(self::basename(), '', is_multisite() && is_plugin_active_for_network(self::basename()), true);
        }
        if ($result === true) {
            delete_site_transient(self::NOTES_CACHE);
            $report['updated'] = true;
            $report['message'] = "Updated from {$report['from']} to {$remote}.";
        } else {
            $errors = is_wp_error($result) ? $result->get_error_messages() : $skin->get_error_messages();
            $report['message'] = 'Update failed: ' . ($errors ? implode(' ', $errors) : 'unknown error');
        }
        return $report;
    }

    public static function admin_update(): void {
        if (!current_user_can('update_plugins')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer('sgap_self_update');
        $r = self::run();
        delete_transient('sgap_dashboard_stats');
        set_transient('sgap_run_result_' . get_current_user_id(), $r['message'], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=steamgears-autopilot'));
        exit;
    }

    /**
     * Fresh version check for the dashboard card — a release must never hide behind the
     * 6-hour cache in remote_version(). At most one GitHub request per minute, and when
     * GitHub has something newer than the installed version, WordPress' own update list
     * is invalidated so the Plugins screen offers the same version straight away.
     */
    public static function refresh_for_admin(): string {
        $version = get_site_transient(self::ADMIN_CACHE);
        if (!is_string($version) || $version === '') {
            $version = self::remote_version(true);
            if ($version !== '') {
                set_site_transient(self::ADMIN_CACHE, $version, MINUTE_IN_SECONDS);
            }
        }
        if ($version !== '' && version_compare($version, SGAP_VERSION, '>')) {
            // Without this the Plugins screen can keep saying "no update" for up to 12 hours.
            delete_site_transient('update_plugins');
        }
        return $version;
    }

    /** Dashboard card: installed vs. GitHub, with the button. */
    public static function panel(): void {
        $remote = self::refresh_for_admin();
        $newer = $remote !== '' && version_compare($remote, SGAP_VERSION, '>');
        SGAP_UI::card_open('Plugin update', 'update');
        echo '<p>' . SGAP_UI::pill('Installed ' . SGAP_VERSION) . ' ' . SGAP_UI::pill('GitHub ' . ($remote !== '' ? $remote : 'unknown'), $newer ? 'warn' : 'ok')
            . ' ' . ($newer ? '<strong>Update available</strong>' : '<span class="description">Up to date</span>') . '</p>'
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="sgap_self_update">'
            . wp_nonce_field('sgap_self_update', '_wpnonce', true, false)
            . '<button class="button' . ($newer ? ' button-primary' : '') . '"><span class="dashicons dashicons-update"></span>' . ($newer ? 'Update now' : 'Check again and update') . '</button></form>';
        SGAP_UI::card_close();
    }
}
