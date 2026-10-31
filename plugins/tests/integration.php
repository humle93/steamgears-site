<?php
/**
 * Integration test, run inside a real WordPress + MySQL by integration.sh:
 *   wp eval-file plugins/tests/integration.php
 * Exits non-zero when any check fails. Leaves the created queue id in the
 * option sgap_ci_queue_id for the HTTP checks that follow in integration.sh.
 */

// wp eval-file includes this file inside a function, so shared state goes in $GLOBALS.
global $wpdb;
$GLOBALS['sgci_failed'] = false;

function sgci_check(string $name, bool $ok, $detail = null): void {
    WP_CLI::line(($ok ? 'PASS ' : 'FAIL ') . $name . ($ok || $detail === null ? '' : ' -> ' . wp_json_encode($detail)));
    if (!$ok) {
        $GLOBALS['sgci_failed'] = true;
    }
}

function sgci_rest(string $method, string $route, array $params = []): WP_REST_Response {
    $request = new WP_REST_Request($method, '/sgap/v1' . $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return rest_do_request($request);
}

/* --- install --- */
foreach (SGAP_Install::TABLES as $table) {
    $name = $wpdb->prefix . $table;
    sgci_check("table {$table} exists", $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)) === $name);
}
sgci_check('version stored', get_option('sgap_version') === SGAP_VERSION);
$autoload = $wpdb->get_var("SELECT autoload FROM {$wpdb->options} WHERE option_name = 'sgap_ai_api_key'");
sgci_check('secret option not autoloaded', in_array($autoload, ['no', 'off'], true), $autoload);
sgci_check('cleanup cron scheduled', (bool) wp_next_scheduled(SGAP_Install::CLEANUP_CRON));
sgci_check('/go/ rewrite rule registered', array_key_exists('^go/([0-9]+)/?$', (array) get_option('rewrite_rules')));
$legacy = array_filter(SGAP_Install::LEGACY_CRON, 'wp_next_scheduled');
sgci_check('no legacy cron events', !$legacy, $legacy);

/* --- REST: auth --- */
wp_set_current_user(0);
sgci_check('REST rejects anonymous', sgci_rest('GET', '/stats')->get_status() === 401);
$admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) $admin[0]);

/* --- REST: queue --- */
sgci_check('queue: missing fields 400', sgci_rest('POST', '/queue', ['channel' => 'pinterest'])->get_status() === 400);
$item = [
    'channel' => 'pinterest',
    'title' => 'CI steampunk goggles',
    'destination_url' => home_url('/ci-goggles/'),
    'angle' => 'angle_b',
    'body' => 'Body text'
];
$res = sgci_rest('POST', '/queue', $item);
$data = $res->get_data();
$qid = (int) ($data['id'] ?? 0);
sgci_check('queue: created 201', $res->get_status() === 201 && $qid > 0, $data);
sgci_check('queue: tracking url', ($data['tracking_url'] ?? '') === home_url('/go/' . $qid . '/'), $data);
sgci_check('queue: utm url', str_contains((string) ($data['utm_url'] ?? ''), 'utm_source=pinterest'), $data);
sgci_check('queue: duplicate 409', sgci_rest('POST', '/queue', $item)->get_status() === 409);
update_option('sgap_ci_queue_id', $qid);
$status = fn() => $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $qid));

update_option('sgap_autopilot_enabled', 'no');
sgci_check('due: autopilot off', (sgci_rest('GET', '/queue/due')->get_data()['reason'] ?? '') === 'autopilot_disabled');
update_option('sgap_autopilot_enabled', 'yes');
update_option('sgap_quiet_start', 0);
update_option('sgap_quiet_end', 0);
update_option('sgap_daily_cap', 8);
update_option('sgap_batch_size', 5);
$due = sgci_rest('GET', '/queue/due')->get_data();
sgci_check('due: returns queued item', in_array($qid, array_map('intval', array_column($due['items'] ?? [], 'id')), true), $due);
sgci_check('due: item claimed as sending', $status() === 'sending', $status());
sgci_check('due: second poll empty', count(sgci_rest('GET', '/queue/due')->get_data()['items'] ?? [1]) === 0);

