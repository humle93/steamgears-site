<?php
/**
 * P11 checks (7.84.0): Publer is phased out channel by channel.
 *
 * Instagram and Pinterest publish through their module's $transport, so nothing leaves the machine.
 * Required from smoke.php, so $opts, $wpdb, $GLOBALS, sgap_http() and check() already exist.
 */

$GLOBALS['dx_calls'] = [];
$GLOBALS['dx_queue'] = [];
function dx_transport(): callable
{
    return function ($method, $url, $args) {
        $GLOBALS['dx_calls'][] = ['method' => $method, 'url' => $url, 'args' => $args];
        $next = $GLOBALS['dx_queue'] ? array_shift($GLOBALS['dx_queue']) : [200, []];
        return sgap_http($next[0], $next[1]);
    };
}
foreach (['instagram_direct', 'pinterest_direct', 'pinterest_board_id', 'instagram_user_id', 'instagram_access_token',
          'facebook_direct', 'publer_pinterest_board'] as $k) {
    unset($GLOBALS['opts']['sgap_' . $k]);
}
$GLOBALS['opts']['sgap_channels'] = 'facebook,instagram,pinterest';
$GLOBALS['opts']['sgap_publish_mode'] = 'rss';

// --- the split: nothing is direct until each channel is switched on ----------------------
check('p11: with no direct switch on, every channel still goes to Publer', SGAP_Direct::split() === ['direct' => [], 'publer' => ['facebook', 'instagram', 'pinterest']] && !SGAP_Direct::publer_free());
check('p11: the settings page offers the three new settings with help', in_array('instagram_direct', SGAP_Settings::SECTIONS['Publer'], true)
    && in_array('pinterest_direct', SGAP_Settings::SECTIONS['Publer'], true) && in_array('pinterest_board_id', SGAP_Settings::SECTIONS['Publer'], true)
    && isset(SGAP_Settings::HELP['instagram_direct'], SGAP_Settings::HELP['pinterest_direct'], SGAP_Settings::HELP['pinterest_board_id'])
    && isset(SGAP_Settings::CHOICE_KEYS['instagram_direct']['yes'], SGAP_Settings::CHOICE_KEYS['pinterest_direct']['yes']));

// --- Instagram ------------------------------------------------------------------------------
SGAP_Settings::set('instagram_user_id', '17841400000000000');
SGAP_Settings::set('instagram_access_token', 'IGAA-test-token');
check('p11 instagram: a connected account alone does not take publishing over', SGAP_Instagram::configured() && !SGAP_Instagram::handles('instagram'));
SGAP_Settings::set('instagram_direct', 'yes');
check('p11 instagram: with the switch on, instagram is posted directly', SGAP_Instagram::handles('instagram') && SGAP_Direct::handler('instagram') === 'SGAP_Instagram' && !SGAP_Instagram::handles('pinterest'));
SGAP_Instagram::$transport = dx_transport();
SGAP_Instagram::$poll_wait = 0;
$GLOBALS['dx_calls'] = [];
$GLOBALS['dx_queue'] = [[200, ['id' => 'c-1']], [200, ['status_code' => 'IN_PROGRESS']], [200, ['status_code' => 'FINISHED']], [200, ['id' => 'm-99']]];
$ig = SGAP_Instagram::publish(['id' => 301, 'channel' => 'instagram', 'title' => 'Goggles', 'body' => 'Brass goggles #steampunk', 'image_url' => 'https://steamgears.eu/g.jpg']);
$c = $GLOBALS['dx_calls'];
check('p11 instagram: container, status until FINISHED, then media_publish; the media id is the receipt', $ig === 'm-99' && count($c) === 4
    && $c[0]['method'] === 'POST' && str_ends_with($c[0]['url'], '/17841400000000000/media') && ($c[0]['args']['body']['image_url'] ?? '') === 'https://steamgears.eu/g.jpg'
    && ($c[0]['args']['body']['caption'] ?? '') === 'Brass goggles #steampunk'
    && $c[1]['method'] === 'GET' && str_contains($c[1]['url'], '/c-1') && $c[3]['method'] === 'POST'
    && str_ends_with($c[3]['url'], '/media_publish') && ($c[3]['args']['body']['creation_id'] ?? '') === 'c-1');
check('p11 instagram: the token goes in the header, never in the URL', ($c[0]['args']['headers']['Authorization'] ?? '') === 'Bearer IGAA-test-token' && !str_contains($c[0]['url'], 'IGAA-test-token'));
$GLOBALS['dx_queue'] = [[200, ['id' => 'c-2']], [200, ['status_code' => 'ERROR']]];
$bad = SGAP_Instagram::publish(['id' => 302, 'channel' => 'instagram', 'title' => 'x', 'body' => 'x', 'image_url' => 'https://steamgears.eu/g.jpg']);
check('p11 instagram: a container Instagram cannot process is an error, never published', is_wp_error($bad) && str_contains($bad->get_error_message(), 'ERROR'));
$GLOBALS['dx_calls'] = [];
$nophoto = SGAP_Instagram::publish(['id' => 303, 'channel' => 'instagram', 'title' => 'x', 'body' => 'x', 'image_url' => '']);
check('p11 instagram: no photo = refused by the preflight before any request', is_wp_error($nophoto) && $GLOBALS['dx_calls'] === []);
$GLOBALS['dx_queue'] = [[403, ['error' => ['message' => '(#10) Application does not have permission for this action', 'code' => 10]]]];
$perm = SGAP_Instagram::publish(['id' => 304, 'channel' => 'instagram', 'title' => 'x', 'body' => 'x', 'image_url' => 'https://steamgears.eu/g.jpg']);
check('p11 instagram: a missing publish permission is named', is_wp_error($perm) && str_contains($perm->get_error_message(), 'refused'));

