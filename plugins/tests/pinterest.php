<?php
/**
 * P1.5b checks: Pinterest token rotation, the 90-day window, and the pin→queue match.
 *
 * Everything goes through SGAP_Pinterest::$transport, so no request leaves the machine
 * and no real credential is involved. Required from smoke.php, so $opts, $wpdb,
 * $GLOBALS['http_calls'] and check() already exist.
 */

$pinterest_options = [
    'sgap_pinterest_client_id', 'sgap_pinterest_client_secret', 'sgap_pinterest_refresh_token',
    'sgap_pinterest_access_token', 'sgap_pinterest_access_expires', 'sgap_pinterest_refresh_expires',
    'sgap_pinterest_pins', 'sgap_pinterest_bookmark', 'sgap_pinterest_state'
];
foreach ($pinterest_options as $key) {
    unset($GLOBALS['opts'][$key]);
}
SGAP_Pinterest::$transport = null;
$GLOBALS['http_calls'] = [];
$GLOBALS['rows'] = [];

// --- a token pasted before 7.33.3 is still a working credential ------------------------
$GLOBALS['opts']['sgap_pinterest_token'] = 'pina_legacy_pasted';
unset($GLOBALS['opts']['sgap_pinterest_access_token']);
check('pinterest: a legacy pinterest_token still counts as configured', SGAP_Pinterest::configured() && SGAP_Pinterest::saved_token() === 'pina_legacy_pasted');
check('pinterest: the legacy token is what a request would send', SGAP_Pinterest::token() === 'pina_legacy_pasted');
SGAP_Pinterest::migrate_legacy();
check('pinterest: the legacy token is copied into the field the Keys card shows', SGAP_Settings::get('pinterest_access_token') === 'pina_legacy_pasted');
$GLOBALS['opts']['sgap_pinterest_access_token'] = 'pina_current';
check('pinterest: a saved access token wins over the legacy copy', SGAP_Pinterest::saved_token() === 'pina_current');
SGAP_Pinterest::migrate_legacy();
check('pinterest: the migration never overwrites a saved access token', SGAP_Settings::get('pinterest_access_token') === 'pina_current');
unset($GLOBALS['opts']['sgap_pinterest_token'], $GLOBALS['opts']['sgap_pinterest_access_token']);
check('pinterest: with neither field there is still no token', SGAP_Pinterest::saved_token() === '' && !SGAP_Pinterest::configured());

// --- nothing configured: honest words, no request -------------------------------------
check('pinterest: nothing is configured until a token or app credentials exist', !SGAP_Pinterest::configured() && !SGAP_Pinterest::refreshable());
check('pinterest: an unconnected site raises no self-check problem', SGAP_Pinterest::problem() === '');
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, []);
};
SGAP_Pinterest::run();
check('pinterest: the cron is silent while nothing is configured', $GLOBALS['http_calls'] === []);
$unconfigured = SGAP_Pinterest::sync();
check('pinterest: sync without credentials explains itself and calls nothing', $unconfigured['configured'] === false
    && str_contains((string) $unconfigured['error'], 'Settings → Keys') && $GLOBALS['http_calls'] === []);

// --- which pin belongs to which queue row --------------------------------------------
check('pinterest: a tracked /go/ link names its queue row', SGAP_Pinterest::queue_id('https://steamgears.eu/go/42/') === 42);
check('pinterest: the utm variant of a tracked link still resolves', SGAP_Pinterest::queue_id('https://steamgears.eu/go/7/?utm_source=pinterest&utm_medium=social') === 7);
check('pinterest: another site\'s /go/ path is not our receipt', SGAP_Pinterest::queue_id('https://elsewhere.example/go/42/') === 0);
check('pinterest: a pin that links to the shop is not a queue row', SGAP_Pinterest::queue_id('https://steamgears.eu/shop/') === 0
    && SGAP_Pinterest::queue_id('') === 0 && SGAP_Pinterest::queue_id('/go/42/') === 0);

