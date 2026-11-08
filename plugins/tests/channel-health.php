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

$report = SGAP_Channel_Health::report();
check('channel-health: reports all six channels', count($report) === 6 && isset($report['pinterest'], $report['instagram'], $report['facebook']));

$shaped = true;
foreach ($report as $id => $row) {
    $shaped = $shaped
        && ($row['id'] ?? '') === $id
        && ($row['label'] ?? '') !== ''
        && in_array($row['state'] ?? '', ['ready', 'needs_refresh', 'missing_token', 'no_class'], true)
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
foreach (SGAP_Channel_Health::report() as $row) {
    $expected = SGAP_Channel_Health::classify($row['configured'], $row['refreshable']);
    $consistent = $consistent && $row['state'] === $expected;
}
check('channel-health: every row agrees with its own flags', $consistent);

// A channel with nothing stored must read as missing, and the summary must count it as such.
$opts['sgap_telegram_bot_token'] = '';
$opts['sgap_telegram_chat_id'] = '';
$telegram = SGAP_Channel_Health::channel('telegram');
check('channel-health: a cleared Telegram reads as missing', $telegram['state'] === 'missing_token' && $telegram['configured'] === false);

$summary = SGAP_Channel_Health::summary();
check('channel-health: the summary adds up to the report', $summary['total'] === 6
    && ($summary['ready'] + $summary['recoverable'] + $summary['missing']) === 6);

do_action('rest_api_init');
check('channel-health: /channel-health is registered under sgap/v1',
    in_array('/channel-health', $GLOBALS['routes']['sgap/v1'] ?? [], true));
