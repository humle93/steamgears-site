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
     * Records the outcome exactly as the Publer path does: a failure gets the queue's backoff and the
     * publish log, a success is published at once (the platform id is the receipt, nothing to poll).
     */
    public static function finish(array $row, string $class, $job, array &$report): void {
        global $wpdb;
        $id = (int) $row['id'];
        $channel = (string) $row['channel'];
        if (is_wp_error($job)) {
            $state = SGAP_Queue::mark($id, 'failed', $job->get_error_message());
            SGAP_Audit::record_error($id, $channel, $job, $state === 'queued' ? 'requeued' : 'failed');
            $report['failed'][] = ['id' => $id, 'channel' => $channel, 'error' => $job->get_error_message()];
            return;
        }
        $wpdb->update($wpdb->prefix . 'sgap_queue', ['external_id' => (string) $job, 'sent_at' => current_time('mysql')], ['id' => $id]);
        SGAP_Queue::mark($id, 'published');
        SGAP_Audit::record($id, $channel, 'published', 200, (self::MODULES[$class] ?? '') . $job);
        $report['sent'][] = ['id' => $id, 'channel' => $channel, 'job_id' => (string) $job, 'direct' => strtolower(str_replace('SGAP_', '', $class))];
    }
}
