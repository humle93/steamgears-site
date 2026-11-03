<?php
/**
 * Blackout calendar: date ranges where nothing may be released (holidays, sale
 * blackouts, a week off). Queued posts are moved to the next free day at the same
 * time of day instead of being dropped or dumped in a heap when the range ends.
 *
 * Loaded from steamgears-autopilot.php. One query per publish tick, no HTTP.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Blackout {
    /** How far ahead a post is moved before we give up and place it anyway. */
    const HORIZON = 60;

    /**
     * Parses the setting: one range per line, ISO dates, date-only or from..to,
     * with an optional label after the dates.
     *   2026-12-24..2026-12-26 Christmas
     *   2026-12-31 New Year's Eve
     * Lines that are not dates are ignored, so a typo cannot block the calendar.
     * @return array<int, array{from: string, to: string, label: string}>
     */
    public static function parse(string $text): array {
        $ranges = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s*(?:\.\.|–|—|->|to)\s*(\d{4}-\d{2}-\d{2}))?\s*(.*)$/u', $line, $m)) {
                continue;
            }
            $from = $m[1];
            $to = $m[2] !== '' ? $m[2] : $from;
            if (!self::is_date($from) || !self::is_date($to)) {
                continue;
            }
            if ($to < $from) {
                [$from, $to] = [$to, $from];
            }
            $ranges[] = ['from' => $from, 'to' => $to, 'label' => trim((string) ($m[3] ?? ''))];
        }
        usort($ranges, fn($a, $b) => strcmp($a['from'], $b['from']));
        return $ranges;
    }

    private static function is_date(string $date): bool {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** The configured ranges, straight from the settings. */
    public static function ranges(): array {
        return self::parse((string) SGAP_Settings::get('blackout_ranges'));
    }

    /**
     * The range that covers this day, or null. No date = today in site time.
     * @return array{from: string, to: string, label: string}|null
     */
    public static function active(?string $date = null): ?array {
        $date = $date ?? wp_date('Y-m-d');
        foreach (self::ranges() as $range) {
            if ($date >= $range['from'] && $date <= $range['to']) {
                return $range;
            }
        }
        return null;
    }

    /**
     * First day at or after $datetime's day that is not blacked out, keeping the
     * time of day. Returns the same timestamp if it is already free, and never
     * loops forever: after HORIZON days it places the post regardless.
     */
    public static function next_free(string $datetime): string {
        $ts = strtotime($datetime);
        if ($ts === false) {
            $ts = strtotime(current_time('mysql'));
        }
        $day = date('Y-m-d', $ts);
        $time = date('H:i:s', $ts);
        for ($i = 0; $i <= self::HORIZON; $i++) {
            $candidate = date('Y-m-d', strtotime($day . ' +' . $i . ' days'));
            if (self::active($candidate) === null) {
                return $candidate . ' ' . $time;
            }
        }
        return date('Y-m-d', strtotime($day . ' +' . (self::HORIZON + 1) . ' days')) . ' ' . $time;
    }

    /** True when a post scheduled for this moment would land in a blackout. */
    public static function hits(string $datetime): bool {
        return self::active(substr($datetime, 0, 10)) !== null;
    }

    /**
     * Moves every queued post inside a blackout to the next free day at the same
     * time. Runs from the publish tick, so a range added while posts are queued is
     * honoured within 15 minutes. Published and in-flight rows are never touched.
     * @return array<int, array{id: int, from: string, to: string, reason: string}>
     */
    public static function reschedule(): array {
        $ranges = self::ranges();
        if (!$ranges) {
            return [];
        }
        $today = wp_date('Y-m-d');
        $last = (string) $ranges[count($ranges) - 1]['to'];
        if ($last < $today) {
            return [];
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, scheduled_at FROM {$wpdb->prefix}sgap_queue
             WHERE status = 'queued' AND scheduled_at >= %s AND scheduled_at <= %s
             ORDER BY scheduled_at ASC, id ASC",
            $today . ' 00:00:00', $last . ' 23:59:59'
        ));
        $moved = [];
        foreach ((array) $rows as $row) {
            $from = (string) $row->scheduled_at;
            $range = self::active(substr($from, 0, 10));
            if ($range === null) {
                continue;
            }
            $to = self::next_free($from);
            if ($to === $from) {
                continue;
            }
            $wpdb->update($wpdb->prefix . 'sgap_queue', ['scheduled_at' => $to], ['id' => (int) $row->id]);
            $moved[] = [
                'id' => (int) $row->id,
                'from' => $from,
                'to' => $to,
                'reason' => $range['label'] !== '' ? $range['label'] : $range['from'] . '..' . $range['to']
            ];
        }
        if ($moved) {
            delete_transient('sgap_dashboard_stats');
        }
        return $moved;
    }

    /** One line for the dashboard, the status ability and the publish report. */
    public static function summary(): array {
        $ranges = self::ranges();
        $active = self::active();
        return [
            'ranges' => count($ranges),
            'active' => $active !== null,
            'range' => $active,
            'last' => $ranges ? (string) $ranges[count($ranges) - 1]['to'] : null,
            'moves_to' => $active !== null ? self::next_free(current_time('mysql')) : null
        ];
    }
}
