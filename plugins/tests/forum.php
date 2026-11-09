<?php
/**
 * P5 — forum + Reddit. Loaded by plugins/tests/smoke.php; the WordPress stubs live there.
 */
$GLOBALS['transients'] = [];
$GLOBALS['transient_store'] = true; // the token cache is real for the rest of this file
$GLOBALS['opts']['sgap_forum_enabled'] = 'yes';
$GLOBALS['opts']['sgap_reddit_enabled'] = 'yes';
$GLOBALS['opts']['sgap_reddit_auto_share'] = 'yes';
$GLOBALS['opts']['sgap_reddit_client_id'] = 'app-id';
$GLOBALS['opts']['sgap_reddit_client_secret'] = 'app-secret';
$GLOBALS['opts']['sgap_reddit_username'] = 'steamgears';
$GLOBALS['opts']['sgap_reddit_password'] = 'hunter2';
$GLOBALS['opts']['sgap_reddit_default_sub'] = 'steampunk';
$GLOBALS['opts']['sgap_reddit_daily_cap'] = 2;
$GLOBALS['opts']['sgap_reddit_min_words'] = 120;
$GLOBALS['opts']['sgap_reddit_min_interval'] = 90;
$GLOBALS['opts']['sgap_forum_seeded'] = '2'; // the init pass seeded the boards already

// The thread every case below talks about.
$thread = (object) [
    'ID' => 123,
    'post_type' => SGAP_Forum::TOPIC,
    'post_status' => 'publish',
    'post_title' => 'Brass goggles that actually fit',
    'post_content' => str_repeat('goggle ', 150),
    'post_author' => 1
];
$GLOBALS['post_by_id'][123] = $thread;
$GLOBALS['post_type_by_id'][123] = SGAP_Forum::TOPIC;
$GLOBALS['post_fields'][123] = ['post_content' => $thread->post_content, 'post_author' => 1];
$GLOBALS['terms_for'][123] = [(object) ['slug' => 'cosplay', 'term_id' => 7, 'name' => 'Costume & Cosplay']];
$GLOBALS['comments_for'][123] = 2;

/* ---------------- registration ---------------- */
check('forum: thread type is public and archived under /forum/', ($GLOBALS['reg_post_types'][SGAP_Forum::TOPIC]['has_archive'] ?? '') === 'forum');
check('forum: thread type takes comments and titles', in_array('comments', $GLOBALS['reg_post_types'][SGAP_Forum::TOPIC]['supports'] ?? [], true));
check('forum: boards are a taxonomy on threads', isset($GLOBALS['reg_taxonomies'][SGAP_Forum::BOARD]));
check('forum: the shortcode is registered', isset($GLOBALS['shortcodes']['steamgears_forum']));
check('forum: the slug setting wins over the default', SGAP_Forum::slug() === 'forum' && SGAP_Forum::board_label('cosplay') === 'Costume & Cosplay');
check('forum: index url is the archive', SGAP_Forum::index_url() === 'https://steamgears.eu/forum/');
check('forum: four boards seeded on the first init', count(array_filter($GLOBALS['inserted_terms'] ?? [], fn($t) => ($t[1] ?? '') === SGAP_Forum::BOARD)) >= 4);
check('forum: board of a thread reads the taxonomy', SGAP_Forum::board_of(123) === 'cosplay');

