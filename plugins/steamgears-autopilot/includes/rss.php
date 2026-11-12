<?php
/**
 * Publer RSS mode, for the Publer Professional plan (the Publer API needs Business).
 * Every 15 minutes due queue posts are released into one RSS feed per channel
 * (https://steamgears.eu/?sgap_feed=<channel>). In Publer each feed is added once
 * under Explore → RSS Feeds and set to Auto-post to that channel's account.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_RSS {
    const QUERY_VAR = 'sgap_feed';
    const MARKER = 'rss'; // external_id of posts released into a feed
    const ITEMS = 20;
    /** A configured feed that has not carried a post for this long is reported as stale. */
    const STALE_DAYS = 7;

    public static function init(): void {
        add_action('init', [__CLASS__, 'add_rewrite_rule']);
        add_filter('query_vars', fn(array $vars) => array_merge($vars, [self::QUERY_VAR]));
        add_action('template_redirect', [__CLASS__, 'render'], 1);
    }

    public static function enabled(): bool {
        return SGAP_Settings::get('publish_mode', 'rss') !== 'api';
    }

    public static function add_rewrite_rule(): void {
        add_rewrite_rule('^sgap-feed/([a-z]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    /**
     * The address to give Publer. A query string, because page caches (W3 Total Cache
     * disk enhanced on steamgears.eu) never store URLs with one, whereas they served a
     * stale copy of /sgap-feed/<channel>/ straight from disk without reaching WordPress.
     */
    public static function url(string $channel): string {
        return add_query_arg([self::QUERY_VAR => $channel], home_url('/'));
    }

    /** Older pretty address; still served, but a page cache may hold a copy of it. */
    public static function pretty_url(string $channel): string {
        return home_url('/sgap-feed/' . $channel . '/');
    }

    /** Publer turns a literal "\n" in the description into a line break. */
    public static function description(string $text): string {
        return str_replace(["\r\n", "\r", "\n"], '\n', trim($text));
    }

    /**
     * Cron (via SGAP_Publer::tick in RSS mode): moves due posts into their channel feed,
     * within quiet hours, the daily cap and batch size. Same report shape as the API publisher.
     */
    public static function release(): array {
        $report = ['mode' => 'rss', 'configured' => true, 'followed_up' => [], 'sent' => [], 'failed' => [], 'reminded' => [], 'blocked' => ''];
        $report['blocked'] = SGAP_Queue::blocked_reason();
        if ($report['blocked'] !== '') {
            return $report;
        }
        global $wpdb;
        foreach (SGAP_Queue::claim(SGAP_Queue::capacity()) as $row) {
            $id = (int) $row['id'];
            $channel = (string) $row['channel'];
            // P1.2: a reminder never reaches the feed — the feed is what posts, so
            // leaving it in would be the opposite of "do not post this one for me".
            if (!empty($row['remind'])) {
                $done = SGAP_Reminder::deliver($row);
                SGAP_Audit::record($id, $channel, 'reminded', 0, $done['message']);
                $report['reminded'][] = ['id' => $id, 'channel' => $channel, 'mailed' => $done['mailed']];
                continue;
            }
            $error = '';
            if (!in_array($channel, SGAP_Publer::CHANNELS, true)) {
                $error = "Channel '{$channel}' is not supported.";
            } elseif (in_array($channel, SGAP_Publer::NEEDS_PHOTO, true) && empty($row['image_url'])) {
                // The featured image, or an AI photo when that is switched on.
                $row['image_url'] = (string) apply_filters('sgap_missing_photo', '', $row);
                if ($row['image_url'] !== '') {
                    $wpdb->update($wpdb->prefix . 'sgap_queue', ['image_url' => $row['image_url']], ['id' => $id]);
                } else {
                    $error = ucfirst($channel) . ' needs a photo: give the post a featured image, or switch on AI photos (Settings → Automation).';
                }
            }
            if ($error !== '') {
                $state = SGAP_Queue::mark($id, 'failed', $error);
                SGAP_Audit::record($id, $channel, $state === 'queued' ? 'requeued' : 'failed', 0, $error);
                $report['failed'][] = ['id' => $id, 'channel' => $channel, 'error' => $error];
                continue;
            }
            // P11: a channel the plugin posts itself never goes into a feed. This is what lets
            // Publer be phased out one channel at a time while the rest still use RSS.
            $class = SGAP_Direct::handler($channel);
            if ($class !== '') {
                SGAP_Direct::finish($row, $class, SGAP_Direct::publish($class, $row), $report);
                continue;
            }
            $wpdb->update($wpdb->prefix . 'sgap_queue', ['external_id' => self::MARKER, 'sent_at' => current_time('mysql')], ['id' => $id]);
            SGAP_Queue::mark($id, 'published');
            SGAP_Audit::record($id, $channel, 'rss', 200, 'Released in ' . self::url($channel));
            self::flush_cache($channel);
            $report['sent'][] = ['id' => $id, 'channel' => $channel, 'feed' => self::url($channel)];
        }
        return $report;
    }

    /**
     * P7: is each configured channel's feed actually usable by Publer?
     *
     * In RSS mode there is no token to test, so the observable equivalent is the feed itself: does it
     * carry posts, are they recent, and does every one of them have what Publer needs to post it
     * (title, text, and a photo where the channel demands one)? The check reads the exact rows
     * `xml()` renders, so it cannot pass on data the feed would not contain.
     *
     * States: `off` (API mode, no feed in use), `ok`, `empty`, `stale`, `invalid`. One row per
     * configured channel; a channel that is not switched on has no feed to judge.
     *
     * @return array<string,array> channel => [state, posts, newest, age_days, url, problems]
     */
    public static function health(): array {
        $out = [];
        $enabled = self::enabled();
        // Same clock the queue writes with, so a post cannot look a day older than it is.
        $now = (int) strtotime((string) current_time('mysql'));
        $stale_days = max(1, (int) apply_filters('sgap_feed_stale_days', self::STALE_DAYS));
        foreach (SGAP_Publer::channels() as $channel) {
            $row = ['state' => 'ok', 'posts' => 0, 'newest' => '', 'age_days' => null, 'url' => self::url($channel), 'problems' => []];
            if (!$enabled) {
                $row['state'] = 'off';
                $out[$channel] = $row;
                continue;
            }
            // P11: posted directly, so its feed is empty on purpose and Publer is not asked.
            if (class_exists('SGAP_Direct') && SGAP_Direct::handler($channel) !== '') {
                $row['state'] = 'direct';
                $out[$channel] = $row;
                continue;
            }
            $items = self::items($channel);
            $row['posts'] = count($items);
            if ($row['posts'] === 0) {
                $row['state'] = 'empty';
                $row['problems'][] = ucfirst($channel) . ' has nothing to post: its feed is empty, so Publer sees no new item.';
                $out[$channel] = $row;
                continue;
            }
            $row['newest'] = (string) $items[0]->published_at;
            $published = (int) strtotime($row['newest']);
            $row['age_days'] = $published > 0 && $now > 0 ? (int) floor(($now - $published) / DAY_IN_SECONDS) : null;
            foreach ($items as $item) {
                $id = (int) $item->id;
                if (trim((string) $item->title) === '') {
                    $row['problems'][] = 'Post #' . $id . ' has no title.';
                }
                if (trim((string) $item->body) === '') {
                    $row['problems'][] = 'Post #' . $id . ' has no text.';
                }
                if (in_array($channel, SGAP_Publer::NEEDS_PHOTO, true) && empty($item->image_url)) {
                    $row['problems'][] = 'Post #' . $id . ' has no photo, and ' . $channel . ' needs one.';
                }
            }
            if ($row['problems'] !== []) {
                $row['state'] = 'invalid';
            } elseif ($row['age_days'] !== null && $row['age_days'] > $stale_days) {
                $row['state'] = 'stale';
                $row['problems'][] = ucfirst($channel) . ' last carried a post ' . $row['age_days'] . ' days ago (limit ' . $stale_days . ').';
            }
            $out[$channel] = $row;
        }
        return $out;
    }

    /** Counts per state plus one sentence, for the channel-health answer and the nightly run. */
    public static function health_summary(array $health): array {
        $counts = ['ok' => 0, 'empty' => 0, 'stale' => 0, 'invalid' => 0, 'off' => 0, 'direct' => 0];
        $attention = [];
        foreach ($health as $channel => $row) {
            $state = (string) ($row['state'] ?? 'invalid');
            $counts[$state] = ($counts[$state] ?? 0) + 1;
            if (in_array($state, ['empty', 'stale', 'invalid'], true)) {
                $attention[] = $channel;
            }
        }
        if ($counts['off'] > 0 && array_sum($counts) === $counts['off']) {
            $note = 'API mode is on, so no feed is used and there is nothing to check.';
        } elseif ($attention !== []) {
            $note = count($attention) . ' of ' . count($health) . ' feed(s) need attention: ' . implode(', ', $attention) . '.';
        } elseif ($health === []) {
            $note = 'No channel is switched on, so no feed exists yet (Settings → Publer → Channels).';
        } elseif ($counts['direct'] === count($health)) {
            $note = 'Every channel is posted directly; Publer is not needed for any of them.';
        } else {
            $note = 'All ' . (count($health) - $counts['direct']) . ' feed(s) carry posts Publer can use'
                . ($counts['direct'] > 0 ? '; ' . $counts['direct'] . ' channel(s) are posted directly.' : '.');
        }
        return $counts + ['total' => count($health), 'note' => $note];
    }

    /** Drops a copy of the feed a page cache stored before DONOTCACHEPAGE was in place. */
    public static function flush_cache(string $channel): void {
        foreach ([self::url($channel), self::pretty_url($channel)] as $url) {
            if (function_exists('w3tc_flush_url')) {
                w3tc_flush_url($url);
            }
            if (function_exists('rocket_clean_files')) {
                rocket_clean_files([$url]);
            }
            do_action('litespeed_purge_url', $url);
        }
    }

    /** Items currently in a channel's feed, newest first. */
    public static function items(string $channel): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, body, image_url, published_at FROM {$wpdb->prefix}sgap_queue
             WHERE channel = %s AND external_id = %s AND status = 'published'
             ORDER BY published_at DESC, id DESC LIMIT %d",
            $channel, self::MARKER, self::ITEMS
        ));
    }

    public static function render(): void {
        $channel = sanitize_key((string) get_query_var(self::QUERY_VAR));
        if ($channel === '') {
            return;
        }
        if (!in_array($channel, SGAP_Publer::CHANNELS, true)) {
            status_header(404);
            nocache_headers();
            exit;
        }
        // Page caches (W3 Total Cache, WP Super Cache, LiteSpeed …) must not store the feed:
        // Publer has to see a released post on its next poll, not an hour later.
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        $items = self::items($channel);
        // Publer polls feeds often: answer 304 when nothing changed since its last fetch.
        $etag = '"' . md5($channel . '|' . implode(',', array_map(fn($i) => $i->id . '@' . $i->published_at, $items))) . '"';
        $newest = $items ? (string) get_gmt_from_date((string) $items[0]->published_at, 'D, d M Y H:i:s') . ' GMT' : '';
        header('Cache-Control: public, max-age=300');
        header('ETag: ' . $etag);
        if ($newest !== '') {
            header('Last-Modified: ' . $newest);
        }
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
        if ($if_none_match !== '' && $if_none_match === $etag) {
            status_header(304);
            exit;
        }
        status_header(200);
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        echo self::xml($channel, $items);
        exit;
    }

    public static function xml(string $channel, ?array $items = null): string {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel>' . "\n"
            . '<title>' . esc_xml(get_bloginfo('name') . ' – ' . $channel) . "</title>\n"
            . '<link>' . esc_url(home_url('/')) . "</link>\n"
            . '<description>' . esc_xml("Steamgears Autopilot posts for {$channel}") . "</description>\n";
        foreach ($items ?? self::items($channel) as $item) {
            $link = SGAP_Tracking::url((int) $item->id);
            $out .= "<item>\n"
                . '  <title>' . esc_xml((string) $item->title) . "</title>\n"
                . '  <link>' . esc_url($link) . "</link>\n"
                . '  <guid isPermaLink="false">steamgears-autopilot-' . (int) $item->id . "</guid>\n"
                . '  <pubDate>' . esc_xml(get_gmt_from_date((string) $item->published_at, DATE_RSS)) . "</pubDate>\n"
                . '  <description>' . esc_xml(self::description((string) $item->body)) . "</description>\n";
            if (!empty($item->image_url)) {
                $out .= '  <enclosure url="' . esc_url((string) $item->image_url) . '" type="' . esc_attr(self::mime((string) $item->image_url)) . '" length="0"/>' . "\n"
                    . '  <media:content url="' . esc_url((string) $item->image_url) . '" medium="image"/>' . "\n";
            }
            $out .= "</item>\n";
        }
        return $out . "</channel></rss>\n";
    }

    private static function mime(string $url): string {
        $ext = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return ['png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/jpeg';
    }
}
