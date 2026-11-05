<?php
/**
 * Publish audit log (P0.5) — one row per publish attempt.
 *
 * The queue's `error` column only keeps the last message, so "why did this not
 * post?" could not be answered after a retry overwrote it, and a successful
 * hand-off to Publer left no trace at all. Every attempt is now written here:
 * when it happened, which channel, what came back (HTTP status and an excerpt
 * of the response), and who started it (cron, admin, REST or WP-CLI).
 *
 * Rows are pruned by SGAP_Maintenance::daily(), keeping `audit_keep_days` days.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Audit {
    const TABLE = 'sgap_publish_log';
    const KEEP_DAYS = 90;
    const MAX_MESSAGE = 500;

    /** Outcome keys used in the table and the filter row. */
    const OUTCOMES = [
        'sent' => 'Handed to Publer',
        'rss' => 'Released in feed',
        'published' => 'Published',
        'requeued' => 'Requeued for retry',
        'failed' => 'Failed',
        'reminded' => 'Reminded by mail',
        'blocked' => 'Blocked'
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('admin_post_sgap_log_clear', [__CLASS__, 'clear']);
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Who caused this attempt. Cron is the normal case; anything else is worth
     * seeing in the log because it means a human or an external tool intervened.
     */
    public static function actor(): string {
        if (defined('WP_CLI') && WP_CLI) {
            return 'wp-cli';
        }
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return 'cron';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'rest';
        }
        return is_admin() ? 'admin' : 'web';
    }

    /** Response excerpt: one line, tags stripped, capped so a stack trace cannot bloat the table. */
    public static function excerpt(string $message): string {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($message)));
        return mb_substr($text, 0, self::MAX_MESSAGE);
    }

    /** HTTP status carried by a WP_Error from the Publer client, or 0 when the failure was local. */
    public static function status_of($error): int {
        // WP_Error has no public $data: read it through get_error_data() (the real class warns otherwise).
        $data = is_wp_error($error) ? $error->get_error_data() : null;
        if (is_array($data) && isset($data['status'])) {
            return self::clamp_status((int) $data['status']);
        }
        return 0;
    }

    public static function clamp_status(int $status): int {
        return $status < 0 || $status > 599 ? 0 : $status;
    }

    /** Writes one row. Never throws: a broken log must not stop publishing. */
    public static function record(int $queue_id, string $channel, string $outcome, int $status = 0, string $message = ''): void {
        global $wpdb;
        $outcome = isset(self::OUTCOMES[$outcome]) ? $outcome : 'failed';
        $wpdb->insert(self::table(), [
            'queue_id' => max(0, $queue_id),
            'channel' => mb_substr($channel, 0, 40),
            'outcome' => $outcome,
            'http_status' => self::clamp_status($status),
            'actor' => self::actor(),
            'message' => self::excerpt($message),
            'created_at' => current_time('mysql')
        ]);
    }

    /** Convenience wrapper for a WP_Error: records the HTTP status and the message together. */
    public static function record_error(int $queue_id, string $channel, $error, string $outcome = 'failed'): void {
        $message = is_wp_error($error) ? $error->get_error_message() : (string) $error;
        self::record($queue_id, $channel, $outcome, self::status_of($error), $message);
    }

    public static function keep_days(): int {
        return max(0, (int) SGAP_Settings::get('audit_keep_days', self::KEEP_DAYS));
    }

    /** Deletes rows older than the retention window in small batches. Returns how many rows went. */
    public static function prune(): int {
        global $wpdb;
        $days = self::keep_days();
        if ($days < 1) {
            return 0; // 0 = keep everything
        }
        $cutoff = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $deleted = 0;
        for ($i = 0; $i < 20; $i++) {
            $rows = (int) $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . self::table() . ' WHERE created_at < %s LIMIT 5000',
                $cutoff
            ));
            $deleted += $rows;
            if ($rows < 5000) {
                break;
            }
        }
        return $deleted;
    }

    /** Builds the WHERE clause shared by rows() and total(). */
    private static function where(string $outcome, string $channel): string {
        global $wpdb;
        $parts = [];
        if ($outcome !== '' && isset(self::OUTCOMES[$outcome])) {
            $parts[] = $wpdb->prepare('outcome = %s', $outcome);
        }
        if ($channel !== '') {
            $parts[] = $wpdb->prepare('channel = %s', $channel);
        }
        return $parts ? ' WHERE ' . implode(' AND ', $parts) : '';
    }

    /** @return object[] Newest first. */
    public static function rows(string $outcome = '', string $channel = '', int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        return (array) $wpdb->get_results(
            'SELECT * FROM ' . self::table() . self::where($outcome, $channel)
            . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}"
        );
    }

    public static function total(string $outcome = '', string $channel = ''): int {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table() . self::where($outcome, $channel));
    }

    public static function page_url(array $args = []): string {
        $base = admin_url('admin.php?page=sgap-log');
        return $args ? $base . '&' . http_build_query($args, '', '&', PHP_QUERY_RFC3986) : $base;
    }

    public static function menu(): void {
        add_submenu_page(
            'steamgears-autopilot',
            'Publish log',
            'Publish log',
            'manage_options',
            'sgap-log',
            [__CLASS__, 'page']
        );
    }

    public static function page(): void {
        global $wpdb;
        $outcome = isset($_GET['outcome']) ? sanitize_key((string) $_GET['outcome']) : '';
        $channel = isset($_GET['channel']) ? sanitize_key((string) $_GET['channel']) : '';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 50;

        SGAP_Admin::open('Publish log', 'Every publish attempt: what was sent, what came back, and who started it.');
        echo '<div class="notice notice-info inline"><p>Kept for ' . esc_html((string) self::keep_days()) . ' days (Settings → Alerts and log). '
            . 'The Queue page shows the current state; this page shows the history behind it.</p></div>';

        if (!SGAP_Admin::table_exists(self::table())) {
            SGAP_UI::empty_state('warning', 'The log table is missing: deactivate and reactivate the plugin.');
            SGAP_Admin::close();
            return;
        }

        $filters = '';
        foreach (['' => 'All'] + self::OUTCOMES as $key => $label) {
            $filters .= '<a class="sgap-pill' . ($outcome === $key ? ' on' : '') . '" style="text-decoration:none" href="'
                . esc_url(self::page_url(array_filter(['outcome' => $key, 'channel' => $channel]))) . '">' . esc_html($label) . '</a>';
        }
        echo '<div class="sgap-pills">' . $filters . '</div>';

        $total = self::total($outcome, $channel);
        $rows = self::rows($outcome, $channel, $per_page, ($paged - 1) * $per_page);
        if (!$rows && $paged === 1 && $outcome === '' && $channel === '') {
            SGAP_UI::empty_state('list-view', 'Nothing has been published yet. The first attempt shows up here.');
            SGAP_Admin::close();
            return;
        }
        if (!$rows) {
            SGAP_UI::empty_state('search', 'No attempts match this filter.');
        }

        echo '<div class="sgap-scroll"><table class="widefat striped sgap-qtable"><thead><tr>'
            . '<th>When</th><th>Channel</th><th>Outcome</th><th>HTTP</th><th>Actor</th><th>Response</th><th>Queue</th>'
            . '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $kind = ['published' => 'ok', 'sent' => 'ok', 'rss' => 'ok', 'reminded' => 'brass', 'requeued' => 'warn', 'failed' => 'bad', 'blocked' => 'warn'][$row->outcome] ?? '';
            echo '<tr>'
                . '<td data-label="When">' . esc_html($row->created_at) . '</td>'
                . '<td data-label="Channel">' . SGAP_UI::channel((string) $row->channel) . '</td>'
                . '<td data-label="Outcome">' . SGAP_UI::pill(self::OUTCOMES[$row->outcome] ?? $row->outcome, $kind) . '</td>'
                . '<td data-label="HTTP">' . ((int) $row->http_status > 0 ? esc_html((string) $row->http_status) : '—') . '</td>'
                . '<td data-label="Actor">' . esc_html($row->actor) . '</td>'
                . '<td data-label="Response">' . esc_html($row->message !== '' ? $row->message : '—') . '</td>'
                . '<td data-label="Queue">' . ((int) $row->queue_id > 0 ? '<a href="' . esc_url(admin_url('admin.php?page=sgap-queue')) . '">#' . (int) $row->queue_id . '</a>' : '—') . '</td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';

        $pages = (int) ceil($total / $per_page);
        if ($pages > 1) {
            echo '<p class="description">Page ' . (int) $paged . ' of ' . $pages . ' · ' . (int) $total . ' attempts</p><p>';
            for ($i = 1; $i <= min($pages, 20); $i++) {
                echo '<a class="button button-small' . ($i === $paged ? ' button-primary' : '') . '" style="margin-right:4px" href="'
                    . esc_url(self::page_url(array_filter(['outcome' => $outcome, 'channel' => $channel, 'paged' => $i > 1 ? $i : '']))) . '">' . $i . '</a>';
            }
            echo '</p>';
        }

        if ($total > 0) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px">'
                . '<input type="hidden" name="action" value="sgap_log_clear">';
            wp_nonce_field('sgap_log_clear');
            submit_button('Clear the log', 'delete small', 'submit', false);
            echo '</form>';
        }
        SGAP_Admin::close();
    }

    /** Empties the log. The queue keeps its own state, so nothing is lost but history. */
    public static function clear(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('sgap_log_clear');
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . self::table());
        SGAP_Admin::done('The publish log was cleared.', 'sgap-log');
    }
}