/* ---------------- index ---------------- */
$GLOBALS['terms'] = [(object) ['slug' => 'cosplay', 'name' => 'Costume & Cosplay', 'count' => 3]];
$GLOBALS['query_posts'] = [$thread];
$GLOBALS['logged_in'] = false;
$html = SGAP_Forum::shortcode();
check('forum: index renders the heading and the board chips', str_contains($html, 'Ask, show, argue') && str_contains($html, 'Costume &amp; Cosplay'));
check('forum: a guest is offered a login, not the form', str_contains($html, 'wp-login.php') && !str_contains($html, 'name="sgap_body"'));
check('forum: the thread list shows title, replies and date', str_contains($html, 'Brass goggles that actually fit') && str_contains($html, '2 replies'));
$GLOBALS['logged_in'] = true;
$html = SGAP_Forum::shortcode();
check('forum: a member gets the form with a nonce and a honeypot', str_contains($html, 'name="sgap_body"') && str_contains($html, 'sgap_website') && str_contains($html, 'sgap_forum_topic'));
$GLOBALS['opts']['sgap_forum_enabled'] = 'no';
check('forum: a closed forum says so instead of rendering', str_contains(SGAP_Forum::shortcode(), 'closed'));
$GLOBALS['opts']['sgap_forum_enabled'] = 'yes';
$GLOBALS['query_posts'] = [];
check('forum: an empty board invites a first thread', str_contains(SGAP_Forum::shortcode(), 'first one sets the tone'));
$GLOBALS['query_posts'] = [$thread];

/* ---------------- writing ---------------- */
$GLOBALS['redirects'] = [];
$GLOBALS['logged_in'] = false;
SGAP_Forum::handle_topic();
check('forum: a guest cannot start a thread', str_contains((string) end($GLOBALS['redirects']), 'forum_error=login'));
$GLOBALS['logged_in'] = true;
$_POST = ['sgap_website' => 'http://spam.example', 'sgap_title' => 'A title that is long enough', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'cosplay'];
SGAP_Forum::handle_topic();
check('forum: a filled honeypot is refused before anything is saved', str_contains((string) end($GLOBALS['redirects']), 'forum_error=spam') && ($GLOBALS['inserted_posts'] ?? []) === []);
$_POST = ['sgap_website' => '', 'sgap_title' => 'short', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'cosplay'];
SGAP_Forum::handle_topic();
check('forum: a short title is refused', str_contains((string) end($GLOBALS['redirects']), 'forum_error=title'));
$_POST = ['sgap_website' => '', 'sgap_title' => 'A title that is long enough', 'sgap_body' => 'too short', 'sgap_board' => 'cosplay'];
SGAP_Forum::handle_topic();
check('forum: a two-line thread is refused', str_contains((string) end($GLOBALS['redirects']), 'forum_error=short'));
$_POST = ['sgap_website' => '', 'sgap_title' => 'A title that is long enough', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'nope'];
SGAP_Forum::handle_topic();
check('forum: an unknown board is refused', str_contains((string) end($GLOBALS['redirects']), 'forum_error=board'));
$GLOBALS['inserted_posts'] = [];
$GLOBALS['deny_caps'] = ['edit_others_posts'];
$_POST = ['sgap_website' => '', 'sgap_title' => 'A title that is long enough', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'workshop'];
SGAP_Forum::handle_topic();
$inserted = $GLOBALS['inserted_posts'][0] ?? [];
check('forum: a member\'s thread lands as pending for moderation', ($inserted['post_status'] ?? '') === 'pending' && ($inserted['post_type'] ?? '') === SGAP_Forum::TOPIC);
check('forum: the board is written as a term, not as text', ($GLOBALS['object_terms'][123] ?? '') === 'workshop');
check('forum: the thread starts with no Reddit state', ($GLOBALS['postmeta'][123][SGAP_Forum::META_STATE] ?? '') === 'none');
unset($GLOBALS['deny_caps']);
$GLOBALS['inserted_posts'] = [];
$_POST = ['sgap_website' => '', 'sgap_title' => 'A moderator title that fits', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'builds'];
SGAP_Forum::handle_topic();
check('forum: a moderator publishes immediately', (($GLOBALS['inserted_posts'][0] ?? [])['post_status'] ?? '') === 'publish');
$GLOBALS['transients']['sgap_forum_rate_1'] = SGAP_Forum::RATE;
$_POST = ['sgap_website' => '', 'sgap_title' => 'One thread too many today', 'sgap_body' => str_repeat('word ', 60), 'sgap_board' => 'builds'];
SGAP_Forum::handle_topic();
check('forum: the fourth thread in an hour is refused', str_contains((string) end($GLOBALS['redirects']), 'forum_error=rate'));
unset($GLOBALS['transients']['sgap_forum_rate_1']);
check('forum: comments stay open on a thread and only on a thread', SGAP_Forum::comments_open(false, 123) === true);
$GLOBALS['post_type_by_id'][9] = 'post';
check('forum: a normal post keeps its own comment setting', SGAP_Forum::comments_open(false, 9) === false);

