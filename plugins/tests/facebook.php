<?php
/**
 * P2.6 checks: the Facebook page — publishing, insights and moderation.
 *
 * Everything goes through SGAP_Facebook::$transport, so no request leaves the machine and
 * no real credential is involved. The Graph answers are shaped like the real ones, and the
 * asserts read what the plugin would store, not what it intended to store.
 *
 * Required from smoke.php, so $opts, $wpdb, $GLOBALS and check() already exist.
 */

foreach ([
    'sgap_facebook_page_id', 'sgap_facebook_access_token', 'sgap_facebook_graph_version',
    'sgap_facebook_direct', 'sgap_facebook_posts', 'sgap_facebook_page', 'sgap_facebook_state',
    'sgap_facebook_access_expires'
] as $key) {
    unset($GLOBALS['opts'][$key]);
}
unset($GLOBALS['opts']['sgap_error_log'], $GLOBALS['opts']['sgap_diagnostics_state']);
SGAP_Facebook::$transport = null;
$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [];
$GLOBALS['rows'] = [];
$wpdb->next = null;
$wpdb->row = null;

/** Answers in order: each queued [code, body] is handed to the next call. */
function fb_transport(array $queue): callable
{
    return function ($method, $url, $args) use ($queue) {
        $GLOBALS['fb_calls'][] = ['method' => $method, 'url' => $url, 'args' => $args];
        $next = $GLOBALS['fb_queue'] ? array_shift($GLOBALS['fb_queue']) : [200, []];
        return sgap_http($next[0], $next[1]);
    };
}

/** The last call's body, decoded, whether it went as a GET query or a POST body. */
function fb_args(): array
{
    $call = end($GLOBALS['fb_calls']);
    return is_array($call) ? (array) ($call['args']['body'] ?? $call['args']) : [];
}

// --- nothing configured: honest words, no request -------------------------------------
check('facebook: nothing is configured until a page id and a token exist', !SGAP_Facebook::configured());
check('facebook: an unconnected page raises no self-check problem', SGAP_Facebook::problem() === '');
check('facebook: an unconnected page does not take publishing over', !SGAP_Facebook::handles('facebook'));
$GLOBALS['fb_queue'] = [];
SGAP_Facebook::$transport = fb_transport([]);
SGAP_Facebook::run();
check('facebook: the cron is silent while nothing is configured', $GLOBALS['fb_calls'] === []);
$refused = SGAP_Facebook::publish(['id' => 5, 'channel' => 'facebook', 'body' => 'Hello', 'title' => 'x']);
check('facebook: publishing without a token explains itself', is_wp_error($refused) && str_contains($refused->get_error_message(), 'page id'));

// --- credentials and the two switches -------------------------------------------------
SGAP_Settings::set('facebook_page_id', '1234567890');
SGAP_Settings::set('facebook_access_token', 'EAAB-page-token');
check('facebook: a page id and a token make it configured', SGAP_Facebook::configured());
check('facebook: the token is read from the settings, not a constant', SGAP_Facebook::token() === 'EAAB-page-token');
SGAP_Settings::set('facebook_graph_version', 'graph.facebook.com');
check('facebook: a nonsense version falls back to v21.0', SGAP_Facebook::version() === 'v21.0');
SGAP_Settings::set('facebook_graph_version', 'v20.0');
check('facebook: a valid version is used as written', SGAP_Facebook::version() === 'v20.0');
SGAP_Settings::set('facebook_graph_version', 'v21.0');

check('facebook: connecting the page for reading does not move publishing', !SGAP_Facebook::handles('facebook'));
SGAP_Settings::set('facebook_direct', 'yes');
check('facebook: with direct publishing on, the channel is ours', SGAP_Facebook::handles('facebook'));
check('facebook: another channel is never ours', !SGAP_Facebook::handles('instagram') && !SGAP_Facebook::handles('pinterest'));
SGAP_Settings::set('facebook_direct', 'no');
SGAP_Settings::set('facebook_page_id', '');
SGAP_Settings::set('facebook_access_token', '');
check('facebook: an empty page id is not configured, even with a token', !SGAP_Facebook::configured());
SGAP_Settings::set('facebook_page_id', '1234567890');
SGAP_Settings::set('facebook_access_token', 'EAAB-page-token');

