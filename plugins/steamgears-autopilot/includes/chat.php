<?php
/**
 * AI Chat in wp-admin: talk to the configured AI (AIMLAPI, Anthropic or any
 * OpenAI-compatible API) about the autopilot. The AI can use the plugin's own
 * abilities as tools (status, drafts, queue, generate, publish, settings, health …),
 * with the logged-in administrator's permissions. History is kept per user.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Chat {
    const META = 'sgap_chat_history';
    const MAX_HISTORY = 40;
    const MAX_ROUNDS = 6;
    /** Tools handed to the model directly; the rest stay reachable through run-tool. */
    const MAX_BOUND = 80;
    /** Add or change chat tools from another plugin: add_filter('sgap_chat_tools', fn($tools) => $tools + [...]); */
    const TOOLS_FILTER = 'sgap_chat_tools';
    /** Ring buffer of the last calls, so a provider rejection can be read without guessing. */
    const LOG_CACHE = 'sgap_chat_tool_log';
    const LOG_KEEP = 3;
    /** Abilities the chat may not use: they change the site beyond the autopilot. */
    const EXCLUDED = ['manage-plugin', 'enable-full-access', 'update', 'run-cron', 'update-settings'];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('admin_menu', [__CLASS__, 'catalog_menu'], 21);
        add_action('wp_ajax_sgap_chat', [__CLASS__, 'ajax_send']);
        add_action('wp_ajax_sgap_chat_clear', [__CLASS__, 'ajax_clear']);
        add_action('wp_ajax_sgap_chat_tools', [__CLASS__, 'ajax_tools']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'AI Chat', 'AI Chat', 'manage_options', 'sgap-chat', [__CLASS__, 'page'], 1);
    }

    /* ---- tools ---- */

    /**
     * Every registered ability, normalised — the chat's own catalogue, this plugin's MCP
     * tools, and whatever WordPress core, WooCommerce or another plugin registered.
     * @return array<int, array{name: string, short: string, category: string, description: string, readonly: bool, ability: object}>
     */
    public static function available(): array {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }
        $out = [];
        foreach (wp_get_abilities() as $ability) {
            if (!is_object($ability) || !method_exists($ability, 'get_name')) {
                continue;
            }
            $full = (string) $ability->get_name();
            if ($full === '') {
                continue;
            }
            $prefix = strpos($full, '/') !== false ? (string) strstr($full, '/', true) : '';
            $declared = method_exists($ability, 'get_category') ? trim((string) $ability->get_category()) : '';
            $category = $declared !== '' ? $declared : $prefix;
            $short = $prefix !== '' ? substr($full, strlen($prefix) + 1) : $full;
            $out[] = [
                'name' => $full,
                'short' => $short,
                'category' => (string) $category,
                'description' => (string) (method_exists($ability, 'get_description') ? $ability->get_description() : ''),
                'readonly' => self::is_readonly($ability),
                'ability' => $ability,
            ];
        }
        return $out;
    }

    /** True when an ability says, in its own metadata, that it only reads. */
    public static function is_readonly($ability): bool {
        if (!method_exists($ability, 'get_meta')) {
            return false;
        }
        $meta = $ability->get_meta();
        $annotations = is_array($meta) ? ($meta['annotations'] ?? []) : [];
        return is_array($annotations) && !empty($annotations['readonly']) && empty($annotations['destructive']);
    }

    /**
     * name => [description, JSON schema, runner]. Bound tools are the model's shortcut:
     * this plugin's own abilities, the read-only MCP tools, and every other read-only
     * ability on the site — up to MAX_BOUND. Everything else stays reachable through the
     * find/describe/run meta tools, so a 300-tool site does not blow up every request.
     */
    public static function tools(): array {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }
        $own = SGAP_Abilities::CATEGORY;
        $site = class_exists('SGAP_MCP_Tools') ? SGAP_MCP_Tools::CATEGORY : '';
        $site_readonly = $site !== '' ? SGAP_MCP_Tools::readonly_names() : [];
        $ranked = [];
        foreach (self::available() as $entry) {
            $name = $entry['short'];
            if (in_array($name, self::EXCLUDED, true)) {
                continue;
            }
            if ($entry['category'] === $own) {
                $rank = 1;
            } elseif ($site !== '' && $entry['category'] === $site) {
                if (!in_array($entry['short'], $site_readonly, true)) {
                    continue;
                }
                $rank = 2;
            } elseif ($entry['readonly']) {
                $rank = 3;
            } else {
                continue;
            }
            $ranked[] = [$rank, $entry];
        }
        usort($ranked, static fn($a, $b) => $a[0] <=> $b[0]);
        $tools = [];
        $max_bound = max(1, (int) apply_filters('sgap_chat_max_bound', self::MAX_BOUND));
        foreach ($ranked as [, $entry]) {
            if (count($tools) >= $max_bound) {
                break;
            }
            $schema = method_exists($entry['ability'], 'get_input_schema') ? $entry['ability']->get_input_schema() : null;
            if (empty($schema) || ($schema['type'] ?? 'object') !== 'object') {
                $schema = ['type' => 'object', 'properties' => new stdClass()];
            } elseif (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            $label = $entry['description'] !== '' ? $entry['description']
                : (method_exists($entry['ability'], 'get_label') ? (string) $entry['ability']->get_label() : $entry['name']);
            $tools[self::unique_name($entry['short'], $tools)] = [$label, $schema, $entry['ability']];
        }
        $tools += self::meta_tools();
        $filtered = apply_filters(self::TOOLS_FILTER, $tools);
        return is_array($filtered) ? self::normalise($filtered) : $tools;
    }

    /** The three tools that make the rest of the site's catalogue reachable. */
    private static function meta_tools(): array {
        return [
            'find-tools' => [
                'Search every ability and MCP tool on this site by keyword. Use it when you need to know whether a tool exists before acting; it also returns the total count.',
                ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Word to look for in the name, description or category.'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20]
                ]],
                new SGAP_Chat_Runner([__CLASS__, 'find_tools'])
            ],
            'describe-tool' => [
                'Full name, category, description and input schema of one tool, by name or short name.',
                ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'minLength' => 1]], 'required' => ['name']],
                new SGAP_Chat_Runner([__CLASS__, 'describe_tool'])
            ],
            'run-tool' => [
                'Run any registered ability or MCP tool by name with an input object — for tools that are not already bound here. Refuses the tools the chat may not use.',
                ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1],
                    'input' => ['type' => 'object', 'description' => 'Arguments for the tool, matching its schema.']
                ], 'required' => ['name']],
                new SGAP_Chat_Runner([__CLASS__, 'run_named'])
            ],
        ];
    }

    /** find-tools: keyword search across everything registered on the site. */
    public static function find_tools($input = null): array {
        $input = is_array($input) ? $input : [];
        $query = strtolower(trim((string) ($input['query'] ?? '')));
        $limit = max(1, min(50, (int) ($input['limit'] ?? 20)));
        $all = self::available();
        $hits = [];
        foreach ($all as $entry) {
            $haystack = strtolower($entry['name'] . ' ' . $entry['description'] . ' ' . $entry['category']);
            if ($query !== '' && strpos($haystack, $query) === false) {
                continue;
            }
            if (in_array($entry['short'], self::EXCLUDED, true)) {
                continue;
            }
            $hits[] = ['name' => $entry['name'], 'category' => $entry['category'], 'readonly' => $entry['readonly'], 'description' => $entry['description']];
        }
        $total = count($hits);
        return ['total_available' => count($all), 'matched' => $total, 'tools' => array_slice($hits, 0, $limit)];
    }

    /** describe-tool: one tool's schema, by full or short name. */
    public static function describe_tool($input = null) {
        $input = is_array($input) ? $input : [];
        $want = (string) ($input['name'] ?? '');
        foreach (self::available() as $entry) {
            if ($want === $entry['name'] || $want === $entry['short']) {
                return [
                    'name' => $entry['name'],
                    'category' => $entry['category'],
                    'readonly' => $entry['readonly'],
                    'description' => $entry['description'],
                    'input_schema' => method_exists($entry['ability'], 'get_input_schema') ? $entry['ability']->get_input_schema() : null
                ];
            }
        }
        return ['error' => "No tool named {$want}."];
    }

    /** run-tool: execute any registered ability, with the chat's exclusions still applied. */
    public static function run_named($input = null) {
        $input = is_array($input) ? $input : [];
        $want = (string) ($input['name'] ?? '');
        $args = is_array($input['input'] ?? null) ? $input['input'] : [];
        foreach (self::available() as $entry) {
            if ($want !== $entry['name'] && $want !== $entry['short']) {
                continue;
            }
            if (in_array($entry['short'], self::EXCLUDED, true)) {
                return ['error' => "The chat may not use {$entry['name']}."];
            }
            if (!method_exists($entry['ability'], 'execute')) {
                return ['error' => "The tool {$entry['name']} has no runner."];
            }
            $schema = method_exists($entry['ability'], 'get_input_schema') ? $entry['ability']->get_input_schema() : null;
            $result = $entry['ability']->execute($args ?: ($schema ? [] : null));
            return is_wp_error($result) ? ['error' => $result->get_error_message()] : $result;
        }
        return ['error' => "No tool named {$want}."];
    }

    /**
     * A tool name every provider accepts. AIMLAPI (Gemini) rejects anything that does not
     * start with a letter or underscore and is not alphanumeric, underscore, dot, colon or
     * dash — so `core/list posts` or a leading digit must never reach the API.
     */
    public static function safe_name(string $name): string {
        $name = (string) preg_replace('/[^A-Za-z0-9_.:-]+/', '_', trim($name));
        $name = trim($name, '_.:-');
        if ($name === '') {
            $name = 'tool';
        }
        if (!preg_match('/^[A-Za-z_]/', $name)) {
            $name = 't_' . $name;
        }
        return substr($name, 0, 64);
    }

    /** safe_name plus a counter, so two abilities with the same short name never collide. */
    public static function unique_name(string $name, array $taken): string {
        $base = self::safe_name($name);
        if (!isset($taken[$base])) {
            return $base;
        }
        for ($i = 2; $i < 1000; $i++) {
            $candidate = substr($base, 0, 60) . '__' . $i;
            if (!isset($taken[$candidate])) {
                return $candidate;
            }
        }
        return 'tool_' . substr(md5($base . microtime(true)), 0, 12);
    }

    /**
     * Keeps only entries shaped like a tool, so a filter or a changed registry cannot
     * hand the model something it cannot call.
     */
    private static function normalise(array $tools): array {
        $clean = [];
        foreach ($tools as $name => $tool) {
            $name = (string) $name;
            if ($name === '' || !is_array($tool) || !isset($tool[0], $tool[1]) || !is_array($tool[1])) {
                continue;
            }
            $schema = $tool[1];
            if (($schema['type'] ?? 'object') !== 'object') {
                $schema = ['type' => 'object', 'properties' => new stdClass()];
            } elseif (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            $clean[self::unique_name($name, $clean)] = [(string) $tool[0], $schema, $tool[2] ?? null];
        }
        return $clean;
    }

    /** md5 of the sorted tool names; changes exactly when the catalogue changes. */
    public static function tools_signature(?array $tools = null): string {
        $names = array_keys($tools ?? self::tools());
        sort($names);
        return md5(implode(',', $names));
    }

    /** What the page and the refresh button report. */
    public static function tool_catalog(): array {
        $tools = self::tools();
        $names = array_keys($tools);
        sort($names);
        $all = self::available();
        $categories = [];
        foreach ($all as $entry) {
            $key = $entry['category'] !== '' ? $entry['category'] : '(none)';
            $categories[$key] = ($categories[$key] ?? 0) + 1;
        }
        ksort($categories);
        return [
            'count' => count($tools),
            'available' => count($all),
            'categories' => $categories,
            'names' => $names,
            'signature' => self::tools_signature($tools)
        ];
    }

    /** Runs one tool; always returns a JSON string for the model. */
    public static function run_tool(string $name, $args, array $tools): string {
        if (!isset($tools[$name])) {
            return wp_json_encode(['error' => "Unknown tool {$name}."]);
        }
        $ability = $tools[$name][2] ?? null;
        if (!is_object($ability) || !method_exists($ability, 'execute')) {
            return wp_json_encode(['error' => "The tool {$name} has no runner in this installation."]);
        }
        $args = is_array($args) ? $args : [];
        $input = $args ?: ((method_exists($ability, 'get_input_schema') && $ability->get_input_schema()) ? [] : null);
        $result = $ability->execute($input);
        if (is_wp_error($result)) {
            return wp_json_encode(['error' => $result->get_error_message()]);
        }
        $json = (string) wp_json_encode($result);
        return strlen($json) > 12000 ? substr($json, 0, 12000) . '… (truncated)' : $json;
    }

    /* ---- diagnostics ---- */

    /**
     * Which tool sits at the index the provider complained about.
     * AIMLAPI answers e.g. `tools[0].function_declarations[30].name: Invalid function name.`
     */
    public static function culprit(string $error, array $names): ?array {
        // Gemini's message contains both `tools[0]` and `function_declarations[30]`; the
        // tool index is the second one, so it must be matched first.
        if (!preg_match('/function_declarations\[(\d+)\]/', $error, $m)
            && !preg_match('/tools\[(\d+)\]/', $error, $m)) {
            return null;
        }
        $index = (int) $m[1];
        return ['index' => $index, 'name' => (string) ($names[$index] ?? '(outside the list)')];
    }

    /** Keeps the last few provider calls (names only — never a key) and resolves the culprit. */
    public static function remember_call(string $provider, string $model, int $round, array $names, ?string $error = null): array {
        $names = array_values(array_map('strval', $names));
        $entry = [
            'at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'provider' => $provider,
            'model' => $model,
            'round' => $round,
            'count' => count($names),
            'names' => array_slice($names, 0, 300),
            'error' => $error,
            'culprit' => $error !== null ? self::culprit($error, $names) : null
        ];
        $log = get_site_transient(self::LOG_CACHE);
        $log = is_array($log) ? $log : [];
        array_unshift($log, $entry);
        set_site_transient(self::LOG_CACHE, array_slice($log, 0, self::LOG_KEEP), DAY_IN_SECONDS);
        if ($error !== null && defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('SGAP chat ' . $provider . ': ' . $error . ($entry['culprit'] ? ' [tool #' . $entry['culprit']['index'] . ' ' . $entry['culprit']['name'] . ']' : ''));
        }
        return $entry;
    }

    /** The recent calls, newest first. */
    public static function call_log(): array {
        $log = get_site_transient(self::LOG_CACHE);
        return is_array($log) ? $log : [];
    }

    /* ---- conversation ---- */

    public static function system_prompt(): string {
        return 'You are the assistant inside the Steamgears Autopilot WordPress plugin on steamgears.eu, a shop and blog for steampunk '
            . 'costumes, accessories and DIY guides. You help the site owner run the social media autopilot: the AI writes drafts for site '
            . 'pages, the owner approves them, approved posts go into a queue and are released every 15 minutes (quiet hours and a daily cap apply) '
            . 'to per-channel RSS feeds that Publer auto-posts from (or straight to Publer on its API plan); clicks and orders are tracked per channel. '
            . 'Use the tools to look things up and to act instead of guessing. The site tools can read posts, products, media, plugins, cron '
            . 'and options (never credentials) — reading is always allowed; changing a post, a product or an option happens on the site\'s MCP '
            . 'surface, not here, so say what you would change and let the owner do it. You cannot change this plugin\'s settings yourself, and you must '
            . 'never guess at a model name for the AI provider: name the setting and the new value and let the owner write it on the Settings page. '
            . 'Only a subset of the site\'s abilities is bound here; when you '
            . 'are unsure whether a tool exists, call find-tools first, describe-tool for its arguments, and run-tool to run it by name. Before deleting anything, rejecting drafts in bulk or '
            . 'changing settings, say what you are about to do and ask, unless the owner already asked for exactly that. Never reveal API keys. '
            . 'Answer in the language the owner writes in (usually Danish), short and practical, with Markdown lists where they help. '
            . 'Site time now: ' . wp_date('Y-m-d H:i (l)') . '.';
    }

    public static function history(): array {
        $history = get_user_meta(get_current_user_id(), self::META, true);
        return is_array($history) ? $history : [];
    }

    /**
     * Sends the owner's message with the earlier conversation and runs the tool loop.
     * @return array{text: string, tools: string[]}|WP_Error
     */
    public static function reply(array $history, string $message) {
        $key = (string) SGAP_Settings::get('ai_api_key');
        if ($key === '') {
            return new WP_Error('sgap_ai_key', 'Set an AI API key in Steamgears → Settings → AI content first.');
        }
        $turns = [];
        foreach (array_slice($history, -20) as $turn) {
            if (in_array($turn['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($turn['content'] ?? '')) !== '') {
                $turns[] = ['role' => $turn['role'], 'content' => (string) $turn['content']];
            }
        }
        $turns[] = ['role' => 'user', 'content' => $message];
        $tools = self::tools();
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        return SGAP_AI::uses_chat_completions()
            ? self::loop_chat_completions($key, $turns, $tools)
            : self::loop_anthropic($key, $turns, $tools);
    }

    /** AIMLAPI / OpenAI-compatible: /chat/completions with function tools. */
    private static function loop_chat_completions(string $key, array $turns, array $tools) {
        $messages = array_merge([['role' => 'system', 'content' => self::system_prompt()]], $turns);
        $specs = [];
        foreach ($tools as $name => [$description, $schema]) {
            $specs[] = ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $schema]];
        }
        $used = [];
        $healed = '';
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            // Read the model every round: a name the provider rejected was repaired on the last call.
            $model = SGAP_AI::model();
            if ($model === '') {
                return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for the OpenAI-compatible provider.');
            }
            $body = ['model' => $model, 'max_tokens' => 4000, 'messages' => $messages];
            if ($specs) {
                $body['tools'] = $specs;
            }
            if (SGAP_AI::provider() === 'aimlapi') {
                $body['reasoning_effort'] = 'low';
            }
            // A stored model the provider does not know is healed inside chat_call: one retry on
            // the provider default, then the stored value is cleared so it is the last time.
            $decoded = SGAP_AI::chat_call($key, $body, 120);
            if ($healed === '' && SGAP_AI::model() !== $model) {
                $healed = $model;
            }
            $names = array_keys($tools);
            if (is_wp_error($decoded)) {
                $entry = self::remember_call(SGAP_AI::provider_name(), $model, $round, $names, $decoded->get_error_message());
                if ($entry['culprit']) {
                    return new WP_Error($decoded->get_error_code(), $decoded->get_error_message()
                        . ' — tool #' . $entry['culprit']['index'] . ' is `' . $entry['culprit']['name'] . '`.');
                }
                return $decoded;
            }
            self::remember_call(SGAP_AI::provider_name(), $model, $round, $names);
            $msg = $decoded['choices'][0]['message'] ?? [];
            $calls = is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : [];
            if (!$calls) {
                $text = trim((string) ($msg['content'] ?? ''));
                if ($text === '' && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
                    return new WP_Error('sgap_ai_truncated', 'The answer was cut off; try a shorter question.' . self::healed_note($healed));
                }
                return ['text' => ($text !== '' ? $text : '(no answer)') . self::healed_note($healed), 'tools' => $used];
            }
            $messages[] = ['role' => 'assistant', 'content' => (string) ($msg['content'] ?? ''), 'tool_calls' => array_map(fn($c) => [
                'id' => (string) ($c['id'] ?? ''), 'type' => 'function',
                'function' => ['name' => (string) ($c['function']['name'] ?? ''), 'arguments' => (string) ($c['function']['arguments'] ?? '') ?: '{}']
            ], $calls)];
            foreach ($calls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $args = json_decode((string) ($call['function']['arguments'] ?? '') ?: '{}', true);
                $used[] = $name;
                $messages[] = ['role' => 'tool', 'tool_call_id' => (string) ($call['id'] ?? ''), 'content' => self::run_tool($name, $args, $tools)];
            }
        }
        return ['text' => 'I stopped after ' . self::MAX_ROUNDS . ' tool steps; ask me to continue.' . self::healed_note($healed), 'tools' => $used];
    }

    /** Says so when a stored model name was rejected and the answer came from the default. */
    private static function healed_note(string $from): string {
        if ($from === '') {
            return '';
        }
        return "\n\nAIMLAPI did not know the model `{$from}`, so this answer came from `" . SGAP_AI::model()
            . '` and Settings has been reset to it — the next command works too.';
    }

    /** Anthropic Messages API with tool use. */
    private static function loop_anthropic(string $key, array $turns, array $tools) {
        $model = SGAP_AI::model();
        $specs = [];
        foreach ($tools as $name => [$description, $schema]) {
            $specs[] = ['name' => $name, 'description' => $description, 'input_schema' => $schema];
        }
        $messages = $turns;
        $used = [];
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $body = [
                'model' => $model,
                'max_tokens' => 8000,
                'system' => self::system_prompt(),
                'messages' => $messages,
                'output_config' => ['effort' => 'low']
            ];
            if ($specs) {
                $body['tools'] = $specs;
            }
            $response = wp_remote_post(SGAP_AI::ANTHROPIC_URL, [
                'timeout' => 120,
                'headers' => ['x-api-key' => $key, 'anthropic-version' => SGAP_AI::ANTHROPIC_VERSION, 'content-type' => 'application/json'],
                'body' => wp_json_encode($body)
            ]);
            $decoded = SGAP_AI::decode($response, 'Anthropic');
            $names = array_keys($tools);
            if (is_wp_error($decoded)) {
                $entry = self::remember_call('Anthropic', $model, $round, $names, $decoded->get_error_message());
                if ($entry['culprit']) {
                    return new WP_Error($decoded->get_error_code(), $decoded->get_error_message()
                        . ' — tool #' . $entry['culprit']['index'] . ' is `' . $entry['culprit']['name'] . '`.');
                }
                return $decoded;
            }
            self::remember_call('Anthropic', $model, $round, $names);
            $content = (array) ($decoded['content'] ?? []);
            if (($decoded['stop_reason'] ?? '') !== 'tool_use') {
                if (($decoded['stop_reason'] ?? '') === 'refusal') {
                    return ['text' => 'The model declined to answer that.', 'tools' => $used];
                }
                $text = trim(implode("\n", array_map(fn($b) => (string) ($b['text'] ?? ''), array_filter($content, fn($b) => is_array($b) && ($b['type'] ?? '') === 'text'))));
                return ['text' => $text !== '' ? $text : '(no answer)', 'tools' => $used];
            }
            // Send the assistant turn back unchanged (thinking blocks included), then the tool results.
            // json_decode turned an empty input {} into []; the API needs an object again.
            foreach ($content as $k => $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_use' && empty($block['input'])) {
                    $content[$k]['input'] = new stdClass();
                }
            }
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $results = [];
            foreach ($content as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'tool_use') {
                    $used[] = (string) $block['name'];
                    $results[] = ['type' => 'tool_result', 'tool_use_id' => (string) $block['id'], 'content' => self::run_tool((string) $block['name'], $block['input'] ?? [], $tools)];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }
        return ['text' => 'I stopped after ' . self::MAX_ROUNDS . ' tool steps; ask me to continue.', 'tools' => $used];
    }

    /* ---- ajax ---- */

    public static function ajax_send(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_chat', 'nonce');
        $message = trim(sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? ''))));
        if ($message === '') {
            wp_send_json_error('Write a message first.');
        }
        $history = self::history();
        $reply = self::reply($history, $message);
        if (is_wp_error($reply)) {
            wp_send_json_error($reply->get_error_message());
        }
        $history[] = ['role' => 'user', 'content' => $message, 'at' => current_time('mysql')];
        $history[] = ['role' => 'assistant', 'content' => $reply['text'], 'tools' => array_values(array_unique($reply['tools'])), 'at' => current_time('mysql')];
        update_user_meta(get_current_user_id(), self::META, array_slice($history, -self::MAX_HISTORY));
        if ($reply['tools']) {
            delete_transient('sgap_dashboard_stats');
        }
        wp_send_json_success(['text' => $reply['text'], 'tools' => array_values(array_unique($reply['tools']))]);
    }

    public static function ajax_clear(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_chat', 'nonce');
        delete_user_meta(get_current_user_id(), self::META);
        wp_send_json_success();
    }

    /** Current tool catalogue, so the page can pick up tools added after it was rendered. */
    public static function ajax_tools(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_chat', 'nonce');
        wp_send_json_success(self::tool_catalog());
    }

    /* ---- tools browser ---- */

    public static function catalog_menu(): void {
        add_submenu_page('steamgears-autopilot', 'Tools', 'Tools', 'manage_options', 'sgap-tools', [__CLASS__, 'catalog_page'], 30);
    }

    /** Every ability the site registers, with search, category filters and read/write badges. */
    public static function catalog_page(): void {
        SGAP_Admin::open('Tools', 'Everything this site registers as an ability or MCP tool — search it, filter by category, and see which ones write.');
        $available = self::available();
        $bound = self::tools();
        $bound_names = [];
        foreach ($bound as $name => $tool) {
            $bound_names[(string) $name] = true;
            if (isset($tool[2]) && is_object($tool[2]) && method_exists($tool[2], 'get_name')) {
                $bound_names[(string) $tool[2]->get_name()] = true;
            }
        }
        $rows = [];
        $categories = [];
        $readonly = 0;
        foreach ($available as $entry) {
            $category = $entry['category'] !== '' ? $entry['category'] : '(none)';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            if (!empty($entry['readonly'])) {
                $readonly++;
            }
            $rows[] = [
                'name' => $entry['name'],
                'short' => $entry['short'],
                'category' => $category,
                'description' => $entry['description'],
                'readonly' => !empty($entry['readonly']),
                'bound' => isset($bound_names[$entry['name']]) || isset($bound_names[$entry['short']]),
                'chat' => !in_array($entry['short'], self::EXCLUDED, true)
            ];
        }
        ksort($categories);
        echo '<div class="sgap-pills">'
            . SGAP_UI::pill(count($rows) . ' registered', count($rows) ? 'brass' : 'warn') . ' '
            . SGAP_UI::pill(count($categories) . ' categories') . ' '
            . SGAP_UI::pill($readonly . ' read-only', 'ok') . ' '
            . SGAP_UI::pill((count($rows) - $readonly) . ' write', $readonly === count($rows) ? 'ok' : 'warn') . ' '
            . SGAP_UI::pill(count($bound) . ' bound to chat', count($bound) ? 'ok' : 'warn')
            . '</div>';
        echo '<div class="sgap-toolsbar"><label class="screen-reader-text" for="sgap-tool-search">Search tools</label>'
            . '<span class="dashicons dashicons-search" aria-hidden="true"></span>'
            . '<input type="search" id="sgap-tool-search" placeholder="Search name, description or category …  ( / )" autocomplete="off">'
            . '<span class="sgap-toolcount" id="sgap-toolcount">' . count($rows) . ' tools</span></div>'
            . '<div class="sgap-cats" id="sgap-tool-cats"><button type="button" class="sgap-cat is-on" data-cat="">All</button>';
        foreach ($categories as $category => $count) {
            echo '<button type="button" class="sgap-cat" data-cat="' . esc_attr($category) . '">' . esc_html($category) . ' <span>' . (int) $count . '</span></button>';
        }
        echo '</div><div class="sgap-toolgrid" id="sgap-toolgrid"></div>'
            . '<p class="sgap-toolempty" id="sgap-tool-empty" hidden>No tool matches that search.</p>';
        $log = self::call_log();
        echo '<h2 class="sgap-calls-h">Last calls to the AI provider</h2>'
            . '<p class="description">The last three calls, kept for 24 hours — tool names only, never a key. A rejected name is resolved to the tool that was refused.</p>';
        if (!$log) {
            echo '<p class="description"><em>Nothing logged yet.</em></p>';
        } else {
            echo '<div class="sgap-calls">';
            foreach ($log as $call) {
                $bad = !empty($call['error']);
                echo '<div class="sgap-call' . ($bad ? ' is-bad' : '') . '">'
                    . '<div class="sgap-call-top"><strong>' . esc_html((string) ($call['at'] ?? '')) . '</strong> '
                    . '<span>' . esc_html(trim((string) ($call['provider'] ?? '') . ' · ' . (string) ($call['model'] ?? ''), ' ·')) . '</span> '
                    . '<span class="sgap-badge mute">round ' . (int) ($call['round'] ?? 0) . '</span> '
                    . '<span class="sgap-badge ' . ($bad ? 'write' : 'read') . '">' . (int) ($call['count'] ?? 0) . ' tools</span></div>';
                if (!empty($call['culprit'])) {
                    echo '<div class="sgap-call-culprit">Rejected tool #' . (int) $call['culprit']['index'] . ': <code>' . esc_html((string) $call['culprit']['name']) . '</code></div>';
                }
                if ($bad) {
                    echo '<code class="sgap-call-err">' . esc_html(substr((string) $call['error'], 0, 300)) . '</code>';
                }
                if (!empty($call['names'])) {
                    echo '<details><summary>Names sent</summary><code>' . esc_html(implode(' · ', (array) $call['names'])) . '</code></details>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<script>(function(){var T=' . wp_json_encode($rows) . ';'
            . 'var grid=document.getElementById("sgap-toolgrid"),search=document.getElementById("sgap-tool-search"),count=document.getElementById("sgap-toolcount"),empty=document.getElementById("sgap-tool-empty"),cats=document.getElementById("sgap-tool-cats");'
            . 'var active="";'
            . 'function esc(s){return String(s==null?"":s).replace(/[&<>"\']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\'":"&#39;"}[c];});}'
            . 'function card(t){return "<article class=\\"sgap-toolcard\\">"+"<h3><code>"+esc(t.short)+"</code>"'
            . '+(t.readonly?"<span class=\\"sgap-badge read\\">read</span>":"<span class=\\"sgap-badge write\\">write</span>")'
            . '+(t.chat?"":"<span class=\\"sgap-badge mute\\">blocked in chat</span>")'
            . '+(t.bound?"<span class=\\"sgap-badge bound\\">bound</span>":"<span class=\\"sgap-badge mute\\">via run-tool</span>")+"</h3>"'
            . '+"<p class=\\"sgap-toolmeta\\">"+esc(t.category)+"</p>"'
            . '+(t.description?"<p class=\\"sgap-tool-desc\\">"+esc(t.description)+"</p>":"")+"</article>";}'
            . 'function render(){var q=search.value.toLowerCase().trim();var hits=T.filter(function(t){'
            . 'if(active&&t.category!==active)return false;if(!q)return true;'
            . 'return (t.name+" "+t.short+" "+t.category+" "+t.description).toLowerCase().indexOf(q)>-1;});'
            . 'grid.innerHTML=hits.map(card).join("");count.textContent=hits.length+" of "+T.length+" tools";empty.hidden=hits.length>0;}'
            . 'search.addEventListener("input",render);'
            . 'cats.addEventListener("click",function(e){var b=e.target.closest(".sgap-cat");if(!b)return;'
            . 'active=b.getAttribute("data-cat")||"";Array.prototype.forEach.call(cats.querySelectorAll(".sgap-cat"),function(x){x.classList.toggle("is-on",x===b);});render();});'
            . 'document.addEventListener("keydown",function(e){'
            . 'if(e.key==="/"&&document.activeElement!==search&&!/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)){e.preventDefault();search.focus();}'
            . 'if(e.key==="Escape"&&document.activeElement===search){search.value="";render();search.blur();}});'
            . 'render();})();</script>';
        SGAP_Admin::close();
    }

    /* ---- page ---- */

    public static function page(): void {
        SGAP_Admin::open('AI Chat', 'Ask about the autopilot or tell it what to do – it can look things up and act for you.');
        $key_set = SGAP_Settings::get('ai_api_key') !== '';
        if (!$key_set) {
            echo '<div class="notice notice-warning"><p>Set an AI API key under <a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings → AI content</a> to use the chat.</p></div>';
        }
        $config = [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sgap_chat'),
            'history' => array_map(fn($t) => ['role' => $t['role'], 'content' => $t['content'], 'tools' => $t['tools'] ?? []], self::history()),
            'model' => SGAP_AI::provider_name() . ' · ' . SGAP_AI::model(),
            'tools' => count(self::tools()),
            'available' => count(self::available()),
            'signature' => self::tools_signature()
        ];
        $suggestions = ['Hvordan går det med autopiloten?', 'Vis kladderne der venter', 'Skriv nye opslag nu', 'Hvorfor er der ikke sendt noget?', 'Hvilken kanal giver flest klik?'];
        echo '<div class="sgap-card sgap-chat"><div class="sgap-chat-top"><span>' . SGAP_UI::pill($config['model'], 'brass') . ' '
            . '<span id="sgap-chat-tools-pill">' . SGAP_UI::pill($config['tools'] . ' of ' . $config['available'] . ' bound', $config['tools'] ? 'ok' : 'warn') . '</span></span>'
            . '<span class="sgap-chat-top-btns"><button type="button" class="button button-small" id="sgap-chat-refresh-tools" title="Read the tool catalogue again — new abilities show up without a reload"><span class="dashicons dashicons-update"></span>Refresh tools</button>'
            . '<button type="button" class="button button-small" id="sgap-chat-clear"><span class="dashicons dashicons-trash"></span>New chat</button></span></div>'
            . '<div id="sgap-chat-log" class="sgap-chat-log" aria-live="polite"></div>'
            . '<div class="sgap-chat-suggest" id="sgap-chat-suggest">';
        foreach ($suggestions as $s) {
            echo '<button type="button" class="sgap-chip">' . esc_html($s) . '</button>';
        }
        echo '</div><form id="sgap-chat-form" class="sgap-chat-form"><textarea id="sgap-chat-input" rows="2" placeholder="Skriv til AI\'en … (Enter sender, Shift+Enter ny linje)"' . ($key_set ? '' : ' disabled') . '></textarea>'
            . '<button class="button button-primary" id="sgap-chat-send"' . ($key_set ? '' : ' disabled') . '><span class="dashicons dashicons-arrow-right-alt"></span>Send</button></form></div>';
        echo '<script>(function(){var C=' . wp_json_encode($config) . ';'
            . 'var log=document.getElementById("sgap-chat-log"),form=document.getElementById("sgap-chat-form"),input=document.getElementById("sgap-chat-input"),send=document.getElementById("sgap-chat-send"),sug=document.getElementById("sgap-chat-suggest");'
            . 'function esc(s){return s.replace(/[&<>"\']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\'":"&#39;"}[c];});}'
            . 'function md(t){var out=[],list=false;esc(t).split("\\n").forEach(function(l){var m=l.match(/^\\s*(?:[-*]|\\d+\\.)\\s+(.*)$/);'
            . 'if(m){if(!list){out.push("<ul>");list=true;}out.push("<li>"+inl(m[1])+"</li>");return;}if(list){out.push("</ul>");list=false;}'
            . 'var h=l.match(/^#{1,4}\\s+(.*)$/);out.push(h?"<strong>"+inl(h[1])+"</strong><br>":(l.trim()===""?"<br>":inl(l)+"<br>"));});if(list)out.push("</ul>");return out.join("");}'
            . 'function inl(s){return s.replace(/\\*\\*(.+?)\\*\\*/g,"<b>$1</b>").replace(/`([^`]+)`/g,"<code>$1</code>");}'
            . 'function add(role,text,tools){var d=document.createElement("div");d.className="sgap-msg sgap-"+role;d.innerHTML=(role==="assistant"?md(text):esc(text).replace(/\\n/g,"<br>"))'
            . '+(tools&&tools.length?"<div class=\\"sgap-msg-tools\\">⚙ "+tools.map(esc).join(" · ")+"</div>":"");log.appendChild(d);log.scrollTop=log.scrollHeight;return d;}'
            . 'if(!C.history.length){add("assistant","Hej! Jeg kan se status, skrive og godkende opslag, frigive køen og ændre indstillinger. Hvad skal vi lave?");}'
            . 'C.history.forEach(function(t){add(t.role,t.content,t.tools);});if(C.history.length)sug.style.display="none";'
            . 'function post(action,data){var b=new URLSearchParams(data||{});b.append("action",action);b.append("nonce",C.nonce);return fetch(C.ajax,{method:"POST",credentials:"same-origin",body:b}).then(function(r){return r.json();});}'
            . 'function ask(text){text=text.trim();if(!text)return;sug.style.display="none";add("user",text);input.value="";send.disabled=true;input.disabled=true;'
            . 'var wait=add("assistant","");wait.classList.add("sgap-typing");wait.innerHTML="<span></span><span></span><span></span>";'
            . 'post("sgap_chat",{message:text}).then(function(j){wait.remove();if(j&&j.success){add("assistant",j.data.text,j.data.tools);}else{add("error",(j&&j.data)||"Something went wrong.");}})'
            . '.catch(function(e){wait.remove();add("error","Could not reach the site: "+e.message);}).then(function(){send.disabled=false;input.disabled=false;input.focus();});}'
            . 'form.addEventListener("submit",function(e){e.preventDefault();ask(input.value);});'
            . 'input.addEventListener("keydown",function(e){if(e.key==="Enter"&&!e.shiftKey){e.preventDefault();ask(input.value);}});'
            . 'sug.addEventListener("click",function(e){if(e.target.classList.contains("sgap-chip"))ask(e.target.textContent);});'
            . 'document.getElementById("sgap-chat-clear").addEventListener("click",function(){post("sgap_chat_clear").then(function(){log.innerHTML="";sug.style.display="";add("assistant","Ny samtale. Hvad skal vi lave?");});});'
            . 'var tBtn=document.getElementById("sgap-chat-refresh-tools"),tPill=document.getElementById("sgap-chat-tools-pill");'
            . 'tBtn.addEventListener("click",function(){tBtn.disabled=true;post("sgap_chat_tools").then(function(j){tBtn.disabled=false;if(!j||!j.success)return;'
            . 'var d=j.data;if(tPill)tPill.textContent=d.count+" of "+d.available+" bound";'
            . 'if(d.signature!==C.signature){var add_n=d.count-C.tools;C.tools=d.count;C.signature=d.signature;'
            . 'add("assistant",add_n>0?("Værktøjskataloget er opdateret: "+add_n+" nyt værktøj"+(add_n===1?"":"er")+" ("+d.count+" i alt)."):("Værktøjskataloget ændrede sig ("+d.count+" værktøjer nu)."));}})'
            . '.catch(function(){tBtn.disabled=false;});});'
            . '})();</script>';
        SGAP_Admin::close();
    }
}

/**
 * Wraps a plain callable so the meta tools (find-tools, describe-tool, run-tool) travel
 * through the same run_tool() path as an ability object.
 */
final class SGAP_Chat_Runner {
    /** @var callable */
    private $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function execute($input = null) {
        return call_user_func($this->callback, $input);
    }

    public function get_input_schema() {
        return null;
    }
}
