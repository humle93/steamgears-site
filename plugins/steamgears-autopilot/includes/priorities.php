<?php
/**
 * Priorities from Command Center (7.82.0).
 *
 * Command Center measures what the plugin cannot: which pages rank on position 4-20, for which
 * search phrase, and which new articles it just wrote. It sends that list here
 * (POST /sgap/v1/priorities). The generator then promotes those pages first and works the
 * search phrase into the posts. The bridge only steers WHICH page gets posted about and WHAT it
 * is about: queue, channels, caps and the quality gate are unchanged.
 */
if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Priorities {
    const OPTION = 'sgap_cc_priorities';
    const MAX_ITEMS = 30;
    /** A list older than this is ignored: Command Center sends a fresh one every run. */
    const TTL_DAYS = 14;
    /** A priority page may be promoted again sooner than the normal repost cooldown, but not daily. */
    const COOLDOWN_DAYS = 7;

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/priorities', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'rest_save'], 'permission_callback' => [SGAP_REST::class, 'can_manage']],
            ['methods' => 'GET', 'callback' => [__CLASS__, 'rest_list'], 'permission_callback' => [SGAP_REST::class, 'can_manage']]
        ]);
    }

    /**
     * Validates and stores the list. Unknown, unpublished or duplicate pages are skipped and
     * counted, so Command Center can show why a page did not arrive.
     * @return array{saved:int, skipped:array<int,array{url:string,reason:string}>}
     */
    public static function save(array $items): array {
        $clean = [];
        $skipped = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS * 2) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = esc_url_raw((string) ($item['url'] ?? ''));
            $post_id = (int) ($item['post_id'] ?? 0) ?: ($url !== '' ? (int) url_to_postid($url) : 0);
            if ($post_id <= 0) {
                $skipped[] = ['url' => $url, 'reason' => 'unknown page'];
                continue;
            }
            if (get_post_status($post_id) !== 'publish' || !in_array(get_post_type($post_id), ['post', 'product', 'page'], true)) {
                $skipped[] = ['url' => $url, 'reason' => 'not a published post, page or product'];
                continue;
            }
            if (isset($clean[$post_id])) {
                $skipped[] = ['url' => $url, 'reason' => 'duplicate'];
                continue;
            }
            if (count($clean) >= self::MAX_ITEMS) {
                $skipped[] = ['url' => $url, 'reason' => 'more than ' . self::MAX_ITEMS . ' pages'];
                continue;
            }
            $position = (int) ($item['position'] ?? 0);
            $clean[$post_id] = [
                'post_id' => $post_id,
                'url' => $url,
                'keyword' => mb_substr(sanitize_text_field((string) ($item['keyword'] ?? '')), 0, 80),
                'position' => $position > 0 && $position <= 100 ? $position : 0,
                'reason' => mb_substr(sanitize_key((string) ($item['reason'] ?? 'cc')), 0, 32)
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

    /** The search phrase Command Center gave for a page, or ''. */
    public static function keyword_for(int $post_id): string {
        foreach (self::items() as $item) {
            if ((int) ($item['post_id'] ?? 0) === $post_id) {
                return (string) ($item['keyword'] ?? '');
            }
        }
        return '';
    }

    /**
     * The priority pages that are due: published and not promoted in the last COOLDOWN_DAYS,
     * in the order Command Center sent them.
     * @return WP_Post[]
     */
    public static function due(): array {
        $cutoff = time() - self::COOLDOWN_DAYS * DAY_IN_SECONDS;
        $out = [];
        foreach (self::items() as $item) {
            $post = get_post((int) ($item['post_id'] ?? 0));
            if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }
            if ((int) get_post_meta($post->ID, SGAP_Generator::META, true) >= $cutoff) {
                continue;
            }
            $out[] = $post;
        }
        return $out;
    }

    /** For /snapshot, so Command Center can see what arrived. */
    public static function status(): array {
        $stored = get_option(self::OPTION);
        $at = is_array($stored) ? (int) ($stored['at'] ?? 0) : 0;
        return [
            'received_at' => $at > 0 ? wp_date('Y-m-d H:i:s', $at) : null,
            'active' => count(self::items()),
            'due' => count(self::due())
        ];
    }

    public static function rest_save(WP_REST_Request $r) {
        $items = $r->get_param('items');
        if (!is_array($items)) {
            return new WP_Error('sgap_priorities', 'Send {"items": [{"url", "keyword", "position", "reason"}]}.', ['status' => 400]);
        }
        return rest_ensure_response(self::save($items) + ['status' => self::status()]);
    }

    public static function rest_list(WP_REST_Request $r) {
        return rest_ensure_response(['items' => self::items(), 'status' => self::status()]);
    }
}

SGAP_Priorities::init();
