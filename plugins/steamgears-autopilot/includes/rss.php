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
        $report = ['mode' => 'rss', 'configured' => true, 'followed_up' => [], 'sent' => [], 'failed' => [], 'blocked' => ''];
        $report['blocked'] = SGAP_Queue::blocked_reason();
        if ($report['blocked'] !== '') {
            return $report;
        }
        global $wpdb;
        foreach (SGAP_Queue::claim(SGAP_Queue::capacity()) as $row) {
            $id = (int) $row['id'];
            $channel = (string) $row['channel'];
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
                SGAP_Queue::mark($id, 'failed', $error);
                $report['failed'][] = ['id' => $id, 'channel' => $channel, 'error' => $error];
                continue;
            }
            $wpdb->update($wpdb->prefix . 'sgap_queue', ['external_id' => self::MARKER, 'sent_at' => current_time('mysql')], ['id' => $id]);
            SGAP_Queue::mark($id, 'published');
            self::flush_cache($channel);
            $report['sent'][] = ['id' => $id, 'channel' => $channel, 'feed' => self::url($channel)];
        }
        return $report;
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