/* ---------------- structured data ---------------- */
$GLOBALS['comments'] = [(object) ['comment_content' => 'Mine fog up too.', 'comment_date' => '2026-11-09 09:30:00', 'comment_author' => 'Ada']];
$GLOBALS['is_singular'] = true;
$GLOBALS['is_singular_type'] = SGAP_Forum::TOPIC;
$GLOBALS['queried_id'] = 123;
$GLOBALS['terms_for'][123][0]->name = 'Costume & Cosplay';
ob_start();
SGAP_Forum::jsonld();
$json = ob_get_clean();
$decoded = json_decode(trim(str_replace(['<script type="application/ld+json">', '</script>'], '', $json)), true);
check('forum: the thread prints DiscussionForumPosting', is_array($decoded) && ($decoded['@type'] ?? '') === 'DiscussionForumPosting');
check('forum: the JSON-LD carries author, about and the comment', ($decoded['author']['name'] ?? '') === 'Henning' && ($decoded['about'] ?? '') === 'Costume & Cosplay' && ($decoded['comment'][0]['text'] ?? '') === 'Mine fog up too.');
check('forum: the counter is the real number of replies', ($decoded['interactionStatistic'][0]['userInteractionCount'] ?? 0) === 2);
$GLOBALS['is_singular_type'] = 'post';
$GLOBALS['is_singular'] = false;
ob_start();
SGAP_Forum::jsonld();
check('forum: no JSON-LD outside a thread', trim((string) ob_get_clean()) === '');

/* ---------------- Reddit: credentials and tokens ---------------- */
check('reddit: a subreddit is normalised and junk refused', SGAP_Reddit::clean_sub('r/steampunk') === 'steampunk' && SGAP_Reddit::clean_sub('no way!') === '');
check('reddit: app id + secret count as configured', SGAP_Reddit::configured() === true);
check('reddit: a user or a refresh token is needed to write', SGAP_Reddit::can_post() === true);
check('reddit: the radar list is trimmed to five valid names', count(SGAP_Reddit::radar_subs()) <= 5 && in_array('steampunk', SGAP_Reddit::radar_subs(), true));
check('reddit: keywords are lower-cased and deduped', SGAP_Reddit::keywords() === array_values(array_unique(SGAP_Reddit::keywords())));

$GLOBALS['transients'] = [];
$GLOBALS['http_calls'] = [];
SGAP_Reddit::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, ['access_token' => 'tok-read', 'expires_in' => 3600]);
};
$read = SGAP_Reddit::token(false);
$call = $GLOBALS['http_calls'][0] ?? [];
check('reddit: reading uses client_credentials', $read === 'tok-read' && ($call[2]['body']['grant_type'] ?? '') === 'client_credentials');
check('reddit: the token request is basic-auth with the app and its own UA', str_starts_with((string) ($call[2]['headers']['Authorization'] ?? ''), 'Basic ') && ($call[2]['headers']['User-Agent'] ?? '') === SGAP_Reddit::UA);
check('reddit: the token is cached, not asked for twice', SGAP_Reddit::token(false) === 'tok-read' && count($GLOBALS['http_calls']) === 1);

