<?php
/**
 * P1.5c checks: Instagram insights, the caption match, and the in-place token refresh.
 *
 * Everything goes through SGAP_Instagram::$transport, so no request leaves the machine and
 * no real credential is involved. Required from smoke.php, so $opts, $wpdb,
 * $GLOBALS['http_calls'] and check() already exist.
 */

$instagram_options = [
    'sgap_instagram_user_id', 'sgap_instagram_graph_host', 'sgap_instagram_access_token',
    'sgap_instagram_access_expires', 'sgap_instagram_media', 'sgap_instagram_after', 'sgap_instagram_state'
];
foreach ($instagram_options as $key) {
    unset($GLOBALS['opts'][$key]);
}
SGAP_Instagram::$transport = null;
$GLOBALS['http_calls'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['http_queue'] = [];

// --- nothing configured: honest words, no request -------------------------------------
check('instagram: nothing is configured until an account id and a token exist', !SGAP_Instagram::configured());
check('instagram: an unconnected account raises no self-check problem', SGAP_Instagram::problem() === '');
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, []);
};
SGAP_Instagram::run();
check('instagram: the cron is silent while nothing is configured', $GLOBALS['http_calls'] === []);
$unconfigured = SGAP_Instagram::sync();
check('instagram: sync without credentials explains itself and calls nothing',
    $unconfigured['configured'] === false && str_contains((string) $unconfigured['error'], 'Settings → Keys')
    && $GLOBALS['http_calls'] === []);

// --- which post belongs to which queue row ------------------------------------------
check('instagram: a tracked link in the caption names its queue row',
    SGAP_Instagram::queue_id('New drop — shop it at https://steamgears.eu/go/42/ today') === 42);
check('instagram: the caption needs no scheme, because people paste it without one',
    SGAP_Instagram::queue_id('steamgears.eu/go/7/ #steampunk') === 7);
check('instagram: the www. variant still resolves', SGAP_Instagram::queue_id('https://www.steamgears.eu/go/9/?utm_source=instagram') === 9);
check('instagram: another site\'s /go/ path is not our receipt',
    SGAP_Instagram::queue_id('https://elsewhere.example/go/42/') === 0);
check('instagram: a caption without a tracked link is nobody\'s post',
    SGAP_Instagram::queue_id('#steampunk #gears') === 0 && SGAP_Instagram::queue_id('') === 0
    && SGAP_Instagram::queue_id('https://steamgears.eu/shop/') === 0);

// --- the two login types talk to different hosts -------------------------------------
$GLOBALS['opts']['sgap_instagram_user_id'] = '17841405822304914';
$GLOBALS['opts']['sgap_instagram_access_token'] = 'IG-token-1';
check('instagram: the account id and the token are both needed', SGAP_Instagram::configured());
check('instagram: Instagram Login is the default host', SGAP_Instagram::host() === 'graph.instagram.com');
$GLOBALS['opts']['sgap_instagram_graph_host'] = 'graph.facebook.com';
check('instagram: a Facebook Login token uses the Facebook host', SGAP_Instagram::host() === 'graph.facebook.com'
    && !SGAP_Instagram::refreshable());
$fb_refresh = SGAP_Instagram::refresh();
check('instagram: a Facebook Login token cannot be refreshed in place, and the refusal says why',
    is_wp_error($fb_refresh) && str_contains($fb_refresh->get_error_message(), 'app secret'));
$GLOBALS['opts']['sgap_instagram_graph_host'] = 'nonsense.example';
check('instagram: an unknown host falls back to the Instagram one instead of sending the token there',
    SGAP_Instagram::host() === 'graph.instagram.com');
$GLOBALS['opts']['sgap_instagram_graph_host'] = 'graph.instagram.com';

// --- a token with no recorded expiry is used as it is -------------------------------
check('instagram: a pasted token with no expiry is valid and is used', SGAP_Instagram::token() === 'IG-token-1'
    && SGAP_Instagram::status()['token_valid'] === true);

