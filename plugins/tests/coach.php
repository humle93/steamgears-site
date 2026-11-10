<?php
/**
 * P8 — the coach (includes/coach.php).
 *
 * The point of the module is that every number is measured on this site: clicks and orders per
 * channel and angle from sgap_events, the draft backlog from sgap_ai_drafts, and the lesson list
 * rebuilt from those two. These checks drive the numbers by hand through the wpdb stub, so no
 * network, no clock and no table is real — and a lesson that the data cannot support must not
 * appear.
 */

// ---- helpers ---------------------------------------------------------------
if (!function_exists('sgap_coach_row')) {
    /** One grouped row as SGAP_Coach::outcomes() gets it from MySQL. */
    function sgap_coach_row(string $channel, string $angle, int $published, int $clicks, int $orders, float $revenue, string $last = ''): object {
        return (object) [
            'channel' => $channel,
            'angle' => $angle,
            'published' => $published,
            'clicks' => $clicks,
            'orders' => $orders,
            'revenue' => $revenue,
            'last_publish' => $last
        ];
    }

    /** The stub answers table_exists() and COUNT(*) in this order: events is not touched here. */
    function sgap_coach_events(array $rows): void {
        global $wpdb;
        $GLOBALS['rows'] = $rows;
        $wpdb->next = ['wp_sgap_events'];
    }

    /** compose() reads facts first (drafts table + count), then the events. */
    function sgap_coach_brief_db(array $rows, string $pending = '0'): void {
        global $wpdb;
        $GLOBALS['rows'] = $rows;
        $wpdb->next = ['wp_sgap_ai_drafts', $pending, 'wp_sgap_events'];
    }

    /** learn() reads the events first, then facts. */
    function sgap_coach_learn_db(array $rows, string $pending = '0'): void {
        global $wpdb;
        $GLOBALS['rows'] = $rows;
        $wpdb->next = ['wp_sgap_events', 'wp_sgap_ai_drafts', $pending];
    }
}

