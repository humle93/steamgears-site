<?php
/**
 * P0.6 — cross-system duplicate guard.
 *
 * The queue is no longer filled by one writer. The AI generator, the Studio, the
 * REST API, an MCP client and Command Center can all queue a post, and two of them
 * can hand over the same one: Command Center syncs every 30 minutes, so a dashboard
 * that retries a hand-over would otherwise post twice.
 *
 * The key is the one the database already indexes: `channel|destination_url|title`,
 * hashed to sha256. This class adds the time window the roadmap asked for — a post
 * that is *still queued* always blocks an identical one, while a published post only
 * blocks for `dupe_days` days (30 by default, 0 = the whole history), so an evergreen
 * campaign can legitimately repeat a title next quarter.
 *
 * Reads the database; writes nothing and makes no HTTP requests.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Duplicate {
    /** Default window: an identical post published less than a month ago still blocks. */
    const DEFAULT_DAYS = 30;

    /** Ten years is not a window, it is "forever with extra steps". */
    const MAX_DAYS = 3650;

    /** Statuses that are still on their way out, so age does not apply to them. */
    const ACTIVE = ['queued', 'sending', 'draft'];

    /** How many days back a published post blocks an identical one. 0 = the whole history. */
    public static function days(): int {
        return max(0, min(self::MAX_DAYS, (int) SGAP_Settings::get('dupe_days', self::DEFAULT_DAYS)));
    }

    /** The fingerprint the queue column indexes: channel + destination + title. */
    public static function fingerprint(string $channel, string $destination, string $title): string {
        return hash('sha256', $channel . '|' . $destination . '|' . $title);
    }

    /** Oldest timestamp inside the window, in the site's own timezone. */
    public static function cutoff(int $days): string {
        return wp_date('Y-m-d H:i:s', time() - max(0, $days) * DAY_IN_SECONDS);
    }

    /**
     * The row that already carries this fingerprint, or null.
     *
     * Failed rows never block: a post that gave up is the owner's to retry.
     * @return array{id: int, status: string, scheduled_at: ?string, published_at: ?string}|null
     */
    public static function find(string $fingerprint, int $exclude_id = 0): ?array {
        global $wpdb;
        if ($fingerprint === '') {
            return null;
        }
        $sql = 'SELECT id, status, scheduled_at, published_at FROM ' . $wpdb->prefix . 'sgap_queue'
            . " WHERE fingerprint = %s AND status <> 'failed'";
        $args = [$fingerprint];
        if ($exclude_id > 0) {
            $sql .= ' AND id <> %d';
            $args[] = $exclude_id;
        }
        $days = self::days();
        if ($days > 0) {
            // Still in the queue? It blocks whatever its age. Already out? Only inside the window.
            // The statuses are class constants, not input, so they are inlined; the cutoff is bound.
            $sql .= " AND (status IN ('" . implode("', '", self::ACTIVE) . "')"
                . ' OR COALESCE(published_at, sent_at, scheduled_at) >= %s)';
            $args[] = self::cutoff($days);
        }
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'status' => (string) ($row['status'] ?? ''),
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'published_at' => $row['published_at'] ?? null
        ];
    }

    /** The queue row with this channel, destination and title, or null. */
    public static function check(array $row, int $exclude_id = 0): ?array {
        return self::find(self::fingerprint(
            (string) ($row['channel'] ?? ''),
            (string) ($row['destination_url'] ?? ''),
            (string) ($row['title'] ?? '')
        ), $exclude_id);
    }

    /**
     * What the caller is told, naming the row it collided with and when — a bare
     * "Already queued." is impossible to act on when another system did the queueing.
     */
    public static function message(array $found): string {
        $id = (int) ($found['id'] ?? 0);
        $status = (string) ($found['status'] ?? 'queued');
        $when = (string) (($found['published_at'] ?? '') ?: ($found['scheduled_at'] ?? ''));
        $days = self::days();
        $window = $days > 0 ? "in the last {$days} days" : 'before';
        $line = sprintf(
            'Already queued as #%d (%s%s). The same channel, destination and title went in %s — change the title, or delete the old row if this repeat is deliberate.',
            $id,
            $status,
            $when !== '' ? ', ' . $when : '',
            $window
        );
        return $line;
    }
}
