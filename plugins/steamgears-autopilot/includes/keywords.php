<?php
/**
 * Article keywords from Command Center (7.92.0).
 *
 * Command Center scans each site for new search phrases (its content feed) and sorts them into
 * article and guide topics. It sends the waiting ones here (POST /sgap/v1/keywords), and the
 * weekly article job writes from this list first, before it asks the AI for topics of its own.
 * The answer tells Command Center which phrases already became posts, so it can stop offering
 * them and does not write the same article itself.
 *
 * Only WHICH topics get written comes from Command Center: articles_per_week, drafts versus
 * auto-publish and the Studio writer are unchanged.
 *
 * 7.93.2: a phrase the site already has a post for (same Rank Math focus keyword, title or slug,
 * also scheduled posts and drafts) is not written again. It is recorded with that post's id and
 * the status "exists", so Command Center gets it back under "written" and stops offering it too.
 */
if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Keywords {
    const OPTION = 'sgap_cc_keywords';
    const USED_OPTION = 'sgap_cc_keywords_used';
    const MAX_ITEMS = 30;
    /** A list older than this is ignored: Command Center sends a fresh one every day. */
    const TTL_DAYS = 14;
    /** A phrase that failed this many times is not tried again until Command Center resends it fresh. */
    const MAX_FAILS = 3;
    const TYPES = ['article', 'guide'];
    /** Content that counts as "already on the site". */
    const EXISTING_TYPES = ['post', 'page', 'product'];
    const EXISTING_STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];
    /** keyword => post id, per request: status() and next() ask for the same phrases. */
    private static $existing = [];

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/keywords', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'rest_save'], 'permission_callback' => [SGAP_REST::class, 'can_manage']],
            ['methods' => 'GET', 'callback' => [__CLASS__, 'rest_list'], 'permission_callback' => [SGAP_REST::class, 'can_manage']]
        ]);
    }

    private static function clean_text(string $value, int $max): string {
        return mb_substr(trim(preg_replace('/\s+/', ' ', sanitize_text_field($value))), 0, $max);
    }

    /**
     * Validates and stores the list (it replaces the previous one). Items without an id or a
     * keyword, duplicates and anything over MAX_ITEMS are skipped and counted.
     * @return array{saved:int, skipped:array<int,array{id:int,reason:string}>}
     */
    public static function save(array $items): array {
        $clean = [];
        $skipped = [];
        $seen = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS * 2) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            $keyword = self::clean_text((string) ($item['keyword'] ?? ''), 120);
            if ($id <= 0 || $keyword === '') {
                $skipped[] = ['id' => $id, 'reason' => 'missing id or keyword'];
                continue;
            }
            $key = mb_strtolower($keyword);
            if (isset($clean[$id]) || isset($seen[$key])) {
                $skipped[] = ['id' => $id, 'reason' => 'duplicate'];
                continue;
            }
            if (count($clean) >= self::MAX_ITEMS) {
                $skipped[] = ['id' => $id, 'reason' => 'more than ' . self::MAX_ITEMS . ' keywords'];
                continue;
            }
            $type = in_array((string) ($item['type'] ?? ''), self::TYPES, true) ? (string) $item['type'] : 'article';
            $related = [];
            foreach (array_slice((array) ($item['related'] ?? []), 0, 8) as $r) {
                $r = self::clean_text((string) $r, 80);
                if ($r !== '' && mb_strtolower($r) !== $key) {
                    $related[] = $r;
                }
            }
            $seen[$key] = true;
            $clean[$id] = [
                'id' => $id,
                'keyword' => $keyword,
                'type' => $type,
                'title' => self::clean_text((string) ($item['title'] ?? ''), 200),
                'related' => $related,
                'score' => round((float) ($item['score'] ?? 0), 2)
            ];
        }
        update_option(self::OPTION, ['at' => time(), 'items' => array_values($clean)], false);
        return ['saved' => count($clean), 'skipped' => $skipped];
    }

    /** The current list, or [] when none was sent or it is older than TTL_DAYS. */
    public static function items(): array {
        $stored = get_option(self::OPTION);
        if (!is_array($stored) || (int) ($stored['at'] ?? 0) < time() - self::TTL_DAYS * DAY_IN_SECONDS) {
            return [];
        }
        return array_values(array_filter((array) ($stored['items'] ?? []), 'is_array'));
    }

    /** id => {keyword, post_id, status, at} for phrases that became posts (or failed). */
    public static function used(): array {
        $used = get_option(self::USED_OPTION);
        return is_array($used) ? $used : [];
    }

    /**
     * The id of a post, page or product that already covers this phrase, or 0. A match is the
     * Rank Math focus keyword (the first one, when several are comma separated), the title (the
     * phrase or Command Center's planned title) or the slug of the phrase. Trashed posts do not count.
     */
    public static function existing_post(array $item): int {
        global $wpdb;
        $kw = mb_strtolower(trim((string) ($item['keyword'] ?? '')));
        if ($kw === '' || !is_object($wpdb)) {
            return 0;
        }
        $title = mb_strtolower(trim((string) ($item['title'] ?? '')));
        $key = $kw . '|' . $title;
        if (array_key_exists($key, self::$existing)) {
            return self::$existing[$key];
        }
        $types = implode(',', array_fill(0, count(self::EXISTING_TYPES), '%s'));
        $statuses = implode(',', array_fill(0, count(self::EXISTING_STATUSES), '%s'));
        $sql = "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'rank_math_focus_keyword'"
             . " WHERE p.post_type IN ($types) AND p.post_status IN ($statuses)"
             . " AND (LOWER(TRIM(m.meta_value)) = %s OR LOWER(m.meta_value) LIKE %s OR LOWER(p.post_title) = %s OR LOWER(p.post_title) = %s OR p.post_name = %s)"
             . " ORDER BY p.ID ASC LIMIT 1";
        $args = array_merge(self::EXISTING_TYPES, self::EXISTING_STATUSES,
                            [$kw, $wpdb->esc_like($kw) . ',%', $kw, $title !== '' ? $title : $kw, sanitize_title($kw)]);
        return self::$existing[$key] = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
    }

    /** Forget the per-request lookups (after a post was written, and in tests). */
    public static function reset_existing(): void {
        self::$existing = [];
    }

    /** The next $n phrases to write, in Command Center's order: not written yet, not on the site already and not failed too often. */
    public static function next(int $n): array {
        if ($n < 1) {
            return [];
        }
        $used = self::used();
        $out = [];
        foreach (self::items() as $item) {
            $u = $used[(int) $item['id']] ?? null;
            if (is_array($u) && ((int) ($u['post_id'] ?? 0) > 0 || (int) ($u['fails'] ?? 0) >= self::MAX_FAILS)) {
                continue;
            }
            $existing = self::existing_post($item);
            if ($existing > 0) {
                self::record($item, ['post_id' => $existing, 'status' => 'exists']);
                $used = self::used();
                continue;
            }
            $out[] = $item;
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /** Records the outcome for one phrase: a post id, or an error (counted, retried next run). */
    public static function record(array $item, $result): void {
        $used = self::used();
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $prev = is_array($used[$id] ?? null) ? $used[$id] : [];
        if (is_wp_error($result)) {
            $used[$id] = ['keyword' => (string) ($item['keyword'] ?? ''), 'post_id' => 0, 'status' => 'failed',
                          'error' => mb_substr($result->get_error_message(), 0, 160),
                          'fails' => (int) ($prev['fails'] ?? 0) + 1, 'at' => time()];
        } else {
            $used[$id] = ['keyword' => (string) ($item['keyword'] ?? ''), 'post_id' => (int) ($result['post_id'] ?? 0),
                          'status' => (string) ($result['status'] ?? 'draft'), 'at' => time()];
            self::reset_existing();
        }
        // Keep the newest 200: enough for months of articles, small enough for one option row.
        if (count($used) > 200) {
            uasort($used, fn($a, $b) => (int) ($b['at'] ?? 0) <=> (int) ($a['at'] ?? 0));
            $used = array_slice($used, 0, 200, true);
        }
        update_option(self::USED_OPTION, $used, false);
    }

    /** What Command Center needs back: the phrases that became posts, with the post id. */
    public static function written(): array {
        $out = [];
        foreach (self::used() as $id => $u) {
            if (is_array($u) && (int) ($u['post_id'] ?? 0) > 0) {
                $out[] = ['id' => (int) $id, 'keyword' => (string) ($u['keyword'] ?? ''), 'post_id' => (int) $u['post_id'],
                          'status' => (string) ($u['status'] ?? ''), 'written_at' => wp_date('Y-m-d H:i:s', (int) ($u['at'] ?? 0))];
            }
        }
        return $out;
    }

    /** True when the weekly article job runs at all; Command Center only hands over topics then. */
    public static function writing(): bool {
        return (int) SGAP_Settings::get('articles_per_week', 0) > 0 && SGAP_Settings::enabled('autopilot_enabled');
    }

    /** For /snapshot and the REST answer. */
    public static function status(): array {
        $stored = get_option(self::OPTION);
        $at = is_array($stored) ? (int) ($stored['at'] ?? 0) : 0;
        $waiting = count(self::next(self::MAX_ITEMS));
        $failed = $existing = 0;
        foreach (self::used() as $u) {
            if (is_array($u) && (int) ($u['post_id'] ?? 0) === 0 && (int) ($u['fails'] ?? 0) > 0) {
                $failed++;
            }
            if (is_array($u) && ($u['status'] ?? '') === 'exists') {
                $existing++;
            }
        }
        return [
            'received_at' => $at > 0 ? wp_date('Y-m-d H:i:s', $at) : null,
            'active' => count(self::items()),
            'waiting' => $waiting,
            'written' => count(self::written()),
            'existing' => $existing,
            'failed' => $failed,
            'writing' => self::writing(),
            'articles_per_week' => (int) SGAP_Settings::get('articles_per_week', 0)
        ];
    }

    public static function rest_save(WP_REST_Request $r) {
        $items = $r->get_param('items');
        if (!is_array($items)) {
            return new WP_Error('sgap_keywords', 'Send {"items": [{"id", "keyword", "type", "title", "related"}]}.', ['status' => 400]);
        }
        return rest_ensure_response(self::save($items) + ['written' => self::written(), 'status' => self::status()]);
    }

    public static function rest_list(WP_REST_Request $r) {
        return rest_ensure_response(['items' => self::items(), 'written' => self::written(), 'status' => self::status()]);
    }
}

SGAP_Keywords::init();