sgci_check('status: failed is retried', sgci_rest('POST', "/queue/{$qid}/status", ['status' => 'failed', 'error' => 'CI'])->get_data()['status'] === 'queued');
sgci_check('due: retried item comes back', count(sgci_rest('GET', '/queue/due')->get_data()['items'] ?? []) === 1);
sgci_check('status: published', sgci_rest('POST', "/queue/{$qid}/status", ['status' => 'published'])->get_data()['status'] === 'published');
sgci_check('status: bad value 400', sgci_rest('POST', "/queue/{$qid}/status", ['status' => 'x'])->get_status() === 400);
sgci_check('status: unknown id 404', sgci_rest('POST', '/queue/999999/status', ['status' => 'published'])->get_status() === 404);
update_option('sgap_daily_cap', 1);
sgci_check('due: daily cap reached', (sgci_rest('GET', '/queue/due')->get_data()['reason'] ?? '') === 'daily_cap_reached');
update_option('sgap_daily_cap', 8);

/* --- REST: drafts --- */
$res = sgci_rest('POST', '/drafts', ['channel' => 'instagram', 'title' => 'CI draft', 'hashtags' => ['#steampunk', '#cosplay']]);
$did = (int) ($res->get_data()['id'] ?? 0);
sgci_check('drafts: created 201', $res->get_status() === 201 && $did > 0);
sgci_check('drafts: none approved yet', count(sgci_rest('GET', '/drafts', ['status' => 'approved'])->get_data()) === 0);
sgci_check('drafts: approve', sgci_rest('POST', "/drafts/{$did}/status", ['status' => 'approved'])->get_status() === 200);
$approved = sgci_rest('GET', '/drafts', ['status' => 'approved'])->get_data();
sgci_check('drafts: listed as approved', count($approved) === 1 && $approved[0]['hashtags'] === '#steampunk #cosplay', $approved);
sgci_check('drafts: bad status 400', sgci_rest('POST', "/drafts/{$did}/status", ['status' => 'x'])->get_status() === 400);

$stats = sgci_rest('GET', '/stats', ['days' => 7]);
sgci_check('stats: 200 with publish event', $stats->get_status() === 200
    && in_array('publish', array_column($stats->get_data()['events'], 'event_type'), true), $stats->get_data());

/* --- learning upsert (real MySQL ON DUPLICATE KEY) --- */
SGAP_ROI::record_event(0, 'ci_channel', 'ci_angle', 'click');
SGAP_ROI::record_event(0, 'ci_channel', 'ci_angle', 'click');
SGAP_ROI::record_event(0, 'ci_channel', 'ci_angle', 'order', 10.5);
$row = $wpdb->get_row("SELECT clicks, orders, revenue FROM {$wpdb->prefix}sgap_learning WHERE dimension = 'channel' AND dimension_value = 'ci_channel'");
sgci_check('learning: counters accumulate', $row && (int) $row->clicks === 2 && (int) $row->orders === 1 && (float) $row->revenue === 10.5, $row);

/* --- maintenance --- */
$wpdb->insert($wpdb->prefix . 'sgap_events', ['queue_id' => 0, 'channel' => 'ci_old', 'angle' => 'a', 'event_type' => 'click', 'value' => 0, 'created_at' => '2000-01-01 00:00:00']);
$wpdb->insert($wpdb->prefix . 'sgap_cro', ['queue_id' => 0, 'angle' => 'a', 'click_id' => 'ci_old_click', 'clicked_at' => '2000-01-01 00:00:00']);
$wpdb->insert($wpdb->prefix . 'sgap_queue', ['channel' => 'ci_stuck', 'destination_url' => home_url('/'), 'title' => 'stuck', 'status' => 'sending']);
$stuck = (int) $wpdb->insert_id;
SGAP_Maintenance::daily();
sgci_check('cleanup: old events pruned', !$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_events WHERE channel = 'ci_old'"));
sgci_check('cleanup: old clicks pruned', !$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_cro WHERE click_id = 'ci_old_click'"));
sgci_check('cleanup: recent events kept', (bool) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_events WHERE channel = 'ci_channel'"));
sgci_check('cleanup: stuck item requeued', $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $stuck)) === 'queued');
$wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => $stuck]);