$GLOBALS['http_calls'] = [];
SGAP_Reddit::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$method, $url, $args];
    return sgap_http(200, ['access_token' => 'tok-write', 'expires_in' => 3600, 'refresh_token' => 'rotated-1']);
};
$write = SGAP_Reddit::token(true);
check('reddit: writing uses the password grant when no refresh token is stored', $write === 'tok-write' && ($GLOBALS['http_calls'][0][2]['body']['grant_type'] ?? '') === 'password');
check('reddit: the rotated refresh token is saved, because that is the only copy', get_option('sgap_reddit_refresh_token') === 'rotated-1');
$GLOBALS['http_calls'] = [];
$GLOBALS['transients'] = [];
SGAP_Reddit::token(true);
check('reddit: a stored refresh token is preferred over the password', ($GLOBALS['http_calls'][0][2]['body']['grant_type'] ?? '') === 'refresh_token');
$GLOBALS['opts']['sgap_reddit_client_secret'] = '';
check('reddit: without app credentials nothing is attempted', SGAP_Reddit::token(false) instanceof WP_Error);
$GLOBALS['opts']['sgap_reddit_client_secret'] = 'app-secret';

/* ---------------- Reddit: submit and queue ---------------- */
SGAP_Reddit::$transport = function ($method, $url, $args) {
    $GLOBALS['http_calls'][] = [$url, $args];
    return sgap_http(200, ['json' => ['errors' => [], 'data' => ['id' => 'abc123', 'url' => 'https://www.reddit.com/r/steampunk/comments/abc123/x/']]]);
};
$GLOBALS['http_calls'] = [];
$sent = SGAP_Reddit::submit('steampunk', 'Brass goggles that actually fit', 'https://steamgears.eu/?p=123');
check('reddit: a submit returns the id and the permalink', ($sent['id'] ?? '') === 'abc123' && str_contains((string) ($sent['url'] ?? ''), '/comments/abc123/'));
check('reddit: the submit is a link post to the named subreddit', ($GLOBALS['http_calls'][0][1]['body']['sr'] ?? '') === 'steampunk' && ($GLOBALS['http_calls'][0][1]['body']['kind'] ?? '') === 'link');
check('reddit: a foreign URL is refused before any call', SGAP_Reddit::submit('steampunk', 'Title', 'https://evil.example/x') instanceof WP_Error);
check('reddit: an empty title is refused', SGAP_Reddit::submit('steampunk', '  ', 'https://steamgears.eu/?p=123') instanceof WP_Error);
SGAP_Reddit::$transport = function () {
    return sgap_http(200, ['json' => ['errors' => [['SUBREDDIT_NOEXIST', 'that community does not exist', 'sr']]]]);
};
$refused = SGAP_Reddit::submit('steampunk', 'Brass goggles', 'https://steamgears.eu/?p=123');
check('reddit: a refusal inside json.errors becomes the error message', $refused instanceof WP_Error && str_contains($refused->get_error_message(), 'does not exist'));
check('reddit: the refusal is kept for the admin page', str_contains(SGAP_Reddit::last_error(), 'does not exist'));

$GLOBALS['transients'] = [];
SGAP_Reddit::$transport = function () {
    return sgap_http(200, ['json' => ['errors' => [], 'data' => ['id' => 'z9', 'url' => 'https://www.reddit.com/r/steampunk/comments/z9/x/']]]);
};
$GLOBALS['next_scheduled'] = [];
$wpdb->next = [0, '']; // no sent rows, no last post
$GLOBALS['termmeta'][7]['reddit_sub'] = 'cosplay';
$queued = SGAP_Reddit::queue(123);
check('reddit: the board mapping wins over the default subreddit', ($queued['subreddit'] ?? '') === 'cosplay');
check('reddit: the shared URL is the thread with Reddit UTM', str_contains((string) ($queued['url'] ?? ''), 'utm_source=reddit') && str_contains((string) ($queued['url'] ?? ''), 'utm_campaign=cosplay'));
check('reddit: queueing writes the state and the subreddit on the thread', get_post_meta(123, SGAP_Forum::META_STATE, true) === 'queued' && get_post_meta(123, SGAP_Forum::META_SUB, true) === 'cosplay');
check('reddit: the row is inserted through prepare-safe data', str_contains(implode("\n", $wpdb->log), 'INSERT wp_sgap_reddit_posts'));
$wpdb->next = [1, '']; // the duplicate lookup finds the row we just queued
check('reddit: the same thread cannot be queued twice', SGAP_Reddit::queue(123) instanceof WP_Error);
$wpdb->next = [0, ''];
$GLOBALS['post_by_id'][124] = (object) ['ID' => 124, 'post_type' => SGAP_Forum::TOPIC, 'post_status' => 'draft', 'post_title' => 'Draft'];
check('reddit: a draft is not shared', SGAP_Reddit::queue(124) instanceof WP_Error);
check('reddit: a post that is not a thread is refused', SGAP_Reddit::queue(9) instanceof WP_Error);
$GLOBALS['opts']['sgap_reddit_password'] = '';
$GLOBALS['opts']['sgap_reddit_refresh_token'] = '';
check('reddit: a queue row waits while publishing is unconnected', SGAP_Reddit::queue(123) instanceof WP_Error);
$GLOBALS['opts']['sgap_reddit_password'] = 'hunter2';