// --- publishing -----------------------------------------------------------------------
$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[200, ['id' => '1234567890_777']]];
SGAP_Facebook::$transport = fb_transport([]);
$post = SGAP_Facebook::publish(['id' => 77, 'channel' => 'facebook', 'body' => 'New gears in the shop', 'title' => 'Gears']);
check('facebook: a published post returns Meta\'s id', $post === '1234567890_777');
check('facebook: a text post goes to the feed endpoint', str_contains($GLOBALS['fb_calls'][0]['url'], '/v21.0/1234567890/feed'));
check('facebook: the Graph version is part of the URL', str_contains($GLOBALS['fb_calls'][0]['url'], '/v21.0/'));
check('facebook: the body is sent as the message', (fb_args()['message'] ?? '') === 'New gears in the shop');
check('facebook: the tracked link travels with the post', str_contains((string) (fb_args()['link'] ?? ''), '/go/77/'));
check('facebook: the post is mapped to its queue row', (SGAP_Facebook::mapping()['1234567890_777']['queue'] ?? 0) === 77);
check('facebook: a fresh mapping has not been measured yet', (SGAP_Facebook::mapping()['1234567890_777']['synced_at'] ?? -1) === 0);

$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[200, ['id' => 'photo-1', 'post_id' => '1234567890_888']]];
SGAP_Facebook::$transport = fb_transport([]);
$photo = SGAP_Facebook::publish(['id' => 88, 'channel' => 'facebook', 'body' => 'With a photo', 'title' => 'x', 'image_url' => 'https://steamgears.eu/a.jpg']);
check('facebook: a photo post returns the post id, not the photo id', $photo === '1234567890_888');
check('facebook: a photo post goes to the photos endpoint', str_contains($GLOBALS['fb_calls'][0]['url'], '/photos'));
check('facebook: the photo is sent as a url', (fb_args()['url'] ?? '') === 'https://steamgears.eu/a.jpg');
check('facebook: the caption carries the tracked link', str_contains((string) (fb_args()['caption'] ?? ''), '/go/88/'));
check('facebook: the photo post is mapped too', (SGAP_Facebook::mapping()['1234567890_888']['queue'] ?? 0) === 88);

