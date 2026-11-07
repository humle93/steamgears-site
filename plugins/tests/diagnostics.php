<?php
/**
 * P1.5d checks: the error log, the checks behind the Fejlsøgning page, and the caveats
 * that travel with the numbers.
 *
 * No WordPress and no network: the state comes from options, and the database questions
 * are answered by the $wpdb stub. Required from smoke.php, so check(), $opts and $wpdb
 * already exist.
 */

foreach ([
    'sgap_error_log', 'sgap_diagnostics_state', 'sgap_ai_api_key',
    'sgap_pinterest_access_token', 'sgap_pinterest_refresh_token', 'sgap_pinterest_state',
    'sgap_instagram_user_id', 'sgap_instagram_access_token', 'sgap_instagram_state'
] as $key) {
    unset($GLOBALS['opts'][$key]);
}
$GLOBALS['next_scheduled'] = [];
$wpdb->next = null;
$wpdb->row = null;

// --- the log keeps what the module state forgets -------------------------------------
check('diagnostics: nothing is logged on a clean install', SGAP_Diagnostics::entries() === [] && SGAP_Diagnostics::count_since() === 0);
check('diagnostics: a clean log raises no self-check problem', SGAP_Diagnostics::problem() === '');

SGAP_Diagnostics::log('instagram', 'Instagram answered HTTP 500 for /media.');
$entries = SGAP_Diagnostics::entries();
check('diagnostics: a plain string is stored with its source', count($entries) === 1 && $entries[0]['source'] === 'instagram');
check('diagnostics: the message is kept verbatim', $entries[0]['message'] === 'Instagram answered HTTP 500 for /media.');
check('diagnostics: an entry carries a machine timestamp, not just the site clock', (int) $entries[0]['ts'] > 0);
check('diagnostics: the first occurrence counts as one', (int) $entries[0]['count'] === 1);

SGAP_Diagnostics::log('pinterest', 'Pinterest refused the token.');
$entries = SGAP_Diagnostics::entries();
check('diagnostics: the newest error comes first', count($entries) === 2 && $entries[0]['source'] === 'pinterest');

SGAP_Diagnostics::log('pinterest', 'Pinterest refused the token.');
$entries = SGAP_Diagnostics::entries();
check('diagnostics: the same error twice is one row with a count', count($entries) === 2 && (int) $entries[0]['count'] === 2);
check('diagnostics: the repeat is stored as the newest row', $entries[0]['source'] === 'pinterest' && $entries[1]['source'] === 'instagram');

SGAP_Diagnostics::log('instagram', new WP_Error('sgap_instagram_401', 'Instagram refused the access token (HTTP 400).'));
$entries = SGAP_Diagnostics::entries();
check('diagnostics: a WP_Error keeps its code', $entries[0]['code'] === 'sgap_instagram_401');
check('diagnostics: a WP_Error keeps its message', $entries[0]['message'] === 'Instagram refused the access token (HTTP 400).');

SGAP_Diagnostics::log('publer', ['code' => 'send_failed', 'message' => 'Publer answered 502.']);
check('diagnostics: an array error is accepted too', SGAP_Diagnostics::entries()[0]['code'] === 'send_failed');
check('diagnostics: an empty message is not a row', count(SGAP_Diagnostics::entries()) === 4 && (SGAP_Diagnostics::log('x', '   ')) === null);

SGAP_Diagnostics::log('mcp', str_repeat('a', 400));
$long = SGAP_Diagnostics::entries()[0]['message'];
check('diagnostics: a long message is cut at the limit', (function_exists('mb_strlen') ? mb_strlen($long) : strlen($long)) <= SGAP_Diagnostics::MESSAGE_MAX && str_ends_with($long, '…'));

SGAP_Diagnostics::log('automation', 'Queue row 12 failed.', ['Channel' => 'instagram', 'count' => 3, 'payload' => ['x' => 1]]);
$ctx = SGAP_Diagnostics::entries()[0]['context'];
check('diagnostics: scalar context is kept and the key sanitised', ($ctx['channel'] ?? '') === 'instagram');
check('diagnostics: a non-scalar is not stored', !isset($ctx['payload']));
check('diagnostics: context values are shortened', strlen($ctx['count'] ?? '') <= 80);

// --- the ring buffer and the window --------------------------------------------------
unset($GLOBALS['opts']['sgap_error_log']);
for ($i = 0; $i < SGAP_Diagnostics::MAX + 6; $i++) {
    SGAP_Diagnostics::log('loop', 'failure ' . $i);
}
check('diagnostics: the log never grows past MAX', count(SGAP_Diagnostics::entries()) === SGAP_Diagnostics::MAX);
check('diagnostics: the oldest rows are the ones dropped', str_ends_with(SGAP_Diagnostics::entries()[0]['message'], (string) (SGAP_Diagnostics::MAX + 5)));