// --- a pasted Trial token works but cannot renew itself -------------------------------
$GLOBALS['opts']['sgap_pinterest_access_token'] = 'trial-token';
check('pinterest: a pasted token is configured but not refreshable', SGAP_Pinterest::configured() && !SGAP_Pinterest::refreshable());
check('pinterest: a hand-pasted token is used as it is', SGAP_Pinterest::token() === 'trial-token');
$no_renewal = SGAP_Pinterest::refresh();
check('pinterest: renewing without app credentials is refused and names the fix', is_wp_error($no_renewal)
    && str_contains($no_renewal->get_error_message(), 'Settings → Keys'));

// --- renewal: Basic auth, form body, rotated pair stored ------------------------------
$GLOBALS['opts']['sgap_pinterest_client_id'] = 'app-id';
$GLOBALS['opts']['sgap_pinterest_client_secret'] = 'app-secret';
$GLOBALS['opts']['sgap_pinterest_refresh_token'] = 'refresh-1';
update_option('sgap_pinterest_state', ['renew_ts' => 0]);
$GLOBALS['http_calls'] = [];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, [
        'access_token' => 'access-1', 'refresh_token' => 'refresh-2', 'expires_in' => 2592000,
        'refresh_token_expires_at' => time() + 5184000
    ]);
};
$renewed = SGAP_Pinterest::refresh();
check('pinterest: renewal is a form-encoded POST to /v5/oauth/token', ($GLOBALS['http_calls'][0][0] ?? '') === 'POST'
    && ($GLOBALS['http_calls'][0][1] ?? '') === 'https://api.pinterest.com/v5/oauth/token'
    && ($GLOBALS['http_calls'][0][2]['body']['grant_type'] ?? '') === 'refresh_token');
check('pinterest: renewal uses HTTP Basic with the app id and secret', ($GLOBALS['http_calls'][0][2]['headers']['Authorization'] ?? '')
    === 'Basic ' . base64_encode('app-id:app-secret'));
check('pinterest: renewal asks for the continuous refresh token', ($GLOBALS['http_calls'][0][2]['body']['continuous_refresh'] ?? '') === 'true');
check('pinterest: the new access token and its expiry are stored', get_option('sgap_pinterest_access_token') === 'access-1'
    && (int) get_option('sgap_pinterest_access_expires') > time());
check('pinterest: the rotated refresh token is written back', SGAP_Pinterest::refresh_token() === 'refresh-2' && !empty($renewed['rotated']));
check('pinterest: the rotated secret is stored without autoload', ($GLOBALS['autoload']['sgap_pinterest_refresh_token'] ?? null) === false);
check('pinterest: a valid cached token is reused without another call', SGAP_Pinterest::token() === 'access-1' && count($GLOBALS['http_calls']) === 1);

// --- a refused renewal is reported, never swallowed ----------------------------------
update_option('sgap_pinterest_access_token', '');
update_option('sgap_pinterest_access_expires', 0);
update_option('sgap_pinterest_state', ['renew_ts' => 0]);
$GLOBALS['http_queue'] = [sgap_http(400, ['message' => 'The refresh token is invalid or expired'])];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$refused = SGAP_Pinterest::refresh();
check('pinterest: a refused renewal carries Pinterest\'s own reason', is_wp_error($refused)
    && str_contains($refused->get_error_message(), 'invalid or expired') && (int) ($refused->data['status'] ?? 0) === 400);
check('pinterest: the refusal reaches the daily self-check', str_contains(SGAP_Pinterest::problem(), 'Pinterest analytics')
    && str_contains(SGAP_Pinterest::problem(), 'invalid or expired'));
$throttled = SGAP_Pinterest::refresh();
check('pinterest: a second renewal inside the window is not sent', is_wp_error($throttled)
    && str_contains($throttled->get_error_message(), 'five minutes'));