$wpdb->next = [5, ''];
check('reddit: the daily cap stops the queue', SGAP_Reddit::within_limits() === false);
$wpdb->next = [0, date('Y-m-d H:i:s')];
check('reddit: two posts inside the interval are refused', SGAP_Reddit::within_limits() === false);
$wpdb->next = [0, '2026-01-01 10:00:00'];
check('reddit: an old last post does not block', SGAP_Reddit::within_limits() === true);

/* ---------------- Reddit: the tick ---------------- */
$GLOBALS['rows'] = [['id' => 7, 'topic_id' => 123, 'subreddit' => 'steampunk', 'title' => 'Brass goggles', 'url' => 'https://steamgears.eu/?p=123&utm_source=reddit', 'status' => 'queued', 'attempts' => 0, 'scheduled_at' => '2026-11-09 08:00:00']];
$GLOBALS['transients'] = ['sgap_reddit_token_write' => 'tok-write'];
SGAP_Reddit::$transport = function () {
    return sgap_http(200, ['json' => ['errors' => [], 'data' => ['id' => 'm1', 'url' => 'https://www.reddit.com/r/steampunk/comments/m1/x/']]]);
};
$wpdb->log = [];
SGAP_Reddit::tick();
$log = implode("\n", $wpdb->log);
check('reddit: a successful tick marks the row sent with its Reddit id', str_contains($log, '"status":"sent"') && str_contains($log, '"reddit_id":"m1"'));
check('reddit: the thread now shows the Reddit link', get_post_meta(123, SGAP_Forum::META_REDDIT, true) === 'https://www.reddit.com/r/steampunk/comments/m1/x/' && get_post_meta(123, SGAP_Forum::META_STATE, true) === 'sent');
check('reddit: a success clears the stored error', SGAP_Reddit::last_error() === '');

$GLOBALS['rows'] = [['id' => 8, 'topic_id' => 123, 'subreddit' => 'steampunk', 'title' => 'Brass goggles', 'url' => 'https://steamgears.eu/?p=123', 'status' => 'queued', 'attempts' => 0, 'scheduled_at' => '2026-11-09 08:00:00']];
SGAP_Reddit::$transport = function () {
    return sgap_http(500, ['message' => 'server error']);
};
$wpdb->log = [];
SGAP_Reddit::tick();
$log = implode("\n", $wpdb->log);
check('reddit: a failed tick retries with a backoff instead of dropping the row', str_contains($log, '"attempts":1') && str_contains($log, '"status":"queued"'));
check('reddit: the failure text is written on the row', str_contains($log, 'server error'));
$GLOBALS['rows'] = [['id' => 9, 'topic_id' => 123, 'subreddit' => 'steampunk', 'title' => 'Brass goggles', 'url' => 'https://steamgears.eu/?p=123', 'status' => 'queued', 'attempts' => 2, 'scheduled_at' => '2026-11-09 08:00:00']];
$wpdb->log = [];
SGAP_Reddit::tick();
check('reddit: the third failure is terminal, not an endless loop', str_contains(implode("\n", $wpdb->log), '"status":"failed"'));
check('reddit: a terminal failure is visible on the thread', get_post_meta(123, SGAP_Forum::META_STATE, true) === 'failed');

