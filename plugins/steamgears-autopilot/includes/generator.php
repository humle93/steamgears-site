<?php
/**
 * Writes social posts for the site's own posts and products with an AI model,
 * once a day from WP-Cron. Posts land under AI Drafts for approval, or go
 * straight to the queue when Full Auto is on. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_AI {
    const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    const ANTHROPIC_VERSION = '2026-10-21';
    const DEFAULT_MODEL = 'claude-opus-5-5';
    /** Models that accept the server-side refusal fallback. */
    const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'];
    const DEFAULT_BASE_URL = 'https://api.aimlapi.com/v1';
    /** Default model on AIMLAPI (Claude through AIMLAPI's OpenAI-compatible API). */
    const AIMLAPI_MODEL = 'anthropic/claude-opus-5.5';

    public static function provider(): string {
        $provider = (string) SGAP_Settings::get('ai_provider', 'anthropic');
        return in_array($provider, ['anthropic', 'aimlapi', 'openai_compatible'], true) ? $provider : 'anthropic';
    }

    /** AIMLAPI and other OpenAI-compatible providers use /chat/completions. */
    public static function uses_chat_completions(): bool {
        return self::provider() !== 'anthropic';
    }

    /** Model from Settings, else the provider's default ('' for a generic OpenAI-compatible API). */
    public static function model(): string {
        $model = trim((string) SGAP_Settings::get('ai_model'));
        if ($model !== '') {
            return $model;
        }
        return ['anthropic' => self::DEFAULT_MODEL, 'aimlapi' => self::AIMLAPI_MODEL][self::provider()] ?? '';
    }

    public static function base_url(): string {
        $base = trim((string) SGAP_Settings::get('ai_base_url'));
        return rtrim(self::provider() === 'aimlapi' || $base === '' ? self::DEFAULT_BASE_URL : $base, '/');
    }

    public static function provider_name(): string {
        return ['anthropic' => 'Anthropic', 'aimlapi' => 'AIMLAPI'][self::provider()] ?? self::base_url();
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
        return "Write one post per channel for this page on steamgears.eu.\n\n"
            . "Title: {$source['title']}\n"
            . "Type: {$source['type']}\n"
            . "Summary: {$source['summary']}\n\n"
            . "Channels and their rules:\n{$rules}\n"
            . 'Return exactly one item per channel. "title" is a short headline (used as the Pinterest pin title), '
            . '"text" is the post itself without hashtags, "hashtags" are separate (each starting with #), '
            . '"alt_text" describes the page\'s main image for screen readers.';
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

    /** Text of an Anthropic Messages response. @return string|WP_Error */
    public static function anthropic_text(array $body) {
        if (($body['stop_reason'] ?? '') === 'refusal') {
            return new WP_Error('sgap_ai_refusal', 'The model declined to write this post.');
        }
        if (($body['stop_reason'] ?? '') === 'max_tokens') {
            return new WP_Error('sgap_ai_truncated', 'The model response was cut off (max_tokens).');
        }
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                return (string) $block['text'];
            }
        }
        return new WP_Error('sgap_ai_empty', 'The model returned no text.');
    }

    /** Generates posts for one source page. @return array|WP_Error List of posts (see parse_posts). */
    public static function generate(array $source, array $channels) {
        $key = (string) SGAP_Settings::get('ai_api_key');
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Set an AI API key in Settings.');
        }
        $prompt = self::prompt($source, $channels);
        if (self::uses_chat_completions()) {
            $text = self::openai_compatible($key, $prompt);
        } else {
            $text = self::anthropic($key, $prompt, $channels);
        }
        if (is_wp_error($text)) {
            return $text;
        }
        $posts = self::parse_posts($text, $channels);
        return $posts ?: new WP_Error('sgap_ai_parse', 'The model answer contained no usable posts.');
    }

    /** @return string|WP_Error */
    private static function anthropic(string $key, string $prompt, array $channels) {
        $model = self::model();
        $body = [
            'model' => $model,
            'max_tokens' => 16000,
            'system' => self::SYSTEM,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            // Short marketing copy: low effort is plenty and keeps cost down.
            'output_config' => [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => self::schema($channels)]
            ]
        ];
        $headers = [
            'x-api-key' => $key,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json'
        ];
        if (in_array($model, self::FALLBACK_MODELS, true)) {
            // If a safety classifier declines, let the API retry on a suitable model.
            $body['fallbacks'] = 'default';
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }
        $response = wp_remote_post(self::ANTHROPIC_URL, ['timeout' => 120, 'headers' => $headers, 'body' => wp_json_encode($body)]);
        $decoded = self::decode($response, 'Anthropic');
        return is_wp_error($decoded) ? $decoded : self::anthropic_text($decoded);
    }

    /** For AIMLAPI, OpenAI or any other /chat/completions endpoint. @return string|WP_Error */
    private static function openai_compatible(string $key, string $prompt) {
        $model = self::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for the OpenAI-compatible provider.');
        }
        $base = self::base_url();
        $response = wp_remote_post($base . '/chat/completions', [
            'timeout' => 120,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(self::chat_body($model, $prompt))
        ]);
        $decoded = self::decode($response, self::provider_name());
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        $text = $decoded['choices'][0]['message']['content'] ?? '';
        if ((!is_string($text) || $text === '') && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
            return new WP_Error('sgap_ai_truncated', 'The model response was cut off (max_tokens).');
        }
        return is_string($text) && $text !== '' ? $text : new WP_Error('sgap_ai_empty', 'The model returned no text.');
    }

    /** Request body for /chat/completions. */
    public static function chat_body(string $model, string $prompt): array {
        $body = [
            'model' => $model,
            'max_tokens' => 8000,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM . ' Answer with JSON only: {"posts": [{"channel", "title", "text", "hashtags": [], "alt_text"}]}.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        if (self::provider() === 'aimlapi') {
            // Claude on AIMLAPI thinks first; short marketing copy needs little, and thinking
            // that eats the token budget leaves an empty answer.
            $body['reasoning_effort'] = 'low';
        }
        return $body;
    }

    /**
     * One structured request to the configured AI: returns the decoded JSON object.
     * Anthropic gets a strict json_schema; chat-completions providers get JSON mode
     * plus the schema in the instructions. Used by the Content Studio.
     * @return array|WP_Error
     */
    public static function json_call(string $system, string $prompt, array $schema, int $max_tokens = 16000, string $effort = 'medium') {
        $key = (string) SGAP_Settings::get('ai_api_key');
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Set an AI API key in Settings → AI content.');
        }
        $model = self::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for the OpenAI-compatible provider.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        if (self::uses_chat_completions()) {
            $body = [
                'model' => $model,
                'max_tokens' => $max_tokens,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system . "\n\nAnswer with one JSON object only, matching this JSON schema:\n" . wp_json_encode($schema)],
                    ['role' => 'user', 'content' => $prompt]
                ]
            ];
            if (self::provider() === 'aimlapi') {
                $body['reasoning_effort'] = $effort;
            }
            $response = wp_remote_post(self::base_url() . '/chat/completions', [
                'timeout' => 280,
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode($body)
            ]);
            $decoded = self::decode($response, self::provider_name());
            if (is_wp_error($decoded)) {
                return $decoded;
            }
            $text = (string) ($decoded['choices'][0]['message']['content'] ?? '');
            if ($text === '' && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
                return new WP_Error('sgap_ai_truncated', 'The model response was cut off (max_tokens).');
            }
        } else {
            $body = [
                'model' => $model,
                'max_tokens' => $max_tokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'output_config' => ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]]
            ];
            $headers = ['x-api-key' => $key, 'anthropic-version' => self::ANTHROPIC_VERSION, 'content-type' => 'application/json'];
            if (in_array($model, self::FALLBACK_MODELS, true)) {
                $body['fallbacks'] = 'default';
                $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            }
            $response = wp_remote_post(self::ANTHROPIC_URL, ['timeout' => 280, 'headers' => $headers, 'body' => wp_json_encode($body)]);
            $decoded = self::decode($response, 'Anthropic');
            if (is_wp_error($decoded)) {
                return $decoded;
            }
            $text = self::anthropic_text($decoded);
            if (is_wp_error($text)) {
                return $text;
            }
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
        return array_slice($candidates, 0, max(0, $count));
    }

    public static function source(WP_Post $post): array {
        $summary = has_excerpt($post) ? $post->post_excerpt : $post->post_content;
        $summary = wp_trim_words(wp_strip_all_tags(strip_shortcodes($summary)), 120, '…');
        return [
            'title' => get_the_title($post),
            'type' => $post->post_type === 'product' ? 'product' : 'guide/blog post',
            'summary' => $summary
        ];
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
        foreach ($posts as $generated) {
            $wpdb->insert($wpdb->prefix . 'sgap_ai_drafts', [
                'post_id' => $post->ID,
                'channel' => $generated['channel'],
                'content_type' => $post->post_type === 'product' ? 'product' : 'guide',
                'format' => $generated['channel'] === 'pinterest' ? 'pin' : 'post',
                'title' => $generated['title'] ?: get_the_title($post),
                'body' => $generated['text'],
                'hashtags' => $generated['hashtags'],
                'alt_text' => $generated['alt_text'],
                'status' => 'pending',
                'ai_model' => substr(SGAP_AI::model(), 0, 40),
                'created_at' => current_time('mysql')
            ]);
            $report['drafts']++;
            if ($full_auto) {
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
