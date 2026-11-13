<?php
/**
 * Writes social posts for the site's own posts and products with an AI model,
 * once a day from WP-Cron. Posts land under AI Drafts for approval, or go
 * straight to the queue when Full Auto is on. Loaded from steamgears-autopilot.php.
 *
 * Since 7.85.0 there is exactly one AI provider: OpenRouter. One OpenAI-compatible
 * endpoint in front of hundreds of models from many providers, reached with one key.
 * There is no second provider, no backup and no free-model retry: a failure is reported
 * as it is instead of being hidden behind another service.
 */

if (!defined('ABSPATH')) exit;

class SGAP_AI {
    /**
     * OpenRouter's fixed address. It is a constant, not a setting: a typo in a base URL
     * cannot point the key at a different service.
     */
    const OPENROUTER_URL = 'https://openrouter.ai/api/v1';

    /**
     * A key as it has to look inside an `Authorization` header. A paste from a browser can carry
     * a line break, a non-breaking space or a zero-width character, and a header value holding one
     * is refused or truncated by cURL — the gateway then answers as if no header had been sent at
     * all, which is exactly the 401 `Missing Authentication header` measured 10/11. Trimming the
     * ends is not enough (`trim()` leaves an inner newline), so every invisible and control
     * character is removed once, here, for every caller.
     */
    public static function bearer(string $key): string {
        $clean = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{2028}\x{2029}]/u', '', $key);
        if (!is_string($clean)) {
            $clean = $key; // invalid UTF-8: the byte-wise strip below still applies
        }
        return (string) preg_replace('/[\x00-\x20\x7F]/', '', $clean);
    }

    /**
     * The headers of every OpenRouter call. One function, because the key test and the real
     * request must send the same headers: a test that proves a header the real call never
     * sends proves nothing.
     */
    public static function openrouter_headers(string $key): array {
        return [
            'Authorization' => 'Bearer ' . self::bearer($key),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url('/'),
            'X-OpenRouter-Title' => 'Steamgears Autopilot'
        ];
    }

    /** One POST to OpenRouter's /chat/completions with the shared headers, undecoded. */
    public static function openrouter_post(string $key, array $body, int $timeout = 30) {
        return wp_remote_post(self::OPENROUTER_URL . '/chat/completions', [
            'timeout' => $timeout,
            'headers' => self::openrouter_headers($key),
            'body' => wp_json_encode($body)
        ]);
    }

    /**
     * Did anything on this site take the Authorization header out before the request left?
     * WordPress passes the final arguments through `http_request_args`; applying that filter to a
     * copy of the request we just built shows what would actually be sent, and names the callbacks
     * that removed it — so the owner is told which filter to turn off instead of being sent to
     * openrouter.ai/keys for a key that was never the problem.
     *
     * @return array{stripped: bool, filters: string[]}
     */
    public static function stripped_by(string $key): array {
        $args = ['timeout' => 30, 'headers' => self::openrouter_headers($key), 'body' => '{}'];
        $final = apply_filters('http_request_args', $args, self::OPENROUTER_URL . '/chat/completions');
        if (!is_array($final)) {
            // A filter replaced the arguments wholesale: not ours to judge, and not a header strip.
            return ['stripped' => false, 'filters' => []];
        }
        foreach ((array) ($final['headers'] ?? []) as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0 && trim((string) $value) !== '') {
                return ['stripped' => false, 'filters' => []];
            }
        }
        return ['stripped' => true, 'filters' => self::http_filter_names('http_request_args')];
    }

    /**
     * Callback names registered on an HTTP filter, for a message only. Never a stored value:
     * a key cannot end up in an error the owner pastes somewhere.
     *
     * @return string[]
     */
    public static function http_filter_names(string $tag): array {
        $hooks = $GLOBALS['wp_filter'][$tag]->callbacks ?? null;
        if (!is_array($hooks)) {
            return [];
        }
        $names = [];
        foreach ($hooks as $priority) {
            foreach ((array) $priority as $entry) {
                $fn = $entry['function'] ?? null;
                if (is_string($fn)) {
                    $names[] = $fn;
                } elseif (is_array($fn) && count($fn) === 2) {
                    $names[] = (is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0]) . '::' . (string) $fn[1];
                } elseif ($fn instanceof Closure) {
                    $ref = new ReflectionFunction($fn);
                    $names[] = 'a closure in ' . basename((string) $ref->getFileName()) . ':' . $ref->getStartLine();
                }
            }
        }
        return array_values(array_unique(array_filter($names)));
    }

    /**
     * The provider name, kept as one function for the screens and the status tools so the
     * wording cannot drift. There is only OpenRouter.
     */
    public static function provider(): string {
        return 'openrouter';
    }

    /** Model from Settings. Empty means nothing is picked yet — OpenRouter has no default. */
    public static function model(): string {
        return trim((string) SGAP_Settings::get('ai_model'));
    }

    /**
     * The model ids OpenRouter lists right now, cached for 12 hours: the Settings dropdown and
     * the save-time check then cost at most one request per half-day. A site behind a proxy can
     * replace the list with the `sgap_ai_model_catalog` filter.
     * @return string[]
     */
    public static function catalog(bool $fresh = false): array {
        $supplied = apply_filters('sgap_ai_model_catalog', null);
        if (is_array($supplied)) {
            return self::catalog_ids($supplied);
        }
        $cached = get_site_transient('sgap_ai_catalog_openrouter');
        if (!$fresh && is_array($cached)) {
            return $cached;
        }
        $ids = self::fetch_catalog();
        // A failure is cached briefly too, so an unreachable endpoint never slows the settings page.
        set_site_transient('sgap_ai_catalog_openrouter', $ids, $ids !== [] ? 12 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
        return $ids;
    }

    /**
     * Model ids from a /models answer — OpenRouter uses data[].id, and a plain list is accepted
     * too. Deduped and sorted so hundreds of ids stay navigable in one dropdown.
     * @return string[]
     */
    public static function catalog_ids(array $body): array {
        $rows = isset($body['data']) || isset($body['models']) ? (array) ($body['data'] ?? $body['models']) : $body;
        $ids = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : (is_string($row) ? $row : '');
            $id = trim($id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids, SORT_STRING);
        return $ids;
    }

    /** @return string[] Empty when OpenRouter cannot be asked (offline, or a refused answer). */
    private static function fetch_catalog(): array {
        // OpenRouter lists its catalogue without a key: the list is not the credential.
        $response = wp_remote_get(self::OPENROUTER_URL . '/models', ['timeout' => 20]);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? self::catalog_ids($body) : [];
    }

    /**
     * The key, stripped of invisible characters. A key copied out of a browser can carry a
     * leading space or a line break, and "Bearer sk-or-…\n" is not a bearer token to any
     * gateway: the answer reads like a missing header instead of a rejected key.
     */
    public static function api_key(): string {
        return self::bearer((string) SGAP_Settings::get('ai_api_key'));
    }

    public static function base_url(): string {
        return self::OPENROUTER_URL;
    }

    public static function provider_name(): string {
        return 'OpenRouter';
    }

    const SYSTEM = 'You write social media posts for Steamgears (steamgears.eu), a shop and blog for steampunk '
        . 'costumes, accessories and DIY guides. Voice: enthusiastic, knowledgeable, a touch of Victorian flair, '
        . 'never cheesy or pushy. Write in English. Never invent prices, discounts, stock levels or facts that are '
        . 'not in the source. Never put a URL in the text: the link is attached separately.';

    /** Per-channel guidance appended to the prompt. */
    const CHANNEL_RULES = [
        'facebook' => '1-3 short paragraphs, conversational, end with a question or call to action. 0-3 hashtags.',
        'instagram' => 'Hook in the first line, 2-4 short lines, emojis welcome. Say "link in bio". 8-15 relevant hashtags.',
        'pinterest' => 'Title max 100 characters with the main keyword first. Description 2-3 keyword-rich sentences, max 450 characters. 3-6 hashtags.',
        'linkedin' => 'Professional but warm, 2-4 short paragraphs, focus on craft and creativity. 0-3 hashtags.',
        'twitter' => 'Max 230 characters including hashtags. One punchy sentence plus 1-2 hashtags.',
        'threads' => 'Max 400 characters, casual. 0-2 hashtags.',
        'bluesky' => 'Max 250 characters, casual. 0-2 hashtags.',
        'mastodon' => 'Max 400 characters. 2-4 CamelCase hashtags.',
        'telegram' => '2-3 short lines for a channel update. No hashtags.',
        'google' => 'Google Business update: 1-2 plain sentences, no hashtags, no emojis.'
    ];

    /** JSON schema the model must answer with. */
    public static function schema(array $channels): array {
        return [
            'type' => 'object',
            'properties' => [
                'posts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'channel' => ['type' => 'string', 'enum' => array_values($channels)],
                            'title' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'hashtags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'alt_text' => ['type' => 'string']
                        ],
                        'required' => ['channel', 'title', 'text', 'hashtags', 'alt_text'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'required' => ['posts'],
            'additionalProperties' => false
        ];
    }

    public static function prompt(array $source, array $channels): string {
        $rules = '';
        foreach ($channels as $channel) {
            $rules .= "- {$channel}: " . (self::CHANNEL_RULES[$channel] ?? 'Short and engaging.') . "\n";
        }
        // P6: a campaign run states why this page is being promoted now. The goal shapes the
        // angle only — the SYSTEM rule against inventing facts still applies to it.
        $goal = trim((string) ($source['campaign_goal'] ?? ''));
        $keyword = trim((string) ($source['keyword'] ?? ''));
        return "Write one post per channel for this page on steamgears.eu.\n\n"
            . "Title: {$source['title']}\n"
            . "Type: {$source['type']}\n"
            . "Summary: {$source['summary']}\n"
            . ($keyword !== '' ? "Search phrase people use to find this page (measured by Command Center): \"{$keyword}\". Use it naturally once in the text, and as a hashtag if it reads well; do not stuff it.\n" : '')
            . ($goal !== '' ? "Campaign goal (shapes the angle; do not state it as a new fact or promise anything the page does not say): {$goal}\n" : '')
            . "\n"
            . "Channels and their rules:\n{$rules}\n"
            . 'Return exactly one item per channel. "title" is a short headline (used as the Pinterest pin title), '
            . '"text" is the post itself without hashtags, "hashtags" are separate (each starting with #), '
            . '"alt_text" describes the page\'s main image for screen readers.'
            // P8: the measured brief. The writer may only use these numbers, and the block
            // already says so — a fixed prompt plus one page's snapshot was the old blind spot.
            . (class_exists('SGAP_Coach') ? SGAP_Coach::block() : '');
    }

    /** Keeps only well-formed posts for the requested channels, one per channel. */
    public static function parse_posts(string $json, array $channels): array {
        $data = json_decode($json, true);
        // Some models wrap the JSON in a ```json fence or a sentence: use the outermost object.
        if (!is_array($data) && preg_match('/\{.*\}/s', $json, $m)) {
            $data = json_decode($m[0], true);
        }
        $posts = [];
        foreach ((array) ($data['posts'] ?? []) as $post) {
            $channel = is_array($post) ? (string) ($post['channel'] ?? '') : '';
            if (!in_array($channel, $channels, true) || isset($posts[$channel]) || trim((string) ($post['text'] ?? '')) === '') {
                continue;
            }
            $hashtags = array_filter(array_map(
                fn($tag) => '#' . ltrim(preg_replace('/\s+/', '', (string) $tag), '#'),
                (array) ($post['hashtags'] ?? [])
            ), fn($tag) => $tag !== '#');
            $posts[$channel] = [
                'channel' => $channel,
                'title' => sanitize_text_field((string) ($post['title'] ?? '')),
                'text' => sanitize_textarea_field((string) $post['text']),
                'hashtags' => implode(' ', array_unique($hashtags)),
                'alt_text' => sanitize_text_field((string) ($post['alt_text'] ?? ''))
            ];
        }
        return array_values($posts);
    }

    /** Generates posts for one source page. @return array|WP_Error List of posts (see parse_posts). */
    public static function generate(array $source, array $channels) {
        $key = self::api_key();
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Add your OpenRouter key under Settings → Keys.');
        }
        $text = self::completion($key, self::prompt($source, $channels), count($channels));
        if (is_wp_error($text)) {
            return $text;
        }
        $posts = self::parse_posts($text, $channels);
        return $posts ?: new WP_Error('sgap_ai_parse', 'The model answer contained no usable posts.');
    }

    /** One /chat/completions request that must answer with JSON posts. @return string|WP_Error */
    private static function completion(string $key, string $prompt, int $channels = 0) {
        $model = self::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Pick an OpenRouter model under Settings → AI & images.');
        }
        $decoded = self::chat_completions($key, self::chat_body($model, $prompt, $channels), 120);
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        $text = $decoded['choices'][0]['message']['content'] ?? '';
        if ((!is_string($text) || $text === '') && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
            return new WP_Error('sgap_ai_truncated', 'The model response was cut off (max_tokens).');
        }
        return is_string($text) && $text !== '' ? $text : new WP_Error('sgap_ai_empty', 'The model returned no text.');
    }

    /**
     * Output tokens for one page: one short post per channel plus room for the JSON and
     * a little thinking. OpenRouter reserves credit for the whole max_tokens up front, so
     * a fixed 8000/16000 made a low balance refuse calls that needed a tenth of it.
     * 0 channels = the old ceiling (callers that do not know the count).
     */
    public static function token_budget(int $channels): int {
        if ($channels <= 0) {
            return 8000;
        }
        return max(2500, min(8000, 1500 + 700 * $channels));
    }

    /** Request body for /chat/completions. */
    public static function chat_body(string $model, string $prompt, int $channels = 0): array {
        return [
            'model' => $model,
            'max_tokens' => self::token_budget($channels),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM . ' Answer with JSON only: {"posts": [{"channel", "title", "text", "hashtags": [], "alt_text"}]}.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
    }

    /**
     * The one path to OpenRouter. The answer is returned decoded; an error is returned as it
     * came, with the status it carried — nothing is retried on another service and nothing is
     * hidden, because there is no second provider to hide behind.
     * @return array|WP_Error
     */
    public static function chat_completions(string $key, array $body, int $timeout = 120, string $who = 'OpenRouter') {
        return self::decode(self::openrouter_post($key, $body, $timeout), $who);
    }

    /** Kept as the name the chat loop and the tests already use. */
    public static function chat_call(string $key, array $body, int $timeout = 120, string $who = 'OpenRouter') {
        return self::chat_completions($key, $body, $timeout, $who);
    }

    /**
     * One structured request to OpenRouter: JSON mode plus the schema in the instructions.
     * Used by the Content Studio and the other writers.
     * @return array|WP_Error
     */
    public static function json_call(string $system, string $prompt, array $schema, int $max_tokens = 16000) {
        $key = self::api_key();
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Add your OpenRouter key under Settings → Keys.');
        }
        $model = self::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Pick an OpenRouter model under Settings → AI & images.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $body = [
            'model' => $model,
            'max_tokens' => $max_tokens,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system . "\n\nAnswer with one JSON object only, matching this JSON schema:\n" . wp_json_encode($schema)],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        $decoded = self::chat_completions($key, $body, 280);
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        $text = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if ($text === '' && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
            return new WP_Error('sgap_ai_truncated', 'The model response was cut off (max_tokens).');
        }
        $data = json_decode($text, true);
        if (!is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }
        return is_array($data) ? $data : new WP_Error('sgap_ai_parse', 'The model did not answer with valid JSON.');
    }

    /** @return array|WP_Error */
    public static function decode($response, string $who) {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($body)) {
            $message = is_array($body) ? (string) ($body['error']['message'] ?? 'HTTP ' . $code) : 'HTTP ' . $code;
            return new WP_Error('sgap_ai_http', "{$who}: {$message}", ['status' => $code]);
        }
        return $body;
    }
}

