<?php
/**
 * Direct publishing (7.84.0, P11): one place that knows which channels the plugin posts itself.
 *
 * Bluesky, Mastodon, Telegram and Facebook could already post directly, but only in API mode, and API
 * mode stopped when no Publer key was saved. To phase Publer out one channel at a time, both modes now
 * ask this class first: a channel whose module `handles()` it is posted here, every other channel
 * keeps going to Publer (API job or RSS feed). Publer can be cancelled once every switched-on channel
 * is direct.
 */
if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Direct {
    /** In this mode a configured account is used directly and Publer is never a fallback. */
    public static function only_mode(): bool {
        return SGAP_Settings::get('publish_mode', 'rss') === 'direct';
    }

    /** Configuration is not proof that a platform has granted publishing permission. */
    public static function connection_status(): array {
        $checks = [
            'facebook' => ['Facebook', 'Add a Page ID and Page token with pages_manage_posts.', SGAP_Facebook::configured()],
            'instagram' => ['Instagram', 'Add a professional account ID and token with content-publishing permission.', SGAP_Instagram::configured()],
            'pinterest' => ['Pinterest', 'Add a token with boards:read and pins:write, plus a destination board ID.', SGAP_Pinterest::configured() && SGAP_Pinterest::board_id() !== ''],
            'bluesky' => ['Bluesky', 'Add a Bluesky handle and app password.', SGAP_Bluesky::configured()],
            'mastodon' => ['Mastodon', 'Add the Mastodon server and access token.', SGAP_Mastodon::configured()],
            'telegram' => ['Telegram', 'Add the bot token and chat ID.', SGAP_Telegram::configured()]
        ];
        $out = [];
        foreach (SGAP_Publer::channels() as $channel) {
            if (!isset($checks[$channel])) {
                $out[$channel] = ['label' => ucfirst($channel), 'configured' => false, 'message' => 'Direct publishing is not supported for this channel; its posts will wait.'];
                continue;
            }
            [$label, $missing, $configured] = $checks[$channel];
            $out[$channel] = ['label' => $label, 'configured' => $configured, 'message' => $configured ? 'Account details saved. Publishing permissions have not been verified here.' : $missing];
        }
        return $out;
    }

    public static function connections_panel(): string {
        $html = '<div class="sgap-card"><h3>Direct social connections</h3><p>Only connected accounts can publish. Posts for other channels stay in the queue.</p><ul>';
        $status = self::connection_status();
        if (!$status) {
            $html .= '<li>Choose your channels under Settings → Basics.</li>';
        }
        foreach ($status as $item) {
            $html .= '<li><strong>' . esc_html($item['label']) . ':</strong> ' . esc_html($item['message']) . '</li>';
        }
        $html .= '</ul><p><a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-settings#keys')) . '">Add account tokens</a> '
            . '<a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-settings#publishing')) . '">Account IDs and publishing</a></p>'
            . '<p class="description">Before switching, stop any Publer automations for these accounts to avoid duplicate posts. When Autopilot is on, scheduled posts may go out as soon as account details are saved.</p></div>';
        return $html;
    }

    /** channel module => receipt label, in the order they are asked. */
    const MODULES = [
        'SGAP_Bluesky' => 'Bluesky post ',
        'SGAP_Mastodon' => 'Mastodon post ',
        'SGAP_Telegram' => 'Telegram message ',
        'SGAP_Facebook' => 'Facebook post ',
        'SGAP_Instagram' => 'Instagram media ',
        'SGAP_Pinterest' => 'Pinterest pin '
    ];

    /** The module class that posts this channel directly, or ''. */
    public static function handler(string $channel): string {
        foreach (array_keys(self::MODULES) as $class) {
            if (class_exists($class) && method_exists($class, 'handles') && $class::handles($channel)) {
                return $class;
            }
        }
        return '';
    }

    /** Switched-on channels that are posted directly / still need Publer. */
    public static function split(): array {
        $direct = [];
        $publer = [];
        foreach (SGAP_Publer::channels() as $channel) {
            if (self::handler($channel) !== '') {
                $direct[] = $channel;
            } else {
                $publer[] = $channel;
            }
        }
        return ['direct' => $direct, 'publer' => $publer];
    }

    /** True when Publer is no longer needed for any switched-on channel. */
    public static function publer_free(): bool {
        $split = self::split();
        return $split['direct'] !== [] && $split['publer'] === [];
    }

    /** @return string|WP_Error The platform's id for the post. */
    public static function publish(string $class, array $row) {
        return $class::publish($row);
    }

    /**
     * Record a platform receipt immediately. Definite refusals use the queue's backoff; an
     * uncertain outcome stops for review because retrying could duplicate a published post.
     */
    public static function finish(array $row, string $class, $job, array &$report): void {
        global $wpdb;
        $id = (int) $row['id'];
        $channel = (string) $row['channel'];
        if (is_wp_error($job)) {
            $data = $job->get_error_data();
            $status = is_array($data) ? (int) ($data['status'] ?? 0) : 0;
            // A lost response or server error can follow a successful platform post. Do not
            // automatically resend when the result is unknown: that can duplicate the post.
            $uncertain = $status < 400 || $status === 408 || $status === 409 || $status >= 500;
            $message = $job->get_error_message() . ($uncertain ? ' The result may be unknown; check the social account before retrying.' : '');
            $state = SGAP_Queue::mark($id, 'failed', $message, !$uncertain);
            SGAP_Audit::record_error($id, $channel, $job, $state === 'queued' ? 'requeued' : 'failed');
            $report['failed'][] = ['id' => $id, 'channel' => $channel, 'error' => $message];
            return;
        }
        $wpdb->update($wpdb->prefix . 'sgap_queue', ['external_id' => (string) $job, 'sent_at' => current_time('mysql')], ['id' => $id]);
        SGAP_Queue::mark($id, 'published');
        SGAP_Audit::record($id, $channel, 'published', 200, (self::MODULES[$class] ?? '') . $job);
        $report['sent'][] = ['id' => $id, 'channel' => $channel, 'job_id' => (string) $job, 'direct' => strtolower(str_replace('SGAP_', '', $class))];
    }
}