// --- refresh: the long-lived token is renewed in place ------------------------------
$GLOBALS['http_calls'] = [];
update_option('sgap_instagram_access_expires', time() + 2 * DAY_IN_SECONDS); // inside MARGIN
update_option('sgap_instagram_state', ['renew_ts' => 0]);
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, ['access_token' => 'IG-token-2', 'token_type' => 'bearer', 'expires_in' => 5183944]);
};
$renewed = SGAP_Instagram::refresh();
$refresh_url = (string) ($GLOBALS['http_calls'][0][1] ?? '');
check('instagram: the refresh is a GET to /refresh_access_token with grant_type=ig_refresh_token',
    ($GLOBALS['http_calls'][0][0] ?? '') === 'GET'
    && str_contains($refresh_url, 'graph.instagram.com/refresh_access_token')
    && str_contains($refresh_url, 'grant_type=ig_refresh_token')
    && str_contains($refresh_url, 'access_token=IG-token-1'));
check('instagram: the refreshed token replaces the saved one', SGAP_Instagram::saved_token() === 'IG-token-2'
    && !empty($renewed['access_token']));
check('instagram: the new expiry is stored and the token counts as fresh', (int) get_option('sgap_instagram_access_expires') > time() + 30 * DAY_IN_SECONDS
    && SGAP_Instagram::token() === 'IG-token-2');
check('instagram: the rotated value is stored without autoload', ($GLOBALS['autoload']['sgap_instagram_access_token'] ?? null) === false);
$throttled = SGAP_Instagram::refresh();
check('instagram: a second refresh inside the window is not sent', is_wp_error($throttled)
    && str_contains($throttled->get_error_message(), 'five minutes'));

// --- a refused refresh is reported, never swallowed ---------------------------------
update_option('sgap_instagram_state', ['renew_ts' => 0]);
SGAP_Instagram::$transport = function ($method, $url, $args) {
    return sgap_http(400, ['error' => ['message' => 'Session has been invalidated', 'type' => 'OAuthException', 'code' => 190]]);
};
$refused = SGAP_Instagram::refresh();
check('instagram: a refused refresh carries Instagram\'s own reason',
    is_wp_error($refused) && str_contains($refused->get_error_message(), 'Session has been invalidated')
    && (int) ($refused->data['status'] ?? 0) === 400);
check('instagram: the refusal reaches the daily self-check', str_contains(SGAP_Instagram::problem(), 'Instagram insights')
    && str_contains(SGAP_Instagram::problem(), 'Session has been invalidated'));