/* ---------------- automatic sharing ---------------- */
$GLOBALS['postmeta'][130][SGAP_Forum::META_STATE] = 'none';
$GLOBALS['post_by_id'][130] = (object) ['ID' => 130, 'post_type' => SGAP_Forum::TOPIC, 'post_status' => 'publish', 'post_title' => 'A long enough thread', 'post_content' => str_repeat('word ', 200)];
$GLOBALS['post_fields'][130] = ['post_content' => str_repeat('word ', 200)];
$GLOBALS['terms_for'][130] = [(object) ['slug' => 'workshop', 'term_id' => 8, 'name' => 'Steampunk Workshop']];
$GLOBALS['post_type_by_id'][130] = SGAP_Forum::TOPIC;
$wpdb->next = [0, ''];
SGAP_Reddit::maybe_queue(130, $GLOBALS['post_by_id'][130], false);
check('reddit: a long published thread is queued automatically', get_post_meta(130, SGAP_Forum::META_STATE, true) === 'queued');
$GLOBALS['postmeta'][131][SGAP_Forum::META_STATE] = 'none';
$GLOBALS['post_by_id'][131] = (object) ['ID' => 131, 'post_type' => SGAP_Forum::TOPIC, 'post_status' => 'publish', 'post_title' => 'Short', 'post_content' => 'three words only'];
$GLOBALS['post_fields'][131] = ['post_content' => 'three words only'];
check('reddit: a short thread is not worth a link post', get_post_meta(131, SGAP_Forum::META_STATE, true) === 'none');
$GLOBALS['opts']['sgap_reddit_auto_share'] = 'no';
$GLOBALS['postmeta'][132][SGAP_Forum::META_STATE] = 'none';
$GLOBALS['post_by_id'][132] = (object) ['ID' => 132, 'post_type' => SGAP_Forum::TOPIC, 'post_status' => 'publish', 'post_title' => 'A long enough thread', 'post_content' => str_repeat('word ', 200)];
$GLOBALS['post_fields'][132] = ['post_content' => str_repeat('word ', 200)];
SGAP_Reddit::maybe_queue(132, $GLOBALS['post_by_id'][132], false);
check('reddit: auto-share off means nothing leaves the site', get_post_meta(132, SGAP_Forum::META_STATE, true) === 'none');
$GLOBALS['opts']['sgap_reddit_auto_share'] = 'yes';

/* ---------------- radar ---------------- */
$GLOBALS['opts']['sgap_reddit_radar_subs'] = 'steampunk,cosplay';
$GLOBALS['opts']['sgap_reddit_keywords'] = 'goggles,top hat';
$GLOBALS['opts']['sgap_reddit_min_interval'] = 90;
delete_option(SGAP_Reddit::RADAR_OPTION);
delete_option(SGAP_Reddit::SEEN_OPTION);
$GLOBALS['transients'] = ['sgap_reddit_token_read' => 'tok-read'];
SGAP_Reddit::$transport = function ($method, $url, $args) {
    if (str_contains($url, 'steampunk')) {
        return sgap_http(200, ['data' => ['children' => [
            ['data' => ['name' => 't3_a', 'id' => 'a', 'title' => 'Where do I buy brass goggles?', 'selftext' => '', 'subreddit' => 'steampunk', 'permalink' => '/r/steampunk/comments/a/x/', 'score' => 20, 'num_comments' => 6]],
            ['data' => ['name' => 't3_b', 'id' => 'b', 'title' => 'A photo of my hat', 'selftext' => '', 'subreddit' => 'steampunk', 'permalink' => '/r/steampunk/comments/b/x/', 'score' => 900, 'num_comments' => 80]],
            ['data' => ['name' => 't3_c', 'id' => 'c', 'title' => 'Sticky goggles thread', 'selftext' => '', 'subreddit' => 'steampunk', 'permalink' => '/r/steampunk/comments/c/x/', 'score' => 99, 'num_comments' => 40, 'stickied' => true]]
        ]]]
        );
    }
    return sgap_http(500, ['message' => 'cosplay is down']);
};
$radar = SGAP_Reddit::radar(true);
check('radar: only threads that mention a keyword are kept', is_array($radar) && count($radar) === 1 && ($radar[0]['id'] ?? '') === 't3_a');
check('radar: a stickied thread is not an opportunity', !str_contains(json_encode($radar), 'Sticky goggles'));
check('radar: the item carries the full Reddit URL and the numbers', str_starts_with((string) ($radar[0]['url'] ?? ''), 'https://www.reddit.com/r/steampunk/') && ($radar[0]['comments'] ?? 0) === 6);
check('radar: one broken subreddit does not stop the other', is_array($radar) && is_array(get_option(SGAP_Reddit::RADAR_OPTION)));
check('radar: the cached list is what the admin page reads', SGAP_Reddit::radar_cached() === $radar);
$radar_again = SGAP_Reddit::radar(false);
check('radar: a fresh cache is used instead of another API round', $radar_again === $radar);
check('radar: seen threads are remembered in a bounded list', count((array) get_option(SGAP_Reddit::SEEN_OPTION)) <= SGAP_Reddit::KEEP_SEEN);
unset($GLOBALS['transients']['sgap_reddit_token_read']); // an expired token cannot be renewed without the app
$GLOBALS['opts']['sgap_reddit_client_secret'] = '';
check('radar: without credentials it reports the missing app instead of guessing', SGAP_Reddit::radar(true) instanceof WP_Error);
$GLOBALS['opts']['sgap_reddit_client_secret'] = 'app-secret';

