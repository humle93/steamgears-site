<?php
/**
 * Admin tools for Claude (includes/admin-tools.php), run with: wp eval-file plugins/tests/admin-tools.php
 * OpenRouter is mocked; installing a plugin talks to wordpress.org.
 */
// Test only: fake OpenRouter so the buttons run end to end without cost.
add_filter('pre_http_request', function ($pre, $args, $url) {
    if ($pre !== false || strpos($url, 'https://openrouter.ai/api/v1/') !== 0) return $pre;
    $reply = fn($data) => ['headers' => [], 'body' => wp_json_encode($data), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    if (str_ends_with($url, '/models')) {
        return $reply(['data' => [['id' => 'vendor/model'], ['id' => 'vendor/other']]]);
    }
    $body = json_decode((string) ($args['body'] ?? ''), true) ?: [];
    // The key test spends one token and nothing else.
    if (($body['max_tokens'] ?? 0) === 1) {
        return $reply(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]]);
    }
    if (!empty($body['tools'])) {
        $tool_msgs = array_values(array_filter($body['messages'], fn($m) => ($m['role'] ?? '') === 'tool'));
        if (!$tool_msgs) {
            return $reply(['choices' => [['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'status', 'arguments' => '']]]], 'finish_reason' => 'tool_calls']]]);
        }
        $st = json_decode($tool_msgs[0]['content'], true) ?: [];
        $txt = "Autopiloten er **" . (!empty($st['autopilot']) ? 'slået til' : 'slået fra') . "**.
- Kladder der venter: " . ($st['pending_drafts'] ?? '?') . "
- Tools: " . count($body['tools']);
        return $reply(['choices' => [['message' => ['role' => 'assistant', 'content' => $txt], 'finish_reason' => 'stop']]]);
    }
    $system = (string) ($body['messages'][0]['content'] ?? '');
    if (str_contains($system, 'content_html')) {
        $GLOBALS['sgci_article_req'] = $body;
        preg_match('#(https?://[^\s]+/\S*)#', (string) ($body['messages'][1]['content'] ?? ''), $m);
        $link = $m[1] ?? home_url('/');
        $article = [
            'title' => 'How to Make Steampunk Goggles', 'slug' => 'make-steampunk-goggles', 'meta_title' => 'Steampunk Goggles: DIY Guide',
            'meta_description' => 'Make your own steampunk goggles from brass and leather in an afternoon. Materials, steps and tips for a perfect Victorian look.',
            'focus_keyword' => 'steampunk goggles', 'excerpt' => 'A beginner guide.',
            'content_html' => '<p>Steampunk goggles are the classic accessory.</p><h2>Materials for steampunk goggles</h2><ul><li>Brass</li></ul><p>See <a href="' . esc_url($link) . '">our guide</a>.</p><script>alert(1)</script>',
            'faq' => [['question' => 'Are they hard to make?', 'answer' => 'No, about two hours.']], 'tags' => ['goggles', 'diy'],
        ];
        return $reply(['choices' => [['message' => ['content' => wp_json_encode($article)], 'finish_reason' => 'stop']]]);
    }
    if (str_contains($system, 'suggestions')) {
        $seo = ['focus_keyword' => 'brass goggles', 'meta_title' => 'Brass Goggles | Steamgears',
            'meta_description' => 'Hand-finished brass goggles for your steampunk costume. Sturdy, comfortable and made to be worn all day at conventions.',
            'suggestions' => ['Add an FAQ', 'Add alt texts']];
        return $reply(['choices' => [['message' => ['content' => wp_json_encode($seo)], 'finish_reason' => 'stop']]]);
    }
    preg_match_all('/^- ([a-z]+):/m', (string) ($body['messages'][1]['content'] ?? ''), $m);
    $posts = array_map(fn($c) => ['channel' => $c, 'title' => "Mock $c title", 'text' => "Mock $c post about brass goggles.", 'hashtags' => ['#steampunk'], 'alt_text' => 'Goggles'], $m[1]);
    return $reply(['choices' => [['message' => ['content' => "```json\n" . wp_json_encode(['posts' => $posts]) . "\n```"], 'finish_reason' => 'stop']]]);
}, 10, 3);
update_option('sgap_autopilot_enabled', 'yes');
update_option('sgap_ai_api_key', 'sk-or-v1-test');
update_option('sgap_ai_model', 'vendor/model');
update_option('sgap_publish_mode', 'rss');
update_option('sgap_channels', 'facebook,instagram,pinterest');
if (!get_posts(['post_type' => 'post', 'numberposts' => 1, 'post_status' => 'publish', 'meta_query' => [['key' => '_sgap_promoted_at', 'compare' => 'NOT EXISTS']]])) {
    wp_insert_post(['post_title' => 'Admin tools test page', 'post_content' => 'Brass goggles.', 'post_status' => 'publish']);
}
if (!function_exists('wp_get_ability')) {
    WP_CLI::line('SKIP admin tools: this WordPress has no Abilities API');
    return;
}
wp_set_current_user(1);
$fail = 0;
function t($name, $ok, $info = null) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($info !== null ? ' — ' . substr(is_string($info) ? $info : wp_json_encode($info), 0, 300) : '') . "\n"; if (!$ok) $fail = 1; }
function run($n, $in = null) { $a = wp_get_ability("steamgears-autopilot/" . $n); if (!$a) return new WP_Error("missing", "ability $n not registered"); return $a->execute($in ?? ($a->get_input_schema() ? [] : null)); }
$names = array_map(fn($a) => $a->get_name(), array_filter(wp_get_abilities(), fn($a) => str_starts_with($a->get_name(), 'steamgears-autopilot/')));
t('42 autopilot abilities registered', count($names) === 42, count($names));
t('channel-health is registered read-only', (wp_get_ability('steamgears-autopilot/channel-health')->get_meta()['annotations']['readonly'] ?? false) === true);
$r = run('channel-health', ['days' => 90]);
t('channel-health answers per channel with the analytics half', is_array($r) && isset($r['channels']['pinterest'], $r['analytics']['telegram']) && ($r['analytics']['telegram']['state'] ?? '') === 'no_metric' && ($r['window_days'] ?? 0) === 90, is_wp_error($r) ? $r->get_error_message() : array_keys((array) $r));
t('the whole-site SEO ability is registered', in_array('steamgears-autopilot/optimize-site-seo', $names, true));
foreach ($names as $n) { $m = wp_get_ability($n)->get_meta(); if (empty($m['mcp']['public'])) t("$n public for MCP", false); }
$r = run('get-settings'); t('get-settings groups + hides secrets', isset($r['General']['quiet_start']) && $r['AI']['ai_api_key']['value'] === 'set', $r['AI']['ai_api_key'] ?? $r);
$r = run('update-settings', ['settings' => ['quiet_start' => 0, 'quiet_end' => '0', 'full_auto_enabled' => false, 'daily_cap' => '12']]); t('update-settings changes + validates', get_option('sgap_quiet_start') == 0 && get_option('sgap_daily_cap') == 12 && get_option('sgap_full_auto_enabled') === 'no', $r);
$r = run('update-settings', ['settings' => ['publish_mode' => 'bogus']]); t('update-settings sanitizes a bad choice', get_option('sgap_publish_mode') !== 'bogus', get_option('sgap_publish_mode'));
$r = run('update-settings', ['settings' => ['nope' => 1]]); t('update-settings rejects unknown keys', is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : $r);
// A different model than the one already stored: saving an unchanged value is not a change to re-test.
$r = run('update-settings', ['settings' => ['ai_model' => 'vendor/other']]); t('saving the model re-tests the key', isset($r['changed']['_key_test']['ai']), $r);
$gen = SGAP_Generator::run(1); t('generate a draft for the next tests', $gen['drafts'] >= 1, $gen);
global $wpdb; $draft = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sgap_ai_drafts WHERE channel='facebook' ORDER BY id DESC LIMIT 1");
$r = run('edit-draft', ['draft_id' => $draft, 'text' => 'Edited by Claude.', 'hashtags' => '#brass']); t('edit-draft', $wpdb->get_var("SELECT body FROM {$wpdb->prefix}sgap_ai_drafts WHERE id=$draft") === 'Edited by Claude.', $r);
$q = SGAP_Queue::from_draft($draft); $qid = is_wp_error($q) ? 0 : (int) ($q['id'] ?? $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}sgap_queue"));
$r = run('edit-draft', ['draft_id' => $draft, 'text' => 'x']); t('edit-draft refuses a used draft', is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-queue', ['queue_id' => $qid, 'action' => 'reschedule', 'scheduled_at' => '2030-01-01 10:00']); t('manage-queue reschedule', ($r['scheduled_at'] ?? '') !== '', $r);
$r = run('manage-queue', ['queue_id' => $qid, 'action' => 'retry']); t('manage-queue retry', ($r['status'] ?? '') === 'queued', $r);
$r = run('run-cron'); t('run-cron lists events', count($r['events']) > 0 && in_array('sgap_publish_tick', array_column($r['events'], 'hook'), true), count($r['events']));
$wpdb->update("{$wpdb->prefix}sgap_queue", ['scheduled_at' => current_time('mysql', true)], ['id' => $qid]);
$r = run('run-cron', ['hook' => 'sgap_publish_tick']); t('run-cron runs the publisher now', ($r['ran'] ?? false) && $wpdb->get_var("SELECT status FROM {$wpdb->prefix}sgap_queue WHERE id=$qid") === 'published', [$r, $wpdb->get_var("SELECT status FROM {$wpdb->prefix}sgap_queue WHERE id=$qid")]);
$r = run('run-cron', ['hook' => 'no_such_hook']); t('run-cron unknown hook is an error', is_wp_error($r));
$r = run('manage-queue', ['queue_id' => $qid, 'action' => 'delete']); t('manage-queue delete', !$wpdb->get_var("SELECT id FROM {$wpdb->prefix}sgap_queue WHERE id=$qid"), $r);
error_log('sgap-tools-test marker line');
$r = run('error-log', ['lines' => 20, 'contains' => 'sgap-tools-test']); t('error-log reads and filters', count($r['lines']) === 1 && str_contains($r['lines'][0], 'marker'), $r);
$r = run('purge-cache'); t('purge-cache', in_array('WordPress object cache', $r['purged'], true), $r);
$r = run('site-health'); t('site-health', !empty($r['wordpress']) && isset($r['wp_cron']['autopilot_events']['sgap_publish_tick']), $r['wp_cron'] ?? $r);
$r = run('list-plugins'); t('list-plugins shows active state', in_array('steamgears-autopilot/steamgears-autopilot.php', array_column(array_filter($r['plugins'], fn($p) => $p['active']), 'file'), true), $r['count']);
$r = run('manage-plugin', ['action' => 'install', 'plugin' => 'hello-dolly', 'activate' => true]); t('install + activate from wordpress.org', !is_wp_error($r) && ($r['active'] ?? false) === true, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'deactivate', 'plugin' => 'hello-dolly']); t('deactivate by slug', !is_wp_error($r) && ($r['active'] ?? true) === false, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'activate', 'plugin' => 'hello-dolly/hello.php']); t('activate by file', !is_wp_error($r) && ($r['active'] ?? false) === true, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'update', 'plugin' => 'hello-dolly']); t('update (already newest)', !is_wp_error($r) && $r['updated'] === false, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'deactivate', 'plugin' => 'steamgears-autopilot']); t('refuses to deactivate its own connection', is_wp_error($r) && is_plugin_active('steamgears-autopilot/steamgears-autopilot.php'), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'install', 'plugin' => 'no-such-plugin-zzz-123']); t('unknown slug is an error', is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('enable-full-access'); t('enable-full-access without AI Engine is an error', is_wp_error($r));
update_option('mwai_options', ['mcp_core' => true, 'mcp_abilities' => false, 'openai_key' => 'keep-me', 'nested' => ['a' => 1]]);
$r = run('enable-full-access'); $o = get_option('mwai_options'); t('enable-full-access turns on Dynamic REST + Abilities, keeps the rest', $o['mcp_dynamic_rest'] === true && $o['mcp_abilities'] === true && $o['openai_key'] === 'keep-me' && $o['nested']['a'] === 1, $r);
$r = run('enable-full-access', ['enabled' => false]); t('enable-full-access can turn it off', get_option('mwai_options')['mcp_dynamic_rest'] === false, $r);

// Content Studio
update_option('sgap_ai_api_key', 'sk-or-v1-test'); update_option('sgap_ai_model', 'vendor/model');
$tools = SGAP_Chat::tools();
t('studio: write-article and optimize-seo are tools for the chat, image generation is gone', isset($tools['write-article'], $tools['optimize-seo']) && !isset($tools['generate-image']));
$r = run('write-article', ['topic' => 'Making steampunk goggles', 'keyword' => 'steampunk goggles', 'type' => 'guide']);
t('studio: article saved as a draft', !is_wp_error($r) && get_post_status($r['post_id']) === 'draft', is_wp_error($r) ? $r->get_error_message() : $r);
if (!is_wp_error($r)) {
    $post = get_post($r['post_id']);
    t('studio: SEO meta saved (Rank Math fields)', get_post_meta($r['post_id'], 'rank_math_focus_keyword', true) === 'steampunk goggles' && strlen(get_post_meta($r['post_id'], 'rank_math_description', true)) > 100);
    t('studio: FAQ with FAQPage schema appended', str_contains($post->post_content, '<h2>FAQ</h2>') && str_contains($post->post_content, '"@type":"FAQPage"'));
    t('studio: unsafe HTML from the model removed', !str_contains($post->post_content, 'alert(1)'));
    t('studio: slug, excerpt and tags', $post->post_name === 'make-steampunk-goggles' && $post->post_excerpt !== '' && count(wp_get_post_tags($r['post_id'])) === 2);
    t('studio: an article gets no featured image of its own', !has_post_thumbnail($r['post_id']));
    t('studio: prompt lists real pages to link to', str_contains((string) ($GLOBALS['sgci_article_req']['messages'][1]['content'] ?? ''), home_url('/')));
    $o = run('optimize-seo', ['post_id' => $r['post_id']]);
    t('studio: optimize-seo saves new meta', !is_wp_error($o) && get_post_meta($r['post_id'], 'rank_math_focus_keyword', true) === 'brass goggles' && count($o['suggestions']) === 2, is_wp_error($o) ? $o->get_error_message() : $o);
}
// AI Chat: the AI calls a tool, gets its result and answers.
$tools = SGAP_Chat::tools();
t('chat: autopilot tools offered, site-changing ones left out', isset($tools['status'], $tools['generate']) && !isset($tools['manage-plugin'], $tools['enable-full-access'], $tools['update-settings']), implode(',', array_keys($tools)));
update_option('sgap_ai_api_key', 'sk-or-v1-test'); update_option('sgap_ai_model', 'vendor/model');
$r = SGAP_Chat::reply([], 'Hvordan går det?');
t('chat: tool loop answers from the status tool', !is_wp_error($r) && $r['tools'] === ['status'] && str_contains($r['text'], 'slået'), is_wp_error($r) ? $r->get_error_message() : $r);
// WordPress 7.1 calls an ability that has no input schema with NO argument (live 5/11: self-check threw
// "Too few arguments"). Every callback must therefore accept being called with zero arguments.
$strict = [];
foreach (wp_get_abilities() as $a) {
    if (!str_starts_with($a->get_name(), 'steamgears-')) continue;
    $prop = (new ReflectionClass($a))->getProperty('execute_callback');
    $prop->setAccessible(true);
    $cb = $prop->getValue($a);
    $ref = is_array($cb) ? new ReflectionMethod($cb[0], $cb[1]) : new ReflectionFunction(Closure::fromCallable($cb));
    if ($ref->getNumberOfRequiredParameters() > 0) $strict[] = $a->get_name();
}
t('every ability callback can be called without arguments (WordPress 7.1)', !$strict, $strict);
$cb = (function () { return $this->execute_callback; })->call(wp_get_ability('steamgears-autopilot/self-check'));
$h = $cb();
t('self-check runs when called with no argument at all', is_array($h) && isset($h['problems']), $h);
wp_set_current_user(0);
$r = wp_get_ability('steamgears-autopilot/list-plugins')->execute(); t('logged-out user is refused', is_wp_error($r));
if ($fail) { WP_CLI::error("admin tools: some checks failed"); }
WP_CLI::line("admin tools: all checks passed");
