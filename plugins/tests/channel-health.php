<?php
/**
 * P1.5 checks: the channel-health report separates "nothing pasted" from "ready to measure"
 * without any network call and without revealing a single key.
 *
 * Required from smoke.php, so $opts, check() and register_rest_route() already exist.
 */

require_once __DIR__ . '/../steamgears-autopilot/includes/channel-health.php';

check('channel-health: a configured channel is ready', SGAP_Channel_Health::classify(true, false) === 'ready');
check('channel-health: a refresh token alone is recoverable', SGAP_Channel_Health::classify(false, true) === 'needs_refresh');
check('channel-health: nothing stored is missing', SGAP_Channel_Health::classify(false, false) === 'missing_token');
check('channel-health: a refused token beats a stored one', SGAP_Channel_Health::classify(true, true, '2026-11-08 10:00:00') === 'refused');
check('channel-health: a token without the scope is needs_scope, not ready', SGAP_Channel_Health::classify(true, false, '', '2026-11-08 17:10:00') === 'needs_scope');

$report = SGAP_Channel_Health::report();
check('channel-health: reports all six channels', count($report) === 6 && isset($report['pinterest'], $report['instagram'], $report['facebook']));

$shaped = true;
foreach ($report as $id => $row) {
    $shaped = $shaped
        && ($row['id'] ?? '') === $id
        && ($row['label'] ?? '') !== ''
        && in_array($row['state'] ?? '', ['ready', 'needs_refresh', 'missing_token', 'refused', 'needs_scope', 'no_class'], true)
        && ($row['next'] ?? '') !== '';
}
check('channel-health: every row carries id, label, state and next step', $shaped);
check('channel-health: the report never carries the token itself', !str_contains(json_encode($report), 'access_token'));

// Pinterest is the channel with real state in the smoke harness: paste a token and it flips.
$opts['sgap_pinterest_access_token'] = 'smoke-token-value';
$opts['sgap_pinterest_access_expires'] = gmdate('c', time() + 86400);
$opts['sgap_pinterest_refresh_token'] = '';
$pinned = SGAP_Channel_Health::channel('pinterest');
check('channel-health: a stored Pinterest token reads as ready', $pinned['state'] === 'ready' && $pinned['configured'] === true);

// Whatever the harness left in the options, every row must agree with its own two flags.
$consistent = true;
foreach (SGAP_Channel_Health::report() as $row) {        $expected = SGAP_Channel_Health::classify($row['configured'], $row['refreshable'], $row['refused_at'] ?? '', $row['scope_at'] ?? '');
    $consistent = $consistent && $row['state'] === $expected;
}
check('channel-health: every row agrees with its own flags', $consistent);

// A stored token that the provider refuses is not ready: that was the 8/11 bug — the one
// channel the screen called ready answered 401 on every call.
$opts['sgap_pinterest_access_token'] = 'smoke-token-value';
$before = SGAP_Channel_Health::summary();
$opts['sgap_pinterest_refused'] = ['at' => '2026-11-08 15:44:00', 'code' => 401];
$refused = SGAP_Channel_Health::channel('pinterest');
check('channel-health: a refused Pinterest token reads as refused, not ready',
    $refused['state'] === 'refused' && ($refused['refused_at'] ?? '') === '2026-11-08 15:44:00');
check('channel-health: the refused row carries the action, not a guess',
    str_contains((string) $refused['next'], '401'));
$summary = SGAP_Channel_Health::summary();
check('channel-health: the summary counts a refused channel on its own line',
    $summary['refused'] === ($before['refused'] ?? 0) + 1
    && $summary['ready'] === $before['ready'] - 1
    && ($summary['ready'] + $summary['recoverable'] + $summary['refused'] + $summary['needs_scope'] + $summary['missing']) === 6);

// A valid token that Pinterest will not let us use is a fourth, distinct answer: the
// owner must reconnect the app with the scope, not paste the same token again.
SGAP_Pinterest::forget_refusal('smoke-token-value', 'a-fresh-token');
$opts['sgap_pinterest_scope'] = ['at' => '2026-11-08 17:12:00', 'scope' => 'boards:read'];
$scoped = SGAP_Channel_Health::channel('pinterest');
check('channel-health: a missing scope reads as needs_scope with the scope named',
    $scoped['state'] === 'needs_scope' && ($scoped['missing_scope'] ?? '') === 'boards:read'
    && str_contains((string) $scoped['next'], 'boards:read'));
check('channel-health: the scope verdict never contains the token',
    !str_contains(json_encode($scoped), 'smoke-token-value'));
unset($opts['sgap_pinterest_scope']);
SGAP_Pinterest::forget_refusal('smoke-token-value', 'a-fresh-token');
check('channel-health: a fresh token puts the channel back to ready',
    SGAP_Channel_Health::channel('pinterest')['state'] === 'ready');