class SGAP_Generator {
    const META = '_sgap_promoted_at';

    public static function init(): void {
        add_action(SGAP_Install::GENERATE_CRON, [__CLASS__, 'cron']);
        // The dashboard's progress bar runs generation one page per request.
        add_action('wp_ajax_sgap_gen_plan', [__CLASS__, 'ajax_plan']);
        add_action('wp_ajax_sgap_gen_one', [__CLASS__, 'ajax_one']);
        add_action('wp_ajax_sgap_gen_done', [__CLASS__, 'ajax_done']);
    }

    private static function ajax_guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_generate', 'nonce');
    }

    /** Which pages today's run will write for. */
    public static function ajax_plan(): void {
        self::ajax_guard();
        if (!SGAP_Publer::channels()) {
            wp_send_json_error('No supported channels in Settings → Publer → Channels.');
        }
        $pages = array_map(fn(WP_Post $p) => ['id' => $p->ID, 'title' => get_the_title($p)], self::pick((int) SGAP_Settings::get('posts_per_day', 2)));
        wp_send_json_success(['pages' => $pages, 'channels' => SGAP_Publer::channels()]);
    }

    /** Writes the posts for one page. */
    public static function ajax_one(): void {
        self::ajax_guard();
        $post = get_post(isset($_POST['post_id']) ? absint($_POST['post_id']) : 0);
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error('That page is not published.');
        }
        $report = ['sources' => 0, 'drafts' => 0, 'queued' => 0, 'errors' => []];
        self::write_for($post, SGAP_Publer::channels(), SGAP_Settings::enabled('full_auto_enabled'), $report);
        wp_send_json_success($report);
    }

    /** Stores the totals like run() does and leaves the summary for the dashboard. */
    public static function ajax_done(): void {
        self::ajax_guard();
        $report = ['sources' => 0, 'drafts' => 0, 'queued' => 0, 'errors' => []];
        foreach (['sources', 'drafts', 'queued'] as $key) {
            $report[$key] = isset($_POST[$key]) ? absint($_POST[$key]) : 0;
        }
        $errors = isset($_POST['errors']) ? (array) wp_unslash($_POST['errors']) : [];
        $report['errors'] = array_values(array_map('sanitize_text_field', array_slice($errors, 0, 20)));
        delete_transient('sgap_dashboard_stats');
        update_option('sgap_last_generate', ['at' => current_time('mysql')] + $report, false);
        set_transient('sgap_run_result_' . get_current_user_id(), self::summary($report), 5 * MINUTE_IN_SECONDS);
        wp_send_json_success();
    }

    /** The message shown after a run. */
    public static function summary(array $r): string {
        return "Generated from {$r['sources']} pages: {$r['drafts']} drafts, {$r['queued']} queued."
            . ($r['drafts'] > $r['queued'] ? ' Review them under Steamgears → AI Drafts.' : '')
            . ($r['sources'] === 0 ? ' No page needed a post: every published post/product was promoted within the cooldown.' : '')
            . ($r['errors'] ? "\nErrors:\n- " . implode("\n- ", $r['errors']) : '');
    }

    public static function cron(): void {
        if (SGAP_Settings::enabled('autopilot_enabled')) {
            self::run();
        }
    }

    /** Site posts/products not promoted within the cooldown, oldest promotion first. */
    public static function pick(int $count): array {
        $types = array_values(array_filter(['post', 'product'], 'post_type_exists'));
        $cooldown = max(1, (int) SGAP_Settings::get('repost_cooldown_days', 30));
        $posts = get_posts([
            'post_type' => $types,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true
        ]);
        $cutoff = time() - $cooldown * DAY_IN_SECONDS;
        $candidates = array_filter($posts, fn($post) => (int) get_post_meta($post->ID, self::META, true) < $cutoff);
        // Never promoted first, then the longest ago; newest posts win ties.
        usort($candidates, fn($a, $b) => (int) get_post_meta($a->ID, self::META, true) <=> (int) get_post_meta($b->ID, self::META, true));
        // 7.82.0: pages Command Center measured as worth a push (position 4-20, new articles) go
        // first, in its order; the rest of the day's slots follow the old rule.
        $first = class_exists('SGAP_Priorities') ? SGAP_Priorities::due() : [];
        $ids = array_map(fn($p) => $p->ID, $first);
        $rest = array_values(array_filter($candidates, fn($p) => !in_array($p->ID, $ids, true)));
        return array_slice(array_merge($first, $rest), 0, max(0, $count));
    }

    public static function source(WP_Post $post): array {
        $summary = has_excerpt($post) ? $post->post_excerpt : $post->post_content;
        $summary = wp_trim_words(wp_strip_all_tags(strip_shortcodes($summary)), 120, '…');
        $source = [
            'title' => get_the_title($post),
            'type' => $post->post_type === 'product' ? 'product' : 'guide/blog post',
            'summary' => $summary
        ];
        $keyword = class_exists('SGAP_Priorities') ? SGAP_Priorities::keyword_for((int) $post->ID) : '';
        if ($keyword !== '') {
            $source['keyword'] = $keyword;
        }
        return $source;
    }

    /**
     * Writes posts for today's picks. Returns counts and any errors, so the admin
     * "Generate now" button can report what happened.
     */
    public static function run(?int $count = null): array {
        global $wpdb;
        $report = ['sources' => 0, 'drafts' => 0, 'queued' => 0, 'errors' => []];
        $channels = SGAP_Publer::channels();
        if (!$channels) {
            $report['errors'][] = 'No supported channels in Settings → Publer → Channels.';
            return $report;
        }
        $full_auto = SGAP_Settings::enabled('full_auto_enabled');
        foreach (self::pick($count ?? (int) SGAP_Settings::get('posts_per_day', 2)) as $post) {
            self::write_for($post, $channels, $full_auto, $report);
        }
        delete_transient('sgap_dashboard_stats');
        update_option('sgap_last_generate', ['at' => current_time('mysql')] + $report, false);
        return $report;
    }

    /** Asks the AI for one page's posts and stores them as drafts (queued in Full auto). Adds to $report. */
    public static function write_for(WP_Post $post, array $channels, bool $full_auto, array &$report): void {
        global $wpdb;
        $report['sources']++;
        $posts = SGAP_AI::generate(self::source($post), $channels);
        if (is_wp_error($posts)) {
            $report['errors'][] = get_the_title($post) . ': ' . $posts->get_error_message();
            return;
        }
        update_post_meta($post->ID, self::META, time());
        // 7.83.0: the AI editor reads every post before it may reach the queue. No editor answer =
        // the posts wait for a person even in Full auto; a low score = rejected with the reason.
        $editor = class_exists('SGAP_Editor') && SGAP_Editor::enabled() ? SGAP_Editor::review($posts, self::source($post)) : null;
        foreach ($posts as $generated) {
            $verdict = $editor !== null ? SGAP_Editor::verdict($editor, (string) $generated['channel']) : ['action' => 'approve', 'note' => ''];
            $status = $verdict['action'] === 'reject' ? 'rejected' : 'pending';
            if ($verdict['action'] === 'reject') {
                $report['rejected'] = (int) ($report['rejected'] ?? 0) + 1;
            } elseif ($verdict['action'] === 'hold') {
                $report['held'] = (int) ($report['held'] ?? 0) + 1;
            }
            $wpdb->insert($wpdb->prefix . 'sgap_ai_drafts', [
                'post_id' => $post->ID,
                'channel' => $generated['channel'],
                'content_type' => $post->post_type === 'product' ? 'product' : 'guide',
                'format' => $generated['channel'] === 'pinterest' ? 'pin' : 'post',
                'title' => $generated['title'] ?: get_the_title($post),
                'body' => $generated['text'],
                'hashtags' => $generated['hashtags'],
                'alt_text' => $generated['alt_text'],
                'gate_report' => $verdict['note'],
                'status' => $status,
                'ai_model' => substr(SGAP_AI::model(), 0, 40),
                'created_at' => current_time('mysql')
            ]);
            if ($status === 'rejected') {
                continue;
            }
            $report['drafts']++;
            if ($full_auto && $verdict['action'] === 'approve') {
                $queued = SGAP_Queue::from_draft((int) $wpdb->insert_id);
                if (is_wp_error($queued)) {
                    $report['errors'][] = $queued->get_error_message();
                } else {
                    $report['queued']++;
                }
            }
        }
    }
}