// --- analytics: documented query, three metrics, zeros for what is missing -----------
update_option('sgap_pinterest_access_token', 'access-3');
update_option('sgap_pinterest_access_expires', time() + 30 * DAY_IN_SECONDS);
$GLOBALS['http_calls'] = [];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, ['all' => ['summary_metrics' => ['IMPRESSION' => 240, 'SAVE' => 20, 'OUTBOUND_CLICK' => 7, 'PIN_CLICK' => 37]]]);
};
$counts = SGAP_Pinterest::analytics('123', '2026-08-10', '2026-11-07');
$url = (string) ($GLOBALS['http_calls'][0][1] ?? '');
check('pinterest: analytics asks for the window and the three documented metrics',
    str_contains($url, '/pins/123/analytics') && str_contains($url, 'start_date=2026-08-10') && str_contains($url, 'end_date=2026-11-07')
    && str_contains($url, 'metric_types=IMPRESSION%2CSAVE%2COUTBOUND_CLICK'));
check('pinterest: the call is authorised with a Bearer token, never a query parameter',
    ($GLOBALS['http_calls'][0][2]['headers']['Authorization'] ?? '') === 'Bearer access-3' && !str_contains($url, 'access-3'));
check('pinterest: impressions, saves and outbound clicks come from summary_metrics',
    $counts === ['impressions' => 240, 'saves' => 20, 'outbound_clicks' => 7]);
SGAP_Pinterest::$transport = fn($method, $url, $args) => sgap_http(200, ['all' => ['summary_metrics' => []]]);
$zero = SGAP_Pinterest::analytics('1', '2026-11-01', '2026-11-07');
check('pinterest: a metric Pinterest omits counts as zero, not as a skip', $zero === ['impressions' => 0, 'saves' => 0, 'outbound_clicks' => 0]);
SGAP_Pinterest::$transport = fn($method, $url, $args) => sgap_http(200, ['all' => ['daily_metrics' => []]]);
$shape = SGAP_Pinterest::analytics('1', '2026-11-01', '2026-11-07');
check('pinterest: an answer without summary_metrics is an error, not a zero', is_wp_error($shape) && $shape->code === 'sgap_pinterest_shape');

// --- a 401 renews once and retries the call -----------------------------------------
update_option('sgap_pinterest_state', ['renew_ts' => 0]);
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(401, ['message' => 'Authentication failed']),
    sgap_http(200, ['access_token' => 'access-4', 'refresh_token' => 'refresh-4', 'expires_in' => 2592000]),
    sgap_http(200, ['all' => ['summary_metrics' => ['IMPRESSION' => 9]]])
];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$retried = SGAP_Pinterest::analytics('55', '2026-11-01', '2026-11-07');
check('pinterest: an expired token is renewed once and the call retried', is_array($retried)
    && count($GLOBALS['http_calls']) === 3
    && str_contains((string) $GLOBALS['http_calls'][1][1], '/oauth/token')
    && str_contains((string) $GLOBALS['http_calls'][2][1], '/pins/55/analytics'));

// --- a refused token is remembered, so the health screen can say so ------------------
unset($GLOBALS['opts']['sgap_pinterest_refresh_token'], $GLOBALS['opts']['sgap_pinterest_client_secret'], $GLOBALS['opts']['sgap_pinterest_refused']);
$GLOBALS['opts']['sgap_pinterest_access_token'] = 'pina_dead';
SGAP_Pinterest::$transport = fn($method, $url, $args) => sgap_http(401, ['message' => 'Authentication failed']);
$refused = SGAP_Pinterest::analytics('56', '2026-11-01', '2026-11-07');
check('pinterest: a 401 with nothing to renew is an error', is_wp_error($refused) && $refused->code === 'sgap_pinterest_401');
check('pinterest: the refusal is remembered with a date', (SGAP_Pinterest::refused()['code'] ?? 0) === 401
    && (SGAP_Pinterest::refused()['at'] ?? '') !== '');
SGAP_Pinterest::$transport = fn($method, $url, $args) => sgap_http(200, ['all' => ['summary_metrics' => []]]);
SGAP_Pinterest::analytics('56', '2026-11-01', '2026-11-07');
check('pinterest: a working call clears the refusal', SGAP_Pinterest::refused() === []);
$GLOBALS['opts']['sgap_pinterest_refused'] = ['at' => '2026-11-08 10:00:00', 'code' => 401];
SGAP_Pinterest::forget_refusal('old-token', 'new-token');
check('pinterest: a new token forgets the old refusal', SGAP_Pinterest::refused() === []);
$GLOBALS['opts']['sgap_pinterest_refused'] = ['at' => '2026-11-08 10:00:00', 'code' => 401];
SGAP_Pinterest::forget_refusal('same', 'same');
check('pinterest: saving an unchanged token keeps the refusal', (SGAP_Pinterest::refused()['code'] ?? 0) === 401);
unset($GLOBALS['opts']['sgap_pinterest_refused']);

