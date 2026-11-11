<?php
/**
 * The Cirla page: the module's own screen in the Steamgears menu.
 *
 * Loaded together with the module (so only while `sgap_cirla_enabled` is `yes`) and only in
 * wp-admin. The four buttons call exactly the functions the four abilities call — the page is a
 * form around SGAP_Cirla::status()/draft()/seo_check()/social_suggestions(), with a nonce and the
 * module's own capability. That way clicking here and calling over MCP cannot drift apart.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Cirla_Admin {
    const SLUG = 'sgap-cirla';
    const ACTION = 'sgap_cirla_run';
    const FLASH = 'sgap_cirla_flash_';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle']);
    }

    public static function menu(): void {
        add_submenu_page(
            'steamgears-autopilot',
            'Cirla',
            'Cirla',
            'manage_options',
            self::SLUG,
            [__CLASS__, 'page'],
            22
        );
    }

    /** action => label, the ability's own input fields, and whether the action writes anything. */
    public static function forms(): array {
        return [
            'status' => ['label' => 'Read the status', 'fields' => [], 'readonly' => true],
            'draft' => ['label' => 'Write one draft', 'fields' => ['topic', 'channel'], 'readonly' => false],
            'seo' => ['label' => 'Run the SEO check', 'fields' => ['post_id'], 'readonly' => true],
            'social' => ['label' => 'Suggest social posts', 'fields' => ['post_id', 'count'], 'readonly' => true]
        ];
    }

    /** Which of the four actions a POST asks for, or '' when it asks for something else. */
    public static function requested(array $post): string {
        $action = sanitize_key((string) ($post['cirla_action'] ?? ''));
        return isset(self::forms()[$action]) ? $action : '';
    }

    /** The posted fields, turned into the input the ability expects. */
    public static function input(string $action, array $post): array {
        $input = [];
        foreach (self::forms()[$action]['fields'] ?? [] as $field) {
            $raw = $post['cirla_' . $field] ?? '';
            if ($field === 'topic') {
                $input['topic'] = sanitize_text_field((string) $raw);
            } elseif ($field === 'channel') {
                $input['channel'] = sanitize_key((string) $raw);
            } elseif ($field === 'post_id') {
                $input['post_id'] = max(0, (int) $raw);
            } elseif ($field === 'count') {
                $input['count'] = min(5, max(1, (int) $raw));
            }
        }
        return $input;
    }

    /** The rows the page prints for a result. Pure, so a test can read them without a browser. */
    public static function result_rows(string $action, array $result): array {
        $yes = static fn($value) => $value ? 'yes' : 'no';
        if ($action === 'draft') {
            return [
                ['Draft', '#' . (int) ($result['id'] ?? 0)],
                ['Duplicate', $yes((bool) ($result['duplicate'] ?? false))],
                ['Channel', (string) ($result['channel'] ?? '')],
                ['Title', (string) ($result['title'] ?? '')],
                ['Queued', $yes((bool) ($result['queued'] ?? false))],
                ['Published', $yes((bool) ($result['published'] ?? true))]
            ];
        }
        if ($action === 'seo') {
            $problems = array_map('strval', (array) ($result['problems'] ?? []));
            return [
                ['Page', '#' . (int) ($result['post_id'] ?? 0)],
                ['Title', (string) ($result['title'] ?? '') . ' — ' . (int) ($result['title_length'] ?? 0)
                    . ' of ' . (int) ($result['title_max'] ?? 0) . ' characters'],
                ['Description', (int) ($result['description_length'] ?? 0) . ' characters ('
                    . (int) ($result['description_min'] ?? 0) . '–' . (int) ($result['description_max'] ?? 0) . ')'],
                ['Problems', $problems === [] ? 'none' : implode('; ', $problems)]
            ];
        }
        if ($action === 'social') {
            $suggestions = (array) ($result['suggestions'] ?? []);
            return [
                ['Page', '#' . (int) ($result['post_id'] ?? 0)],
                ['Suggestions', (string) count($suggestions)],
                ['Saved', 'nothing — hand one to the queue yourself']
            ];
        }
        $calls = (array) ($result['ai_calls_today'] ?? []);
        return [
            ['State', !empty($result['enabled']) ? 'on' : 'off'],
            ['Actions', implode(', ', array_map('strval', (array) ($result['actions'] ?? [])))],
            ['Daily job', (string) ($result['cron']['next'] ?? 'not scheduled')],
            ['AI calls today', (int) ($calls['used'] ?? 0) . ' of ' . (int) ($calls['limit'] ?? 0)],
            ['Pending Cirla drafts', (string) (int) ($result['pending_drafts'] ?? 0)],
            ['Queues or publishes', !empty($result['queues_or_publishes']) ? 'yes' : 'no']
        ];
    }

    /** The form handler: nonce, capability, one of the four actions, then back to the page. */
    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to run Cirla. It needs manage_options.');
        }
        check_admin_referer(self::ACTION);
        $action = self::requested($_POST);
        if ($action === '') {
            self::flash(['error' => 'That is not one of the four Cirla actions.']);
            self::back();
        }
        $input = self::input($action, $_POST);
        if ($action === 'draft') {
            $result = SGAP_Cirla::draft($input);
        } elseif ($action === 'seo') {
            $result = SGAP_Cirla::seo_check($input);
        } elseif ($action === 'social') {
            $result = SGAP_Cirla::social_suggestions($input);
        } else {
            $result = SGAP_Cirla::status();
        }
        if (is_wp_error($result)) {
            self::flash([
                'action' => $action,
                'error' => $result->get_error_message(),
                'code' => $result->get_error_code()
            ]);
        } else {
            self::flash(['action' => $action, 'result' => (array) $result]);
        }
        self::back();
    }

    /** One result per user, read once: a reload must not run the action again. */
    private static function flash(array $payload): void {
        set_transient(self::FLASH . get_current_user_id(), $payload, 60);
    }

    private static function back(): void {
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to see this page.');
        }
        $flash = get_transient(self::FLASH . get_current_user_id());
        if ($flash) {
            delete_transient(self::FLASH . get_current_user_id());
        }
        $status = SGAP_Cirla::status();
        $settings = admin_url('admin.php?page=sgap-settings') . '#tab-cirla';
        $channels = class_exists('SGAP_Publer') ? SGAP_Publer::CHANNELS : ['facebook'];

        echo '<div class="wrap sgap-wrap">';
        echo '<h1>Cirla</h1>';
        echo '<p class="description">Cirla is a module of its own: four safe actions, a daily AI budget and a draft queue that only a human empties. Everything on this page calls the same code the MCP abilities call.</p>';

        echo '<div class="sgap-grid">';
        foreach (self::result_rows('status', $status) as [$label, $value]) {
            echo '<div class="sgap-card"><h3>' . esc_html((string) $label) . '</h3><p class="sgap-metric">'
                . esc_html((string) $value) . '</p></div>';
        }
        echo '</div>';

        if (is_array($flash) && isset($flash['error'])) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html((string) ($flash['code'] ?? 'error'))
                . ':</strong> ' . esc_html((string) $flash['error']) . '</p></div>';
        } elseif (is_array($flash) && isset($flash['result'])) {
            echo '<div class="sgap-card"><h2>Result: ' . esc_html((string) ($flash['action'] ?? '')) . '</h2>';
            echo '<table class="widefat striped"><tbody>';
            foreach (self::result_rows((string) ($flash['action'] ?? ''), (array) $flash['result']) as [$label, $value]) {
                echo '<tr><th scope="row">' . esc_html((string) $label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '<div class="sgap-card"><h2>The four actions</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Action</th><th>Input</th><th></th></tr></thead><tbody>';
        foreach (self::forms() as $action => $form) {
            echo '<tr><td><strong>' . esc_html($form['label']) . '</strong><br><span class="description">'
                . esc_html($action) . ($form['readonly'] ? ' · reads only' : ' · writes one pending draft') . '</span></td>';
            echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
            echo '<input type="hidden" name="cirla_action" value="' . esc_attr($action) . '">';
            wp_nonce_field(self::ACTION);
            if (in_array('topic', $form['fields'], true)) {
                echo '<input type="text" name="cirla_topic" class="regular-text" required placeholder="Topic for the draft"> ';
            }
            if (in_array('channel', $form['fields'], true)) {
                echo '<select name="cirla_channel">';
                foreach ((array) $channels as $channel) {
                    echo '<option value="' . esc_attr((string) $channel) . '">' . esc_html((string) $channel) . '</option>';
                }
                echo '</select> ';
            }
            if (in_array('post_id', $form['fields'], true)) {
                echo '<input type="number" name="cirla_post_id" min="1" step="1" required placeholder="Post id"> ';
            }
            if (in_array('count', $form['fields'], true)) {
                echo '<select name="cirla_count">';
                foreach ([1, 2, 3, 4, 5] as $count) {
                    echo '<option value="' . esc_attr((string) $count) . '"' . selected(3, $count, false) . '>'
                        . esc_html((string) $count) . '</option>';
                }
                echo '</select> ';
            }
            echo '<button type="submit" class="button button-primary">Run</button>';
            echo '</form></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><a class="button" href="' . esc_url($settings) . '">Open the Cirla settings</a> '
            . '<span class="description">The AI budget, the allowed actions and the log level live under Settings &amp; keys → Cirla.</span></p></div>';

        echo '<div class="sgap-card"><h2>Log</h2>';
        $entries = SGAP_Cirla::entries(20);
        if ($entries === []) {
            echo '<p>Nothing yet. The module writes one row per action, and errors also go to Troubleshooting.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>When</th><th>Action</th><th>Result</th></tr></thead><tbody>';
            foreach ($entries as $entry) {
                echo '<tr><td>' . esc_html((string) ($entry['at'] ?? '')) . '</td><td>'
                    . esc_html((string) ($entry['action'] ?? '')) . '</td><td>'
                    . esc_html((string) ($entry['result'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        echo '</div>';
    }
}
