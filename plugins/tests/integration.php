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
sgci_check('evergreen cron scheduled', (bool) wp_next_scheduled(SGAP_Install::EVERGREEN_CRON));
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
sgci_check('utm: campaign default', str_contains((string) ($data['utm_url'] ?? ''), 'utm_campaign=steamgears_premium_evergreen'), $data);
update_option('sgap_utm_campaign', 'ci_campaign');
sgci_check('utm: setting names the campaign', str_contains(SGAP_UTM::build(['channel' => 'pinterest', 'destination_url' => home_url('/x/')]), 'utm_campaign=ci_campaign'));
sgci_check('utm: per-post campaign wins', str_contains(SGAP_UTM::build(['channel' => 'pinterest', 'campaign' => 'Clockwork Punk', 'destination_url' => home_url('/x/')]), 'utm_campaign=clockworkpunk'));
update_option('sgap_utm_campaign', 'steamgears_premium_evergreen');
sgci_check('queue: duplicate 409', sgci_rest('POST', '/queue', $item)->get_status() === 409);

// scheduled_at is site wall-clock time: a Danish 10:00 must still read 10:00 in MySQL,
// and February 31 must be refused instead of being normalised to March.
update_option('timezone_string', 'Europe/Copenhagen');
$clock = sgci_rest('POST', '/queue', [
    'channel' => 'pinterest',
    'title' => 'CI wall clock',
    'destination_url' => home_url('/ci-wall-clock/'),
    'scheduled_at' => '2026-12-25 10:00'
]);
$clock_row = $wpdb->get_row($wpdb->prepare("SELECT id, scheduled_at FROM {$wpdb->prefix}sgap_queue WHERE title = %s", 'CI wall clock'));
sgci_check('queue: scheduled_at keeps site wall-clock', $clock->get_status() === 201
    && $clock_row && $clock_row->scheduled_at === '2026-12-25 10:00:00', $clock_row);
sgci_check('queue: impossible scheduled_at 400', sgci_rest('POST', '/queue', [
    'channel' => 'pinterest',
    'title' => 'CI impossible date',
    'destination_url' => home_url('/ci-impossible-date/'),
    'scheduled_at' => '2026-02-31 10:00'
])->get_status() === 400);
if ($clock_row) {
    $wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => (int) $clock_row->id]);
}
update_option('timezone_string', '');

// A form field is a string: "false" must not become true and bypass the quality gate.
update_option('sgap_quality_gate', 'block');
update_option('sgap_banned_words', 'scam');
$false_force = sgci_rest('POST', '/queue', [
    'channel' => 'facebook',
    'title' => 'CI false force',
    'destination_url' => home_url('/ci-false-force/'),
    'body' => 'This is a scam',
    'force' => 'false'
]);
sgci_check('queue: force=false does not bypass the gate', $false_force->get_status() === 422);
$true_force = sgci_rest('POST', '/queue', [
    'channel' => 'facebook',
    'title' => 'CI true force',
    'destination_url' => home_url('/ci-true-force/'),
    'body' => 'This is a scam',
    'force' => 'true'
]);
sgci_check('queue: force=true still queues with a warning', $true_force->get_status() === 201);
if ($true_force->get_status() === 201) {
    $wpdb->delete($wpdb->prefix . 'sgap_queue', ['id' => (int) ($true_force->get_data()['id'] ?? 0)]);
}
update_option('sgap_quality_gate', 'block');
update_option('sgap_banned_words', '');