// --- pin listing: bookmark paging, mapping kept across runs -------------------------
$GLOBALS['opts']['sgap_pinterest_pins'] = [];
$GLOBALS['opts']['sgap_pinterest_bookmark'] = '';
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(200, ['items' => [
        ['id' => 'pin-1', 'link' => 'https://steamgears.eu/go/7/', 'created_at' => '2026-11-01T10:00:00'],
        ['id' => 'pin-2', 'link' => 'https://example.com/x', 'created_at' => '2026-11-02T10:00:00']
    ], 'bookmark' => 'b2']),
    sgap_http(200, ['items' => [
        ['id' => 'pin-3', 'link' => 'https://steamgears.eu/go/8/?utm_source=pinterest', 'created_at' => '2026-11-03T10:00:00']
    ], 'bookmark' => ''])
];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$scan = SGAP_Pinterest::scan();
check('pinterest: the pin list follows the bookmark to the end', is_array($scan)
    && $scan['pages'] === 2 && $scan['complete'] === true && $scan['pins'] === 3);
check('pinterest: the next page asks for the bookmark Pinterest returned', str_contains((string) ($GLOBALS['http_calls'][1][1] ?? ''), 'bookmark=b2'));
check('pinterest: the mapping keeps each pin\'s link', (SGAP_Pinterest::mapping()['pin-1']['link'] ?? '') === 'https://steamgears.eu/go/7/');
check('pinterest: the bookmark is cleared when the last page is in', get_option('sgap_pinterest_bookmark', '') === '');
$known = SGAP_Pinterest::mapping();
$known['pin-1']['synced_at'] = 123;
update_option('sgap_pinterest_pins', $known);
$GLOBALS['http_queue'] = [sgap_http(200, ['items' => [
    ['id' => 'pin-1', 'link' => 'https://steamgears.eu/go/7/', 'created_at' => '2026-11-01T10:00:00']
], 'bookmark' => ''])];
SGAP_Pinterest::scan();
check('pinterest: a known pin keeps its snapshot time when the list is re-read', (SGAP_Pinterest::mapping()['pin-1']['synced_at'] ?? 0) === 123);

// --- one run: match, spend the budget, store a snapshot per row ---------------------
$GLOBALS['opts']['sgap_pinterest_pins'] = [];
$GLOBALS['rows'] = [
    ['id' => '7', 'angle' => 'angle_a', 'published_at' => '2026-11-05 09:00:00', 'external_id' => ''],
    ['id' => '8', 'angle' => 'angle_b', 'published_at' => '2026-11-06 09:00:00', 'external_id' => '']
];
$GLOBALS['http_calls'] = [];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    if (str_contains($url, '/pins?')) {
        return sgap_http(200, ['items' => [
            ['id' => 'pin-1', 'link' => 'https://steamgears.eu/go/7/', 'created_at' => '2026-11-01'],
            ['id' => 'pin-2', 'link' => 'https://example.com/x', 'created_at' => '2026-11-02'],
            ['id' => 'pin-3', 'link' => 'https://steamgears.eu/go/8/', 'created_at' => '2026-11-03'],
            ['id' => 'pin-9', 'link' => 'https://steamgears.eu/go/999/', 'created_at' => '2026-11-04']
        ], 'bookmark' => '']);
    }
    if (str_contains($url, 'pin-1/analytics')) {
        return sgap_http(200, ['all' => ['summary_metrics' => ['IMPRESSION' => 100, 'SAVE' => 5, 'OUTBOUND_CLICK' => 2]]]);
    }
    return sgap_http(403, ['message' => 'You are not permitted to view this resource']);
};
$wpdb->log = [];
$report = SGAP_Pinterest::sync(5);
$urls = array_map(static fn($call) => (string) $call[1], $GLOBALS['http_calls']);
$analytics = array_values(array_filter($urls, static fn($u) => str_contains($u, '/analytics')));
check('pinterest: a run separates pins that link elsewhere from pins whose post is gone',
    $report['unmapped'] === 1 && $report['orphaned'] === 1 && $report['matched'] === 2);
