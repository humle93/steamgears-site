<?php
/**
 * Command Center link (one-way): Command Center calls this site's REST API
 * (GET /snapshot, POST /drafts …) with an admin application password and sends
 * an X-Command-Center: <version> header. This file only notices those calls, so
 * the plugin can show "Command Center connected" and the self-check can warn
 * when a dashboard that used to call stops doing so. Nothing here calls out.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGAP_CC {
    const SEEN = 'sgap_cc_seen';
    /** Command Center syncs every 30 minutes; three missed rounds count as gone. */
    const STALE = 2 * HOUR_IN_SECONDS;

    public static function init(): void {
        add_filter('rest_request_before_callbacks', [__CLASS__, 'note_call'], 10, 3);
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
            return ['state' => 'never', 'connected' => false, 'last_seen' => null, 'version' => null];
        }
        $age = time() - (int) $seen['ts'];
        return [
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

    /** Log ind button to the Command Center dashboard; empty until its address is set in Settings. */
    public static function dashboard_button(): string {
        $url = esc_url(SGAP_Settings::get('command_center_url'));
        if ($url === '') {
            return '';
        }
        return '<p><a class="button button-secondary" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
            . '<span class="dashicons dashicons-external"></span>Log ind i Command Center</a></p>';
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
