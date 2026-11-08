<?php
/**
 * Alerts — the two ways the autopilot goes quiet without telling anyone.
 *
 * P0.1: a queue item that has used its last attempt is mailed at once, so a dead
 *       token or a refused post is not discovered weeks later on the Queue page.
 * P0.2: the queue running dry is mailed *before* the last post goes out. The
 *       measure is runway in days: queued items divided by the daily cap.
 *
 * Both are deduplicated. The same problem is mailed at most once per REPEAT
 * window, and a broken channel cannot turn into a mail storm: at most
 * MAX_PER_HOUR mails leave the site in any hour.
 *
 * Modes (Settings → Alerts):
 *   immediate  one mail per failure, plus a mail when the queue runs dry
 *   daily      nothing immediate; one digest a day from the self-check
 *   off        no mail at all (the dashboard still shows the problems)
 */
if (!defined('ABSPATH')) exit;

class SGAP_Alerts {
    const LAST = 'sgap_alerts_last';
    const REPEAT = 6 * HOUR_IN_SECONDS;
    const MAX_PER_HOUR = 5;

    public static function init(): void {
        add_action('sgap_queue_failed', [__CLASS__, 'on_failure'], 10, 3);
    }

    public static function mode(): string {
        $mode = (string) SGAP_Settings::get('alert_mode', 'immediate');
        return in_array($mode, ['immediate', 'daily', 'off'], true) ? $mode : 'immediate';
    }