// --- media listing: fields, cursor paging, caption match ----------------------------
$GLOBALS['opts']['sgap_instagram_state'] = [];
$GLOBALS['opts']['sgap_instagram_access_expires'] = time() + 40 * DAY_IN_SECONDS;
$GLOBALS['opts']['sgap_instagram_media'] = [];
$GLOBALS['opts']['sgap_instagram_after'] = '';
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(200, [
        'data' => [
            ['id' => 'm1', 'caption' => 'New drop https://steamgears.eu/go/7/', 'permalink' => 'https://www.instagram.com/p/AAA/', 'timestamp' => '2026-11-01T10:00:00+0000', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED'],
            ['id' => 'm2', 'caption' => '#gears', 'permalink' => 'https://www.instagram.com/p/BBB/', 'timestamp' => '2026-11-02T10:00:00+0000', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED']
        ],
        'paging' => ['cursors' => ['after' => 'c2'], 'next' => 'https://graph.instagram.com/next']
    ]),
    sgap_http(200, [
        'data' => [
            ['id' => 'm3', 'caption' => 'steamgears.eu/go/8/', 'permalink' => 'https://www.instagram.com/p/CCC/', 'timestamp' => '2026-11-03T10:00:00+0000', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS']
        ],
        'paging' => ['cursors' => ['after' => 'c3']]
    ])
];
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$scan = SGAP_Instagram::scan();
$media_url = (string) ($GLOBALS['http_calls'][0][1] ?? '');
check('instagram: the media list asks for the six fields the match needs and a page size',
    str_contains($media_url, '17841405822304914/media') && str_contains($media_url, 'caption')
    && str_contains($media_url, 'permalink') && str_contains($media_url, 'media_product_type')
    && str_contains($media_url, 'limit=50'));
check('instagram: the list follows the cursor to the last page', is_array($scan)
    && $scan['pages'] === 2 && $scan['complete'] === true && $scan['media'] === 3);
check('instagram: the second page asks for the cursor Instagram returned',
    str_contains((string) ($GLOBALS['http_calls'][1][1] ?? ''), 'after=c2'));
check('instagram: the mapping remembers each caption\'s queue row',
    (SGAP_Instagram::mapping()['m1']['queue'] ?? 0) === 7 && (SGAP_Instagram::mapping()['m3']['queue'] ?? 0) === 8
    && (SGAP_Instagram::mapping()['m2']['queue'] ?? -1) === 0);
check('instagram: the cursor is cleared when the last page is in', get_option('sgap_instagram_after', '') === '');

// --- insights: five metrics, partial answers, and a narrower retry -------------------
update_option('sgap_instagram_access_expires', time() + 40 * DAY_IN_SECONDS);
$GLOBALS['http_calls'] = [];
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, ['data' => [
        ['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 240]]],
        ['name' => 'likes', 'period' => 'lifetime', 'values' => [['value' => 19]]],
        ['name' => 'comments', 'period' => 'lifetime', 'values' => [['value' => 3]]],
        ['name' => 'shares', 'period' => 'lifetime', 'values' => [['value' => 2]]],
        ['name' => 'saved', 'period' => 'lifetime', 'values' => [['value' => 6]]]
    ]]);
};
$counts = SGAP_Instagram::analytics('m1');
$insights_url = (string) ($GLOBALS['http_calls'][0][1] ?? '');
check('instagram: five documented metrics are asked for in one call',
    str_contains($insights_url, 'm1/insights') && str_contains($insights_url, 'metric=views%2Clikes%2Ccomments%2Cshares%2Csaved'));
check('instagram: the call is authorised with a Bearer token, never a query parameter',
    ($GLOBALS['http_calls'][0][2]['headers']['Authorization'] ?? '') === 'Bearer IG-token-2'
    && !str_contains($insights_url, 'IG-token-2'));
check('instagram: views, likes, comments, shares and saved are read from data[].values[0]',
    $counts === ['views' => 240, 'likes' => 19, 'comments' => 3, 'shares' => 2, 'saves' => 6]);
SGAP_Instagram::$transport = fn($method, $url, $args) => sgap_http(200, ['data' => [
    ['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 12]]]
]]);
$partial = SGAP_Instagram::analytics('m1');
check('instagram: a metric Instagram leaves out is missing, not zero', $partial === ['views' => 12]);
SGAP_Instagram::$transport = fn($method, $url, $args) => sgap_http(200, ['data' => []]);
check('instagram: an empty data set means "not reported yet", not five zeros', SGAP_Instagram::analytics('m1') === []);
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(400, ['error' => ['message' => 'Unsupported metric: saved', 'code' => 100]]),
    sgap_http(200, ['data' => [['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 5]]]]])
];
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$narrowed = SGAP_Instagram::analytics('m1');
check('instagram: a media type that refuses a metric is retried with the three every type has',
    $narrowed === ['views' => 5] && count($GLOBALS['http_calls']) === 2
    && str_contains((string) $GLOBALS['http_calls'][1][1], 'metric=views%2Clikes%2Ccomments'));

// --- a stale token is refreshed once and the call retried ---------------------------
$GLOBALS['opts']['sgap_instagram_state'] = [];
update_option('sgap_instagram_state', ['renew_ts' => 0]);
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(400, ['error' => ['message' => 'The access token could not be decrypted', 'code' => 190]]),
    sgap_http(200, ['access_token' => 'IG-token-3', 'expires_in' => 5183944]),
    sgap_http(200, ['data' => [['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 9]]]]])
];
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return $GLOBALS['http_queue'] ? array_shift($GLOBALS['http_queue']) : sgap_http(500, []);
};
$retried = SGAP_Instagram::analytics('m4');
check('instagram: a code-190 answer refreshes the token and retries the same call', $retried === ['views' => 9]
    && count($GLOBALS['http_calls']) === 3
    && str_contains((string) $GLOBALS['http_calls'][1][1], 'refresh_access_token')
    && ($GLOBALS['http_calls'][2][2]['headers']['Authorization'] ?? '') === 'Bearer IG-token-3');

// --- one run: match, spend the budget, store per channel ---------------------------
$GLOBALS['opts']['sgap_instagram_media'] = [];
$GLOBALS['opts']['sgap_instagram_after'] = '';
$GLOBALS['rows'] = [
    ['id' => '7', 'angle' => 'angle_a', 'published_at' => '2026-11-05 09:00:00', 'external_id' => ''],
    ['id' => '8', 'angle' => 'angle_b', 'published_at' => '2026-11-06 09:00:00', 'external_id' => '']
];
$GLOBALS['http_calls'] = [];
SGAP_Instagram::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    if (str_contains($url, '/media?')) {
        return sgap_http(200, ['data' => [
            ['id' => 'm1', 'caption' => 'https://steamgears.eu/go/7/', 'timestamp' => '2026-11-05T09:00:00+0000'],
            ['id' => 'm2', 'caption' => '#gears', 'timestamp' => '2026-11-05T10:00:00+0000'],
            ['id' => 'm3', 'caption' => 'steamgears.eu/go/8/', 'timestamp' => '2026-11-06T09:00:00+0000'],
            ['id' => 'm9', 'caption' => 'https://steamgears.eu/go/999/', 'timestamp' => '2026-11-04T09:00:00+0000']
        ], 'paging' => ['cursors' => ['after' => 'c9']]]);
    }
    if (str_contains($url, 'm1/insights')) {
        return sgap_http(200, ['data' => [
            ['name' => 'views', 'values' => [['value' => 100]]],
            ['name' => 'likes', 'values' => [['value' => 5]]],
            ['name' => 'comments', 'values' => [['value' => 2]]],
            ['name' => 'shares', 'values' => [['value' => 1]]],
            ['name' => 'saved', 'values' => [['value' => 3]]]
        ]]);
    }
    if (str_contains($url, 'm3/insights')) {
        return sgap_http(200, ['data' => []]); // Meta has not reported this one yet.
    }
    return sgap_http(403, ['error' => ['message' => 'Insufficient permissions', 'code' => 200]]);
};
$wpdb->log = [];
$report = SGAP_Instagram::sync(5);
$urls = array_map(static fn($call) => (string) $call[1], $GLOBALS['http_calls']);
$insights = array_values(array_filter($urls, static fn($u) => str_contains($u, '/insights')));
check('instagram: a run separates posts without a tracked link from posts whose row is gone',
    $report['unmapped'] === 1 && $report['orphaned'] === 1 && $report['matched'] === 2);
check('instagram: one insights call per matched post', count($insights) === 2);
check('instagram: a post with no numbers yet is counted as waiting, not stored as zero',
    $report['waiting'] === 1 && $report['updated'] === 1);
$delete = implode("\n", $wpdb->log);
check('instagram: the five types replace the old snapshot only for this channel',
    substr_count($delete, 'DELETE FROM wp_sgap_events WHERE queue_id') === 1
    && str_contains($delete, "channel = 'instagram'") && substr_count($delete, 'INSERT wp_sgap_events') === 5);
$before = count($GLOBALS['http_calls']);
SGAP_Instagram::sync(1);
$spent = array_filter(array_slice(array_map(static fn($call) => (string) $call[1], $GLOBALS['http_calls']), $before),
    static fn($u) => str_contains($u, '/insights'));
check('instagram: a run never spends more than its insights budget', count($spent) === 1);
check('instagram: the run is recorded with its counts for the dashboard',
    (int) (SGAP_Instagram::status()['report']['matched'] ?? 0) === 2 && SGAP_Instagram::state()['at'] !== '');

// --- reading it back: Instagram numbers never mix with the other channels ------------
// The fake $wpdb hands back whatever was put in $rows, so the fixture is already the
// filtered answer; the SQL itself is checked on the next line.
$GLOBALS['rows'] = [
    ['channel' => 'instagram', 'event_type' => 'views', 'total' => '150', 'rows_count' => '2'],
    ['channel' => 'instagram', 'event_type' => 'saves', 'total' => '6', 'rows_count' => '2']
];
$wpdb->log = [];
$summary = SGAP_Instagram::summary();
check('instagram: the summary sums its own five types',
    ($summary['instagram']['views'] ?? null) === 150.0 && ($summary['instagram']['saves'] ?? null) === 6.0
    && ($summary['instagram']['rows'] ?? null) === 4);
check('instagram: the summary query is limited to this channel and its five types',
    str_contains(str_replace(' ', '', implode("\n", $wpdb->log)), "channel='instagram'"));
ob_start();
SGAP_Instagram::dashboard_card();
$card = ob_get_clean();
check('instagram admin: the card shows views, likes, comments, shares and saves',
    str_contains($card, 'Instagram') && str_contains($card, 'Views') && str_contains($card, '>150</td>')
    && str_contains($card, 'Saves') && str_contains($card, '>6</td>'));
foreach ($instagram_options as $key) {
    unset($GLOBALS['opts'][$key]);
}
$GLOBALS['rows'] = [];
ob_start();
SGAP_Instagram::dashboard_card();
$empty_card = ob_get_clean();
check('instagram admin: an unconnected account is explained instead of shown as zero',
    str_contains($empty_card, 'not connected') && str_contains($empty_card, 'Settings → Keys')
    && !str_contains($empty_card, '<table'));

// --- the credentials have one home, labels and help ---------------------------------
check('instagram: the token is a secret', in_array('instagram_access_token', SGAP_Settings::SECRET_KEYS, true));
check('instagram: the token has its own keys card', isset(SGAP_Keys::PROVIDERS['instagram'])
    && SGAP_Keys::PROVIDERS['instagram']['keys'] === ['instagram_access_token']);
check('instagram: the account id and the host are ordinary settings with help text',
    in_array('instagram_user_id', SGAP_Settings::TEXT_KEYS, true)
    && isset(SGAP_Settings::HELP['instagram_user_id'])
    && isset(SGAP_Settings::HELP['instagram_graph_host'])
    && isset(SGAP_Settings::CHOICE_KEYS['instagram_graph_host']['graph.instagram.com'])
    && isset(SGAP_Settings::CHOICE_KEYS['instagram_graph_host']['graph.facebook.com']));
check('instagram: both settings sit on the Publishing tab and the token only on Keys',
    in_array('instagram_user_id', SGAP_Setup::TABS['publishing'][2], true)
    && in_array('instagram_graph_host', SGAP_Setup::TABS['publishing'][2], true)
    && in_array('instagram_access_token', SGAP_Setup::TABS['keys'][2], true)
    && !in_array('instagram_access_token', SGAP_Setup::TABS['publishing'][2], true));
check('instagram: the daily hook can be unscheduled on uninstall', str_contains(
    (string) file_get_contents(dirname(__DIR__) . '/steamgears-autopilot/uninstall.php'), 'sgap_instagram_refresh'));
check('plugin: 7.54.0 carries the Instagram module', SGAP_VERSION === '7.54.0' && class_exists('SGAP_Instagram'));

SGAP_Instagram::$transport = null;
$GLOBALS['http_calls'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['http_queue'] = [];