$entries = SGAP_Diagnostics::entries();
$entries[] = ['at' => '2026-01-01 00:00:00', 'ts' => time() - 3 * DAY_IN_SECONDS, 'source' => 'old', 'code' => '', 'message' => 'last week', 'count' => 4, 'context' => []];
$GLOBALS['opts']['sgap_error_log'] = $entries;
check('diagnostics: the 24-hour count ignores older rows', SGAP_Diagnostics::count_since(24) === SGAP_Diagnostics::MAX);
check('diagnostics: a wider window includes them', SGAP_Diagnostics::count_since(168) === SGAP_Diagnostics::MAX + 4);

SGAP_Diagnostics::clear();
check('diagnostics: clear empties the log and the snapshot', SGAP_Diagnostics::entries() === [] && !isset($GLOBALS['opts']['sgap_diagnostics_state']));

// --- the caveats are content, not decoration -----------------------------------------
foreach (['instagram', 'pinterest', 'engagement', 'roi', 'automation', 'mcp'] as $key) {
    check('diagnostics: ' . $key . ' has a caveat', SGAP_Diagnostics::caveat($key) !== '');
}
check('diagnostics: an unknown caveat is empty', SGAP_Diagnostics::caveat('nope') === '');
check('diagnostics: the instagram caveat says missing is not zero', str_contains(SGAP_Diagnostics::caveat('instagram'), 'not stored as 0'));
check('diagnostics: the pinterest caveat names the channel scoping', str_contains(SGAP_Diagnostics::caveat('pinterest'), 'scoped to the pinterest channel'));

// --- the checks read state, they do not write it -------------------------------------
$ids = array_column(SGAP_Diagnostics::checks(), 'id');
check('diagnostics: every check is present', $ids === ['php', 'tables', 'cron', 'queue', 'ai', 'pinterest', 'instagram', 'facebook', 'mcp', 'errors']);
$states = array_column(SGAP_Diagnostics::checks(), 'state');
check('diagnostics: a state is one of the four', count(array_diff($states, ['ok', 'warn', 'bad', 'off'])) === 0);
check('diagnostics: a failing check always says what to do', array_reduce(SGAP_Diagnostics::checks(), fn($c, $r) => $c && ($r['state'] === 'ok' || $r['fix'] !== ''), true));

// The four table questions arrive in the order the checks ask them.
$wpdb->next = ['wp_sgap_queue', 'wp_sgap_events', 'wp_sgap_ai_drafts', 'wp_sgap_publish_log'];
$tables = SGAP_Diagnostics::checks()[1];
check('diagnostics: existing tables pass', $tables['state'] === 'ok');
$wpdb->next = ['wp_sgap_queue', null, null, null];
$tables = SGAP_Diagnostics::checks()[1];
check('diagnostics: a missing table fails the check', $tables['state'] === 'bad' && str_contains($tables['detail'], 'sgap_events'));

// The scheduler hands everything to Action Scheduler when it is loaded, so that is the
// stub the cron row reads here (smoke.php loads the AS stubs).
$wpdb->next = ['wp_sgap_queue', 'wp_sgap_events', 'wp_sgap_ai_drafts', 'wp_sgap_publish_log'];
$GLOBALS['as']['next'] = time() + 600;
check('diagnostics: four scheduled jobs pass', SGAP_Diagnostics::checks()[2]['state'] === 'ok');
$GLOBALS['as']['next'] = 0;
$cron = SGAP_Diagnostics::checks()[2];
check('diagnostics: a job with no next run fails', $cron['state'] === 'bad' && str_contains($cron['detail'], 'Not scheduled'));
$GLOBALS['as']['next'] = time() - 3 * HOUR_IN_SECONDS;
$cron = SGAP_Diagnostics::checks()[2];
check('diagnostics: a job hours late warns instead of failing', $cron['state'] === 'warn' && str_contains($cron['detail'], 'late'));
$GLOBALS['as']['next'] = 0;

// A channel that was never connected is not broken. Pinterest is a different case in this
// harness: smoke.php defines a Pinterest token in wp-config.php, so the plugin reports the
// refresh it cannot do — that is a fault, and it is reported as one.
$channels = array_column(array_slice(SGAP_Diagnostics::checks(), 5, 3), 'state', 'id');
check('diagnostics: an unconnected channel is off, not bad', $channels['instagram'] === 'off' && $channels['facebook'] === 'off');
check('diagnostics: a token that cannot be refreshed is a fault, not an off row', $channels['pinterest'] === 'bad');
SGAP_Settings::set('instagram_user_id', '17841400000000000');
SGAP_Settings::set('instagram_access_token', 'IGQVJtest');
$row = array_column(array_slice(SGAP_Diagnostics::checks(), 5, 3), 'state', 'id');
check('diagnostics: a connected channel is accounted for', $row['instagram'] !== 'off');
check('diagnostics: a channel that never ran says so', str_contains(SGAP_Diagnostics::checks()[6]['detail'], 'never'));
SGAP_Diagnostics::log('instagram', 'Media listing: Instagram refused the access token (HTTP 400).');
$row = array_column(array_slice(SGAP_Diagnostics::checks(), 5, 3), 'state', 'id');
check('diagnostics: a logged failure reaches the channel row through the module state', in_array($row['instagram'], ['warn', 'bad'], true));