/* --- settings sanitising --- */
SGAP_Settings::register();
update_option('sgap_ai_api_key', 'ci-secret');
update_option('sgap_ai_api_key', '');
sgci_check('settings: empty secret keeps value', get_option('sgap_ai_api_key') === 'ci-secret');
$autoload = $wpdb->get_var("SELECT autoload FROM {$wpdb->options} WHERE option_name = 'sgap_ai_api_key'");
sgci_check('settings: secret still not autoloaded', in_array($autoload, ['no', 'off'], true), $autoload);
update_option('sgap_full_auto_enabled', 'maybe');
sgci_check('settings: on/off sanitised', get_option('sgap_full_auto_enabled') === 'no');
update_option('sgap_quiet_start', 99);
sgci_check('settings: hour clamped', (int) get_option('sgap_quiet_start') === 23);
update_option('sgap_quiet_start', 0);

/* --- AI generation + Publer publishing, with Anthropic and Publer mocked --- */
$GLOBALS['sgci_http'] = [];
$GLOBALS['sgci_job_state'] = 'working';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
add_filter('pre_http_request', function ($pre, $args, $url) use ($png) {
    $GLOBALS['sgci_http'][] = ['url' => $url, 'args' => $args];
    $reply = fn(int $code, $body, string $type = 'application/json') => [
        'headers' => ['content-type' => $type],
        'body' => is_string($body) ? $body : wp_json_encode($body),
        'response' => ['code' => $code, 'message' => 'OK'],
        'cookies' => [],
        'filename' => null
    ];
    if (str_starts_with($url, 'https://api.anthropic.com/')) {
        $request = json_decode($args['body'], true);
        $channels = $request['output_config']['format']['schema']['properties']['posts']['items']['properties']['channel']['enum'];
        $posts = array_map(fn($c) => ['channel' => $c, 'title' => "CI {$c}", 'text' => "CI text for {$c}", 'hashtags' => ['steampunk'], 'alt_text' => 'alt'], $channels);
        return $reply(200, ['type' => 'message', 'stop_reason' => 'end_turn', 'content' => [
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => wp_json_encode(['posts' => $posts])]
        ]]);
    }
    if ($url === 'https://app.publer.com/api/v1/accounts') {
        return $reply(200, [
            ['id' => 'acc_fb', 'provider' => 'facebook', 'name' => 'SG FB'],
            ['id' => 'acc_ig', 'provider' => 'instagram', 'name' => 'SG IG'],
            ['id' => 'acc_pin', 'provider' => 'pinterest', 'name' => 'SG Pin']
        ]);
    }
    if ($url === 'https://app.publer.com/api/v1/media') {
        return $reply(200, ['id' => 'media_1', 'type' => 'photo']);
    }
    if ($url === 'https://app.publer.com/api/v1/posts/schedule/publish') {
        $GLOBALS['sgci_jobs'] = ($GLOBALS['sgci_jobs'] ?? 0) + 1;
        return $reply(200, ['job_id' => 'job_' . $GLOBALS['sgci_jobs']]);
    }
    if (str_starts_with($url, 'https://app.publer.com/api/v1/job_status/')) {
        return $reply(200, ['success' => true, 'data' => ['status' => $GLOBALS['sgci_job_state'], 'result' => ['payload' => ['failures' => []]]]]);
    }
    if (str_contains($url, '/ci-image')) {
        return $reply(200, $png, 'image/png');
    }
    return $pre;
}, 10, 3);
$requests_to = fn(string $prefix) => array_values(array_filter($GLOBALS['sgci_http'], fn($r) => str_starts_with($r['url'], $prefix)));

// Every existing post counts as recently promoted, so only the CI posts get picked.
foreach (get_posts(['post_type' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']) as $existing) {
    update_post_meta($existing, SGAP_Generator::META, time());
}
$make_post = function (string $title) use ($png) {
    $post_id = wp_insert_post(['post_title' => $title, 'post_content' => 'Brass goggles with leather straps. ' . str_repeat('Steam. ', 50), 'post_status' => 'publish']);
    $upload = wp_upload_bits('ci-image.png', null, $png);
    $attachment = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'ci', 'post_status' => 'inherit'], $upload['file'], $post_id);
    set_post_thumbnail($post_id, $attachment);
    return $post_id;
};
$post_a = $make_post('CI Brass Goggles Guide');

