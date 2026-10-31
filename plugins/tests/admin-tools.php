<?php
/**
 * Admin tools for Claude (includes/admin-tools.php), run with: wp eval-file plugins/tests/admin-tools.php
 * AIMLAPI is mocked; installing a plugin talks to wordpress.org.
 */
// Test only: fake AIMLAPI so the buttons run end to end without cost.
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'https://api.aimlapi.com/') !== 0) return $pre;
    $body = json_decode((string) ($args['body'] ?? ''), true) ?: [];
    if (($body['max_tokens'] ?? 0) === 1) {
        return ['headers' => [], 'body' => '{"choices":[{"message":{"content":""},"finish_reason":"length"}]}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    preg_match_all('/^- ([a-z]+):/m', $body['messages'][1]['content'] ?? '', $m);
    $posts = array_map(fn($c) => ['channel' => $c, 'title' => "Mock $c title", 'text' => "Mock $c post about brass goggles.", 'hashtags' => ['#steampunk'], 'alt_text' => 'Goggles'], $m[1]);
    return ['headers' => [], 'body' => wp_json_encode(['choices' => [['message' => ['content' => "```json\n" . wp_json_encode(['posts' => $posts]) . "\n```"], 'finish_reason' => 'stop']]]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, 10, 3);

update_option('sgap_autopilot_enabled', 'yes');
update_option('sgap_ai_provider', 'aimlapi');
update_option('sgap_ai_api_key', 'test-key');
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
t('22 autopilot abilities registered', count($names) === 22, count($names));
foreach ($names as $n) { $m = wp_get_ability($n)->get_meta(); if (empty($m['mcp']['public'])) t("$n public for MCP", false); }
$r = run('get-settings'); t('get-settings groups + hides secrets', isset($r['General']['quiet_start']) && $r['AI content']['ai_api_key']['value'] === 'set', $r['AI content']['ai_api_key'] ?? $r);
$r = run('update-settings', ['settings' => ['quiet_start' => 0, 'quiet_end' => '0', 'full_auto_enabled' => false, 'daily_cap' => '12']]); t('update-settings changes + validates', get_option('sgap_quiet_start') == 0 && get_option('sgap_daily_cap') == 12 && get_option('sgap_full_auto_enabled') === 'no', $r);
$r = run('update-settings', ['settings' => ['ai_provider' => 'bogus']]); t('update-settings sanitizes a bad choice', get_option('sgap_ai_provider') !== 'bogus', get_option('sgap_ai_provider'));
update_option('sgap_ai_provider', 'aimlapi');
$r = run('update-settings', ['settings' => ['nope' => 1]]); t('update-settings rejects unknown keys', is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('update-settings', ['settings' => ['ai_model' => 'anthropic/claude-opus-5.5']]); t('saving the model re-tests the key', isset($r['changed']['_key_test']['ai']), $r);
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
$r = run('manage-plugin', ['action' => 'install', 'plugin' => 'hello-dolly', 'activate' => true]); t('install + activate from wordpress.org', ($r['active'] ?? false) === true, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'deactivate', 'plugin' => 'hello-dolly']); t('deactivate by slug', ($r['active'] ?? true) === false, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'activate', 'plugin' => 'hello-dolly/hello.php']); t('activate by file', ($r['active'] ?? false) === true, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'update', 'plugin' => 'hello-dolly']); t('update (already newest)', !is_wp_error($r) && $r['updated'] === false, is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'deactivate', 'plugin' => 'steamgears-autopilot']); t('refuses to deactivate its own connection', is_wp_error($r) && is_plugin_active('steamgears-autopilot/steamgears-autopilot.php'), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('manage-plugin', ['action' => 'install', 'plugin' => 'no-such-plugin-zzz-123']); t('unknown slug is an error', is_wp_error($r), is_wp_error($r) ? $r->get_error_message() : $r);
$r = run('enable-full-access'); t('enable-full-access without AI Engine is an error', is_wp_error($r));
update_option('mwai_options', ['mcp_core' => true, 'mcp_abilities' => false, 'openai_key' => 'keep-me', 'nested' => ['a' => 1]]);
$r = run('enable-full-access'); $o = get_option('mwai_options'); t('enable-full-access turns on Dynamic REST + Abilities, keeps the rest', $o['mcp_dynamic_rest'] === true && $o['mcp_abilities'] === true && $o['openai_key'] === 'keep-me' && $o['nested']['a'] === 1, $r);
$r = run('enable-full-access', ['enabled' => false]); t('enable-full-access can turn it off', get_option('mwai_options')['mcp_dynamic_rest'] === false, $r);
wp_set_current_user(0);
$r = wp_get_ability('steamgears-autopilot/list-plugins')->execute(); t('logged-out user is refused', is_wp_error($r));
if ($fail) { WP_CLI::error("admin tools: some checks failed"); }
WP_CLI::line("admin tools: all checks passed");
