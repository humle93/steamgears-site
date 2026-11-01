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
    /** Abilities the chat may not use: they change the site beyond the autopilot. */
    const EXCLUDED = ['manage-plugin', 'enable-full-access', 'update', 'run-cron'];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('wp_ajax_sgap_chat', [__CLASS__, 'ajax_send']);
        add_action('wp_ajax_sgap_chat_clear', [__CLASS__, 'ajax_clear']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'AI Chat', 'AI Chat', 'manage_options', 'sgap-chat', [__CLASS__, 'page'], 1);
    }

    /* ---- tools ---- */

    /** name => [description, JSON schema, WP_Ability] for the abilities the chat may call. */
    public static function tools(): array {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }
        $tools = [];
        foreach (wp_get_abilities() as $ability) {
            $full = $ability->get_name();
            if (strpos($full, SGAP_Abilities::CATEGORY . '/') !== 0) {
                continue;
            }
            $name = substr($full, strlen(SGAP_Abilities::CATEGORY) + 1);
            if (in_array($name, self::EXCLUDED, true)) {
                continue;
            }
            $schema = $ability->get_input_schema();
            if (empty($schema) || ($schema['type'] ?? 'object') !== 'object') {
                $schema = ['type' => 'object', 'properties' => new stdClass()];
            } elseif (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            }
            $tools[$name] = [$ability->get_description() ?: $ability->get_label(), $schema, $ability];
        }
        return $tools;
    }

    /** Runs one tool; always returns a JSON string for the model. */
    public static function run_tool(string $name, $args, array $tools): string {
        if (!isset($tools[$name])) {
            return wp_json_encode(['error' => "Unknown tool {$name}."]);
        }
        $ability = $tools[$name][2];
        $args = is_array($args) ? $args : [];
        $input = $args ?: ($ability->get_input_schema() ? [] : null);
        $result = $ability->execute($input);
        if (is_wp_error($result)) {
            return wp_json_encode(['error' => $result->get_error_message()]);
        }
        $json = (string) wp_json_encode($result);
        return strlen($json) > 12000 ? substr($json, 0, 12000) . '… (truncated)' : $json;
    }

    /* ---- conversation ---- */

    public static function system_prompt(): string {
        return 'You are the assistant inside the Steamgears Autopilot WordPress plugin on steamgears.eu, a shop and blog for steampunk '
            . 'costumes, accessories and DIY guides. You help the site owner run the social media autopilot: the AI writes drafts for site '
            . 'pages, the owner approves them, approved posts go into a queue and are released every 15 minutes (quiet hours and a daily cap apply) '
            . 'to per-channel RSS feeds that Publer auto-posts from (or straight to Publer on its API plan); clicks and orders are tracked per channel. '
            . 'Use the tools to look things up and to act instead of guessing. Before deleting anything, rejecting drafts in bulk or changing '
            . 'settings, say what you are about to do and ask, unless the owner already asked for exactly that. Never reveal API keys. '
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
        $model = SGAP_AI::model();
        if ($model === '') {
            return new WP_Error('sgap_ai_model', 'Set an AI model in Settings for the OpenAI-compatible provider.');
        }
        $messages = array_merge([['role' => 'system', 'content' => self::system_prompt()]], $turns);
        $specs = [];
        foreach ($tools as $name => [$description, $schema]) {
            $specs[] = ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $schema]];
        }
        $used = [];
        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $body = ['model' => $model, 'max_tokens' => 4000, 'messages' => $messages];
            if ($specs) {
                $body['tools'] = $specs;
            }
            if (SGAP_AI::provider() === 'aimlapi') {
                $body['reasoning_effort'] = 'low';
            }
            $response = wp_remote_post(SGAP_AI::base_url() . '/chat/completions', [
                'timeout' => 120,
                'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode($body)
            ]);
            $decoded = SGAP_AI::decode($response, SGAP_AI::provider_name());
            if (is_wp_error($decoded)) {
                return $decoded;
            }
            $msg = $decoded['choices'][0]['message'] ?? [];
            $calls = is_array($msg['tool_calls'] ?? null) ? $msg['tool_calls'] : [];
            if (!$calls) {
                $text = trim((string) ($msg['content'] ?? ''));
                if ($text === '' && ($decoded['choices'][0]['finish_reason'] ?? '') === 'length') {
                    return new WP_Error('sgap_ai_truncated', 'The answer was cut off; try a shorter question.');
                }
                return ['text' => $text !== '' ? $text : '(no answer)', 'tools' => $used];
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
        return ['text' => 'I stopped after ' . self::MAX_ROUNDS . ' tool steps; ask me to continue.', 'tools' => $used];
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
            if (is_wp_error($decoded)) {
                return $decoded;
            }
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
            'tools' => count(self::tools())
        ];
        $suggestions = ['Hvordan går det med autopiloten?', 'Vis kladderne der venter', 'Skriv nye opslag nu', 'Hvorfor er der ikke sendt noget?', 'Hvilken kanal giver flest klik?'];
        echo '<div class="sgap-card sgap-chat"><div class="sgap-chat-top"><span>' . SGAP_UI::pill($config['model'], 'brass') . ' '
            . SGAP_UI::pill($config['tools'] . ' tools', $config['tools'] ? 'ok' : 'warn') . '</span>'
            . '<button type="button" class="button button-small" id="sgap-chat-clear"><span class="dashicons dashicons-trash"></span>New chat</button></div>'
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
            . '})();</script>';
        SGAP_Admin::close();
    }
}