$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[400, ['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]]]];
SGAP_Facebook::$transport = fb_transport([]);
$refused = SGAP_Facebook::publish(['id' => 99, 'channel' => 'facebook', 'body' => 'x', 'title' => 'x']);
check('facebook: a refused token is a token error, not a generic HTTP error', is_wp_error($refused) && $refused->get_error_code() === 'sgap_facebook_401');
check('facebook: the token error says a Page token is what writes', str_contains($refused->get_error_message(), 'Page access token'));
check('facebook: a failed publish is not mapped', !isset(SGAP_Facebook::mapping()['1234567890_999']));

$GLOBALS['fb_queue'] = [[200, ['id' => '']]];
SGAP_Facebook::$transport = fb_transport([]);
$half = SGAP_Facebook::publish(['id' => 100, 'channel' => 'facebook', 'body' => 'x', 'title' => 'x']);
check('facebook: an answer without an id is refused instead of stored as a post', is_wp_error($half));

$GLOBALS['fb_queue'] = [[400, ['error' => ['message' => '(#4) Application request limit reached', 'code' => 4]]]];
SGAP_Facebook::$transport = fb_transport([]);
$limited = SGAP_Facebook::publish(['id' => 101, 'channel' => 'facebook', 'body' => 'x', 'title' => 'x']);
check('facebook: a rate limit is its own error, so the next run retries', is_wp_error($limited) && $limited->get_error_code() === 'sgap_facebook_429');

// --- insights -------------------------------------------------------------------------
$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[200, ['data' => [
    ['name' => 'post_impressions', 'values' => [['value' => 120]]],
    ['name' => 'post_impressions_unique', 'values' => [['value' => 90]]],
    ['name' => 'post_reactions_by_type_total', 'values' => [['value' => ['LIKE' => 3, 'LOVE' => 2]]]],
    ['name' => 'post_clicks', 'values' => [['value' => 7]]],
]]]];
SGAP_Facebook::$transport = fb_transport([]);
$counts = SGAP_Facebook::analytics('1234567890_777');
check('facebook: impressions are read', ($counts['impressions'] ?? -1) === 120);
check('facebook: reach is the unique impressions', ($counts['reach'] ?? -1) === 90);
check('facebook: reactions are summed out of Meta\'s object', ($counts['reactions'] ?? -1) === 5);
check('facebook: post clicks are read', ($counts['post_clicks'] ?? -1) === 7);
check('facebook: the insights call asks for all four metrics', str_contains(urldecode($GLOBALS['fb_calls'][0]['url']), 'post_impressions,post_impressions_unique,post_reactions_by_type_total,post_clicks'));

$GLOBALS['fb_queue'] = [[200, ['data' => [['name' => 'post_impressions', 'values' => [['value' => 12]]]]]]];
SGAP_Facebook::$transport = fb_transport([]);
$partial = SGAP_Facebook::analytics('1234567890_777');
check('facebook: a metric Meta did not report is left out, not stored as 0', $partial === ['impressions' => 12]);

$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [
    [400, ['error' => ['message' => 'Invalid metric', 'code' => 100]]],
    [200, ['data' => [['name' => 'post_impressions', 'values' => [['value' => 33]]]]]],
];
SGAP_Facebook::$transport = fb_transport([]);
$narrow = SGAP_Facebook::analytics('1234567890_777');
check('facebook: a refused metric set is retried with the two every post has', ($narrow['impressions'] ?? -1) === 33 && count($GLOBALS['fb_calls']) === 2);
check('facebook: the retry asks for the narrow set', str_contains(urldecode($GLOBALS['fb_calls'][1]['url']), 'post_impressions,post_impressions_unique'));

// --- storing and reading the numbers --------------------------------------------------
$wpdb->log = [];
SGAP_Facebook::store(77, 'angle_a', ['impressions' => 120, 'reach' => 90, 'reactions' => 5, 'post_clicks' => 7]);
$sql = implode("\n", $wpdb->log);
check('facebook: a store deletes the old snapshot for the row', str_contains($sql, 'DELETE FROM wp_sgap_events') && str_contains($sql, 'queue_id = 77'));
check('facebook: the delete is scoped to the facebook channel', str_contains($sql, "channel = 'facebook'"));
check('facebook: every owned type is in the delete', str_contains($sql, "'impressions'") && str_contains($sql, "'post_clicks'"));
check('facebook: four events are written', substr_count($sql, 'INSERT wp_sgap_events') === 4);
check('facebook: the events carry the facebook channel', substr_count($sql, '"channel":"facebook"') === 4);
SGAP_Facebook::store(0, 'angle_a', ['impressions' => 1]);
check('facebook: a store without a queue row writes nothing', substr_count(implode("\n", $wpdb->log), 'INSERT') === 4);

$GLOBALS['rows'] = [
    ['channel' => 'facebook', 'event_type' => 'impressions', 'total' => '120', 'rows_count' => '2'],
    ['channel' => 'facebook', 'event_type' => 'reach', 'total' => '90', 'rows_count' => '2'],
];
$summary = SGAP_Facebook::summary();
check('facebook: the summary reads the channel totals', ($summary['facebook']['impressions'] ?? -1) === 120.0);
check('facebook: the summary counts the snapshots behind them', ($summary['facebook']['rows'] ?? -1) === 4);
check('facebook: a type with no rows is 0 in the summary, because that is a sum of nothing', ($summary['facebook']['post_clicks'] ?? -1) === 0.0);
$GLOBALS['rows'] = [];

// --- one run --------------------------------------------------------------------------
$GLOBALS['opts']['sgap_facebook_posts'] = ['1234567890_777' => ['queue' => 77, 'synced_at' => 0, 'at' => '2026-11-07 10:00:00']];
$wpdb->row = ['id' => 77, 'channel' => 'facebook', 'angle' => 'angle_a', 'body' => 'New gears', 'status' => 'published'];
$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [
    [200, ['name' => 'Steamgears', 'link' => 'https://facebook.com/steamgears', 'fan_count' => 812, 'followers_count' => 900]],
    [200, ['data' => [['name' => 'post_impressions', 'values' => [['value' => 50]]]]]],
];
SGAP_Facebook::$transport = fb_transport([]);
$report = SGAP_Facebook::sync();
check('facebook: a run reads the page first', str_contains(urldecode($GLOBALS['fb_calls'][0]['url']), 'fields=name,link,fan_count,followers_count'));
check('facebook: the page snapshot is stored, not thrown away', (SGAP_Facebook::page()['fans'] ?? null) === 812);
check('facebook: the run matches the mapped post to its queue row', $report['matched'] === 1);
check('facebook: the run stores what Meta reported', $report['updated'] === 1);
check('facebook: nothing is waiting when a metric came back', $report['waiting'] === 0);
check('facebook: the mapping remembers when it was read', (int) (SGAP_Facebook::mapping()['1234567890_777']['synced_at'] ?? 0) > 0);
check('facebook: a successful run clears the error', SGAP_Facebook::state()['ok'] === true && SGAP_Facebook::state()['error'] === '');

$GLOBALS['opts']['sgap_facebook_posts'] = ['1234567890_777' => ['queue' => 77, 'synced_at' => 0, 'at' => '2026-11-07 10:00:00']];
$GLOBALS['fb_queue'] = [
    [200, ['name' => 'Steamgears']],
    [200, ['data' => []]],
];
SGAP_Facebook::$transport = fb_transport([]);
$waiting = SGAP_Facebook::sync();
check('facebook: a post with no numbers yet is waiting, not a post with zeros', $waiting['waiting'] === 1 && $waiting['updated'] === 0);

$GLOBALS['opts']['sgap_facebook_posts'] = ['1234567890_777' => ['queue' => 77, 'synced_at' => 0, 'at' => '2026-11-07 10:00:00']];
$wpdb->row = null;
$GLOBALS['fb_queue'] = [[200, ['name' => 'Steamgears']]];
SGAP_Facebook::$transport = fb_transport([]);
$orphan = SGAP_Facebook::sync();
check('facebook: a post whose queue row is gone is counted as orphaned', $orphan['orphaned'] === 1 && $orphan['matched'] === 0);
$wpdb->row = ['id' => 77, 'channel' => 'facebook', 'angle' => 'angle_a', 'body' => 'New gears', 'status' => 'published'];

$GLOBALS['fb_queue'] = [
    [200, ['name' => 'Steamgears']],
    [400, ['error' => ['message' => 'Metric post_clicks is not supported', 'code' => 100]]],
    [500, ['error' => ['message' => 'Unknown error', 'code' => 1]]],
];
SGAP_Facebook::$transport = fb_transport([]);
$failed = SGAP_Facebook::sync();
check('facebook: a refused insights call is reported, not hidden', $failed['errors'] !== []);
check('facebook: a failed run is stored and logged', SGAP_Facebook::state()['ok'] === false && SGAP_Diagnostics::count_since(24) > 0);
check('facebook: the failure becomes a self-check line', str_contains(SGAP_Facebook::problem(), 'Facebook page:'));

// A good run after a bad one must clear the line, or the dashboard keeps crying wolf.
$GLOBALS['fb_queue'] = [
    [200, ['name' => 'Steamgears']],
    [200, ['data' => [['name' => 'post_impressions', 'values' => [['value' => 61]]]]]],
];
SGAP_Facebook::$transport = fb_transport([]);
SGAP_Facebook::sync();
check('facebook: a good run clears the error the bad one left', SGAP_Facebook::problem() === '');

// --- moderation -----------------------------------------------------------------------
$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[200, ['data' => [
    ['id' => 'c1', 'message' => 'Do you ship to Germany?', 'from' => ['name' => 'Anna'], 'created_time' => '2026-11-07T09:00:00+0000', 'like_count' => 2, 'can_hide' => true],
    ['id' => 'c2', 'message' => 'Spam', 'is_hidden' => true, 'can_hide' => false],
]]]];
SGAP_Facebook::$transport = fb_transport([]);
$comments = SGAP_Facebook::comments('1234567890_777');
check('facebook: the comments come back with their author', ($comments[0]['from'] ?? '') === 'Anna');
check('facebook: a comment knows whether it is hidden', ($comments[1]['hidden'] ?? false) === true);
check('facebook: Meta\'s can_hide is respected', ($comments[1]['can_hide'] ?? true) === false);
check('facebook: a comment with no can_hide field is treated as hideable', ($comments[0]['can_hide'] ?? false) === true);
check('facebook: the comments call asks for the fields the screen needs', str_contains(urldecode($GLOBALS['fb_calls'][0]['url']), 'from{name}'));