update_option('sgap_ai_provider', 'anthropic');
update_option('sgap_ai_api_key', 'ci-anthropic-key');
update_option('sgap_ai_model', '');
update_option('sgap_channels', 'facebook, instagram, pinterest, myspace');
update_option('sgap_posts_per_day', 1);
update_option('sgap_full_auto_enabled', 'no');
update_option('sgap_publish_mode', 'api');
update_option('sgap_publer_api_key', 'ci-publer-key');
update_option('sgap_publer_workspace_id', 'ws_1');
update_option('sgap_publer_pinterest_board', 'board_1');

$report = SGAP_Generator::run();
sgci_check('generate: 3 drafts for the picked post, none queued', $report['sources'] === 1 && $report['drafts'] === 3 && $report['queued'] === 0 && !$report['errors'], $report);
$ai = $requests_to('https://api.anthropic.com/');
$ai_body = json_decode($ai[0]['args']['body'] ?? '{}', true);
sgci_check('generate: Anthropic request headers', ($ai[0]['args']['headers']['x-api-key'] ?? '') === 'ci-anthropic-key'
    && ($ai[0]['args']['headers']['anthropic-version'] ?? '') === '2026-10-21', $ai[0]['args']['headers'] ?? null);
sgci_check('generate: model, structured output, fallback', ($ai_body['model'] ?? '') === 'claude-opus-5-5'
    && ($ai_body['output_config']['format']['type'] ?? '') === 'json_schema' && ($ai_body['fallbacks'] ?? '') === 'default', $ai_body);
sgci_check('generate: prompt contains the post title', str_contains($ai_body['messages'][0]['content'] ?? '', 'CI Brass Goggles Guide'));
$drafts = $wpdb->get_results($wpdb->prepare("SELECT id, channel, hashtags, status FROM {$wpdb->prefix}sgap_ai_drafts WHERE post_id = %d ORDER BY id", $post_a));
sgci_check('generate: drafts pending with hashtags', count($drafts) === 3 && $drafts[0]->status === 'pending' && $drafts[0]->hashtags === '#steampunk', $drafts);
sgci_check('generate: cooldown stops a second pick', SGAP_Generator::run()['sources'] === 0);

$queued = SGAP_Queue::from_draft((int) $drafts[0]->id);
$row = is_wp_error($queued) ? null : $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $queued['id']));
sgci_check('approve: draft becomes a queue item', $row && $row->destination_url === get_permalink($post_a)
    && str_contains((string) $row->image_url, '/ci-image') && str_contains((string) $row->body, '#steampunk'), $row);
