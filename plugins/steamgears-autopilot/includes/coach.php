<?php
/**
 * P8 — the coach: the measured brief every writing agent reads, and the lessons the site
 * has taught them.
 *
 * Why this exists: each writer started from a fixed prompt and one page's snapshot, so a
 * channel that had burned 120 clicks without an order was chosen again the next day, and a
 * channel that had gone quiet stayed quiet. The coach reads what the site already measured
 * (sgap_events, sgap_ai_drafts, WordPress' own post counts) and turns it into two things: a
 * short brief that goes into the prompts, and a small, dated list of lessons rebuilt once a
 * day. Every number comes from this site; a measurement that does not exist is left out
 * rather than estimated, and the brief says so when there is nothing to report.
 *
 * Nothing here publishes, queues or writes content: it only reads, and its one write is the
 * lesson list in a single non-autoloaded option.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Coach {
    /** The lesson store: one option, rewritten only when a rule or its evidence changed. */
    const OPTION = 'sgap_agent_lessons';
    const CACHE = 'sgap_agent_brief';
    const CRON = 'sgap_coach_learn';
    /** Days of outcomes the brief and the lessons read. */
    const WINDOW = 30;
    /** Clicks a channel or an angle needs before a lesson is drawn from it. */
    const MIN_CLICKS = 25;
    /** A channel whose last publish inside the window is older than this is called silent. */
    const SILENT_DAYS = 14;
    const MAX_LESSONS = 12;
    const CACHE_TTL = HOUR_IN_SECONDS;

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'learn']);
        add_action('init', [__CLASS__, 'schedule']);
    }

    /** Daily recalculation; idempotent, so it is safe to call on every request. */
    public static function schedule(): void {
        if (!class_exists('SGAP_Scheduler')) {
            return;
        }
        if (SGAP_Scheduler::next(self::CRON) === false) {
            SGAP_Scheduler::recurring(self::CRON, DAY_IN_SECONDS, [], HOUR_IN_SECONDS);
        }
    }

    /* ---------------- what the site is ---------------- */

    /**
     * The site's own facts. Every read is guarded, because an install without WooCommerce or
     * without a published product must still get a brief instead of a fatal.
     * @return array{products:int,product_drafts:int,posts:int,currency:string,newest_days:int|null,pending_drafts:int}
     */
    public static function facts(): array {
        $facts = [
            'products' => self::count_type('product', 'publish'),
            'product_drafts' => self::count_type('product', 'draft'),
            'posts' => self::count_type('post', 'publish'),
            'currency' => function_exists('get_woocommerce_currency') ? strtoupper((string) get_woocommerce_currency()) : '',
            'newest_days' => self::newest_days(),
            'pending_drafts' => self::pending_drafts()
        ];
        // A site with another catalogue plugin can supply its own numbers; the shape is merged,
        // so a filter that only knows the product count cannot blank the rest.
        $filtered = apply_filters('sgap_agent_facts', $facts);
        return is_array($filtered) ? array_merge($facts, $filtered) : $facts;
    }

    private static function count_type(string $type, string $status): int {
        if (!function_exists('post_type_exists') || !post_type_exists($type) || !function_exists('wp_count_posts')) {
            return 0;
        }
        $counts = wp_count_posts($type);
        if (!is_object($counts) || !isset($counts->{$status})) {
            return 0;
        }
        return (int) $counts->{$status};
    }

    /** Age in whole days of the newest published page, or null when nothing is published. */
    private static function newest_days(): ?int {
        if (!function_exists('get_posts') || !function_exists('post_type_exists')) {
            return null;
        }
        $types = array_values(array_filter(['post', 'product'], 'post_type_exists'));
        if ($types === []) {
            return null;
        }
        $posts = get_posts([
            'post_type' => $types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true
        ]);
        $post = is_array($posts) && isset($posts[0]) && is_object($posts[0]) ? $posts[0] : null;
        $date = $post !== null ? (string) ($post->post_date ?? $post->post_date_gmt ?? '') : '';
        $at = $date !== '' ? strtotime($date) : 0;
        return $at > 0 ? max(0, (int) floor((time() - $at) / DAY_IN_SECONDS)) : null;
    }

    private static function pending_drafts(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'sgap_ai_drafts';
        if (!class_exists('SGAP_Admin') || !SGAP_Admin::table_exists($table)) {
            return 0;
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
    }

    /* ---------------- what the site has measured ---------------- */

    /**
     * Clicks, orders and revenue per channel and per angle inside the window, folded from one
     * grouped query so a site with many posts still costs one read. Days are counted on the
     * same clock the events are written with (`current_time('mysql')`), never on UTC.
     * @return array{window_days:int,channels:array,angles:array,totals:array}
     */
    public static function outcomes(int $days = self::WINDOW): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $out = [
            'window_days' => $days,
            'channels' => [],
            'angles' => [],
            'totals' => ['published' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0.0]
        ];
        $table = $wpdb->prefix . 'sgap_events';
        if (!class_exists('SGAP_Admin') || !SGAP_Admin::table_exists($table)) {
            return $out;
        }
        $now = (int) strtotime((string) current_time('mysql'));
        $since = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT channel, angle, SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks,
                    SUM(event_type = 'order') AS orders, SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue,
                    MAX(CASE WHEN event_type = 'publish' THEN created_at END) AS last_publish
             FROM {$table} WHERE created_at >= %s GROUP BY channel, angle", $since
        ));
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $channel = sanitize_key((string) ($row->channel ?? ''));
            $angle = sanitize_key((string) ($row->angle ?? ''));
            $published = (int) ($row->published ?? 0);
            $clicks = (int) ($row->clicks ?? 0);
            $orders = (int) ($row->orders ?? 0);
            $revenue = (float) ($row->revenue ?? 0);
            foreach (['totals' => '', 'channels' => $channel, 'angles' => $angle] as $bucket => $key) {
                if ($bucket !== 'totals' && $key === '') {
                    continue;
                }
                if ($bucket === 'totals') {
                    $out['totals']['published'] += $published;
                    $out['totals']['clicks'] += $clicks;
                    $out['totals']['orders'] += $orders;
                    $out['totals']['revenue'] += $revenue;
                    continue;
                }
                $entry = $out[$bucket][$key] ?? ['published' => 0, 'clicks' => 0, 'orders' => 0, 'revenue' => 0.0, 'last_publish' => '', 'days_silent' => null];
                $entry['published'] += $published;
                $entry['clicks'] += $clicks;
                $entry['orders'] += $orders;
                $entry['revenue'] += $revenue;
                $stamp = (string) ($row->last_publish ?? '');
                if ($stamp !== '' && strcmp($stamp, (string) $entry['last_publish']) > 0) {
                    $entry['last_publish'] = $stamp;
                }
                $out[$bucket][$key] = $entry;
            }
        }
        foreach ($out['channels'] as $channel => $entry) {
            $at = $entry['last_publish'] !== '' ? (int) strtotime((string) $entry['last_publish']) : 0;
            $out['channels'][$channel]['days_silent'] = $at > 0 && $now > 0
                ? max(0, (int) floor(($now - $at) / DAY_IN_SECONDS))
                : null;
        }
        uasort($out['channels'], static fn($a, $b) => [$b['orders'], $b['clicks']] <=> [$a['orders'], $a['clicks']]);
        uasort($out['angles'], static fn($a, $b) => [$b['orders'], $b['clicks']] <=> [$a['orders'], $a['clicks']]);
        return $out;
    }

    /* ---------------- what the agents have learned ---------------- */

    /** The stored lessons, newest list order preserved. @return array<int,array{key:string,rule:string,evidence:string,at:string}> */
    public static function lessons(): array {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
    }

    /**
     * Rebuilds the lesson list from the measured outcomes. A lesson is only drawn when the data
     * behind it is there (MIN_CLICKS for a click-based rule), so a quiet week cannot teach the
     * agents something the numbers do not support. `at` is the day the rule was first drawn.
     */
    public static function learn(): array {
        $fresh = self::draw(self::outcomes(), self::facts());
        $stored = self::lessons();
        $seen = [];
        foreach ($stored as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key !== '') {
                $seen[$key] = (string) ($row['at'] ?? '');
            }
        }
        $next = [];
        $added = 0;
        foreach ($fresh as $row) {
            $key = $row['key'];
            if (isset($seen[$key])) {
                $row['at'] = $seen[$key];
            } else {
                $added++;
            }
            $next[$key] = $row;
        }
        $next = array_values(array_slice($next, 0, self::MAX_LESSONS, true));
        $changed = wp_json_encode($next) !== wp_json_encode($stored);
        if ($changed) {
            update_option(self::OPTION, $next, false);
            delete_transient(self::CACHE);
        }
        return ['added' => $added, 'kept' => count($next) - $added, 'total' => count($next), 'changed' => $changed, 'lessons' => $next];
    }

    /** @return array<int,array> The rules the current numbers support, strongest first. */
    private static function draw(array $outcomes, array $facts): array {
        $today = wp_date('Y-m-d');
        $sales = [];
        $warnings = [];
        $money = static fn(float $value, string $currency): string => number_format_i18n($value, 0) . ($currency !== '' ? ' ' . $currency : '');
        $currency = (string) ($facts['currency'] ?? '');

        foreach ($outcomes['channels'] as $channel => $row) {
            if ($row['clicks'] < self::MIN_CLICKS) {
                continue;
            }
            if ($row['orders'] === 0) {
                $warnings[] = ['key' => 'channel_zero:' . $channel, 'at' => $today,
                    'rule' => ucfirst($channel) . ' has no order to show: ' . $row['clicks'] . ' clicks in ' . $outcomes['window_days'] . ' days. Weigh it lower and change the offer before spending more posts on it.',
                    'evidence' => $row['clicks'] . ' clicks, 0 orders, ' . $row['published'] . ' posts'];
            } else {
                $sales[] = ['key' => 'channel_best:' . $channel, 'at' => $today,
                    'rule' => ucfirst($channel) . ' converts: ' . $row['orders'] . ' order(s) from ' . $row['clicks'] . ' clicks. Give it the stronger hooks.',
                    'evidence' => $row['clicks'] . ' clicks, ' . $row['orders'] . ' orders, ' . $money($row['revenue'], $currency)];
            }
        }
        foreach ($outcomes['angles'] as $angle => $row) {
            if ($row['clicks'] < self::MIN_CLICKS) {
                continue;
            }
            if ($row['orders'] === 0) {
                $warnings[] = ['key' => 'angle_zero:' . $angle, 'at' => $today,
                    'rule' => 'The angle "' . $angle . '" has ' . $row['clicks'] . ' clicks and no order in ' . $outcomes['window_days'] . ' days. Write a different hook instead of repeating it.',
                    'evidence' => $row['clicks'] . ' clicks, 0 orders'];
            }
        }
        // A channel/angle pair that sells is the most usable instruction of all, so it goes first.
        foreach ($outcomes['channels'] as $channel => $row) {
            if ($row['orders'] > 0) {
                $sales[] = ['key' => 'pair_best:' . $channel, 'at' => $today,
                    'rule' => ucfirst($channel) . ' sells with the angle it used in this window (' . $row['orders'] . ' order(s)). Reuse that angle there.',
                    'evidence' => $row['clicks'] . ' clicks, ' . $row['orders'] . ' orders'];
            }
        }
        foreach ($outcomes['channels'] as $channel => $row) {
            if ($row['days_silent'] === null || $row['days_silent'] < self::SILENT_DAYS) {
                continue;
            }
            $warnings[] = ['key' => 'channel_silent:' . $channel, 'at' => $today,
                'rule' => ucfirst($channel) . ' has published nothing for ' . $row['days_silent'] . ' days. Check its token or feed before writing more for it.',
                'evidence' => 'last publish ' . $row['last_publish']];
        }
        if ((int) ($facts['pending_drafts'] ?? 0) >= 20) {
            $warnings[] = ['key' => 'drafts_backlog', 'at' => $today,
                'rule' => (int) $facts['pending_drafts'] . ' drafts are waiting for review. Write fewer new ones until the queue is decided.',
                'evidence' => (int) $facts['pending_drafts'] . ' pending drafts'];
        }
        // Sales first, then the warnings; each key appears once.
        $out = [];
        foreach (array_merge($sales, $warnings) as $row) {
            if (!isset($out[$row['key']])) {
                $out[$row['key']] = $row;
            }
        }
        return array_values($out);
    }

    /* ---------------- the brief ---------------- */

    /**
     * The block that goes into every writing prompt. Cached for an hour because a prompt is
     * built on every generation run; a filter can replace it (tests and sites with their own
     * numbers), and an empty string means "nothing measured yet".
     */
    public static function brief(bool $fresh = false): string {
        $supplied = apply_filters('sgap_agent_brief', null);
        if (is_string($supplied)) {
            return trim($supplied);
        }
        if (!$fresh) {
            $cached = get_transient(self::CACHE);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }
        $text = self::compose();
        set_transient(self::CACHE, $text, self::CACHE_TTL);
        return $text;
    }

    /** The brief as a prompt appendix, or '' when there is nothing to say. */
    public static function block(): string {
        $brief = self::brief();
        return $brief === '' ? '' : "\n\n" . $brief . "\n";
    }

    private static function compose(): string {
        $facts = self::facts();
        $outcomes = self::outcomes();
        $lessons = self::lessons();
        $currency = (string) ($facts['currency'] ?? '');
        $money = static fn(float $value): string => number_format_i18n($value, 0) . ($currency !== '' ? ' ' . $currency : '');

        $lines = ['WHAT THIS SITE HAS MEASURED (' . wp_date('j M Y') . ', last ' . $outcomes['window_days'] . ' days)'];
        $site = [];
        if ((int) ($facts['products'] ?? 0) > 0) $site[] = (int) $facts['products'] . ' published products';
        if ((int) ($facts['product_drafts'] ?? 0) > 0) $site[] = (int) $facts['product_drafts'] . ' product drafts';
        if ((int) ($facts['posts'] ?? 0) > 0) $site[] = (int) $facts['posts'] . ' published posts';
        if ($currency !== '') $site[] = 'currency ' . $currency;
        if (is_int($facts['newest_days'] ?? null)) $site[] = 'newest page ' . (int) $facts['newest_days'] . ' day(s) old';
        if ((int) ($facts['pending_drafts'] ?? 0) > 0) $site[] = (int) $facts['pending_drafts'] . ' drafts waiting for review';
        $lines[] = 'Site: ' . ($site === [] ? 'no published content measured yet' : implode(', ', $site)) . '.';

        $channels = [];
        foreach (array_slice($outcomes['channels'], 0, 6, true) as $channel => $row) {
            $channels[] = $channel . ': ' . $row['clicks'] . ' clicks, ' . $row['orders'] . ' orders'
                . ($row['revenue'] > 0 ? ', ' . $money($row['revenue']) : '');
        }
        $lines[] = 'Channels: ' . ($channels === [] ? 'no clicks or orders measured in the window.' : implode(' · ', $channels) . '.');

        if ($outcomes['angles'] !== []) {
            $keys = array_keys($outcomes['angles']);
            $best = $keys[0];
            $worst = $keys[count($keys) - 1];
            $lines[] = 'Angles: best "' . $best . '" (' . $outcomes['angles'][$best]['orders'] . ' order(s) from '
                . $outcomes['angles'][$best]['clicks'] . ' clicks)'
                . ($worst !== $best ? '; weakest "' . $worst . '" (' . $outcomes['angles'][$worst]['orders'] . ' order(s) from '
                    . $outcomes['angles'][$worst]['clicks'] . ' clicks).' : '.');
        }

        if ($lessons !== []) {
            $lines[] = 'Lessons this site has taught us (respect them):';
            foreach (array_slice($lessons, 0, 6) as $lesson) {
                $lines[] = '- ' . (string) ($lesson['rule'] ?? '');
            }
        } else {
            $lines[] = 'Lessons: none yet — the numbers above are all we know.';
        }
        $lines[] = 'Rules: use only these numbers. Do not invent metrics, prices, discounts or stock, and do not mention this brief to the reader.';
        return implode("\n", $lines);
    }

    /** Everything the ability, the card and a test need, in one call. */
    public static function summary(): array {
        $outcomes = self::outcomes();
        $lessons = self::lessons();
        return [
            'facts' => self::facts(),
            'outcomes' => $outcomes,
            'lessons' => $lessons,
            'brief' => self::brief()
        ];
    }

    /** The dashboard card: what the agents currently know, and where it came from. */
    public static function card(): void {
        $summary = self::summary();
        $lessons = $summary['lessons'];
        $channels = $summary['outcomes']['channels'];
        SGAP_UI::card_open('What the agents have learned', 'welcome-learn-more');
        echo '<p class="description" style="margin-top:0">Every writer — the daily posts, the campaign agent and the Studio — gets these measured facts in its prompt. The list is rebuilt every day from this site\'s own clicks, orders and publish history; nothing is estimated.</p>';
        echo '<div class="sgap-metrics">'
            . SGAP_UI::metric('Lessons', count($lessons), count($lessons) === 1 ? '1 rule in the prompts' : 'rules in the prompts')
            . SGAP_UI::metric('Channels measured', count($channels))
            . SGAP_UI::metric('Window', $summary['outcomes']['window_days'] . ' days')
            . '</div>';
        if ($lessons === []) {
            echo '<p>' . SGAP_UI::pill('Nothing measured yet', 'warn') . ' A lesson needs at least ' . (int) self::MIN_CLICKS
                . ' clicks on a channel or an angle before it is drawn. Until then the brief carries the site facts only.</p>';
        } else {
            echo '<ul class="sgap-lessons">';
            foreach ($lessons as $lesson) {
                echo '<li><strong>' . esc_html((string) ($lesson['rule'] ?? '')) . '</strong>'
                    . '<br><small>' . esc_html((string) ($lesson['evidence'] ?? '')) . ' · since ' . esc_html((string) ($lesson['at'] ?? '')) . '</small></li>';
            }
            echo '</ul>';
        }
        echo '<p class="description"><strong>The brief in the prompt:</strong></p>'
            . '<div class="sgap-text">' . nl2br(esc_html($summary['brief'])) . '</div>';
        SGAP_UI::card_close();
    }
}
