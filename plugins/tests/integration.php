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

if ($GLOBALS['sgci_failed']) {
    WP_CLI::error('Integration checks failed.');
}
WP_CLI::success('All integration checks passed.');