check('pinterest: one analytics call per matched pin', count($analytics) === 2);
check('pinterest: the window starts at the post\'s publication day', (bool) array_filter($analytics,
    static fn($u) => str_contains($u, 'pin-1/analytics') && str_contains($u, 'start_date=2026-11-05')));
check('pinterest: a refused pin is reported and the run continues', $report['updated'] === 1
    && count($report['errors']) === 1 && str_contains($report['errors'][0], '403'));
check('pinterest: the three types replace the old snapshot for a row', substr_count(implode("\n", $wpdb->log), 'DELETE FROM wp_sgap_events WHERE queue_id') === 1
    && substr_count(implode("\n", $wpdb->log), 'INSERT wp_sgap_events') === 3);
check('pinterest: the run is recorded with its counts for the dashboard', (int) (SGAP_Pinterest::status()['report']['matched'] ?? 0) === 2
    && SGAP_Pinterest::state()['at'] !== '');
$before = count($GLOBALS['http_calls']);
SGAP_Pinterest::sync(1);
$spent = array_filter(array_slice(array_map(static fn($call) => (string) $call[1], $GLOBALS['http_calls']), $before),
    static fn($u) => str_contains($u, '/analytics'));
check('pinterest: a run never spends more than its analytics budget', count($spent) === 1);

// --- 90 days is the floor, and it applies to the window, not to the store -----------
check('pinterest: a post older than 90 days is only asked for the last 90', SGAP_Pinterest::start_date('2026-01-01') === SGAP_Pinterest::floor_date());
check('pinterest: the floor is never more than 90 days back', SGAP_Pinterest::floor_date() >= gmdate('Y-m-d', time() - 89 * DAY_IN_SECONDS));
check('pinterest: a fresh post keeps its own publication day', SGAP_Pinterest::start_date('2026-11-05 09:00:00') === '2026-11-05');
check('pinterest: an unreadable publication date falls back to the floor', SGAP_Pinterest::start_date('') === SGAP_Pinterest::floor_date());

// --- reading it back: platform numbers never mix with our own clicks ----------------
$GLOBALS['rows'] = [
    ['channel' => 'pinterest', 'event_type' => 'impressions', 'total' => '150', 'rows_count' => '2'],
    ['channel' => 'pinterest', 'event_type' => 'saves', 'total' => '6', 'rows_count' => '2'],
    ['channel' => 'pinterest', 'event_type' => 'outbound_clicks', 'total' => '2', 'rows_count' => '2']
];
$wpdb->log = [];
$summary = SGAP_Pinterest::summary();
check('pinterest: the summary sums the three platform types', ($summary['pinterest']['impressions'] ?? null) === 150.0
    && ($summary['pinterest']['saves'] ?? null) === 6.0 && ($summary['pinterest']['outbound_clicks'] ?? null) === 2.0);
check('pinterest: the summary query is limited to the types this module owns',
    str_contains(str_replace(' ', '', implode("\n", $wpdb->log)), "'impressions','saves','outbound_clicks'"));
check('pinterest: Pinterest numbers never enter the Bluesky/Mastodon snapshot', array_intersect(SGAP_Pinterest::TYPES, SGAP_Engagement::TYPES) === []);
ob_start();
SGAP_Pinterest::dashboard_card();
$card = ob_get_clean();
check('pinterest admin: the card shows impressions, saves and outbound clicks',
    str_contains($card, 'Pinterest') && str_contains($card, 'Impressions') && str_contains($card, '>150</td>') && str_contains($card, '>2</td>'));
foreach ($pinterest_options as $key) {
    unset($GLOBALS['opts'][$key]);
}
ob_start();
SGAP_Pinterest::dashboard_card();
$empty_card = ob_get_clean();
check('pinterest admin: an unconnected account is explained instead of shown as zero',
    str_contains($empty_card, 'not connected') && str_contains($empty_card, 'Settings → Keys') && !str_contains($empty_card, '<table'));