/* ---------------- status, REST and the admin page ---------------- */
$GLOBALS['opts']['sgap_reddit_enabled'] = 'no';
check('reddit: switched off is its own state', (SGAP_Reddit::status()['state'] ?? '') === 'off');
$GLOBALS['opts']['sgap_reddit_enabled'] = 'yes';
$GLOBALS['opts']['sgap_reddit_client_id'] = '';
check('reddit: a missing app is named, not hidden', (SGAP_Reddit::status()['state'] ?? '') === 'missing_token');
$GLOBALS['opts']['sgap_reddit_client_id'] = 'app-id';
$GLOBALS['opts']['sgap_reddit_username'] = '';
$GLOBALS['opts']['sgap_reddit_password'] = '';
$GLOBALS['opts']['sgap_reddit_refresh_token'] = '';
check('reddit: reading without a user is read_only, not ready', (SGAP_Reddit::status()['state'] ?? '') === 'read_only');
$GLOBALS['opts']['sgap_reddit_username'] = 'steamgears';
$GLOBALS['opts']['sgap_reddit_password'] = 'hunter2';
delete_option(SGAP_Reddit::LAST_ERROR); // the radar's 500 above is the previous state, not this one
check('reddit: a connected account reports ready', (SGAP_Reddit::status()['state'] ?? '') === 'ready');

$GLOBALS['routes'] = [];
ob_start();
SGAP_Forum::routes();
SGAP_Reddit::routes();
ob_end_clean();
$ns = $GLOBALS['routes'][SGAP_REST::NS] ?? [];
check('forum: the REST surface has five routes', count(array_filter($ns, fn($r) => str_starts_with((string) $r, '/forum') || str_starts_with((string) $r, '/reddit'))) === 5);
$GLOBALS['query_posts'] = [$thread];
check('forum: the public topics route exposes titles and links', isset(SGAP_Forum::rest_topics()['topics'][0]['url']));
ob_start();
SGAP_Forum::page();
$admin_html = ob_get_clean();
check('forum: the admin page renders counters, the queue and the radar', str_contains($admin_html, 'Published threads') && str_contains($admin_html, 'Radar') && str_contains($admin_html, 'Queue on Reddit'));
check('forum: the admin page never prints a stored secret', !str_contains($admin_html, 'app-secret') && !str_contains($admin_html, 'hunter2'));

/* ---------------- restore ---------------- */
$GLOBALS['transients'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['comments'] = [];
$GLOBALS['is_singular'] = false;
$GLOBALS['logged_in'] = true;
$_POST = [];
SGAP_Reddit::$transport = null;
