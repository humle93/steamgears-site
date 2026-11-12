<?php
/**
 * Quality gate before queueing: what a person would have caught with one look at
 * the post before pressing publish — a banned word, a link that goes nowhere, a
 * photo nobody described, a caption we already posted on that channel.
 *
 * Called from SGAP_Queue::add(), which is the only way into the queue, so the
 * Studio, AI drafts, the REST API and the Claude tools are all covered by one
 * check. Reads the database; writes nothing and makes no HTTP requests.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Quality {
    /** How many recent posts per channel a new caption is compared against. */
    const RECENT = 40;

    /** Word overlap at or above this means "the same caption in other words". */
    const SIMILARITY = 0.85;

    /**
     * Below this many words a caption is too short to compare: "New drop!" and
     * "New drop" are similar without being a duplicate worth blocking.
     */
    const MIN_WORDS = 8;

    /** Characters of the colliding caption shown in the message. */
    const EXCERPT = 60;

    /** Hosts that only ever exist in examples, so a post carrying one is a mistake. */
    const PLACEHOLDERS = ['example.com', 'example.org', 'example.net', 'localhost', '127.0.0.1', 'yoursite.com', 'yourdomain.com', 'domain.com', 'site.com'];

    /** Link shapes url_to_postid() cannot resolve but that are not dead: archives, feeds, search, the front page. */
    const ARCHIVE_PARTS = ['/category/', '/tag/', '/author/', '/feed', '/wp-json', '/go/', '/search'];

    /** block, warn or off: how much say the gate has. */
    public static function mode(): string {
        $mode = (string) SGAP_Settings::get('quality_gate', 'block');
        return in_array($mode, ['block', 'warn', 'off'], true) ? $mode : 'block';
    }

    /** The banned words, one per line or comma-separated, lowercased and de-duplicated. */
    public static function words(): array {
        $words = [];
        foreach (preg_split('/[\r\n,]+/', (string) SGAP_Settings::get('banned_words', '')) ?: [] as $word) {
            $word = mb_strtolower(trim($word));
            if ($word !== '') {
                $words[$word] = $word;
            }
        }
        return array_values($words);
    }

    /** The text with links, punctuation and case removed, so near-copies look alike. */
    public static function normalize(string $text): string {
        $text = (string) preg_replace('#https?://\S+#iu', ' ', mb_strtolower($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Words of a normalized caption, without duplicates and empties. */
    private static function tokens(string $text): array {
        return array_values(array_unique(array_filter(explode(' ', self::normalize($text)), 'strlen')));
    }

    /** Share of the words two captions have in common. 0.0–1.0. */
    public static function similarity(string $a, string $b): float {
        $left = self::tokens($a);
        $right = self::tokens($b);
        if (!$left || !$right) {
            return 0.0;
        }
        $shared = count(array_intersect($left, $right));
        return round($shared / count(array_unique(array_merge($left, $right))), 3);
    }

    /** The banned words that occur in the text, as written in the list. */
    public static function banned(string $text): array {
        $found = [];
        foreach (self::words() as $word) {
            // Whole words only, so "sale" does not fire on "wholesale" — but a hashtag
            // counts as the word, because #sale is the same word in a different dress.
            if (preg_match('/(?<![\p{L}\p{N}_])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_])/iu', $text)) {
                $found[] = $word;
            }
        }
        return $found;
    }

    /** Every http(s) link in the text, without the punctuation that follows it in a sentence. */
    public static function links(string $text): array {
        preg_match_all('#https?://[^\s<>"\']+#iu', $text, $matches);
        $links = [];
        foreach ($matches[0] ?? [] as $link) {
            $link = rtrim($link, '.,;:!?)]}');
            if ($link !== '') {
                $links[$link] = $link;
            }
        }
        return array_values($links);
    }

    /** The host the site itself is served from. */
    private static function site_host(): string {
        return mb_strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    }

    /**
     * True for links that are not a single post or page: archives, feeds, search,
     * the front page. url_to_postid() answers 0 for those without anything being
     * wrong, so they must not be reported as dead.
     */
    public static function is_archive(string $url): bool {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $query = (string) wp_parse_url($url, PHP_URL_QUERY);
        if ($query !== '') {
            parse_str($query, $args);
            $archive_keys = ['s', 'paged', 'page', 'orderby', 'cat', 'tag', 'author', 'post_type', 'feed', 'preview', 'replytocom', 'embed'];
            if (array_intersect(array_keys($args), $archive_keys) !== []) {
                return true;
            }
        }
        if ($path === '') {
            return true;
        }
        if ($path === '/') {
            // The front page is an archive, unless the query names one post or page.
            parse_str($query, $single);
            return array_intersect(array_keys($single), ['p', 'page_id', 'attachment_id', 'name', 'preview']) === [];
        }
        foreach (self::ARCHIVE_PARTS as $part) {
            if (str_contains($path, $part)) {
                return true;
            }
        }
        return (bool) preg_match('#\.(php|xml|txt|jpg|jpeg|png|webp|pdf)$#i', $path);
    }

    /**
     * What is wrong with this link, or null when it looks usable.
     *
     * Internal links are resolved against WordPress itself instead of being fetched:
     * a link to a page that is not there is a 404 the moment it is posted, and
     * resolving it costs nothing on the tick. External hosts are not probed at all —
     * see the README for why.
     * @return array{code: string, severity: string, message: string}|null
     */
    public static function link_problem(string $url): ?array {
        $host = mb_strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return self::finding('bad_link', 'block', "\"{$url}\" has no domain, so it will not open for anyone.");
        }
        // A link to this site itself is never a placeholder, even when the site runs on localhost (dev, CI).
        if ($host !== self::site_host() && (in_array($host, self::PLACEHOLDERS, true) || (bool) preg_match('/\.(test|invalid|local|example)$/', $host))) {
            return self::finding('placeholder_link', 'block', "\"{$url}\" points at a placeholder ({$host}), so it will not open for anyone.");
        }
        if (str_starts_with($url, 'http://')) {
            return self::finding('insecure_link', 'warn', "\"{$url}\" is http; use https so browsers do not warn about it.");
        }
        if ($host !== self::site_host()) {
            return null;
        }
        $id = (int) url_to_postid($url);
        if ($id > 0) {
            $status = (string) get_post_status($id);
            if ($status !== '' && !in_array($status, ['publish', 'private'], true)) {
                return self::finding('unpublished_link', 'block', "\"{$url}\" points at a post that is {$status}, not published.");
            }
            return null;
        }
        if (self::is_archive($url)) {
            return null;
        }
        return self::finding('unresolved_link', 'warn', "\"{$url}\" points at this site, but no page has that address — check the slug before this goes out.");
    }

    /** '' when the photo is described, otherwise what is missing. Only the media library can answer. */
    public static function alt_problem(string $image_url): string {
        if ($image_url === '') {
            return '';
        }
        $id = (int) attachment_url_to_postid($image_url);
        if ($id <= 0) {
            return ''; // Not in the media library: there is no alt text to read or to miss.
        }
        if (trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) !== '') {
            return '';
        }
        return 'the photo has no alt text, so screen readers and image search see nothing: Media → the image → Alternative text.';
    }

    /** The last posts on this channel, newest first — the field a new caption is compared against. */
    private static function recent(string $channel, int $exclude_id): array {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, body, scheduled_at FROM {$wpdb->prefix}sgap_queue
             WHERE channel = %s AND status <> 'failed' AND id <> %d
             ORDER BY id DESC LIMIT %d",
            $channel, $exclude_id, self::RECENT
        ), ARRAY_A);
    }

    /**
     * The caption already queued or posted on this channel that this one repeats,
     * with how much they overlap — or null when nothing is close enough.
     * @return array{id: int, score: float, excerpt: string, scheduled_at: string}|null
     */
    public static function duplicate(string $channel, string $body, int $exclude_id = 0): ?array {
        if ($channel === '' || count(self::tokens($body)) < self::MIN_WORDS) {
            return null;
        }
        $best = null;
        foreach (self::recent($channel, $exclude_id) as $row) {
            $other = trim((string) ($row['body'] ?? ''));
            if ($other === '') {
                $other = (string) ($row['title'] ?? '');
            }
            $score = self::similarity($body, $other);
            if ($score >= self::SIMILARITY && ($best === null || $score > $best['score'])) {
                $best = [
                    'id' => (int) ($row['id'] ?? 0),
                    'score' => $score,
                    'excerpt' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $other)), 0, self::EXCERPT),
                    'scheduled_at' => (string) ($row['scheduled_at'] ?? '')
                ];
            }
        }
        return $best;
    }

    private static function finding(string $code, string $severity, string $message): array {
        return ['code' => $code, 'severity' => $severity, 'message' => $message];
    }

    /**
     * Everything a person would have caught, with the field it belongs to.
     *
     * 'ok' is false only for blocking problems, and only while the gate is in block
     * mode: a forced post is queued with its problems reported instead of refused.
     * @return array{ok: bool, mode: string, problems: array<int, array{code: string, field: string, severity: string, message: string}>}
     */
    public static function check(array $row, bool $force = false): array {
        $mode = self::mode();
        if ($mode === 'off') {
            return ['ok' => true, 'mode' => $mode, 'problems' => []];
        }
        $problems = [];
        $channel = (string) ($row['channel'] ?? '');
        $body = (string) ($row['body'] ?? '');
        $text = trim((string) ($row['title'] ?? '') . "\n" . $body);

        foreach (self::banned($text) as $word) {
            $problems[] = ['code' => 'banned_word', 'field' => 'body', 'severity' => 'block',
                'message' => "\"{$word}\" is on the banned words list (Settings → General → Banned words)."];
        }
        $links = self::links($body);
        $destination = (string) ($row['destination_url'] ?? '');
        if ($destination !== '') {
            $links[] = $destination;
        }
        foreach (array_unique($links) as $link) {
            $problem = self::link_problem($link);
            if ($problem !== null) {
                $problems[] = ['code' => $problem['code'], 'field' => 'body', 'severity' => $problem['severity'], 'message' => $problem['message']];
            }
        }
        $alt = self::alt_problem((string) ($row['image_url'] ?? ''));
        if ($alt !== '') {
            $problems[] = ['code' => 'missing_alt', 'field' => 'image_url', 'severity' => 'warn', 'message' => ucfirst($alt)];
        }
        $dupe = self::duplicate($channel, $body, (int) ($row['id'] ?? 0));
        if ($dupe !== null) {
            $problems[] = ['code' => 'duplicate_caption', 'field' => 'body', 'severity' => 'block',
                'message' => sprintf('%d %% of this caption already went to %s as post #%d (%s): "%s…".',
                    (int) round($dupe['score'] * 100), $channel, $dupe['id'], $dupe['scheduled_at'], $dupe['excerpt'])];
        }
        $blocking = array_filter($problems, fn($problem) => $problem['severity'] === 'block');
        return [
            'ok' => $mode === 'warn' || !$blocking || $force,
            'mode' => $mode,
            'problems' => $problems
        ];
    }

    /** The blocking problems, for the refusal a caller returns. */
    public static function blocking(array $problems): array {
        return array_values(array_filter($problems, fn($problem) => ($problem['severity'] ?? '') === 'block'));
    }

    /** The problems as one sentence for a refusal, the warning list or the admin. */
    public static function message(array $problems): string {
        $messages = [];
        foreach (array_slice($problems, 0, 3) as $problem) {
            $messages[] = (string) ($problem['message'] ?? '');
        }
        return implode(' ', array_filter($messages));
    }
}