// Archive detection must look at the query keys, not merely at the presence of a query.
sgci_check('quality: utm query on a post is not an archive', !SGAP_Quality::is_archive(home_url('/ci-brass-goggles-guide/?utm_source=ci')));
sgci_check('quality: paged query is an archive', SGAP_Quality::is_archive(home_url('/?paged=2')));
sgci_check('quality: p is one post, not an archive', !SGAP_Quality::is_archive(home_url('/?p=1')));

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
$queued = sgci_rest('POST', "/queue/{$qid}/status", ['status' => 'queued']);
$reset_row = $wpdb->get_row($wpdb->prepare("SELECT status, attempts, error, remind FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $qid));
sgci_check('status: queued retries and resets the row', $queued->get_status() === 200
    && ($queued->get_data()['status'] ?? '') === 'queued' && $reset_row
    && $reset_row->status === 'queued' && (int) $reset_row->attempts === 0
    && $reset_row->error === null && (int) $reset_row->remind === 0, $reset_row);
sgci_check('status: queued unknown id 404', sgci_rest('POST', '/queue/999999/status', ['status' => 'queued'])->get_status() === 404);
// A failure is retried after a back-off (5, 15, 45 min); pretend the wait is over.
$wpdb->update($wpdb->prefix . 'sgap_queue', ['scheduled_at' => wp_date('Y-m-d H:i:s', time() - 60)], ['id' => $qid]);
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
sgci_check('drafts: unknown id 404', sgci_rest('POST', '/drafts/999999/status', ['status' => 'approved'])->get_status() === 404);

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
// A real JPEG: Instagram's preflight only accepts JPEG, so a PNG would keep those posts out of the run.
$png = file_get_contents(__DIR__ . '/fixtures/ai-image.jpg');
add_filter('pre_http_request', function ($pre, $args, $url) use ($png) {
    $reply = fn(int $code, $body, string $type = 'application/json') => [
        'headers' => ['content-type' => $type],
        'body' => is_string($body) ? $body : wp_json_encode($body),
        'response' => ['code' => $code, 'message' => 'OK'],
        'cookies' => [],
        'filename' => null
    ];
    // Since 7.39.0 the model field is a dropdown over what the provider offers, so saving the
    // model asks for /v1/models. That GET is answered before the request log, so $ai[0] below
    // stays the chat call, and no test run reaches the real api.anthropic.com.
    if (str_starts_with($url, 'https://api.anthropic.com/v1/models')) {
        return $reply(200, ['data' => [
            ['id' => 'claude-opus-5-5', 'type' => 'model'],
            ['id' => 'claude-sonnet-5-5', 'type' => 'model']
        ]]);
    }
    $GLOBALS['sgci_http'][] = ['url' => $url, 'args' => $args];
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
        return $reply(200, $png, 'image/jpeg');
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
    $upload = wp_upload_bits('ci-image.jpg', null, $png);
    $attachment = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'ci', 'post_status' => 'inherit'], $upload['file'], $post_id);
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
    // The catalogue the settings dropdown and the save-time check read: /v1/models?limit=100.
    // It is a different URL from the single-model probe below, and the check that a retired
    // name cannot be saved is meaningless unless this list answers.
    if ($url === 'https://api.anthropic.com/v1/models?limit=100') {
        return $reply(200, ['data' => [['id' => 'claude-opus-5-5', 'type' => 'model']], 'has_more' => false]);
    }
    if (str_starts_with($url, 'https://api.anthropic.com/v1/models/')) {
        $GLOBALS['sgci_anthropic_models'][] = ['url' => $url, 'args' => $args];
        if (($args['headers']['x-api-key'] ?? '') !== 'good-ai-key') {
            return $reply(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
        }
        return str_ends_with($url, '/claude-opus-5-5') ? $reply(200, ['id' => 'claude-opus-5-5', 'type' => 'model']) : $reply(404, ['type' => 'error']);
    }
    if ($url === 'https://ai.example.test/v1/models') {
        // A custom OpenAI-compatible endpoint: the same key test as a real provider, but the
        // list and the auth header are what prove it went to the configured address.
        $GLOBALS['sgci_custom_models'][] = ['url' => $url, 'auth' => $auth, 'args' => $args];
        if ($auth !== 'Bearer good-custom-key') {
            return $reply(401, ['error' => ['message' => 'Invalid API key']]);
        }
        return $reply(200, ['data' => [['id' => 'custom-model'], ['id' => 'legacy-model']]]);
    }
    if ($url === 'https://api.aimlapi.com/v1/models') {
        // The dropdown asks what this provider offers; provider switching resolves ai_model
        // against this list, so it has to answer like the real /v1/models does.
        return $reply(200, ['data' => [['id' => 'anthropic/claude-opus-5.5'], ['id' => 'openai/gpt-5.5']]]);
    }
    if ($url === 'https://api.aimlapi.com/v1/chat/completions') {
        $sent_body = json_decode((string) $args['body'], true);
        $GLOBALS['sgci_aiml'][] = $sent_body;
        if ($auth !== 'Bearer good-aiml-key') {
            return $reply(401, ['error' => ['message' => 'Invalid API key']]);
        }
        if (($sent_body['model'] ?? '') === 'anthropic/claude-3-5-sonnet') {
            // A name the provider retired: the retry on the default is what has to happen next.
            return $reply(404, ['error' => ['message' => 'Model anthropic/claude-3-5-sonnet not found', 'type' => 'model_not_found']]);
        }
        $content = "```json\n" . wp_json_encode(['posts' => [['channel' => 'facebook', 'title' => 'Goggles', 'text' => 'Brass goggles, polished by hand.', 'hashtags' => ['#steampunk'], 'alt_text' => 'Goggles']]]) . "\n```";
        return $reply(200, ['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]]);
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
$GLOBALS['sgci_anthropic_models'] = [];
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: good Publer key passes', $r['publer']['ok'] === true, $r['publer']);
sgci_check('keycheck: single workspace filled in', get_option('sgap_publer_workspace_id') === 'ws_only');
sgci_check('keycheck: good Claude key + default model passes', $r['ai']['ok'] === true && str_contains($r['ai']['message'], 'claude-opus-5-5'), $r['ai']);
$anthropic_probe = $GLOBALS['sgci_anthropic_models'][0] ?? [];
sgci_check('keycheck: Anthropic check asks the Models API with key and version',
    str_ends_with((string) ($anthropic_probe['url'] ?? ''), '/v1/models/claude-opus-5-5')
    && ($anthropic_probe['args']['headers']['x-api-key'] ?? '') === 'good-ai-key'
    && ($anthropic_probe['args']['headers']['anthropic-version'] ?? '') === SGAP_AI::ANTHROPIC_VERSION,
    $anthropic_probe);
sgci_check('keycheck: result stored, flag cleared', is_array(get_option(SGAP_KeyCheck::RESULT)) && !get_transient(SGAP_KeyCheck::PENDING));
update_option('sgap_channels', 'facebook, instagram, pinterest');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: channel without Publer account is reported', $r['publer']['ok'] === false && str_contains($r['publer']['message'], 'pinterest'), $r['publer']);
update_option('sgap_publer_api_key', 'bad-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: invalid Publer key is reported', $r['publer']['ok'] === false && str_contains($r['publer']['message'], 'invalid'), $r['publer']);
// A retired name cannot be saved again: the save-time check keeps the model that works.
// The catalogue is cached for 12 hours, so clear it: a cached list from another phase would
// decide this check instead of the stub above.
delete_site_transient('sgap_ai_catalog_' . SGAP_AI::provider());
update_option('sgap_ai_model', 'claude-made-up');
sgci_check('model: a name the provider does not offer is refused at save', get_option('sgap_ai_model') === 'claude-opus-5-5', get_option('sgap_ai_model'));
// A site can still have one stored from before the check existed: written straight into the
// options table, the way such a site has it, because the save above now refuses it.
$wpdb->update($wpdb->options, ['option_value' => 'claude-made-up'], ['option_name' => 'sgap_ai_model']);
wp_cache_delete('sgap_ai_model', 'options');
wp_cache_delete('alloptions', 'options');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: unknown model is reported', $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'claude-made-up'), $r['ai']);
update_option('sgap_ai_model', '');
update_option('sgap_ai_api_key', 'wrong-ai-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: invalid Claude key is reported', $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'refused'), $r['ai']);
// Custom OpenAI-compatible provider: the key is tested with GET /models, never a chat call.
update_option('sgap_ai_provider', 'openai_compatible');
update_option('sgap_ai_base_url', 'https://ai.example.test/v1');
update_option('sgap_ai_api_key', 'good-custom-key');
update_option('sgap_ai_model', 'custom-model');
sgci_check('keycheck: custom provider accepts a listed model', get_option('sgap_ai_model') === 'custom-model', get_option('sgap_ai_model'));
$GLOBALS['sgci_custom_models'] = [];
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: good custom OpenAI-compatible key and listed model pass',
    $r['ai']['ok'] === true && str_contains($r['ai']['message'], 'custom-model'), $r['ai']);
$custom_probe = $GLOBALS['sgci_custom_models'][0] ?? [];
sgci_check('keycheck: custom check reads /models with Bearer auth',
    ($custom_probe['url'] ?? '') === 'https://ai.example.test/v1/models'
    && ($custom_probe['auth'] ?? '') === 'Bearer good-custom-key', $custom_probe);
update_option('sgap_ai_api_key', 'wrong-custom-key');
$r = SGAP_KeyCheck::run();
sgci_check('keycheck: invalid custom key is reported',
    $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'refused'), $r['ai']);
update_option('sgap_ai_model', '');
update_option('sgap_ai_base_url', '');
update_option('sgap_ai_provider', 'aimlapi');
// AIMLAPI: key test with a one-token request, then a real generation through /chat/completions.
update_option('sgap_ai_provider', 'aimlapi');
update_option('sgap_ai_api_key', 'good-aiml-key');
$GLOBALS['sgci_aiml'] = [];
$r = SGAP_KeyCheck::run();
sgci_check('aimlapi: good key passes with the default model', $r['ai']['ok'] === true && str_contains($r['ai']['message'], 'anthropic/claude-opus-5.5'), $r['ai']);
sgci_check('aimlapi: key test asks for one token', ($GLOBALS['sgci_aiml'][0]['max_tokens'] ?? 0) === 1, $GLOBALS['sgci_aiml']);
$posts = SGAP_AI::generate(['title' => 'Brass goggles', 'type' => 'product', 'summary' => 'Hand-polished goggles.'], ['facebook']);
$sent = end($GLOBALS['sgci_aiml']);
sgci_check('aimlapi: generates posts (fenced JSON parsed)', is_array($posts) && count($posts) === 1 && $posts[0]['channel'] === 'facebook', $posts);
sgci_check('aimlapi: request uses Claude, JSON mode and low reasoning effort', ($sent['model'] ?? '') === 'anthropic/claude-opus-5.5' && ($sent['reasoning_effort'] ?? '') === 'low' && ($sent['response_format']['type'] ?? '') === 'json_object', $sent);
update_option('sgap_ai_api_key', 'wrong-aiml-key');
$r = SGAP_KeyCheck::run();
sgci_check('aimlapi: invalid key is reported', $r['ai']['ok'] === false && str_contains($r['ai']['message'], 'refused'), $r['ai']);

/* --- the four model rules, checked against this WordPress instead of a stub --- */
// A site whose stored model was retired underneath it: written straight into the options table,
// because the save-time check below refuses new writes of it.
$retire = function (string $name) use ($wpdb) {
    $wpdb->update($wpdb->options, ['option_value' => $name], ['option_name' => 'sgap_ai_model']);
    wp_cache_delete('sgap_ai_model', 'options');
    wp_cache_delete('alloptions', 'options');
};
update_option('sgap_ai_api_key', 'good-aiml-key');
// (1) the stored name stays visible as retired, and (4) only offered models are pickable.
$retire('anthropic/claude-3-5-sonnet');
sgci_check('model: a stored name that is gone is marked, not hidden',
    str_contains(SGAP_Setup::field('ai_model'), 'anthropic/claude-3-5-sonnet (not offered any more)'));
$html = SGAP_Setup::field('ai_model');
sgci_check('model: dropdown groups by vendor, default first, only offered ids',
    substr_count($html, '<option') === 4
    && strpos($html, 'Provider default: anthropic/claude-opus-5.5') < strpos($html, '<optgroup')
    && str_contains($html, '<optgroup label="anthropic">') && str_contains($html, '<optgroup label="openai">'), $html);
// (3) the rejected name is retried on the provider default, and the answer says what changed.
$GLOBALS['sgci_aiml'] = [];
$answer = SGAP_Chat::reply([], 'Hvordan går det?');
$asked = array_column($GLOBALS['sgci_aiml'], 'model');
sgci_check('model: a rejected name is retried on the default and the answer says so',
    is_array($answer) && $asked === ['anthropic/claude-3-5-sonnet', 'anthropic/claude-opus-5.5']
    && str_contains($answer['text'], 'did not know the model `anthropic/claude-3-5-sonnet`')
    && get_option('sgap_ai_model') === 'anthropic/claude-opus-5.5', [$asked, is_array($answer) ? $answer['text'] : $answer]);
// (2) "Provider default" is stored as the model that is actually used, never as an empty field.
update_option('sgap_ai_model', '');
sgci_check('model: Provider default is saved as the model that is used',
    get_option('sgap_ai_model') === 'anthropic/claude-opus-5.5' && SGAP_AI::model() === 'anthropic/claude-opus-5.5', get_option('sgap_ai_model'));
update_option('sgap_ai_model', 'anthropic/claude-3-5-sonnet');
sgci_check('model: a name that is not offered cannot be saved again', get_option('sgap_ai_model') === 'anthropic/claude-opus-5.5', get_option('sgap_ai_model'));
update_option('sgap_ai_provider', 'anthropic');
update_option('sgap_ai_api_key', 'wrong-ai-key');
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

/* --- Evergreen recycling: mark, AI variation, budget, cooldown and the real queue --- */
$GLOBALS['sgci_evergreen_http'] = [];
$GLOBALS['sgci_evergreen_n'] = 0;
add_filter('pre_http_request', function ($pre, $args, $url) {
    $reply = fn(int $code, $body) => ['headers' => ['content-type' => 'application/json'], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    // Updating the model asks the provider what it offers; answer that before the chat call.
    if (str_starts_with($url, 'https://api.anthropic.com/v1/models')) {
        return $reply(200, ['data' => [['id' => 'claude-opus-5-5', 'type' => 'model']]]);
    }
    if ($url !== SGAP_AI::ANTHROPIC_URL) {
        return $pre;
    }
    $body = json_decode((string) $args['body'], true);
    $prompt = (string) ($body['messages'][0]['content'] ?? '');
    $GLOBALS['sgci_evergreen_n']++;
    preg_match('/^Page: (.+?) \([^)]+\)$/m', $prompt, $match);
    $title = $match[1] ?? 'Evergreen page';
    // Three genuinely different captions: the quality gate compares them on the same channel.
    $texts = [
        "The workshop returns to {$title} with a closer look at the hand-finished brass. Every scratch and rivet is part of the story, and the full notes show how it is made.",
        "Why does {$title} still matter? Because the silhouette does the work. This guide walks through the fit, the leather and the small details that turn an outfit into a character.",
        "A quieter take on {$title}: start with one piece, then let the rest follow. The guide covers materials, care and the choices that keep the look coherent."
    ];
    $answer = [
        'title' => 'A second look at ' . $title,
        'text' => $texts[($GLOBALS['sgci_evergreen_n'] - 1) % 3],
        'hashtags' => ['steampunk', 'evergreen'],
        'alt_text' => 'Brass goggles on a workbench'
    ];
    $GLOBALS['sgci_evergreen_http'][] = ['body' => $body, 'prompt' => $prompt, 'answer' => $answer];
    return $reply(200, ['type' => 'message', 'stop_reason' => 'end_turn', 'content' => [
        ['type' => 'thinking', 'thinking' => ''],
        ['type' => 'text', 'text' => wp_json_encode($answer)]
    ]]);
}, 10, 3);

update_option('sgap_ai_provider', 'anthropic');
update_option('sgap_ai_model', 'claude-opus-5-5');
update_option('sgap_ai_api_key', 'ci-evergreen-key');
update_option('sgap_channels', 'facebook, instagram');
update_option('sgap_evergreen_enabled', 'yes');
update_option('sgap_evergreen_after_days', 60);
update_option('sgap_evergreen_max_shares', 2);
update_option('sgap_evergreen_batch', 2);
$ever_a = wp_insert_post([
    'post_title' => 'CI Evergreen One',
    'post_content' => 'A guide to brass goggles, hand-finished leather and the small details that make a steampunk outfit hold together.',
    'post_status' => 'publish'
]);
$ever_b = wp_insert_post([
    'post_title' => 'CI Evergreen Two',
    'post_content' => 'A guide to corset fitting, silhouette and the materials that keep a dark academia look coherent all day.',
    'post_status' => 'publish'
]);
$a_status = SGAP_Evergreen::mark($ever_a, ['max' => 2]);
$b_status = SGAP_Evergreen::mark($ever_b, ['max' => 2]);
sgci_check('evergreen: marking real posts makes them ready', !is_wp_error($a_status) && !is_wp_error($b_status)
    && $a_status['marked'] && $a_status['max'] === 2 && $a_status['eligible'] && $b_status['eligible'], [$a_status, $b_status]);
sgci_check('evergreen: the cron hook is wired', has_action(SGAP_Install::EVERGREEN_CRON, [SGAP_Evergreen::class, 'cron']) !== false);
$summary = SGAP_Evergreen::summary();
sgci_check('evergreen: the summary counts two marked and two ready', $summary['enabled'] && $summary['marked'] === 2
    && $summary['ready'] === 2 && $summary['shares_used'] === 0, $summary);
if (function_exists('wp_get_ability')) {
    $missing = array_values(array_filter(['list-evergreen', 'mark-evergreen', 'recycle-evergreen'], fn($n) => !wp_get_ability('steamgears-autopilot/' . $n)));
    sgci_check('evergreen: all three abilities are registered', !$missing, $missing);
    $listed = wp_get_ability('steamgears-autopilot/list-evergreen')->execute(['limit' => 10]);
    sgci_check('evergreen: the ability lists both marked pages', is_array($listed) && count($listed['items'] ?? []) === 2
        && in_array($ever_a, array_map('intval', array_column($listed['items'], 'post_id')), true), $listed);
}

$report = SGAP_Evergreen::run(2);
sgci_check('evergreen: the run recycles both due pages', $report['recycled'] === 2 && count($report['items']) === 2 && !$report['errors'], $report);
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sgap_queue WHERE post_id IN (%d, %d) ORDER BY id",
    $ever_a, $ever_b
));
sgci_check('evergreen: both shares are queued on the first channel and angle', count($rows) === 2
    && array_column($rows, 'channel') === ['facebook', 'facebook'] && array_column($rows, 'angle') === ['angle_a', 'angle_a']
    && array_column($rows, 'content_type') === ['guide', 'guide'] && array_column($rows, 'format') === ['post', 'post'], $rows);
$ever_destinations = array_column($rows, 'destination_url');
$expected_destinations = [get_permalink($ever_a), get_permalink($ever_b)];
sort($ever_destinations);
sort($expected_destinations);
sgci_check('evergreen: caption, hashtags and destination reach the queue', count($rows) === 2
    && count(array_filter($rows, fn($row) => str_contains($row->body, '#steampunk #evergreen'))) === 2
    && $ever_destinations === $expected_destinations, $rows);
sgci_check('evergreen: the share is counted and dated on the page', SGAP_Evergreen::shares($ever_a) === 1
    && SGAP_Evergreen::shares($ever_b) === 1 && SGAP_Evergreen::last_share($ever_a) > 0 && SGAP_Evergreen::last_share($ever_b) > 0);
$calls = $GLOBALS['sgci_evergreen_http'];
sgci_check('evergreen: one structured AI call per share', count($calls) === 2
    && ($calls[0]['body']['model'] ?? '') === 'claude-opus-5-5'
    && ($calls[0]['body']['max_tokens'] ?? 0) === 1200
    && ($calls[0]['body']['output_config']['effort'] ?? '') === 'low'
    && ($calls[0]['body']['output_config']['format']['type'] ?? '') === 'json_schema', $calls);
sgci_check('evergreen: the prompt asks for a new angle and forbids invented offers', count($calls) === 2
    && str_contains($calls[0]['prompt'], 'genuinely different angle')
    && str_contains($calls[0]['prompt'], 'do not invent a discount')
    && str_contains($calls[0]['prompt'], 'CI Evergreen One'), $calls);

$waiting = SGAP_Evergreen::run(1);
sgci_check('evergreen: a page waits between shares, so a second run does nothing', $waiting['recycled'] === 0
    && str_contains($waiting['note'] ?? '', 'No evergreen page is due'), $waiting);

// The first share is out, so the next one may run; mark the queue rows published to close them.
$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sgap_queue SET status = 'published' WHERE post_id IN (%d, %d)", $ever_a, $ever_b));
update_post_meta($ever_a, SGAP_Evergreen::LAST, time() - 61 * DAY_IN_SECONDS);
$next = SGAP_Evergreen::run(1);
$next_row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}sgap_queue WHERE post_id = %d AND channel = 'instagram' ORDER BY id DESC LIMIT 1",
    $ever_a
));
sgci_check('evergreen: after the waiting period the share rotates channel and angle', $next['recycled'] === 1
    && ($next['items'][0]['channel'] ?? '') === 'instagram' && ($next['items'][0]['share'] ?? 0) === 2
    && $next_row && $next_row->angle === 'angle_b', [$next, $next_row]);
