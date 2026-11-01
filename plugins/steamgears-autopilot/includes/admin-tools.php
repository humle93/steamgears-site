<?php
/**
 * Site administration tools for Claude, exposed as WordPress abilities next to the
 * autopilot's own (same category, administrators only): plugin settings, drafts and
 * queue editing, the PHP error log, cache purge, plugin management, cron, site health,
 * and switching on AI Engine's full REST access for the MCP connection.
 * Loaded from steamgears-autopilot.php; does nothing without the Abilities API.
 */

if (!defined('ABSPATH')) exit;

class SGAP_AdminTools {
    /** Plugins that carry Claude's own connection; deactivating them would cut it off. */
    const LIFELINES = ['steamgears-autopilot/steamgears-autopilot.php', 'ai-engine/ai-engine.php'];

    public static function init(): void {
        add_action('wp_abilities_api_init', [__CLASS__, 'register']);
    }

    /** name => [label, description, properties, required, callback, readonly, idempotent, destructive] */
    private static function definitions(): array {
        return [
            'get-settings' => [
                'Read autopilot settings',
                'All Steamgears Autopilot settings with their current values, grouped as on the Settings page. Secret keys are reported only as set/not set.',
                [], [], [__CLASS__, 'get_settings'], true, true, false
            ],
            'update-settings' => [
                'Change autopilot settings',
                'Changes one or more Steamgears Autopilot settings, e.g. {"quiet_start": 0, "channels": "facebook,instagram"}. Keys are those from read-settings; yes/no switches take "yes"/"no". Values are validated like the Settings page. Saving an API key, provider or model re-tests the keys.',
                ['settings' => ['type' => 'object', 'description' => 'Setting key => new value.', 'minProperties' => 1]],
                ['settings'], [__CLASS__, 'update_settings'], false, true, false
            ],
            'edit-draft' => [
                'Edit an AI draft',
                'Rewrites the title, text and/or hashtags of an AI draft that is not used yet, before you approve it.',
                [
                    'draft_id' => ['type' => 'integer', 'minimum' => 1],
                    'title' => ['type' => 'string', 'maxLength' => 200],
                    'text' => ['type' => 'string'],
                    'hashtags' => ['type' => 'string', 'description' => 'Space-separated, each starting with #.']
                ], ['draft_id'], [__CLASS__, 'edit_draft'], false, true, false
            ],
            'manage-queue' => [
                'Retry, reschedule or delete a queued post',
                'retry: back to queued with attempts reset. reschedule: set scheduled_at (site time, e.g. 2026-11-02 18:00). delete: remove the post from the queue for good.',
                [
                    'queue_id' => ['type' => 'integer', 'minimum' => 1],
                    'action' => ['type' => 'string', 'enum' => ['retry', 'reschedule', 'delete']],
                    'scheduled_at' => ['type' => 'string']
                ], ['queue_id', 'action'], [__CLASS__, 'manage_queue'], false, false, true
            ],
            'error-log' => [
                'Read the PHP error log',
                'The last lines of the site\'s PHP error log (wp-content/debug.log when WP_DEBUG_LOG is on, else PHP\'s error_log), optionally only lines containing a text.',
                [
                    'lines' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100],
                    'contains' => ['type' => 'string', 'description' => 'Only lines containing this text (case-insensitive), e.g. "Fatal" or "steamgears".']
                ], [], [__CLASS__, 'error_log'], true, true, false
            ],
            'purge-cache' => [
                'Purge the page cache',
                'Empties the page/object cache (W3 Total Cache, WP Rocket, LiteSpeed, WordPress object cache). scope "url" purges one address only.',
                [
                    'scope' => ['type' => 'string', 'enum' => ['all', 'url'], 'default' => 'all'],
                    'url' => ['type' => 'string', 'format' => 'uri']
                ], [], [__CLASS__, 'purge_cache'], false, true, false
            ],
            'list-plugins' => [
                'List plugins',
                'Every installed plugin with file, version, active state and available update.',
                [], [], [__CLASS__, 'list_plugins'], true, true, false
            ],
            'manage-plugin' => [
                'Install, activate, deactivate or update a plugin',
                'install: from wordpress.org by slug (e.g. "query-monitor"), optionally activating it. activate / deactivate / update: by plugin file from list-plugins or by slug. The Steamgears Autopilot and AI Engine plugins cannot be deactivated here, since they carry this connection.',
                [
                    'action' => ['type' => 'string', 'enum' => ['install', 'activate', 'deactivate', 'update']],
                    'plugin' => ['type' => 'string', 'minLength' => 1, 'description' => 'Slug (query-monitor) or plugin file (query-monitor/query-monitor.php).'],
                    'activate' => ['type' => 'boolean', 'default' => false, 'description' => 'install only: activate afterwards.']
                ], ['action', 'plugin'], [__CLASS__, 'manage_plugin'], false, false, true
            ],
            'run-cron' => [
                'Run a scheduled task now',
                'Lists WP-Cron events (no hook given), or runs the next scheduled event of a hook right now, e.g. sgap_publish_tick or sgap_generate.',
                ['hook' => ['type' => 'string']],
                [], [__CLASS__, 'run_cron'], false, false, false
            ],
            'site-health' => [
                'Site health',
                'WordPress, PHP and database versions, memory limit, debug settings, WP-Cron state and overdue events, active cache plugin, error log size, free disk space and the MCP access level of AI Engine.',
                [], [], [__CLASS__, 'site_health'], true, true, false
            ],
            'enable-full-access' => [
                'Turn on full REST access for Claude',
                'Switches on AI Engine\'s "Dynamic REST" MCP module (and Abilities), which exposes the whole WordPress REST API (posts, pages, media, users, settings, plugins, WooCommerce, ...) as MCP tools. Takes effect when the MCP connection is reopened. enabled=false turns it off again.',
                ['enabled' => ['type' => 'boolean', 'default' => true]],
                [], [__CLASS__, 'enable_full_access'], false, true, false
            ]
        ];
    }

    public static function register(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        foreach (self::definitions() as $name => [$label, $description, $properties, $required, $execute, $readonly, $idempotent, $destructive]) {
            $args = [
                'label' => $label,
                'description' => $description,
                'category' => SGAP_Abilities::CATEGORY,
                'output_schema' => ['type' => 'object'],
                'execute_callback' => $execute,
                'permission_callback' => [SGAP_Abilities::class, 'can_manage'],
                'meta' => SGAP_Abilities::meta($readonly, $idempotent, $destructive)
            ];
            if ($properties) {
                $args['input_schema'] = SGAP_Abilities::object($properties, $required);
            }
            wp_register_ability(SGAP_Abilities::CATEGORY . '/' . $name, $args);
        }
    }

    /* ---- settings ---- */

    /** Every known setting key => its kind. */
    public static function setting_kinds(): array {
        $kinds = [];
        foreach (SGAP_Settings::BOOL_KEYS as $k) $kinds[$k] = 'yes/no';
        foreach (SGAP_Settings::HOUR_KEYS as $k) $kinds[$k] = 'hour 0-23';
        foreach (SGAP_Settings::INT_KEYS as $k) $kinds[$k] = 'number';
        foreach (SGAP_Settings::TEXT_KEYS as $k) $kinds[$k] = 'text';
        foreach (SGAP_Settings::CHOICE_KEYS as $k => $choices) $kinds[$k] = 'one of: ' . implode(', ', array_keys($choices));
        foreach (SGAP_Settings::SECRET_KEYS as $k) $kinds[$k] = 'secret';
        return $kinds;
    }

    public static function get_settings($input = null): array {
        $kinds = self::setting_kinds();
        $out = [];
        $shown = [];
        foreach (SGAP_Settings::SECTIONS as $section => $keys) {
            foreach ($keys as $key) {
                $out[$section][$key] = self::describe($key, $kinds[$key] ?? 'text');
                $shown[] = $key;
            }
        }
        foreach (array_diff(array_keys($kinds), $shown) as $key) {
            $out['Other (not on the Settings page)'][$key] = self::describe($key, $kinds[$key]);
        }
        return $out;
    }

    private static function describe(string $key, string $kind): array {
        $value = SGAP_Settings::get($key);
        if ($kind === 'secret') {
            $value = $value !== '' ? 'set' . (SGAP_Settings::constant_name($key) ? ' (in wp-config.php)' : '') : 'not set';
        }
        return ['value' => $value, 'kind' => $kind] + (isset(SGAP_Settings::HELP[$key]) ? ['help' => SGAP_Settings::HELP[$key]] : []);
    }

    /** @return array|WP_Error */
    public static function update_settings($input) {
        $kinds = self::setting_kinds();
        $wanted = (array) ($input['settings'] ?? []);
        $unknown = array_values(array_diff(array_keys($wanted), array_keys($kinds)));
        if ($unknown) {
            return new WP_Error('sgap_unknown_setting', 'Unknown setting(s): ' . implode(', ', $unknown) . '. Use read-settings for the list.');
        }
        self::ensure_registered();
        $changed = [];
        foreach ($wanted as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }
            $before = get_option('sgap_' . $key);
            update_option('sgap_' . $key, is_scalar($value) ? (string) $value : '');
            $after = get_option('sgap_' . $key);
            if ($after !== $before) {
                $changed[$key] = $kinds[$key] === 'secret' ? 'updated' : $after;
            }
        }
        if (array_intersect(array_keys($changed), SGAP_KeyCheck::WATCH)) {
            $changed['_key_test'] = SGAP_KeyCheck::run();
        }
        return ['changed' => $changed ?: 'nothing (values were already set)'];
    }

    /** The Settings page registers its sanitizers on admin_init; tools run outside wp-admin. */
    private static function ensure_registered(): void {
        global $wp_registered_settings;
        if (!isset($wp_registered_settings['sgap_autopilot_enabled'])) {
            SGAP_Settings::register();
        }
    }

    /* ---- drafts and queue ---- */

    /** @return array|WP_Error */
    public static function edit_draft($input) {
        global $wpdb;
        $table = $wpdb->prefix . 'sgap_ai_drafts';
        $id = (int) $input['draft_id'];
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $id));
        if ($status === null) {
            return new WP_Error('sgap_no_draft', "Draft #{$id} does not exist.");
        }
        if ($status === 'used') {
            return new WP_Error('sgap_draft_used', "Draft #{$id} is already in the queue; edit the queued post instead.");
        }
        $data = [];
        if (isset($input['title'])) $data['title'] = sanitize_text_field($input['title']);
        if (isset($input['text'])) $data['body'] = sanitize_textarea_field($input['text']);
        if (isset($input['hashtags'])) $data['hashtags'] = sanitize_text_field($input['hashtags']);
        if (!$data) {
            return new WP_Error('sgap_nothing', 'Give a title, text or hashtags to change.');
        }
        $wpdb->update($table, $data, ['id' => $id]);
        return ['draft_id' => $id, 'updated' => array_keys($data)];
    }

    /** @return array|WP_Error */
    public static function manage_queue($input) {
        global $wpdb;
        $table = $wpdb->prefix . 'sgap_queue';
        $id = (int) $input['queue_id'];
        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $id))) {
            return new WP_Error('sgap_no_post', "Queue post #{$id} does not exist.");
        }
        delete_transient('sgap_dashboard_stats');
        switch ($input['action']) {
            case 'retry':
                $wpdb->update($table, ['status' => 'queued', 'attempts' => 0, 'error' => null, 'external_id' => ''], ['id' => $id]);
                $blocked = SGAP_Queue::blocked_reason();
                return ['queue_id' => $id, 'status' => 'queued', 'waits_because' => $blocked !== '' ? SGAP_Queue::blocked_message($blocked) : null];
            case 'reschedule':
                // Site time, like the rest of the queue (compared with current_time('mysql')).
                $local = (string) ($input['scheduled_at'] ?? '');
                $parsed = $local !== '' ? date_create($local, wp_timezone()) : false;
                if (!$parsed) {
                    return new WP_Error('sgap_bad_time', 'Give scheduled_at as a date and time, e.g. 2026-11-02 18:00.');
                }
                $when = $parsed->format('Y-m-d H:i:s');
                $wpdb->update($table, ['scheduled_at' => $when, 'status' => 'queued'], ['id' => $id]);
                return ['queue_id' => $id, 'scheduled_at' => $when];
            default:
                $wpdb->delete($table, ['id' => $id]);
                return ['queue_id' => $id, 'deleted' => true];
        }
    }

    /* ---- error log ---- */

    public static function log_path(): string {
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '') {
            return WP_DEBUG_LOG;
        }
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            return WP_CONTENT_DIR . '/debug.log';
        }
        $ini = (string) ini_get('error_log');
        return $ini !== '' ? $ini : WP_CONTENT_DIR . '/debug.log';
    }

    /** Last lines of a file without reading all of it. */
    public static function tail(string $path, int $lines, string $contains = ''): array {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return [];
        }
        $size = filesize($path);
        $chunk = 65536;
        $buffer = '';
        $pos = $size;
        $found = [];
        while ($pos > 0 && count($found) <= $lines) {
            $read = min($chunk, $pos);
            $pos -= $read;
            fseek($fh, $pos);
            $buffer = fread($fh, $read) . $buffer;
            $all = explode("\n", $buffer);
            $buffer = $pos > 0 ? array_shift($all) : '';
            $found = array_merge(array_values(array_filter($all, fn($l) => trim($l) !== '' && ($contains === '' || stripos($l, $contains) !== false))), $found);
            if ($pos <= max(0, $size - 20 * $chunk)) {
                break; // never scan more than ~1.3 MB
            }
        }
        fclose($fh);
        return array_slice($found, -$lines);
    }

    public static function error_log($input = null): array {
        $path = self::log_path();
        $lines = (int) ($input['lines'] ?? 100);
        $contains = (string) ($input['contains'] ?? '');
        if (!is_readable($path)) {
            return ['log' => $path, 'readable' => false, 'hint' => 'No readable log. Turn on WP_DEBUG and WP_DEBUG_LOG in wp-config.php to collect one.', 'lines' => []];
        }
        return [
            'log' => $path,
            'size_kb' => (int) round(filesize($path) / 1024),
            'modified' => wp_date('Y-m-d H:i:s', (int) filemtime($path)),
            'lines' => self::tail($path, max(1, min(500, $lines)), $contains)
        ];
    }

    /* ---- cache ---- */

    public static function purge_cache($input = null): array {
        $done = [];
        if (($input['scope'] ?? 'all') === 'url') {
            $url = esc_url_raw((string) ($input['url'] ?? ''));
            if ($url === '') {
                return ['purged' => [], 'error' => 'Give the url to purge.'];
            }
            if (function_exists('w3tc_flush_url')) { w3tc_flush_url($url); $done[] = 'W3 Total Cache (url)'; }
            if (function_exists('rocket_clean_files')) { rocket_clean_files([$url]); $done[] = 'WP Rocket (url)'; }
            do_action('litespeed_purge_url', $url);
            return ['purged' => $done, 'url' => $url];
        }
        if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); $done[] = 'W3 Total Cache'; }
        if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); $done[] = 'WP Rocket'; }
        do_action('litespeed_purge_all');
        wp_cache_flush();
        $done[] = 'WordPress object cache';
        delete_transient('sgap_dashboard_stats');
        return ['purged' => $done];
    }

    /* ---- plugins ---- */

    private static function load_plugin_admin(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    public static function list_plugins($input = null): array {
        self::load_plugin_admin();
        $updates = get_site_transient('update_plugins');
        $out = [];
        foreach (get_plugins() as $file => $data) {
            $out[] = [
                'file' => $file,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'active' => is_plugin_active($file),
                'update_available' => isset($updates->response[$file]) ? $updates->response[$file]->new_version : null
            ];
        }
        return ['plugins' => $out, 'count' => count($out)];
    }

    /** Plugin file for a slug or file, '' when not installed. */
    private static function resolve(string $plugin): string {
        $plugins = get_plugins();
        if (isset($plugins[$plugin])) {
            return $plugin;
        }
        foreach (array_keys($plugins) as $file) {
            if (strpos($file, $plugin . '/') === 0 || $file === $plugin . '.php') {
                return $file;
            }
        }
        return '';
    }

    /** @return array|WP_Error */
    public static function manage_plugin($input) {
        self::load_plugin_admin();
        $action = (string) $input['action'];
        $plugin = trim((string) $input['plugin']);
        $skin = new WP_Ajax_Upgrader_Skin();
        $errors = fn($result) => is_wp_error($result) ? $result->get_error_message() : implode(' ', $skin->get_error_messages());

        if ($action === 'install') {
            $slug = sanitize_key(strtok($plugin, '/'));
            if (self::resolve($slug) !== '') {
                return new WP_Error('sgap_installed', "{$slug} is already installed; use activate or update.");
            }
            $info = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($info)) {
                return new WP_Error('sgap_not_found', "wordpress.org has no plugin \"{$slug}\": " . $info->get_error_message());
            }
            $result = (new Plugin_Upgrader($skin))->install($info->download_link);
            if ($result !== true) {
                return new WP_Error('sgap_install_failed', 'Install failed: ' . ($errors($result) ?: 'unknown error'));
            }
            wp_clean_plugins_cache();
            $file = self::resolve($slug);
            $report = ['installed' => $file, 'name' => $info->name, 'version' => $info->version];
            if (!empty($input['activate'])) {
                $activated = activate_plugin($file);
                $report['active'] = !is_wp_error($activated);
                if (is_wp_error($activated)) {
                    $report['activate_error'] = $activated->get_error_message();
                }
            }
            return $report;
        }

        $file = self::resolve($plugin);
        if ($file === '') {
            return new WP_Error('sgap_not_installed', "No installed plugin matches \"{$plugin}\". See list-plugins.");
        }
        if ($action === 'activate') {
            $result = activate_plugin($file);
            return is_wp_error($result) ? $result : ['plugin' => $file, 'active' => true];
        }
        if ($action === 'deactivate') {
            if (in_array($file, self::LIFELINES, true)) {
                return new WP_Error('sgap_lifeline', "{$file} carries this connection; deactivate it in wp-admin if you really mean to.");
            }
            deactivate_plugins($file);
            return ['plugin' => $file, 'active' => is_plugin_active($file)];
        }
        // update
        $was_active = is_plugin_active($file);
        delete_site_transient('update_plugins');
        wp_update_plugins();
        $updates = get_site_transient('update_plugins');
        if (!isset($updates->response[$file])) {
            return ['plugin' => $file, 'updated' => false, 'message' => 'Already the newest version.'];
        }
        $before = get_plugins()[$file]['Version'] ?? '';
        $result = (new Plugin_Upgrader($skin))->upgrade($file);
        if ($was_active && !is_plugin_active($file)) {
            activate_plugin($file, '', false, true);
        }
        wp_clean_plugins_cache();
        if ($result !== true) {
            return new WP_Error('sgap_update_failed', 'Update failed: ' . ($errors($result) ?: 'unknown error'));
        }
        return ['plugin' => $file, 'updated' => true, 'from' => $before, 'to' => get_plugins()[$file]['Version'] ?? ''];
    }

    /* ---- cron ---- */

    /** @return array|WP_Error */
    public static function run_cron($input = null) {
        $crons = _get_cron_array() ?: [];
        $hook = trim((string) ($input['hook'] ?? ''));
        if ($hook === '') {
            $events = [];
            foreach ($crons as $time => $hooks) {
                foreach ($hooks as $name => $instances) {
                    foreach ($instances as $instance) {
                        $events[] = ['hook' => $name, 'next_run' => wp_date('Y-m-d H:i:s', (int) $time), 'overdue' => $time < time() - 600, 'schedule' => $instance['schedule'] ?: 'once'];
                    }
                }
            }
            return ['events' => $events, 'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON];
        }
        foreach ($crons as $time => $hooks) {
            if (isset($hooks[$hook])) {
                $instance = reset($hooks[$hook]);
                $start = microtime(true);
                ob_start();
                do_action_ref_array($hook, $instance['args'] ?? []);
                $output = trim((string) ob_get_clean());
                return ['hook' => $hook, 'ran' => true, 'seconds' => round(microtime(true) - $start, 2), 'output' => $output !== '' ? substr($output, 0, 2000) : null];
            }
        }
        return new WP_Error('sgap_no_event', "No scheduled event for \"{$hook}\". Call run-cron without a hook to list them.");
    }

    /* ---- health ---- */

    public static function site_health($input = null): array {
        global $wpdb;
        $overdue = 0;
        foreach ((_get_cron_array() ?: []) as $time => $hooks) {
            if ($time < time() - 600) {
                $overdue += count($hooks);
            }
        }
        $log = self::log_path();
        $mwai = get_option('mwai_options');
        $mcp = is_array($mwai) ? array_filter([
            'abilities' => !empty($mwai['mcp_abilities']),
            'dynamic_rest' => !empty($mwai['mcp_dynamic_rest']),
            'role' => $mwai['mcp_role'] ?? null
        ], fn($v) => $v !== null) : null;
        $sgap_events = [];
        foreach ([SGAP_Install::PUBLISH_CRON, SGAP_Install::GENERATE_CRON, SGAP_Install::CLEANUP_CRON] as $hook) {
            $next = wp_next_scheduled($hook);
            $sgap_events[$hook] = $next ? wp_date('Y-m-d H:i:s', $next) : 'not scheduled';
        }
        $free = function_exists('disk_free_space') ? @disk_free_space(ABSPATH) : false;
        return [
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'database' => $wpdb->db_server_info(),
            'memory_limit' => ini_get('memory_limit'),
            'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : null,
            'debug' => ['WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG, 'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false],
            'error_log' => ['path' => $log, 'size_kb' => is_readable($log) ? (int) round(filesize($log) / 1024) : null],
            'wp_cron' => ['disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'overdue_events' => $overdue, 'autopilot_events' => $sgap_events],
            'page_cache' => function_exists('w3tc_flush_all') ? 'W3 Total Cache' : (function_exists('rocket_clean_domain') ? 'WP Rocket' : (defined('LSCWP_V') ? 'LiteSpeed' : 'none detected')),
            'disk_free_mb' => $free !== false ? (int) round($free / 1048576) : null,
            'ai_engine_mcp' => $mcp,
            'autopilot_version' => SGAP_VERSION
        ];
    }

    /* ---- AI Engine full access ---- */

    /** @return array|WP_Error */
    public static function enable_full_access($input = null) {
        $options = get_option('mwai_options');
        if (!is_array($options)) {
            return new WP_Error('sgap_no_ai_engine', 'AI Engine settings were not found; is AI Engine installed and set up?');
        }
        $enable = !isset($input['enabled']) || (bool) $input['enabled'];
        $before = ['dynamic_rest' => !empty($options['mcp_dynamic_rest']), 'abilities' => !empty($options['mcp_abilities'])];
        $options['mcp_dynamic_rest'] = $enable;
        if ($enable) {
            $options['mcp_abilities'] = true;
        }
        update_option('mwai_options', $options);
        return [
            'before' => $before,
            'now' => ['dynamic_rest' => $enable, 'abilities' => $enable ? true : $before['abilities']],
            'note' => 'Reconnect the MCP client (start a new conversation) to see the new tools.'
        ];
    }
}
