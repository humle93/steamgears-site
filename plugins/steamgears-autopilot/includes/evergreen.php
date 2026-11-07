<?php
/**
 * Evergreen recycling (P1.4): a page that keeps earning can be promoted again
 * long after its first run, with a fresh caption every time, until it has used
 * its share budget or reached its expiry date. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Evergreen {
    /** Marks a post/product as evergreen, whatever its status; only published items are recycled. */
    const MARK = '_sgap_evergreen';
    /** How many times this page has been recycled. */
    const SHARES = '_sgap_evergreen_shares';
    /** Per-page share budget; 0 means "use the setting". */
    const MAX = '_sgap_evergreen_max';
    /** Per-page expiry date (Y-m-d); empty means "never expires". */
    const UNTIL = '_sgap_evergreen_until';
    /** Shared with the generator, so a recycled page is not also promoted as new the same week. */
    const LAST = '_sgap_promoted_at';

    /** How many variations are written per page and run at most: a guard against a runaway loop. */
    const MAX_PER_RUN = 10;

    public static function init(): void {
        add_action(SGAP_Install::EVERGREEN_CRON, [__CLASS__, 'cron']);
        add_action('add_meta_boxes', [__CLASS__, 'metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);
    }

    /** The post types that can be evergreen: the same ones the generator promotes. */
    public static function types(): array {
        return array_values(array_filter(['post', 'product'], 'post_type_exists')) ?: ['post'];
    }

    public static function enabled(): bool {
        return SGAP_Settings::enabled('evergreen_enabled');
    }

    /** Days between two shares of the same page. */
    public static function after_days(): int {
        return max(1, (int) SGAP_Settings::get('evergreen_after_days', 60));
    }

    /** The share budget used when a page has none of its own. */
    public static function default_max(): int {
        return max(1, (int) SGAP_Settings::get('evergreen_max_shares', 3));
    }

    public static function batch(): int {
        return min(self::MAX_PER_RUN, max(1, (int) SGAP_Settings::get('evergreen_batch', 1)));
    }

    /** True when the id exists and is a page/product this module can recycle. */
    public static function is_supported($post): bool {
        return is_object($post) && !empty($post->ID) && in_array((string) ($post->post_type ?? ''), self::types(), true);
    }

    /**
     * Marks a post/product as evergreen. $args: max (int, 0 = inherit), until (Y-m-d, '' = never).
     * A draft can be marked; it simply stays ineligible until it is published.
     * @return array|WP_Error
     */
    public static function mark(int $post_id, array $args = []) {
        $post = get_post($post_id);
        if (!self::is_supported($post)) {
            return new WP_Error('sgap_evergreen_type', 'Only blog posts and products can be evergreen.', ['status' => 400]);
        }
        $max = isset($args['max']) ? absint($args['max']) : 0;
        $until = trim((string) ($args['until'] ?? ''));
        if ($until !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $until);
            if (!$date || $date->format('Y-m-d') !== $until) {
                return new WP_Error('sgap_evergreen_date', 'until must be a date in the form YYYY-MM-DD.', ['status' => 400]);
            }
            if ($until < wp_date('Y-m-d')) {
                return new WP_Error('sgap_evergreen_date', 'until cannot be in the past.', ['status' => 400]);
            }
        }
        update_post_meta($post_id, self::MARK, '1');
        if ($max > 0) {
            update_post_meta($post_id, self::MAX, $max);
        } else {
            delete_post_meta($post_id, self::MAX);
        }
        if ($until !== '') {
            update_post_meta($post_id, self::UNTIL, $until);
        } else {
            delete_post_meta($post_id, self::UNTIL);
        }
        if (!empty($args['reset'])) {
            update_post_meta($post_id, self::SHARES, 0);
        }
        return self::status($post_id);
    }

    public static function unmark(int $post_id): bool {
        delete_post_meta($post_id, self::MARK);
        delete_post_meta($post_id, self::MAX);
        delete_post_meta($post_id, self::UNTIL);
        delete_post_meta($post_id, self::SHARES);
        return true;
    }

    public static function max_shares(int $post_id): int {
        $own = absint(get_post_meta($post_id, self::MAX, true));
        return $own > 0 ? $own : self::default_max();
    }

    public static function shares(int $post_id): int {
        return absint(get_post_meta($post_id, self::SHARES, true));
    }

    public static function until(int $post_id): string {
        return (string) get_post_meta($post_id, self::UNTIL, true);
    }

    public static function last_share(int $post_id): int {
        return absint(get_post_meta($post_id, self::LAST, true));
    }

    /** A post is open while a queue row for it can still go out: recycling it again would double-post. */
    public static function has_open(int $post_id): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sgap_queue WHERE post_id = %d AND status IN ('queued','sending','draft') LIMIT 1",
            $post_id
        ));
    }

    /**
     * Everything the admin, the abilities and the queue decision need about one page.
     * `reason` is empty exactly when the page is ready to recycle.
     */
    public static function status(int $post_id): array {
        $marked = get_post_meta($post_id, self::MARK, true) === '1';
        $shares = self::shares($post_id);
        $max = self::max_shares($post_id);
        $until = self::until($post_id);
        $last = self::last_share($post_id);
        $post = get_post($post_id);
        $published = is_object($post) && ($post->post_status ?? '') === 'publish';
        $today = wp_date('Y-m-d');
        $expired = $until !== '' && $until < $today;
        $full = $shares >= $max;
        $next_ts = $last > 0 ? $last + self::after_days() * DAY_IN_SECONDS : 0;
        $waiting = $next_ts > time();
        $open = $marked && !$expired && !$full && !$waiting ? self::has_open($post_id) : false;

        $reason = '';
        if (!$marked) {
            $reason = 'not marked as evergreen';
        } elseif (!$published) {
            $reason = 'not published';
        } elseif ($expired) {
            $reason = 'expired on ' . $until;
        } elseif ($full) {
            $reason = 'all ' . $max . ' shares used';
        } elseif ($waiting) {
            $reason = 'next share from ' . wp_date('Y-m-d', $next_ts);
        } elseif ($open) {
            $reason = 'a post for this page is still in the queue';
        }

        return [
            'post_id' => $post_id,
            'marked' => $marked,
            'published' => $published,
            'shares' => $shares,
            'max' => $max,
            'until' => $until,
            'last_share' => $last > 0 ? wp_date('Y-m-d H:i:s', $last) : null,
            'next_share' => $next_ts > 0 ? wp_date('Y-m-d', $next_ts) : null,
            'eligible' => $marked && $published && !$expired && !$full && !$waiting && !$open,
            'reason' => $reason
        ];
    }

    /**
     * The marked pages that can be recycled right now, oldest share first.
     * @return int[]
     */
    public static function candidates(int $limit = 0): array {
        $rows = get_posts([
            'post_type' => self::types(),
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_key' => self::MARK,
            'meta_value' => '1'
        ]);
        $ids = [];
        foreach ((array) $rows as $row) {
            $id = is_object($row) ? (int) ($row->ID ?? 0) : (int) $row;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        // Oldest share first, and the id breaks the tie. Two pages that have never been
        // shared have the same last-share time, and without the second key the order is
        // whatever the database happens to return — the rotation then depends on the
        // MySQL version instead of on the shop.
        usort($ids, fn($a, $b) => [self::last_share($a), $a] <=> [self::last_share($b), $b]);
        $ready = array_values(array_filter($ids, fn($id) => self::status($id)['eligible']));
        return $limit > 0 ? array_slice($ready, 0, $limit) : $ready;
    }

    /**
     * One fresh share of one page: a variation written for the channel that is next in
     * rotation, queued through the normal queue (quality gate, preflight, blackout and
     * duplicate guard all apply). A share is counted when the post reaches the queue.
     * @return array|WP_Error
     */
    public static function queue_one(int $post_id) {
        $status = self::status($post_id);
        if (!$status['eligible']) {
            return new WP_Error('sgap_evergreen_not_ready', 'Not ready to recycle: ' . $status['reason'] . '.', ['status' => 409, 'status_detail' => $status]);
        }
        $channels = SGAP_Publer::channels();
        if (!$channels) {
            return new WP_Error('sgap_evergreen_channels', 'No supported channels in Settings → Publer → Channels.', ['status' => 400]);
        }
        $post = get_post($post_id);
        $share = $status['shares'] + 1;
        $channel = $channels[$status['shares'] % count($channels)];
        $angle = ['angle_a', 'angle_b', 'angle_c'][$status['shares'] % 3];
        $history = self::history($post_id);
        $variation = self::variation($post, $channel, $angle, $history);
        if (is_wp_error($variation)) {
            return $variation;
        }
        $body = trim((string) $variation['text']);
        $hashtags = trim((string) ($variation['hashtags'] ?? ''));
        if ($hashtags !== '') {
            $body .= "\n\n" . $hashtags;
        }
        $queued = SGAP_Queue::add([
            'post_id' => $post_id,
            'channel' => $channel,
            'angle' => $angle,
            'content_type' => ($post->post_type ?? '') === 'product' ? 'product' : 'guide',
            'format' => $channel === 'pinterest' ? 'pin' : 'post',
            'destination_url' => (string) get_permalink($post_id),
            'title' => trim((string) $variation['title']) ?: get_the_title($post_id),
            'body' => $body,
            'image_url' => self::image($post_id),
            'scheduled_at' => current_time('mysql')
        ]);
        if (is_wp_error($queued)) {
            return $queued;
        }
        update_post_meta($post_id, self::SHARES, $share);
        update_post_meta($post_id, self::LAST, time());
        delete_transient('sgap_dashboard_stats');
        return [
            'post_id' => $post_id,
            'queue_id' => (int) $queued['id'],
            'channel' => $channel,
            'share' => $share,
            'max' => $status['max'],
            'tracking_url' => $queued['tracking_url'] ?? SGAP_Tracking::url((int) $queued['id']),
            'title' => (string) $variation['title']
        ];
    }

    /**
     * Recycles up to $limit pages now. Returns what went out and why the rest did not,
     * so a cron run and a button press report the same thing.
     */
    public static function run(?int $limit = null): array {
        $report = ['recycled' => 0, 'skipped' => 0, 'errors' => [], 'items' => []];
        if (!self::enabled()) {
            $report['errors'][] = 'Evergreen recycling is off (Settings → Evergreen).';
            return $report;
        }
        if (!SGAP_Publer::channels()) {
            $report['errors'][] = 'No supported channels in Settings → Publer → Channels.';
            return $report;
        }
        $limit = min(self::MAX_PER_RUN, max(1, $limit ?? self::batch()));
        $ready = self::candidates($limit + 5);
        if (!$ready) {
            $report['note'] = 'No evergreen page is due: none is marked, or each one is waiting for its next share, has used its budget or has expired.';
        }
        foreach ($ready as $post_id) {
            if ($report['recycled'] >= $limit) {
                break;
            }
            $result = self::queue_one($post_id);
            if (is_wp_error($result)) {
                $report['skipped']++;
                $report['errors'][] = get_the_title($post_id) . ': ' . $result->get_error_message();
                continue;
            }
            $report['items'][] = $result;
            $report['recycled']++;
        }
        delete_transient('sgap_dashboard_stats');
        update_option('sgap_last_evergreen', ['at' => current_time('mysql')] + $report, false);
        return $report;
    }

    public static function cron(): void {
        if (self::enabled()) {
            self::run();
        }
    }

    /** The last few captions for this page, so the model is told what not to repeat. */
    public static function history(int $post_id, int $limit = 5): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT title, body FROM {$wpdb->prefix}sgap_queue WHERE post_id = %d ORDER BY id DESC LIMIT %d",
            $post_id,
            max(1, min(20, $limit))
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * A fresh caption for one page on one channel. The `sgap_evergreen_variation`
     * filter replaces the AI call (a human editor, another model, or a test).
     * @return array|WP_Error title, text, hashtags, alt_text
     */
    public static function variation($post, string $channel, string $angle, array $history = []) {
        $custom = apply_filters('sgap_evergreen_variation', null, $post, $channel, $angle, $history);
        if (is_array($custom)) {
            return self::normalise($custom, $post);
        }
        $source = SGAP_Generator::source($post);
        $previous = [];
        foreach (array_slice($history, 0, 3) as $row) {
            $text = trim(wp_strip_all_tags((string) ($row['body'] ?? '')));
            if ($text !== '') {
                $previous[] = '- ' . wp_trim_words($text, 35, '…');
            }
        }
        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'text' => ['type' => 'string'],
                'hashtags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'alt_text' => ['type' => 'string']
            ],
            'required' => ['title', 'text'],
            'additionalProperties' => false
        ];
        $prompt = "Write one social post that brings an evergreen page back to an audience that has seen it before.\n\n"
            . "Page: {$source['title']} ({$source['type']})\n"
            . "Channel: {$channel}\n"
            . "Variation: {$angle}\n"
            . "Page summary: {$source['summary']}\n";
        if ($previous) {
            $prompt .= "\nThese captions already ran for this page. Do not reuse their hook, opening or call to action:\n" . implode("\n", $previous) . "\n";
        }
        $prompt .= "\nTake a genuinely different angle: a new opening sentence, a different benefit, and a call to action that is not a copy of the ones above. "
            . "Keep the same facts — do not invent a discount, a product feature or a deadline. Two to four short sentences, no emoji unless the page calls for it.";
        $system = 'You are a careful social media copywriter for a small print-on-demand shop in steampunk and dark academia style. You never invent offers and never repeat a caption.';
        $answer = SGAP_AI::json_call($system, $prompt, $schema, 1200, 'low');
        if (is_wp_error($answer)) {
            return $answer;
        }
        return self::normalise($answer, $post);
    }

    /** @return array|WP_Error */
    private static function normalise(array $answer, $post) {
        $title = trim(sanitize_text_field((string) ($answer['title'] ?? '')));
        $text = trim(sanitize_textarea_field((string) ($answer['text'] ?? '')));
        if ($text === '') {
            return new WP_Error('sgap_evergreen_empty', 'The variation had no caption.');
        }
        $tags = $answer['hashtags'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[\s,]+/', $tags) ?: [];
        }
        $clean = [];
        foreach ((array) $tags as $tag) {
            $tag = ltrim(trim(sanitize_text_field((string) $tag)), '#');
            if ($tag !== '' && preg_match('/^[A-Za-z0-9_]+$/', $tag)) {
                $clean[] = '#' . $tag;
            }
        }
        return [
            'title' => $title !== '' ? $title : get_the_title($post->ID ?? 0),
            'text' => $text,
            'hashtags' => implode(' ', array_slice(array_values(array_unique($clean)), 0, 10)),
            'alt_text' => trim(sanitize_text_field((string) ($answer['alt_text'] ?? '')))
        ];
    }

    private static function image(int $post_id): string {
        if (function_exists('get_the_post_thumbnail_url')) {
            return (string) (get_the_post_thumbnail_url($post_id, 'large') ?: '');
        }
        return '';
    }

    /** Every marked page/product, published or not. @return int[] */
    public static function marked(): array {
        $rows = get_posts([
            'post_type' => self::types(),
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 200,
            'no_found_rows' => true,
            'meta_key' => self::MARK,
            'meta_value' => '1'
        ]);
        $ids = [];
        foreach ((array) $rows as $row) {
            $id = is_object($row) ? (int) ($row->ID ?? 0) : (int) $row;
            // The query already filters on the meta key; the second read makes the list
            // trustworthy even when another plugin alters the query.
            if ($id > 0 && get_post_meta($id, self::MARK, true) === '1') {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** Counts for the admin page and the status ability. */
    public static function summary(): array {
        $marked = self::candidates(0);
        $ids = self::marked();
        $used = 0;
        foreach (array_filter($ids) as $id) {
            $used += self::shares($id);
        }
        return [
            'enabled' => self::enabled(),
            'marked' => count(array_filter($ids)),
            'ready' => count($marked),
            'shares_used' => $used,
            'after_days' => self::after_days(),
            'default_max' => self::default_max(),
            'batch' => self::batch()
        ];
    }

    /* ---- editor metabox ---- */

    public static function metabox(): void {
        add_meta_box('sgap-evergreen', 'Steamgears: Evergreen', [__CLASS__, 'render_metabox'], self::types(), 'side', 'default');
    }

    public static function render_metabox($post): void {
        $id = (int) ($post->ID ?? 0);
        $status = self::status($id);
        wp_nonce_field('sgap_evergreen_meta_' . $id, 'sgap_evergreen_nonce');
        echo '<p><label><input type="checkbox" name="sgap_evergreen" value="1"' . checked($status['marked'], true, false) . '> Recycle this page when it is old</label></p>';
        echo '<p><label>Max shares<br><input type="number" min="0" name="sgap_evergreen_max" value="' . esc_attr((string) absint(get_post_meta($id, self::MAX, true))) . '" class="small-text"></label> '
            . '<span class="description">0 = the setting (' . esc_html((string) self::default_max()) . ')</span></p>';
        echo '<p><label>Do not recycle after<br><input type="date" name="sgap_evergreen_until" value="' . esc_attr($status['until']) . '"></label></p>';
        echo '<p class="description">' . esc_html($status['shares'] . ' of ' . $status['max'] . ' shares used' . ($status['reason'] !== '' ? ' — ' . $status['reason'] : '')) . '</p>';
    }

    public static function save_metabox($post_id, $post = null): void {
        $post_id = (int) $post_id;
        if (!isset($_POST['sgap_evergreen_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['sgap_evergreen_nonce'])), 'sgap_evergreen_meta_' . $post_id)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $post = $post ?: get_post($post_id);
        if (!self::is_supported($post)) {
            return;
        }
        if (empty($_POST['sgap_evergreen'])) {
            self::unmark($post_id);
            return;
        }
        self::mark($post_id, [
            'max' => isset($_POST['sgap_evergreen_max']) ? absint($_POST['sgap_evergreen_max']) : 0,
            'until' => isset($_POST['sgap_evergreen_until']) ? sanitize_text_field(wp_unslash((string) $_POST['sgap_evergreen_until'])) : ''
        ]);
    }
}
