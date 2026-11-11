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
    /**
     * OpenRouter: one OpenAI-compatible endpoint in front of hundreds of models from many
     * providers. The address is fixed, like AIMLAPI's, so a typo in the base URL cannot point
     * the key at a different service. There is deliberately no default model: OpenRouter's
     * catalogue changes week by week, so the model is picked from the live list instead of
     * being guessed here.
     */
    const OPENROUTER_URL = 'https://openrouter.ai/api/v1';

    /**
     * The backup: its own key and its own model. `ai_api_key` is "one key at a time" for whichever
     * provider is chosen, so reusing it here would either overwrite the primary's key or send the
     * wrong credential to OpenRouter. The model has no default for the same reason P1.23 has none —
     * OpenRouter's catalogue changes, so a guessed slug ages into a 404.
     */
    const FALLBACK_KEY = 'openrouter_api_key';
    const FALLBACK_MODEL = 'openrouter_model';
    /** Its own catalogue cache, so a backup list and a provider list cannot overwrite each other. */
    const FALLBACK_CATALOG = 'sgap_ai_catalog_openrouter_backup';

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
     * The headers of every OpenRouter call. One function, because the key test and the failover
     * must send the same request: a test that proves a header the real call never sends proves
     * nothing, and that is how the two drifted apart before 7.70.1.
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

    /** What the last call did. Set only when the backup answered; reset on every new call. */
    private static array $fallback = [];

    /**
     * The chosen provider. OpenRouter is the default since 7.72.0 (P10): a site that never saved a
     * provider starts there, and so does an unknown value. A saved choice — AIMLAPI included — is
     * kept until the owner switches, so an update never cuts off a working key.
     */
    public static function provider(): string {
        $provider = (string) SGAP_Settings::get('ai_provider', 'openrouter');
        return in_array($provider, ['anthropic', 'aimlapi', 'openai_compatible', 'openrouter'], true) ? $provider : 'openrouter';
    }

    /**
     * The key that reaches OpenRouter: the main key when OpenRouter is the provider, else the
     * backup's own key. '' when neither is saved. Used by the images, which have no key of their own
     * on OpenRouter.
     */
    public static function openrouter_key(): string {
        return self::provider() === 'openrouter' ? self::api_key() : self::fallback_key();
    }

    /** AIMLAPI and other OpenAI-compatible providers use /chat/completions. */
    public static function uses_chat_completions(): bool {
        return self::provider() !== 'anthropic';
    }

    /** Model from Settings, else the provider's default ('' for a generic OpenAI-compatible API). */
    public static function model(): string {
        $model = trim((string) SGAP_Settings::get('ai_model'));
        return $model !== '' ? $model : self::default_model();
    }

    /**
     * The model the provider itself recommends: what an empty Settings field means. OpenRouter
     * has none on purpose — a hardcoded slug ages, and its live catalogue is the source.
     */
    public static function default_model(): string {
        return ['anthropic' => self::DEFAULT_MODEL, 'aimlapi' => self::AIMLAPI_MODEL][self::provider()] ?? '';
    }

    /**
     * True when the provider's error is about the model *name* — unknown, retired or not
     * offered to this key. Network, auth, rate-limit and credit errors are never model
     * errors, and must be reported as they are instead of being retried.
     */
    public static function model_rejected(string $message): bool {
        $message = strtolower($message);
        if (strpos($message, 'model') === false) {
            return false;
        }
        foreach ([
            'not found', 'does not exist', 'not exist', 'unknown model', 'not available', 'unavailable',
            'not offered', 'no such model', 'invalid model', 'not a valid model', 'model_not_found', 'not supported'
        ] as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** The model to retry with when the asked name is rejected — '' when no retry is right. */
    public static function model_fallback(string $asked, WP_Error $error): string {
        if ($asked === '' || !in_array(self::provider(), ['aimlapi', 'openai_compatible'], true)) {
            return '';
        }
        $default = self::default_model();
        if ($default === '' || $default === $asked || !self::model_rejected($error->get_error_message())) {
            return '';
        }
        return $default;
    }

    /**
     * Clears a stored model the provider rejected, so the provider default applies from now
     * on. Only when the stored value is still the rejected one: a value the owner changed
     * in the meantime is theirs and is left alone.
     */
    public static function repair_model(string $asked): bool {
        $repaired = self::default_model();
        if ($asked === '' || $repaired === '' || trim((string) SGAP_Settings::get('ai_model')) !== $asked) {
            return false;
        }
        // Store the concrete model, not an empty field: what is used is then visible in Settings.
        SGAP_Settings::set('ai_model', $repaired);
        return trim((string) SGAP_Settings::get('ai_model')) === $repaired;
    }

    /**
     * The model ids an endpoint lists right now, cached for 12 hours: the Settings dropdown and
     * the save-time check then cost at most one request per half-day. A site whose provider is
     * reached through a proxy can replace the list with the `sgap_ai_model_catalog` filter.
     *
     * $provider is empty for the configured provider and 'openrouter_backup' for the backup's own
     * list, which is why the cache name is per provider rather than per service.
     * @return string[]
     */
    public static function catalog(bool $fresh = false, string $provider = ''): array {
        $provider = $provider !== '' ? $provider : self::provider();
        $supplied = apply_filters('sgap_ai_model_catalog', null, $provider);
        if (is_array($supplied)) {
            return self::catalog_ids($supplied);
        }
        $cache = $provider === 'openrouter_backup' ? self::FALLBACK_CATALOG : 'sgap_ai_catalog_' . $provider;
        $cached = get_site_transient($cache);
        if (!$fresh && is_array($cached)) {
            return $cached;
        }
        $ids = self::fetch_catalog($provider);
        // A failure is cached briefly too, so an unreachable provider never slows the settings page.
        set_site_transient($cache, $ids, $ids !== [] ? 12 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
        return $ids;
    }

    /**
     * Model ids from a /models answer — AIMLAPI and OpenAI use data[].id, Anthropic data[].id,
     * and a plain list is accepted too. Deduped and sorted, because AIMLAPI repeats ids.
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

    /** @return string[] Empty when the provider cannot be asked (no key yet, or offline). */
    private static function fetch_catalog(string $provider): array {
        if ($provider === 'aimlapi') {
            // AIMLAPI lists its catalogue without a key. The URL is fixed, never ai_base_url.
            $response = wp_remote_get(self::DEFAULT_BASE_URL . '/models', ['timeout' => 20]);
        } elseif ($provider === 'openrouter' || $provider === 'openrouter_backup') {
            // OpenRouter lists its catalogue without a key too, so the backup model can be picked
            // before the backup key exists — the list is not the credential.
            $response = wp_remote_get(self::OPENROUTER_URL . '/models', ['timeout' => 20]);
        } else {
            $key = self::api_key();
            if ($key === '') {
                return [];
            }
            $response = $provider === 'anthropic'
                ? wp_remote_get('https://api.anthropic.com/v1/models?limit=100', [
                    'timeout' => 20,
                    'headers' => ['x-api-key' => $key, 'anthropic-version' => self::ANTHROPIC_VERSION]
                ])
                : wp_remote_get(self::base_url() . '/models', ['timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . $key]]);
        }
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) ? self::catalog_ids($body) : [];
    }

    /**
     * The primary key, trimmed. A key copied out of a browser can carry a leading space or a line
     * break, and "Bearer sk-or-…\n" is not a bearer token to any gateway: the answer reads like a
     * missing header instead of a rejected key (measured 10/11 against OpenRouter).
     */
    public static function api_key(): string {
        return self::bearer((string) SGAP_Settings::get('ai_api_key'));
    }

    /** The OpenRouter backup key, or '' when none is saved. */
    public static function fallback_key(): string {
        return self::bearer((string) SGAP_Settings::get(self::FALLBACK_KEY));
    }

    /** The model the backup asks, or '' when the backup is switched off. */
    public static function fallback_model(): string {
        return trim((string) SGAP_Settings::get(self::FALLBACK_MODEL));
    }

    /**
     * Ready means a key, a model, a primary that speaks /chat/completions — and a primary that is
     * not OpenRouter itself, because a backup to the same service is not a backup.
     */
    public static function fallback_ready(): bool {
        return self::uses_chat_completions() && self::provider() !== 'openrouter'
            && self::fallback_key() !== '' && self::fallback_model() !== '';
    }

    /**
     * Only a provider failure may start the backup. A 2xx answer — even an empty or truncated one —
     * means the provider *did* answer, so calling a second service would pay twice for the same
     * request. 400/404/422 are our own mistakes and are never retried on another service either.
     */
    public static function should_fallback(WP_Error $error): bool {
        $status = (int) ($error->get_error_data()['status'] ?? 0);
        if ($status === 0) {
            return true; // transport: DNS, timeout, TLS, or wp_remote_post refusing the call
        }
        return in_array($status, [401, 403, 402, 429], true) || $status >= 500;
    }

    /** What the last call did: empty unless the backup answered. */
    public static function last_fallback(): array {
        return self::$fallback;
    }

    /** One sentence for the owner — or '' when the primary provider answered. */
    public static function fallback_note(): string {
        if (self::$fallback === []) {
            return '';
        }
        return "\n\nThe primary provider failed (" . self::$fallback['reason'] . '), so this answer came from the OpenRouter backup on `'
            . self::$fallback['model'] . '`. Nothing was switched: the next call tries the primary again.';
    }

    /**
     * The one path to /chat/completions. A model name the provider does not know is retried once
     * on the provider default and Settings is repaired; if that also fails at the provider level,
     * or any other provider failure occurs, the OpenRouter backup answers instead. Every other
     * error is returned unchanged, so nothing loops and nothing hides.
     * @return array|WP_Error
     */
    public static function chat_completions(string $key, array $body, int $timeout = 120, string $who = '') {
        self::$fallback = [];
        $who = $who !== '' ? $who : self::provider_name();
        $asked = (string) ($body['model'] ?? '');
        $decoded = self::post_chat(self::base_url(), $key, $body, $timeout, $who);
        if (!is_wp_error($decoded)) {
            return $decoded;
        }
        $retry = self::model_fallback($asked, $decoded);
        if ($retry !== '') {
            $body['model'] = $retry;
            $decoded = self::post_chat(self::base_url(), $key, $body, $timeout, $who);
            if (!is_wp_error($decoded)) {
                self::repair_model($asked);
                return $decoded;
            }
        }
        if (!self::fallback_ready() || !self::should_fallback($decoded)) {
            return $decoded;
        }
        return self::post_backup($body, $timeout, $decoded);
    }

    /** Kept as the name the chat loop and the tests already use. */
    public static function chat_call(string $key, array $body, int $timeout = 120, string $who = '') {
        return self::chat_completions($key, $body, $timeout, $who);
    }

    /** @return array|WP_Error One POST to a /chat/completions endpoint, decoded. */
    private static function post_chat(string $base, string $key, array $body, int $timeout, string $who) {
        return self::decode(wp_remote_post($base . '/chat/completions', [
            'timeout' => $timeout,
            'headers' => ['Authorization' => 'Bearer ' . self::bearer($key), 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body)
        ]), $who);
    }

    /**
     * The backup request: the same body on OpenRouter, with the backup's key and model and without
     * `reasoning_effort`, which only AIMLAPI knows. Both failures are named in the error, primary
     * first, so the owner reads what actually happened instead of a single vague message.
     * @return array|WP_Error
     */
    private static function post_backup(array $body, int $timeout, WP_Error $primary) {
        $model = self::fallback_model();
        $body['model'] = $model;
        unset($body['reasoning_effort']);
        $decoded = self::decode(self::openrouter_post(self::fallback_key(), $body, $timeout), 'OpenRouter backup');
        $status = (int) ($primary->get_error_data()['status'] ?? 0);
        if (is_wp_error($decoded)) {
            return new WP_Error(
                $primary->get_error_code(),
                $primary->get_error_message() . ' The OpenRouter backup did not answer either: ' . $decoded->get_error_message(),
                ['status' => $status, 'backup_status' => (int) ($decoded->get_error_data()['status'] ?? 0)]
            );
        }
        self::$fallback = [
            'at' => current_time('mysql'),
            'model' => $model,
            'status' => $status,
            'reason' => $primary->get_error_message()
        ];
        if (class_exists('SGAP_Diagnostics')) {
            // The status and the model only: never the key, never the request body.
            SGAP_Diagnostics::log('ai-fallback', 'The backup answered (' . $model . '): ' . $primary->get_error_message(), ['status' => $status]);
        }
        return $decoded;
    }

    public static function base_url(): string {
        // A provider with its own fixed host ignores the free-text base URL: the key belongs to
        // that service, so the address cannot be redirected by a typo in Settings.
        $fixed = ['aimlapi' => self::DEFAULT_BASE_URL, 'openrouter' => self::OPENROUTER_URL][self::provider()] ?? '';
        if ($fixed !== '') {
            return $fixed;
        }
        $base = trim((string) SGAP_Settings::get('ai_base_url'));
        return rtrim($base === '' ? self::DEFAULT_BASE_URL : $base, '/');
    }

    public static function provider_name(): string {
        return ['anthropic' => 'Anthropic', 'aimlapi' => 'AIMLAPI', 'openrouter' => 'OpenRouter'][self::provider()] ?? self::base_url();
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
        return "Write one post per channel for this page on steamgears.eu.\n\n"
            . "Title: {$source['title']}\n"
            . "Type: {$source['type']}\n"
            . "Summary: {$source['summary']}\n"
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
        $key = self::api_key();
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
            return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for ' . self::provider_name() . '.');
        }
        $decoded = self::chat_completions($key, self::chat_body($model, $prompt), 120);
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
        $key = self::api_key();
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Set an AI API key in Settings → AI content.');
        }
        $model = self::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for ' . self::provider_name() . '.');
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
            $decoded = self::chat_completions($key, $body, 280);
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