$GLOBALS['filters'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['transient_store'] = true;
$GLOBALS['next_scheduled'] = [];
$GLOBALS['cron'] = [];
delete_option('sgap_agent_lessons');

// ---- 1) the site's facts ---------------------------------------------------
$GLOBALS['filters']['sgap_agent_facts'] = fn($facts) => ['products' => 20, 'currency' => 'DKK'];
$GLOBALS['rows'] = [];
$wpdb->next = ['wp_sgap_ai_drafts', '9'];
$facts = SGAP_Coach::facts();
check('coach: a filter can supply the catalogue facts without blanking the shape', $facts['products'] === 20 && $facts['currency'] === 'DKK' && array_key_exists('posts', $facts) && array_key_exists('newest_days', $facts));
check('coach: the draft backlog is counted from the table', $facts['pending_drafts'] === 9);
unset($GLOBALS['filters']['sgap_agent_facts']);

// ---- 2) outcomes are folded from one grouped read ---------------------------
sgap_coach_events([
    sgap_coach_row('facebook', 'angle_a', 3, 120, 2, 1480.0, '2026-10-20 12:00:00'),
    sgap_coach_row('facebook', 'angle_b', 0, 40, 0, 0.0, '2026-10-16 12:00:00'),
    sgap_coach_row('pinterest', 'angle_a', 2, 90, 0, 0.0, '2026-10-29 12:00:00')
]);
$outcomes = SGAP_Coach::outcomes();
check('coach: channel totals are folded across the channel\'s angles', $outcomes['channels']['facebook']['clicks'] === 160 && $outcomes['channels']['facebook']['orders'] === 2 && abs($outcomes['channels']['facebook']['revenue'] - 1480.0) < 0.01);
check('coach: the newest publish wins for the channel', $outcomes['channels']['facebook']['last_publish'] === '2026-10-20 12:00:00');
check('coach: silence is counted on the same clock the events are written with', $outcomes['channels']['facebook']['days_silent'] === 10 && $outcomes['channels']['pinterest']['days_silent'] === 1);
check('coach: angles are folded too', $outcomes['angles']['angle_a']['clicks'] === 210 && $outcomes['angles']['angle_a']['orders'] === 2);
check('coach: the window totals add up', $outcomes['totals']['published'] === 5 && $outcomes['totals']['clicks'] === 250 && $outcomes['totals']['orders'] === 2);
check('coach: the channel with orders comes first', array_key_first($outcomes['channels']) === 'facebook' && array_key_first($outcomes['angles']) === 'angle_a');
check('coach: a missing events table gives empty numbers, not a guess', (function () {
    global $wpdb;
    $GLOBALS['rows'] = [];
    $wpdb->next = ['something_else'];
    $o = SGAP_Coach::outcomes();
    return $o['channels'] === [] && $o['totals']['clicks'] === 0;
})());

// ---- 3) lessons are drawn only from data that supports them -----------------
// angle_a: 210 clicks and no order -> a lesson. instagram: 10 clicks -> below the floor.
sgap_coach_learn_db([
    sgap_coach_row('facebook', 'angle_a', 3, 120, 0, 0.0, '2026-10-20 12:00:00'),
    sgap_coach_row('pinterest', 'angle_a', 2, 90, 0, 0.0, '2026-10-29 12:00:00'),
    sgap_coach_row('instagram', 'angle_c', 1, 10, 0, 0.0, '2026-10-29 12:00:00')
]);
$learn = SGAP_Coach::learn();
$keys = array_column($learn['lessons'], 'key');
check('coach: an angle with clicks and no order becomes a lesson', in_array('angle_zero:angle_a', $keys, true));
check('coach: a channel with clicks and no order becomes a lesson', in_array('channel_zero:facebook', $keys, true) && in_array('channel_zero:pinterest', $keys, true));
check('coach: a channel below the click floor teaches nothing', !in_array('channel_zero:instagram', $keys, true));
check('coach: a lesson carries its own evidence', (function () use ($learn) {
    foreach ($learn['lessons'] as $lesson) {
        if ($lesson['key'] === 'angle_zero:angle_a') {
            return str_contains($lesson['evidence'], '210 clicks') && $lesson['at'] !== '';
        }
    }
    return false;
})());

// A channel that sells is a sales lesson, and its angle is worth reusing.
sgap_coach_learn_db([
    sgap_coach_row('facebook', 'angle_b', 2, 120, 3, 1750.0, '2026-10-28 12:00:00')
]);
$learn = SGAP_Coach::learn();
$keys = array_column($learn['lessons'], 'key');
check('coach: a converting channel is recorded as a sales lesson', in_array('channel_best:facebook', $keys, true) && in_array('pair_best:facebook', $keys, true));
check('coach: the sales lesson comes before the warnings', $keys[0] === 'channel_best:facebook' || $keys[0] === 'pair_best:facebook');

// A silent channel is named, because writing more for it will not help.
sgap_coach_learn_db([
    sgap_coach_row('bluesky', 'angle_a', 1, 3, 0, 0.0, '2026-10-01 12:00:00')
]);
$learn = SGAP_Coach::learn();
$keys = array_column($learn['lessons'], 'key');
check('coach: a channel silent past the limit is named', in_array('channel_silent:bluesky', $keys, true));
check('coach: the silent lesson says how long and when', (function () use ($learn) {
    foreach ($learn['lessons'] as $lesson) {
        if ($lesson['key'] === 'channel_silent:bluesky') {
            return str_contains($lesson['rule'], '29 days') && str_contains($lesson['evidence'], '2026-10-01');
        }
    }
    return false;
})());

// A review backlog is a lesson too: writing more while 25 wait is the wrong next step.
sgap_coach_learn_db([sgap_coach_row('facebook', 'angle_a', 1, 30, 1, 200.0, '2026-10-29 12:00:00')], '25');
$learn = SGAP_Coach::learn();
check('coach: a draft backlog is a lesson', in_array('drafts_backlog', array_column($learn['lessons'], 'key'), true));
check('coach: the backlog lesson counts the drafts', (function () use ($learn) {
    foreach ($learn['lessons'] as $lesson) {
        if ($lesson['key'] === 'drafts_backlog') {
            return str_contains($lesson['rule'], '25 drafts');
        }
    }
    return false;
})());

// ---- 4) the store is stable, capped and dated -------------------------------
delete_option('sgap_agent_lessons');
$healthy = [sgap_coach_row('facebook', 'angle_a', 1, 30, 1, 200.0, '2026-10-29 12:00:00')];
sgap_coach_learn_db($healthy);
$first = SGAP_Coach::learn();
sgap_coach_learn_db($healthy);
$second = SGAP_Coach::learn();
check('coach: unchanged numbers do not rewrite the store', $second['changed'] === false && $second['added'] === 0);
check('coach: the first run reports what it added', $first['added'] === count($first['lessons']) && $first['kept'] === 0);
check('coach: the lesson keeps the day it was first drawn', (function () use ($healthy) {
    $stored = get_option('sgap_agent_lessons', []);
    foreach ($stored as $i => $lesson) {
        $stored[$i]['at'] = '2026-01-01';
    }
    update_option('sgap_agent_lessons', $stored, false);
    sgap_coach_learn_db($healthy);
    SGAP_Coach::learn();
    foreach (get_option('sgap_agent_lessons', []) as $lesson) {
        if ($lesson['key'] === 'channel_best:facebook') {
            return $lesson['at'] === '2026-01-01';
        }
    }
    return false;
})());
check('coach: the store is capped', (function () {
    delete_option('sgap_agent_lessons');
    $rows = [];
    for ($i = 0; $i < 15; $i++) {
        $rows[] = sgap_coach_row('channel' . $i, 'angle_a', 1, 30, 0, 0.0, '');
    }
    sgap_coach_learn_db($rows);
    $learn = SGAP_Coach::learn();
    return $learn['total'] === SGAP_Coach::MAX_LESSONS;
})());
check('coach: a corrupt store reads as empty instead of crashing', (function () {
    update_option('sgap_agent_lessons', 'not-an-array', false);
    return SGAP_Coach::lessons() === [];
})());

// ---- 5) the brief ----------------------------------------------------------
$GLOBALS['filters'] = [];
sgap_coach_brief_db([
    sgap_coach_row('facebook', 'angle_a', 3, 120, 2, 1480.0, '2026-10-20 12:00:00'),
    sgap_coach_row('pinterest', 'angle_a', 2, 90, 0, 0.0, '2026-10-29 12:00:00')
]);
$brief = SGAP_Coach::brief(true);
check('coach: the brief says what it is and over which window', str_contains($brief, 'WHAT THIS SITE HAS MEASURED') && str_contains($brief, 'last 30 days'));
check('coach: the brief carries the measured channel numbers', str_contains($brief, 'facebook: 120 clicks, 2 orders, 1') && str_contains($brief, 'pinterest: 90 clicks, 0 orders'));
check('coach: the brief names the best and the weakest angle', str_contains($brief, 'best "angle_a"') && str_contains($brief, 'weakest "angle_a"') === false);
check('coach: the brief forbids numbers it does not contain', str_contains($brief, 'Do not invent metrics'));
check('coach: a site with nothing measured says so', (function () {
    global $wpdb;
    $GLOBALS['rows'] = [];
    $wpdb->next = ['wp_sgap_ai_drafts', '0', 'wp_sgap_events'];
    delete_option('sgap_agent_lessons');
    $text = SGAP_Coach::brief(true);
    return str_contains($text, 'no clicks or orders measured') && str_contains($text, 'Lessons: none yet');
})());
$GLOBALS['filters']['sgap_agent_brief'] = fn($value) => '  FIXED BRIEF  ';
check('coach: a filter replaces the brief and is trimmed', SGAP_Coach::brief(true) === 'FIXED BRIEF');
check('coach: the prompt block adds the blank lines around the brief', SGAP_Coach::block() === "\n\nFIXED BRIEF\n");
$GLOBALS['filters']['sgap_agent_brief'] = fn($value) => '';
check('coach: an empty brief adds nothing to a prompt', SGAP_Coach::block() === '');
unset($GLOBALS['filters']['sgap_agent_brief']);

// The cache exists so a generation run does not re-read the tables for every page.
set_transient('sgap_agent_brief', 'CACHED BRIEF', 60);
check('coach: a cached brief is used instead of new queries', SGAP_Coach::brief() === 'CACHED BRIEF');
delete_transient('sgap_agent_brief');

// ---- 6) every writer receives it ------------------------------------------
$GLOBALS['filters']['sgap_agent_brief'] = fn($value) => 'MEASURED-BRIEF';
$social = SGAP_AI::prompt(['title' => 'Brass goggles', 'type' => 'product', 'summary' => 'Hand-polished.'], ['facebook']);
check('coach: the daily writer and the campaign agent get the brief', str_contains($social, 'MEASURED-BRIEF'));
$article = SGAP_Studio::prompt(['type' => 'article', 'topic' => 'Steampunk goggles', 'keyword' => '', 'language' => 'English', 'length' => 'medium', 'notes' => ''], []);
check('coach: the Studio article writer gets the brief', str_contains($article, 'MEASURED-BRIEF'));
$page = new WP_Post(['ID' => 601, 'post_type' => 'post', 'post_title' => 'Goggles', 'post_content' => 'A short page about goggles.']);
check('coach: the SEO editor gets the brief', str_contains(SGAP_Studio::optimize_prompt($page), 'MEASURED-BRIEF'));
unset($GLOBALS['filters']['sgap_agent_brief']);

// ---- 7) the surfaces ------------------------------------------------------
sgap_coach_learn_db([sgap_coach_row('facebook', 'angle_a', 1, 30, 1, 200.0, '2026-10-29 12:00:00')]);
SGAP_Coach::learn();
$ability = SGAP_Abilities::agent_brief(['days' => 7]);
check('coach: the ability answers with the window, the facts and the lessons', $ability['window_days'] === 7 && isset($ability['facts']['products']) && is_array($ability['lessons']) && is_string($ability['brief']));
check('coach: the ability is registered as read-only and not destructive', (function () {
    SGAP_Abilities::register();
    $reg = $GLOBALS['abilities']['steamgears-autopilot/agent-brief'] ?? [];
    $notes = $reg['meta']['annotations'] ?? [];
    return !empty($notes['readonly']) && empty($notes['destructive']);
})());

sgap_coach_brief_db([sgap_coach_row('facebook', 'angle_a', 1, 30, 1, 200.0, '2026-10-29 12:00:00')]);
ob_start();
SGAP_Coach::card();
$html = ob_get_clean();
check('coach: the dashboard card explains where the list comes from', str_contains($html, 'What the agents have learned') && str_contains($html, 'rebuilt every day'));
check('coach: the card shows a lesson with its evidence', str_contains($html, 'channel_best:facebook') === false && str_contains($html, 'Lessons') && str_contains($html, 'since'));
check('coach: the card shows the brief that goes into the prompts', str_contains($html, 'The brief in the prompt') && str_contains($html, 'WHAT THIS SITE HAS MEASURED'));
delete_option('sgap_agent_lessons');
$GLOBALS['rows'] = [];
$wpdb->next = ['wp_sgap_ai_drafts', '0', 'wp_sgap_events'];
ob_start();
SGAP_Coach::card();
$empty = ob_get_clean();
check('coach: an empty store explains the click floor instead of showing nothing', str_contains($empty, 'Nothing measured yet') && str_contains($empty, '25'));

check('coach: the daily rebuild is scheduled once', (function () {
    $GLOBALS['cron'] = [];
    $GLOBALS['as']['recurring'] = [];
    $GLOBALS['as']['next'] = 0;
    $GLOBALS['next_scheduled'] = [];
    SGAP_Coach::schedule();
    $hooks = array_column($GLOBALS['as']['recurring'], 2);
    return in_array('sgap_coach_learn', $hooks, true) || in_array('sgap_coach_learn', $GLOBALS['cron'], true);
})());
check('coach: an existing daily rebuild is not scheduled twice', (function () {
    $before = count($GLOBALS['as']['recurring']);
    $GLOBALS['as']['next'] = 12345;
    SGAP_Coach::schedule();
    return count($GLOBALS['as']['recurring']) === $before;
})());

// ---- cleanup ---------------------------------------------------------------
$GLOBALS['filters'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['transient_store'] = false;
$GLOBALS['transients'] = [];
$wpdb->next = null;
delete_option('sgap_agent_lessons');