sgci_check('evergreen: the rotated AI answer is what reaches the queue', $next_row && str_contains($next_row->body, 'A quieter take on CI Evergreen One'), $next_row);
$prompt3 = $GLOBALS['sgci_evergreen_http'][2]['prompt'] ?? '';
$first_a = '';
foreach (array_slice($GLOBALS['sgci_evergreen_http'], 0, 2) as $call) {
    if (str_contains($call['prompt'], 'CI Evergreen One')) {
        $first_a = $call['answer']['text'] ?? '';
    }
}
sgci_check('evergreen: the next prompt carries the earlier captions as a do-not-repeat list', $first_a !== ''
    && str_contains($prompt3, 'These captions already ran') && str_contains($prompt3, 'Do not reuse their hook')
    && str_contains($prompt3, $first_a), $prompt3);
$full = SGAP_Evergreen::status($ever_a);
sgci_check('evergreen: the budget closes the page with a reason', $full['shares'] === 2 && !$full['eligible']
    && str_contains($full['reason'], 'all 2 shares used'), $full);
$paused = SGAP_Evergreen::status($ever_b);
sgci_check('evergreen: the other page is waiting for its next share', $paused['shares'] === 1 && !$paused['eligible']
    && str_contains($paused['reason'], 'next share from'), $paused);