// --- Pinterest ------------------------------------------------------------------------------
SGAP_Pinterest::$transport = dx_transport();
$GLOBALS['opts']['sgap_pinterest_access_token'] = 'pina-test';
$GLOBALS['opts']['sgap_pinterest_access_expires'] = time() + 30 * DAY_IN_SECONDS;
SGAP_Settings::set('pinterest_direct', 'yes');
check('p11 pinterest: without a board id it is not taken over (Publer keeps it)', SGAP_Pinterest::configured() && !SGAP_Pinterest::handles('pinterest'));
SGAP_Settings::set('pinterest_board_id', '987654321');
$pin_ready = SGAP_Pinterest::configured();
check('p11 pinterest: with token, board and the switch on, pins are created directly', $pin_ready && (SGAP_Pinterest::handles('pinterest') && SGAP_Direct::handler('pinterest') === 'SGAP_Pinterest'));
$GLOBALS['dx_calls'] = [];
$GLOBALS['dx_queue'] = [[201, ['id' => 'pin-42']]];
$pin = SGAP_Pinterest::publish(['id' => 401, 'channel' => 'pinterest', 'title' => '10 steampunk mad scientist costume ideas',
    'body' => str_repeat('Brass and leather. ', 40), 'image_url' => 'https://steamgears.eu/p.jpg']);
$sent = json_decode((string) ($GLOBALS['dx_calls'][0]['args']['body'] ?? '{}'), true);
check('p11 pinterest: one POST /v5/pins with board, title, capped text, alt, image and the tracking link', $pin === 'pin-42'
    && str_ends_with($GLOBALS['dx_calls'][0]['url'], '/v5/pins') && ($sent['board_id'] ?? '') === '987654321'
    && mb_strlen($sent['description'] ?? '') <= 500 && ($sent['media_source']['url'] ?? '') === 'https://steamgears.eu/p.jpg'
    && ($sent['media_source']['source_type'] ?? '') === 'image_url' && ($sent['alt_text'] ?? '') !== '' && str_contains((string) ($sent['link'] ?? ''), '/go/'));
$GLOBALS['dx_queue'] = [[401, ['code' => 3, 'message' => "Authentication failed. Missing: ['pins:write']"]]];
$scope = SGAP_Pinterest::publish(['id' => 402, 'channel' => 'pinterest', 'title' => 'x', 'body' => 'x', 'image_url' => 'https://steamgears.eu/p.jpg']);
check('p11 pinterest: a token without pins:write is named as a scope problem', is_wp_error($scope) && str_contains($scope->get_error_message(), 'pins:write'));
SGAP_Settings::set('pinterest_board_id', '');
$noboard = SGAP_Pinterest::publish(['id' => 403, 'channel' => 'pinterest', 'title' => 'x', 'body' => 'x', 'image_url' => 'https://steamgears.eu/p.jpg']);
check('p11 pinterest: no board id = clear error', is_wp_error($noboard) && str_contains($noboard->get_error_message(), 'board'));
SGAP_Settings::set('pinterest_board_id', '987654321');
check('p11 preflight: the direct board satisfies the board check (no Publer board needed)',
    !array_filter(SGAP_Preflight::problems('pinterest', ['title' => 'x', 'body' => 'x', 'image_url' => 'https://steamgears.eu/p.jpg']), fn($p) => $p['code'] === 'no_board'));

// --- the bookkeeping is the same as Publer's ------------------------------------------------
$wpdb->log = [];
$rep = ['sent' => [], 'failed' => []];
SGAP_Direct::finish(['id' => 501, 'channel' => 'instagram'], 'SGAP_Instagram', 'm-1', $rep);
SGAP_Direct::finish(['id' => 502, 'channel' => 'pinterest'], 'SGAP_Pinterest', new WP_Error('x', 'Pinterest answered HTTP 500'), $rep);
$log = implode("\n", $wpdb->log);
check('p11 finish: a success stores the platform id and is published at once; a failure gets the queue backoff',
    ($rep['sent'][0]['job_id'] ?? '') === 'm-1' && ($rep['sent'][0]['direct'] ?? '') === 'instagram'
    && str_contains($log, '"external_id":"m-1"') && ($rep['failed'][0]['error'] ?? '') === 'Pinterest answered HTTP 500');

// --- RSS mode: direct channels skip the feed --------------------------------------------------
$rss_src = (string) file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/rss.php');
check('p11 rss: the release posts a direct channel itself instead of putting it in the feed',
    strpos($rss_src, 'SGAP_Direct::handler($channel)') !== false
    && strpos($rss_src, 'SGAP_Direct::handler($channel)') < strpos($rss_src, "'external_id' => self::MARKER"));
$GLOBALS['rows'] = [];
$h = SGAP_RSS::health();
check('p11 rss: feed health marks direct channels as direct, not as empty feeds',
    ($h['instagram']['state'] ?? '') === 'direct' && ($h['facebook']['state'] ?? '') !== 'direct'
    && SGAP_RSS::health_summary($h)['direct'] >= 1);
check('p11 split: instagram (and pinterest when its token is valid) are direct, facebook still Publer', in_array('instagram', SGAP_Direct::split()['direct'], true)
    && in_array('facebook', SGAP_Direct::split()['publer'], true) && !SGAP_Direct::publer_free());

// clean up
foreach (['instagram_direct', 'pinterest_direct', 'pinterest_board_id', 'instagram_user_id', 'instagram_access_token', 'pinterest_access_token', 'pinterest_access_expires'] as $k) {
    unset($GLOBALS['opts']['sgap_' . $k]);
}
SGAP_Instagram::$transport = null;
SGAP_Pinterest::$transport = null;
SGAP_Instagram::$poll_wait = 2;