    /** Configured address, or the site admin when it is empty or not an address. */
    public static function recipient(): string {
        $to = trim((string) SGAP_Settings::get('alert_email', ''));
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $to;
        }
        return (string) get_option('admin_email');
    }

    /** Days of queue left at the current daily cap. 0 = nothing queued at all. */
    public static function runway_days(): float {
        global $wpdb;
        $queued = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_queue WHERE status = 'queued'");
        $per_day = max(1, (int) SGAP_Settings::get('daily_cap', 8));
        return round($queued / $per_day, 1);
    }

    /**
     * Per-channel caps from Settings → Alerts, written as "pinterest=2, facebook=3".
     * A channel without an entry is measured against daily_cap; a typo, an unknown
     * channel or a number outside 1–50 is ignored rather than warned about forever.
     */
    public static function caps(): array {
        $known = class_exists('SGAP_Publer') ? SGAP_Publer::CHANNELS : [];
        $caps = [];
        foreach (preg_split('/[\r\n,;]+/', (string) SGAP_Settings::get('channel_caps', '')) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$channel, $value] = array_map('trim', explode('=', $line, 2));
            $channel = sanitize_key($channel);
            $value = (int) $value;
            if ($channel === '' || $value < 1 || $value > 50) {
                continue;
            }
            if ($known && !in_array($channel, $known, true)) {
                continue; // a channel the publisher does not know cannot run dry
            }
            $caps[$channel] = $value;
        }
        return $caps;
    }

    /**
     * Queue depth and runway per channel: the whole-queue measure hides one dead
     * channel behind nine healthy ones. A channel with its own cap is measured
     * against that cap, everything else against daily_cap.
     */
    public static function channel_runway(): array {
        global $wpdb;
        $caps = self::caps();
        $daily = max(1, (int) SGAP_Settings::get('daily_cap', 8));
        $counts = [];
        $rows = (array) $wpdb->get_results("SELECT channel, COUNT(*) AS n FROM {$wpdb->prefix}sgap_queue WHERE status = 'queued' GROUP BY channel", ARRAY_A);
        foreach ($rows as $row) {
            $channel = sanitize_key((string) ($row['channel'] ?? ''));
            if ($channel === '') {
                continue;
            }
            $counts[$channel] = (int) ($row['n'] ?? 0);
        }
        $channels = array_keys($counts + $caps);
        sort($channels);
        $out = [];
        foreach ($channels as $channel) {
            $cap = (int) ($caps[$channel] ?? $daily);
            $queued = (int) ($counts[$channel] ?? 0);
            $out[$channel] = ['queued' => $queued, 'cap' => $cap, 'days' => round($queued / $cap, 1)];
        }
        return $out;
    }

    /**
     * Channels below the warning level. A channel with its own cap always counts:
     * setting a cap is a promise about that channel, so an empty one is a gap.
     * A channel without a cap only counts while it still has something queued.
     */
    public static function dry_channels(): array {
        $threshold = max(0, (int) SGAP_Settings::get('alert_dry_days', 2));
        if ($threshold === 0) {
            return [];
        }
        $caps = self::caps();
        $out = [];
        foreach (self::channel_runway() as $channel => $row) {
            if ($row['days'] < $threshold && ($row['queued'] > 0 || isset($caps[$channel]))) {
                $out[$channel] = $row;
            }
        }
        return $out;
    }

    /** Fired by SGAP_Queue::mark() when an item has spent its last attempt. */
    public static function on_failure(int $id, string $channel, string $error): void {
        if (self::mode() !== 'immediate') {
            return; // daily mode collects it in the digest instead
        }
        $channel = $channel !== '' ? $channel : 'A';
        $body = [
            "A {$channel} post has failed its last attempt and will not be retried by itself.",
            '',
            'Queue item: #' . $id,
            'Reason: ' . ($error !== '' ? $error : '(none recorded)'),
            '',
            'Fix the cause, then set the item back to queued on the Queue page:',
            admin_url('admin.php?page=sgap-queue'),
            '',
            'If every item fails the same way it is usually an expired Publer key or a refused image,',
            'not the post itself. Settings → Alerts turns these mails off or moves them into the daily digest.'
        ];
        self::send('queue:' . $id, "Steamgears: a {$channel} post failed", implode("\n", $body));
    }

    /**
     * Called from SGAP_Auto::health() once a day, right after the self-check is stored.
     * Sends the dry-queue alert (immediate mode) and the digest (daily mode).
     */
    public static function after_health(array $health): void {
        $mode = self::mode();
        if ($mode === 'off') {
            return;
        }
        global $wpdb;
        $runway = self::runway_days();
        $threshold = max(0, (int) SGAP_Settings::get('alert_dry_days', 2));
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_queue WHERE status = 'failed'");
        $problems = (array) ($health['problems'] ?? []);
        $dry = self::dry_channels();

        if ($mode === 'immediate') {
            if ($threshold > 0 && $runway < $threshold) {
                $body = [
                    $runway > 0
                        ? "The queue has {$runway} days of posts left at the daily cap. In a few days nothing goes out."
                        : 'The queue is empty: nothing is waiting to be published.',
                    '',
                    'Write new posts (the generator runs on its own when the autopilot is on), or approve the drafts',
                    'waiting under AI Drafts. You are warned at ' . $threshold . ' day(s) of runway — change that under Settings → Alerts.',
                    '',
                    admin_url('admin.php?page=sgap-queue')
                ];
                self::send('dry', 'Steamgears: the queue is running dry', implode("\n", $body));
            }
            // A healthy total can still hide one channel that is about to stop.
            if ($dry) {
                $lines = [count($dry) === 1 ? 'A channel is about to run dry:' : count($dry) . ' channels are about to run dry:', ''];
                foreach ($dry as $channel => $row) {
                    $lines[] = "- {$channel}: {$row['queued']} queued, {$row['days']} day(s) at {$row['cap']}/day";
                }
                $lines[] = '';
                $lines[] = 'Top up the quiet channel, or lower its cap if that rhythm was never intended.';
                $lines[] = 'Per-channel caps are set under Settings → Alerts; a channel without one is measured against Max posts per day.';
                $lines[] = '';
                $lines[] = admin_url('admin.php?page=sgap-queue');
                $names = array_keys($dry);
                $subject = count($names) === 1
                    ? 'Steamgears: ' . $names[0] . ' is running dry'
                    : 'Steamgears: ' . count($names) . ' channels are running dry';
                self::send('dry:' . implode(',', $names), $subject, implode("\n", $lines));
            }
            return;
        }

        // daily: one mail, and only when there is something to say.
        if (!$failed && !$problems && !$dry && ($threshold === 0 || $runway >= $threshold)) {
            return;
        }
        $lines = ['Steamgears Autopilot — daily digest', ''];
        $lines[] = "Queue: {$runway} day(s) of runway at the daily cap" . ($threshold > 0 && $runway < $threshold ? ' (below the warning level of ' . $threshold . ')' : '');
        if ($dry) {
            $names = [];
            foreach ($dry as $channel => $row) {
                $names[] = $channel . ' (' . $row['queued'] . ' queued, ' . $row['days'] . ' day(s) at ' . $row['cap'] . '/day)';
            }
            $lines[] = 'Channels below the warning level: ' . implode(', ', $names);
        }
        $lines[] = "Failed posts: {$failed}";
        $per_channel = self::channel_runway();
        if ($per_channel) {
            $lines[] = '';
            $lines[] = 'Per channel at its own cap:';
            foreach ($per_channel as $channel => $row) {
                $lines[] = "- {$channel}: {$row['queued']} queued, {$row['days']} day(s) at {$row['cap']}/day";
            }
        }
        if ($problems) {
            $lines[] = '';
            $lines[] = 'Self-check:';
            foreach (array_slice($problems, 0, 10) as $problem) {
                $lines[] = '- ' . $problem;
            }
        }
        $lines[] = '';
        $lines[] = 'Dashboard: ' . admin_url('admin.php?page=steamgears-autopilot');
        $subject = "Steamgears daily: {$failed} failed, {$runway} days of queue";
        self::send('digest:' . wp_date('Y-m-d'), $subject, implode("\n", $lines), 12 * HOUR_IN_SECONDS);
    }

    /**
     * Sends one mail unless the same key was mailed inside $window or the hourly
     * budget is spent. Returns whether a mail actually went out.
     */
    private static function send(string $key, string $subject, string $body, int $window = self::REPEAT): bool {
        if (self::mode() === 'off') {
            return false;
        }
        $now = time();
        $last = (array) get_option(self::LAST, []);
        foreach ($last as $k => $ts) {
            if ($now - (int) $ts > DAY_IN_SECONDS) {
                unset($last[$k]); // keep the option small
            }
        }
        if (isset($last[$key]) && ($now - (int) $last[$key]) < $window) {
            return false;
        }
        $this_hour = array_filter($last, fn($ts) => $now - (int) $ts < HOUR_IN_SECONDS);
        if (count($this_hour) >= self::MAX_PER_HOUR) {
            return false;
        }
        $sent = wp_mail(self::recipient(), $subject, $body);
        if ($sent) {
            $last[$key] = $now;
            update_option(self::LAST, $last, false);
        }
        return (bool) $sent;
    }
}