SGAP_Diagnostics::clear();
SGAP_Diagnostics::log('instagram', 'one');
check('diagnostics: one error in the window is a warning', SGAP_Diagnostics::checks()[9]['state'] === 'warn');
SGAP_Diagnostics::log('pinterest', 'two');
SGAP_Diagnostics::log('publer', 'three');
check('diagnostics: three errors in the window fail the check', SGAP_Diagnostics::checks()[9]['state'] === 'bad');
check('diagnostics: the error check counts the window', str_contains(SGAP_Diagnostics::checks()[9]['detail'], '3 error(s)'));

// --- the snapshot, the line and the report -------------------------------------------
$status = SGAP_Diagnostics::status();
check('diagnostics: the status carries every check', count($status['checks']) === 10);
check('diagnostics: the totals add up to the checks', array_sum($status['totals']) === 10);
check('diagnostics: the totals count the states they claim', $status['totals']['bad'] === count(array_filter($status['checks'], fn($c) => $c['state'] === 'bad')));
check('diagnostics: the status has a timestamp', $status['at'] !== '');
check('diagnostics: the snapshot is stored for the dashboard', ($GLOBALS['opts']['sgap_diagnostics_state']['totals'] ?? null) === $status['totals']);
check('diagnostics: the summary names what needs attention', str_contains($status['problem'], 'error(s) in the last 24 h'));
check('diagnostics: the self-check line points at the page', str_contains(SGAP_Diagnostics::problem(), 'Fejlsøgning'));

$report = SGAP_Diagnostics::rest_report();
check('diagnostics: the REST report is a response with the same report', $report instanceof WP_REST_Response && count($report->data['checks']) === 10);
check('diagnostics: the REST report carries the caveats', isset($report->data['caveats']['instagram']));

// --- the page and the dashboard card -------------------------------------------------
ob_start();
SGAP_Diagnostics::page();
$html = ob_get_clean();
check('diagnostics: the page lists the checks', str_contains($html, 'Checks') && str_contains($html, 'Failed posts'));
check('diagnostics: the page shows what to do', str_contains($html, 'What to do'));
check('diagnostics: the page spells out the caveats', str_contains($html, 'Forbehold') && str_contains($html, 'not stored as 0'));
check('diagnostics: the page offers the buttons', str_contains($html, 'Run the checks now') && str_contains($html, 'sgap_diagnostics_clear'));
check('diagnostics: the page escapes the log it prints', str_contains($html, 'one') && !str_contains($html, '<script>alert'));

ob_start();
SGAP_Diagnostics::dashboard_card();
$card = ob_get_clean();
check('diagnostics: the dashboard card appears when something is wrong', str_contains($card, 'Fejlsøgning') && str_contains($card, 'sgap-diagnostics'));

SGAP_Diagnostics::clear();
unset($GLOBALS['opts']['sgap_instagram_user_id'], $GLOBALS['opts']['sgap_instagram_access_token'], $GLOBALS['opts']['sgap_instagram_state']);
// Five SHOW TABLES lookups (the table check plus the queue's own) and then the failed count.
$wpdb->next = ['wp_sgap_queue', 'wp_sgap_events', 'wp_sgap_ai_drafts', 'wp_sgap_publish_log', 'wp_sgap_queue', '0'];
SGAP_Settings::set('ai_api_key', 'sk-test');
$GLOBALS['opts'][SGAP_KeyCheck::RESULT] = ['ai' => ['ok' => true, 'message' => 'ok']];
$GLOBALS['as']['next'] = time() + 600;
$green = SGAP_Diagnostics::status();
$green_bad = array_values(array_filter($green['checks'], fn($c) => $c['state'] === 'bad'));
// The two channel rows are allowed to complain here: smoke.php defines a Pinterest token in
// wp-config.php, which the plugin rightly refuses to refresh. Nothing else may.
check('diagnostics: no false alarm outside the channel rows', array_diff(array_column($green_bad, 'id'), ['pinterest', 'instagram']) === []);
check('diagnostics: a healthy check set needs no attention', $green['totals']['warn'] === 0);
check('diagnostics: the card has a quiet path', SGAP_Diagnostics::quiet(['problem' => '', 'errors' => 0]));
check('diagnostics: the quiet path is not taken when an error is logged', !SGAP_Diagnostics::quiet(['problem' => '', 'errors' => 2]));
check('diagnostics: the quiet path is not taken when a check fails', !SGAP_Diagnostics::quiet(['problem' => '1 check(s) failed', 'errors' => 0]));

// Leave the shared stubs as the next test file expects them.
SGAP_Diagnostics::clear();
$wpdb->next = null;
$GLOBALS['as']['next'] = 0;