sgci_check('approve: draft marked used', $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sgap_ai_drafts WHERE id = %d", $drafts[0]->id)) === 'used');
SGAP_Queue::from_draft((int) $drafts[1]->id);
SGAP_Queue::from_draft((int) $drafts[2]->id);

$post_b = $make_post('CI Top Hat Product');
update_option('sgap_full_auto_enabled', 'yes');
$report = SGAP_Generator::run();
sgci_check('full auto: posts go straight to the queue', $report['drafts'] === 3 && $report['queued'] === 3, $report);

update_option('sgap_autopilot_enabled', 'yes');
update_option('sgap_quiet_start', 0);
update_option('sgap_quiet_end', 0);
update_option('sgap_daily_cap', 50);
update_option('sgap_batch_size', 10);
SGAP_Publer::tick();
$sending = $wpdb->get_results("SELECT id, channel, external_id FROM {$wpdb->prefix}sgap_queue WHERE status = 'sending'");
sgci_check('publish: 6 posts handed to Publer', count($sending) === 6 && !in_array('', array_column($sending, 'external_id'), true), $sending);
$sent = $requests_to('https://app.publer.com/api/v1/posts/schedule/publish');
$first = $sent[0]['args']['headers'] ?? [];
sgci_check('publish: Publer auth headers', ($first['Authorization'] ?? '') === 'Bearer-API ci-publer-key' && ($first['Publer-Workspace-Id'] ?? '') === 'ws_1', $first);
$by_network = [];
foreach ($sent as $request) {
    $post = json_decode($request['args']['body'], true)['bulk']['posts'][0];
    $by_network[array_key_first($post['networks'])] = $post;
}
$fb = $by_network['facebook'] ?? [];
sgci_check('publish: facebook link post uses the /go/ link', ($fb['networks']['facebook']['type'] ?? '') === 'link'
    && preg_match('#/go/\d+/$#', $fb['networks']['facebook']['link']['url'] ?? '') && ($fb['accounts'][0]['id'] ?? '') === 'acc_fb', $fb);
$pin = $by_network['pinterest'] ?? [];
sgci_check('publish: pinterest pin on the board with uploaded photo', ($pin['accounts'][0]['album_id'] ?? '') === 'board_1'
    && ($pin['networks']['pinterest']['media'][0]['id'] ?? '') === 'media_1', $pin);
sgci_check('publish: each post image uploaded once, for instagram + pinterest only', count($requests_to('https://app.publer.com/api/v1/media')) === 2, count($requests_to('https://app.publer.com/api/v1/media')));
$jobs_before = count($sent);
SGAP_Publer::tick();
sgci_check('publish: working jobs are not sent twice', count($requests_to('https://app.publer.com/api/v1/posts/schedule/publish')) === $jobs_before);
$GLOBALS['sgci_job_state'] = 'complete';
SGAP_Publer::tick();
sgci_check('publish: completed jobs marked published', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_queue WHERE status = 'published' AND external_id LIKE 'job_%'") === 6);

$no_photo = SGAP_Queue::add(['channel' => 'instagram', 'title' => 'CI no photo', 'destination_url' => home_url('/no-photo/'), 'body' => 'x']);
SGAP_Publer::tick();
$failed = $wpdb->get_row($wpdb->prepare("SELECT status, attempts, error FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $no_photo['id']));
sgci_check('publish: instagram without photo is retried with a clear error', $failed && $failed->status === 'queued'
    && (int) $failed->attempts === 1 && str_contains((string) $failed->error, 'needs a photo'), $failed);
$wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => $no_photo['id']]);
update_option('sgap_autopilot_enabled', 'no');
remove_all_filters('pre_http_request');

/* --- v7.4 features: suppression, Etsy orders, product feed --- */
$res = sgci_rest('POST', '/suppression', ['channel' => 'pinterest', 'angle' => 'angle_a', 'content_type' => 'product', 'format' => 'pin', 'level' => 'blocked', 'reason' => 'CI']);
sgci_check('suppression: saved', $res->get_status() === 200 && ($res->get_data()['level'] ?? '') === 'blocked', $res->get_data());
update_option('sgap_full_auto_enabled', 'yes');
$blocked = SGAP_Queue::add(['channel' => 'pinterest', 'title' => 'CI suppressed', 'destination_url' => home_url('/s/')]);
sgci_check('suppression: full auto refuses a blocked combination', is_wp_error($blocked) && $blocked->get_error_code() === 'sgap_suppressed');
update_option('sgap_full_auto_enabled', 'no');
$allowed = SGAP_Queue::add(['channel' => 'pinterest', 'title' => 'CI suppressed', 'destination_url' => home_url('/s/')]);
sgci_check('suppression: manual mode still allows it', is_array($allowed));
if (is_array($allowed)) {
    $wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => $allowed['id']]);
}
$wpdb->query("DELETE FROM {$wpdb->prefix}sgap_suppression WHERE reason = 'CI'");
$res = sgci_rest('POST', '/etsy/orders', ['etsy_order_id' => 123456, 'revenue' => 50, 'fees' => 5, 'shipping' => 7.5]);
sgci_check('etsy: order imported with profit', $res->get_status() === 200 && (float) ($res->get_data()['profit'] ?? 0) === 37.5, $res->get_data());
wp_set_current_user(0);
sgci_check('feed: refused without the secret', sgci_rest('GET', '/feed')->get_status() === 403);
wp_set_current_user((int) $admin[0]);