// --- the credentials have one home, labels and help --------------------------------
check('pinterest: the access token, refresh token and secret are secrets',
    in_array('pinterest_access_token', SGAP_Settings::SECRET_KEYS, true)
    && in_array('pinterest_refresh_token', SGAP_Settings::SECRET_KEYS, true)
    && in_array('pinterest_client_secret', SGAP_Settings::SECRET_KEYS, true));
check('pinterest: the app credentials have their own keys card', isset(SGAP_Keys::PROVIDERS['pinterest'])
    && SGAP_Keys::PROVIDERS['pinterest']['keys'] === ['pinterest_access_token', 'pinterest_refresh_token', 'pinterest_client_secret']);
check('pinterest: the App ID is an ordinary setting with help text',
    in_array('pinterest_client_id', SGAP_Settings::TEXT_KEYS, true)
    && isset(SGAP_Settings::HELP['pinterest_client_id'])
    && in_array('pinterest_client_id', SGAP_Setup::TABS['publishing'][2], true));
check('plugin: the Pinterest module ships in the current version', version_compare(SGAP_VERSION, '7.53.1', '>=') && class_exists('SGAP_Pinterest'));

// --- a token with no recorded expiry is usable, not "expired" ------------------------
$GLOBALS['opts']['sgap_pinterest_access_token'] = 'pasted-by-hand';
update_option('sgap_pinterest_access_expires', 0);
check('pinterest: a pasted token with no recorded expiry counts as valid, not expired',
    SGAP_Pinterest::status()['token_valid'] === true && SGAP_Pinterest::token() === 'pasted-by-hand');

// --- a refused pin listing keeps the pins that are already mapped --------------------
$GLOBALS['opts']['sgap_pinterest_pins'] = [
    'pin-1' => ['link' => 'https://steamgears.eu/go/7/', 'created_at' => '2026-11-01', 'synced_at' => 0]
];
$GLOBALS['rows'] = [
    ['id' => '7', 'angle' => 'angle_a', 'published_at' => '2026-11-05 09:00:00', 'external_id' => '']
];
$GLOBALS['http_calls'] = [];
SGAP_Pinterest::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    if (str_contains($url, '/pins?')) {
        return sgap_http(403, ['message' => 'Not permitted']);
    }
    return sgap_http(200, ['all' => ['summary_metrics' => ['IMPRESSION' => 12, 'SAVE' => 1, 'OUTBOUND_CLICK' => 1]]]);
};
$partial = SGAP_Pinterest::sync(5);
check('pinterest: a refused pin listing still measures the pins already mapped', $partial['configured'] === true
    && $partial['matched'] === 1 && $partial['updated'] === 1 && $partial['pins'] === 1
    && count($partial['errors']) === 1 && str_contains($partial['errors'][0], 'Pin listing'));
$GLOBALS['opts']['sgap_pinterest_pins'] = [];
SGAP_Pinterest::$transport = null;

// --- a wp-config.php constant wins, so renewal must refuse rather than write a dead value
// Defined last on purpose: a PHP constant cannot be removed, and the keys checks that follow
// render the settings page, where a constant legitimately replaces the field with a note.
define('SGAP_PINTEREST_ACCESS_TOKEN', 'constant-token');
update_option('sgap_pinterest_access_token', 'stored-token');
update_option('sgap_pinterest_access_expires', time() + DAY_IN_SECONDS);
check('pinterest: a wp-config access token is used instead of the stored one',
    SGAP_Pinterest::access_constant() === 'SGAP_PINTEREST_ACCESS_TOKEN'
    && SGAP_Pinterest::token() === 'constant-token');
$locked_refresh = SGAP_Pinterest::refresh();
check('pinterest: renewal is refused when a constant would swallow the rotated token',
    is_wp_error($locked_refresh)
    && str_contains($locked_refresh->get_error_message(), 'SGAP_PINTEREST_ACCESS_TOKEN'));

$GLOBALS['http_calls'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['http_queue'] = [];