sgci_check('evergreen: the summary counts three used shares and none ready', SGAP_Evergreen::summary()['shares_used'] === 3
    && SGAP_Evergreen::summary()['ready'] === 0);
sgci_check('evergreen: the run leaves a report on the option', is_array(get_option('sgap_last_evergreen'))
    && (get_option('sgap_last_evergreen')['recycled'] ?? -1) === 1, get_option('sgap_last_evergreen'));

SGAP_Evergreen::unmark($ever_a);
SGAP_Evergreen::unmark($ever_b);
sgci_check('evergreen: unmark clears the mark, the budget and the share count', !SGAP_Evergreen::status($ever_a)['marked']
    && get_post_meta($ever_a, SGAP_Evergreen::MARK, true) === '' && get_post_meta($ever_a, SGAP_Evergreen::MAX, true) === ''
    && get_post_meta($ever_a, SGAP_Evergreen::SHARES, true) === '', SGAP_Evergreen::status($ever_a));
$wpdb->delete($wpdb->prefix . 'sgap_queue', ['post_id' => $ever_a]);
$wpdb->delete($wpdb->prefix . 'sgap_queue', ['post_id' => $ever_b]);
wp_delete_post($ever_a, true);
wp_delete_post($ever_b, true);
update_option('sgap_evergreen_enabled', 'no');
remove_all_filters('pre_http_request');