/* --- Claude / MCP abilities (WordPress 6.9+ only) --- */
if (function_exists('wp_get_ability')) {
    $names = ['status', 'list-drafts', 'review-draft', 'list-queue', 'queue-post', 'generate', 'publish', 'set-mode', 'update', 'check-keys', 'check-publer'];
    $missing = array_values(array_filter($names, fn($n) => !wp_get_ability('steamgears-autopilot/' . $n)));
    sgci_check('abilities: all 11 registered', !$missing, $missing);
    $status_ability = wp_get_ability('steamgears-autopilot/status');
    $meta = $status_ability && method_exists($status_ability, 'get_meta') ? $status_ability->get_meta() : [];
    sgci_check('abilities: exposed to MCP, read-only status', ($meta['mcp']['public'] ?? false) === true && ($meta['annotations']['readonly'] ?? null) === true, $meta);
    $status = $status_ability ? $status_ability->execute() : null;
    sgci_check('abilities: status runs', is_array($status) && $status['version'] === SGAP_VERSION && isset($status['queue']['published']), $status);
    $mode = wp_get_ability('steamgears-autopilot/set-mode')->execute(['autopilot' => true, 'full_auto' => false]);
    sgci_check('abilities: set-mode switches autopilot', is_array($mode) && $mode['autopilot'] === true && $mode['full_auto'] === false
        && get_option('sgap_autopilot_enabled') === 'yes', $mode);
    wp_get_ability('steamgears-autopilot/set-mode')->execute(['autopilot' => false]);
    $queued = wp_get_ability('steamgears-autopilot/queue-post')->execute([
        'channel' => 'facebook', 'title' => 'CI from Claude', 'text' => 'Hello from the ability', 'destination_url' => home_url('/claude/')
    ]);
    sgci_check('abilities: queue-post adds to the queue', is_array($queued) && !empty($queued['id']) && str_contains($queued['tracking_url'], '/go/'), $queued);
    $listed = wp_get_ability('steamgears-autopilot/list-queue')->execute(['status' => 'queued', 'limit' => 5]);
    sgci_check('abilities: list-queue shows it', is_array($listed) && in_array((int) $queued['id'], array_map('intval', array_column($listed['items'], 'id')), true), $listed);
    $drafts = wp_get_ability('steamgears-autopilot/list-drafts')->execute(['status' => 'used', 'limit' => 3]);
    sgci_check('abilities: list-drafts shows the promoted page', is_array($drafts) && count($drafts['drafts']) === 3 && !empty($drafts['drafts'][0]['promotes']['url']), $drafts);
    sgci_check('abilities: review of unknown draft is an error', is_wp_error(wp_get_ability('steamgears-autopilot/review-draft')->execute(['draft_id' => 999999, 'decision' => 'reject'])));
    $wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => (int) $queued['id']]);
    wp_set_current_user(0);
    sgci_check('abilities: anonymous users are refused', is_wp_error(wp_get_ability('steamgears-autopilot/status')->execute()));
    wp_set_current_user((int) $admin[0]);
} else {
    WP_CLI::line('SKIP abilities: this WordPress has no Abilities API');
}