$GLOBALS['fb_queue'] = [[200, ['id' => 'r1']]];
SGAP_Facebook::$transport = fb_transport([]);
$reply = SGAP_Facebook::reply('c1', 'Yes, we ship to Germany.');
check('facebook: a reply returns its id', $reply === 'r1');
check('facebook: a reply goes to the comment, not the page', str_contains((string) (end($GLOBALS['fb_calls'])['url'] ?? ''), '/c1/comments'));
check('facebook: the reply text is what is sent', (fb_args()['message'] ?? '') === 'Yes, we ship to Germany.');
check('facebook: an empty reply is refused before a call', is_wp_error(SGAP_Facebook::reply('c1', '   ')));

$GLOBALS['fb_calls'] = [];
$GLOBALS['fb_queue'] = [[200, ['success' => true]]];
SGAP_Facebook::$transport = fb_transport([]);
$hidden = SGAP_Facebook::hide('c2', true);
check('facebook: hiding a comment succeeds', $hidden === true);
check('facebook: hiding posts is_hidden=true to the comment', (string) (fb_args()['is_hidden'] ?? '') === 'true');
$GLOBALS['fb_queue'] = [[200, ['success' => true]]];
SGAP_Facebook::hide('c2', false);
check('facebook: unhiding sends is_hidden=false', (string) (fb_args()['is_hidden'] ?? '') === 'false');
check('facebook: an empty comment id is refused before a call', is_wp_error(SGAP_Facebook::hide('', true)));

// --- status, card and REST ------------------------------------------------------------
$status = SGAP_Facebook::status();
check('facebook: the status names the page', $status['page_id'] === '1234567890');
check('facebook: the status says whether publishing is direct', $status['direct'] === false);
check('facebook: the status counts the mapped posts', $status['posts_mapped'] === 1);
check('facebook: a configured, but unread page reports no problem', $status['problem'] === '');
check('facebook: the caveat is written down', SGAP_Diagnostics::caveat('facebook') !== '');

ob_start();
SGAP_Facebook::dashboard_card();
$card = ob_get_clean();
check('facebook: the dashboard card shows the page', str_contains($card, 'Steamgears'));
check('facebook: the card carries the caveat', str_contains($card, 'Caveat:'));
check('facebook: the card links to the page screen', str_contains($card, 'sgap-facebook'));

$report = SGAP_Facebook::rest_refresh();
check('facebook: the REST refresh returns the run and the status', $report instanceof WP_REST_Response && isset($report->data['matched'], $report->data['status']));

// Leave the shared stubs as the next test file expects them.
SGAP_Facebook::$transport = null;
$GLOBALS['fb_queue'] = [];
$wpdb->row = null;
$GLOBALS['rows'] = [];
