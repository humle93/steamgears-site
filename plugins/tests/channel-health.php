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
        + $summary['refused'] + $summary['needs_scope']) === 6);

do_action('rest_api_init');
check('channel-health: /channel-health is registered under sgap/v1',
    in_array('/channel-health', $GLOBALS['routes']['sgap/v1'] ?? [], true));