/* --- API key test after saving (Publer + Anthropic mocked) --- */
add_filter('pre_http_request', function ($pre, $args, $url) {
    $auth = (string) ($args['headers']['Authorization'] ?? '');
    $reply = fn(int $code, $body) => ['headers' => ['content-type' => 'application/json'], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
    if (str_starts_with($url, 'https://app.publer.com/')) {
        if (str_contains($auth, 'bad-key')) {
            return $reply(401, ['errors' => ['Invalid API key']]);
        }
        if (str_ends_with($url, '/workspaces')) {
            return $reply(200, [['id' => 'ws_only', 'name' => 'Steamgears']]);
        }
        if (str_ends_with($url, '/accounts')) {
            return $reply(200, [['id' => 'a1', 'provider' => 'facebook', 'name' => 'FB'], ['id' => 'a2', 'provider' => 'instagram', 'name' => 'IG']]);
        }
    }
    if (str_starts_with($url, 'https://api.anthropic.com/v1/models/')) {
        if (($args['headers']['x-api-key'] ?? '') !== 'good-ai-key') {
            return $reply(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
        }
        return str_ends_with($url, '/claude-opus-5-5') ? $reply(200, ['id' => 'claude-opus-5-5', 'type' => 'model']) : $reply(404, ['type' => 'error']);
    }
    return $pre;
}, 10, 3);
SGAP_Settings::register();
delete_transient(SGAP_KeyCheck::PENDING);
update_option('sgap_publer_workspace_id', '');
update_option('sgap_channels', 'facebook, instagram');
update_option('sgap_publer_api_key', 'good-key');
sgci_check('keycheck: saving a key flags a test', (bool) get_transient(SGAP_KeyCheck::PENDING));
update_option('sgap_ai_provider', 'anthropic');
update_option('sgap_ai_model', '');
update_option('sgap_ai_api_key', 'good-ai-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: good Publer key passes', $r['publer']['ok'] === true, $r['publer']);
sgci_check('keycheck: single workspace filled in', get_option('sgap_publer_workspace_id') === 'ws_only');
sgci_check('keycheck: good Claude key + default model passes', $r['ai']['ok'] === true && str_contains($r['ai']['message'], 'claude-opus-5-5'), $r['ai']);
sgci_check('keycheck: result stored, flag cleared', is_array(get_option(SGAP_KeyCheck::RESULT)) && !get_transient(SGAP_KeyCheck::PENDING));
update_option('sgap_channels', 'facebook, instagram, pinterest');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: channel without Publer account is reported', $r['publer']['ok'] === false && str_contains($r['publer']['message'], 'pinterest'), $r['publer']);
update_option('sgap_publer_api_key', 'bad-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: invalid Publer key is reported', $r['publer']['ok'] === false && str_contains($r['publer']['message'], 'invalid'), $r['publer']);
update_option('sgap_ai_model', 'claude-made-up');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: unknown model is reported', $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'claude-made-up'), $r['ai']);
update_option('sgap_ai_model', '');
update_option('sgap_ai_api_key', 'wrong-ai-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: invalid Claude key is reported', $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'refused'), $r['ai']);
if (function_exists('wp_get_ability')) {
    $r = wp_get_ability('steamgears-autopilot/check-keys')->execute();
    sgci_check('keycheck: available as Claude tool', is_array($r) && isset($r['publer'], $r['ai']), $r);
}
remove_all_filters('pre_http_request');

/* --- RSS mode for Publer Professional --- */
update_option('sgap_publish_mode', 'rss');
update_option('sgap_autopilot_enabled', 'yes');
update_option('sgap_quiet_start', 0);
update_option('sgap_quiet_end', 0);
update_option('sgap_daily_cap', 50);
update_option('sgap_batch_size', 10);
update_option('sgap_channels', 'facebook, instagram');
$rss_fb = SGAP_Queue::add(['channel' => 'facebook', 'title' => 'CI RSS post', 'body' => "Line one\nLine two #steampunk",
    'destination_url' => home_url('/rss-post/'), 'image_url' => home_url('/wp-content/uploads/ci-rss.png')]);
$rss_ig = SGAP_Queue::add(['channel' => 'instagram', 'title' => 'CI RSS no photo', 'body' => 'x', 'destination_url' => home_url('/rss-ig/')]);
$r = SGAP_Publer::tick();
sgci_check('rss: tick releases into the feed instead of calling Publer', ($r['mode'] ?? '') === 'rss'
    && in_array((int) $rss_fb['id'], array_column($r['sent'], 'id'), true), $r);
sgci_check('rss: instagram without photo fails with a reason', in_array((int) $rss_ig['id'], array_column($r['failed'], 'id'), true));
$row = $wpdb->get_row($wpdb->prepare("SELECT status, external_id, published_at FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $rss_fb['id']));
sgci_check('rss: released post is published and marked rss', $row && $row->status === 'published' && $row->external_id === 'rss' && $row->published_at, $row);
$xml = SGAP_RSS::xml('facebook');
$parsed = simplexml_load_string($xml);
sgci_check('rss: feed is valid XML with the item', $parsed !== false && count($parsed->channel->item) >= 1, $xml);
sgci_check('rss: item links to the /go/ tracking link', str_contains($xml, '<link>' . SGAP_Tracking::url((int) $rss_fb['id']) . '</link>'));
sgci_check('rss: line breaks as literal \n, image enclosure', str_contains($xml, 'Line one\nLine two #steampunk') && str_contains($xml, '<enclosure url="' . home_url('/wp-content/uploads/ci-rss.png')));
sgci_check('rss: other channels stay empty', !str_contains(SGAP_RSS::xml('instagram'), 'CI RSS post'));
sgci_check('rss: key check needs no Publer key', SGAP_KeyCheck::check_publer()['ok'] === null);
update_option('sgap_ci_rss_id', (int) $rss_fb['id']);
$wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => (int) $rss_ig['id']]);
update_option('sgap_autopilot_enabled', 'no');

/* --- Self-update from GitHub (GitHub mocked; the "new" version is this plugin relabelled 9.9.9) --- */
$plugin_dir = WP_PLUGIN_DIR . '/steamgears-autopilot';
$new_zip = wp_tempnam('sgap-update.zip');
$zip = new ZipArchive();
$zip->open($new_zip, ZipArchive::OVERWRITE);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_dir, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $local = 'steamgears-autopilot/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($plugin_dir))), '/');
    $body = file_get_contents($file->getPathname());
    if ($local === 'steamgears-autopilot/steamgears-autopilot.php') {
        $body = preg_replace('/^\* Version: .*$/m', '* Version: 9.9.9', $body);
    }
    $zip->addFromString($local, $body);
}
$zip->close();
$GLOBALS['sgci_update_zip'] = $new_zip;
add_filter('pre_http_request', function ($pre, $args, $url) {
    if ($url === SGAP_Updater::RAW . 'steamgears-autopilot/steamgears-autopilot.php') {
        return ['headers' => [], 'body' => "<?php\n/**\n* Plugin Name: Steamgears Autopilot\n* Version: 9.9.9\n*/", 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    if ($url === SGAP_Updater::package_url('9.9.9')) {
        if (!empty($args['stream']) && !empty($args['filename'])) {
            copy($GLOBALS['sgci_update_zip'], $args['filename']);
        }
        return ['headers' => ['content-type' => 'application/zip'], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => $args['filename'] ?? null];
    }
    return $pre;
}, 10, 3);
delete_site_transient(SGAP_Updater::CACHE);
sgci_check('update: GitHub version read', SGAP_Updater::remote_version(true) === '9.9.9');
$transient = SGAP_Updater::inject_update((object) ['response' => [], 'no_update' => []]);
$entry = $transient->response[SGAP_Updater::basename()] ?? null;
sgci_check('update: shown in WordPress updates with the GitHub package', $entry && $entry->new_version === '9.9.9'
    && $entry->package === SGAP_Updater::package_url('9.9.9'), $entry);
$info = SGAP_Updater::plugin_info(false, 'plugin_information', (object) ['slug' => 'steamgears-autopilot']);
sgci_check('update: "View details" info', is_object($info) && $info->version === '9.9.9');
$report = SGAP_Updater::run();
$installed = get_file_data($plugin_dir . '/steamgears-autopilot.php', ['Version' => 'Version']);
sgci_check('update: Update now installs the GitHub version', $report['updated'] === true && $installed['Version'] === '9.9.9', [$report, $installed]);
sgci_check('update: plugin still active afterwards', is_plugin_active(SGAP_Updater::basename()));
sgci_check('update: all plugin files still there', file_exists($plugin_dir . '/includes/updater.php') && file_exists($plugin_dir . '/uninstall.php'));
if (function_exists('wp_get_ability')) {
    $r = wp_get_ability('steamgears-autopilot/update')->execute();
    sgci_check('update: available as Claude tool', is_array($r) && ($r['to'] ?? '') === '9.9.9' && isset($r['message']), $r);
}
remove_all_filters('pre_http_request');

if ($GLOBALS['sgci_failed']) {
    WP_CLI::error('Integration checks failed.');
}
WP_CLI::success('All integration checks passed.');