/* --- Command Center link (one-way: CC calls us with X-Command-Center) --- */
delete_option(SGAP_CC::SEEN);
sgci_check('cc: never seen before any call', SGAP_CC::status()['state'] === 'never' && SGAP_CC::problem() === '');
$req = new WP_REST_Request('GET', '/sgap/v1/snapshot');
$req->set_param('days', 7);
$res = rest_do_request($req);
sgci_check('cc: a call without the header is not counted', $res->get_status() === 200 && get_option(SGAP_CC::SEEN) === false);
$req = new WP_REST_Request('GET', '/sgap/v1/snapshot');
$req->set_header('X-Command-Center', '7.19.0');
$res = rest_do_request($req);
$cc = SGAP_CC::status();
sgci_check('cc: snapshot call with the header marks it connected', $res->get_status() === 200 && $cc['state'] === 'connected' && $cc['version'] === '7.19.0' && $cc['last_route'] === '/sgap/v1/snapshot', $cc);
sgci_check('cc: snapshot reports the link', ($res->get_data()['status']['command_center']['state'] ?? '') === 'connected');
sgci_check('cc: dashboard line shows it', str_contains(SGAP_CC::line(), 'connected'));
wp_set_current_user(0);
delete_option(SGAP_CC::SEEN);
$req = new WP_REST_Request('GET', '/sgap/v1/snapshot');
$req->set_header('X-Command-Center', '7.19.0');
sgci_check('cc: an anonymous call is refused and not counted', rest_do_request($req)->get_status() === 401 && get_option(SGAP_CC::SEEN) === false);
wp_set_current_user((int) $admin[0]);
update_option(SGAP_CC::SEEN, ['ts' => time() - 5 * HOUR_IN_SECONDS, 'version' => '7.19.0', 'route' => '/sgap/v1/snapshot'], false);
sgci_check('cc: silent for 5 h is stale', SGAP_CC::status()['state'] === 'stale' && str_contains(SGAP_CC::problem(), 'has not called for 5 h'));
sgci_check('cc: self-check reports the silence', in_array(SGAP_CC::problem(), SGAP_Auto::health()['problems'], true));
delete_option(SGAP_CC::SEEN);

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