// A channel with nothing stored must read as missing, and the summary must count it as such.
$opts['sgap_telegram_bot_token'] = '';
$opts['sgap_telegram_chat_id'] = '';
$telegram = SGAP_Channel_Health::channel('telegram');
check('channel-health: a cleared Telegram reads as missing', $telegram['state'] === 'missing_token' && $telegram['configured'] === false);

// Every state the summary knows must be in the sum: with refused and needs_scope left out,
// the line stopped adding up the moment a channel was anything but ready or empty.
$summary = SGAP_Channel_Health::summary();
check('channel-health: the summary adds up to the report', $summary['total'] === 6
    && ($summary['ready'] + $summary['recoverable'] + $summary['missing']
        + $summary['refused'] + $summary['needs_scope'] + $summary['broken']) === 6);

// Per-state, not just the total: a counter that folds two states together still sums to six.
$expected = array_fill_keys(['ready', 'recoverable', 'missing', 'refused', 'needs_scope', 'broken'], 0);
foreach (SGAP_Channel_Health::report() as $row) {
    $expected[SGAP_Channel_Health::STATES[$row['state']]]++;
}
$counted = [
    'ready' => $summary['ready'], 'recoverable' => $summary['recoverable'],
    'missing' => $summary['missing'], 'refused' => $summary['refused'],
    'needs_scope' => $summary['needs_scope'], 'broken' => $summary['broken'],
];
check('channel-health: every counter matches its own state in the report', $counted === $expected);
check('channel-health: a missing class is broken, not a missing token',
    SGAP_Channel_Health::STATES['no_class'] === 'broken'
    && SGAP_Channel_Health::STATES['missing_token'] === 'missing'
    && count(SGAP_Channel_Health::STATES) === 6);

do_action('rest_api_init');
check('channel-health: /channel-health is registered under sgap/v1',
    in_array('/channel-health', $GLOBALS['routes']['sgap/v1'] ?? [], true));

/* ---- P1.5's actual acceptance question: did the platform numbers land? ---- */

check('channel-health: every channel has a platform-type list',
    array_keys(SGAP_Channel_Health::PLATFORM_TYPES) === array_keys(SGAP_Channel_Health::CHANNELS));

$GLOBALS['rows'] = [];
$empty = SGAP_Channel_Health::analytics();
check('channel-health: nothing stored reads as empty, not as a measured zero',
    $empty['pinterest']['state'] === 'empty' && $empty['pinterest']['total'] === 0.0
    && $empty['pinterest']['last_at'] === null);
check('channel-health: Telegram reports no metric instead of a zero',
    $empty['telegram']['state'] === 'no_metric' && $empty['telegram']['types'] === []);

$GLOBALS['rows'] = [
    ['channel' => 'pinterest', 'event_type' => 'impressions', 'total' => '12', 'rows' => '1', 'last_at' => '2026-11-08 10:00:00'],
    ['channel' => 'pinterest', 'event_type' => 'click', 'total' => '9', 'rows' => '3', 'last_at' => '2026-11-08 11:00:00'],
    ['channel' => 'instagram', 'event_type' => 'likes', 'total' => '4', 'rows' => '2', 'last_at' => '2026-11-08 09:00:00'],
    ['channel' => 'bluesky', 'event_type' => 'likes', 'total' => '7', 'rows' => '1', 'last_at' => '2026-11-08 08:00:00'],
    ['channel' => 'mastodon', 'event_type' => 'reposts', 'total' => '3', 'rows' => '1', 'last_at' => '2026-11-07 20:00:00'],
];
$wpdb->log = [];
$measured = SGAP_Channel_Health::analytics();
check('channel-health: our own click rows are not a platform number',
    $measured['pinterest']['total'] === 12.0 && $measured['pinterest']['rows'] === 1);
check('channel-health: likes are counted per channel, not summed over four platforms',
    $measured['instagram']['counts']['likes'] === 4.0 && $measured['bluesky']['counts']['likes'] === 7.0
    && $measured['mastodon']['counts']['likes'] === 0.0);
check('channel-health: a measured channel carries its own last timestamp',
    $measured['pinterest']['last_at'] === '2026-11-08 10:00:00' && $measured['bluesky']['state'] === 'measured');
check('channel-health: the analytics query is windowed, not a full-table scan',
    str_contains(implode("\n", $wpdb->log), 'created_at >='));
$counted = SGAP_Channel_Health::analytics_summary($measured);
check('channel-health: the analytics summary counts states and rows',
    $counted['measured'] === 4 && $counted['empty'] === 1 && $counted['no_metric'] === 1
    && $counted['rows'] === 5 && $counted['total'] === 6);
check('channel-health: the window is bounded, and 90 days is the default',
    SGAP_Channel_Health::MIN_DAYS === 1 && SGAP_Channel_Health::MAX_DAYS === 365
    && SGAP_Channel_Health::WINDOW_DAYS === 90);
$wpdb->log = [];
SGAP_Channel_Health::analytics(100000);
check('channel-health: an oversized window is clamped instead of asked for',
    str_contains(implode("\n", $wpdb->log), "created_at >= '"));
$GLOBALS['rows'] = [];
